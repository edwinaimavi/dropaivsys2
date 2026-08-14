<?php

use App\Models\Bank;
use App\Models\BankMovement;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Services\WarehouseEntryBankPaymentService;
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
