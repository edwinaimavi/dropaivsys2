<?php

use App\Models\Bank;
use App\Models\BankMovement;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\GeneralCashBox;
use App\Models\GeneralCashExpense;
use App\Models\GeneralCashMovement;
use App\Models\GeneralCashReconciliation;
use App\Models\User;
use App\Services\BankMovementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('public');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->company = Company::create(['business_name' => 'EMPRESA CAJA GENERAL S.A.C.', 'ruc' => '20988888881', 'status' => true]);
    $this->currency = Currency::create(['code' => 'PEN', 'description' => 'Soles', 'symbol' => 'S/', 'status' => 'ACTIVE']);
    $this->bank = Bank::create(['description' => 'BANCO CAJA GENERAL', 'short_name' => 'BCG', 'status' => 'ACTIVE']);
    $this->account = CompanyBankAccount::create([
        'company_id' => $this->company->id, 'bank_id' => $this->bank->id, 'currency_id' => $this->currency->id,
        'account_holder' => 'EMPRESA CAJA GENERAL S.A.C.', 'account_number' => '001-CAJA-GENERAL',
        'is_detraction' => 'NO', 'status' => 'ACTIVE',
    ]);
    $this->user = User::factory()->create();
    foreach ([
        'admin.general-cash.index', 'admin.general-cash.show', 'admin.general-cash.store',
        'admin.general-cash.update', 'admin.general-cash.annul', 'admin.general-cash.movements',
        'admin.general-cash.expenses', 'admin.general-cash.expenses.store',
        'admin.general-cash.expenses.approve', 'admin.general-cash.expenses.annul',
        'admin.general-cash.replenishments', 'admin.general-cash.close',
        'admin.general-cash.documents', 'admin.general-cash.reports',
        'admin.banks.view', 'admin.banks.movements', 'admin.banks.movements.cancel',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo(Permission::all());
    $this->actingAs($this->user);
    app(BankMovementService::class)->createMovement([
        'company_bank_account_id' => $this->account->id,
        'currency_id' => $this->currency->id,
        'movement_date' => '2026-08-15 08:00:00',
        'movement_type' => 'INGRESO', 'amount' => 5000, 'direction' => 'IN',
        'concept' => 'Saldo disponible para pruebas', 'source_type' => 'MANUAL',
        'idempotency_key' => 'general-cash-test-opening',
    ], $this->user->id);
});

function createGeneralCashBoxForTest(): GeneralCashBox
{
    $response = test()->postJson(route('admin.general-cash.store'), [
        'company_id' => test()->company->id,
        'currency_id' => test()->currency->id,
        'name' => 'Caja Efectivo Principal',
        'description' => 'Efectivo físico de gerencia',
        'responsible_user_id' => test()->user->id,
        'status' => 'ACTIVE',
    ])->assertCreated();

    return GeneralCashBox::findOrFail($response->json('data.id'));
}

it('crea una caja independiente e ingresa efectivo desde banco con trazabilidad e idempotencia', function () {
    $box = createGeneralCashBoxForTest();
    expect((float) $box->current_balance)->toBe(0.0);
    $this->get(route('admin.general-cash.index'))->assertOk()->assertSee('Caja General');
    $this->getJson(route('admin.general-cash.list', ['draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()->assertJsonPath('data.0.id', $box->id);

    $payload = [
        'general_cash_box_id' => $box->id,
        'company_bank_account_id' => $this->account->id,
        'movement_date' => '2026-08-15',
        'amount' => 2000,
        'operation_number' => 'OP-CG-0001',
        'responsible_user_id' => $this->user->id,
        'observation' => 'Retiro para gastos generales',
        'idempotency_key' => 'funding-http-0001',
        'support_file' => UploadedFile::fake()->create('constancia-retiro.pdf', 20, 'application/pdf'),
    ];
    $response = $this->post(route('admin.general-cash.fundings.store'), $payload, ['Accept' => 'application/json'])
        ->assertCreated();
    $movement = GeneralCashMovement::findOrFail($response->json('data.id'));
    $bankMovement = BankMovement::findOrFail($movement->bank_movement_id);

    expect((float) $box->fresh()->current_balance)->toBe(2000.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(3000.0)
        ->and($movement->source_type)->toBe(GeneralCashMovement::SOURCE_BANK_FUNDING)
        ->and($movement->documents()->count())->toBe(1)
        ->and($bankMovement->direction)->toBe(BankMovement::DIRECTION_OUT)
        ->and($bankMovement->source_type)->toBe(GeneralCashMovement::SOURCE_BANK_FUNDING)
        ->and($bankMovement->source_id)->toBe($movement->id);

    unset($payload['support_file']);
    $this->postJson(route('admin.general-cash.fundings.store'), $payload)->assertCreated()->assertJsonPath('data.id', $movement->id);
    expect(GeneralCashMovement::where('source_type', GeneralCashMovement::SOURCE_BANK_FUNDING)->count())->toBe(1)
        ->and(BankMovement::where('source_type', GeneralCashMovement::SOURCE_BANK_FUNDING)->count())->toBe(1)
        ->and((float) $box->fresh()->current_balance)->toBe(2000.0);

    $payload['idempotency_key'] = 'funding-http-retry-with-new-token';
    $this->postJson(route('admin.general-cash.fundings.store'), $payload)->assertCreated()->assertJsonPath('data.id', $movement->id);
    expect(GeneralCashMovement::where('source_type', GeneralCashMovement::SOURCE_BANK_FUNDING)->count())->toBe(1)
        ->and(BankMovement::where('source_type', GeneralCashMovement::SOURCE_BANK_FUNDING)->count())->toBe(1);

    $payload['idempotency_key'] = 'funding-http-duplicate-operation';
    $payload['amount'] = 1900;
    $this->postJson(route('admin.general-cash.fundings.store'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('operation_number');

    $show = $this->getJson(route('admin.general-cash.show', $box))->assertOk();
    $document = $show->json('data.movements.0.documents.0');
    expect($document['view_url'])->not->toBeNull();
    $this->get($document['view_url'])->assertOk();
    $bankDetail = $this->getJson(route('admin.banks.show', $this->account))->assertOk();
    $fundingBankMovement = collect($bankDetail->json('data.movements'))
        ->firstWhere('source_type', GeneralCashMovement::SOURCE_BANK_FUNDING);
    expect($fundingBankMovement['source_url'])->toBe(route('admin.general-cash.index', [
        'from_movement' => $movement->id,
        'auto_open' => 1,
    ]));
});

it('registra clasifica aprueba y anula un gasto restaurando el saldo mediante reversa', function () {
    $box = createGeneralCashBoxForTest();
    $this->postJson(route('admin.general-cash.fundings.store'), [
        'general_cash_box_id' => $box->id, 'company_bank_account_id' => $this->account->id,
        'movement_date' => '2026-08-15', 'amount' => 2000, 'operation_number' => 'OP-CG-EXPENSE',
        'idempotency_key' => 'funding-expense-test',
    ])->assertCreated();

    $response = $this->post(route('admin.general-cash.expenses.store'), [
        'general_cash_box_id' => $box->id, 'expense_date' => '2026-08-15',
        'expense_type' => 'GASOLINA', 'person_name' => 'GRIFO GENERAL S.A.C.',
        'identity_document' => '20111111111', 'concept' => 'Gasolina vehículo gerencia',
        'document_type' => 'FACTURA', 'document_series' => 'F001', 'document_number' => '00012345',
        'amount' => 360, 'affects_igv' => 1, 'idempotency_key' => 'expense-http-0001',
        'receipt_file' => UploadedFile::fake()->create('factura.pdf', 20, 'application/pdf'),
        'payment_file' => UploadedFile::fake()->image('voucher-yape.png'),
    ], ['Accept' => 'application/json'])->assertCreated();
    $expense = GeneralCashExpense::findOrFail($response->json('data.id'));

    expect((float) $box->fresh()->current_balance)->toBe(1640.0)
        ->and($expense->expense_classification)->toBe(GeneralCashExpense::CLASSIFICATION_OFFICIAL)
        ->and((float) $expense->taxable_base)->toBe(305.0847)
        ->and((float) $expense->igv_amount)->toBe(54.9153)
        ->and($expense->documents()->count())->toBe(2);

    $this->postJson(route('admin.general-cash.expenses.approve', $expense))->assertOk();
    expect($expense->fresh()->status)->toBe(GeneralCashExpense::STATUS_APPROVED)
        ->and((float) $box->fresh()->current_balance)->toBe(1640.0);

    $box->update(['status' => GeneralCashBox::STATUS_INACTIVE]);
    $this->postJson(route('admin.general-cash.expenses.cancel', $expense), ['reason' => 'Comprobante registrado por error'])->assertOk();
    $expense->refresh();
    expect($expense->status)->toBe(GeneralCashExpense::STATUS_CANCELLED)
        ->and((float) $box->fresh()->current_balance)->toBe(2000.0)
        ->and($expense->movement->status)->toBe(GeneralCashMovement::STATUS_CANCELLED)
        ->and($expense->movement->reversal)->not->toBeNull()
        ->and($expense->exists)->toBeTrue();
    $this->postJson(route('admin.general-cash.expenses.cancel', $expense), ['reason' => 'Segundo intento inválido'])->assertUnprocessable();
});

it('rechaza saldos insuficientes sin dejar movimientos parciales', function () {
    $box = createGeneralCashBoxForTest();
    $bankCount = BankMovement::count();
    $this->postJson(route('admin.general-cash.fundings.store'), [
        'general_cash_box_id' => $box->id, 'company_bank_account_id' => $this->account->id,
        'movement_date' => '2026-08-15', 'amount' => 6000, 'operation_number' => 'OP-SIN-SALDO',
        'idempotency_key' => 'funding-no-balance',
    ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    expect(GeneralCashMovement::count())->toBe(0)
        ->and(BankMovement::count())->toBe($bankCount)
        ->and((float) $box->fresh()->current_balance)->toBe(0.0);

    $this->postJson(route('admin.general-cash.expenses.store'), [
        'general_cash_box_id' => $box->id, 'expense_date' => '2026-08-15', 'expense_type' => 'MOVILIDAD',
        'person_name' => 'PERSONA SIN CUENTA', 'concept' => 'Movilidad', 'document_type' => 'RECIBO_INTERNO',
        'amount' => 10, 'affects_igv' => 0, 'idempotency_key' => 'expense-no-balance',
    ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    expect(GeneralCashExpense::count())->toBe(0)->and(GeneralCashMovement::count())->toBe(0);
});

it('anula un ingreso desde caja y revierte también el banco sin permitir anulación bancaria aislada', function () {
    $box = createGeneralCashBoxForTest();
    $movementId = $this->postJson(route('admin.general-cash.fundings.store'), [
        'general_cash_box_id' => $box->id, 'company_bank_account_id' => $this->account->id,
        'movement_date' => '2026-08-15', 'amount' => 1200, 'operation_number' => 'OP-REVERSA',
        'idempotency_key' => 'funding-reversal-test',
    ])->assertCreated()->json('data.id');
    $movement = GeneralCashMovement::findOrFail($movementId);

    $this->postJson(route('admin.banks.movements.cancel', $movement->bank_movement_id), [
        'cancellation_reason' => 'Intento desde banco',
    ])->assertUnprocessable()->assertJsonValidationErrors('movement');
    expect((float) $this->account->fresh()->current_balance)->toBe(3800.0);

    $this->postJson(route('admin.general-cash.fundings.cancel', $movement), ['reason' => 'Retiro bancario anulado'])->assertOk();
    expect((float) $box->fresh()->current_balance)->toBe(0.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(5000.0)
        ->and($movement->fresh()->status)->toBe(GeneralCashMovement::STATUS_CANCELLED)
        ->and($movement->fresh()->reversal)->not->toBeNull()
        ->and($movement->fresh()->bankMovement->reversal)->not->toBeNull();
});

it('registra arqueos con diferencia visible y auditoría sin alterar el saldo', function () {
    $box = createGeneralCashBoxForTest();
    $this->postJson(route('admin.general-cash.fundings.store'), [
        'general_cash_box_id' => $box->id, 'company_bank_account_id' => $this->account->id,
        'movement_date' => '2026-08-15', 'amount' => 500, 'operation_number' => 'OP-ARQUEO',
        'idempotency_key' => 'funding-reconciliation-test',
    ])->assertCreated();
    $response = $this->post(route('admin.general-cash.reconciliations.store'), [
        'general_cash_box_id' => $box->id, 'reconciliation_date' => '2026-08-15 18:00:00',
        'physical_balance' => 490, 'responsible_user_id' => $this->user->id,
        'observation' => 'Diferencia pendiente de regularización',
        'support_file' => UploadedFile::fake()->create('arqueo.pdf', 15, 'application/pdf'),
    ], ['Accept' => 'application/json'])->assertCreated();
    $reconciliation = GeneralCashReconciliation::findOrFail($response->json('data.id'));
    expect((float) $reconciliation->system_balance)->toBe(500.0)
        ->and((float) $reconciliation->physical_balance)->toBe(490.0)
        ->and((float) $reconciliation->difference)->toBe(-10.0)
        ->and((float) $box->fresh()->current_balance)->toBe(500.0)
        ->and($reconciliation->documents()->count())->toBe(1);
    $this->getJson(route('admin.general-cash.show', $box))->assertOk()
        ->assertJsonPath('data.reconciliations.0.id', $reconciliation->id)
        ->assertJsonFragment(['title' => 'Arqueo registrado: '.$reconciliation->code]);
});

it('protege el módulo y sus operaciones con permisos independientes', function () {
    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)->get(route('admin.general-cash.index'))->assertForbidden();
    $this->postJson(route('admin.general-cash.expenses.store'), [])->assertForbidden();
    $this->postJson(route('admin.general-cash.fundings.store'), [])->assertForbidden();
});
