<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerReturn;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Services\CustomerReturnService;
use App\Services\WarehouseDispatchService;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function accountingSnapshotColumns(): array
{
    return [
        'sunat_establishment_code_snapshot',
        'article_code_snapshot',
        'article_description_snapshot',
        'sunat_existence_type_code_snapshot',
        'existence_catalog_code_snapshot',
        'existence_code_snapshot',
        'sunat_unit_code_snapshot',
        'unit_description_snapshot',
        'valuation_method_code_snapshot',
        'valuation_method_description_snapshot',
    ];
}

function accountingSnapshotFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);

    $valuationCatalogId = DB::table('sunat_catalogs')->insertGetId([
        'code' => '14',
        'name' => 'MÉTODO DE VALORIZACIÓN',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $valuationMethodItemId = DB::table('sunat_catalog_items')->insertGetId([
        'sunat_catalog_id' => $valuationCatalogId,
        'catalog_code' => '14',
        'item_code' => '1',
        'description' => 'PROMEDIO PONDERADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA SNAPSHOT '.$suffix,
        'ruc' => '20'.str_pad((string) crc32('SNAP'.$suffix), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'inventory_valuation_method_item_id' => $valuationMethodItemId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'SN'.$suffix,
        'name' => 'ALMACÉN SNAPSHOT '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'sunat_establishment_code' => '0001',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $sunatUnitItemId = testSunatUnitItemId();
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'UND-'.$suffix,
        'description' => 'UNIDAD SNAPSHOT '.$suffix,
        'sunat_unit_item_id' => $sunatUnitItemId,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA SNAPSHOT '.$suffix,
        'code' => 'CS-'.$suffix,
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = 'AS-'.$suffix;
    $inventoryMasterIds = testSunatInventoryMasterIds();
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO LEGAL SNAPSHOT '.$suffix,
        'billing_name' => 'ARTÍCULO SNAPSHOT '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...$inventoryMasterIds,
        'sunat_inventory_catalog_code' => $articleCode,
        'sales_tax_affectation_code' => '10',
        'is_taxable' => true,
        'has_batch' => true,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '10'.str_pad((string) crc32('SUP-SNAP'.$suffix), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR SNAPSHOT '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'P'.$suffix,
        'description' => 'SOLES SNAPSHOT '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE SNAPSHOT '.$suffix,
        'document_type' => 'RUC',
        'document_number' => '10'.str_pad((string) crc32('CLI-SNAP'.$suffix), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'OC-SNAPSHOT-'.$suffix,
        'company_id' => $companyId,
        'customer_id' => $customerId,
        'order_type' => 'articles',
        'currency_id' => $currencyId,
        'status' => CustomerPurchaseOrder::STATUS_ENTERED,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderItemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $orderId,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO SNAPSHOT '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 10,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-SNAPSHOT-'.$suffix,
        'entry_mode' => 'supplier_invoice',
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'document_type' => 'FACTURA',
        'status' => 'registered',
    ]);
    $entryItem = WarehouseEntryItem::create([
        'warehouse_entry_id' => $entry->id,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO SNAPSHOT '.$suffix,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-'.$suffix,
        'quantity' => 10,
        'unit_price' => 20,
        'line_total' => 200,
        'status' => 'active',
    ]);
    DB::table('warehouse_entry_item_allocations')->insert([
        'warehouse_entry_id' => $entry->id,
        'warehouse_entry_item_id' => $entryItem->id,
        'customer_purchase_order_id' => $orderId,
        'customer_purchase_order_item_id' => $orderItemId,
        'article_id' => $articleId,
        'quantity_allocated' => 10,
        'unit_cost' => 20,
        'total_cost' => 200,
        'allocation_type' => 'customer_order',
        'status' => 'active',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return compact(
        'user',
        'companyId',
        'warehouseId',
        'unitId',
        'articleId',
        'articleCode',
        'entry',
        'orderItemId',
        'sunatUnitItemId',
        'inventoryMasterIds',
        'valuationMethodItemId'
    ) + ['order' => CustomerPurchaseOrder::findOrFail($orderId)];
}

function registerAccountingSnapshotEntry(array $fixture): WarehouseKardexMovement
{
    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($fixture['entry']);

    return WarehouseKardexMovement::query()
        ->where('operation_type', 'warehouse_entry')
        ->sole();
}

function expectedAccountingSnapshots(array $fixture): array
{
    return [
        'sunat_establishment_code_snapshot' => '0001',
        'article_code_snapshot' => $fixture['articleCode'],
        'article_description_snapshot' => 'ARTÍCULO SNAPSHOT '.str_replace('AS-', '', $fixture['articleCode']),
        'sunat_existence_type_code_snapshot' => '01',
        'existence_catalog_code_snapshot' => '9',
        'existence_code_snapshot' => $fixture['articleCode'],
        'sunat_unit_code_snapshot' => 'NIU',
        'unit_description_snapshot' => 'UNIDAD SNAPSHOT '.str_replace('AS-', '', $fixture['articleCode']),
        'valuation_method_code_snapshot' => '1',
        'valuation_method_description_snapshot' => 'PROMEDIO PONDERADO',
    ];
}

function confirmedAccountingSnapshotDispatch(array $fixture): WarehouseDispatch
{
    registerAccountingSnapshotEntry($fixture);
    $stock = WarehouseStock::query()->sole();
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], [
        'warehouse_id' => $fixture['warehouseId'],
        'dispatch_date' => now(),
        'responsible_user_id' => $fixture['user']->id,
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'customer_purchase_order_item_id' => $fixture['orderItemId'],
            'warehouse_stock_id' => $stock->id,
            'quantity' => 4,
        ]],
    ]);

    return $service->confirm($draft);
}

function confirmedAccountingSnapshotReturn(array $fixture, WarehouseDispatch $dispatch): CustomerReturn
{
    $dispatchItem = $dispatch->items()->sole();
    $service = app(CustomerReturnService::class);
    $draft = $service->createDraft($dispatch, [
        'idempotency_key' => (string) Str::uuid(),
        'return_date' => now(),
        'reason' => 'customer_rejection',
        'received_by_user_id' => $fixture['user']->id,
        'items' => [[
            'warehouse_dispatch_item_id' => $dispatchItem->id,
            'quantity' => 2,
        ]],
    ]);

    return $service->confirm($draft);
}

it('congela todos los snapshots contables SUNAT en una entrada nueva', function () {
    $fixture = accountingSnapshotFixture('ENTRY');
    $movement = registerAccountingSnapshotEntry($fixture);

    expect(Schema::hasColumns('warehouse_kardex_movements', accountingSnapshotColumns()))->toBeTrue()
        ->and($movement->only(accountingSnapshotColumns()))->toBe(expectedAccountingSnapshots($fixture));
});

it('mantiene los snapshots aunque cambien posteriormente los maestros', function () {
    $fixture = accountingSnapshotFixture('FROZEN');
    $movement = registerAccountingSnapshotEntry($fixture);
    $expected = expectedAccountingSnapshots($fixture);

    DB::table('company_warehouses')
        ->where('company_id', $fixture['companyId'])
        ->where('warehouse_id', $fixture['warehouseId'])
        ->update(['sunat_establishment_code' => '9999']);
    DB::table('articles')->where('id', $fixture['articleId'])->update([
        'code' => 'ART-CAMBIADO',
        'billing_name' => 'ARTÍCULO CAMBIADO',
        'sunat_inventory_catalog_code' => 'EXISTENCIA-CAMBIADA',
    ]);
    DB::table('units')->where('id', $fixture['unitId'])->update([
        'description' => 'UNIDAD CAMBIADA',
    ]);
    DB::table('sunat_catalog_items')->where('id', $fixture['inventoryMasterIds']['sunat_existence_type_item_id'])->update([
        'item_code' => '99',
    ]);
    DB::table('sunat_catalog_items')->where('id', $fixture['inventoryMasterIds']['sunat_inventory_catalog_item_id'])->update([
        'item_code' => '8',
    ]);
    DB::table('sunat_catalog_items')->where('id', $fixture['sunatUnitItemId'])->update([
        'item_code' => 'ZZZ',
    ]);
    DB::table('sunat_catalog_items')->where('id', $fixture['valuationMethodItemId'])->update([
        'item_code' => '2',
        'description' => 'IDENTIFICACIÓN ESPECÍFICA',
    ]);

    expect($movement->fresh()->only(accountingSnapshotColumns()))->toBe($expected);
});

it('copia en la reversa los snapshots del movimiento original', function () {
    $fixture = accountingSnapshotFixture('REVERSAL');
    $original = registerAccountingSnapshotEntry($fixture);
    $originalSnapshots = $original->only(accountingSnapshotColumns());

    DB::table('articles')->where('id', $fixture['articleId'])->update([
        'code' => 'CODIGO-POSTERIOR',
        'billing_name' => 'DESCRIPCIÓN POSTERIOR',
    ]);

    app(WarehouseKardexService::class)->reverseWarehouseEntry($fixture['entry'], 'Reversa focal snapshots');
    $reversal = WarehouseKardexMovement::query()
        ->where('operation_type', 'warehouse_entry_cancel')
        ->sole();

    expect($reversal->only(accountingSnapshotColumns()))->toBe($originalSnapshots);
});

it('aplica el mismo snapshot a entrada ajuste positivo y movimiento de salida', function () {
    $fixture = accountingSnapshotFixture('TYPES');
    $entryMovement = registerAccountingSnapshotEntry($fixture);
    $stock = $entryMovement->stock()->firstOrFail();
    $expected = expectedAccountingSnapshots($fixture);

    $adjustmentIn = app(WarehouseKardexService::class)->registerAdjustment(
        $stock,
        $fixture['companyId'],
        'adjustment_in',
        2,
        25,
        'Ajuste positivo snapshot',
        'snapshot-adjustment-in'
    );
    $adjustmentOut = app(WarehouseKardexService::class)->registerAdjustment(
        $stock->fresh(),
        $fixture['companyId'],
        'adjustment_out',
        1,
        null,
        'Salida por ajuste snapshot',
        'snapshot-adjustment-out'
    );

    expect($entryMovement->only(accountingSnapshotColumns()))->toBe($expected)
        ->and($adjustmentIn->only(accountingSnapshotColumns()))->toBe($expected)
        ->and($adjustmentOut->only(accountingSnapshotColumns()))->toBe($expected)
        ->and((float) $adjustmentOut->quantity_out)->toBe(1.0);
});

it('congela los snapshots en una SAL confirmada', function () {
    $fixture = accountingSnapshotFixture('DISPATCH');
    $dispatch = confirmedAccountingSnapshotDispatch($fixture);
    $movement = $dispatch->items()->sole()->kardexMovement;

    expect($movement->only(accountingSnapshotColumns()))->toBe(expectedAccountingSnapshots($fixture));
});

it('preserva snapshots de la SAL en devolución y reversa aunque cambien los maestros', function () {
    $fixture = accountingSnapshotFixture('RETURN');
    $dispatch = confirmedAccountingSnapshotDispatch($fixture);
    $dispatchMovement = $dispatch->items()->sole()->kardexMovement;
    $historicalSnapshots = $dispatchMovement->only(accountingSnapshotColumns());

    DB::table('articles')->where('id', $fixture['articleId'])->update([
        'code' => 'CODIGO-NUEVO',
        'billing_name' => 'ARTÍCULO NUEVO',
    ]);
    DB::table('units')->where('id', $fixture['unitId'])->update([
        'description' => 'UNIDAD NUEVA',
    ]);
    DB::table('sunat_catalog_items')->where('id', $fixture['sunatUnitItemId'])->update([
        'item_code' => 'ZZZ',
    ]);

    $return = confirmedAccountingSnapshotReturn($fixture, $dispatch);
    $returnMovement = $return->items()->sole()->kardexMovement;
    $reversed = app(CustomerReturnService::class)->reverse($return, 'Reversa focal snapshots');
    $reversalMovement = $reversed->items()->sole()->reversalKardexMovement;

    expect($returnMovement->only(accountingSnapshotColumns()))->toBe($historicalSnapshots)
        ->and($reversalMovement->only(accountingSnapshotColumns()))->toBe($historicalSnapshots)
        ->and($dispatchMovement->fresh()->only(accountingSnapshotColumns()))->toBe($historicalSnapshots);
});
