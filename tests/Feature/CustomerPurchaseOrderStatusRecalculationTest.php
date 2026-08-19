<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\User;
use App\Services\CustomerPurchaseOrderStatusService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->user = User::factory()->create();
    $now = now();

    $this->companyId = DB::table('companies')->insertGetId([
        'business_name' => 'DROPAIV STATUS TEST',
        'trade_name' => 'DROPAIV',
        'ruc' => '20111111111',
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $this->customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE STATUS TEST',
        'document_type' => 'RUC',
        'document_number' => '20222222222',
        'ruc' => '20222222222',
        'status' => true,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $this->supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20333333333',
        'business_name' => 'PROVEEDOR STATUS TEST',
        'short_name' => 'PROVEEDOR TEST',
        'supplier_type' => 'DISTRIBUIDOR',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $this->currencyId = DB::table('currencies')->insertGetId([
        'code' => 'PEN',
        'description' => 'SOLES',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $presentationId = DB::table('presentations')->insertGetId([
        'description' => 'UNIDAD',
        'unit_id' => $unitId,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $brandId = DB::table('brands')->insertGetId([
        'code' => 'STATUS-BRAND',
        'description' => 'MARCA STATUS',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA STATUS',
        'code' => 'STATUS-CAT',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $this->articleId = DB::table('articles')->insertGetId([
        'code' => 'STATUS-ART',
        'category_id' => $categoryId,
        'presentation_id' => $presentationId,
        'unit_id' => $unitId,
        'brand_id' => $brandId,
        'legal_name' => 'ARTÍCULO STATUS',
        'billing_name' => 'ARTÍCULO STATUS',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $this->secondArticleId = DB::table('articles')->insertGetId([
        'code' => 'STATUS-ART-2',
        'category_id' => $categoryId,
        'presentation_id' => $presentationId,
        'unit_id' => $unitId,
        'brand_id' => $brandId,
        'legal_name' => 'SEGUNDO ARTÍCULO STATUS',
        'billing_name' => 'SEGUNDO ARTÍCULO STATUS',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

it('mantiene registrada una OC cliente sin compra proveedor ni ingreso', function () {
    [$order] = statusTestCustomerOrder($this, 10, 'registered');

    app(CustomerPurchaseOrderStatusService::class)->recalculate($order);

    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_REGISTERED);
});

it('no mezcla los estados finales de atención con el abastecimiento', function () {
    [$order] = statusTestCustomerOrder($this, 10, CustomerPurchaseOrder::STATUS_ATTENDED);

    $result = app(CustomerPurchaseOrderStatusService::class)->recalculate($order);

    expect($result['skipped'])->toBeTrue()
        ->and($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED);
});

it('marca en compra cuando existe OC proveedor pero aún no existe ingreso', function () {
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'registered');
    statusTestSupplierOrder($this, $order->id, $customerItemId, 10);

    app(CustomerPurchaseOrderStatusService::class)->recalculate($order);

    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_IN_PURCHASE);
});

it('distingue ingreso parcial y abastecimiento completo al editar cantidades', function () {
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'in_purchase');
    [$supplierOrderId, $supplierItemId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    [$entryId, $entryItemId] = statusTestWarehouseEntry($this, $supplierOrderId, $supplierItemId, 6);
    $service = app(CustomerPurchaseOrderStatusService::class);

    $service->recalculate($order);
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_ENTERED);

    DB::table('warehouse_entry_items')->where('id', $entryItemId)->update(['quantity' => 10]);
    $service->recalculate($order);
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);

    DB::table('warehouse_entries')->where('id', $entryId)->update(['status' => 'cancelled']);
    $service->recalculate($order);
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_IN_PURCHASE);
});

it('suma varios ingresos y varias OC proveedor por cada línea de la OC cliente', function () {
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'in_purchase');
    [$firstSupplierOrderId, $firstSupplierItemId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 4);
    [$secondSupplierOrderId, $secondSupplierItemId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 6);
    statusTestWarehouseEntry($this, $firstSupplierOrderId, $firstSupplierItemId, 4);
    statusTestWarehouseEntry($this, $secondSupplierOrderId, $secondSupplierItemId, 6);

    app(CustomerPurchaseOrderStatusService::class)->recalculate($order);

    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('no abastece una OC con varios artículos hasta que todos estén completos', function () {
    [$order, $firstCustomerItemId] = statusTestCustomerOrder($this, 10, 'registered');
    $secondCustomerItemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $order->id,
        'article_id' => $this->secondArticleId,
        'billing_name_snapshot' => 'SEGUNDO ARTÍCULO STATUS',
        'quantity' => 5,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    [$firstSupplierOrderId, $firstSupplierItemId] = statusTestSupplierOrder($this, $order->id, $firstCustomerItemId, 10);
    [$secondSupplierOrderId, $secondSupplierItemId] = statusTestSupplierOrder(
        $this,
        $order->id,
        $secondCustomerItemId,
        5,
        $this->secondArticleId
    );
    statusTestWarehouseEntry($this, $firstSupplierOrderId, $firstSupplierItemId, 10);

    $service = app(CustomerPurchaseOrderStatusService::class);
    $service->syncStatus($order);
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_ENTERED);

    statusTestWarehouseEntry($this, $secondSupplierOrderId, $secondSupplierItemId, 5);
    $service->syncStatus($order);
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('suma varios ingresos de almacén asociados a una misma compra proveedor', function () {
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'in_purchase');
    [$supplierOrderId, $supplierItemId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    statusTestWarehouseEntry($this, $supplierOrderId, $supplierItemId, 4);
    statusTestWarehouseEntry($this, $supplierOrderId, $supplierItemId, 6);

    app(CustomerPurchaseOrderStatusService::class)->syncStatus($order);

    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('regresa a registrada cuando se anula la única compra proveedor sin ingresos', function () {
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'registered');
    [$supplierOrderId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    $service = app(CustomerPurchaseOrderStatusService::class);

    $service->syncStatus($order);
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_IN_PURCHASE);

    DB::table('supplier_purchase_orders')->where('id', $supplierOrderId)->update(['status' => 'cancelled']);
    $service->syncStatus($order);
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_REGISTERED);
});

it('recupera ingresos históricos cuya línea perdió el vínculo con la línea proveedor', function () {
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'in_purchase', '4505463671');
    [$supplierOrderId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    statusTestWarehouseEntry($this, $supplierOrderId, null, 10);

    $result = app(CustomerPurchaseOrderStatusService::class)->recalculate($order);

    expect($result['changed'])->toBeTrue()
        ->and($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('conserva el vínculo de detalle y recalcula al editar cantidades de la OC cliente', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.customer-purchase-orders.update', 'web');
    $this->user->givePermissionTo('admin.customer-purchase-orders.update');
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'in_purchase');
    [$supplierOrderId, $supplierItemId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    statusTestWarehouseEntry($this, $supplierOrderId, $supplierItemId, 10);
    app(CustomerPurchaseOrderStatusService::class)->syncStatus($order);
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);

    $this->actingAs($this->user)->putJson(
        route('admin.customer-purchase-orders.update', $order),
        [
            'company_id' => $this->companyId,
            'customer_id' => $this->customerId,
            'order_type' => 'articles',
            'purchase_order_number' => $order->purchase_order_number,
            'currency_id' => $this->currencyId,
            'billing_type' => 'local',
            'affect_igv' => false,
            'items' => [[
                'id' => $customerItemId,
                'article_id' => $this->articleId,
                'billing_name_snapshot' => 'ARTÍCULO STATUS',
                'quantity' => 12,
                'unit_price' => 1,
                'line_total' => 12,
                'status' => 'active',
            ]],
        ]
    )->assertOk();

    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_ENTERED)
        ->and(DB::table('customer_purchase_order_items')->where('id', $customerItemId)->value('quantity'))->toBe(12)
        ->and(DB::table('supplier_purchase_order_items')->where('id', $supplierItemId)->value('customer_purchase_order_item_id'))
        ->toBe($customerItemId);
});

it('recalcula automáticamente al crear editar y anular un ingreso de almacén', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $permissions = [
        'admin.warehouse-entries.store',
        'admin.warehouse-entries.update',
        'admin.warehouse-entries.destroy',
    ];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo($permissions);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'STATUS-WH',
        'name' => 'ALMACÉN STATUS',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'in_purchase');
    [$supplierOrderId, $supplierItemId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    $payload = [
        'supplier_purchase_order_id' => $supplierOrderId,
        'warehouse_id' => $warehouseId,
        'document_type' => 'FACTURA',
        'generate_account_payable' => 1,
        'expected_payment_date' => today()->toDateString(),
        'items' => [[
            'supplier_purchase_order_item_id' => $supplierItemId,
            'article_id' => $this->articleId,
            'billing_name_snapshot' => 'ARTÍCULO STATUS',
            'ordered_quantity' => 10,
            'quantity' => 10,
            'unit_price' => 1,
            'status' => 'active',
        ]],
    ];

    $createResponse = $this->actingAs($this->user)
        ->postJson(route('admin.warehouse-entries.store'), $payload)
        ->assertCreated();
    $entryId = (int) $createResponse->json('data.id');
    $entryItemId = (int) $createResponse->json('data.items.0.id');
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);

    $payload['items'][0]['id'] = $entryItemId;
    $payload['items'][0]['quantity'] = 6;
    $this->putJson(route('admin.warehouse-entries.update', $entryId), $payload)->assertOk();
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_ENTERED);

    $this->deleteJson(route('admin.warehouse-entries.destroy', $entryId))->assertOk();
    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_IN_PURCHASE);
});

it('repara una OC específica mediante el comando seguro y muestra el cambio', function () {
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'in_purchase', '4505463671');
    [$supplierOrderId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    statusTestWarehouseEntry($this, $supplierOrderId, null, 10);

    $exitCode = Artisan::call('customer-orders:sync-statuses', ['--order' => '4505463671']);

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('P-STATUS-')
        ->toContain('4505463671')
        ->toContain('En compra (in_purchase)')
        ->toContain('Abastecida (entered)');

    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('el comando conserva el estado atendida cuando la orden completa tiene documento de cierre', function () {
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'registered');
    $order->update(['attention_document_path' => 'customer-purchase-orders/attention/cierre.pdf']);
    [$supplierOrderId, $supplierItemId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    statusTestWarehouseEntry($this, $supplierOrderId, $supplierItemId, 10);

    expect(Artisan::call('customer-orders:sync-statuses', ['--order' => $order->code]))->toBe(0)
        ->and($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED);
});

it('el listado y el filtro abastecidas usan el estado sincronizado', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.customer-purchase-orders.index', 'web');
    $this->user->givePermissionTo('admin.customer-purchase-orders.index');
    [$order, $customerItemId] = statusTestCustomerOrder($this, 10, 'in_purchase');
    [$supplierOrderId, $supplierItemId] = statusTestSupplierOrder($this, $order->id, $customerItemId, 10);
    statusTestWarehouseEntry($this, $supplierOrderId, $supplierItemId, 10);
    app(CustomerPurchaseOrderStatusService::class)->recalculate($order);

    $response = $this->actingAs($this->user)->getJson(route('admin.customer-purchase-orders.list', [
        'status_filter' => 'entered',
    ]));

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('id'))->toContain($order->id);
});

function statusTestCustomerOrder(object $test, float $quantity, string $status, ?string $number = null): array
{
    static $sequence = 0;
    $sequence++;
    $number ??= 'STATUS-CLIENT-'.$sequence;
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'P-STATUS-'.$sequence,
        'company_id' => $test->companyId,
        'customer_id' => $test->customerId,
        'order_type' => 'articles',
        'purchase_order_number' => $number,
        'currency_id' => $test->currencyId,
        'status' => $status,
        'created_by' => $test->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $itemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $orderId,
        'article_id' => $test->articleId,
        'billing_name_snapshot' => 'ARTÍCULO STATUS',
        'quantity' => $quantity,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [CustomerPurchaseOrder::query()->findOrFail($orderId), $itemId];
}

function statusTestSupplierOrder(
    object $test,
    int $customerOrderId,
    int $customerItemId,
    float $quantity,
    ?int $articleId = null
): array {
    static $sequence = 0;
    $sequence++;
    $supplierOrderId = DB::table('supplier_purchase_orders')->insertGetId([
        'code' => 'OCP-STATUS-'.$sequence,
        'company_id' => $test->companyId,
        'supplier_id' => $test->supplierId,
        'currency_id' => $test->currencyId,
        'customer_purchase_order_id' => $customerOrderId,
        'order_type' => 'articles',
        'status' => 'registered',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('supplier_purchase_order_customer_purchase_order')->insert([
        'supplier_purchase_order_id' => $supplierOrderId,
        'customer_purchase_order_id' => $customerOrderId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $supplierItemId = DB::table('supplier_purchase_order_items')->insertGetId([
        'supplier_purchase_order_id' => $supplierOrderId,
        'article_id' => $articleId ?? $test->articleId,
        'customer_purchase_order_item_id' => $customerItemId,
        'billing_name_snapshot' => 'ARTÍCULO STATUS',
        'quantity' => $quantity,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$supplierOrderId, $supplierItemId];
}

function statusTestWarehouseEntry(
    object $test,
    int $supplierOrderId,
    ?int $supplierItemId,
    float $quantity
): array {
    static $sequence = 0;
    $sequence++;
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-STATUS-'.$sequence,
        'supplier_purchase_order_id' => $supplierOrderId,
        'company_id' => $test->companyId,
        'supplier_id' => $test->supplierId,
        'currency_id' => $test->currencyId,
        'status' => 'registered',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $entryItemId = DB::table('warehouse_entry_items')->insertGetId([
        'warehouse_entry_id' => $entryId,
        'supplier_purchase_order_item_id' => $supplierItemId,
        'article_id' => $test->articleId,
        'billing_name_snapshot' => 'ARTÍCULO STATUS',
        'quantity' => $quantity,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$entryId, $entryItemId];
}
