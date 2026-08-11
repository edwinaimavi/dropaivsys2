<?php

use App\Services\CustomerOrderProfitabilityService;

function invokeProfitabilityMethod(string $method, mixed ...$arguments): mixed
{
    return (new ReflectionMethod(CustomerOrderProfitabilityService::class, $method))
        ->invoke(new CustomerOrderProfitabilityService, ...$arguments);
}

it('clasifica los documentos oficiales por su tipo', function (string $documentType, bool $expected) {
    expect(invokeProfitabilityMethod('hasOfficialDocument', (object) [
        'document_type' => $documentType,
    ]))->toBe($expected);
})->with([
    ['FACTURA', true], ['Factura', true], ['boleta', true], ['RECIBO_HONORARIOS', true],
    ['RECIBO_INTERNO', false], ['RECIBO', false],
    ['VOUCHER', false], ['SIN_COMPROBANTE', false], ['', false],
]);

it('la clasificación oficial no depende de la constancia de pago adjunta', function () {
    $cost = (object) [
        'document_type' => 'FACTURA',
        'documents' => [(object) ['document_type' => 'payment_proof', 'file_path' => 'voucher.webp', 'status' => 'ACTIVE']],
    ];

    expect(invokeProfitabilityMethod('hasOfficialDocument', $cost))->toBeTrue();
});

it('considera formal el costo cuando se adjunta luego la factura', function () {
    $cost = (object) [
        'document_type' => 'BOLETA',
        'documents' => [
            (object) ['document_type' => 'payment_proof', 'file_path' => 'pago.png', 'status' => 'ACTIVE'],
            (object) ['document_type' => 'invoice', 'file_path' => 'boleta.pdf', 'status' => 'ACTIVE'],
        ],
    ];

    expect(invokeProfitabilityMethod('hasOfficialDocument', $cost))->toBeTrue();
});

it('mantiene el importe completo aunque una factura tenga IGV', function () {
    $cost = (object) [
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'amount' => 118.00,
        'total_amount' => 118.00,
        'taxable_amount' => 100.00,
        'igv_amount' => 18.00,
        'documents' => [(object) ['document_type' => 'payment_proof', 'file_path' => 'pago.png', 'status' => 'ACTIVE']],
    ];

    expect(invokeProfitabilityMethod('costValueForMode', $cost, 'without_igv'))->toBe(118.0);
});

it('reconoce tipos actuales e históricos de transporte', function (array $cost, bool $expected) {
    expect(invokeProfitabilityMethod('isTransportCost', (object) $cost))->toBe($expected);
})->with([
    [['expense_category' => 'freight_transport'], true],
    [['cost_type' => 'flete_agencia'], true],
    [['expense_type' => 'pickup_transfer'], true],
    [['category' => 'otros_gastos', 'expense_type' => 'other'], false],
]);

it('clasifica los cinco casos obligatorios de costos vinculados', function () {
    $costs = collect([
        (object) ['id' => 1, 'expense_type' => 'agency_freight', 'expense_category' => 'freight_transport', 'document_type' => 'FACTURA', 'amount' => 100.00],
        (object) ['id' => 2, 'expense_type' => 'pickup_transfer', 'expense_category' => 'freight_transport', 'document_type' => 'RECIBO_INTERNO', 'amount' => 105.00],
        (object) ['id' => 3, 'expense_type' => 'pickup_transfer', 'expense_category' => 'freight_transport', 'document_type' => 'SIN_COMPROBANTE', 'amount' => 50.00],
        (object) ['id' => 4, 'expense_type' => 'other', 'expense_category' => 'other_expense', 'document_type' => 'FACTURA', 'amount' => 1500.00],
    ]);

    $classified = invokeProfitabilityMethod('classifyLinkedCosts', $costs);

    expect($classified['freight']->pluck('id')->all())->toBe([1])
        ->and((float) $classified['freight']->sum('amount'))->toBe(100.0)
        ->and($classified['other']->pluck('id')->all())->toBe([2, 3, 4])
        ->and((float) $classified['other']->sum('amount'))->toBe(1655.0);
});

it('reclasifica un recojo regularizado de recibo interno a factura', function () {
    $cost = (object) [
        'id' => 1,
        'expense_type' => 'pickup_transfer',
        'expense_category' => 'freight_transport',
        'document_type' => 'RECIBO_INTERNO',
        'amount' => 105.00,
    ];

    $initial = invokeProfitabilityMethod('classifyLinkedCosts', collect([$cost]));
    $cost->document_type = 'FACTURA';
    $regularized = invokeProfitabilityMethod('classifyLinkedCosts', collect([$cost]));

    expect((float) $initial['other']->sum('amount'))->toBe(105.0)
        ->and($initial['freight'])->toBeEmpty()
        ->and((float) $regularized['freight']->sum('amount'))->toBe(105.0)
        ->and($regularized['other'])->toBeEmpty();
});

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

it('usa el importe completo del flete en ambos modos y conserva el IGV informativo', function () {
    $cost = (object) [
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'igv_rate' => 18,
        'amount' => 247.40,
        'total_amount' => 247.40,
        'taxable_amount' => 209.66,
        'igv_amount' => 37.74,
        'documents' => [(object) ['document_type' => 'invoice', 'file_path' => 'factura.pdf', 'status' => 'ACTIVE']],
    ];

    $withoutIgv = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), 'without_igv');
    $withIgv = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), 'with_igv');

    expect($withoutIgv['freightValue'])->toBe(247.40)
        ->and($withoutIgv['linkedIgv'])->toBe(37.74)
        ->and($withIgv['freightValue'])->toBe(247.40);
});

it('reproduce los costos vinculados de la OC 4505460426 sin retirar el IGV', function () {
    $freight = collect([
        (object) [
            'document_type' => 'FACTURA', 'affects_igv' => true, 'igv_rate' => 18,
            'amount' => 128.00, 'total_amount' => 128.00,
            'taxable_amount' => 108.47, 'igv_amount' => 19.53,
        ],
        (object) [
            'document_type' => 'FACTURA', 'affects_igv' => true, 'igv_rate' => 18,
            'amount' => 25.00, 'total_amount' => 25.00,
            'taxable_amount' => 21.19, 'igv_amount' => 3.81,
        ],
    ]);
    $other = collect([
        (object) [
            'document_type' => 'SIN_COMPROBANTE', 'affects_igv' => false,
            'amount' => 1150.00, 'total_amount' => 1150.00,
        ],
    ]);

    $figures = invokeProfitabilityMethod('linkedCostFigures', $freight, $other, 'without_igv');
    $profit = invokeProfitabilityMethod('profitFigures', 10000.00, 5000.00, $figures['freightValue'], $figures['otherValue']);

    expect($figures['freightValue'])->toBe(153.0)
        ->and($figures['otherValue'])->toBe(1150.0)
        ->and($figures['linkedValue'])->toBe(1303.0)
        ->and($figures['freightBase'])->toBe(129.66)
        ->and($figures['freightIgv'])->toBe(23.34)
        ->and($profit['operating'])->toBe(4847.0)
        ->and($profit['incomeTax'])->toBe(1429.87)
        ->and($profit['net'])->toBe(2267.13);
});

it('mantiene recibos y gastos sin comprobante por su importe completo', function () {
    $receipt = (object) ['document_type' => 'RECIBO', 'affects_igv' => false, 'amount' => 105.00, 'total_amount' => 105.00];
    $withoutDocument = (object) ['document_type' => 'SIN_COMPROBANTE', 'affects_igv' => false, 'amount' => 1500.00, 'total_amount' => 1500.00];

    $figures = invokeProfitabilityMethod('linkedCostFigures', collect(), collect([$receipt, $withoutDocument]), 'without_igv');

    expect($figures['otherValue'])->toBe(1605.0)
        ->and($figures['otherIgv'])->toBe(0.0);
});

it('usa total pen como fuente principal para convertir compras extranjeras', function () {
    $item = (object) [
        'order_grand_total' => 1000,
        'order_total_pen' => 3750,
        'order_total_payment_currency' => 3750,
        'purchase_currency_code' => 'USD',
        'payment_currency_code' => 'PEN',
        'order_exchange_rate' => 3.80,
    ];

    expect(invokeProfitabilityMethod('supplierPurchasePenFactor', $item))->toBe(3.75);
});

it('no trata una compra extranjera sin conversión como si fueran soles', function () {
    $item = (object) [
        'order_grand_total' => 1000,
        'order_total_pen' => null,
        'order_total_payment_currency' => 0,
        'purchase_currency_code' => 'USD',
        'payment_currency_code' => 'USD',
        'order_exchange_rate' => null,
    ];

    expect(invokeProfitabilityMethod('supplierPurchasePenFactor', $item))->toBe(0.0);
});

it('considera el total con IGV cuando la OC proveedor es afecta', function () {
    $purchase = (object) [
        'taxable_base_pen' => 26047.80,
        'line_total_pen' => 30736.40,
        'order_affect_igv' => true,
    ];

    invokeProfitabilityMethod('applyConsideredPurchaseAmounts', $purchase);

    expect($purchase->purchase_subtotal_pen)->toBe(26047.80)
        ->and($purchase->purchase_igv_pen)->toBe(4688.60)
        ->and($purchase->purchase_total_pen)->toBe(30736.40)
        ->and($purchase->considered_purchase_amount)->toBe(30736.40);
});

it('considera el importe normal de una OC proveedor no afecta a IGV', function () {
    $purchase = (object) [
        'taxable_base_pen' => 1200.00,
        'line_total_pen' => 1200.00,
        'order_affect_igv' => false,
    ];

    invokeProfitabilityMethod('applyConsideredPurchaseAmounts', $purchase);

    expect($purchase->purchase_igv_pen)->toBe(0.0)
        ->and($purchase->considered_purchase_amount)->toBe(1200.00);
});

it('prioriza el desglose del ingreso de almacén sobre los importes de la OC proveedor', function () {
    $item = (object) [
        'order_affect_igv' => true,
        'total_with_igv' => 99999.00,
        'line_total' => 99999.00,
        'quantity' => 1,
        'unit_price' => 99999.00,
        'taxable_base' => 84744.92,
    ];
    $warehouseAmount = (object) [
        'subtotal' => 26047.80,
        'igv' => 4688.60,
        'total' => 30736.40,
    ];

    $amounts = invokeProfitabilityMethod('purchaseSourceAmounts', $item, $warehouseAmount, true);

    expect($amounts)->toBe([
        'subtotal' => 26047.80,
        'total' => 30736.40,
        'source' => 'warehouse_entry',
    ]);
});

it('reproduce el caso 4505470202 con la compra total como costo real', function () {
    $figures = invokeProfitabilityMethod('profitFigures', 43781.50, 30736.40, 0, 0);

    expect($figures['gross'])->toBe(13045.10);
});
