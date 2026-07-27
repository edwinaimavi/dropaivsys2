<?php

use App\Models\Bank;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\Authorize;

beforeEach(function () {
    $this->withoutMiddleware([Authenticate::class, Authorize::class]);

    $this->company = Company::create([
        'business_name' => 'EMPRESA DE PRUEBA S.A.C.',
        'ruc' => '20123456789',
        'status' => true,
    ]);
    $this->bank = Bank::create([
        'description' => 'BANCO DE PRUEBA',
        'short_name' => 'TEST',
        'status' => 'ACTIVE',
    ]);
    $this->currency = Currency::create([
        'code' => 'TST',
        'description' => 'MONEDA DE PRUEBA',
        'symbol' => 'T',
        'status' => 'ACTIVE',
    ]);
});

function companyBankAccountPayload(): array
{
    return [
        'bank_id' => test()->bank->id,
        'currency_id' => test()->currency->id,
        'account_holder' => 'EMPRESA DE PRUEBA S.A.C.',
        'account_number' => '123456789',
        'cci' => '987654321',
        'is_detraction' => 'NO',
        'status' => 'ACTIVE',
        'observation' => 'CUENTA PRINCIPAL',
    ];
}

it('registra y lista una cuenta bancaria asociada a la empresa', function () {
    $this->postJson(
        route('admin.companies.bank-accounts.store', $this->company),
        companyBankAccountPayload()
    )->assertCreated()
        ->assertJsonPath('status', true);

    $account = CompanyBankAccount::firstOrFail();

    expect($account->company_id)->toBe($this->company->id)
        ->and($account->account_number)->toBe('123456789');

    $this->getJson(route('admin.companies.bank-accounts.list', $this->company))
        ->assertOk()
        ->assertJsonPath('recordsTotal', 1)
        ->assertJsonFragment([
            'account_holder' => 'EMPRESA DE PRUEBA S.A.C.',
            'account_number' => '123456789',
        ]);
});

it('incluye las cuentas bancarias en el detalle de la empresa', function () {
    $this->company->bankAccounts()->create(companyBankAccountPayload());

    $this->getJson(route('admin.companies.show', $this->company))
        ->assertOk()
        ->assertJsonPath('data.bank_accounts.0.bank', 'BANCO DE PRUEBA')
        ->assertJsonPath('data.bank_accounts.0.currency_code', 'TST')
        ->assertJsonPath('data.bank_accounts.0.account_holder', 'EMPRESA DE PRUEBA S.A.C.')
        ->assertJsonPath('data.bank_accounts.0.account_number', '123456789')
        ->assertJsonPath('data.bank_accounts.0.is_detraction', 'NO')
        ->assertJsonPath('data.bank_accounts.0.status', 'ACTIVE');
});

it('actualiza y elimina una cuenta bancaria de la misma empresa', function () {
    $account = $this->company->bankAccounts()->create(companyBankAccountPayload());
    $payload = companyBankAccountPayload();
    $payload['account_number'] = '555555555';

    $this->putJson(
        route('admin.companies.bank-accounts.update', [$this->company, $account]),
        $payload
    )->assertOk();

    expect($account->fresh()->account_number)->toBe('555555555');

    $this->deleteJson(
        route('admin.companies.bank-accounts.destroy', [$this->company, $account])
    )->assertOk();

    expect(CompanyBankAccount::withTrashed()->findOrFail($account->id)->trashed())->toBeTrue();
});

it('impide modificar o eliminar una cuenta bancaria de otra empresa', function () {
    $otherCompany = Company::create([
        'business_name' => 'OTRA EMPRESA S.A.C.',
        'ruc' => '20987654321',
        'status' => true,
    ]);
    $account = $otherCompany->bankAccounts()->create(companyBankAccountPayload());

    $this->putJson(
        route('admin.companies.bank-accounts.update', [$this->company, $account]),
        companyBankAccountPayload()
    )->assertNotFound();

    $this->deleteJson(
        route('admin.companies.bank-accounts.destroy', [$this->company, $account])
    )->assertNotFound();
});
