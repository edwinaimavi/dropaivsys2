<?php

use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
