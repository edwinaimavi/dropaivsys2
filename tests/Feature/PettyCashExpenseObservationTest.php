<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\PettyCashExpense;
use App\Models\PettyCashExpenseObservation;
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
        'admin.petty-cash.expenses.approve',
        'admin.petty-cash.expenses.observe',
    ] as $name) {
        Permission::findOrCreate($name, 'web');
    }

    $this->creator = User::factory()->create();
    $this->creator->givePermissionTo(Permission::all());
    $this->observer = User::factory()->create();
    $this->observer->givePermissionTo('admin.petty-cash.expenses.observe');

    $this->actingAs($this->creator)->putJson(route('admin.petty-cash.approved-amount.update'), [
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

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), [
        'expense_date' => '2026-07-02',
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_correlative' => '123',
        'supplier_name' => 'PROVEEDOR',
        'concept' => 'PAGO DE TRANSPORTE',
        'amount' => 300,
    ])->assertCreated();

    $this->expense = PettyCashExpense::latest('id')->firstOrFail();
});

it('observa, corrige y aprueba un gasto sin afectar saldo antes de la aprobación', function () {
    $this->actingAs($this->observer)
        ->postJson(route('admin.petty-cash.expenses.observe', $this->expense), [
            'observation' => 'Detallar origen, destino, mercadería trasladada y orden relacionada.',
        ])
        ->assertOk();

    $this->assertDatabaseHas('petty_cash_expenses', [
        'id' => $this->expense->id,
        'approval_status' => PettyCashExpense::APPROVAL_OBSERVED,
    ]);
    $this->assertDatabaseHas('petty_cash_expense_observations', [
        'petty_cash_expense_id' => $this->expense->id,
        'status' => PettyCashExpenseObservation::STATUS_OPEN,
        'observed_by' => $this->observer->id,
    ]);
    $this->assertDatabaseHas('petty_cash_boxes', [
        'id' => $this->boxId,
        'total_expenses' => 0,
        'cash_balance' => 2000,
        'reimbursement_amount' => 0,
    ]);
    $this->getJson(route('admin.petty-cash.expenses.pending'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->actingAs($this->creator)
        ->getJson(route('admin.petty-cash.expenses.observed'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('data.0.current_observation.observation', 'Detallar origen, destino, mercadería trasladada y orden relacionada.');
    $this->getJson(route('admin.petty-cash.list'))
        ->assertOk()
        ->assertJsonPath('summary.pending_expenses_count', 0)
        ->assertJsonPath('summary.observed_expenses_count', 1);

    $this->actingAs($this->observer)
        ->postJson(route('admin.petty-cash.expenses.approve', $this->expense))
        ->assertForbidden();

    $this->creator->revokePermissionTo('admin.petty-cash.expenses.update');
    $this->actingAs($this->creator)
        ->putJson(route('admin.petty-cash.expenses.update', $this->expense), [
            'expense_date' => '2026-07-02',
            'document_type' => 'FACTURA',
            'document_series' => 'F001',
            'document_correlative' => '123',
            'supplier_name' => 'PROVEEDOR',
            'concept' => 'TRANSPORTE DE MERCADERÍA DE LIMA A CALLAO PARA OC-100',
            'amount' => 300,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('correction_comment');

    $this->actingAs($this->creator)
        ->putJson(route('admin.petty-cash.expenses.update', $this->expense), [
            'expense_date' => '2026-07-02',
            'document_type' => 'FACTURA',
            'document_series' => 'F001',
            'document_correlative' => '123',
            'supplier_name' => 'PROVEEDOR',
            'concept' => 'TRANSPORTE DE MERCADERÍA DE LIMA A CALLAO PARA OC-100',
            'observation' => 'SOLICITADO POR LOGÍSTICA',
            'correction_comment' => 'Se detalló el origen, destino, mercadería trasladada y la orden relacionada.',
            'amount' => 300,
        ])
        ->assertOk();

    $this->assertDatabaseHas('petty_cash_expenses', [
        'id' => $this->expense->id,
        'approval_status' => PettyCashExpense::APPROVAL_PENDING,
    ]);
    $this->assertDatabaseHas('petty_cash_expense_observations', [
        'petty_cash_expense_id' => $this->expense->id,
        'status' => PettyCashExpenseObservation::STATUS_RESOLVED,
        'resolved_by' => $this->creator->id,
        'correction_comment' => 'Se detalló el origen, destino, mercadería trasladada y la orden relacionada.',
    ]);
    $this->assertDatabaseHas('petty_cash_boxes', [
        'id' => $this->boxId,
        'total_expenses' => 0,
        'cash_balance' => 2000,
    ]);
    $this->getJson(route('admin.petty-cash.expenses.observed'))
        ->assertOk()
        ->assertJsonPath('count', 0);
    $this->getJson(route('admin.petty-cash.list'))
        ->assertOk()
        ->assertJsonPath('summary.pending_expenses_count', 1)
        ->assertJsonPath('summary.observed_expenses_count', 0);
    $this->getJson(route('admin.petty-cash.expenses.pending'))
        ->assertOk()
        ->assertJsonPath('data.0.observations.0.status', PettyCashExpenseObservation::STATUS_RESOLVED)
        ->assertJsonPath(
            'data.0.observations.0.correction_comment',
            'Se detalló el origen, destino, mercadería trasladada y la orden relacionada.'
        )
        ->assertJsonPath('data.0.observations.0.resolver.id', $this->creator->id);

    $this->actingAs($this->observer)
        ->postJson(route('admin.petty-cash.expenses.observe', $this->expense), [
            'observation' => 'Adjuntar también el comprobante emitido por la agencia de transporte.',
        ])
        ->assertOk();

    expect(PettyCashExpenseObservation::where('petty_cash_expense_id', $this->expense->id)->count())->toBe(2);
    $this->assertDatabaseHas('petty_cash_expense_observations', [
        'petty_cash_expense_id' => $this->expense->id,
        'observation' => 'Adjuntar también el comprobante emitido por la agencia de transporte.',
        'status' => PettyCashExpenseObservation::STATUS_OPEN,
    ]);
    $this->assertDatabaseHas('petty_cash_expense_observations', [
        'petty_cash_expense_id' => $this->expense->id,
        'correction_comment' => 'Se detalló el origen, destino, mercadería trasladada y la orden relacionada.',
        'status' => PettyCashExpenseObservation::STATUS_RESOLVED,
    ]);

    $this->actingAs($this->creator)
        ->putJson(route('admin.petty-cash.expenses.update', $this->expense), [
            'expense_date' => '2026-07-02',
            'document_type' => 'FACTURA',
            'document_series' => 'F001',
            'document_correlative' => '123',
            'supplier_name' => 'PROVEEDOR',
            'concept' => 'TRANSPORTE DE MERCADERÍA DE LIMA A CALLAO PARA OC-100',
            'observation' => 'SOLICITADO POR LOGÍSTICA',
            'correction_comment' => 'Se adjuntó el comprobante solicitado de la agencia de transporte.',
            'amount' => 300,
        ])
        ->assertOk();

    $this->postJson(route('admin.petty-cash.expenses.approve', $this->expense))
        ->assertOk();

    $this->assertDatabaseHas('petty_cash_boxes', [
        'id' => $this->boxId,
        'total_expenses' => 300,
        'cash_balance' => 1700,
        'reimbursement_amount' => 300,
    ]);
});

it('impide observar sin el permiso específico', function () {
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->postJson(route('admin.petty-cash.expenses.observe', $this->expense), [
            'observation' => 'Debe ampliar el detalle y adjuntar sustento.',
        ])
        ->assertForbidden();

    expect($this->expense->fresh()->approval_status)->toBe(PettyCashExpense::APPROVAL_PENDING);
});
