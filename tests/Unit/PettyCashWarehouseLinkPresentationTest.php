<?php

it('informa que solo se pueden vincular gastos de cajas abiertas', function () {
    $warehouseScript = file_get_contents(resource_path('js/pages/warehouse-entry.js'));
    $pettyCashScript = file_get_contents(resource_path('js/pages/petty-cash.js'));
    $closeModal = file_get_contents(resource_path('views/admin/petty-cash/partials/closeModal.blade.php'));

    expect($warehouseScript)
        ->toContain('No hay gastos disponibles en cajas abiertas para vincular.')
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
        ->toContain('Si la cierra, ya no podrán jalarse desde almacén. ¿Desea continuar?')
        ->and($closeModal)
        ->toContain('pcc_pending_link_warning');
});
