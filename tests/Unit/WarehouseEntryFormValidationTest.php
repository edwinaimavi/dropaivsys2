<?php

it('evita la validación HTML5 nativa en el modal con pestañas', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/warehouse-entries/partials/modal.blade.php');

    expect($blade)
        ->toContain('id="warehouseEntryForm"')
        ->toContain('novalidate')
        ->toContain('id="btnSaveWarehouseEntry"')
        ->not->toMatch('/\srequired(?:\s|>)/');
});

it('valida por javascript, cambia de pestaña y controla el estado de guardado', function () {
    $root = dirname(__DIR__, 2);
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    expect($javascript)
        ->toContain("$(document).on('submit', '#warehouseEntryForm'")
        ->toContain('event.preventDefault()')
        ->toContain('validateWarehouseEntryRequiredData()')
        ->toContain('validateWarehouseEntryItems()')
        ->toContain('validateWarehouseEntryLots()')
        ->toContain('validateWarehouseEntryPendingExpense()')
        ->toContain('setWarehouseEntrySaving(true)')
        ->toContain('.always(() => setWarehouseEntrySaving(false))')
        ->toContain('.warehouse-entry-form-tabs a[href=');
});
