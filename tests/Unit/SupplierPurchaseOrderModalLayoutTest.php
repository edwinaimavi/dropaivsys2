<?php

it('organiza el modal de orden a proveedor en siete pestañas y conserva un único guardado', function () {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/supplier-purchase-orders/partials/modal.blade.php');

    expect($blade)
        ->toContain('supplier_order_tab_data')
        ->toContain('supplier_order_tab_finance')
        ->toContain('supplier_order_tab_logistics')
        ->toContain('supplier_order_tab_documents')
        ->toContain('supplier_order_tab_items')
        ->toContain('supplier_order_tab_pdf')
        ->toContain('supplier_order_tab_summary')
        ->toContain('supplier-order-modal-footer')
        ->toContain('form="supplierPurchaseOrderForm"')
        ->and(substr_count($blade, 'id="btnSaveSupplierPurchaseOrder"'))->toBe(1);
});

it('mantiene los contenedores funcionales que el diseño distribuye entre las pestañas', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/supplier-purchase-orders/partials/modal.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/supplier-purchase-order.js');

    expect($blade)
        ->toContain('supplierOrderDocumentsContainer')
        ->toContain('supplierOrderItemsTbody')
        ->toContain('supplierOrderFinancialPenTotal')
        ->toContain('supplierOrderShippingAgencySection')
        ->toContain('supplierOrderFormSummary')
        ->and($javascript)
        ->toContain('initializeSupplierOrderTabbedLayout')
        ->toContain('updateSupplierOrderFormSummary')
        ->toContain("showSupplierOrderTab('finance')")
        ->toContain("showSupplierOrderTab('items')");
});

it('carga las cuentas bancarias de origen por empresa y moneda sin precargar cuentas globales', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/supplier-purchase-orders/partials/modal.blade.php');
    $javascript = file_get_contents($root.'/resources/js/pages/supplier-purchase-order.js');

    expect($blade)
        ->toContain('id="supplier_order_new_advance_bank_account_id"')
        ->toContain('Seleccione empresa y moneda de pago')
        ->not->toContain('@foreach ($companyBankAccounts as $account)')
        ->and($javascript)
        ->toContain('loadSupplierOrderAdvanceBankAccounts')
        ->toContain('ensureSupplierOrderAdvanceBankAccountsLoaded')
        ->toContain('supplierPurchaseOrderCompanyBankAccounts')
        ->toContain("'is-negative'")
        ->toContain('No hay cuentas bancarias activas para esta empresa y moneda.')
        ->not->toContain("$('#supplier_order_code').val('Seleccione cuenta bancaria')");
});
