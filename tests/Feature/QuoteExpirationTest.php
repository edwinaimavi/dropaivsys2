<?php

use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Carbon::setTestNow('2026-06-26 09:00:00');

    $this->user = User::factory()->create();
    $now = now();

    $this->companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA TEST',
        'ruc' => '20111111111',
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE TEST',
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
});

afterEach(function () {
    Carbon::setTestNow();
});

test('quotes list dismisses only expired editable quotes', function () {
    $expiredDraft = createQuoteForExpirationTest($this, 'COT-EXP-DRAFT', 'draft', '2026-06-25');
    $expiredSent = createQuoteForExpirationTest($this, 'COT-EXP-SENT', 'sent', '2026-06-25');
    $validDraft = createQuoteForExpirationTest($this, 'COT-VALID', 'draft', '2026-06-26');
    $withoutDate = createQuoteForExpirationTest($this, 'COT-NO-DATE', 'draft', null);
    $approved = createQuoteForExpirationTest($this, 'COT-APPROVED', 'approved', '2026-06-25');
    $awarded = createQuoteForExpirationTest($this, 'COT-AWARDED', 'awarded', '2026-06-25');
    $rejected = createQuoteForExpirationTest($this, 'COT-REJECTED', 'rejected', '2026-06-25');
    $withPurchaseOrder = createQuoteForExpirationTest($this, 'COT-WITH-ORDER', 'sent', '2026-06-25');
    $deleted = createQuoteForExpirationTest($this, 'COT-DELETED', 'draft', '2026-06-25', now());

    DB::table('customer_purchase_orders')->insert([
        'code' => 'P00001',
        'company_id' => $this->companyId,
        'quote_id' => $withPurchaseOrder->id,
        'customer_id' => $this->customerId,
        'order_type' => 'articles',
        'currency_id' => $this->currencyId,
        'status' => 'draft',
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('admin.quotes.list', [
            'draw' => 1,
            'start' => 0,
            'length' => 20,
        ]))
        ->assertOk();

    expect($expiredDraft->fresh()->status)->toBe('expired')
        ->and($expiredSent->fresh()->status)->toBe('expired')
        ->and($validDraft->fresh()->status)->toBe('draft')
        ->and($withoutDate->fresh()->status)->toBe('draft')
        ->and($approved->fresh()->status)->toBe('approved')
        ->and($awarded->fresh()->status)->toBe('awarded')
        ->and($rejected->fresh()->status)->toBe('rejected')
        ->and($withPurchaseOrder->fresh()->status)->toBe('sent')
        ->and(Quote::withTrashed()->find($deleted->id)->status)->toBe('draft');
});

test('expired status is displayed as desestimado in quotes list', function () {
    createQuoteForExpirationTest($this, 'COT-DISMISSED', 'draft', '2026-06-25');

    $response = $this->actingAs($this->user)
        ->getJson(route('admin.quotes.list', [
            'draw' => 1,
            'start' => 0,
            'length' => 10,
        ]))
        ->assertOk();

    expect($response->json('data.0.status'))
        ->toContain('Desestimado')
        ->toContain('rounded-pill')
        ->toContain('fas fa-ban');
});

test('new quotes are emitted by backend even when frontend sends draft', function () {
    Storage::fake('public');

    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $presentationId = DB::table('presentations')->insertGetId([
        'description' => 'UNIDAD',
        'unit_id' => $unitId,
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTOS',
        'code' => 'CAT-QUOTE',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $articleId = DB::table('articles')->insertGetId([
        'code' => 'ART-QUOTE',
        'category_id' => $categoryId,
        'presentation_id' => $presentationId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO DE PRUEBA',
        'billing_name' => 'ARTÍCULO DE PRUEBA',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'quote_number' => 'COT-NEW-SENT',
        'customer_id' => $this->customerId,
        'company_id' => $this->companyId,
        'currency_id' => $this->currencyId,
        'show_code_type' => 'internal',
        'orientation' => 'vertical',
        'billing_type' => 'local',
        'affect_igv' => 0,
        'validity_date' => '2026-07-10',
        'status' => 'draft',
        'items' => [[
            'article_id' => $articleId,
            'article_code' => 'ART-QUOTE',
            'billing_name_snapshot' => 'ARTÍCULO DE PRUEBA',
            'unit_id' => $unitId,
            'presentation_id' => $presentationId,
            'quantity' => 2,
            'unit_price' => 25,
            'line_total' => 50,
        ]],
    ];

    $storeResponse = $this->actingAs($this->user)
        ->postJson(route('admin.quotes.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', Quote::STATUS_SENT);

    $quoteId = $storeResponse->json('data.id');

    expect(Quote::findOrFail($quoteId)->status)->toBe(Quote::STATUS_SENT);

    unset($payload['status']);
    $payload['observations'] = 'ACTUALIZACIÓN SIN CAMBIO DE ESTADO';

    $this->putJson(route('admin.quotes.update', $quoteId), $payload)
        ->assertOk()
        ->assertJsonPath('data.status', Quote::STATUS_SENT);

    expect(Quote::findOrFail($quoteId)->status)->toBe(Quote::STATUS_SENT);
});

function createQuoteForExpirationTest(
    object $test,
    string $number,
    string $status,
    ?string $validityDate,
    mixed $deletedAt = null
): Quote {
    $id = DB::table('quotes')->insertGetId([
        'quote_number' => $number,
        'customer_id' => $test->customerId,
        'company_id' => $test->companyId,
        'currency_id' => $test->currencyId,
        'validity_date' => $validityDate,
        'status' => $status,
        'created_by' => $test->user->id,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => $deletedAt,
    ]);

    return Quote::withTrashed()->findOrFail($id);
}
