<?php

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\SupplierPurchaseOrder;
use Illuminate\Support\Collection;

function supplierOrderDocument(int $id, string $code, string $description, string $status = 'ACTIVE'): Document
{
    $document = new Document([
        'status' => $status,
        'file_path' => "documents/{$id}.pdf",
    ]);
    $document->id = $id;
    $document->setRelation('documentType', new DocumentType([
        'code' => $code,
        'description' => $description,
    ]));

    return $document;
}

it('selecciona únicamente la cotización activa más reciente del proveedor', function () {
    $order = new SupplierPurchaseOrder();
    $order->setRelation('documents', new Collection([
        supplierOrderDocument(10, 'SPO_PAYMENT_SUPPORT', 'SUSTENTO DE PAGO'),
        supplierOrderDocument(11, 'COTIZACION_PROVEEDOR', 'Cotización del proveedor'),
        supplierOrderDocument(12, 'SPO_OTHER', 'OTRO'),
        supplierOrderDocument(13, 'SPO_QUOTE', 'COTIZACION DEL PROVEEDOR'),
    ]));

    expect($order->supplierQuotationDocument()?->id)->toBe(13);
});

it('normaliza tildes espacios y guiones bajos sin aceptar otros documentos', function () {
    $order = new SupplierPurchaseOrder();
    $order->setRelation('documents', new Collection([
        supplierOrderDocument(20, 'VOUCHER', 'Comprobante de pago'),
        supplierOrderDocument(21, 'LEGACY', 'Cotización del proveedor'),
        supplierOrderDocument(22, 'COTIZACION_PROVEEDOR', 'Cotización del proveedor', 'INACTIVE'),
    ]));

    expect($order->supplierQuotationDocument()?->id)->toBe(21);
});

it('devuelve null cuando la orden solo tiene sustento u otros documentos', function () {
    $order = new SupplierPurchaseOrder();
    $order->setRelation('documents', new Collection([
        supplierOrderDocument(30, 'SPO_PAYMENT_SUPPORT', 'SUSTENTO DE PAGO'),
        supplierOrderDocument(31, 'SPO_OTHER', 'OTRO'),
    ]));

    expect($order->supplierQuotationDocument())->toBeNull();
});
