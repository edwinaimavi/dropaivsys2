<?php

use App\Models\Bank;
use App\Models\BankMovement;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\CustomerPurchaseOrder;
use App\Models\ElectronicInvoice;
use App\Models\InvoiceCollection;
use App\Models\User;
use App\Services\InvoiceCollectionService;
use App\Services\InvoiceFromCustomerOrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->company = Company::create([
        'business_name' => 'EMISOR LOCAL S.A.C.',
        'ruc' => '20601010101',
        'status' => true,
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->bank = Bank::create([
        'description' => 'BANCO DE PRUEBA',
        'short_name' => 'BPR',
        'status' => 'ACTIVE',
    ]);
    $this->account = CompanyBankAccount::create([
        'company_id' => $this->company->id,
        'bank_id' => $this->bank->id,
        'currency_id' => $this->currency->id,
        'account_holder' => 'EMISOR LOCAL S.A.C.',
        'account_number' => '001-LOCAL',
        'current_balance' => 0,
        'status' => 'ACTIVE',
    ]);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'first_name' => '',
        'last_name' => '',
        'business_name' => 'CLIENTE LOCAL',
        'document_type' => 'RUC',
        'document_number' => '20501010101',
        'ruc' => '20501010101',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $this->order = CustomerPurchaseOrder::create([
        'code' => 'OCC-TEST-001',
        'company_id' => $this->company->id,
        'customer_id' => $customerId,
        'order_type' => 'articles',
        'purchase_order_number' => '450000001',
        'currency_id' => $this->currency->id,
        'grand_total' => 10000,
        'status' => 'attended',
        'created_by' => $this->user->id,
    ]);
    $this->orderItem = $this->order->items()->create([
        'billing_name_snapshot' => 'PRODUCTO DE PRUEBA',
        'quantity' => 10,
        'unit_price' => 1000,
        'line_total' => 10000,
        'status' => 'active',
    ]);
    $this->invoice = ElectronicInvoice::create([
        'company_id' => $this->company->id,
        'customer_id' => $customerId,
        'customer_purchase_order_id' => $this->order->id,
        'currency_id' => $this->currency->id,
        'document_type' => '01',
        'serie' => 'F001',
        'correlativo' => '00000001',
        'full_number' => 'F001-00000001',
        'issue_date' => today(),
        'currency_code' => 'PEN',
        'client_name' => 'CLIENTE LOCAL',
        'total_amount' => 10000,
        'paid_amount' => 0,
        'pending_amount' => 10000,
        'payment_status' => 'pending',
        'status' => 'generated',
        'created_by' => $this->user->id,
    ]);
    $this->service = app(InvoiceCollectionService::class);
});

function invoiceCollectionPayload(CompanyBankAccount $account, Currency $currency, float $amount, string $key): array
{
    return [
        'company_bank_account_id' => $account->id,
        'collection_date' => today()->toDateString(),
        'amount' => $amount,
        'currency_id' => $currency->id,
        'operation_number' => 'OP-LOCAL-001',
        'observation' => 'COBRO CONFIRMADO',
        'idempotency_key' => $key,
    ];
}

it('genera la cuenta por cobrar sin crear un ingreso bancario al emitir', function () {
    expect((float) $this->invoice->pending_amount)->toBe(10000.0)
        ->and($this->invoice->payment_status)->toBe('pending')
        ->and(BankMovement::count())->toBe(0);
});

it('muestra empresa banco moneda y numero en la cuenta seleccionable del modal', function () {
    Permission::findOrCreate('admin.electronic-invoices.index', 'web');
    $this->user->givePermissionTo('admin.electronic-invoices.index');

    $this->actingAs($this->user)
        ->get(route('admin.electronic-invoices.index'))
        ->assertOk()
        ->assertSee('EMISOR LOCAL S.A.C. — BPR - PEN - 001-LOCAL')
        ->assertSee('value="'.$this->account->id.'"', false)
        ->assertDontSee('EMISOR LOCAL S.A.C. &mdash; BPR', false);
});

it('registra cobros parciales y completa la factura con movimientos bancarios trazables', function () {
    $first = $this->service->register(
        $this->invoice,
        invoiceCollectionPayload($this->account, $this->currency, 3000, 'collection-partial-1'),
        null,
        $this->user->id
    );

    expect((float) $this->invoice->fresh()->paid_amount)->toBe(3000.0)
        ->and((float) $this->invoice->fresh()->pending_amount)->toBe(7000.0)
        ->and($this->invoice->fresh()->payment_status)->toBe('partial')
        ->and((float) $this->account->fresh()->current_balance)->toBe(3000.0)
        ->and($first->bankMovement->source_type)->toBe('ELECTRONIC_INVOICE_COLLECTION')
        ->and($first->bankMovement->source_code)->toBe('F001-00000001');

    $this->service->register(
        $this->invoice->fresh(),
        invoiceCollectionPayload($this->account, $this->currency, 7000, 'collection-partial-2'),
        null,
        $this->user->id
    );

    expect((float) $this->invoice->fresh()->pending_amount)->toBe(0.0)
        ->and($this->invoice->fresh()->payment_status)->toBe('paid')
        ->and((float) $this->account->fresh()->current_balance)->toBe(10000.0)
        ->and(InvoiceCollection::count())->toBe(2)
        ->and(BankMovement::count())->toBe(2)
        ->and(app(InvoiceFromCustomerOrderService::class)->summary($this->order->fresh())['collection_status'])->toBe('paid');
});

it('es idempotente y no duplica el ingreso bancario ante un reintento', function () {
    $payload = invoiceCollectionPayload($this->account, $this->currency, 3000, 'collection-retry');
    $first = $this->service->register($this->invoice, $payload, null, $this->user->id);
    $second = $this->service->register($this->invoice->fresh(), $payload, null, $this->user->id);

    expect($second->id)->toBe($first->id)
        ->and(InvoiceCollection::count())->toBe(1)
        ->and(BankMovement::count())->toBe(1)
        ->and((float) $this->account->fresh()->current_balance)->toBe(3000.0);
});

it('bloquea cobros mayores al saldo pendiente', function () {
    expect(fn () => $this->service->register(
        $this->invoice,
        invoiceCollectionPayload($this->account, $this->currency, 11000, 'collection-overpayment'),
        null,
        $this->user->id
    ))->toThrow(ValidationException::class, 'no puede superar el saldo pendiente');

    expect(BankMovement::count())->toBe(0)
        ->and(InvoiceCollection::count())->toBe(0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(0.0);
});

it('bloquea una cuenta bancaria de otra empresa', function () {
    $otherCompany = Company::create([
        'business_name' => 'OTRA EMPRESA S.A.C.',
        'ruc' => '20602020202',
        'status' => true,
    ]);
    $otherAccount = CompanyBankAccount::create([
        'company_id' => $otherCompany->id,
        'bank_id' => $this->bank->id,
        'currency_id' => $this->currency->id,
        'account_holder' => 'OTRA EMPRESA S.A.C.',
        'account_number' => '009-OTRA',
        'status' => 'ACTIVE',
    ]);

    expect(fn () => $this->service->register(
        $this->invoice,
        invoiceCollectionPayload($otherAccount, $this->currency, 1000, 'collection-wrong-company'),
        null,
        $this->user->id
    ))->toThrow(ValidationException::class, 'no pertenece a la empresa emisora');

    expect(BankMovement::count())->toBe(0);
});

it('exige tipo de cambio cuando la cuenta tiene otra moneda', function () {
    $usd = Currency::create([
        'code' => 'USD',
        'description' => 'Dolares',
        'symbol' => '$',
        'status' => 'ACTIVE',
    ]);
    $usdAccount = CompanyBankAccount::create([
        'company_id' => $this->company->id,
        'bank_id' => $this->bank->id,
        'currency_id' => $usd->id,
        'account_holder' => 'EMISOR LOCAL S.A.C.',
        'account_number' => '001-USD',
        'status' => 'ACTIVE',
    ]);

    expect(fn () => $this->service->register(
        $this->invoice,
        invoiceCollectionPayload($usdAccount, $usd, 1000, 'collection-without-rate'),
        null,
        $this->user->id
    ))->toThrow(ValidationException::class, 'Ingrese un tipo de cambio');

    expect(BankMovement::count())->toBe(0);
});

it('marca como vencida una factura pendiente cuya fecha ya paso', function () {
    $this->invoice->update(['due_date' => today()->subDay()]);

    expect($this->invoice->fresh()->effectivePaymentStatus())->toBe('overdue');
});

it('bloquea facturar nuevamente cantidades ya cubiertas por otra factura', function () {
    $this->invoice->items()->create([
        'customer_purchase_order_item_id' => $this->orderItem->id,
        'item_number' => 1,
        'description' => 'PRODUCTO DE PRUEBA',
        'unit_code' => 'NIU',
        'quantity' => 10,
        'unit_price' => 1000,
        'line_total' => 10000,
    ]);

    expect(fn () => app(InvoiceFromCustomerOrderService::class)->validateGeneratedInvoice(
        $this->order,
        [[
            'customer_purchase_order_item_id' => $this->orderItem->id,
            'quantity' => 10,
        ]]
    ))->toThrow(ValidationException::class, 'supera el saldo pendiente');
});

it('bloquea una factura cuyo importe supera el saldo monetario de la orden', function () {
    expect(fn () => app(InvoiceFromCustomerOrderService::class)->validateGeneratedInvoice(
        $this->order,
        [[
            'customer_purchase_order_item_id' => $this->orderItem->id,
            'quantity' => 10,
        ]],
        $this->invoice,
        11000
    ))->toThrow(ValidationException::class, 'supera el saldo por facturar');
});
