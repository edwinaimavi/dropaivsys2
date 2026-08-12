<?php

function profitabilitySupplierItem(float $subtotal, float $total, bool $affectIgv = true): object
{
    return (object) [
        'order_code' => 'OCP-TEST',
        'supplier_name' => 'PROVEEDOR DE PRUEBA',
        'billing_name_snapshot' => 'ARTÍCULO DE PRUEBA',
        'quantity' => 1,
        'purchase_currency_code' => 'PEN',
        'purchase_currency_symbol' => 'S/',
        'payment_currency_code' => 'PEN',
        'line_total' => $total,
        'pen_conversion_factor' => 1,
        'purchase_subtotal_pen' => $subtotal,
        'purchase_igv_pen' => round($total - $subtotal, 2),
        'purchase_total_pen' => $total,
        'considered_purchase_amount' => $affectIgv ? $total : $subtotal,
        'purchase_affected_total_pen' => $affectIgv ? $total : 0,
        'purchase_unaffected_total_pen' => $affectIgv ? 0 : $total,
        'profitability_purchase_amount' => $affectIgv ? $subtotal : $total,
        'purchase_amount_source' => 'warehouse_entry',
        'order_status' => 'registered',
    ];
}

it('muestra la base de la compra afecta como importe usado en utilidad', function () {
    $items = collect([
        profitabilitySupplierItem(22000.00, 25960.00),
        profitabilitySupplierItem(4047.80, 4776.40),
    ]);
    $html = view('admin.customer-order-profitability.partials.supplier-purchases', [
        'supplierItems' => $items,
        'supplierOrderIds' => collect([1]),
        'purchaseValue' => 30736.40,
        'purchaseProfitValue' => 26047.80,
        'money' => fn ($value) => number_format((float) $value, 2),
    ])->render();

    expect($html)->toContain('Compra para utilidad')
        ->toContain('S/ 26,047.80')
        ->toContain('Subtotal')
        ->toContain('IGV')
        ->toContain('Total compra')
        ->toContain('S/ 25,960.00')
        ->toContain('S/ 4,776.40')
        ->toContain('Base sin IGV')
        ->toContain('Según Almacén')
        ->not->toContain('Total sin IGV en soles');
});

it('muestra completo el importe usado de una compra no afecta', function () {
    $items = collect([
        profitabilitySupplierItem(1200.00, 1200.00, false),
    ]);
    $html = view('admin.customer-order-profitability.partials.supplier-purchases', [
        'supplierItems' => $items,
        'supplierOrderIds' => collect([1]),
        'purchaseValue' => 1200.00,
        'purchaseProfitValue' => 1200.00,
        'money' => fn ($value) => number_format((float) $value, 2),
    ])->render();

    expect($html)->toContain('Compra para utilidad')
        ->toContain('S/ 1,200.00')
        ->toContain('S/ 0.00')
        ->toContain('Importe no afecto')
        ->not->toContain('modo de cálculo seleccionado');
});
