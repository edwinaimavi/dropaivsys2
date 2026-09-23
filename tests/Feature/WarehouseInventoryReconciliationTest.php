<?php

use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItem;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseInventoryReconciliationService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function reconciliationFixture(string $suffix, bool $inventory = true): array
{
    $now = now();
    $user = User::factory()->create();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA CUADRE '.$suffix,
        'ruc' => sprintf('20%09d', abs(crc32('REC-'.$suffix)) % 1_000_000_000),
        'status' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica', 'business_name' => 'CLIENTE CUADRE '.$suffix,
        'document_type' => 'RUC',
        'document_number' => sprintf('10%09d', abs(crc32('CLI-'.$suffix)) % 1_000_000_000),
        'status' => true, 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => substr('R'.$suffix, 0, 10), 'description' => 'MONEDA '.$suffix,
        'symbol' => 'S/', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('WR'.$suffix, 0, 30), 'name' => 'ALMACÉN CUADRE '.$suffix,
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId, 'warehouse_id' => $warehouseId,
        'sunat_establishment_code' => '0001', 'is_active' => true,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('R'.$suffix, 0, 20), 'description' => 'UNIDAD '.$suffix,
        'decimal_quantity' => true, 'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA '.$suffix, 'code' => substr('CR'.$suffix, 0, 20),
        'type' => $inventory ? 'PRODUCTO COMERCIAL' : 'SERVICIO',
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $articleCode = substr('AR'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode, 'category_id' => $categoryId, 'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO '.$suffix, 'billing_name' => 'ARTÍCULO '.$suffix,
        'item_kind' => $inventory ? 'product' : 'service', 'is_inventory_item' => $inventory,
        ...($inventory ? testSunatInventoryArticleFields($articleCode) : []),
        'has_batch' => true, 'has_expiration' => false, 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => substr('OC'.$suffix, 0, 20), 'company_id' => $companyId,
        'customer_id' => $customerId, 'order_type' => 'articles', 'currency_id' => $currencyId,
        'status' => 'entered', 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $orderItemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $orderId, 'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO '.$suffix, 'unit_id' => $unitId,
        'quantity' => 10, 'unit_price' => 20, 'status' => 'active',
        'created_at' => $now, 'updated_at' => $now,
    ]);

    return compact(
        'user', 'companyId', 'customerId', 'currencyId', 'warehouseId',
        'unitId', 'articleId', 'articleCode', 'orderId', 'orderItemId'
    );
}

function reconciliationStock(array $fixture, string $suffix, string $quantity): WarehouseStock
{
    return WarehouseStock::create([
        'stock_key' => 'REC-STOCK-'.$suffix.'-'.uniqid(),
        'company_id' => $fixture['companyId'], 'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'], 'unit_id' => $fixture['unitId'],
        'lot_number' => $suffix, 'current_quantity' => $quantity,
        'reserved_quantity' => 0, 'average_unit_cost' => 0, 'total_cost' => 0,
        'status' => 'ACTIVE',
    ]);
}

function reconciliationPool(array $fixture, string $quantity, string $cost, string $average): WarehouseValuationPool
{
    return WarehouseValuationPool::create([
        'company_id' => $fixture['companyId'], 'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'], 'current_quantity' => $quantity,
        'total_cost' => $cost, 'average_unit_cost' => $average,
    ]);
}

function reconciliationMovement(array $fixture, string $number, array $overrides = []): WarehouseKardexMovement
{
    return WarehouseKardexMovement::create(array_merge([
        'movement_number' => $number,
        'company_id' => $fixture['companyId'], 'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'], 'unit_id' => $fixture['unitId'],
        'sunat_establishment_code_snapshot' => '0001',
        'article_code_snapshot' => 'REC-SNAPSHOT',
        'article_description_snapshot' => 'ARTÍCULO SNAPSHOT',
        'sunat_existence_type_code_snapshot' => '01',
        'existence_catalog_code_snapshot' => '9', 'existence_code_snapshot' => 'REC-EXIST',
        'sunat_unit_code_snapshot' => 'NIU', 'unit_description_snapshot' => 'UNIDAD',
        'valuation_method_code_snapshot' => '1',
        'valuation_method_description_snapshot' => 'PROMEDIO PONDERADO',
        'movement_date' => '2026-09-20 10:00:00',
        'movement_type' => 'adjustment_in', 'operation_type' => 'manual_adjustment',
        'sunat_operation_type_code_snapshot' => '28',
        'source_key' => 'reconciliation:'.$number,
        'quantity_in' => '0.0000', 'quantity_out' => '0.0000',
        'unit_cost' => '0.000000', 'total_cost_in' => '0.00', 'total_cost_out' => '0.00',
        'balance_quantity' => '0.0000', 'average_unit_cost' => '0.000000',
        'balance_total_cost' => '0.00', 'status' => 'registered',
    ], $overrides));
}

function reconciliationAudit(array $fixture): array
{
    return app(WarehouseInventoryReconciliationService::class)->audit($fixture['companyId']);
}

function reconciliationBalanced(array $fixture, string $quantity = '10.0000', string $cost = '250.00'): WarehouseKardexMovement
{
    reconciliationStock($fixture, 'BAL', $quantity);
    reconciliationPool($fixture, $quantity, $cost, number_format((float) $cost / (float) $quantity, 6, '.', ''));

    return reconciliationMovement($fixture, 'REC-BAL-'.$fixture['articleId'], [
        'quantity_in' => $quantity, 'unit_cost' => number_format((float) $cost / (float) $quantity, 6, '.', ''),
        'total_cost_in' => $cost,
    ]);
}

function reconciliationDispatch(array $fixture, string $status, WarehouseStock $stock): array
{
    $dispatch = WarehouseDispatch::create([
        'company_id' => $fixture['companyId'], 'dispatch_number' => 'DSP-'.$fixture['articleId'].'-'.$status,
        'customer_purchase_order_id' => $fixture['orderId'], 'warehouse_id' => $fixture['warehouseId'],
        'dispatch_date' => '2026-09-20 12:00:00', 'dispatch_type' => 'customer_order',
        'status' => $status,
    ]);
    $item = WarehouseDispatchItem::create([
        'warehouse_dispatch_id' => $dispatch->id,
        'customer_purchase_order_item_id' => $fixture['orderItemId'],
        'warehouse_stock_id' => $stock->id, 'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'], 'quantity' => '5.0000',
        'unit_cost' => '20.000000', 'total_cost' => '100.00', 'status' => $status,
    ]);

    return compact('dispatch', 'item');
}

function reconciliationInvoice(array $fixture): array
{
    $invoice = ElectronicInvoice::create([
        'company_id' => $fixture['companyId'], 'customer_id' => $fixture['customerId'],
        'customer_purchase_order_id' => $fixture['orderId'], 'warehouse_id' => $fixture['warehouseId'],
        'currency_id' => $fixture['currencyId'], 'document_type' => '01',
        'serie' => 'F001', 'correlativo' => str_pad((string) $fixture['articleId'], 8, '0', STR_PAD_LEFT),
        'full_number' => 'F001-'.str_pad((string) $fixture['articleId'], 8, '0', STR_PAD_LEFT),
        'issue_date' => '2026-09-20', 'status' => ElectronicInvoice::STATUS_GENERATED,
        'is_voided' => false,
    ]);
    $item = ElectronicInvoiceItem::create([
        'electronic_invoice_id' => $invoice->id, 'article_id' => $fixture['articleId'],
        'item_number' => 1, 'description' => 'ARTÍCULO FACTURADO',
        'quantity' => '5.0000', 'unit_value' => '20.000000', 'unit_price' => '20.000000',
        'subtotal' => '100.00', 'line_total' => '100.00', 'status' => 'active',
    ]);

    return compact('invoice', 'item');
}

it('devuelve OK cuando stock Kardex y pool cuadran', function () {
    $fixture = reconciliationFixture('OK');
    reconciliationBalanced($fixture);

    $report = reconciliationAudit($fixture);
    $group = $report['groups'][0];

    expect($group['physical_quantity'])->toBe('10.0000')
        ->and($group['kardex_quantity'])->toBe('10.0000')
        ->and($group['pool_quantity'])->toBe('10.0000')
        ->and($group['kardex_total_cost'])->toBe('250.00')
        ->and($group['pool_total_cost'])->toBe('250.00')
        ->and($group['status'])->toBe('OK');
});

it('detecta diferencia entre stock físico y Kardex', function () {
    $fixture = reconciliationFixture('STOCK-DIFF');
    reconciliationStock($fixture, 'A', '10.0000');
    reconciliationPool($fixture, '9.0000', '225.00', '25.000000');
    reconciliationMovement($fixture, 'REC-STOCK-DIFF', ['quantity_in' => '9.0000', 'unit_cost' => '25.000000', 'total_cost_in' => '225.00']);

    $group = reconciliationAudit($fixture)['groups'][0];

    expect($group['quantity_difference_stock_vs_kardex'])->toBe('1.0000')
        ->and($group['status'])->toBe('ERROR');
});

it('detecta diferencia entre cantidad del pool y Kardex', function () {
    $fixture = reconciliationFixture('POOL-DIFF');
    reconciliationStock($fixture, 'A', '10.0000');
    reconciliationPool($fixture, '9.0000', '225.00', '25.000000');
    reconciliationMovement($fixture, 'REC-POOL-DIFF', ['quantity_in' => '10.0000', 'unit_cost' => '25.000000', 'total_cost_in' => '250.00']);

    $group = reconciliationAudit($fixture)['groups'][0];

    expect($group['quantity_difference_pool_vs_kardex'])->toBe('-1.0000')
        ->and($group['status'])->toBe('ERROR');
});

it('detecta diferencia valorizada entre pool y Kardex', function () {
    $fixture = reconciliationFixture('VALUE-DIFF');
    reconciliationStock($fixture, 'A', '10.0000');
    reconciliationPool($fixture, '10.0000', '240.00', '24.000000');
    reconciliationMovement($fixture, 'REC-VALUE-DIFF', ['quantity_in' => '10.0000', 'unit_cost' => '25.000000', 'total_cost_in' => '250.00']);

    $group = reconciliationAudit($fixture)['groups'][0];

    expect($group['value_difference_pool_vs_kardex'])->toBe('-10.00')
        ->and($group['status'])->toBe('ERROR');
});

it('consolida varios lotes contra un único pool global', function () {
    $fixture = reconciliationFixture('LOTS');
    reconciliationStock($fixture, 'LOTE-A', '4.0000');
    reconciliationStock($fixture, 'LOTE-B', '6.0000');
    reconciliationPool($fixture, '10.0000', '250.00', '25.000000');
    reconciliationMovement($fixture, 'REC-LOTS', ['quantity_in' => '10.0000', 'unit_cost' => '25.000000', 'total_cost_in' => '250.00']);

    $report = reconciliationAudit($fixture);

    expect($report['groups'])->toHaveCount(1)
        ->and($report['groups'][0]['physical_quantity'])->toBe('10.0000')
        ->and($report['groups'][0]['status'])->toBe('OK');
});

it('valida una sola SAL por despacho confirmado y detecta duplicidad', function () {
    $fixture = reconciliationFixture('DISPATCH');
    $stock = reconciliationStock($fixture, 'DSP', '0.0000');
    reconciliationPool($fixture, '0.0000', '0.00', '0.000000');
    reconciliationMovement($fixture, 'REC-DSP-OPEN', ['warehouse_stock_id' => $stock->id, 'quantity_in' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_in' => '100.00']);
    ['dispatch' => $dispatch, 'item' => $item] = reconciliationDispatch($fixture, WarehouseDispatch::STATUS_CONFIRMED, $stock);
    $exit = reconciliationMovement($fixture, 'REC-DSP-EXIT', [
        'warehouse_stock_id' => $stock->id, 'movement_type' => 'exit',
        'operation_type' => 'customer_order_dispatch', 'sunat_operation_type_code_snapshot' => '01',
        'source_type' => WarehouseDispatch::class, 'source_id' => $dispatch->id,
        'source_item_type' => WarehouseDispatchItem::class, 'source_item_id' => $item->id,
        'source_key' => 'customer-dispatch:'.$dispatch->id.':'.$item->id.':'.$stock->id,
        'quantity_out' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_out' => '100.00',
    ]);
    $item->update(['kardex_movement_id' => $exit->id]);

    expect(reconciliationAudit($fixture)['groups'][0]['status'])->toBe('OK');

    reconciliationMovement($fixture, 'REC-DSP-DUP', [
        'warehouse_stock_id' => $stock->id, 'movement_type' => 'exit',
        'operation_type' => 'customer_order_dispatch', 'sunat_operation_type_code_snapshot' => '01',
        'source_type' => WarehouseDispatch::class, 'source_id' => $dispatch->id,
        'source_item_type' => WarehouseDispatchItem::class, 'source_item_id' => $item->id,
        'source_key' => 'customer-dispatch-duplicate:'.$dispatch->id.':'.$item->id,
        'quantity_out' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_out' => '100.00',
    ]);
    $report = reconciliationAudit($fixture);

    expect($report['summary']['duplicate_issues'])->toBeGreaterThan(0)
        ->and($report['groups'][0]['status'])->toBe('ERROR');
});

it('acepta draft sin SAL y rechaza draft con salida', function () {
    $fixture = reconciliationFixture('DRAFT');
    $stock = reconciliationStock($fixture, 'DRAFT', '0.0000');
    ['dispatch' => $dispatch, 'item' => $item] = reconciliationDispatch($fixture, WarehouseDispatch::STATUS_DRAFT, $stock);

    expect(reconciliationAudit($fixture)['groups'][0]['status'])->toBe('OK');

    reconciliationMovement($fixture, 'REC-DRAFT-EXIT', [
        'warehouse_stock_id' => $stock->id, 'movement_type' => 'exit',
        'operation_type' => 'customer_order_dispatch', 'sunat_operation_type_code_snapshot' => '01',
        'source_type' => WarehouseDispatch::class, 'source_id' => $dispatch->id,
        'source_item_type' => WarehouseDispatchItem::class, 'source_item_id' => $item->id,
        'quantity_out' => '1.0000', 'unit_cost' => '20.000000', 'total_cost_out' => '20.00',
    ]);

    expect(reconciliationAudit($fixture)['groups'][0]['status'])->toBe('ERROR');
});

it('acepta factura respaldada por despacho sin segunda SAL y detecta salida adicional', function () {
    $fixture = reconciliationFixture('INVOICE-DSP');
    $stock = reconciliationStock($fixture, 'INV-DSP', '0.0000');
    reconciliationPool($fixture, '0.0000', '0.00', '0.000000');
    reconciliationMovement($fixture, 'REC-IDSP-OPEN', ['warehouse_stock_id' => $stock->id, 'quantity_in' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_in' => '100.00']);
    ['dispatch' => $dispatch, 'item' => $dispatchItem] = reconciliationDispatch($fixture, WarehouseDispatch::STATUS_CONFIRMED, $stock);
    $dispatchExit = reconciliationMovement($fixture, 'REC-IDSP-EXIT', [
        'warehouse_stock_id' => $stock->id, 'movement_type' => 'exit',
        'operation_type' => 'customer_order_dispatch', 'sunat_operation_type_code_snapshot' => '01',
        'source_type' => WarehouseDispatch::class, 'source_id' => $dispatch->id,
        'source_item_type' => WarehouseDispatchItem::class, 'source_item_id' => $dispatchItem->id,
        'quantity_out' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_out' => '100.00',
    ]);
    $dispatchItem->update(['kardex_movement_id' => $dispatchExit->id]);
    ['invoice' => $invoice, 'item' => $invoiceItem] = reconciliationInvoice($fixture);
    DB::table('electronic_invoice_item_dispatch_allocations')->insert([
        'electronic_invoice_item_id' => $invoiceItem->id,
        'warehouse_dispatch_item_id' => $dispatchItem->id,
        'quantity' => '5.0000', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(reconciliationAudit($fixture)['groups'][0]['status'])->toBe('OK');

    reconciliationMovement($fixture, 'REC-IDSP-DUP', [
        'warehouse_stock_id' => $stock->id, 'movement_type' => 'exit',
        'operation_type' => 'electronic_invoice', 'sunat_operation_type_code_snapshot' => '01',
        'source_type' => ElectronicInvoice::class, 'source_id' => $invoice->id,
        'source_item_type' => ElectronicInvoiceItem::class, 'source_item_id' => $invoiceItem->id,
        'quantity_out' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_out' => '100.00',
    ]);

    $report = reconciliationAudit($fixture);
    expect(collect($report['issues'])->pluck('type'))->toContain('dispatch_backed_invoice_with_exit')
        ->and($report['groups'][0]['status'])->toBe('ERROR');
});

it('valida factura directa con una SAL y detecta factura sin salida', function () {
    $fixture = reconciliationFixture('DIRECT');
    $stock = reconciliationStock($fixture, 'DIRECT', '0.0000');
    reconciliationPool($fixture, '0.0000', '0.00', '0.000000');
    reconciliationMovement($fixture, 'REC-DIRECT-OPEN', ['warehouse_stock_id' => $stock->id, 'quantity_in' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_in' => '100.00']);
    ['invoice' => $invoice, 'item' => $item] = reconciliationInvoice($fixture);
    reconciliationMovement($fixture, 'REC-DIRECT-EXIT', [
        'warehouse_stock_id' => $stock->id, 'movement_type' => 'exit',
        'operation_type' => 'electronic_invoice', 'sunat_operation_type_code_snapshot' => '01',
        'source_type' => ElectronicInvoice::class, 'source_id' => $invoice->id,
        'source_item_type' => ElectronicInvoiceItem::class, 'source_item_id' => $item->id,
        'quantity_out' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_out' => '100.00',
    ]);

    expect(reconciliationAudit($fixture)['groups'][0]['status'])->toBe('OK');

    $missing = reconciliationFixture('DIRECT-MISSING');
    reconciliationInvoice($missing);
    $missingReport = reconciliationAudit($missing);

    expect(collect($missingReport['issues'])->pluck('type'))->toContain('direct_invoice_without_exit')
        ->and($missingReport['groups'][0]['status'])->toBe('ERROR');
});

it('valida devolución vinculada y detecta devolución sin reingreso', function () {
    $fixture = reconciliationFixture('RETURN');
    $stock = reconciliationStock($fixture, 'RETURN', '2.0000');
    reconciliationPool($fixture, '2.0000', '50.00', '25.000000');
    reconciliationMovement($fixture, 'REC-RETURN-OPEN', ['warehouse_stock_id' => $stock->id, 'quantity_in' => '5.0000', 'unit_cost' => '25.000000', 'total_cost_in' => '125.00']);
    ['dispatch' => $dispatch, 'item' => $dispatchItem] = reconciliationDispatch($fixture, WarehouseDispatch::STATUS_CONFIRMED, $stock);
    $dispatchExit = reconciliationMovement($fixture, 'REC-RETURN-EXIT', [
        'warehouse_stock_id' => $stock->id, 'movement_type' => 'exit',
        'operation_type' => 'customer_order_dispatch', 'sunat_operation_type_code_snapshot' => '01',
        'source_type' => WarehouseDispatch::class, 'source_id' => $dispatch->id,
        'source_item_type' => WarehouseDispatchItem::class, 'source_item_id' => $dispatchItem->id,
        'quantity_out' => '5.0000', 'unit_cost' => '25.000000', 'total_cost_out' => '125.00',
    ]);
    $dispatchItem->update(['kardex_movement_id' => $dispatchExit->id]);
    $return = CustomerReturn::create([
        'return_number' => 'RET-'.$fixture['articleId'], 'company_id' => $fixture['companyId'],
        'customer_purchase_order_id' => $fixture['orderId'], 'warehouse_dispatch_id' => $dispatch->id,
        'warehouse_id' => $fixture['warehouseId'], 'return_date' => '2026-09-21',
        'status' => CustomerReturn::STATUS_CONFIRMED, 'reason' => 'other',
    ]);
    $returnItem = CustomerReturnItem::create([
        'customer_return_id' => $return->id, 'warehouse_dispatch_item_id' => $dispatchItem->id,
        'warehouse_stock_id' => $stock->id, 'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'], 'quantity' => '2.0000',
        'unit_cost_snapshot' => '25.000000', 'total_cost' => '50.00',
        'status' => CustomerReturnItem::STATUS_CONFIRMED,
    ]);
    $returnMovement = reconciliationMovement($fixture, 'REC-RETURN-IN', [
        'warehouse_stock_id' => $stock->id, 'movement_type' => 'entry',
        'operation_type' => 'customer_return', 'sunat_operation_type_code_snapshot' => '24',
        'source_type' => CustomerReturn::class, 'source_id' => $return->id,
        'source_item_type' => CustomerReturnItem::class, 'source_item_id' => $returnItem->id,
        'quantity_in' => '2.0000', 'unit_cost' => '25.000000', 'total_cost_in' => '50.00',
    ]);
    $returnItem->update(['kardex_movement_id' => $returnMovement->id]);

    expect(reconciliationAudit($fixture)['groups'][0]['status'])->toBe('OK');

    $returnMovement->delete();
    $report = reconciliationAudit($fixture);
    expect(collect($report['issues'])->pluck('type'))->toContain('customer_return_without_entry')
        ->and($report['groups'][0]['status'])->toBe('ERROR');
});

it('mantiene servicios fuera del inventario y detecta impacto indebido', function () {
    $fixture = reconciliationFixture('SERVICE', false);

    expect(reconciliationAudit($fixture)['summary']['error_groups'])->toBe(0);

    reconciliationStock($fixture, 'SERVICE', '1.0000');
    reconciliationPool($fixture, '1.0000', '10.00', '10.000000');
    reconciliationMovement($fixture, 'REC-SERVICE', ['quantity_in' => '1.0000', 'unit_cost' => '10.000000', 'total_cost_in' => '10.00']);
    reconciliationMovement($fixture, 'REC-SERVICE-ORPHAN', [
        'movement_type' => 'entry', 'operation_type' => 'warehouse_entry',
        'sunat_operation_type_code_snapshot' => '02',
        'source_type' => App\Models\WarehouseEntry::class, 'source_id' => 999999,
        'source_key' => 'orphan-service-source',
    ]);
    $report = reconciliationAudit($fixture);

    expect(collect($report['issues'])->pluck('type'))->toContain('non_inventory_item_with_inventory')
        ->and($report['summary']['orphan_issues'])->toBeGreaterThan(0)
        ->and($report['groups'][0]['status'])->toBe('ERROR');
});

it('clasifica snapshots históricos incompletos como WARNING cuando los saldos cuadran', function () {
    $fixture = reconciliationFixture('SNAPSHOT');
    reconciliationStock($fixture, 'SNAPSHOT', '10.0000');
    reconciliationPool($fixture, '10.0000', '250.00', '25.000000');
    reconciliationMovement($fixture, 'REC-SNAPSHOT', [
        'sunat_establishment_code_snapshot' => null, 'article_code_snapshot' => null,
        'article_description_snapshot' => null, 'sunat_existence_type_code_snapshot' => null,
        'sunat_unit_code_snapshot' => null, 'valuation_method_code_snapshot' => null,
        'valuation_method_description_snapshot' => null, 'sunat_operation_type_code_snapshot' => null,
        'quantity_in' => '10.0000', 'unit_cost' => '25.000000', 'total_cost_in' => '250.00',
    ]);

    $report = reconciliationAudit($fixture);

    expect($report['groups'][0]['status'])->toBe('WARNING')
        ->and($report['summary']['snapshot_issues'])->toBeGreaterThan(0)
        ->and($report['summary']['error_groups'])->toBe(0);
});

it('segrega completamente los datos por empresa', function () {
    $companyA = reconciliationFixture('COMPANY-A');
    $companyB = reconciliationFixture('COMPANY-B');
    reconciliationBalanced($companyA, '3.0000', '60.00');
    reconciliationBalanced($companyB, '9.0000', '270.00');

    $report = reconciliationAudit($companyA);

    expect($report['groups'])->toHaveCount(1)
        ->and($report['groups'][0]['company_id'])->toBe($companyA['companyId'])
        ->and($report['groups'][0]['physical_quantity'])->toBe('3.0000');
});

it('muestra el endpoint autorizado con el reporte de conciliación', function () {
    $fixture = reconciliationFixture('HTTP');
    reconciliationBalanced($fixture);
    Permission::findOrCreate('admin.kardex.index', 'web');
    $fixture['user']->givePermissionTo('admin.kardex.index');

    $response = $this->actingAs($fixture['user'])->get(route('admin.kardex.reconciliation', [
        'consult' => 1, 'company_id' => $fixture['companyId'],
    ]));

    $response->assertOk()
        ->assertSee('Cuadre de Inventario')
        ->assertSee('STOCK')
        ->assertSee('KARDEX')
        ->assertSee('Valorizaci&oacute;n', false);
});
