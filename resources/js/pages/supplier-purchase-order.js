let tableSupplierPurchaseOrder;
let supplierOrderItemIndex = 0;
let supplierOrderSourceLoadRequest = null;
let supplierOrderSourceLoadTimer = null;

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
        $('#supplierPurchaseOrderModalLabel').text('Registrar Orden de Compra a Proveedor');
        generateSupplierPurchaseOrderCode();
        $('#supplierPurchaseOrderModal').modal('show');
    });

    $('#supplierPurchaseOrderModal').on('hidden.bs.modal', resetSupplierPurchaseOrderForm);

    $(document).on('submit', '#supplierPurchaseOrderForm', function (event) {
        event.preventDefault();
        saveSupplierPurchaseOrder(this);
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
        scheduleSupplierOrderSourceAutoLoad();
    });

    $(document).on('change', '#supplier_order_currency_id', updateSupplierOrderCurrency);

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
        scheduleSupplierOrderSourceAutoLoad();
    });

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
    $('#supplier_order_payment_method').val('').trigger('change.select2');
    $('#supplier_order_document_type').val('').trigger('change.select2');

    setDefaultSupplierOrderCurrency();
    $('#supplierOrderSideSupplier').text('Seleccione proveedor');
    calculateSupplierOrderTotals();
}

function generateSupplierPurchaseOrderCode() {
    $('#supplier_order_code').val('Generando...');

    $.get(window.routes.supplierPurchaseOrderGenerateCode)
        .done(function (response) {
            $('#supplier_order_code').val(response.code || '');
        })
        .fail(function (xhr) {
            $('#supplier_order_code').val('');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo generar el codigo.'
            });
        });
}

function saveSupplierPurchaseOrder(formElement) {
    clearSupplierPurchaseOrderErrors();
    refreshSupplierOrderItemIndexes();
    calculateSupplierOrderTotals();

    if ($('#supplierOrderItemsTbody tr.supplier-order-item-row').length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Agregue al menos un item',
            text: 'La orden debe contener productos o servicios para comprar.'
        });
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

function loadSupplierAccounts(supplierId, selectedAccountId = null) {
    const select = $('#supplier_order_supplier_account_id');

    if (!supplierId) {
        select.html('<option value="">Seleccione proveedor primero</option>').trigger('change.select2');
        return;
    }

    const url = window.routes.supplierPurchaseOrderSupplierAccounts.replace(':id', supplierId);

    select
        .prop('disabled', true)
        .html('<option value="">Cargando cuentas...</option>')
        .trigger('change.select2');

    $.get(url)
        .done(function (response) {
            const accounts = response.accounts || [];
            let options = '<option value="">Seleccione cuenta</option>';

            accounts.forEach(function (account) {
                const bank = account.bank?.description || 'Banco';
                const currency = account.currency?.code || '';
                options += `<option value="${escapeSupplierOrderHtml(account.id)}">
                    ${escapeSupplierOrderHtml(bank)} - ${escapeSupplierOrderHtml(account.account_number)} - ${escapeSupplierOrderHtml(currency)}
                </option>`;
            });

            select.html(options).prop('disabled', accounts.length === 0);

            if (selectedAccountId) {
                select.val(String(selectedAccountId));
            }

            select.trigger('change.select2');
        })
        .fail(function () {
            select
                .prop('disabled', true)
                .html('<option value="">Error al cargar cuentas</option>')
                .trigger('change.select2');
        });
}

function scheduleSupplierOrderSourceAutoLoad() {
    clearTimeout(supplierOrderSourceLoadTimer);

    supplierOrderSourceLoadTimer = setTimeout(function () {
        loadSupplierOrderSourceItems({ silent: true });
    }, 250);
}

function loadSupplierOrderSourceItems(options = {}) {
    const orderIds = $('#supplier_order_customer_purchase_order_ids').val() || [];
    const supplierId = $('#supplier_order_supplier_id').val();
    const isSilent = Boolean(options.silent);

    if (!orderIds.length) {
        if (isSilent) {
            clearSupplierOrderItemRows();
            showEmptySupplierOrderItemsRow();
            calculateSupplierOrderTotals();
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Seleccione al menos un pedido',
            text: 'Elija una o varias ordenes de compra del cliente para cargar sus items.'
        });
        return;
    }

    if (!supplierId) {
        clearSupplierOrderItemRows();
        showEmptySupplierOrderItemsRow();
        calculateSupplierOrderTotals();

        if (!isSilent) {
            Swal.fire({
                icon: 'info',
                title: 'Seleccione un proveedor para cargar los articulos adjudicados.'
            });
        }

        return;
    }

    if (supplierOrderSourceLoadRequest) {
        supplierOrderSourceLoadRequest.abort();
    }

    supplierOrderSourceLoadRequest = $.ajax({
        url: window.routes.supplierPurchaseOrderLoadCustomerItems,
        type: 'POST',
        data: {
            supplier_id: supplierId,
            customer_purchase_order_ids: orderIds
        }
    })
        .done(function (response) {
            if (response.company_id) {
                $('#supplier_order_company_id').val(response.company_id).trigger('change.select2');
            }

            if (response.currency_id) {
                $('#supplier_order_currency_id').val(response.currency_id).trigger('change');
            }

            clearSupplierOrderItemRows();
            (response.items || []).forEach(addSupplierOrderItemRow);
            showEmptySupplierOrderItemsRow();
            calculateSupplierOrderTotals();

            if (!isSilent) {
                if ((response.items || []).length) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Origen cargado',
                        text: 'Items adjudicados al proveedor cargados correctamente.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2500
                    });
                } else {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sin articulos adjudicados',
                        text: 'El proveedor seleccionado no tiene articulos adjudicados en las ordenes cliente seleccionadas.'
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
    row.find('.item-quantity').val(formatSupplierOrderMoney(data.quantity || 1));
    row.find('.item-unit-price').val(formatSupplierOrderMoney(data.unit_price || 0));

    initSupplierOrderSelect2(row);

    supplierOrderItemIndex++;
    refreshSupplierOrderItemIndexes();
    calculateSupplierOrderTotals();
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
                <td colspan="14" class="text-center text-muted py-4">
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
        const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
        const lineSubtotal = quantity * unitPrice;
        const taxAmount = affectIgv ? lineSubtotal * 0.18 : 0;
        const lineTotal = lineSubtotal + taxAmount;

        row.find('.item-line-total').val(formatSupplierOrderMoney(lineTotal));
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
    loadSupplierAccounts(order.supplier_id, order.supplier_account_id);
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
    $('#supplierOrderSideSupplier').text(supplierName(order.supplier));

    clearSupplierOrderItemRows();
    (order.items || []).forEach(addSupplierOrderItemRow);
    showEmptySupplierOrderItemsRow();
    calculateSupplierOrderTotals();
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
        received: ['RECIBIDO', 'badge-primary'],
        cancelled: ['CANCELADO', 'badge-danger'],
        invoiced: ['FACTURADO', 'badge-warning text-dark']
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
                ${escapeSupplierOrderHtml(customerOrder.code || '-')} | ${escapeSupplierOrderHtml(customerName)}
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
    $('#vspo_subtotal').text(`${currencyCode} ${formatSupplierOrderMoney(order.subtotal)}`);
    $('#vspo_igv').text(`${currencyCode} ${formatSupplierOrderMoney(order.igv)}`);
    $('#vspo_total').text(`${currencyCode} ${formatSupplierOrderMoney(order.grand_total)}`);

    const rows = (order.items || []).map(function (item, index) {
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
                <td class="text-right">${formatSupplierOrderMoney(item.quantity)}</td>
                <td class="text-right">${formatSupplierOrderMoney(item.reference_purchase_price)}</td>
                <td class="text-right">${formatSupplierOrderMoney(item.unit_price)}</td>
                <td class="text-right">${formatSupplierOrderMoney(item.tax_amount)}</td>
                <td class="text-right font-weight-bold">${formatSupplierOrderMoney(item.line_total)}</td>
            </tr>
        `;
    }).join('');

    $('#vspo_items_body').html(
        rows || '<tr><td colspan="12" class="text-center text-muted py-3">Sin items registrados</td></tr>'
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

function formatSupplierOrderDate(value) {
    return value ? String(value).substring(0, 10) : '';
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
