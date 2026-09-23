import '../../css/electronic-invoice-form.css';
import {
    currentDateOnlyInTimeZone,
    formatDateOnlyForDisplay,
    normalizeDateOnly,
} from '../utils/date-only';

let electronicInvoiceItemIndex = 0;
let electronicInvoicePaymentIndex = 0;
let sourceContext = 'electronic_invoices';
let onSaved = null;
let initialized = false;

export function initElectronicInvoiceForm(options = {}) {
    if (!$('#electronicInvoiceModal').length) return false;

    sourceContext = options.sourceContext || sourceContext;
    onSaved = typeof options.onSaved === 'function' ? options.onSaved : null;

    $('#electronicInvoiceModal').modal({ backdrop: 'static', keyboard: false, show: false });
    initElectronicInvoiceSelect2();
    bindElectronicInvoiceFormEvents();
    initialized = true;

    return true;
}

export function openNewElectronicInvoice() {
    ensureInitialized();
    resetElectronicInvoiceForm();
    $('#electronicInvoiceModalLabel').text('Nuevo Comprobante');
    $('#electronicInvoiceModal').modal('show');
}

export function openElectronicInvoiceFromCustomerOrder(orderId) {
    ensureInitialized();
    resetElectronicInvoiceForm();
    ensureCustomerPurchaseOrderOption(orderId);
    $('#electronicInvoiceModalLabel').text('Facturar Orden de Compra de Cliente');
    $('#ei_customer_purchase_order_id').val(String(orderId)).trigger('change.select2');
    $('#electronicInvoiceModal').modal('show');
    setElectronicInvoiceLoading(true, 'Preparando la orden y sus despachos confirmados...');

    return applyElectronicInvoiceOrigin(orderId).always(function () {
        setElectronicInvoiceLoading(false);
    });
}

export function loadElectronicInvoiceForEdit(id) {
    ensureInitialized();
    setElectronicInvoiceLoading(true, 'Cargando comprobante...');
    $('#electronicInvoiceModal').modal('show');

    return $.get(`${window.routes.electronicInvoiceShow}/${id}/edit`)
        .done(function (response) {
            resetElectronicInvoiceForm();
            const invoice = response.data;
            if (invoice.customer_purchase_order_id) ensureCustomerPurchaseOrderOption(invoice.customer_purchase_order_id);
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
        })
        .fail(function (xhr) {
            $('#electronicInvoiceModal').modal('hide');
            Swal.fire('No se pudo cargar', xhr.responseJSON?.message || 'No se pudo cargar el comprobante.', 'error');
        })
        .always(function () {
            setElectronicInvoiceLoading(false);
        });
}

function ensureInitialized() {
    if (!initialized) initElectronicInvoiceForm({ sourceContext });
}

function bindElectronicInvoiceFormEvents() {
    const namespace = '.electronicInvoiceForm';
    const documentNode = $(document);

    $('#electronicInvoiceForm').off(`submit${namespace}`).on(`submit${namespace}`, function (event) {
        event.preventDefault();
        const status = event.originalEvent?.submitter?.dataset?.status || $('#ei_requested_status').val() || 'draft';
        $('#ei_requested_status').val(status);
        saveElectronicInvoice(this);
    });
    $('#btnAddElectronicInvoiceItem').off(`click${namespace}`).on(`click${namespace}`, addElectronicInvoiceItemRow);
    $('#btnAddElectronicInvoicePayment').off(`click${namespace}`).on(`click${namespace}`, addElectronicInvoicePaymentRow);
    $('#electronicInvoiceModal').off(`hidden.bs.modal${namespace}`).on(`hidden.bs.modal${namespace}`, function () {
        setElectronicInvoiceLoading(false);
        clearElectronicInvoiceValidation();
    });

    documentNode.off(namespace)
        .on(`change${namespace}`, '#ei_company_id, #ei_document_type', filterElectronicInvoiceSeries)
        .on(`change${namespace}`, '#ei_serie_id', updateElectronicInvoiceCorrelative)
        .on(`change${namespace} input${namespace}`, '#ei_issue_date, #ei_currency_id, #ei_correlativo_preview', updateElectronicInvoiceSummary)
        .on(`change${namespace}`, '#ei_customer_id', applyElectronicInvoiceCustomer)
        .on(`change${namespace}`, '#ei_customer_branch_id', applyElectronicInvoiceBranch)
        .on(`change${namespace}`, '#ei_customer_purchase_order_id', function () { applyElectronicInvoiceOrigin(); })
        .on(`change${namespace}`, '#ei_payment_type', toggleElectronicInvoicePayments)
        .on(`change${namespace}`, '.item-article', applyElectronicInvoiceArticle)
        .on(`input${namespace} change${namespace}`, '.item-quantity, .item-price, .item-tax-affectation', calculateElectronicInvoiceTotals)
        .on(`click${namespace}`, '.removeElectronicInvoiceItem', function () {
            $(this).closest('tr').remove();
            calculateElectronicInvoiceTotals();
        })
        .on(`click${namespace}`, '.removeElectronicInvoicePayment', function () {
            $(this).closest('.electronic-invoice-payment-row').remove();
        });
}

function initElectronicInvoiceSelect2() {
    $('#electronicInvoiceModal select').each(function () {
        const select = $(this);
        if (select.hasClass('select2-hidden-accessible')) select.select2('destroy');
        select.select2({ width: '100%', dropdownParent: $('#electronicInvoiceModal') });
    });
}

function resetElectronicInvoiceForm() {
    const form = $('#electronicInvoiceForm')[0];
    if (!form) return;
    form.reset();
    $('#electronic_invoice_id').val('');
    $('#ei_requested_status').val('draft');
    $('#btnSaveElectronicInvoiceDraft').show();
    clearElectronicInvoiceValidation();
    $('#ei_issue_date').val(currentDateOnlyInTimeZone());
    $('#ei_document_type').val('01').trigger('change.select2');
    $('#ei_payment_type').val('Contado').trigger('change.select2');
    const defaultWarehouse = $('#ei_warehouse_id option[value]').filter(function () { return this.value; }).first();
    $('#ei_warehouse_id').prop('disabled', false).val(defaultWarehouse.val() || '').trigger('change.select2');
    $('#ei_warehouse_id').next('.select2-container').removeClass('d-none');
    $('#ei_dispatch_warehouse_display').addClass('d-none').val('');
    $('#ei_dispatch_warehouse_help').addClass('d-none').text('');
    $('#ei_warehouse_required').removeClass('d-none');
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
    filterElectronicInvoiceWarehouses(companyId);

    if (companyId && !environment) {
        $('#ei_serie_id').val('').trigger('change.select2');
        if (event?.target?.id === 'ei_company_id') {
            Swal.fire({
                icon: 'warning',
                title: 'Configuración local requerida',
                text: 'La empresa seleccionada no tiene una configuración de facturación local activa.'
            });
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
    if (selected.prop('disabled')) $('#ei_serie_id').val('').trigger('change.select2');
    if (!$('#ei_serie_id').val()) {
        const available = $('#ei_serie_id option').filter(function () {
            return this.value && !this.disabled;
        });
        const defaults = available.filter(function () {
            return String($(this).data('is-default')) === '1';
        });
        const internalDefault = defaults.filter(function () {
            return $(this).data('environment') === 'internal';
        }).first();
        const preferred = internalDefault.length
            ? internalDefault
            : (defaults.length ? defaults.first() : (available.length === 1 ? available.first() : $()));
        if (preferred.length) {
            $('#ei_serie_id').val(preferred.val()).trigger('change.select2');
        } else if (!available.length && companyId && environment === 'internal' && event?.target?.id) {
            Swal.fire({
                icon: 'warning',
                title: 'Serie local requerida',
                text: 'Configure una serie interna activa para emitir este comprobante.'
            });
        }
    }
    updateElectronicInvoiceCorrelative();
    updateElectronicInvoiceSummary();
}

function filterElectronicInvoiceWarehouses(companyId) {
    const select = $('#ei_warehouse_id');
    select.find('option').each(function () {
        const option = $(this);
        const companyIds = String(option.attr('data-company-ids') || '').split(',').filter(Boolean);
        const visible = !option.val() || (companyId && companyIds.includes(String(companyId)));
        option.prop('disabled', !visible).toggle(visible);
    });

    if (select.find('option:selected').prop('disabled')) {
        select.val('').trigger('change.select2');
    }

    if (!select.val()) {
        const available = select.find('option').filter(function () {
            return this.value && !this.disabled;
        });
        if (available.length === 1) select.val(available.first().val()).trigger('change.select2');
    }
}

function updateElectronicInvoiceCorrelative() {
    const option = $('#ei_serie_id option:selected');
    $('#ei_correlativo_preview').val(option.val() ? `${option.data('serie')}-${option.data('next-number')}` : '');
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
    $('#ei_client_address').val(address || $('#ei_customer_id option:selected').data('address') || '');
}

function applyElectronicInvoiceOrigin(explicitOrderId = null) {
    const orderId = explicitOrderId || $('#ei_customer_purchase_order_id').val();
    if (!orderId) return $.Deferred().resolve().promise();

    return $.get(`${window.routes.electronicInvoiceCustomerPurchaseOrder}/${orderId}`)
        .done(function (response) {
            const order = response.data || {};
            ensureCustomerPurchaseOrderOption(order.id || orderId, order.purchase_order_number);
            $('#ei_customer_purchase_order_id').val(String(order.id || orderId)).trigger('change.select2');
            $('#ei_company_id').val(order.company_id || '').trigger('change.select2').trigger('change');
            $('#ei_customer_id').val(order.customer_id || '').trigger('change.select2').trigger('change');
            $('#ei_customer_branch_id').val(order.customer_branch_id || '').trigger('change.select2').trigger('change');
            $('#ei_quote_id').val(order.quote_id || '').trigger('change.select2');
            $('#ei_currency_id').val(order.currency_id || '').trigger('change.select2');
            $('#ei_purchase_order_number').val(order.purchase_order_number || '');
            $('#ei_siaf_number').val(order.siaf_number || '');
            $('#ei_process_number').val(order.process_number || '');
            $('#ei_contract_number').val(order.contract_number || '');
            const dispatchBacked = Boolean(order.dispatch_backed);
            applyElectronicInvoiceWarehouseContext(dispatchBacked, order.warehouse_context);
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
            Swal.fire({
                icon: 'warning',
                title: 'Orden no disponible',
                text: xhr.responseJSON?.message || 'No se pudo cargar la orden de compra.'
            });
        });
}

function applyElectronicInvoiceWarehouseContext(dispatchBacked, warehouseContext = null) {
    const select = $('#ei_warehouse_id');
    const display = $('#ei_dispatch_warehouse_display');
    const help = $('#ei_dispatch_warehouse_help');

    if (!dispatchBacked) {
        select.prop('disabled', false).next('.select2-container').removeClass('d-none');
        display.addClass('d-none').val('');
        help.addClass('d-none').text('');
        $('#ei_warehouse_required').removeClass('d-none');
        return;
    }

    const singleWarehouseId = warehouseContext?.mode === 'single'
        ? String(warehouseContext.warehouse_id || '')
        : '';
    select.val(singleWarehouseId).trigger('change.select2').prop('disabled', true);
    select.next('.select2-container').addClass('d-none');
    display.val(warehouseContext?.label || 'Almacén no identificado — revisar despachos').removeClass('d-none');
    help.text(warehouseContext?.mode === 'single'
        ? 'Según despacho confirmado'
        : 'La trazabilidad se conserva por cada detalle de despacho confirmado.')
        .removeClass('d-none');
    $('#ei_warehouse_required').addClass('d-none');
}

function ensureCustomerPurchaseOrderOption(orderId, label = null) {
    const select = $('#ei_customer_purchase_order_id');
    if (!select.length || !orderId) return;
    if (!select.find(`option[value="${orderId}"]`).length) {
        select.append(new Option(label || `OC #${orderId}`, String(orderId), false, false));
    }
}

function addElectronicInvoiceItemRow(data = {}) {
    const template = $('#electronicInvoiceItemRowTemplate').html();
    if (!template) return;
    const html = template.replaceAll('__INDEX__', electronicInvoiceItemIndex);
    $('#electronicInvoiceItemsTbody').append(html);
    const row = $('#electronicInvoiceItemsTbody tr').last();
    row.find('.item-article').select2({ width: '100%', dropdownParent: $('#electronicInvoiceModal') });

    if (data.article_id) row.find('.item-article').val(data.article_id).trigger('change.select2');
    row.find('[name$="[customer_purchase_order_item_id]"]').val(data.customer_purchase_order_item_id || '');
    row.find('[name$="[warehouse_dispatch_item_id]"]').val(data.warehouse_dispatch_item_id || '');
    row.find('[name$="[description]"]').val(data.description || '');
    row.find('[name$="[product_code]"]').val(data.product_code || '');
    row.find('[name$="[lot_number]"]').val(data.lot_number || '');
    row.find('[name$="[expiration_date]"]').val(formatElectronicInvoiceInputDate(data.expiration_date));
    if (data.warehouse_dispatch_item_id) {
        row.find('[name$="[lot_number]"], [name$="[expiration_date]"]').prop('readonly', true);
    }
    row.find('[name$="[brand_name]"]').val(data.brand_name || '');
    row.find('[name$="[presentation_name]"]').val(data.presentation_name || '');
    row.find('[name$="[origin]"]').val(data.origin || '');
    row.find('[name$="[unit_code]"]').val(data.unit_code || '');
    row.find('.item-quantity').val(data.quantity || 1);
    row.find('.item-price').val(data.unit_price || 0);
    row.find('.item-tax-affectation').val(data.tax_affectation_code || '');

    electronicInvoiceItemIndex++;
    calculateElectronicInvoiceTotals();
}

function applyElectronicInvoiceArticle() {
    const row = $(this).closest('tr');
    const option = $(this).find('option:selected');
    if (!option.val()) return;
    row.find('[name$="[product_code]"]').val(option.data('code') || '');
    row.find('[name$="[description]"]').val(option.data('name') || '');
    // La afectación tributaria se elige en la línea del comprobante.
    // Seleccionar un artículo no debe cambiarla silenciosamente.
    calculateElectronicInvoiceTotals();
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
    const typeLabel = $('#ei_document_type').val() === '03' ? 'Boleta de venta' : 'Factura';
    const number = $('#ei_correlativo_preview').val() || '-';
    const customer = $('#ei_client_name').val() || $('#ei_customer_id option:selected').data('name') || 'Seleccione cliente';
    const issueDate = $('#ei_issue_date').val() ? formatElectronicInvoiceDisplayDate($('#ei_issue_date').val()) : '-';
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
    if (isCredit && !$('#electronicInvoicePaymentsList .electronic-invoice-payment-row').length) addElectronicInvoicePaymentRow();
}

function addElectronicInvoicePaymentRow(data = {}) {
    const total = $('#ei_total_amount').text();
    const html = `
        <div class="electronic-invoice-payment-row border rounded p-2 mb-2">
            <div class="form-row">
                <div class="col-3"><input name="payments[${electronicInvoicePaymentIndex}][quota_number]" type="number" class="form-control form-control-sm" value="${data.quota_number || electronicInvoicePaymentIndex + 1}"></div>
                <div class="col-5"><input name="payments[${electronicInvoicePaymentIndex}][due_date]" type="date" class="form-control form-control-sm" value="${formatElectronicInvoiceInputDate(data.due_date) || $('#ei_due_date').val()}"></div>
                <div class="col-3"><input name="payments[${electronicInvoicePaymentIndex}][amount]" type="number" step="0.01" class="form-control form-control-sm" value="${data.amount || total}"></div>
                <div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger removeElectronicInvoicePayment"><i class="fas fa-times"></i></button></div>
            </div>
        </div>`;
    $('#electronicInvoicePaymentsList').append(html);
    electronicInvoicePaymentIndex++;
}

function saveElectronicInvoice(form) {
    clearElectronicInvoiceValidation();
    const id = $('#electronic_invoice_id').val();
    const url = id ? `${window.routes.electronicInvoiceUpdate}/${id}` : window.routes.electronicInvoiceStore;
    const data = $(form).serializeArray();
    if (id) data.push({ name: '_method', value: 'PUT' });
    const buttons = $('#btnSaveElectronicInvoiceDraft, #btnGenerateElectronicInvoice').prop('disabled', true);

    $.ajax({ url, type: 'POST', data })
        .done(function (response) {
            $('#electronicInvoiceModal').modal('hide');
            if (typeof onSaved === 'function') onSaved(response, { sourceContext });
            if (response.data?.status === 'generated' && response.pdf_url) window.open(response.pdf_url, '_blank');
            Swal.fire({ icon: 'success', title: response.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 2800 });
        })
        .fail(function (xhr) {
            if (xhr.status === 422) {
                showElectronicInvoiceValidation(xhr.responseJSON?.errors || {});
                return;
            }
            Swal.fire({ icon: 'error', title: 'Error', text: xhr.responseJSON?.message || 'No se pudo guardar el comprobante.' });
        })
        .always(function () {
            buttons.prop('disabled', false);
        });
}

function setElectronicInvoiceLoading(isLoading, message = '') {
    $('#electronicInvoiceLoadingText').text(message || 'Cargando...');
    $('#electronicInvoiceLoading').toggleClass('d-none', !isLoading).attr('aria-hidden', isLoading ? 'false' : 'true');
    $('#electronicInvoiceForm :input').not('[data-dismiss="modal"]').prop('disabled', isLoading);
    if (!isLoading && $('#ei_warehouse_required').hasClass('d-none')) $('#ei_warehouse_id').prop('disabled', true);
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

export function formatElectronicInvoiceMoney(value) {
    return (parseFloat(value) || 0).toFixed(3);
}

export function formatElectronicInvoiceInputDate(value) {
    return normalizeDateOnly(value);
}

export function formatElectronicInvoiceDisplayDate(value) {
    return formatDateOnlyForDisplay(value);
}

export function escapeElectronicInvoiceHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
