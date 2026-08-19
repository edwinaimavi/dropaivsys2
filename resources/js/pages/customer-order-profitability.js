let customerOrderProfitabilityTable;
let currentProfitabilityOrderId = null;
let customerOrderProfitabilityTotals = emptyProfitabilityTotals();

document.addEventListener('DOMContentLoaded', () => {
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    customerOrderProfitabilityTable = $('#tableCustomerOrderProfitability').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: window.customerOrderProfitabilityRoutes.list,
            data: data => Object.assign(data, filters()),
            dataSrc: response => {
                customerOrderProfitabilityTotals = normalizeProfitabilityTotals(response.totals);

                return response.data || [];
            }
        },
        columns: [
            { data: 'DT_RowIndex', className: 'text-muted' },
            { data: 'code', render: value => `<strong class="text-dark">${escapeHtml(value || '-')}</strong>` },
            { data: 'purchase_order_number', render: value => escapeHtml(value || '-') },
            { data: 'customer', render: value => escapeHtml(value || '-') },
            { data: 'company', render: value => escapeHtml(value || '-') },
            { data: 'sale_total', className: 'text-right', render: renderMoney },
            { data: 'purchase_total', className: 'text-right', render: renderMoney },
            { data: 'linked_costs_total', className: 'text-right', render: renderMoney },
            { data: 'igv_payable', className: 'text-right', render: renderIgvPayable },
            { data: 'income_tax', className: 'text-right', render: renderIncomeTax },
            { data: 'net_profit', className: 'text-right', render: renderNetProfit },
            { data: 'profitability_percentage', className: 'text-center', render: renderProfitability },
            { data: 'status_label', render: (value, type, row) => renderStatus(value, row) },
            { data: null, orderable: false, searchable: false, className: 'text-center', render: row => `<button type="button" class="btn btn-outline-success btn-sm cop-view" data-id="${Number(row.id)}" title="Ver análisis"><i class="fas fa-chart-pie mr-1"></i>Ver análisis</button>` }
        ],
        order: [[0, 'asc']],
        responsive: false,
        autoWidth: false,
        pageLength: 10,
        footerCallback: function () { updateProfitabilityFooter(this.api()); },
        dom: "<'row align-items-center mb-3'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>><'row'<'col-sm-12'tr>><'row align-items-center mt-3'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7 d-flex justify-content-center justify-content-md-end'p>>",
        language: {
            processing: 'Procesando...',
            search: 'Buscar:',
            searchPlaceholder: 'Buscar en la tabla...',
            lengthMenu: 'Mostrar _MENU_ registros',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            loadingRecords: 'Cargando...',
            zeroRecords: 'No se encontraron registros',
            emptyTable: 'No se encontraron registros',
            paginate: { first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último' },
            aria: { sortAscending: ': activar para ordenar ascendente', sortDescending: ': activar para ordenar descendente' }
        }
    });

    $('#cop_filter').on('click', reloadTable);
    $('#cop_mode').on('change', reloadTable);
    $('#cop_search').on('keydown', event => { if (event.key === 'Enter') reloadTable(); });
    $('#cop_clear').on('click', clearFilters);
    $(document).on('click', '.cop-view', function () { openProfitability($(this).data('id')); });
    $(document).on('click', '.cop-view-order-documents', function () { showProfitabilityOrderDocuments($(this).attr('data-documents') || '[]'); });
    $(document).on('click', '.cop-summary-tab-link[data-tab-target]', function () { openProfitabilityTab($(this).data('tab-target')); });
    $(document).on('click', '.cop-preview-linked-image', function (event) {
        event.preventDefault();
        showLinkedExpenseImage($(this).attr('href'), $(this).data('label'));
    });
    $(document).on('keydown', '.cop-summary-tab-link[data-tab-target]', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openProfitabilityTab($(this).data('tab-target'));
        }
    });
    $('#copRecalculate').on('click', function () { recalculate(this); });
    $('#copLinkedDocumentPreviewModal').on('hidden.bs.modal', function () {
        $('#copLinkedDocumentPreviewImage').attr('src', '');
        if ($('#copDetailModal').hasClass('show')) $('body').addClass('modal-open');
    });
});

function filters() { return { company_id: $('#cop_company').val(), customer_id: $('#cop_customer').val(), status: $('#cop_status').val(), date_from: $('#cop_from').val(), date_to: $('#cop_to').val(), search_order: $('#cop_search').val(), mode: $('#cop_mode').val() }; }
function reloadTable() { customerOrderProfitabilityTable.ajax.reload(); }
function clearFilters() { $('#cop_company,#cop_customer,#cop_status').val(''); $('#cop_from,#cop_to,#cop_search').val(''); $('#cop_mode').val('without_igv'); reloadTable(); }
function openProfitability(id) { currentProfitabilityOrderId = id; const mode = $('#cop_mode').val(); $('#copDetailBody').html('<div class="text-center p-5"><i class="fas fa-spinner fa-spin fa-2x text-success"></i><div class="mt-2">Calculando...</div></div>'); $('#copDetailModal').modal('show'); $.get(`${window.customerOrderProfitabilityRoutes.show}/${id}`, { mode }).done(showDetail).fail(showError); }
function recalculate(button) { if (!currentProfitabilityOrderId) return; const $button = $(button); if ($button.prop('disabled')) return; const original = $button.html(); $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Recalculando...'); $.post(`${window.customerOrderProfitabilityRoutes.recalculate}/${currentProfitabilityOrderId}/recalculate`, { mode: $('#cop_mode').val() }).done(response => { showDetail(response); customerOrderProfitabilityTable.ajax.reload(null, false); }).fail(showError).always(() => $button.prop('disabled', false).html(original)); }
function showDetail(response) { $('#copDetailBody').html(response.html); const mode = $('#cop_mode').val(); $('#copPdf').attr('href', `${window.customerOrderProfitabilityRoutes.pdf}/${currentProfitabilityOrderId}/pdf?mode=${mode}`); $('#copPrint').attr('href', `${window.customerOrderProfitabilityRoutes.print}/${currentProfitabilityOrderId}/print?mode=${mode}`); }
function openProfitabilityTab(tabTarget) {
    const targetId = `#cop_${String(tabTarget || '').replace(/[^a-z]/g, '')}`;
    const tabLink = $(`#copDetailModal .cop-modal-nav .nav-link[href="${targetId}"]`);

    if (!tabLink.length) return;

    tabLink.tab('show');
    $('#copDetailModal .cop-tab-content').stop(true).animate({ scrollTop: 0 }, 220);
}
function showError() { $('#copDetailBody').html('<div class="alert alert-danger m-4">No se pudo calcular la rentabilidad de esta orden.</div>'); }
function money(value) { return Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function emptyProfitabilityTotals() {
    return {
        sale_total: 0,
        purchase_total: 0,
        cost_total: 0,
        igv_payable_total: 0,
        income_tax_total: 0,
        net_profit_total: 0
    };
}
function normalizeProfitabilityTotals(totals) {
    const values = totals || {};

    return {
        sale_total: Number(values.sale_total || 0),
        purchase_total: Number(values.purchase_total || 0),
        cost_total: Number(values.cost_total || 0),
        igv_payable_total: Number(values.igv_payable_total || 0),
        income_tax_total: Number(values.income_tax_total || 0),
        net_profit_total: Number(values.net_profit_total || 0)
    };
}
function hasInternalProfitabilitySearch(api) {
    return String(api.search() || '').trim() !== ''
        || api.columns().search().toArray().some(value => String(value || '').trim() !== '');
}
function internalSearchProfitabilityTotals(api) {
    return api.rows({ search: 'applied' }).data().toArray().reduce((totals, row) => {
        totals.sale_total += Number(row.sale_total || 0);
        totals.purchase_total += Number(row.purchase_total || 0);
        totals.cost_total += Number(row.linked_costs_total || 0);
        totals.igv_payable_total += Number(row.igv_payable || 0);
        totals.income_tax_total += Number(row.income_tax || 0);
        totals.net_profit_total += Number(row.net_profit || 0);

        return totals;
    }, emptyProfitabilityTotals());
}
function updateProfitabilityFooter(api) {
    const totals = hasInternalProfitabilitySearch(api)
        ? internalSearchProfitabilityTotals(api)
        : customerOrderProfitabilityTotals;

    $('#cop_total_sale').text(`S/ ${money(totals.sale_total)}`);
    $('#cop_total_purchase').text(`S/ ${money(totals.purchase_total)}`);
    $('#cop_total_cost').text(`S/ ${money(totals.cost_total)}`);

    $('#cop_total_igv_payable')
        .removeClass('cop-total-igv')
        .addClass('cop-total-igv')
        .text(`S/ ${money(totals.igv_payable_total)}`);

    $('#cop_total_income_tax')
        .removeClass('cop-total-tax')
        .addClass('cop-total-tax')
        .text(`S/ ${money(totals.income_tax_total)}`);

    $('#cop_total_net_profit')
        .removeClass('cop-total-positive cop-total-negative')
        .addClass(totals.net_profit_total > 0 ? 'cop-total-positive' : totals.net_profit_total < 0 ? 'cop-total-negative' : '')
        .text(`S/ ${money(totals.net_profit_total)}`);
}
function renderMoney(value, type, row) { if (type !== 'display') return Number(value || 0); return `<span class="cop-money">${escapeHtml(row.currency || 'S/')} ${money(value)}</span>`; }
function renderIgvPayable(value, type, row) {
    if (type !== 'display') return Number(value || 0);

    return `<span class="cop-money cop-money-igv">${escapeHtml(row.currency || 'S/')} ${money(value)}</span>`;
}

function renderIncomeTax(value, type, row) {
    if (type !== 'display') return Number(value || 0);

    return `<span class="cop-money cop-money-tax">${escapeHtml(row.currency || 'S/')} ${money(value)}</span>`;
}
function renderNetProfit(value, type, row) { if (type !== 'display') return Number(value || 0); const css = Number(value) < 0 ? 'cop-money-profit-negative' : 'cop-money-profit-positive'; return `<span class="cop-money ${css}">${escapeHtml(row.currency || 'S/')} ${money(value)}</span>`; }
function renderProfitability(value, type) { const amount = Number(value || 0); if (type !== 'display') return amount; const css = amount < 0 ? 'cop-profit-negative' : amount <= 5 ? 'cop-profit-low' : amount <= 15 ? 'cop-profit-medium' : 'cop-profit-high'; const icon = amount < 0 ? 'fa-arrow-down' : amount <= 5 ? 'fa-minus' : 'fa-arrow-up'; return `<span class="cop-profit-pill ${css}"><i class="fas ${icon}"></i>${amount.toFixed(2)}%</span>`; }
function renderStatus(value, row) { return `<span class="cop-status-pill ${escapeHtml(row.status_class || 'cop-status-unknown')}"><i class="fas ${escapeHtml(row.status_icon || 'fa-minus')}"></i>${escapeHtml(value || 'Sin estado')}</span>`; }
function showProfitabilityOrderDocuments(documents) { try { documents = JSON.parse(documents); } catch (_) { documents = []; } const rows = documents.length ? documents.map(document => `<tr><td>${escapeHtml(document.type || '-')}</td><td>${escapeHtml(document.file || '-')}</td><td>${escapeHtml(document.date || '-')}</td><td class="text-center"><a href="${escapeHtml(document.view_url)}" target="_blank" rel="noopener" class="btn btn-outline-info btn-xs"><i class="fas fa-eye mr-1"></i>Ver</a></td></tr>`).join('') : '<tr><td colspan="4" class="text-center text-muted py-4">No hay documentos adjuntos para esta orden.</td></tr>'; $('#copOrderDocumentsBody').html(`<table class="table table-sm table-hover mb-0"><thead><tr><th>Tipo</th><th>Archivo</th><th>Fecha</th><th class="text-center">Acción</th></tr></thead><tbody>${rows}</tbody></table>`); $('#copOrderDocumentsModal').modal('show'); }
function showLinkedExpenseImage(url, label) { const image = $('#copLinkedDocumentPreviewImage'); const error = $('#copLinkedDocumentPreviewError'); $('#copLinkedDocumentPreviewTitle').text(label || 'Vista previa'); $('#copLinkedDocumentOpenTab').attr('href', url); error.addClass('d-none'); image.removeClass('d-none').off('error').one('error', function () { image.addClass('d-none'); error.removeClass('d-none'); }).attr('src', url); $('#copLinkedDocumentPreviewModal').modal('show'); }
function escapeHtml(value) { return $('<div>').text(value).html(); }
