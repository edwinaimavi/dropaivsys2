<?php

use App\Http\Controllers\Admin\SupplierPurchaseOrderController;

function prepareSupplierOrderItemForPrecisionTest(float $quantity, string $unitPrice, bool $affectIgv = false): array
{
    $method = new ReflectionMethod(SupplierPurchaseOrderController::class, 'prepareItems');

    return $method->invoke(new SupplierPurchaseOrderController(), [[
        'article_id' => 1,
        'billing_name_snapshot' => 'ARTICULO DE PRUEBA',
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
    ]], $affectIgv)[0];
}

it('conserva precios unitarios con hasta seis decimales', function (string $price, float $quantity, float $expectedTotal) {
    $item = prepareSupplierOrderItemForPrecisionTest($quantity, $price);

    expect($item['unit_price'])->toBe((float) $price)
        ->and($item['line_total'])->toBe($expectedTotal);
})->with([
    ['22', 50, 1100.0],
    ['22.50', 50, 1125.0],
    ['1.955', 22500, 43987.5],
    ['0.833551', 7000, 5834.857],
]);

it('mantiene cuadrado el desglose de IGV con precio de alta precision', function () {
    $item = prepareSupplierOrderItemForPrecisionTest(7000, '0.833551', true);

    expect(round($item['taxable_base'] + $item['igv_amount'], 6))
        ->toBe(round($item['total_with_igv'], 6))
        ->and($item['total_with_igv'])->toBe(5834.857);
});
