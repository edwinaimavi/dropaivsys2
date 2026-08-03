<?php

use App\Models\CustomerPurchaseOrder;

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

it('marks the customer order as partial purchase while at least one item remains pending', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 50, 2 => 60, 3 => 3],
        [1 => 50, 2 => 60, 3 => 0],
        []
    ))->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_PURCHASE);
});

it('marks the customer order as in purchase only when every item was sent completely', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 50, 2 => 60, 3 => 3],
        [1 => 50, 2 => 60, 3 => 3],
        []
    ))->toBe(CustomerPurchaseOrder::STATUS_IN_PURCHASE);
});

it('returns to partial purchase when an active supplier quantity is removed', function () {
    expect(CustomerPurchaseOrder::supplyStatusFromQuantities(
        [1 => 50, 2 => 3],
        [1 => 50],
        []
    ))->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_PURCHASE);
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
