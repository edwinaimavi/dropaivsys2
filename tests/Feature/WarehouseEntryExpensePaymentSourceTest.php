<?php

use App\Http\Controllers\Admin\WarehouseEntryController;
use App\Models\Bank;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\GeneralCashBox;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
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
        'admin.warehouse-entries.show', 'admin.warehouse-entries.expenses.index',
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
