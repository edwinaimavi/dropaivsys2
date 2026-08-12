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

it('usa el total registrado en modo sin IGV y la base en modo con IGV', function () {
    $cost = (object) [
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'igv_rate' => 18,
        'amount' => 128.00,
        'total_amount' => 128.00,
        'taxable_amount' => 108.47,
        'igv_amount' => 19.53,
    ];

    $withoutIgv = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), 'without_igv');
    $withIgv = invokeProfitabilityMethod('linkedCostFigures', collect([$cost]), collect(), 'with_igv');

    expect($withoutIgv['linkedValue'])->toBe(128.0)
        ->and($withIgv['linkedValue'])->toBe(108.47)
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

it('usa la base del flete oficial afecto solo en modo con IGV', function () {
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
        ->and($withIgv['freightValue'])->toBe(209.66);
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

it('considera el total de 14700 cuando la OC proveedor es afecta a IGV', function () {
    $purchase = (object) [
        'taxable_base_pen' => 12457.63,
        'line_total_pen' => 14700.00,
        'order_affect_igv' => true,
    ];

    invokeProfitabilityMethod('applyConsideredPurchaseAmounts', $purchase, 'with_igv');

    expect($purchase->purchase_subtotal_pen)->toBe(12457.63)
        ->and($purchase->purchase_igv_pen)->toBe(2242.37)
        ->and($purchase->purchase_total_pen)->toBe(14700.00)
        ->and($purchase->considered_purchase_amount)->toBe(14700.00)
        ->and($purchase->profitability_purchase_amount)->toBe(12457.63);
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

    $sale = invokeProfitabilityMethod('saleAmounts', $order, $items, 'with_igv');

    expect($sale['base'])->toBe(15752.54)
        ->and($sale['igv'])->toBe(2835.46)
        ->and($sale['total'])->toBe(18588.00)
        ->and($sale['considered'])->toBe(18588.00)
        ->and($sale['profitability'])->toBe(15752.54);
});

it('reproduce venta compra y flete afectos del caso esperado', function () {
    $saleForProfit = invokeProfitabilityMethod('profitabilityAmount', 18588.00, true, 'with_igv');
    $purchaseForProfit = invokeProfitabilityMethod('purchaseProfitabilityValue', collect([
        (object) ['purchase_affected_total_pen' => 14952.00, 'purchase_unaffected_total_pen' => 0],
    ]), 'with_igv');
    $freight = (object) [
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'igv_rate' => 18,
        'total_amount' => 27.50,
    ];
    $linked = invokeProfitabilityMethod('linkedCostFigures', collect([$freight]), collect(), 'with_igv');
    $profit = invokeProfitabilityMethod(
        'profitFigures',
        $saleForProfit,
        $purchaseForProfit,
        $linked['freightValue'],
        0
    );

    expect($saleForProfit)->toBe(15752.54)
        ->and($purchaseForProfit)->toBe(12671.19)
        ->and($profit['gross'])->toBe(3081.35)
        ->and($linked['freightValue'])->toBe(23.31)
        ->and($profit['operating'])->toBe(3058.04);
});

it('mantiene venta compra y costos completos en modo sin IGV', function () {
    $sale = invokeProfitabilityMethod('profitabilityAmount', 18588.00, true, 'without_igv');
    $purchase = invokeProfitabilityMethod('purchaseProfitabilityValue', collect([
        (object) ['purchase_affected_total_pen' => 14952.00, 'purchase_unaffected_total_pen' => 0],
    ]), 'without_igv');
    $freight = invokeProfitabilityMethod('profitabilityAmount', 27.50, true, 'without_igv');

    expect($sale)->toBe(18588.00)
        ->and($purchase)->toBe(14952.00)
        ->and($freight)->toBe(27.50);
});

it('mantiene completos recibos internos y gastos sin comprobante', function (string $documentType) {
    $cost = (object) [
        'document_type' => $documentType,
        'affects_igv' => true,
        'igv_rate' => 18,
        'total_amount' => 118.00,
    ];

    expect(invokeProfitabilityMethod('costValueForMode', $cost, 'with_igv'))->toBe(118.0);
})->with(['RECIBO_INTERNO', 'SIN_COMPROBANTE']);

it('resuelve operaciones mixtas según la afectación de cada lado', function () {
    $affectedSale = invokeProfitabilityMethod('profitabilityAmount', 1180.00, true, 'with_igv');
    $unaffectedPurchase = invokeProfitabilityMethod('purchaseProfitabilityValue', collect([
        (object) ['purchase_affected_total_pen' => 0, 'purchase_unaffected_total_pen' => 700.00],
    ]), 'with_igv');
    $unaffectedSale = invokeProfitabilityMethod('profitabilityAmount', 1180.00, false, 'with_igv');
    $affectedPurchase = invokeProfitabilityMethod('purchaseProfitabilityValue', collect([
        (object) ['purchase_affected_total_pen' => 708.00, 'purchase_unaffected_total_pen' => 0],
    ]), 'with_igv');

    expect($affectedSale)->toBe(1000.00)
        ->and($unaffectedPurchase)->toBe(700.00)
        ->and($unaffectedSale)->toBe(1180.00)
        ->and($affectedPurchase)->toBe(600.00);
});
