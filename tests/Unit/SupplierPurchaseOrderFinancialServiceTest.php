<?php

use App\Models\SupplierPurchaseOrder;
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
        1000, 'USD', 'PEN', true, 3.75, true, 'percentage', 30, null, 500, 500, 'contado'
    );
    $paid = $service->calculate(
        1000, 'USD', 'PEN', true, 3.75, true, 'percentage', 30, null, 1125, 1125, 'contado'
    );

    expect($partial['advance_amount'])->toBe(1125.0)
        ->and($partial['advance_status'])->toBe(SupplierPurchaseOrder::ADVANCE_PARTIAL)
        ->and($paid['advance_status'])->toBe(SupplierPurchaseOrder::ADVANCE_PAID);
});

it('rechaza pagos mayores al anticipo requerido', function () {
    (new SupplierPurchaseOrderFinancialService())->calculate(
        1000, 'PEN', 'PEN', false, null, true, 'fixed_amount', null, 300, 301, 301, 'contado'
    );
})->throws(InvalidArgumentException::class, 'El monto pagado no puede ser mayor al anticipo requerido.');

it('exige tipo de cambio para normalizar una compra y pago en dólares', function () {
    (new SupplierPurchaseOrderFinancialService())->calculate(
        1000, 'USD', 'USD', false, null, false, null, null, null, 0, 0, 'credito_30_dias'
    );
})->throws(InvalidArgumentException::class);

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
