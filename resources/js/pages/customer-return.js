let customerReturnsTable = null;
let customerReturnContext = null;
let customerReturnDocumentsId = null;
let customerReturnReverseOpening = false;
let customerReturnReverseSubmitting = false;

const crEscape = value => $('<div>').text(value ?? '').html();
const crNumber = value => Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 0, maximumFractionDigits: 4 });
const crMoney = value => Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const crError = xhr => Object.values(xhr.responseJSON?.errors || {}).flat()[0] || xhr.responseJSON?.message || 'No se pudo completar la operación.';
const crStatus = status => ({ draft: ['BORRADOR', 'warning'], confirmed: ['CONFIRMADA', 'success'], cancelled: ['CANCELADA', 'secondary'], reversed: ['REVERTIDA', 'dark'] }[status] || [status, 'secondary']);
const crLocalDisplay = value => {
    const match = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/);
    return match ? `${match[3]}/${match[2]}/${match[1]} ${match[4]}:${match[5]}` : '—';
};

$(function () {
    if ($('#customerReturnsTable').length) initCustomerReturnTable();
    $('#crFilterSearch,#crFilterCompany,#crFilterStatus,#crFilterFrom,#crFilterTo').off('.customerReturn').on('change.customerReturn keyup.customerReturn', () => customerReturnsTable?.ajax.reload());
    $('#customerReturnForm').off('.customerReturn').on('submit.customerReturn', submitCustomerReturn);
    $('#customerReturnDocumentsForm').off('.customerReturn').on('submit.customerReturn', submitCustomerReturnDocument);
    $('#crReason').off('.customerReturn').on('change.customerReturn', function () {
        $('#crReasonDescription').prop('required', this.value === 'other');
        if (['damaged_product', 'expiration_or_lot'].includes(this.value)) {
            $('#customerReturnErrors').removeClass('d-none').html('<strong>Confirmación restringida:</strong> este motivo requiere cuarentena o baja y no puede reintegrarse al stock disponible. Puede conservar el caso como borrador y adjuntar sustento.');
        } else {
            $('#customerReturnErrors').addClass('d-none').empty();
        }
    });

    $(document).off('click.customerReturn', '.viewCustomerReturn').on('click.customerReturn', '.viewCustomerReturn', function () { openCustomerReturnDetail($(this).data('id')); });
    $(document).off('click.customerReturn', '.editCustomerReturn').on('click.customerReturn', '.editCustomerReturn', function () { $('#customerReturnDetailModal').modal('hide'); editCustomerReturn($(this).data('id')); });
    $(document).off('click.customerReturn', '.confirmCustomerReturn').on('click.customerReturn', '.confirmCustomerReturn', function () { customerReturnAction($(this).data('id')); });
    $(document).off('click.customerReturn', '.cancelCustomerReturn').on('click.customerReturn', '.cancelCustomerReturn', function () { customerReturnReasonAction($(this).data('id'), 'cancel-draft', 'Cancelar borrador', 'El borrador quedará histórico sin afectar inventario.'); });
    $(document).off('click.customerReturn', '.reverseCustomerReturn').on('click.customerReturn', '.reverseCustomerReturn', function () { reverseCustomerReturn($(this).data('id')); });
    $(document).off('click.customerReturn', '.manageCustomerReturnDocuments').on('click.customerReturn', '.manageCustomerReturnDocuments', function () { $('#customerReturnDetailModal').modal('hide'); openCustomerReturnDocuments($(this).data('id'), $(this).data('number')); });
    $(document).off('click.customerReturn', '.deleteCustomerReturnDocument').on('click.customerReturn', '.deleteCustomerReturnDocument', function () { deleteCustomerReturnDocument($(this).data('id')); });
    $('#customerReturnModal,#customerReturnDetailModal,#customerReturnDocumentsModal').off('hidden.bs.modal.customerReturn').on('hidden.bs.modal.customerReturn', function () {
        if ($('#viewCustomerPurchaseOrderModal').hasClass('show')) $('body').addClass('modal-open');
    });

    if (window.customerReturnDeepLink) openCustomerReturnDetail(window.customerReturnDeepLink);
});

function initCustomerReturnTable() {
    customerReturnsTable = $('#customerReturnsTable').DataTable({
        processing: true, serverSide: true, responsive: true, order: [[1, 'desc']],
        ajax: { url: window.customerReturnRoutes.list, data: data => Object.assign(data, { search_text: $('#crFilterSearch').val(), company_id: $('#crFilterCompany').val(), status: $('#crFilterStatus').val(), date_from: $('#crFilterFrom').val(), date_to: $('#crFilterTo').val() }) },
        columns: [
            { data: 'return_number', name: 'return_number', render: value => `<strong class="text-primary">${crEscape(value)}</strong>` },
            { data: 'return_date', name: 'return_date', render: value => crEscape(value || '—') },
            { data: 'company_name', name: 'company.business_name' }, { data: 'customer_name', orderable: false }, { data: 'order_number', orderable: false },
            { data: 'dispatch_number', orderable: false }, { data: 'warehouse_name', orderable: false },
            { data: 'total_quantity', searchable: false, render: value => `<strong>${crNumber(value)}</strong>` },
            { data: 'status', render: value => { const status = crStatus(value); return `<span class="badge badge-${status[1]}">${status[0]}</span>`; } },
            { data: 'invoice_situation', orderable: false, searchable: false }, { data: null, orderable: false, searchable: false, render: row => crActions(row) },
        ],
        language: { url: '/vendor/datatables/js/i18n/es-ES.json' },
    });
}

function crActions(row) {
    let html = '<div class="btn-group">';
    if (row.can_view !== false) html += `<button class="btn btn-sm btn-outline-primary viewCustomerReturn" data-id="${row.id}" title="Ver"><i class="fas fa-eye"></i></button>`;
    if (row.can_edit) html += `<button class="btn btn-sm btn-outline-secondary editCustomerReturn" data-id="${row.id}" title="Editar"><i class="fas fa-edit"></i></button>`;
    if (row.can_documents) html += `<button class="btn btn-sm btn-outline-info manageCustomerReturnDocuments" data-id="${row.id}" data-number="${crEscape(row.return_number)}" title="Documentos"><i class="fas fa-paperclip"></i></button>`;
    if (row.can_confirm) html += `<button class="btn btn-sm btn-success confirmCustomerReturn" data-id="${row.id}" title="Confirmar devolución"><i class="fas fa-check"></i></button>`;
    if (row.can_cancel) html += `<button class="btn btn-sm btn-outline-danger cancelCustomerReturn" data-id="${row.id}" title="Cancelar borrador"><i class="fas fa-ban"></i></button>`;
    if (row.can_reverse) html += `<button class="btn btn-sm btn-danger reverseCustomerReturn" data-id="${row.id}" title="Anular / Revertir devolución"><i class="fas fa-undo"></i></button>`;
    return html + '</div>';
}

window.openCustomerReturnForDispatch = function (dispatchId) {
    if (!dispatchId) return;
    $.get(`${window.customerReturnRoutes.dispatchData}/${dispatchId}/data`).done(response => prepareCustomerReturnModal(response.data)).fail(xhr => Swal.fire('No se pudo preparar la devolución', crError(xhr), 'error'));
};

function prepareCustomerReturnModal(data, editing = null, editingDocuments = []) {
    customerReturnContext = data;
    const dispatch = data.dispatch;
    const order = dispatch.customer_purchase_order || {};
    const customer = order.customer || {};
    const warehouse = dispatch.warehouse || {};
    $('#customerReturnModalTitle').text(editing ? `Continuar devolución ${editing.return_number}` : 'Registrar devolución de cliente');
    $('#customerReturnModalSubtitle').text(editing ? 'Editando el borrador existente; no se creará otra DEV' : 'La SAL original permanecerá intacta');
    $('#customerReturnId').val(editing?.id || ''); $('#customerReturnDispatchId').val(dispatch.id); $('#customerReturnIdempotencyKey').val(editing?.idempotency_key || data.idempotency_key);
    $('#crOrder').text(order.purchase_order_number || order.code || '—'); $('#crCustomer').text(customer.business_name || customer.full_name || '—'); $('#crDispatch').text(dispatch.dispatch_number || '—');
    $('#crDispatchDate').text(dispatch.dispatch_date_display || crLocalDisplay(dispatch.dispatch_date_local)); $('#crWarehouse').text([warehouse.code, warehouse.name].filter(Boolean).join(' | ') || '—'); $('#crGuide').text([dispatch.document_type, dispatch.document_number].filter(Boolean).join(' | ') || 'Sin guía registrada');
    $('#crReturnDate').val(editing?.return_date_local || data.current_local_datetime || ''); $('#crSafetyWarning').text(data.safety_warning); $('#crObservation').val(editing?.observation || ''); $('#crReasonDescription').val(editing?.reason_description || '');
    $('#crReceivedBy').html((data.responsibles || []).map(user => `<option value="${user.id}">${crEscape([user.name, user.lastname].filter(Boolean).join(' '))}</option>`).join('')).val(editing?.received_by_user_id || data.current_user_id);
    $('#crReason').html(Object.entries(data.reasons || {}).map(([value, label]) => `<option value="${value}">${crEscape(label)}</option>`).join('')).val(editing?.reason || 'customer_rejection').trigger('change');
    const editingItems = new Map((editing?.items || []).map(item => [Number(item.warehouse_dispatch_item_id), item.quantity]));
    $('#crItemsBody').html((data.items || []).map(item => `<tr data-item-id="${item.id}"><td><strong>${crEscape(item.article)}</strong>${item.billed_quantity > 0 ? `<small class="d-block text-danger"><i class="fas fa-file-invoice-dollar"></i> Facturado: ${crNumber(item.billed_quantity)}</small>` : ''}</td><td>${crEscape(item.lot_number || '—')}</td><td>${crEscape(item.expiration_date || '—')}</td><td>${crNumber(item.dispatched_quantity)}</td><td>${crNumber(item.returned_quantity)}</td><td><strong class="text-success">${crNumber(item.returnable_quantity)}</strong></td><td><input type="number" min="0" max="${item.returnable_quantity}" step="0.0001" class="form-control cr-item-quantity" value="${editingItems.get(Number(item.id)) || ''}" placeholder="0"></td></tr>`).join(''));
    const invoiceRows = (data.items || []).flatMap(item => item.invoices || []); $('#crInvoiceWarning').toggleClass('d-none', invoiceRows.length === 0); $('#crInvoiceWarningText').text(data.invoice_warning || '');
    $('#crInvoiceList').html(invoiceRows.map(invoice => `<span class="badge badge-light border mr-1">${crEscape(invoice.number)} · ${crNumber(invoice.quantity)} · cobro ${crEscape(invoice.payment_status || 'pendiente')}</span>`).join(''));
    const types = window.customerReturnDocumentTypes || {}; $('#crDocumentType').html('<option value="">Tipo</option>' + Object.entries(types).map(([value, label]) => `<option value="${value}">${crEscape(label)}</option>`).join(''));
    $('#crDocumentFile').val(''); $('#crDocumentDescription').val('');
    $('#crExistingDocuments').html(editing ? `<div class="mb-2"><strong>Documentos asociados a ${crEscape(editing.return_number)}</strong></div>${renderCrDocuments(editingDocuments, false)}` : '');
    $('#btnSaveCustomerReturn').html(`<i class="fas fa-save mr-1"></i>${editing ? 'Guardar cambios' : 'Guardar borrador'}`);
    $('#customerReturnModal').modal('show');
}

function submitCustomerReturn(event) {
    event.preventDefault(); const id = $('#customerReturnId').val(); const dispatchId = $('#customerReturnDispatchId').val(); const form = new FormData();
    form.append('return_date', $('#crReturnDate').val()); form.append('reason', $('#crReason').val()); form.append('reason_description', $('#crReasonDescription').val()); form.append('observation', $('#crObservation').val()); form.append('received_by_user_id', $('#crReceivedBy').val());
    if (!id) form.append('idempotency_key', $('#customerReturnIdempotencyKey').val()); let index = 0;
    $('#crItemsBody tr').each(function () { const quantity = $(this).find('.cr-item-quantity').val(); if (Number(quantity) > 0) { form.append(`items[${index}][warehouse_dispatch_item_id]`, $(this).data('item-id')); form.append(`items[${index}][quantity]`, quantity); index += 1; } });
    const file = $('#crDocumentFile')[0]?.files?.[0]; if (file) { form.append('documents[0][type]', $('#crDocumentType').val()); form.append('documents[0][description]', $('#crDocumentDescription').val()); form.append('documents[0][file]', file); }
    if (id) form.append('_method', 'PUT'); $('#btnSaveCustomerReturn').prop('disabled', true);
    $.ajax({ url: id ? `${window.customerReturnRoutes.base}/${id}` : `${window.customerReturnRoutes.dispatchData}/${dispatchId}`, method: 'POST', data: form, processData: false, contentType: false })
        .done(response => { $('#customerReturnModal').modal('hide'); Swal.fire({ icon: 'success', title: 'Borrador guardado', text: response.message, timer: 1800, showConfirmButton: false }); refreshCustomerReturnUi(response.data || { customer_purchase_order_id: customerReturnContext?.dispatch?.customer_purchase_order_id }); })
        .fail(xhr => $('#customerReturnErrors').removeClass('d-none').text(crError(xhr))).always(() => $('#btnSaveCustomerReturn').prop('disabled', false));
}

function openCustomerReturnDetail(id) {
    $.get(`${window.customerReturnRoutes.base}/${id}`).done(response => {
        const payload = response.data; const ret = payload.return; const status = crStatus(ret.status); const invoices = payload.invoices || [];
        const statusDescription = ret.status === 'reversed' ? '<small class="d-block text-muted mt-1">Devolución dejada sin efecto</small>' : '';
        $('#crdNumber').text(ret.return_number);
        $('#crdContent').html(`<div class="d-flex justify-content-between mb-3"><div><h3 class="mb-0 text-primary">${crEscape(ret.return_number)}</h3><span class="badge badge-${status[1]}">${status[0]}</span>${statusDescription}</div><div class="text-right"><small class="text-muted">SAL ORIGINAL</small><strong class="d-block">${crEscape(ret.warehouse_dispatch?.dispatch_number)}</strong></div></div>
        <div class="cr-detail-metrics mb-3"><div><small>CANTIDAD DEVUELTA</small><strong>${crNumber(payload.total_quantity)}</strong></div><div><small>VALOR RESTITUIDO</small><strong>S/ ${crMoney(payload.inventory_value)}</strong></div><div><small>FACTURA RELACIONADA</small><strong>${invoices.length ? invoices.map(invoice => crEscape(invoice.number)).join(', ') : 'No aplica'}</strong></div><div><small>NOTA DE CRÉDITO</small><strong>${invoices.length ? 'Pendiente / aplica' : 'No aplica'}</strong></div></div>
        <div class="row mb-3"><div class="col-md-4"><small>Cliente</small><strong class="d-block">${crEscape(ret.customer_purchase_order?.customer?.business_name || ret.customer_purchase_order?.customer?.full_name)}</strong></div><div class="col-md-4"><small>OC / Almacén</small><strong class="d-block">${crEscape(ret.customer_purchase_order?.purchase_order_number || ret.customer_purchase_order?.code)} · ${crEscape(ret.warehouse?.name)}</strong></div><div class="col-md-4"><small>Fecha / Recibido por</small><strong class="d-block">${crEscape(ret.return_date_display || crLocalDisplay(ret.return_date_local))} · ${crEscape([ret.received_by?.name, ret.received_by?.lastname].filter(Boolean).join(' '))}</strong></div></div>
        <p><strong>Motivo:</strong> ${crEscape(payload.reason_label)} ${ret.reason_description ? `— ${crEscape(ret.reason_description)}` : ''}</p>
        ${invoices.length ? '<div class="alert alert-danger"><strong>Atención:</strong> la devolución física no modificó el comprobante ni la cobranza. Gestione la Nota de Crédito correspondiente.</div>' : ''}
        <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Artículo</th><th>Lote</th><th>Vencimiento</th><th>Cantidad</th><th>Costo unitario</th><th>Valor</th><th>Kardex</th></tr></thead><tbody>${(ret.items || []).map(item => `<tr><td>${crEscape(item.article?.billing_name || 'Artículo')}</td><td>${crEscape(item.lot_number_snapshot || '—')}</td><td>${crEscape(item.expiration_date_snapshot || '—')}</td><td>${crNumber(item.quantity)}</td><td>S/ ${crMoney(item.unit_cost_snapshot)}</td><td>S/ ${crMoney(item.total_cost)}</td><td>${crEscape(item.kardex_movement?.movement_number || 'Pendiente')}${item.reversal_kardex_movement ? ` / ${crEscape(item.reversal_kardex_movement.movement_number)}` : ''}</td></tr>`).join('')}</tbody></table></div>
        <h6 class="font-weight-bold mt-3">Documentos</h6>${renderCrDocuments(payload.documents?.documents || [], false)}<h6 class="font-weight-bold mt-3">Trazabilidad</h6>${crTraceability(ret)}
        <div class="alert alert-warning mt-3 mb-0"><i class="fas fa-shield-alt mr-1"></i> Productos dañados, vencidos o no aptos requieren un flujo independiente de cuarentena/baja.</div>${crDetailActions(ret, payload.actions || {})}`);
        $('#customerReturnDetailModal').modal('show');
    }).fail(xhr => Swal.fire('No se pudo abrir', crError(xhr), 'error'));
}

function crTraceability(ret) {
    const actor = user => crEscape([user?.name, user?.lastname].filter(Boolean).join(' ') || 'No registrado');
    const rows = [`<li><strong>Creada por:</strong> ${actor(ret.creator)}</li>`];
    if (ret.confirmed_at) rows.push(`<li><strong>Confirmada por:</strong> ${actor(ret.confirmed_by)}</li>`);
    if (ret.cancelled_at) rows.push(`<li><strong>Cancelada por:</strong> ${actor(ret.cancelled_by)}${ret.cancellation_reason ? ` · ${crEscape(ret.cancellation_reason)}` : ''}</li>`);
    if (ret.reversed_at) rows.push(`<li><strong>Revertida por:</strong> ${actor(ret.reversed_by)}${ret.reversal_reason ? ` · ${crEscape(ret.reversal_reason)}` : ''}</li>`);
    return `<ul class="small mb-0 pl-3">${rows.join('')}</ul>`;
}

function crDetailActions(ret, actions) {
    const buttons = [];
    if (actions.edit) buttons.push(`<button type="button" class="btn btn-outline-secondary editCustomerReturn" data-id="${ret.id}"><i class="fas fa-edit mr-1"></i>Editar</button>`);
    if (actions.documents) buttons.push(`<button type="button" class="btn btn-outline-info manageCustomerReturnDocuments" data-id="${ret.id}" data-number="${crEscape(ret.return_number)}"><i class="fas fa-paperclip mr-1"></i>Documentos</button>`);
    if (actions.confirm) buttons.push(`<button type="button" class="btn btn-success confirmCustomerReturn" data-id="${ret.id}"><i class="fas fa-check-circle mr-1"></i>Confirmar devolución</button>`);
    if (actions.cancel) buttons.push(`<button type="button" class="btn btn-outline-danger cancelCustomerReturn" data-id="${ret.id}"><i class="fas fa-ban mr-1"></i>Cancelar borrador</button>`);
    if (actions.reverse) buttons.push(`<button type="button" class="btn btn-danger reverseCustomerReturn" data-id="${ret.id}"><i class="fas fa-undo mr-1"></i>Anular / Revertir devolución</button>`);
    return buttons.length ? `<div class="d-flex flex-wrap justify-content-end mt-3 cr-detail-actions">${buttons.join('')}</div>` : '';
}

function editCustomerReturn(id) {
    $.get(`${window.customerReturnRoutes.base}/${id}`).done(response => { const ret = response.data.return; $.get(`${window.customerReturnRoutes.dispatchData}/${ret.warehouse_dispatch_id}/data`).done(dispatchResponse => prepareCustomerReturnModal(dispatchResponse.data, ret, response.data.documents?.documents || [])).fail(xhr => Swal.fire('No se pudo cargar el borrador', crError(xhr), 'error')); }).fail(xhr => Swal.fire('No se pudo abrir el borrador', crError(xhr), 'error'));
}

function customerReturnAction(id) {
    $.get(`${window.customerReturnRoutes.base}/${id}`).done(response => {
        const payload = response.data; const ret = payload.return; const order = ret.customer_purchase_order || {}; const customer = order.customer || {}; const warehouse = ret.warehouse || {};
        const rows = (ret.items || []).map(item => `<tr><td class="text-left">${crEscape(item.article?.billing_name || 'Artículo')}<small class="d-block text-muted">Lote: ${crEscape(item.lot_number_snapshot || '—')}</small></td><td class="text-right font-weight-bold">${crNumber(item.quantity)}</td></tr>`).join('');
        Swal.fire({ icon: 'warning', title: `Confirmar devolución ${ret.return_number}`, width: 720,
            html: `<div class="text-left small"><div class="row mb-2"><div class="col-sm-6"><strong>Cliente:</strong> ${crEscape(customer.business_name || customer.full_name || '—')}</div><div class="col-sm-6"><strong>SAL:</strong> ${crEscape(ret.warehouse_dispatch?.dispatch_number || '—')}</div><div class="col-sm-6"><strong>Almacén:</strong> ${crEscape([warehouse.code, warehouse.name].filter(Boolean).join(' | ') || '—')}</div><div class="col-sm-6"><strong>Fecha devolución:</strong> ${crEscape(ret.return_date_display || crLocalDisplay(ret.return_date_local))}</div><div class="col-12"><strong>Motivo:</strong> ${crEscape(payload.reason_label || ret.reason)}</div></div><div class="table-responsive"><table class="table table-sm mb-2"><thead><tr><th>Artículos / lotes</th><th class="text-right">Cantidad</th></tr></thead><tbody>${rows}</tbody><tfoot><tr><th>Total</th><th class="text-right">${crNumber(payload.total_quantity)}</th></tr></tfoot></table></div><div class="alert alert-warning mb-0"><strong>Advertencia:</strong> Al confirmar se reintegrará físicamente la mercadería al stock y se generará Kardex. La devolución dejará de ser editable.</div></div>`,
            showCancelButton: true, confirmButtonText: 'Confirmar devolución', cancelButtonText: 'Cancelar', confirmButtonColor: '#28a745' }).then(result => {
            if (!result.isConfirmed) return;
            $.post(`${window.customerReturnRoutes.base}/${id}/confirm`).done(confirmResponse => { $('#customerReturnDetailModal').modal('hide'); Swal.fire('Devolución confirmada', confirmResponse.message, 'success'); refreshCustomerReturnUi(confirmResponse.data); }).fail(xhr => Swal.fire('No se pudo confirmar', crError(xhr), 'error'));
        });
    }).fail(xhr => Swal.fire('No se pudo preparar la confirmación', crError(xhr), 'error'));
}

function customerReturnReasonAction(id, action, title, text) {
    Swal.fire({ icon: 'warning', title, text, input: 'textarea', inputLabel: 'Motivo', showCancelButton: true, inputValidator: value => (!value || value.trim().length < 5) ? 'Ingrese al menos 5 caracteres.' : undefined }).then(result => {
        if (!result.isConfirmed) return;
        $.post(`${window.customerReturnRoutes.base}/${id}/${action}`, { reason: result.value }).done(response => { $('#customerReturnDetailModal').modal('hide'); Swal.fire('Operación completada', response.message, 'success'); refreshCustomerReturnUi(response.data); }).fail(xhr => Swal.fire('No se pudo completar', crError(xhr), 'error'));
    });
}

function customerReturnSwalTarget() {
    const openModals = document.querySelectorAll('.modal.show');
    return openModals.length ? openModals[openModals.length - 1] : document.body;
}

function reverseCustomerReturn(id) {
    if (!id || customerReturnReverseOpening || customerReturnReverseSubmitting) return;
    customerReturnReverseOpening = true;

    $.get(`${window.customerReturnRoutes.base}/${id}`).done(response => {
        const payload = response.data;
        const ret = payload.return;
        const order = ret.customer_purchase_order || {};
        const customer = order.customer || {};
        const warehouse = ret.warehouse || {};
        const alertTarget = customerReturnSwalTarget();
        const itemSummary = (ret.items || []).map(item => `${crEscape(item.article?.billing_name || 'Artículo')} · Lote ${crEscape(item.lot_number_snapshot || '—')}`).join('<br>');

        Swal.fire({
            target: alertTarget,
            icon: 'warning',
            title: 'Revertir devolución confirmada',
            width: 680,
            html: `<div class="customer-return-reverse-summary text-left">
                <div><small>DEV</small><strong>${crEscape(ret.return_number)}</strong></div>
                <div><small>SAL</small><strong>${crEscape(ret.warehouse_dispatch?.dispatch_number || '—')}</strong></div>
                <div class="cr-summary-wide"><small>CLIENTE</small><strong>${crEscape(customer.business_name || customer.full_name || '—')}</strong></div>
                <div class="cr-summary-wide"><small>ARTÍCULO / LOTE</small><strong>${itemSummary || '—'}</strong></div>
                <div><small>CANTIDAD</small><strong>${crNumber(payload.total_quantity)}</strong></div>
                <div><small>ALMACÉN</small><strong>${crEscape([warehouse.code, warehouse.name].filter(Boolean).join(' | ') || '—')}</strong></div>
            </div>
            <div class="alert alert-danger text-left mt-3 mb-2"><i class="fas fa-exclamation-circle mr-1"></i> Use esta opción únicamente para dejar sin efecto una devolución que ya fue confirmada e ingresó nuevamente al almacén.</div>
            <div class="alert alert-warning text-left mb-2"><i class="fas fa-exclamation-triangle mr-1"></i> Al continuar, las unidades serán descontadas nuevamente del stock mediante un nuevo movimiento Kardex. La devolución original permanecerá en el historial.</div>
            <div class="alert alert-info text-left mb-1 font-weight-bold"><i class="fas fa-info-circle mr-1"></i> Esta opción NO se utiliza para registrar la devolución de un cliente.</div>`,
            input: 'textarea',
            inputLabel: 'MOTIVO DE LA REVERSA *',
            inputPlaceholder: 'Indique por qué se está dejando sin efecto esta devolución',
            inputAttributes: { maxlength: 2000, rows: 5, 'aria-required': 'true' },
            showCancelButton: true,
            confirmButtonText: 'Revertir devolución',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !Swal.isLoading(),
            allowEscapeKey: () => !Swal.isLoading(),
            buttonsStyling: false,
            customClass: {
                popup: 'customer-return-reverse-alert',
                confirmButton: 'btn btn-danger mx-1',
                cancelButton: 'btn btn-light mx-1',
            },
            didOpen: () => {
                const textarea = Swal.getInput();
                textarea?.removeAttribute('readonly');
                textarea?.removeAttribute('disabled');
                textarea?.focus();
            },
            preConfirm: value => {
                const reason = String(value || '').trim();
                if (!reason) {
                    Swal.showValidationMessage('Debe indicar el motivo de la reversa.');
                    return false;
                }
                if (customerReturnReverseSubmitting) return false;
                customerReturnReverseSubmitting = true;

                return new Promise(resolve => {
                    $.post(`${window.customerReturnRoutes.base}/${id}/reverse`, { reason })
                        .done(reverseResponse => resolve(reverseResponse))
                        .fail(xhr => {
                            Swal.showValidationMessage(crError(xhr));
                            resolve(false);
                        })
                        .always(() => { customerReturnReverseSubmitting = false; });
                });
            },
        }).then(result => {
            if (!result.isConfirmed || !result.value) return;
            refreshCustomerReturnUi(result.value.data);
            Swal.fire({ target: alertTarget, icon: 'success', title: 'Devolución revertida', text: result.value.message, confirmButtonText: 'Aceptar' })
                .then(() => $('#customerReturnDetailModal').modal('hide'));
        });
    }).fail(xhr => Swal.fire('No se pudo preparar la reversa', crError(xhr), 'error'))
        .always(() => { customerReturnReverseOpening = false; });
}

function refreshCustomerReturnUi(customerReturn) {
    customerReturnsTable?.ajax.reload(null, false);
    const orderId = customerReturn?.customer_purchase_order_id || customerReturnContext?.dispatch?.customer_purchase_order_id;
    $(document).trigger('customer-return:changed', [orderId]);
}

function openCustomerReturnDocuments(id, number) {
    customerReturnDocumentsId = id; $('#crDocsNumber').text(number); $('#customerReturnDocumentsModal').modal('show');
    $.get(`${window.customerReturnRoutes.base}/${id}/documents`).done(response => { $('#crDocsList').html(renderCrDocuments(response.data.documents, true)); $('#crDocsType').html(Object.entries(response.data.types || {}).map(([value, label]) => `<option value="${value}">${crEscape(label)}</option>`).join('')); });
}

function renderCrDocuments(documents, deletable) {
    return documents.length ? documents.map(document => `<div class="cr-document"><div><strong>${crEscape(document.type_label)}</strong><small class="d-block text-muted">${crEscape(document.original_name)} · ${crEscape(document.description || 'Sin descripción')}</small></div><div><a class="btn btn-sm btn-outline-primary" target="_blank" href="${crEscape(document.view_url)}"><i class="fas fa-eye"></i></a>${deletable ? `<button class="btn btn-sm btn-outline-danger deleteCustomerReturnDocument" data-id="${document.id}"><i class="fas fa-trash"></i></button>` : ''}</div></div>`).join('') : '<p class="text-muted">Sin documentos adjuntos.</p>';
}

function submitCustomerReturnDocument(event) {
    event.preventDefault(); const form = new FormData(); const file = $('#crDocsFile')[0].files[0]; form.append('documents[0][type]', $('#crDocsType').val()); form.append('documents[0][description]', $('#crDocsDescription').val()); form.append('documents[0][file]', file);
    $.ajax({ url: `${window.customerReturnRoutes.base}/${customerReturnDocumentsId}/documents`, method: 'POST', data: form, processData: false, contentType: false }).done(response => { $('#crDocsList').html(renderCrDocuments(response.data.documents, true)); $('#customerReturnDocumentsForm')[0].reset(); }).fail(xhr => Swal.fire('No se pudo adjuntar', crError(xhr), 'error'));
}

function deleteCustomerReturnDocument(documentId) {
    $.ajax({ url: `${window.customerReturnRoutes.base}/${customerReturnDocumentsId}/documents/${documentId}`, method: 'DELETE' }).done(response => $('#crDocsList').html(renderCrDocuments(response.data.documents, true))).fail(xhr => Swal.fire('No se pudo retirar', crError(xhr), 'error'));
}
