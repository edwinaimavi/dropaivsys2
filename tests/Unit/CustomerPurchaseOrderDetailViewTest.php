<?php

it('organiza el detalle de OC Cliente en resumen ejecutivo y pestañas', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/customer-purchase-orders/partials/viewModal.blade.php');

    expect($blade)
        ->toContain('id="customerOrderViewTabs"')
        ->toContain('href="#vpo_tab_summary"')
        ->toContain('href="#vpo_tab_general"')
        ->toContain('href="#vpo_tab_seller"')
        ->toContain('href="#vpo_tab_items"')
        ->toContain('href="#vpo_tab_supply"')
        ->toContain('href="#vpo_tab_dispatches"')
        ->toContain('href="#vpo_tab_documents"')
        ->toContain('id="vpo_operational_timeline"')
        ->toContain('id="vpo_supplier_orders"')
        ->toContain('id="vpo_warehouse_trace"')
        ->toContain('id="vpo_dispatches"');
});

it('conserva todos los destinos dinámicos del detalle sin identificadores duplicados', function () {
    $root = dirname(__DIR__, 2);
    $blade = file_get_contents($root.'/resources/views/admin/customer-purchase-orders/partials/viewModal.blade.php');
    preg_match_all('/id="(vpo_[^"]+)"/', $blade, $matches);

    expect($matches[1])->not->toBeEmpty()
        ->and(array_unique($matches[1]))->toHaveCount(count($matches[1]));
});

it('carga los proveedores relacionados y completa los nuevos indicadores en frontend', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/Admin/CustomerPurchaseOrderController.php');
    $javascript = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');

    expect($controller)
        ->toContain("'supplierPurchaseOrders.supplier'")
        ->toContain("'supplierPurchaseOrders.currency'")
        ->toContain("'supplierPurchaseOrders.warehouseEntries'")
        ->and($javascript)
        ->toContain('customerPurchaseOrderStatusMeta(order.status)')
        ->toContain("$('#vpo_operational_timeline').html")
        ->toContain("$('#vpo_supplier_orders').html")
        ->toContain("$('#customerOrderViewTabs a[href=\"#vpo_tab_summary\"]').tab('show')");
});
