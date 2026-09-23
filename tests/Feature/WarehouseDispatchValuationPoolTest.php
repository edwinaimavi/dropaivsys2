<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseDispatchService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function dispatchValuationPoolFixture(string $suffix, bool $inventory = true): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA DESPACHO PPM '.$suffix,
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE PPM '.$suffix,
        'document_type' => 'RUC',
        'document_number' => '10'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'D'.$suffix,
        'description' => 'SOLES DESPACHO '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'U'.$suffix,
        'description' => 'UNIDAD DESPACHO '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => ($inventory ? 'PRODUCTO ' : 'SERVICIO ').$suffix,
        'code' => 'DC'.$suffix,
        'type' => $inventory ? 'PRODUCTO COMERCIAL' : 'SERVICIO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = 'DA'.$suffix;
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO DESPACHO '.$suffix,
        'billing_name' => 'ARTÍCULO DESPACHO '.$suffix,
        'item_kind' => $inventory ? 'product' : 'service',
        'is_inventory_item' => $inventory,
        ...($inventory ? testSunatInventoryArticleFields($articleCode) : []),
        'has_batch' => true,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'DW'.$suffix,
        'name' => 'ALMACÉN DESPACHO '.$suffix,
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
        'business_name' => 'PROVEEDOR DESPACHO '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'OC-DP-'.$suffix,
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
        'billing_name_snapshot' => 'ARTÍCULO DESPACHO '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 20,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-DP-'.$suffix,
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
        'billing_name_snapshot' => 'ARTÍCULO DESPACHO '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 20,
        'unit_price' => 25,
        'line_total' => 500,
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
        'quantity_allocated' => 20,
        'unit_cost' => 25,
        'total_cost' => 500,
        'allocation_type' => 'customer_order',
        'status' => 'active',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stockA = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-A|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-A',
        'current_quantity' => 10,
        'average_unit_cost' => 20,
        'total_cost' => 200,
        'status' => 'ACTIVE',
    ]);
    $stockB = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-B|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-B',
        'current_quantity' => 10,
        'average_unit_cost' => 30,
        'total_cost' => 300,
        'status' => 'ACTIVE',
    ]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'current_quantity' => 20,
        'average_unit_cost' => 25,
        'total_cost' => 500,
    ]);

    return compact(
        'user',
        'companyId',
        'warehouseId',
        'articleId',
        'orderItemId',
        'stockA',
        'stockB',
        'pool'
    ) + ['order' => CustomerPurchaseOrder::findOrFail($orderId)];
}

function dispatchValuationPoolPayload(array $fixture, array $rows): array
{
    return [
        'warehouse_id' => $fixture['warehouseId'],
        'dispatch_date' => now(),
        'responsible_user_id' => $fixture['user']->id,
        'idempotency_key' => (string) Str::uuid(),
        'items' => array_map(fn (array $row) => [
            'customer_purchase_order_item_id' => $fixture['orderItemId'],
            'warehouse_stock_id' => $row['stock']->id,
            'quantity' => $row['quantity'],
        ], $rows),
    ];
}

it('valoriza la SAL al PPM global y no al costo legacy del lote', function () {
    $fixture = dispatchValuationPoolFixture('GLOBAL');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchValuationPoolPayload($fixture, [
        ['stock' => $fixture['stockA'], 'quantity' => 4],
    ]));

    $dispatch = $service->confirm($draft);
    $service->confirm($draft);
    $movement = $dispatch->items()->firstOrFail()->kardexMovement()->firstOrFail();
    $pool = $fixture['pool']->fresh();

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(6.0)
        ->and((float) $fixture['stockB']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $pool->current_quantity)->toBe(16.0)
        ->and((float) $pool->total_cost)->toBe(400.0)
        ->and((float) $pool->average_unit_cost)->toBe(25.0)
        ->and((float) $movement->unit_cost)->toBe(25.0)
        ->and((float) $movement->total_cost_out)->toBe(100.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(1);
});

it('descuenta dos lotes físicos desde un único pool PPM', function () {
    $fixture = dispatchValuationPoolFixture('LOTS');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchValuationPoolPayload($fixture, [
        ['stock' => $fixture['stockA'], 'quantity' => 2],
        ['stock' => $fixture['stockB'], 'quantity' => 3],
    ]));

    $dispatch = $service->confirm($draft);
    $movements = $dispatch->items()->with('kardexMovement')->get()->pluck('kardexMovement');
    $pool = $fixture['pool']->fresh();

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(8.0)
        ->and((float) $fixture['stockB']->fresh()->current_quantity)->toBe(7.0)
        ->and(WarehouseValuationPool::count())->toBe(1)
        ->and((float) $pool->current_quantity)->toBe(15.0)
        ->and((float) $pool->total_cost)->toBe(375.0)
        ->and((float) $pool->average_unit_cost)->toBe(25.0)
        ->and($movements->map(fn ($movement) => (float) $movement->unit_cost)->all())
        ->toBe([25.0, 25.0])
        ->and($movements->map(fn ($movement) => (float) $movement->total_cost_out)->all())
        ->toBe([50.0, 75.0]);
});


it('restaura cantidad y costo historico en el pool al revertir un despacho', function () {
    $fixture = dispatchValuationPoolFixture('REVPOOL');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchValuationPoolPayload($fixture, [
        ['stock' => $fixture['stockA'], 'quantity' => 4],
    ]));

    $dispatch = $service->confirm($draft);
    $movement = $dispatch->items()->firstOrFail()->kardexMovement()->firstOrFail();

    expect((float) $fixture['pool']->fresh()->current_quantity)->toBe(16.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(400.0)
        ->and((float) $movement->total_cost_out)->toBe(100.0);

    $service->reverse($dispatch, 'Reversa valorizada del despacho');
    $pool = $fixture['pool']->fresh();
    $reversal = WarehouseKardexMovement::query()
        ->where('source_id', $dispatch->id)
        ->where('movement_type', 'exit_reversal')
        ->sole();

    expect((float) $pool->current_quantity)->toBe(20.0)
        ->and((float) $pool->total_cost)->toBe(500.0)
        ->and((float) $pool->average_unit_cost)->toBe(25.0)
        ->and((float) $reversal->quantity_in)->toBe(4.0)
        ->and((float) $reversal->total_cost_in)->toBe((float) $movement->total_cost_out);
});

it('no modifica el pool para servicios no inventariables', function () {
    $fixture = dispatchValuationPoolFixture('SERV', false);
    $poolBefore = $fixture['pool']->fresh()->getAttributes();

    expect(fn () => app(WarehouseDispatchService::class)->createDraft(
        $fixture['order'],
        dispatchValuationPoolPayload($fixture, [['stock' => $fixture['stockA'], 'quantity' => 1]])
    ))->toThrow(ValidationException::class);

    expect($fixture['pool']->fresh()->getAttributes())->toBe($poolBefore)
        ->and(WarehouseDispatch::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('hace rollback de stock pool y Kardex si falla un detalle posterior', function () {
    $fixture = dispatchValuationPoolFixture('ROLL');
    $fixture['pool']->update([
        'current_quantity' => 3,
        'average_unit_cost' => 25,
        'total_cost' => 75,
    ]);
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchValuationPoolPayload($fixture, [
        ['stock' => $fixture['stockA'], 'quantity' => 2],
        ['stock' => $fixture['stockB'], 'quantity' => 2],
    ]));

    expect(fn () => $service->confirm($draft))->toThrow(ValidationException::class);

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['stockB']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(3.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(75.0)
        ->and(WarehouseKardexMovement::count())->toBe(0)
        ->and($draft->fresh()->isDraft())->toBeTrue();
});

it('bloquea una salida mayor al saldo del pool sin cambios parciales', function () {
    $fixture = dispatchValuationPoolFixture('OVER');
    $fixture['pool']->update([
        'current_quantity' => 3,
        'average_unit_cost' => 25,
        'total_cost' => 75,
    ]);
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchValuationPoolPayload($fixture, [
        ['stock' => $fixture['stockA'], 'quantity' => 4],
    ]));

    expect(fn () => $service->confirm($draft))->toThrow(ValidationException::class);

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(3.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(75.0)
        ->and(WarehouseKardexMovement::count())->toBe(0)
        ->and($draft->fresh()->isDraft())->toBeTrue();
});
