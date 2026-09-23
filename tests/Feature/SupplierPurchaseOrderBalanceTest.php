<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderAdvancePayment;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user = User::factory()->create();
    Permission::findOrCreate('admin.supplier-purchase-orders.show', 'web');
    $this->user->givePermissionTo('admin.supplier-purchase-orders.show');
    $this->actingAs($this->user);

    $this->company = Company::create([
        'business_name' => 'DROPAIV SALDO TEST S.A.C.',
        'trade_name' => 'DROPAIV SALDO TEST',
        'ruc' => '20111111111',
        'status' => true,
    ]);
    $this->supplier = Supplier::create([
        'ruc' => '20999999999',
        'business_name' => 'PROVEEDOR SALDO TEST S.A.C.',
        'short_name' => 'PROVEEDOR SALDO',
        'supplier_type' => 'DISTRIBUIDOR',
        'payment_condition' => 'CREDITO',
        'status' => 'ACTIVE',
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->order = SupplierPurchaseOrder::create([
        'code' => 'OCP-SALDO-TEST',
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'payment_currency_id' => $this->currency->id,
        'order_type' => 'DIRECTA',
        'payment_method' => 'deposito_cuenta',
        'payment_condition' => 'credito_30_dias',
        'document_type' => 'factura',
        'affect_igv' => true,
        'subtotal' => 84.75,
        'igv' => 15.25,
        'grand_total' => 100,
        'total_purchase_currency' => 100,
        'total_payment_currency' => 100,
        'total_pen' => 100,
        'apply_advance' => false,
        'advance_amount' => 0,
        'advance_paid_amount' => 0,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_NOT_REQUIRED,
        'payment_status' => 'credit',
        'status' => 'registered',
    ]);
});

it('devuelve el saldo real de compra en el endpoint de detalle', function () {
    $url = route('admin.supplier-purchase-orders.show', $this->order);

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.payment_summary.currency', 'PEN')
        ->assertJsonPath('data.payment_summary.order_total', fn ($value) => (float) $value === 100.0)
        ->assertJsonPath('data.payment_summary.paid_total', fn ($value) => (float) $value === 0.0)
        ->assertJsonPath('data.payment_summary.balance', fn ($value) => (float) $value === 100.0);

    $payment = SupplierPurchaseOrderAdvancePayment::create([
        'supplier_purchase_order_id' => $this->order->id,
        'purchase_currency_id' => $this->currency->id,
        'currency_id' => $this->currency->id,
        'payment_date' => now()->toDateString(),
        'applied_amount' => 40,
        'amount' => 40,
        'amount_pen' => 40,
        'exchange_rate' => 1,
        'payment_method' => 'deposito_cuenta',
        'status' => 'ACTIVE',
        'created_by' => $this->user->id,
        'updated_by' => $this->user->id,
    ]);

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.payment_summary.paid_total', fn ($value) => (float) $value === 40.0)
        ->assertJsonPath('data.payment_summary.balance', fn ($value) => (float) $value === 60.0);

    $payment->update(['applied_amount' => 100, 'amount' => 100, 'amount_pen' => 100]);

    $this->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.payment_summary.paid_total', fn ($value) => (float) $value === 100.0)
        ->assertJsonPath('data.payment_summary.balance', fn ($value) => (float) $value === 0.0);
});
