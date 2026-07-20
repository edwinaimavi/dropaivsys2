let tableWarehouseEntry;
let warehouseEntryItemIndex = 0;
let warehouseEntrySourceLoadRequest = null;
let warehouseEntrySourceLoadTimer = null;
let warehouseEntryPendingDocuments = [];
let warehouseEntryExistingDocuments = [];

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

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $('#warehouseEntryModal').modal({
        backdrop: 'static',
        keyboard: false,
        show: false
    });

    initWarehouseEntrySelect2($('#warehouseEntryModal'));
    initWarehouseEntryTable();

    $(document).on('click', '#btnCreateWarehouseEntry', function () {
        resetWarehouseEntryForm();
        $('#warehouseEntryModalLabel').text('Registrar Ingreso de Almacen');
        generateWarehouseEntryNumber();
        $('#warehouseEntryModal').modal('show');
    });

    $('#warehouseEntryModal').on('hidden.bs.modal', resetWarehouseEntryForm);

    $(document).on('submit', '#warehouseEntryForm', function (event) {
        event.preventDefault();
        saveWarehouseEntry(this);
    });

    $(document).on('click', '#btnAddWarehouseEntryItem', function () {
        addWarehouseEntryItemRow();
    });

    $(document).on('click', '.btnRemoveWarehouseEntryItem', function () {
        const row = $(this).closest('tr');
        destroyWarehouseEntryRowSelect2(row);
        row.remove();
        refreshWarehouseEntryItemIndexes();
        calculateWarehouseEntryTotals();
        showEmptyWarehouseEntryItemsRow();
    });

    $(document).on('change', '#warehouse_entry_supplier_purchase_order_id', function () {
        applySelectedSupplierOrderHeader();
        scheduleWarehouseEntrySourceAutoLoad();
    });

    $(document).on('change', '#warehouse_entry_supplier_id', function () {
        syncWarehouseEntrySupplierFields();
    });

    $(document).on('change', '#warehouse_entry_warehouse_id', function () {
        const text = $(this).find('option:selected').text().trim();
        $('#warehouseEntrySideWarehouse').text($(this).val() ? text : 'Sin almacen');
    });

    $(document).on('change', '#warehouse_entry_currency_id', updateWarehouseEntryCurrency);

    $(document).on(
        'input change',
        '#warehouse_entry_affect_igv, .item-quantity, .item-unit-price',
        calculateWarehouseEntryTotals
    );

    $(document).on('change', '#warehouse_entry_generate_account_payable', syncWarehouseEntryPayableAmount);

    $(document).on('change', '.item-article-picker', function () {
        applySelectedWarehouseEntryArticle($(this).closest('tr'));
    });

    $(document).on('click', '#btnLoadWarehouseEntrySource', loadWarehouseEntrySourceItems);

    $(document).on('change', '#warehouse_entry_document_attachment_file', function () {
        const file = this.files?.[0];
        $(this).siblings('.custom-file-label').text(file ? file.name : 'Seleccionar archivo');
    });

    $(document).on('click', '#btnAddWarehouseEntryDocument', addWarehouseEntryPendingDocument);

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
            { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm' },
            { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-danger btn-sm' },
            { extend: 'print', text: '<i class="fas fa-print"></i> Imprimir', className: 'btn btn-secondary btn-sm' }
        ]
    });
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
    $('#warehouseEntrySideSupplier').text('Seleccione proveedor');
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
    resetWarehouseEntryDocumentInputs();
    renderWarehouseEntryDocuments();
    setWarehouseEntrySupplierLocked(false);
    syncWarehouseEntryPayableAmount();
}

function clearWarehouseEntryValidation() {
    $('#warehouseEntryForm .is-invalid').removeClass('is-invalid');
    $('#warehouseEntryForm .invalid-feedback').text('');
}

function saveWarehouseEntry(form) {
    clearWarehouseEntryValidation();
    syncWarehouseEntryPayableAmount();

    if (!$('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').length) {
        Swal.fire({
            icon: 'warning',
            title: 'Agregue al menos un articulo.'
        });
        return;
    }

    const id = $('#warehouse_entry_id').val();
    const url = id
        ? `${window.routes.warehouseEntryUpdate}/${id}`
        : window.routes.warehouseEntryStore;
    const formData = new FormData(form);

    if (id) {
        formData.append('_method', 'PUT');
    }

    warehouseEntryPendingDocuments.forEach(function (document, index) {
        formData.append(`warehouse_entry_documents[${index}][type]`, document.type);
        formData.append(`warehouse_entry_documents[${index}][description]`, document.description || '');
        formData.append(`warehouse_entry_documents[${index}][file]`, document.file);
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
    Object.keys(errors).forEach(function (name) {
        const input = $(`[name="${name}"]`);
        input.addClass('is-invalid');
        input.closest('.form-group, td').find('.invalid-feedback').first().text(errors[name][0]);
    });

    Swal.fire({
        icon: 'warning',
        title: 'Revise los datos ingresados',
        text: 'Hay campos pendientes o con valores invalidos.'
    });
}

function applySelectedSupplierOrderHeader() {
    const option = $('#warehouse_entry_supplier_purchase_order_id option:selected');
    const orderId = option.val();

    if (!orderId) {
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
            $('#warehouse_entry_payment_method').val(response.payment_method || '');
            $('#warehouse_entry_payment_condition').val(response.payment_condition || '');
            $('#warehouse_entry_affect_igv').val(response.affect_igv ? '1' : '0');

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
    row.find('.item-expiration-date').val(formatWarehouseEntryDate(data.expiration_date));
    row.find('.item-lot-number').val(data.lot_number || '');
    row.find('.item-ordered-quantity').val(formatWarehouseEntryMoney(data.ordered_quantity || 0));
    row.find('.item-quantity').val(formatWarehouseEntryMoney(data.quantity || 1));
    row.find('.item-unit-price').val(formatWarehouseEntryMoney(data.unit_price || 0));

    initWarehouseEntrySelect2(row);

    warehouseEntryItemIndex++;
    refreshWarehouseEntryItemIndexes();
    calculateWarehouseEntryTotals();
}

function applySelectedWarehouseEntryArticle(row) {
    const option = row.find('.item-article-picker option:selected');
    const articleId = option.val() || '';

    row.find('.item-article-id').val(articleId);
    row.find('.item-article-code').val(option.data('code') || '');
    row.find('.item-billing-name').val(option.data('billing-name') || '');

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
            <td colspan="15" class="text-center text-muted py-4">
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
    });
}

function calculateWarehouseEntryTotals() {
    const affectIgv = $('#warehouse_entry_affect_igv').val() === '1';
    let subtotal = 0;
    let igv = 0;

    $('#warehouseEntryItemsTbody tr.warehouse-entry-item-row').each(function () {
        const row = $(this);
        const quantity = parseWarehouseEntryNumber(row.find('.item-quantity').val());
        const unitPrice = parseWarehouseEntryNumber(row.find('.item-unit-price').val());
        const lineSubtotal = quantity * unitPrice;
        const lineIgv = affectIgv ? lineSubtotal * 0.18 : 0;
        const lineTotal = lineSubtotal + lineIgv;

        subtotal += lineSubtotal;
        igv += lineIgv;
        row.find('.item-line-total').text(formatWarehouseEntryMoney(lineTotal));
    });

    const total = subtotal + igv;

    $('#warehouse_entry_subtotal').val(formatWarehouseEntryMoney(subtotal));
    $('#warehouse_entry_igv').val(formatWarehouseEntryMoney(igv));
    $('#warehouse_entry_grand_total').val(formatWarehouseEntryMoney(total));
    $('#warehouseEntrySideGrandTotal').text(formatWarehouseEntryMoney(total));
    syncWarehouseEntryPayableAmount(total);
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

    clearWarehouseEntryItemRows();
    (entry.items || []).forEach(addWarehouseEntryItemRow);
    showEmptyWarehouseEntryItemsRow();
    warehouseEntryPendingDocuments = [];
    warehouseEntryExistingDocuments = entry.documents || [];
    resetWarehouseEntryDocumentInputs();
    renderWarehouseEntryDocuments();
    calculateWarehouseEntryTotals();
    syncWarehouseEntryPayableAmount();
}

function loadWarehouseEntryDetail(id) {
    $.get(`${window.routes.warehouseEntryShow}/${id}`)
        .done(function (response) {
            renderWarehouseEntryDetail(response.data, response.warehouse_name);
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

    const rows = (entry.items || []).map(function (item, index) {
        return `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeWarehouseEntryHtml(item.billing_name_snapshot || item.article?.billing_name || '-')}</td>
                <td>${escapeWarehouseEntryHtml(item.unit?.description || '-')}</td>
                <td>${escapeWarehouseEntryHtml(item.presentation?.description || '-')}</td>
                <td>${escapeWarehouseEntryHtml(item.brand?.description || '-')}</td>
                <td>${escapeWarehouseEntryHtml(item.lot_number || '-')}</td>
                <td class="text-right">${formatWarehouseEntryMoney(item.ordered_quantity || 0)}</td>
                <td class="text-right">${formatWarehouseEntryMoney(item.quantity || 0)}</td>
                <td class="text-right">${formatWarehouseEntryMoney(item.unit_price || 0)}</td>
                <td class="text-right">${formatWarehouseEntryMoney(item.line_total || 0)}</td>
            </tr>
        `;
    }).join('');

    $('#vwe_items').html(rows || '<tr><td colspan="10" class="text-center text-muted py-3">Sin articulos ingresados.</td></tr>');
    renderWarehouseEntryDetailDocuments(entry.documents || [], entry.id);
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
    return parseFloat(String(value || '0').replace(',', '.')) || 0;
}

function formatWarehouseEntryMoney(value) {
    return (parseFloat(value) || 0).toFixed(2);
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
        Swal.fire('Archivo no permitido', 'Adjunte PDF, imagen, Word o Excel.', 'warning');
        return;
    }

    if (file.size > warehouseEntryMaxDocumentSize) {
        Swal.fire('Archivo muy pesado', 'El documento no debe superar 10 MB.', 'warning');
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
            <td colspan="6" class="text-center text-muted py-3">
                No hay documentos adjuntos.
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
