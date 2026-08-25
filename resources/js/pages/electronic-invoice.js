let tableElectronicInvoice = null;
let electronicInvoiceItemIndex = 0;
let electronicInvoicePaymentIndex = 0;

$(function () {
    $('#electronicInvoiceModal').modal({ backdrop: 'static', keyboard: false, show: false });
    initElectronicInvoiceSelect2();
    initElectronicInvoiceTable();
    $('#electronicInvoiceCollectionModal').modal({ backdrop: 'static', keyboard: false, show: false });
    $('#electronicInvoiceCollectionModal select').select2({ width: '100%', dropdownParent: $('#electronicInvoiceCollectionModal') });

    $('#btnCreateElectronicInvoice').on('click', function () {
        resetElectronicInvoiceForm();
        $('#electronicInvoiceModalLabel').text('Nuevo Comprobante');
        $('#electronicInvoiceModal').modal('show');
    });

    $('#electronicInvoiceForm').on('submit', function (event) {
        event.preventDefault();
        const status = event.originalEvent?.submitter?.dataset?.status || $('#ei_requested_status').val() || 'draft';
        $('#ei_requested_status').val(status);
        saveElectronicInvoice(this);
    });
    $('#electronicInvoiceCollectionForm').on('submit', function (event) {
        event.preventDefault();
        saveElectronicInvoiceCollection(this);
    });

    $('#btnAddElectronicInvoiceItem').on('click', function () {
        addElectronicInvoiceItemRow();
    });

    $('#btnAddElectronicInvoicePayment').on('click', function () {
        addElectronicInvoicePaymentRow();
    });

    $(document).on('change', '#ei_company_id, #ei_document_type', filterElectronicInvoiceSeries);
    $(document).on('change', '#ei_serie_id', updateElectronicInvoiceCorrelative);
    $(document).on('change input', '#ei_issue_date, #ei_currency_id, #ei_correlativo_preview', updateElectronicInvoiceSummary);
    $(document).on('change', '#ei_customer_id', applyElectronicInvoiceCustomer);
    $(document).on('change', '#ei_customer_branch_id', applyElectronicInvoiceBranch);
    $(document).on('change', '#ei_customer_purchase_order_id', applyElectronicInvoiceOrigin);
    $(document).on('change', '#ei_payment_type', toggleElectronicInvoicePayments);
    $(document).on('change', '#eic_company_bank_account_id', syncElectronicInvoiceCollectionAccount);
    $(document).on('change', '#eic_currency_id', toggleElectronicInvoiceCollectionExchangeRate);
    $(document).on('change', '.item-article', applyElectronicInvoiceArticle);
    $(document).on('input change', '.item-quantity, .item-price, .item-tax-affectation', calculateElectronicInvoiceTotals);
    $(document).on('click', '.removeElectronicInvoiceItem', function () {
        $(this).closest('tr').remove();
        calculateElectronicInvoiceTotals();
    });
    $(document).on('click', '.removeElectronicInvoicePayment', function () {
        $(this).closest('.electronic-invoice-payment-row').remove();
    });
    $(document).on('click', '.viewElectronicInvoice', function () {
        loadElectronicInvoiceDetail($(this).data('id'));
    });
    $(document).on('click', '.editElectronicInvoice', function () {
        loadElectronicInvoiceForEdit($(this).data('id'));
    });
    $(document).on('click', '.deleteElectronicInvoice', function () {
        deleteElectronicInvoice($(this).data('id'));
    });
    $(document).on('click', '.previewElectronicInvoicePayload', function () {
        previewElectronicInvoicePayload($(this).data('id'));
    });
    $(document).on('click', '.sendElectronicInvoiceToApi', function () {
        sendElectronicInvoiceToApi($(this).data('id'));
    });
    $(document).on('click', '.apiNotConfiguredElectronicInvoice', function () {
        Swal.fire('API no configurada', 'Configura APIs Perú antes de enviar a SUNAT.', 'info');
    });
    $(document).on('click', '.collectElectronicInvoice', function () {
        openElectronicInvoiceCollection($(this).data('id'));
    });
    $(document).on('click', '.disabledElectronicInvoiceApiAction', function () {
        Swal.fire({
            icon: 'info',
            title: 'Disponible cuando se integre APIs Peru.',
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

function initElectronicInvoiceSelect2() {
    $('#electronicInvoiceModal select').select2({
        width: '100%',
        dropdownParent: $('#electronicInvoiceModal')
    });
}

function initElectronicInvoiceTable() {
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
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
        }
    });
}

function resetElectronicInvoiceForm() {
    const form = $('#electronicInvoiceForm')[0];
    form.reset();
    $('#electronic_invoice_id').val('');
    $('#ei_requested_status').val('draft');
    $('#btnSaveElectronicInvoiceDraft').show();
    $('#electronicInvoiceErrors').addClass('d-none').empty();
    $('#electronicInvoiceForm .is-invalid').removeClass('is-invalid');
    $('#electronicInvoiceForm .invalid-feedback').text('');
    $('#ei_issue_date').val(new Date().toISOString().slice(0, 10));
    $('#ei_document_type').val('01').trigger('change.select2');
    $('#ei_payment_type').val('Contado').trigger('change.select2');
    const defaultWarehouse = $('#ei_warehouse_id option[value]').filter(function () { return this.value; }).first();
    $('#ei_warehouse_id').val(defaultWarehouse.val() || '').trigger('change.select2');
    $('#electronicInvoiceItemsTbody').empty();
    $('#electronicInvoicePaymentsList').empty();
    electronicInvoiceItemIndex = 0;
    electronicInvoicePaymentIndex = 0;
    addElectronicInvoiceItemRow();
    filterElectronicInvoiceSeries();
    toggleElectronicInvoicePayments();
    calculateElectronicInvoiceTotals();
    updateElectronicInvoiceSummary();
}

function filterElectronicInvoiceSeries(event) {
    const companyId = $('#ei_company_id').val();
    const documentType = $('#ei_document_type').val();
    const environment = window.electronicInvoiceCompanyEnvironments?.[String(companyId)];

    if (companyId && !environment) {
        $('#ei_serie_id').val('').trigger('change.select2');
        if (event?.target?.id === 'ei_company_id') {
            Swal.fire({ icon: 'warning', title: 'Configuración requerida', text: 'La empresa seleccionada no tiene configuración electrónica activa.' });
        }
    }

    $('#ei_serie_id option').each(function () {
        const option = $(this);
        const visible = !option.val()
            || (String(option.data('company-id')) === String(companyId)
                && String(option.data('document-type')) === String(documentType)
                && String(option.data('environment')) === String(environment));
        option.prop('disabled', !visible).toggle(visible);
    });

    const selected = $('#ei_serie_id option:selected');
    if (selected.prop('disabled')) {
        $('#ei_serie_id').val('').trigger('change.select2');
    }
    if (!$('#ei_serie_id').val()) {
        const defaults = $('#ei_serie_id option').filter(function () {
            return this.value && !this.disabled && String($(this).data('is-default')) === '1';
        });
        const internalDefault = defaults.filter(function () {
            return $(this).data('environment') === 'internal';
        }).first();
        const preferred = internalDefault.length ? internalDefault : defaults.first();
        if (preferred.length) {
            $('#ei_serie_id').val(preferred.val()).trigger('change.select2');
        }
    }
    updateElectronicInvoiceCorrelative();
    updateElectronicInvoiceSummary();
}

function updateElectronicInvoiceCorrelative() {
    const option = $('#ei_serie_id option:selected');
    $('#ei_correlativo_preview').val(option.val()
        ? `${option.data('serie')}-${option.data('next-number')}`
        : '');
    updateElectronicInvoiceSummary();
}

function applyElectronicInvoiceCustomer() {
    const option = $('#ei_customer_id option:selected');
    $('#ei_client_document').val(option.data('document-number') || '');
    $('#ei_client_name').val(option.data('name') || '');
    $('#ei_client_email').val(option.data('email') || '');
    $('#ei_client_address').val(option.data('address') || '');
    $('#ei_customer_branch_id option').each(function () {
        const branch = $(this);
        const visible = !branch.val() || String(branch.data('customer-id')) === String(option.val());
        branch.prop('disabled', !visible).toggle(visible);
    });
    $('#ei_customer_branch_id').val('').trigger('change.select2');
    updateElectronicInvoiceSummary();
}

function applyElectronicInvoiceBranch() {
    const address = $('#ei_customer_branch_id option:selected').data('address');
    if (address) {
        $('#ei_client_address').val(address);
    } else {
        $('#ei_client_address').val($('#ei_customer_id option:selected').data('address') || '');
    }
}

function applyElectronicInvoiceOrigin() {
    const option = $('#ei_customer_purchase_order_id option:selected');
    if (!option.val()) return;

    $.get(`${window.routes.electronicInvoiceCustomerPurchaseOrder}/${option.val()}`)
        .done(function (response) {
            const order = response.data || {};
            $('#ei_company_id').val(order.company_id || '').trigger('change.select2').trigger('change');
            $('#ei_customer_id').val(order.customer_id || '').trigger('change.select2').trigger('change');
            $('#ei_customer_branch_id').val(order.customer_branch_id || '').trigger('change.select2').trigger('change');
            $('#ei_quote_id').val(order.quote_id || '').trigger('change.select2');
            $('#ei_currency_id').val(order.currency_id || '').trigger('change.select2');
            $('#ei_purchase_order_number').val(order.purchase_order_number || '');
            $('#ei_siaf_number').val(order.siaf_number || '');
            $('#ei_process_number').val(order.process_number || '');
            $('#ei_contract_number').val(order.contract_number || '');
            $('#electronicInvoiceItemsTbody').empty();
            electronicInvoiceItemIndex = 0;
            (order.items || []).forEach(addElectronicInvoiceItemRow);
            if (!(order.items || []).length) {
                addElectronicInvoiceItemRow();
                Swal.fire('Orden facturada', 'La orden no tiene cantidades pendientes por facturar.', 'info');
            }
            calculateElectronicInvoiceTotals();
            updateElectronicInvoiceSummary();
        })
        .fail(function (xhr) {
            Swal.fire({ icon: 'warning', title: 'Orden no disponible', text: xhr.responseJSON?.message || 'No se pudo cargar la orden de compra.' });
        });
}

function addElectronicInvoiceItemRow(data = {}) {
    const html = $('#electronicInvoiceItemRowTemplate').html().replaceAll('__INDEX__', electronicInvoiceItemIndex);
    $('#electronicInvoiceItemsTbody').append(html);
    const row = $('#electronicInvoiceItemsTbody tr').last();
    row.find('.item-article').select2({ width: '100%', dropdownParent: $('#electronicInvoiceModal') });

    if (data.article_id) row.find('.item-article').val(data.article_id).trigger('change.select2');
    row.find('[name$="[customer_purchase_order_item_id]"]').val(data.customer_purchase_order_item_id || '');
    row.find('[name$="[description]"]').val(data.description || '');
    row.find('[name$="[product_code]"]').val(data.product_code || '');
    row.find('[name$="[lot_number]"]').val(data.lot_number || '');
    row.find('[name$="[expiration_date]"]').val(formatElectronicInvoiceInputDate(data.expiration_date));
    row.find('[name$="[brand_name]"]').val(data.brand_name || '');
    row.find('[name$="[presentation_name]"]').val(data.presentation_name || '');
    row.find('[name$="[origin]"]').val(data.origin || '');
    row.find('[name$="[unit_code]"]').val(data.unit_code || 'NIU');
    row.find('.item-quantity').val(data.quantity || 1);
    row.find('.item-price').val(data.unit_price || 0);
    row.find('.item-tax-affectation').val(data.tax_affectation_code || '10');

    electronicInvoiceItemIndex++;
    calculateElectronicInvoiceTotals();
}

function applyElectronicInvoiceArticle() {
    const row = $(this).closest('tr');
    const option = $(this).find('option:selected');
    if (!option.val()) return;

    row.find('[name$="[product_code]"]').val(option.data('code') || '');
    row.find('[name$="[description]"]').val(option.data('name') || '');
}

function calculateElectronicInvoiceTotals() {
    let taxable = 0;
    let exonerated = 0;
    let unaffected = 0;
    let igvTotal = 0;
    let total = 0;

    $('#electronicInvoiceItemsTbody tr').each(function () {
        const row = $(this);
        const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
        const price = parseFloat(row.find('.item-price').val()) || 0;
        const affectation = row.find('.item-tax-affectation').val();
        const lineTotal = quantity * price;
        const subtotal = affectation === '10' ? lineTotal / 1.18 : lineTotal;
        const igv = affectation === '10' ? lineTotal - subtotal : 0;

        if (affectation === '10') taxable += subtotal;
        if (affectation === '20') exonerated += subtotal;
        if (affectation === '30') unaffected += subtotal;
        igvTotal += igv;
        total += lineTotal;

        row.find('.item-igv').text(formatElectronicInvoiceMoney(igv));
        row.find('.item-total').text(formatElectronicInvoiceMoney(lineTotal));
    });

    $('#ei_taxable_amount').text(formatElectronicInvoiceMoney(taxable));
    $('#ei_exonerated_amount').text(formatElectronicInvoiceMoney(exonerated));
    $('#ei_unaffected_amount').text(formatElectronicInvoiceMoney(unaffected));
    $('#ei_igv_amount').text(formatElectronicInvoiceMoney(igvTotal));
    $('#ei_total_amount').text(formatElectronicInvoiceMoney(total));
    updateElectronicInvoiceSummary();
}

function updateElectronicInvoiceSummary() {
    const typeLabel = $('#ei_document_type').val() === '03'
        ? 'Boleta de venta'
        : 'Factura';
    const number = $('#ei_correlativo_preview').val() || '-';
    const customer = $('#ei_client_name').val()
        || $('#ei_customer_id option:selected').data('name')
        || 'Seleccione cliente';
    const issueDate = $('#ei_issue_date').val()
        ? formatElectronicInvoiceDisplayDate($('#ei_issue_date').val())
        : '-';
    const currency = $('#ei_currency_id option:selected').data('code') || '';
    const total = $('#ei_total_amount').text() || '0.00';

    $('#ei_summary_type').text(typeLabel);
    $('#ei_summary_number').text(number);
    $('#ei_summary_customer').text(customer);
    $('#ei_summary_issue_date').text(issueDate);
    $('#ei_summary_total').text(`${currency} ${total}`.trim());
}

function toggleElectronicInvoicePayments() {
    const isCredit = $('#ei_payment_type').val() === 'Credito';
    $('#electronicInvoicePaymentsBox').toggleClass('d-none', !isCredit);
    if (isCredit && !$('#electronicInvoicePaymentsList .electronic-invoice-payment-row').length) {
        addElectronicInvoicePaymentRow();
    }
}

function addElectronicInvoicePaymentRow(data = {}) {
    const total = $('#ei_total_amount').text();
    const html = `
        <div class="electronic-invoice-payment-row border rounded p-2 mb-2">
            <div class="form-row">
                <div class="col-3">
                    <input name="payments[${electronicInvoicePaymentIndex}][quota_number]" type="number"
                        class="form-control form-control-sm" value="${data.quota_number || electronicInvoicePaymentIndex + 1}">
                </div>
                <div class="col-5">
                    <input name="payments[${electronicInvoicePaymentIndex}][due_date]" type="date"
                        class="form-control form-control-sm" value="${formatElectronicInvoiceInputDate(data.due_date) || $('#ei_due_date').val()}">
                </div>
                <div class="col-3">
                    <input name="payments[${electronicInvoicePaymentIndex}][amount]" type="number" step="0.01"
                        class="form-control form-control-sm" value="${data.amount || total}">
                </div>
                <div class="col-1">
                    <button type="button" class="btn btn-sm btn-outline-danger removeElectronicInvoicePayment"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>
    `;
    $('#electronicInvoicePaymentsList').append(html);
    electronicInvoicePaymentIndex++;
}

function saveElectronicInvoice(form) {
    clearElectronicInvoiceValidation();
    const id = $('#electronic_invoice_id').val();
    const url = id ? `${window.routes.electronicInvoiceUpdate}/${id}` : window.routes.electronicInvoiceStore;
    const data = $(form).serializeArray();
    if (id) data.push({ name: '_method', value: 'PUT' });

    $.ajax({ url, type: 'POST', data })
        .done(function (response) {
            $('#electronicInvoiceModal').modal('hide');
            tableElectronicInvoice.ajax.reload(null, false);
            if (response.data?.status === 'generated' && response.pdf_url) window.open(response.pdf_url, '_blank');
            Swal.fire({ icon: 'success', title: response.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2800 });
        })
        .fail(function (xhr) {
            if (xhr.status === 422) {
                showElectronicInvoiceValidation(xhr.responseJSON?.errors || {});
                return;
            }
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'No se pudo guardar el comprobante.' });
        });
}

function loadElectronicInvoiceForEdit(id) {
    $.get(`${window.routes.electronicInvoiceShow}/${id}`)
        .done(function (response) {
            resetElectronicInvoiceForm();
            const invoice = response.data;
            $('#electronic_invoice_id').val(invoice.id);
            $('#ei_requested_status').val(invoice.status === 'generated' ? 'generated' : 'draft');
            $('#btnSaveElectronicInvoiceDraft').toggle(invoice.status === 'draft');
            $('#electronicInvoiceModalLabel').text('Editar Comprobante');
            $('#ei_company_id').val(invoice.company_id).trigger('change.select2').trigger('change');
            $('#ei_document_type').val(invoice.document_type).trigger('change.select2').trigger('change');
            $('#ei_serie_id').val(invoice.serie_id).trigger('change.select2').trigger('change');
            $('#ei_currency_id').val(invoice.currency_id).trigger('change.select2');
            $('#ei_customer_id').val(invoice.customer_id).trigger('change.select2').trigger('change');
            $('#ei_customer_branch_id').val(invoice.customer_branch_id || '').trigger('change.select2').trigger('change');
            $('#ei_quote_id').val(invoice.quote_id || '').trigger('change.select2');
            $('#ei_customer_purchase_order_id').val(invoice.customer_purchase_order_id || '').trigger('change.select2');
            $('#ei_warehouse_id').val(invoice.warehouse_id || '').trigger('change.select2');
            $('#ei_issue_date').val(formatElectronicInvoiceInputDate(invoice.issue_date));
            $('#ei_due_date').val(formatElectronicInvoiceInputDate(invoice.due_date));
            $('#ei_payment_type').val(invoice.payment_type || 'Contado').trigger('change.select2').trigger('change');
            $('#ei_payment_condition').val(invoice.payment_condition || '');
            $('#ei_purchase_order_number').val(invoice.purchase_order_number || '');
            $('#ei_siaf_number').val(invoice.siaf_number || '');
            $('#ei_process_number').val(invoice.process_number || '');
            $('#ei_contract_number').val(invoice.contract_number || '');
            $('#ei_observations').val(invoice.observations || '');
            $('#electronicInvoiceItemsTbody').empty();
            electronicInvoiceItemIndex = 0;
            (invoice.items || []).forEach(addElectronicInvoiceItemRow);
            $('#electronicInvoicePaymentsList').empty();
            electronicInvoicePaymentIndex = 0;
            (invoice.payments || []).forEach(addElectronicInvoicePaymentRow);
            calculateElectronicInvoiceTotals();
            updateElectronicInvoiceSummary();
            $('#electronicInvoiceModal').modal('show');
        });
}

function loadElectronicInvoiceDetail(id) {
    $.get(`${window.routes.electronicInvoiceShow}/${id}`)
        .done(function (response) {
            fillElectronicInvoiceDetail(response.data);
            $('#viewElectronicInvoiceModal').modal('show');
        });
}

function fillElectronicInvoiceDetail(invoice) {
    const typeLabel = invoice.document_type === '03' ? 'Boleta de Venta Electronica' : 'Factura Electronica';
    $('#vei_full_number').text(invoice.full_number || '-');
    $('#vei_document_type').text(typeLabel);
    $('#vei_status').text((invoice.status || '').toUpperCase()).attr('class', 'badge badge-primary px-3 py-2');
    $('#vei_client_name').text(invoice.client_name || '-');
    $('#vei_total_amount').text(`${invoice.currency_code || ''} ${formatElectronicInvoiceMoney(invoice.total_amount)}`);
    $('#vei_company').text(invoice.company_business_name || '-');
    $('#vei_company_ruc').text(invoice.company_ruc || '-');
    $('#vei_client_document').text(invoice.client_document_number || '-');
    $('#vei_issue_date').text(formatElectronicInvoiceDisplayDate(invoice.issue_date));
    $('#vei_currency').text(invoice.currency_code || '-');
    $('#vei_payment_type').text(invoice.payment_type || '-');
    $('#vei_purchase_order').text(invoice.purchase_order_number || '-');
    $('#vei_sunat_status').text(invoice.sunat_status || 'Pendiente');
    $('#vei_observations').text(invoice.observations || '-');
    $('#vei_taxable_amount').text(formatElectronicInvoiceMoney(invoice.taxable_amount));
    $('#vei_exonerated_amount').text(formatElectronicInvoiceMoney(invoice.exonerated_amount));
    $('#vei_unaffected_amount').text(formatElectronicInvoiceMoney(invoice.unaffected_amount));
    $('#vei_igv_amount').text(formatElectronicInvoiceMoney(invoice.igv_amount));
    $('#vei_total_footer').text(formatElectronicInvoiceMoney(invoice.total_amount));
    $('#vei_paid_amount').text(formatElectronicInvoiceMoney(invoice.paid_amount));
    $('#vei_pending_amount').text(formatElectronicInvoiceMoney(invoice.pending_amount));
    $('#vei_payment_status').text(electronicInvoicePaymentStatusLabel(invoice));

    const rows = (invoice.items || []).map((item, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${escapeElectronicInvoiceHtml(item.product_code || '-')}</td>
            <td>${escapeElectronicInvoiceHtml(item.description || '-')}</td>
            <td>${escapeElectronicInvoiceHtml(item.lot_number || '-')}</td>
            <td>${formatElectronicInvoiceDisplayDate(item.expiration_date)}</td>
            <td class="text-right">${formatElectronicInvoiceMoney(item.quantity)}</td>
            <td class="text-right">${formatElectronicInvoiceMoney(item.unit_price)}</td>
            <td class="text-right">${formatElectronicInvoiceMoney(item.igv_amount)}</td>
            <td class="text-right font-weight-bold">${formatElectronicInvoiceMoney(item.line_total)}</td>
        </tr>
    `).join('');
    $('#vei_items_body').html(rows || '<tr><td colspan="9" class="text-center text-muted">Sin items</td></tr>');

    const collections = (invoice.collections || []).map(collection => `
        <tr>
            <td>${formatElectronicInvoiceDisplayDate(collection.collection_date)}</td>
            <td>${escapeElectronicInvoiceHtml([
                collection.account?.bank?.short_name || collection.account?.bank?.description || '-',
                collection.account?.account_number || ''
            ].filter(Boolean).join(' - '))}</td>
            <td>${escapeElectronicInvoiceHtml(collection.operation_number || '-')}</td>
            <td class="text-right">${escapeElectronicInvoiceHtml(collection.currency?.code || '')} ${formatElectronicInvoiceMoney(collection.amount)}</td>
            <td>${escapeElectronicInvoiceHtml([collection.creator?.name, collection.creator?.lastname].filter(Boolean).join(' ') || '-')}</td>
            <td class="text-center">${collection.proof_url ? `<a href="${escapeElectronicInvoiceHtml(collection.proof_url)}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary"><i class="fas fa-paperclip"></i></a>` : '-'}</td>
        </tr>
    `).join('');
    $('#vei_collections_body').html(collections || '<tr><td colspan="6" class="text-center text-muted">Sin cobros confirmados.</td></tr>');
}

function openElectronicInvoiceFromCustomerOrder(orderId) {
    resetElectronicInvoiceForm();
    $('#electronicInvoiceModalLabel').text('Facturar Orden de Compra de Cliente');
    $('#ei_customer_purchase_order_id').val(String(orderId)).trigger('change.select2');
    $('#electronicInvoiceModal').modal('show');
    applyElectronicInvoiceOrigin();
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
        tableElectronicInvoice.ajax.reload(null, false);
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

function electronicInvoicePaymentStatusLabel(invoice) {
    if (invoice.status === 'draft') return 'BORRADOR';
    if (invoice.payment_status === 'paid') return 'COBRADA';
    if (invoice.payment_status === 'partial') return 'COBRO PARCIAL';
    return 'PENDIENTE DE COBRO';
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
            tableElectronicInvoice.ajax.reload(null, false);
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
        text: 'Se cancelara internamente. No representa baja SUNAT.',
        showCancelButton: true,
        confirmButtonText: 'Si, cancelar',
        cancelButtonText: 'Volver',
        confirmButtonColor: '#d33'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.ajax({ url: `${window.routes.electronicInvoiceDelete}/${id}`, type: 'POST', data: { _method: 'DELETE', _token: $('meta[name="csrf-token"]').attr('content') } })
            .done(function (response) {
                tableElectronicInvoice.ajax.reload(null, false);
                Swal.fire({ icon: 'success', title: response.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2600 });
            });
    });
}

function clearElectronicInvoiceValidation() {
    $('#electronicInvoiceErrors').addClass('d-none').empty();
    $('#electronicInvoiceForm .is-invalid').removeClass('is-invalid');
    $('#electronicInvoiceForm .invalid-feedback').text('');
}

function showElectronicInvoiceValidation(errors) {
    const list = [];
    Object.keys(errors).forEach(function (name) {
        const input = $(`[name="${name}"]`);
        input.addClass('is-invalid');
        input.closest('.form-group, td').find('.invalid-feedback').first().text(errors[name][0]);
        list.push(`<li>${escapeElectronicInvoiceHtml(errors[name][0])}</li>`);
    });
    $('#electronicInvoiceErrors').removeClass('d-none').html(`<ul class="mb-0">${list.join('')}</ul>`);
}

function formatElectronicInvoiceMoney(value) {
    return (parseFloat(value) || 0).toFixed(3);
}

function formatElectronicInvoiceInputDate(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function formatElectronicInvoiceDisplayDate(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value).slice(0, 10);
    return date.toLocaleDateString('es-PE');
}

function escapeElectronicInvoiceHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
