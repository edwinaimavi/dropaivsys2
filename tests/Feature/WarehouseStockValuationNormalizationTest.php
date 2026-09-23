<?php

use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Services\WarehouseStockValuationNormalizationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function normalizationStockFixture(
    string $suffix,
    float $quantity = 0,
    float $reserved = 0,
    float $totalCost = 0,
    float $averageCost = 0
): array {
    $now = now();
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'N'.$suffix,
        'description' => 'UNIDAD NORMALIZACION '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORIA NORMALIZACION '.$suffix,
        'code' => 'NC'.$suffix,
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = 'NART'.$suffix;
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTICULO NORMALIZACION '.$suffix,
        'billing_name' => 'ARTICULO NORMALIZACION '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'NAL'.$suffix,
        'name' => 'ALMACEN NORMALIZACION '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $lotNumber = 'LOT-'.$suffix;
    $stock = WarehouseStock::create([
        'stock_key' => "{$warehouseId}|{$articleId}|{$lotNumber}|SIN_FECHA",
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => $lotNumber,
        'current_quantity' => $quantity,
        'reserved_quantity' => $reserved,
        'average_unit_cost' => $averageCost,
        'total_cost' => $totalCost,
        'status' => 'ACTIVE',
    ]);

    return compact('warehouseId', 'articleId', 'unitId', 'lotNumber', 'stock');
}

function normalizationHistoricalMovement(array $fixture, float $historicalValue = 33.48): WarehouseKardexMovement
{
    return WarehouseKardexMovement::create([
        'movement_number' => 'KDX-NORM-'.$fixture['stock']->id,
        'warehouse_stock_id' => $fixture['stock']->id,
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'lot_number' => $fixture['lotNumber'],
        'movement_date' => '2026-08-01 09:00:00',
        'movement_type' => 'linked_cost',
        'operation_type' => 'warehouse_entry_linked_cost',
        'quantity_in' => 0,
        'quantity_out' => 0,
        'balance_quantity' => 0,
        'unit_cost' => 0,
        'total_cost_in' => $historicalValue,
        'total_cost_out' => 0,
        'average_unit_cost' => 0,
        'balance_total_cost' => $historicalValue,
        'status' => 'registered',
    ]);
}

function normalizationPersistenceSnapshot(array $fixture): array
{
    return [
        'stock' => $fixture['stock']->fresh()->getAttributes(),
        'kardex' => WarehouseKardexMovement::query()
            ->orderBy('id')
            ->get()
            ->map(fn (WarehouseKardexMovement $movement) => $movement->getAttributes())
            ->all(),
        'valuation_pools' => DB::table('warehouse_valuation_pools')
            ->orderBy('id')
            ->get()
            ->map(fn ($pool) => (array) $pool)
            ->all(),
    ];
}

it('mantiene disponible la auditoría sin modificar stock Kardex ni valuation pool', function () {
    $fixture = normalizationStockFixture('AUDIT', -0.0001, 0, 33.48, 2.50);
    normalizationHistoricalMovement($fixture);
    $before = normalizationPersistenceSnapshot($fixture);

    $report = app(WarehouseStockValuationNormalizationService::class)->audit();

    expect($report['count'])->toBe(1)
        ->and($report['residual_total'])->toBe(33.48)
        ->and($report['stocks'][0]['id'])->toBe($fixture['stock']->id)
        ->and($report['stocks'][0]['current_quantity'])->toBe(-0.0001)
        ->and($report['stocks'][0]['average_unit_cost'])->toBe(2.5)
        ->and(normalizationPersistenceSnapshot($fixture))->toBe($before);
});

it('bloquea normalize antes de cualquier escritura', function () {
    $fixture = normalizationStockFixture('NORMALIZE', -0.0001, 0, 33.48, 2.50);
    normalizationHistoricalMovement($fixture);
    $before = normalizationPersistenceSnapshot($fixture);

    expect(fn () => app(WarehouseStockValuationNormalizationService::class)->normalize())
        ->toThrow(LogicException::class, WarehouseStockValuationNormalizationService::MUTATION_DISABLED_MESSAGE)
        ->and(normalizationPersistenceSnapshot($fixture))->toBe($before);
});

it('bloquea normalizeIfMatches aunque las expectativas coincidan', function () {
    $fixture = normalizationStockFixture('MATCHES', 0, 0, 33.48, 2.50);
    normalizationHistoricalMovement($fixture);
    $service = app(WarehouseStockValuationNormalizationService::class);
    $report = $service->audit();
    $before = normalizationPersistenceSnapshot($fixture);

    expect(fn () => $service->normalizeIfMatches(
        $report['count'],
        $report['residual_total'],
        [$fixture['stock']->id]
    ))->toThrow(LogicException::class, WarehouseStockValuationNormalizationService::MUTATION_DISABLED_MESSAGE)
        ->and(normalizationPersistenceSnapshot($fixture))->toBe($before);
});

it('bloquea el comando apply sin modificar datos', function () {
    $fixture = normalizationStockFixture('APPLY', -0.0001, 0, 33.48, 2.50);
    normalizationHistoricalMovement($fixture);
    $before = normalizationPersistenceSnapshot($fixture);

    $exitCode = Artisan::call('warehouse:normalize-stock-valuation', [
        '--apply' => true,
    ]);

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain(WarehouseStockValuationNormalizationService::MUTATION_DISABLED_MESSAGE)
        ->and(normalizationPersistenceSnapshot($fixture))->toBe($before);
});

it('mantiene operativo el comando dry-run sin modificar datos', function () {
    $fixture = normalizationStockFixture('DRYRUN', -0.0001, 0, 33.48, 2.50);
    normalizationHistoricalMovement($fixture);
    $before = normalizationPersistenceSnapshot($fixture);

    $exitCode = Artisan::call('warehouse:normalize-stock-valuation', [
        '--dry-run' => true,
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('DRY-RUN')
        ->and($output)->toContain('Registros detectados: 1')
        ->and(normalizationPersistenceSnapshot($fixture))->toBe($before);
});
