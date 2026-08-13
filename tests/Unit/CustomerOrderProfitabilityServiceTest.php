<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerPurchaseOrderItem;
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

it('normaliza una factura afecta cuando la OC cliente usa estructura con IGV', function () {
    $cost = (object) [
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'amount' => 118.00,
        'total_amount' => 118.00,
        'taxable_amount' => 100.00,
        'igv_amount' => 18.00,
        'documents' => [(object) ['document_type' => 'payment_proof', 'file_path' => 'pago.png', 'status' => 'ACTIVE']],
    ];

    expect(invokeProfitabilityMethod('costValueForStructure', $cost, true))->toBe(100.0);
});

it('usa la base de un costo oficial afecto solo en la estructura con IGV', function () {
    $cost = (object) [
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'igv_rate' => 18,
        'amount' => 128.00,
        'total_amount' => 128.00,
        'taxable_amount' => 108.47,
        'igv_amount' => 19.53,
    ];

    $exonerated = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), false);
    $withIgv = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), true);

    expect($exonerated['linkedValue'])->toBe(128.0)
        ->and(round($withIgv['linkedValue'], 2))->toBe(108.47)
        ->and($withIgv['linkedBase'])->toBe(108.47)
        ->and($withIgv['linkedIgv'])->toBe(19.53);
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

    expect($classified['freight']->pluck('id')->all())->toBe([1, 4])
        ->and((float) $classified['freight']->sum('amount'))->toBe(1600.0)
        ->and($classified['other']->pluck('id')->all())->toBe([2, 3])
        ->and((float) $classified['other']->sum('amount'))->toBe(155.0);
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

it('no incluye el impuesto a la renta estimado en la base del porcentaje de rentabilidad', function () {
    $figures = invokeProfitabilityMethod('profitFigures', 890.00, 400.00, 90.00, 0.00);
    $metrics = invokeProfitabilityMethod(
        'profitabilityMetrics',
        false,
        400.00,
        400.00,
        0.00,
        0.00,
        90.00,
        0.00,
        $figures['net']
    );

    expect($figures['incomeTax'])->toBe(118.00)
        ->and($figures['net'])->toBe(282.00)
        ->and($metrics['base'])->toBe(490.00)
        ->and($metrics['percentage'])->toBe(57.55);
});

it('calcula la rentabilidad con IGV usando la compra considerada y la diferencia entre IGV de venta y compra', function () {
    $metrics = invokeProfitabilityMethod(
        'profitabilityMetrics',
        true,
        25800.00,
        21864.41,
        5834.75,
        3935.59,
        254.62,
        1500.00,
        5758.84
    );

    expect($metrics['base'])->toBe(29453.78)
        ->and($metrics['percentage'])->toBe(19.55);
});

it('usa la base del flete oficial afecto solo en la estructura con IGV', function () {
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

    $exonerated = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), false);
    $withIgv = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), true);

    expect($exonerated['freightValue'])->toBe(247.40)
        ->and($exonerated['linkedIgv'])->toBe(37.74)
        ->and(round($withIgv['freightValue'], 2))->toBe(209.66);
});

it('normaliza los costos oficiales y mantiene completos los gastos sin comprobante', function () {
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

    $figures = invokeProfitabilityMethod('linkedCostFigures', $freight, $other, true);
    $profit = invokeProfitabilityMethod('profitFigures', 10000.00, 5000.00, $figures['freightValue'], $figures['otherValue']);

    expect(round($figures['freightValue'], 2))->toBe(129.66)
        ->and($figures['otherValue'])->toBe(1150.0)
        ->and(round($figures['linkedValue'], 2))->toBe(1279.66)
        ->and($figures['freightBase'])->toBe(129.66)
        ->and($figures['freightIgv'])->toBe(23.34)
        ->and($profit['operating'])->toBe(4870.34)
        ->and($profit['incomeTax'])->toBe(1436.75)
        ->and($profit['net'])->toBe(2283.59);
});

it('mantiene recibos y gastos sin comprobante por su importe completo', function () {
    $receipt = (object) ['document_type' => 'RECIBO', 'affects_igv' => false, 'amount' => 105.00, 'total_amount' => 105.00];
    $withoutDocument = (object) ['document_type' => 'SIN_COMPROBANTE', 'affects_igv' => false, 'amount' => 1500.00, 'total_amount' => 1500.00];

    $figures = invokeProfitabilityMethod('linkedCostFigures', collect(), collect([$receipt, $withoutDocument]), true);

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

it('considera el total de 14700 cuando la OC proveedor es afecta a IGV', function () {
    $purchase = (object) [
        'taxable_base_pen' => 12457.63,
        'line_total_pen' => 14700.00,
        'order_affect_igv' => true,
    ];

    invokeProfitabilityMethod('applyConsideredPurchaseAmounts', $purchase, true);

    expect($purchase->purchase_subtotal_pen)->toBe(12457.63)
        ->and($purchase->purchase_igv_pen)->toBe(2242.37)
        ->and($purchase->purchase_total_pen)->toBe(14700.00)
        ->and($purchase->considered_purchase_amount)->toBe(14700.00)
        ->and(round($purchase->profitability_purchase_amount, 2))->toBe(12457.63);
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
        'accounting_base' => 26047.80,
        'igv' => 4688.60,
        'total' => 30736.40,
        'affected_total' => 30736.40,
        'unaffected_total' => 0,
    ];

    $amounts = invokeProfitabilityMethod('purchaseSourceAmounts', $item, $warehouseAmount, true);

    expect($amounts)->toBe([
        'subtotal' => 26047.80,
        'total' => 30736.40,
        'affected_total' => 30736.40,
        'unaffected_total' => 0.0,
        'source' => 'warehouse_entry',
    ]);
});

it('reproduce el caso 4505470202 con la compra total como costo real', function () {
    $figures = invokeProfitabilityMethod('profitFigures', 43781.50, 30736.40, 0, 0);

    expect($figures['gross'])->toBe(13045.10);
});

it('muestra el total de venta y usa su base en modo con IGV', function () {
    $order = new CustomerPurchaseOrder([
        'affect_igv' => true,
        'subtotal_taxed' => 15752.54,
        'subtotal_exonerated' => 0,
        'igv' => 2835.46,
        'grand_total' => 18588.00,
    ]);
    $items = collect([
        new CustomerPurchaseOrderItem([
            'quantity' => 1,
            'unit_price' => 18588.00,
            'subtotal' => 15752.54,
            'tax_amount' => 2835.46,
            'line_total' => 18588.00,
            'status' => 'active',
        ]),
    ]);

    $sale = invokeProfitabilityMethod('saleAmounts', $order, $items, true);

    expect($sale['base'])->toBe(15752.54)
        ->and($sale['igv'])->toBe(2835.46)
        ->and($sale['total'])->toBe(18588.00)
        ->and($sale['considered'])->toBe(18588.00)
        ->and(round($sale['profitability'], 2))->toBe(15752.54);
});

it('reproduce venta compra y flete afectos del caso esperado', function () {
    $saleForProfit = invokeProfitabilityMethod('profitabilityAmount', 18588.00, true, true);
    $purchaseForProfit = invokeProfitabilityMethod('purchaseProfitabilityValue', collect([
        (object) ['purchase_affected_total_pen' => 14951.97, 'purchase_unaffected_total_pen' => 0],
    ]), true);
    $freight = (object) [
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'igv_rate' => 18,
        'total_amount' => 27.50,
    ];
    $linked = invokeProfitabilityMethod('linkedCostFigures', collect([$freight]), collect(), true);
    $profit = invokeProfitabilityMethod(
        'profitFigures',
        $saleForProfit,
        $purchaseForProfit,
        $linked['freightValue'],
        0
    );

    expect(round($saleForProfit, 2))->toBe(15752.54)
        ->and(round($purchaseForProfit, 2))->toBe(12671.16)
        ->and($profit['gross'])->toBe(3081.38)
        ->and(round($linked['freightValue'], 2))->toBe(23.31)
        ->and($profit['operating'])->toBe(3058.08)
        ->and($profit['gross'])->not->toBe(3636.03);
});

it('mantiene importes completos cuando la OC cliente usa estructura exonerada', function () {
    $sale = invokeProfitabilityMethod('profitabilityAmount', 18588.00, true, false);
    $purchase = invokeProfitabilityMethod('purchaseProfitabilityValue', collect([
        (object) ['purchase_affected_total_pen' => 14951.97, 'purchase_unaffected_total_pen' => 0],
    ]), false);
    $freight = invokeProfitabilityMethod('profitabilityAmount', 27.50, true, false);

    expect($sale)->toBe(18588.00)
        ->and($purchase)->toBe(14951.97)
        ->and($freight)->toBe(27.50);
});

it('mantiene completos recibos internos y gastos sin comprobante', function (string $documentType) {
    $cost = (object) [
        'document_type' => $documentType,
        'affects_igv' => true,
        'igv_rate' => 18,
        'total_amount' => 118.00,
    ];

    expect(invokeProfitabilityMethod('costValueForStructure', $cost, true))->toBe(118.0);
})->with(['RECIBO_INTERNO', 'SIN_COMPROBANTE']);

it('normaliza un recibo por honorarios marcado como afecto en estructura con IGV', function () {
    $cost = (object) [
        'document_type' => 'RECIBO_HONORARIOS',
        'affects_igv' => true,
        'igv_rate' => 18,
        'total_amount' => 118.00,
    ];

    expect(invokeProfitabilityMethod('costValueForStructure', $cost, true))->toBe(100.0);
});

it('calcula IGV por pagar y total de impuestos sin descontar el IGV de la utilidad neta', function () {
    $taxes = invokeProfitabilityMethod(
        'taxFigures',
        true,
        18588.00,
        18588.00 / 1.18,
        14951.97,
        14951.97 / 1.18,
        27.50,
        27.50 / 1.18,
        902.13
    );

    expect($taxes['igvSales'])->toBe(2835.46)
        ->and($taxes['igvPurchases'])->toBe(2280.81)
        ->and($taxes['igvOfficialCosts'])->toBe(4.19)
        ->and($taxes['igvPayable'])->toBe(550.46)
        ->and($taxes['igvCreditBalance'])->toBe(0.0)
        ->and($taxes['totalTaxes'])->toBe(1452.59);
});

it('muestra un IGV negativo como crédito fiscal y no lo suma a los impuestos', function () {
    $taxes = invokeProfitabilityMethod('taxFigures', true, 1180, 1000, 2360, 2000, 0, 0, 50);

    expect($taxes['igvPayable'])->toBe(-180.0)
        ->and($taxes['igvCreditBalance'])->toBe(180.0)
        ->and($taxes['totalTaxes'])->toBe(50.0);
});

it('resuelve operaciones mixtas según la afectación de cada lado', function () {
    $affectedSale = invokeProfitabilityMethod('profitabilityAmount', 1180.00, true, true);
    $unaffectedPurchase = invokeProfitabilityMethod('purchaseProfitabilityValue', collect([
        (object) ['purchase_affected_total_pen' => 0, 'purchase_unaffected_total_pen' => 700.00],
    ]), true);
    $unaffectedSale = invokeProfitabilityMethod('profitabilityAmount', 1180.00, false, true);
    $affectedPurchase = invokeProfitabilityMethod('purchaseProfitabilityValue', collect([
        (object) ['purchase_affected_total_pen' => 708.00, 'purchase_unaffected_total_pen' => 0],
    ]), true);

    expect($affectedSale)->toBe(1000.00)
        ->and($unaffectedPurchase)->toBe(700.00)
        ->and($unaffectedSale)->toBe(1180.00)
        ->and($affectedPurchase)->toBe(600.00);
});
