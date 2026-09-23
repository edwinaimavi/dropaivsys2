import '../../css/electronic-invoice-detail.css';
import '../../css/electronic-invoice-collection.css';

import {
    escapeElectronicInvoiceHtml,
    formatElectronicInvoiceDisplayDate,
    formatElectronicInvoiceMoney,
    initElectronicInvoiceForm,
    loadElectronicInvoiceForEdit,
    openElectronicInvoiceFromCustomerOrder,
    openNewElectronicInvoice
} from './electronic-invoice-form';

let tableElectronicInvoice = null;

$(function () {
    initElectronicInvoiceForm({
        sourceContext: 'electronic_invoices',
        onSaved: reloadElectronicInvoiceTable
    });
    initElectronicInvoiceTable();

    if ($('#electronicInvoiceCollectionModal').length) {
        $('#electronicInvoiceCollectionModal').modal({ backdrop: 'static', keyboard: false, show: false });
        $('#electronicInvoiceCollectionModal select').select2({
            width: '100%',
            dropdownParent: $('#electronicInvoiceCollectionModal')
        });
    }

    $('#btnCreateElectronicInvoice').off('click.electronicInvoicePage').on('click.electronicInvoicePage', openNewElectronicInvoice);
    $('#electronicInvoiceCollectionForm').off('submit.electronicInvoicePage').on('submit.electronicInvoicePage', function (event) {
        event.preventDefault();
        saveElectronicInvoiceCollection(this);
    });

    $(document).off('.electronicInvoicePage')
        .on('change.electronicInvoicePage', '#eic_company_bank_account_id', syncElectronicInvoiceCollectionAccount)
        .on('change.electronicInvoicePage', '#eic_currency_id', toggleElectronicInvoiceCollectionExchangeRate)
        .on('change.electronicInvoicePage', '#eic_proof', function () {
            updateElectronicInvoiceCollectionProof(this.files?.[0]);
        })
        .on('click.electronicInvoicePage', '#eic_proof_remove', resetElectronicInvoiceCollectionProof)
        .on('click.electronicInvoicePage', '.viewElectronicInvoice', function () { loadElectronicInvoiceDetail($(this).data('id')); })
        .on('click.electronicInvoicePage', '.editElectronicInvoice', function () { loadElectronicInvoiceForEdit($(this).data('id')); })
        .on('click.electronicInvoicePage', '.deleteElectronicInvoice', function () { deleteElectronicInvoice($(this).data('id')); })
        .on('click.electronicInvoicePage', '.previewElectronicInvoicePayload', function () { previewElectronicInvoicePayload($(this).data('id')); })
        .on('click.electronicInvoicePage', '.sendElectronicInvoiceToApi', function () { sendElectronicInvoiceToApi($(this).data('id')); })
        .on('click.electronicInvoicePage', '.apiNotConfiguredElectronicInvoice', function () {
            Swal.fire('API no configurada', 'Configura APIs Perú antes de enviar a SUNAT.', 'info');
        })
        .on('click.electronicInvoicePage', '.collectElectronicInvoice', function () { openElectronicInvoiceCollection($(this).data('id')); })
        .on('click.electronicInvoicePage', '.disabledElectronicInvoiceApiAction', function () {
            Swal.fire({
                icon: 'info',
                title: 'Disponible cuando se integre APIs Perú.',
                timer: 2200,
                showConfirmButton: false
            });
        });

    if (window.electronicInvoiceInitialCustomerOrderId) {
        openElectronicInvoiceFromCustomerOrder(window.electronicInvoiceInitialCustomerOrderId);
    } else if (window.electronicInvoiceInitialCollectionInvoiceId) {
        openElectronicInvoiceCollection(window.electronicInvoiceInitialCollectionInvoiceId);
    }
});

function initElectronicInvoiceTable() {
    if (!$('#tableElectronicInvoice').length) return;
    tableElectronicInvoice = $('#tableElectronicInvoice').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: {
            url: window.routes.electronicInvoiceList,
            data: function (data) {
                data.customer_purchase_order_id = window.electronicInvoiceOrderFilterId || '';
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id', visible: false },
            { data: 'type_label', name: 'document_type' },
            { data: 'full_number', name: 'full_number' },
            { data: 'customer_name', name: 'client_name' },
            { data: 'customer_document', name: 'client_document_number' },
            { data: 'currency_code', name: 'currency_code' },
            { data: 'total_amount', name: 'total_amount' },
            { data: 'pending_amount_label', name: 'pending_amount' },
            { data: 'payment_status_label', name: 'payment_status' },
            { data: 'sunat_status', name: 'sunat_status' },
            { data: 'status', name: 'status' },
            { data: 'issue_date', name: 'issue_date' },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        order: [[1, 'desc']],
        language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
    });
}

function reloadElectronicInvoiceTable() {
    if (tableElectronicInvoice?.ajax) tableElectronicInvoice.ajax.reload(null, false);
}

function loadElectronicInvoiceDetail(id) {
    $.get(`${window.routes.electronicInvoiceShow}/${id}`)
        .done(function (response) {
            fillElectronicInvoiceDetail(response.data);
            $('#viewElectronicInvoiceModal').modal('show');
        });
}

function fillElectronicInvoiceDetail(invoice) {
    const dispatchContext = invoice.dispatch_context || {};
    const status = electronicInvoiceStatusPresentation(invoice.status);
    const sunatStatus = electronicInvoiceSunatStatusPresentation(invoice.sunat_status);
    const paymentStatus = electronicInvoicePaymentStatusPresentation(invoice);
    const typeLabel = invoice.document_type === '03' ? 'Boleta de Venta Electrónica' : 'Factura Electrónica';
    $('#vei_full_number').text(invoice.full_number || '-');
    $('#vei_document_type').text(typeLabel);
    const currencyCode = invoice.currency_code || '';
    const totalLabel = `${currencyCode} ${formatElectronicInvoiceMoney(invoice.total_amount)}`.trim();
    const paidLabel = `${currencyCode} ${formatElectronicInvoiceMoney(invoice.paid_amount)}`.trim();
    const pendingLabel = `${currencyCode} ${formatElectronicInvoiceMoney(invoice.pending_amount)}`.trim();
    applyElectronicInvoiceStatus($('#vei_status'), status);
    $('#vei_client_name').text(invoice.client_name || '-');
    $('#vei_total_amount, #vei_kpi_total').text(totalLabel);
    $('#vei_company').text(invoice.company_business_name || '-');
    $('#vei_company_ruc').text(invoice.company_ruc || '-');
    $('#vei_client_document').text(invoice.client_document_number || '-');
    $('#vei_issue_date').text(formatElectronicInvoiceDisplayDate(invoice.issue_date));
    $('#vei_currency').text(invoice.currency_code || '-');
    $('#vei_payment_type').text(invoice.payment_type || '-');
    $('#vei_purchase_order').text([
        invoice.customer_purchase_order?.code,
        invoice.purchase_order_number
    ].filter(Boolean).join(' | ') || '-');
    applyElectronicInvoiceStatus($('#vei_sunat_status'), sunatStatus);
    $('#vei_observations').text(invoice.observations || '-');
    $('#vei_taxable_amount').text(formatElectronicInvoiceMoney(invoice.taxable_amount));
    $('#vei_exonerated_amount').text(formatElectronicInvoiceMoney(invoice.exonerated_amount));
    $('#vei_unaffected_amount').text(formatElectronicInvoiceMoney(invoice.unaffected_amount));
    $('#vei_igv_amount').text(formatElectronicInvoiceMoney(invoice.igv_amount));
    $('#vei_total_footer').text(totalLabel);
    $('#vei_paid_amount').text(paidLabel);
    $('#vei_pending_amount').text(pendingLabel);
    applyElectronicInvoiceStatus($('#vei_payment_status, #vei_kpi_payment_status'), paymentStatus);
    applyElectronicInvoiceStatus($('#vei_dispatch'), {
        label: dispatchContext.dispatch_label || '-',
        tone: dispatchContext.dispatch_backed ? 'primary' : 'neutral'
    });
    $('#vei_warehouse').text(dispatchContext.warehouse_label
        || [invoice.warehouse?.code, invoice.warehouse?.name].filter(Boolean).join(' | ')
        || '-');
    $('#vei_warehouse_note')
        .text(dispatchContext.dispatch_backed ? 'Según despacho confirmado' : '')
        .toggleClass('d-none', !dispatchContext.dispatch_backed);

    const rows = (invoice.items || []).map((item, index) => `
        <tr>
            <td class="text-center text-muted">${index + 1}</td>
            <td><span class="electronic-invoice-product-code">${escapeElectronicInvoiceHtml(item.product_code || '-')}</span></td>
            <td><strong class="electronic-invoice-product-description">${escapeElectronicInvoiceHtml(item.description || '-')}</strong></td>
            <td><span class="electronic-invoice-lot-tag">${escapeElectronicInvoiceHtml(item.lot_number || '-')}</span></td>
            <td><span class="electronic-invoice-expiration">${formatElectronicInvoiceDisplayDate(item.expiration_date)}</span></td>
            <td class="text-right electronic-invoice-number-cell">${formatElectronicInvoiceMoney(item.quantity)}</td>
            <td class="text-right electronic-invoice-number-cell">${formatElectronicInvoiceMoney(item.unit_price)}</td>
            <td class="text-right electronic-invoice-number-cell">${formatElectronicInvoiceMoney(item.igv_amount)}</td>
            <td class="text-right electronic-invoice-number-cell electronic-invoice-number-cell--total">${formatElectronicInvoiceMoney(item.line_total)}</td>
        </tr>`).join('');
    $('#vei_items_body').html(rows || electronicInvoiceEmptyState(9, 'fa-box-open', 'No hay productos registrados.', 'El comprobante no contiene ítems para mostrar.'));

    const collections = (invoice.collections || []).map(collection => `
        <tr>
            <td><span class="electronic-invoice-expiration">${formatElectronicInvoiceDisplayDate(collection.collection_date)}</span></td>
            <td>${escapeElectronicInvoiceHtml([collection.account?.bank?.short_name || collection.account?.bank?.description || '-', collection.account?.account_number || ''].filter(Boolean).join(' - '))}</td>
            <td>${escapeElectronicInvoiceHtml(collection.operation_number || '-')}</td>
            <td class="text-right electronic-invoice-number-cell electronic-invoice-number-cell--total">${escapeElectronicInvoiceHtml(collection.currency?.code || '')} ${formatElectronicInvoiceMoney(collection.amount)}</td>
            <td>${escapeElectronicInvoiceHtml([collection.creator?.name, collection.creator?.lastname].filter(Boolean).join(' ') || '-')}</td>
            <td class="text-center">${collection.proof_url ? `<a href="${escapeElectronicInvoiceHtml(collection.proof_url)}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary" title="Ver constancia" aria-label="Ver constancia"><i class="fas fa-paperclip"></i></a>` : '<span class="text-muted">-</span>'}</td>
        </tr>`).join('');
    $('#vei_collections_body').html(collections || electronicInvoiceEmptyState(6, 'fa-wallet', 'Aún no hay cobros confirmados para este comprobante.', 'Los pagos confirmados aparecerán en este historial.'));
}

function electronicInvoiceStatusPresentation(status) {
    const statuses = {
        draft: { label: 'Borrador', tone: 'neutral' },
        generated: { label: 'Generado', tone: 'primary' },
        sent: { label: 'Enviado', tone: 'info' },
        accepted: { label: 'Aceptado SUNAT', tone: 'success' },
        observed: { label: 'Observado', tone: 'warning' },
        rejected: { label: 'Rechazado', tone: 'danger' },
        voided: { label: 'Anulado', tone: 'danger' },
        cancelled: { label: 'Anulado', tone: 'danger' },
        error: { label: 'Error', tone: 'danger' }
    };

    return statuses[status] || { label: status || 'Sin estado', tone: 'neutral' };
}

function electronicInvoiceSunatStatusPresentation(status) {
    const statuses = {
        not_configured: { label: 'API no configurada', tone: 'neutral' },
        pending_send: { label: 'Pendiente de envío SUNAT', tone: 'warning' },
        sent: { label: 'Enviado', tone: 'info' },
        accepted: { label: 'Aceptado SUNAT', tone: 'success' },
        rejected: { label: 'Rechazado SUNAT', tone: 'danger' },
        error: { label: 'Error de API', tone: 'danger' }
    };

    return statuses[status] || { label: 'No enviado', tone: 'neutral' };
}

function electronicInvoicePaymentStatusPresentation(invoice) {
    if (invoice.status === 'draft') return { label: 'Borrador', tone: 'neutral' };
    if (invoice.status === 'cancelled' || invoice.status === 'voided' || invoice.is_voided) {
        return { label: 'Anulada', tone: 'danger' };
    }
    if (invoice.payment_status === 'paid') return { label: 'Cobrada', tone: 'success' };
    if (invoice.payment_status === 'partial') return { label: 'Cobro parcial', tone: 'info' };
    if (invoice.payment_status === 'overdue') return { label: 'Vencida', tone: 'danger' };
    return { label: 'Pendiente de cobro', tone: 'warning' };
}

function applyElectronicInvoiceStatus(element, presentation) {
    element
        .text(presentation.label)
        .attr('class', `electronic-invoice-status electronic-invoice-status--${presentation.tone}`);
}

function electronicInvoiceEmptyState(colspan, icon, title, description) {
    return `
        <tr class="electronic-invoice-empty-state">
            <td colspan="${colspan}">
                <div class="electronic-invoice-empty-state__content">
                    <span class="electronic-invoice-empty-state__icon"><i class="fas ${icon}"></i></span>
                    <strong>${title}</strong>
                    <span>${description}</span>
                </div>
            </td>
        </tr>`;
}

function openElectronicInvoiceCollection(invoiceId) {
    $.get(`${window.routes.electronicInvoiceShow}/${invoiceId}`)
        .done(function (response) {
            const invoice = response.data;
            if ((parseFloat(invoice.pending_amount) || 0) <= 0) {
                return Swal.fire('Factura cobrada', 'El comprobante ya no tiene saldo pendiente.', 'info');
            }
            const form = $('#electronicInvoiceCollectionForm')[0];
            form.reset();
            resetElectronicInvoiceCollectionProof();
            $('#electronicInvoiceCollectionErrors').addClass('d-none').empty();
            $('#eic_invoice_id').val(invoice.id);
            $('#eic_idempotency_key').val(generateElectronicInvoiceCollectionKey());
            $('#eic_invoice_number').text(invoice.full_number || '-');
            $('#eic_invoice_total').text(`${invoice.currency_code || ''} ${formatElectronicInvoiceMoney(invoice.total_amount)}`);
            $('#eic_invoice_pending').text(`${invoice.currency_code || ''} ${formatElectronicInvoiceMoney(invoice.pending_amount)}`);
            $('#eic_collection_date').val(new Date().toISOString().slice(0, 10));
            $('#eic_currency_id').val(String(invoice.currency_id || '')).trigger('change.select2');
            $('#eic_amount').val(formatElectronicInvoiceCollectionAmount(invoice.pending_amount));
            $('#eic_company_bank_account_id option').each(function () {
                const available = !this.value || String($(this).data('company-id')) === String(invoice.company_id);
                $(this).prop('disabled', !available).toggle(available);
            });
            $('#eic_company_bank_account_id').val('').trigger('change.select2');
            $('#electronicInvoiceCollectionModal').data('invoice-currency-code', invoice.currency_code || 'PEN').modal('show');
            toggleElectronicInvoiceCollectionExchangeRate();
        });
}

function syncElectronicInvoiceCollectionAccount() {
    const option = $('#eic_company_bank_account_id option:selected');
    if (option.val()) $('#eic_currency_id').val(String(option.data('currency-id'))).trigger('change.select2');
    toggleElectronicInvoiceCollectionExchangeRate();
}

function toggleElectronicInvoiceCollectionExchangeRate() {
    const invoiceCurrency = String($('#electronicInvoiceCollectionModal').data('invoice-currency-code') || '').toUpperCase();
    const collectionCurrency = String($('#eic_currency_id option:selected').data('code') || '').toUpperCase();
    const differs = Boolean(invoiceCurrency && collectionCurrency && invoiceCurrency !== collectionCurrency);
    $('#eic_exchange_rate_group').toggleClass('d-none', !differs);
    $('#eic_exchange_rate').prop('required', differs).val(differs ? $('#eic_exchange_rate').val() : '');
}

function updateElectronicInvoiceCollectionProof(file) {
    if (!file) {
        resetElectronicInvoiceCollectionProof();
        return;
    }

    $('#eic_proof_uploader').addClass('electronic-invoice-file-uploader--selected');
    $('#eic_proof_name').text(file.name);
    $('#eic_proof_help').text(`${electronicInvoiceFileSizeLabel(file.size)} · Archivo listo para adjuntar`);
    $('#eic_proof_action').html('<i class="fas fa-sync-alt mr-1"></i> Reemplazar');
    $('#eic_proof_remove').removeClass('d-none');
}

function resetElectronicInvoiceCollectionProof() {
    $('#eic_proof').val('');
    $('#eic_proof_uploader').removeClass('electronic-invoice-file-uploader--selected');
    $('#eic_proof_name').text('Adjuntar constancia');
    $('#eic_proof_help').text('PDF, JPG, PNG o WEBP · Máximo 10 MB');
    $('#eic_proof_action').html('<i class="fas fa-paperclip mr-1"></i> Seleccionar');
    $('#eic_proof_remove').addClass('d-none');
}

function electronicInvoiceFileSizeLabel(bytes) {
    const size = Number(bytes) || 0;
    if (size < 1024) return `${size} B`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

function saveElectronicInvoiceCollection(form) {
    const invoiceId = $('#eic_invoice_id').val();
    const button = $('#btnConfirmElectronicInvoiceCollection').prop('disabled', true);
    $.ajax({
        url: `${window.routes.electronicInvoiceCollections}/${invoiceId}/collections`,
        type: 'POST',
        data: new FormData(form),
        processData: false,
        contentType: false
    }).done(function (response) {
        $('#electronicInvoiceCollectionModal').modal('hide');
        reloadElectronicInvoiceTable();
        Swal.fire({ icon: 'success', title: response.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
    }).fail(function (xhr) {
        const errors = xhr.responseJSON?.errors || {};
        const messages = Object.values(errors).flat();
        $('#electronicInvoiceCollectionErrors').toggleClass('d-none', !messages.length)
            .html(messages.length ? `<ul class="mb-0">${messages.map(message => `<li>${escapeElectronicInvoiceHtml(message)}</li>`).join('')}</ul>` : '');
        if (!messages.length) Swal.fire('No se pudo confirmar el cobro', xhr.responseJSON?.message || 'Revise los datos.', 'error');
    }).always(function () {
        button.prop('disabled', false);
    });
}

function generateElectronicInvoiceCollectionKey() {
    return window.crypto?.randomUUID
        ? window.crypto.randomUUID()
        : `invoice-collection-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

function formatElectronicInvoiceCollectionAmount(value) {
    return (parseFloat(value) || 0).toFixed(2);
}

function previewElectronicInvoicePayload(id) {
    $.get(`${window.routes.electronicInvoicePayload}/${id}/payload`)
        .done(function (response) {
            Swal.fire({
                title: 'Payload JSON preliminar',
                html: `<pre class="text-left bg-light p-3" style="max-height:420px;overflow:auto;font-size:11px;">${escapeElectronicInvoiceHtml(JSON.stringify(response.data, null, 2))}</pre>`,
                width: 900,
                confirmButtonText: 'Cerrar'
            });
        });
}

function sendElectronicInvoiceToApi(id) {
    $.post(`${window.routes.electronicInvoiceSend}/${id}/send`)
        .done(function (response) {
            reloadElectronicInvoiceTable();
            Swal.fire({ icon: 'info', title: response.message, confirmButtonText: 'Entendido' });
        })
        .fail(function (xhr) {
            Swal.fire('No se pudo preparar el envío', xhr.responseJSON?.message || 'Revise la configuración electrónica.', 'warning');
        });
}

function deleteElectronicInvoice(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Cancelar comprobante',
        input: 'textarea',
        inputLabel: 'Motivo de cancelación',
        inputPlaceholder: 'Explique por qué debe cancelarse el comprobante...',
        inputAttributes: { maxlength: 1000 },
        inputValidator: function (value) {
            if (!String(value || '').trim()) return 'Debe ingresar el motivo de cancelación del comprobante.';
        },
        text: 'Se cancelará internamente. No representa baja SUNAT.',
        showCancelButton: true,
        confirmButtonText: 'Sí, cancelar',
        cancelButtonText: 'Volver',
        confirmButtonColor: '#d33'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: `${window.routes.electronicInvoiceDelete}/${id}`,
            type: 'POST',
            data: {
                _method: 'DELETE',
                _token: $('meta[name="csrf-token"]').attr('content'),
                reason: String(result.value || '').trim()
            }
        }).done(function (response) {
            reloadElectronicInvoiceTable();
            Swal.fire({ icon: 'success', title: response.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2600 });
        }).fail(function (xhr) {
            const errors = xhr.responseJSON?.errors || {};
            const message = Object.values(errors).flat()[0]
                || xhr.responseJSON?.message
                || 'No se pudo cancelar el comprobante.';
            Swal.fire('No se pudo cancelar', message, 'error');
        });
    });
}
