<?php

it('informa que se pueden vincular gastos de cajas abiertas o cerradas no anuladas', function () {
    $warehouseScript = file_get_contents(resource_path('js/pages/warehouse-entry.js'));
    $pettyCashScript = file_get_contents(resource_path('js/pages/petty-cash.js'));
    $closeModal = file_get_contents(resource_path('views/admin/petty-cash/partials/closeModal.blade.php'));

    expect($warehouseScript)
        ->toContain('No hay gastos aprobados disponibles en cajas no anuladas para vincular.')
        ->toContain("$('#wePettyCashExchangeStatus').val('all')")
        ->toContain('Con comprobante oficial')
        ->toContain('Otros gastos / sin comprobante')
        ->toContain('warehouseEntryExpenseStoredDocuments')
        ->toContain('documents: inheritedDocuments')
        ->toContain('Ver documentos')
        ->toContain('Documento no disponible o archivo no encontrado')
        ->toContain('Documento directo')
        ->and($pettyCashScript)
        ->toContain('Esta caja tiene gastos pendientes de canje o vinculación.')
        ->toContain('El cierre no anula los gastos aprobados y estos seguirán disponibles para vincularlos desde almacén.')
        ->and($closeModal)
        ->toContain('pcc_pending_link_warning');
});
