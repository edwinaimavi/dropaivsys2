<?php

use App\Models\CustomerPurchaseOrder;

it('presenta los estados de rentabilidad en español sin exponer códigos internos', function (string $status, string $label) {
    expect(CustomerPurchaseOrder::statusPresentation($status)['label'])->toBe($label);
})->with([
    ['registered', 'Registrada'],
    ['in_purchase', 'Compra en proceso'],
    ['partial_purchase', 'Compra parcial'],
    ['partial_entered', 'Ingreso parcial'],
    ['partial_dispatched', 'Despacho parcial'],
    ['entered', 'Abastecida en almacén'],
    ['attended', 'Atendida / Despachada'],
    ['delivered', 'Entregada'],
    ['invoiced', 'Facturada'],
    ['cancelled', 'Anulada'],
    ['completed', 'Completada'],
    ['sent', 'Enviada'],
    ['approved', 'Aprobada'],
]);

it('presenta la ayuda funcional de los estados operativos de OC Cliente', function (string $status, string $description) {
    expect(CustomerPurchaseOrder::statusPresentation($status)['description'])->toBe($description);
})->with([
    ['registered', 'Orden registrada, pendiente de compra al proveedor.'],
    ['in_purchase', 'Ya existe una compra a proveedor vinculada, pero la mercadería aún no ha ingresado completa al almacén.'],
    ['partial_entered', 'Llegó parte de la mercadería al almacén.'],
    ['partial_dispatched', 'Parte de la mercadería ya salió del almacén; aún queda saldo por despachar.'],
    ['entered', 'Mercadería ingresada, falta atención o despacho.'],
    ['attended', 'Mercadería despachada o atención cerrada.'],
    ['delivered', 'Recepción confirmada por el cliente.'],
    ['invoiced', 'Comprobante emitido.'],
]);

it('recognizes every supported in-purchase status representation', function (string $status) {
    $order = new CustomerPurchaseOrder(['status' => $status]);

    expect($order->isInPurchase())->toBeTrue();
})->with([
    'technical status' => 'in_purchase',
    'Spanish technical status' => 'en_compra',
    'Spanish label' => 'En compra',
    'uppercase Spanish technical status' => 'EN_COMPRA',
]);

it('does not classify an available order as in purchase', function () {
    $order = new CustomerPurchaseOrder(['status' => 'registered']);

    expect($order->isInPurchase())->toBeFalse();
});

it('keeps an order registered when it has neither supplier purchase nor warehouse entries', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 10],
        [],
        []
    ))->toBe(CustomerPurchaseOrder::STATUS_REGISTERED);
});

it('marks the customer order as in purchase when at least one supplier order item exists', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 50, 2 => 60, 3 => 3],
        [1 => 50, 2 => 60, 3 => 0],
        []
    ))->toBe(CustomerPurchaseOrder::STATUS_IN_PURCHASE);
});

it('marks the customer order as in purchase only when every item was sent completely', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 50, 2 => 60, 3 => 3],
        [1 => 50, 2 => 60, 3 => 3],
        []
    ))->toBe(CustomerPurchaseOrder::STATUS_IN_PURCHASE);
});

it('remains in purchase while an active supplier quantity exists', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 50, 2 => 3],
        [1 => 50],
        []
    ))->toBe(CustomerPurchaseOrder::STATUS_IN_PURCHASE);
});

it('prioritizes warehouse entry progress over purchase progress', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 50, 2 => 3],
        [1 => 50, 2 => 3],
        [1 => 20, 2 => 0]
    ))->toBe('partial_entered')
        ->and(CustomerPurchaseOrder::supplyStatusFromQuantities(
            [1 => 50, 2 => 3],
            [1 => 50, 2 => 3],
            [1 => 50, 2 => 3]
        ))->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('only marks a fully supplied order as attended when it has a closure document', function () {
    $requested = [1 => 10, 2 => 5];
    $purchased = [1 => 10, 2 => 5];
    $entered = [1 => 10, 2 => 5];

    expect(CustomerPurchaseOrder::supplyStatusFromQuantities($requested, $purchased, $entered))
        ->toBe(CustomerPurchaseOrder::STATUS_ENTERED)
        ->and(CustomerPurchaseOrder::supplyStatusFromQuantities($requested, $purchased, $entered, true))
        ->toBe(CustomerPurchaseOrder::STATUS_ATTENDED);
});

it('distingue despacho parcial y despacho completo sin usar facturación', function () {
    $requested = [1 => 10, 2 => 5];
    $purchased = [1 => 10, 2 => 5];
    $entered = [1 => 10, 2 => 5];

    expect(CustomerPurchaseOrder::supplyStatusFromQuantities($requested, $purchased, $entered, false, true, [1 => 4]))
        ->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_DISPATCHED)
        ->and(CustomerPurchaseOrder::supplyStatusFromQuantities($requested, $purchased, $entered, false, true, [1 => 10, 2 => 5]))
        ->toBe(CustomerPurchaseOrder::STATUS_ATTENDED);
});

it('compares supplied quantities with four decimal precision', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 10.0000],
        [1 => 10.0000],
        [1 => 9.99996]
    ))->toBe(CustomerPurchaseOrder::STATUS_ENTERED)
        ->and(CustomerPurchaseOrder::supplyStatusFromQuantities(
            [1 => 10.0000],
            [1 => 10.0000],
            [1 => 9.99994]
        ))->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_ENTERED);
});
