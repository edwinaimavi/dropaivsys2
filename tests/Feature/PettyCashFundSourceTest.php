<?php

use App\Models\Bank;
use App\Models\BankMovement;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\PettyCashApprovedAmount;
use App\Models\PettyCashBox;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach (['admin.petty-cash.index', 'admin.petty-cash.store', 'admin.petty-cash.show', 'admin.petty-cash.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo(Permission::all());
    $this->actingAs($this->user);

    $this->dropaiv = Company::create([
        'business_name' => 'DROGUERIA DROPAIV S.A.C.',
        'trade_name' => 'DROGUERIA DROPAIV',
        'ruc' => '20111111111',
        'status' => true,
    ]);
    $this->praga = Company::create([
        'business_name' => 'PRAGA MEDICAL IMPORT S.A.C.',
        'trade_name' => 'PRAGA MEDICAL IMPORT S.A.C.',
        'ruc' => '20222222222',
        'status' => true,
    ]);
    $this->pen = Currency::create([
        'code' => 'PEN', 'description' => 'Soles', 'symbol' => 'S/', 'status' => 'ACTIVE',
    ]);
    $this->usd = Currency::create([
        'code' => 'USD', 'description' => 'Dolares', 'symbol' => '$', 'status' => 'ACTIVE',
    ]);
    $this->bank = Bank::create([
        'description' => 'BANCO DE PRUEBA', 'short_name' => 'BBVA', 'status' => 'ACTIVE',
    ]);

    $account = function (Company $company, Currency $currency, string $number, string $status = 'ACTIVE') {
        return CompanyBankAccount::create([
            'company_id' => $company->id,
            'bank_id' => $this->bank->id,
            'currency_id' => $currency->id,
            'account_holder' => $company->business_name,
            'account_number' => $number,
            'is_detraction' => 'NO',
            'status' => $status,
            'current_balance' => 5000,
        ]);
    };

    $this->dropaivPenAccount = $account($this->dropaiv, $this->pen, '0011-DROPAIV-PEN');
    $this->pragaPenAccount = $account($this->praga, $this->pen, '0011-PRAGA-PEN');
    $this->pragaUsdAccount = $account($this->praga, $this->usd, '0011-PRAGA-USD');
    $this->pragaInactiveAccount = $account($this->praga, $this->pen, '0011-PRAGA-INACTIVA', 'INACTIVE');

    foreach ([$this->dropaiv, $this->praga] as $company) {
        PettyCashApprovedAmount::create([
            'company_id' => $company->id,
            'currency_id' => $this->pen->id,
            'amount' => 1000,
            'active' => true,
            'approved_at' => now(),
            'approved_by_user_id' => $this->user->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
    }
});

function pettyCashFundSourcePayload(Company $company, Currency $currency, array $overrides = []): array
{
    return [
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'start_date' => '2026-08-24',
        'responsible_name' => 'RESPONSABLE PRUEBA',
        'responsible_dni' => '12345678',
        'supervisor_name' => 'SUPERVISOR PRUEBA',
        'supervisor_dni' => '87654321',
        ...$overrides,
    ];
}

function closedPettyCashForFundSourceTest(
    Company $company,
    Currency $currency,
    string $code,
    float $balance
): PettyCashBox {
    return PettyCashBox::create([
        'code' => $code,
        'company_id' => $company->id,
        'currency_id' => $currency->id,
        'period_month' => 7,
        'period_year' => 2026,
        'periodicity' => 'OTHER',
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'approved_fund' => 1000,
        'previous_balance' => 0,
        'opening_amount' => 1000,
        'total_expenses' => 0,
        'cash_balance' => $balance,
        'reimbursement_amount' => 0,
        'responsible_name' => 'RESPONSABLE',
        'responsible_dni' => '12345678',
        'supervisor_name' => 'SUPERVISOR',
        'supervisor_dni' => '87654321',
        'status' => PettyCashBox::STATUS_CLOSED,
        'opened_by' => test()->user->id,
        'created_by' => test()->user->id,
        'updated_by' => test()->user->id,
    ]);
}

it('filtra las cuentas activas por empresa y moneda seleccionadas', function () {
    $praga = $this->getJson(route('admin.petty-cash.source-bank-accounts', [
        'company' => $this->praga,
        'currency_id' => $this->pen->id,
    ]))->assertOk();

    expect(collect($praga->json('data'))->pluck('id')->all())
        ->toBe([$this->pragaPenAccount->id]);
    $praga->assertJsonPath('data.0.currency_code', 'PEN');
    expect($praga->json('data.0.label'))
        ->toContain('BBVA', 'PEN', '0011-PRAGA-PEN', 'Saldo S/ 5,000.00');

    $dropaiv = $this->getJson(route('admin.petty-cash.source-bank-accounts', [
        'company' => $this->dropaiv,
        'currency_id' => $this->pen->id,
    ]))->assertOk();

    expect(collect($dropaiv->json('data'))->pluck('id')->all())
        ->toBe([$this->dropaivPenAccount->id]);
});

it('busca el saldo anterior solo dentro de la misma empresa y moneda', function () {
    $dropaivPrevious = closedPettyCashForFundSourceTest(
        $this->dropaiv, $this->pen, 'CC-DROPAIV-PEN', 297.29
    );
    closedPettyCashForFundSourceTest($this->praga, $this->usd, 'CC-PRAGA-USD', 800);

    $this->getJson(route('admin.petty-cash.previous-balance', [
        'company_id' => $this->praga->id,
        'currency_id' => $this->pen->id,
    ]))->assertOk()
        ->assertJsonPath('data.previous_petty_cash_id', null)
        ->assertJsonPath('data.previous_balance', 0);

    $this->getJson(route('admin.petty-cash.previous-balance', [
        'company_id' => $this->dropaiv->id,
        'currency_id' => $this->pen->id,
    ]))->assertOk()
        ->assertJsonPath('data.previous_petty_cash_id', $dropaivPrevious->id)
        ->assertJsonPath('data.previous_balance', 297.29);
});

it('exige y registra el origen bancario en la primera apertura', function () {
    $this->postJson(route('admin.petty-cash.store'), pettyCashFundSourcePayload($this->praga, $this->pen))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('fund_source_company_id');

    $response = $this->postJson(route('admin.petty-cash.store'), pettyCashFundSourcePayload(
        $this->praga,
        $this->pen,
        [
            'fund_source_company_id' => $this->praga->id,
            'fund_source_bank_account_id' => $this->pragaPenAccount->id,
        ]
    ))->assertCreated()
        ->assertJsonPath('data.previous_balance', '0.00')
        ->assertJsonPath('data.approved_fund', '1000.00')
        ->assertJsonPath('data.opening_amount', '1000.00');

    $this->assertDatabaseHas('bank_movements', [
        'company_bank_account_id' => $this->pragaPenAccount->id,
        'source_type' => 'PETTY_CASH_OPENING',
        'source_id' => $response->json('data.id'),
        'amount' => 1000,
    ]);

    $this->putJson(
        route('admin.petty-cash.update', $response->json('data.id')),
        pettyCashFundSourcePayload($this->praga, $this->pen, [
            'fund_source_company_id' => $this->praga->id,
            'fund_source_bank_account_id' => $this->pragaPenAccount->id,
            'observations' => 'ORIGEN BANCARIO VERIFICADO EN EDICION',
        ])
    )->assertOk();

    $this->assertDatabaseHas('petty_cash_boxes', [
        'id' => $response->json('data.id'),
        'company_id' => $this->praga->id,
        'currency_id' => $this->pen->id,
        'fund_source_company_id' => $this->praga->id,
        'fund_source_bank_account_id' => $this->pragaPenAccount->id,
        'approved_fund' => 1000,
    ]);
    expect(BankMovement::where('source_type', 'PETTY_CASH_OPENING')
        ->where('source_id', $response->json('data.id'))->count())->toBe(1);
});

it('completa el fondo solo con el saldo arrastrado de la misma empresa y moneda', function () {
    $previous = closedPettyCashForFundSourceTest($this->dropaiv, $this->pen, 'CC-DROPAIV-ANTERIOR', 297.29);

    $this->postJson(route('admin.petty-cash.store'), pettyCashFundSourcePayload(
        $this->dropaiv,
        $this->pen,
        [
            'previous_petty_cash_id' => $previous->id,
            'previous_balance' => 999,
            'fund_source_company_id' => $this->dropaiv->id,
            'fund_source_bank_account_id' => $this->dropaivPenAccount->id,
        ]
    ))->assertCreated()
        ->assertJsonPath('data.previous_petty_cash_id', $previous->id)
        ->assertJsonPath('data.previous_balance', '297.29')
        ->assertJsonPath('data.approved_fund', '702.71')
        ->assertJsonPath('data.opening_amount', '1000.00');
});

it('bloquea una cuenta bancaria de otra empresa y una cuenta inactiva', function () {
    $this->postJson(route('admin.petty-cash.store'), pettyCashFundSourcePayload(
        $this->praga,
        $this->pen,
        [
            'fund_source_company_id' => $this->praga->id,
            'fund_source_bank_account_id' => $this->dropaivPenAccount->id,
        ]
    ))->assertUnprocessable()
        ->assertJsonPath(
            'errors.fund_source_bank_account_id.0',
            'La cuenta bancaria origen no pertenece a la empresa origen seleccionada.'
        );

    $this->postJson(route('admin.petty-cash.store'), pettyCashFundSourcePayload(
        $this->praga,
        $this->pen,
        [
            'fund_source_company_id' => $this->praga->id,
            'fund_source_bank_account_id' => $this->pragaInactiveAccount->id,
        ]
    ))->assertUnprocessable()
        ->assertJsonPath(
            'errors.fund_source_bank_account_id.0',
            'La cuenta bancaria origen no se encuentra activa.'
        );
});

it('conserva el origen y su comprobante cuando el fondo calculado por reponer es cero', function () {
    Storage::fake('public');
    $previous = closedPettyCashForFundSourceTest(
        $this->praga, $this->pen, 'CC-PRAGA-SALDO-COMPLETO', 1000
    );

    $response = $this->post(route('admin.petty-cash.store'), pettyCashFundSourcePayload(
        $this->praga,
        $this->pen,
        [
            'previous_petty_cash_id' => $previous->id,
            'fund_source_company_id' => $this->praga->id,
            'fund_source_bank_account_id' => $this->pragaPenAccount->id,
            'fund_source_receipts' => [UploadedFile::fake()->create('origen-praga.pdf', 100, 'application/pdf')],
            'observations' => 'ORIGEN PRAGA CONSERVADO',
        ]
    ))->assertCreated();

    $box = PettyCashBox::findOrFail($response->json('data.id'));
    expect((float) $box->approved_fund)->toBe(0.0)
        ->and($box->fund_source_company_id)->toBe($this->praga->id)
        ->and($box->fund_source_bank_account_id)->toBe($this->pragaPenAccount->id)
        ->and($box->documents()->count())->toBe(1);

    $this->putJson(route('admin.petty-cash.update', $box), pettyCashFundSourcePayload(
        $this->praga,
        $this->pen,
        [
            'previous_petty_cash_id' => $previous->id,
            'fund_source_company_id' => $this->praga->id,
            'fund_source_bank_account_id' => $this->pragaPenAccount->id,
            'observations' => 'ACTUALIZADO SIN REEMPLAZAR COMPROBANTE',
        ]
    ))->assertOk();

    $this->getJson(route('admin.petty-cash.show', $box))
        ->assertOk()
        ->assertJsonPath('data.fund_source_company_id', $this->praga->id)
        ->assertJsonPath('data.fund_source_bank_account_id', $this->pragaPenAccount->id)
        ->assertJsonCount(1, 'data.documents');
});
