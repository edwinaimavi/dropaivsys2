let spoTrackingOrderId = null;
const spoMainFlow = ['registered','sent_to_supplier','supplier_confirmed','preparing','delivered_to_carrier','in_transit','arrived_destination','received_office','received_warehouse'];

document.addEventListener('DOMContentLoaded', () => {
    $(document).on('click', '.trackingSupplierPurchaseOrder', function () {
        openSpoTracking($(this).data('id'));
    });
    $(document).on('submit', '#supplierPurchaseOrderTrackingForm', saveSpoTracking);
    $(document).on('click', '.spo-delete-event', function () {
        deleteSpoTracking($(this).data('id'));
    });
    $('#supplierPurchaseOrderTrackingModal').on('hidden.bs.modal', resetSpoTrackingForm);
});

function openSpoTracking(orderId) {
    spoTrackingOrderId = orderId;
    $('#spo_tracking_order_id').val(orderId);
    $('#spoTrackingTimeline').empty();
    $('#spoTrackingLoading').show();
    $('#supplierPurchaseOrderTrackingModal').modal('show');
    loadSpoTrackings();
}

function loadSpoTrackings() {
    if (!spoTrackingOrderId) return;
    $.get(`${window.routes.supplierPurchaseOrderTrackings}/${spoTrackingOrderId}/trackings`)
        .done((response) => renderSpoTracking(response.data))
        .fail(() => {
            $('#spoTrackingLoading').hide();
            $('#spoTrackingTimeline').html('<div class="alert alert-danger">No se pudo cargar el seguimiento log&iacute;stico.</div>');
        });
}

function renderSpoTracking(data) {
    $('#spoTrackingLoading').hide();
    $('#spoTrackingOrderCode').text(data.order.code || '-');
    $('#spoTrackingSupplier').text(data.order.supplier || '-');
    $('#spoTrackingCreatedAt').text(data.order.created_at || '-');
    const currentLabel = data.current_status ? data.statuses[data.current_status] : 'Sin seguimiento';
    $('#spoTrackingCurrentStatus').html(currentLabel || 'Sin seguimiento');
    $('#spoWarehouseSuggestion').toggleClass('d-none', data.current_status !== 'received_warehouse');
    populateSpoStatuses(data.statuses);

    const eventsByStatus = {};
    data.trackings.forEach((event) => { (eventsByStatus[event.status] ||= []).push(event); });
    const currentMainIndex = spoMainFlow.indexOf(data.current_status);
    let html = '';

    spoMainFlow.forEach((status, index) => {
        const events = eventsByStatus[status] || [];
        if (events.length) {
            events.forEach((event, eventIndex) => {
                const isCurrent = status === data.current_status && eventIndex === events.length - 1;
                html += spoEventHtml(event, isCurrent ? 'current' : 'completed');
            });
        } else {
            const completed = currentMainIndex >= 0 && index < currentMainIndex;
            html += `<div class="spo-tracking-item ${completed ? 'completed' : 'pending'}"><span class="spo-tracking-dot"><i class="fas ${completed ? 'fa-check' : 'fa-circle'}"></i></span><div class="spo-tracking-item-head"><strong>${data.statuses[status]}</strong><time>${completed ? 'Etapa superada' : 'Pendiente'}</time></div></div>`;
        }
    });

    data.trackings.filter((event) => ['observed', 'cancelled'].includes(event.status))
        .forEach((event) => { html += spoEventHtml(event, event.status); });
    $('#spoTrackingTimeline').html(html || '<div class="text-muted text-center py-5">Sin seguimiento registrado.</div>');
}

function spoEventHtml(event, state) {
    const meta = [];
    if (event.location) meta.push(`<span><i class="fas fa-map-marker-alt"></i> ${spoEscape(event.location)}</span>`);
    if (event.carrier_name) meta.push(`<span><i class="fas fa-truck"></i> ${spoEscape(event.carrier_name)}</span>`);
    if (event.tracking_number) meta.push(`<span><i class="fas fa-barcode"></i> ${spoEscape(event.tracking_number)}</span>`);
    if (event.estimated_date_label) meta.push(`<span><i class="far fa-calendar"></i> Est. ${spoEscape(event.estimated_date_label)}</span>`);
    if (event.document_url) meta.push(`<a href="${spoEscape(event.document_url)}" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> ${spoEscape(event.document_name || 'Adjunto')}</a>`);
    const remove = window.spoTrackingPermissions?.destroy
        ? `<button type="button" class="spo-delete-event" data-id="${event.id}" title="Eliminar evento"><i class="fas fa-trash-alt"></i></button>` : '';
    return `<div class="spo-tracking-item ${state}"><span class="spo-tracking-dot"><i class="fas fa-check"></i></span><div class="spo-tracking-item-head"><strong>${spoEscape(event.title)}</strong><div><time>${spoEscape(event.event_date_label || '-')}</time>${remove}</div></div>${event.description ? `<p>${spoEscape(event.description)}</p>` : ''}${meta.length ? `<div class="spo-tracking-meta">${meta.join('')}</div>` : ''}</div>`;
}

function populateSpoStatuses(statuses) {
    const select = $('#spo_tracking_status');
    if (select.children().length) return;
    select.append('<option value="">Seleccione estado</option>');
    Object.entries(statuses).forEach(([value, label]) => select.append(new Option(spoDecode(label), value)));
}

function saveSpoTracking(event) {
    event.preventDefault();
    clearSpoTrackingErrors();
    const form = event.currentTarget;
    const button = $(form).find('button[type="submit"]');
    const original = button.html();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');
    $.ajax({
        url: `${window.routes.supplierPurchaseOrderTrackings}/${spoTrackingOrderId}/trackings`,
        method: 'POST', data: new FormData(form), processData: false, contentType: false
    }).done((response) => {
        resetSpoTrackingForm();
        loadSpoTrackings();
        if (typeof Swal !== 'undefined') Swal.fire({icon:'success',title:'Seguimiento actualizado',text:spoDecode(response.message),timer:1800,showConfirmButton:false});
    }).fail((xhr) => {
        if (xhr.status === 422) showSpoTrackingErrors(xhr.responseJSON.errors || {});
        else if (typeof Swal !== 'undefined') Swal.fire('Error', 'No se pudo guardar el seguimiento.', 'error');
    }).always(() => button.prop('disabled', false).html(original));
}

function deleteSpoTracking(id) {
    const execute = () => $.ajax({url:`${window.routes.supplierPurchaseOrderTrackingEvents}/${id}`,method:'DELETE'})
        .done((response) => { loadSpoTrackings(); if (typeof Swal !== 'undefined') Swal.fire({icon:'success',title:spoDecode(response.message),timer:1400,showConfirmButton:false}); })
        .fail(() => typeof Swal !== 'undefined' && Swal.fire('Error','No se pudo eliminar el evento.','error'));
    if (typeof Swal === 'undefined') return execute();
    Swal.fire({title:'¿Eliminar este evento?',text:'La información logística dejará de mostrarse.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar'}).then((result) => result.isConfirmed && execute());
}

function resetSpoTrackingForm() {
    const form = document.getElementById('supplierPurchaseOrderTrackingForm');
    if (!form) return;
    form.reset(); clearSpoTrackingErrors();
    const now = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0,16);
    $('#spo_tracking_event_date').val(now);
}
function clearSpoTrackingErrors(){ $('#supplierPurchaseOrderTrackingForm .is-invalid').removeClass('is-invalid'); $('#supplierPurchaseOrderTrackingForm .invalid-feedback').text(''); }
function showSpoTrackingErrors(errors){ Object.entries(errors).forEach(([field,messages])=>{ const input=$(`#supplierPurchaseOrderTrackingForm [name="${field}"]`); input.addClass('is-invalid'); $(`#supplierPurchaseOrderTrackingForm [data-error="${field}"]`).text(messages[0]).show(); }); }
function spoEscape(value){ return $('<div>').text(value ?? '').html(); }
function spoDecode(value){ return $('<textarea>').html(value ?? '').text(); }
