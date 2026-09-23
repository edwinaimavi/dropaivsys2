<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\UnitSunatAuditService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutMiddleware();
    $catalog06 = SunatCatalog::create(['code' => '06', 'name' => 'UNIDADES', 'is_active' => true]);
    $this->niu = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog06->id, 'catalog_code' => '06', 'item_code' => 'NIU',
        'description' => 'UNIDAD (BIENES)', 'status' => 'ACTIVE',
    ]);
    $this->kgm = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog06->id, 'catalog_code' => '06', 'item_code' => 'KGM',
        'description' => 'KILOGRAMO', 'status' => 'ACTIVE',
    ]);
    $this->grm = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog06->id, 'catalog_code' => '06', 'item_code' => 'GRM',
        'description' => 'GRAMO', 'status' => 'ACTIVE',
    ]);
    $this->bx = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog06->id, 'catalog_code' => '06', 'item_code' => 'BX',
        'description' => 'CAJA', 'status' => 'ACTIVE',
    ]);
    $this->inactive = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog06->id, 'catalog_code' => '06', 'item_code' => 'MLT',
        'description' => 'MILILITRO', 'status' => 'INACTIVE',
    ]);
    $catalog05 = SunatCatalog::create(['code' => '05', 'name' => 'EXISTENCIAS', 'is_active' => true]);
    $this->wrongCatalog = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog05->id, 'catalog_code' => '05', 'item_code' => '01',
        'description' => 'MERCADERÍAS', 'status' => 'ACTIVE',
    ]);
    $this->wrongBx = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog05->id, 'catalog_code' => '05', 'item_code' => 'BX',
        'description' => 'CAJA INCORRECTA', 'status' => 'ACTIVE',
    ]);
});

function unitSunatPayload(string $abbreviation, mixed $mapping): array
{
    return [
        'abbreviation' => $abbreviation,
        'description' => $abbreviation === 'KG' ? 'KILOGRAMO' : 'UNIDAD',
        'decimal_quantity' => 0,
        'status' => 'ACTIVE',
        'observation' => null,
        'sunat_unit_item_id' => $mapping,
    ];
}

it('agrega la FK nullable y guarda la relación válida de Tabla 06', function () {
    expect(Schema::hasColumn('units', 'sunat_unit_item_id'))->toBeTrue();

    $this->postJson(route('admin.units.store'), unitSunatPayload('UND', $this->niu->id))->assertCreated();

    $unit = Unit::where('abbreviation', 'UND')->firstOrFail();
    expect($unit->sunatUnit->item_code)->toBe('NIU');
});

it('rechaza ids inexistentes, inactivos y pertenecientes a otro catálogo', function ($mapping) {
    $this->postJson(route('admin.units.store'), unitSunatPayload('UND', is_callable($mapping) ? $mapping() : $mapping))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sunat_unit_item_id');
})->with([
    'inexistente' => [999999],
    'inactivo' => [fn () => test()->inactive->id],
    'Tabla 05' => [fn () => test()->wrongCatalog->id],
]);

it('rechaza un item cuando el catálogo SUNAT 06 está inactivo', function () {
    SunatCatalog::where('code', '06')->update(['is_active' => false]);

    $this->postJson(route('admin.units.store'), unitSunatPayload('UND', $this->niu->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sunat_unit_item_id');
});

it('permite asignación inicial con historial y bloquea cambios posteriores', function () {
    $unit = Unit::create(unitSunatPayload('UND', null));
    $category = Category::create(['code' => 'CAT-U06', 'description' => 'CATEGORÍA', 'type' => 'ARTICLE', 'status' => 'ACTIVE']);
    $article = Article::create([
        'code' => 'ART-U06', 'category_id' => $category->id, 'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO', 'billing_name' => 'ARTÍCULO', 'item_kind' => 'product',
        'is_inventory_item' => true, 'status' => 'ACTIVE',
    ]);
    $company = Company::create(['business_name' => 'EMPRESA U06', 'ruc' => '20987654321', 'status' => true]);
    $warehouse = Warehouse::create(['code' => 'ALM-U06', 'name' => 'ALMACÉN U06', 'status' => 'ACTIVE']);
    DB::table('warehouse_stocks')->insert([
        'stock_key' => 'U06-HISTORY', 'company_id' => $company->id, 'warehouse_id' => $warehouse->id,
        'article_id' => $article->id, 'unit_id' => $unit->id, 'current_quantity' => 1,
        'reserved_quantity' => 0, 'average_unit_cost' => 10, 'total_cost' => 10,
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->putJson(route('admin.units.update', $unit), unitSunatPayload('UND', $this->niu->id))->assertOk();
    $this->putJson(route('admin.units.update', $unit), unitSunatPayload('UND', $this->kgm->id))
        ->assertUnprocessable()
        ->assertJsonPath('errors.sunat_unit_item_id.0', 'La unidad SUNAT no puede modificarse porque esta unidad ya tiene historial operativo.');

    expect((int) $unit->fresh()->sunat_unit_item_id)->toBe($this->niu->id);
});

it('permite cambiar o retirar la equivalencia cuando no existe historial operativo', function () {
    $unit = Unit::create(unitSunatPayload('UND', $this->niu->id));

    $this->putJson(route('admin.units.update', $unit), unitSunatPayload('UND', $this->kgm->id))->assertOk();
    $this->putJson(route('admin.units.update', $unit), unitSunatPayload('UND', null))->assertOk();

    expect($unit->fresh()->sunat_unit_item_id)->toBeNull();
});

it('audita candidatos seguros y abreviaturas ambiguas sin escribir datos', function () {
    Unit::create(unitSunatPayload('UND', null));
    Unit::create(array_merge(unitSunatPayload('AMP', null), ['description' => 'AMPOLLA']));
    Unit::create(unitSunatPayload('KG', $this->kgm->id));
    $before = Unit::query()->pluck('sunat_unit_item_id', 'id')->all();

    $result = app(UnitSunatAuditService::class)->analyze();
    $exit = Artisan::call('units:audit-sunat-unit');

    expect(count($result['configured']))->toBe(1)
        ->and(count($result['safe_candidates']))->toBe(1)
        ->and($result['safe_candidates'][0]['suggested_code'])->toBe('NIU')
        ->and(count($result['ambiguous']))->toBe(1)
        ->and($result['ambiguous'][0]['abbreviation'])->toBe('AMP')
        ->and($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Modo DRY-RUN')
        ->and(Unit::query()->pluck('sunat_unit_item_id', 'id')->all())->toBe($before);
});

it('apply configura únicamente un candidato seguro', function () {
    $unit = Unit::create(unitSunatPayload('UND', null));

    expect(Artisan::call('units:audit-sunat-unit', ['--apply' => true]))->toBe(0)
        ->and((int) $unit->fresh()->sunat_unit_item_id)->toBe($this->niu->id)
        ->and(Artisan::output())->toContain('APLICADA');
});

it('apply resuelve UND hacia NIU por código y catálogo activo', function () {
    $unit = Unit::create(unitSunatPayload('UND', null));
    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);

    expect($unit->fresh()->sunatUnit->item_code)->toBe('NIU')
        ->and($unit->fresh()->sunatUnit->catalog_code)->toBe('06');
});

it('apply resuelve CJ hacia BX', function () {
    $unit = Unit::create(array_merge(unitSunatPayload('CJ', null), ['description' => 'CAJA']));
    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);

    expect($unit->fresh()->sunatUnit->item_code)->toBe('BX');
});

it('apply resuelve KG hacia KGM', function () {
    $unit = Unit::create(unitSunatPayload('KG', null));
    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);

    expect($unit->fresh()->sunatUnit->item_code)->toBe('KGM');
});

it('apply preserva todas las unidades ambiguas en NULL', function () {
    $units = collect(['MCG', 'TAB', 'CAP', 'AMP', 'FR', 'DET', 'PBA', 'ROL', 'SOB'])
        ->map(fn ($abbreviation) => Unit::create(array_merge(
            unitSunatPayload($abbreviation, null),
            ['description' => $abbreviation]
        )));

    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);

    expect(Unit::whereKey($units->pluck('id'))->whereNotNull('sunat_unit_item_id')->count())->toBe(0);
});

it('apply no sobrescribe una configuración existente y la reporta', function () {
    $unit = Unit::create(unitSunatPayload('KG', $this->niu->id));
    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);

    expect((int) $unit->fresh()->sunat_unit_item_id)->toBe($this->niu->id)
        ->and(Artisan::output())->toContain('YA CONFIGURADA');
});

it('apply nunca toma un item homónimo perteneciente a otro catálogo', function () {
    $unit = Unit::create(array_merge(unitSunatPayload('CJ', null), ['description' => 'CAJA']));
    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);

    expect((int) $unit->fresh()->sunat_unit_item_id)->toBe($this->bx->id)
        ->and((int) $unit->fresh()->sunat_unit_item_id)->not->toBe($this->wrongBx->id);
});

it('apply es idempotente en una segunda ejecución', function () {
    $unit = Unit::create(unitSunatPayload('UND', null));
    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);
    $firstMapping = $unit->fresh()->sunat_unit_item_id;
    $firstUpdatedAt = $unit->fresh()->updated_at?->format('Y-m-d H:i:s.u');

    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);

    expect($unit->fresh()->sunat_unit_item_id)->toBe($firstMapping)
        ->and($unit->fresh()->updated_at?->format('Y-m-d H:i:s.u'))->toBe($firstUpdatedAt)
        ->and(Artisan::output())->toContain('YA CONFIGURADA');
});

it('apply no modifica facturas históricas ni stock Kardex cantidades o costos', function () {
    $unit = Unit::create(unitSunatPayload('UND', null));
    $category = Category::create(['code' => 'CAT-APPLY', 'description' => 'CATEGORÍA APPLY', 'type' => 'ARTICLE', 'status' => 'ACTIVE']);
    $article = Article::create([
        'code' => 'ART-APPLY', 'category_id' => $category->id, 'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO APPLY', 'billing_name' => 'ARTÍCULO APPLY',
        'item_kind' => 'product', 'is_inventory_item' => true, 'status' => 'ACTIVE',
    ]);
    $company = Company::create(['business_name' => 'EMPRESA APPLY', 'ruc' => '20111222333', 'status' => true]);
    $warehouse = Warehouse::create(['code' => 'ALM-APPLY', 'name' => 'ALMACÉN APPLY', 'status' => 'ACTIVE']);
    $stockId = DB::table('warehouse_stocks')->insertGetId([
        'stock_key' => 'APPLY-STOCK', 'company_id' => $company->id, 'warehouse_id' => $warehouse->id,
        'article_id' => $article->id, 'unit_id' => $unit->id, 'current_quantity' => 7,
        'reserved_quantity' => 2, 'average_unit_cost' => 12.5, 'total_cost' => 87.5,
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('warehouse_kardex_movements')->insert([
        'movement_number' => 'KDX-APPLY-001', 'company_id' => $company->id,
        'warehouse_stock_id' => $stockId, 'warehouse_id' => $warehouse->id,
        'article_id' => $article->id, 'unit_id' => $unit->id, 'movement_date' => now(),
        'movement_type' => 'entry', 'operation_type' => 'test', 'quantity_in' => 7,
        'quantity_out' => 0, 'balance_quantity' => 7, 'unit_cost' => 12.5,
        'total_cost_in' => 87.5, 'total_cost_out' => 0, 'average_unit_cost' => 12.5,
        'balance_total_cost' => 87.5, 'status' => 'registered', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $invoiceId = DB::table('electronic_invoices')->insertGetId([
        'company_id' => $company->id, 'document_type' => '01', 'serie' => 'F001',
        'correlativo' => '00000001', 'full_number' => 'F001-00000001',
        'issue_date' => today(), 'status' => 'generated', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('electronic_invoice_items')->insert([
        'electronic_invoice_id' => $invoiceId, 'article_id' => $article->id, 'item_number' => 1,
        'description' => 'HISTÓRICO', 'unit_code' => 'HIST', 'quantity' => 7,
        'unit_value' => 12.5, 'unit_price' => 14.75, 'subtotal' => 87.5,
        'igv_base' => 87.5, 'igv_amount' => 15.75, 'line_total' => 103.25,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $articleBefore = DB::table('articles')->where('id', $article->id)->first();
    $stockBefore = DB::table('warehouse_stocks')->where('id', $stockId)->first();
    $kardexBefore = DB::table('warehouse_kardex_movements')->where('warehouse_stock_id', $stockId)->first();
    $invoiceItemBefore = DB::table('electronic_invoice_items')->where('electronic_invoice_id', $invoiceId)->first();

    Artisan::call('units:audit-sunat-unit', ['--apply' => true]);

    expect((int) $unit->fresh()->sunat_unit_item_id)->toBe($this->niu->id)
        ->and(DB::table('articles')->where('id', $article->id)->first())->toEqual($articleBefore)
        ->and(DB::table('warehouse_stocks')->where('id', $stockId)->first())->toEqual($stockBefore)
        ->and(DB::table('warehouse_kardex_movements')->where('warehouse_stock_id', $stockId)->first())->toEqual($kardexBefore)
        ->and(DB::table('electronic_invoice_items')->where('electronic_invoice_id', $invoiceId)->first())->toEqual($invoiceItemBefore);
});
