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

it('presenta los costos vinculados como tarjetas responsivas sin perder sus acciones', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/warehouse-entries/partials/modal.blade.php');
    $styles = file_get_contents($root.'/resources/views/admin/warehouse-entries/index.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    expect($blade)
        ->toContain('warehouse-entry-expense-list-summary')
        ->toContain('id="warehouseEntryExpenseCount"')
        ->toContain('id="warehouseEntryFreightTotal"')
        ->toContain('id="warehouseEntryOtherExpenseTotal"')
        ->toContain('id="warehouseEntryExpenseLinkedTotal"')
        ->toContain('id="warehouseEntryExpensesBody" class="warehouse-entry-expense-cards"')
        ->not->toContain('warehouse-entry-expenses-table')
        ->and($styles)
        ->toContain('.warehouse-entry-expense-card-main')
        ->toContain('.warehouse-entry-expense-card.is-pending')
        ->toContain('@media (max-width: 767.98px)')
        ->and($javascript)
        ->toContain('warehouse-entry-expense-doc-dropdown')
        ->toContain('btnViewWarehouseEntryExpenseObservation')
        ->toContain('btnReviewWarehouseEntryExpense')
        ->toContain('btnEditWarehouseEntryExpense')
        ->toContain('btnRemoveWarehouseEntryExpense');
});

it('presenta el resumen real del anticipo sin completar saldos faltantes con cero', function () {
    $root = dirname(__DIR__, 2);
    $styles = file_get_contents($root.'/resources/views/admin/warehouse-entries/index.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/warehouse-entry.js');

    expect($javascript)
        ->toContain('response.payment_summary')
        ->toContain('Total de la orden')
        ->toContain('Anticipo pagado')
        ->toContain('Saldo pendiente')
        ->toContain('payments_by_currency')
        ->not->toContain('formatWarehouseEntryMoney(response.advance_balance || 0)')
        ->and($styles)
        ->toContain('.warehouse-entry-advance-summary')
        ->toContain('.warehouse-entry-advance-values')
        ->toContain('grid-template-columns: 1fr');
});
