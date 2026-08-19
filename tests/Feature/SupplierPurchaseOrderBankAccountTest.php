<?php

use App\Http\Controllers\Admin\SupplierPurchaseOrderController;
use App\Models\Bank;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user = User::factory()->create();
    Permission::findOrCreate('admin.supplier-purchase-orders.index', 'web');
    $this->user->givePermissionTo('admin.supplier-purchase-orders.index');
    $this->actingAs($this->user);

    $this->praga = Company::create([
        'business_name' => 'PRAGA MEDICAL IMPORT S.A.C.',
        'trade_name' => 'PRAGA MEDICAL',
        'ruc' => '20999999991',
        'status' => true,
    ]);
    $this->dropaiv = Company::create([
        'business_name' => 'DROGUERIA DROPAIV S.A.C.',
        'trade_name' => 'DROGUERIA DROPAIV',
        'ruc' => '20999999992',
        'status' => true,
    ]);
    $this->bank = Bank::create([
        'description' => 'BANCO BBVA',
        'short_name' => 'BBVA',
        'status' => 'ACTIVE',
    ]);
    $this->pen = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->usd = Currency::create([
        'code' => 'USD',
        'description' => 'Dolares',
        'symbol' => '$',
        'status' => 'ACTIVE',
    ]);
});

function supplierOrderCompanyAccount(array $attributes): CompanyBankAccount
{
    return CompanyBankAccount::create(array_merge([
        'bank_id' => test()->bank->id,
        'account_holder' => 'TITULAR DE PRUEBA',
        'is_detraction' => 'NO',
        'status' => 'ACTIVE',
        'current_balance' => 0,
    ], $attributes));
}

it('lista solo cuentas activas de la empresa y moneda seleccionadas', function () {
    $pragaPen = supplierOrderCompanyAccount([
        'company_id' => $this->praga->id,
        'currency_id' => $this->pen->id,
        'account_number' => '456464646',
        'current_balance' => -68800,
    ]);
    $dropaivPen = supplierOrderCompanyAccount([
        'company_id' => $this->dropaiv->id,
        'currency_id' => $this->pen->id,
        'account_number' => 'DROP-PEN-001',
    ]);
    supplierOrderCompanyAccount([
        'company_id' => $this->praga->id,
        'currency_id' => $this->usd->id,
        'account_number' => 'PRAGA-USD-001',
    ]);
    supplierOrderCompanyAccount([
        'company_id' => $this->praga->id,
        'currency_id' => $this->pen->id,
        'account_number' => 'PRAGA-INACTIVA',
        'status' => 'INACTIVE',
    ]);

    $this->getJson(route('admin.supplier-purchase-orders.companyBankAccounts', [
        'company_id' => $this->praga->id,
        'currency_id' => $this->pen->id,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'accounts')
        ->assertJsonPath('accounts.0.id', $pragaPen->id)
        ->assertJsonPath('accounts.0.company_id', $this->praga->id)
        ->assertJsonPath('accounts.0.currency_id', $this->pen->id)
        ->assertJsonPath('accounts.0.balance', -68800)
        ->assertJsonPath('accounts.0.label', 'PRAGA MEDICAL · BBVA · PEN · 456464646 · Saldo: S/ -68,800.00');

    $this->getJson(route('admin.supplier-purchase-orders.companyBankAccounts', [
        'company_id' => $this->dropaiv->id,
        'currency_id' => $this->pen->id,
    ]))
        ->assertOk()
        ->assertJsonCount(1, 'accounts')
        ->assertJsonPath('accounts.0.id', $dropaivPen->id)
        ->assertJsonPath('accounts.0.company_id', $this->dropaiv->id);

    $this->getJson(route('admin.supplier-purchase-orders.companyBankAccounts', [
        'company_id' => $this->dropaiv->id,
        'currency_id' => $this->usd->id,
    ]))
        ->assertOk()
        ->assertJsonCount(0, 'accounts');
});

it('bloquea una cuenta bancaria que pertenece a otra empresa', function () {
    $dropaivAccount = supplierOrderCompanyAccount([
        'company_id' => $this->dropaiv->id,
        'currency_id' => $this->pen->id,
        'account_number' => 'DROP-PEN-002',
    ]);
    $method = new ReflectionMethod(SupplierPurchaseOrderController::class, 'validateAdvancePaymentBankAccounts');
    $exception = null;

    try {
        $method->invoke(
            app(SupplierPurchaseOrderController::class),
            collect([['company_bank_account_id' => $dropaivAccount->id]]),
            $this->praga->id,
            $this->pen->id
        );
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->errors()['advance_payments.0.company_bank_account_id'][0])
        ->toBe('La cuenta bancaria seleccionada no pertenece a la empresa de la orden o no corresponde a la moneda seleccionada.');
});
