let tableKardex;

document.addEventListener('DOMContentLoaded', function () {
    initKardexSelect2();
    initKardexTable();

    $(document).on('click', '#btnFilterKardex', function () {
        tableKardex.ajax.reload();
    });

    $(document).on('click', '#btnClearKardexFilters', function () {
        $('#kardex_filter_warehouse_id, #kardex_filter_article_id, #kardex_filter_movement_type').val('').trigger('change.select2');
        $('#kardex_filter_date_from, #kardex_filter_date_to, #kardex_filter_lot_number, #kardex_filter_document, #kardex_filter_related_party').val('');
        tableKardex.ajax.reload();
    });

    $(document).on('click', '.viewKardexMovement', function () {
        loadKardexMovementDetail($(this).data('id'));
    });

    $(document).on('click', '#btnKardexStockAtDate', loadKardexStockAtDate);
    $(document).on('click', '#btnRecalculateKardex', recalculateKardex);
});

function initKardexSelect2() {
    if (!$.fn.select2) {
        return;
    }

    $('.js-kardex-filter').select2({
        width: '100%'
    });
}

function initKardexTable() {
    tableKardex = $('#tableKardex').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.kardexList,
            data: function (data) {
                data.warehouse_id = $('#kardex_filter_warehouse_id').val();
                data.article_id = $('#kardex_filter_article_id').val();
                data.date_from = $('#kardex_filter_date_from').val();
                data.date_to = $('#kardex_filter_date_to').val();
                data.movement_type = $('#kardex_filter_movement_type').val();
                data.lot_number = $('#kardex_filter_lot_number').val();
                data.document = $('#kardex_filter_document').val();
                data.related_party = $('#kardex_filter_related_party').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'movement_date', name: 'movement_date' },
            { data: 'movement_number', name: 'movement_number', render: renderKardexMovementNumber },
            { data: 'warehouse', name: 'warehouse.name', orderable: false },
            { data: 'article', name: 'article.billing_name', orderable: false, render: renderKardexArticleCell },
            { data: 'lot_number', name: 'lot_number', defaultContent: '-' },
            { data: 'expiration_date', name: 'expiration_date' },
            { data: 'movement_type', name: 'movement_type' },
            { data: 'document', name: 'document', orderable: false, searchable: false, render: renderKardexDocumentPill },
            { data: 'quantity_in', name: 'quantity_in', className: 'text-right', render: renderKardexEntryNumber },
            { data: 'entry_unit_cost', name: 'unit_cost', className: 'text-right', render: renderKardexMoneyCell },
            { data: 'entry_total_cost', name: 'total_cost_in', className: 'text-right', render: renderKardexMoneyCell },
            { data: 'quantity_out', name: 'quantity_out', className: 'text-right', render: renderKardexExitNumber },
            { data: 'exit_unit_cost', name: 'unit_cost', className: 'text-right', render: renderKardexMoneyCell },
            { data: 'exit_total_cost', name: 'total_cost_out', className: 'text-right', render: renderKardexMoneyCell },
            { data: 'balance_quantity', name: 'balance_quantity', className: 'text-right', render: renderKardexBalanceNumber },
            { data: 'average_unit_cost_display', name: 'average_unit_cost', className: 'text-right', render: renderKardexMoneyCell },
            { data: 'balance_total_cost', name: 'balance_total_cost', className: 'text-right', render: renderKardexMoneyCell },
            { data: 'created_by_label', name: 'created_by_label', orderable: false, searchable: false },
            { data: 'updated_by_label', name: 'updated_by_label', orderable: false, searchable: false },
            { data: 'status', name: 'status' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: false,
        autoWidth: false,
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
        buttons: (window.routes.kardexCanExport ? ['excel', 'pdf', 'print'] : []).map(function (format) {
            const presentation = {
                excel: ['fa-file-excel', 'Excel', 'btn-success'],
                pdf: ['fa-file-pdf', 'PDF', 'btn-danger'],
                print: ['fa-print', 'Imprimir', 'btn-secondary']
            }[format];
            return {
                text: `<i class="fas ${presentation[0]}"></i> ${presentation[1]}`,
                className: `btn ${presentation[2]} btn-sm`,
                action: function () { window.open(`${window.routes.kardexExport}/${format}?${kardexFilterQuery()}`, '_blank'); }
            };
        })
    });
}

function loadKardexMovementDetail(id) {
    $.get(`${window.routes.kardexShow}/${id}`)
        .done(function (response) {
            renderKardexMovementDetail(response);
            $('#kardexViewModal').modal('show');
        })
        .fail(function () {
            Swal.fire('Error', 'No se pudo cargar el movimiento Kardex.', 'error');
        });
}

function renderKardexMovementDetail(response) {
    const movement = response.data;
    const symbol = movement.currency?.symbol || movement.currency?.code || '';

    $('#vk_movement_number').text(movement.movement_number || '-');
    $('#vk_status').html(kardexStatusBadge(movement.status));
    $('#vk_movement_type').html(kardexMovementBadge(movement.movement_type));
    $('#vk_warehouse').text(movement.warehouse?.name || '-');
    $('#vk_article').text([movement.article?.code, movement.article?.billing_name].filter(Boolean).join(' | ') || '-');
    $('#vk_balance_quantity').text(formatKardexNumber(movement.balance_quantity));
    $('#vk_movement_date').text(formatKardexDisplayDateTime(movement.movement_date));
    $('#vk_operation_type').text(formatKardexOperation(movement.operation_type));
    $('#vk_document').text([movement.document_type, movement.document_series, movement.document_number].filter(Boolean).join(' ') || '-');
    $('#vk_related_party').text(movement.related_party_name || '-');
    $('#vk_lot_number').text(movement.lot_number || '-');
    $('#vk_expiration_date').text(formatKardexDisplayDate(movement.expiration_date));
    $('#vk_unit').text(movement.unit?.description || '-');
    $('#vk_presentation').text(movement.presentation?.description || '-');
    $('#vk_brand').text(movement.brand?.description || '-');
    $('#vk_origin').text(movement.origin || '-');
    $('#vk_cost_type').text(movement.cost_type || '-');
    $('#vk_quantity_in').text(formatKardexNumber(movement.quantity_in));
    $('#vk_quantity_out').text(formatKardexNumber(movement.quantity_out));
    $('#vk_unit_cost').text(formatKardexMoney(movement.unit_cost, symbol));
    $('#vk_average_unit_cost').text(formatKardexMoney(movement.average_unit_cost, symbol));
    $('#vk_total_cost_in').text(formatKardexMoney(movement.total_cost_in, symbol));
    $('#vk_total_cost_out').text(formatKardexMoney(movement.total_cost_out, symbol));
    $('#vk_balance_total_cost').text(formatKardexMoney(movement.balance_total_cost, symbol));
    $('#vk_observations').text(movement.observations || '-');
    $('#vk_source_type').text(response.source_label || '-');
    $('#vk_source_id').text(movement.source_id || '-');
    $('#vk_source_item_type').text(response.source_item_label || '-');
    $('#vk_source_item_id').text(movement.source_item_id || '-');
    $('#vk_source_url')
        .toggleClass('d-none', !response.source_url)
        .attr('href', response.source_url || '#');
}

function kardexMovementBadge(type) {
    const map = {
        entry: ['Entrada', 'kardex-badge-entry', 'fa-sign-in-alt'],
        exit: ['Salida', 'kardex-badge-exit', 'fa-sign-out-alt'],
        adjustment_in: ['Ajuste Entrada', 'kardex-badge-adjustment-in', 'fa-plus-circle'],
        adjustment_out: ['Ajuste Salida', 'kardex-badge-adjustment-out', 'fa-minus-circle'],
        transfer_in: ['Transferencia Entrada', 'kardex-badge-transfer-in', 'fa-exchange-alt'],
        transfer_out: ['Transferencia Salida', 'kardex-badge-transfer-out', 'fa-exchange-alt'],
        reversal: ['Reversa', 'kardex-badge-reversal', 'fa-undo-alt'],
        exit_reversal: ['Reversa de salida', 'kardex-badge-reversal', 'fa-undo-alt'],
        linked_cost: ['Costo vinculado', 'kardex-badge-transfer-in', 'fa-coins'],
        cost_reversal: ['Reversa de costo', 'kardex-badge-reversal', 'fa-undo-alt']
    };
    const item = map[type] || [type || '-', 'kardex-badge-reversal', 'fa-circle'];

    return `<span class="kardex-badge ${item[1]}"><i class="fas ${item[2]}"></i>${escapeKardexHtml(item[0])}</span>`;
}

function kardexStatusBadge(status) {
    const map = {
        registered: ['Registrado', 'kardex-badge-registered', 'fa-check-circle'],
        cancelled: ['Anulado', 'kardex-badge-cancelled', 'fa-ban'],
        reversed: ['Revertido', 'kardex-badge-reversed', 'fa-history']
    };
    const item = map[status] || [status || '-', 'kardex-badge-reversed', 'fa-circle'];

    return `<span class="kardex-badge ${item[1]}"><i class="fas ${item[2]}"></i>${escapeKardexHtml(item[0])}</span>`;
}

function renderKardexMovementNumber(data, type) {
    if (type !== 'display') {
        return data;
    }

    const value = escapeKardexHtml(data || '-');

    return `<span class="kardex-movement-pill">${value}</span>`;
}

function renderKardexArticleCell(data, type) {
    if (type !== 'display') {
        return data;
    }

    const value = String(data || '-');
    const parts = value.split('|');
    const code = escapeKardexHtml((parts[0] || '').trim());
    const name = escapeKardexHtml((parts.slice(1).join('|') || parts[0] || '-').trim());

    if (!code || code === name) {
        return `<div class="kardex-article-cell"><span class="kardex-article-name" title="${name}">${name}</span></div>`;
    }

    return `
        <div class="kardex-article-cell">
            <span class="kardex-article-code">${code}</span>
            <span class="kardex-article-name" title="${name}">${name}</span>
        </div>
    `;
}

function renderKardexDocumentPill(data, type) {
    if (type !== 'display') {
        return data;
    }

    const value = escapeKardexHtml(data || '-');

    return `<span class="kardex-document-pill" title="${value}"><i class="fas fa-file-invoice mr-1"></i><span class="kardex-document-text">${value}</span></span>`;
}

function renderKardexEntryNumber(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="kardex-num-in">${escapeKardexHtml(data || '0.00')}</span>`;
}

function renderKardexExitNumber(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="kardex-num-out">${escapeKardexHtml(data || '0.00')}</span>`;
}

function renderKardexBalanceNumber(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="kardex-num-balance">${escapeKardexHtml(data || '0.00')}</span>`;
}

function renderKardexMoneyCell(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="kardex-money">${escapeKardexHtml(data || '0.00')}</span>`;
}

function formatKardexOperation(value) {
    const map = {
        warehouse_entry: 'Ingreso de Almacen',
        warehouse_entry_cancel: 'Anulacion de Ingreso',
        manual_adjustment: 'Ajuste Manual',
        sale_exit: 'Salida por Venta',
        transfer: 'Transferencia',
        warehouse_entry_linked_cost: 'Costo vinculado al ingreso',
        warehouse_entry_linked_cost_cancel: 'Reversa de costo vinculado',
        customer_order_dispatch: 'Despacho de OC Cliente',
        customer_order_dispatch_cancel: 'Reversa de despacho de OC Cliente',
        customer_return: 'Devolución cliente',
        customer_return_reversal: 'Anulación de devolución de cliente'
    };

    return map[value] || value || '-';
}

function formatKardexNumber(value) {
    return (parseFloat(value) || 0).toFixed(2);
}

function formatKardexMoney(value, symbol = '') {
    return `${symbol ? `${symbol} ` : ''}${formatKardexNumber(value)}`.trim();
}

function formatKardexDisplayDate(value) {
    if (!value) {
        return '-';
    }

    const date = String(value).substring(0, 10);
    const parts = date.split('-');

    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : date;
}

function formatKardexDisplayDateTime(value) {
    if (!value) {
        return '-';
    }

    const raw = String(value);
    const date = formatKardexDisplayDate(raw.substring(0, 10));
    const time = raw.substring(11, 16);

    return time ? `${date} ${time}` : date;
}

function escapeKardexHtml(value) {
    return $('<div>').text(value ?? '').html();
}

function kardexFilterData() {
    return {
        warehouse_id: $('#kardex_filter_warehouse_id').val() || '',
        article_id: $('#kardex_filter_article_id').val() || '',
        date_from: $('#kardex_filter_date_from').val() || '',
        date_to: $('#kardex_filter_date_to').val() || '',
        movement_type: $('#kardex_filter_movement_type').val() || '',
        lot_number: $('#kardex_filter_lot_number').val() || '',
        document: $('#kardex_filter_document').val() || '',
        related_party: $('#kardex_filter_related_party').val() || ''
    };
}

function kardexFilterQuery() {
    return $.param(kardexFilterData());
}

function loadKardexStockAtDate() {
    const date = $('#kardex_stock_date').val();
    if (!date) {
        Swal.fire('Validación', 'Seleccione la fecha de consulta.', 'warning');
        return;
    }

    $.get(window.routes.kardexStockAtDate, {
        date,
        warehouse_id: $('#kardex_filter_warehouse_id').val(),
        article_id: $('#kardex_filter_article_id').val()
    }).done(function (response) {
        const rows = response.items.map(item => `
            <tr>
                <td>${escapeKardexHtml(item.warehouse || '-')}</td>
                <td>${escapeKardexHtml(item.article || '-')}</td>
                <td>${escapeKardexHtml(item.lot_number || '-')}</td>
                <td class="text-right">${formatKardexNumber(item.quantity)}</td>
                <td class="text-right">${formatKardexMoney(item.average_cost, 'S/')}</td>
                <td class="text-right">${formatKardexMoney(item.total_value, 'S/')}</td>
            </tr>`).join('');
        Swal.fire({
            title: `Stock al ${formatKardexDisplayDate(date)}`,
            width: 1000,
            html: `<div class="text-left mb-2"><strong>Cantidad:</strong> ${formatKardexNumber(response.total_quantity)} · <strong>Valor:</strong> ${formatKardexMoney(response.total_value, 'S/')}</div>
                <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Almacén</th><th>Artículo</th><th>Lote</th><th>Cantidad</th><th>C. promedio</th><th>Valor</th></tr></thead><tbody>${rows || '<tr><td colspan="6">Sin stock a esa fecha.</td></tr>'}</tbody></table></div>`,
            confirmButtonText: 'Cerrar'
        });
    }).fail(function () {
        Swal.fire('Error', 'No se pudo consultar el stock histórico.', 'error');
    });
}

function recalculateKardex() {
    Swal.fire({
        title: '¿Recalcular Kardex?',
        text: 'Se reconstruirán costos promedio y saldos de los artículos alcanzados por los filtros.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, recalcular',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        const data = kardexFilterData();
        data._token = $('meta[name="csrf-token"]').attr('content');
        $.post(window.routes.kardexRecalculate, data)
            .done(function (response) {
                Swal.fire('Completado', response.message, 'success');
                tableKardex.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo recalcular el Kardex.', 'error');
            });
    });
}
