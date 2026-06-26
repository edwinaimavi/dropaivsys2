<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->user = User::factory()->create();
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
        ->assertJsonPath('items.0.line_total', 236);

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
        'items' => [[
            'quote_item_id' => $this->quoteItemId,
            'article_id' => $this->articleId,
            'article_code' => 'ART001',
            'billing_name_snapshot' => 'ARTÍCULO TEST',
            'unit_id' => $this->unitId,
            'presentation_id' => $this->presentationId,
            'brand_id' => $this->brandId,
            'quoted_quantity' => 10,
            'quantity' => 3,
            'unit_price' => 20,
            'line_total' => 70.80,
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
        ->assertJsonPath('data.grand_total', '70.80');

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
        'subtotal_taxed' => 60,
        'igv' => 10.80,
        'grand_total' => 70.80,
        'status' => 'registered',
    ]);

    $this->assertDatabaseHas('quotes', [
        'id' => $this->quoteId,
        'status' => 'approved',
    ]);

    $this->getJson(route('admin.customer-purchase-orders.show', $orderId))
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', '3.00');

    $payload['affect_igv'] = 0;
    $payload['status'] = 'registered';
    $payload['items'][0]['quantity'] = 2;
    $payload['items'][0]['line_total'] = 40;

    $this->putJson(
        route('admin.customer-purchase-orders.update', $orderId),
        $payload
    )
        ->assertOk()
        ->assertJsonPath('data.subtotal_exonerated', '40.00')
        ->assertJsonPath('data.grand_total', '40.00');

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
