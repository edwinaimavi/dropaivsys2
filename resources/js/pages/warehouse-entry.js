let tableWarehouseEntry;
let warehouseEntryItemIndex = 0;
let warehouseEntrySourceLoadRequest = null;
let warehouseEntrySourceLoadTimer = null;
let warehouseEntryLogisticsStatusRequest = null;
let warehouseEntryLogisticsSelectionSequence = 0;
let warehouseEntryAcknowledgedLogisticsOrders = new Set();
let warehouseEntryPendingDocuments = [];
let warehouseEntryExistingDocuments = [];
let warehouseEntryPendingLotDocuments = [];
let warehouseEntryExistingLotDocuments = [];
let warehouseEntryExpenses = [];
let warehouseEntryExpenseEditorRemovedDocuments = { invoice: false, payment_proof: false };
let warehouseEntryActiveLotsRow = null;
let warehouseEntryDeliveryType = '';
let warehouseEntrySourceOrderTotal = null;
const warehouseEntryExpandedGroups = new Set();

const warehouseEntryDocumentTypes = {
    purchase_invoice: { label: 'Factura', badge: 'badge-doc-green' },
    receipt: { label: 'Boleta', badge: 'badge-doc-green' },
    dispatch_guide: { label: 'Guia de remision', badge: 'badge-doc-blue' },
    analysis_certificate: { label: 'Certificado de analisis', badge: 'badge-doc-yellow' },
    sanitary_registration: { label: 'Registro sanitario', badge: 'badge-doc-teal' },
    quality_certificate: { label: 'Certificado de calidad', badge: 'badge-doc-yellow' },
    bpm_bpa_certificate: { label: 'Certificado BPM / BPA', badge: 'badge-doc-yellow' },
    technical_sheet: { label: 'Ficha tecnica', badge: 'badge-doc-teal' },
    medicine_document: { label: 'Documento del medicamento', badge: 'badge-doc-teal' },
    other: { label: 'Otro', badge: 'badge-doc-gray' }
};

const warehouseEntryDocumentCodeMap = {
    WE001: 'purchase_invoice',
    WE002: 'receipt',
    WE003: 'dispatch_guide',
    WE004: 'analysis_certificate',
    WE005: 'sanitary_registration',
    WE006: 'quality_certificate',
    WE007: 'bpm_bpa_certificate',
    WE008: 'technical_sheet',
    WE009: 'medicine_document',
    WE010: 'other',
    DOC001: 'technical_sheet',
    DOC002: 'analysis_certificate',
    DOC003: 'sanitary_registration',
    DOC004: 'bpm_bpa_certificate'
};

const warehouseEntryAllowedDocumentExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
const warehouseEntryMaxDocumentSize = 10 * 1024 * 1024;
const warehouseEntryOfficialFreightTypes = {
    agencia: { agency_freight: 'Flete de agencia', agency_pickup_to_warehouse: 'Recojo de agencia a almacén', agency_direct_to_warehouse: 'Entrega directa de agencia a almacén' },
    recojo_almacen: { supplier_warehouse_pickup: 'Recojo en almacén del proveedor', transfer_to_agency: 'Traslado a agencia', agency_freight: 'Flete de agencia', agency_pickup_to_warehouse: 'Recojo de agencia a almacén', agency_direct_to_warehouse: 'Entrega directa de agencia a almacén' },
    transportista_proveedor: {}
};
const warehouseEntryExpenseTypes = {
    freight_transport: { transport_agency: 'Agencia de transporte', courier: 'Courier', truck: 'Camión', mobility: 'Movilidad', shipping: 'Envío', delivery: 'Reparto', transfer: 'Traslado' },
    other_expense: { stowage: 'Estiba', packaging: 'Embalaje', toll: 'Peaje', insurance: 'Seguro', commission: 'Comisión', handling: 'Manipuleo', loading_unloading: 'Carga / descarga', other: 'Otros' }
};
const warehouseEntryExpenseTypeLabels = Object.assign({}, ...Object.values(warehouseEntryExpenseTypes), { flete:'Flete', transporte:'Transporte', estiba:'Estiba', movilidad:'Movilidad', embalaje:'Embalaje', peaje:'Peaje', seguro:'Seguro', comision:'Comisión', aduana:'Aduana', otro:'Otro' });
const warehouseEntryExpenseOriginLabels = { included_in_purchase_price:'Incluido en el precio de compra', same_purchase_document:'Mismo comprobante de compra', third_party:'Tercero / agencia externa', internal_without_document:'Interno sin comprobante' };

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    prepareWarehouseEntryModalLayers();
    initializeWarehouseEntryFormTabs();

    $('#warehouseEntryModal').modal({
        backdrop: 'static',
        keyboard: false,
        show: false
    });

    initWarehouseEntrySelect2($('#warehouseEntryModal'));
    initWarehouseEntryTable();

    $(document).on('click', '.warehouse-entry-group-toggle', function () {
        toggleWarehouseEntryCustomerOrderGroup($(this).closest('.warehouse-entry-accordion').attr('data-group-key'));
    });

    $(document).on('click', '.vwe-lot-doc-toggle', function () {
        const button = $(this);
        const group = button.closest('.vwe-lot-doc-accordion');
        const opening = !group.hasClass('is-open');

        group.toggleClass('is-open', opening);
        group.find('.vwe-lot-doc-body').stop(true, true).slideToggle(160);
        button.attr('aria-expanded', opening ? 'true' : 'false');
        button.find('.vwe-lot-doc-toggle-label').text(opening ? 'Ocultar documentos' : 'Ver documentos');
        button.find('.vwe-lot-doc-chevron').toggleClass('fa-chevron-up', opening).toggleClass('fa-chevron-down', !opening);
    });

    $(document).on('click', '#btnCreateWarehouseEntry', function () {
        resetWarehouseEntryForm();
        $('#warehouseEntryModalLabel').text('Nuevo Ingreso de Almacen');
        generateWarehouseEntryNumber();
        $('#warehouseEntryModal').modal('show');
    });

    $('#warehouseEntryModal')
        .on('shown.bs.modal', function () {
            tagWarehouseEntryBackdrop('warehouse-entry-backdrop-main');
            document.body.classList.add('warehouse-entry-active');
        })
        .on('hidden.bs.modal', function () {
            resetWarehouseEntryForm();
            cleanupWarehouseEntryModalBackdrops();
        });

    $('#warehouseEntryLotsModal')
        .on('shown.bs.modal', function () {
            tagWarehouseEntryBackdrop('warehouse-entry-backdrop-lots');
        })
        .on('hidden.bs.modal', function () {
            warehouseEntryActiveLotsRow = null;
            if ($('#warehouseEntryModal').hasClass('show')) {
                document.body.classList.add('modal-open', 'warehouse-entry-active');
                $('#warehouseEntryModal').trigger('focus');
            } else {
                cleanupWarehouseEntryModalBackdrops();
            }
        });

    $(document).on('submit', '#warehouseEntryForm', function (event) {
        event.preventDefault();
        saveWarehouseEntry(this);
    });

    $(document).on('click', '#btnAddWarehouseEntryItem', function () {
        addWarehouseEntryItemRow();
    });

    $(document).on('click', '.btnRemoveWarehouseEntryItem', function () {
        const row = $(this).closest('tr');
        row.nextUntil('.warehouse-entry-item-row').filter('.warehouse-entry-lot-visual-row').remove();
        destroyWarehouseEntryRowSelect2(row);
        row.remove();
        refreshWarehouseEntryItemIndexes();
        calculateWarehouseEntryTotals();
        showEmptyWarehouseEntryItemsRow();
    });

    $(document).on('change', '#warehouse_entry_supplier_purchase_order_id', function () {
        handleWarehouseEntrySupplierOrderSelection();
    });

    $(document).on('change', '#warehouse_entry_supplier_id', function () {
        syncWarehouseEntrySupplierFields();
    });

    $(document).on('change', '#warehouse_entry_warehouse_id', function () {
        const text = $(this).find('option:selected').text().trim();
        $('#warehouseEntrySideWarehouse').text($(this).val() ? text : 'Sin almacen');
    });

    $(document).on('change', '#warehouse_entry_company_id', function () {
        const text = $(this).find('option:selected').text().trim();
        $('#warehouseEntrySideCompany').text($(this).val() ? text : 'Seleccione empresa');
        updateWarehouseEntryReview();
    });

    $(document).on('shown.bs.tab', '#warehouseEntryModal .warehouse-entry-form-tabs a[data-toggle="pill"]', updateWarehouseEntryReview);

    $(document).on('change', '#warehouse_entry_currency_id', updateWarehouseEntryCurrency);
    $(document).on('change', '#warehouse_entry_expense_provider_id', function () { $('#warehouse_entry_expense_provider_ruc').val($(this).find('option:selected').data('ruc') || ''); });
    $(document).on('change', '#warehouse_entry_expense_type', function () { renderWarehouseEntryExpenseTypes($(this).val()); });
    $(document).on('change', '#warehouse_entry_expense_shipping_agency_id', function () { $('#warehouse_entry_expense_provider_ruc').val($(this).find('option:selected').data('ruc') || ''); });
    $(document).on('change', '#warehouse_entry_expense_document_type', toggleWarehouseEntryExpenseDocumentFields);
    $(document).on('change', '#warehouse_entry_expense_cost_origin', applyWarehouseEntryExpenseOriginRules);
    $(document).on('change', '#warehouse_entry_expense_affects_cost', toggleWarehouseEntryExpenseDistribution);
    $(document).on('change', '#warehouse_entry_expense_distribution_method', renderWarehouseEntryExpenseManualDistribution);
    $(document).on('change', '.warehouse-entry-expense-file-input[data-expense-document-type]', handleWarehouseEntryExpenseFileChange);
    $(document).on('click', '.btnRemoveWarehouseEntryExpenseDocument', function () { clearWarehouseEntryExpenseFile($(this).data('expense-document-type')); });
    $(document).on('click', '#btnAddWarehouseEntryExpense', addWarehouseEntryExpense);
    $(document).on('click', '.btnEditWarehouseEntryExpense', function () { editWarehouseEntryExpense(Number($(this).data('index'))); });
    $(document).on('click', '.btnRemoveWarehouseEntryExpense', function () { removeWarehouseEntryExpense(Number($(this).data('index'))); });

    $(document).on(
        'input change',
        '#warehouse_entry_affect_igv, .item-quantity, .item-unit-price',
        calculateWarehouseEntryTotals
    );

    $(document).on('change', '#warehouse_entry_generate_account_payable', syncWarehouseEntryPayableAmount);

    $(document).on('change', '.item-article-picker', function () {
        applySelectedWarehouseEntryArticle($(this).closest('tr'));
    });

    $(document).on('input change', '.item-quantity', function () {
        renderWarehouseEntryLotsSummary($(this).closest('tr'));
    });

    $(document).on('click', '.btnManageWarehouseEntryLots', function () {
        openWarehouseEntryLotsModal($(this).closest('tr'));
    });

    $(document).on('click', '#btnAddWarehouseEntryLot', function () {
        addWarehouseEntryLotEditorRow();
    });

    $(document).on('click', '.btnRemoveWarehouseEntryLot', function () {
        $(this).closest('tr').remove();
        refreshWarehouseEntryLotEditor();
    });

    $(document).on('input change', '#warehouseEntryLotsTbody input', refreshWarehouseEntryLotEditor);
    $(document).on('click', '#btnApplyWarehouseEntryLots', applyWarehouseEntryLots);

    $(document).on('click', '#btnLoadWarehouseEntrySource', loadWarehouseEntrySourceItems);

    $(document).on('change', '#warehouse_entry_document_attachment_file', function () {
        const file = this.files?.[0];
        $(this).siblings('.custom-file-label').text(file ? file.name : 'Seleccionar archivo');
    });

    $(document).on('click', '#btnAddWarehouseEntryDocument', addWarehouseEntryPendingDocument);
    $(document).on('change', '#warehouse_entry_lot_document_item', refreshWarehouseEntryLotDocumentLots);
    $(document).on('change', '#warehouse_entry_lot_document_lot', renderWarehouseEntrySelectedLotInfo);
    $(document).on('click', '#btnAddWarehouseEntryLotDocument', addWarehouseEntryPendingLotDocument);
    $(document).on('change', '#warehouse_entry_lot_document_file', function () {
        const file = this.files?.[0];
        $(this).siblings('.custom-file-label')
            .text(file ? file.name : 'Seleccionar archivo')
            .attr('title', file?.name || '');
    });
    $(document).on('click', '.btnWarehouseEntryLotDocuments', function () {
        const visualRow = $(this).closest('tr');
        const itemRow = visualRow.hasClass('warehouse-entry-item-row') ? visualRow : visualRow.prevAll('.warehouse-entry-item-row').first();
        const itemIndex = $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').index(itemRow);
        $('#warehouseEntryModal .warehouse-entry-form-tabs a[href="#warehouse_entry_tab_documents"]').tab('show');
        $('#warehouse_entry_lot_document_item').val(String(itemIndex)).trigger('change');
        $('#warehouse_entry_lot_document_lot').val($(this).data('lot-key')).trigger('change');
    });
    $(document).on('click', '.btnRemovePendingLotDocument', function () {
        warehouseEntryPendingLotDocuments.splice(Number($(this).data('index')), 1);
        renderWarehouseEntryLotDocuments();
    });
    $(document).on('click', '.btnDeleteExistingLotDocument', function () {
        deleteWarehouseEntryLotDocument($(this).data('id'));
    });

    $(document).on('click', '.btnRemoveWarehouseEntryPendingDocument', function () {
        const index = Number($(this).data('index'));
        warehouseEntryPendingDocuments.splice(index, 1);
        renderWarehouseEntryDocuments();
    });

    $(document).on('click', '.btnDeleteWarehouseEntryExistingDocument', function () {
        deleteWarehouseEntryDocument($(this).data('id'));
    });

    $(document).on('click', '.editWarehouseEntry', function () {
        loadWarehouseEntryForEdit($(this).data('id'));
    });

    $(document).on('click', '.viewWarehouseEntry', function () {
        loadWarehouseEntryDetail($(this).data('id'));
    });

    $(document).on('click', '.deleteWarehouseEntry', function () {
        deleteWarehouseEntry($(this).data('id'));
    });
});

function prepareWarehouseEntryModalLayers() {
    const mainModal = document.getElementById('warehouseEntryModal');
    const lotsModal = document.getElementById('warehouseEntryLotsModal');

    if (mainModal && mainModal.parentElement !== document.body) {
        document.body.appendChild(mainModal);
    }
    if (lotsModal && lotsModal.parentElement !== document.body) {
        document.body.appendChild(lotsModal);
    }
}

function initializeWarehouseEntryFormTabs() {
    const destinations = [
        ['#warehouseEntryOriginalDataCard', '#warehouse_entry_tab_data'],
        ['#warehouseEntryOriginalItemsCard', '#warehouse_entry_tab_items'],
        ['#warehouseEntryOriginalExpensesCard', '#warehouse_entry_tab_expenses'],
        ['#warehouseEntryOriginalDocumentsCard', '#warehouse_entry_tab_documents']
    ];

    destinations.forEach(function ([cardSelector, tabSelector]) {
        const card = $(cardSelector);
        if (card.length) {
            card.removeClass('mb-3').appendTo(tabSelector);
            card.parent().closest('.col-12').removeClass('mt-3');
        }
    });
}

function tagWarehouseEntryBackdrop(className) {
    const backdrop = Array.from(document.querySelectorAll('.modal-backdrop'))
        .reverse()
        .find(element => !element.classList.contains('warehouse-entry-backdrop-main')
            && !element.classList.contains('warehouse-entry-backdrop-lots'));

    if (backdrop) {
        backdrop.classList.add(className);
    }
}

function cleanupWarehouseEntryModalBackdrops() {
    if (document.querySelector('.modal.show')) {
        return;
    }

    document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
    document.body.classList.remove('modal-open', 'warehouse-entry-active');
    document.body.style.removeProperty('padding-right');
    document.body.style.removeProperty('overflow');
}

function initWarehouseEntryTable() {
    tableWarehouseEntry = $('#tableWarehouseEntry').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.warehouseEntryList,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'entry_number', name: 'entry_number' },
            { data: 'supplier_purchase_order_id', name: 'supplier_purchase_order_id', orderable: false },
            {
                data: 'customer_order',
                name: 'customer_order',
                orderable: false,
                defaultContent: '-',
                render: function (data, type) {
                    if (type === 'display') {
                        return data || '-';
                    }

                    const container = $('<div>').html(data || '');
                    const labels = container.find('.warehouse-customer-order').map(function () {
                        return [
                            $(this).find('span').first().text().trim(),
                            $(this).find('small').not('.warehouse-customer-order-branch').first().text().trim(),
                            $(this).find('.warehouse-customer-order-branch').text().trim()
                        ]
                            .filter(Boolean)
                            .join(' - ');
                    }).get();

                    return labels.length ? labels.join('; ') : '-';
                }
            },
            { data: 'supplier', name: 'supplier.business_name', orderable: false },
            { data: 'company', name: 'company.business_name', orderable: false },
            { data: 'warehouse', name: 'warehouse_id' },
            { data: 'currency', name: 'currency.code', orderable: false },
            { data: 'grand_total', name: 'grand_total' },
            { data: 'status', name: 'status' },
            { data: 'created_at', name: 'created_at' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        order: [],
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        },
        dom: `
            <'row mb-3'
                <'col-sm-12 col-md-6'l>
                <'col-sm-12 col-md-6 text-md-end'f>
            >
            <'row'<'col-sm-12'tr>>
            <'row mt-3'
                <'col-sm-12 col-md-5'i>
                <'col-sm-12 col-md-7 d-flex justify-content-center justify-content-md-end'p>
            >
            <'row mt-3'<'col-sm-12 text-center'B>>
        `,
        buttons: [
            { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm', exportOptions: { columns: ':not(:last-child)', orthogonal: 'export' } },
            { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-danger btn-sm', exportOptions: { columns: ':not(:last-child)', orthogonal: 'export' } },
            { extend: 'print', text: '<i class="fas fa-print"></i> Imprimir', className: 'btn btn-secondary btn-sm', exportOptions: { columns: ':not(:last-child)', orthogonal: 'export' } }
        ],
        drawCallback: function () {
            renderWarehouseEntryCustomerOrderGroups(this.api());
        }
    });
}

function renderWarehouseEntryCustomerOrderGroups(table) {
    const rows = table.rows({ page: 'current' });
    const data = rows.data().toArray();
    const nodes = rows.nodes().toArray();
    const groups = {};
    const groupOrder = [];

    data.forEach(function (entry) {
        const key = String(entry.customer_order_group_key || 'without-customer-order');
        if (!groups[key]) groupOrder.push(key);

        const group = groups[key] || {
            entries: 0,
            providers: new Set(),
            total: 0,
            currency: entry.currency || '',
            number: entry.customer_order_number || 'Sin OC Cliente',
            client: entry.customer_order_client || 'Sin cliente relacionado',
            branch: entry.customer_order_branch || 'Sin sede registrada',
            lastEntry: entry.created_at || '-',
            items: []
        };

        group.entries += 1;
        group.total += Number(entry.grand_total_value || 0);
        if (entry.supplier_id) group.providers.add(String(entry.supplier_id));
        group.items.push(entry);
        groups[key] = group;
    });

    const colspan = table.columns(':visible').count();
    const searchActive = String(table.search() || '').trim() !== '';

    $(nodes).addClass('warehouse-entry-source-row');

    groupOrder.forEach(function (key) {
        const group = groups[key];
        const firstEntryIndex = data.findIndex(entry => String(entry.customer_order_group_key || 'without-customer-order') === key);
        const isExpanded = searchActive || warehouseEntryExpandedGroups.has(key);
        const entryLabel = group.entries === 1 ? 'ingreso' : 'ingresos';
        const providerLabel = group.providers.size === 1 ? 'proveedor' : 'proveedores';
        const lastEntryDate = String(group.lastEntry).split(' ')[0];
        const detailRows = group.items.map(entry => `<tr>
            <td>${escapeWarehouseEntryHtml(entry.entry_number || '-')}</td>
            <td>${escapeWarehouseEntryHtml(entry.supplier_purchase_order_id || '-')}</td>
            <td>${escapeWarehouseEntryHtml(entry.supplier || '-')}</td>
            <td>${escapeWarehouseEntryHtml(entry.company || '-')}</td>
            <td>${escapeWarehouseEntryHtml(entry.warehouse || '-')}</td>
            <td>${escapeWarehouseEntryHtml(entry.currency || '-')}</td>
            <td class="text-right font-weight-bold">${escapeWarehouseEntryHtml(entry.grand_total || '-')}</td>
            <td>${entry.status || '-'}</td>
            <td>${escapeWarehouseEntryHtml(entry.created_at || '-')}</td>
            <td class="warehouse-entry-group-actions">${entry.acciones || '-'}</td>
        </tr>`).join('');

        const groupRow = `<tr class="warehouse-entry-accordion-row">
            <td colspan="${colspan}">
                <section class="warehouse-entry-accordion${isExpanded ? ' is-open' : ''}" data-group-key="${escapeWarehouseEntryHtml(key)}">
                    <div class="warehouse-entry-group-header">
                        <div class="warehouse-entry-group-identity">
                            <span class="warehouse-entry-group-icon"><i class="fas fa-file-invoice"></i></span>
                            <div>
                                <small>OC Cliente</small>
                                <strong>${escapeWarehouseEntryHtml(group.number)}</strong>
                                <span>${escapeWarehouseEntryHtml(group.client)}</span>
                            </div>
                        </div>
                        <div class="warehouse-entry-group-branch" title="Sucursal o sede de la OC Cliente">
                            <small>Sucursal / Sede</small>
                            <span><i class="fas fa-map-marker-alt"></i>${escapeWarehouseEntryHtml(group.branch)}</span>
                        </div>
                        <div class="warehouse-entry-group-metrics">
                            <span><i class="fas fa-warehouse"></i>${group.entries} ${entryLabel}</span>
                            <span><i class="fas fa-truck"></i>${group.providers.size} ${providerLabel}</span>
                            <span class="warehouse-entry-group-total"><i class="fas fa-coins"></i>${escapeWarehouseEntryHtml(group.currency)} ${formatWarehouseEntryMoney(group.total)}</span>
                            <span><i class="far fa-calendar-alt"></i>&Uacute;ltimo: ${escapeWarehouseEntryHtml(lastEntryDate)}</span>
                            <button type="button" class="warehouse-entry-group-toggle" aria-expanded="${isExpanded ? 'true' : 'false'}">
                                <span>${isExpanded ? 'Ocultar ingresos' : 'Ver ingresos'}</span>
                                <i class="fas fa-chevron-${isExpanded ? 'up' : 'down'}"></i>
                            </button>
                        </div>
                    </div>
                    <div class="warehouse-entry-group-body"${isExpanded ? '' : ' style="display:none"'}>
                        <div class="warehouse-entry-group-table-wrap">
                            <table class="warehouse-entry-group-table">
                                <thead><tr><th>N&deg; Ingreso</th><th>OC Proveedor</th><th>Proveedor</th><th>Empresa</th><th>Almac&eacute;n</th><th>Moneda</th><th>Total</th><th>Estado</th><th>F. Registro</th><th>Acciones</th></tr></thead>
                                <tbody>${detailRows}</tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </td>
        </tr>`;

        $(nodes[firstEntryIndex]).before(groupRow);
    });
}

function toggleWarehouseEntryCustomerOrderGroup(key) {
    const group = $('.warehouse-entry-accordion').filter(function () {
        return $(this).attr('data-group-key') === String(key);
    }).first();

    if (!group.length) return;

    const opening = !group.hasClass('is-open');
    group.toggleClass('is-open', opening);
    group.find('.warehouse-entry-group-body').stop(true, true).slideToggle(160);
    group.find('.warehouse-entry-group-toggle')
        .attr('aria-expanded', opening ? 'true' : 'false')
        .find('span').text(opening ? 'Ocultar ingresos' : 'Ver ingresos');
    group.find('.warehouse-entry-group-toggle i')
        .toggleClass('fa-chevron-up', opening)
        .toggleClass('fa-chevron-down', !opening);

    if (opening) warehouseEntryExpandedGroups.add(String(key));
    else warehouseEntryExpandedGroups.delete(String(key));
}

function initWarehouseEntrySelect2(context) {
    if (!$.fn.select2) {
        return;
    }

    context.find('.js-warehouse-entry-select, .js-warehouse-entry-row-select').each(function () {
        const select = $(this);
        const config = {
            width: '100%',
            dropdownParent: $('#warehouseEntryModal')
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

        select.select2(config);
    });
}

function destroyWarehouseEntryRowSelect2(row) {
    if (!$.fn.select2) {
        return;
    }

    row.find('.js-warehouse-entry-row-select').each(function () {
        if ($(this).data('select2')) {
            $(this).select2('destroy');
        }
    });
}

function generateWarehouseEntryNumber() {
    $.get(window.routes.warehouseEntryGenerateNumber)
        .done(function (response) {
            $('#warehouse_entry_number').val(response.entry_number || '');
        });
}

function resetWarehouseEntryForm() {
    const form = $('#warehouseEntryForm');

    form[0]?.reset();
    $('#warehouse_entry_id').val('');
    $('#warehouse_entry_number').val('');
    clearWarehouseEntryValidation();
    clearWarehouseEntryItemRows();
    showEmptyWarehouseEntryItemsRow();
    warehouseEntryItemIndex = 0;
    warehouseEntryExpenses = [];
    warehouseEntryAcknowledgedLogisticsOrders = new Set();
    warehouseEntryLogisticsSelectionSequence += 1;
    warehouseEntryLogisticsStatusRequest?.abort();
    warehouseEntryLogisticsStatusRequest = null;
    warehouseEntrySourceOrderTotal = null;
    $('#warehouseEntryOrderAmountWarning').addClass('d-none').empty();
    resetWarehouseEntryExpenseEditor();
    renderWarehouseEntryExpenses();
    $('#warehouseEntrySideSupplier').text('Seleccione proveedor');
    $('#warehouseEntrySideCompany').text('Seleccione empresa');
    $('#warehouseEntrySideWarehouse').text('Sin almacen');
    $('#warehouseEntrySideGrandTotal').text('0.00');
    $('.warehouse-entry-currency-symbol').text('S/');
    $('#warehouse_entry_subtotal, #warehouse_entry_igv, #warehouse_entry_grand_total').val('0.00');

    form.find('select').val('').trigger('change.select2');
    $('#warehouse_entry_document_type').val('FACTURA');
    $('#warehouse_entry_affect_igv').val('1');
    $('#warehouse_entry_generate_account_payable').val('0');
    $('#warehouse_entry_payable_amount').val('0.00');
    $('#warehouse_entry_supplier_ruc').val('');
    $('#warehouse_entry_guide_ruc').val('');
    warehouseEntryPendingDocuments = [];
    warehouseEntryExistingDocuments = [];
    warehouseEntryPendingLotDocuments = [];
    warehouseEntryExistingLotDocuments = [];
    resetWarehouseEntryDocumentInputs();
    renderWarehouseEntryDocuments();
    refreshWarehouseEntryLotDocumentItems();
    setWarehouseEntrySupplierLocked(false);
    syncWarehouseEntryPayableAmount();
    $('#warehouseEntryModal .warehouse-entry-form-tabs .nav-link').first().tab('show');
    updateWarehouseEntryReview();
}

function clearWarehouseEntryValidation() {
    $('#warehouseEntryForm .is-invalid').removeClass('is-invalid');
    $('#warehouseEntryForm .invalid-feedback').text('');
}

function saveWarehouseEntry(form) {
    clearWarehouseEntryValidation();
    syncWarehouseEntryPayableAmount();

    if (!$('#warehouse_entry_warehouse_id').val()) {
        const warehouse = $('#warehouse_entry_warehouse_id');
        warehouse.addClass('is-invalid');
        warehouse.closest('.form-group').find('.invalid-feedback').first().text('Debe seleccionar un almacén.');
        warehouse.next('.select2-container').find('.select2-selection').addClass('is-invalid');
        $('#warehouseEntryModal .warehouse-entry-form-tabs a[href="#warehouse_entry_tab_data"]').tab('show');
        Swal.fire('Atención', 'Debe seleccionar un almacén.', 'warning');
        return;
    }

    if (!$('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').length) {
        Swal.fire({
            icon: 'warning',
            title: 'Agregue al menos un articulo.'
        });
        return;
    }

    if (!validateWarehouseEntryLots()) {
        return;
    }

    const id = $('#warehouse_entry_id').val();
    const url = id
        ? `${window.routes.warehouseEntryUpdate}/${id}`
        : window.routes.warehouseEntryStore;
    const formData = new FormData(form);

    // Al editar pueden permanecer metadatos de archivos existentes en el formulario.
    // Se reconstruyen ambas colecciones para enviar únicamente uploads nuevos.
    Array.from(formData.keys())
        .filter(key => key.startsWith('warehouse_entry_documents[')
            || key.startsWith('warehouse_entry_lot_documents['))
        .forEach(key => formData.delete(key));

    if (id) {
        formData.append('_method', 'PUT');
    }

    warehouseEntryPendingDocuments
        .filter(document => document.file instanceof File)
        .forEach(function (document, index) {
        formData.append(`warehouse_entry_documents[${index}][type]`, document.type);
        formData.append(`warehouse_entry_documents[${index}][description]`, document.description || '');
        formData.append(`warehouse_entry_documents[${index}][file]`, document.file);
    });
    warehouseEntryPendingLotDocuments
        .filter(document => document.file instanceof File)
        .forEach(function (document, index) {
        formData.append(`warehouse_entry_lot_documents[${index}][item_index]`, document.itemIndex);
        formData.append(`warehouse_entry_lot_documents[${index}][lot_key]`, document.lotKey);
        formData.append(`warehouse_entry_lot_documents[${index}][type]`, document.type);
        formData.append(`warehouse_entry_lot_documents[${index}][description]`, document.description || '');
        formData.append(`warehouse_entry_lot_documents[${index}][file]`, document.file);
    });
    warehouseEntryExpenses.forEach(function (expense, index) {
        Object.entries(expense).forEach(function ([field, value]) {
            if (['file', 'invoice_file', 'payment_proof_file', 'documents', 'distributions'].includes(field) || value === null || value === undefined) return;
            formData.append(`expenses[${index}][${field}]`, typeof value === 'boolean' ? (value ? '1' : '0') : value);
        });
        (expense.distributions || []).forEach(function (distribution, distributionIndex) {
            formData.append(`expenses[${index}][distributions][${distributionIndex}][item_index]`, distribution.item_index);
            formData.append(`expenses[${index}][distributions][${distributionIndex}][distributed_amount]`, distribution.distributed_amount);
        });
        if (expense.invoice_file) formData.append(`expenses[${index}][invoice_file]`, expense.invoice_file);
        if (expense.payment_proof_file) formData.append(`expenses[${index}][payment_proof_file]`, expense.payment_proof_file);
    });

    $.ajax({
        url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false
    })
        .done(function (response) {
            $('#warehouseEntryModal').modal('hide');
            tableWarehouseEntry.ajax.reload(null, false);
            warehouseEntryPendingDocuments = [];

            if (!id && response.pdf_url) {
                window.open(response.pdf_url, '_blank');
            }

            Swal.fire({
                icon: 'success',
                title: response.message || 'Ingreso guardado correctamente.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2800
            });
        })
        .fail(function (xhr) {
            if (xhr.status === 422) {
                showWarehouseEntryValidationErrors(xhr.responseJSON?.errors || {});
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo guardar el ingreso.'
            });
        });
}

function showWarehouseEntryValidationErrors(errors) {
    const messages = [];

    Object.keys(errors).forEach(function (name) {
        const message = Array.isArray(errors[name]) ? errors[name][0] : errors[name];
        if (message) messages.push(message);
        const input = $(`[name="${name}"]`);
        input.addClass('is-invalid');
        input.closest('.form-group, td').find('.invalid-feedback').first().text(message);
    });

    const hasDocumentError = Object.keys(errors).some(name =>
        name.startsWith('warehouse_entry_documents.')
        || name.startsWith('warehouse_entry_lot_documents.'));

    Swal.fire({
        icon: 'warning',
        title: hasDocumentError ? 'Revise los documentos adjuntos' : 'Revise los datos ingresados',
        text: messages[0] || (hasDocumentError
            ? 'No se pudo guardar el ingreso de almacén. Revise los documentos adjuntos.'
            : 'Hay campos pendientes o con valores inválidos.')
    });
}

function applySelectedSupplierOrderHeader() {
    const option = $('#warehouse_entry_supplier_purchase_order_id option:selected');
    const orderId = option.val();

    if (!orderId) {
        warehouseEntrySourceOrderTotal = null;
        renderWarehouseEntryOrderAmountDifference(parseWarehouseEntryNumber($('#warehouse_entry_grand_total').val()));
        updateWarehouseEntryDeliveryCostContext('');
        setWarehouseEntrySupplierLocked(false);
        $('#warehouse_entry_supplier_ruc').val('');
        $('#warehouse_entry_guide_ruc').val('');
        return;
    }

    setWarehouseEntrySupplierLocked(true);
    $('#warehouse_entry_purchase_order_number').val(option.data('code') || '');
    $('#warehouse_entry_company_id').val(option.data('company-id') || '').trigger('change.select2');
    setWarehouseEntrySupplier(option.data('supplier-id') || '');
    $('#warehouse_entry_currency_id').val(option.data('currency-id') || '').trigger('change.select2').trigger('change');
    updateWarehouseEntryDeliveryCostContext(option.data('delivery-type') || '');
}

function handleWarehouseEntrySupplierOrderSelection() {
    const orderId = String($('#warehouse_entry_supplier_purchase_order_id').val() || '');
    const selectionSequence = ++warehouseEntryLogisticsSelectionSequence;

    warehouseEntryLogisticsStatusRequest?.abort();
    warehouseEntryLogisticsStatusRequest = null;

    if (!orderId) {
        applySelectedSupplierOrderHeader();
        return;
    }

    if (warehouseEntryAcknowledgedLogisticsOrders.has(orderId)) {
        acceptWarehouseEntrySupplierOrder(orderId);
        return;
    }

    warehouseEntryLogisticsStatusRequest = $.get(
        `${window.routes.supplierPurchaseOrderLogisticsStatus}/${orderId}/logistics-status`
    )
        .done(function (response) {
            if (selectionSequence !== warehouseEntryLogisticsSelectionSequence
                || String($('#warehouse_entry_supplier_purchase_order_id').val() || '') !== orderId) {
                return;
            }

            if (response.financial_blocked) {
                showWarehouseEntryBlockedAdvanceAlert(response, orderId);
                return;
            }
            if (response.financial_credit_warning) {
                Swal.fire({
                    icon: 'info',
                    title: 'Anticipo pendiente en una compra a crédito',
                    text: 'La orden tiene anticipo pendiente, pero está marcada como crédito. Se permitirá el ingreso a almacén.',
                    confirmButtonText: 'Entendido, continuar',
                    confirmButtonColor: '#1684a7'
                }).then(result => result.isConfirmed && continueWarehouseEntrySelectionValidation(response, orderId));
                return;
            }
            continueWarehouseEntrySelectionValidation(response, orderId);
        })
        .fail(function (xhr) {
            if (xhr.statusText === 'abort'
                || selectionSequence !== warehouseEntryLogisticsSelectionSequence) {
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: 'No se pudo consultar el seguimiento',
                text: 'Puedes continuar con el ingreso o cancelar la selección de la orden.',
                showCancelButton: true,
                confirmButtonText: 'Continuar con ingreso',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d'
            }).then(function (result) {
                if (result.isConfirmed) {
                    warehouseEntryAcknowledgedLogisticsOrders.add(orderId);
                    acceptWarehouseEntrySupplierOrder(orderId);
                } else {
                    cancelWarehouseEntrySupplierOrderSelection();
                }
            });
        })
        .always(function () {
            warehouseEntryLogisticsStatusRequest = null;
        });
}

function continueWarehouseEntrySelectionValidation(response, orderId) {
    if (String($('#warehouse_entry_supplier_purchase_order_id').val() || '') !== String(orderId)) return;
    if (response.is_complete) {
        warehouseEntryAcknowledgedLogisticsOrders.add(orderId);
        acceptWarehouseEntrySupplierOrder(orderId);
        return;
    }
    showWarehouseEntryIncompleteLogisticsAlert(response, orderId);
}

function showWarehouseEntryBlockedAdvanceAlert(response, orderId) {
    const currency = escapeWarehouseEntryHtml(response.payment_currency || '');
    const balance = formatWarehouseEntryMoney(response.advance_balance || 0);
    Swal.fire({
        icon: 'error',
        title: 'Anticipo pendiente',
        html: `<p>Esta orden de compra requiere anticipo y todavía no se encuentra pagado completamente. Para ingresar la mercadería al almacén debe completar el anticipo, pagar el total correspondiente o cambiar la condición de pago a crédito si corresponde.</p><div class="alert alert-warning mb-0"><strong>Saldo pendiente:</strong> ${currency} ${balance}</div>`,
        showCancelButton: true,
        allowOutsideClick: false,
        confirmButtonText: 'Ir a la orden',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#1684a7',
        cancelButtonColor: '#6c757d'
    }).then(function (result) {
        if (String($('#warehouse_entry_supplier_purchase_order_id').val() || '') !== String(orderId)) return;
        if (result.isConfirmed) {
            window.location.assign(response.order_edit_url);
        } else {
            cancelWarehouseEntrySupplierOrderSelection();
        }
    });
}

function showWarehouseEntryIncompleteLogisticsAlert(response, orderId) {
    const pendingSteps = (response.missing_steps || []).length
        ? `<div class="text-left mt-3"><strong class="d-block mb-2">Etapas pendientes:</strong><ul class="mb-0 pl-4">${response.missing_steps.map(step => `<li>${escapeWarehouseEntryHtml(step)}</li>`).join('')}</ul></div>`
        : '';
    const currentStatus = escapeWarehouseEntryHtml(response.current_status || 'Sin seguimiento');

    Swal.fire({
        icon: 'warning',
        title: 'Seguimiento logístico incompleto',
        html: `<p class="mb-2">La orden de compra seleccionada aún tiene etapas logísticas pendientes. Puedes continuar con el ingreso a almacén, pero se recomienda completar el seguimiento para mantener la trazabilidad correcta.</p><div class="alert alert-light border text-left mb-0"><strong>Estado actual:</strong> ${currentStatus}${pendingSteps}</div>`,
        showDenyButton: true,
        showCancelButton: true,
        allowOutsideClick: false,
        confirmButtonText: 'Continuar con ingreso',
        denyButtonText: 'Completar seguimiento',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#198754',
        denyButtonColor: '#1684a7',
        cancelButtonColor: '#6c757d',
        width: 650
    }).then(function (result) {
        if (String($('#warehouse_entry_supplier_purchase_order_id').val() || '') !== orderId) {
            return;
        }

        if (result.isConfirmed) {
            warehouseEntryAcknowledgedLogisticsOrders.add(orderId);
            acceptWarehouseEntrySupplierOrder(orderId);
            return;
        }

        if (result.isDenied) {
            window.location.assign(response.tracking_url);
            return;
        }

        cancelWarehouseEntrySupplierOrderSelection();
    });
}

function acceptWarehouseEntrySupplierOrder(orderId) {
    if (String($('#warehouse_entry_supplier_purchase_order_id').val() || '') !== String(orderId)) {
        return;
    }

    applySelectedSupplierOrderHeader();
    scheduleWarehouseEntrySourceAutoLoad();
}

function cancelWarehouseEntrySupplierOrderSelection() {
    warehouseEntryLogisticsSelectionSequence += 1;
    warehouseEntryLogisticsStatusRequest?.abort();
    warehouseEntryLogisticsStatusRequest = null;
    clearTimeout(warehouseEntrySourceLoadTimer);
    warehouseEntrySourceLoadRequest?.abort();
    warehouseEntrySourceLoadRequest = null;

    $('#warehouse_entry_supplier_purchase_order_id').val('').trigger('change.select2');
    $('#warehouse_entry_purchase_order_number').val('');
    $('#warehouse_entry_company_id,#warehouse_entry_currency_id').val('').trigger('change.select2');
    setWarehouseEntrySupplier('', '');
    setWarehouseEntrySupplierLocked(false);
    warehouseEntrySourceOrderTotal = null;
    updateWarehouseEntryDeliveryCostContext('');
    clearWarehouseEntryItemRows();
    showEmptyWarehouseEntryItemsRow();
    calculateWarehouseEntryTotals();
    updateWarehouseEntryReview();
}

function scheduleWarehouseEntrySourceAutoLoad() {
    clearTimeout(warehouseEntrySourceLoadTimer);

    warehouseEntrySourceLoadTimer = setTimeout(function () {
        loadWarehouseEntrySourceItems({ silent: true });
    }, 250);
}

function loadWarehouseEntrySourceItems(options = {}) {
    const orderId = $('#warehouse_entry_supplier_purchase_order_id').val();
    const entryId = $('#warehouse_entry_id').val();
    const isSilent = Boolean(options.silent);

    if (!orderId) {
        if (!isSilent) {
            Swal.fire({
                icon: 'warning',
                title: 'Seleccione una orden de compra a proveedor.'
            });
        }
        return;
    }

    if (warehouseEntrySourceLoadRequest) {
        warehouseEntrySourceLoadRequest.abort();
    }

    warehouseEntrySourceLoadRequest = $.ajax({
        url: window.routes.warehouseEntryLoadSupplierOrderItems,
        type: 'POST',
        data: {
            supplier_purchase_order_id: orderId,
            warehouse_entry_id: entryId || ''
        }
    })
        .done(function (response) {
            $('#warehouse_entry_company_id').val(response.company_id || '').trigger('change.select2');
            setWarehouseEntrySupplier(response.supplier_id || '', response.supplier_ruc || '');
            setWarehouseEntrySupplierLocked(true);
            $('#warehouse_entry_currency_id').val(response.currency_id || '').trigger('change.select2').trigger('change');
            $('#warehouse_entry_purchase_order_number').val(response.purchase_order_number || '');
            warehouseEntrySourceOrderTotal = parseWarehouseEntryNumber(response.order_total);
            $('#warehouse_entry_payment_method').val(response.payment_method || '');
            $('#warehouse_entry_payment_condition').val(response.payment_condition || '');
            $('#warehouse_entry_affect_igv').val(response.affect_igv ? '1' : '0');
            updateWarehouseEntryDeliveryCostContext(response.delivery_type || '');

            clearWarehouseEntryItemRows();
            (response.items || []).forEach(addWarehouseEntryItemRow);
            showEmptyWarehouseEntryItemsRow();
            calculateWarehouseEntryTotals();

            if (!isSilent) {
                Swal.fire({
                    icon: (response.items || []).length ? 'success' : 'info',
                    title: (response.items || []).length ? 'Items cargados' : 'Sin pendiente',
                    text: (response.items || []).length
                        ? 'Items pendientes cargados desde la orden.'
                        : 'La orden seleccionada no tiene cantidades pendientes por ingresar.'
                });
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
            warehouseEntrySourceLoadRequest = null;
        });
}

function addWarehouseEntryItemRow(data = {}) {
    $('#warehouseEntryItemsEmptyRow').remove();

    const html = $('#warehouseEntryItemRowTemplate')
        .html()
        .replaceAll('__INDEX__', warehouseEntryItemIndex);

    $('#warehouseEntryItemsTbody').append(html);

    const row = $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').last();

    row.find('.item-supplier-purchase-order-item-id').val(data.supplier_purchase_order_item_id || '');
    row.find('.item-entry-id').val(data.id || '');
    row.find('.item-article-id').val(data.article_id || '');
    row.find('.item-article-code').val(data.article_code || '');
    row.find('.item-billing-name').val(data.billing_name_snapshot || '');
    row.find('.item-article-picker').val(data.article_id || '');
    row.find('.item-note').val(data.note || '');
    row.find('.item-unit-id').val(data.unit_id || '');
    row.find('.item-presentation-id').val(data.presentation_id || '');
    row.find('.item-brand-id').val(data.brand_id || '');
    row.find('.item-origin').val(data.origin || '');
    row.find('.item-cost-type').val(data.cost_type || 'PESO');
    const articleOption = row.find('.item-article-picker option:selected');
    row.find('.item-has-batch').val(data.has_batch ?? articleOption.data('has-batch') ?? 0);
    row.find('.item-has-expiration').val(data.has_expiration ?? articleOption.data('has-expiration') ?? 0);
    let lots = Array.isArray(data.lots) ? data.lots.map(normalizeWarehouseEntryLot) : [];
    if (!lots.length && data.lot_number) {
        lots = [{
            lot_code: data.lot_number,
            quantity: parseWarehouseEntryNumber(data.quantity),
            expiration_date: formatWarehouseEntryDate(data.expiration_date),
            manufacturing_date: ''
        }];
    }
    row.data('lots', lots);
    row.find('.item-ordered-quantity').val(formatWarehouseEntryMoney(data.ordered_quantity || 0));
    row.find('.item-quantity').val(formatWarehouseEntryMoney(data.quantity || 1));
    row.find('.item-unit-price').val(formatWarehouseEntryUnitPrice(data.unit_price || 0));

    initWarehouseEntrySelect2(row);

    warehouseEntryItemIndex++;
    refreshWarehouseEntryItemIndexes();
    renderWarehouseEntryLotsSummary(row);
    renderWarehouseEntryLotRows(row);
    calculateWarehouseEntryTotals();
}

function applySelectedWarehouseEntryArticle(row) {
    const option = row.find('.item-article-picker option:selected');
    const articleId = option.val() || '';

    row.find('.item-article-id').val(articleId);
    row.find('.item-article-code').val(option.data('code') || '');
    row.find('.item-billing-name').val(option.data('billing-name') || '');
    row.find('.item-has-batch').val(option.data('has-batch') || 0);
    row.find('.item-has-expiration').val(option.data('has-expiration') || 0);

    if (articleId) {
        row.find('.item-unit-id').val(option.data('unit-id') || '').trigger('change.select2');
        row.find('.item-presentation-id').val(option.data('presentation-id') || '').trigger('change.select2');
        row.find('.item-brand-id').val(option.data('brand-id') || '').trigger('change.select2');
    }
}

function clearWarehouseEntryItemRows() {
    $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').each(function () {
        destroyWarehouseEntryRowSelect2($(this));
    });
    $('#warehouseEntryItemsTbody').empty();
}

function showEmptyWarehouseEntryItemsRow() {
    if ($('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').length) {
        return;
    }

    $('#warehouseEntryItemsTbody').html(`
        <tr id="warehouseEntryItemsEmptyRow">
            <td colspan="14" class="text-center text-muted py-4">
                <i class="fas fa-box-open d-block mb-2"></i>
                Carga una orden o inserta articulos para registrar el ingreso.
            </td>
        </tr>
    `);
}

function refreshWarehouseEntryItemIndexes() {
    $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').each(function (index) {
        $(this).find('.warehouse-entry-item-index').text(index + 1);

        $(this).find('input, select, textarea').each(function () {
            const name = $(this).attr('name');
            if (name) {
                $(this).attr('name', name.replace(/items\[\d+]/, `items[${index}]`));
            }
        });
        syncWarehouseEntryLotInputs($(this), index);
    });
    $('#warehouseEntrySideItemCount').text($('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').length);
    updateWarehouseEntryReview();
}

function normalizeWarehouseEntryLot(lot) {
    return {
        id: lot.id || null,
        client_key: lot.client_key || (lot.id ? `id:${lot.id}` : `new:${Date.now()}:${Math.random().toString(36).slice(2)}`),
        documents: Array.isArray(lot.documents) ? lot.documents : [],
        lot_code: String(lot.lot_code || '').trim().toUpperCase(),
        quantity: parseWarehouseEntryNumber(lot.quantity),
        expiration_date: formatWarehouseEntryDate(lot.expiration_date),
        manufacturing_date: formatWarehouseEntryDate(lot.manufacturing_date)
    };
}

function openWarehouseEntryLotsModal(row) {
    warehouseEntryActiveLotsRow = row;
    const lots = row.data('lots') || [];
    $('#warehouseEntryLotsArticle').text(row.find('.item-billing-name').val() || 'Articulo');
    $('#warehouseEntryLotsTbody').empty();
    lots.forEach(addWarehouseEntryLotEditorRow);
    if (!lots.length) addWarehouseEntryLotEditorRow();
    refreshWarehouseEntryLotEditor();
    $('#warehouseEntryLotsModal').modal({ backdrop: 'static', keyboard: false, show: true });
}

function addWarehouseEntryLotEditorRow(lot = {}) {
    const requireExpiration = Number(warehouseEntryActiveLotsRow?.find('.item-has-expiration').val()) === 1;
    $('#warehouseEntryLotsTbody').append(`
        <tr>
            <td class="d-none"><input type="hidden" class="lot-editor-id" value="${lot.id || ''}"><input type="hidden" class="lot-editor-key" value="${escapeWarehouseEntryHtml(lot.client_key || '')}"></td>
            <td class="lot-editor-index align-middle"></td>
            <td><input type="text" class="form-control form-control-sm lot-editor-code text-uppercase" maxlength="100" value="${escapeWarehouseEntryHtml(lot.lot_code || '')}"></td>
            <td><input type="number" class="form-control form-control-sm lot-editor-quantity text-right" min="0.0001" step="0.0001" value="${lot.quantity || ''}"></td>
            <td><input type="date" class="form-control form-control-sm lot-editor-expiration" ${requireExpiration ? 'required' : ''} value="${formatWarehouseEntryDate(lot.expiration_date)}"></td>
            <td><button type="button" class="btn btn-outline-danger btn-sm btnRemoveWarehouseEntryLot"><i class="fas fa-trash-alt"></i></button></td>
        </tr>`);
    refreshWarehouseEntryLotEditor();
}

function refreshWarehouseEntryLotEditor() {
    if (!warehouseEntryActiveLotsRow) return;
    const quantity = parseWarehouseEntryNumber(warehouseEntryActiveLotsRow.find('.item-quantity').val());
    let total = 0;
    $('#warehouseEntryLotsTbody tr').each(function (index) {
        $(this).find('.lot-editor-index').text(index + 1);
        total += parseWarehouseEntryNumber($(this).find('.lot-editor-quantity').val());
    });
    const difference = Math.round((quantity - total) * 10000) / 10000;
    $('#warehouseEntryLotsQuantity').text(formatWarehouseEntryMoney(quantity));
    $('#warehouseEntryLotsTotal').text(formatWarehouseEntryMoney(total));
    $('#warehouseEntryLotsDifference').text(formatWarehouseEntryMoney(Math.abs(difference)))
        .toggleClass('text-success', Math.abs(difference) <= 0.0001)
        .toggleClass('text-warning', difference > 0.0001)
        .toggleClass('text-danger', difference < -0.0001);
    $('#warehouseEntryLotsError').text(difference > 0.0001 ? `Faltan ${formatWarehouseEntryMoney(difference)}.` : difference < -0.0001 ? `Excede ${formatWarehouseEntryMoney(Math.abs(difference))}.` : '');
}

function applyWarehouseEntryLots() {
    const lots = [];
    const previousLots = warehouseEntryActiveLotsRow.data('lots') || [];
    let error = '';
    $('#warehouseEntryLotsTbody tr').each(function () {
        const lot = normalizeWarehouseEntryLot({
            id: $(this).find('.lot-editor-id').val() || null,
            client_key: $(this).find('.lot-editor-key').val() || null,
            lot_code: $(this).find('.lot-editor-code').val(),
            quantity: $(this).find('.lot-editor-quantity').val(),
            expiration_date: $(this).find('.lot-editor-expiration').val()
        });
        lot.documents = previousLots.find(previous => previous.client_key === lot.client_key)?.documents || [];
        if (!lot.lot_code) error = 'Todos los lotes deben tener codigo.';
        else if (lot.quantity <= 0) error = 'La cantidad de cada lote debe ser mayor a cero.';
        else if (Number(warehouseEntryActiveLotsRow.find('.item-has-expiration').val()) === 1 && !lot.expiration_date) error = 'La fecha de vencimiento es obligatoria para este articulo.';
        lots.push(lot);
    });
    const expected = parseWarehouseEntryNumber(warehouseEntryActiveLotsRow.find('.item-quantity').val());
    const total = lots.reduce((sum, lot) => sum + lot.quantity, 0);
    if (!error && Math.abs(total - expected) > 0.0001) error = 'La suma de los lotes debe coincidir con la cantidad ingresada del articulo.';
    if (error) {
        $('#warehouseEntryLotsError').text(error);
        return;
    }
    warehouseEntryActiveLotsRow.data('lots', lots);
    renderWarehouseEntryLotsSummary(warehouseEntryActiveLotsRow);
    renderWarehouseEntryLotRows(warehouseEntryActiveLotsRow);
    refreshWarehouseEntryLotDocumentItems();
    $('#warehouseEntryLotsModal').modal('hide');
}

function renderWarehouseEntryLotsSummary(row) {
    const lots = row.data('lots') || [];
    const expected = parseWarehouseEntryNumber(row.find('.item-quantity').val());
    const total = lots.reduce((sum, lot) => sum + parseWarehouseEntryNumber(lot.quantity), 0);
    const difference = Math.round((expected - total) * 10000) / 10000;
    let state = 'text-muted';
    let detail = 'Sin lotes';
    if (lots.length) {
        state = Math.abs(difference) <= 0.0001 ? 'text-success' : difference > 0 ? 'text-warning' : 'text-danger';
        detail = `${lots.length} ${lots.length === 1 ? 'lote' : 'lotes'} · Total: ${formatWarehouseEntryMoney(total)} / ${formatWarehouseEntryMoney(expected)}`;
    }
    row.find('.warehouse-entry-lots-summary').attr('class', `warehouse-entry-lots-summary mt-1 small ${state}`).text(detail);
}

function renderWarehouseEntryLotRows(row) {
    row.nextUntil('.warehouse-entry-item-row').filter('.warehouse-entry-lot-visual-row').remove();

    const lots = Array.isArray(row.data('lots')) ? row.data('lots') : [];
    const quantityInput = row.find('.item-quantity');
    const quantityDisplay = row.find('.warehouse-entry-lot-quantity-display');
    const firstLotContainer = row.find('.warehouse-entry-first-lot');

    if (!lots.length) {
        quantityInput.removeClass('d-none');
        quantityDisplay.empty().addClass('d-none');
        firstLotContainer.empty();
        return;
    }

    const unitPrice = parseWarehouseEntryNumber(row.find('.item-unit-price').val());
    const firstLot = lots[0];
    quantityInput.addClass('d-none');
    quantityDisplay.removeClass('d-none').text(formatWarehouseEntryMoney(firstLot.quantity));
    firstLotContainer.html(warehouseEntryLotBadge(firstLot, 1));

    const article = row.find('.item-article-picker option:selected').text().trim()
        || row.find('.item-billing-name').val()
        || '-';
    const note = row.find('.item-note').val() || '-';
    const unit = warehouseEntrySelectedText(row.find('.item-unit-id'));
    const presentation = warehouseEntrySelectedText(row.find('.item-presentation-id'));
    const brand = warehouseEntrySelectedText(row.find('.item-brand-id'));
    const origin = row.find('.item-origin').val() || '-';
    const costType = row.find('.item-cost-type').val() || '-';
    const visualRows = lots.slice(1).map(function (lot, lotIndex) {
        const lotTotal = parseWarehouseEntryNumber(lot.quantity) * unitPrice;
        return `
            <tr class="warehouse-entry-lot-visual-row">
                <td class="text-center"><span class="warehouse-entry-lot-branch"><i class="fas fa-level-up-alt fa-rotate-90"></i></span></td>
                <td><span class="warehouse-entry-lot-repeat">${escapeWarehouseEntryHtml(article)}</span></td>
                <td>${escapeWarehouseEntryHtml(note)}</td>
                <td>${escapeWarehouseEntryHtml(unit)}</td>
                <td>${escapeWarehouseEntryHtml(presentation)}</td>
                <td>${escapeWarehouseEntryHtml(brand)}</td>
                <td>${escapeWarehouseEntryHtml(origin)}</td>
                <td>${escapeWarehouseEntryHtml(costType)}</td>
                <td>${warehouseEntryLotBadge(lot, lotIndex + 2)}</td>
                <td class="text-right text-muted">-</td>
                <td class="text-right text-success font-weight-bold">${formatWarehouseEntryMoney(lot.quantity)}</td>
                <td class="text-right">${formatWarehouseEntryMoney(unitPrice)}</td>
                <td class="text-right warehouse-entry-lot-reference-total">${formatWarehouseEntryMoney(lotTotal)}</td>
                <td></td>
            </tr>`;
    }).join('');

    row.after(visualRows);
}

function warehouseEntrySelectedText(select) {
    const text = select.find('option:selected').text().trim();
    return text && text !== '-' ? text : '-';
}

function warehouseEntryLotBadge(lot, number) {
    const expiration = formatWarehouseEntryDate(lot.expiration_date);
    const expirationLabel = expiration
        ? `<small class="d-block text-muted mt-1">Vence: ${escapeWarehouseEntryHtml(formatWarehouseEntryDisplayDate(expiration))}</small>`
        : '';
    const documentCount = (lot.documents || []).length + warehouseEntryPendingLotDocuments.filter(document => document.lotKey === lot.client_key).length;
    return `<span class="badge warehouse-entry-lot-badge"><i class="fas fa-tag mr-1"></i>${escapeWarehouseEntryHtml(lot.lot_code || `Lote ${number}`)}</span>${expirationLabel}<button type="button" class="btn btn-outline-info btn-xs mt-1 btnWarehouseEntryLotDocuments" data-lot-key="${escapeWarehouseEntryHtml(lot.client_key)}"><i class="fas fa-paperclip"></i> ${documentCount}</button>`;
}

function syncWarehouseEntryLotInputs(row, itemIndex) {
    const container = row.find('.warehouse-entry-lots-inputs').empty();
    (row.data('lots') || []).forEach(function (lot, lotIndex) {
        ['id', 'client_key', 'lot_code', 'quantity', 'expiration_date', 'manufacturing_date'].forEach(function (field) {
            $('<input>', { type: 'hidden', name: `items[${itemIndex}][lots][${lotIndex}][${field}]`, value: lot[field] || '' }).appendTo(container);
        });
    });
}

function validateWarehouseEntryLots() {
    let message = '';
    $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').each(function (index) {
        const row = $(this);
        const lots = row.data('lots') || [];
        const required = Number(row.find('.item-has-batch').val()) === 1;
        const expected = parseWarehouseEntryNumber(row.find('.item-quantity').val());
        const total = lots.reduce((sum, lot) => sum + parseWarehouseEntryNumber(lot.quantity), 0);
        const name = row.find('.item-billing-name').val() || `#${index + 1}`;
        if (required && !lots.length) message = `El articulo ${name} debe tener al menos un lote.`;
        else if (lots.length && Math.abs(total - expected) > 0.0001) message = `La suma de lotes del articulo ${name} es ${formatWarehouseEntryMoney(total)}, pero la cantidad ingresada es ${formatWarehouseEntryMoney(expected)}.`;
        if (message) return false;
        syncWarehouseEntryLotInputs(row, index);
    });
    if (message) Swal.fire({ icon: 'warning', title: 'Lotes incompletos', text: message });
    return !message;
}

function calculateWarehouseEntryTotals() {
    const affectIgv = $('#warehouse_entry_affect_igv').val() === '1';
    let subtotal = 0;
    let igv = 0;
    let total = 0;

    $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').each(function () {
        const row = $(this);
        const quantity = parseWarehouseEntryNumber(row.find('.item-quantity').val());
        const unitPrice = parseWarehouseEntryNumber(row.find('.item-unit-price').val());
        const lineTotal = Math.round((quantity * unitPrice + Number.EPSILON) * 100) / 100;
        const lineSubtotal = affectIgv
            ? Math.round((lineTotal / 1.18 + Number.EPSILON) * 100) / 100
            : 0;
        const lineIgv = affectIgv
            ? Math.round((lineTotal - lineSubtotal + Number.EPSILON) * 100) / 100
            : 0;

        subtotal += lineSubtotal;
        igv += lineIgv;
        total += lineTotal;
        row.find('.item-line-total').text(formatWarehouseEntryMoney(lineTotal));
        renderWarehouseEntryLotRows(row);
    });

    $('#warehouse_entry_subtotal').val(formatWarehouseEntryMoney(subtotal));
    $('#warehouse_entry_igv').val(formatWarehouseEntryMoney(igv));
    $('#warehouse_entry_grand_total').val(formatWarehouseEntryMoney(total));
    $('#warehouseEntrySideGrandTotal').text(formatWarehouseEntryMoney(total));
    syncWarehouseEntryPayableAmount(total);
    renderWarehouseEntryOrderAmountDifference(total);
    updateWarehouseEntryReview();
}

function renderWarehouseEntryOrderAmountDifference(entryTotal) {
    const warning = $('#warehouseEntryOrderAmountWarning');
    if (warehouseEntrySourceOrderTotal === null) return warning.addClass('d-none').empty();
    const difference = Math.round((warehouseEntrySourceOrderTotal - entryTotal + Number.EPSILON) * 100) / 100;
    if (Math.abs(difference) < 0.01) return warning.addClass('d-none').empty();
    warning.removeClass('d-none').html(`<strong>El monto del ingreso no coincide con el total de la orden de compra a proveedor.</strong><br>Total OC proveedor: S/ ${formatWarehouseEntryMoney(warehouseEntrySourceOrderTotal)} · Monto ingreso: S/ ${formatWarehouseEntryMoney(entryTotal)} · Diferencia: S/ ${formatWarehouseEntryMoney(Math.abs(difference))}`);
}

function updateWarehouseEntryReview() {
    const itemRows = $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row');
    const itemCount = itemRows.length;
    const documentCount = warehouseEntryExistingDocuments.length + warehouseEntryPendingDocuments.length;
    let lotCount = 0;
    const alerts = [];

    itemRows.each(function () {
        const row = $(this);
        const lots = row.data('lots') || [];
        const expected = parseWarehouseEntryNumber(row.find('.item-quantity').val());
        const lotTotal = lots.reduce((sum, lot) => sum + parseWarehouseEntryNumber(lot.quantity), 0);
        const name = row.find('.item-billing-name').val() || 'Artículo sin nombre';
        lotCount += lots.length;
        if (Number(row.find('.item-has-batch').val()) === 1 && !lots.length) alerts.push(`${name}: requiere lotes.`);
        else if (lots.length && Math.abs(lotTotal - expected) > 0.0001) alerts.push(`${name}: la suma de lotes no coincide.`);
    });

    if (!$('#warehouse_entry_company_id').val()) alerts.push('Seleccione una empresa.');
    if (!$('#warehouse_entry_supplier_id').val()) alerts.push('Seleccione un proveedor.');
    if (!$('#warehouse_entry_warehouse_id').val()) alerts.push('Seleccione un almacén.');
    if (!itemCount) alerts.push('Agregue al menos un artículo.');

    $('#warehouseEntrySideItemCount').text(itemCount);
    $('#warehouseEntrySideDocumentCount').text(documentCount);
    const companyText = $('#warehouse_entry_company_id option:selected').text().trim();
    $('#warehouseEntrySideCompany').text($('#warehouse_entry_company_id').val() ? companyText : 'Seleccione empresa');

    const selectedText = selector => $(selector).find('option:selected').text().trim() || '-';
    const alertHtml = alerts.length
        ? `<div class="warehouse-entry-review-alert"><i class="fas fa-exclamation-triangle"></i><div><strong>Revisión pendiente</strong><ul>${alerts.map(alert => `<li>${escapeWarehouseEntryHtml(alert)}</li>`).join('')}</ul></div></div>`
        : '<div class="warehouse-entry-review-ok"><i class="fas fa-check-circle"></i><div><strong>Ingreso listo para guardar</strong><small>No se detectaron inconsistencias en artículos o lotes.</small></div></div>';
    const freightTotal = warehouseEntryExpenses.filter(expense => expense.expense_category === 'freight_transport').reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const otherExpenseTotal = warehouseEntryExpenses.filter(expense => expense.expense_category !== 'freight_transport').reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const informativeTotal = warehouseEntryExpenses.filter(expense => !expense.affects_inventory_cost).reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const inventoryTotal = warehouseEntryExpenses.filter(expense => expense.affects_inventory_cost).reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);

    $('#warehouseEntryReview').html(`
        <div class="card border-0 shadow-sm warehouse-entry-review-card">
            <div class="card-header border-0 warehouse-entry-section-header"><h6 class="mb-0"><i class="fas fa-clipboard-check text-info mr-1"></i>Revisión antes de guardar</h6></div>
            <div class="card-body"><div class="warehouse-entry-review-grid">
                <div><small>Empresa</small><strong>${escapeWarehouseEntryHtml(selectedText('#warehouse_entry_company_id'))}</strong></div>
                <div><small>Proveedor</small><strong>${escapeWarehouseEntryHtml(selectedText('#warehouse_entry_supplier_id'))}</strong></div>
                <div><small>Almacén</small><strong>${escapeWarehouseEntryHtml(selectedText('#warehouse_entry_warehouse_id'))}</strong></div>
                <div><small>Documento</small><strong>${escapeWarehouseEntryHtml([$('#warehouse_entry_document_series').val(), $('#warehouse_entry_document_number').val()].filter(Boolean).join('-') || '-')}</strong></div>
                <div><small>Fecha</small><strong>${escapeWarehouseEntryHtml(formatWarehouseEntryDisplayDate($('#warehouse_entry_document_date').val()))}</strong></div>
                <div><small>Artículos</small><strong>${itemCount}</strong></div><div><small>Lotes</small><strong>${lotCount}</strong></div><div><small>Documentos</small><strong>${documentCount}</strong></div>
                <div><small>Mercadería</small><strong>${formatWarehouseEntryMoney($('#warehouse_entry_subtotal').val())}</strong></div><div><small>IGV</small><strong>${formatWarehouseEntryMoney($('#warehouse_entry_igv').val())}</strong></div><div><small>Total compra</small><strong>${formatWarehouseEntryMoney($('#warehouse_entry_grand_total').val())}</strong></div>
                <div><small>Flete / transporte</small><strong>${formatWarehouseEntryMoney(freightTotal)}</strong></div><div><small>Otros gastos</small><strong>${formatWarehouseEntryMoney(otherExpenseTotal)}</strong></div><div><small>Total informativo</small><strong>${formatWarehouseEntryMoney(informativeTotal)}</strong></div><div><small>Afecta inventario</small><strong>${formatWarehouseEntryMoney(inventoryTotal)}</strong></div><div class="warehouse-entry-review-grand"><small>Costo real de ingreso</small><strong>${formatWarehouseEntryMoney(parseWarehouseEntryNumber($('#warehouse_entry_grand_total').val()) + inventoryTotal)}</strong></div>
            </div>${alertHtml}</div>
        </div>`);
}

function syncWarehouseEntryPayableAmount(total = null) {
    const grandTotal = total !== null
        ? total
        : parseWarehouseEntryNumber($('#warehouse_entry_grand_total').val());
    const payable = $('#warehouse_entry_payable_amount');

    payable.prop('readonly', true);
    payable.val(formatWarehouseEntryMoney(grandTotal));
}

function updateWarehouseEntryCurrency() {
    const option = $('#warehouse_entry_currency_id option:selected');
    const symbol = option.data('symbol') || option.text().split('-')[0]?.trim() || 'S/';
    $('.warehouse-entry-currency-symbol').text(symbol);
}

function setWarehouseEntrySupplier(supplierId, supplierRuc = null) {
    $('#warehouse_entry_supplier_id').val(supplierId || '').trigger('change.select2');
    $('#warehouse_entry_supplier_id_hidden').val(supplierId || '');
    syncWarehouseEntrySupplierFields(supplierRuc);
}

function syncWarehouseEntrySupplierFields(supplierRuc = null) {
    const supplier = $('#warehouse_entry_supplier_id');
    const option = supplier.find('option:selected');
    const supplierId = supplier.val() || '';
    const supplierName = option.text().trim();
    const ruc = supplierRuc !== null ? supplierRuc : (option.data('ruc') || '');

    $('#warehouse_entry_supplier_id_hidden').val(supplierId);
    $('#warehouseEntrySideSupplier').text(supplierId ? supplierName : 'Seleccione proveedor');
    $('#warehouse_entry_supplier_ruc').val(ruc || '');
    $('#warehouse_entry_guide_ruc').val(ruc || '');
}

function setWarehouseEntrySupplierLocked(locked) {
    const supplier = $('#warehouse_entry_supplier_id');

    supplier.prop('disabled', locked);
    $('#warehouse_entry_supplier_id_hidden').prop('disabled', !locked);

    if ($.fn.select2 && supplier.data('select2')) {
        supplier.trigger('change.select2');
    }
}

function toggleWarehouseEntryExpenseDistribution() {
    const affects = $('#warehouse_entry_expense_affects_cost').val() === '1' && $('#warehouse_entry_expense_cost_origin').val() !== 'included_in_purchase_price';
    $('#warehouse_entry_expense_distribution_method').prop('disabled', !affects).val(affects ? $('#warehouse_entry_expense_distribution_method').val() : '');
    if (!affects) $('#warehouseEntryExpenseManualDistribution').addClass('d-none').empty();
}

function renderWarehouseEntryExpenseTypes(selected = '') {
    const simpleType = ['agency_freight', 'transport_agency', 'courier', 'shipping'].includes(selected)
        ? 'agency_freight'
        : (['pickup_transfer', 'agency_pickup_to_warehouse', 'agency_direct_to_warehouse', 'supplier_warehouse_pickup', 'transfer_to_agency', 'truck', 'mobility', 'delivery', 'transfer', 'flete', 'transporte', 'movilidad'].includes(selected) ? 'pickup_transfer' : 'other');
    $('#warehouse_entry_expense_type').val(simpleType);
    const isAgency = simpleType === 'agency_freight';
    $('#warehouseEntryExpenseAgencyGroup').toggleClass('d-none', !isAgency);
    $('#warehouseEntryExpenseResponsibleGroup').toggleClass('d-none', isAgency);
    $('#warehouse_entry_expense_category').val(simpleType === 'other' ? 'other_expense' : 'freight_transport');
    $('#warehouse_entry_expense_affects_cost').val('1');
    $('#warehouse_entry_expense_distribution_method').val('quantity').prop('disabled', false);
}

function warehouseEntryExpenseMapping(type) {
    if (type === 'agency_freight') {
        return { group: 'transporte', category: 'transporte', cost_type: 'flete_agencia', origin: 'agencia_envio', cost_origin: 'agencia_envio', expense_category: 'freight_transport', affects_inventory_cost: true };
    }
    if (type === 'pickup_transfer') {
        return { group: 'transporte', category: 'transporte', cost_type: 'recojo_traslado', origin: 'responsable_externo', cost_origin: 'responsable_externo', expense_category: 'freight_transport', affects_inventory_cost: true };
    }
    if (type === 'other') {
        return { group: 'otros_gastos', category: 'otros_gastos', cost_type: 'otros_gastos', origin: 'responsable_externo', cost_origin: 'responsable_externo', expense_category: 'other_expense', affects_inventory_cost: true };
    }
    return null;
}

function toggleWarehouseEntryExpenseDocumentFields() {
    const documentType = $('#warehouse_entry_expense_document_type').val();
    const withoutDocument = documentType === 'SIN_COMPROBANTE';
    const supportsIgv = ['FACTURA', 'BOLETA'].includes(documentType);
    $('#warehouse_entry_expense_document_series, #warehouse_entry_expense_document_number').prop('disabled', withoutDocument);
    if (withoutDocument) $('#warehouse_entry_expense_document_series, #warehouse_entry_expense_document_number').val('');
    $('#warehouse_entry_expense_affects_igv').prop('disabled', !supportsIgv);
    if (!supportsIgv) $('#warehouse_entry_expense_affects_igv').val('0');
    $('#warehouseEntryExpenseIgvHelp').text(supportsIgv
        ? 'Indique si el importe incluye IGV.'
        : 'Los recibos o costos sin comprobante no generan IGV para el análisis.');
}

function calculateWarehouseEntryExpenseTax(amount, affectsIgv) {
    const total = Math.round(parseWarehouseEntryNumber(amount) * 100) / 100;
    const taxableAmount = affectsIgv ? Math.round((total / 1.18) * 100) / 100 : total;
    return {
        affects_igv: affectsIgv,
        igv_rate: affectsIgv ? 18 : 0,
        taxable_amount: taxableAmount,
        igv_amount: affectsIgv ? Math.round((total - taxableAmount) * 100) / 100 : 0,
        total_amount: total
    };
}

function normalizeWarehouseEntryDeliveryType(value) {
    const normalized = String(value || '').trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[-.]/g, '').replace(/\s+/g, '_');
    return ['agencia_de_transporte', 'agencia_transporte', 'en_agencia', 'transporte'].includes(normalized)
        ? 'agencia'
        : ({ recojo_de_almacen: 'recojo_almacen', transportista_del_proveedor: 'transportista_proveedor' }[normalized] || normalized);
}

function updateWarehouseEntryDeliveryCostContext(deliveryType = '') {
    warehouseEntryDeliveryType = normalizeWarehouseEntryDeliveryType(deliveryType);
    const presentations = {
        agencia: ['Agencia', 'Puede registrar el flete de agencia y el recojo o entrega final hasta el almacén.', 'fa-shipping-fast'],
        recojo_almacen: ['Recojo de almacén', 'Puede registrar el recojo donde el proveedor, traslado a agencia, flete y recojo final.', 'fa-warehouse'],
        transportista_proveedor: ['Transportista del proveedor', 'El proveedor asumió el traslado hasta el almacén. Solo registre costos si hubo un gasto adicional pagado por la empresa.', 'fa-truck'],
    };
    const presentation = presentations[warehouseEntryDeliveryType];
    $('#warehouseEntryDeliveryCostHelp').html(presentation
        ? `<i class="fas ${presentation[2]}"></i><div><strong>Tipo de entrega: ${presentation[0]}</strong><span>${presentation[1]}</span></div>`
        : '<i class="fas fa-info-circle"></i><div><strong>Seleccione una orden de compra</strong><span>El tipo de entrega definirá las etapas de transporte disponibles.</span></div>');
    renderWarehouseEntryExpenseTypes($('#warehouse_entry_expense_type').val() || 'agency_freight');
}

function warehouseEntryExpenseTypeLabel(type) {
    for (const options of Object.values(warehouseEntryOfficialFreightTypes)) {
        if (options[type]) return options[type];
    }
    return warehouseEntryExpenseTypeLabels[type] || type;
}

function applyWarehouseEntryExpenseOriginRules() {
    const included = $('#warehouse_entry_expense_cost_origin').val() === 'included_in_purchase_price';
    $('#warehouse_entry_expense_amount').prop('readonly', included).attr('placeholder', included ? 'Sin importe adicional' : '').val(included ? '0.00' : $('#warehouse_entry_expense_amount').val());
    $('#warehouse_entry_expense_affects_cost').prop('disabled', included).val(included ? '0' : $('#warehouse_entry_expense_affects_cost').val());
    if (included && !$('#warehouse_entry_expense_description').val().trim()) $('#warehouse_entry_expense_description').val('El proveedor asumió el flete / costo incluido en la compra.');
    toggleWarehouseEntryExpenseDistribution();
}

function renderWarehouseEntryExpenseManualDistribution() {
    const container = $('#warehouseEntryExpenseManualDistribution');
    if ($('#warehouse_entry_expense_distribution_method').val() !== 'manual') return container.addClass('d-none').empty();
    const rows = $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').map(function (index) {
        const name = $(this).find('.item-billing-name').val() || `Artículo ${index + 1}`;
        return `<div class="input-group input-group-sm mb-1"><div class="input-group-prepend"><span class="input-group-text">${escapeWarehouseEntryHtml(name)}</span></div><input type="number" min="0" step="0.01" class="form-control text-right warehouse-entry-expense-manual-amount" data-item-index="${index}" value="0.00"></div>`;
    }).get().join('');
    container.removeClass('d-none').html(`<label class="small font-weight-bold">DISTRIBUCIÓN MANUAL POR ARTÍCULO</label>${rows || '<div class="alert alert-warning">Agregue artículos antes de distribuir el gasto.</div>'}`);
}

function resetWarehouseEntryExpenseEditor() {
    warehouseEntryExpenseEditorRemovedDocuments = { invoice: false, payment_proof: false };
    $('#warehouse_entry_expense_edit_index').val('');
    $('#warehouse_entry_expense_provider_id,#warehouse_entry_expense_provider_name,#warehouse_entry_expense_document_series,#warehouse_entry_expense_document_number,#warehouse_entry_expense_document_date,#warehouse_entry_expense_description,#warehouse_entry_expense_invoice_file,#warehouse_entry_expense_payment_proof_file,#warehouse_entry_expense_shipping_agency_id').val('');
    $('#warehouse_entry_expense_provider_ruc,#warehouse_entry_expense_amount').val('');
    $('#warehouse_entry_expense_affects_cost').val('1');
    $('#warehouse_entry_expense_category').val('freight_transport');
    $('#warehouse_entry_expense_cost_origin').val('third_party');
    $('#warehouse_entry_expense_type').val('agency_freight');
    $('#warehouse_entry_expense_document_type').val('FACTURA');
    $('#warehouse_entry_expense_affects_igv').val('');
    renderWarehouseEntryExpenseTypes('agency_freight');
    $('#warehouse_entry_expense_distribution_method').val('quantity').prop('disabled', false);
    toggleWarehouseEntryExpenseDocumentFields();
    $('#warehouseEntryExpenseManualDistribution').addClass('d-none').empty();
    $('#btnAddWarehouseEntryExpense').html('<i class="fas fa-plus mr-1"></i>Agregar costo');
    renderWarehouseEntryExpenseFileSelection('invoice', null);
    renderWarehouseEntryExpenseFileSelection('payment_proof', null);
}

function formatWarehouseEntryFileSize(bytes) {
    const size = Number(bytes || 0);
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function warehouseEntryExpenseDocumentConfig(type) {
    return type === 'payment_proof'
        ? { input: '#warehouse_entry_expense_payment_proof_file', picker: '#warehouseEntryExpensePaymentProofFilePicker', name: '#warehouseEntryExpensePaymentProofFileName', size: '#warehouseEntryExpensePaymentProofFileSize', icon: '#warehouseEntryExpensePaymentProofFileTypeIcon', view: '#warehouseEntryExpensePaymentProofView', fileField: 'payment_proof_file', removeField: 'remove_payment_proof_document', label: 'constancia de pago' }
        : { input: '#warehouse_entry_expense_invoice_file', picker: '#warehouseEntryExpenseInvoiceFilePicker', name: '#warehouseEntryExpenseInvoiceFileName', size: '#warehouseEntryExpenseInvoiceFileSize', icon: '#warehouseEntryExpenseInvoiceFileTypeIcon', view: '#warehouseEntryExpenseInvoiceView', fileField: 'invoice_file', removeField: 'remove_invoice_document', label: 'factura' };
}

function normalizeWarehouseEntryExpenseDocumentType(type) {
    return String(type || '').toLowerCase() === 'payment_proof' ? 'payment_proof' : 'invoice';
}

function warehouseEntryExpenseStoredDocument(expense, type) {
    const config = warehouseEntryExpenseDocumentConfig(type);
    if (expense?.[config.removeField]) return null;
    return [...(expense?.documents || [])]
        .filter(document => normalizeWarehouseEntryExpenseDocumentType(document.document_type) === type)
        .sort((left, right) => Number(right.id || 0) - Number(left.id || 0))[0] || null;
}

function handleWarehouseEntryExpenseFileChange() {
    const type = $(this).data('expense-document-type');
    const config = warehouseEntryExpenseDocumentConfig(type);
    const file = this.files?.[0] || null;
    if (!file) return renderWarehouseEntryExpenseFileSelection(type, null);
    const extension = getWarehouseEntryFileExtension(file.name);
    const editIndex = $('#warehouse_entry_expense_edit_index').val();
    const editingExpense = editIndex === '' ? null : warehouseEntryExpenses[Number(editIndex)];
    const previousFile = editingExpense?.[config.fileField] || warehouseEntryExpenseStoredDocument(editingExpense, type);
    if (!['pdf', 'jpg', 'jpeg', 'png', 'webp'].includes(extension)) {
        $(config.input).val('');
        renderWarehouseEntryExpenseFileSelection(type, previousFile);
        const message = type === 'payment_proof'
            ? 'El archivo de la constancia de pago debe ser PDF, JPG, JPEG, PNG o WEBP.'
            : 'El archivo de la factura debe ser PDF, JPG, JPEG, PNG o WEBP.';
        return Swal.fire('Archivo no permitido', message, 'warning');
    }
    if (file.size > warehouseEntryMaxDocumentSize) {
        $(config.input).val('');
        renderWarehouseEntryExpenseFileSelection(type, previousFile);
        return Swal.fire('Archivo muy pesado', `El archivo de la ${config.label} no debe superar los 10 MB.`, 'warning');
    }
    warehouseEntryExpenseEditorRemovedDocuments[type] = false;
    renderWarehouseEntryExpenseFileSelection(type, file);
}

function renderWarehouseEntryExpenseFileSelection(type, file) {
    const config = warehouseEntryExpenseDocumentConfig(type);
    const picker = $(config.picker);
    picker.toggleClass('has-file', Boolean(file));
    picker.find('.warehouse-entry-expense-file-empty').toggleClass('d-none', Boolean(file));
    picker.find('.warehouse-entry-expense-file-selected').toggleClass('d-none', !file);
    const name = file?.name || file?.original_name || '';
    const size = file?.size ?? file?.file_size;
    const isPending = typeof File !== 'undefined' && file instanceof File;
    $(config.name).text(name).attr('title', name);
    $(config.size).text(file ? `${formatWarehouseEntryFileSize(size)} · ${isPending ? 'Listo para adjuntar' : 'Documento guardado'}` : '');
    $(config.icon).attr('class', `fas ${getWarehouseEntryFileExtension(name) === 'pdf' ? 'fa-file-pdf' : 'fa-file-image'}`);
    $(config.view).attr('href', file?.view_url || '#').toggleClass('d-none', !file?.view_url);
}

function clearWarehouseEntryExpenseFile(type) {
    const config = warehouseEntryExpenseDocumentConfig(type);
    $(config.input).val('');
    const editIndex = $('#warehouse_entry_expense_edit_index').val();
    warehouseEntryExpenseEditorRemovedDocuments[type] = true;
    renderWarehouseEntryExpenseFileSelection(type, null);
}

function addWarehouseEntryExpense() {
    const type = $('#warehouse_entry_expense_type').val();
    const mapping = warehouseEntryExpenseMapping(type);
    if (!mapping) return Swal.fire('Tipo requerido', 'Seleccione el tipo de costo.', 'warning');
    const amount = parseWarehouseEntryNumber($('#warehouse_entry_expense_amount').val());
    const affectsIgvSelection = $('#warehouse_entry_expense_affects_igv').val();
    const affects = true;
    const method = $('#warehouse_entry_expense_distribution_method').val();
    const invoiceInputFile = $('#warehouse_entry_expense_invoice_file')[0]?.files?.[0] || null;
    const paymentProofInputFile = $('#warehouse_entry_expense_payment_proof_file')[0]?.files?.[0] || null;
    if (type === 'agency_freight' && !$('#warehouse_entry_expense_shipping_agency_id').val()) return Swal.fire('Agencia requerida', 'Seleccione la agencia de envío.', 'warning');
    if (type !== 'agency_freight' && !$('#warehouse_entry_expense_provider_name').val().trim()) return Swal.fire('Responsable requerido', 'Ingrese el responsable o persona que cobró.', 'warning');
    if (amount <= 0) return Swal.fire('Importe requerido', 'Ingrese un importe válido.', 'warning');
    if (affectsIgvSelection === '') return Swal.fire('Afecto IGV requerido', 'Seleccione si el costo está afecto a IGV.', 'warning');
    if (!$('#warehouse_entry_expense_document_date').val()) return Swal.fire('Fecha requerida', 'Seleccione una fecha válida.', 'warning');
    for (const [documentFile, documentLabel] of [[invoiceInputFile, 'factura'], [paymentProofInputFile, 'constancia de pago']]) {
        if (documentFile && (!['pdf', 'jpg', 'jpeg', 'png', 'webp'].includes(getWarehouseEntryFileExtension(documentFile.name)) || documentFile.size > warehouseEntryMaxDocumentSize)) {
            return Swal.fire('Archivo no permitido', `El archivo de la ${documentLabel} debe ser PDF, JPG, JPEG, PNG o WEBP y no superar los 10 MB.`, 'warning');
        }
    }
    if (affects && !method) return Swal.fire('Atención', 'Seleccione el método de distribución.', 'warning');
    if (method === 'weight') return Swal.fire('Peso no disponible', 'Los artículos de este ingreso no tienen peso registrado. Seleccione otro método.', 'warning');
    if (affects && !$('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').length) return Swal.fire('Atención', 'Debe agregar al menos un artículo antes de distribuir el gasto.', 'warning');
    const description = $('#warehouse_entry_expense_description').val().trim();
    const editIndex = $('#warehouse_entry_expense_edit_index').val();
    const previous = editIndex === '' ? {} : warehouseEntryExpenses[Number(editIndex)];
    const invoiceFile = warehouseEntryExpenseEditorRemovedDocuments.invoice ? null : (invoiceInputFile || previous?.invoice_file || null);
    const paymentProofFile = warehouseEntryExpenseEditorRemovedDocuments.payment_proof ? null : (paymentProofInputFile || previous?.payment_proof_file || null);
    const documentType = $('#warehouse_entry_expense_document_type').val().trim();
    const affectsIgv = affectsIgvSelection === '1';
    if (invoiceFile && !['FACTURA', 'BOLETA'].includes(documentType)) return Swal.fire('Tipo de documento requerido', 'Seleccione Factura o Boleta como tipo de documento para adjuntar el comprobante tributario.', 'warning');
    if (affectsIgv && !['FACTURA', 'BOLETA'].includes(documentType)) return Swal.fire('IGV no permitido', 'Solo una factura o boleta puede registrarse como afecto a IGV.', 'warning');
    const taxBreakdown = calculateWarehouseEntryExpenseTax(amount, affectsIgv);
    const hasStoredInvoice = !warehouseEntryExpenseEditorRemovedDocuments.invoice
        && Boolean(warehouseEntryExpenseStoredDocument(previous, 'invoice'));
    if (documentType !== 'SIN_COMPROBANTE' && (!$('#warehouse_entry_expense_document_series').val().trim() || !$('#warehouse_entry_expense_document_number').val().trim())) return Swal.fire('Comprobante incompleto', 'Ingrese la serie y el número del comprobante.', 'warning');
    if ((documentType === 'SIN_COMPROBANTE' || (!invoiceFile && !hasStoredInvoice)) && !description) return Swal.fire('Observación requerida', 'Ingrese una observación cuando no adjunta factura o comprobante tributario.', 'warning');
    const distributions = method === 'manual' ? $('.warehouse-entry-expense-manual-amount').map(function () { return { item_index: Number($(this).data('item-index')), distributed_amount: parseWarehouseEntryNumber($(this).val()) }; }).get() : [];
    if (method === 'manual' && Math.abs(distributions.reduce((sum, item) => sum + item.distributed_amount, 0) - amount) > 0.009) return Swal.fire('Distribución inválida', 'La distribución del gasto debe coincidir con el importe total.', 'warning');
    const documentKey = [$('#warehouse_entry_expense_document_type').val(), $('#warehouse_entry_expense_document_series').val(), $('#warehouse_entry_expense_document_number').val()].map(value => value.trim().toUpperCase()).join('|');
    const responsibleKey = String($('#warehouse_entry_expense_provider_id').val() || $('#warehouse_entry_expense_provider_ruc').val().trim() || $('#warehouse_entry_expense_provider_name').val().trim()).toUpperCase();
    const duplicate = documentKey !== '||' && warehouseEntryExpenses.some((expense, index) => index !== Number(editIndex) && [expense.document_type, expense.document_series, expense.document_number].map(value => String(value || '').trim().toUpperCase()).join('|') === documentKey && String(expense.provider_id || expense.provider_ruc || expense.provider_name || '').toUpperCase() === responsibleKey);
    if (duplicate) return Swal.fire('Comprobante duplicado', 'Ya agregó este comprobante para el mismo responsable.', 'warning');
    const expense = {
        id: previous?.id || '', ...mapping, expense_type: type, distributed_amount: affects ? amount : 0,
        shipping_agency_id: type === 'agency_freight' ? ($('#warehouse_entry_expense_shipping_agency_id').val() || '') : '',
        provider_id: '',
        provider_name: type === 'agency_freight' ? $('#warehouse_entry_expense_shipping_agency_id option:selected').text().trim() : $('#warehouse_entry_expense_provider_name').val().trim(),
        provider_ruc: $('#warehouse_entry_expense_provider_ruc').val().trim(),
        document_type: $('#warehouse_entry_expense_document_type').val().trim(), document_series: $('#warehouse_entry_expense_document_series').val().trim(), document_number: $('#warehouse_entry_expense_document_number').val().trim(), document_date: $('#warehouse_entry_expense_document_date').val(),
        currency_id: $('#warehouse_entry_currency_id').val() || '', amount, ...taxBreakdown, affects_inventory_cost: affects, distribution_method: affects ? method : '', description, distributions,
        invoice_file: invoiceFile,
        payment_proof_file: paymentProofFile,
        remove_invoice_document: invoiceFile ? false : warehouseEntryExpenseEditorRemovedDocuments.invoice,
        remove_payment_proof_document: paymentProofFile ? false : warehouseEntryExpenseEditorRemovedDocuments.payment_proof,
        documents: previous?.documents || []
    };
    if (editIndex === '') warehouseEntryExpenses.push(expense); else warehouseEntryExpenses[Number(editIndex)] = expense;
    resetWarehouseEntryExpenseEditor(); renderWarehouseEntryExpenses(); updateWarehouseEntryReview();
}

function editWarehouseEntryExpense(index) {
    const expense = warehouseEntryExpenses[index]; if (!expense) return;
    warehouseEntryExpenseEditorRemovedDocuments = {
        invoice: Boolean(expense.remove_invoice_document),
        payment_proof: Boolean(expense.remove_payment_proof_document)
    };
    const category = expense.expense_category || (['flete', 'transporte', 'movilidad'].includes(expense.expense_type) ? 'freight_transport' : 'other_expense');
    $('#warehouse_entry_expense_edit_index').val(index); $('#warehouse_entry_expense_category').val(category); renderWarehouseEntryExpenseTypes(expense.expense_type); $('#warehouse_entry_expense_cost_origin').val(expense.cost_origin || 'third_party'); $('#warehouse_entry_expense_provider_id').val(expense.provider_id || ''); $('#warehouse_entry_expense_provider_name').val(expense.provider_name || ''); $('#warehouse_entry_expense_provider_ruc').val(expense.provider_ruc || ''); $('#warehouse_entry_expense_amount').val(expense.total_amount ?? expense.amount); $('#warehouse_entry_expense_document_type').val(expense.document_type || ''); $('#warehouse_entry_expense_document_series').val(expense.document_series || ''); $('#warehouse_entry_expense_document_number').val(expense.document_number || ''); $('#warehouse_entry_expense_document_date').val(formatWarehouseEntryDate(expense.document_date)); $('#warehouse_entry_expense_description').val(expense.description || ''); $('#warehouse_entry_expense_affects_cost').val(expense.affects_inventory_cost ? '1' : '0'); $('#warehouse_entry_expense_affects_igv').val(expense.affects_igv ? '1' : '0'); applyWarehouseEntryExpenseOriginRules();
    $('#warehouse_entry_expense_shipping_agency_id').val(expense.shipping_agency_id || '');
    $('#warehouse_entry_expense_document_type').val(expense.document_type || 'SIN_COMPROBANTE');
    toggleWarehouseEntryExpenseDocumentFields();
    toggleWarehouseEntryExpenseDistribution(); $('#warehouse_entry_expense_distribution_method').val(expense.distribution_method || ''); renderWarehouseEntryExpenseManualDistribution();
    (expense.distributions || []).forEach(item => $(`.warehouse-entry-expense-manual-amount[data-item-index="${item.item_index}"]`).val(item.distributed_amount));
    renderWarehouseEntryExpenseFileSelection('invoice', expense.invoice_file || warehouseEntryExpenseStoredDocument(expense, 'invoice'));
    renderWarehouseEntryExpenseFileSelection('payment_proof', expense.payment_proof_file || warehouseEntryExpenseStoredDocument(expense, 'payment_proof'));
    $('#btnAddWarehouseEntryExpense').html('<i class="fas fa-save mr-1"></i>Actualizar costo');
}

function removeWarehouseEntryExpense(index) {
    const expense = warehouseEntryExpenses[index];
    if (expense?.id && !Boolean(Number($('#warehouseEntryOriginalExpensesCard').data('can-destroy')))) return Swal.fire('Sin permiso', 'No tiene permiso para anular gastos registrados.', 'error');
    warehouseEntryExpenses.splice(index, 1); renderWarehouseEntryExpenses(); updateWarehouseEntryReview();
}

function renderWarehouseEntryExpenseDocumentLinks(expense) {
    const line = (type, label, missingLabel) => {
        const config = warehouseEntryExpenseDocumentConfig(type);
        const pendingFile = expense?.[config.fileField];
        const storedDocument = warehouseEntryExpenseStoredDocument(expense, type);
        if (pendingFile) {
            return `<span title="${escapeWarehouseEntryHtml(pendingFile.name)}"><strong>${label}:</strong> <span class="badge badge-info">Listo</span></span>`;
        }
        if (storedDocument?.view_url) {
            return `<span><strong>${label}:</strong> <a href="${storedDocument.view_url}" target="_blank" class="btn btn-outline-info btn-xs"><i class="fas fa-eye mr-1"></i>Ver</a></span>`;
        }
        return `<span class="text-muted"><strong>${label}:</strong> ${missingLabel}</span>`;
    };

    return `<div class="warehouse-entry-expense-document-links">${line('invoice', 'Factura', 'Sin factura')}${line('payment_proof', 'Pago', 'Sin constancia')}</div>`;
}

function renderWarehouseEntryExpenses() {
    $('#warehouseEntryExpensesBody').html(warehouseEntryExpenses.length ? warehouseEntryExpenses.map((expense, index) => {
        const type = renderWarehouseEntrySimpleExpenseLabel(expense.expense_type);
        const document = expense.document_type === 'SIN_COMPROBANTE' || !expense.document_type ? 'Sin comprobante' : [expense.document_type, [expense.document_series, expense.document_number].filter(Boolean).join('-')].filter(Boolean).join(' ');
        const igvTitle = `Base: ${formatWarehouseEntryMoney(expense.taxable_amount ?? expense.amount)} | IGV: ${formatWarehouseEntryMoney(expense.igv_amount ?? 0)}`;
        const igvBadge = expense.affects_igv ? '<span class="badge badge-success">Afecto IGV</span>' : '<span class="badge badge-light">Sin IGV</span>';
        return `<tr><td><span class="badge badge-${expense.expense_category === 'freight_transport' ? 'info' : 'secondary'}">${type}</span></td><td>${escapeWarehouseEntryHtml(expense.provider_name || 'Sin responsable indicado')}</td><td>${escapeWarehouseEntryHtml(document)}</td><td class="text-right font-weight-bold">${formatWarehouseEntryMoney(expense.total_amount ?? expense.amount)}</td><td title="${escapeWarehouseEntryHtml(igvTitle)}">${igvBadge}</td><td>${escapeWarehouseEntryHtml(expense.description || '-')}</td><td>${renderWarehouseEntryExpenseDocumentLinks(expense)}</td><td class="text-nowrap"><button type="button" class="btn btn-outline-info btn-xs btnEditWarehouseEntryExpense" data-index="${index}"><i class="fas fa-edit"></i></button> <button type="button" class="btn btn-outline-danger btn-xs btnRemoveWarehouseEntryExpense" data-index="${index}"><i class="fas fa-ban"></i></button></td></tr>`;
    }).join('') : '<tr><td colspan="8" class="text-center text-muted py-3">No hay costos vinculados.</td></tr>');
    const freight = warehouseEntryExpenses.filter(expense => expense.expense_category === 'freight_transport').reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const other = warehouseEntryExpenses.filter(expense => expense.expense_category !== 'freight_transport').reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const inventory = warehouseEntryExpenses.filter(expense => expense.affects_inventory_cost).reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const informative = warehouseEntryExpenses.filter(expense => !expense.affects_inventory_cost).reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    $('#warehouseEntryFreightTotal').text(formatWarehouseEntryMoney(freight)); $('#warehouseEntryOtherExpenseTotal').text(formatWarehouseEntryMoney(other)); $('#warehouseEntryExpenseInventoryTotal').text(formatWarehouseEntryMoney(inventory)); $('#warehouseEntryExpenseInformativeTotal').text(formatWarehouseEntryMoney(informative));
    $('#warehouseEntryExpenseLinkedTotal').text(formatWarehouseEntryMoney(freight + other));
}

function renderWarehouseEntrySimpleExpenseLabel(type) {
    if (['agency_freight', 'transport_agency', 'courier', 'shipping'].includes(type)) return 'Flete de agencia';
    if (['pickup_transfer', 'agency_pickup_to_warehouse', 'agency_direct_to_warehouse', 'supplier_warehouse_pickup', 'transfer_to_agency', 'truck', 'mobility', 'delivery', 'transfer', 'flete', 'transporte', 'movilidad'].includes(type)) return 'Recojo / traslado';
    return 'Otros gastos';
}

function loadWarehouseEntryForEdit(id) {
    $.get(`${window.routes.warehouseEntryShow}/${id}`)
        .done(function (response) {
            const entry = response.data;

            resetWarehouseEntryForm();
            $('#warehouseEntryModalLabel').text('Editar Ingreso de Almacen');
            $('#warehouse_entry_id').val(entry.id);
            $('#warehouse_entry_number').val(entry.entry_number);
            fillWarehouseEntryForm(entry);
            $('#warehouseEntryModal').modal('show');
        })
        .fail(function () {
            Swal.fire('Error', 'No se pudo cargar el ingreso.', 'error');
        });
}

function fillWarehouseEntryForm(entry) {
    warehouseEntrySourceOrderTotal = entry.supplier_purchase_order?.grand_total !== undefined
        ? parseWarehouseEntryNumber(entry.supplier_purchase_order.grand_total)
        : null;
    $('#warehouse_entry_supplier_purchase_order_id').val(entry.supplier_purchase_order_id || '').trigger('change.select2');
    $('#warehouse_entry_warehouse_id').val(entry.warehouse_id || '').trigger('change.select2').trigger('change');
    $('#warehouse_entry_company_id').val(entry.company_id || '').trigger('change.select2');
    setWarehouseEntrySupplier(entry.supplier_id || '', entry.supplier?.ruc || '');
    setWarehouseEntrySupplierLocked(Boolean(entry.supplier_purchase_order_id));
    $('#warehouse_entry_currency_id').val(entry.currency_id || '').trigger('change.select2').trigger('change');
    $('#warehouse_entry_purchase_order_number').val(entry.purchase_order_number || '');
    $('#warehouse_entry_document_type').val(normalizeWarehouseEntryDocumentType(entry.document_type));
    $('#warehouse_entry_document_series').val(entry.document_series || '');
    $('#warehouse_entry_document_number').val(entry.document_number || '');
    $('#warehouse_entry_document_date').val(formatWarehouseEntryDate(entry.document_date));
    $('#warehouse_entry_payment_method').val(entry.payment_method || '');
    $('#warehouse_entry_payment_condition').val(entry.payment_condition || '');
    $('#warehouse_entry_generate_account_payable').val(entry.generate_account_payable ? '1' : '0');
    $('#warehouse_entry_payable_amount').val(formatWarehouseEntryMoney(entry.payable_amount || 0));
    $('#warehouse_entry_expected_payment_date').val(formatWarehouseEntryDate(entry.expected_payment_date));
    $('#warehouse_entry_seller_name').val(entry.seller_name || '');
    $('#warehouse_entry_affect_igv').val(entry.affect_igv ? '1' : '0');
    $('#warehouse_entry_guide_series').val(entry.guide_series || '');
    $('#warehouse_entry_guide_number').val(entry.guide_number || '');
    $('#warehouse_entry_guide_ruc').val(entry.guide_ruc || entry.supplier?.ruc || '');
    $('#warehouse_entry_observations').val(entry.observations || '');
    warehouseEntryExpenses = (entry.expenses || []).map(expense => ({
        ...expense,
        affects_igv: Boolean(expense.affects_igv),
        affects_inventory_cost: Boolean(expense.affects_inventory_cost),
        distributions: (expense.distributions || []).map(distribution => ({
            item_index: (entry.items || []).findIndex(item => Number(item.id) === Number(distribution.warehouse_entry_item_id)),
            distributed_amount: distribution.distributed_amount
        })).filter(distribution => distribution.item_index >= 0)
    }));
    renderWarehouseEntryExpenses();

    clearWarehouseEntryItemRows();
    (entry.items || []).forEach(addWarehouseEntryItemRow);
    showEmptyWarehouseEntryItemsRow();
    warehouseEntryPendingDocuments = [];
    warehouseEntryExistingDocuments = entry.documents || [];
    warehouseEntryPendingLotDocuments = [];
    warehouseEntryExistingLotDocuments = (entry.items || []).flatMap((item, itemIndex) =>
        (item.lots || []).flatMap(lot => (lot.documents || []).map(document => ({
            ...document,
            itemIndex,
            lotKey: `id:${lot.id}`,
            lotId: lot.id,
            lotCode: lot.lot_code,
            lotQuantity: lot.quantity,
            articleName: item.billing_name_snapshot || item.article?.billing_name || '-'
        })))
    );
    resetWarehouseEntryDocumentInputs();
    renderWarehouseEntryDocuments();
    refreshWarehouseEntryLotDocumentItems();
    renderWarehouseEntryLotDocuments();
    calculateWarehouseEntryTotals();
    syncWarehouseEntryPayableAmount();
}

function loadWarehouseEntryDetail(id) {
    $.get(`${window.routes.warehouseEntryShow}/${id}`)
        .done(function (response) {
            renderWarehouseEntryDetail(response.data, response.warehouse_name);
            $('#warehouseEntryViewModal .warehouse-entry-view-tabs .nav-link').first().tab('show');
            $('#warehouseEntryViewModal').modal('show');
        })
        .fail(function () {
            Swal.fire('Error', 'No se pudo cargar el detalle.', 'error');
        });
}

function renderWarehouseEntryDetail(entry, warehouseName) {
    const status = entry.status === 'cancelled'
        ? ['ANULADO', 'badge-danger']
        : ['REGISTRADO', 'badge-primary'];
    const currencySymbol = entry.currency?.symbol || entry.currency?.code || '';

    $('#vwe_entry_number').text(entry.entry_number || '-');
    $('#vwe_status').text(status[0]).attr('class', `badge ${status[1]} rounded-pill px-3 py-2`);
    $('#vwe_supplier').text(entry.supplier?.short_name || entry.supplier?.business_name || '-');
    $('#vwe_company').text(entry.company?.trade_name || entry.company?.business_name || '-');
    $('#vwe_warehouse').text(warehouseName || 'SIN ALMACEN');
    $('#vwe_currency_symbol').text(currencySymbol);
    $('#vwe_grand_total').text(formatWarehouseEntryMoney(entry.grand_total || 0));
    $('#vwe_purchase_order').text(entry.supplier_purchase_order?.code || entry.purchase_order_number || '-');
    const supplierOrder = entry.supplier_purchase_order;
    const customerOrders = supplierOrder?.customer_purchase_orders?.length
        ? supplierOrder.customer_purchase_orders
        : (supplierOrder?.customer_purchase_order ? [supplierOrder.customer_purchase_order] : []);
    $('#vwe_customer_orders').html(customerOrders.length
        ? customerOrders.map(function (customerOrder) {
            const customerName = customerOrder.customer?.business_name
                || customerOrder.customer?.full_name
                || [customerOrder.customer?.first_name, customerOrder.customer?.last_name].filter(Boolean).join(' ')
                || 'Sin cliente';
            const companyName = customerOrder.company?.trade_name || customerOrder.company?.business_name || '-';
            const currency = customerOrder.currency?.symbol || customerOrder.currency?.code || '';

            return `<div class="warehouse-entry-customer-order-card">
                <span>Orden de Compra del Cliente</span>
                <strong>${escapeWarehouseEntryHtml(customerOrder.purchase_order_number || customerOrder.code || '-')}</strong>
                <small><i class="fas fa-user mr-1"></i>${escapeWarehouseEntryHtml(customerName)}</small>
                <small><i class="fas fa-building mr-1"></i>${escapeWarehouseEntryHtml(companyName)}</small>
                <small><i class="fas fa-coins mr-1"></i>${escapeWarehouseEntryHtml(currency)} ${formatWarehouseEntryMoney(customerOrder.grand_total)}</small>
            </div>`;
        }).join('')
        : '<div class="warehouse-entry-customer-order-card"><span>Orden de Compra del Cliente</span><strong>Sin OC cliente relacionada</strong></div>');
    $('#vwe_detail_company').text(entry.company?.trade_name || entry.company?.business_name || '-');
    $('#vwe_detail_supplier').text(entry.supplier?.short_name || entry.supplier?.business_name || '-');
    $('#vwe_detail_warehouse').text(warehouseName || 'SIN ALMACEN');
    $('#vwe_currency').text(entry.currency?.code || '-');
    $('#vwe_document_type').text(normalizeWarehouseEntryDocumentType(entry.document_type));
    $('#vwe_document_number').text([entry.document_series, entry.document_number].filter(Boolean).join(' ') || '-');
    $('#vwe_document_date').text(formatWarehouseEntryDisplayDate(entry.document_date));
    $('#vwe_guide').text([entry.guide_series, entry.guide_number, entry.guide_ruc].filter(Boolean).join(' / ') || '-');
    $('#vwe_payment_method').text(entry.payment_method || '-');
    $('#vwe_payment_condition').text(entry.payment_condition || '-');
    $('#vwe_payable').text(entry.generate_account_payable
        ? `Si - ${formatWarehouseEntryDisplayDate(entry.expected_payment_date)}`
        : 'No');
    $('#vwe_payable_amount').text(formatWarehouseEntryMoney(entry.payable_amount || 0));
    $('#vwe_observations').text(entry.observations || '-');
    $('#vwe_subtotal').text(formatWarehouseEntryMoney(entry.subtotal || 0));
    $('#vwe_igv').text(formatWarehouseEntryMoney(entry.igv || 0));
    $('#vwe_total').text(formatWarehouseEntryMoney(entry.grand_total || 0));
    const expenseMethods = { quantity:'Por cantidad', amount:'Por valor', weight:'Por peso', manual:'Manual' };
    const expenses = entry.expenses || [];
    $('#vwe_expenses').html(expenses.length ? expenses.map(expense => {
        const igvTitle = `Base: ${formatWarehouseEntryMoney(expense.taxable_amount ?? expense.amount)} | IGV: ${formatWarehouseEntryMoney(expense.igv_amount ?? 0)}`;
        return `<tr><td>${expense.expense_category === 'freight_transport' ? 'Flete / Transporte' : 'Otro gasto'}</td><td>${warehouseEntryExpenseTypeLabels[expense.expense_type] || expense.expense_type}</td><td>${warehouseEntryExpenseOriginLabels[expense.cost_origin] || 'Tercero / agencia externa'}</td><td>${escapeWarehouseEntryHtml(expense.provider_name || expense.provider?.short_name || expense.provider_ruc || 'Sin proveedor')}</td><td>${escapeWarehouseEntryHtml([expense.document_type, expense.document_series, expense.document_number].filter(Boolean).join(' ') || 'Sin comprobante')}</td><td class="text-right font-weight-bold">${formatWarehouseEntryMoney(expense.total_amount ?? expense.amount)}</td><td title="${escapeWarehouseEntryHtml(igvTitle)}">${expense.affects_igv ? '<span class="badge badge-success">Afecto IGV</span>' : '<span class="badge badge-light">Sin IGV</span>'}</td><td>${expense.affects_inventory_cost ? '<span class="badge badge-success">Sí</span>' : '<span class="badge badge-light">No</span>'}</td><td>${expense.affects_inventory_cost ? expenseMethods[expense.distribution_method] || '-' : 'Informativo'}</td><td>${renderWarehouseEntryExpenseDocumentLinks(expense)}</td></tr>`;
    }).join('') : '<tr><td colspan="10" class="text-center text-muted py-3">Sin costos vinculados.</td></tr>');
    const freightExpenses = expenses.filter(expense => expense.expense_category === 'freight_transport').reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const otherExpenses = expenses.filter(expense => expense.expense_category !== 'freight_transport').reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const informativeExpenses = expenses.filter(expense => !expense.affects_inventory_cost).reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    const inventoryExpenses = expenses.filter(expense => expense.affects_inventory_cost).reduce((sum, expense) => sum + parseWarehouseEntryNumber(expense.amount), 0);
    $('#vwe_expenses_informative').text(formatWarehouseEntryMoney(informativeExpenses));
    $('#vwe_expenses_inventory').text(formatWarehouseEntryMoney(inventoryExpenses));
    $('#vwe_expenses_freight').text(formatWarehouseEntryMoney(freightExpenses));
    $('#vwe_expenses_other').text(formatWarehouseEntryMoney(otherExpenses));
    $('#vwe_real_total').text(formatWarehouseEntryMoney(parseWarehouseEntryNumber(entry.grand_total) + inventoryExpenses));
    $('#vwe_created_by').text(formatWarehouseEntryUser(entry.creator));
    $('#vwe_created_at').text(formatWarehouseEntryDateTime(entry.created_at));
    $('#vwe_updated_by').text(formatWarehouseEntryUser(entry.updater));
    $('#vwe_updated_at').text(formatWarehouseEntryDateTime(entry.updated_at));
    $('#vwe_audit_status').text(status[0]);

    let detailRowNumber = 0;
    const rows = (entry.items || []).flatMap(function (item) {
        const lots = Array.isArray(item.lots) && item.lots.length
            ? item.lots
            : (item.lot_number ? [{ lot_code: item.lot_number, quantity: item.quantity }] : [null]);

        return lots.map(function (lot) {
            detailRowNumber += 1;
            const quantity = lot ? parseWarehouseEntryNumber(lot.quantity) : parseWarehouseEntryNumber(item.quantity);
            const rowTotal = lot
                ? quantity * parseWarehouseEntryNumber(item.unit_price)
                : parseWarehouseEntryNumber(item.line_total);

            return `
                <tr class="${lot ? 'warehouse-entry-detail-lot-row' : ''}">
                    <td>${detailRowNumber}</td>
                    <td>${escapeWarehouseEntryHtml(item.billing_name_snapshot || item.article?.billing_name || '-')}</td>
                    <td>${escapeWarehouseEntryHtml(item.unit?.description || '-')}</td>
                    <td>${escapeWarehouseEntryHtml(item.presentation?.description || '-')}</td>
                    <td>${escapeWarehouseEntryHtml(item.brand?.description || '-')}</td>
                    <td>${escapeWarehouseEntryHtml(item.origin || '-')}</td>
                    <td>${lot ? `<span class="warehouse-entry-lot-badge"><i class="fas fa-tag mr-1"></i>${escapeWarehouseEntryHtml(lot.lot_code || 'Sin código')}</span>` : '<span class="text-muted">Sin lote</span>'}</td>
                    <td class="text-right">${formatWarehouseEntryMoney(quantity)}</td>
                    <td class="text-right">${formatWarehouseEntryMoney(item.unit_price || 0)}</td>
                    <td class="text-right">${formatWarehouseEntryMoney(item.additional_cost || 0)}</td>
                    <td class="text-right">${formatWarehouseEntryMoney(item.real_unit_cost || item.unit_price || 0)}</td>
                    <td class="text-right font-weight-bold">${formatWarehouseEntryMoney(quantity * parseWarehouseEntryNumber(item.real_unit_cost || item.unit_price))}</td>
                </tr>`;
        });
    }).join('');

    $('#vwe_items').html(rows || '<tr><td colspan="12" class="text-center text-muted py-3">Sin articulos ingresados.</td></tr>');
    renderWarehouseEntryDetailDocuments(entry.documents || [], entry.id);
    renderWarehouseEntryDetailLotDocuments(entry.items || [], entry.id);
}

function refreshWarehouseEntryLotDocumentItems() {
    const current = $('#warehouse_entry_lot_document_item').val();
    const options = ['<option value="">Seleccione artículo</option>'];
    $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').each(function (index) {
        const name = $(this).find('.item-billing-name').val() || $(this).find('.item-article-picker option:selected').text().trim() || `Artículo ${index + 1}`;
        options.push(`<option value="${index}">${escapeWarehouseEntryHtml(name)}</option>`);
    });
    $('#warehouse_entry_lot_document_item').html(options.join('')).val(current || '');
    refreshWarehouseEntryLotDocumentLots();
    renderWarehouseEntryLotDocuments();
}

function refreshWarehouseEntryLotDocumentLots() {
    const itemIndex = Number($('#warehouse_entry_lot_document_item').val());
    const row = Number.isInteger(itemIndex) ? $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').eq(itemIndex) : $();
    const options = ['<option value="">Seleccione lote</option>'];
    (row.data('lots') || []).forEach(lot => options.push(`<option value="${escapeWarehouseEntryHtml(lot.client_key)}">${escapeWarehouseEntryHtml(lot.lot_code)} · ${formatWarehouseEntryMoney(lot.quantity)}</option>`));
    $('#warehouse_entry_lot_document_lot').html(options.join(''));
    renderWarehouseEntrySelectedLotInfo();
}

function selectedWarehouseEntryLotContext() {
    const itemIndex = Number($('#warehouse_entry_lot_document_item').val());
    const lotKey = $('#warehouse_entry_lot_document_lot').val();
    if (!Number.isInteger(itemIndex) || !lotKey) return null;
    const row = $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').eq(itemIndex);
    const lot = (row.data('lots') || []).find(value => value.client_key === lotKey);
    if (!lot) return null;
    return { itemIndex, lotKey, lot, articleName: row.find('.item-billing-name').val() || '-' };
}

function renderWarehouseEntrySelectedLotInfo() {
    const context = selectedWarehouseEntryLotContext();
    const box = $('#warehouseEntryLotSelectedInfo');
    if (!context) return box.addClass('d-none').empty();
    const count = [...warehouseEntryExistingLotDocuments, ...warehouseEntryPendingLotDocuments]
        .filter(document => document.itemIndex === context.itemIndex && document.lotKey === context.lotKey).length;
    box.removeClass('d-none').html(`<strong>${escapeWarehouseEntryHtml(context.articleName)}</strong><span>Lote ${escapeWarehouseEntryHtml(context.lot.lot_code)} · Cantidad ${formatWarehouseEntryMoney(context.lot.quantity)}${context.lot.expiration_date ? ` · Vence ${formatWarehouseEntryDisplayDate(context.lot.expiration_date)}` : ''} · ${count} documento(s)</span>`);
}

function addWarehouseEntryPendingLotDocument() {
    const context = selectedWarehouseEntryLotContext();
    const file = $('#warehouse_entry_lot_document_file')[0]?.files?.[0];
    const type = $('#warehouse_entry_lot_document_type').val();
    if (!$('#warehouse_entry_lot_document_item').val()) return Swal.fire('Atención', 'Seleccione el artículo.', 'warning');
    if (!$('#warehouse_entry_lot_document_lot').val() || !context) return Swal.fire('Atención', 'Seleccione el lote.', 'warning');
    if (!type) return Swal.fire('Atención', 'Seleccione el tipo de documento.', 'warning');
    if (!file) return Swal.fire('Atención', 'Seleccione un archivo para adjuntar.', 'warning');
    if (!warehouseEntryAllowedDocumentExtensions.includes(getWarehouseEntryFileExtension(file.name))) return Swal.fire('Archivo no permitido', 'El archivo adjunto debe ser PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX y no debe superar los 10 MB.', 'warning');
    if (file.size > warehouseEntryMaxDocumentSize) return Swal.fire('Archivo no permitido', 'El archivo adjunto debe ser PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX y no debe superar los 10 MB.', 'warning');
    warehouseEntryPendingLotDocuments.push({ ...context, type, description: $('#warehouse_entry_lot_document_description').val(), file, original_name: file.name });
    $('#warehouse_entry_lot_document_description, #warehouse_entry_lot_document_file').val('');
    $('#warehouse_entry_lot_document_file').siblings('.custom-file-label').text('Seleccionar archivo').removeAttr('title');
    renderWarehouseEntryLotDocuments();
    renderWarehouseEntryLotRows($('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').eq(context.itemIndex));
}

function renderWarehouseEntryLotDocuments() {
    const documents = [
        ...warehouseEntryExistingLotDocuments.map(document => ({ ...document, existing: true })),
        ...warehouseEntryPendingLotDocuments.map((document, pendingIndex) => ({ ...document, pendingIndex }))
    ];
    if (!documents.length) {
        $('#warehouseEntryLotDocumentsList').html('<div class="warehouse-entry-lot-documents-empty"><i class="fas fa-folder-open"></i>No hay documentos específicos por lote.</div>');
        return renderWarehouseEntrySelectedLotInfo();
    }
    const groups = {};
    documents.forEach(document => {
        const key = `${document.itemIndex}:${document.lotKey}`;
        (groups[key] ||= { articleName: document.articleName, lotCode: document.lot?.lot_code || document.lotCode, quantity: document.lot?.quantity || document.lotQuantity, documents: [] }).documents.push(document);
    });
    $('#warehouseEntryLotDocumentsList').html(Object.values(groups).map(group => `<div class="warehouse-entry-lot-document-group"><div class="warehouse-entry-lot-document-group-title"><strong>${escapeWarehouseEntryHtml(group.articleName)}</strong><span>Lote ${escapeWarehouseEntryHtml(group.lotCode)} · ${formatWarehouseEntryMoney(group.quantity)}</span></div>${group.documents.map(document => `<div class="warehouse-entry-lot-document-row"><span>${escapeWarehouseEntryHtml(warehouseEntryDocumentTypes[document.document_type || document.type]?.label || 'Documento')} · ${escapeWarehouseEntryHtml(document.original_name)}</span><span>${document.existing ? `<a target="_blank" class="btn btn-outline-info btn-sm" href="/storage/${encodeURI(document.file_path)}"><i class="fas fa-eye"></i></a> <button type="button" class="btn btn-outline-danger btn-sm btnDeleteExistingLotDocument" data-id="${document.id}"><i class="fas fa-ban"></i></button>` : `<button type="button" class="btn btn-outline-danger btn-sm btnRemovePendingLotDocument" data-index="${document.pendingIndex}"><i class="fas fa-times"></i></button>`}</span></div>`).join('')}</div>`).join(''));
    renderWarehouseEntrySelectedLotInfo();
}

function deleteWarehouseEntryLotDocument(documentId) {
    const entryId = $('#warehouse_entry_id').val();
    if (!entryId) return;
    $.ajax({ url: `${window.routes.warehouseEntryShow}/${entryId}/lot-documents/${documentId}`, type: 'DELETE' })
        .done(() => { warehouseEntryExistingLotDocuments = warehouseEntryExistingLotDocuments.filter(document => Number(document.id) !== Number(documentId)); renderWarehouseEntryLotDocuments(); })
        .fail(xhr => Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo anular el documento.', 'error'));
}

function renderWarehouseEntryDetailLotDocuments(items, entryId) {
    const groups = items.flatMap(item => (item.lots || []).map(lot => ({
        item,
        lot,
        documents: Array.isArray(lot.documents) ? lot.documents : []
    })));

    if (!groups.length) {
        $('#vwe_lot_documents').html('<div class="warehouse-entry-lot-documents-empty"><i class="fas fa-folder-open"></i>No hay lotes registrados para revisar.</div>');
        return;
    }

    $('#vwe_lot_documents').html(groups.map(function (group, index) {
        const articleName = group.item.billing_name_snapshot || group.item.article?.billing_name || '-';
        const documentCount = group.documents.length;
        const documentLabel = documentCount === 1 ? 'documento' : 'documentos';
        const rows = group.documents.map(function (document) {
            const type = warehouseEntryDocumentTypes[document.document_type]?.label || 'Documento';
            const viewUrl = `/storage/${encodeURI(document.file_path || '')}`;
            const downloadUrl = `${window.routes.warehouseEntryShow}/${entryId}/lot-documents/${document.id}/download`;

            return `<tr>
                <td><span class="vwe-lot-doc-type">${escapeWarehouseEntryHtml(type)}</span></td>
                <td>${escapeWarehouseEntryHtml(document.description || 'Sin descripción')}</td>
                <td><span class="vwe-lot-doc-file" title="${escapeWarehouseEntryHtml(document.original_name || '-')}"><i class="far fa-file mr-1"></i>${escapeWarehouseEntryHtml(document.original_name || '-')}</span></td>
                <td>${escapeWarehouseEntryHtml(formatWarehouseEntryDateTime(document.created_at))}</td>
                <td class="text-center"><div class="vwe-lot-doc-actions">
                    <a target="_blank" rel="noopener" class="btn btn-outline-info btn-sm" href="${escapeWarehouseEntryHtml(viewUrl)}"><i class="fas fa-eye mr-1"></i>Ver</a>
                    <a class="btn btn-outline-success btn-sm" href="${escapeWarehouseEntryHtml(downloadUrl)}"><i class="fas fa-download mr-1"></i>Descargar</a>
                </div></td>
            </tr>`;
        }).join('');

        return `<section class="vwe-lot-doc-accordion" data-group-index="${index}">
            <div class="vwe-lot-doc-header">
                <div class="vwe-lot-doc-identity">
                    <span class="vwe-lot-doc-icon"><i class="fas fa-box-open"></i></span>
                    <div><small>Artículo</small><strong>${escapeWarehouseEntryHtml(articleName)}</strong>
                        <div class="vwe-lot-doc-meta"><span><i class="fas fa-tag"></i>Lote ${escapeWarehouseEntryHtml(group.lot.lot_code || 'Sin código')}</span><span>Cantidad ${formatWarehouseEntryMoney(group.lot.quantity)}</span><span class="vwe-lot-doc-count">${documentCount} ${documentLabel}</span></div>
                    </div>
                </div>
                <button type="button" class="vwe-lot-doc-toggle" aria-expanded="false">
                    <span class="vwe-lot-doc-toggle-label">Ver documentos</span><i class="fas fa-chevron-down vwe-lot-doc-chevron"></i>
                </button>
            </div>
            <div class="vwe-lot-doc-body" style="display:none">
                ${documentCount ? `<div class="table-responsive"><table class="table table-sm mb-0 vwe-lot-doc-table"><thead><tr><th>Tipo de documento</th><th>Descripción</th><th>Archivo</th><th>Fecha</th><th class="text-center">Acciones</th></tr></thead><tbody>${rows}</tbody></table></div>` : '<div class="vwe-lot-doc-empty"><i class="far fa-folder-open mr-1"></i>Sin documentos adjuntos</div>'}
            </div>
        </section>`;
    }).join(''));
}

function formatWarehouseEntryUser(user) {
    if (!user) return '-';
    return [user.name, user.lastname].filter(Boolean).join(' ') || user.email || '-';
}

function formatWarehouseEntryDateTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' });
}

function deleteWarehouseEntry(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Eliminar ingreso',
        text: 'El ingreso se anulara y eliminara logicamente.',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: `${window.routes.warehouseEntryDelete}/${id}`,
            type: 'DELETE'
        })
            .done(function (response) {
                tableWarehouseEntry.ajax.reload(null, false);
                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Ingreso eliminado correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500
                });
            })
            .fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar.', 'error');
            });
    });
}

function parseWarehouseEntryNumber(value) {
    let normalized = String(value ?? '').trim().replace(/[^0-9,.-]/g, '');
    if (normalized.includes(',') && normalized.includes('.')) {
        normalized = normalized.lastIndexOf('.') > normalized.lastIndexOf(',')
            ? normalized.replace(/,/g, '')
            : normalized.replace(/\./g, '').replace(',', '.');
    } else if (normalized.includes(',')) {
        const parts = normalized.split(',');
        normalized = parts.length === 2 && parts[1].length <= 2 ? `${parts[0]}.${parts[1]}` : parts.join('');
    }
    return Number.parseFloat(normalized) || 0;
}

function formatWarehouseEntryMoney(value) {
    return parseWarehouseEntryNumber(value).toFixed(2);
}

function formatWarehouseEntryUnitPrice(value) {
    return parseWarehouseEntryNumber(value).toFixed(6).replace(/0+$/, '').replace(/\.$/, '') || '0.00';
}

function formatWarehouseEntryDate(value) {
    if (!value) {
        return '';
    }

    return String(value).substring(0, 10);
}

function formatWarehouseEntryDisplayDate(value) {
    const date = formatWarehouseEntryDate(value);

    if (!date) {
        return '-';
    }

    const parts = date.split('-');
    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : date;
}

function normalizeWarehouseEntryDocumentType(value) {
    const documentType = String(value || 'FACTURA').toUpperCase();

    return documentType === 'BOLETA' ? 'BOLETA' : 'FACTURA';
}

function addWarehouseEntryPendingDocument() {
    const type = $('#warehouse_entry_document_attachment_type').val();
    const description = $('#warehouse_entry_document_attachment_description').val();
    const fileInput = $('#warehouse_entry_document_attachment_file')[0];
    const file = fileInput?.files?.[0];

    if (!type) {
        Swal.fire('Atencion', 'Seleccione el tipo de documento.', 'warning');
        return;
    }

    if (!file) {
        Swal.fire('Atencion', 'Seleccione un archivo para adjuntar.', 'warning');
        return;
    }

    const extension = getWarehouseEntryFileExtension(file.name);

    if (!warehouseEntryAllowedDocumentExtensions.includes(extension)) {
        Swal.fire('Archivo no permitido', 'El archivo adjunto debe ser PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX y no debe superar los 10 MB.', 'warning');
        return;
    }

    if (file.size > warehouseEntryMaxDocumentSize) {
        Swal.fire('Archivo no permitido', 'El archivo adjunto debe ser PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX y no debe superar los 10 MB.', 'warning');
        return;
    }

    warehouseEntryPendingDocuments.push({
        type,
        description,
        file,
        original_name: file.name,
        created_at: new Date().toISOString(),
        pending: true
    });

    resetWarehouseEntryDocumentInputs();
    renderWarehouseEntryDocuments();
}

function resetWarehouseEntryDocumentInputs() {
    $('#warehouse_entry_document_attachment_type').val('purchase_invoice');
    $('#warehouse_entry_document_attachment_description').val('');
    $('#warehouse_entry_document_attachment_file').val('');
    $('#warehouse_entry_document_attachment_file').siblings('.custom-file-label').text('Seleccionar archivo');
}

function renderWarehouseEntryDocuments() {
    const entryId = $('#warehouse_entry_id').val();
    const rows = [];

    warehouseEntryExistingDocuments.forEach(function (document, index) {
        rows.push(renderWarehouseEntryDocumentRow(document, index + 1, {
            entryId,
            existing: true
        }));
    });

    warehouseEntryPendingDocuments.forEach(function (document, index) {
        rows.push(renderWarehouseEntryDocumentRow(document, warehouseEntryExistingDocuments.length + index + 1, {
            pendingIndex: index,
            pending: true
        }));
    });

    $('#warehouseEntryDocumentCount').text(rows.length);
    $('#warehouseEntrySideDocumentCount').text(rows.length);
    updateWarehouseEntryReview();

    $('#warehouseEntryDocumentsTbody').html(rows.join('') || `
        <tr id="warehouseEntryDocumentsEmptyRow">
            <td colspan="6" class="text-center text-muted py-3">
                <i class="fas fa-folder-open d-block mb-2"></i>
                No hay documentos adjuntos para este ingreso.
            </td>
        </tr>
    `);
}

function renderWarehouseEntryDetailDocuments(documents, entryId) {
    const rows = documents.map(function (document, index) {
        return renderWarehouseEntryDocumentRow(document, index + 1, {
            entryId,
            detail: true
        });
    });

    $('#vwe_documents').html(rows.join('') || `
        <tr>
            <td colspan="6" class="text-center text-muted py-4 warehouse-entry-empty-state">
                <i class="fas fa-folder-open d-block mb-2"></i>
                No hay documentos adjuntos para este ingreso.
            </td>
        </tr>
    `);
}

function renderWarehouseEntryDocumentRow(document, rowNumber, options = {}) {
    const typeKey = resolveWarehouseEntryDocumentTypeKey(document);
    const type = warehouseEntryDocumentTypes[typeKey] || warehouseEntryDocumentTypes.other;
    const description = document.description || document.observation || '-';
    const fileName = document.original_name || document.file?.name || '-';
    const date = options.pending ? 'Pendiente' : formatWarehouseEntryDisplayDate(document.created_at);
    const filePath = document.file_path ? `/storage/${document.file_path}` : '#';
    let actions = '';

    if (options.pending) {
        actions = `
            <button type="button" class="btn btn-outline-danger btn-sm btnRemoveWarehouseEntryPendingDocument"
                data-index="${options.pendingIndex}" title="Quitar">
                <i class="fas fa-trash-alt"></i>
            </button>
        `;
    } else {
        const downloadUrl = `${window.routes.warehouseEntryShow}/${options.entryId}/documents/${document.id}/download`;
        actions = `
            <a href="${filePath}" target="_blank" class="btn btn-outline-info btn-sm" title="Ver">
                <i class="fas fa-eye"></i>
            </a>
            <a href="${downloadUrl}" class="btn btn-outline-success btn-sm" title="Descargar">
                <i class="fas fa-download"></i>
            </a>
        `;

        if (!options.detail) {
            actions += `
                <button type="button" class="btn btn-outline-danger btn-sm btnDeleteWarehouseEntryExistingDocument"
                    data-id="${document.id}" title="Eliminar">
                    <i class="fas fa-trash-alt"></i>
                </button>
            `;
        }
    }

    return `
        <tr>
            <td>${rowNumber}</td>
            <td>
                <span class="warehouse-entry-document-badge ${type.badge}">
                    <i class="fas fa-file-medical"></i>${escapeWarehouseEntryHtml(type.label)}
                </span>
            </td>
            <td>${escapeWarehouseEntryHtml(description)}</td>
            <td>
                <span class="warehouse-entry-document-file-name" title="${escapeWarehouseEntryHtml(fileName)}">
                    ${escapeWarehouseEntryHtml(fileName)}
                </span>
            </td>
            <td>${escapeWarehouseEntryHtml(date)}</td>
            <td class="text-center">
                <span class="warehouse-entry-document-actions">${actions}</span>
            </td>
        </tr>
    `;
}

function deleteWarehouseEntryDocument(documentId) {
    const entryId = $('#warehouse_entry_id').val();

    if (!entryId || !documentId) {
        return;
    }

    Swal.fire({
        icon: 'warning',
        title: 'Eliminar documento',
        text: 'Se eliminara el archivo adjunto sin borrar el ingreso.',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: `${window.routes.warehouseEntryShow}/${entryId}/documents/${documentId}`,
            type: 'DELETE'
        })
            .done(function (response) {
                warehouseEntryExistingDocuments = warehouseEntryExistingDocuments.filter(function (document) {
                    return Number(document.id) !== Number(documentId);
                });
                renderWarehouseEntryDocuments();
                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Documento eliminado correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2400
                });
            })
            .fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar el documento.', 'error');
            });
    });
}

function resolveWarehouseEntryDocumentTypeKey(document) {
    if (document.type && warehouseEntryDocumentTypes[document.type]) {
        return document.type;
    }

    const code = String(document.document_type?.code || document.documentType?.code || '').toUpperCase();
    if (warehouseEntryDocumentCodeMap[code]) {
        return warehouseEntryDocumentCodeMap[code];
    }

    const label = String(document.document_type?.description || document.documentType?.description || '').toUpperCase();

    if (label.includes('FACTURA')) return 'purchase_invoice';
    if (label.includes('BOLETA')) return 'receipt';
    if (label.includes('GUIA')) return 'dispatch_guide';
    if (label.includes('ANALISIS') || label.includes('PROTOCOLO')) return 'analysis_certificate';
    if (label.includes('SANITARIO')) return 'sanitary_registration';
    if (label.includes('CALIDAD')) return 'quality_certificate';
    if (label.includes('BPM') || label.includes('BPA') || label.includes('ISO')) return 'bpm_bpa_certificate';
    if (label.includes('FICHA')) return 'technical_sheet';
    if (label.includes('MEDICAMENTO')) return 'medicine_document';

    return 'other';
}

function getWarehouseEntryFileExtension(fileName) {
    const parts = String(fileName || '').toLowerCase().split('.');
    return parts.length > 1 ? parts.pop() : '';
}

function escapeWarehouseEntryHtml(value) {
    return $('<div>').text(value ?? '').html();
}
