<?php

use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\DB;

function valuedKardexFixture(string $suffix = ''): array
{
    $now = now();
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'U'.$suffix,
        'description' => 'UNIDAD '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORIA '.$suffix,
        'code' => 'CAT'.$suffix,
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => 'ART'.$suffix,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTICULO '.$suffix,
        'billing_name' => 'ARTICULO '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'ALM'.$suffix,
        'name' => 'ALMACEN '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stock = WarehouseStock::create([
        'stock_key' => "{$warehouseId}|{$articleId}|SIN_LOTE|SIN_FECHA",
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'current_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
        'status' => 'ACTIVE',
    ]);

    return compact('warehouseId', 'articleId', 'unitId', 'stock');
}

function valuedKardexMovement(array $fixture, string $number, string $date, array $values): WarehouseKardexMovement
{
    return WarehouseKardexMovement::create(array_merge([
        'movement_number' => $number,
        'warehouse_stock_id' => $fixture['stock']->id,
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'movement_date' => $date,
        'movement_type' => 'entry',
        'operation_type' => 'test',
        'quantity_in' => 0,
        'quantity_out' => 0,
        'balance_quantity' => 0,
        'unit_cost' => 0,
        'total_cost_in' => 0,
        'total_cost_out' => 0,
        'average_unit_cost' => 0,
        'balance_total_cost' => 0,
        'status' => 'registered',
    ], $values));
}

it('calcula el promedio ponderado de dos compras', function () {
    $service = app(WarehouseKardexService::class);

    expect($service->calculateAverageCost(100 + 200, 10 + 10))->toBe(15.0);
});

it('asigna todo el costo vinculado cuando existe un solo producto', function () {
    expect(app(WarehouseKardexService::class)->proportionalAllocation(30, [300]))->toBe([30.0]);
});

it('distribuye un costo vinculado proporcionalmente al valor de compra', function () {
    expect(app(WarehouseKardexService::class)->proportionalAllocation(40, [75, 25]))->toBe([30.0, 10.0]);
});

it('valoriza una salida al promedio vigente', function () {
    $fixture = valuedKardexFixture('A');
    valuedKardexMovement($fixture, 'KDX-T-A1', '2026-08-01 09:00:00', ['quantity_in' => 10, 'unit_cost' => 10, 'total_cost_in' => 100]);
    valuedKardexMovement($fixture, 'KDX-T-A2', '2026-08-02 09:00:00', ['quantity_in' => 10, 'unit_cost' => 20, 'total_cost_in' => 200]);
    $exit = valuedKardexMovement($fixture, 'KDX-T-A3', '2026-08-03 09:00:00', ['movement_type' => 'exit', 'quantity_out' => 5, 'total_cost_out' => 999]);

    app(WarehouseKardexService::class)->recalculate(['warehouse_id' => $fixture['warehouseId']]);

    expect((float) $exit->fresh()->total_cost_out)->toBe(75.0)
        ->and((float) $exit->fresh()->balance_quantity)->toBe(15.0)
        ->and((float) $exit->fresh()->balance_total_cost)->toBe(225.0);
});

it('incrementa el valor sin cantidad y conserva el nuevo promedio en la siguiente salida', function () {
    $fixture = valuedKardexFixture('B');
    valuedKardexMovement($fixture, 'KDX-T-B1', '2026-08-01 09:00:00', ['quantity_in' => 10, 'total_cost_in' => 100]);
    valuedKardexMovement($fixture, 'KDX-T-B2', '2026-08-02 09:00:00', ['quantity_in' => 10, 'total_cost_in' => 200]);
    valuedKardexMovement($fixture, 'KDX-T-B3', '2026-08-03 09:00:00', ['movement_type' => 'exit', 'quantity_out' => 5]);
    $cost = valuedKardexMovement($fixture, 'KDX-T-B4', '2026-08-04 09:00:00', ['movement_type' => 'linked_cost', 'total_cost_in' => 30]);
    $exit = valuedKardexMovement($fixture, 'KDX-T-B5', '2026-08-05 09:00:00', ['movement_type' => 'exit', 'quantity_out' => 3]);

    app(WarehouseKardexService::class)->recalculate(['warehouse_id' => $fixture['warehouseId']]);

    expect((float) $cost->fresh()->balance_quantity)->toBe(15.0)
        ->and((float) $cost->fresh()->average_unit_cost)->toBe(17.0)
        ->and((float) $exit->fresh()->total_cost_out)->toBe(51.0)
        ->and((float) $exit->fresh()->balance_total_cost)->toBe(204.0);
});

it('consulta el ultimo saldo disponible a una fecha', function () {
    $fixture = valuedKardexFixture('C');
    valuedKardexMovement($fixture, 'KDX-T-C1', '2026-08-01 09:00:00', ['balance_quantity' => 10, 'average_unit_cost' => 10, 'balance_total_cost' => 100]);
    valuedKardexMovement($fixture, 'KDX-T-C2', '2026-08-05 09:00:00', ['balance_quantity' => 20, 'average_unit_cost' => 15, 'balance_total_cost' => 300]);

    $stock = app(WarehouseKardexService::class)->stockAtDateQuery('2026-08-03')->firstOrFail();

    expect((float) $stock->balance_quantity)->toBe(10.0)
        ->and((float) $stock->balance_total_cost)->toBe(100.0);
});

it('respeta los filtros de almacen y articulo en el stock historico', function () {
    $first = valuedKardexFixture('D1');
    $second = valuedKardexFixture('D2');
    valuedKardexMovement($first, 'KDX-T-D1', '2026-08-01 09:00:00', ['balance_quantity' => 8]);
    valuedKardexMovement($second, 'KDX-T-D2', '2026-08-01 09:00:00', ['balance_quantity' => 99]);

    $items = app(WarehouseKardexService::class)->stockAtDateQuery(
        '2026-08-02',
        $first['warehouseId'],
        $first['articleId']
    )->get();

    expect($items)->toHaveCount(1)
        ->and((float) $items->first()->balance_quantity)->toBe(8.0);
});

it('recalcula solo el alcance filtrado y registra una bitacora', function () {
    $first = valuedKardexFixture('E1');
    $second = valuedKardexFixture('E2');
    valuedKardexMovement($first, 'KDX-T-E1', '2026-08-01 09:00:00', ['quantity_in' => 4, 'total_cost_in' => 40]);
    valuedKardexMovement($second, 'KDX-T-E2', '2026-08-01 09:00:00', ['quantity_in' => 9, 'total_cost_in' => 90]);
    $second['stock']->update(['current_quantity' => 77, 'total_cost' => 777]);

    $log = app(WarehouseKardexService::class)->recalculate(['warehouse_id' => $first['warehouseId']]);

    expect((float) $first['stock']->fresh()->current_quantity)->toBe(4.0)
        ->and((float) $second['stock']->fresh()->current_quantity)->toBe(77.0)
        ->and($log->stocks_processed)->toBe(1)
        ->and($log->movements_processed)->toBe(1)
        ->and($log->finished_at)->not->toBeNull();
});
