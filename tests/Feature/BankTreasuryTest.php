<?php

use App\Http\Controllers\Admin\SupplierPurchaseOrderController;
use App\Models\Bank;
use App\Models\BankMovement;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\SupplierAccount;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Services\BankMovementService;
use Database\Seeders\RoleSeeder;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->company = Company::create([
        'business_name' => 'EMPRESA TESORERIA S.A.C.',
        'ruc' => '20999999991',
        'status' => true,
    ]);
    $this->bank = Bank::create([
        'description' => 'BANCO TESORERIA',
        'short_name' => 'BT',
        'status' => 'ACTIVE',
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->user = User::factory()->create();
    $this->origin = CompanyBankAccount::create([
        'company_id' => $this->company->id,
        'bank_id' => $this->bank->id,
        'currency_id' => $this->currency->id,
        'account_holder' => 'EMPRESA TESORERIA S.A.C.',
        'account_number' => '001-100',
        'is_detraction' => 'NO',
        'status' => 'ACTIVE',
    ]);
    $this->destination = CompanyBankAccount::create([
        'company_id' => $this->company->id,
        'bank_id' => $this->bank->id,
        'currency_id' => $this->currency->id,
        'account_holder' => 'EMPRESA TESORERIA S.A.C.',
        'account_number' => '001-200',
        'is_detraction' => 'NO',
        'status' => 'ACTIVE',
    ]);
    $this->service = app(BankMovementService::class);
});

it('configura el saldo inicial y conserva el asiento de apertura', function () {
    $this->service->configureOpeningBalance(
        $this->origin,
        '1000.2500',
        '2026-08-01',
        'SALDO DEL ESTADO DE CUENTA',
        null,
        $this->user->id
    );

    expect((float) $this->origin->fresh()->current_balance)->toBe(1000.25)
        ->and(BankMovement::where('source_type', 'BANK_OPENING_BALANCE')->count())->toBe(1)
        ->and((float) BankMovement::firstOrFail()->balance_after)->toBe(1000.25);
});

it('registra ingresos y egresos actualizando el saldo de forma transaccional', function () {
    $income = $this->service->createMovement([
        'company_bank_account_id' => $this->origin->id,
        'currency_id' => $this->currency->id,
        'movement_date' => '2026-08-02 10:00:00',
        'movement_type' => 'INGRESO',
        'amount' => '500.0000',
        'direction' => 'IN',
        'concept' => 'Cobro confirmado',
        'source_type' => 'CUSTOMER_PAYMENT',
        'source_id' => 45,
        'source_code' => 'OC-CLIENTE-45',
        'idempotency_key' => 'test-customer-payment-45',
    ], $this->user->id);
    $this->service->createMovement([
        'company_bank_account_id' => $this->origin->id,
        'currency_id' => $this->currency->id,
        'movement_date' => '2026-08-02 12:00:00',
        'movement_type' => 'EGRESO',
        'amount' => '125.5000',
        'direction' => 'OUT',
        'concept' => 'Pago confirmado',
        'source_type' => 'SUPPLIER_PAYMENT',
        'source_id' => 12,
        'source_code' => 'OC-PROVEEDOR-12',
    ], $this->user->id);

    expect((float) $this->origin->fresh()->current_balance)->toBe(374.5)
        ->and($income->source_code)->toBe('OC-CLIENTE-45')
        ->and(BankMovement::count())->toBe(2);
});

it('evita duplicar movimientos automaticos mediante idempotencia', function () {
    $data = [
        'company_bank_account_id' => $this->origin->id,
        'currency_id' => $this->currency->id,
        'movement_date' => '2026-08-03',
        'movement_type' => 'EGRESO',
        'amount' => '200.0000',
        'direction' => 'OUT',
        'concept' => 'Anticipo a proveedor',
        'source_type' => 'SUPPLIER_ADVANCE',
        'source_id' => 99,
        'source_code' => 'OC-P-99',
        'idempotency_key' => 'supplier-advance:99',
    ];

    $first = $this->service->createMovement($data, $this->user->id);
    $second = $this->service->createMovement($data, $this->user->id);

    expect($second->id)->toBe($first->id)
        ->and(BankMovement::count())->toBe(1)
        ->and((float) $this->origin->fresh()->current_balance)->toBe(-200.0);
});

it('transfiere entre cuentas creando dos asientos vinculados', function () {
    $this->service->configureOpeningBalance($this->origin, '1000', '2026-08-01', null, null, $this->user->id);
    $transfer = $this->service->createTransfer([
        'from_company_bank_account_id' => $this->origin->id,
        'to_company_bank_account_id' => $this->destination->id,
        'transfer_date' => '2026-08-04 09:30:00',
        'amount' => '300.0000',
        'operation_number' => 'OP-TRF-001',
    ], $this->user->id);

    $outgoingMovement = $transfer->movements->firstWhere('direction', BankMovement::DIRECTION_OUT);
    $incomingMovement = $transfer->movements->firstWhere('direction', BankMovement::DIRECTION_IN);

    expect($transfer->movements)->toHaveCount(2)
        ->and($outgoingMovement->bank_transfer_id)->toBe($transfer->id)
        ->and($incomingMovement->bank_transfer_id)->toBe($transfer->id)
        ->and($outgoingMovement->source_code)->toBe($transfer->code)
        ->and($incomingMovement->source_code)->toBe($transfer->code)
        ->and($outgoingMovement->status)->toBe(BankMovement::STATUS_REGISTERED)
        ->and($incomingMovement->status)->toBe(BankMovement::STATUS_REGISTERED)
        ->and((float) $this->origin->fresh()->current_balance)->toBe(700.0)
        ->and((float) $this->destination->fresh()->current_balance)->toBe(300.0);
});

it('permite transferir hacia una cuenta activa de otra empresa', function () {
    $otherCompany = Company::create([
        'business_name' => 'EMPRESA DESTINO S.A.C.',
        'ruc' => '20999999992',
        'status' => true,
    ]);
    $this->destination->update(['company_id' => $otherCompany->id]);
    $this->service->configureOpeningBalance($this->origin, '200', '2026-08-01', null, null, $this->user->id);

    $transfer = $this->service->createTransfer([
        'from_company_bank_account_id' => $this->origin->id,
        'to_company_bank_account_id' => $this->destination->id,
        'transfer_date' => '2026-08-04 10:00:00',
        'amount' => '50.0000',
        'operation_number' => 'OP-INTEREMPRESA-01',
    ], $this->user->id);

    expect($transfer->company_id)->toBe($this->company->id)
        ->and($transfer->movements->firstWhere('direction', 'OUT')->company_id)->toBe($this->company->id)
        ->and($transfer->movements->firstWhere('direction', 'IN')->company_id)->toBe($otherCompany->id)
        ->and((float) $this->origin->fresh()->current_balance)->toBe(150.0)
        ->and((float) $this->destination->fresh()->current_balance)->toBe(50.0);
});

it('transfiere de soles a moneda extranjera usando el tipo de cambio', function () {
    $usd = Currency::create([
        'code' => 'USD',
        'description' => 'Dólares americanos',
        'symbol' => '$',
        'status' => 'ACTIVE',
    ]);
    $this->destination->update(['currency_id' => $usd->id]);
    $this->service->configureOpeningBalance($this->origin, '760', '2026-08-01', null, null, $this->user->id);

    $transfer = $this->service->createTransfer([
        'from_company_bank_account_id' => $this->origin->id,
        'to_company_bank_account_id' => $this->destination->id,
        'transfer_date' => '2026-08-04 11:00:00',
        'amount' => '380.0000',
        'exchange_rate' => '3.800000',
        'operation_number' => 'OP-PEN-USD-01',
    ], $this->user->id);

    expect((float) $transfer->destination_amount)->toBe(100.0)
        ->and((float) $transfer->exchange_rate)->toBe(3.8)
        ->and((float) $this->origin->fresh()->current_balance)->toBe(380.0)
        ->and((float) $this->destination->fresh()->current_balance)->toBe(100.0);
});

it('permite transferir entre cuentas de la misma moneda extranjera sin exigir tipo de cambio', function () {
    $usd = Currency::create([
        'code' => 'USD',
        'description' => 'Dólares americanos',
        'symbol' => '$',
        'status' => 'ACTIVE',
    ]);
    $this->origin->update(['currency_id' => $usd->id, 'current_balance' => 100]);
    $this->destination->update(['currency_id' => $usd->id]);

    $transfer = $this->service->createTransfer([
        'from_company_bank_account_id' => $this->origin->id,
        'to_company_bank_account_id' => $this->destination->id,
        'transfer_date' => '2026-08-04 11:30:00',
        'amount' => '25.0000',
        'operation_number' => 'OP-USD-USD-01',
    ], $this->user->id);

    expect((float) $transfer->destination_amount)->toBe(25.0)
        ->and((float) $transfer->exchange_rate)->toBe(1.0)
        ->and((float) $this->origin->fresh()->current_balance)->toBe(75.0)
        ->and((float) $this->destination->fresh()->current_balance)->toBe(25.0);
});

it('rechaza transferencias sin saldo suficiente o hacia cuentas inactivas', function () {
    expect(fn () => $this->service->createTransfer([
        'from_company_bank_account_id' => $this->origin->id,
        'to_company_bank_account_id' => $this->destination->id,
        'transfer_date' => '2026-08-04 12:00:00',
        'amount' => '1.0000',
        'operation_number' => 'OP-SIN-SALDO',
    ], $this->user->id))->toThrow(ValidationException::class, 'saldo suficiente');

    $this->origin->update(['current_balance' => 100]);
    $this->destination->update(['status' => 'INACTIVE']);

    expect(fn () => $this->service->createTransfer([
        'from_company_bank_account_id' => $this->origin->id,
        'to_company_bank_account_id' => $this->destination->id,
        'transfer_date' => '2026-08-04 12:30:00',
        'amount' => '1.0000',
        'operation_number' => 'OP-CUENTA-INACTIVA',
    ], $this->user->id))->toThrow(ValidationException::class, 'no se encuentra activa')
        ->and(BankTransfer::count())->toBe(0)
        ->and(BankMovement::count())->toBe(0);
});

it('valida por HTTP destino obligatorio diferente y activo', function () {
    Permission::findOrCreate('admin.banks.transfers.create', 'web');
    $this->user->givePermissionTo('admin.banks.transfers.create');
    $this->origin->update(['current_balance' => 100]);
    $payload = [
        'from_company_bank_account_id' => $this->origin->id,
        'transfer_date' => '2026-08-04 13:00:00',
        'amount' => '10.0000',
        'operation_number' => 'OP-HTTP-01',
    ];

    $this->actingAs($this->user)->postJson(route('admin.banks.transfers.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_company_bank_account_id')
        ->assertJsonPath('errors.to_company_bank_account_id.0', 'Seleccione la cuenta bancaria que recibirá la transferencia.');

    $this->postJson(route('admin.banks.transfers.store'), [
        ...$payload,
        'to_company_bank_account_id' => $this->origin->id,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.to_company_bank_account_id.0', 'No puede transferir a la misma cuenta bancaria.');

    $usd = Currency::create([
        'code' => 'USD',
        'description' => 'Dólares americanos',
        'symbol' => '$',
        'status' => 'ACTIVE',
    ]);
    $this->destination->update(['currency_id' => $usd->id]);
    $this->postJson(route('admin.banks.transfers.store'), [
        ...$payload,
        'to_company_bank_account_id' => $this->destination->id,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.exchange_rate.0', 'Ingrese un tipo de cambio mayor a cero para transferir entre monedas distintas.');

    $this->destination->update(['status' => 'INACTIVE']);
    $this->postJson(route('admin.banks.transfers.store'), [
        ...$payload,
        'to_company_bank_account_id' => $this->destination->id,
    ])->assertUnprocessable()
        ->assertJsonPath('errors.to_company_bank_account_id.0', 'La cuenta bancaria destino no existe o no se encuentra activa.');
});

it('renderiza todas las cuentas activas en el modal y conserva el refresco de destino', function () {
    Permission::findOrCreate('admin.banks.view', 'web');
    $this->user->givePermissionTo('admin.banks.view');
    $inactive = CompanyBankAccount::create([
        'company_id' => $this->company->id,
        'bank_id' => $this->bank->id,
        'currency_id' => $this->currency->id,
        'account_holder' => 'CUENTA INACTIVA',
        'account_number' => '001-300',
        'is_detraction' => 'NO',
        'status' => 'INACTIVE',
    ]);

    $this->actingAs($this->user)->get(route('admin.banks.index'))
        ->assertOk()
        ->assertSee($this->origin->account_number)
        ->assertSee($this->destination->account_number)
        ->assertDontSee($inactive->account_number)
        ->assertSee('bankTransferDestinationHelp', false);

    $javascript = file_get_contents(resource_path('js/pages/bank-treasury.js'));
    expect($javascript)
        ->toContain('filter(option => !originId || String(option.value) !== originId)')
        ->toContain('No hay otra cuenta bancaria activa disponible para recibir la transferencia.')
        ->toContain('Seleccione la cuenta bancaria que recibirá la transferencia.')
        ->not->toContain("data('company')) === String(company)");
});

it('anula mediante reversa y reconstruye correctamente el saldo del libro', function () {
    $movement = $this->service->createMovement([
        'company_bank_account_id' => $this->origin->id,
        'currency_id' => $this->currency->id,
        'movement_date' => '2026-08-05 08:00:00',
        'movement_type' => 'EGRESO',
        'amount' => '80.0000',
        'direction' => 'OUT',
        'concept' => 'Gasto bancario',
        'source_type' => 'MANUAL',
    ], $this->user->id);
    $this->service->cancelMovement($movement, 'OPERACION REGISTRADA POR ERROR', $this->user->id);

    expect($movement->fresh()->status)->toBe(BankMovement::STATUS_CANCELLED)
        ->and($movement->fresh()->reversal)->not->toBeNull()
        ->and((float) $this->origin->fresh()->current_balance)->toBe(0.0)
        ->and($this->service->systemBalanceAt($this->origin->id, '2026-08-31'))->toBe(0.0);
});

it('concilia movimientos y calcula la diferencia contra el estado bancario', function () {
    $movement = $this->service->createMovement([
        'company_bank_account_id' => $this->origin->id,
        'currency_id' => $this->currency->id,
        'movement_date' => '2026-08-06 11:00:00',
        'movement_type' => 'INGRESO',
        'amount' => '450.0000',
        'direction' => 'IN',
        'concept' => 'Deposito',
        'source_type' => 'MANUAL',
    ], $this->user->id);
    $reconciliation = $this->service->reconcile([
        'company_bank_account_id' => $this->origin->id,
        'period' => '2026-08',
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'bank_statement_balance' => '440.0000',
        'movement_ids' => [$movement->id],
        'observation' => 'DIFERENCIA EN REVISION',
    ], $this->user->id);

    expect((float) $reconciliation->system_balance)->toBe(450.0)
        ->and((float) $reconciliation->difference)->toBe(-10.0)
        ->and($movement->fresh()->status)->toBe(BankMovement::STATUS_RECONCILED);
});

it('genera un egreso bancario al registrar un anticipo real a proveedor', function () {
    $supplier = Supplier::create([
        'ruc' => '20111111119',
        'business_name' => 'PROVEEDOR TESORERIA S.A.C.',
        'supplier_type' => 'BIENES',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
    ]);
    $supplierAccount = SupplierAccount::create([
        'supplier_id' => $supplier->id,
        'bank_id' => $this->bank->id,
        'currency_id' => $this->currency->id,
        'account_holder' => $supplier->business_name,
        'account_number' => '999-111',
        'is_detraction' => 'NO',
        'status' => 'ACTIVE',
    ]);
    $order = SupplierPurchaseOrder::create([
        'code' => 'OC-PROV-TEST-01',
        'company_id' => $this->company->id,
        'supplier_id' => $supplier->id,
        'supplier_account_id' => $supplierAccount->id,
        'currency_id' => $this->currency->id,
        'payment_currency_id' => $this->currency->id,
        'order_type' => 'articles',
        'apply_advance' => true,
        'advance_status' => 'pending',
        'status' => 'registered',
    ]);
    $this->actingAs($this->user);
    $method = new ReflectionMethod(SupplierPurchaseOrderController::class, 'storeAdvancePayments');
    $method->invoke(
        app(SupplierPurchaseOrderController::class),
        $order,
        collect([[
            'company_bank_account_id' => $this->origin->id,
            'payment_date' => '2026-08-07',
            'amount' => '225.0000',
            'payment_method' => 'deposito_cuenta',
            'operation_number' => 'ANT-0001',
            'observation' => 'ANTICIPO CONFIRMADO',
        ]]),
        $this->currency,
        null
    );

    $payment = $order->advancePayments()->firstOrFail();
    $movement = BankMovement::where('source_type', 'SUPPLIER_ADVANCE')->firstOrFail();
    expect($payment->company_bank_account_id)->toBe($this->origin->id)
        ->and($movement->source_id)->toBe($payment->id)
        ->and($movement->source_code)->toBe($order->code)
        ->and($movement->direction)->toBe('OUT')
        ->and((float) $this->origin->fresh()->current_balance)->toBe(-225.0);
});

it('registra permisos y sirve la pantalla y el listado de tesoreria', function () {
    $this->seed(RoleSeeder::class);
    $this->user->assignRole('Administrador');

    $this->actingAs($this->user)
        ->get(route('admin.banks.index'))
        ->assertOk()
        ->assertSee('Bancos / Tesorería')
        ->assertSee('Conciliación bancaria');
    $this->getJson(route('admin.banks.list', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]))->assertOk()
        ->assertJsonPath('recordsTotal', 2)
        ->assertJsonStructure(['summary' => [
            'total_banks_pen', 'period_income_pen', 'period_expense_pen',
            'available_balance_pen', 'pending_reconciliation',
        ]]);

    expect(Permission::where('name', 'like', 'admin.banks.%')->count())->toBe(12);
});
