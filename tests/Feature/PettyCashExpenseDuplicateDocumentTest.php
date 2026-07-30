<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\PettyCashBox;
use App\Models\PettyCashExpense;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->company = Company::create([
        'business_name' => 'DROPAIV S.A.C.',
        'trade_name' => 'DROPAIV',
        'ruc' => '20123456789',
        'status' => true,
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);

    foreach ([
        'admin.petty-cash.approved-amount.index',
        'admin.petty-cash.approved-amount.update',
        'admin.petty-cash.index',
        'admin.petty-cash.store',
        'admin.petty-cash.expenses.store',
        'admin.petty-cash.expenses.update',
        'admin.petty-cash.expenses.observe',
    ] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(Permission::all());
    $this->actingAs($this->user);

    $this->putJson(route('admin.petty-cash.approved-amount.update'), [
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

function expensePayload(array $overrides = []): array
{
    return array_merge([
        'expense_date' => '2026-07-02',
        'document_type' => 'RECIBO',
        'document_series' => 'R',
        'document_correlative' => '001',
        'supplier_ruc' => '20609784050',
        'supplier_name' => 'PROVEEDOR DE PRUEBA',
        'concept' => 'MOVILIDAD',
        'amount' => 100,
    ], $overrides);
}

it('normaliza y bloquea el mismo comprobante para el mismo proveedor sin alterar saldos', function () {
    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload([
        'document_type' => ' recibo ',
        'document_series' => ' r ',
        'document_correlative' => ' 001 ',
        'supplier_ruc' => ' 20609784050 ',
    ]))->assertCreated();

    $expense = PettyCashExpense::latest('id')->firstOrFail();
    expect($expense->document_type)->toBe('RECIBO')
        ->and($expense->document_series)->toBe('R')
        ->and($expense->document_correlative)->toBe('001')
        ->and($expense->supplier_ruc)->toBe('20609784050');

    $this->getJson(route('admin.petty-cash.expenses.check-document', [
        'document_type' => 'RECIBO',
        'document_series' => 'R',
        'document_correlative' => '001',
        'supplier_ruc' => '20609784050',
    ]))->assertOk()->assertJsonPath('exists', true);

    $this->getJson(route('admin.petty-cash.expenses.check-document', [
        'document_type' => 'RECIBO',
        'document_series' => 'R',
        'document_correlative' => '001',
        'supplier_ruc' => '20609784050',
        'expense_id' => $expense->id,
    ]))->assertOk()->assertJsonPath('exists', false);

    $before = PettyCashBox::findOrFail($this->boxId)
        ->only(['total_expenses', 'cash_balance', 'reimbursement_amount']);

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('document_correlative')
        ->assertJsonPath(
            'errors.document_correlative.0',
            'El comprobante RECIBO R-001 ya fue registrado para el proveedor con RUC 20609784050.'
        );

    expect(PettyCashExpense::count())->toBe(1)
        ->and(PettyCashBox::findOrFail($this->boxId)
            ->only(['total_expenses', 'cash_balance', 'reimbursement_amount']))->toBe($before);
});

it('permite la misma numeración con otro RUC o tipo de comprobante', function () {
    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload())
        ->assertCreated();

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload([
        'supplier_ruc' => '20111111111',
    ]))->assertCreated();

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload([
        'document_type' => 'TICKET',
    ]))->assertCreated();

    expect(PettyCashExpense::count())->toBe(3);
});

it('ignora el propio gasto al editar y bloquea el comprobante de otro gasto', function () {
    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload())
        ->assertCreated();
    $first = PettyCashExpense::latest('id')->firstOrFail();

    $this->putJson(route('admin.petty-cash.expenses.update', $first), expensePayload([
        'concept' => 'MOVILIDAD ACTUALIZADA',
    ]))->assertOk();

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload([
        'document_correlative' => '002',
    ]))->assertCreated();

    $this->putJson(route('admin.petty-cash.expenses.update', $first), expensePayload([
        'document_correlative' => '002',
    ]))->assertUnprocessable()->assertJsonValidationErrors('document_correlative');

    expect($first->fresh()->document_correlative)->toBe('001');
});

it('bloquea un comprobante duplicado al corregir un gasto observado', function () {
    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload())
        ->assertCreated();
    $observed = PettyCashExpense::latest('id')->firstOrFail();

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload([
        'document_correlative' => '002',
    ]))->assertCreated();

    $this->postJson(route('admin.petty-cash.expenses.observe', $observed), [
        'observation' => 'Debe corregir los datos del comprobante registrado.',
    ])->assertOk();

    $this->putJson(route('admin.petty-cash.expenses.update', $observed), expensePayload([
        'document_correlative' => '002',
        'correction_comment' => 'Se corrigieron los datos solicitados por administración.',
    ]))->assertUnprocessable()->assertJsonValidationErrors('document_correlative');

    expect($observed->fresh()->approval_status)->toBe(PettyCashExpense::APPROVAL_OBSERVED)
        ->and($observed->fresh()->document_correlative)->toBe('001');
});
