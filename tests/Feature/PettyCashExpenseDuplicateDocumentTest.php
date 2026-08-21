<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\PettyCashBox;
use App\Models\PettyCashExpense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('public');
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

it('genera el recibo interno en PDF y conserva los comprobantes manuales', function () {
    $manualDocument = UploadedFile::fake()->create('sustento-manual.pdf', 80, 'application/pdf');

    $this->getJson(route('admin.petty-cash.expenses.internal-receipt-number', $this->boxId))
        ->assertOk()
        ->assertJson([
            'series' => 'R001',
            'correlative' => '0000011',
            'full_number' => 'R001-0000011',
        ]);

    $createResponse = $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload([
        'document_series' => 'MANUAL',
        'document_correlative' => '9999999',
        'documents' => [$manualDocument],
    ]))->assertCreated();

    $expense = PettyCashExpense::with('documents.documentType')->latest('id')->firstOrFail();
    expect($expense->documents)->toHaveCount(2)
        ->and($expense->document_series)->toBe('R001')
        ->and($expense->document_correlative)->toBe('0000011')
        ->and($expense->document_full_number)->toBe('R001-0000011');

    $generated = $expense->documents->firstWhere(
        'documentType.code',
        'PETTY_CASH_INTERNAL_RECEIPT'
    );
    expect($generated)->not->toBeNull()
        ->and($generated->mime_type)->toBe('application/pdf')
        ->and($generated->original_name)->toStartWith('recibo-interno-R001-0000011-')
        ->and($createResponse->json('internal_receipt_url'))->toBe(
            route('admin.petty-cash.documents.view', $generated)
        );
    Storage::disk('public')->assertExists($generated->file_path);

    $previousGeneratedPath = $generated->file_path;
    $updateResponse = $this->putJson(route('admin.petty-cash.expenses.update', $expense), expensePayload([
        'concept' => 'MOVILIDAD ACTUALIZADA',
    ]))->assertOk();

    $expense->refresh()->load('documents.documentType');
    $regenerated = $expense->documents->firstWhere(
        'documentType.code',
        'PETTY_CASH_INTERNAL_RECEIPT'
    );
    expect($expense->documents)->toHaveCount(2)
        ->and($regenerated?->file_path)->not->toBe($previousGeneratedPath)
        ->and($updateResponse->json('internal_receipt_url'))->toBe(
            route('admin.petty-cash.documents.view', $regenerated)
        );
    Storage::disk('public')->assertMissing($previousGeneratedPath);
    Storage::disk('public')->assertExists($regenerated->file_path);

    $this->getJson(route('admin.petty-cash.expenses.internal-receipt-number', [
        'pettyCash' => $this->boxId,
        'expense_id' => $expense->id,
    ]))->assertOk()->assertJson([
        'series' => 'R001',
        'correlative' => '0000011',
    ]);

    $this->getJson(route('admin.petty-cash.expenses.internal-receipt-number', $this->boxId))
        ->assertOk()
        ->assertJsonPath('correlative', '0000012');

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload([
        'supplier_ruc' => '20111111111',
        'document_series' => null,
        'document_correlative' => null,
        'documents' => [],
    ]))->assertCreated();

    $secondReceipt = PettyCashExpense::latest('id')->firstOrFail();
    expect($secondReceipt->document_series)->toBe('R001')
        ->and($secondReceipt->document_correlative)->toBe('0000012');

    $ticketResponse = $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), expensePayload([
        'document_type' => 'TICKET',
        'document_correlative' => '002',
        'documents' => [],
    ]))->assertCreated();

    expect(PettyCashExpense::latest('id')->firstOrFail()->documents()->count())->toBe(0)
        ->and($ticketResponse->json('internal_receipt_url'))->toBeNull();
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

function manualExpensePayload(array $overrides = []): array
{
    return expensePayload(array_merge([
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_correlative' => '001',
    ], $overrides));
}

it('normaliza y bloquea el mismo comprobante para el mismo proveedor sin alterar saldos', function () {
    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload([
        'document_type' => ' factura ',
        'document_series' => ' f001 ',
        'document_correlative' => ' 001 ',
        'supplier_ruc' => ' 20609784050 ',
    ]))->assertCreated();

    $expense = PettyCashExpense::latest('id')->firstOrFail();
    expect($expense->document_type)->toBe('FACTURA')
        ->and($expense->document_series)->toBe('F001')
        ->and($expense->document_correlative)->toBe('001')
        ->and($expense->supplier_ruc)->toBe('20609784050');

    $this->getJson(route('admin.petty-cash.expenses.check-document', [
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_correlative' => '001',
        'supplier_ruc' => '20609784050',
    ]))->assertOk()->assertJsonPath('exists', true);

    $this->getJson(route('admin.petty-cash.expenses.check-document', [
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_correlative' => '001',
        'supplier_ruc' => '20609784050',
        'expense_id' => $expense->id,
    ]))->assertOk()->assertJsonPath('exists', false);

    $before = PettyCashBox::findOrFail($this->boxId)
        ->only(['total_expenses', 'cash_balance', 'reimbursement_amount']);

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('document_correlative')
        ->assertJsonPath(
            'errors.document_correlative.0',
            'El comprobante FACTURA F001-001 ya fue registrado para el proveedor con RUC 20609784050.'
        );

    expect(PettyCashExpense::count())->toBe(1)
        ->and(PettyCashBox::findOrFail($this->boxId)
            ->only(['total_expenses', 'cash_balance', 'reimbursement_amount']))->toBe($before);
});

it('permite la misma numeración con otro RUC o tipo de comprobante', function () {
    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload())
        ->assertCreated();

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload([
        'supplier_ruc' => '20111111111',
    ]))->assertCreated();

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload([
        'document_type' => 'BOLETA',
    ]))->assertCreated();

    expect(PettyCashExpense::count())->toBe(3);
});

it('ignora el propio gasto al editar y bloquea el comprobante de otro gasto', function () {
    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload())
        ->assertCreated();
    $first = PettyCashExpense::latest('id')->firstOrFail();

    $this->putJson(route('admin.petty-cash.expenses.update', $first), manualExpensePayload([
        'concept' => 'MOVILIDAD ACTUALIZADA',
    ]))->assertOk();

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload([
        'document_correlative' => '002',
    ]))->assertCreated();

    $this->putJson(route('admin.petty-cash.expenses.update', $first), manualExpensePayload([
        'document_correlative' => '002',
    ]))->assertUnprocessable()->assertJsonValidationErrors('document_correlative');

    expect($first->fresh()->document_correlative)->toBe('001');
});

it('bloquea un comprobante duplicado al corregir un gasto observado', function () {
    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload())
        ->assertCreated();
    $observed = PettyCashExpense::latest('id')->firstOrFail();

    $this->postJson(route('admin.petty-cash.expenses.store', $this->boxId), manualExpensePayload([
        'document_correlative' => '002',
    ]))->assertCreated();

    $this->postJson(route('admin.petty-cash.expenses.observe', $observed), [
        'observation' => 'Debe corregir los datos del comprobante registrado.',
    ])->assertOk();

    $this->putJson(route('admin.petty-cash.expenses.update', $observed), manualExpensePayload([
        'document_correlative' => '002',
        'correction_comment' => 'Se corrigieron los datos solicitados por administración.',
    ]))->assertUnprocessable()->assertJsonValidationErrors('document_correlative');

    expect($observed->fresh()->approval_status)->toBe(PettyCashExpense::APPROVAL_OBSERVED)
        ->and($observed->fresh()->document_correlative)->toBe('001');
});
