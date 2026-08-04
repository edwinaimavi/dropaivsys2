<?php

use App\Http\Controllers\Admin\WarehouseEntryController;
use App\Models\SupplierPurchaseOrderItem;

function prepareWarehouseEntryItemsForTest(array $items, bool $affectIgv): array
{
    $method = new ReflectionMethod(WarehouseEntryController::class, 'prepareItems');

    return $method->invoke(new WarehouseEntryController(), $items, $affectIgv);
}

function calculateWarehouseEntryTotalsForTest(array $items): array
{
    $method = new ReflectionMethod(WarehouseEntryController::class, 'calculateTotals');

    return $method->invoke(new WarehouseEntryController(), $items);
}

function warehouseEntryItemForTest(float $quantity, float $unitPrice): array
{
    return [
        'article_id' => 1,
        'billing_name_snapshot' => 'ARTICULO DE PRUEBA',
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
    ];
}

it('desglosa el IGV incluido sin incrementar el total del ingreso', function () {
    $items = prepareWarehouseEntryItemsForTest([
        warehouseEntryItemForTest(1, 14372.40),
    ], true);

    expect($items[0])->toMatchArray([
        'subtotal' => 12180.00,
        'tax_amount' => 2192.40,
        'line_total' => 14372.40,
    ])->and(calculateWarehouseEntryTotalsForTest($items))->toBe([
        'subtotal' => 12180.00,
        'igv' => 2192.40,
        'grand_total' => 14372.40,
    ]);
});

it('mantiene el total y no genera base ni IGV cuando no esta afecto', function () {
    $items = prepareWarehouseEntryItemsForTest([
        warehouseEntryItemForTest(1, 14372.40),
    ], false);

    expect($items[0])->toMatchArray([
        'subtotal' => 0,
        'tax_amount' => 0,
        'line_total' => 14372.40,
    ])->and(calculateWarehouseEntryTotalsForTest($items))->toBe([
        'subtotal' => 0.0,
        'igv' => 0.0,
        'grand_total' => 14372.40,
    ]);
});

it('suma los totales finales de varias lineas sin volver a agregar IGV', function () {
    $items = prepareWarehouseEntryItemsForTest([
        warehouseEntryItemForTest(10, 100),
        warehouseEntryItemForTest(2, 50),
    ], true);

    expect(calculateWarehouseEntryTotalsForTest($items))->toBe([
        'subtotal' => 932.21,
        'igv' => 167.79,
        'grand_total' => 1100.0,
    ]);
});

it('conserva seis decimales del precio al cargar una orden de proveedor', function () {
    $item = new SupplierPurchaseOrderItem([
        'quantity' => 7000,
        'unit_price' => '0.833551',
    ]);
    $item->setRelation('article', null);
    $method = new ReflectionMethod(WarehouseEntryController::class, 'sourceItemPayload');
    $payload = $method->invoke(new WarehouseEntryController(), $item, 7000, 7000, false);

    expect($payload['unit_price'])->toBe(0.833551)
        ->and($payload['line_total'])->toBe(5834.86);
});
