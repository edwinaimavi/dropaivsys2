<?php

it('reutiliza una sola fuente visual y una sola lógica de formulario en ambos módulos', function () {
    $root = dirname(__DIR__, 2);
    $customerIndex = file_get_contents($root.'/resources/views/admin/customer-purchase-orders/index.blade.php');
    $invoiceIndex = file_get_contents($root.'/resources/views/admin/electronic-invoices/index.blade.php');
    $customerJs = file_get_contents($root.'/resources/js/pages/customer-purchase-order.js');
    $invoiceJs = file_get_contents($root.'/resources/js/pages/electronic-invoice.js');

    expect($customerIndex)
        ->toContain("@include('admin.electronic-invoices.partials.modal')")
        ->and($invoiceIndex)
        ->toContain("@include('admin.electronic-invoices.partials.modal')")
        ->and($customerJs)
        ->toContain("from './electronic-invoice-form'")
        ->toContain("sourceContext: 'customer_purchase_orders'")
        ->not->toContain('tableElectronicInvoice')
        ->and($invoiceJs)
        ->toContain("from './electronic-invoice-form'")
        ->toContain("sourceContext: 'electronic_invoices'")
        ->not->toContain("$('#electronicInvoiceForm').on");
});

it('convierte facturar en una acción modal sin navegación al módulo de facturación', function () {
    $root = dirname(__DIR__, 2);
    $actions = file_get_contents($root.'/resources/views/admin/customer-purchase-orders/partials/acciones.blade.php');

    expect($actions)
        ->toContain('class="dropdown-item invoiceCustomerPurchaseOrder"')
        ->toContain('data-customer-purchase-order-id="{{ $order->id }}"')
        ->not->toContain("['customer_purchase_order_id' => \$order->id]");
});

it('mantiene el formulario independiente de la tabla de facturas y evita listeners duplicados', function () {
    $root = dirname(__DIR__, 2);
    $formJs = file_get_contents($root.'/resources/js/pages/electronic-invoice-form.js');

    expect($formJs)
        ->toContain("const namespace = '.electronicInvoiceForm'")
        ->toContain('documentNode.off(namespace)')
        ->toContain("off(`submit\${namespace}`)")
        ->toContain("if (typeof onSaved === 'function') onSaved")
        ->toContain('electronicInvoiceLoading')
        ->not->toContain('tableElectronicInvoice')
        ->not->toContain('window.location');
});

it('hidrata afectación tributaria y alterna correctamente el almacén derivado o legacy', function () {
    $root = dirname(__DIR__, 2);
    $formJs = file_get_contents($root.'/resources/js/pages/electronic-invoice-form.js');
    $modal = file_get_contents($root.'/resources/views/admin/electronic-invoices/partials/modal.blade.php');

    expect($formJs)
        ->toContain("row.find('.item-tax-affectation').val(data.tax_affectation_code || '10')")
        ->toContain('applyElectronicInvoiceWarehouseContext(dispatchBacked, order.warehouse_context)')
        ->toContain("warehouseContext?.mode === 'single'")
        ->toContain("select.next('.select2-container').addClass('d-none')")
        ->toContain("select.prop('disabled', false).next('.select2-container').removeClass('d-none')")
        ->toContain("$('#ei_dispatch_warehouse_display').addClass('d-none').val('')")
        ->toContain('Según despacho confirmado')
        ->and($modal)
        ->toContain('id="ei_dispatch_warehouse_display"')
        ->toContain('id="ei_dispatch_warehouse_help"')
        ->toContain('@foreach ($taxAffectations as $tax)');
});

it('mantiene la precisión comercial con precios incluidos en IGV y total exacto', function () {
    $base = (100 / 1.18) + (100 / 1.18);
    $igv = 200 - $base;

    expect(round($base, 10))->toBe(169.4915254237)
        ->and(round($igv, 10))->toBe(30.5084745763)
        ->and((10 * 10) + (20 * 5))->toBe(200);
});

it('sincroniza la fecha DATE del formulario con el resumen y el payload sin conversion UTC', function () {
    $root = dirname(__DIR__, 2);
    $formJs = file_get_contents($root.'/resources/js/pages/electronic-invoice-form.js');
    $dateOnlyJs = file_get_contents($root.'/resources/js/utils/date-only.js');

    expect($formJs)
        ->toContain("currentDateOnlyInTimeZone,")
        ->toContain("$('#ei_issue_date').val(currentDateOnlyInTimeZone())")
        ->toContain("'#ei_issue_date, #ei_currency_id, #ei_correlativo_preview'")
        ->toContain("formatElectronicInvoiceDisplayDate($('#ei_issue_date').val())")
        ->toContain('const data = $(form).serializeArray()')
        ->not->toContain("new Date().toISOString().slice(0, 10)")
        ->and($dateOnlyJs)
        ->toContain("const [year, month, day] = date.split('-')")
        ->toContain('`${day}/${month}/${year}`')
        ->toContain("APPLICATION_TIME_ZONE = 'America/Lima'")
        ->not->toContain('new Date(value)');
});

it('reserva el formulario de edición para borradores y exige motivo al cancelar', function () {
    $root = dirname(__DIR__, 2);
    $actions = file_get_contents($root.'/resources/views/admin/electronic-invoices/partials/acciones.blade.php');
    $pageJs = file_get_contents($root.'/resources/js/pages/electronic-invoice.js');
    $formJs = file_get_contents($root.'/resources/js/pages/electronic-invoice-form.js');
    $viewModal = file_get_contents($root.'/resources/views/admin/electronic-invoices/partials/viewModal.blade.php');

    expect($actions)
        ->toContain("@if (\$invoice->status === 'draft')")
        ->toContain('Editar borrador')
        ->not->toContain('Editar comprobante')
        ->and($formJs)->toContain('`${window.routes.electronicInvoiceShow}/${id}/edit`')
        ->and($pageJs)
        ->toContain("inputLabel: 'Motivo de cancelación'")
        ->toContain("reason: String(result.value || '').trim()")
        ->toContain("label: dispatchContext.dispatch_label || '-'")
        ->toContain('Según despacho confirmado')
        ->and($viewModal)
        ->toContain('id="vei_dispatch"')
        ->toContain('id="vei_warehouse"')
        ->toContain('id="vei_warehouse_note"');
});

it('conserva los mensajes INTERNAL y un indicador de carga dentro del modal compartido', function () {
    $root = dirname(__DIR__, 2);
    $formJs = file_get_contents($root.'/resources/js/pages/electronic-invoice-form.js');
    $modal = file_get_contents($root.'/resources/views/admin/electronic-invoices/partials/modal.blade.php');

    expect($formJs)
        ->toContain('Configuración local requerida')
        ->toContain('Serie local requerida')
        ->and($modal)
        ->toContain('id="electronicInvoiceLoading"')
        ->toContain('id="electronicInvoiceLoadingText"');
});

it('distingue una serie disponible no predeterminada de la ausencia real de series', function () {
    $root = dirname(__DIR__, 2);
    $formJs = file_get_contents($root.'/resources/js/pages/electronic-invoice-form.js');

    expect($formJs)
        ->toContain("String(option.data('company-id')) === String(companyId)")
        ->toContain("String(option.data('document-type')) === String(documentType)")
        ->toContain("String(option.data('environment')) === String(environment)")
        ->toContain('available.length === 1 ? available.first()')
        ->toContain('!available.length && companyId')
        ->toContain("title: 'Configuración local requerida'")
        ->toContain("title: 'Serie local requerida'");
});

it('muestra el ambiente del detalle como texto plano y no interpreta HTML del servidor', function () {
    $root = dirname(__DIR__, 2);
    $seriesJs = file_get_contents($root.'/resources/js/pages/electronic-invoice-series.js');

    expect($seriesJs)
        ->toContain(".text(item.environment_label || environmentLabel(item.environment))")
        ->not->toContain(".text(renderEnvironment(item.environment, 'display'))")
        ->not->toContain("$('#view_series_environment').html");
});
