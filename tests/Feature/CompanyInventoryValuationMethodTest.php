<?php

use App\Models\Company;
use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use App\Models\WarehouseStock;
use App\Services\CompanyInventoryValuationAuditService;
use App\Services\CompanyInventoryValuationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function valuationMethodItem(string $code = '1', string $description = 'PROMEDIO PONDERADO'): SunatCatalogItem
{
    $catalog = SunatCatalog::query()->updateOrCreate(
        ['code' => '14'],
        ['name' => 'MÉTODO DE VALUACIÓN', 'source' => 'test', 'is_active' => true]
    );

    return SunatCatalogItem::query()->updateOrCreate(
        ['catalog_code' => '14', 'item_code' => $code],
        [
            'sunat_catalog_id' => $catalog->id,
            'description' => $description,
            'source' => 'test',
            'is_official' => true,
            'status' => 'ACTIVE',
        ]
    );
}

function valuationCompany(string $suffix = 'A'): Company
{
    return Company::create([
        'business_name' => 'EMPRESA VALUACIÓN '.$suffix,
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
}

function valuationStockForCompany(Company $company, string $suffix = 'A'): WarehouseStock
{
    $now = now();
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'ALM-VAL-'.$suffix,
        'name' => 'ALMACÉN VAL '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'UV'.$suffix,
        'description' => 'UNIDAD VAL '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA VAL '.$suffix,
        'code' => 'CV'.$suffix,
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => 'AV'.$suffix,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO VAL '.$suffix,
        'billing_name' => 'ARTÍCULO VAL '.$suffix,
        'has_batch' => false,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return WarehouseStock::create([
        'stock_key' => $company->id.'|'.$warehouseId.'|'.$articleId.'|SIN_LOTE|SIN_FECHA',
        'company_id' => $company->id,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'current_quantity' => 10,
        'reserved_quantity' => 0,
        'average_unit_cost' => 5,
        'total_cost' => 50,
        'status' => 'ACTIVE',
    ]);
}

it('acepta exclusivamente el método 1 de la Tabla 14 mientras el motor actual usa promedio ponderado', function () {
    $weightedAverage = valuationMethodItem();
    $fifo = valuationMethodItem('2', 'PRIMERAS ENTRADAS, PRIMERAS SALIDAS');
    $policy = app(CompanyInventoryValuationPolicy::class);

    expect($policy->resolveSupportedMethod($weightedAverage->id)->item_code)->toBe('1')
        ->and(fn () => $policy->resolveSupportedMethod($fifo->id))
        ->toThrow(ValidationException::class);
});

it('permite la primera configuración legacy pero bloquea quitar o cambiar el método con historial', function () {
    $weightedAverage = valuationMethodItem();
    $company = valuationCompany('HIST');
    valuationStockForCompany($company, 'HIST');
    $policy = app(CompanyInventoryValuationPolicy::class);

    expect(fn () => $policy->assertCanAssign($company, $weightedAverage->id))->not->toThrow(ValidationException::class);

    $company->update(['inventory_valuation_method_item_id' => $weightedAverage->id]);

    expect(fn () => $policy->assertCanAssign($company->fresh(), null))
        ->toThrow(ValidationException::class);
});

it('el dry-run propone promedio ponderado sin modificar empresa stock ni costo', function () {
    $weightedAverage = valuationMethodItem();
    $company = valuationCompany('DRY');
    $stock = valuationStockForCompany($company, 'DRY');

    $before = $stock->only(['current_quantity', 'average_unit_cost', 'total_cost']);
    $report = app(CompanyInventoryValuationAuditService::class)->audit();

    expect($report['safe_candidates'])->toBeGreaterThanOrEqual(1)
        ->and($company->fresh()->inventory_valuation_method_item_id)->toBeNull()
        ->and($stock->fresh()->only(['current_quantity', 'average_unit_cost', 'total_cost']))->toBe($before)
        ->and($weightedAverage->item_code)->toBe('1');
});

it('apply configura solo la empresa candidata y es idempotente sin revalorizar stock', function () {
    $weightedAverage = valuationMethodItem();
    $company = valuationCompany('APPLY');
    $stock = valuationStockForCompany($company, 'APPLY');
    $before = $stock->only(['current_quantity', 'average_unit_cost', 'total_cost']);
    $service = app(CompanyInventoryValuationAuditService::class);

    $first = $service->apply();
    $second = $service->apply();

    expect($company->fresh()->inventory_valuation_method_item_id)->toBe($weightedAverage->id)
        ->and($stock->fresh()->only(['current_quantity', 'average_unit_cost', 'total_cost']))->toBe($before)
        ->and($first['applied'])->toBeGreaterThanOrEqual(1)
        ->and($second['applied'])->toBe(0);
});
