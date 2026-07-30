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
        'admin.petty-cash.show',
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

it('entrega la observación original para los modales administrativos y conserva vacío cuando no existe', function () {
    $this->expense->update([
        'observation' => "Información detallada,\ncon sustento adicional.",
    ]);

    $this->actingAs($this->creator)
        ->getJson(route('admin.petty-cash.expenses.pending'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->expense->id)
        ->assertJsonPath('data.0.observation', "Información detallada,\ncon sustento adicional.");

    $this->getJson(route('admin.petty-cash.expenses.detail', $this->expense))
        ->assertOk()
        ->assertJsonPath('data.id', $this->expense->id)
        ->assertJsonPath('data.observation', "Información detallada,\ncon sustento adicional.")
        ->assertJsonPath('data.creator.id', $this->creator->id)
        ->assertJsonCount(0, 'data.documents')
        ->assertJsonCount(0, 'data.observations');

    $document = $this->expense->documents()->create([
        'original_name' => 'comprobante.png',
        'stored_name' => 'comprobante.png',
        'file_path' => 'petty-cash/test/comprobante.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'file_size' => 2048,
        'status' => 'ACTIVE',
        'created_by' => $this->creator->id,
        'updated_by' => $this->creator->id,
    ]);
    $this->getJson(route('admin.petty-cash.expenses.detail', $this->expense))
        ->assertOk()
        ->assertJsonCount(1, 'data.documents')
        ->assertJsonPath('data.documents.0.original_name', 'comprobante.png')
        ->assertJsonPath('data.documents.0.mime_type', 'image/png')
        ->assertJsonPath('data.documents.0.view_url', route('admin.petty-cash.documents.view', $document));

    $this->expense->update(['observation' => null]);

    $this->getJson(route('admin.petty-cash.expenses.pending'))
        ->assertOk()
        ->assertJsonPath('data.0.observation', null);

    $this->get(route('admin.petty-cash.index'))
        ->assertOk()
        ->assertSee('OBSERVACIÓN DEL GASTO')
        ->assertSee('Sin observación registrada.')
        ->assertSee('id="pca_expense_observation"', false)
        ->assertSee('Observación de aprobación (opcional)')
        ->assertSee('id="pca_observation"', false)
        ->assertSee('id="pettyCashExpenseDetailModal"', false)
        ->assertSee('Detalle del gasto')
        ->assertSee('HISTORIAL ADMINISTRATIVO')
        ->assertSee('id="pced_summary_tab"', false)
        ->assertSee('id="pced_documents_tab"', false)
        ->assertSee('id="pced_history_tab"', false)
        ->assertSee('id="pced_approval_tab"', false)
        ->assertSee('Canje / Aprobación')
        ->assertSee('id="pettyCashImageEditorModal"', false)
        ->assertSee('Editar comprobante')
        ->assertSee('Girar izquierda')
        ->assertSee('Girar derecha')
        ->assertSee('Aplicar cambios');

    $unauthorized = User::factory()->create();
    $this->actingAs($unauthorized)
        ->getJson(route('admin.petty-cash.expenses.detail', $this->expense))
        ->assertForbidden();
});

it('observa, corrige y aprueba un gasto sin afectar saldo antes de la aprobación', function () {
    $observeResponse = $this->actingAs($this->observer)
        ->postJson(route('admin.petty-cash.expenses.observe', $this->expense), [
            'observation' => 'Detallar origen, destino, mercadería trasladada y orden relacionada.',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('expense.status', PettyCashExpense::APPROVAL_OBSERVED)
        ->assertJsonPath('expense.status_label', 'Observado')
        ->assertJsonPath('counts.pending', 0)
        ->assertJsonPath('counts.observed', 1);

    $this->assertDatabaseHas('petty_cash_expenses', [
        'id' => $this->expense->id,
        'approval_status' => PettyCashExpense::APPROVAL_OBSERVED,
        'approved_at' => null,
        'approved_by_user_id' => null,
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
    $this->getJson(route('admin.petty-cash.expenses.observed'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('data.0.id', $this->expense->id);
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
    $correctionResponse = $this->actingAs($this->creator)
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
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Gasto corregido y enviado nuevamente para aprobación.')
        ->assertJsonPath('expense.approval_status', PettyCashExpense::APPROVAL_PENDING)
        ->assertJsonPath('expense.observations.0.status', PettyCashExpenseObservation::STATUS_RESOLVED)
        ->assertJsonPath(
            'expense.observations.0.correction_comment',
            'Se detalló el origen, destino, mercadería trasladada y la orden relacionada.'
        )
        ->assertJsonPath('latest_lifted_observation.resolved_by', $this->creator->id)
        ->assertJsonPath('latest_lifted_observation.resolver.id', $this->creator->id)
        ->assertJsonPath('counts.pending', 1)
        ->assertJsonPath('counts.observed', 0)
        ->assertJsonCount(1, 'history');

    expect($correctionResponse->json('expense.approved_at'))->toBeNull()
        ->and($correctionResponse->json('expense.approved_by_user_id'))->toBeNull();

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
    $this->getJson(route('admin.petty-cash.expenses.detail', $this->expense))
        ->assertOk()
        ->assertJsonPath('data.observations.0.observation', 'Detallar origen, destino, mercadería trasladada y orden relacionada.')
        ->assertJsonPath('data.observations.0.status', PettyCashExpenseObservation::STATUS_RESOLVED)
        ->assertJsonPath('data.observations.0.resolver.id', $this->creator->id)
        ->assertJsonPath(
            'data.observations.0.correction_comment',
            'Se detalló el origen, destino, mercadería trasladada y la orden relacionada.'
        );

    $reobserveResponse = $this->actingAs($this->observer)
        ->postJson(route('admin.petty-cash.expenses.observe', $this->expense), [
            'observation' => 'Adjuntar también el comprobante emitido por la agencia de transporte.',
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('expense.status', PettyCashExpense::APPROVAL_OBSERVED)
        ->assertJsonPath('counts.pending', 0)
        ->assertJsonPath('counts.observed', 1);

    expect(PettyCashExpenseObservation::where('petty_cash_expense_id', $this->expense->id)->count())->toBe(2);
    $this->assertDatabaseHas('petty_cash_expenses', [
        'id' => $this->expense->id,
        'approval_status' => PettyCashExpense::APPROVAL_OBSERVED,
        'approved_at' => null,
        'approved_by_user_id' => null,
    ]);
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
    $this->assertDatabaseHas('petty_cash_boxes', [
        'id' => $this->boxId,
        'total_expenses' => 0,
        'cash_balance' => 2000,
        'reimbursement_amount' => 0,
    ]);
    $this->getJson(route('admin.petty-cash.expenses.pending'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
    $this->getJson(route('admin.petty-cash.expenses.observed'))
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonCount(2, 'data.0.observations');

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
        ->assertOk()
        ->assertJsonPath('expense.approval_status', PettyCashExpense::APPROVAL_PENDING)
        ->assertJsonPath(
            'latest_lifted_observation.correction_comment',
            'Se adjuntó el comprobante solicitado de la agencia de transporte.'
        )
        ->assertJsonPath('counts.pending', 1)
        ->assertJsonPath('counts.observed', 0)
        ->assertJsonCount(2, 'history');

    $this->postJson(route('admin.petty-cash.expenses.approve', $this->expense), [
        'approval_observation' => 'Sustento y correcciones conformes.',
    ])
        ->assertOk();

    $this->getJson(route('admin.petty-cash.expenses.detail', $this->expense))
        ->assertOk()
        ->assertJsonPath('data.approval_status', PettyCashExpense::APPROVAL_APPROVED)
        ->assertJsonPath('data.approval_observation', 'Sustento y correcciones conformes.')
        ->assertJsonPath('data.approved_by.id', $this->creator->id)
        ->assertJsonCount(2, 'data.observations');

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
