<?php

use App\Models\SupplierPurchaseOrder;

it('keeps the three official delivery types', function (string $deliveryType) {
    expect(SupplierPurchaseOrder::normalizeDeliveryType($deliveryType))->toBe($deliveryType);
})->with(SupplierPurchaseOrder::DELIVERY_TYPES);

it('maps legacy agency delivery types to agency', function (string $legacyDeliveryType) {
    expect(SupplierPurchaseOrder::normalizeDeliveryType($legacyDeliveryType))
        ->toBe(SupplierPurchaseOrder::DELIVERY_TYPE_AGENCY);
})->with([
    'Agencia de transporte',
    'agencia_transporte',
    'En agencia',
    'Transporte',
]);

it('normalizes readable official aliases', function () {
    expect(SupplierPurchaseOrder::normalizeDeliveryType('Recojo de almacén'))
        ->toBe(SupplierPurchaseOrder::DELIVERY_TYPE_WAREHOUSE_PICKUP)
        ->and(SupplierPurchaseOrder::normalizeDeliveryType('Transportista del proveedor'))
        ->toBe(SupplierPurchaseOrder::DELIVERY_TYPE_SUPPLIER_CARRIER);
});
