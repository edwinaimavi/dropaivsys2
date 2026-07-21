<?php

use App\Http\Controllers\Admin\QuoteController;

function calculateQuoteTotalsForTest(array $items, bool $affectIgv): array
{
    $method = new ReflectionMethod(QuoteController::class, 'calculateTotals');

    return $method->invoke(new QuoteController(), $items, $affectIgv);
}

it('desglosa el IGV incluido sin incrementar el total de venta', function () {
    $totals = calculateQuoteTotalsForTest([
        ['quantity' => 10, 'unit_price' => 40, 'discount_percentage' => 0],
        ['quantity' => 20, 'unit_price' => 70, 'discount_percentage' => 0],
    ], true);

    expect($totals)->toBe([
        'subtotal_exonerated' => 0,
        'subtotal_taxed' => 1525.42,
        'igv' => 274.58,
        'grand_total' => 1800.0,
    ]);
});

it('mantiene el total como venta exonerada cuando no afecta IGV', function () {
    $totals = calculateQuoteTotalsForTest([
        ['quantity' => 10, 'unit_price' => 40, 'discount_percentage' => 0],
        ['quantity' => 20, 'unit_price' => 70, 'discount_percentage' => 0],
    ], false);

    expect($totals)->toBe([
        'subtotal_exonerated' => 1800.0,
        'subtotal_taxed' => 0,
        'igv' => 0,
        'grand_total' => 1800.0,
    ]);
});

it('aplica el descuento antes de desglosar el IGV', function () {
    $totals = calculateQuoteTotalsForTest([
        ['quantity' => 10, 'unit_price' => 100, 'discount_percentage' => 10],
    ], true);

    expect($totals)->toBe([
        'subtotal_exonerated' => 0,
        'subtotal_taxed' => 762.71,
        'igv' => 137.29,
        'grand_total' => 900.0,
    ]);
});
