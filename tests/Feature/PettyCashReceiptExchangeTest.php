<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\PettyCashBox;
use App\Models\PettyCashExpense;
use App\Models\PettyCashExpenseExchange;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->company = Company::create([
        'business_name' => 'DROPAIV S.A.C.', 'trade_name' => 'DROPAIV',
        'ruc' => '20123456789', 'status' => true,
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN', 'description' => 'Soles', 'symbol' => 'S/', 'status' => 'ACTIVE',
    ]);
    $this->user = User::factory()->create();
    foreach ([
        'admin.petty-cash.approved-amount.update', 'admin.petty-cash.store',
        'admin.petty-cash.expenses.store', 'admin.petty-cash.expenses.approve',
        'admin.petty-cash.receipt-exchanges.index', 'admin.petty-cash.receipt-exchanges.store',
        'admin.petty-cash.receipt-exchanges.show',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo(Permission::all());
    $this->actingAs($this->user)->putJson(route('admin.petty-cash.approved-amount.update'), [
        'company_id' => $this->company->id,
        'currency_id' => $this->currency->id,
        'amount' => 2000,
        'active' => true,
    ])->assertOk();
    $this->boxId = $this->postJson(route('admin.petty-cash.store'), [
        'company_id' => $this->company->id,
        'currency_id' => $this->currency->id,
        'start_date' => '2026-07-01',
        'responsible_name' => 'RESPONSABLE',
        'responsible_dni' => '12345678',
        'supervisor_name' => 'SUPERVISOR',
        'supervisor_dni' => '87654321',
    ])->assertCreated()->json('data.id');
});

function createReceiptExpense(float $amount, string $correlative): PettyCashExpense
{
    test()->postJson(route('admin.petty-cash.expenses.store', test()->boxId), [
        'expense_date' => '2026-07-02',
        'document_type' => 'RECIBO',
        'document_series' => 'R',
        'document_correlative' => $correlative,
        'supplier_name' => 'PROVEEDOR ' . $correlative,
        'concept' => 'SERVICIO ' . $correlative,
        'amount' => $amount,
    ])->assertCreated();

    return PettyCashExpense::latest('id')->firstOrFail();
}

it('canjea varios recibos aprobados sin modificar ningún saldo de caja', function () {
    Storage::fake('public');
    $first = createReceiptExpense(60, '000261');
    $second = createReceiptExpense(70, '000262');
    expect($first->exchange_status)->toBe(PettyCashExpense::EXCHANGE_PENDING)
        ->and($second->exchange_status)->toBe(PettyCashExpense::EXCHANGE_PENDING);

    $this->postJson(route('admin.petty-cash.expenses.approve', $first))->assertOk();
    $this->postJson(route('admin.petty-cash.expenses.approve', $second))->assertOk();
    $before = PettyCashBox::findOrFail($this->boxId)->only([
        'opening_amount', 'total_expenses', 'cash_balance', 'reimbursement_amount',
    ]);

    $this->post(route('admin.petty-cash.receipt-exchanges.store', $this->boxId), [
        'exchange_date' => '2026-07-30',
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_correlative' => '000123',
        'expense_ids' => [$first->id, $second->id],
        'documents' => [UploadedFile::fake()->create('factura.pdf', 120, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('data.total_amount', '130.00');

    $after = PettyCashBox::findOrFail($this->boxId)->only([
        'opening_amount', 'total_expenses', 'cash_balance', 'reimbursement_amount',
    ]);
    expect($after)->toBe($before)
        ->and($first->fresh()->exchange_status)->toBe(PettyCashExpense::EXCHANGE_COMPLETED)
        ->and($second->fresh()->exchange_status)->toBe(PettyCashExpense::EXCHANGE_COMPLETED)
        ->and($first->fresh()->exchange_id)->toBe($second->fresh()->exchange_id)
        ->and(PettyCashExpenseExchange::firstOrFail()->items)->toHaveCount(2)
        ->and(PettyCashExpenseExchange::firstOrFail()->documents)->toHaveCount(1);

    $this->getJson(route('admin.petty-cash.receipt-exchanges.index', $this->boxId))
        ->assertOk()->assertJsonCount(0, 'data');
});

it('no lista ni permite canjear recibos pendientes de aprobación', function () {
    $receipt = createReceiptExpense(60, '000300');

    $this->getJson(route('admin.petty-cash.receipt-exchanges.index', $this->boxId))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->postJson(route('admin.petty-cash.receipt-exchanges.store', $this->boxId), [
        'exchange_date' => '2026-07-30',
        'document_type' => 'BOLETA',
        'document_series' => 'B001',
        'document_correlative' => '000010',
        'expense_ids' => [$receipt->id],
    ])->assertStatus(422)
        ->assertJsonPath('message', 'No se puede canjear este recibo porque todavía no está aprobado.');

    expect(PettyCashExpenseExchange::count())->toBe(0);
});
