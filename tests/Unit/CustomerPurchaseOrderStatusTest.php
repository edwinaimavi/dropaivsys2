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
