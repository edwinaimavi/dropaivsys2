<?php

use App\Models\User;
use App\Models\CustomerPurchaseOrder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user = User::factory()->create();
    $this->user->forceFill(['dni' => '12345678'])->save();
    $permissions = [
        'admin.customer-purchase-orders.index',
        'admin.customer-purchase-orders.load-items',
        'admin.customer-purchase-orders.store',
        'admin.customer-purchase-orders.update',
        'admin.customer-purchase-orders.destroy',
        'admin.customer-purchase-orders.show',
    ];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo($permissions);
    $now = now();

    $this->companyId = DB::table('companies')->insertGetId([
        'business_name' => 'DROPAIV TEST',
        'trade_name' => 'DROPAIV',
        'ruc' => '20123456789',
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE TEST',
        'document_type' => 'RUC',
        'document_number' => '20987654321',
        'ruc' => '20987654321',
        'status' => true,
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->branchId = DB::table('customer_branches')->insertGetId([
        'customer_id' => $this->customerId,
        'branch_name' => 'SEDE PRINCIPAL',
        'address' => 'AV. PRUEBA 123',
        'generate_guide' => 'NO',
        'is_main' => true,
        'status' => true,
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

    $this->unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->presentationId = DB::table('presentations')->insertGetId([
        'description' => 'UNIDAD',
        'unit_id' => $this->unitId,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->brandId = DB::table('brands')->insertGetId([
        'code' => 'MAR001',
        'description' => 'MARCA TEST',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA TEST',
        'code' => 'CAT001',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->articleId = DB::table('articles')->insertGetId([
        'code' => 'ART001',
        'category_id' => $categoryId,
        'presentation_id' => $this->presentationId,
        'unit_id' => $this->unitId,
        'brand_id' => $this->brandId,
        'legal_name' => 'ARTÍCULO TEST',
        'billing_name' => 'ARTÍCULO TEST',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->quoteId = DB::table('quotes')->insertGetId([
        'quote_number' => 'COT-000001',
        'customer_id' => $this->customerId,
        'company_id' => $this->companyId,
        'currency_id' => $this->currencyId,
        'billing_type' => 'local',
        'affect_igv' => true,
        'status' => 'sent',
        'created_by' => $this->user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->quoteItemId = DB::table('quote_items')->insertGetId([
        'quote_id' => $this->quoteId,
        'article_id' => $this->articleId,
        'article_code' => 'ART001',
        'billing_name_snapshot' => 'ARTÍCULO TEST',
        'unit_id' => $this->unitId,
        'presentation_id' => $this->presentationId,
        'brand_id' => $this->brandId,
        'quantity' => 10,
        'unit_price' => 20,
        'line_total' => 200,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

test('supplier purchase availability query is evaluated by pending item quantity', function () {
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'P-PENDING', 'company_id' => $this->companyId, 'customer_id' => $this->customerId,
        'order_type' => 'articles', 'currency_id' => $this->currencyId, 'status' => 'in_purchase',
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('customer_purchase_order_items')->insert([
        'customer_purchase_order_id' => $orderId, 'article_id' => $this->articleId,
        'billing_name_snapshot' => 'ARTÍCULO PENDIENTE', 'quantity' => 3, 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(CustomerPurchaseOrder::query()->availableForSupplierPurchase()->pluck('id')->all())
        ->toContain($orderId);
});

test('customer purchase order backend flow works', function () {
    $this->actingAs($this->user);

    $expiredQuoteId = DB::table('quotes')->insertGetId([
        'quote_number' => 'COT-EXPIRED',
        'customer_id' => $this->customerId,
        'company_id' => $this->companyId,
        'currency_id' => $this->currencyId,
        'status' => 'expired',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->getJson(route('admin.customer-purchase-orders.quoteItems', $expiredQuoteId))
        ->assertUnprocessable();

    $this->getJson(route('admin.customer-purchase-orders.generateCode'))
        ->assertOk()
        ->assertJsonPath('code', 'P00001');

    $this->getJson(route('admin.customer-purchase-orders.quoteItems', $this->quoteId))
        ->assertOk()
        ->assertJsonPath('customer_id', $this->customerId)
        ->assertJsonPath('items.0.quote_item_id', $this->quoteItemId)
        ->assertJsonPath('items.0.line_total', '200.0000000000');

    $payload = [
        'company_id' => $this->companyId,
        'quote_id' => $this->quoteId,
        'customer_id' => $this->customerId,
        'customer_branch_id' => $this->branchId,
        'order_type' => 'articles',
        'purchase_order_number' => 'OC-001',
        'currency_id' => $this->currencyId,
        'billing_type' => 'local',
        'affect_igv' => 1,
        'status' => 'draft',
        'seller_type' => 'EXTERNAL',
        'seller_dni' => '70587639',
        'seller_names' => 'JUAN',
        'seller_lastnames' => 'PÉREZ RAMOS',
        'seller_full_name' => 'JUAN PÉREZ RAMOS',
        'seller_phone' => '999888777',
        'seller_email' => 'juan@example.com',
        'seller_observation' => 'GESTOR EXTERNO',
        'items' => [[
            'quote_item_id' => $this->quoteItemId,
            'article_id' => $this->articleId,
            'article_code' => 'ART001',
            'billing_name_snapshot' => 'ARTÍCULO TEST',
            'unit_id' => $this->unitId,
            'presentation_id' => $this->presentationId,
            'brand_id' => $this->brandId,
            'quoted_quantity' => 10,
            'quantity' => 1800,
            'unit_price' => '23.950',
            'line_total' => '43110.000',
        ]],
    ];

    $invalidPayload = $payload;
    $invalidPayload['items'] = [];

    $this->postJson(
        route('admin.customer-purchase-orders.store'),
        $invalidPayload
    )->assertUnprocessable();

    $this->assertDatabaseHas('quotes', [
        'id' => $this->quoteId,
        'status' => 'sent',
    ]);

    $storeResponse = $this->postJson(
        route('admin.customer-purchase-orders.store'),
        $payload
    )
        ->assertCreated()
        ->assertJsonPath('data.code', 'P00001')
        ->assertJsonPath('data.status', 'registered')
        ->assertJsonPath('data.grand_total', '43110.0000000000');

    $orderId = $storeResponse->json('data.id');

    $this->get(route('admin.customer-purchase-orders.index'))
        ->assertOk()
        ->assertSee('tableCustomerPurchaseOrder')
        ->assertSee('COT-000001')
        ->assertDontSee('COT-EXPIRED');

    $this->getJson(route('admin.customer-purchase-orders.list', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'search' => ['value' => 'CLIENTE', 'regex' => false],
    ]))
        ->assertOk()
        ->assertJsonPath('data.0.code', 'P00001');

    $this->assertDatabaseHas('customer_purchase_orders', [
        'id' => $orderId,
        'code' => 'P00001',
        'subtotal_taxed' => '36533.8983050847',
        'igv' => '6576.1016949153',
        'grand_total' => '43110.0000000000',
        'status' => 'registered',
        'seller_type' => 'EXTERNAL',
        'seller_dni' => '70587639',
        'seller_full_name' => 'JUAN PÉREZ RAMOS',
    ]);

    $this->assertDatabaseHas('quotes', [
        'id' => $this->quoteId,
        'status' => 'approved',
    ]);

    $this->getJson(route('admin.customer-purchase-orders.show', $orderId))
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', '1800.00')
        ->assertJsonPath('data.items.0.unit_price', '23.9500000000')
        ->assertJsonPath('data.items.0.line_total', '43110.0000000000');

    $payload['affect_igv'] = 0;
    $payload['status'] = 'registered';
    $payload['items'][0]['quantity'] = 2;
    $payload['items'][0]['line_total'] = 40;
    $payload['seller_type'] = 'USER';
    $payload['seller_user_id'] = $this->user->id;
    $payload['seller_dni'] = $this->user->dni;
    $payload['seller_names'] = $this->user->name;
    $payload['seller_lastnames'] = $this->user->lastname;
    $payload['seller_full_name'] = trim($this->user->name . ' ' . $this->user->lastname);

    $this->putJson(
        route('admin.customer-purchase-orders.update', $orderId),
        $payload
    )
        ->assertOk()
        ->assertJsonPath('data.subtotal_exonerated', '47.9000000000')
        ->assertJsonPath('data.grand_total', '47.9000000000');

    $this->assertDatabaseHas('customer_purchase_orders', [
        'id' => $orderId,
        'seller_type' => 'USER',
        'seller_user_id' => $this->user->id,
        'seller_dni' => $this->user->dni,
    ]);

    $this->deleteJson(route('admin.customer-purchase-orders.destroy', $orderId))
        ->assertOk();

    $this->assertSoftDeleted('customer_purchase_orders', [
        'id' => $orderId,
    ]);

    $this->assertDatabaseHas('quotes', [
        'id' => $this->quoteId,
        'status' => 'sent',
    ]);
});

test('deleting one of multiple active orders keeps quote approved', function () {
    $this->actingAs($this->user);

    DB::table('quotes')
        ->where('id', $this->quoteId)
        ->update(['status' => 'approved']);

    $firstOrderId = createCustomerPurchaseOrderForDestroyTest(
        $this,
        'P00001'
    );

    createCustomerPurchaseOrderForDestroyTest(
        $this,
        'P00002'
    );

    $this->deleteJson(
        route('admin.customer-purchase-orders.destroy', $firstOrderId)
    )->assertOk();

    $this->assertSoftDeleted('customer_purchase_orders', [
        'id' => $firstOrderId,
    ]);

    $this->assertDatabaseHas('quotes', [
        'id' => $this->quoteId,
        'status' => 'approved',
    ]);
});

test('filters active and attended orders and only marks active overdue orders as expired', function () {
    $this->actingAs($this->user);

    $activeId = createCustomerPurchaseOrderForDestroyTest($this, 'P-ACTIVE');
    DB::table('customer_purchase_orders')->where('id', $activeId)->update([
        'delivery_start_date' => today()->subDays(8)->toDateString(),
        'delivery_end_date' => today()->subDays(3)->toDateString(),
        'delivery_days' => 5,
        'status' => 'registered',
    ]);

    $attendedId = createCustomerPurchaseOrderForDestroyTest($this, 'P-ATTENDED');
    DB::table('customer_purchase_orders')->where('id', $attendedId)->update([
        'delivery_start_date' => today()->subDays(10)->toDateString(),
        'delivery_end_date' => today()->subDays(5)->toDateString(),
        'delivery_days' => 5,
        'status' => 'attended',
        'attention_closed_at' => today()->subDay(),
        'updated_at' => today()->subDay(),
    ]);

    $activeResponse = $this->getJson(route('admin.customer-purchase-orders.list', ['status_filter' => 'active']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'P-ACTIVE')
        ->assertSee('delivery-period-danger', false);
    expect($activeResponse->json('data.0.delivery_period'))->toContain('Vencido hace 3 días');

    $attendedResponse = $this->getJson(route('admin.customer-purchase-orders.list', ['status_filter' => 'attended']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'P-ATTENDED')
        ->assertSee('delivery-period-completed', false)
        ->assertDontSee('delivery-period-danger', false);
    expect($attendedResponse->json('data.0.delivery_period'))
        ->toContain('Atendida')
        ->toContain('Regularizado con 4 días de atraso');

    $this->getJson(route('admin.customer-purchase-orders.list', ['status_filter' => 'overdue']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'P-ACTIVE');

    $this->getJson(route('admin.customer-purchase-orders.list', ['status_filter' => 'all']))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('deleting last order marks an expired quote as expired', function () {
    $this->actingAs($this->user);

    DB::table('quotes')
        ->where('id', $this->quoteId)
        ->update([
            'status' => 'approved',
            'validity_date' => today()->subDay()->toDateString(),
        ]);

    $orderId = createCustomerPurchaseOrderForDestroyTest(
        $this,
        'P00001'
    );

    $this->deleteJson(
        route('admin.customer-purchase-orders.destroy', $orderId)
    )->assertOk();

    $this->assertDatabaseHas('quotes', [
        'id' => $this->quoteId,
        'status' => 'expired',
    ]);
});

test('deleting an order does not overwrite protected quote statuses', function () {
    $this->actingAs($this->user);

    DB::table('quotes')
        ->where('id', $this->quoteId)
        ->update(['status' => 'rejected']);

    $orderId = createCustomerPurchaseOrderForDestroyTest(
        $this,
        'P00001'
    );

    $this->deleteJson(
        route('admin.customer-purchase-orders.destroy', $orderId)
    )->assertOk();

    $this->assertDatabaseHas('quotes', [
        'id' => $this->quoteId,
        'status' => 'rejected',
    ]);
});

function createCustomerPurchaseOrderForDestroyTest(
    object $test,
    string $code
): int {
    return DB::table('customer_purchase_orders')->insertGetId([
        'code' => $code,
        'company_id' => $test->companyId,
        'quote_id' => $test->quoteId,
        'customer_id' => $test->customerId,
        'customer_branch_id' => $test->branchId,
        'order_type' => 'articles',
        'currency_id' => $test->currencyId,
        'status' => 'registered',
        'created_by' => $test->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}
