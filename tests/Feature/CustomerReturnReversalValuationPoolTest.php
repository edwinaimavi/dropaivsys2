<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerReturn;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\CustomerReturnService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function customerReturnReversalPoolFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA REV DEV '.$suffix,
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE REV DEV '.$suffix,
        'document_type' => 'RUC',
        'document_number' => '10'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => substr('V'.$suffix, 0, 10),
        'description' => 'SOLES REV DEV '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('U'.$suffix, 0, 10),
        'description' => 'UNIDAD REV DEV '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTO REV DEV '.$suffix,
        'code' => substr('VC'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('VA'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO REV DEV '.$suffix,
        'billing_name' => 'ARTÍCULO REV DEV '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => true,
        'has_expiration' => true,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('VW'.$suffix, 0, 20),
        'name' => 'ALMACÉN REV DEV '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '15'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR REV DEV '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'OC-RV-'.$suffix,
        'company_id' => $companyId,
        'customer_id' => $customerId,
        'order_type' => 'articles',
        'currency_id' => $currencyId,
        'status' => CustomerPurchaseOrder::STATUS_ATTENDED,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderItemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $orderId,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO REV DEV '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 4,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-RV-'.$suffix,
        'entry_mode' => 'supplier_invoice',
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'status' => 'registered',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryItemId = DB::table('warehouse_entry_items')->insertGetId([
        'warehouse_entry_id' => $entryId,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO REV DEV '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 4,
        'unit_price' => 25,
        'line_total' => 100,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('warehouse_entry_item_allocations')->insert([
        'warehouse_entry_id' => $entryId,
        'warehouse_entry_item_id' => $entryItemId,
        'customer_purchase_order_id' => $orderId,
        'customer_purchase_order_item_id' => $orderItemId,
        'article_id' => $articleId,
        'quantity_allocated' => 4,
        'unit_cost' => 25,
        'total_cost' => 100,
        'allocation_type' => 'customer_order',
        'status' => 'active',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $originalStock = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-ORIGINAL|2030-12-31",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-ORIGINAL',
        'expiration_date' => '2030-12-31',
        'current_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
        'status' => 'ACTIVE',
    ]);
    $otherStock = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-ACTUAL|2031-12-31",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-ACTUAL',
        'expiration_date' => '2031-12-31',
        'current_quantity' => 10,
        'average_unit_cost' => 30,
        'total_cost' => 300,
        'status' => 'ACTIVE',
    ]);
    $dispatch = WarehouseDispatch::create([
        'company_id' => $companyId,
        'dispatch_number' => 'SAL-RV-'.$suffix,
        'idempotency_key' => (string) Str::uuid(),
        'customer_purchase_order_id' => $orderId,
        'warehouse_id' => $warehouseId,
        'dispatch_date' => $now,
        'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER,
        'status' => WarehouseDispatch::STATUS_CONFIRMED,
        'confirmed_at' => $now,
        'confirmed_by' => $user->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $dispatchItem = WarehouseDispatchItem::create([
        'warehouse_dispatch_id' => $dispatch->id,
        'customer_purchase_order_item_id' => $orderItemId,
        'warehouse_stock_id' => $originalStock->id,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-ORIGINAL',
        'expiration_date' => '2030-12-31',
        'quantity' => 4,
        'unit_cost' => 25,
        'total_cost' => 100,
        'status' => WarehouseDispatchItem::STATUS_CONFIRMED,
    ]);
    $movement = WarehouseKardexMovement::create([
        'movement_number' => 'KDX-RV-'.$suffix,
        'company_id' => $companyId,
        'warehouse_stock_id' => $originalStock->id,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-ORIGINAL',
        'expiration_date' => '2030-12-31',
        'movement_date' => $now,
        'movement_type' => 'exit',
        'operation_type' => 'customer_order_dispatch',
        'source_type' => WarehouseDispatch::class,
        'source_id' => $dispatch->id,
        'source_item_type' => WarehouseDispatchItem::class,
        'source_item_id' => $dispatchItem->id,
        'source_key' => "customer-dispatch:{$dispatch->id}:{$dispatchItem->id}:{$originalStock->id}",
        'quantity_in' => 0,
        'quantity_out' => 4,
        'balance_quantity' => 0,
        'unit_cost' => 25,
        'total_cost_in' => 0,
        'total_cost_out' => 100,
        'average_unit_cost' => 0,
        'balance_total_cost' => 0,
        'currency_id' => $currencyId,
        'status' => 'registered',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $dispatchItem->update(['kardex_movement_id' => $movement->id]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'current_quantity' => 10,
        'average_unit_cost' => 30,
        'total_cost' => 300,
    ]);

    return compact(
        'user',
        'originalStock',
        'otherStock',
        'dispatch',
        'dispatchItem',
        'movement',
        'pool'
    );
}

function confirmedCustomerReturnForPool(array $fixture, float $quantity): CustomerReturn
{
    $service = app(CustomerReturnService::class);
    $draft = $service->createDraft($fixture['dispatch'], [
        'idempotency_key' => (string) Str::uuid(),
        'return_date' => now(),
        'reason' => 'customer_rejection',
        'received_by_user_id' => $fixture['user']->id,
        'items' => [[
            'warehouse_dispatch_item_id' => $fixture['dispatchItem']->id,
            'quantity' => $quantity,
        ]],
    ]);

    return $service->confirm($draft);
}

it('revierte exactamente la cantidad y el costo histórico reincorporado al pool', function () {
    $fixture = customerReturnReversalPoolFixture('EXACT');
    $return = confirmedCustomerReturnForPool($fixture, 2);
    $detail = $return->items()->firstOrFail();

    expect((float) $fixture['pool']->fresh()->average_unit_cost)->toBe(29.166667);

    $reversed = app(CustomerReturnService::class)->reverse($return, 'Reversa contable exacta');
    $reversalMovement = $reversed->items()->firstOrFail()->reversalKardexMovement;
    $pool = $fixture['pool']->fresh();

    expect((float) $pool->current_quantity)->toBe(10.0)
        ->and((float) $pool->total_cost)->toBe(300.0)
        ->and((float) $pool->average_unit_cost)->toBe(30.0)
        ->and((float) $detail->total_cost)->toBe(50.0)
        ->and((float) $reversalMovement->total_cost_out)->toBe(50.0)
        ->and((float) $reversalMovement->total_cost_out)->not->toBe(58.33);
});

it('retira la devolución del mismo lote físico original', function () {
    $fixture = customerReturnReversalPoolFixture('LOT');
    $return = confirmedCustomerReturnForPool($fixture, 2);

    app(CustomerReturnService::class)->reverse($return, 'Reversa del lote original');
    $movement = $return->fresh('items.reversalKardexMovement')
        ->items->firstOrFail()->reversalKardexMovement;

    expect((float) $fixture['originalStock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['otherStock']->fresh()->current_quantity)->toBe(10.0)
        ->and($movement->warehouse_stock_id)->toBe($fixture['originalStock']->id)
        ->and($movement->lot_number)->toBe('LOTE-ORIGINAL');
});

it('revierte exactamente una devolución parcial', function () {
    $fixture = customerReturnReversalPoolFixture('PART');
    $return = confirmedCustomerReturnForPool($fixture, 1.5);

    expect((float) $fixture['pool']->fresh()->current_quantity)->toBe(11.5)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(337.5);

    $reversed = app(CustomerReturnService::class)->reverse($return, 'Reversa parcial exacta');
    $movement = $reversed->items()->firstOrFail()->reversalKardexMovement;

    expect((float) $fixture['pool']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(300.0)
        ->and((float) $fixture['pool']->fresh()->average_unit_cost)->toBe(30.0)
        ->and((float) $movement->quantity_out)->toBe(1.5)
        ->and((float) $movement->total_cost_out)->toBe(37.5);
});

it('hace rollback de stock pool y Kardex si falla el movimiento de reversa', function () {
    $fixture = customerReturnReversalPoolFixture('ROLL');
    $return = confirmedCustomerReturnForPool($fixture, 2);
    $detail = $return->items()->firstOrFail();
    $entryMovement = $detail->kardexMovement;
    $entryMovement->update([
        'source_key' => "customer-return-reversal:{$return->id}:{$detail->id}",
    ]);
    $movementCount = WarehouseKardexMovement::count();

    expect(fn () => app(CustomerReturnService::class)->reverse($return, 'Reversa que debe fallar'))
        ->toThrow(QueryException::class);

    expect((float) $fixture['originalStock']->fresh()->current_quantity)->toBe(2.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(12.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(350.0)
        ->and(WarehouseKardexMovement::count())->toBe($movementCount)
        ->and($return->fresh()->isConfirmed())->toBeTrue();
});

it('bloquea una segunda reversa sin volver a modificar el pool', function () {
    $fixture = customerReturnReversalPoolFixture('ONCE');
    $service = app(CustomerReturnService::class);
    $return = confirmedCustomerReturnForPool($fixture, 2);
    $service->reverse($return, 'Primera reversa válida');
    $movementCount = WarehouseKardexMovement::count();

    expect(fn () => $service->reverse($return, 'Segunda reversa inválida'))
        ->toThrow(ValidationException::class);

    expect((float) $fixture['pool']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(300.0)
        ->and(WarehouseKardexMovement::count())->toBe($movementCount);
});
