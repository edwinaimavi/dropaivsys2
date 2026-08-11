<?php

use App\Services\CustomerOrderProfitabilityService;

function profitabilitySupplierItem(float $subtotal, float $total): object
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
        'considered_purchase_amount' => $subtotal,
        'order_status' => 'registered',
    ];
}

it('muestra como principal el subtotal sin IGV y conserva el desglose auditable', function () {
    $items = collect([
        profitabilitySupplierItem(22000.00, 25960.00),
        profitabilitySupplierItem(4047.80, 4776.40),
    ]);
    $html = view('admin.customer-order-profitability.partials.supplier-purchases', [
        'mode' => CustomerOrderProfitabilityService::MODE_WITHOUT_IGV,
        'supplierItems' => $items,
        'supplierOrderIds' => collect([1]),
        'purchaseValue' => 26047.80,
        'money' => fn ($value) => number_format((float) $value, 2),
    ])->render();

    expect($html)->toContain('Total sin IGV en soles')
        ->toContain('Compra considerada')
        ->toContain('S/ 26,047.80')
        ->toContain('S/ 22,000.00')
        ->toContain('S/ 4,047.80')
        ->toContain('Subtotal')
        ->toContain('IGV')
        ->toContain('Total')
        ->not->toContain('Total con IGV en soles');
});

it('muestra como principal el total con IGV cuando ese modo está seleccionado', function () {
    $items = collect([
        profitabilitySupplierItem(22000.00, 25960.00),
        profitabilitySupplierItem(4047.80, 4776.40),
    ])->each(fn ($item) => $item->considered_purchase_amount = $item->purchase_total_pen);
    $html = view('admin.customer-order-profitability.partials.supplier-purchases', [
        'mode' => CustomerOrderProfitabilityService::MODE_WITH_IGV,
        'supplierItems' => $items,
        'supplierOrderIds' => collect([1]),
        'purchaseValue' => 30736.40,
        'money' => fn ($value) => number_format((float) $value, 2),
    ])->render();

    expect($html)->toContain('Total con IGV en soles')
        ->toContain('S/ 30,736.40')
        ->toContain('S/ 25,960.00')
        ->toContain('S/ 4,776.40')
        ->not->toContain('Total sin IGV en soles');
});
