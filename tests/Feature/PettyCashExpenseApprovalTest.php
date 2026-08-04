<?php

use App\Models\Bank;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\PettyCashExpense;
use App\Models\PettyCashReplenishment;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->company = Company::create(['business_name' => 'DROPAIV S.A.C.', 'trade_name' => 'DROPAIV', 'ruc' => '20123456789', 'status' => true]);
    $this->currency = Currency::create(['code' => 'PEN', 'description' => 'Soles', 'symbol' => 'S/', 'status' => 'ACTIVE']);
    $this->manager = User::factory()->create();
    foreach (['admin.petty-cash.approved-amount.index', 'admin.petty-cash.approved-amount.update', 'admin.petty-cash.show', 'admin.petty-cash.store', 'admin.petty-cash.expenses.store', 'admin.petty-cash.expenses.approve', 'admin.petty-cash.close', 'admin.petty-cash.replenishments.store'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $this->manager->givePermissionTo(Permission::all());
});

it('registra una reposición sin datos bancarios manuales y recalcula los saldos', function () {
    Storage::fake('public');
    $bank = Bank::create([
        'description' => 'BANCO DE PRUEBA', 'short_name' => 'TEST', 'status' => 'ACTIVE',
    ]);
    $account = CompanyBankAccount::create([
        'company_id' => $this->company->id,
        'bank_id' => $bank->id,
        'currency_id' => $this->currency->id,
        'account_holder' => 'DROPAIV S.A.C.',
        'account_number' => '0011223344',
        'is_detraction' => 'NO',
        'status' => 'ACTIVE',
    ]);
    $this->actingAs($this->manager)->putJson(route('admin.petty-cash.approved-amount.update'), [
        'company_id' => $this->company->id, 'currency_id' => $this->currency->id, 'amount' => 2000, 'active' => true,
    ])->assertOk();
    $boxId = $this->postJson(route('admin.petty-cash.store'), [
        'company_id' => $this->company->id, 'currency_id' => $this->currency->id, 'start_date' => '2026-07-01',
        'responsible_name' => 'RESPONSABLE', 'responsible_dni' => '12345678',
        'supervisor_name' => 'SUPERVISOR', 'supervisor_dni' => '87654321',
    ])->assertCreated()->json('data.id');
    $this->postJson(route('admin.petty-cash.expenses.store', $boxId), [
        'expense_date' => '2026-07-02', 'supplier_name' => 'PROVEEDOR', 'concept' => 'MOVILIDAD', 'amount' => 300,
    ])->assertCreated();
    $expense = PettyCashExpense::latest('id')->firstOrFail();
    $this->postJson(route('admin.petty-cash.expenses.approve', $expense))->assertOk();

    $this->postJson(route('admin.petty-cash.replenishments.store', $boxId), [
        'replenishment_date' => '2026-07-03',
        'amount' => 300,
        'fund_source_company_id' => $this->company->id,
        'fund_source_bank_account_id' => $account->id,
        'observation' => 'REPOSICIÓN COMPLETA',
        'fund_source_receipts' => [UploadedFile::fake()->create('sustento.pdf', 100, 'application/pdf')],
    ])->assertCreated()
        ->assertJsonPath('data.total_spent', 300)
        ->assertJsonPath('data.total_replenished', 300)
        ->assertJsonPath('data.current_balance', 2000)
        ->assertJsonPath('data.pending_replenishment', 0);

    $replenishment = PettyCashReplenishment::latest('id')->firstOrFail();
    expect($replenishment->payment_method)->toBeNull()
        ->and($replenishment->bank_id)->toBeNull()
        ->and($replenishment->bank_account)->toBeNull()
        ->and($replenishment->reference_number)->toBeNull()
        ->and($replenishment->fund_source_company_id)->toBe($this->company->id)
        ->and($replenishment->fund_source_bank_account_id)->toBe($account->id)
        ->and($replenishment->documents)->toHaveCount(1)
        ->and($replenishment->documents->first()->documentType->code)->toBe('CAJA_REP')
        ->and(DocumentType::where('code', 'CAJA_REP')->count())->toBe(1);
});

it('impide cerrar con gastos pendientes y permite cerrar después de resolverlos', function () {
    $this->actingAs($this->manager)->putJson(route('admin.petty-cash.approved-amount.update'), [
        'company_id' => $this->company->id, 'currency_id' => $this->currency->id, 'amount' => 2000, 'active' => true,
    ])->assertOk();
    $boxId = $this->postJson(route('admin.petty-cash.store'), [
        'company_id' => $this->company->id, 'currency_id' => $this->currency->id, 'start_date' => '2026-07-01',
        'responsible_name' => 'RESPONSABLE', 'responsible_dni' => '12345678',
        'supervisor_name' => 'SUPERVISOR', 'supervisor_dni' => '87654321',
    ])->assertCreated()->json('data.id');

    $this->postJson(route('admin.petty-cash.expenses.store', $boxId), [
        'expense_date' => '2026-07-02', 'supplier_name' => 'PROVEEDOR', 'concept' => 'MOVILIDAD', 'amount' => 100,
    ])->assertCreated();
    $expense = PettyCashExpense::latest('id')->firstOrFail();

    $this->postJson(route('admin.petty-cash.close', $boxId))
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'No se puede cerrar la caja chica porque existen gastos pendientes de aprobación. Apruebe o rechace los gastos antes de cerrar.'
        );
    $this->assertDatabaseHas('petty_cash_boxes', [
        'id' => $boxId, 'status' => 'OPEN', 'closed_at' => null, 'closed_by' => null,
    ]);

    $this->postJson(route('admin.petty-cash.expenses.reject', $expense), [
        'approval_observation' => 'No corresponde',
    ])->assertOk();

    $this->postJson(route('admin.petty-cash.close', $boxId))
        ->assertOk()
        ->assertJsonPath('message', 'Caja chica cerrada correctamente.');
    $this->assertDatabaseHas('petty_cash_boxes', ['id' => $boxId, 'status' => 'CLOSED']);
});

it('solo afecta los saldos al aprobar y excluye los gastos rechazados', function () {
    $this->actingAs($this->manager)->putJson(route('admin.petty-cash.approved-amount.update'), [
        'company_id' => $this->company->id, 'currency_id' => $this->currency->id, 'amount' => 2000, 'active' => true,
    ])->assertOk();
    $boxId = $this->postJson(route('admin.petty-cash.store'), [
        'company_id' => $this->company->id, 'currency_id' => $this->currency->id, 'start_date' => '2026-07-01',
        'responsible_name' => 'RESPONSABLE', 'responsible_dni' => '12345678',
        'supervisor_name' => 'SUPERVISOR', 'supervisor_dni' => '87654321',
    ])->assertCreated()->json('data.id');

    $this->postJson(route('admin.petty-cash.expenses.store', $boxId), [
        'expense_date' => '2026-07-02', 'supplier_name' => 'PROVEEDOR UNO', 'concept' => 'MOVILIDAD', 'amount' => 300,
    ])->assertCreated()->assertJsonPath('message', 'Gasto registrado correctamente. Queda pendiente de aprobación administrativa.');
    $expense = PettyCashExpense::latest('id')->first();
    expect($expense->approval_status)->toBe(PettyCashExpense::APPROVAL_PENDING);
    $this->assertDatabaseHas('petty_cash_boxes', ['id' => $boxId, 'total_expenses' => 0, 'cash_balance' => 2000, 'reimbursement_amount' => 0]);

    $this->postJson(route('admin.petty-cash.expenses.approve', $expense), ['approval_observation' => 'Sustento conforme'])->assertOk();
    $this->assertDatabaseHas('petty_cash_expenses', ['id' => $expense->id, 'approval_status' => 'aprobado', 'approved_by_user_id' => $this->manager->id]);
    $this->assertDatabaseHas('petty_cash_boxes', ['id' => $boxId, 'total_expenses' => 300, 'cash_balance' => 1700, 'reimbursement_amount' => 300]);

    $this->postJson(route('admin.petty-cash.expenses.store', $boxId), [
        'expense_date' => '2026-07-03', 'supplier_name' => 'PROVEEDOR DOS', 'concept' => 'UTILES', 'amount' => 200,
    ])->assertCreated();
    $rejected = PettyCashExpense::latest('id')->first();
    $this->postJson(route('admin.petty-cash.expenses.reject', $rejected), ['approval_observation' => 'Comprobante ilegible'])->assertOk();
    $this->getJson(route('admin.petty-cash.expenses.detail', $rejected))
        ->assertOk()
        ->assertJsonPath('data.approval_status', PettyCashExpense::APPROVAL_REJECTED)
        ->assertJsonPath('data.approval_observation', 'Comprobante ilegible')
        ->assertJsonPath('data.rejected_by.id', $this->manager->id);
    $this->assertDatabaseHas('petty_cash_expenses', ['id' => $rejected->id, 'approval_status' => 'rechazado', 'rejected_by_user_id' => $this->manager->id]);
    $this->assertDatabaseHas('petty_cash_boxes', ['id' => $boxId, 'total_expenses' => 300, 'cash_balance' => 1700, 'reimbursement_amount' => 300]);
});
