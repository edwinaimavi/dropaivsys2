<?php

use App\Http\Controllers\Admin\WarehouseEntryController;
use App\Models\Bank;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\DetractionType;
use App\Models\GeneralCashBox;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDocument;
use Database\Seeders\DetractionTypeSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->company = Company::create([
        'business_name' => 'DROPAIV FUENTES S.A.C.', 'ruc' => '20977777771', 'status' => true,
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN', 'description' => 'Soles', 'symbol' => 'S/', 'status' => 'ACTIVE',
    ]);
    $this->supplier = Supplier::create([
        'ruc' => '20607777771', 'business_name' => 'PROVEEDOR FUENTES S.A.C.',
        'short_name' => 'PROVEEDOR FUENTES', 'supplier_type' => 'SERVICIOS',
        'payment_condition' => 'CONTADO', 'status' => 'ACTIVE',
    ]);
    $this->user = User::factory()->create();
    foreach ([
        'admin.warehouse-entries.index', 'admin.warehouse-entries.show', 'admin.warehouse-entries.expenses.index',
        'admin.warehouse-entries.expenses.store', 'admin.warehouse-entries.expenses.update',
        'admin.warehouse-entries.expenses.approve',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo(Permission::all());
    $this->actingAs($this->user);
    $this->entry = WarehouseEntry::create([
        'entry_number' => 'ING-FUENTES-001', 'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id, 'currency_id' => $this->currency->id,
        'status' => 'registered',
    ]);
    $this->box = GeneralCashBox::create([
        'code' => 'CG-TEST-001', 'company_id' => $this->company->id,
        'currency_id' => $this->currency->id, 'name' => 'Caja principal',
        'responsible_user_id' => $this->user->id, 'current_balance' => 500,
        'status' => GeneralCashBox::STATUS_ACTIVE,
    ]);
    $bank = Bank::create(['description' => 'BANCO TEST', 'short_name' => 'BT', 'status' => 'ACTIVE']);
    $this->account = CompanyBankAccount::create([
        'company_id' => $this->company->id, 'bank_id' => $bank->id,
        'currency_id' => $this->currency->id, 'account_holder' => 'DROPAIV FUENTES S.A.C.',
        'account_number' => '001-999999', 'current_balance' => 1000,
        'is_detraction' => 'NO', 'status' => 'ACTIVE',
    ]);
});

it('carga el catálogo vigente de los anexos SUNAT', function () {
    $legacyType = DetractionType::create([
        'appendix' => 'ANEXO_III',
        'code' => 'ANEXO_III_10',
        'name' => 'Demás servicios gravados con el IGV',
        'percentage' => 12,
        'status' => DetractionType::STATUS_ACTIVE,
    ]);
    app(DetractionTypeSeeder::class)->run();

    expect(DetractionType::where('status', DetractionType::STATUS_ACTIVE)->count())->toBe(36)
        ->and((float) DetractionType::where('code', '001')->value('percentage'))->toBe(10.0)
        ->and((float) DetractionType::where('code', '002')->value('percentage'))->toBe(3.85)
        ->and((float) DetractionType::where('code', '003')->value('percentage'))->toBe(10.0)
        ->and((float) DetractionType::where('code', '021')->value('percentage'))->toBe(10.0)
        ->and((float) DetractionType::where('code', '037')->value('percentage'))->toBe(12.0)
        ->and((float) DetractionType::where('code', '044')->value('percentage'))->toBe(12.0)
        ->and((float) DetractionType::where('code', '045')->value('percentage'))->toBe(10.0)
        ->and((float) DetractionType::where('code', '099')->value('percentage'))->toBe(8.0)
        ->and(DetractionType::where('code', '037')->value('id'))->toBe($legacyType->id);

    $this->get(route('admin.warehouse-entries.index'))
        ->assertOk()
        ->assertSee('warehouse_entry_expense_detraction_type_id', false)
        ->assertSee('037 &middot; Demás servicios gravados con el IGV &middot; 12%', false);
});

function warehouseSourceExpensePayload(string $source, string $responsible, array $extra = []): array
{
    return array_merge([
        'source_type' => $source, 'expense_type' => 'other',
        'expense_category' => 'other_expense', 'cost_origin' => 'third_party',
        'provider_name' => $responsible, 'document_type' => 'SIN_COMPROBANTE',
        'document_date' => '2026-08-17', 'amount' => 25,
        'affects_igv' => false, 'affects_inventory_cost' => false,
        'description' => 'GASTO PENDIENTE DE PRUEBA',
    ], $extra);
}

it('registra fuentes manual, Caja General y Banco como pendientes sin afectar saldos', function () {
    $cashBalance = $this->box->current_balance;
    $bankBalance = $this->account->current_balance;
    $sync = new ReflectionMethod(WarehouseEntryController::class, 'syncEntryExpenses');
    $sync->invoke(app(WarehouseEntryController::class), $this->entry, [
        warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_MANUAL, 'RESPONSABLE MANUAL'),
        warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_GENERAL_CASH, 'RESPONSABLE CAJA', [
            'general_cash_box_id' => $this->box->id,
        ]),
        warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_BANK, 'RESPONSABLE BANCO', [
            'company_bank_account_id' => $this->account->id,
        ]),
    ], [], []);

    $expenses = $this->entry->expenses()->orderBy('id')->get();
    expect($expenses)->toHaveCount(3)
        ->and($expenses->pluck('approval_status')->unique()->all())->toBe([WarehouseEntryExpense::APPROVAL_PENDING])
        ->and($expenses->firstWhere('source_type', WarehouseEntryExpense::SOURCE_GENERAL_CASH)->general_cash_box_id)->toBe($this->box->id)
        ->and($expenses->firstWhere('source_type', WarehouseEntryExpense::SOURCE_GENERAL_CASH)->general_cash_movement_id)->toBeNull()
        ->and($expenses->firstWhere('source_type', WarehouseEntryExpense::SOURCE_BANK)->company_bank_account_id)->toBe($this->account->id)
        ->and($expenses->firstWhere('source_type', WarehouseEntryExpense::SOURCE_BANK)->bank_movement_id)->toBeNull()
        ->and($this->box->fresh()->current_balance)->toBe($cashBalance)
        ->and($this->account->fresh()->current_balance)->toBe($bankBalance);

    $this->getJson(route('admin.warehouse-entries.show', $this->entry))
        ->assertOk()
        ->assertJsonPath('data.expenses.1.general_cash_box.code', 'CG-TEST-001')
        ->assertJsonPath('data.expenses.2.company_bank_account.account_number', '001-999999');
});

it('conserva trazabilidad al aprobar un gasto manual', function () {
    $expense = $this->entry->expenses()->create([
        ...warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_BANK, 'RESPONSABLE BANCO', [
            'company_bank_account_id' => $this->account->id,
        ]),
        ...WarehouseEntryExpense::documentMetadata('SIN_COMPROBANTE'),
        'currency_id' => $this->currency->id, 'taxable_amount' => 25, 'igv_amount' => 0,
        'total_amount' => 25, 'status' => 'ACTIVE',
        'approval_status' => WarehouseEntryExpense::APPROVAL_PENDING,
        'created_by' => $this->user->id,
    ]);

    $this->postJson(route('admin.warehouse-entries.expenses.approval', [$this->entry, $expense]), [
        'approval_status' => WarehouseEntryExpense::APPROVAL_APPROVED,
        'approval_observation' => 'Sustento conforme',
    ])->assertOk()->assertJsonPath('data.approval_status', WarehouseEntryExpense::APPROVAL_APPROVED);

    $expense->refresh();
    expect($expense->approved_by)->toBe($this->user->id)
        ->and($expense->approved_at)->not->toBeNull()
        ->and($expense->approval_observation)->toBe('SUSTENTO CONFORME')
        ->and($expense->bank_movement_id)->toBeNull()
        ->and((float) $this->account->fresh()->current_balance)->toBe(1000.0);
});

it('guarda el importe completo cuando el costo no aplica detracción', function () {
    $sync = new ReflectionMethod(WarehouseEntryController::class, 'syncEntryExpenses');
    $sync->invoke(app(WarehouseEntryController::class), $this->entry, [
        warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_MANUAL, 'SERVICIO SIN DETRACCIÓN', [
            'amount' => 1000,
            'applies_detraction' => false,
        ]),
    ], [], []);

    $expense = $this->entry->expenses()->firstOrFail();
    expect($expense->applies_detraction)->toBeFalse()
        ->and($expense->detraction_type_id)->toBeNull()
        ->and((float) $expense->detraction_percentage)->toBe(0.0)
        ->and((float) $expense->detraction_amount)->toBe(0.0)
        ->and((float) $expense->supplier_net_amount)->toBe(1000.0)
        ->and((float) $expense->amount)->toBe(1000.0);
});

it('calcula la detracción desde el catálogo y la recalcula al editar el importe', function () {
    app(DetractionTypeSeeder::class)->run();
    $type = DetractionType::where('code', '037')->firstOrFail();
    $sync = new ReflectionMethod(WarehouseEntryController::class, 'syncEntryExpenses');
    $payload = warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_MANUAL, 'SERVICIO CON DETRACCIÓN', [
        'amount' => 1000,
        'applies_detraction' => true,
        'detraction_type_id' => $type->id,
        'detraction_percentage' => 99,
        'detraction_amount' => 999,
        'supplier_net_amount' => 1,
    ]);
    $sync->invoke(app(WarehouseEntryController::class), $this->entry, [$payload], [], []);

    $expense = $this->entry->expenses()->firstOrFail();
    expect($expense->applies_detraction)->toBeTrue()
        ->and((float) $expense->detraction_percentage)->toBe(12.0)
        ->and((float) $expense->detraction_amount)->toBe(120.0)
        ->and((float) $expense->supplier_net_amount)->toBe(880.0)
        ->and((float) $expense->amount)->toBe(1000.0);

    $payload['id'] = $expense->id;
    $payload['amount'] = 2000;
    $sync->invoke(app(WarehouseEntryController::class), $this->entry, [$payload], [], []);

    $expense->refresh()->load('detractionType');
    expect((float) $expense->amount)->toBe(2000.0)
        ->and((float) $expense->detraction_amount)->toBe(240.0)
        ->and((float) $expense->supplier_net_amount)->toBe(1760.0)
        ->and($expense->detractionType->id)->toBe($type->id);

    $movementType = DetractionType::where('code', '021')->firstOrFail();
    $payload['amount'] = 1000;
    $payload['detraction_type_id'] = $movementType->id;
    $sync->invoke(app(WarehouseEntryController::class), $this->entry, [$payload], [], []);

    $expense->refresh();
    expect((float) $expense->detraction_percentage)->toBe(10.0)
        ->and((float) $expense->detraction_amount)->toBe(100.0)
        ->and((float) $expense->supplier_net_amount)->toBe(900.0)
        ->and((float) $expense->amount)->toBe(1000.0);
});

it('bloquea la detracción sin tipo seleccionado', function () {
    $sync = new ReflectionMethod(WarehouseEntryController::class, 'syncEntryExpenses');

    expect(fn () => $sync->invoke(app(WarehouseEntryController::class), $this->entry, [
        warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_MANUAL, 'SERVICIO INCOMPLETO', [
            'amount' => 1000,
            'applies_detraction' => true,
            'detraction_type_id' => null,
        ]),
    ], [], []))->toThrow(\Illuminate\Validation\ValidationException::class, 'Seleccione el tipo de detracción.');

    $inactiveType = DetractionType::create([
        'appendix' => 'ANEXO_III',
        'code' => 'TEST_INACTIVE',
        'name' => 'Tipo no vigente',
        'percentage' => 12,
        'status' => 'INACTIVE',
    ]);

    expect(fn () => $sync->invoke(app(WarehouseEntryController::class), $this->entry, [
        warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_MANUAL, 'SERVICIO NO VIGENTE', [
            'amount' => 1000,
            'applies_detraction' => true,
            'detraction_type_id' => $inactiveType->id,
        ]),
    ], [], []))->toThrow(\Illuminate\Validation\ValidationException::class, 'El tipo de detracción seleccionado no está vigente.');
});

it('guarda la constancia SUNAT como documento independiente y la conserva al desactivar la detracción', function () {
    Storage::fake('public');
    app(DetractionTypeSeeder::class)->run();
    $type = DetractionType::where('code', '037')->firstOrFail();
    $sync = new ReflectionMethod(WarehouseEntryController::class, 'syncEntryExpenses');
    $payload = warehouseSourceExpensePayload(WarehouseEntryExpense::SOURCE_MANUAL, 'SERVICIO CON CONSTANCIA', [
        'amount' => 1000,
        'applies_detraction' => true,
        'detraction_type_id' => $type->id,
    ]);

    $sync->invoke(app(WarehouseEntryController::class), $this->entry, [$payload], [[
        'detraction_proof_file' => UploadedFile::fake()->create('constancia-sunat.pdf', 128, 'application/pdf'),
    ]], []);

    $expense = $this->entry->expenses()->firstOrFail();
    $document = $expense->documents()->sole();
    expect($document->document_type)->toBe(WarehouseEntryExpenseDocument::TYPE_DETRACTION_PROOF)
        ->and($document->original_name)->toBe('constancia-sunat.pdf');
    Storage::disk('public')->assertExists($document->file_path);

    $payload['id'] = $expense->id;
    $payload['applies_detraction'] = false;
    $payload['detraction_type_id'] = null;
    $sync->invoke(app(WarehouseEntryController::class), $this->entry, [$payload], [[
        'detraction_proof_file' => UploadedFile::fake()->create('archivo-ignorado.pdf', 64, 'application/pdf'),
    ]], []);

    expect($expense->fresh()->applies_detraction)->toBeFalse()
        ->and($expense->documents()->count())->toBe(1)
        ->and($expense->documents()->sole()->original_name)->toBe('constancia-sunat.pdf');
});
