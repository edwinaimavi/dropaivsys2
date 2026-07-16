let tableSupplierPurchaseOrder;
let supplierOrderItemIndex = 0;
let supplierOrderSourceLoadRequest = null;
let supplierOrderSourceLoadTimer = null;
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

    initSupplierOrderSelect2($('#supplierPurchaseOrderModal'));
    initSupplierPurchaseOrderTable();

    $(document).on('click', '#btnCreateSupplierPurchaseOrder', function () {
        resetSupplierPurchaseOrderForm();
        syncPurchaseInstructions(true);
        $('#supplierPurchaseOrderModalLabel').text('Registrar Orden de Compra a Proveedor');
        $('#supplier_order_code').val('Seleccione cuenta bancaria');
        $('#supplierPurchaseOrderModal').modal('show');
    });

    $('#supplierPurchaseOrderModal').on('hidden.bs.modal', resetSupplierPurchaseOrderForm);

    $(document).on('submit', '#supplierPurchaseOrderForm', function (event) {
        event.preventDefault();
        saveSupplierPurchaseOrder(this);
    });

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
    });

    $(document).on('change', '#supplier_order_supplier_id', function () {
        const supplierId = $(this).val();
        const selected = $(this).find('option:selected');
        $('#supplierOrderSideSupplier').text(
            supplierId ? selected.text().trim() : 'Seleccione proveedor'
        );

        if (!$('#supplier_order_payment_condition').val()) {
            const paymentCondition = normalizeSupplierOrderOption(
                selected.data('payment-condition') || ''
            );

            if (
                paymentCondition
                && $(`#supplier_order_payment_condition option[value="${paymentCondition}"]`).length
            ) {
                $('#supplier_order_payment_condition')
                    .val(paymentCondition)
                    .trigger('change.select2');
            }
        }

        loadSupplierAccounts(supplierId);
        clearSupplierOrderPendingItems();
    });

    $(document).on('change', '#supplier_order_currency_id', updateSupplierOrderCurrency);

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

function initSupplierPurchaseOrderTable() {
    tableSupplierPurchaseOrder = $('#tableSupplierPurchaseOrder').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.supplierPurchaseOrderList,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'code', name: 'code' },
            { data: 'supplier', name: 'supplier.business_name', orderable: false },
            { data: 'company', name: 'company.business_name', orderable: false },
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
            {
                extend: 'excel',
                className: 'btn btn-success btn-sm',
                text: '<i class="fas fa-file-excel"></i> Excel'
            },
            {
                extend: 'pdf',
                className: 'btn btn-danger btn-sm',
                text: '<i class="fas fa-file-pdf"></i> PDF'
            },
            {
                extend: 'print',
                className: 'btn btn-secondary btn-sm',
                text: '<i class="fas fa-print"></i> Imprimir'
            }
        ],
        drawCallback: function () {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });
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

        select.select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: $('#supplierPurchaseOrderModal'),
            placeholder: select.data('placeholder') || select.find('option:first').text().trim(),
            allowClear: !select.prop('required')
        });
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
    clearSupplierPurchaseOrderErrors();

    $('#supplier_purchase_order_id').val('');
    $('#supplier_order_code').val('');
    $('#supplierPurchaseOrderModalLabel').text('Registrar Orden de Compra a Proveedor');
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
    $('#supplier_order_document_type').val('').trigger('change.select2');
    $('#supplier_order_request_department').val('COMPRAS');
    $('#supplier_order_authorized_by_name').val('IVAN CUBAS BINCES');
    $('#supplier_order_authorized_by_position').val('GERENTE GENERAL');
    $('#supplier_order_delivery_text').val('EN AGENCIA DE TRANSPORTES - ENVIO A PROVINCIA');
    $('#supplier_order_purchase_instructions').val('').data('last-auto-value', '');
    $('#supplier_order_important_note').val(defaultSupplierOrderImportantNote);

    setDefaultSupplierOrderCurrency();
    $('#supplierOrderSideSupplier').text('Seleccione proveedor');
    toggleSupplierOrderShippingAgencySection();
    calculateSupplierOrderTotals();
    syncPurchaseInstructions(true);
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
        $('#supplier_order_code').val('Seleccione cuenta bancaria');
        return;
    }

    $('#supplier_order_code').val('Generando...');

    $.get(window.routes.supplierPurchaseOrderGenerateCode, {
        supplier_account_id: supplierAccountId
    })
        .done(function (response) {
            $('#supplier_order_code').val(response.code || 'Seleccione cuenta bancaria');
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

    if (!$('#supplier_order_supplier_account_id').val()) {
        Swal.fire({ icon: 'warning', title: 'Debe seleccionar o registrar una cuenta bancaria del proveedor.' });
        return;
    }

    if ($('#supplierOrderItemsTbody tr.supplier-order-item-row').length === 0) {
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
                window.open(response.pdf_url, '_blank');
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
                $('#supplier_order_company_id').val(response.company_id).trigger('change.select2');
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
                        value="${formatSupplierOrderUnitPrice(unitPrice)}" min="0" step="0.000001" inputmode="decimal"
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
    row.find('.item-reference-purchase-price').val(formatSupplierOrderMoney(data.reference_purchase_price || 0));
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

    $('#supplier_purchase_order_id').val(order.id || '');
    $('#supplier_order_code').val(order.code || '');
    $('#supplier_order_company_id').val(order.company_id || '').trigger('change.select2');
    $('#supplier_order_supplier_id').val(order.supplier_id || '').trigger('change.select2');
    const supplierAccountsRequest = loadSupplierAccounts(order.supplier_id, order.supplier_account_id, { suppressInstructionSync: true });
    $('#supplier_order_currency_id').val(order.currency_id || '').trigger('change');
    const customerPurchaseOrderIds = (order.customer_purchase_orders || [])
        .map(customerOrder => String(customerOrder.id));
    $('#supplier_order_customer_purchase_order_ids')
        .val(customerPurchaseOrderIds.length ? customerPurchaseOrderIds : (order.customer_purchase_order_id ? [String(order.customer_purchase_order_id)] : []))
        .trigger('change.select2');
    $('#supplier_order_type').val(order.order_type || 'articles');
    $('#supplier_order_payment_condition')
        .val(normalizeSupplierOrderOption(order.payment_condition || ''))
        .trigger('change.select2');
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
    $('#supplierOrderSideSupplier').text(supplierName(order.supplier));

    clearSupplierOrderItemRows();
    (order.items || []).forEach(addSupplierOrderItemRow);
    showEmptySupplierOrderItemsRow();
    calculateSupplierOrderTotals();

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

            return `<span class="supplier-order-related-badge">
                <i class="fas fa-clipboard-check mr-1"></i>
                ${escapeSupplierOrderHtml(customerOrder.purchase_order_number || customerOrder.code || '-')} | ${escapeSupplierOrderHtml(customerName)}
            </span>`;
        }).join('')
        : '-');
    $('#vspo_currency').text([currencyCode, order.currency?.description].filter(Boolean).join(' | ') || '-');
    $('#vspo_payment_condition').text(supplierOrderOptionLabel(order.payment_condition) || '-');
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
}

function showSupplierPurchaseOrderErrors(errors) {
    const errorMessages = [];

    Object.entries(errors).forEach(function ([field, fieldMessages]) {
        const normalizedField = field.replace(/\.\d+$/, '');
        let input = $(`[name="${normalizedField}"]`);
        const message = fieldMessages[0];

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

        errorMessages.push(message);
    });

    $('#supplierPurchaseOrderErrors')
        .removeClass('d-none')
        .html(`<ul class="mb-0 pl-3">${errorMessages.map(
            message => `<li>${escapeSupplierOrderHtml(message)}</li>`
        ).join('')}</ul>`);
}

function updateSupplierOrderCurrency() {
    const selected = $('#supplier_order_currency_id option:selected');
    const code = selected.data('code') || 'PEN';
    const symbol = selected.data('symbol') || 'S/';

    $('.supplier-order-currency-code').text(code);
    $('.supplier-order-currency-symbol').text(symbol);
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
        credito_20_dias: 'credito_20_dias',
        credito_20_dia: 'credito_20_dias',
        credito_30_dias: 'credito_30_dias',
        credito_30_dia: 'credito_30_dias',
        credito_45_dias: 'credito_45_dias',
        credito_45_dia: 'credito_45_dias',
        credito_60_dias: 'credito_60_dias',
        credito_60_dia: 'credito_60_dias',
        deposito_en_cuenta: 'deposito_cuenta',
        deposito_cuenta: 'deposito_cuenta',
        agencia_de_transporte: 'agencia_transporte',
        agencia_transporte: 'agencia_transporte',
        en_agencia: 'en_agencia',
        transporte: 'transporte',
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

function supplierOrderRequiresShippingAgency(value) {
    return ['agencia', 'agencia_transporte', 'en_agencia', 'transporte']
        .includes(normalizeSupplierOrderOption(value));
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
    const condition = supplierOrderOptionLabel(order.payment_condition) || 'Condicion no indicada';
    const bankName = bank.short_name || bank.description || 'Banco no indicado';
    const accountNumber = account.account_number || account.cci || 'Cuenta no indicada';

    return `${condition} - ${bankName} - ${accountNumber}`;
}

function supplierOrderOptionLabel(value) {
    const labels = {
        terrestre: 'Terrestre',
        aereo: 'Aereo',
        contado: 'Contado',
        credito_20_dias: 'Credito 20 dias',
        credito_30_dias: 'Credito 30 dias',
        credito_45_dias: 'Credito 45 dias',
        credito_60_dias: 'Credito 60 dias',
        agencia: 'Agencia',
        agencia_transporte: 'Agencia de transporte',
        en_agencia: 'En agencia',
        transporte: 'Transporte',
        recojo_almacen: 'Recojo de almacen',
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
