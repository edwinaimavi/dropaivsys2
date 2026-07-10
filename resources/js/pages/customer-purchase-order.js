let tableCustomerPurchaseOrder;
let purchaseOrderItemIndex = 0;
let currentCustomerOrderItemRow = null;
let quickBrandReturnTarget = 'row';
let lastQuickCustomerDocumentLookup = '';
let quickCustomerDocumentRequest = null;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $('#customerPurchaseOrderModal').modal({
        backdrop: 'static',
        keyboard: false,
        show: false
    });

    $('#quickCustomerModalForCustomerOrder').modal({
        backdrop: 'static',
        keyboard: false,
        show: false
    });

    initPurchaseOrderSelect2($('#customerPurchaseOrderModal'));
    initPurchaseOrderSelect2($('#quickCustomerModalForCustomerOrder'));
    initCustomerPurchaseOrderTable();

    $(document).on('click', '#btnCreateCustomerPurchaseOrder', function () {
        resetCustomerPurchaseOrderForm();
        $('#customerPurchaseOrderModalLabel').text('Registrar Orden de Compra del Cliente');
        generateCustomerPurchaseOrderCode();
        $('#customerPurchaseOrderModal').modal('show');
    });

    $('#customerPurchaseOrderModal').on('hidden.bs.modal', function () {
        resetCustomerPurchaseOrderForm();
    });

    $(document).on('submit', '#customerPurchaseOrderForm', function (event) {
        event.preventDefault();
        saveCustomerPurchaseOrder(this);
    });

    $(document).on('click', '#btnAddPurchaseOrderItem', function () {
        addPurchaseOrderItemRow();
    });

    $(document).on('click', '.btnRemovePurchaseOrderItem', function () {
        const row = $(this).closest('tr');
        destroyPurchaseOrderRowSelect2(row);
        row.remove();
        refreshPurchaseOrderItemIndexes();
        calculatePurchaseOrderTotals();
        showEmptyPurchaseOrderItemsRow();
    });

    $(document).on(
        'input change',
        '#purchase_order_affect_igv, .item-quantity, .item-unit-price',
        calculatePurchaseOrderTotals
    );

    $(document).on('change', '.item-article-picker', function () {
        applySelectedArticle($(this).closest('tr'));
    });

    $(document).on('click', '.btnQuickCreateArticle', function () {
        currentCustomerOrderItemRow = $(this).closest('tr');
        resetQuickPurchaseOrderArticleForm();
        $('#quickPurchaseOrderArticleModal').modal('show');
    });

    $(document).on('click', '#btnQuickCreateCustomerForOrder', function () {
        resetQuickCustomerForCustomerOrderForm();
        $('#quickCustomerModalForCustomerOrder').modal('show');
    });

    $(document).on('click', '.btnQuickCreateBrand', function () {
        currentCustomerOrderItemRow = $(this).closest('tr');
        quickBrandReturnTarget = 'row';
        resetQuickPurchaseOrderBrandForm();
        $('#quickPurchaseOrderBrandModal').modal('show');
    });

    $(document).on('submit', '#quickPurchaseOrderBrandForm', function (event) {
        event.preventDefault();
        saveQuickPurchaseOrderBrand(this);
    });

    $(document).on('submit', '#quickPurchaseOrderArticleForm', function (event) {
        event.preventDefault();
        saveQuickPurchaseOrderArticle(this);
    });

    $(document).on('submit', '#quickCustomerForCustomerOrderForm', function (event) {
        event.preventDefault();
        saveQuickCustomerForCustomerOrder(this);
    });

    $(document).on('change', '#quick_customer_person_type', syncQuickCustomerDocumentType);

    $(document).on('change', '#quick_customer_document_type', function () {
        lastQuickCustomerDocumentLookup = '';
        updateQuickCustomerDocumentLength();
        maybeConsultQuickCustomerDocument();
    });

    $(document).on('input', '#quick_customer_document_number', function () {
        const sanitized = $(this).val().replace(/\D/g, '').slice(0, getQuickCustomerDocumentLength());
        $(this).val(sanitized);
        maybeConsultQuickCustomerDocument();
    });

    $(document).on('blur', '#quick_customer_document_number', maybeConsultQuickCustomerDocument);

    $(document).on('input', '#quick_article_legal_name', function () {
        syncQuickArticleNames('legal');
    });

    $(document).on('input', '#quick_article_commercial_name', function () {
        syncQuickArticleNames('commercial');
    });

    $('#quickCustomerModalForCustomerOrder, #quickPurchaseOrderBrandModal, #quickPurchaseOrderArticleModal').on('hidden.bs.modal', function () {
        if ($('#customerPurchaseOrderModal').hasClass('show')) {
            $('body').addClass('modal-open');
        }
    });

    $(document).on('change', '#purchase_order_customer_id', function () {
        const customerId = $(this).val();
        const customerText = getPurchaseOrderSelectedText(
            '#purchase_order_customer_id',
            'Seleccione cliente'
        );

        $('#purchaseOrderSideCustomer').text(customerId ? customerText : 'Seleccione cliente');
        $('#purchaseOrderSideBranch').text('Seleccione sucursal');
        resetPurchaseOrderCustomerBranches();

        if (customerId) {
            loadPurchaseOrderCustomerBranches(customerId);
        }
    });

    $(document).on('change', '#purchase_order_customer_branch_id', function () {
        const selected = $(this).find('option:selected');
        const branchName = selected.data('branch-name') || '';
        const address = selected.data('address') || '';
        const reference = selected.data('reference') || '';
        let summary = branchName || 'Seleccione sucursal';

        if (address) {
            summary += ` · ${address}`;
        }

        if (reference) {
            summary += ` (${reference})`;
        }

        $('#purchaseOrderSideBranch').text(summary);
    });

    $(document).on('change', '#purchase_order_currency_id', updatePurchaseOrderCurrency);

    $(document).on('click', '#btnFilterQuote', function () {
        const quoteId = $('#purchase_order_quote_id').val();

        if (!quoteId) {
            Swal.fire({
                icon: 'info',
                title: 'Cotización opcional',
                text: 'Seleccione una cotización para cargar datos automáticamente, o agregue artículos manualmente.'
            });
            return;
        }

        const hasItems = $('#purchaseOrderItemsTbody tr.purchase-order-item-row').length > 0;

        if (!hasItems) {
            loadPurchaseOrderQuoteItems(quoteId);
            return;
        }

        Swal.fire({
            icon: 'question',
            title: 'Reemplazar ítems',
            text: 'Los ítems actuales serán reemplazados por los de la cotización seleccionada.',
            showCancelButton: true,
            confirmButtonText: 'Sí, reemplazar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#0d6efd'
        }).then(function (result) {
            if (result.isConfirmed) {
                loadPurchaseOrderQuoteItems(quoteId);
            }
        });
    });

    $(document).on('click', '.editCustomerPurchaseOrder', function () {
        loadCustomerPurchaseOrderForEdit($(this).data('id'));
    });

    $(document).on('click', '.viewCustomerPurchaseOrder', function () {
        loadCustomerPurchaseOrderDetail($(this).data('id'));
    });

    $(document).on('click', '.deleteCustomerPurchaseOrder', function () {
        deleteCustomerPurchaseOrder($(this).data('id'));
    });
});

$(document).on(
    'mousedown.purchaseOrderSelect2 click.purchaseOrderSelect2',
    '.select2-container, .select2-dropdown, .select2-search__field',
    function (event) {
        event.stopPropagation();
    }
);

function initCustomerPurchaseOrderTable() {
    tableCustomerPurchaseOrder = $('#tableCustomerPurchaseOrder').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.customerPurchaseOrderList,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'code', name: 'code' },
            { data: 'purchase_order_number', name: 'purchase_order_number', defaultContent: '-' },
            { data: 'quote', name: 'quote.quote_number', orderable: false },
            { data: 'customer', name: 'customer.business_name', orderable: false },
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

function initPurchaseOrderSelect2(scope) {
    if (!$.fn.select2) {
        return;
    }

    const container = scope && scope.length ? scope : $('#customerPurchaseOrderModal');
    const parentModal = container.hasClass('modal')
        ? container
        : (container.closest('.modal').length ? container.closest('.modal') : $('#customerPurchaseOrderModal'));

    container.find('select').each(function () {
        const select = $(this);

        if (select.hasClass('select2-hidden-accessible')) {
            return;
        }

        const config = {
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: parentModal,
            placeholder: select.find('option:first').text().trim(),
            allowClear: !select.prop('required')
        };

        if (
            select.attr('id') === 'purchase_order_customer_id'
            && window.routes.customerPurchaseOrderCustomersSearch
        ) {
            config.ajax = {
                url: window.routes.customerPurchaseOrderCustomersSearch,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (response) {
                    return { results: response.results || [] };
                },
                cache: true
            };
            config.minimumInputLength = 0;
        }

        select.select2(config);
    });
}

function destroyPurchaseOrderRowSelect2(row) {
    if (!$.fn.select2) {
        return;
    }

    row.find('select.select2-hidden-accessible').select2('destroy');
}

function resetCustomerPurchaseOrderForm() {
    const form = $('#customerPurchaseOrderForm');

    form[0].reset();
    clearCustomerPurchaseOrderErrors();

    $('#customer_purchase_order_id').val('');
    $('#purchase_order_code').val('');
    $('#purchase_order_status').val('registered');
    $('#customerPurchaseOrderModalLabel').text('Registrar Orden de Compra del Cliente');
    $('#btnSaveCustomerPurchaseOrder')
        .prop('disabled', false)
        .html('<i class="fas fa-save mr-1"></i> Guardar');

    $('#purchaseOrderItemsTbody tr.purchase-order-item-row').each(function () {
        destroyPurchaseOrderRowSelect2($(this));
    });

    purchaseOrderItemIndex = 0;
    $('#purchaseOrderItemsTbody').empty();
    showEmptyPurchaseOrderItemsRow();

    $('#purchase_order_type').val('articles').trigger('change.select2');
    $('#purchase_order_billing_type').val('local').trigger('change.select2');
    $('#purchase_order_affect_igv').val('0').trigger('change.select2');
    $('#purchase_order_customer_id').val('').trigger('change.select2');
    $('#purchase_order_quote_id').val('').trigger('change.select2');
    $('#purchase_order_company_id').val('').trigger('change.select2');

    setDefaultPurchaseOrderCurrency();
    configurePurchaseOrderQuoteOptions();
    resetPurchaseOrderCustomerBranches();

    $('#purchaseOrderSideCustomer').text('Seleccione cliente');
    $('#purchaseOrderSideBranch').text('Seleccione sucursal');
    calculatePurchaseOrderTotals();
}

function generateCustomerPurchaseOrderCode() {
    $('#purchase_order_code').val('Generando...');

    $.get(window.routes.customerPurchaseOrderGenerateCode)
        .done(function (response) {
            $('#purchase_order_code').val(response.code || '');
        })
        .fail(function (xhr) {
            $('#purchase_order_code').val('');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo generar el código.'
            });
        });
}

function saveCustomerPurchaseOrder(formElement) {
    clearCustomerPurchaseOrderErrors();
    refreshPurchaseOrderItemIndexes();
    calculatePurchaseOrderTotals();

    if ($('#purchaseOrderItemsTbody tr.purchase-order-item-row').length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Agregue al menos un ítem',
            text: 'La orden debe contener productos o servicios adjudicados.'
        });
        return;
    }

    const id = $('#customer_purchase_order_id').val();
    const formData = new FormData(formElement);
    const button = $('#btnSaveCustomerPurchaseOrder');
    const url = id
        ? `${window.routes.customerPurchaseOrderUpdate}/${id}`
        : window.routes.customerPurchaseOrderStore;

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
            $('#customerPurchaseOrderModal').modal('hide');
            tableCustomerPurchaseOrder.ajax.reload(null, false);

            Swal.fire({
                icon: 'success',
                title: response.message || 'Orden guardada correctamente.',
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
                showCustomerPurchaseOrderErrors(xhr.responseJSON.errors || {});
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

function loadPurchaseOrderQuoteItems(quoteId) {
    const button = $('#btnFilterQuote');
    const url = window.routes.customerPurchaseOrderQuoteItems.replace(':id', quoteId);

    button
        .prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Cargando...');

    $.get(url)
        .done(function (response) {
            $('#purchase_order_quote_id').val(response.quote_id || '').trigger('change.select2');
            $('#purchase_order_company_id').val(response.company_id || '').trigger('change.select2');
            $('#purchase_order_currency_id').val(response.currency_id || '').trigger('change');
            $('#purchase_order_billing_type').val(response.billing_type || 'local').trigger('change.select2');
            $('#purchase_order_affect_igv').val(response.affect_igv ? '1' : '0').trigger('change.select2');

            $('#purchase_order_customer_id')
                .val(response.customer_id || '')
                .trigger('change.select2');

            $('#purchaseOrderSideCustomer').text(
                getPurchaseOrderSelectedText(
                    '#purchase_order_customer_id',
                    'Seleccione cliente'
                )
            );

            if (response.customer_id) {
                loadPurchaseOrderCustomerBranches(response.customer_id, response.customer_branch_id);
            }

            clearPurchaseOrderItemRows();

            (response.items || []).forEach(function (item) {
                addPurchaseOrderItemRow(item);
            });

            showEmptyPurchaseOrderItemsRow();
            calculatePurchaseOrderTotals();

            Swal.fire({
                icon: 'success',
                title: 'Cotización cargada',
                text: 'Elimina los ítems que el cliente no adjudicó.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2800
            });
        })
        .fail(function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudieron cargar los ítems de la cotización.'
            });
        })
        .always(function () {
            button
                .prop('disabled', false)
                .html('<i class="fas fa-filter mr-1"></i> Filtrar Cotización');
        });
}

function loadPurchaseOrderCustomerBranches(customerId, selectedBranchId = null) {
    const select = $('#purchase_order_customer_branch_id');
    const url = window.routes.customerPurchaseOrderCustomerBranches.replace(':id', customerId);

    select
        .prop('disabled', true)
        .html('<option value="">Cargando sucursales...</option>')
        .trigger('change.select2');

    $.get(url)
        .done(function (response) {
            const branches = response.branches || [];
            let options = '<option value="">Seleccione sucursal</option>';

            branches.forEach(function (branch) {
                const main = Number(branch.is_main) === 1 ? ' - PRINCIPAL' : '';

                options += `
                    <option value="${escapePurchaseOrderHtml(branch.id)}"
                        data-branch-name="${escapePurchaseOrderHtml(branch.branch_name || '')}"
                        data-address="${escapePurchaseOrderHtml(branch.address || '')}"
                        data-reference="${escapePurchaseOrderHtml(branch.reference || '')}"
                        data-payment-condition="${escapePurchaseOrderHtml(branch.payment_condition || '')}">
                        ${escapePurchaseOrderHtml(branch.branch_name || 'SIN NOMBRE')}${main}
                    </option>
                `;
            });

            select
                .html(options)
                .prop('disabled', branches.length === 0);

            if (selectedBranchId) {
                select.val(String(selectedBranchId));
            } else if (branches.length === 1) {
                select.val(String(branches[0].id));
            }

            select.trigger('change');
        })
        .fail(function () {
            select
                .prop('disabled', true)
                .html('<option value="">Error al cargar sucursales</option>')
                .trigger('change.select2');
        });
}

function resetPurchaseOrderCustomerBranches() {
    $('#purchase_order_customer_branch_id')
        .prop('disabled', true)
        .html('<option value="">Seleccione cliente primero</option>')
        .trigger('change.select2');
}

function addPurchaseOrderItemRow(data = {}) {
    $('#purchaseOrderItemsEmptyRow').remove();

    const html = $('#purchaseOrderItemRowTemplate')
        .html()
        .replaceAll('__INDEX__', purchaseOrderItemIndex);

    $('#purchaseOrderItemsTbody').append(html);

    const row = $('#purchaseOrderItemsTbody tr.purchase-order-item-row').last();

    row.find('.item-quote-item-id').val(data.quote_item_id || '');
    row.find('.item-market-study-item-id').val(data.market_study_item_id || '');
    row.find('.item-article-id').val(data.article_id || '');
    row.find('.item-article-code').val(data.article_code || '');
    row.find('.item-billing-name').val(data.billing_name_snapshot || '');
    row.find('.item-article-picker').val(data.article_id || '');
    row.find('.item-note').val(data.note || '');
    row.find('.item-unit-id').val(data.unit_id || '');
    row.find('.item-presentation-id').val(data.presentation_id || '');
    row.find('.item-brand-id').val(data.brand_id || '');
    row.find('.item-origin').val(data.origin || '');
    row.find('.item-expiration-date').val(formatPurchaseOrderDate(data.expiration_date));
    row.find('.item-cost-type').val(data.cost_type || 'PESO');
    row.find('.item-quoted-quantity').val(formatPurchaseOrderMoney(data.quoted_quantity || data.quantity || 0));
    row.find('.item-quantity').val(formatPurchaseOrderMoney(data.quantity || 1));
    row.find('.item-unit-price').val(formatPurchaseOrderMoney(data.unit_price || 0));
    row.find('.item-subtotal').val(formatPurchaseOrderMoney(data.subtotal || 0));
    row.find('.item-tax-amount').val(formatPurchaseOrderMoney(data.tax_amount || 0));
    row.find('.item-line-total').val(formatPurchaseOrderMoney(data.line_total || 0));

    initPurchaseOrderSelect2(row);

    purchaseOrderItemIndex++;
    refreshPurchaseOrderItemIndexes();
    calculatePurchaseOrderTotals();
}

function applySelectedArticle(row) {
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

function resetQuickPurchaseOrderBrandForm() {
    const form = $('#quickPurchaseOrderBrandForm');
    form[0].reset();
    clearQuickPurchaseOrderErrors('#quickPurchaseOrderBrandForm', '#quickPurchaseOrderBrandErrors');
}

function resetQuickPurchaseOrderArticleForm() {
    const form = $('#quickPurchaseOrderArticleForm');
    form[0].reset();
    clearQuickPurchaseOrderErrors('#quickPurchaseOrderArticleForm', '#quickPurchaseOrderArticleErrors');
    $('#quick_article_code').val('Cargando...');
    $('#quick_article_code_type').val('SIGA/SISMED');

    loadQuickArticleCode();
}

function resetQuickCustomerForCustomerOrderForm() {
    const form = $('#quickCustomerForCustomerOrderForm');
    form[0].reset();
    clearQuickPurchaseOrderErrors('#quickCustomerForCustomerOrderForm', '#quickCustomerForCustomerOrderErrors');
    lastQuickCustomerDocumentLookup = '';

    if (quickCustomerDocumentRequest) {
        quickCustomerDocumentRequest.abort();
        quickCustomerDocumentRequest = null;
    }

    $('#quick_customer_person_type').val('juridica').trigger('change.select2');
    $('#quick_customer_document_type').val('RUC').trigger('change.select2');
    $('#quick_customer_status').val('1').trigger('change.select2');
    $('#quick_customer_withholding_agent').val('0').trigger('change.select2');
    $('#quickCustomerDocumentStatus').removeClass('text-danger text-success').addClass('text-muted').text('');
    updateQuickCustomerDocumentLength();
}

function syncQuickCustomerDocumentType() {
    const personType = $('#quick_customer_person_type').val();

    $('#quick_customer_document_type')
        .val(personType === 'juridica' ? 'RUC' : 'DNI')
        .trigger('change.select2');

    lastQuickCustomerDocumentLookup = '';
    updateQuickCustomerDocumentLength();
    maybeConsultQuickCustomerDocument();
}

function updateQuickCustomerDocumentLength() {
    const maxLength = getQuickCustomerDocumentLength();
    $('#quick_customer_document_number').attr('maxlength', maxLength);
}

function getQuickCustomerDocumentLength() {
    return $('#quick_customer_document_type').val() === 'DNI' ? 8 : 11;
}

function maybeConsultQuickCustomerDocument() {
    const documentType = $('#quick_customer_document_type').val();
    const documentNumber = ($('#quick_customer_document_number').val() || '').trim();
    const expectedLength = documentType === 'DNI' ? 8 : 11;
    const lookupKey = `${documentType}:${documentNumber}`;

    if (!documentNumber) {
        $('#quickCustomerDocumentStatus').removeClass('text-danger text-success').addClass('text-muted').text('');
        return;
    }

    if (documentNumber.length < expectedLength) {
        $('#quickCustomerDocumentStatus')
            .removeClass('text-danger text-success')
            .addClass('text-muted')
            .text(documentType === 'DNI' ? 'El DNI debe tener 8 dígitos.' : 'El RUC debe tener 11 dígitos.');
        return;
    }

    if (documentNumber.length !== expectedLength || lookupKey === lastQuickCustomerDocumentLookup) {
        return;
    }

    lastQuickCustomerDocumentLookup = lookupKey;
    consultQuickCustomerDocument(documentType, documentNumber);
}

function consultQuickCustomerDocument(documentType, documentNumber) {
    if (!window.routes.customerPurchaseOrderCustomerDocumentConsult) {
        return;
    }

    if (quickCustomerDocumentRequest) {
        quickCustomerDocumentRequest.abort();
    }

    const url = window.routes.customerPurchaseOrderCustomerDocumentConsult.replace('DOC_PLACEHOLDER', documentNumber);

    $('#quickCustomerDocumentStatus')
        .removeClass('text-danger text-success')
        .addClass('text-muted')
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Consultando documento...');

    quickCustomerDocumentRequest = $.get(url)
        .done(function (response) {
            if (!response.status) {
                $('#quickCustomerDocumentStatus')
                    .removeClass('text-success')
                    .addClass('text-danger')
                    .text(response.message || 'No se encontró el documento. Puede llenar los datos manualmente.');
                return;
            }

            fillQuickCustomerFromDocument(response, documentType);
            $('#quickCustomerDocumentStatus')
                .removeClass('text-danger text-muted')
                .addClass('text-success')
                .text('Documento consultado correctamente.');
        })
        .fail(function (xhr) {
            if (xhr.statusText === 'abort') {
                return;
            }

            $('#quickCustomerDocumentStatus')
                .removeClass('text-success text-muted')
                .addClass('text-danger')
                .text(xhr.responseJSON?.message || 'No se pudo consultar el documento. Puede llenar los datos manualmente.');
        })
        .always(function () {
            quickCustomerDocumentRequest = null;
        });
}

function fillQuickCustomerFromDocument(response, documentType) {
    const data = response.data || {};

    if ((response.type || documentType) === 'DNI') {
        const names = [
            data.nombres,
            data.apellidoPaterno,
            data.apellidoMaterno
        ].filter(Boolean).join(' ');

        if (names) {
            $('#quick_customer_business_name').val(names);
        }

        if (data.direccion) {
            $('#quick_customer_address').val(data.direccion);
        }

        return;
    }

    const businessName = data.razonSocial || data.nombre || '';
    const address = data.direccion || data.direccionCompleta || '';

    if (businessName) {
        $('#quick_customer_business_name').val(businessName);
    }

    if (address) {
        $('#quick_customer_address').val(address);
    }
}

function saveQuickCustomerForCustomerOrder(formElement) {
    clearQuickPurchaseOrderErrors('#quickCustomerForCustomerOrderForm', '#quickCustomerForCustomerOrderErrors');

    const button = $('#btnSaveQuickCustomerForCustomerOrder');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.customerPurchaseOrderCustomersQuickStore,
        type: 'POST',
        data: new FormData(formElement),
        processData: false,
        contentType: false,
        success: function (response) {
            selectCustomerForPurchaseOrder(response.customer, response.branch);
            $('#quickCustomerModalForCustomerOrder').modal('hide');

            Swal.fire({
                icon: 'success',
                title: 'Cliente registrado y seleccionado correctamente.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2800
            });
        },
        error: function (xhr) {
            const response = xhr.responseJSON || {};

            if (xhr.status === 409 && response.customer) {
                selectCustomerForPurchaseOrder(response.customer, response.branch);
                $('#quickCustomerModalForCustomerOrder').modal('hide');

                Swal.fire({
                    icon: 'info',
                    title: response.message || 'Este cliente ya está registrado.',
                    text: 'Se seleccionó el cliente existente en la orden.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3500
                });
                return;
            }

            handleQuickPurchaseOrderError(
                xhr,
                '#quickCustomerForCustomerOrderForm',
                '#quickCustomerForCustomerOrderErrors',
                'No se pudo guardar el cliente.'
            );
        },
        complete: function () {
            button.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar cliente');
        }
    });
}

function saveQuickPurchaseOrderBrand(formElement) {
    clearQuickPurchaseOrderErrors('#quickPurchaseOrderBrandForm', '#quickPurchaseOrderBrandErrors');

    const button = $('#btnSaveQuickPurchaseOrderBrand');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.quickStoreBrand,
        type: 'POST',
        data: new FormData(formElement),
        processData: false,
        contentType: false,
        success: function (response) {
            const brand = response.data || {};
            addBrandOptionToPurchaseOrderSelects(brand);

            if (quickBrandReturnTarget === 'row' && currentCustomerOrderItemRow && currentCustomerOrderItemRow.length) {
                currentCustomerOrderItemRow
                    .find('.item-brand-id')
                    .val(String(brand.id))
                    .trigger('change.select2');
            }

            $('#quickPurchaseOrderBrandModal').modal('hide');

            Swal.fire({
                icon: 'success',
                title: response.message || 'Marca registrada correctamente.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500
            });
        },
        error: function (xhr) {
            handleQuickPurchaseOrderError(
                xhr,
                '#quickPurchaseOrderBrandForm',
                '#quickPurchaseOrderBrandErrors',
                'No se pudo guardar la marca.'
            );
        },
        complete: function () {
            button.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar marca');
        }
    });
}

function saveQuickPurchaseOrderArticle(formElement) {
    clearQuickPurchaseOrderErrors('#quickPurchaseOrderArticleForm', '#quickPurchaseOrderArticleErrors');
    normalizeQuickArticleNames();

    const button = $('#btnSaveQuickPurchaseOrderArticle');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.quickStoreArticle,
        type: 'POST',
        data: new FormData(formElement),
        processData: false,
        contentType: false,
        success: function (response) {
            const article = response.data || {};
            addArticleOptionToPurchaseOrderSelects(article);

            if (currentCustomerOrderItemRow && currentCustomerOrderItemRow.length) {
                applyQuickArticleToPurchaseOrderRow(currentCustomerOrderItemRow, article);
            }

            $('#quickPurchaseOrderArticleModal').modal('hide');

            Swal.fire({
                icon: 'success',
                title: response.message || 'Artículo registrado correctamente.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500
            });
        },
        error: function (xhr) {
            handleQuickPurchaseOrderError(
                xhr,
                '#quickPurchaseOrderArticleForm',
                '#quickPurchaseOrderArticleErrors',
                'No se pudo guardar el artículo.'
            );
        },
        complete: function () {
            button.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar artículo');
        }
    });
}

function addBrandOptionToPurchaseOrderSelects(brand) {
    if (!brand.id) {
        return;
    }

    const text = brand.name || brand.description || 'MARCA';
    $('.item-brand-id').each(function () {
        const select = $(this);

        if (!select.find(`option[value="${brand.id}"]`).length) {
            select.append(new Option(text, brand.id, false, false));
        }
    });
}

function addArticleOptionToPurchaseOrderSelects(article) {
    if (!article.id) {
        return;
    }

    const text = `${article.code || ''} | ${article.billing_name || article.name || ''}`.trim();
    $('.item-article-picker').each(function () {
        const select = $(this);
        let option = select.find(`option[value="${article.id}"]`);

        if (!option.length) {
            option = $(new Option(text, article.id, false, false));
            select.append(option);
        }

        option
            .attr('data-code', article.code || '')
            .attr('data-billing-name', article.billing_name || article.name || '')
            .attr('data-unit-id', article.unit_id || '')
            .attr('data-presentation-id', article.presentation_id || '')
            .attr('data-brand-id', article.brand_id || '');
    });
}

function selectCustomerForPurchaseOrder(customer, branch = null) {
    if (!customer || !customer.id) {
        return;
    }

    const select = $('#purchase_order_customer_id');
    let option = select.find(`option[value="${customer.id}"]`);

    if (!option.length) {
        option = new Option(customer.text || 'Cliente', customer.id, true, true);
        select.append(option);
    } else {
        option.text(customer.text || option.text());
    }

    select
        .val(String(customer.id))
        .trigger('change.select2');

    $('#purchaseOrderSideCustomer').text(customer.text || 'Seleccione cliente');
    $('#purchaseOrderSideBranch').text('Seleccione sucursal');
    loadPurchaseOrderCustomerBranches(customer.id, branch?.id || null);
}

function applyQuickArticleToPurchaseOrderRow(row, article) {
    row.find('.item-article-picker')
        .val(String(article.id))
        .trigger('change.select2');
    row.find('.item-article-id').val(article.id || '');
    row.find('.item-article-code').val(article.code || '');
    row.find('.item-billing-name').val(article.billing_name || article.name || '');
    calculatePurchaseOrderTotals();
}

function loadQuickArticleCode() {
    $.get(window.routes.generateArticleCode)
        .done(function (response) {
            $('#quick_article_code').val(response.code || '');
        })
        .fail(function () {
            $('#quick_article_code').val('');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo generar el código del artículo.'
            });
        });
}

function normalizeQuickArticleNames() {
    const legal = ($('#quick_article_legal_name').val() || '').trim();
    const commercial = ($('#quick_article_commercial_name').val() || '').trim();
    const billing = ($('#quick_article_billing_name').val() || '').trim();
    const baseName = legal || commercial || billing;

    if (!legal && baseName) {
        $('#quick_article_legal_name').val(baseName);
    }

    if (!commercial && baseName) {
        $('#quick_article_commercial_name').val(baseName);
    }

    if (!billing && baseName) {
        $('#quick_article_billing_name').val(baseName);
    }
}

function syncQuickArticleNames(source) {
    const legal = ($('#quick_article_legal_name').val() || '').trim();
    const commercial = ($('#quick_article_commercial_name').val() || '').trim();
    const billing = ($('#quick_article_billing_name').val() || '').trim();

    if (source === 'legal') {
        if (!commercial) {
            $('#quick_article_commercial_name').val(legal);
        }

        if (!billing) {
            $('#quick_article_billing_name').val(legal);
        }
    }

    if (source === 'commercial' && !billing) {
        $('#quick_article_billing_name').val(commercial);
    }
}

function clearQuickPurchaseOrderErrors(formSelector, errorSelector) {
    $(formSelector).find('.is-invalid').removeClass('is-invalid');
    $(formSelector).find('.invalid-feedback').text('');
    $(errorSelector).addClass('d-none').empty();
}

function handleQuickPurchaseOrderError(xhr, formSelector, errorSelector, fallbackMessage) {
    if (xhr.status === 422) {
        const errors = xhr.responseJSON?.errors || {};
        const messages = [];

        Object.entries(errors).forEach(function ([field, fieldMessages]) {
            const input = $(formSelector).find(`[name="${field}"]`);
            const message = fieldMessages[0];

            if (input.length) {
                input.addClass('is-invalid');
                input.closest('.form-group').find('.invalid-feedback').first().text(message);
            }

            messages.push(message);
        });

        $(errorSelector)
            .removeClass('d-none')
            .html(`<ul class="mb-0 pl-3">${messages.map(message => `<li>${escapePurchaseOrderHtml(message)}</li>`).join('')}</ul>`);
        return;
    }

    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: xhr.responseJSON?.message || fallbackMessage
    });
}

function clearPurchaseOrderItemRows() {
    $('#purchaseOrderItemsTbody tr.purchase-order-item-row').each(function () {
        destroyPurchaseOrderRowSelect2($(this));
    });

    $('#purchaseOrderItemsTbody').empty();
    purchaseOrderItemIndex = 0;
}

function refreshPurchaseOrderItemIndexes() {
    $('#purchaseOrderItemsTbody tr.purchase-order-item-row').each(function (index) {
        const row = $(this);
        row.find('.purchase-order-item-index').text(index + 1);

        row.find('[name]').each(function () {
            this.name = this.name.replace(/items\[\d+]\[/, `items[${index}][`);
        });
    });

    purchaseOrderItemIndex = $('#purchaseOrderItemsTbody tr.purchase-order-item-row').length;
}

function showEmptyPurchaseOrderItemsRow() {
    if ($('#purchaseOrderItemsTbody tr.purchase-order-item-row').length === 0) {
        $('#purchaseOrderItemsTbody').html(`
            <tr id="purchaseOrderItemsEmptyRow">
                <td colspan="13" class="text-center text-muted py-4">
                    <i class="fas fa-box-open d-block mb-2"></i>
                    No hay ítems adjudicados.
                </td>
            </tr>
        `);
    }
}

function calculatePurchaseOrderTotals() {
    let subtotal = 0;
    const affectIgv = $('#purchase_order_affect_igv').val() === '1';

    $('#purchaseOrderItemsTbody tr.purchase-order-item-row').each(function () {
        const row = $(this);
        const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
        const lineSubtotal = quantity * unitPrice;
        const taxAmount = affectIgv ? lineSubtotal * 0.18 : 0;
        const lineTotal = lineSubtotal + taxAmount;

        row.find('.item-subtotal').val(formatPurchaseOrderMoney(lineSubtotal));
        row.find('.item-tax-amount').val(formatPurchaseOrderMoney(taxAmount));
        row.find('.item-line-total').val(formatPurchaseOrderMoney(lineTotal));

        subtotal += lineSubtotal;
    });

    const subtotalExonerated = affectIgv ? 0 : subtotal;
    const subtotalTaxed = affectIgv ? subtotal : 0;
    const igv = affectIgv ? subtotal * 0.18 : 0;
    const grandTotal = subtotal + igv;

    $('#purchase_order_subtotal_exonerated').val(formatPurchaseOrderMoney(subtotalExonerated));
    $('#purchase_order_subtotal_taxed').val(formatPurchaseOrderMoney(subtotalTaxed));
    $('#purchase_order_igv').val(formatPurchaseOrderMoney(igv));
    $('#purchase_order_grand_total').val(formatPurchaseOrderMoney(grandTotal));
    $('#purchaseOrderSideGrandTotal').text(formatPurchaseOrderMoney(grandTotal));
}

function loadCustomerPurchaseOrderForEdit(id) {
    clearCustomerPurchaseOrderErrors();

    $.get(`${window.routes.customerPurchaseOrderShow}/${id}`)
        .done(function (response) {
            fillCustomerPurchaseOrderForm(response.data);
            $('#customerPurchaseOrderModalLabel').text('Editar Orden de Compra del Cliente');
            $('#btnSaveCustomerPurchaseOrder')
                .html('<i class="fas fa-save mr-1"></i> Actualizar');
            $('#customerPurchaseOrderModal').modal('show');
        })
        .fail(function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo cargar la orden.'
            });
        });
}

function fillCustomerPurchaseOrderForm(order) {
    resetCustomerPurchaseOrderForm();

    $('#customer_purchase_order_id').val(order.id || '');
    $('#purchase_order_code').val(order.code || '');
    $('#purchase_order_status').val(order.status === 'approved' ? 'registered' : (order.status || 'registered'));
    $('#purchase_order_company_id').val(order.company_id || '').trigger('change.select2');
    $('#purchase_order_type').val(order.order_type || 'articles').trigger('change.select2');
    $('#purchase_order_number').val(order.purchase_order_number || '');
    $('#purchase_order_quote_id').val(order.quote_id || '').trigger('change.select2');
    configurePurchaseOrderQuoteOptions(order.quote_id || null);
    $('#purchase_order_currency_id').val(order.currency_id || '').trigger('change');
    $('#purchase_order_customer_id').val(order.customer_id || '').trigger('change.select2');
    $('#purchase_order_notification_date').val(formatPurchaseOrderDate(order.notification_date));
    $('#purchase_order_delivery_start_date').val(formatPurchaseOrderDate(order.delivery_start_date));
    $('#purchase_order_delivery_end_date').val(formatPurchaseOrderDate(order.delivery_end_date));
    $('#purchase_order_siaf_file_number').val(order.siaf_file_number || '');
    $('#purchase_order_acquisition_chart_number').val(order.acquisition_chart_number || '');
    $('#purchase_order_process_type').val(order.process_type || '');
    $('#purchase_order_billing_type').val(order.billing_type || 'local').trigger('change.select2');
    $('#purchase_order_affect_igv').val(order.affect_igv ? '1' : '0').trigger('change.select2');
    $('#purchase_order_observations').val(order.observations || '');

    $('#purchaseOrderSideCustomer').text(
        order.customer
            ? customerPurchaseOrderName(order.customer)
            : 'Seleccione cliente'
    );

    if (order.customer_id) {
        loadPurchaseOrderCustomerBranches(order.customer_id, order.customer_branch_id);
    }

    clearPurchaseOrderItemRows();
    (order.items || []).forEach(addPurchaseOrderItemRow);
    showEmptyPurchaseOrderItemsRow();
    calculatePurchaseOrderTotals();
}

function loadCustomerPurchaseOrderDetail(id) {
    $.get(`${window.routes.customerPurchaseOrderShow}/${id}`)
        .done(function (response) {
            fillCustomerPurchaseOrderDetail(response.data);
            $('#viewCustomerPurchaseOrderModal').modal('show');
        })
        .fail(function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo cargar el detalle.'
            });
        });
}

function fillCustomerPurchaseOrderDetail(order) {
    const currencyCode = order.currency?.code || '';
    const currencySymbol = order.currency?.symbol || '';
    const statuses = {
        approved: ['REGISTRADA', 'badge-secondary'],
        registered: ['REGISTRADA', 'badge-secondary'],
        in_purchase: ['EN COMPRA', 'badge-warning text-dark'],
        partial_entered: ['INGRESO PARCIAL', 'badge-info'],
        entered: ['ABASTECIDA', 'badge-success'],
        cancelled: ['CANCELADA', 'badge-danger'],
        delivered: ['ENTREGADA', 'badge-primary'],
        invoiced: ['FACTURADA', 'badge-info']
    };
    const status = statuses[order.status] || [String(order.status || '').toUpperCase(), 'badge-secondary'];

    $('#vpo_code').text(order.code || '—');
    $('#vpo_purchase_order_number').text(order.purchase_order_number || 'Sin número de cliente');
    $('#vpo_status').text(status[0]).attr('class', `badge ${status[1]} px-3 py-2`);
    $('#vpo_customer').text(customerPurchaseOrderName(order.customer));
    $('#vpo_branch').text(order.customer_branch?.branch_name || 'Sin sucursal');
    $('#vpo_company').text(order.company?.trade_name || order.company?.business_name || '—');
    $('#vpo_quote').text(order.quote?.quote_number || 'Sin cotización');
    $('#vpo_currency').text(
        [currencyCode, order.currency?.description].filter(Boolean).join(' | ') || '—'
    );
    $('#vpo_currency_symbol').text(currencySymbol);
    $('#vpo_grand_total').text(formatPurchaseOrderMoney(order.grand_total));
    $('#vpo_order_type').text(order.order_type === 'services' ? 'SERVICIOS' : 'ARTÍCULOS');
    $('#vpo_billing_type').text(order.billing_type === 'export' ? 'EXPORTACIÓN' : 'LOCAL');
    $('#vpo_affect_igv').text(order.affect_igv ? 'SÍ' : 'NO');
    $('#vpo_notification_date').text(formatPurchaseOrderDisplayDate(order.notification_date));
    $('#vpo_delivery_start_date').text(formatPurchaseOrderDisplayDate(order.delivery_start_date));
    $('#vpo_delivery_end_date').text(formatPurchaseOrderDisplayDate(order.delivery_end_date));
    $('#vpo_siaf').text(order.siaf_file_number || '—');
    $('#vpo_chart').text(order.acquisition_chart_number || '—');
    $('#vpo_process').text(order.process_type || '—');
    $('#vpo_observations').text(order.observations || 'Sin observaciones');
    $('#vpo_subtotal_exonerated').text(`${currencyCode} ${formatPurchaseOrderMoney(order.subtotal_exonerated)}`);
    $('#vpo_subtotal_taxed').text(`${currencyCode} ${formatPurchaseOrderMoney(order.subtotal_taxed)}`);
    $('#vpo_igv').text(`${currencyCode} ${formatPurchaseOrderMoney(order.igv)}`);
    $('#vpo_total').text(`${currencyCode} ${formatPurchaseOrderMoney(order.grand_total)}`);

    const items = order.items || [];
    const supplyStatuses = {
        registered: ['REGISTRADA', 'badge-secondary'],
        in_purchase: ['EN COMPRA', 'badge-warning text-dark'],
        partial_entered: ['INGRESO PARCIAL', 'badge-info'],
        entered: ['ABASTECIDA', 'badge-success']
    };
    const rows = items.map(function (item, index) {
        const itemStatus = supplyStatuses[item.supply_status] || ['PENDIENTE', 'badge-secondary'];

        return `
            <tr>
                <td>
                    <div class="font-weight-bold">${escapePurchaseOrderHtml(item.billing_name_snapshot || '—')}</div>
                    <small class="text-muted">
                        ${escapePurchaseOrderHtml(item.unit?.abbreviation || item.unit?.description || '—')}
                        ${item.presentation?.description ? ` | ${escapePurchaseOrderHtml(item.presentation.description)}` : ''}
                        ${item.brand?.description ? ` | ${escapePurchaseOrderHtml(item.brand.description)}` : ''}
                    </small>
                </td>
                <td class="text-right">${formatPurchaseOrderMoney(item.requested_quantity ?? item.quantity)}</td>
                <td class="text-right">${formatPurchaseOrderMoney(item.purchase_quantity)}</td>
                <td class="text-right">${formatPurchaseOrderMoney(item.entered_quantity)}</td>
                <td class="text-right font-weight-bold">${formatPurchaseOrderMoney(item.pending_quantity)}</td>
                <td class="text-center">
                    <span class="badge ${itemStatus[1]} px-2 py-1">${itemStatus[0]}</span>
                </td>
            </tr>
        `;
    }).join('');

    $('#vpo_items_body').html(
        rows || '<tr><td colspan="6" class="text-center text-muted py-3">Sin items registrados</td></tr>'
    );
}

function deleteCustomerPurchaseOrder(id) {
    Swal.fire({
        icon: 'warning',
        title: '¿Eliminar orden de compra?',
        text: 'La orden quedará eliminada de forma lógica.',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: `${window.routes.customerPurchaseOrderDelete}/${id}`,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: function (response) {
                tableCustomerPurchaseOrder.ajax.reload(null, false);
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

function clearCustomerPurchaseOrderErrors() {
    $('#customerPurchaseOrderForm .is-invalid').removeClass('is-invalid');
    $('#customerPurchaseOrderForm .select2-selection').removeClass('border-danger');
    $('#customerPurchaseOrderForm .invalid-feedback').text('');
    $('#customerPurchaseOrderErrors').addClass('d-none').empty();
}

function showCustomerPurchaseOrderErrors(errors) {
    const errorMessages = [];

    Object.entries(errors).forEach(function ([field, fieldMessages]) {
        const input = $(`[name="${field}"]`);
        const message = fieldMessages[0];

        if (input.length) {
            input.addClass('is-invalid');

            if (input.hasClass('select2-hidden-accessible')) {
                input.next('.select2-container').find('.select2-selection').addClass('border-danger');
            }

            input.closest('.form-group, td').find('.invalid-feedback').first().text(message);
        }

        errorMessages.push(message);
    });

    $('#customerPurchaseOrderErrors')
        .removeClass('d-none')
        .html(`<ul class="mb-0 pl-3">${errorMessages.map(
            message => `<li>${escapePurchaseOrderHtml(message)}</li>`
        ).join('')}</ul>`);
}

function updatePurchaseOrderCurrency() {
    const selected = $('#purchase_order_currency_id option:selected');
    const code = selected.data('code') || 'PEN';
    const symbol = selected.data('symbol') || 'S/';

    $('.purchase-order-currency-code').text(code);
    $('.purchase-order-currency-symbol').text(symbol);
}

function setDefaultPurchaseOrderCurrency() {
    const option = $('#purchase_order_currency_id option').filter(function () {
        return String($(this).data('code')).toUpperCase() === 'PEN';
    }).first();

    $('#purchase_order_currency_id')
        .val(option.length ? option.val() : '')
        .trigger('change');
}

function configurePurchaseOrderQuoteOptions(selectedQuoteId = null) {
    const quoteSelect = $('#purchase_order_quote_id');

    quoteSelect.find('option').each(function () {
        $(this).prop('disabled', false);
    });

    if (selectedQuoteId) {
        quoteSelect.val(String(selectedQuoteId));
    }

    quoteSelect.trigger('change.select2');
}

function customerPurchaseOrderName(customer) {
    if (!customer) {
        return '—';
    }

    return customer.business_name
        || customer.full_name
        || `${customer.first_name || ''} ${customer.last_name || ''}`.trim()
        || customer.name
        || '—';
}

function getPurchaseOrderSelectedText(selector, fallback = '') {
    const text = $(selector).find('option:selected').text().trim();
    return text || fallback;
}

function formatPurchaseOrderMoney(value) {
    return (parseFloat(value) || 0).toFixed(2);
}

function formatPurchaseOrderDate(value) {
    return value ? String(value).substring(0, 10) : '';
}

function formatPurchaseOrderDisplayDate(value) {
    const date = formatPurchaseOrderDate(value);

    if (!date) {
        return '—';
    }

    const parts = date.split('-');
    return `${parts[2]}/${parts[1]}/${parts[0]}`;
}

function escapePurchaseOrderHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
