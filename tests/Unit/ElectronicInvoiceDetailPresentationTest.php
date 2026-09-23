<?php

it('mantiene todos los datos del comprobante dentro del nuevo detalle visual', function () {
    $root = dirname(__DIR__, 2);
    $modal = file_get_contents($root.'/resources/views/admin/electronic-invoices/partials/viewModal.blade.php');

    $requiredIds = [
        'vei_full_number',
        'vei_document_type',
        'vei_status',
        'vei_client_name',
        'vei_total_amount',
        'vei_company',
        'vei_company_ruc',
        'vei_client_document',
        'vei_issue_date',
        'vei_currency',
        'vei_payment_type',
        'vei_purchase_order',
        'vei_sunat_status',
        'vei_payment_status',
        'vei_paid_amount',
        'vei_pending_amount',
        'vei_dispatch',
        'vei_warehouse',
        'vei_warehouse_note',
        'vei_observations',
        'vei_items_body',
        'vei_collections_body',
        'vei_taxable_amount',
        'vei_exonerated_amount',
        'vei_unaffected_amount',
        'vei_igv_amount',
        'vei_total_footer',
    ];

    foreach ($requiredIds as $id) {
        expect($modal)->toContain('id="'.$id.'"');
    }
});

it('presenta resumen ejecutivo kpis y secciones semanticas sin convertir el detalle en pagina', function () {
    $root = dirname(__DIR__, 2);
    $modal = file_get_contents($root.'/resources/views/admin/electronic-invoices/partials/viewModal.blade.php');

    expect($modal)
        ->toContain('id="viewElectronicInvoiceModal"')
        ->toContain('electronic-invoice-summary')
        ->toContain('electronic-invoice-info-grid')
        ->toContain('electronic-invoice-kpis')
        ->toContain('id="vei_kpi_total"')
        ->toContain('id="vei_kpi_payment_status"')
        ->toContain('electronic-invoice-tax-summary')
        ->toContain('data-dismiss="modal"');
});

it('traduce estados y renderiza tablas y estados vacios de forma segura', function () {
    $root = dirname(__DIR__, 2);
    $pageJs = file_get_contents($root.'/resources/js/pages/electronic-invoice.js');

    expect($pageJs)
        ->toContain("generated: { label: 'Generado', tone: 'primary' }")
        ->toContain("draft: { label: 'Borrador', tone: 'neutral' }")
        ->toContain("voided: { label: 'Anulado', tone: 'danger' }")
        ->toContain('electronicInvoiceSunatStatusPresentation')
        ->toContain('electronicInvoiceEmptyState(6')
        ->toContain('escapeElectronicInvoiceHtml(item.product_code')
        ->toContain('escapeElectronicInvoiceHtml(item.description')
        ->toContain('escapeElectronicInvoiceHtml(collection.proof_url)')
        ->not->toContain(".text((invoice.status || '').toUpperCase())");
});

it('encapsula el detalle en estilos propios y responsivos del modulo', function () {
    $root = dirname(__DIR__, 2);
    $pageJs = file_get_contents($root.'/resources/js/pages/electronic-invoice.js');
    $css = file_get_contents($root.'/resources/css/electronic-invoice-detail.css');

    expect($pageJs)
        ->toContain("import '../../css/electronic-invoice-detail.css'")
        ->and($css)
        ->toContain('.electronic-invoice-detail__layout')
        ->toContain('.electronic-invoice-status--success')
        ->toContain('.electronic-invoice-empty-state__content')
        ->toContain('@media (max-width: 991.98px)')
        ->toContain('@media (max-width: 575.98px)');
});
