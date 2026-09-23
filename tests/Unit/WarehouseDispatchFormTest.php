<?php

it('abre el modal sin saldos ni cantidades habilitadas hasta seleccionar almacén', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/customer-purchase-orders/partials/dispatchModal.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');

    expect($blade)
        ->toContain('Seleccione un almacén para consultar saldos disponibles')
        ->toContain('id="btnSaveWarehouseDispatch" disabled')
        ->toContain('id="warehouseDispatchRequestedTotal"')
        ->toContain('id="warehouseDispatchHistorySummary"')
        ->and($javascript)
        ->toContain('const stocks = warehouseId')
        ->toContain(': [];')
        ->toContain("? 'Seleccione un almacén para ver lotes disponibles'")
        ->toContain('class="form-control form-control-sm dispatch-quantity"')
        ->toContain('${selectedStock && limit > 0 ? \'\' : \'disabled\'}');
});

it('reinicia y consulta los saldos al cambiar de almacén', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');

    expect($javascript)
        ->toContain("$(document).on('change', '#warehouse_dispatch_warehouse_id'")
        ->toContain('resetWarehouseDispatchStocks();')
        ->toContain('loadWarehouseDispatchStocks(warehouseId);')
        ->toContain('/dispatch-stocks`')
        ->toContain('item.stocks = [];')
        ->toContain('item.assignments = [];')
        ->toContain('Sin stock en este almacén');
});

it('mantiene bloqueado el registro ante combinaciones inválidas', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');

    expect($javascript)
        ->toContain('function warehouseDispatchValidation()')
        ->toContain('Seleccione un almacén para registrar la salida.')
        ->toContain('Seleccione un saldo o lote para cada cantidad a despachar.')
        ->toContain('La suma de lotes supera el pendiente de despacho.')
        ->toContain('La cantidad a despachar supera el stock disponible del lote seleccionado.')
        ->toContain("$('#btnSaveWarehouseDispatch').prop('disabled', warehouseDispatchSubmitting || !canDispatch || !validation.valid)");
});

it('permite multilote evita stocks repetidos y recalcula asignado y pendiente', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/customer-purchase-orders/partials/dispatchModal.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');

    expect($blade)
        ->toContain('Asignaciones de stock y lotes')
        ->toContain('warehouse-dispatch-assignment-row')
        ->toContain('warehouse-dispatch-items-list{')
        ->toContain('overflow-y:auto')
        ->and($javascript)
        ->toContain("$(document).on('click', '.add-dispatch-assignment'")
        ->toContain("$(document).on('click', '.remove-dispatch-assignment'")
        ->toContain('selectedStockIds.has(String(stock.id))')
        ->toContain('El lote/stock seleccionado está repetido para este artículo.')
        ->toContain('warehouseDispatchAssignedQuantity(item)')
        ->toContain('dispatch-assigned-total')
        ->toContain('dispatch-assignment-pending');
});

it('envia la cabecera preparada con destino e idempotencia y muestra estados canonicos', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/customer-purchase-orders/partials/dispatchModal.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');

    expect($blade)
        ->toContain('id="warehouse_dispatch_idempotency_key" name="idempotency_key"')
        ->toContain('id="warehouse_dispatch_destination" name="destination"')
        ->and($javascript)
        ->toContain("$('#warehouse_dispatch_idempotency_key').val(response.data.idempotency_key || '')")
        ->toContain("dispatch.status === 'confirmed'")
        ->toContain('CONFIRMADA')
        ->toContain('BORRADOR');
});

it('rehidrata datetime-local con la fecha local enviada por el backend', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');

    expect($javascript)
        ->toContain("$('#warehouse_dispatch_date').val(editingDispatch.dispatch_date_local || '')")
        ->not->toContain("String(editingDispatch.dispatch_date || '').replace(' ', 'T').slice(0, 16)");
});

it('gestiona múltiples documentos en creación e historial sin mezclar efectos físicos', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/customer-purchase-orders/partials/dispatchModal.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');

    expect($blade)
        ->toContain('Documentos del despacho')
        ->toContain('id="warehouseDispatchCreateDocuments"')
        ->toContain('id="warehouseDispatchDocumentsModal"')
        ->toContain('+ Agregar documento')
        ->and($javascript)
        ->toContain('function addWarehouseDispatchDocumentRow(target)')
        ->toContain('name="documents[${index}][file]"')
        ->toContain('manageWarehouseDispatchDocuments')
        ->toContain('Documentos (${documentCount})')
        ->toContain('function saveWarehouseDispatchDocuments()')
        ->toContain('function deleteWarehouseDispatchDocument(documentId)');
});
