let tableSupplierPurchaseOrder;
let supplierOrderDocumentIndex = 0;
let supplierOrderItemIndex = 0;
let supplierOrderSourceLoadRequest = null;
let supplierOrderSourceLoadTimer = null;
let supplierOrderAdvanceBankAccountsRequest = null;
const supplierOrderExpandedGroups = new Set();
let supplierOrderEditDeepLinkHandled = false;
let supplierOrderEditDeepLinkSearchApplied = false;
let supplierOrderPaymentBaseDate = new Date();
const defaultSupplierOrderImportantNote = `ADJUNTAR JUNTAMENTE CON LA FACTURA Y GUIA DE REMISION AL CORREO: LOGISTICA@DROPAIV.COM, LOS DOCUMENTOS LEGALES NECESARIOS TALES COMO:
1. BPM O ISO DEL BIEN ADQUIRIDO O SU EQUIVALENTE - VIGENTE
2. CERTIFICADO O PROTOCOLO DE ANALISIS DEL BIEN ADQUIRIDO - VIGENTE
3. REGISTRO SANITARIO DEL BIEN ADQUIRIDO - VIGENTE`;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $('#supplierPurchaseOrderModal').modal({
        backdrop: 'static',
        keyboard: false,
        show: false
    });

    initializeSupplierOrderTabbedLayout();
    initSupplierOrderSelect2($('#supplierPurchaseOrderModal'));
    initSupplierPurchaseOrderTable();
    $(document).on('supplier-orders:groups-rendered', openSupplierOrderEditFromQuery);
    openSupplierOrderEditFromQuery();

    $(document).on('click', '.supplier-order-group-toggle', function () {
        toggleSupplierOrderCustomerGroup($(this).closest('.supplier-order-accordion').attr('data-group-key'));
    });

    $(document).on('click', '#btnCreateSupplierPurchaseOrder', function () {
        resetSupplierPurchaseOrderForm();
        syncPurchaseInstructions(true);
        $('#supplierPurchaseOrderModalLabel').text('Registrar Orden de Compra a Proveedor');
        $('#supplier_order_code').val('C\u00f3digo autom\u00e1tico');
        $('#supplierPurchaseOrderModal').modal('show');
    });

    $('#supplierPurchaseOrderModal').on('hidden.bs.modal', resetSupplierPurchaseOrderForm);

    $(document).on('submit', '#supplierPurchaseOrderForm', function (event) {
        event.preventDefault();
        saveSupplierPurchaseOrder(this);
    });

    $(document).on('click', '#btnAddSupplierOrderDocument', addSupplierOrderDocumentRow);
    $(document).on('click', '.btnRemoveSupplierOrderDocument', function () {
        $(this).closest('.supplier-order-document-row').remove();
        updateSupplierOrderFormSummary();
    });
    $(document).on('change', '.supplier-document-file', function () {
        const fileName = this.files?.length
            ? this.files[0].name
            : 'Ningún archivo seleccionado';

        $(this)
            .closest('.supplier-order-document-row')
            .find('.supplier-doc-file-name')
            .text(fileName)
            .toggleClass('has-file', Boolean(this.files?.length));
    });
    $(document).on('click', '.btnDeleteExistingSupplierOrderDocument', deleteExistingSupplierOrderDocument);

    $(document).on('click', '#btnQuickSupplierForOrder', openQuickSupplierForOrderModal);
    $(document).on('click', '#btnSearchQuickSupplierRuc', searchQuickSupplierForOrderRuc);
    $(document).on('input', '#spo_quick_supplier_ruc', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });
    $(document).on('submit', '#quickSupplierForOrderForm', saveQuickSupplierForOrder);
    $(document).on('click', '#btnQuickSupplierAccountForOrder', openQuickSupplierAccountForOrderModal);
    $(document).on('submit', '#quickSupplierAccountForOrderForm', saveQuickSupplierAccountForOrder);

    $('#quickSupplierForOrderModal').on('shown.bs.modal', initQuickSupplierForOrderUbigeo);
    $('#quickSupplierForOrderModal').on('hidden.bs.modal', function () {
        if ($('#supplierPurchaseOrderModal').hasClass('show')) {
            $('body').addClass('modal-open');
        }
    });
    $('#quickSupplierAccountForOrderModal').on('hidden.bs.modal', function () {
        if ($('#supplierPurchaseOrderModal').hasClass('show')) $('body').addClass('modal-open');
    });

    $(document).on('click', '#btnAddSupplierOrderItem', function () {
        addSupplierOrderItemRow();
    });

    $(document).on('click', '.btnRemoveSupplierOrderItem', function () {
        const row = $(this).closest('tr');
        destroySupplierOrderRowSelect2(row);
        row.remove();
        refreshSupplierOrderItemIndexes();
        calculateSupplierOrderTotals();
        showEmptySupplierOrderItemsRow();
        updateSupplierOrderFormSummary();
    });

    $(document).on('change', '#supplier_order_supplier_id', function () {
        const supplierId = $(this).val();
        const selected = $(this).find('option:selected');
        $('#supplierOrderSideSupplier').text(
            supplierId ? selected.text().trim() : 'Seleccione proveedor'
        );

        if (!$('#supplier_order_payment_condition').val()) {
            const supplierPaymentCondition = selected.data('payment-condition') || '';
            const paymentCondition = normalizeSupplierOrderPaymentCondition(supplierPaymentCondition);

            if (
                paymentCondition
                && $(`#supplier_order_payment_condition option[value="${paymentCondition}"]`).length
            ) {
                $('#supplier_order_payment_condition')
                    .val(paymentCondition)
                    .trigger('change');

                const legacyCreditDays = supplierOrderCreditDaysFromCondition(supplierPaymentCondition);
                if (paymentCondition === 'credito' && legacyCreditDays > 0) {
                    $('#supplier_order_credit_days').val(legacyCreditDays).trigger('input');
                }
            }
        }

        loadSupplierAccounts(supplierId);
        clearSupplierOrderPendingItems();
    });

    $(document).on('change', '#supplier_order_currency_id', updateSupplierOrderCurrency);
    $(document).on('change', '#supplier_order_payment_condition', toggleSupplierOrderCreditFields);
    $(document).on('input', '#supplier_order_credit_days', calculateSupplierOrderPaymentDueDate);
    $(document).on('change input', '#supplier_order_exchange_rate,#supplier_order_apply_advance,#supplier_order_advance_type,#supplier_order_advance_percentage,#supplier_order_advance_amount,#supplier_order_new_advance_applied_amount,#supplier_order_new_advance_exchange_rate', function () {
        updateSupplierOrderFinancialSummary();
    });
    $(document).on('change', '#supplier_order_payment_currency_id', function () {
        $('#supplier_order_new_advance_payment_currency_id').val($(this).val() || '').trigger('change');
    });
    $(document).on('change', '#supplier_order_new_advance_payment_currency_id', function () {
        $('#supplier_order_new_advance_exchange_rate').val('');
        loadSupplierOrderAdvanceBankAccounts();
        updateSupplierOrderFinancialSummary();
    });
    $(document).on('change input', '#supplierPurchaseOrderForm', updateSupplierOrderFormSummary);
    $(document).on('shown.bs.tab', '#supplierPurchaseOrderModal .supplier-order-form-tabs a[data-toggle="pill"]', function () {
        updateSupplierOrderFormSummary();
        if ($(this).data('section') === 'finance') ensureSupplierOrderAdvanceBankAccountsLoaded();
    });
    $(document).on('change', '#supplier_order_new_advance_proof', function () {
        $(this).siblings('.custom-file-label').text(this.files?.[0]?.name || 'Seleccionar archivo');
    });

    $(document).on('change', '#supplier_order_company_id', function () {
        applySupplierOrderCompanyDefaults();
        loadSupplierOrderAdvanceBankAccounts();
    });

    $(document).on('input', '#supplierPurchaseOrderForm .text-uppercase', function () {
        this.value = this.value.toUpperCase();
    });

    $(document).on('change', '#supplier_order_supplier_account_id', function () {
        if (!$('#supplier_purchase_order_id').val()) {
            generateSupplierPurchaseOrderCode($(this).val());
        }

        syncPurchaseInstructions(true);
    });

    $(document).on('change', '#supplier_order_destination_ubigeo_id', function () {
        syncPurchaseInstructions(true);
    });

    $(document).on('input', '#supplier_order_destination_text', function () {
        syncPurchaseInstructions(true);
    });

    $(document).on('input', '#supplier_order_purchase_instructions', function () {
        const input = $('#supplier_order_purchase_instructions');

        if (input.val().trim() !== String(input.data('last-auto-value') || '').trim()) {
            input.data('last-auto-value', '');
        }
    });

    $(document).on('change', '#supplier_order_delivery_type', function () {
        toggleSupplierOrderShippingAgencySection();
    });

    $(document).on('change', '#supplier_order_shipping_agency_id', function () {
        loadSupplierOrderShippingBranches($(this).val());
    });

    $(document).on('change', '#supplier_order_shipping_agency_branch_id', function () {
        const selected = $(this).find('option:selected');
        $('#supplier_order_shipping_agency_address').val(selected.data('address') || '');
        $('#supplier_order_shipping_reference').val(selected.data('reference') || $('#supplier_order_shipping_reference').val());
        loadSupplierOrderShippingContacts($(this).val(), $('#supplier_order_shipping_agency_id').val());
    });

    $(document).on('change', '#supplier_order_shipping_agency_contact_id', function () {
        const selected = $(this).find('option:selected');
        $('#supplier_order_shipping_contact_phone').val(selected.data('phone') || '');
        $('#supplier_order_shipping_contact_email').val(selected.data('email') || '');
    });

    $(document).on(
        'input change',
        '#supplier_order_affect_igv, .item-quantity, .item-unit-price',
        calculateSupplierOrderTotals
    );

    $(document).on('change', '.item-article-picker', function () {
        applySelectedSupplierOrderArticle($(this).closest('tr'));
    });

    $(document).on('click', '#btnLoadSupplierOrderSource', loadSupplierOrderSourceItems);

    $(document).on('change', '#supplier_order_customer_purchase_order_ids', function () {
        clearSupplierOrderPendingItems();
    });

    $(document).on('change', '#supplierOrderPendingCheckAll', function () {
        $('#supplierOrderPendingItemsTbody .pending-item-check')
            .prop('checked', $(this).is(':checked'));
    });

    $(document).on('input change', '.pending-item-quantity, .pending-item-unit-price', function () {
        const row = $(this).closest('tr');
        updateSupplierOrderPendingItemTotal(row);
    });

    $(document).on('click', '#btnAddSelectedSupplierPendingItems', addSelectedSupplierPendingItems);

    $(document).on('click', '.editSupplierPurchaseOrder', function () {
        loadSupplierPurchaseOrderForEdit($(this).data('id'));
    });

    $(document).on('click', '.viewSupplierPurchaseOrder', function () {
        loadSupplierPurchaseOrderDetail($(this).data('id'));
    });

    $(document).on('click', '.deleteSupplierPurchaseOrder', function () {
        deleteSupplierPurchaseOrder($(this).data('id'));
    });
});

function initializeSupplierOrderTabbedLayout() {
    const shell = $('#supplierOrderTabsShell');
    if (!shell.length || shell.data('initialized')) return;

    const column = shell.closest('.supplier-order-tabs-column');
    const legacyCard = column.children('.card').first();
    const dataGrid = shell.find('.supplier-order-data-grid');
    const logisticsGrid = shell.find('.supplier-order-logistics-grid');

    [
        '#supplier_order_company_id',
        '#supplier_order_customer_purchase_order_ids',
        '#supplier_order_currency_id',
        '#supplier_order_supplier_id',
        '#supplier_order_supplier_account_id',
        '#supplier_order_payment_condition',
        '#supplier_order_credit_days',
        '#supplier_order_payment_due_date',
        '#supplier_order_payment_method',
        '#supplier_order_document_type',
        '#supplier_order_affect_igv',
        '#supplier_order_observations'
    ].forEach(selector => {
        const group = $(selector).closest('.form-group');
        if (selector === '#supplier_order_observations') group.removeClass('col-md-4').addClass('col-md-12');
        group.appendTo(dataGrid);
    });

    [
        '#supplier_order_transport_type',
        '#supplier_order_delivery_type',
        '#supplier_order_shipping_address',
        '#supplier_order_destination_ubigeo_id',
        '#supplier_order_destination_text'
    ].forEach(selector => $(selector).closest('.form-group').appendTo(logisticsGrid));

    $('#supplierOrderShippingAgencySection').appendTo(shell.find('.supplier-order-agency-container'));
    $('.supplier-order-financial-card').appendTo(shell.find('.supplier-order-finance-container'));
    $('#supplier_order_request_department').closest('.card').appendTo(shell.find('.supplier-order-pdf-container'));

    const documentsCard = $('.supplier-order-documents-card').first();
    const documentsWrapper = documentsCard.parent();
    documentsCard.appendTo(shell.find('.supplier-order-documents-container'));
    if (documentsWrapper.hasClass('col-12')) documentsWrapper.remove();

    const itemsCard = $('.supplier-order-items-full').first();
    const itemsWrapper = itemsCard.parent();
    itemsCard.appendTo(shell.find('.supplier-order-items-container'));
    if (itemsWrapper.hasClass('col-12')) itemsWrapper.remove();

    legacyCard.remove();
    shell.data('initialized', true).removeClass('d-none');
    updateSupplierOrderFormSummary();
}

function showSupplierOrderTab(section) {
    const tab = $(`#supplierPurchaseOrderModal .supplier-order-form-tabs [data-section="${section}"]`);
    if (tab.length) tab.tab('show');
}

function supplierOrderModalStatusPresentation(status) {
    return {
        registered: ['Registrado', 'badge-primary'],
        draft: ['Registrado', 'badge-primary'],
        sent: ['Enviado', 'badge-info'],
        approved: ['Aprobado', 'badge-success'],
        received: ['Ingresado', 'badge-success'],
        partial_entered: ['Ingreso parcial', 'badge-warning text-dark'],
        entered: ['Ingresado', 'badge-success'],
        cancelled: ['Cancelado', 'badge-danger'],
        invoiced: ['Facturado', 'badge-info']
    }[String(status || 'registered').toLowerCase()] || [String(status || 'Registrado'), 'badge-secondary'];
}

function updateSupplierOrderFormSummary() {
    const summary = $('#supplierOrderFormSummary');
    if (!summary.length) return;

    const selectedText = selector => {
        const selected = $(`${selector} option:selected`);
        if (!selected.length || !selected.val()) return '-';
        const values = selected.map(function () { return $(this).text().trim(); }).get();
        return values.length > 1 ? `${values[0]} y ${values.length - 1} m\u00e1s` : values[0];
    };
    const purchase = supplierOrderFinancialCurrency('#supplier_order_currency_id');
    const payment = supplierOrderFinancialCurrency('#supplier_order_payment_currency_id');
    const applyAdvance = $('#supplier_order_apply_advance').is(':checked');
    const rate = parseFloat($('#supplier_order_exchange_rate').val()) || 0;
    const totalPurchase = parseFloat($('#supplier_order_grand_total').val()) || 0;
    const advanceStatus = $('#supplierOrderAdvanceStatusBadge').text().trim() || 'Sin anticipo';
    const itemCount = $('#supplierOrderItemsTbody tr.supplier-order-item-row').length;
    const documentCount = $('#supplierOrderExistingDocuments .supplier-doc-existing, #supplierOrderDocumentsContainer .supplier-doc-row').length;
    const customerCount = ($('#supplier_order_customer_purchase_order_ids').val() || []).length;
    const cards = [
        ['Proveedor', selectedText('#supplier_order_supplier_id'), 'fa-building'],
        ['Empresa', selectedText('#supplier_order_company_id'), 'fa-landmark'],
        ['OC cliente relacionada', selectedText('#supplier_order_customer_purchase_order_ids'), 'fa-link'],
        ['Moneda compra', purchase.code || '-', 'fa-money-bill-wave'],
        ['Moneda pago', payment.code || '-', 'fa-wallet'],
        ['TC referencial', rate > 0 ? rate.toFixed(4) : 'No definido', 'fa-exchange-alt'],
        ['Total compra', `${purchase.code} ${formatSupplierOrderMoney(totalPurchase)}`, 'fa-shopping-cart'],
        ['Condici\u00f3n de pago', selectedText('#supplier_order_payment_condition'), 'fa-calendar-check'],
        ['Anticipo', applyAdvance ? advanceStatus : 'No aplica', 'fa-hand-holding-usd'],
        ['Total art\u00edculos', String(itemCount), 'fa-boxes'],
        ['Total documentos', String(documentCount), 'fa-folder-open']
    ];
    const alerts = [];
    if (applyAdvance && advanceStatus !== 'Pagado') alerts.push(['warning', 'fa-exclamation-triangle', 'Esta orden requiere anticipo pendiente.']);
    if (rate > 0) alerts.push(['info', 'fa-exchange-alt', 'Hay un tipo de cambio referencial para precargar pagos.']);
    if (customerCount > 0) alerts.push(['success', 'fa-link', 'Esta orden est\u00e1 relacionada a una OC del cliente.']);

    summary.html(`<div class="supplier-order-summary-heading"><div><span>Vista ejecutiva</span><h6>Resumen de la orden</h6><small>Compruebe los datos principales antes de guardar.</small></div><span class="supplier-order-summary-code">${escapeSupplierOrderHtml($('#supplier_order_code').val() || 'C\u00f3digo autom\u00e1tico')}</span></div><div class="supplier-order-summary-grid">${cards.map(([label, value, icon]) => `<div class="supplier-order-summary-card"><span><i class="fas ${icon}"></i></span><div><small>${label}</small><strong>${escapeSupplierOrderHtml(value)}</strong></div></div>`).join('')}</div><div class="supplier-order-summary-alerts">${alerts.length ? alerts.map(([tone, icon, text]) => `<div class="alert alert-${tone}"><i class="fas ${icon}"></i>${text}</div>`).join('') : '<div class="alert alert-light border"><i class="fas fa-check-circle text-success"></i>Sin alertas financieras adicionales.</div>'}</div>`);

    $('#supplierOrderSideCurrency').text(purchase.code || '-');
    $('#supplierOrderSideAdvance').text(applyAdvance ? $('#supplierOrderAdvanceRequired').text() : 'No aplica');
    $('#supplierOrderSideFinancialStatus').text(applyAdvance ? advanceStatus : 'Sin anticipo');
}

function initSupplierPurchaseOrderTable() {
    tableSupplierPurchaseOrder = $('#tableSupplierPurchaseOrder').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.supplierPurchaseOrderList,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            {
                data: 'code',
                name: 'code',
                responsivePriority: 1,
                render: function (data, type) {
                    return type === 'display'
                        ? data
                        : $('<div>').html(data || '').text().trim();
                }
            },
            {
                data: 'customer_order',
                name: 'customer_order',
                orderable: false,
                render: function (data, type) {
                    if (type === 'display') {
                        return data;
                    }

                    const container = $('<div>').html(data || '');
                    const labels = container.find('.customer-order-cell').map(function () {
                        const number = $(this).find('.customer-order-number').text().trim();
                        const customer = $(this).find('.customer-order-client').text().trim();
                        const branch = $(this).find('.customer-order-branch').text().trim();

                        return [number, customer, branch].filter(Boolean).join(' | ');
                    }).get();

                    return labels.length
                        ? labels.join('; ')
                        : container.text().replace(/\s+/g, ' ').trim();
                }
            },
            {
                data: 'supplier_name',
                name: 'supplier.business_name',
                orderable: false,
                render: function (data, type, row) {
                    const supplierName = String(data || '-');

                    if (type !== 'display') {
                        return supplierName;
                    }

                    if (row.supplier_has_quotation && row.supplier_quotation_url) {
                        return `<a href="${escapeSupplierOrderHtml(row.supplier_quotation_url)}"
                            target="_blank" rel="noopener" class="supplier-provider-quote-link"
                            title="Ver cotización del proveedor">
                            <span class="supplier-provider-quote-icon"><i class="far fa-file-pdf" aria-hidden="true"></i></span>
                            <span>${escapeSupplierOrderHtml(supplierName)}</span>
                        </a>`;
                    }

                    return `<span class="supplier-provider-name">${escapeSupplierOrderHtml(supplierName)}</span>`;
                }
            },
            { data: 'company', name: 'company.business_name', orderable: false },
            { data: 'currency', name: 'currency.code', orderable: false },
            {
                data: 'grand_total',
                name: 'grand_total',
                render: function (data, type) {
                    return type === 'display'
                        ? data
                        : $('<div>').html(data || '').text().trim();
                }
            },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false, responsivePriority: 2 }
        ],
        responsive: true,
        autoWidth: false,
        order: [],
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json',
            search: 'Buscar:',
            searchPlaceholder: 'Buscar orden, proveedor u OC cliente...',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            zeroRecords: 'No se encontraron órdenes coincidentes',
            processing: 'Procesando...',
            paginate: {
                previous: 'Anterior',
                next: 'Siguiente'
            }
        },
        dom: `
            <'row supplier-orders-toolbar align-items-center'
                <'col-sm-12 col-md-6'l>
                <'col-sm-12 col-md-6 text-md-end'f>
            >
            <'row'<'col-sm-12'tr>>
            <'row supplier-orders-footer align-items-center'
                <'col-sm-12 col-md-5'i>
                <'col-sm-12 col-md-7 d-flex justify-content-center justify-content-md-end'p>
            >
            <'row mt-3'<'col-sm-12 text-center'B>>
        `,
        buttons: [
            {
                extend: 'excel',
                className: 'btn btn-success btn-sm',
                text: '<i class="fas fa-file-excel"></i> Excel',
                exportOptions: { columns: ':not(:last-child)', orthogonal: 'export' }
            },
            {
                extend: 'pdf',
                className: 'btn btn-danger btn-sm',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                exportOptions: { columns: ':not(:last-child)', orthogonal: 'export' }
            },
            {
                extend: 'print',
                className: 'btn btn-secondary btn-sm',
                text: '<i class="fas fa-print"></i> Imprimir',
                exportOptions: { columns: ':not(:last-child)', orthogonal: 'export' }
            }
        ],
        drawCallback: function () {
            renderSupplierOrderCustomerGroups(this.api());
            $('[data-toggle="tooltip"]').tooltip();
        },
        initComplete: function () {
            const searchInput = $('#tableSupplierPurchaseOrder_filter input');
            searchInput
                .attr('aria-label', 'Buscar orden, proveedor u OC cliente')
                .attr('autocomplete', 'off');

            if (!$('#tableSupplierPurchaseOrder_filter .supplier-order-search-icon').length) {
                searchInput.before('<i class="fas fa-search supplier-order-search-icon" aria-hidden="true"></i>');
            }
        }
    });
}

function renderSupplierOrderCustomerGroups(table) {
    const rows = table.rows({ page: 'current' });
    const data = rows.data().toArray();
    const nodes = rows.nodes().toArray();
    const groups = {};
    const groupOrder = [];

    data.forEach(function (order) {
        const key = String(order.customer_order_group_key || 'direct-purchases');
        if (!groups[key]) {
            groupOrder.push(key);
            groups[key] = {
                purchases: 0,
                providers: new Set(),
                totals: new Map(),
                number: order.customer_order_number || 'Compras directas / Sin OC Cliente',
                client: order.customer_order_client || 'Sin cliente relacionado',
                branch: order.customer_order_branch || 'Sin OC Cliente vinculada',
                lastDate: order.group_date || order.created_at || '-',
                statuses: new Set(),
                items: []
            };
        }

        const group = groups[key];
        const currency = String(order.currency || '-');
        group.purchases += 1;
        if (order.supplier_id_value) group.providers.add(String(order.supplier_id_value));
        group.totals.set(currency, (group.totals.get(currency) || 0) + Number(order.grand_total_value || 0));
        group.statuses.add(String(order.status_code || '').toLowerCase());
        group.items.push(order);
    });

    const colspan = table.columns(':visible').count();
    const searchActive = String(table.search() || '').trim() !== '';
    $(nodes).addClass('supplier-order-source-row');

    groupOrder.forEach(function (key) {
        const group = groups[key];
        const firstIndex = data.findIndex(order => String(order.customer_order_group_key || 'direct-purchases') === key);
        const isExpanded = searchActive || supplierOrderExpandedGroups.has(key);
        const purchaseLabel = group.purchases === 1 ? 'compra' : 'compras';
        const providerLabel = group.providers.size === 1 ? 'proveedor' : 'proveedores';
        const groupStatus = supplierOrderGroupStatus(group.statuses);
        const totals = Array.from(group.totals.entries())
            .map(([currency, total]) => `${escapeSupplierOrderHtml(currency)} ${formatSupplierOrderMoney(total)}`)
            .join(' / ');
        const supplierRows = group.items.map(order => {
            const supplier = order.supplier_has_quotation && order.supplier_quotation_url
                ? `<a href="${escapeSupplierOrderHtml(order.supplier_quotation_url)}" target="_blank" rel="noopener" class="supplier-provider-quote-link"><i class="far fa-file-pdf mr-1"></i>${escapeSupplierOrderHtml(order.supplier_name || '-')}</a>`
                : escapeSupplierOrderHtml(order.supplier_name || '-');

            return `<tr>
                <td>${order.code || '-'}<span class="supplier-order-financial-chip">${escapeSupplierOrderHtml(order.financial_summary || '-')}</span><span class="supplier-order-advance-chip">${escapeSupplierOrderHtml(order.advance_summary || 'Sin anticipo')}</span></td>
                <td>${supplier}</td>
                <td>${escapeSupplierOrderHtml(order.company || '-')}</td>
                <td>${escapeSupplierOrderHtml(order.currency || '-')}</td>
                <td class="text-right font-weight-bold">${order.grand_total || '-'}</td>
                <td>${order.status || '-'}</td>
                <td>${escapeSupplierOrderHtml(order.created_at || '-')}</td>
                <td class="supplier-order-group-actions">${order.acciones || '-'}</td>
            </tr>`;
        }).join('');

        const groupRow = `<tr class="supplier-order-accordion-row"><td colspan="${colspan}">
            <section class="supplier-order-accordion supplier-order-group--${groupStatus.code}${isExpanded ? ' is-open' : ''}" data-group-key="${escapeSupplierOrderHtml(key)}">
                <div class="supplier-order-group-header">
                    <div class="supplier-order-group-identity">
                        <span class="supplier-order-group-icon"><i class="fas fa-file-invoice"></i></span>
                        <div><small>OC Cliente</small><strong>${escapeSupplierOrderHtml(group.number)}</strong><span>${escapeSupplierOrderHtml(group.client)}</span></div>
                    </div>
                    <div class="supplier-order-group-branch">
                        <small>Sucursal / Sede</small>
                        <span><i class="fas fa-map-marker-alt"></i>${escapeSupplierOrderHtml(group.branch)}</span>
                    </div>
                    <div class="supplier-order-group-metrics">
                        <span class="supplier-order-group-status"><i class="${groupStatus.icon}"></i>${groupStatus.label}</span>
                        <span><i class="fas fa-shopping-cart"></i>${group.purchases} ${purchaseLabel}</span>
                        <span><i class="fas fa-truck"></i>${group.providers.size} ${providerLabel}</span>
                        <span class="supplier-order-group-total"><i class="fas fa-coins"></i>${totals}</span>
                        <span><i class="far fa-calendar-alt"></i>&Uacute;ltimo: ${escapeSupplierOrderHtml(String(group.lastDate).split(' ')[0])}</span>
                        <button type="button" class="supplier-order-group-toggle" aria-expanded="${isExpanded ? 'true' : 'false'}">
                            <span>${isExpanded ? 'Ocultar compras' : 'Ver compras'}</span><i class="fas fa-chevron-${isExpanded ? 'up' : 'down'}"></i>
                        </button>
                    </div>
                </div>
                <div class="supplier-order-group-body"${isExpanded ? '' : ' style="display:none"'}>
                    <div class="supplier-order-group-table-wrap"><table class="supplier-order-group-table">
                        <thead><tr><th>C&oacute;digo OC proveedor</th><th>Proveedor</th><th>Empresa</th><th>Moneda</th><th>Total</th><th>Estado</th><th>Fecha registro</th><th>Acciones</th></tr></thead>
                        <tbody>${supplierRows}</tbody>
                    </table></div>
                </div>
            </section>
        </td></tr>`;

        $(nodes[firstIndex]).before(groupRow);
    });

    $(document).trigger('supplier-orders:groups-rendered');
}

function supplierOrderGroupStatus(statuses) {
    const values = Array.from(statuses).filter(Boolean);

    if (values.length === 1 && values[0] === 'registered') {
        return { code: 'registered', label: 'Registrado', icon: 'fas fa-clipboard-check' };
    }
    if (values.length === 1 && values[0] === 'entered') {
        return { code: 'entered', label: 'Ingresado', icon: 'fas fa-check-circle' };
    }
    if (values.length === 1 && values[0] === 'cancelled') {
        return { code: 'cancelled', label: 'Cancelado', icon: 'fas fa-ban' };
    }

    return { code: 'mixed', label: 'Mixto', icon: 'fas fa-adjust' };
}

function toggleSupplierOrderCustomerGroup(key) {
    const group = $('.supplier-order-accordion').filter(function () {
        return $(this).attr('data-group-key') === String(key);
    }).first();
    if (!group.length) return;

    const opening = !group.hasClass('is-open');
    group.toggleClass('is-open', opening);
    group.find('.supplier-order-group-body').stop(true, true).slideToggle(160);
    group.find('.supplier-order-group-toggle').attr('aria-expanded', opening ? 'true' : 'false')
        .find('span').text(opening ? 'Ocultar compras' : 'Ver compras');
    group.find('.supplier-order-group-toggle i').toggleClass('fa-chevron-up', opening).toggleClass('fa-chevron-down', !opening);

    if (opening) supplierOrderExpandedGroups.add(String(key));
    else supplierOrderExpandedGroups.delete(String(key));
}

function openSupplierOrderEditFromQuery() {
    if (supplierOrderEditDeepLinkHandled) return;
    const params = new URLSearchParams(window.location.search);
    const orderId = params.get('openOrder');
    if (!orderId) return;

    const editButton = $('.editSupplierPurchaseOrder').filter(function () {
        return String($(this).data('id')) === String(orderId);
    }).first();
    if (!editButton.length) {
        if (supplierOrderEditDeepLinkSearchApplied || !$.fn.DataTable.isDataTable('#tableSupplierPurchaseOrder')) return;
        supplierOrderEditDeepLinkSearchApplied = true;
        $('#tableSupplierPurchaseOrder').DataTable().search(params.get('orderCode') || orderId).draw();
        return;
    }

    supplierOrderEditDeepLinkHandled = true;
    const accordion = editButton.closest('.supplier-order-accordion');
    if (accordion.length && !accordion.hasClass('is-open')) accordion.find('.supplier-order-group-toggle').first().trigger('click');
    const row = editButton.closest('tr');
    row.addClass('supplier-order-deep-link-highlight');
    row[0]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => row.removeClass('supplier-order-deep-link-highlight'), 3200);
    setTimeout(() => loadSupplierPurchaseOrderForEdit(orderId), 300);

    params.delete('openOrder');
    params.delete('orderCode');
    const query = params.toString();
    window.history.replaceState({}, '', `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash}`);
}

function initSupplierOrderSelect2(scope) {
    if (!$.fn.select2) {
        return;
    }

    const container = scope && scope.length ? scope : $('#supplierPurchaseOrderModal');

    container.find('select').each(function () {
        const select = $(this);

        if (select.hasClass('select2-hidden-accessible')) {
            return;
        }

        const config = {
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $('#supplierPurchaseOrderModal'),
            placeholder: select.data('placeholder') || select.find('option:first').text().trim(),
            allowClear: !select.prop('required')
        };

        if (select.hasClass('item-article-picker')) {
            config.matcher = function (params, data) {
                const term = String(params.term || '').trim().toLocaleLowerCase();

                if (!term) {
                    return data;
                }

                const searchText = data.element
                    ? String($(data.element).attr('data-search') || data.text || '').toLocaleLowerCase()
                    : String(data.text || '').toLocaleLowerCase();

                return searchText.includes(term) ? data : null;
            };
        }

        if (select.is('#supplier_order_new_advance_bank_account_id')) {
            config.templateResult = supplierOrderBankAccountOptionTemplate;
            config.templateSelection = supplierOrderBankAccountSelectionTemplate;
        }

        select.select2(config);
    });
}

function destroySupplierOrderRowSelect2(row) {
    if (!$.fn.select2) {
        return;
    }

    row.find('select.select2-hidden-accessible').select2('destroy');
}

function resetSupplierPurchaseOrderForm() {
    const form = $('#supplierPurchaseOrderForm');

    if (!form.length) {
        return;
    }

    form[0].reset();
    supplierOrderPaymentBaseDate = new Date();
    $('#supplierOrderPaymentDueDateHelp').text('La fecha de vencimiento se calcula automáticamente desde hoy.');
    clearSupplierPurchaseOrderErrors();

    $('#supplier_purchase_order_id').val('');
    $('#supplier_order_code').val('');
    $('#supplierPurchaseOrderModalLabel').text('Registrar Orden de Compra a Proveedor');
    $('#supplierOrderSideDate').text(new Intl.DateTimeFormat('es-PE').format(new Date()));
    $('#supplierOrderSideStatus').attr('class', 'badge badge-primary px-2 py-1 mb-2').text('Registrado');
    $('#btnSaveSupplierPurchaseOrder')
        .prop('disabled', false)
        .html('<i class="fas fa-save mr-1"></i> Guardar');

    $('#supplierOrderItemsTbody tr.supplier-order-item-row').each(function () {
        destroySupplierOrderRowSelect2($(this));
    });

    supplierOrderItemIndex = 0;
    $('#supplierOrderItemsTbody').empty();
    showEmptySupplierOrderItemsRow();

    $('#supplier_order_type').val('articles');
    $('#supplier_order_affect_igv').val('1').trigger('change.select2');
    $('#supplier_order_supplier_id').val('').trigger('change.select2');
    $('#supplier_order_supplier_account_id').val('').trigger('change.select2');
    $('#supplier_order_customer_purchase_order_ids').val([]).trigger('change.select2');
    $('#supplier_order_company_id').val('').trigger('change.select2');
    $('#supplier_order_destination_ubigeo_id').val('').trigger('change.select2');
    $('#supplier_order_transport_type').val('').trigger('change.select2');
    $('#supplier_order_payment_condition').val('').trigger('change.select2');
    $('#supplier_order_credit_days,#supplier_order_payment_due_date').val('');
    $('#supplier_order_delivery_type').val('').trigger('change.select2');
    $('#supplier_order_shipping_agency_id').val('').trigger('change.select2');
    $('#supplier_order_shipping_agency_branch_id')
        .html('<option value="">Seleccione agencia primero</option>')
        .val('')
        .trigger('change.select2');
    $('#supplier_order_shipping_agency_contact_id')
        .html('<option value="">Seleccione sede primero</option>')
        .val('')
        .trigger('change.select2');
    $('#supplier_order_shipping_agency_address').val('');
    $('#supplier_order_shipping_contact_phone').val('');
    $('#supplier_order_shipping_contact_email').val('');
    $('#supplier_order_shipping_reference').val('');
    $('#supplier_order_payment_method').val('').trigger('change.select2');
    $('#supplier_order_payment_currency_id').val('').trigger('change.select2');
    $('#supplier_order_apply_advance').prop('checked', false);
    $('#supplier_order_exchange_rate,#supplier_order_advance_percentage,#supplier_order_advance_amount,#supplier_order_new_advance_applied_amount,#supplier_order_new_advance_exchange_rate,#supplier_order_new_advance_amount').val('');
    $('#supplier_order_advance_type,#supplier_order_new_advance_method,#supplier_order_new_advance_payment_currency_id').val('');
    $('#supplier_order_new_advance_date').val(new Date().toISOString().slice(0, 10));
    resetSupplierOrderAdvanceBankAccountSelect('Seleccione empresa y moneda de pago.');
    $('#supplier_order_new_advance_proof').val('').siblings('.custom-file-label').text('Seleccionar archivo');
    $('#supplierOrderExistingAdvancePayments').empty().data('paid-applied', 0).data('paid-pen', 0);
    $('#supplier_order_document_type').val('').trigger('change.select2');
    $('#supplier_order_request_department').val('COMPRAS');
    $('#supplier_order_authorized_by_name').val('IVAN CUBAS BINCES');
    $('#supplier_order_authorized_by_position').val('GERENTE GENERAL');
    $('#supplier_order_delivery_text').val('EN AGENCIA DE TRANSPORTES - ENVIO A PROVINCIA');
    $('#supplier_order_purchase_instructions').val('').data('last-auto-value', '');
    $('#supplier_order_important_note').val(defaultSupplierOrderImportantNote);
    supplierOrderDocumentIndex = 0;
    $('#supplierOrderDocumentsContainer, #supplierOrderExistingDocuments').empty();

    setDefaultSupplierOrderCurrency();
    syncSupplierOrderPaymentCurrency();
    loadSupplierOrderAdvanceBankAccounts();
    $('#supplierOrderSideSupplier').text('Seleccione proveedor');
    toggleSupplierOrderCreditFields();
    toggleSupplierOrderShippingAgencySection();
    calculateSupplierOrderTotals();
    syncPurchaseInstructions(true);
    showSupplierOrderTab('data');
    updateSupplierOrderFormSummary();
}

function clearQuickSupplierForOrderErrors() {
    $('#quickSupplierForOrderErrors').addClass('d-none').empty();
    $('#quickSupplierForOrderForm .is-invalid').removeClass('is-invalid');
    $('#quickSupplierForOrderForm .invalid-feedback').text('');
}

function showQuickSupplierForOrderErrors(errors) {
    clearQuickSupplierForOrderErrors();
    const messages = [];

    Object.entries(errors || {}).forEach(function ([field, fieldMessages]) {
        const message = Array.isArray(fieldMessages) ? fieldMessages[0] : fieldMessages;
        const inputName = field.replace(/\.([^.]+)/g, '[$1]');
        const input = $(`#quickSupplierForOrderForm [name="${inputName}"]`);
        messages.push(message);
        input.addClass('is-invalid');
        input.closest('.form-group').find('.invalid-feedback').first().text(message);
    });

    if (messages.length) {
        $('#quickSupplierForOrderErrors').removeClass('d-none').html(messages.map(escapeSupplierOrderHtml).join('<br>'));
    }
}

function openQuickSupplierForOrderModal() {
    const form = $('#quickSupplierForOrderForm')[0];
    form.reset();
    clearQuickSupplierForOrderErrors();
    $('#quickSupplierForOrderForm [name="igv_percentage"]').val('18.00');
    $('#spo_quick_supplier_ubigeo_id').empty().append(new Option('Buscar ubigeo...', '', true, true));
    $('#quickSupplierForOrderModal').modal('show');
}

function initQuickSupplierForOrderUbigeo() {
    const select = $('#spo_quick_supplier_ubigeo_id');
    if (!$.fn.select2) return;
    if (select.hasClass('select2-hidden-accessible')) select.select2('destroy');
    select.select2({
        theme: 'bootstrap4', width: '100%', dropdownParent: $('#quickSupplierForOrderModal'),
        placeholder: 'Buscar ubigeo...', allowClear: true, minimumInputLength: 2,
        ajax: {
            url: window.routes.supplierQuickSearchUbigeo, dataType: 'json', delay: 250,
            data: params => ({ search: params.term || '' }),
            processResults: response => ({ results: response || [] }), cache: true
        }
    });
    $('#spo_quick_supplier_ruc').trigger('focus');
}

function fillQuickSupplierForOrderFromRuc(response) {
    const data = response?.data || {};
    $('#spo_quick_supplier_business_name').val(response?.razon_social || data.nombre || data.razonSocial || '');
    $('#spo_quick_supplier_address').val(response?.direccion || data.direccion || data.domicilioFiscal || '');
    $('#quickSupplierForOrderForm [name="bank_account[account_holder]"]').val(
        response?.razon_social || data.nombre || data.razonSocial || ''
    );

    const location = [data.distrito, data.provincia, data.departamento].filter(Boolean).join(' ');
    if (location) {
        $.get(window.routes.supplierQuickSearchUbigeo, { search: location }).done(function (items) {
            if (items?.length) {
                const option = new Option(items[0].text, items[0].id, true, true);
                $('#spo_quick_supplier_ubigeo_id').append(option).trigger('change');
            }
        });
    }
}

function searchQuickSupplierForOrderRuc() {
    clearQuickSupplierForOrderErrors();
    const ruc = String($('#spo_quick_supplier_ruc').val() || '');
    const button = $('#btnSearchQuickSupplierRuc');

    if (!/^\d{11}$/.test(ruc)) {
        showQuickSupplierForOrderErrors({ ruc: ['El RUC debe tener 11 dígitos.'] });
        return;
    }

    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    $.get(`${window.routes.supplierQuickByRuc}/${ruc}`)
        .done(function () {
            showQuickSupplierForOrderErrors({ ruc: ['Ya existe un proveedor registrado con este RUC.'] });
            button.prop('disabled', false).html('<i class="fas fa-search"></i>');
        })
        .fail(function (xhr) {
            if (xhr.status !== 404) {
                showQuickSupplierForOrderErrors({ ruc: [xhr.responseJSON?.message || 'No se pudo validar el RUC.'] });
                button.prop('disabled', false).html('<i class="fas fa-search"></i>');
                return;
            }

            $.get(`${window.routes.supplierQuickConsultarRuc}/${ruc}`)
                .done(function (response) {
                    if (response.status) fillQuickSupplierForOrderFromRuc(response);
                    else showQuickSupplierForOrderErrors({ ruc: ['No se encontraron datos para este RUC.'] });
                })
                .fail(function (consultXhr) {
                    showQuickSupplierForOrderErrors({ ruc: [consultXhr.responseJSON?.message || 'No se encontraron datos para este RUC.'] });
                })
                .always(function () {
                    button.prop('disabled', false).html('<i class="fas fa-search"></i>');
                });
        });
}

function saveQuickSupplierForOrder(event) {
    event.preventDefault();
    clearQuickSupplierForOrderErrors();
    const form = this;
    const button = $('#btnSaveQuickSupplierForOrder');

    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');
    $.ajax({
        url: window.routes.supplierQuickStore, type: 'POST', data: new FormData(form),
        processData: false, contentType: false,
        success: function (response) {
            const supplier = response.supplier || response.data || {};
            const account = response.bank_account || {};
            const optionText = supplier.text || [supplier.ruc, supplier.business_name].filter(Boolean).join(' | ');
            const option = new Option(optionText, supplier.id, true, true);
            $(option).attr('data-payment-condition', supplier.payment_condition || '');
            $('#supplier_order_supplier_id').append(option).val(String(supplier.id)).trigger('change.select2');
            $('#supplierOrderSideSupplier').text(optionText);
            const paymentCondition = normalizeSupplierOrderOption(supplier.payment_condition || '');
            if ($(`#supplier_order_payment_condition option[value="${paymentCondition}"]`).length) {
                $('#supplier_order_payment_condition').val(paymentCondition).trigger('change.select2');
            }
            clearSupplierOrderPendingItems();
            loadSupplierAccounts(supplier.id, account.id);
            $('#quickSupplierForOrderModal').modal('hide');
            Swal.fire({ icon: 'success', title: response.message || 'Proveedor y cuenta bancaria registrados correctamente.', toast: true, position: 'top-end', showConfirmButton: false, timer: 2800 });
        },
        error: function (xhr) {
            if (xhr.status === 422) showQuickSupplierForOrderErrors(xhr.responseJSON?.errors || {});
            else Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'No se pudo registrar el proveedor.' });
        },
        complete: function () {
            button.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar proveedor');
        }
    });
}

function clearQuickSupplierAccountForOrderErrors() {
    $('#quickSupplierAccountForOrderErrors').addClass('d-none').empty();
    $('#quickSupplierAccountForOrderForm .is-invalid').removeClass('is-invalid');
    $('#quickSupplierAccountForOrderForm .invalid-feedback').text('');
}

function showQuickSupplierAccountForOrderErrors(errors) {
    clearQuickSupplierAccountForOrderErrors();
    const messages = [];
    Object.entries(errors || {}).forEach(function ([field, values]) {
        const message = Array.isArray(values) ? values[0] : values;
        const input = $(`#quickSupplierAccountForOrderForm [name="${field}"]`);
        messages.push(message); input.addClass('is-invalid');
        input.closest('.form-group').find('.invalid-feedback').first().text(message);
    });
    if (messages.length) $('#quickSupplierAccountForOrderErrors').removeClass('d-none').html(messages.map(escapeSupplierOrderHtml).join('<br>'));
}

function openQuickSupplierAccountForOrderModal() {
    if (!$('#supplier_order_supplier_id').val()) {
        Swal.fire({ icon: 'warning', title: 'Primero seleccione un proveedor.' });
        return;
    }
    $('#quickSupplierAccountForOrderForm')[0].reset();
    clearQuickSupplierAccountForOrderErrors();
    $('#quickSupplierAccountForOrderModal').modal('show');
}

function saveQuickSupplierAccountForOrder(event) {
    event.preventDefault();
    const supplierId = $('#supplier_order_supplier_id').val();
    const form = this;
    const button = $('#btnSaveQuickSupplierAccountForOrder');
    clearQuickSupplierAccountForOrderErrors();
    if (!form.checkValidity()) { form.reportValidity(); return; }
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');
    $.ajax({
        url: window.routes.supplierQuickAccountStore.replace(':id', supplierId),
        type: 'POST', data: new FormData(form), processData: false, contentType: false,
        success: function (response) {
            const account = response.bank_account || {};
            loadSupplierAccounts(supplierId, account.id);
            $('#quickSupplierAccountForOrderModal').modal('hide');
            Swal.fire({ icon: 'success', title: response.message || 'Cuenta bancaria registrada correctamente.', toast: true, position: 'top-end', showConfirmButton: false, timer: 2600 });
        },
        error: function (xhr) {
            if (xhr.status === 422) showQuickSupplierAccountForOrderErrors(xhr.responseJSON?.errors || {});
            else Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'No se pudo registrar la cuenta bancaria.' });
        },
        complete: function () { button.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar cuenta'); }
    });
}

function generateSupplierPurchaseOrderCode(supplierAccountId = null) {
    if (!supplierAccountId) {
        $('#supplier_order_code').val('C\u00f3digo autom\u00e1tico');
        return;
    }

    $('#supplier_order_code').val('Generando...');

    $.get(window.routes.supplierPurchaseOrderGenerateCode, {
        supplier_account_id: supplierAccountId
    })
        .done(function (response) {
            $('#supplier_order_code').val(response.code || 'C\u00f3digo autom\u00e1tico');
        })
        .fail(function (xhr) {
            $('#supplier_order_code').val('');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo generar el numero de orden.'
            });
        });
}

function saveSupplierPurchaseOrder(formElement) {
    clearSupplierPurchaseOrderErrors();
    refreshSupplierOrderItemIndexes();
    calculateSupplierOrderTotals();
    syncPurchaseInstructions(true);

    const paymentTermsError = validateSupplierOrderPaymentTerms();
    if (paymentTermsError) {
        showSupplierOrderTab('data');
        showSupplierPurchaseOrderErrors(paymentTermsError.errors);
        Swal.fire({ icon: 'warning', title: 'Revise la condición de pago', text: paymentTermsError.message });
        return;
    }

    if (!$('#supplier_order_supplier_account_id').val()) {
        showSupplierOrderTab('data');
        Swal.fire({ icon: 'warning', title: 'Debe seleccionar o registrar una cuenta bancaria del proveedor.' });
        return;
    }

    if ($('#supplierOrderItemsTbody tr.supplier-order-item-row').length === 0) {
        showSupplierOrderTab('items');
        Swal.fire({
            icon: 'warning',
            title: 'Agregue al menos un item',
            text: 'La orden debe contener productos o servicios para comprar.'
        });
        return;
    }

    const priceViolation = findSupplierOrderPriceViolation();
    if (priceViolation) {
        showSupplierOrderPriceViolation(priceViolation);
        return;
    }

    const financialError = validateSupplierOrderFinancialTerms();
    if (financialError) {
        showSupplierOrderTab('finance');
        Swal.fire({ icon: 'warning', title: 'Revise las condiciones financieras', text: financialError });
        return;
    }

    const id = $('#supplier_purchase_order_id').val();
    const formData = new FormData(formElement);
    const button = $('#btnSaveSupplierPurchaseOrder');
    const url = id
        ? `${window.routes.supplierPurchaseOrderUpdate}/${id}`
        : window.routes.supplierPurchaseOrderStore;

    if (id) {
        formData.append('_method', 'PUT');
    }

    button
        .prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            $('#supplierPurchaseOrderModal').modal('hide');
            tableSupplierPurchaseOrder.ajax.reload(null, false);

            if (response.pdf_url) {
                window.open(response.pdf_url, '_blank', 'noopener');
            }

            Swal.fire({
                icon: response.pdf_error ? 'warning' : 'success',
                title: response.message || 'Orden guardada correctamente.',
                text: response.pdf_error || undefined,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        },
        error: function (xhr) {
            button
                .prop('disabled', false)
                .html(`<i class="fas fa-save mr-1"></i> ${id ? 'Actualizar' : 'Guardar'}`);

            if (xhr.status === 422) {
                showSupplierPurchaseOrderErrors(xhr.responseJSON.errors || {});
                Swal.fire({
                    icon: 'warning',
                    title: 'Revisa el formulario',
                    text: 'Hay campos obligatorios o con formato incorrecto.'
                });
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo guardar la orden.'
            });
        }
    });
}

function addSupplierOrderDocumentRow() {
    const index = supplierOrderDocumentIndex++;

    $('#supplierOrderDocumentsContainer').append(`
        <div class="supplier-order-document-row supplier-doc-row">
            <div class="supplier-doc-row-header">
                <div>
                    <span class="supplier-doc-row-icon"><i class="fas fa-file-upload"></i></span>
                    <strong>Documento #${index + 1}</strong>
                </div>
                <button type="button" class="supplier-doc-remove btnRemoveSupplierOrderDocument"
                    title="Quitar documento" aria-label="Quitar documento">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="supplier-doc-row-grid">
                <div class="form-group mb-0">
                    <label>Tipo de documento</label>
                    <select name="supplier_documents[${index}][type]" class="form-control form-control-sm supplier-doc-control">
                        <option value="supplier_quote">Cotización del proveedor</option>
                        <option value="payment_support">Sustento de pago</option>
                        <option value="other">Otro documento</option>
                    </select>
                </div>
                <div class="form-group mb-0">
                    <label>Archivo</label>
                    <input type="file" name="supplier_documents[${index}][file]"
                        id="supplier_document_file_${index}"
                        class="d-none supplier-document-file" accept=".pdf,.jpg,.jpeg,.png">
                    <label for="supplier_document_file_${index}" class="supplier-doc-file-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Seleccionar archivo</span>
                    </label>
                    <small class="supplier-doc-file-name">Ningún archivo seleccionado</small>
                </div>
                <div class="form-group mb-0">
                    <label>Observación</label>
                    <input type="text" name="supplier_documents[${index}][observation]"
                        class="form-control form-control-sm supplier-doc-control" maxlength="500"
                        placeholder="Ej. Enviada por WhatsApp, depósito parcial, proforma, etc.">
                </div>
            </div>
        </div>
    `);
    updateSupplierOrderFormSummary();
}

function renderExistingSupplierOrderDocuments(documents) {
    const container = $('#supplierOrderExistingDocuments');

    if (!documents?.length) {
        container.empty();
        updateSupplierOrderFormSummary();
        return;
    }

    container.html(documents.map(document => {
        const extension = String(document.extension || document.original_name?.split('.').pop() || '').toLowerCase();
        const isImage = ['jpg', 'jpeg', 'png'].includes(extension);
        const iconClass = isImage ? 'fas fa-file-image' : 'fas fa-file-pdf';
        const iconTone = isImage ? 'is-image' : 'is-pdf';

        return `
        <div class="supplier-doc-existing">
            <span class="supplier-doc-existing-icon ${iconTone}">
                <i class="${iconClass}"></i>
            </span>
            <div class="supplier-doc-existing-info">
                <strong>${escapeSupplierOrderHtml(document.original_name || 'Documento')}</strong>
                <small>${escapeSupplierOrderHtml(document.document_type?.description || 'Documento del proveedor')}${document.observation ? ` · ${escapeSupplierOrderHtml(document.observation)}` : ''}</small>
            </div>
            <div class="supplier-doc-existing-actions">
                <a href="${escapeSupplierOrderHtml(document.view_url)}" target="_blank" rel="noopener"
                    class="supplier-doc-action is-open" title="Abrir documento">
                    <i class="fas fa-external-link-alt"></i>
                </a>
                <a href="${escapeSupplierOrderHtml(document.view_url)}" download="${escapeSupplierOrderHtml(document.original_name || 'documento')}"
                    class="supplier-doc-action is-open" title="Descargar documento">
                    <i class="fas fa-download"></i>
                </a>
                <button type="button" class="supplier-doc-action is-delete btnDeleteExistingSupplierOrderDocument"
                    data-url="${escapeSupplierOrderHtml(document.delete_url)}" title="Eliminar documento">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `}).join(''));
    updateSupplierOrderFormSummary();
}

function deleteExistingSupplierOrderDocument() {
    const button = $(this);
    const url = button.data('url');

    Swal.fire({
        icon: 'warning',
        title: '¿Eliminar documento?',
        text: 'El archivo adjunto será eliminado de la orden.',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;

        $.ajax({
            url,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: response => {
                const container = button.closest('#supplierOrderExistingDocuments');
                button.closest('.supplier-doc-existing').remove();
                if (!container.children('.supplier-doc-existing').length) container.empty();
                updateSupplierOrderFormSummary();
                Swal.fire('Correcto', response.message || 'Documento eliminado.', 'success');
            },
            error: xhr => Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar el documento.', 'error')
        });
    });
}

function applySupplierOrderCompanyDefaults() {
    const company = $('#supplier_order_company_id option:selected');
    const ruc = String(company.data('ruc') || '').trim();
    const companyName = String(
        company.data('business-name') || company.data('trade-name') || ''
    ).toUpperCase();
    const isPraga = ruc === '20612701904' || companyName.includes('PRAGA');

    $('#supplier_order_authorized_by_name').val(
        isPraga ? 'ROSA L. VINCES VALDERRAMA' : 'IVAN CUBAS BINCES'
    );
    $('#supplier_order_authorized_by_position').val('GERENTE GENERAL');

    const companyEmail = String(company.data('email') || '').trim();
    const importantNote = $('#supplier_order_important_note');

    if (companyEmail && importantNote.length) {
        importantNote.val(
            String(importantNote.val() || defaultSupplierOrderImportantNote)
                .replace(/(AL\s+CORREO\s*:\s*)[^,\s]+/i, `$1${companyEmail}`)
        );
    }
}

function loadSupplierAccounts(supplierId, selectedAccountId = null, options = {}) {
    const select = $('#supplier_order_supplier_account_id');

    if (!supplierId) {
        select.html('<option value="">Seleccione proveedor primero</option>').trigger('change.select2');
        syncPurchaseInstructions(true);
        return;
    }

    const url = window.routes.supplierPurchaseOrderSupplierAccounts.replace(':id', supplierId);

    select
        .prop('disabled', true)
        .html('<option value="">Cargando cuentas...</option>')
        .trigger('change.select2');

    return $.get(url)
        .done(function (response) {
            const accounts = response.accounts || [];
            let accountOptions = '<option value="">Seleccione cuenta</option>';

            accounts.forEach(function (account) {
                const bank = account.bank?.short_name || account.bank?.description || 'Banco';
                const currency = account.currency?.code || '';
                accountOptions += `<option value="${escapeSupplierOrderHtml(account.id)}"
                    data-bank="${escapeSupplierOrderHtml(bank)}">
                    ${escapeSupplierOrderHtml(bank)} - ${escapeSupplierOrderHtml(account.account_number)} - ${escapeSupplierOrderHtml(currency)}
                </option>`;
            });

            if (accounts.length === 0) {
                accountOptions = '<option value="">Este proveedor no tiene cuentas bancarias registradas</option>';
            }
            select.html(accountOptions).prop('disabled', false);

            if (selectedAccountId) {
                select.val(String(selectedAccountId));
            } else if (accounts.length === 1) {
                select.val(String(accounts[0].id));
            }

            select.trigger('change');

            if (!options.suppressInstructionSync) {
                syncPurchaseInstructions(true);
            }
        })
        .fail(function () {
            select
                .prop('disabled', true)
                .html('<option value="">Error al cargar cuentas</option>')
                .trigger('change.select2');
            syncPurchaseInstructions(true);
        });
}

function toggleSupplierOrderShippingAgencySection() {
    const isAgency = supplierOrderRequiresShippingAgency($('#supplier_order_delivery_type').val());
    $('#supplierOrderShippingAgencySection').toggleClass('d-none', !isAgency);
    $('#supplier_order_shipping_agency_id, #supplier_order_shipping_agency_branch_id')
        .prop('required', isAgency);

    if (!isAgency) {
        $('#supplier_order_shipping_agency_id').val('').trigger('change.select2');
        $('#supplier_order_shipping_agency_branch_id')
            .html('<option value="">Seleccione agencia primero</option>')
            .val('')
            .trigger('change.select2');
        $('#supplier_order_shipping_agency_contact_id')
            .html('<option value="">Seleccione sede primero</option>')
            .val('')
            .trigger('change.select2');
        $('#supplier_order_shipping_agency_address').val('');
        $('#supplier_order_shipping_contact_phone').val('');
        $('#supplier_order_shipping_contact_email').val('');
        $('#supplier_order_shipping_reference').val('');
    }
}

function loadSupplierOrderShippingBranches(agencyId, selectedBranchId = null, selectedContactId = null) {
    const branchSelect = $('#supplier_order_shipping_agency_branch_id');
    const contactSelect = $('#supplier_order_shipping_agency_contact_id');

    $('#supplier_order_shipping_agency_address').val('');
    $('#supplier_order_shipping_contact_phone').val('');
    $('#supplier_order_shipping_contact_email').val('');

    if (!agencyId) {
        branchSelect.html('<option value="">Seleccione agencia primero</option>').trigger('change.select2');
        contactSelect.html('<option value="">Seleccione sede primero</option>').trigger('change.select2');
        return;
    }

    const url = window.routes.supplierOrderShippingAgencyBranches.replace(':id', agencyId);
    branchSelect.prop('disabled', true).html('<option value="">Cargando sedes...</option>').trigger('change.select2');
    contactSelect.html('<option value="">Seleccione sede primero</option>').trigger('change.select2');

    $.get(url)
        .done(function (response) {
            const branches = response.branches || [];
            let options = '<option value="">Seleccione sede</option>';

            branches.forEach(function (branch) {
                const location = [branch.district, branch.province, branch.department].filter(Boolean).join(' / ');
                const address = [branch.address, location].filter(Boolean).join(' | ');

                options += `<option value="${escapeSupplierOrderHtml(branch.id)}"
                    data-address="${escapeSupplierOrderHtml(address)}"
                    data-reference="${escapeSupplierOrderHtml(branch.reference || '')}">
                    ${escapeSupplierOrderHtml(branch.branch_name || 'Sede')} ${branch.is_main ? '(Principal)' : ''}
                </option>`;
            });

            branchSelect.html(options).prop('disabled', false);

            const defaultBranch = selectedBranchId
                || branches.find(branch => Boolean(branch.is_main))?.id
                || (branches.length === 1 ? branches[0].id : null);

            if (defaultBranch) {
                branchSelect.val(String(defaultBranch));
            }

            branchSelect.trigger('change.select2');

            if (defaultBranch) {
                const selected = branchSelect.find('option:selected');
                $('#supplier_order_shipping_agency_address').val(selected.data('address') || '');
                if (!$('#supplier_order_shipping_reference').val()) {
                    $('#supplier_order_shipping_reference').val(selected.data('reference') || '');
                }
                loadSupplierOrderShippingContacts(defaultBranch, agencyId, selectedContactId);
            } else if (!branches.length) {
                loadSupplierOrderShippingContacts(null, agencyId, selectedContactId);
            }
        })
        .fail(function () {
            branchSelect.prop('disabled', false).html('<option value="">Error al cargar sedes</option>').trigger('change.select2');
        });
}

function loadSupplierOrderShippingContacts(branchId = null, agencyId = null, selectedContactId = null) {
    const contactSelect = $('#supplier_order_shipping_agency_contact_id');
    let url = null;

    if (branchId) {
        url = window.routes.supplierOrderShippingBranchContacts.replace(':id', branchId);
    } else if (agencyId) {
        url = window.routes.supplierOrderShippingAgencyContacts.replace(':id', agencyId);
    }

    if (!url) {
        contactSelect.html('<option value="">Seleccione agencia primero</option>').trigger('change.select2');
        return;
    }

    contactSelect.prop('disabled', true).html('<option value="">Cargando contactos...</option>').trigger('change.select2');
    $('#supplier_order_shipping_contact_phone').val('');
    $('#supplier_order_shipping_contact_email').val('');

    $.get(url)
        .done(function (response) {
            const contacts = response.contacts || [];
            let options = '<option value="">Seleccione contacto</option>';

            contacts.forEach(function (contact) {
                const phone = [
                    contact.phone ? `Tel: ${contact.phone}` : '',
                    contact.whatsapp ? `WhatsApp: ${contact.whatsapp}` : ''
                ].filter(Boolean).join(' | ');

                options += `<option value="${escapeSupplierOrderHtml(contact.id)}"
                    data-phone="${escapeSupplierOrderHtml(phone)}"
                    data-email="${escapeSupplierOrderHtml(contact.email || '')}">
                    ${escapeSupplierOrderHtml(contact.contact_name || 'Contacto')} ${contact.is_primary ? '(Principal)' : ''}
                </option>`;
            });

            contactSelect.html(options).prop('disabled', false);

            const defaultContact = selectedContactId
                || contacts.find(contact => Boolean(contact.is_primary))?.id
                || (contacts.length === 1 ? contacts[0].id : null);

            if (defaultContact) {
                contactSelect.val(String(defaultContact));
            }

            contactSelect.trigger('change.select2');
            $('#supplier_order_shipping_contact_phone').val(contactSelect.find('option:selected').data('phone') || '');
            $('#supplier_order_shipping_contact_email').val(contactSelect.find('option:selected').data('email') || '');
        })
        .fail(function () {
            contactSelect.prop('disabled', false).html('<option value="">Error al cargar contactos</option>').trigger('change.select2');
        });
}

function scheduleSupplierOrderSourceAutoLoad() {
    clearTimeout(supplierOrderSourceLoadTimer);
}

function loadSupplierOrderSourceItems(options = {}) {
    const orderIds = $('#supplier_order_customer_purchase_order_ids').val() || [];
    const supplierId = $('#supplier_order_supplier_id').val();
    const isSilent = Boolean(options.silent);

    if (!orderIds.length) {
        Swal.fire({
            icon: 'warning',
            title: 'Seleccione al menos un pedido',
            text: 'Elija una o varias ordenes de compra del cliente para cargar sus items.'
        });
        return;
    }

    if (!supplierId) {
        if (!isSilent) {
            Swal.fire({
                icon: 'info',
                title: 'Seleccione un proveedor antes de cargar los articulos.'
            });
        }

        return;
    }

    if (supplierOrderSourceLoadRequest) {
        supplierOrderSourceLoadRequest.abort();
    }

    supplierOrderSourceLoadRequest = $.ajax({
        url: window.routes.supplierPurchaseOrderLoadCustomerItems,
        type: 'GET',
        data: {
            supplier_id: supplierId,
            customer_purchase_order_ids: orderIds,
            supplier_purchase_order_id: $('#supplier_purchase_order_id').val() || ''
        }
    })
        .done(function (response) {
            if (response.company_id) {
                $('#supplier_order_company_id').val(response.company_id).trigger('change');
            }

            if (response.currency_id) {
                $('#supplier_order_currency_id').val(response.currency_id).trigger('change');
            }

            renderSupplierOrderPendingItems(response.items || []);

            if (!isSilent) {
                if ((response.items || []).length) {
                    $('#supplierOrderPendingItemsModal').modal('show');
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin articulos pendientes',
                        text: 'No hay articulos pendientes para comprar en las ordenes cliente seleccionadas.'
                    });
                }
            }
        })
        .fail(function (xhr) {
            if (xhr.statusText === 'abort') {
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudieron cargar los items.'
            });
        })
        .always(function () {
            supplierOrderSourceLoadRequest = null;
        });
}

function clearSupplierOrderPendingItems() {
    $('#supplierOrderPendingCheckAll').prop('checked', false);
    $('#supplierOrderPendingItemsTbody').html(`
        <tr>
            <td colspan="13" class="text-center text-muted py-4">
                Sin items pendientes para mostrar.
            </td>
        </tr>
    `);
}

function renderSupplierOrderPendingItems(items) {
    const body = $('#supplierOrderPendingItemsTbody');
    $('#supplierOrderPendingCheckAll').prop('checked', false);

    if (!items.length) {
        clearSupplierOrderPendingItems();
        return;
    }

    body.html(items.map(function (item, index) {
        const pending = parseFloat(item.pending_quantity || item.suggested_quantity || item.quantity || 0) || 0;
        const quantity = parseFloat(item.suggested_quantity || pending) || 0;
        const unitPrice = parseFloat(item.unit_price || 0) || 0;
        const customerUnitPrice = parseFloat(item.customer_unit_price ?? 0) || 0;
        const total = quantity * unitPrice;

        return `
            <tr class="supplier-order-pending-item-row" data-index="${index}">
                <td class="text-center">
                    <input type="checkbox" class="pending-item-check" ${pending > 0 ? 'checked' : ''}>
                </td>
                <td>${escapeSupplierOrderHtml(item.customer_order_code || item.customer_purchase_order_code || '-')}</td>
                <td>
                    <div class="font-weight-bold">${escapeSupplierOrderHtml(item.article_code || '-')}</div>
                    <small class="text-muted">${escapeSupplierOrderHtml(item.article_name || item.billing_name_snapshot || '-')}</small>
                </td>
                <td>${escapeSupplierOrderHtml(item.presentation_name || '-')}</td>
                <td>${escapeSupplierOrderHtml(item.brand_name || '-')}</td>
                <td>${escapeSupplierOrderHtml(item.origin || '-')}</td>
                <td>${escapeSupplierOrderHtml(formatSupplierOrderDate(item.expiration_date) || '-')}</td>
                <td class="text-right">${formatSupplierOrderMoney(item.requested_quantity)}</td>
                <td class="text-right">${formatSupplierOrderMoney(item.purchased_quantity)}</td>
                <td class="text-right"><span class="pending-badge">${formatSupplierOrderMoney(pending)}</span></td>
                <td>
                    <input type="number" class="form-control form-control-sm text-right pending-item-quantity"
                        value="${formatSupplierOrderMoney(quantity)}" min="0.01" max="${formatSupplierOrderMoney(pending)}" step="0.01">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm text-right pending-item-unit-price"
                        value="${formatSupplierOrderUnitPrice(unitPrice)}" min="0" step="any" inputmode="decimal"
                        data-customer-item-id="${item.customer_purchase_order_item_id || ''}"
                        data-customer-unit-price="${customerUnitPrice}">
                    ${customerUnitPrice > 0
                        ? `<small class="text-muted d-block">Precio venta OC Cliente: ${escapeSupplierOrderHtml(supplierOrderCurrencyLabel())} ${formatSupplierOrderMoney(customerUnitPrice)}</small>`
                        : ''}
                </td>
                <td class="text-right font-weight-bold pending-item-total">${formatSupplierOrderMoney(total)}</td>
            </tr>
        `;
    }).join(''));

    body.find('.supplier-order-pending-item-row').each(function () {
        const row = $(this);
        row.data('item', items[Number(row.data('index'))] || {});
    });
}

function updateSupplierOrderPendingItemTotal(row) {
    const quantityInput = row.find('.pending-item-quantity');
    const pending = parseFloat(quantityInput.attr('max')) || 0;
    let quantity = parseFloat(quantityInput.val()) || 0;
    const priceInput = row.find('.pending-item-unit-price');
    const unitPrice = parseFloat(priceInput.val()) || 0;
    const customerUnitPrice = parseFloat(priceInput.data('customer-unit-price')) || 0;
    priceInput.toggleClass('is-invalid', customerUnitPrice > 0 && unitPrice > customerUnitPrice);

    if (pending > 0 && quantity > pending) {
        quantity = pending;
        quantityInput.val(formatSupplierOrderMoney(quantity));
    }

    row.find('.pending-item-total').text(formatSupplierOrderMoney(quantity * unitPrice));
}

function addSelectedSupplierPendingItems() {
    const selectedRows = $('#supplierOrderPendingItemsTbody .supplier-order-pending-item-row')
        .filter(function () {
            return $(this).find('.pending-item-check').is(':checked');
        });

    if (!selectedRows.length) {
        Swal.fire({
            icon: 'warning',
            title: 'Seleccione articulos',
            text: 'Marque al menos un item pendiente para agregarlo a la orden.'
        });
        return;
    }

    let hasInvalidQuantity = false;
    let priceViolation = null;
    selectedRows.each(function () {
        const row = $(this);
        const quantity = parseFloat(row.find('.pending-item-quantity').val()) || 0;
        const pending = parseFloat(row.find('.pending-item-quantity').attr('max')) || 0;

        if (quantity <= 0 || quantity > pending) {
            hasInvalidQuantity = true;
        }

        const priceInput = row.find('.pending-item-unit-price');
        const price = parseFloat(priceInput.val()) || 0;
        const maxPrice = parseFloat(priceInput.data('customer-unit-price')) || 0;
        if (!priceViolation && maxPrice > 0 && price > maxPrice) {
            const item = row.data('item') || {};
            priceViolation = {
                input: priceInput,
                article: item.article_name || item.billing_name_snapshot || item.article_code || 'seleccionado',
                maxPrice: maxPrice
            };
        }
    });

    if (hasInvalidQuantity) {
        Swal.fire({
            icon: 'warning',
            title: 'Cantidad no valida',
            text: 'La cantidad a comprar debe ser mayor a cero y no superar el saldo pendiente.'
        });
        return;
    }

    if (priceViolation) {
        showSupplierOrderPriceViolation(priceViolation);
        return;
    }

    selectedRows.each(function () {
        const row = $(this);
        const item = { ...(row.data('item') || {}) };
        item.quantity = parseFloat(row.find('.pending-item-quantity').val()) || 0;
        item.unit_price = parseFloat(row.find('.pending-item-unit-price').val()) || 0;
        item.reference_purchase_price = item.reference_purchase_price || item.unit_price;

        removeSupplierOrderItemByCustomerItem(item.customer_purchase_order_item_id);
        addSupplierOrderItemRow(item);
    });

    $('#supplierOrderPendingItemsModal').modal('hide');
    calculateSupplierOrderTotals();
}

function removeSupplierOrderItemByCustomerItem(customerPurchaseOrderItemId) {
    if (!customerPurchaseOrderItemId) {
        return;
    }

    $('#supplierOrderItemsTbody tr.supplier-order-item-row').each(function () {
        const row = $(this);

        if (String(row.find('.item-customer-purchase-order-item-id').val()) === String(customerPurchaseOrderItemId)) {
            destroySupplierOrderRowSelect2(row);
            row.remove();
        }
    });

    refreshSupplierOrderItemIndexes();
    showEmptySupplierOrderItemsRow();
}

function addSupplierOrderItemRow(data = {}) {
    $('#supplierOrderItemsEmptyRow').remove();

    const html = $('#supplierOrderItemRowTemplate')
        .html()
        .replaceAll('__INDEX__', supplierOrderItemIndex);

    $('#supplierOrderItemsTbody').append(html);

    const row = $('#supplierOrderItemsTbody tr.supplier-order-item-row').last();

    row.find('.item-id').val(data.id || '');
    row.find('.item-market-study-item-id').val(data.market_study_item_id || '');
    row.find('.item-quote-item-id').val(data.quote_item_id || '');
    row.find('.item-customer-purchase-order-item-id').val(data.customer_purchase_order_item_id || '');
    row.find('.item-article-id').val(data.article_id || '');
    row.find('.item-article-code').val(data.article_code || '');
    row.find('.item-article-picker').val(data.article_id || '');
    row.find('.item-billing-name').val(data.billing_name_snapshot || '');
    row.find('.item-note').val(data.note || '');
    row.find('.item-unit-id').val(data.unit_id || '');
    row.find('.item-presentation-id').val(data.presentation_id || '');
    row.find('.item-brand-id').val(data.brand_id || '');
    row.find('.item-origin').val(data.origin || '');
    row.find('.item-expiration-date').val(formatSupplierOrderDate(data.expiration_date));
    row.find('.item-cost-type').val(data.cost_type || 'PESO');
    row.find('.item-reference-purchase-price').val(formatSupplierOrderUnitPrice(data.reference_purchase_price || 0));
    const customerUnitPrice = parseFloat(
        data.customer_unit_price ?? data.customer_purchase_order_item?.unit_price ?? 0
    ) || 0;
    row.find('.item-customer-unit-price').val(customerUnitPrice > 0 ? formatSupplierOrderMoney(customerUnitPrice) : '');
    row.find('.item-quantity')
        .val(formatSupplierOrderMoney(data.quantity || 1))
        .attr('max', data.pending_quantity ? formatSupplierOrderMoney(data.pending_quantity) : null);
    row.find('.item-unit-price')
        .val(formatSupplierOrderUnitPrice(data.unit_price || 0))
        .attr('data-customer-item-id', data.customer_purchase_order_item_id || '')
        .attr('data-customer-unit-price', customerUnitPrice > 0 ? customerUnitPrice : '');
    row.find('.item-max-price-reference')
        .toggleClass('d-none', customerUnitPrice <= 0)
        .text(customerUnitPrice > 0
            ? `Precio venta OC Cliente: ${supplierOrderCurrencyLabel()} ${formatSupplierOrderMoney(customerUnitPrice)}`
            : '');

    initSupplierOrderSelect2(row);

    supplierOrderItemIndex++;
    refreshSupplierOrderItemIndexes();
    calculateSupplierOrderTotals();
    updateSupplierOrderFormSummary();
}

function supplierOrderCurrencyLabel() {
    const option = $('#supplier_order_currency_id option:selected');
    return option.data('symbol') || option.data('code') || option.text().split('|')[0].trim() || 'S/';
}

function findSupplierOrderPriceViolation() {
    let violation = null;

    $('#supplierOrderItemsTbody tr.supplier-order-item-row').each(function () {
        const row = $(this);
        const input = row.find('.item-unit-price');
        const price = parseFloat(input.val()) || 0;
        const maxPrice = parseFloat(input.attr('data-customer-unit-price')) || 0;

        input.removeClass('is-invalid');
        if (!violation && maxPrice > 0 && price > maxPrice) {
            violation = {
                input: input,
                article: row.find('.item-billing-name').val() || row.find('.item-article-code').val() || 'seleccionado',
                maxPrice: maxPrice
            };
        }
    });

    return violation;
}

function showSupplierOrderPriceViolation(violation) {
    const message = `El precio de compra del artículo ${violation.article} no puede ser mayor al precio de la Orden de Compra del Cliente. Precio cliente: ${supplierOrderCurrencyLabel()} ${formatSupplierOrderMoney(violation.maxPrice)}.`;
    violation.input.addClass('is-invalid').trigger('focus');
    showSupplierOrderTab('items');
    violation.input.closest('td').find('.invalid-feedback').text(message);
    Swal.fire({ icon: 'warning', title: 'Precio no permitido', text: message });
}

function applySelectedSupplierOrderArticle(row) {
    const option = row.find('.item-article-picker option:selected');
    const articleId = option.val() || '';

    row.find('.item-article-id').val(articleId);
    row.find('.item-article-code').val(option.data('code') || '');

    if (articleId && !row.find('.item-billing-name').val()) {
        row.find('.item-billing-name').val(option.data('billing-name') || '');
    }

    if (articleId) {
        row.find('.item-unit-id').val(option.data('unit-id') || '').trigger('change.select2');
        row.find('.item-presentation-id').val(option.data('presentation-id') || '').trigger('change.select2');
        row.find('.item-brand-id').val(option.data('brand-id') || '').trigger('change.select2');
    }
}

function clearSupplierOrderItemRows() {
    $('#supplierOrderItemsTbody tr.supplier-order-item-row').each(function () {
        destroySupplierOrderRowSelect2($(this));
    });

    $('#supplierOrderItemsTbody').empty();
    supplierOrderItemIndex = 0;
}

function refreshSupplierOrderItemIndexes() {
    $('#supplierOrderItemsTbody tr.supplier-order-item-row').each(function (index) {
        const row = $(this);
        row.find('.supplier-order-item-index').text(index + 1);

        row.find('[name]').each(function () {
            this.name = this.name.replace(/items\[\d+]\[/, `items[${index}][`);
        });
    });

    supplierOrderItemIndex = $('#supplierOrderItemsTbody tr.supplier-order-item-row').length;
}

function showEmptySupplierOrderItemsRow() {
    if ($('#supplierOrderItemsTbody tr.supplier-order-item-row').length === 0) {
        $('#supplierOrderItemsTbody').html(`
            <tr id="supplierOrderItemsEmptyRow">
                <td colspan="17" class="text-center text-muted py-4">
                    <i class="fas fa-box-open d-block mb-2"></i>
                    No hay items registrados.
                </td>
            </tr>
        `);
    }
}

function calculateSupplierOrderTotals() {
    let subtotal = 0;
    let igv = 0;
    const affectIgv = $('#supplier_order_affect_igv').val() === '1';

    $('#supplierOrderItemsTbody tr.supplier-order-item-row').each(function () {
        const row = $(this);
        const quantityInput = row.find('.item-quantity');
        const maxQuantity = parseFloat(quantityInput.attr('max')) || 0;
        let quantity = parseFloat(quantityInput.val()) || 0;
        const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
        const priceInput = row.find('.item-unit-price');
        const customerUnitPrice = parseFloat(priceInput.attr('data-customer-unit-price')) || 0;
        const exceedsCustomerPrice = customerUnitPrice > 0 && unitPrice > customerUnitPrice;
        priceInput.toggleClass('is-invalid', exceedsCustomerPrice);
        priceInput.closest('td').find('.invalid-feedback').text(exceedsCustomerPrice
            ? `Precio cliente: ${supplierOrderCurrencyLabel()} ${formatSupplierOrderMoney(customerUnitPrice)}.`
            : '');

        if (maxQuantity > 0 && quantity > maxQuantity) {
            quantity = maxQuantity;
            quantityInput.val(formatSupplierOrderMoney(quantity));
        }

        const lineTotal = quantity * unitPrice;
        const lineSubtotal = affectIgv
            ? lineTotal / 1.18
            : lineTotal;
        const taxAmount = affectIgv
            ? lineTotal - lineSubtotal
            : 0;

        row.find('.item-line-total').val(formatSupplierOrderDecimal(lineTotal));
        row.find('.item-taxable-base').val(formatSupplierOrderDecimal(lineSubtotal));
        row.find('.item-igv-percent').val(formatSupplierOrderMoney(affectIgv ? 18 : 0));
        row.find('.item-igv-amount').val(formatSupplierOrderDecimal(taxAmount));
        subtotal += lineSubtotal;
        igv += taxAmount;
    });

    const grandTotal = subtotal + igv;

    setSupplierOrderValue('#supplier_order_subtotal', formatSupplierOrderMoney(subtotal));
    setSupplierOrderValue('#supplier_order_igv', formatSupplierOrderMoney(igv));
    setSupplierOrderValue('#supplier_order_grand_total', formatSupplierOrderMoney(grandTotal));
    $('#supplierOrderSideGrandTotal').text(formatSupplierOrderMoney(grandTotal));
    updateSupplierOrderFinancialSummary();
}

function loadSupplierPurchaseOrderForEdit(id) {
    clearSupplierPurchaseOrderErrors();

    $.get(`${window.routes.supplierPurchaseOrderShow}/${id}`)
        .done(function (response) {
            fillSupplierPurchaseOrderForm(response.data);
            $('#supplierPurchaseOrderModalLabel').text('Editar Orden de Compra a Proveedor');
            $('#btnSaveSupplierPurchaseOrder').html('<i class="fas fa-save mr-1"></i> Actualizar');
            $('#supplierPurchaseOrderModal').modal('show');
        })
        .fail(function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo cargar la orden.'
            });
        });
}

function fillSupplierPurchaseOrderForm(order) {
    resetSupplierPurchaseOrderForm();
    supplierOrderPaymentBaseDate = supplierOrderDateFromInput(order.created_at) || new Date();
    $('#supplierOrderPaymentDueDateHelp').text('La fecha de vencimiento se calcula desde la fecha de registro de la orden.');

    $('#supplier_purchase_order_id').val(order.id || '');
    $('#supplier_order_code').val(order.code || '');
    const modalStatus = supplierOrderModalStatusPresentation(order.status);
    $('#supplierOrderSideStatus').attr('class', `badge ${modalStatus[1]} px-2 py-1 mb-2`).text(modalStatus[0]);
    const createdDate = String(order.created_at || '').slice(0, 10).split('-').reverse().join('/');
    $('#supplierOrderSideDate').text(createdDate || new Intl.DateTimeFormat('es-PE').format(new Date()));
    $('#supplier_order_company_id').val(order.company_id || '').trigger('change.select2');
    $('#supplier_order_supplier_id').val(order.supplier_id || '').trigger('change.select2');
    const supplierAccountsRequest = loadSupplierAccounts(order.supplier_id, order.supplier_account_id, { suppressInstructionSync: true });
    $('#supplier_order_currency_id').val(order.currency_id || '').trigger('change');
    $('#supplier_order_payment_currency_id').val(order.payment_currency_id || order.currency_id || '').trigger('change.select2');
    $('#supplier_order_new_advance_payment_currency_id').val(order.payment_currency_id || order.currency_id || '').trigger('change');
    $('#supplier_order_exchange_rate').val(order.exchange_rate || '');
    $('#supplier_order_apply_advance').prop('checked', Boolean(order.apply_advance));
    $('#supplier_order_advance_type').val(order.advance_type || '');
    $('#supplier_order_advance_percentage').val(order.advance_percentage || '');
    $('#supplier_order_advance_amount').val(order.advance_type === 'fixed_amount' ? (order.advance_amount || '') : '');
    renderSupplierOrderAdvancePayments(order.advance_payments || []);
    const relatedCustomerOrders = (order.customer_purchase_orders || []).length
        ? order.customer_purchase_orders
        : (order.customer_purchase_order ? [order.customer_purchase_order] : []);
    const customerPurchaseOrderIds = relatedCustomerOrders
        .map(customerOrder => String(customerOrder.id));
    relatedCustomerOrders.forEach(function (customerOrder) {
        const select = $('#supplier_order_customer_purchase_order_ids');

        if (!select.find(`option[value="${customerOrder.id}"]`).length) {
            const customerName = customerOrder.customer?.business_name
                || customerOrder.customer?.full_name
                || [customerOrder.customer?.first_name, customerOrder.customer?.last_name].filter(Boolean).join(' ')
                || 'Sin cliente';
            const orderNumber = customerOrder.purchase_order_number || customerOrder.code;
            const currency = customerOrder.currency?.symbol || '';
            const total = Number(customerOrder.grand_total || 0).toFixed(2);
            select.append(new Option(`${orderNumber} | ${customerName} | ${currency} ${total}`.trim(), customerOrder.id));
        }
    });
    $('#supplier_order_customer_purchase_order_ids')
        .val(customerPurchaseOrderIds.length ? customerPurchaseOrderIds : (order.customer_purchase_order_id ? [String(order.customer_purchase_order_id)] : []))
        .trigger('change.select2');
    $('#supplier_order_type').val(order.order_type || 'articles');
    const paymentCondition = normalizeSupplierOrderPaymentCondition(order.payment_condition);
    const creditDays = Number(order.credit_days) || supplierOrderCreditDaysFromCondition(order.payment_condition);
    $('#supplier_order_payment_condition').val(paymentCondition).trigger('change');
    $('#supplier_order_credit_days').val(paymentCondition === 'credito' && creditDays > 0 ? creditDays : '');
    $('#supplier_order_payment_due_date').val(
        paymentCondition === 'credito' ? formatSupplierOrderDateInput(order.payment_due_date) : ''
    );
    if (paymentCondition === 'credito' && !$('#supplier_order_payment_due_date').val()) {
        calculateSupplierOrderPaymentDueDate();
    }
    $('#supplier_order_delivery_type')
        .val(normalizeSupplierOrderOption(order.delivery_type || ''))
        .trigger('change.select2');
    toggleSupplierOrderShippingAgencySection();
    $('#supplier_order_shipping_agency_id').val(order.shipping_agency_id || '').trigger('change.select2');
    if (order.shipping_agency_id) {
        loadSupplierOrderShippingBranches(
            order.shipping_agency_id,
            order.shipping_agency_branch_id,
            order.shipping_agency_contact_id
        );
    }
    $('#supplier_order_shipping_reference').val(order.shipping_reference || '');
    $('#supplier_order_transport_type')
        .val(normalizeSupplierOrderOption(order.transport_type || ''))
        .trigger('change.select2');
    $('#supplier_order_shipping_address').val(order.shipping_address || '');
    $('#supplier_order_destination_ubigeo_id').val(order.destination_ubigeo_id || '').trigger('change.select2');
    $('#supplier_order_destination_text').val(order.destination_text || '');
    $('#supplier_order_payment_method')
        .val(normalizeSupplierOrderOption(order.payment_method || ''))
        .trigger('change.select2');
    $('#supplier_order_document_type')
        .val(normalizeSupplierOrderOption(order.document_type || ''))
        .trigger('change.select2');
    $('#supplier_order_affect_igv').val(order.affect_igv ? '1' : '0').trigger('change.select2');
    $('#supplier_order_observations').val(order.observations || '');
    $('#supplier_order_request_department').val(order.request_department || 'COMPRAS');
    $('#supplier_order_authorized_by_name').val(order.authorized_by_name || 'IVAN CUBAS BINCES');
    $('#supplier_order_authorized_by_position').val(order.authorized_by_position || 'GERENTE GENERAL');
    $('#supplier_order_delivery_text').val(order.delivery_text || 'EN AGENCIA DE TRANSPORTES - ENVIO A PROVINCIA');
    $('#supplier_order_purchase_instructions')
        .val(order.purchase_instructions || '')
        .data(
            'last-auto-value',
            isDefaultPurchaseInstructionText(order.purchase_instructions) ? order.purchase_instructions : ''
        );
    $('#supplier_order_important_note').val(order.important_note || defaultSupplierOrderImportantNote);
    renderExistingSupplierOrderDocuments(order.supplier_documents || []);
    applySupplierOrderCompanyDefaults();
    $('#supplierOrderSideSupplier').text(supplierName(order.supplier));

    clearSupplierOrderItemRows();
    (order.items || []).forEach(addSupplierOrderItemRow);
    showEmptySupplierOrderItemsRow();
    calculateSupplierOrderTotals();
    updateSupplierOrderFinancialSummary();
    loadSupplierOrderAdvanceBankAccounts();

    if (supplierAccountsRequest && typeof supplierAccountsRequest.always === 'function') {
        supplierAccountsRequest.always(function () {
            syncPurchaseInstructions(true);
        });
    } else {
        syncPurchaseInstructions(true);
    }
}

function loadSupplierPurchaseOrderDetail(id) {
    $.get(`${window.routes.supplierPurchaseOrderShow}/${id}`)
        .done(function (response) {
            fillSupplierPurchaseOrderDetail(response.data);
            $('#viewSupplierPurchaseOrderModal').modal('show');
        })
        .fail(function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo cargar el detalle.'
            });
        });
}

function fillSupplierPurchaseOrderDetail(order) {
    const statuses = {
        registered: ['REGISTRADO', 'badge-primary'],
        draft: ['REGISTRADO', 'badge-primary'],
        sent: ['ENVIADO', 'badge-info'],
        approved: ['APROBADO', 'badge-success'],
        received: ['INGRESADO', 'badge-success'],
        partial_entered: ['INGRESO PARCIAL', 'badge-warning text-dark'],
        entered: ['INGRESADO', 'badge-success'],
        cancelled: ['CANCELADO', 'badge-danger'],
        invoiced: ['FACTURADO', 'badge-info']
    };
    const status = statuses[order.status] || [String(order.status || '').toUpperCase(), 'badge-secondary'];
    const currencyCode = order.currency?.code || '';
    const currencySymbol = order.currency?.symbol || '';
    const account = order.supplier_account
        ? `${order.supplier_account.bank?.description || 'Banco'} - ${order.supplier_account.account_number}`
        : '-';
    const destination = [
        order.destination_ubigeo
            ? `${order.destination_ubigeo.department}/${order.destination_ubigeo.province}/${order.destination_ubigeo.district}`
            : '',
        order.destination_text || ''
    ].filter(Boolean).join(' | ') || '-';

    $('#vspo_code').text(order.code || '-');
    $('#vspo_status').text(status[0]).attr('class', `badge ${status[1]} rounded-pill px-3 py-2 shadow-sm`);
    $('#vspo_supplier').text(supplierName(order.supplier));
    $('#vspo_company').text(order.company?.trade_name || order.company?.business_name || '-');
    $('#vspo_currency_symbol').text(currencySymbol);
    $('#vspo_grand_total').text(formatSupplierOrderMoney(order.grand_total));
    $('#vspo_supplier_account').text(account);
    const customerOrders = order.customer_purchase_orders?.length
        ? order.customer_purchase_orders
        : (order.customer_purchase_order ? [order.customer_purchase_order] : []);
    $('#vspo_customer_orders').html(customerOrders.length
        ? customerOrders.map(customerOrder => {
            const customerName = customerOrder.customer?.business_name
                || customerOrder.customer?.full_name
                || [customerOrder.customer?.first_name, customerOrder.customer?.last_name].filter(Boolean).join(' ')
                || 'Sin cliente';
            const companyName = customerOrder.company?.trade_name || customerOrder.company?.business_name || '-';
            const customerOrderCurrency = customerOrder.currency?.symbol || customerOrder.currency?.code || '';
            const customerOrderStatus = supplierCustomerOrderStatusPresentation(customerOrder.status);

            return `<div class="supplier-order-related-card">
                <div class="d-flex justify-content-between align-items-start flex-wrap">
                    <strong><i class="fas fa-clipboard-check mr-1"></i>${escapeSupplierOrderHtml(customerOrder.purchase_order_number || customerOrder.code || '-')}</strong>
                    <span class="badge ${customerOrderStatus.className}">${customerOrderStatus.label}</span>
                </div>
                <small><i class="fas fa-user mr-1"></i>${escapeSupplierOrderHtml(customerName)}</small>
                <small><i class="fas fa-building mr-1"></i>${escapeSupplierOrderHtml(companyName)}</small>
                <small><i class="fas fa-coins mr-1"></i>${escapeSupplierOrderHtml(customerOrderCurrency)} ${formatSupplierOrderMoney(customerOrder.grand_total)}</small>
            </div>`;
        }).join('')
        : '<span class="text-muted">Sin OC cliente relacionada.</span>');
    $('#vspo_currency').text([currencyCode, order.currency?.description].filter(Boolean).join(' | ') || '-');
    $('#vspo_payment_condition').text(supplierOrderPaymentConditionLabel(order));
    $('#vspo_delivery_type').text(supplierOrderOptionLabel(order.delivery_type) || '-');
    $('#vspo_transport_type').text(supplierOrderOptionLabel(order.transport_type) || '-');
    $('#vspo_document_type').text(supplierOrderOptionLabel(order.document_type) || '-');
    $('#vspo_payment_method').text(supplierOrderOptionLabel(order.payment_method) || '-');
    $('#vspo_affect_igv').text(order.affect_igv ? 'SI' : 'NO');
    $('#vspo_destination').text(destination);
    $('#vspo_shipping_address').text(order.shipping_address || '-');
    $('#vspo_observations').text(order.observations || 'Sin observaciones');
    const agency = order.shipping_agency;
    const branch = order.shipping_agency_branch;
    const contact = order.shipping_agency_contact;
    const branchLocation = branch
        ? [branch.address, [branch.district, branch.province, branch.department].filter(Boolean).join(' / ')].filter(Boolean).join(' | ')
        : '-';
    const contactPhone = contact
        ? [contact.phone ? `Tel: ${contact.phone}` : '', contact.whatsapp ? `WhatsApp: ${contact.whatsapp}` : '', contact.email ? `Correo: ${contact.email}` : ''].filter(Boolean).join(' | ')
        : '-';

    $('#vspo_shipping_agency_card').toggleClass('d-none', !supplierOrderRequiresShippingAgency(order.delivery_type));
    $('#vspo_shipping_agency').text(agency ? `${agency.ruc ? agency.ruc + ' | ' : ''}${agency.trade_name || agency.business_name || '-'}` : '-');
    $('#vspo_shipping_branch').text(branch ? `${branch.branch_name || '-'} | ${branchLocation}` : '-');
    $('#vspo_shipping_contact').text(contact ? contact.contact_name || '-' : '-');
    $('#vspo_shipping_contact_phone').text(contactPhone || '-');
    $('#vspo_shipping_contact_email').text(contact?.email || '-');
    $('#vspo_shipping_reference').text(order.shipping_reference || branch?.reference || '-');
    $('#vspo_requested_by').text(supplierOrderRequestedBy(order) || '-');
    $('#vspo_request_department').text(order.request_department || '-');
    $('#vspo_authorized_by_name').text(order.authorized_by_name || '-');
    $('#vspo_authorized_by_position').text(order.authorized_by_position || '-');
    $('#vspo_delivery_text').text(order.delivery_text || '-');
    $('#vspo_payment_terms_text').text(supplierOrderPaymentTerms(order) || '-');
    $('#vspo_purchase_instructions').text(order.purchase_instructions || '-');
    $('#vspo_important_note').text(order.important_note || '-');
    $('#vspo_subtotal').text(`${currencyCode} ${formatSupplierOrderMoney(order.subtotal)}`);
    $('#vspo_igv').text(`${currencyCode} ${formatSupplierOrderMoney(order.igv)}`);
    $('#vspo_total').text(`${currencyCode} ${formatSupplierOrderMoney(order.grand_total)}`);

    const supplierDocuments = order.supplier_documents || [];
    $('#vspo_documents').html(supplierDocuments.length
        ? supplierDocuments.map(document => `
            <div class="d-flex justify-content-between align-items-center border rounded px-2 py-2 mb-2 bg-white">
                <div>
                    <strong>${escapeSupplierOrderHtml(document.document_type?.description || 'Documento del proveedor')}</strong>
                    <small class="text-muted d-block">${escapeSupplierOrderHtml(document.original_name || '-')}${document.observation ? ` · ${escapeSupplierOrderHtml(document.observation)}` : ''}</small>
                </div>
                <a href="${escapeSupplierOrderHtml(document.view_url)}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-external-link-alt mr-1"></i>Abrir
                </a>
            </div>
        `).join('')
        : '<span class="text-muted">Sin documentos adjuntos.</span>');

    const rows = (order.items || []).map(function (item, index) {
        const orderedQuantity = item.ordered_quantity ?? item.quantity ?? 0;
        const enteredQuantity = item.entered_quantity ?? 0;
        const pendingQuantity = item.pending_quantity ?? orderedQuantity;
        const entryStatus = supplierOrderEntryStatusPresentation(item.entry_status, enteredQuantity, pendingQuantity);

        return `
            <tr>
                <td class="text-center">${index + 1}</td>
                <td>${escapeSupplierOrderHtml(item.article_code || '-')}</td>
                <td class="supplier-order-item-name">
                    ${escapeSupplierOrderHtml(item.billing_name_snapshot || '-')}
                    ${item.note ? `<div class="text-muted font-weight-normal">${escapeSupplierOrderHtml(item.note)}</div>` : ''}
                </td>
                <td>${escapeSupplierOrderHtml(item.unit?.abbreviation || item.unit?.description || '-')}</td>
                <td>${escapeSupplierOrderHtml(item.presentation?.description || '-')}</td>
                <td>${escapeSupplierOrderHtml(item.brand?.description || '-')}</td>
                <td>${escapeSupplierOrderHtml(item.origin || '-')}</td>
                <td class="text-right">${formatSupplierOrderMoney(orderedQuantity)}</td>
                <td class="text-right">${formatSupplierOrderMoney(enteredQuantity)}</td>
                <td class="text-right">${formatSupplierOrderMoney(pendingQuantity)}</td>
                <td class="text-center">
                    <span class="supplier-order-entry-status ${entryStatus.className}">
                        <i class="${entryStatus.icon} mr-1"></i>${entryStatus.label}
                    </span>
                </td>
                <td class="text-right">${formatSupplierOrderMoney(item.reference_purchase_price)}</td>
                <td class="text-right">${formatSupplierOrderDecimal(item.unit_price)}</td>
                <td class="text-right font-weight-bold">${formatSupplierOrderDecimal(item.total_with_igv ?? item.line_total)}</td>
                <td class="text-right">${formatSupplierOrderDecimal(item.taxable_base ?? item.subtotal)}</td>
                <td class="text-right">${formatSupplierOrderMoney(item.igv_percent ?? (order.affect_igv ? 18 : 0))}</td>
                <td class="text-right">${formatSupplierOrderDecimal(item.igv_amount ?? item.tax_amount)}</td>
            </tr>
        `;
    }).join('');

    $('#vspo_items_body').html(
        rows || '<tr><td colspan="17" class="text-center text-muted py-3">Sin items registrados</td></tr>'
    );
}

function deleteSupplierPurchaseOrder(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Eliminar orden de compra',
        text: 'La orden quedara eliminada de forma logica.',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: `${window.routes.supplierPurchaseOrderDelete}/${id}`,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: function (response) {
                tableSupplierPurchaseOrder.ajax.reload(null, false);
                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Orden eliminada correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500
                });
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'No se pudo eliminar la orden.'
                });
            }
        });
    });
}

function clearSupplierPurchaseOrderErrors() {
    $('#supplierPurchaseOrderForm .is-invalid').removeClass('is-invalid');
    $('#supplierPurchaseOrderForm .select2-selection').removeClass('border-danger');
    $('#supplierPurchaseOrderForm .invalid-feedback').text('');
    $('#supplierPurchaseOrderErrors').addClass('d-none').empty();
    $('#supplierPurchaseOrderModal .supplier-order-tab-error').addClass('d-none');
}

function supplierOrderSectionForField(field) {
    if (/^(financial_terms|payment_currency_id|apply_exchange_rate|exchange_rate|apply_advance|advance_|advance_payments)/.test(field)) return 'finance';
    if (/^(transport_type|delivery_type|shipping_|destination_)/.test(field)) return 'logistics';
    if (/^supplier_documents/.test(field)) return 'documents';
    if (/^items/.test(field)) return 'items';
    if (/^(request_department|authorized_by_|delivery_text|purchase_instructions|important_note)/.test(field)) return 'pdf';
    return 'data';
}

function showSupplierPurchaseOrderErrors(errors) {
    const errorMessages = [];
    let firstInvalidPane = null;

    Object.entries(errors).forEach(function ([field, fieldMessages]) {
        const normalizedField = field.replace(/\.\d+$/, '');
        const bracketField = field.replace(/\.([^.]+)/g, '[$1]');
        let input = $(`[name="${bracketField}"]`);
        const message = fieldMessages[0];

        if (!input.length) {
            input = $(`[name="${normalizedField}"]`);
        }
        if (!input.length) {
            input = $(`[name="${normalizedField}[]"]`);
        }

        if (input.length) {
            input.addClass('is-invalid');

            if (input.hasClass('select2-hidden-accessible')) {
                input.next('.select2-container').find('.select2-selection').addClass('border-danger');
            }

            input.closest('.form-group, td').find('.invalid-feedback').first().text(message);
        }

        const pane = input.length ? input.closest('.tab-pane') : $();
        const section = pane.length
            ? $(`#supplierPurchaseOrderModal .supplier-order-form-tabs a[href="#${pane.attr('id')}"]`).data('section')
            : supplierOrderSectionForField(field);
        const tab = $(`#supplierPurchaseOrderModal .supplier-order-form-tabs a[data-section="${section}"]`);
        tab.find('.supplier-order-tab-error').removeClass('d-none');
        if (!firstInvalidPane && tab.attr('href')) firstInvalidPane = tab.attr('href').slice(1);

        errorMessages.push(message);
    });

    $('#supplierPurchaseOrderErrors')
        .removeClass('d-none')
        .html(`<ul class="mb-0 pl-3">${errorMessages.map(
            message => `<li>${escapeSupplierOrderHtml(message)}</li>`
        ).join('')}</ul>`);

    if (firstInvalidPane) {
        $(`#supplierPurchaseOrderModal .supplier-order-form-tabs a[href="#${firstInvalidPane}"]`).tab('show');
    }
}

function updateSupplierOrderCurrency() {
    const selected = $('#supplier_order_currency_id option:selected');
    const code = selected.data('code') || 'PEN';
    const symbol = selected.data('symbol') || 'S/';

    $('.supplier-order-currency-code').text(code);
    $('.supplier-order-currency-symbol').text(symbol);
    $('#supplier_order_purchase_currency_label').val(selected.text().trim() || '-');
    syncSupplierOrderPaymentCurrency();
    loadSupplierOrderAdvanceBankAccounts();
    updateSupplierOrderFinancialSummary();
}

function syncSupplierOrderPaymentCurrency() {
    const paymentCurrency = $('#supplier_order_payment_currency_id');
    if (!paymentCurrency.val()) {
        paymentCurrency.val($('#supplier_order_currency_id').val() || '').trigger('change.select2');
    }
    const newPaymentCurrency = $('#supplier_order_new_advance_payment_currency_id');
    if (!newPaymentCurrency.val()) {
        newPaymentCurrency.val(paymentCurrency.val() || $('#supplier_order_currency_id').val() || '').trigger('change');
    }
}

function supplierOrderFinancialCurrency(selector) {
    const option = $(`${selector} option:selected`);
    return {
        id: option.val() || '',
        code: String(option.data('code') || 'PEN').toUpperCase(),
        symbol: option.data('symbol') || (String(option.data('code')).toUpperCase() === 'PEN' ? 'S/' : option.data('code'))
    };
}

function resetSupplierOrderAdvanceBankAccountSelect(message, helpClass = '', disabled = true) {
    const select = $('#supplier_order_new_advance_bank_account_id');
    if (!select.length) return;
    select
        .empty()
        .append(new Option(message, ''))
        .val('')
        .prop('disabled', disabled)
        .removeData('loaded-key loading-key')
        .trigger('change.select2');
    $('#supplierOrderAdvanceAccountHelp')
        .removeClass('is-empty is-ready')
        .addClass(helpClass)
        .text(message);
}

function supplierOrderAdvanceBankAccountKey() {
    const companyId = String($('#supplier_order_company_id').val() || '');
    const currencyId = String($('#supplier_order_new_advance_payment_currency_id').val() || '');

    return companyId && currencyId ? `${companyId}:${currencyId}` : '';
}

function ensureSupplierOrderAdvanceBankAccountsLoaded() {
    const select = $('#supplier_order_new_advance_bank_account_id');
    const key = supplierOrderAdvanceBankAccountKey();
    if (!select.length || !key) return;
    if (select.data('loaded-key') === key || select.data('loading-key') === key) return;

    loadSupplierOrderAdvanceBankAccounts();
}

function supplierOrderBankAccountOptionTemplate(data) {
    if (!data.id || !data.element) return data.text;
    const option = $(data.element);
    const balance = Number(option.attr('data-balance') || 0);
    const balanceClass = balance < 0 ? 'is-negative' : (balance > 0 ? 'is-positive' : 'is-zero');
    const heading = $('<div>', { class: 'supplier-order-bank-option-heading' })
        .append($('<strong>').text(option.attr('data-company-name') || '-'))
        .append($('<span>').text(option.attr('data-bank-name') || '-'));
    const details = $('<div>', { class: 'supplier-order-bank-option-details' })
        .append($('<span>').text(option.attr('data-currency-code') || '-'))
        .append($('<span>').text(option.attr('data-account-number') || '-'))
        .append($('<b>', { class: `supplier-order-bank-balance ${balanceClass}` })
            .text(`Saldo: ${option.attr('data-balance-label') || '-'}`));

    return $('<div>', { class: 'supplier-order-bank-option' }).append(heading, details);
}

function supplierOrderBankAccountSelectionTemplate(data) {
    if (!data.id || !data.element) return data.text || 'Seleccione cuenta bancaria';
    const option = $(data.element);
    const balance = Number(option.attr('data-balance') || 0);
    const balanceClass = balance < 0 ? 'is-negative' : (balance > 0 ? 'is-positive' : 'is-zero');
    const accountSummary = [
        option.attr('data-company-name'),
        option.attr('data-bank-name'),
        option.attr('data-currency-code'),
        option.attr('data-account-number')
    ].filter(Boolean).join(' · ');

    return $('<span>', { class: 'supplier-order-bank-selection' })
        .append($('<span>').text(accountSummary || data.text))
        .append($('<b>', { class: `supplier-order-bank-balance ${balanceClass}` })
            .text(option.attr('data-balance-label') || '-'));
}

function loadSupplierOrderAdvanceBankAccounts() {
    const select = $('#supplier_order_new_advance_bank_account_id');
    if (!select.length) return;
    const companyId = String($('#supplier_order_company_id').val() || '');
    const currencyId = String($('#supplier_order_new_advance_payment_currency_id').val() || '');
    const accountKey = companyId && currencyId ? `${companyId}:${currencyId}` : '';
    if (supplierOrderAdvanceBankAccountsRequest) {
        supplierOrderAdvanceBankAccountsRequest.abort();
        supplierOrderAdvanceBankAccountsRequest = null;
    }

    if (!companyId || !currencyId) {
        resetSupplierOrderAdvanceBankAccountSelect('Seleccione primero una empresa y moneda de pago.');
        return;
    }

    resetSupplierOrderAdvanceBankAccountSelect('Cargando cuentas bancarias...');
    select.data('loading-key', accountKey);
    const request = $.get(window.routes.supplierPurchaseOrderCompanyBankAccounts, {
        company_id: companyId,
        currency_id: currencyId
    });
    supplierOrderAdvanceBankAccountsRequest = request;

    request.done(function (response) {
        const accounts = Array.isArray(response.accounts) ? response.accounts : [];
        const seenAccountIds = new Set();
        select.empty().append(new Option('Seleccione cuenta bancaria', ''));

        accounts.forEach(function (account) {
            const accountId = String(account.id || '');
            if (!accountId || seenAccountIds.has(accountId)) return;
            seenAccountIds.add(accountId);
            const option = new Option(account.label || `Cuenta ${accountId}`, accountId);
            $(option)
                .attr('data-company-id', account.company_id)
                .attr('data-currency-id', account.currency_id)
                .attr('data-company-name', account.company_name || '')
                .attr('data-bank-name', account.bank_name || '')
                .attr('data-currency-code', account.currency_code || '')
                .attr('data-account-number', account.account_number || '')
                .attr('data-balance', Number(account.balance || 0))
                .attr('data-balance-label', `${account.currency_symbol || account.currency_code || ''} ${formatSupplierOrderMoney(account.balance)}`.trim());
            select.append(option);
        });

        if (!seenAccountIds.size) {
            resetSupplierOrderAdvanceBankAccountSelect(
                'No hay cuentas bancarias activas para esta empresa y moneda.',
                'is-empty',
                false
            );
            select.data('loaded-key', accountKey);
            return;
        }

        select
            .prop('disabled', false)
            .removeData('loading-key')
            .data('loaded-key', accountKey)
            .val('')
            .trigger('change.select2');
        $('#supplierOrderAdvanceAccountHelp')
            .removeClass('is-empty')
            .addClass('is-ready')
            .text(`${seenAccountIds.size} cuenta${seenAccountIds.size === 1 ? '' : 's'} disponible${seenAccountIds.size === 1 ? '' : 's'} para la empresa y moneda seleccionadas.`);
    }).fail(function (_xhr, status) {
        if (status === 'abort') return;
        resetSupplierOrderAdvanceBankAccountSelect(
            'No se pudieron cargar las cuentas bancarias. Intente nuevamente.',
            'is-empty'
        );
    }).always(function () {
        if (select.data('loading-key') === accountKey) select.removeData('loading-key');
        if (supplierOrderAdvanceBankAccountsRequest === request) {
            supplierOrderAdvanceBankAccountsRequest = null;
        }
    });
}

function updateSupplierOrderFinancialSummary() {
    if (!$('#supplier_order_payment_currency_id').length) return;
    const purchase = supplierOrderFinancialCurrency('#supplier_order_currency_id');
    const payment = supplierOrderFinancialCurrency('#supplier_order_new_advance_payment_currency_id');
    const totalPurchase = parseFloat($('#supplier_order_grand_total').val()) || 0;
    const referenceRate = parseFloat($('#supplier_order_exchange_rate').val()) || 0;
    const rateInput = $('#supplier_order_new_advance_exchange_rate');
    const sameCurrency = Boolean(payment.id) && purchase.code === payment.code;
    if (sameCurrency) {
        rateInput.val('1').prop('readonly', true);
    } else {
        rateInput.prop('readonly', false);
        if (!rateInput.val() && referenceRate > 0) rateInput.val(referenceRate);
    }
    const rate = parseFloat(rateInput.val()) || 0;
    const appliedAmount = parseFloat($('#supplier_order_new_advance_applied_amount').val()) || 0;
    let paidAmount = 0;
    if (sameCurrency) paidAmount = appliedAmount;
    else if (rate > 0 && payment.code === 'PEN') paidAmount = appliedAmount * rate;
    else if (rate > 0 && purchase.code === 'PEN') paidAmount = appliedAmount / rate;
    $('#supplier_order_new_advance_amount').val(paidAmount > 0 ? paidAmount.toFixed(4) : '');
    $('#supplier_order_new_advance_purchase_currency_id').val(purchase.id || '');
    $('#supplierOrderAppliedAmountHelp').text(`En ${purchase.code}, moneda de la compra.`);
    $('#supplierOrderPaidAmountHelp').text(payment.id ? `Salida bancaria en ${payment.code}.` : 'Seleccione la moneda del pago.');
    $('#supplierOrderFinancialPurchaseTotal').text(`${purchase.code} ${formatSupplierOrderMoney(totalPurchase)}`);

    const applyAdvance = $('#supplier_order_apply_advance').is(':checked');
    const advanceType = $('#supplier_order_advance_type').val();
    const percentage = parseFloat($('#supplier_order_advance_percentage').val()) || 0;
    const fixedAmount = parseFloat($('#supplier_order_advance_amount').val()) || 0;
    const storedPaid = parseFloat($('#supplierOrderExistingAdvancePayments').data('paid-applied')) || 0;
    const storedPaidPen = parseFloat($('#supplierOrderExistingAdvancePayments').data('paid-pen')) || 0;
    const newPaid = appliedAmount;
    const paid = storedPaid + newPaid;
    const required = !applyAdvance ? 0 : (advanceType === 'percentage' ? totalPurchase * percentage / 100 : fixedAmount);
    const balance = Math.max(required - paid, 0);
    const purchaseBalance = Math.max(totalPurchase - paid, 0);
    const paidPen = storedPaidPen + (payment.code === 'PEN' ? paidAmount : 0);
    const status = !applyAdvance ? 'Sin anticipo' : (paid <= 0 ? 'Pendiente' : (paid + 0.0001 < required ? 'Parcial' : 'Pagado'));

    $('.supplier-order-advance-field').toggleClass('d-none', !applyAdvance);
    $('#supplierOrderAdvancePercentageGroup').toggleClass('d-none', advanceType !== 'percentage');
    $('#supplierOrderAdvanceAmountGroup').toggleClass('d-none', advanceType !== 'fixed_amount');
    $('#supplierOrderAdvanceRequired').text(`${purchase.code} ${formatSupplierOrderMoney(required)}`);
    $('#supplierOrderAdvancePaid').text(`${purchase.code} ${formatSupplierOrderMoney(paid)}`);
    $('#supplierOrderAdvanceBalance').text(`${purchase.code} ${formatSupplierOrderMoney(balance)}`);
    $('#supplierOrderPurchaseBalance').text(`${purchase.code} ${formatSupplierOrderMoney(purchaseBalance)}`);
    $('#supplierOrderPaidPenTotal').text(`PEN ${formatSupplierOrderMoney(paidPen)}`);
    $('#supplierOrderAdvanceStatusBadge')
        .removeClass('badge-light badge-warning badge-info badge-success')
        .addClass(status === 'Pagado' ? 'badge-success' : (status === 'Parcial' ? 'badge-info' : (applyAdvance ? 'badge-warning' : 'badge-light')))
        .text(status);
    updateSupplierOrderFormSummary();
}

function validateSupplierOrderFinancialTerms() {
    const purchase = supplierOrderFinancialCurrency('#supplier_order_currency_id');
    const preferredPayment = supplierOrderFinancialCurrency('#supplier_order_payment_currency_id');
    if (!preferredPayment.id) return 'Seleccione la moneda de pago referencial.';

    if ($('#supplier_order_apply_advance').is(':checked')) {
        const type = $('#supplier_order_advance_type').val();
        if (!type) return 'Seleccione el tipo de anticipo.';
        const percentage = parseFloat($('#supplier_order_advance_percentage').val()) || 0;
        const amount = parseFloat($('#supplier_order_advance_amount').val()) || 0;
        if (type === 'percentage' && (percentage <= 0 || percentage > 100)) return 'El porcentaje del anticipo debe ser mayor a 0 y menor o igual a 100.';
        if (type === 'fixed_amount' && amount <= 0) return 'Ingrese un monto fijo de anticipo mayor a cero.';

        const appliedRaw = String($('#supplier_order_new_advance_applied_amount').val() || '').trim();
        const appliedAmount = parseFloat(appliedRaw) || 0;
        if (appliedRaw && appliedAmount <= 0) return 'El monto aplicado a la compra debe ser mayor a cero.';
        if (appliedAmount > 0) {
            const payment = supplierOrderFinancialCurrency('#supplier_order_new_advance_payment_currency_id');
            const rate = parseFloat($('#supplier_order_new_advance_exchange_rate').val()) || 0;
            const storedPaid = parseFloat($('#supplierOrderExistingAdvancePayments').data('paid-applied')) || 0;
            const totalPurchase = parseFloat($('#supplier_order_grand_total').val()) || 0;
            const required = type === 'percentage'
                ? totalPurchase * percentage / 100
                : amount;
            const pending = Math.min(
                Math.max(required - storedPaid, 0),
                Math.max(totalPurchase - storedPaid, 0)
            );
            if (!payment.id) return 'Seleccione la moneda de este pago.';
            if (purchase.code !== payment.code && purchase.code !== 'PEN' && payment.code !== 'PEN') return 'Una de las monedas del pago debe ser PEN.';
            if (purchase.code !== payment.code && rate <= 0) return 'Ingrese el tipo de cambio de este pago, mayor a cero.';
            if (appliedAmount > pending + 0.0001) return `El monto aplicado no puede superar el saldo pendiente de ${purchase.code} ${formatSupplierOrderMoney(pending)}.`;
            if (!$('#supplier_order_new_advance_bank_account_id').val()) return 'Seleccione una cuenta bancaria de la empresa en la moneda del pago.';
            if (!$('#supplier_order_new_advance_method').val()) return 'Seleccione el medio de pago del anticipo.';
            if (!$('#supplier_order_new_advance_date').val()) return 'Ingrese la fecha del pago del anticipo.';
        }
    }

    return null;
}

function renderSupplierOrderAdvancePayments(payments) {
    const container = $('#supplierOrderExistingAdvancePayments');
    const rows = payments || [];
    const purchaseCode = supplierOrderFinancialCurrency('#supplier_order_currency_id').code;
    const paidApplied = rows.reduce((sum, payment) => sum + Number(payment.effective_applied_amount ?? payment.applied_amount ?? 0), 0);
    const paidPen = rows.reduce((sum, payment) => String(payment.currency?.code || '').toUpperCase() === 'PEN' ? sum + Number(payment.amount || 0) : sum, 0);
    container.data('paid-applied', paidApplied).data('paid-pen', paidPen);
    if (!rows.length) {
        container.html('<div class="text-muted small py-2">Aún no hay pagos de anticipo registrados.</div>');
        return;
    }

    container.html(`<div class="table-responsive"><table class="table table-sm supplier-order-advance-payments-table"><thead><tr><th>Fecha</th><th>Cuenta origen</th><th>Medio</th><th>Operación</th><th class="text-right">Monto aplicado</th><th class="text-right">TC</th><th class="text-right">Monto pagado</th><th>Usuario</th><th>Constancia</th></tr></thead><tbody>${rows.map(payment => `<tr><td>${escapeSupplierOrderHtml(String(payment.payment_date || '').slice(0,10))}</td><td>${escapeSupplierOrderHtml(payment.company_bank_account?.bank?.short_name || payment.company_bank_account?.bank?.description || '-')}<small class="d-block text-muted">${escapeSupplierOrderHtml(payment.company_bank_account?.account_number || '')}</small></td><td>${escapeSupplierOrderHtml(supplierOrderOptionLabel(payment.payment_method) || '-')}</td><td>${escapeSupplierOrderHtml(payment.operation_number || '-')}</td><td class="text-right">${escapeSupplierOrderHtml(payment.purchase_currency?.code || purchaseCode)} ${formatSupplierOrderMoney(payment.effective_applied_amount ?? payment.applied_amount)}</td><td class="text-right">${Number(payment.exchange_rate || 1).toFixed(4)}</td><td class="text-right">${escapeSupplierOrderHtml(payment.currency?.code || '')} ${formatSupplierOrderMoney(payment.amount)}</td><td>${escapeSupplierOrderHtml(payment.creator?.name || '-')}</td><td>${payment.proof_url ? `<a href="${escapeSupplierOrderHtml(payment.proof_url)}" target="_blank" class="btn btn-outline-info btn-xs"><i class="fas fa-eye"></i> Ver</a>` : '-'}</td></tr>`).join('')}</tbody></table></div>`);
}

function setDefaultSupplierOrderCurrency() {
    const option = $('#supplier_order_currency_id option').filter(function () {
        return String($(this).data('code')).toUpperCase() === 'PEN';
    }).first();

    $('#supplier_order_currency_id')
        .val(option.length ? option.val() : '')
        .trigger('change');
}

function supplierName(supplier) {
    if (!supplier) {
        return '-';
    }

    return supplier.short_name || supplier.business_name || supplier.ruc || '-';
}

function formatSupplierOrderMoney(value) {
    return (parseFloat(value) || 0).toFixed(2);
}

function formatSupplierOrderUnitPrice(value) {
    return formatSupplierOrderDecimal(value);
}

function formatSupplierOrderDecimal(value, decimals = 6) {
    const parsed = parseFloat(String(value ?? 0).replace(',', '.')) || 0;

    return parsed.toFixed(decimals).replace(/\.?0+$/, '');
}

function formatSupplierOrderDate(value) {
    return value ? String(value).substring(0, 10) : '';
}

function normalizeSupplierOrderText(value) {
    return String(value || '')
        .trim()
        .toUpperCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

function getSelectedBankName() {
    const selected = $('#supplier_order_supplier_account_id option:selected');
    let bank = selected.data('bank') || '';

    if (!bank) {
        bank = String(selected.text() || '').split(/[-|]/)[0] || '';
    }

    bank = normalizeSupplierOrderText(bank).replace(/[^A-Z0-9 ]/g, '').replace(/\s+/g, ' ').trim();
    const compactBank = bank.replace(/\s+/g, '');
    const knownBank = ['BBVA', 'BCP', 'INTERBANK', 'SCOTIABANK'].find(code => compactBank.includes(code));

    return knownBank || bank;
}

function getSelectedDestinationText() {
    const optionalDestination = normalizeSupplierOrderText($('#supplier_order_destination_text').val());

    if (optionalDestination) {
        return optionalDestination;
    }

    const selected = $('#supplier_order_destination_ubigeo_id option:selected');
    const department = normalizeSupplierOrderText(selected.data('department'));
    const district = normalizeSupplierOrderText(selected.data('district'));

    if (department || district) {
        return [department, district]
            .filter(Boolean)
            .filter((value, index, values) => values.indexOf(value) === index)
            .join(' / ');
    }

    return normalizeSupplierOrderText(selected.text() && selected.val() ? selected.text() : '');
}

function buildDefaultPurchaseInstructions() {
    const bank = getSelectedBankName();
    const destination = getSelectedDestinationText();

    return `Abono de la presente Orden de compra se realizo a cuentas de la empresa ${bank || ''} - Factura enviar al correo, embalaje y rotulado de forma correcta, para ser enviado a la ciudad de ${destination || '-'}`.trim();
}

function isDefaultPurchaseInstructionText(value) {
    return normalizeSupplierOrderText(value)
        .startsWith('ABONO DE LA PRESENTE ORDEN DE COMPRA SE REALIZO A CUENTAS DE LA EMPRESA');
}

function syncPurchaseInstructions(force = false) {
    const input = $('#supplier_order_purchase_instructions');
    const currentValue = String(input.val() || '').trim();
    const lastAutoValue = String(input.data('last-auto-value') || '').trim();
    const nextAutoValue = buildDefaultPurchaseInstructions();

    if (!input.length || !nextAutoValue) {
        return;
    }

    if (
        force
        || !currentValue
        || isOldPurchaseInstructionTestText(currentValue)
        || isDefaultPurchaseInstructionText(currentValue)
        || (lastAutoValue && currentValue === lastAutoValue)
    ) {
        input.val(nextAutoValue);
        input.data('last-auto-value', nextAutoValue);
    }
}

function isOldPurchaseInstructionTestText(value) {
    const normalized = normalizeSupplierOrderText(value);

    return [
        'PRUEBA DE INSTRUCCIONES',
        'PRUEBA INSTRUCCIONES',
        'TEST',
        'LOREM'
    ].some(testText => normalized.includes(testText));
}

function normalizeSupplierOrderOption(value) {
    const normalized = String(value || '')
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replaceAll('.', '')
        .replaceAll('-', ' ')
        .replace(/\s+/g, '_');

    const aliases = {
        credito_20_dias: 'credito',
        credito_20_dia: 'credito',
        credito_30_dias: 'credito',
        credito_30_dia: 'credito',
        credito_45_dias: 'credito',
        credito_45_dia: 'credito',
        credito_60_dias: 'credito',
        credito_60_dia: 'credito',
        deposito_en_cuenta: 'deposito_cuenta',
        deposito_cuenta: 'deposito_cuenta',
        agencia_de_transporte: 'agencia',
        agencia_transporte: 'agencia',
        en_agencia: 'agencia',
        transporte: 'agencia',
        recojo_de_almacen: 'recojo_almacen',
        recojo_almacen: 'recojo_almacen',
        transportista_del_proveedor: 'transportista_proveedor',
        transportista_proveedor: 'transportista_proveedor',
        guia_de_remision: 'guia_remision',
        guia_remision: 'guia_remision',
        nota_de_pedido: 'nota_pedido',
        nota_pedido: 'nota_pedido',
        aereo: 'aereo'
    };

    return aliases[normalized] || normalized;
}

function normalizeSupplierOrderPaymentCondition(value) {
    const normalized = normalizeSupplierOrderOption(value);

    if (normalized.startsWith('credito')) return 'credito';
    if (normalized.includes('contado') || normalized.includes('pagado')) return 'contado';

    return normalized;
}

function supplierOrderCreditDaysFromCondition(value) {
    const normalized = String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase();
    const match = normalized.match(/credito\D*(\d+)/);

    return match ? Number(match[1]) : 0;
}

function supplierOrderDateFromInput(value) {
    const datePart = formatSupplierOrderDate(value);
    if (!datePart) return null;

    const date = new Date(`${datePart}T00:00:00`);
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatSupplierOrderDateInput(value) {
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}/.test(value)) {
        return value.slice(0, 10);
    }

    const date = value instanceof Date ? value : supplierOrderDateFromInput(value);
    if (!date || Number.isNaN(date.getTime())) return '';

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function calculateSupplierOrderPaymentDueDate() {
    const daysValue = String($('#supplier_order_credit_days').val() || '').trim();
    const days = Number(daysValue);

    if (!/^\d+$/.test(daysValue) || days <= 0) {
        $('#supplier_order_payment_due_date').val('');
        return;
    }

    const dueDate = new Date(supplierOrderPaymentBaseDate.getTime());
    dueDate.setHours(0, 0, 0, 0);
    dueDate.setDate(dueDate.getDate() + days);
    $('#supplier_order_payment_due_date').val(formatSupplierOrderDateInput(dueDate));
}

function toggleSupplierOrderCreditFields() {
    const isCredit = normalizeSupplierOrderPaymentCondition(
        $('#supplier_order_payment_condition').val()
    ) === 'credito';

    $('.supplier-order-credit-field').toggleClass('d-none', !isCredit);
    $('#supplierOrderCashPaymentHelp').toggleClass(
        'd-none',
        $('#supplier_order_payment_condition').val() !== 'contado'
    );
    $('#supplier_order_credit_days,#supplier_order_payment_due_date').prop('required', isCredit);

    if (!isCredit) {
        $('#supplier_order_credit_days,#supplier_order_payment_due_date').val('').removeClass('is-invalid');
        return;
    }

    calculateSupplierOrderPaymentDueDate();
}

function validateSupplierOrderPaymentTerms() {
    if (normalizeSupplierOrderPaymentCondition($('#supplier_order_payment_condition').val()) !== 'credito') {
        return null;
    }

    const daysValue = String($('#supplier_order_credit_days').val() || '').trim();
    if (!daysValue) {
        return {
            message: 'Ingrese los días de crédito.',
            errors: { credit_days: ['Ingrese los días de crédito.'] }
        };
    }

    if (!/^\d+$/.test(daysValue) || Number(daysValue) <= 0) {
        return {
            message: 'Los días de crédito deben ser mayores a 0.',
            errors: { credit_days: ['Los días de crédito deben ser mayores a 0.'] }
        };
    }

    calculateSupplierOrderPaymentDueDate();
    return null;
}

function supplierOrderRequiresShippingAgency(value) {
    return normalizeSupplierOrderOption(value) === 'agencia';
}

function supplierOrderUserName(user) {
    if (!user) {
        return '';
    }

    return [user.name, user.lastname].filter(Boolean).join(' ').trim()
        || user.email
        || '';
}

function supplierOrderRequestedBy(order) {
    return supplierOrderUserName(order.updater) || supplierOrderUserName(order.creator);
}

function supplierOrderPaymentTerms(order) {
    const account = order.supplier_account || {};
    const bank = account.bank || {};
    const condition = supplierOrderPaymentConditionLabel(order, 'Condicion no indicada');
    const bankName = bank.short_name || bank.description || 'Banco no indicado';
    const accountNumber = account.account_number || account.cci || 'Cuenta no indicada';

    return `${condition} - ${bankName} - ${accountNumber}`;
}

function supplierOrderPaymentConditionLabel(order, fallback = '-') {
    const conditionLabel = supplierOrderOptionLabel(order.payment_condition) || fallback;
    const creditDays = Number(order.credit_days) || supplierOrderCreditDaysFromCondition(order.payment_condition);

    return normalizeSupplierOrderPaymentCondition(order.payment_condition) === 'credito' && creditDays > 0
        ? `${conditionLabel} ${creditDays} dias`
        : conditionLabel;
}

function supplierOrderOptionLabel(value) {
    const labels = {
        terrestre: 'Terrestre',
        aereo: 'Aereo',
        contado: 'Contado',
        credito: 'Credito',
        agencia: 'Agencia',
        recojo_almacen: 'Recojo de almacén',
        transportista_proveedor: 'Transportista del proveedor',
        efectivo: 'Efectivo',
        tarjeta: 'Tarjeta',
        deposito_cuenta: 'Deposito en cuenta',
        factura: 'Factura',
        boleta: 'Boleta',
        nota_pedido: 'Nota de pedido',
        guia_remision: 'Guia de remision'
    };
    const normalized = normalizeSupplierOrderOption(value);

    return labels[normalized] || value || '';
}

function supplierOrderEntryStatusPresentation(status, enteredQuantity, pendingQuantity) {
    if (status === 'entered' || (parseFloat(pendingQuantity) || 0) <= 0) {
        return {
            label: 'Ingresado',
            className: 'status-entered',
            icon: 'fas fa-check-circle'
        };
    }

    if (status === 'partial_entered' || (parseFloat(enteredQuantity) || 0) > 0) {
        return {
            label: 'Parcial',
            className: 'status-partial',
            icon: 'fas fa-hourglass-half'
        };
    }

    return {
        label: 'Pendiente',
        className: 'status-pending',
        icon: 'fas fa-clock'
    };
}

function supplierCustomerOrderStatusPresentation(status) {
    const statuses = {
        registered: ['Registrada', 'badge-secondary'],
        draft: ['Registrada', 'badge-secondary'],
        sent: ['Enviada', 'badge-info'],
        approved: ['Aprobada', 'badge-success'],
        received: ['Recibida', 'badge-success'],
        in_purchase: ['En compra', 'badge-primary'],
        en_compra: ['En compra', 'badge-primary'],
        partial_purchase: ['Compra parcial', 'badge-warning'],
        entered: ['Ingresada', 'badge-success'],
        partial_entered: ['Ingreso parcial', 'badge-warning'],
        attended: ['Atendida', 'badge-success'],
        not_attended: ['No atendida', 'badge-danger'],
        cancelled: ['Anulada', 'badge-danger'],
        completed: ['Completada', 'badge-success'],
        delivered: ['Entregada', 'badge-success'],
        invoiced: ['Facturada', 'badge-info']
    };
    const presentation = statuses[String(status || '').toLowerCase()] || ['Sin estado', 'badge-light text-dark border'];

    return { label: presentation[0], className: presentation[1] };
}

function setSupplierOrderValue(selector, value) {
    const element = $(selector);

    if (element.is('input, textarea, select')) {
        element.val(value);
        return;
    }

    element.text(value);
}

function escapeSupplierOrderHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
