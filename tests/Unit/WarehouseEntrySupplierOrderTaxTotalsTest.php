<?php

use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Services\WarehouseEntryTaxTotalsService;

function supplierOrderForWarehouseTaxTest(bool $affectIgv = true): SupplierPurchaseOrder
{
    $order = new SupplierPurchaseOrder([
        'affect_igv' => $affectIgv,
        'subtotal' => $affectIgv ? '84.75' : '100.00',
        'igv' => $affectIgv ? '15.25' : '0.00',
        'grand_total' => '100.00',
    ]);
    $order->setAttribute('id', 4);

    $items = collect([
        ['id' => 41, 'quantity' => '10.00', 'unit_price' => '5.000000'],
        ['id' => 42, 'quantity' => '20.00', 'unit_price' => '2.500000'],
    ])->map(function (array $data) use ($affectIgv, $order) {
        $total = '50.000000';
        $base = $affectIgv ? '42.372881' : $total;
        $igv = $affectIgv ? '7.627119' : '0.000000';
        $item = new SupplierPurchaseOrderItem($data + [
            'subtotal' => $base,
            'tax_amount' => $igv,
            'line_total' => $total,
            'total_with_igv' => $total,
            'taxable_base' => $base,
            'igv_percent' => $affectIgv ? '18.00' : '0.00',
            'igv_amount' => $igv,
            'status' => 'active',
        ]);
        $item->setAttribute('id', $data['id']);
        $item->setRelation('purchaseOrder', $order);

        return $item;
    });

    $order->setRelation('items', $items);

    return $order;
}

function warehouseTaxReceivedItems(float $firstQuantity, float $secondQuantity): array
{
    return [
        [
            'supplier_purchase_order_item_id' => 41,
            'article_id' => 1,
            'quantity' => number_format($firstQuantity, 2, '.', ''),
            'unit_price' => '5.000000',
        ],
        [
            'supplier_purchase_order_item_id' => 42,
            'article_id' => 2,
            'quantity' => number_format($secondQuantity, 2, '.', ''),
            'unit_price' => '2.500000',
        ],
    ];
}

it('A y B conserva exactamente 84.75 15.25 y 100 en una recepcion completa', function () {
    $result = app(WarehouseEntryTaxTotalsService::class)->calculate(
        supplierOrderForWarehouseTaxTest(),
        warehouseTaxReceivedItems(10, 20)
    );

    expect($result['totals'])->toBe([
        'subtotal' => '84.75',
        'igv' => '15.25',
        'grand_total' => '100.00',
    ])->and($result['totals'])->not->toBe([
        'subtotal' => '84.74',
        'igv' => '15.26',
        'grand_total' => '100.00',
    ])->and(collect($result['items'])->sum(fn ($item) => (float) $item['subtotal']))->toBe(84.75)
        ->and(collect($result['items'])->sum(fn ($item) => (float) $item['tax_amount']))->toBe(15.25);
});

it('C conserva base igual al total e IGV cero en una OC no afecta', function () {
    $result = app(WarehouseEntryTaxTotalsService::class)->calculate(
        supplierOrderForWarehouseTaxTest(false),
        warehouseTaxReceivedItems(10, 20)
    );

    expect($result['totals'])->toBe([
        'subtotal' => '100.00',
        'igv' => '0.00',
        'grand_total' => '100.00',
    ]);
});

it('D prorratea una recepcion parcial sin copiar la cabecera completa', function () {
    $result = app(WarehouseEntryTaxTotalsService::class)->calculate(
        supplierOrderForWarehouseTaxTest(),
        warehouseTaxReceivedItems(5, 10)
    );

    expect($result['totals'])->toBe([
        'subtotal' => '42.37',
        'igv' => '7.63',
        'grand_total' => '50.00',
    ])->and($result['totals']['grand_total'])->not->toBe('100.00');
});

it('E y F limita parciales y cierra el residual tributario en la recepcion final', function () {
    $service = app(WarehouseEntryTaxTotalsService::class);
    $order = supplierOrderForWarehouseTaxTest();

    $first = $service->calculate($order, warehouseTaxReceivedItems(10, 0));
    $second = $service->calculate(
        $order,
        warehouseTaxReceivedItems(0, 10),
        [41 => '10.00'],
        $first['totals']
    );
    $previous = [
        'subtotal' => bcadd($first['totals']['subtotal'], $second['totals']['subtotal'], 2),
        'igv' => bcadd($first['totals']['igv'], $second['totals']['igv'], 2),
        'grand_total' => bcadd($first['totals']['grand_total'], $second['totals']['grand_total'], 2),
    ];
    $final = $service->calculate(
        $order,
        warehouseTaxReceivedItems(0, 10),
        [41 => '10.00', 42 => '10.00'],
        $previous
    );

    expect((float) $previous['subtotal'])->toBeLessThanOrEqual(84.75)
        ->and((float) $previous['igv'])->toBeLessThanOrEqual(15.25)
        ->and((float) $previous['grand_total'])->toBeLessThanOrEqual(100.00)
        ->and([
            'subtotal' => bcadd($previous['subtotal'], $final['totals']['subtotal'], 2),
            'igv' => bcadd($previous['igv'], $final['totals']['igv'], 2),
            'grand_total' => bcadd($previous['grand_total'], $final['totals']['grand_total'], 2),
        ])->toBe([
            'subtotal' => '84.75',
            'igv' => '15.25',
            'grand_total' => '100.00',
        ])
        ->and($final['totals'])->toBe([
            'subtotal' => '21.19',
            'igv' => '3.81',
            'grand_total' => '25.00',
        ]);
});

it('G calcula tributos sin mutar cantidades precios ni totales de articulo', function () {
    $received = warehouseTaxReceivedItems(10, 20);
    $original = $received;

    $result = app(WarehouseEntryTaxTotalsService::class)->calculate(
        supplierOrderForWarehouseTaxTest(),
        $received
    );

    expect($received)->toBe($original)
        ->and($received[0]['quantity'])->toBe('10.00')
        ->and($received[0]['unit_price'])->toBe('5.000000')
        ->and($received[1]['quantity'])->toBe('20.00')
        ->and($received[1]['unit_price'])->toBe('2.500000')
        ->and(collect($result['items'])->sum(fn ($item) => (float) $item['subtotal'] + (float) $item['tax_amount']))->toBe(100.0);
});

it('el frontend prioriza el contexto tributario canonico antes de pintar los totales', function () {
    $javascript = file_get_contents(dirname(__DIR__, 2).'/resources/js/pages/warehouse-entry.js');

    expect($javascript)
        ->toContain('warehouseEntrySourceTaxContext = response.tax_totals')
        ->toContain("row.data('source-tax'")
        ->toContain('function warehouseEntryCanonicalSourceTotals()')
        ->toContain('const canonicalSourceTotals = warehouseEntryCanonicalSourceTotals();')
        ->toContain('subtotal = canonicalSourceTotals.subtotal;')
        ->toContain('igv = canonicalSourceTotals.igv;')
        ->toContain('total = canonicalSourceTotals.total;');
});
