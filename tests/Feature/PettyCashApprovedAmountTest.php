<?php

use App\Models\Company;
use App\Models\Currency;
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
    $this->viewer = User::factory()->create();
    $this->manager = User::factory()->create();

    foreach ([
        'admin.petty-cash.approved-amount.index',
        'admin.petty-cash.approved-amount.update',
        'admin.petty-cash.store',
        'admin.petty-cash.close',
    ] as $name) {
        Permission::create(['name' => $name, 'guard_name' => 'web']);
    }

    $this->viewer->givePermissionTo('admin.petty-cash.approved-amount.index');
    $this->manager->givePermissionTo([
        'admin.petty-cash.approved-amount.index',
        'admin.petty-cash.approved-amount.update',
        'admin.petty-cash.store',
        'admin.petty-cash.close',
    ]);
});

it('apertura la primera caja con el monto aprobado y permite cerrarla sin gastos', function () {
    $this->actingAs($this->manager)
        ->putJson(route('admin.petty-cash.approved-amount.update'), [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'amount' => 2000,
            'active' => true,
        ])
        ->assertOk();

    $response = $this->actingAs($this->manager)
        ->postJson(route('admin.petty-cash.store'), [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'period_month' => 7,
            'period_year' => 2026,
            'periodicity' => 'MONTHLY',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'approved_fund' => 999,
            'previous_balance' => 999,
            'responsible_name' => 'RESPONSABLE PRUEBA',
            'responsible_dni' => '12345678',
            'supervisor_name' => 'SUPERVISOR PRUEBA',
            'supervisor_dni' => '87654321',
        ])
        ->assertCreated()
        ->assertJsonPath('data.opening_amount', '2000.00')
        ->assertJsonPath('data.approved_fund', '0.00')
        ->assertJsonPath('data.previous_balance', '0.00')
        ->assertJsonPath('data.end_date', null);

    $boxId = $response->json('data.id');
    $this->actingAs($this->manager)
        ->postJson(route('admin.petty-cash.close', $boxId), [
            'close_observation' => 'Cierre autorizado por gerencia',
        ])
        ->assertOk();

    $this->assertDatabaseHas('petty_cash_boxes', [
        'id' => $boxId,
        'opening_amount' => 2000,
        'cash_balance' => 2000,
        'reimbursement_amount' => 0,
        'status' => 'CLOSED',
        'close_observation' => 'Cierre autorizado por gerencia',
    ]);
    expect(\App\Models\PettyCashBox::find($boxId)->closed_at)->not->toBeNull()
        ->and(\App\Models\PettyCashBox::find($boxId)->end_date)->not->toBeNull()
        ->and(\App\Models\PettyCashBox::find($boxId)->closed_by)->toBe($this->manager->id);
});

it('rechaza la apertura cuando no existe monto aprobado activo', function () {
    $this->actingAs($this->manager)
        ->postJson(route('admin.petty-cash.store'), [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'period_month' => 7,
            'period_year' => 2026,
            'periodicity' => 'MONTHLY',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'responsible_name' => 'RESPONSABLE PRUEBA',
            'responsible_dni' => '12345678',
            'supervisor_name' => 'SUPERVISOR PRUEBA',
            'supervisor_dni' => '87654321',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_id');
});

it('permite configurar y consultar el monto activo por empresa y moneda', function () {
    $this->actingAs($this->manager)
        ->putJson(route('admin.petty-cash.approved-amount.update'), [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'amount' => 1500,
            'active' => true,
            'observation' => 'Autorizado por gerencia',
        ])
        ->assertOk()
        ->assertJsonPath('data.formatted_amount', 'S/ 1,500.00');

    $this->actingAs($this->manager)
        ->putJson(route('admin.petty-cash.approved-amount.update'), [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'amount' => 1800,
            'active' => true,
            'observation' => 'Ampliación autorizada',
        ])
        ->assertOk()
        ->assertJsonPath('data.approved_by_user_id', $this->manager->id);

    $this->actingAs($this->viewer)
        ->getJson(route('admin.petty-cash.approved-amount.active', [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.amount', '1800.00');

    $this->actingAs($this->manager)
        ->getJson(route('admin.petty-cash.approved-amount.show', [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
        ]))
        ->assertOk()
        ->assertJsonCount(2, 'data.history')
        ->assertJsonPath('data.history.0.previous_amount', '1500.00')
        ->assertJsonPath('data.history.0.approved_amount', '1800.00');

    $this->assertDatabaseHas('petty_cash_approved_amounts', [
        'company_id' => $this->company->id,
        'currency_id' => $this->currency->id,
        'amount' => 1800,
        'active' => true,
        'approved_by_user_id' => $this->manager->id,
    ]);
    $this->assertDatabaseCount('approved_amount_histories', 2);
    expect(\App\Models\PettyCashApprovedAmount::first()->approved_at)->not->toBeNull();
});

it('permite ver el monto pero impide editarlo sin permiso de gestión', function () {
    $this->actingAs($this->viewer)
        ->putJson(route('admin.petty-cash.approved-amount.update'), [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'amount' => 500,
            'active' => true,
        ])
        ->assertForbidden();
});

it('responde vacío cuando la configuración está inactiva', function () {
    $this->actingAs($this->manager)
        ->putJson(route('admin.petty-cash.approved-amount.update'), [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
            'amount' => 800,
            'active' => false,
        ])
        ->assertOk();

    $this->actingAs($this->viewer)
        ->getJson(route('admin.petty-cash.approved-amount.active', [
            'company_id' => $this->company->id,
            'currency_id' => $this->currency->id,
        ]))
        ->assertOk()
        ->assertJsonPath('status', 'empty');
});
