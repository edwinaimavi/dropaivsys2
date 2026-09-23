<?php

use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseKardexHistoricalBackfillService;
use Illuminate\Support\Facades\DB;

function historicalBackfillFixture(string $suffix): array
{
    $now = now();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA BACKFILL '.$suffix,
        'ruc' => sprintf('20%09d', abs(crc32('BACKFILL-'.$suffix)) % 1_000_000_000),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'BF-'.$suffix,
        'name' => 'ALMACÉN BACKFILL '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'sunat_establishment_code' => '0099',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'NIU',
        'description' => 'UNIDAD ACTUAL '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA BACKFILL '.$suffix,
        'code' => 'CBF-'.$suffix,
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = 'ABF-'.$suffix;
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO ACTUAL '.$suffix,
        'billing_name' => 'ARTÍCULO ACTUAL '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => false,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return compact('companyId', 'warehouseId', 'unitId', 'articleId', 'articleCode');
}

function historicalBackfillMovement(array $fixture, string $number, array $overrides = []): WarehouseKardexMovement
{
    return WarehouseKardexMovement::create(array_merge([
        'movement_number' => $number,
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'movement_date' => '2026-01-15 10:00:00',
        'movement_type' => 'adjustment_in',
        'operation_type' => 'manual_adjustment',
        'quantity_in' => '2.5000',
        'quantity_out' => '0.0000',
        'balance_quantity' => '2.5000',
        'unit_cost' => '12.345678',
        'total_cost_in' => '30.86',
        'total_cost_out' => '0.00',
        'average_unit_cost' => '12.345678',
        'balance_total_cost' => '30.86',
        'status' => 'registered',
    ], $overrides));
}

function historicalBackfillDetail(array $result, int $movementId, string $field): array
{
    return collect($result['details'])
        ->first(fn (array $detail): bool => $detail['movement_id'] === $movementId && $detail['field'] === $field);
}

function completeHistoricalSnapshots(): array
{
    return [
        'sunat_establishment_code_snapshot' => '0001',
        'article_code_snapshot' => 'HIST-001',
        'article_description_snapshot' => 'ARTÍCULO HISTÓRICO',
        'sunat_existence_type_code_snapshot' => '01',
        'existence_catalog_code_snapshot' => '9',
        'existence_code_snapshot' => 'HIST-001',
        'sunat_unit_code_snapshot' => 'NIU',
        'unit_description_snapshot' => 'UNIDAD HISTÓRICA',
        'valuation_method_code_snapshot' => '1',
        'valuation_method_description_snapshot' => 'PROMEDIO PONDERADO',
        'document_date_snapshot' => '2026-01-10',
        'sunat_document_type_code_snapshot' => '01',
        'sunat_operation_type_code_snapshot' => '02',
        'document_series' => 'F001',
        'document_number' => '00000001',
    ];
}

it('clasifica FACTURA como Tabla 10 SAFE y el comando por defecto es DRY-RUN', function () {
    $fixture = historicalBackfillFixture('DOC10');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-DOC10', ['document_type' => 'FACTURA']);

    $result = app(WarehouseKardexHistoricalBackfillService::class)->audit();
    $detail = historicalBackfillDetail($result, $movement->id, 'sunat_document_type_code_snapshot');

    expect($detail['classification'])->toBe('SAFE')
        ->and($detail['proposed_value'])->toBe('01')
        ->and($movement->fresh()->sunat_document_type_code_snapshot)->toBeNull();
    $this->artisan('inventory:audit-kardex-backfill')->expectsOutputToContain('MODO: DRY-RUN')->assertSuccessful();
});

it('clasifica la Tabla 12 determinística como SAFE', function () {
    $fixture = historicalBackfillFixture('DOC12');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-DOC12', [
        'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry',
    ]);

    $detail = historicalBackfillDetail(
        app(WarehouseKardexHistoricalBackfillService::class)->audit(),
        $movement->id,
        'sunat_operation_type_code_snapshot'
    );

    expect($detail['classification'])->toBe('SAFE')->and($detail['proposed_value'])->toBe('02');
});

it('copia snapshots completos del movimiento original en una reversa', function () {
    $fixture = historicalBackfillFixture('REV');
    $original = historicalBackfillMovement($fixture, 'KDX-BF-ORIGINAL', [
        ...completeHistoricalSnapshots(),
        'document_type' => 'FACTURA',
    ]);
    $reversal = historicalBackfillMovement($fixture, 'KDX-BF-REVERSAL', [
        'movement_type' => 'reversal',
        'operation_type' => 'warehouse_entry_cancel',
        'document_type' => 'FACTURA',
        'source_key' => 'reversal:'.$original->id,
    ]);

    $result = app(WarehouseKardexHistoricalBackfillService::class)->audit();
    $articleDetail = historicalBackfillDetail($result, $reversal->id, 'article_code_snapshot');

    expect($articleDetail['classification'])->toBe('SAFE')
        ->and($articleDetail['proposed_value'])->toBe('HIST-001')
        ->and(historicalBackfillDetail($result, $reversal->id, 'sunat_operation_type_code_snapshot')['proposed_value'])->toBe('99');
});

it('no usa Article actual para inventar article_code_snapshot', function () {
    $fixture = historicalBackfillFixture('ARTICLE');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-ARTICLE');

    $detail = historicalBackfillDetail(app(WarehouseKardexHistoricalBackfillService::class)->audit(), $movement->id, 'article_code_snapshot');

    expect($detail['classification'])->toBe('MANUAL_REVIEW')->and($detail['proposed_value'])->toBeNull();
});

it('no usa Unit ni Tabla 06 actuales para inventar snapshots', function () {
    $fixture = historicalBackfillFixture('UNIT');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-UNIT');
    $result = app(WarehouseKardexHistoricalBackfillService::class)->audit();

    expect(historicalBackfillDetail($result, $movement->id, 'sunat_unit_code_snapshot')['classification'])->toBe('MANUAL_REVIEW')
        ->and(historicalBackfillDetail($result, $movement->id, 'unit_description_snapshot')['classification'])->toBe('MANUAL_REVIEW');
});

it('deja document_date en revisión manual sin fuente histórica fiable', function () {
    $fixture = historicalBackfillFixture('DATE');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-DATE', ['document_type' => 'FACTURA']);

    $detail = historicalBackfillDetail(app(WarehouseKardexHistoricalBackfillService::class)->audit(), $movement->id, 'document_date_snapshot');

    expect($detail['classification'])->toBe('MANUAL_REVIEW')->and($detail['proposed_value'])->toBeNull();
});

it('apply-safe actualiza solo campos SAFE y deja los manuales NULL', function () {
    $fixture = historicalBackfillFixture('APPLY');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-APPLY', ['document_type' => 'FACTURA']);

    $result = app(WarehouseKardexHistoricalBackfillService::class)->audit(true);
    $movement->refresh();

    expect($result['summary']['applied_fields'])->toBe(2)
        ->and($movement->sunat_document_type_code_snapshot)->toBe('01')
        ->and($movement->sunat_operation_type_code_snapshot)->toBe('28')
        ->and($movement->article_code_snapshot)->toBeNull()
        ->and($movement->document_date_snapshot)->toBeNull();
});

it('no sobrescribe un snapshot existente', function () {
    $fixture = historicalBackfillFixture('KEEP');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-KEEP', [
        'document_type' => 'FACTURA',
        'sunat_document_type_code_snapshot' => '03',
    ]);

    app(WarehouseKardexHistoricalBackfillService::class)->audit(true);

    expect($movement->fresh()->sunat_document_type_code_snapshot)->toBe('03');
});

it('es idempotente al aplicar candidatos seguros dos veces', function () {
    $fixture = historicalBackfillFixture('IDEMP');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-IDEMP', ['document_type' => 'FACTURA']);
    $service = app(WarehouseKardexHistoricalBackfillService::class);

    $first = $service->audit(true);
    $afterFirst = $movement->fresh()->updated_at?->format('Y-m-d H:i:s.u');
    $second = $service->audit(true);

    expect($first['summary']['applied_fields'])->toBe(2)
        ->and($second['summary']['applied_fields'])->toBe(0)
        ->and($movement->fresh()->updated_at?->format('Y-m-d H:i:s.u'))->toBe($afterFirst);
});

it('no modifica cantidades ni costos del movimiento', function () {
    $fixture = historicalBackfillFixture('VALUES');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-VALUES', ['document_type' => 'FACTURA']);
    $before = $movement->only(['quantity_in', 'quantity_out', 'balance_quantity', 'unit_cost', 'total_cost_in', 'total_cost_out', 'average_unit_cost', 'balance_total_cost']);

    app(WarehouseKardexHistoricalBackfillService::class)->audit(true);

    expect($movement->fresh()->only(array_keys($before)))->toBe($before);
});

it('no modifica stock ni valuation pool', function () {
    $fixture = historicalBackfillFixture('POOL');
    $stock = WarehouseStock::create([
        'stock_key' => 'BF-STOCK-POOL',
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'current_quantity' => '2.5000',
        'reserved_quantity' => '0.5000',
        'average_unit_cost' => '12.345678',
        'total_cost' => '30.86',
        'status' => 'ACTIVE',
    ]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'current_quantity' => '2.5000',
        'average_unit_cost' => '12.345678',
        'total_cost' => '30.86',
    ]);
    historicalBackfillMovement($fixture, 'KDX-BF-POOL', ['document_type' => 'FACTURA', 'warehouse_stock_id' => $stock->id]);
    $stockBefore = $stock->fresh()->getRawOriginal();
    $poolBefore = $pool->fresh()->getRawOriginal();

    app(WarehouseKardexHistoricalBackfillService::class)->audit(true);

    expect($stock->fresh()->getRawOriginal())->toBe($stockBefore)
        ->and($pool->fresh()->getRawOriginal())->toBe($poolBefore);
});

it('clasifica SOURCE_MISSING cuando el origen declarado no existe', function () {
    $fixture = historicalBackfillFixture('MISSING');
    $movement = historicalBackfillMovement($fixture, 'KDX-BF-MISSING', [
        'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry',
        'source_type' => App\Models\WarehouseEntry::class,
        'source_id' => 999999,
    ]);

    $result = app(WarehouseKardexHistoricalBackfillService::class)->audit();
    $detail = historicalBackfillDetail($result, $movement->id, 'article_code_snapshot');

    expect($detail['classification'])->toBe('SOURCE_MISSING')
        ->and($result['summary']['source_missing'])->toBe(1);
});
