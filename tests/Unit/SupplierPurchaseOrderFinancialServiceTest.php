<?php

use App\Models\Currency;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderAdvancePayment;
use App\Services\SupplierPurchaseOrderFinancialService;

it('mantiene una orden en soles sin conversión ni anticipo', function () {
    $result = (new SupplierPurchaseOrderFinancialService())->calculate(
        1180, 'PEN', 'PEN', false, null, false, null, null, null, 0, 0, 'contado'
    );

    expect($result['total_payment_currency'])->toBe(1180.0)
        ->and($result['total_pen'])->toBe(1180.0)
        ->and($result['advance_status'])->toBe(SupplierPurchaseOrder::ADVANCE_NOT_REQUIRED)
        ->and($result['payment_status'])->toBe('pending');
});

it('convierte una compra en dólares pagada en soles', function () {
    $result = (new SupplierPurchaseOrderFinancialService())->calculate(
        1000, 'USD', 'PEN', true, 3.75, false, null, null, null, 0, 0, 'contado'
    );

    expect($result['total_purchase_currency'])->toBe(1000.0)
        ->and($result['total_payment_currency'])->toBe(3750.0)
        ->and($result['total_pen'])->toBe(3750.0);
});

it('calcula el anticipo porcentual y sus estados por pagos trazados', function () {
    $service = new SupplierPurchaseOrderFinancialService();
    $partial = $service->calculate(
        1000, 'USD', 'PEN', true, 3.75, true, 'percentage', 30, null, 150, 562.5, 'contado'
    );
    $paid = $service->calculate(
        1000, 'USD', 'PEN', true, 3.75, true, 'percentage', 30, null, 300, 1125, 'contado'
    );

    expect($partial['advance_amount'])->toBe(300.0)
        ->and($partial['advance_status'])->toBe(SupplierPurchaseOrder::ADVANCE_PARTIAL)
        ->and($paid['advance_status'])->toBe(SupplierPurchaseOrder::ADVANCE_PAID);
});

it('rechaza pagos mayores al anticipo requerido', function () {
    (new SupplierPurchaseOrderFinancialService())->calculate(
        1000, 'PEN', 'PEN', false, null, true, 'fixed_amount', null, 300, 301, 301, 'contado'
    );
})->throws(InvalidArgumentException::class, 'El monto aplicado no puede ser mayor al anticipo pendiente.');

it('permite compra y pago en dólares sin exigir un tipo de cambio global', function () {
    $result = (new SupplierPurchaseOrderFinancialService())->calculate(
        1000, 'USD', 'USD', false, null, false, null, null, null, 0, 0, 'credito_30_dias'
    );

    expect($result['total_purchase_currency'])->toBe(1000.0)
        ->and($result['total_payment_currency'])->toBe(1000.0)
        ->and($result['exchange_rate'])->toBeNull();
});

it('calcula cada pago con su propio tipo de cambio', function () {
    $service = new SupplierPurchaseOrderFinancialService();

    expect($service->convertAppliedToPaid(500, 'USD', 'PEN', 3.39))->toBe(1695.0)
        ->and($service->convertAppliedToPaid(600, 'USD', 'PEN', 3.56))->toBe(2136.0)
        ->and($service->convertAppliedToPaid(300, 'PEN', 'PEN', 1))->toBe(300.0)
        ->and($service->convertAppliedToPaid(300, 'USD', 'USD', 1))->toBe(300.0);
});

it('bloquea el ingreso contado con anticipo pendiente y permite crédito con aviso', function () {
    $cash = new SupplierPurchaseOrder([
        'apply_advance' => true,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PARTIAL,
        'payment_condition' => 'contado',
    ]);
    $credit = new SupplierPurchaseOrder([
        'apply_advance' => true,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PENDING,
        'payment_condition' => 'credito_30_dias',
    ]);

    expect($cash->hasPendingCashAdvance())->toBeTrue()
        ->and($cash->hasCreditAdvanceWarning())->toBeFalse()
        ->and($credit->hasPendingCashAdvance())->toBeFalse()
        ->and($credit->hasCreditAdvanceWarning())->toBeTrue();
});

it('calcula el saldo real en PEN desde los pagos activos y no desde el acumulado guardado', function () {
    $pen = new Currency(['code' => 'PEN']);
    $payment = new SupplierPurchaseOrderAdvancePayment([
        'amount' => 4000,
        'amount_pen' => 4000,
        'status' => 'ACTIVE',
    ]);
    $payment->setRelation('currency', $pen);
    $payment->setRelation('purchaseCurrency', $pen);
    $order = new SupplierPurchaseOrder([
        'grand_total' => 10000,
        'total_purchase_currency' => 10000,
        'total_payment_currency' => 10000,
        'apply_advance' => true,
        'advance_type' => 'percentage',
        'advance_percentage' => 50,
        'advance_amount' => 0,
        'advance_paid_amount' => 0,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PENDING,
        'payment_condition' => 'contado',
    ]);
    $order->setRelation('currency', $pen);
    $order->setRelation('paymentCurrency', $pen);
    $order->setRelation('advancePayments', collect([$payment]));

    $summary = (new SupplierPurchaseOrderFinancialService())->paymentSummary($order);
    $order->payment_condition = 'credito_30_dias';
    $creditSummary = (new SupplierPurchaseOrderFinancialService())->paymentSummary($order);

    expect($summary['currency'])->toBe('PEN')
        ->and($summary['order_total'])->toBe(10000.0)
        ->and($summary['paid_total'])->toBe(4000.0)
        ->and($summary['balance'])->toBe(6000.0)
        ->and($summary['advance_required'])->toBe(5000.0)
        ->and($summary['required_advance_balance'])->toBe(1000.0)
        ->and($summary['financial_blocked'])->toBeTrue()
        ->and($creditSummary['financial_blocked'])->toBeFalse()
        ->and($creditSummary['financial_credit_warning'])->toBeTrue();
});

it('interpreta un pago histórico usando el tipo de cambio registrado en la orden', function () {
    $pen = new Currency(['code' => 'PEN']);
    $usd = new Currency(['code' => 'USD']);
    $payment = new SupplierPurchaseOrderAdvancePayment([
        'amount' => 1850,
        'amount_pen' => 1850,
        'status' => 'ACTIVE',
    ]);
    $payment->setRelation('currency', $pen);
    $payment->setRelation('purchaseCurrency', $usd);
    $order = new SupplierPurchaseOrder([
        'grand_total' => 1000,
        'total_purchase_currency' => 1000,
        'total_payment_currency' => 3700,
        'total_pen' => 3700,
        'exchange_rate' => 3.7,
        'apply_advance' => true,
        'advance_type' => 'percentage',
        'advance_percentage' => 60,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PARTIAL,
        'payment_condition' => 'contado',
    ]);
    $order->setRelation('currency', $usd);
    $order->setRelation('paymentCurrency', $pen);
    $order->setRelation('advancePayments', collect([$payment]));

    $summary = (new SupplierPurchaseOrderFinancialService())->paymentSummary($order);

    expect($summary['breakdown'])->toHaveCount(1)
        ->and($summary['currency'])->toBe('USD')
        ->and($summary['order_total'])->toBe(1000.0)
        ->and($summary['paid_total'])->toBe(500.0)
        ->and($summary['balance'])->toBe(500.0);
});

it('calcula el saldo aplicado con dos pagos PEN y tipos de cambio distintos', function () {
    $pen = new Currency(['code' => 'PEN']);
    $usd = new Currency(['code' => 'USD']);
    $first = new SupplierPurchaseOrderAdvancePayment([
        'purchase_currency_id' => 2,
        'applied_amount' => 500,
        'amount' => 1695,
        'amount_pen' => 1695,
        'exchange_rate' => 3.39,
        'status' => 'ACTIVE',
    ]);
    $second = new SupplierPurchaseOrderAdvancePayment([
        'purchase_currency_id' => 2,
        'applied_amount' => 600,
        'amount' => 2136,
        'amount_pen' => 2136,
        'exchange_rate' => 3.56,
        'status' => 'ACTIVE',
    ]);
    foreach ([$first, $second] as $payment) {
        $payment->setRelation('currency', $pen);
        $payment->setRelation('purchaseCurrency', $usd);
    }
    $order = new SupplierPurchaseOrder([
        'grand_total' => 1100,
        'total_purchase_currency' => 1100,
        'apply_advance' => true,
        'advance_type' => 'percentage',
        'advance_percentage' => 100,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PARTIAL,
        'payment_condition' => 'contado',
    ]);
    $order->setRelation('currency', $usd);
    $order->setRelation('paymentCurrency', $pen);
    $order->setRelation('advancePayments', collect([$first, $second]));

    $summary = (new SupplierPurchaseOrderFinancialService())->paymentSummary($order);

    expect($summary['paid_total'])->toBe(1100.0)
        ->and($summary['balance'])->toBe(0.0)
        ->and($summary['required_advance_balance'])->toBe(0.0)
        ->and($summary['fully_paid'])->toBeTrue();
});

it('permite el ingreso si el total real ya fue pagado aunque el estado guardado siga pendiente', function () {
    $usd = new Currency(['code' => 'USD']);
    $payment = new SupplierPurchaseOrderAdvancePayment([
        'amount' => 2000,
        'amount_pen' => 7400,
        'exchange_rate' => 3.7,
        'status' => 'ACTIVE',
    ]);
    $payment->setRelation('currency', $usd);
    $payment->setRelation('purchaseCurrency', $usd);
    $order = new SupplierPurchaseOrder([
        'grand_total' => 2000,
        'total_purchase_currency' => 2000,
        'total_payment_currency' => 2000,
        'exchange_rate' => 3.7,
        'apply_advance' => true,
        'advance_type' => 'percentage',
        'advance_percentage' => 50,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PENDING,
        'payment_condition' => 'contado',
    ]);
    $order->setRelation('currency', $usd);
    $order->setRelation('paymentCurrency', $usd);
    $order->setRelation('advancePayments', collect([$payment]));

    $summary = (new SupplierPurchaseOrderFinancialService())->paymentSummary($order);

    expect($summary['balance'])->toBe(0.0)
        ->and($summary['fully_paid'])->toBeTrue()
        ->and($summary['has_pending_advance'])->toBeFalse()
        ->and($summary['financial_blocked'])->toBeFalse();
});

it('desglosa pagos registrados en PEN y USD sin mezclar sus importes originales', function () {
    $pen = new Currency(['code' => 'PEN']);
    $usd = new Currency(['code' => 'USD']);
    $usdPayment = new SupplierPurchaseOrderAdvancePayment([
        'amount' => 300,
        'amount_pen' => 1110,
        'exchange_rate' => 3.7,
        'status' => 'ACTIVE',
    ]);
    $usdPayment->setRelation('currency', $usd);
    $usdPayment->setRelation('purchaseCurrency', $usd);
    $penPayment = new SupplierPurchaseOrderAdvancePayment([
        'amount' => 740,
        'amount_pen' => 740,
        'exchange_rate' => 3.7,
        'status' => 'ACTIVE',
    ]);
    $penPayment->setRelation('currency', $pen);
    $penPayment->setRelation('purchaseCurrency', $usd);
    $order = new SupplierPurchaseOrder([
        'grand_total' => 1000,
        'total_purchase_currency' => 1000,
        'total_payment_currency' => 1000,
        'exchange_rate' => 3.7,
        'apply_advance' => true,
        'advance_type' => 'percentage',
        'advance_percentage' => 60,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PARTIAL,
        'payment_condition' => 'contado',
    ]);
    $order->setRelation('currency', $usd);
    $order->setRelation('paymentCurrency', $usd);
    $order->setRelation('advancePayments', collect([$usdPayment, $penPayment]));

    $summary = (new SupplierPurchaseOrderFinancialService())->paymentSummary($order);

    expect($summary['paid_total'])->toBe(500.0)
        ->and($summary['balance'])->toBe(500.0)
        ->and($summary['payments_by_currency'])->toBe([
            ['currency' => 'USD', 'amount' => 300.0],
            ['currency' => 'PEN', 'amount' => 740.0],
        ]);
});
