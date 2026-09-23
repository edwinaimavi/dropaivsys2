<?php

use App\Http\Controllers\Admin\CustomerPurchaseOrderController;
use App\Http\Controllers\Admin\QuoteController;
use App\Models\CustomerPurchaseOrder;
use App\Services\ArticleSalesTaxPolicy;
use App\Services\InvoiceFromCustomerOrderService;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

it('acepta solo las afectaciones de venta 10 20 y 30', function () {
    $policy = app(ArticleSalesTaxPolicy::class);

    expect($policy->validate('10'))->toBe('10')
        ->and($policy->validate('20'))->toBe('20')
        ->and($policy->validate('30'))->toBe('30')
        ->and($policy->isTaxable('10'))->toBeTrue()
        ->and($policy->isTaxable('20'))->toBeFalse()
        ->and($policy->isTaxable('30'))->toBeFalse();

    expect(fn () => $policy->validate('99'))->toThrow(ValidationException::class);
});

it('calcula una cotización mixta sin confundir exonerado e inafecto', function () {
    $method = new ReflectionMethod(QuoteController::class, 'calculateTotals');
    $totals = $method->invoke(new QuoteController(), [
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

it('calcula una orden mixta conservando 10 20 y 30 por línea', function () {
    $method = new ReflectionMethod(CustomerPurchaseOrderController::class, 'calculateTotals');
    $totals = $method->invoke(new CustomerPurchaseOrderController(), [
        ['line_total' => '118', 'subtotal' => '100', 'tax_amount' => '18', 'tax_affectation_code' => '10'],
        ['line_total' => '50', 'subtotal' => '50', 'tax_amount' => '0', 'tax_affectation_code' => '20'],
        ['line_total' => '30', 'subtotal' => '30', 'tax_amount' => '0', 'tax_affectation_code' => '30'],
    ]);

    expect((float) $totals['subtotal_taxed'])->toBe(100.0)
        ->and((float) $totals['subtotal_exonerated'])->toBe(50.0)
        ->and((float) $totals['subtotal_unaffected'])->toBe(30.0)
        ->and((float) $totals['igv'])->toBe(18.0)
        ->and((float) $totals['grand_total'])->toBe(198.0);
});

it('la factura desde orden conserva el snapshot y solo usa fallback legacy cuando falta', function () {
    $service = app(InvoiceFromCustomerOrderService::class);
    $method = new ReflectionMethod(InvoiceFromCustomerOrderService::class, 'orderItemTaxAffectation');
    $order = new CustomerPurchaseOrder(['affect_igv' => true]);

    expect($method->invoke($service, (object) ['tax_affectation_code' => '30'], $order))->toBe('30');

    $order->affect_igv = false;
    expect($method->invoke($service, (object) ['tax_affectation_code' => null], $order))->toBe('20');

    $order->affect_igv = true;
    expect($method->invoke($service, (object) ['tax_affectation_code' => null], $order))->toBe('10');
});
