<?php

use App\Models\Bank;
use App\Models\BankMovement;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryCreditPayment;
use App\Services\WarehouseEntryBankPaymentService;
use App\Services\WarehouseEntryCreditPaymentService;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = Company::create([
        'business_name' => 'EMPRESA ALMACÉN S.A.C.',
        'ruc' => '20555555551',
        'status' => true,
    ]);
    $this->bank = Bank::create([
        'description' => 'BANCO DE PRUEBA',
        'short_name' => 'BPR',
        'status' => 'ACTIVE',
    ]);
    $this->pen = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->supplier = Supplier::create([
        'ruc' => '20666666661',
        'business_name' => 'PROVEEDOR ALMACÉN S.A.C.',
        'supplier_type' => 'BIENES',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
    ]);
    $this->account = warehousePaymentAccount($this->company, $this->bank, $this->pen, '001-100', 1000);
    $this->entry = warehousePaymentEntry($this->company, $this->supplier, $this->pen, $this->account);
    $this->service = app(WarehouseEntryBankPaymentService::class);
});

it('genera un egreso por el total real incluido IGV y descuenta la cuenta', function () {
    $movement = $this->service->sync($this->entry, $this->user->id);

    expect($movement->direction)->toBe(BankMovement::DIRECTION_OUT)
        ->and($movement->source_type)->toBe(WarehouseEntryBankPaymentService::SOURCE_TYPE)
        ->and($movement->source_id)->toBe($this->entry->id)
        ->and($movement->source_code)->toBe($this->entry->entry_number)
        ->and($movement->concept)->toBe('Pago a proveedor por ingreso de almacén')
        ->and((float) $movement->original_amount)->toBe(118.0)
        ->and((float) $movement->amount)->toBe(118.0)
        ->and((float) $movement->amount_pen)->toBe(118.0)
        ->and($movement->status)->toBe(BankMovement::STATUS_REGISTERED)
        ->and((float) $this->account->fresh()->current_balance)->toBe(882.0);
});

it('no genera movimiento cuando el ingreso queda como cuenta por pagar', function () {
    $this->entry->update([
        'generate_account_payable' => true,
        'payment_company_bank_account_id' => null,
        'bank_payment_date' => null,
    ]);

    expect($this->service->sync($this->entry, $this->user->id))->toBeNull()
        ->and(BankMovement::count())->toBe(0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(1000.0);
});

it('rechaza una cuenta bancaria de otra empresa', function () {
    $otherCompany = Company::create([
        'business_name' => 'OTRA EMPRESA S.A.C.',
        'ruc' => '20777777771',
        'status' => true,
    ]);
    $otherAccount = warehousePaymentAccount($otherCompany, $this->bank, $this->pen, '009-999', 1000);
    $this->entry->update(['payment_company_bank_account_id' => $otherAccount->id]);

    expect(fn () => $this->service->sync($this->entry, $this->user->id))
        ->toThrow(ValidationException::class, 'pertenecer a la empresa')
        ->and(BankMovement::count())->toBe(0);
});

it('rechaza una cuenta bancaria inactiva o archivada', function () {
    $this->account->update(['status' => 'INACTIVE']);

    expect(fn () => $this->service->sync($this->entry, $this->user->id))
        ->toThrow(ValidationException::class, 'cuenta bancaria activa')
        ->and(BankMovement::count())->toBe(0);
});

it('convierte una compra en dólares a una cuenta PEN y conserva el importe original', function () {
    $usd = Currency::create([
        'code' => 'USD',
        'description' => 'Dólares americanos',
        'symbol' => '$',
        'status' => 'ACTIVE',
    ]);
    $this->entry->update([
        'currency_id' => $usd->id,
        'grand_total' => 100,
        'payable_amount' => 100,
        'bank_payment_exchange_rate' => 3.8,
    ]);

    $movement = $this->service->sync($this->entry, $this->user->id);

    expect($movement->original_currency_id)->toBe($usd->id)
        ->and((float) $movement->original_amount)->toBe(100.0)
        ->and((float) $movement->original_exchange_rate)->toBe(3.8)
        ->and((float) $movement->amount)->toBe(380.0)
        ->and((float) $movement->amount_pen)->toBe(380.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(620.0);
});

it('debita correctamente una cuenta en moneda extranjera', function () {
    $usd = Currency::create([
        'code' => 'USD',
        'description' => 'Dólares americanos',
        'symbol' => '$',
        'status' => 'ACTIVE',
    ]);
    $usdAccount = warehousePaymentAccount($this->company, $this->bank, $usd, '001-USD', 500);
    $this->entry->update([
        'currency_id' => $usd->id,
        'payment_company_bank_account_id' => $usdAccount->id,
        'grand_total' => 100,
        'payable_amount' => 100,
        'bank_payment_exchange_rate' => 3.8,
    ]);

    $movement = $this->service->sync($this->entry, $this->user->id);

    expect($movement->currency_id)->toBe($usd->id)
        ->and((float) $movement->amount)->toBe(100.0)
        ->and((float) $movement->amount_pen)->toBe(380.0)
        ->and((float) $usdAccount->fresh()->current_balance)->toBe(400.0);
});

it('es idempotente al guardar dos veces sin cambios', function () {
    $first = $this->service->sync($this->entry, $this->user->id);
    $second = $this->service->sync($this->entry->fresh(), $this->user->id);

    expect($second->id)->toBe($first->id)
        ->and(BankMovement::where('source_type', WarehouseEntryBankPaymentService::SOURCE_TYPE)->count())->toBe(1)
        ->and((float) $this->account->fresh()->current_balance)->toBe(882.0);
});

it('revierte el pago anterior y crea uno nuevo cuando cambian cuenta o monto', function () {
    $secondAccount = warehousePaymentAccount($this->company, $this->bank, $this->pen, '001-200', 1000);
    $original = $this->service->sync($this->entry, $this->user->id);
    $this->entry->update([
        'payment_company_bank_account_id' => $secondAccount->id,
        'grand_total' => 200,
        'payable_amount' => 200,
    ]);

    $replacement = $this->service->sync($this->entry->fresh(), $this->user->id);

    expect($original->fresh()->status)->toBe(BankMovement::STATUS_CANCELLED)
        ->and($original->fresh()->reversal)->not->toBeNull()
        ->and($replacement->id)->not->toBe($original->id)
        ->and($replacement->company_bank_account_id)->toBe($secondAccount->id)
        ->and((float) $this->account->fresh()->current_balance)->toBe(1000.0)
        ->and((float) $secondAccount->fresh()->current_balance)->toBe(800.0);
});

it('corrige mediante reversa incluso si el movimiento ya estaba conciliado', function () {
    $original = $this->service->sync($this->entry, $this->user->id);
    $original->update(['status' => BankMovement::STATUS_RECONCILED]);
    $this->entry->update(['bank_payment_observation' => 'OPERACIÓN CORREGIDA']);

    $replacement = $this->service->sync($this->entry->fresh(), $this->user->id);

    expect($original->fresh()->status)->toBe(BankMovement::STATUS_CANCELLED)
        ->and($original->fresh()->reversal)->not->toBeNull()
        ->and($replacement->status)->toBe(BankMovement::STATUS_REGISTERED)
        ->and($replacement->description)->toBe('OPERACIÓN CORREGIDA')
        ->and((float) $this->account->fresh()->current_balance)->toBe(882.0);
});

it('revierte el egreso al anular el ingreso sin borrar la trazabilidad', function () {
    $movement = $this->service->sync($this->entry, $this->user->id);
    $cancelled = $this->service->cancel($this->entry, 'INGRESO ANULADO', $this->user->id);

    expect($cancelled?->id)->toBe($movement->id)
        ->and($movement->fresh()->status)->toBe(BankMovement::STATUS_CANCELLED)
        ->and($movement->fresh()->reversal)->not->toBeNull()
        ->and((float) $this->account->fresh()->current_balance)->toBe(1000.0);
});

it('exige confirmación explícita antes de permitir saldo negativo', function () {
    $this->account->update(['current_balance' => 50]);

    expect(fn () => $this->service->sync($this->entry, $this->user->id))
        ->toThrow(ValidationException::class, 'saldo suficiente')
        ->and(BankMovement::count())->toBe(0);

    $this->entry->update(['bank_payment_negative_balance_confirmed' => true]);
    $movement = $this->service->sync($this->entry->fresh(), $this->user->id);

    expect($movement)->not->toBeNull()
        ->and((float) $this->account->fresh()->current_balance)->toBe(-68.0);
});

it('propaga la constancia, operación y observación al movimiento de tesorería', function () {
    $this->entry->update([
        'bank_payment_operation_number' => 'OP-ALM-001',
        'bank_payment_proof_path' => 'warehouse_entries/1/bank-payment/constancia.pdf',
        'bank_payment_proof_original_name' => 'constancia.pdf',
        'bank_payment_proof_mime_type' => 'application/pdf',
        'bank_payment_proof_size' => 2048,
        'bank_payment_observation' => 'PAGO CONFIRMADO',
    ]);

    $movement = $this->service->sync($this->entry->fresh(), $this->user->id);

    expect($movement->operation_number)->toBe('OP-ALM-001')
        ->and($movement->file_path)->toBe('warehouse_entries/1/bank-payment/constancia.pdf')
        ->and($movement->file_original_name)->toBe('constancia.pdf')
        ->and($movement->description)->toBe('PAGO CONFIRMADO');
});

it('integra el guardado HTTP del ingreso con la constancia y el egreso bancario', function () {
    Storage::fake('public');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.store', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.store');
    [$warehouse, $article] = warehousePaymentInventoryData();

    $response = $this->actingAs($this->user)->post(route('admin.warehouse-entries.store'), [
        'warehouse_id' => $warehouse->id,
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->pen->id,
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_number' => '999',
        'document_date' => '2026-08-14',
        'affect_igv' => 1,
        'generate_account_payable' => 0,
        'payment_company_bank_account_id' => $this->account->id,
        'bank_payment_date' => '2026-08-14',
        'bank_payment_operation_number' => 'OP-HTTP-001',
        'bank_payment_observation' => 'PAGO DESDE EL MODAL',
        'bank_payment_proof' => UploadedFile::fake()->create('constancia.pdf', 100, 'application/pdf'),
        'items' => [[
            'article_id' => $article->id,
            'billing_name_snapshot' => $article->billing_name,
            'unit_id' => $article->unit_id,
            'quantity' => 1,
            'unit_price' => 118,
        ]],
    ]);

    $response->assertCreated()->assertJsonPath('status', 'success');
    $entry = WarehouseEntry::query()->where('document_number', '999')->firstOrFail();
    $movement = BankMovement::query()
        ->where('source_type', WarehouseEntryBankPaymentService::SOURCE_TYPE)
        ->where('source_id', $entry->id)
        ->firstOrFail();

    Storage::disk('public')->assertExists($entry->bank_payment_proof_path);
    expect($entry->payment_company_bank_account_id)->toBe($this->account->id)
        ->and($movement->operation_number)->toBe('OP-HTTP-001')
        ->and($movement->file_path)->toBe($entry->bank_payment_proof_path)
        ->and((float) $movement->amount)->toBe(118.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(882.0);
});

it('hereda crédito de la OC, calcula vencimiento y no genera egreso aunque manipulen el request', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.store', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.store');
    [$warehouse, $article] = warehousePaymentInventoryData();
    $order = warehousePaymentSupplierOrder(
        $this->company,
        $this->supplier,
        $this->pen,
        'credito_30_dias',
        'OCP-CREDITO-001'
    );

    $response = $this->actingAs($this->user)->post(route('admin.warehouse-entries.store'), [
        'supplier_purchase_order_id' => $order->id,
        'warehouse_id' => $warehouse->id,
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_number' => 'CRED-001',
        'document_date' => '2026-07-21',
        'payment_condition' => 'contado',
        'generate_account_payable' => 0,
        'items' => [[
            'article_id' => $article->id,
            'billing_name_snapshot' => $article->billing_name,
            'unit_id' => $article->unit_id,
            'quantity' => 1,
            'unit_price' => 118,
        ]],
    ]);

    $response->assertCreated()->assertJsonPath('status', 'success');
    $entry = WarehouseEntry::query()->where('document_number', 'CRED-001')->firstOrFail();

    expect($entry->payment_condition)->toBe('credito_30_dias')
        ->and($entry->generate_account_payable)->toBeTrue()
        ->and($entry->expected_payment_date?->toDateString())->toBe('2026-08-20')
        ->and($entry->payment_company_bank_account_id)->toBeNull()
        ->and($entry->bank_payment_date)->toBeNull()
        ->and(BankMovement::where('source_type', WarehouseEntryBankPaymentService::SOURCE_TYPE)
            ->where('source_id', $entry->id)->doesntExist())->toBeTrue();
});

it('hereda contado de la OC y genera el egreso bancario', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.store', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.store');
    [$warehouse, $article] = warehousePaymentInventoryData();
    $order = warehousePaymentSupplierOrder(
        $this->company,
        $this->supplier,
        $this->pen,
        'contado',
        'OCP-CONTADO-001'
    );

    $this->actingAs($this->user)->post(route('admin.warehouse-entries.store'), [
        'supplier_purchase_order_id' => $order->id,
        'warehouse_id' => $warehouse->id,
        'document_type' => 'FACTURA',
        'document_number' => 'CONT-001',
        'document_date' => '2026-07-21',
        'payment_condition' => 'credito_30_dias',
        'generate_account_payable' => 1,
        'payment_company_bank_account_id' => $this->account->id,
        'bank_payment_date' => '2026-07-21',
        'items' => [[
            'article_id' => $article->id,
            'billing_name_snapshot' => $article->billing_name,
            'unit_id' => $article->unit_id,
            'quantity' => 1,
            'unit_price' => 118,
        ]],
    ])->assertCreated();

    $entry = WarehouseEntry::query()->where('document_number', 'CONT-001')->firstOrFail();
    expect($entry->payment_condition)->toBe('contado')
        ->and($entry->generate_account_payable)->toBeFalse()
        ->and(BankMovement::where('source_type', WarehouseEntryBankPaymentService::SOURCE_TYPE)
            ->where('source_id', $entry->id)->exists())->toBeTrue();
});

it('muestra en el listado la alerta de vencimiento del crédito', function () {
    $this->travelTo(Carbon::parse('2026-07-31'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.index', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.index');
    $order = warehousePaymentSupplierOrder(
        $this->company,
        $this->supplier,
        $this->pen,
        'credito_30_dias',
        'OCP-CREDITO-LISTA'
    );
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-CREDITO-LISTA',
        'supplier_purchase_order_id' => $order->id,
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->pen->id,
        'document_type' => 'FACTURA',
        'document_date' => '2026-07-21',
        'payment_condition' => 'credito_30_dias',
        'generate_account_payable' => true,
        'expected_payment_date' => '2026-08-20',
        'grand_total' => 118,
        'payable_amount' => 118,
        'status' => 'registered',
    ]);

    $response = $this->actingAs($this->user)->getJson(route('admin.warehouse-entries.list'));
    $row = collect($response->json('data'))->firstWhere('entry_number', $entry->entry_number);

    expect($response->status())->toBe(200)
        ->and($row['credit_alert'] ?? '')->toContain('Crédito 30 días')
        ->and($row['credit_alert'] ?? '')->toContain('Faltan 20 días')
        ->and($row['credit_summary']['is_pending'] ?? false)->toBeTrue()
        ->and($row['credit_summary']['status_label'] ?? '')->toBe('Faltan 20 días');
});

it('devuelve créditos vencidos, que vencen hoy y próximos dentro de quince días', function () {
    $this->travelTo(Carbon::parse('2026-08-20'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.index', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.index');

    warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-VENCIDA', '2026-08-16', 100);
    warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-HOY', '2026-08-20', 200, [
        'expected_payment_date' => null,
    ]);
    warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-CINCO', '2026-08-25', 300);
    warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-DIEZ', '2026-08-30', 400);

    $response = $this->actingAs($this->user)->getJson(route('admin.warehouse-entries.credit-alerts'));

    $response->assertOk()
        ->assertJsonPath('warning_days', 15)
        ->assertJsonPath('total', 4)
        ->assertJsonPath('overdue', 1)
        ->assertJsonPath('due_today', 1)
        ->assertJsonPath('due_soon', 2)
        ->assertJsonPath('due_within_7', 1)
        ->assertJsonPath('due_within_15', 2)
        ->assertJsonPath('total_pending', 1000)
        ->assertJsonPath('data.0.status_type', 'overdue')
        ->assertJsonPath('data.0.status_label', 'Vencido hace 4 días')
        ->assertJsonPath('data.1.status_type', 'due_today')
        ->assertJsonPath('data.1.status_label', 'Vence hoy')
        ->assertJsonPath('data.2.days_remaining', 5)
        ->assertJsonPath('data.2.status_label', 'Faltan 5 días');
});

it('excluye de alertas créditos pagados, anulados, sin saldo, contado y fuera del rango', function () {
    $this->travelTo(Carbon::parse('2026-08-20'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.index', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.index');

    warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-ANULADA', '2026-08-20', 100, [
        'status' => 'cancelled',
    ]);
    warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-CERO', '2026-08-20', 0);
    warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-LEJANA', '2026-09-05', 100);
    warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-CONTADO', '2026-08-20', 100, [], 'contado');
    $paid = warehouseCreditAlertEntry($this->company, $this->supplier, $this->pen, 'ALERTA-PAGADA', '2026-08-20', 118, [
        'generate_account_payable' => false,
        'payment_company_bank_account_id' => $this->account->id,
        'bank_payment_date' => '2026-08-20',
    ]);
    $this->service->sync($paid, $this->user->id);

    $this->actingAs($this->user)
        ->getJson(route('admin.warehouse-entries.credit-alerts'))
        ->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('total_pending', 0)
        ->assertJsonCount(0, 'data');
});

it('registra pagos parciales, genera egresos y completa el saldo sin duplicar el submit', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['admin.warehouse-entries.update', 'admin.warehouse-entries.index'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo([
        'admin.warehouse-entries.update',
        'admin.warehouse-entries.index',
    ]);
    $this->account->update(['current_balance' => 20000]);
    $entry = warehouseCreditAlertEntry(
        $this->company,
        $this->supplier,
        $this->pen,
        'CREDITO-PARCIAL',
        today()->toDateString(),
        14951.97
    );

    $first = $this->actingAs($this->user)->postJson(
        route('admin.warehouse-entries.credit-payments.store', $entry),
        warehouseCreditPaymentPayload($this->account, $this->pen, 5000, 'pago-parcial-1')
    );

    $first->assertCreated()
        ->assertJsonPath('credit_payment_summary.status', 'partial')
        ->assertJsonPath('credit_payment_summary.paid_amount', 5000)
        ->assertJsonPath('credit_payment_summary.pending_amount', 9951.97);
    $payment = WarehouseEntryCreditPayment::query()->firstOrFail();
    expect((float) $payment->applied_amount)->toBe(5000.0)
        ->and((float) $payment->amount)->toBe(5000.0)
        ->and($payment->bank_movement_id)->not->toBeNull()
        ->and((float) $this->account->fresh()->current_balance)->toBe(15000.0)
        ->and(BankMovement::where('source_type', WarehouseEntryCreditPaymentService::SOURCE_TYPE)
            ->where('source_id', $payment->id)->exists())->toBeTrue();
    $this->actingAs($this->user)
        ->getJson(route('admin.warehouse-entries.credit-alerts'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.payment_status', 'partial')
        ->assertJsonPath('data.0.pending_amount', 9951.97);

    $secondPayload = warehouseCreditPaymentPayload($this->account, $this->pen, 9951.97, 'pago-parcial-2');
    $second = $this->actingAs($this->user)->postJson(
        route('admin.warehouse-entries.credit-payments.store', $entry),
        $secondPayload
    );
    $second->assertCreated()
        ->assertJsonPath('credit_payment_summary.status', 'paid')
        ->assertJsonPath('credit_payment_summary.pending_amount', 0);

    $this->actingAs($this->user)
        ->postJson(route('admin.warehouse-entries.credit-payments.store', $entry), $secondPayload)
        ->assertCreated()
        ->assertJsonPath('credit_payment_summary.status', 'paid');

    expect(WarehouseEntryCreditPayment::where('warehouse_entry_id', $entry->id)->count())->toBe(2)
        ->and(BankMovement::where('source_type', WarehouseEntryCreditPaymentService::SOURCE_TYPE)->count())->toBe(2)
        ->and((float) $this->account->fresh()->current_balance)->toBe(5048.03);
    $this->actingAs($this->user)
        ->getJson(route('admin.warehouse-entries.credit-alerts'))
        ->assertOk()
        ->assertJsonPath('total', 0);
});

it('bloquea un pago mayor al saldo pendiente', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.update', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.update');
    $entry = warehouseCreditAlertEntry(
        $this->company,
        $this->supplier,
        $this->pen,
        'CREDITO-EXCESO',
        today()->toDateString(),
        100
    );

    $this->actingAs($this->user)
        ->postJson(
            route('admin.warehouse-entries.credit-payments.store', $entry),
            warehouseCreditPaymentPayload($this->account, $this->pen, 100.01, 'pago-excesivo')
        )
        ->assertUnprocessable()
        ->assertJsonValidationErrors('applied_amount')
        ->assertJsonPath('errors.applied_amount.0', 'El monto aplicado no puede superar el saldo pendiente.');

    expect(WarehouseEntryCreditPayment::count())->toBe(0)
        ->and(BankMovement::where('source_type', WarehouseEntryCreditPaymentService::SOURCE_TYPE)->count())->toBe(0);
});

it('convierte un pago aplicado en USD a una salida bancaria en PEN', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.update', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.update');
    $usd = Currency::create([
        'code' => 'USD',
        'description' => 'Dólares',
        'symbol' => '$',
        'status' => 'ACTIVE',
    ]);
    $this->account->update(['current_balance' => 3000]);
    $entry = warehouseCreditAlertEntry(
        $this->company,
        $this->supplier,
        $usd,
        'CREDITO-USD-PEN',
        today()->toDateString(),
        500
    );
    $payload = warehouseCreditPaymentPayload($this->account, $this->pen, 500, 'pago-usd-pen');
    $payload['exchange_rate'] = 3.39;

    $this->actingAs($this->user)
        ->postJson(route('admin.warehouse-entries.credit-payments.store', $entry), $payload)
        ->assertCreated()
        ->assertJsonPath('data.applied_amount', '500.0000')
        ->assertJsonPath('data.amount', '1695.0000')
        ->assertJsonPath('data.exchange_rate', '3.390000');

    $movement = BankMovement::where('source_type', WarehouseEntryCreditPaymentService::SOURCE_TYPE)->firstOrFail();
    expect((float) $movement->original_amount)->toBe(500.0)
        ->and((float) $movement->amount)->toBe(1695.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(1305.0);
});

it('rechaza una cuenta de otra empresa al pagar el crédito', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.update', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.update');
    $otherCompany = Company::create([
        'business_name' => 'EMPRESA AJENA CRÉDITO S.A.C.',
        'ruc' => '20999999991',
        'status' => true,
    ]);
    $otherAccount = warehousePaymentAccount($otherCompany, $this->bank, $this->pen, 'OTRA-CREDITO', 1000);
    $entry = warehouseCreditAlertEntry(
        $this->company,
        $this->supplier,
        $this->pen,
        'CREDITO-CUENTA-AJENA',
        today()->toDateString(),
        100
    );

    $this->actingAs($this->user)
        ->postJson(
            route('admin.warehouse-entries.credit-payments.store', $entry),
            warehouseCreditPaymentPayload($otherAccount, $this->pen, 100, 'pago-cuenta-ajena')
        )
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.company_bank_account_id.0',
            'La cuenta bancaria seleccionada no pertenece a la empresa o moneda del pago.'
        );
});

it('entrega al selector únicamente cuentas activas de la empresa solicitada', function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.index', 'web');
    $this->user->givePermissionTo('admin.warehouse-entries.index');
    $inactive = warehousePaymentAccount($this->company, $this->bank, $this->pen, '001-INACTIVA', 500);
    $inactive->update(['status' => 'INACTIVE']);
    $otherCompany = Company::create([
        'business_name' => 'EMPRESA AJENA S.A.C.',
        'ruc' => '20888888881',
        'status' => true,
    ]);
    $otherAccount = warehousePaymentAccount($otherCompany, $this->bank, $this->pen, '999-AJENA', 900);

    $response = $this->actingAs($this->user)->getJson(route(
        'admin.warehouse-entries.company-bank-accounts',
        $this->company
    ));

    $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->account->id);
    expect(collect($response->json('data'))->pluck('id'))
        ->not->toContain($inactive->id)
        ->not->toContain($otherAccount->id);
});

function warehousePaymentAccount(
    Company $company,
    Bank $bank,
    Currency $currency,
    string $number,
    float $balance
): CompanyBankAccount {
    return CompanyBankAccount::create([
        'company_id' => $company->id,
        'bank_id' => $bank->id,
        'currency_id' => $currency->id,
        'account_holder' => $company->business_name,
        'account_number' => $number,
        'is_detraction' => 'NO',
        'status' => 'ACTIVE',
        'current_balance' => $balance,
    ]);
}

function warehousePaymentEntry(
    Company $company,
    Supplier $supplier,
    Currency $currency,
    CompanyBankAccount $account
): WarehouseEntry {
    return WarehouseEntry::create([
        'entry_number' => 'ING-PAGO-001',
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_number' => '123',
        'document_date' => '2026-08-14',
        'generate_account_payable' => false,
        'payment_company_bank_account_id' => $account->id,
        'bank_payment_date' => '2026-08-14',
        'subtotal' => 100,
        'igv' => 18,
        'grand_total' => 118,
        'payable_amount' => 118,
        'status' => 'registered',
    ]);
}

function warehousePaymentInventoryData(): array
{
    $category = Category::create([
        'code' => 'CAT-PAGO',
        'description' => 'CATEGORÍA PAGO',
        'type' => 'PRODUCTO',
        'status' => 'ACTIVE',
    ]);
    $unit = Unit::create([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'decimal_quantity' => false,
        'status' => 'ACTIVE',
    ]);
    $warehouse = Warehouse::create([
        'code' => 'ALM-PAGO',
        'name' => 'ALMACÉN PAGO',
        'status' => 'ACTIVE',
    ]);
    $article = Article::create([
        'code' => 'ART-PAGO',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO PAGO',
        'billing_name' => 'ARTÍCULO PAGO',
        'status' => 'ACTIVE',
    ]);

    return [$warehouse, $article];
}

function warehousePaymentSupplierOrder(
    Company $company,
    Supplier $supplier,
    Currency $currency,
    string $paymentCondition,
    string $code
): SupplierPurchaseOrder {
    return SupplierPurchaseOrder::create([
        'code' => $code,
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'payment_currency_id' => $currency->id,
        'order_type' => 'DIRECTA',
        'payment_method' => 'deposito_cuenta',
        'payment_condition' => $paymentCondition,
        'document_type' => 'factura',
        'affect_igv' => true,
        'grand_total' => 118,
        'total_purchase_currency' => 118,
        'total_payment_currency' => 118,
        'status' => 'registered',
    ]);
}

function warehouseCreditAlertEntry(
    Company $company,
    Supplier $supplier,
    Currency $currency,
    string $code,
    string $dueDate,
    float $pendingAmount,
    array $overrides = [],
    string $paymentCondition = 'credito_30_dias'
): WarehouseEntry {
    $order = warehousePaymentSupplierOrder(
        $company,
        $supplier,
        $currency,
        $paymentCondition,
        "OCP-{$code}"
    );

    return WarehouseEntry::create(array_merge([
        'entry_number' => "ING-{$code}",
        'supplier_purchase_order_id' => $order->id,
        'company_id' => $company->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $currency->id,
        'document_type' => 'FACTURA',
        'document_date' => Carbon::parse($dueDate)->subDays(30)->toDateString(),
        'payment_condition' => $paymentCondition,
        'generate_account_payable' => true,
        'expected_payment_date' => $dueDate,
        'grand_total' => $pendingAmount,
        'payable_amount' => $pendingAmount,
        'status' => 'registered',
    ], $overrides));
}

function warehouseCreditPaymentPayload(
    CompanyBankAccount $account,
    Currency $paymentCurrency,
    float $appliedAmount,
    string $idempotencyKey
): array {
    return [
        'company_bank_account_id' => $account->id,
        'payment_currency_id' => $paymentCurrency->id,
        'applied_amount' => $appliedAmount,
        'exchange_rate' => 1,
        'payment_date' => today()->toDateString(),
        'payment_method' => 'transferencia',
        'operation_number' => 'OP-'.strtoupper($idempotencyKey),
        'observation' => 'Pago de prueba',
        'idempotency_key' => $idempotencyKey,
    ];
}
