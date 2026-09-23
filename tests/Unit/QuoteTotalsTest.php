<?php

use App\Http\Controllers\Admin\QuoteController;

function calculateQuoteTotalsForTest(array $items): array
{
    $method = new ReflectionMethod(QuoteController::class, 'calculateTotals');

    return $method->invoke(new QuoteController(), $items);
}

it('desglosa el IGV incluido de las líneas gravadas sin incrementar el total de venta', function () {
    $totals = calculateQuoteTotalsForTest([
        ['quantity' => 10, 'unit_price' => 40, 'discount_percentage' => 0, 'tax_affectation_code' => '10'],
        ['quantity' => 20, 'unit_price' => 70, 'discount_percentage' => 0, 'tax_affectation_code' => '10'],
    ]);

    expect($totals)->toBe([
        'subtotal_exonerated' => 0.0,
        'subtotal_unaffected' => 0.0,
        'subtotal_taxed' => 1525.42,
        'igv' => 274.58,
        'grand_total' => 1800.0,
    ]);
});

it('separa ventas exoneradas e inafectas por línea', function () {
    $totals = calculateQuoteTotalsForTest([
        ['quantity' => 2, 'unit_price' => 100, 'discount_percentage' => 0, 'tax_affectation_code' => '20'],
        ['quantity' => 3, 'unit_price' => 50, 'discount_percentage' => 0, 'tax_affectation_code' => '30'],
    ]);

    expect($totals)->toBe([
        'subtotal_exonerated' => 200.0,
        'subtotal_unaffected' => 150.0,
        'subtotal_taxed' => 0.0,
        'igv' => 0.0,
        'grand_total' => 350.0,
    ]);
});

it('calcula correctamente una cotización mixta 10 20 y 30', function () {
    $totals = calculateQuoteTotalsForTest([
        ['quantity' => 1, 'unit_price' => 118, 'discount_percentage' => 0, 'tax_affectation_code' => '10'],
        ['quantity' => 1, 'unit_price' => 50, 'discount_percentage' => 0, 'tax_affectation_code' => '20'],
        ['quantity' => 1, 'unit_price' => 30, 'discount_percentage' => 0, 'tax_affectation_code' => '30'],
    ]);

    expect($totals)->toBe([
        'subtotal_exonerated' => 50.0,
        'subtotal_unaffected' => 30.0,
        'subtotal_taxed' => 100.0,
        'igv' => 18.0,
        'grand_total' => 198.0,
    ]);
});

it('aplica el descuento antes de desglosar el IGV', function () {
    $totals = calculateQuoteTotalsForTest([
        ['quantity' => 10, 'unit_price' => 100, 'discount_percentage' => 10, 'tax_affectation_code' => '10'],
    ]);

    expect($totals)->toBe([
        'subtotal_exonerated' => 0.0,
        'subtotal_unaffected' => 0.0,
        'subtotal_taxed' => 762.71,
        'igv' => 137.29,
        'grand_total' => 900.0,
    ]);
});
