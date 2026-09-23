<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function phase28CatalogItem(string $catalogCode, string $itemCode, string $description): SunatCatalogItem
{
    $catalog = SunatCatalog::query()->firstOrCreate(
        ['code' => $catalogCode],
        ['name' => 'CATÁLOGO '.$catalogCode, 'source' => 'test', 'is_active' => true]
    );

    return SunatCatalogItem::query()->firstOrCreate(
        ['catalog_code' => $catalogCode, 'item_code' => $itemCode],
        [
            'sunat_catalog_id' => $catalog->id,
            'description' => $description,
            'source' => 'test',
            'is_official' => true,
            'status' => 'ACTIVE',
        ]
    );
}

function phase28Fixture(): array
{
    $type05 = phase28CatalogItem('05', '01', 'MERCADERÍAS');
    $niu = phase28CatalogItem('06', 'NIU', 'UNIDAD (BIENES)');
    $other13 = phase28CatalogItem('13', '9', 'OTROS');
    $weighted14 = phase28CatalogItem('14', '1', 'PROMEDIO PONDERADO');

    $unit = Unit::create([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'decimal_quantity' => false,
        'status' => 'ACTIVE',
    ]);
    $category = Category::create([
        'code' => 'CAT-F28',
        'description' => 'CATEGORÍA FASE 28',
        'type' => 'ARTICLE',
        'status' => 'ACTIVE',
    ]);
    $article = Article::create([
        'code' => 'ARTF28001',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO FASE 28',
        'billing_name' => 'ARTÍCULO FASE 28',
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        'sunat_existence_type_item_id' => $type05->id,
        'status' => 'ACTIVE',
        'has_batch' => false,
        'has_expiration' => false,
    ]);
    $company = Company::create([
        'business_name' => 'EMPRESA FASE 28',
        'ruc' => '20999999028',
        'status' => true,
    ]);
    $warehouse = Warehouse::create([
        'code' => 'ALM-F28',
        'name' => 'ALMACÉN FASE 28',
        'status' => 'ACTIVE',
    ]);

    $stockId = DB::table('warehouse_stocks')->insertGetId([
        'stock_key' => $company->id.'|'.$warehouse->id.'|'.$article->id.'|SIN_LOTE|SIN_FECHA',
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'article_id' => $article->id,
        'unit_id' => $unit->id,
        'current_quantity' => 5,
        'reserved_quantity' => 0,
        'average_unit_cost' => 20,
        'total_cost' => 100,
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return compact('type05', 'niu', 'other13', 'weighted14', 'unit', 'article', 'company', 'warehouse', 'stockId');
}

it('ejecuta la auditoría integral en dry-run sin modificar mappings costos ni cantidades', function () {
    $fixture = phase28Fixture();
    $stockBefore = DB::table('warehouse_stocks')->where('id', $fixture['stockId'])->first();

    $exit = Artisan::call('sunat:audit-master-data');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('FASE 2.8')
        ->toContain('Modo DRY-RUN')
        ->toContain('SUNAT Tabla 06')
        ->toContain('SUNAT Tabla 13')
        ->toContain('SUNAT Tabla 14')
        ->and($fixture['unit']->fresh()->sunat_unit_item_id)->toBeNull()
        ->and($fixture['article']->fresh()->sunat_inventory_catalog_item_id)->toBeNull()
        ->and($fixture['company']->fresh()->inventory_valuation_method_item_id)->toBeNull()
        ->and(DB::table('warehouse_stocks')->where('id', $fixture['stockId'])->first())->toEqual($stockBefore);
});

it('apply-safe completa solo mappings inequívocos sin revalorizar stock', function () {
    $fixture = phase28Fixture();
    $stockBefore = DB::table('warehouse_stocks')->where('id', $fixture['stockId'])->first();

    $exit = Artisan::call('sunat:audit-master-data', ['--apply-safe' => true]);

    expect($exit)->toBe(0)
        ->and((int) $fixture['unit']->fresh()->sunat_unit_item_id)->toBe($fixture['niu']->id)
        ->and((int) $fixture['article']->fresh()->sunat_inventory_catalog_item_id)->toBe($fixture['other13']->id)
        ->and($fixture['article']->fresh()->sunat_inventory_catalog_code)->toBe($fixture['article']->code)
        ->and((int) $fixture['company']->fresh()->inventory_valuation_method_item_id)->toBe($fixture['weighted14']->id)
        ->and(DB::table('warehouse_stocks')->where('id', $fixture['stockId'])->first())->toEqual($stockBefore)
        ->and(Artisan::output())->toContain('Tabla 05 y códigos de establecimiento SUNAT permanecen sin autoaplicar.');
});
