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

function customerReturnValuationPoolFixture(string $suffix, bool $inventory = true): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA DEV PPM '.$suffix,
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE DEV PPM '.$suffix,
        'document_type' => 'RUC',
        'document_number' => '10'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => substr('R'.$suffix, 0, 10),
        'description' => 'SOLES DEV PPM '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('U'.$suffix, 0, 10),
        'description' => 'UNIDAD DEV PPM '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => ($inventory ? 'PRODUCTO ' : 'SERVICIO ').$suffix,
        'code' => substr('RC'.$suffix, 0, 20),
        'type' => $inventory ? 'PRODUCTO COMERCIAL' : 'SERVICIO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('RA'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO DEV PPM '.$suffix,
        'billing_name' => 'ARTÍCULO DEV PPM '.$suffix,
        'item_kind' => $inventory ? 'product' : 'service',
        'is_inventory_item' => $inventory,
        ...($inventory ? testSunatInventoryArticleFields($articleCode) : []),
        'has_batch' => true,
        'has_expiration' => true,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('RW'.$suffix, 0, 20),
        'name' => 'ALMACÉN DEV PPM '.$suffix,
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
        'business_name' => 'PROVEEDOR DEV PPM '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'OC-RET-'.$suffix,
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
        'billing_name_snapshot' => 'ARTÍCULO DEV PPM '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 4,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-RET-'.$suffix,
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
        'billing_name_snapshot' => 'ARTÍCULO DEV PPM '.$suffix,
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
        'dispatch_number' => 'SAL-RET-'.$suffix,
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
        'movement_number' => 'KDX-RET-'.$suffix,
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
        'companyId',
        'warehouseId',
        'articleId',
        'originalStock',
        'otherStock',
        'dispatch',
        'dispatchItem',
        'movement',
        'pool'
    );
}

function customerReturnValuationPoolDraft(array $fixture, float $quantity): CustomerReturn
{
    return app(CustomerReturnService::class)->createDraft($fixture['dispatch'], [
        'idempotency_key' => (string) Str::uuid(),
        'return_date' => now(),
        'reason' => 'customer_rejection',
        'received_by_user_id' => $fixture['user']->id,
        'items' => [[
            'warehouse_dispatch_item_id' => $fixture['dispatchItem']->id,
            'quantity' => $quantity,
        ]],
    ]);
}

it('reingresa al lote original al costo histórico de la SAL y recalcula el PPM', function () {
    $fixture = customerReturnValuationPoolFixture('HIST');
    $originalMovement = $fixture['movement']->fresh()->getAttributes();

    $return = app(CustomerReturnService::class)->confirm(
        customerReturnValuationPoolDraft($fixture, 2)
    );
    $item = $return->items()->firstOrFail();
    $returnMovement = $item->kardexMovement;
    $pool = $fixture['pool']->fresh();

    expect((float) $fixture['originalStock']->fresh()->current_quantity)->toBe(2.0)
        ->and((float) $fixture['otherStock']->fresh()->current_quantity)->toBe(10.0)
        ->and($returnMovement->warehouse_stock_id)->toBe($fixture['originalStock']->id)
        ->and((float) $item->unit_cost_snapshot)->toBe(25.0)
        ->and((float) $item->total_cost)->toBe(50.0)
        ->and((float) $returnMovement->total_cost_in)->toBe(50.0)
        ->and((float) $pool->current_quantity)->toBe(12.0)
        ->and((float) $pool->total_cost)->toBe(350.0)
        ->and((float) $pool->average_unit_cost)->toBe(29.166667)
        ->and((float) $pool->total_cost)->not->toBe(360.0)
        ->and($fixture['movement']->fresh()->getAttributes())->toBe($originalMovement);
});

it('valoriza una devolución parcial con cantidad por costo unitario histórico', function () {
    $fixture = customerReturnValuationPoolFixture('PART');

    $return = app(CustomerReturnService::class)->confirm(
        customerReturnValuationPoolDraft($fixture, 1.5)
    );
    $item = $return->items()->firstOrFail();

    expect((float) $item->unit_cost_snapshot)->toBe(25.0)
        ->and((float) $item->total_cost)->toBe(37.5)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(11.5)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(337.5);
});

it('no modifica el pool para servicios no inventariables', function () {
    $fixture = customerReturnValuationPoolFixture('SERV', false);
    $poolBefore = $fixture['pool']->fresh()->getAttributes();

    expect(fn () => customerReturnValuationPoolDraft($fixture, 1))
        ->toThrow(ValidationException::class);

    expect($fixture['pool']->fresh()->getAttributes())->toBe($poolBefore)
        ->and(CustomerReturn::count())->toBe(0);
});

it('hace rollback de stock pool y Kardex si falla la creación del movimiento', function () {
    $fixture = customerReturnValuationPoolFixture('ROLL');
    $return = customerReturnValuationPoolDraft($fixture, 2);
    $detail = $return->items()->firstOrFail();
    $fixture['movement']->update([
        'source_key' => "customer-return:{$return->id}:{$detail->id}",
    ]);
    $movementCount = WarehouseKardexMovement::count();

    expect(fn () => app(CustomerReturnService::class)->confirm($return))
        ->toThrow(QueryException::class);

    expect((float) $fixture['originalStock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(300.0)
        ->and(WarehouseKardexMovement::count())->toBe($movementCount)
        ->and($return->fresh()->isDraft())->toBeTrue();
});

it('no incrementa dos veces el pool al reconfirmar la misma devolución', function () {
    $fixture = customerReturnValuationPoolFixture('ONCE');
    $service = app(CustomerReturnService::class);
    $return = $service->confirm(customerReturnValuationPoolDraft($fixture, 2));
    $movementCount = WarehouseKardexMovement::count();

    $service->confirm($return);

    expect((float) $fixture['originalStock']->fresh()->current_quantity)->toBe(2.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(12.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(350.0)
        ->and(WarehouseKardexMovement::count())->toBe($movementCount);
});
