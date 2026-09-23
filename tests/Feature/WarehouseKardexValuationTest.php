<?php

use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDistribution;
use App\Models\WarehouseEntryItem;
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
        'sunat_unit_item_id' => testSunatUnitItemId(),
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

function valuedLinkedCostContext(array $fixture, string $suffix, float $amount): array
{
    $now = now();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA '.$suffix,
        'ruc' => '2012345'.str_pad((string) strlen($suffix), 4, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId,
        'warehouse_id' => $fixture['warehouseId'],
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $fixture['stock']->update(['company_id' => $companyId]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '2098765'.str_pad((string) strlen($suffix), 4, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'LC'.$suffix,
        'description' => 'MONEDA '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-LC-'.$suffix,
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $fixture['warehouseId'],
        'company_id' => $companyId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'status' => 'registered',
    ]);
    $item = WarehouseEntryItem::create([
        'warehouse_entry_id' => $entry->id,
        'article_id' => $fixture['articleId'],
        'billing_name_snapshot' => 'ARTICULO '.$suffix,
        'unit_id' => $fixture['unitId'],
        'quantity' => 10,
        'unit_price' => 5,
        'line_total' => 50,
        'status' => 'active',
    ]);
    $entryMovement = valuedKardexMovement(
        $fixture,
        'KDX-LC-'.$suffix.'-IN',
        '2026-08-01 09:00:00',
        [
            'company_id' => $companyId,
            'operation_type' => 'warehouse_entry',
            'source_type' => WarehouseEntry::class,
            'source_id' => $entry->id,
            'source_item_type' => WarehouseEntryItem::class,
            'source_item_id' => $item->id,
            'source_key' => 'warehouse-entry-linked-test-'.$suffix,
            'quantity_in' => 10,
            'unit_cost' => 5,
            'total_cost_in' => 50,
            'balance_quantity' => 10,
            'average_unit_cost' => 5,
            'balance_total_cost' => 50,
        ]
    );
    $expense = WarehouseEntryExpense::create([
        'warehouse_entry_id' => $entry->id,
        'source_type' => WarehouseEntryExpense::SOURCE_MANUAL,
        'expense_category' => 'freight_transport',
        'cost_origin' => 'third_party',
        'expense_type' => 'agency_freight',
        'provider_name' => 'TRANSPORTE '.$suffix,
        'document_type' => 'SIN_COMPROBANTE',
        'currency_id' => $currencyId,
        'amount' => $amount,
        'affects_igv' => false,
        'taxable_amount' => $amount,
        'igv_amount' => 0,
        'total_amount' => $amount,
        'affects_inventory_cost' => true,
        'distribution_method' => 'amount',
        'description' => 'COSTO VINCULADO '.$suffix,
        'status' => 'ACTIVE',
        'approval_status' => WarehouseEntryExpense::APPROVAL_APPROVED,
    ]);
    $distribution = WarehouseEntryExpenseDistribution::create([
        'warehouse_entry_expense_id' => $expense->id,
        'warehouse_entry_item_id' => $item->id,
        'distributed_amount' => $amount,
    ]);

    return compact('entry', 'item', 'entryMovement', 'expense', 'distribution');
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

it('deja en cero cantidad y valor al agotar diez unidades valorizadas en cincuenta', function () {
    $fixture = valuedKardexFixture('ZERO10');
    valuedKardexMovement($fixture, 'KDX-Z10-1', '2026-08-01 09:00:00', [
        'quantity_in' => 10,
        'unit_cost' => 5,
        'total_cost_in' => 50,
    ]);
    $exit = valuedKardexMovement($fixture, 'KDX-Z10-2', '2026-08-02 09:00:00', [
        'movement_type' => 'exit',
        'quantity_out' => 10,
    ]);

    app(WarehouseKardexService::class)->recalculate(['warehouse_id' => $fixture['warehouseId']]);

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(0.0)
        ->and((float) $exit->fresh()->total_cost_out)->toBe(50.0)
        ->and((float) $exit->fresh()->balance_total_cost)->toBe(0.0);
});

it('deja en cero cantidad y valor al agotar veinte unidades valorizadas en cincuenta', function () {
    $fixture = valuedKardexFixture('ZERO20');
    valuedKardexMovement($fixture, 'KDX-Z20-1', '2026-08-01 09:00:00', [
        'quantity_in' => 20,
        'unit_cost' => 2.5,
        'total_cost_in' => 50,
    ]);
    $exit = valuedKardexMovement($fixture, 'KDX-Z20-2', '2026-08-02 09:00:00', [
        'movement_type' => 'exit',
        'quantity_out' => 20,
    ]);

    app(WarehouseKardexService::class)->recalculate(['warehouse_id' => $fixture['warehouseId']]);

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(0.0)
        ->and((float) $exit->fresh()->total_cost_out)->toBe(50.0)
        ->and((float) $exit->fresh()->balance_total_cost)->toBe(0.0);
});

it('conserva treinta de valor al despachar parcialmente cuatro de diez unidades', function () {
    $fixture = valuedKardexFixture('PARTIAL');
    valuedKardexMovement($fixture, 'KDX-PAR-1', '2026-08-01 09:00:00', [
        'quantity_in' => 10,
        'unit_cost' => 5,
        'total_cost_in' => 50,
    ]);
    $exit = valuedKardexMovement($fixture, 'KDX-PAR-2', '2026-08-02 09:00:00', [
        'movement_type' => 'exit',
        'quantity_out' => 4,
    ]);

    app(WarehouseKardexService::class)->recalculate(['warehouse_id' => $fixture['warehouseId']]);

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0)
        ->and((float) $fixture['stock']->fresh()->average_unit_cost)->toBe(5.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(30.0)
        ->and((float) $exit->fresh()->balance_total_cost)->toBe(30.0)
        ->and(WarehouseStock::currentInventoryValue())->toBe(30.0);
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

it('aplica el costo vinculado al valor del stock restante cuando existe cantidad', function () {
    $fixture = valuedKardexFixture('LINKEDPOS');
    $fixture['stock']->update([
        'current_quantity' => 6,
        'average_unit_cost' => 5,
        'total_cost' => 30,
    ]);
    $context = valuedLinkedCostContext($fixture, 'POS', 12);

    app(WarehouseKardexService::class)->syncLinkedCosts($context['entry']);

    $movement = WarehouseKardexMovement::where(
        'source_item_id',
        $context['distribution']->id
    )->where('operation_type', 'warehouse_entry_linked_cost')->sole();

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(42.0)
        ->and((float) $fixture['stock']->fresh()->average_unit_cost)->toBe(7.0)
        ->and((float) $movement->total_cost_in)->toBe(12.0)
        ->and((float) $movement->balance_total_cost)->toBe(42.0)
        ->and(WarehouseStock::currentInventoryValue())->toBe(42.0);
});

it('conserva la trazabilidad del costo vinculado sin valorizar un lote agotado', function () {
    $fixture = valuedKardexFixture('LINKEDZERO');
    $context = valuedLinkedCostContext($fixture, 'ZERO', 12);
    valuedKardexMovement($fixture, 'KDX-LC-ZERO-OUT', '2026-08-02 09:00:00', [
        'movement_type' => 'exit',
        'operation_type' => 'test_exit',
        'quantity_out' => 10,
        'total_cost_out' => 50,
        'balance_quantity' => 0,
        'balance_total_cost' => 0,
    ]);

    app(WarehouseKardexService::class)->syncLinkedCosts($context['entry']);

    $movement = WarehouseKardexMovement::where(
        'source_item_id',
        $context['distribution']->id
    )->where('operation_type', 'warehouse_entry_linked_cost')->sole();

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(0.0)
        ->and((float) $fixture['stock']->fresh()->average_unit_cost)->toBe(0.0)
        ->and((float) $movement->total_cost_in)->toBe(12.0)
        ->and((float) $movement->balance_quantity)->toBe(0.0)
        ->and((float) $movement->balance_total_cost)->toBe(0.0);

    app(WarehouseKardexService::class)->recalculate(['warehouse_id' => $fixture['warehouseId']]);

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(0.0)
        ->and((float) $movement->fresh()->total_cost_in)->toBe(12.0)
        ->and((float) $movement->fresh()->balance_total_cost)->toBe(0.0);
});

it('excluye del valor actual todos los stocks agotados aunque tengan residuos historicos', function () {
    $first = valuedKardexFixture('KPI1');
    $second = valuedKardexFixture('KPI2');
    $first['stock']->update(['current_quantity' => 0, 'average_unit_cost' => 0, 'total_cost' => 33.48]);
    $second['stock']->update(['current_quantity' => 0, 'average_unit_cost' => 0, 'total_cost' => 36.52]);

    expect((float) WarehouseStock::where('status', 'ACTIVE')->sum('current_quantity'))->toBe(0.0)
        ->and((float) WarehouseStock::where('status', 'ACTIVE')->sum('total_cost'))->toBe(70.0)
        ->and(WarehouseStock::currentInventoryValue())->toBe(0.0);
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
