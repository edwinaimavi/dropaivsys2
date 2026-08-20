<?php

use App\Http\Controllers\Admin\SupplierPurchaseOrderController;
use App\Models\Bank;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\User;
use App\Services\SupplierPurchaseOrderFinancialService;
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
            collect([[
                'company_bank_account_id' => $dropaivAccount->id,
                'payment_currency_id' => $this->pen->id,
            ]]),
            $this->praga->id
        );
    } catch (ValidationException $caught) {
        $exception = $caught;
    }

    expect($exception)->not->toBeNull()
        ->and($exception->errors()['advance_payments.0.company_bank_account_id'][0])
        ->toBe('La cuenta bancaria seleccionada no pertenece a la empresa de la orden o no corresponde a la moneda seleccionada.');
});

it('calcula y prepara un pago PEN con tipo de cambio individual', function () {
    $account = supplierOrderCompanyAccount([
        'company_id' => $this->praga->id,
        'currency_id' => $this->pen->id,
        'account_number' => 'PRAGA-PEN-PAGO',
    ]);
    $method = new ReflectionMethod(SupplierPurchaseOrderController::class, 'prepareAdvancePayments');

    $payments = $method->invoke(
        app(SupplierPurchaseOrderController::class),
        collect([[
            'purchase_currency_id' => $this->usd->id,
            'payment_currency_id' => $this->pen->id,
            'company_bank_account_id' => $account->id,
            'applied_amount' => 500,
            'exchange_rate' => 3.39,
        ]]),
        $this->usd,
        $this->praga->id,
        app(SupplierPurchaseOrderFinancialService::class)
    );

    expect($payments[0]['applied_amount'])->toBe(500.0)
        ->and($payments[0]['amount'])->toBe(1695.0)
        ->and($payments[0]['amount_pen'])->toBe(1695.0)
        ->and($payments[0]['exchange_rate'])->toBe(3.39);
});

it('fuerza tipo de cambio uno cuando compra y pago usan la misma moneda', function () {
    $account = supplierOrderCompanyAccount([
        'company_id' => $this->praga->id,
        'currency_id' => $this->usd->id,
        'account_number' => 'PRAGA-USD-PAGO',
    ]);
    $method = new ReflectionMethod(SupplierPurchaseOrderController::class, 'prepareAdvancePayments');

    $payments = $method->invoke(
        app(SupplierPurchaseOrderController::class),
        collect([[
            'purchase_currency_id' => $this->usd->id,
            'payment_currency_id' => $this->usd->id,
            'company_bank_account_id' => $account->id,
            'applied_amount' => 300,
            'exchange_rate' => 9.99,
        ]]),
        $this->usd,
        $this->praga->id,
        app(SupplierPurchaseOrderFinancialService::class)
    );

    expect($payments[0]['applied_amount'])->toBe(300.0)
        ->and($payments[0]['amount'])->toBe(300.0)
        ->and($payments[0]['exchange_rate'])->toBe(1.0);
});

it('bloquea una cuenta cuya moneda no coincide con la moneda real del pago', function () {
    $penAccount = supplierOrderCompanyAccount([
        'company_id' => $this->praga->id,
        'currency_id' => $this->pen->id,
        'account_number' => 'PRAGA-PEN-INCORRECTA',
    ]);
    $method = new ReflectionMethod(SupplierPurchaseOrderController::class, 'validateAdvancePaymentBankAccounts');

    expect(fn () => $method->invoke(
        app(SupplierPurchaseOrderController::class),
        collect([[
            'company_bank_account_id' => $penAccount->id,
            'payment_currency_id' => $this->usd->id,
        ]]),
        $this->praga->id
    ))->toThrow(ValidationException::class);
});

it('retira el total normalizado y el switch global del formulario financiero', function () {
    $view = file_get_contents(resource_path('views/admin/supplier-purchase-orders/partials/modal.blade.php'));

    expect($view)
        ->not->toContain('Total normalizado')
        ->not->toContain('supplier_order_apply_exchange_rate')
        ->toContain('MONTO APLICADO')
        ->toContain('TC DEL PAGO')
        ->toContain('MONTO PAGADO');
});
