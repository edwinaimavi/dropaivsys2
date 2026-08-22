<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\PettyCashBox;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.petty-cash.index', 'web');
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('admin.petty-cash.index');
    $this->actingAs($this->user);

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

    $createBox = function (string $code, string $status, int $month): PettyCashBox {
        return PettyCashBox::create([
            'code' => $code,
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'period_month' => $month,
            'period_year' => 2026,
            'periodicity' => 'MONTHLY',
            'start_date' => "2026-{$month}-01",
            'end_date' => null,
            'approved_fund' => 1000,
            'opening_amount' => 1000,
            'total_expenses' => 0,
            'cash_balance' => 1000,
            'reimbursement_amount' => 0,
            'responsible_name' => 'RESPONSABLE',
            'responsible_dni' => '12345678',
            'supervisor_name' => 'SUPERVISOR',
            'supervisor_dni' => '87654321',
            'status' => $status,
            'opened_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);
    };

    $createBox('CC-ABIERTA-01', PettyCashBox::STATUS_OPEN, 1);
    $createBox('CC-ABIERTA-02', PettyCashBox::STATUS_OPEN, 2);
    $createBox('CC-CERRADA-01', PettyCashBox::STATUS_CLOSED, 3);
    $createBox('CC-ANULADA-01', PettyCashBox::STATUS_CANCELLED, 4);
});

it('filtra el listado de cajas chicas por estado y usa abiertas por defecto', function () {
    $request = fn (array $parameters = []) => $this->getJson(route('admin.petty-cash.list', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        ...$parameters,
    ]))->assertOk();

    $default = $request();
    expect(collect($default->json('data'))->pluck('code')->all())
        ->toContain('CC-ABIERTA-01', 'CC-ABIERTA-02')
        ->not->toContain('CC-CERRADA-01', 'CC-ANULADA-01');

    $closed = $request(['status_filter' => 'closed']);
    expect(collect($closed->json('data'))->pluck('code')->all())->toBe(['CC-CERRADA-01']);

    $cancelled = $request(['status_filter' => 'cancelled']);
    expect(collect($cancelled->json('data'))->pluck('code')->all())->toBe(['CC-ANULADA-01']);

    $all = $request(['status_filter' => 'all']);
    expect($all->json('recordsFiltered'))->toBe(4);
});

it('mantiene el filtro de abiertas al buscar y paginar con DataTables', function () {
    $response = $this->getJson(route('admin.petty-cash.list', [
        'draw' => 1,
        'start' => 0,
        'length' => 1,
        'status_filter' => 'open',
        'search' => ['value' => 'ABIERTA', 'regex' => false],
    ]))->assertOk();

    expect($response->json('recordsFiltered'))->toBe(2)
        ->and($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toContain('ABIERTA');
});

it('rechaza valores de filtro no soportados', function () {
    $this->getJson(route('admin.petty-cash.list', ['status_filter' => 'deleted']))
        ->assertUnprocessable();
});
