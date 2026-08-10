<?php

use App\Services\CustomerOrderProfitabilityService;

function invokeProfitabilityMethod(string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod(CustomerOrderProfitabilityService::class, $method))
        ->invoke(new CustomerOrderProfitabilityService(), ...$arguments);
}

it('solo reconoce factura y boleta como comprobantes válidos', function (string $documentType, bool $expected) {
    expect(invokeProfitabilityMethod('isValidPaymentDocument', (object) ['document_type' => $documentType]))->toBe($expected);
})->with([
    ['FACTURA', true], ['Factura', true], ['boleta', true], ['RECIBO', false],
    ['VOUCHER', false], ['SIN_COMPROBANTE', false], ['', false],
]);

it('reconoce tipos actuales e históricos de transporte', function (array $cost, bool $expected) {
    expect(invokeProfitabilityMethod('isTransportCost', (object) $cost))->toBe($expected);
})->with([
    [['expense_category' => 'freight_transport'], true],
    [['cost_type' => 'flete_agencia'], true],
    [['expense_type' => 'pickup_transfer'], true],
    [['category' => 'otros_gastos', 'expense_type' => 'other'], false],
]);

it('reproduce la fórmula gerencial con transporte válido y otros gastos sin comprobante', function () {
    $figures = invokeProfitabilityMethod('profitFigures', 43335.00, 31193.37, 142.40, 1605.00);

    expect($figures['gross'])->toBe(12141.63)
        ->and($figures['operating'])->toBe(11999.23)
        ->and($figures['incomeTax'])->toBe(3539.77)
        ->and($figures['net'])->toBe(6854.46);
});

it('calcula impuesto antes de descontar otros gastos', function () {
    $figures = invokeProfitabilityMethod(
        'profitFigures',
        12141.63,
        0,
        247.40,
        1500.00
    );

    expect($figures['gross'])->toBe(12141.63)
        ->and($figures['operating'])->toBe(11894.23)
        ->and($figures['incomeTax'])->toBe(3508.80)
        ->and($figures['net'])->toBe(6885.43);
});

it('no calcula impuesto a la renta cuando la utilidad operativa es negativa', function () {
    $figures = invokeProfitabilityMethod('profitFigures', 100, 150, 20, 10);

    expect($figures['operating'])->toBe(-70.0)
        ->and($figures['incomeTax'])->toBe(0.0)
        ->and($figures['net'])->toBe(-80.0);
});

it('usa la base de un flete afecto en modo sin IGV y su total en modo con IGV', function () {
    $cost = (object) [
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'igv_rate' => 18,
        'amount' => 247.40,
        'total_amount' => 247.40,
        'taxable_amount' => 209.66,
        'igv_amount' => 37.74,
    ];

    $withoutIgv = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), 'without_igv');
    $withIgv = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), 'with_igv');

    expect($withoutIgv['freightValue'])->toBe(209.66)
        ->and($withoutIgv['linkedIgv'])->toBe(37.74)
        ->and($withIgv['freightValue'])->toBe(247.40);
});

it('mantiene recibos y gastos sin comprobante por su importe completo', function () {
    $receipt = (object) ['document_type' => 'RECIBO', 'affects_igv' => false, 'amount' => 105.00, 'total_amount' => 105.00];
    $withoutDocument = (object) ['document_type' => 'SIN_COMPROBANTE', 'affects_igv' => false, 'amount' => 1500.00, 'total_amount' => 1500.00];

    $figures = invokeProfitabilityMethod('linkedCostFigures', collect(), collect([$receipt, $withoutDocument]), 'without_igv');

    expect($figures['otherValue'])->toBe(1605.0)
        ->and($figures['otherIgv'])->toBe(0.0);
});
