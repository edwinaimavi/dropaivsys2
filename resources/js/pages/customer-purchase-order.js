import {
    initElectronicInvoiceForm,
    openElectronicInvoiceFromCustomerOrder
} from './electronic-invoice-form';
import { initQuickSunatExistenceTypes } from '../utils/sunat-existence-types';

$(document).on('change', '.quick-item-kind', function () {
    const inventory = $(this).closest('form').find('.quick-is-inventory-item');
    if ($(this).val() === 'service') {
        inventory.val('0').prop('disabled', true);
    } else {
        inventory.val('').prop('disabled', false);
    }
});

$(document).on('reset', 'form:has(.quick-item-kind)', function () {
    const form = $(this);
    setTimeout(() => form.find('.quick-is-inventory-item').prop('disabled', false).val(''), 0);
});

initQuickSunatExistenceTypes();

let tableCustomerPurchaseOrder;
let customerPurchaseOrderStatusFilter = 'active';
let purchaseOrderItemIndex = 0;
let currentCustomerOrderItemRow = null;
let quickBrandReturnTarget = 'row';
let lastQuickCustomerDocumentLookup = '';
let quickCustomerDocumentRequest = null;
let purchaseOrderDocumentIndex = 0;
let deletedPurchaseOrderDocuments = [];
let purchaseOrderNumberIsDuplicate = false;
let purchaseOrderNumberCheckRequest = null;
let lastCheckedPurchaseOrderNumber = '';
let purchaseOrderSellerLookupVersion = 0;
const purchaseOrderSellerManualOption = '__manual__';
const quickPurchaseOrderCatalog = {
    presentations: new Map(),
    units: new Map()
};

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
    updateCustomerPurchaseOrderStatusFilters();
    initCustomerPurchaseOrderTable();
    initElectronicInvoiceForm({
        sourceContext: 'customer_purchase_orders',
        onSaved: function () {
            if (tableCustomerPurchaseOrder?.ajax) {
                tableCustomerPurchaseOrder.ajax.reload(null, false);
            }
        }
    });

    $(document).off('click.customerPurchaseOrderInvoice', '.invoiceCustomerPurchaseOrder')
        .on('click.customerPurchaseOrderInvoice', '.invoiceCustomerPurchaseOrder', function (event) {
            event.preventDefault();
            openElectronicInvoiceFromCustomerOrder($(this).data('customer-purchase-order-id'));
        });

    $(document).on('click', '#btnToggleSuppliedOrders', function () {
        customerPurchaseOrderStatusFilter = customerPurchaseOrderStatusFilter === 'all' ? 'active' : 'all';
        updateCustomerPurchaseOrderStatusFilters();
        tableCustomerPurchaseOrder.ajax.reload(null, false);
    });
    $(document).on('click', '.customer-order-filter', function () {
        customerPurchaseOrderStatusFilter = String($(this).data('status-filter') || 'active');
        updateCustomerPurchaseOrderStatusFilters();
        tableCustomerPurchaseOrder.ajax.reload();
    });

    $(document).on('click', '.customer-order-copy-btn', function (event) {
        event.preventDefault();
        event.stopPropagation();
        copyCustomerPurchaseOrderNumber(this);
    });

    $(document).on('click', '.closeCustomerPurchaseOrderAttention', function () {
        openCustomerPurchaseOrderAttentionModal($(this).data('id'), $(this).data('code'));
    });

    $(document).on('change', '#attention_result', function () {
        const isNotAttended = $(this).val() === 'not_attended';
        $('#attention_observation').prop('required', isNotAttended);
        $('#attentionObservationRequired').toggleClass('d-none', !isNotAttended);
    });

    $(document).on('submit', '#closeCustomerPurchaseOrderAttentionForm', function (event) {
        event.preventDefault();
        saveCustomerPurchaseOrderAttention(this);
    });

    $(document).on('change', '#attention_file', updateAttentionFileName);

    $(document).on('dragenter dragover', '#attentionFileDropzone', function (event) {
        event.preventDefault();
        $(this).addClass('is-dragging');
    });

    $(document).on('dragleave drop', '#attentionFileDropzone', function (event) {
        event.preventDefault();
        $(this).removeClass('is-dragging');
    });

    $(document).on('drop', '#attentionFileDropzone', function (event) {
        const files = event.originalEvent.dataTransfer?.files;
        if (files?.length) {
            $('#attention_file')[0].files = files;
            updateAttentionFileName();
        }
    });

    $(document).on('keydown', '#attentionFileDropzone', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            $('#attention_file').trigger('click');
        }
    });

    $(document).on('click', '#btnCreateCustomerPurchaseOrder', function () {
        resetCustomerPurchaseOrderForm();
        $('#customerPurchaseOrderModalLabel').text('Registrar Orden de Compra del Cliente');
        generateCustomerPurchaseOrderCode();
        $('#customerPurchaseOrderModal').modal('show');
    });

    $('#customerPurchaseOrderModal').on('hidden.bs.modal', function () {
        resetCustomerPurchaseOrderForm();
    });
    $('#customerPurchaseOrderModal').on('shown.bs.tab', '.purchase-order-tabs [data-toggle="pill"]', function () {
        initPurchaseOrderSelect2($($(this).attr('href')));
        if ($.fn.dataTable) {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }
    });

    $(document).on('submit', '#customerPurchaseOrderForm', function (event) {
        event.preventDefault();
        saveCustomerPurchaseOrder(this);
    });
    $(document).on('click', '#btnSaveCustomerPurchaseOrderTop', function () {
        $('#customerPurchaseOrderForm').trigger('submit');
    });

    $(document).on('input', '#purchase_order_number', resetPurchaseOrderNumberValidation);
    $(document).on('blur change', '#purchase_order_number', checkPurchaseOrderNumber);
    $(document).on('change', '#purchase_order_seller_mode', changePurchaseOrderSellerMode);
    $(document).on('change', '#purchase_order_seller_user_picker', changePurchaseOrderSellerUser);
    $(document).on('input', '#purchase_order_seller_dni', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 8);

        if (!purchaseOrderSellerUsesManualFields()) {
            return;
        }

        $('#purchase_order_seller_user_id').val('');
        $('#purchaseOrderSellerLookupStatus').text('');
    });
    $(document).on('click', '#btnSearchPurchaseOrderSellerDni', searchPurchaseOrderSellerDni);
    $(document).on('input', '#purchase_order_seller_names, #purchase_order_seller_lastnames', syncPurchaseOrderSellerFullName);

    $(document).on('click', '#btnAddPurchaseOrderDocument', function () {
        addPurchaseOrderDocumentRow();
    });

    $(document).on('click', '.remove-purchase-order-document', function () {
        const row = $(this).closest('tr');
        const documentId = Number(row.data('document-id') || 0);

        if (documentId && !deletedPurchaseOrderDocuments.includes(documentId)) {
            deletedPurchaseOrderDocuments.push(documentId);
        }

        row.remove();
        showEmptyPurchaseOrderDocumentsRow();
    });

    $(document).on(
        'input change',
        '#purchase_order_notification_date, #purchase_order_delivery_days',
        recalculateCustomerOrderDeliveryDates
    );

    $(document).on('change', '.purchase-order-document-file', function () {
        const fileName = this.files?.[0]?.name || 'Ningún archivo seleccionado';
        $(this).closest('.purchase-order-file-picker').find('.purchase-order-file-name').text(fileName);
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
        '.item-quantity, .item-unit-price, .item-tax-affectation-code',
        function () {
            updatePurchaseOrderTaxLabel($(this).closest('tr'));
            calculatePurchaseOrderTotals();
        }
    );

    $(document).on('change', '.item-article-picker', function () {
        applySelectedArticle($(this).closest('tr'));
    });

    $(document).on('change', '#quick_article_unit_id', updateQuickArticleSunatUnitWarning);

    $(document).on('click', '.btnQuickCreateArticle', function () {
        currentCustomerOrderItemRow = $(this).closest('tr');
        resetQuickPurchaseOrderArticleForm();
        $('#quickPurchaseOrderArticleModal').modal('show');
    });

    $(document).on('click', '.btnQuickCreatePresentation', openQuickPresentationModal);
    $(document).on('click', '.btnQuickCreateUnit', openQuickUnitModal);

    $(document).on('submit', '#quickPurchaseOrderPresentationForm', function (event) {
        event.preventDefault();
        saveQuickPresentation(this);
    });

    $(document).on('submit', '#quickPurchaseOrderUnitForm', function (event) {
        event.preventDefault();
        saveQuickUnit(this);
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

    $('#quickCustomerModalForCustomerOrder, #quickPurchaseOrderBrandModal, #quickPurchaseOrderArticleModal, .purchase-order-child-modal').on('hidden.bs.modal', function () {
        if ($('#customerPurchaseOrderModal').hasClass('show')) {
            $('body').addClass('modal-open');
        }
    });

    $('.purchase-order-child-modal')
        .on('show.bs.modal', function () {
            const visibleModals = $('.modal.show').length;
            $(this).css('z-index', 1060 + (visibleModals * 20));
            setTimeout(() => {
                $('.modal-backdrop').not('.purchase-order-stacked-backdrop').last()
                    .css('z-index', 1055 + (visibleModals * 20))
                    .addClass('purchase-order-stacked-backdrop');
            });
        })
        .on('hidden.bs.modal', function () {
            if ($('#quickPurchaseOrderArticleModal').hasClass('show')) {
                $('body').addClass('modal-open');
                setTimeout(() => $('#quickPurchaseOrderArticleModal').trigger('focus'), 0);
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
        ajax: {
            url: window.routes.customerPurchaseOrderList,
            data: function (data) {
                data.status_filter = customerPurchaseOrderStatusFilter;
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'code', name: 'code' },
            {
                data: 'purchase_order_number',
                name: 'purchase_order_number',
                defaultContent: '-',
                render: function (data, type, row) {
                    return type === 'display' ? data : (row.purchase_order_number_text || '-');
                }
            },
            {
                data: 'customer',
                name: 'customer',
                orderable: false,
                render: function (data, type, row) {
                    return type === 'export' ? (row.customer_text || '-') : data;
                }
            },
            { data: 'seller', name: 'seller_full_name', defaultContent: '-' },
            { data: 'company', name: 'company.business_name', orderable: false },
            { data: 'currency', name: 'currency.code', orderable: false },
            { data: 'grand_total', name: 'grand_total' },
            { data: 'delivery_period', name: 'delivery_end_date', orderable: true, searchable: false },
            { data: 'operational_progress', name: 'operational_progress', orderable: false, searchable: false },
            { data: 'status', name: 'status' },
            { data: 'billing_status', name: 'billing_status', orderable: false, searchable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        order: [],
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
                text: '<i class="fas fa-file-excel"></i> Excel',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], orthogonal: 'export' }
            },
            {
                extend: 'pdf',
                className: 'btn btn-danger btn-sm',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], orthogonal: 'export' }
            },
            {
                extend: 'print',
                className: 'btn btn-secondary btn-sm',
                text: '<i class="fas fa-print"></i> Imprimir',
                exportOptions: { columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11], orthogonal: 'export' }
            }
        ],
        drawCallback: function () {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });

}

async function copyCustomerPurchaseOrderNumber(button) {
    const orderNumber = button.getAttribute('data-order-number') || '';
    if (!orderNumber) {
        return;
    }

    let copied = false;

    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(orderNumber);
            copied = true;
        } catch (error) {
            copied = copyCustomerPurchaseOrderNumberFallback(orderNumber);
        }
    } else {
        copied = copyCustomerPurchaseOrderNumberFallback(orderNumber);
    }

    if (!copied) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: 'No se pudo copiar',
            showConfirmButton: false,
            timer: 1800
        });
        return;
    }

    const cell = button.closest('.customer-order-code-cell');
    const icon = button.querySelector('i');
    const originalTitle = button.getAttribute('title') || 'Copiar número';

    window.clearTimeout(button.customerOrderCopyTimer);
    cell?.classList.add('is-copied');
    icon?.classList.remove('fa-copy');
    icon?.classList.add('fa-check');
    button.setAttribute('title', 'Copiado');
    button.setAttribute('aria-label', 'Número copiado');

    button.customerOrderCopyTimer = window.setTimeout(function () {
        cell?.classList.remove('is-copied');
        icon?.classList.remove('fa-check');
        icon?.classList.add('fa-copy');
        button.setAttribute('title', originalTitle);
        button.setAttribute('aria-label', 'Copiar número de orden');
    }, 1400);
}

function copyCustomerPurchaseOrderNumberFallback(orderNumber) {
    const textarea = document.createElement('textarea');
    textarea.value = orderNumber;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    textarea.setSelectionRange(0, textarea.value.length);

    let copied = false;
    try {
        copied = document.execCommand('copy');
    } catch (error) {
        copied = false;
    }

    textarea.remove();

    return copied;
}

function updateCustomerPurchaseOrderStatusFilters() {
    const showingAll = customerPurchaseOrderStatusFilter === 'all';
    $('.customer-order-filter')
        .removeClass('is-active')
        .attr('aria-pressed', 'false')
        .filter(`[data-status-filter="${customerPurchaseOrderStatusFilter}"]`)
        .addClass('is-active')
        .attr('aria-pressed', 'true');
    $('#btnToggleSuppliedOrders')
        .toggleClass('btn-outline-secondary', !showingAll)
        .toggleClass('btn-primary', showingAll)
        .attr('aria-pressed', showingAll ? 'true' : 'false')
        .html(showingAll
            ? '<i class="fas fa-eye-slash mr-1"></i> Ocultar atendidas/finalizadas'
            : '<i class="fas fa-eye mr-1"></i> Mostrar atendidas/finalizadas');
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
    activatePurchaseOrderTab('data');

    $('#customer_purchase_order_id').val('');
    $('#purchase_order_code').val('');
    $('#purchase_order_status').val('registered');
    $('#customerPurchaseOrderModalLabel').text('Registrar Orden de Compra del Cliente');
    $('#btnSaveCustomerPurchaseOrder')
        .prop('disabled', false)
        .html('<i class="fas fa-save mr-1"></i> Guardar');
    $('#btnSaveCustomerPurchaseOrderTop')
        .prop('disabled', false)
        .html('<i class="fas fa-save mr-1"></i><span>Guardar</span>');
    resetPurchaseOrderNumberValidation();

    $('#purchaseOrderItemsTbody tr.purchase-order-item-row').each(function () {
        destroyPurchaseOrderRowSelect2($(this));
    });

    purchaseOrderItemIndex = 0;
    $('#purchaseOrderItemsTbody').empty();
    showEmptyPurchaseOrderItemsRow();
    purchaseOrderDocumentIndex = 0;
    deletedPurchaseOrderDocuments = [];
    $('#purchaseOrderDocumentsTbody').empty();
    showEmptyPurchaseOrderDocumentsRow();

    $('#purchase_order_type').val('articles').trigger('change.select2');
    $('#purchase_order_billing_type').val('local').trigger('change.select2');
    $('#purchase_order_affect_igv').val('0');
    $('#purchase_order_customer_id').val('').trigger('change.select2');
    $('#purchase_order_quote_id').val('').trigger('change.select2');
    $('#purchase_order_company_id').val('').trigger('change.select2');
    $('#purchase_order_seller_mode').val('').trigger('change.select2');
    $('#purchase_order_seller_type').val('');
    $('#purchase_order_seller_user_picker').val('').trigger('change.select2');
    $('#purchase_order_seller_user_id').val('');
    $('#purchaseOrderSellerLookupStatus').text('');
    purchaseOrderSellerLookupVersion += 1;
    updatePurchaseOrderSellerUi();

    setDefaultPurchaseOrderCurrency();
    configurePurchaseOrderQuoteOptions();
    resetPurchaseOrderCustomerBranches();

    $('#purchaseOrderSideCustomer').text('Seleccione cliente');
    $('#purchaseOrderSideBranch').text('Seleccione sucursal');
    calculatePurchaseOrderTotals();
}

function syncPurchaseOrderSellerFullName() {
    const fullName = [
        $('#purchase_order_seller_names').val(),
        $('#purchase_order_seller_lastnames').val()
    ].map(value => String(value || '').trim()).filter(Boolean).join(' ');
    $('#purchase_order_seller_full_name').val(fullName);
}

function purchaseOrderSellerUsesManualFields() {
    const mode = $('#purchase_order_seller_mode').val();
    const selectedUser = $('#purchase_order_seller_user_picker').val();

    return mode === 'EXTERNAL'
        || (mode === 'USER' && selectedUser === purchaseOrderSellerManualOption);
}

function purchaseOrderSellerIdentityFields() {
    return $('#purchase_order_seller_dni, #purchase_order_seller_full_name, #purchase_order_seller_names, '
        + '#purchase_order_seller_lastnames, #purchase_order_seller_phone, #purchase_order_seller_email');
}

function clearPurchaseOrderSellerIdentity() {
    purchaseOrderSellerIdentityFields().val('');
    $('#purchaseOrderSellerLookupStatus').removeClass('text-success text-warning').addClass('text-muted').text('');
}

function setPurchaseOrderSellerReadonly(readonly) {
    purchaseOrderSellerIdentityFields()
        .prop('readonly', readonly)
        .toggleClass('purchase-order-seller-readonly', readonly);
}

function updatePurchaseOrderSellerUi() {
    const mode = $('#purchase_order_seller_mode').val();
    const selectedUser = String($('#purchase_order_seller_user_picker').val() || '');
    const isUserMode = mode === 'USER';
    const hasInternalUser = isUserMode
        && selectedUser !== ''
        && selectedUser !== purchaseOrderSellerManualOption;
    const usesManualFields = mode === 'EXTERNAL'
        || (isUserMode && selectedUser === purchaseOrderSellerManualOption);

    $('#purchaseOrderSellerInternalGroup').toggleClass('d-none', !isUserMode);
    $('#purchaseOrderSellerDetails').toggleClass('d-none', !(hasInternalUser || usesManualFields));
    $('#purchaseOrderSellerDniSearch').toggleClass('d-none', !usesManualFields);
    $('#btnSearchPurchaseOrderSellerDni').prop('disabled', !usesManualFields);
    setPurchaseOrderSellerReadonly(hasInternalUser);
}

function changePurchaseOrderSellerMode() {
    const mode = $('#purchase_order_seller_mode').val();
    const hadInternalUser = Boolean($('#purchase_order_seller_user_id').val());

    purchaseOrderSellerLookupVersion += 1;
    $('#purchase_order_seller_user_picker').removeClass('is-invalid');
    $('#purchase_order_seller_user_picker').next('.select2-container').find('.select2-selection').removeClass('border-danger');

    if (mode === 'USER') {
        $('#purchase_order_seller_type').val('USER');
        $('#purchase_order_seller_user_id').val('');
        $('#purchase_order_seller_user_picker').val('').trigger('change.select2');
        $('#purchaseOrderSellerLookupStatus').removeClass('text-success text-warning').addClass('text-muted')
            .text('Seleccione un usuario interno para completar sus datos.');
    } else if (mode === 'EXTERNAL') {
        if (hadInternalUser) {
            clearPurchaseOrderSellerIdentity();
        }

        $('#purchase_order_seller_type').val('EXTERNAL');
        $('#purchase_order_seller_user_id').val('');
        $('#purchase_order_seller_user_picker').val('').trigger('change.select2');
        $('#purchaseOrderSellerLookupStatus').removeClass('text-success text-warning').addClass('text-muted').text('');
    } else {
        $('#purchase_order_seller_type').val('');
        $('#purchase_order_seller_user_id').val('');
        $('#purchase_order_seller_user_picker').val('').trigger('change.select2');
        clearPurchaseOrderSellerIdentity();
        $('#purchase_order_seller_observation').val('');
    }

    updatePurchaseOrderSellerUi();
}

function changePurchaseOrderSellerUser() {
    const picker = $('#purchase_order_seller_user_picker');
    const selectedValue = String(picker.val() || '');
    const hadInternalUser = Boolean($('#purchase_order_seller_user_id').val());

    purchaseOrderSellerLookupVersion += 1;
    picker.removeClass('is-invalid');
    picker.next('.select2-container').find('.select2-selection').removeClass('border-danger');

    if (!selectedValue) {
        if (hadInternalUser) {
            clearPurchaseOrderSellerIdentity();
        }

        $('#purchase_order_seller_type').val('USER');
        $('#purchase_order_seller_user_id').val('');
        $('#purchaseOrderSellerLookupStatus').removeClass('text-success text-warning').addClass('text-muted')
            .text('Seleccione un usuario interno para completar sus datos.');
        updatePurchaseOrderSellerUi();
        return;
    }

    if (selectedValue === purchaseOrderSellerManualOption) {
        if (hadInternalUser) {
            clearPurchaseOrderSellerIdentity();
        }

        $('#purchase_order_seller_type').val('EXTERNAL');
        $('#purchase_order_seller_user_id').val('');
        $('#purchaseOrderSellerLookupStatus').removeClass('text-success text-warning').addClass('text-muted')
            .text('Gestor no registrado: puede buscar el DNI o completar los datos manualmente.');
        updatePurchaseOrderSellerUi();
        return;
    }

    const option = picker.find('option:selected');
    fillPurchaseOrderSeller({
        id: selectedValue,
        dni: option.attr('data-dni') || '',
        names: option.attr('data-names') || '',
        lastnames: option.attr('data-lastnames') || '',
        full_name: option.attr('data-full-name') || '',
        phone: option.attr('data-phone') || '',
        email: option.attr('data-email') || ''
    }, 'USER');
    $('#purchaseOrderSellerLookupStatus').removeClass('text-muted text-warning').addClass('text-success')
        .text('Usuario interno seleccionado. Los datos se completaron automáticamente.');
}

function purchaseOrderSellerOptionLabel(data) {
    const fullName = data.full_name
        || [data.name || data.names, data.lastname || data.lastnames].filter(Boolean).join(' ')
        || 'Usuario interno';
    const parts = [fullName];
    if (data.dni) parts.push(`DNI ${data.dni}`);
    if (data.email) parts.push(data.email);
    if (Number(data.status) !== 1 && data.status !== undefined) parts.push('INACTIVO');
    return parts.join(' · ');
}

function ensurePurchaseOrderSellerOption(data) {
    if (!data?.id) {
        return;
    }

    const picker = $('#purchase_order_seller_user_picker');
    let option = picker.find(`option[value="${data.id}"]`);

    if (!option.length) {
        option = $('<option>', { value: data.id, text: purchaseOrderSellerOptionLabel(data) });
        picker.find(`option[value="${purchaseOrderSellerManualOption}"]`).before(option);
    }

    option
        .attr('data-dni', data.dni || '')
        .attr('data-names', data.name || data.names || '')
        .attr('data-lastnames', data.lastname || data.lastnames || '')
        .attr('data-full-name', data.full_name || [data.name || data.names, data.lastname || data.lastnames].filter(Boolean).join(' '))
        .attr('data-phone', data.phone || '')
        .attr('data-email', data.email || '');
}

function fillPurchaseOrderSeller(data, type) {
    const names = data.names || data.nombres || '';
    const lastnames = data.lastnames
        || [data.apellidoPaterno, data.apellidoMaterno].filter(Boolean).join(' ')
        || data.apellidos
        || '';
    const fullName = data.full_name || data.nombreCompleto || [names, lastnames].filter(Boolean).join(' ');

    if (type === 'USER') {
        ensurePurchaseOrderSellerOption(data);
        $('#purchase_order_seller_mode').val('USER').trigger('change.select2');
        $('#purchase_order_seller_type').val('USER');
        $('#purchase_order_seller_user_picker').val(String(data.id || '')).trigger('change.select2');
        $('#purchase_order_seller_user_id').val(data.id || '');
    } else {
        const isInternalManualFallback = $('#purchase_order_seller_mode').val() === 'USER'
            && $('#purchase_order_seller_user_picker').val() === purchaseOrderSellerManualOption;

        if (!isInternalManualFallback) {
            $('#purchase_order_seller_mode').val('EXTERNAL').trigger('change.select2');
        }

        $('#purchase_order_seller_type').val('EXTERNAL');
        $('#purchase_order_seller_user_id').val('');
    }

    $('#purchase_order_seller_dni').val(data.dni || '');
    $('#purchase_order_seller_names').val(names);
    $('#purchase_order_seller_lastnames').val(lastnames);
    $('#purchase_order_seller_full_name').val(fullName);
    $('#purchase_order_seller_phone').val(data.phone || '');
    $('#purchase_order_seller_email').val(data.email || '');
    updatePurchaseOrderSellerUi();
}

function searchPurchaseOrderSellerDni() {
    const dni = String($('#purchase_order_seller_dni').val() || '').trim();
    const button = $('#btnSearchPurchaseOrderSellerDni');
    const status = $('#purchaseOrderSellerLookupStatus');

    if (!purchaseOrderSellerUsesManualFields()) {
        return;
    }

    if (!/^\d{8}$/.test(dni)) {
        Swal.fire({ icon: 'warning', title: 'DNI inválido', text: 'Ingrese un DNI de 8 dígitos.' });
        return;
    }

    const lookupVersion = ++purchaseOrderSellerLookupVersion;
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Buscando');
    status.removeClass('text-success text-warning').addClass('text-muted').text('Consultando información...');

    $.get(`${window.routes.customerPurchaseOrderSellerUser}/${dni}`)
        .done(function (response) {
            if (lookupVersion !== purchaseOrderSellerLookupVersion || !purchaseOrderSellerUsesManualFields()) {
                return;
            }

            if (response.found && response.data) {
                fillPurchaseOrderSeller(response.data, 'USER');
                status.removeClass('text-muted text-warning').addClass('text-success')
                    .text('DNI vinculado a un usuario del sistema.');
                return;
            }

            const externalUrl = window.routes.customerPurchaseOrderCustomerDocumentConsult
                .replace('TYPE_PLACEHOLDER', 'dni')
                .replace('DOC_PLACEHOLDER', dni);
            $.get(externalUrl)
                .done(function (documentResponse) {
                    if (lookupVersion !== purchaseOrderSellerLookupVersion || !purchaseOrderSellerUsesManualFields()) {
                        return;
                    }

                    fillPurchaseOrderSeller(documentResponse.data || {}, 'EXTERNAL');
                    status.removeClass('text-muted text-warning').addClass('text-success')
                        .text('Datos encontrados. Gestor externo.');
                })
                .fail(function () {
                    if (lookupVersion !== purchaseOrderSellerLookupVersion || !purchaseOrderSellerUsesManualFields()) {
                        return;
                    }

                    $('#purchase_order_seller_type').val('EXTERNAL');
                    $('#purchase_order_seller_user_id').val('');
                    status.removeClass('text-muted text-success').addClass('text-warning')
                        .text('No se encontraron datos para este DNI. Puede completar la información manualmente.');
                });
        })
        .fail(function (xhr) {
            if (lookupVersion !== purchaseOrderSellerLookupVersion || !purchaseOrderSellerUsesManualFields()) {
                return;
            }

            status.removeClass('text-muted text-success').addClass('text-warning')
                .text(xhr.responseJSON?.message || 'No se pudo consultar el DNI.');
        })
        .always(function () {
            button.prop('disabled', false).html('<i class="fas fa-search mr-1"></i> Buscar DNI');
            updatePurchaseOrderSellerUi();
        });
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
    if (purchaseOrderNumberIsDuplicate) {
        showDuplicatePurchaseOrderNumber();
        return;
    }

    clearCustomerPurchaseOrderErrors();
    refreshPurchaseOrderItemIndexes();
    calculatePurchaseOrderTotals();

    const purchaseOrderNumberInput = $('#purchase_order_number');
    const purchaseOrderNumber = String(purchaseOrderNumberInput.val() || '').trim();
    purchaseOrderNumberInput.val(purchaseOrderNumber);

    if (!purchaseOrderNumber) {
        const message = 'El N° de Orden de Compra es obligatorio.';
        purchaseOrderNumberInput
            .addClass('is-invalid')
            .closest('.form-group')
            .find('.invalid-feedback')
            .text(message);
        purchaseOrderNumberInput.trigger('focus');
        activatePurchaseOrderTab('data', true);

        Swal.fire({
            icon: 'warning',
            title: 'Dato obligatorio',
            text: message
        });
        return;
    }

    if (
        $('#purchase_order_seller_mode').val() === 'USER'
        && !$('#purchase_order_seller_user_picker').val()
    ) {
        const message = 'Seleccione un usuario interno o elija “No está registrado / buscar por DNI”.';
        const picker = $('#purchase_order_seller_user_picker');
        picker.addClass('is-invalid');
        picker.next('.select2-container').find('.select2-selection').addClass('border-danger');
        picker.closest('.form-group').find('.invalid-feedback').text(message);
        activatePurchaseOrderTab('seller', true);

        Swal.fire({
            icon: 'warning',
            title: 'Gestor incompleto',
            text: message
        });
        return;
    }

    if ($('#purchaseOrderItemsTbody tr.purchase-order-item-row').length === 0) {
        activatePurchaseOrderTab('items', true);
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

    deletedPurchaseOrderDocuments.forEach(function (documentId) {
        formData.append('deleted_documents[]', documentId);
    });

    button
        .prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');
    $('#btnSaveCustomerPurchaseOrderTop')
        .prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i><span>Guardando</span>');

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
            $('#btnSaveCustomerPurchaseOrderTop')
                .prop('disabled', false)
                .html(`<i class="fas fa-save mr-1"></i><span>${id ? 'Actualizar' : 'Guardar'}</span>`);

            if (xhr.status === 422) {
                const errors = xhr.responseJSON.errors || {};
                showCustomerPurchaseOrderErrors(errors);
                if (errors.purchase_order_number?.some(message => message.includes('Ya se registró'))) {
                    purchaseOrderNumberIsDuplicate = true;
                }
                Swal.fire({
                    icon: 'warning',
                    title: 'Revisa el formulario',
                    text: errors.purchase_order_number?.[0]
                        || 'Hay campos obligatorios o con formato incorrecto.'
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

function resetPurchaseOrderNumberValidation() {
    purchaseOrderNumberIsDuplicate = false;
    lastCheckedPurchaseOrderNumber = '';

    const input = $('#purchase_order_number');
    input.removeClass('is-invalid');
    input.closest('.form-group').find('.invalid-feedback').text('');
    $('#btnSaveCustomerPurchaseOrder').prop('disabled', false);
    $('#btnSaveCustomerPurchaseOrderTop').prop('disabled', false);
}

function showDuplicatePurchaseOrderNumber(message = 'Ya se registró una Orden de Compra del Cliente con este Nro de Orden de Compra.') {
    const input = $('#purchase_order_number');

    purchaseOrderNumberIsDuplicate = true;
    input
        .addClass('is-invalid')
        .closest('.form-group')
        .find('.invalid-feedback')
        .text('Este número de orden ya fue registrado.');
    $('#btnSaveCustomerPurchaseOrder').prop('disabled', true);
    $('#btnSaveCustomerPurchaseOrderTop').prop('disabled', true);

    Swal.fire({
        icon: 'warning',
        title: 'Número de orden duplicado',
        text: message,
        confirmButtonText: 'Corregir'
    }).then(function () {
        input.trigger('focus').select();
    });
}

function checkPurchaseOrderNumber() {
    const input = $('#purchase_order_number');
    const purchaseOrderNumber = String(input.val() || '').trim();
    const orderId = $('#customer_purchase_order_id').val();

    input.val(purchaseOrderNumber);

    if (!purchaseOrderNumber || !window.routes.customerPurchaseOrderCheckNumber) {
        resetPurchaseOrderNumberValidation();
        return;
    }

    const checkKey = `${orderId || 'new'}:${purchaseOrderNumber}`;
    if (checkKey === lastCheckedPurchaseOrderNumber) {
        return;
    }

    if (purchaseOrderNumberCheckRequest) {
        purchaseOrderNumberCheckRequest.abort();
    }

    $('#btnSaveCustomerPurchaseOrder').prop('disabled', true);
    $('#btnSaveCustomerPurchaseOrderTop').prop('disabled', true);

    const request = $.get(window.routes.customerPurchaseOrderCheckNumber, {
        purchase_order_number: purchaseOrderNumber,
        id: orderId || undefined
    });
    purchaseOrderNumberCheckRequest = request;

    request
        .done(function (response) {
            lastCheckedPurchaseOrderNumber = checkKey;

            if (response.exists) {
                showDuplicatePurchaseOrderNumber(response.message);
                return;
            }

            purchaseOrderNumberIsDuplicate = false;
            input.removeClass('is-invalid');
            input.closest('.form-group').find('.invalid-feedback').text('');
            $('#btnSaveCustomerPurchaseOrder').prop('disabled', false);
            $('#btnSaveCustomerPurchaseOrderTop').prop('disabled', false);
        })
        .fail(function (xhr, status) {
            if (status !== 'abort') {
                lastCheckedPurchaseOrderNumber = '';
            }
        })
        .always(function () {
            if (purchaseOrderNumberCheckRequest === request) {
                purchaseOrderNumberCheckRequest = null;
                $('#btnSaveCustomerPurchaseOrder').prop('disabled', purchaseOrderNumberIsDuplicate);
                $('#btnSaveCustomerPurchaseOrderTop').prop('disabled', purchaseOrderNumberIsDuplicate);
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
            $('#purchase_order_affect_igv').val(response.affect_igv ? '1' : '0');

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

    quickPurchaseOrderCatalog.units.forEach((text, id) => {
        appendOptionIfMissing(row.find('.item-unit-id'), id, text);
    });
    quickPurchaseOrderCatalog.presentations.forEach((text, id) => {
        appendOptionIfMissing(row.find('.item-presentation-id'), id, text);
    });

    row.find('.item-id').val(data.id || '');
    row.find('.item-quote-item-id').val(data.quote_item_id || '');
    row.find('.item-market-study-item-id').val(data.market_study_item_id || '');
    row.find('.item-article-id').val(data.article_id || '');
    row.find('.item-article-code').val(data.article_code || '');
    row.find('.item-billing-name').val(data.billing_name_snapshot || '');
    row.find('.item-tax-affectation-code').val(data.tax_affectation_code || '');
    updatePurchaseOrderTaxLabel(row);
    row.find('.item-article-picker').val(data.article_id || '');
    row.find('.item-note').val(data.note || '');
    row.find('.item-unit-id').val(data.unit_id || '');
    row.find('.item-presentation-id').val(data.presentation_id || '');
    row.find('.item-brand-id').val(data.brand_id || '');
    row.find('.item-origin').val(data.origin || '');
    row.find('.item-expiration-date').val(formatPurchaseOrderDate(data.expiration_date));
    row.find('.item-cost-type').val(data.cost_type || 'PESO');
    row.find('.item-quoted-quantity').val(formatPurchaseOrderRaw(data.quoted_quantity || data.quantity || 0));
    row.find('.item-quantity').val(formatPurchaseOrderRaw(data.quantity || 1));
    row.find('.item-unit-price').val(formatPurchaseOrderRaw(data.unit_price || 0));
    row.find('.item-subtotal').val(formatPurchaseOrderRaw(data.subtotal || 0));
    row.find('.item-tax-amount').val(formatPurchaseOrderRaw(data.tax_amount || 0));
    row.find('.item-line-total-raw').val(formatPurchaseOrderRaw(data.line_total || 0));
    row.find('.item-line-total').val(formatDecimalView(data.line_total || 0));

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
    updatePurchaseOrderTaxLabel(row);

    if (articleId) {
        row.find('.item-unit-id').val(option.data('unit-id') || '').trigger('change.select2');
        row.find('.item-presentation-id').val(option.data('presentation-id') || '').trigger('change.select2');
        row.find('.item-brand-id').val(option.data('brand-id') || '').trigger('change.select2');
    }
}

function updatePurchaseOrderTaxLabel(row) {
    const code = row.find('.item-tax-affectation-code').val();
    const labels = {
        '10': 'GRAVADO CON IGV',
        '20': 'EXONERADO',
        '30': 'INAFECTO'
    };
    row.find('.item-tax-affectation-label').text(`Venta: ${labels[code] || 'PENDIENTE'}`);
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
    $('#quick_article_presentation_id, #quick_article_unit_id').val('').trigger('change');
    updateQuickArticleSunatUnitWarning();
    $('#quick_article_legal_name, #quick_article_commercial_name, #quick_article_billing_name, #quick_article_institutional_code').val('');
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

    const url = window.routes.customerPurchaseOrderCustomerDocumentConsult
        .replace('TYPE_PLACEHOLDER', documentType.toLowerCase())
        .replace('DOC_PLACEHOLDER', documentNumber);

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

function openQuickPresentationModal() {
    const form = $('#quickPurchaseOrderPresentationForm');
    form[0]?.reset();
    clearQuickPurchaseOrderErrors('#quickPurchaseOrderPresentationForm', '#quickPurchaseOrderPresentationErrors');
    $('#quick_presentation_unit_id').val($('#quick_article_unit_id').val() || '');
    $('#quickPurchaseOrderPresentationModal').modal('show');
    $('#quickPurchaseOrderPresentationModal').one('shown.bs.modal', () => $('#quick_presentation_description').trigger('focus'));
}

function openQuickUnitModal() {
    const form = $('#quickPurchaseOrderUnitForm');
    form[0]?.reset();
    clearQuickPurchaseOrderErrors('#quickPurchaseOrderUnitForm', '#quickPurchaseOrderUnitErrors');
    $('#quickPurchaseOrderUnitModal').modal('show');
    $('#quickPurchaseOrderUnitModal').one('shown.bs.modal', () => $('#quick_unit_abbreviation').trigger('focus'));
}

function appendOptionIfMissing(select, id, text, selected = false) {
    const target = $(select);
    if (!target.length || !id) {
        return;
    }

    let option = target.find(`option[value="${id}"]`);
    if (!option.length) {
        option = $(new Option(text, id, false, selected));
        target.append(option);
    } else {
        option.text(text);
    }

    if (selected) {
        target.val(String(id)).trigger('change');
    } else {
        target.trigger('change.select2');
    }
}

function saveQuickPresentation(formElement) {
    clearQuickPurchaseOrderErrors('#quickPurchaseOrderPresentationForm', '#quickPurchaseOrderPresentationErrors');
    const button = $('#btnSaveQuickPurchaseOrderPresentation');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.quickStorePresentation,
        type: 'POST',
        data: new FormData(formElement),
        processData: false,
        contentType: false,
        success: function (response) {
            const presentation = response.data || {};
            const text = presentation.text || presentation.description;
            quickPurchaseOrderCatalog.presentations.set(String(presentation.id), text);
            appendOptionIfMissing('#quick_article_presentation_id', presentation.id, text, true);
            $('.item-presentation-id').each(function () {
                appendOptionIfMissing(this, presentation.id, text);
            });
            $('#quickPurchaseOrderPresentationModal').modal('hide');
            showQuickCatalogSuccess(response.message || 'Presentación registrada correctamente.');
        },
        error: function (xhr) {
            handleQuickPurchaseOrderError(xhr, '#quickPurchaseOrderPresentationForm', '#quickPurchaseOrderPresentationErrors', 'No se pudo guardar la presentación.');
        },
        complete: function () {
            button.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar');
        }
    });
}

function saveQuickUnit(formElement) {
    clearQuickPurchaseOrderErrors('#quickPurchaseOrderUnitForm', '#quickPurchaseOrderUnitErrors');
    const button = $('#btnSaveQuickPurchaseOrderUnit');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.quickStoreUnit,
        type: 'POST',
        data: new FormData(formElement),
        processData: false,
        contentType: false,
        success: function (response) {
            const unit = response.data || {};
            const text = unit.text || unit.description || unit.abbreviation;
            const tableText = unit.abbreviation || text;
            quickPurchaseOrderCatalog.units.set(String(unit.id), tableText);
            appendOptionIfMissing('#quick_article_unit_id', unit.id, text, true);
            $('#quick_article_unit_id option[value="' + unit.id + '"]').attr('data-sunat-configured', unit.sunat_configured ? '1' : '0');
            updateQuickArticleSunatUnitWarning();
            appendOptionIfMissing('#quick_presentation_unit_id', unit.id, text);
            $('.item-unit-id').each(function () {
                appendOptionIfMissing(this, unit.id, tableText);
            });
            $('#quickPurchaseOrderUnitModal').modal('hide');
            showQuickCatalogSuccess(response.message || 'Unidad registrada correctamente.');
        },
        error: function (xhr) {
            handleQuickPurchaseOrderError(xhr, '#quickPurchaseOrderUnitForm', '#quickPurchaseOrderUnitErrors', 'No se pudo guardar la unidad.');
        },
        complete: function () {
            button.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar');
        }
    });
}

function updateQuickArticleSunatUnitWarning() {
    const option = $('#quick_article_unit_id option:selected');
    const pending = Boolean(option.val()) && String(option.attr('data-sunat-configured') || '0') !== '1';
    $('#quickArticleSunatUnitWarning').toggleClass('d-none', !pending);
}

function showQuickCatalogSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: message,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500
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
            const brand = response.brand || response.data || {};
            refreshCustomerPurchaseOrderBrandSelects(brand);

            if (quickBrandReturnTarget === 'row' && currentCustomerOrderItemRow && currentCustomerOrderItemRow.length) {
                currentCustomerOrderItemRow
                    .find('.item-brand-id')
                    .val(String(brand.id))
                    .trigger('change');
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

    const presentationId = $('#quick_article_presentation_id').val();
    const unitId = $('#quick_article_unit_id').val();
    if (!presentationId || !unitId) {
        if (!presentationId) {
            $('#quick_article_presentation_id').addClass('is-invalid')
                .siblings('.invalid-feedback').text('Seleccione una presentación.');
        }
        if (!unitId) {
            $('#quick_article_unit_id').addClass('is-invalid')
                .siblings('.invalid-feedback').text('Seleccione una unidad.');
        }
        $('#quickPurchaseOrderArticleErrors').removeClass('d-none')
            .text('Seleccione la presentación y la unidad del artículo.');
        return;
    }

    const button = $('#btnSaveQuickPurchaseOrderArticle');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.quickStoreArticle,
        type: 'POST',
        data: new FormData(formElement),
        processData: false,
        contentType: false,
        success: function (response) {
            const article = response.article || response.data || {};
            refreshCustomerPurchaseOrderArticleSelects(article);

            if (currentCustomerOrderItemRow && currentCustomerOrderItemRow.length) {
                applyQuickArticleToPurchaseOrderRow(currentCustomerOrderItemRow, article);
            }

            resetQuickPurchaseOrderArticleForm();
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

function refreshCustomerPurchaseOrderBrandSelects(brand) {
    if (!brand.id) {
        return;
    }

    const text = brand.text || brand.name || brand.description || 'MARCA';

    const updateSelect = function (selectElement) {
        const select = $(selectElement);
        let option = select.find(`option[value="${brand.id}"]`);

        if (!option.length) {
            option = $(new Option(text, brand.id, false, false));
            select.append(option);
        } else {
            option.text(text);
        }
    };

    // Selects ya visibles en las filas del modal.
    $('.item-brand-id').each(function () {
        updateSelect(this);
    });

    // Fuente de opciones para las filas que se creen posteriormente. El
    // contenido de <template> no forma parte del DOM consultado por jQuery.
    const template = document.getElementById('purchaseOrderItemRowTemplate');
    const templateSelect = template?.content?.querySelector('.item-brand-id');
    if (templateSelect) {
        updateSelect(templateSelect);
    }
}

function refreshCustomerPurchaseOrderArticleSelects(article) {
    if (!article.id) {
        return;
    }

    const institutionalLabel = article.institutional_code
        ? `${article.code_type || 'C.I.'}: ${article.institutional_code}`
        : '';
    const text = [
        article.code,
        institutionalLabel,
        article.billing_name || article.name
    ].filter(Boolean).join(' | ');
    const searchText = [
        article.code,
        article.legal_name,
        article.commercial_name,
        article.billing_name || article.name,
        article.institutional_code
    ].filter(Boolean).join(' ');

    const updateSelect = function (selectElement) {
        const select = $(selectElement);
        let option = select.find(`option[value="${article.id}"]`);

        if (!option.length) {
            option = $(new Option(text, article.id, false, false));
            select.append(option);
        } else {
            option.text(text);
        }

        option
            .attr('data-code', article.code || '')
            .attr('data-billing-name', article.billing_name || article.name || '')
            .attr('data-search', searchText)
            .attr('data-unit-id', article.unit_id || '')
            .attr('data-presentation-id', article.presentation_id || '')
            .attr('data-brand-id', article.brand_id || '');
    };

    // Selects que ya están montados en el modal actual.
    $('.item-article-picker').each(function () {
        updateSelect(this);
    });

    // Fuente reutilizable de las filas futuras. El contenido de <template> vive
    // en un DocumentFragment y no es encontrado por $('.item-article-picker').
    const template = document.getElementById('purchaseOrderItemRowTemplate');
    const templateSelect = template?.content?.querySelector('.item-article-picker');
    if (templateSelect) {
        updateSelect(templateSelect);
    }
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
    row.find('.item-unit-id').val(String(article.unit_id || '')).trigger('change.select2');
    row.find('.item-presentation-id').val(String(article.presentation_id || '')).trigger('change.select2');
    row.find('.item-brand-id').val(String(article.brand_id || '')).trigger('change.select2');
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
    let subtotalExonerated = 0;
    let subtotalUnaffected = 0;
    let subtotalTaxed = 0;
    let igv = 0;
    let grandTotal = 0;
    let hasTaxable = false;

    $('#purchaseOrderItemsTbody tr.purchase-order-item-row').each(function () {
        const row = $(this);
        const quantity = parseFloat(row.find('.item-quantity').val()) || 0;
        const unitPrice = parseFloat(row.find('.item-unit-price').val()) || 0;
        const lineTotal = quantity * unitPrice;
        const affectation = row.find('.item-tax-affectation-code').val();
        const lineSubtotal = affectation === '10' ? lineTotal / 1.18 : lineTotal;
        const taxAmount = affectation === '10' ? lineTotal - lineSubtotal : 0;

        row.find('.item-subtotal').val(formatPurchaseOrderRaw(lineSubtotal));
        row.find('.item-tax-amount').val(formatPurchaseOrderRaw(taxAmount));
        row.find('.item-line-total-raw').val(formatPurchaseOrderRaw(lineTotal));
        row.find('.item-line-total').val(formatDecimalView(lineTotal));

        if (affectation === '10') {
            subtotalTaxed += lineSubtotal;
            igv += taxAmount;
            hasTaxable = true;
        } else if (affectation === '20') {
            subtotalExonerated += lineTotal;
        } else if (affectation === '30') {
            subtotalUnaffected += lineTotal;
        }
        grandTotal += lineTotal;
    });

    $('#purchase_order_affect_igv').val(hasTaxable ? '1' : '0');
    $('#purchase_order_subtotal_exonerated').val(formatDecimalView(subtotalExonerated));
    $('#purchase_order_subtotal_unaffected').val(formatDecimalView(subtotalUnaffected));
    $('#purchase_order_subtotal_taxed').val(formatDecimalView(subtotalTaxed));
    $('#purchase_order_igv').val(formatDecimalView(igv));
    $('#purchase_order_grand_total').val(formatDecimalView(grandTotal));
    $('#purchase_order_subtotal_exonerated_raw').val(formatPurchaseOrderRaw(subtotalExonerated));
    $('#purchase_order_subtotal_unaffected_raw').val(formatPurchaseOrderRaw(subtotalUnaffected));
    $('#purchase_order_subtotal_taxed_raw').val(formatPurchaseOrderRaw(subtotalTaxed));
    $('#purchase_order_igv_raw').val(formatPurchaseOrderRaw(igv));
    $('#purchase_order_grand_total_raw').val(formatPurchaseOrderRaw(grandTotal));
    $('#purchaseOrderSideGrandTotal').text(formatDecimalView(grandTotal));
}


function loadCustomerPurchaseOrderForEdit(id) {
    clearCustomerPurchaseOrderErrors();

    $.get(`${window.routes.customerPurchaseOrderShow}/${id}`)
        .done(function (response) {
            fillCustomerPurchaseOrderForm(response.data);
            $('#customerPurchaseOrderModalLabel').text('Editar Orden de Compra del Cliente');
            $('#btnSaveCustomerPurchaseOrder')
                .html('<i class="fas fa-save mr-1"></i> Actualizar');
            $('#btnSaveCustomerPurchaseOrderTop')
                .html('<i class="fas fa-save mr-1"></i><span>Actualizar</span>');
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
    $('#purchase_order_delivery_days').val(order.delivery_days || '');
    $('#purchase_order_delivery_end_date').val(formatPurchaseOrderDate(order.delivery_end_date));
    $('#purchase_order_siaf_file_number').val(order.siaf_file_number || '');
    $('#purchase_order_acquisition_chart_number').val(order.acquisition_chart_number || '');
    $('#purchase_order_process_type').val(order.process_type || '');
    $('#purchase_order_billing_type').val(order.billing_type || 'local').trigger('change.select2');
    $('#purchase_order_affect_igv').val(order.affect_igv ? '1' : '0');
    $('#purchase_order_observations').val(order.observations || '');
    $('#purchase_order_seller_dni').val(order.seller_dni || '');
    $('#purchase_order_seller_names').val(order.seller_names || '');
    $('#purchase_order_seller_lastnames').val(order.seller_lastnames || '');
    $('#purchase_order_seller_full_name').val(order.seller_full_name || '');
    $('#purchase_order_seller_phone').val(order.seller_phone || '');
    $('#purchase_order_seller_email').val(order.seller_email || '');
    $('#purchase_order_seller_observation').val(order.seller_observation || '');

    if (order.seller_user_id) {
        const sellerUser = order.seller_user || {
            id: order.seller_user_id,
            dni: order.seller_dni,
            names: order.seller_names,
            lastnames: order.seller_lastnames,
            full_name: order.seller_full_name,
            phone: order.seller_phone,
            email: order.seller_email
        };
        ensurePurchaseOrderSellerOption(sellerUser);
        $('#purchase_order_seller_mode').val('USER').trigger('change.select2');
        $('#purchase_order_seller_type').val('USER');
        $('#purchase_order_seller_user_picker').val(String(order.seller_user_id)).trigger('change.select2');
        $('#purchase_order_seller_user_id').val(order.seller_user_id);
        $('#purchaseOrderSellerLookupStatus').removeClass('text-muted text-warning').addClass('text-success')
            .text('Vinculado a un usuario del sistema.');
    } else {
        const sellerMode = order.seller_type === 'EXTERNAL' ? 'EXTERNAL' : '';
        $('#purchase_order_seller_mode').val(sellerMode).trigger('change.select2');
        $('#purchase_order_seller_type').val(order.seller_type || '');
        $('#purchase_order_seller_user_picker').val('').trigger('change.select2');
        $('#purchase_order_seller_user_id').val('');
        $('#purchaseOrderSellerLookupStatus').removeClass('text-success text-warning').addClass('text-muted').text('');
    }
    updatePurchaseOrderSellerUi();

    $('#purchaseOrderDocumentsTbody').empty();
    (order.documents || []).forEach(addExistingPurchaseOrderDocumentRow);
    showEmptyPurchaseOrderDocumentsRow();

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

function loadCustomerPurchaseOrderDetail(id, preserveActiveTab = false) {
    const activeTab = preserveActiveTab
        ? ($('#customerOrderViewTabs a.active').attr('href') || '#vpo_tab_dispatches')
        : '#vpo_tab_summary';
    $.get(`${window.routes.customerPurchaseOrderShow}/${id}`)
        .done(function (response) {
            fillCustomerPurchaseOrderDetail(response.data);
            $(`#customerOrderViewTabs a[href="${activeTab}"]`).tab('show');
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

function customerPurchaseOrderStatusMeta(statusCode) {
    const statuses = {
        approved: ['REGISTRADA', 'badge-secondary', 'Orden registrada, pendiente de compra al proveedor.', 0],
        registered: ['REGISTRADA', 'badge-secondary', 'Orden registrada, pendiente de compra al proveedor.', 0],
        partial_purchase: ['COMPRA PARCIAL', 'badge-warning text-dark', 'Parte de la mercadería ya tiene una compra a proveedor vinculada.', 1],
        in_purchase: ['COMPRA EN PROCESO', 'badge-warning text-dark', 'Ya existe una compra a proveedor vinculada, pero la mercadería aún no ha ingresado completa al almacén.', 1],
        partial_entered: ['INGRESO PARCIAL', 'badge-info', 'Llegó parte de la mercadería al almacén.', 2],
        entered: ['ABASTECIDA EN ALMACÉN', 'badge-success', 'Mercadería ingresada, falta atención o despacho.', 2],
        partial_dispatched: ['DESPACHO PARCIAL', 'badge-warning text-dark', 'Parte de la mercadería ya salió del almacén; aún queda saldo por despachar.', 3],
        attended: ['ATENDIDA / DESPACHADA', 'badge-primary', 'Mercadería despachada o atención cerrada.', 3],
        not_attended: ['NO ATENDIDA', 'badge-dark', 'La atención fue cerrada sin completar la entrega.', 3],
        cancelled: ['ANULADA', 'badge-danger', 'Orden anulada.', 0],
        delivered: ['ENTREGADA', 'badge-primary', 'Recepción confirmada por el cliente.', 3],
        invoiced: ['FACTURADA', 'badge-info', 'Comprobante emitido.', 3]
    };

    return statuses[statusCode] || [String(statusCode || '').toUpperCase(), 'badge-secondary', '', 0];
}

function fillCustomerPurchaseOrderDetail(order) {
    const currencyCode = order.currency?.code || '';
    const currencySymbol = order.currency?.symbol || '';
    const status = customerPurchaseOrderStatusMeta(order.status);

    $('#vpo_code').text(order.code || '—');
    $('#vpo_purchase_order_number').text(order.purchase_order_number || 'Sin número de cliente');
    $('#vpo_status')
        .text(status[0])
        .attr('class', `badge ${status[1]} rounded-pill px-3 py-2`)
        .attr('title', status[2] || '');
    $('#vpo_status_help').text(status[2] || '');
    $('#vpo_customer').text(customerPurchaseOrderName(order.customer));
    $('#vpo_branch').text(order.customer_branch?.branch_name || 'Sin sucursal');
    $('#vpo_company').text(order.company?.trade_name || order.company?.business_name || '—');
    $('#vpo_quote').text(order.quote?.quote_number || 'Sin cotización');
    $('#vpo_currency').text(
        [currencyCode, order.currency?.description].filter(Boolean).join(' | ') || '—'
    );
    $('#vpo_currency_symbol').text(currencySymbol);
    $('#vpo_grand_total').text(formatDecimalView(order.grand_total));
    $('#vpo_order_type').text(order.order_type === 'services' ? 'SERVICIOS' : 'ARTÍCULOS');
    $('#vpo_billing_type').text(order.billing_type === 'export' ? 'EXPORTACIÓN' : 'LOCAL');
    $('#vpo_affect_igv').text('POR ÍTEM (10/20/30)');
    $('#vpo_notification_date').text(formatPurchaseOrderDisplayDate(order.notification_date));
    $('#vpo_delivery_start_date').text(formatPurchaseOrderDisplayDate(order.delivery_start_date));
    $('#vpo_delivery_end_date').text(formatPurchaseOrderDisplayDate(order.delivery_end_date));
    $('#vpo_siaf').text(order.siaf_file_number || '—');
    $('#vpo_chart').text(order.acquisition_chart_number || '—');
    $('#vpo_process').text(order.process_type || '—');
    $('#vpo_observations').text(order.observations || 'Sin observaciones');
    $('#vpo_subtotal_exonerated').text(`${currencyCode} ${formatDecimalView(order.subtotal_exonerated)}`);
    $('#vpo_subtotal_unaffected').text(`${currencyCode} ${formatDecimalView(order.subtotal_unaffected || 0)}`);
    $('#vpo_subtotal_taxed').text(`${currencyCode} ${formatDecimalView(order.subtotal_taxed)}`);
    $('#vpo_igv').text(`${currencyCode} ${formatDecimalView(order.igv)}`);
    $('#vpo_total').text(`${currencyCode} ${formatDecimalView(order.grand_total)}`);
    const hasSeller = Boolean(order.seller_dni || order.seller_full_name);
    const creatorName = order.creator
        ? [order.creator.name, order.creator.lastname].filter(Boolean).join(' ')
        : '—';
    $('#vpo_seller_card').toggleClass('d-none', !hasSeller);
    $('#vpo_seller_empty').toggleClass('d-none', hasSeller);
    $('#vpo_seller_type').text(order.seller_type === 'USER' ? 'Usuario del sistema' : 'Externo');
    $('#vpo_seller_dni').text(order.seller_dni || '—');
    $('#vpo_seller_full_name').text(order.seller_full_name || '—');
    $('#vpo_seller_phone').text(order.seller_phone || '—');
    $('#vpo_seller_email').text(order.seller_email || '—');
    $('#vpo_seller_observation').text(order.seller_observation || 'Sin observación');
    $('#vpo_created_by').text(creatorName);

    const billingSummary = order.billing_summary || {};
    $('#vpo_billing_order_total').text(`${currencyCode} ${formatDecimalView(billingSummary.order_total || 0)}`.trim());
    $('#vpo_billed_amount').text(`${currencyCode} ${formatDecimalView(billingSummary.billed_amount || 0)}`.trim());
    $('#vpo_unbilled_amount').text(`${currencyCode} ${formatDecimalView(billingSummary.unbilled_amount || 0)}`.trim());
    $('#vpo_paid_amount').text(`${currencyCode} ${formatDecimalView(billingSummary.paid_amount || 0)}`.trim());
    $('#vpo_pending_amount').text(`${currencyCode} ${formatDecimalView(billingSummary.pending_amount || 0)}`.trim());

    const supplierOrders = order.supplier_purchase_orders || [];
    const warehouseAllocations = order.warehouse_entry_allocations || [];
    const dispatches = order.warehouse_dispatches || [];
    const activeDispatches = dispatches.filter(dispatch => dispatch.status === 'confirmed');
    const invoices = billingSummary.invoices || [];
    const billedAmount = Number(billingSummary.billed_amount || 0);
    const pendingAmount = Number(billingSummary.pending_amount || 0);
    const billingLabel = invoices.length === 0
        ? 'Sin facturas emitidas'
        : (pendingAmount <= 0 ? 'Facturada y cobrada'
            : (billedAmount >= Number(billingSummary.order_total || order.grand_total || 0)
                ? 'Facturada · cobro pendiente'
                : 'Facturación parcial'));
    const customerName = customerPurchaseOrderName(order.customer);
    const companyName = order.company?.trade_name || order.company?.business_name || '—';
    const sellerName = order.seller_full_name || creatorName;
    const deliveryRange = [formatPurchaseOrderDisplayDate(order.delivery_start_date), formatPurchaseOrderDisplayDate(order.delivery_end_date)]
        .filter(value => value && value !== '—').join(' → ') || 'Sin plazo definido';

    $('#vpo_side_operational_status, #vpo_summary_status').text(status[0]);
    $('#vpo_side_billing_status, #vpo_summary_billing').text(billingLabel);
    $('#vpo_side_item_count').text((order.items || []).length);
    $('#vpo_side_purchase_count, #vpo_supplier_order_count').text(supplierOrders.length);
    $('#vpo_side_dispatch_count, #vpo_dispatch_count').text(activeDispatches.length);
    $('#vpo_entry_count').text(new Set(warehouseAllocations.map(allocation => allocation.warehouse_entry_id).filter(Boolean)).size);
    $('#vpo_invoice_count').text(invoices.length);
    $('#vpo_summary_customer').text(customerName);
    $('#vpo_summary_seller').text(sellerName || 'Sin gestor registrado');
    $('#vpo_summary_company').text(companyName);
    $('#vpo_summary_currency').text([currencyCode, order.currency?.description].filter(Boolean).join(' | ') || '—');
    $('#vpo_summary_total').text(`${currencyCode} ${formatDecimalView(order.grand_total)}`.trim());
    $('#vpo_summary_delivery').text(deliveryRange);
    $('#vpo_requested_total').text(formatDecimalView(order.requested_quantity_total || 0));
    $('#vpo_entered_total').text(formatDecimalView(order.entered_quantity_total || 0));
    $('#vpo_dispatched_total').text(formatDecimalView(order.dispatched_quantity_total || 0));
    $('#vpo_pending_dispatch_total').text(formatDecimalView(order.pending_dispatch_quantity_total || 0));

    const timelineSteps = [
        ['Registrada', 'Orden recibida'],
        ['Compra en proceso', 'Abastecimiento'],
        ['Ingreso / Abastecida', 'En almacén'],
        ['Despacho / Atendida', 'Salida física']
    ];
    $('#vpo_operational_timeline').html(timelineSteps.map((step, index) => `
        <div class="customer-order-view-timeline-step ${index <= status[3] ? 'is-complete' : ''} ${index === status[3] ? 'is-current' : ''}">
            <strong>${step[0]}</strong><small>${step[1]}</small>
        </div>
    `).join(''));

    const invoiceStatusLabels = {
        pending: ['PENDIENTE DE COBRO', 'badge-warning text-dark'],
        partial: ['COBRO PARCIAL', 'badge-info'],
        paid: ['COBRADA', 'badge-success']
    };
    const invoiceRows = invoices.map(function (invoice) {
        let paymentStatus = invoiceStatusLabels[invoice.payment_status] || invoiceStatusLabels.pending;
        const dueDate = invoice.due_date ? new Date(`${String(invoice.due_date).slice(0, 10)}T23:59:59`) : null;
        if (invoice.payment_status !== 'paid' && dueDate && dueDate < new Date()) {
            paymentStatus = ['VENCIDA', 'badge-danger'];
        }

        return `
            <tr>
                <td class="font-weight-bold">${escapePurchaseOrderHtml(invoice.full_number || '-')}</td>
                <td>${formatPurchaseOrderDisplayDate(invoice.issue_date)}</td>
                <td>${formatPurchaseOrderDisplayDate(invoice.due_date)}</td>
                <td><span class="badge ${paymentStatus[1]}">${paymentStatus[0]}</span></td>
                <td class="text-right">${escapePurchaseOrderHtml(invoice.currency_code || currencyCode)} ${formatDecimalView(invoice.total_amount)}</td>
                <td class="text-right text-success">${escapePurchaseOrderHtml(invoice.currency_code || currencyCode)} ${formatDecimalView(invoice.paid_amount)}</td>
                <td class="text-right font-weight-bold">${escapePurchaseOrderHtml(invoice.currency_code || currencyCode)} ${formatDecimalView(invoice.pending_amount)}</td>
                <td>${escapePurchaseOrderHtml([invoice.creator?.name, invoice.creator?.lastname].filter(Boolean).join(' ') || '-')}</td>
                <td class="text-center">${invoice.pdf_url ? `<a href="${escapePurchaseOrderHtml(invoice.pdf_url)}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-danger"><i class="fas fa-file-pdf"></i></a>` : '-'}</td>
            </tr>
        `;
    }).join('');
    $('#vpo_invoices_body').html(
        invoiceRows || '<tr><td colspan="9" class="text-center text-muted py-3">Sin facturas generadas</td></tr>'
    );

    const collectionRows = invoices.flatMap(function (invoice) {
        return (invoice.collections || []).map(function (collection) {
            const bank = collection.account?.bank?.short_name || collection.account?.bank?.description || '-';
            const account = collection.account?.account_number || '';
            const creator = [collection.creator?.name, collection.creator?.lastname].filter(Boolean).join(' ') || '-';
            return `
                <tr>
                    <td>${formatPurchaseOrderDisplayDate(collection.collection_date)}</td>
                    <td>${escapePurchaseOrderHtml(invoice.full_number || '-')}</td>
                    <td>${escapePurchaseOrderHtml([bank, account].filter(Boolean).join(' - '))}</td>
                    <td>${escapePurchaseOrderHtml(collection.operation_number || '-')}</td>
                    <td class="text-right">${escapePurchaseOrderHtml(collection.currency?.code || '')} ${formatDecimalView(collection.amount)}</td>
                    <td>${escapePurchaseOrderHtml(creator)}</td>
                    <td class="text-center">${collection.proof_url ? `<a href="${escapePurchaseOrderHtml(collection.proof_url)}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary"><i class="fas fa-paperclip"></i></a>` : '-'}</td>
                </tr>
            `;
        });
    }).join('');
    $('#vpo_collections_body').html(
        collectionRows || '<tr><td colspan="7" class="text-center text-muted py-3">Sin cobros confirmados</td></tr>'
    );

    const items = order.items || [];
    const supplyStatuses = {
        registered: ['REGISTRADA', 'badge-secondary', 'Orden registrada, pendiente de compra al proveedor.'],
        partial_purchase: ['EN COMPRA PARCIAL', 'badge-warning text-dark'],
        in_purchase: ['COMPRA EN PROCESO', 'badge-warning text-dark', 'Ya existe una compra a proveedor vinculada, pero la mercadería aún no ha ingresado completa al almacén.'],
        partial_entered: ['INGRESO PARCIAL', 'badge-info', 'Llegó parte de la mercadería al almacén.'],
        entered: ['ABASTECIDA EN ALMACÉN', 'badge-success', 'Mercadería ingresada, falta atención o despacho.'],
        partial_dispatched: ['DESPACHO PARCIAL', 'badge-warning text-dark', 'Parte de la mercadería ya salió del almacén; aún queda saldo por despachar.'],
        attended: ['ATENDIDA / DESPACHADA', 'badge-success', 'Toda la mercadería requerida salió del almacén.']
    };
    const rows = items.map(function (item, index) {
        const itemStatus = supplyStatuses[item.operational_status || item.supply_status] || ['PENDIENTE', 'badge-secondary'];

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
                <td class="text-right">${formatDecimalView(item.requested_quantity ?? item.quantity)}</td>
                <td class="text-right">${formatDecimalView(item.purchase_quantity)}</td>
                <td class="text-right">${formatDecimalView(item.entered_quantity)}</td>
                <td class="text-right text-success font-weight-bold">${formatDecimalView(item.dispatched_quantity)}</td>
                <td class="text-right text-danger font-weight-bold">${formatDecimalView(item.pending_dispatch_quantity)}</td>
                <td class="text-center">
                    <span class="badge ${itemStatus[1]} px-2 py-1" title="${escapePurchaseOrderHtml(itemStatus[2] || '')}">${itemStatus[0]}</span>
                </td>
            </tr>
        `;
    }).join('');

    $('#vpo_items_body').html(
        rows || '<tr><td colspan="7" class="text-center text-muted py-3">Sin items registrados</td></tr>'
    );

    renderCustomerOrderDispatches(dispatches);
    $('#vpo_register_dispatch')
        .toggleClass('d-none', !order.can_dispatch)
        .data('id', order.id)
        .data('code', order.code || '');

    const supplierStatusLabels = {
        registered: ['REGISTRADA', 'badge-secondary'],
        sent: ['ENVIADA', 'badge-info'],
        approved: ['APROBADA', 'badge-primary'],
        partial_entered: ['INGRESO PARCIAL', 'badge-warning text-dark'],
        entered: ['INGRESADA', 'badge-success'],
        invoiced: ['FACTURADA', 'badge-info'],
        cancelled: ['ANULADA', 'badge-danger']
    };
    const supplierOrderCards = supplierOrders.map(function (supplierOrder) {
        const supplier = supplierOrder.supplier?.short_name || supplierOrder.supplier?.business_name || 'Proveedor sin nombre';
        const supplierStatus = supplierStatusLabels[supplierOrder.status] || [String(supplierOrder.status || 'REGISTRADA').toUpperCase(), 'badge-secondary'];
        const supplierCurrency = supplierOrder.currency?.code || currencyCode;
        const entryCount = (supplierOrder.warehouse_entries || []).filter(entry => entry.status !== 'cancelled').length;

        return `<article class="customer-order-view-card">
            <div class="customer-order-view-card-head"><div><h6>${escapePurchaseOrderHtml(supplierOrder.code || 'OC Proveedor')}</h6><small><i class="fas fa-building mr-1"></i>${escapePurchaseOrderHtml(supplier)}</small></div><span class="badge ${supplierStatus[1]}">${supplierStatus[0]}</span></div>
            <dl><dt>Fecha</dt><dd>${formatPurchaseOrderDisplayDate(supplierOrder.created_at)}</dd><dt>Monto</dt><dd>${escapePurchaseOrderHtml(supplierCurrency)} ${formatDecimalView(supplierOrder.grand_total)}</dd><dt>Ingresos a almacén</dt><dd>${entryCount}</dd></dl>
            <div class="mt-2 pt-2 border-top text-muted small"><i class="fas fa-shopping-cart text-primary mr-1"></i>Compra proveedor <i class="fas fa-long-arrow-alt-right mx-1"></i><i class="fas fa-warehouse text-info mr-1"></i>${entryCount ? `${entryCount} ingreso(s)` : 'Ingreso pendiente'}</div>
        </article>`;
    }).join('');
    $('#vpo_supplier_orders').html(supplierOrderCards || '<div class="customer-order-view-empty"><i class="fas fa-shopping-basket"></i><strong>Aún no hay compras proveedoras vinculadas.</strong><span>La orden permanece pendiente de abastecimiento.</span></div>');

    const traceByEntry = warehouseAllocations.reduce(function (groups, allocation) {
        const entry = allocation.warehouse_entry;
        if (!entry) return groups;
        const key = String(entry.id);
        if (!groups[key]) groups[key] = { entry, allocations: [] };
        groups[key].allocations.push(allocation);
        return groups;
    }, {});
    const traceRows = Object.values(traceByEntry).map(function (group) {
        const entry = group.entry;
        const supplier = entry.supplier?.short_name || entry.supplier?.business_name
            || group.allocations.find(allocation => allocation.supplier_purchase_order?.supplier)?.supplier_purchase_order?.supplier?.business_name
            || '-';
        const document = [entry.document_type, entry.document_series, entry.document_number].filter(Boolean).join(' ');
        const quantities = group.allocations.map(allocation => {
            const article = allocation.warehouse_entry_item?.billing_name_snapshot
                || allocation.warehouse_entry_item?.article?.billing_name || 'Artículo';
            return `<li>${escapePurchaseOrderHtml(article)}: <strong>${formatDecimalView(allocation.quantity_allocated)}</strong></li>`;
        }).join('');

        return `<article class="customer-order-view-card">
            <div class="customer-order-view-card-head">
                <div><h6>${escapePurchaseOrderHtml(entry.entry_number || '-')}</h6><small>${escapePurchaseOrderHtml(document || 'Sin comprobante')} · ${escapePurchaseOrderHtml(supplier)}</small></div>
                <span class="badge badge-info">${formatPurchaseOrderDisplayDate(entry.document_date)}</span>
            </div>
            <ul class="mb-0 mt-2 pl-3">${quantities}</ul>
        </article>`;
    }).join('');
    $('#vpo_warehouse_trace').html(traceRows || '<div class="customer-order-view-empty"><i class="fas fa-box-open"></i><strong>Aún no hay ingresos asignados.</strong><span>Los ingresos vinculados aparecerán en esta cronología.</span></div>');

    const documentRows = (order.documents || []).map(function (document) {
        return `
            <tr>
                <td>${escapePurchaseOrderHtml(document.document_type?.description || 'Sin tipo')}</td>
                <td>${escapePurchaseOrderHtml(document.original_name || 'Documento')}</td>
                <td class="text-center">
                    <a href="${escapePurchaseOrderHtml(document.url || '#')}" target="_blank"
                        rel="noopener" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-external-link-alt mr-1"></i> Abrir
                    </a>
                </td>
            </tr>
        `;
    }).join('');

    $('#vpo_documents_body').html(
        documentRows || '<tr><td colspan="3" class="text-center text-muted py-3">Sin documentos adjuntos</td></tr>'
    );

    const hasAttentionClosure = Boolean(
        order.attention_result || order.attention_closed_at || order.attention_document_url
    );
    $('#vpo_attention_closure').toggleClass('d-none', !hasAttentionClosure);

    if (hasAttentionClosure) {
        const closedBy = order.attention_closed_by;
        const closedByName = closedBy?.name
            || closedBy?.full_name
            || closedBy?.username
            || closedBy?.email
            || '—';

        $('#vpo_attention_result').text(
            order.attention_result === 'attended'
                ? 'ATENDIDO'
                : (order.attention_result === 'not_attended' ? 'NO ATENDIDO' : '—')
        );
        $('#vpo_attention_closed_at').text(formatPurchaseOrderDisplayDate(order.attention_closed_at));
        $('#vpo_attention_closed_by').text(closedByName);
        $('#vpo_attention_observation').text(order.attention_observation || 'Sin observación');

        const hasDocument = Boolean(order.attention_document_url);
        $('#vpo_attention_document_wrapper').toggleClass('d-none', !hasDocument);
        $('#vpo_attention_document')
            .attr('href', hasDocument ? order.attention_document_url : '#')
            .attr('title', order.attention_document_name || 'Abrir sustento');
    }
}

function openCustomerPurchaseOrderAttentionModal(orderId, orderCode) {
    const form = $('#closeCustomerPurchaseOrderAttentionForm');
    form[0].reset();
    form.find('.is-invalid').removeClass('is-invalid');
    form.find('.invalid-feedback').text('');
    $('#closeAttentionErrors').addClass('d-none').empty();
    $('#close_attention_order_id').val(orderId);
    $('#closeAttentionOrderCode').text(orderCode || '—');
    $('#closeAttentionPurchaseNumber, #closeAttentionCustomer, #closeAttentionBranch, #closeAttentionTotal').text('Cargando...');
    $('#attentionFileName').text('Ningún archivo seleccionado');
    $('#attention_closed_at').val(localPurchaseOrderDate());
    $('#attention_observation').prop('required', false);
    $('#attentionObservationRequired').addClass('d-none');
    $('#btnSaveAttentionClosure')
        .prop('disabled', false)
        .html('<i class="fas fa-save mr-1"></i> Guardar cierre');
    $('#closeCustomerPurchaseOrderAttentionModal').modal('show');

    $.get(`${window.routes.customerPurchaseOrderShow}/${orderId}`)
        .done(function (response) {
            const order = response.data || {};
            const currency = order.currency?.symbol || order.currency?.code || '';
            $('#closeAttentionOrderCode').text(order.code || orderCode || '—');
            $('#closeAttentionPurchaseNumber').text(order.purchase_order_number || '—');
            $('#closeAttentionCustomer').text(customerPurchaseOrderName(order.customer));
            $('#closeAttentionBranch').text(order.customer_branch?.branch_name || 'Sin sucursal');
            $('#closeAttentionTotal').text(`${currency} ${formatDecimalView(order.grand_total)}`.trim());
        })
        .fail(function () {
            $('#closeAttentionPurchaseNumber, #closeAttentionCustomer, #closeAttentionBranch, #closeAttentionTotal').text('No disponible');
        });
}

function updateAttentionFileName() {
    const file = $('#attention_file')[0]?.files?.[0];
    $('#attentionFileName').text(file?.name || 'Ningún archivo seleccionado');
}

function saveCustomerPurchaseOrderAttention(formElement) {
    const orderId = $('#close_attention_order_id').val();
    const button = $('#btnSaveAttentionClosure');
    const formData = new FormData(formElement);
    const url = `${window.routes.customerPurchaseOrderCloseAttention}/${orderId}/close-attention`;

    $(formElement).find('.is-invalid').removeClass('is-invalid');
    $(formElement).find('.invalid-feedback').text('');
    $('#closeAttentionErrors').addClass('d-none').empty();
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false
    })
        .done(function (response) {
            $('#closeCustomerPurchaseOrderAttentionModal').modal('hide');
            tableCustomerPurchaseOrder.ajax.reload(null, false);
            Swal.fire({
                icon: 'success',
                title: 'Atención cerrada',
                text: response.message || 'La atención de la orden fue cerrada correctamente.'
            });
        })
        .fail(function (xhr) {
            const errors = xhr.responseJSON?.errors || {};
            const messages = [];

            Object.entries(errors).forEach(function ([field, fieldMessages]) {
                const input = $(formElement).find(`[name="${field}"]`);
                const message = fieldMessages[0];
                input.addClass('is-invalid');
                input.closest('.form-group').find('.invalid-feedback').text(message);
                messages.push(message);
            });

            const message = messages[0]
                || xhr.responseJSON?.message
                || 'No se pudo cerrar la atención de la orden.';
            $('#closeAttentionErrors').removeClass('d-none').text(message);
        })
        .always(function () {
            button.prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar cierre');
        });
}

function localPurchaseOrderDate() {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function purchaseOrderDocumentTypeOptions(selectedId = '') {
    const options = ['<option value="">Sin tipo</option>'];

    (window.purchaseOrderDocumentTypes || []).forEach(function (type) {
        const selected = String(type.id) === String(selectedId) ? ' selected' : '';
        options.push(`<option value="${type.id}"${selected}>${escapePurchaseOrderHtml(type.description)}</option>`);
    });

    return options.join('');
}

function addPurchaseOrderDocumentRow() {
    const index = purchaseOrderDocumentIndex++;
    $('#purchaseOrderDocumentsTbody .purchase-order-documents-empty').remove();
    $('#purchaseOrderDocumentsTbody').append(`
        <tr class="purchase-order-document-row">
            <td>
                <select name="documents[${index}][document_type_id]" class="form-control form-control-sm">
                    ${purchaseOrderDocumentTypeOptions()}
                </select>
            </td>
            <td>
                <div class="purchase-order-file-picker">
                    <input type="file" id="purchase_order_document_file_${index}"
                        name="documents[${index}][file]"
                        class="purchase-order-document-file custom-file-input-real"
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx" required>
                    <label for="purchase_order_document_file_${index}" class="purchase-order-file-picker-label">
                        <i class="fas fa-paperclip mr-1"></i> Seleccionar archivo
                    </label>
                    <span class="purchase-order-file-name">Ningún archivo seleccionado</span>
                </div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm remove-purchase-order-document" title="Quitar">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `);
}

function addExistingPurchaseOrderDocumentRow(document) {
    $('#purchaseOrderDocumentsTbody').append(`
        <tr class="purchase-order-document-row" data-document-id="${document.id}">
            <td>${escapePurchaseOrderHtml(document.document_type?.description || 'Sin tipo')}</td>
            <td class="text-break">
                <i class="fas fa-file-alt text-primary mr-1"></i>
                ${escapePurchaseOrderHtml(document.original_name || 'Documento')}
            </td>
            <td class="text-center text-nowrap">
                <a href="${escapePurchaseOrderHtml(document.url || '#')}" target="_blank" rel="noopener"
                    class="btn btn-outline-primary btn-sm" title="Abrir"><i class="fas fa-eye"></i></a>
                <button type="button" class="btn btn-outline-danger btn-sm remove-purchase-order-document" title="Quitar">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `);
}

function showEmptyPurchaseOrderDocumentsRow() {
    const tbody = $('#purchaseOrderDocumentsTbody');
    if (!tbody.find('.purchase-order-document-row').length) {
        tbody.html('<tr class="purchase-order-documents-empty"><td colspan="3" class="text-center text-muted py-4 customer-po-empty-state"><i class="far fa-folder-open"></i><strong>Sin documentos adjuntos</strong><small>Use “Agregar documento” para incorporar archivos.</small></td></tr>');
    }
}

function parseCustomerOrderLocalDate(value) {
    if (!value) return null;
    const parts = String(value).split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
    return new Date(parts[0], parts[1] - 1, parts[2]);
}

function formatCustomerOrderDateInput(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function addCustomerOrderDays(date, days) {
    const result = new Date(date.getTime());
    result.setDate(result.getDate() + Number(days));
    return result;
}

function recalculateCustomerOrderDeliveryDates() {
    const notificationDate = parseCustomerOrderLocalDate($('#purchase_order_notification_date').val());
    const deliveryDays = Number.parseInt($('#purchase_order_delivery_days').val(), 10);

    if (!notificationDate) {
        $('#purchase_order_delivery_start_date, #purchase_order_delivery_end_date').val('');
        return;
    }

    $('#purchase_order_delivery_start_date').val(
        formatCustomerOrderDateInput(addCustomerOrderDays(notificationDate, 1))
    );

    if (!Number.isInteger(deliveryDays) || deliveryDays < 1) {
        $('#purchase_order_delivery_end_date').val('');
        return;
    }

    $('#purchase_order_delivery_end_date').val(
        formatCustomerOrderDateInput(addCustomerOrderDays(notificationDate, deliveryDays))
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
    $('.purchase-order-tab-error').addClass('d-none');
}

function activatePurchaseOrderTab(section, hasError = false) {
    const tab = $(`.purchase-order-tabs .nav-link[data-section="${section}"]`);
    if (hasError) tab.find('.purchase-order-tab-error').removeClass('d-none');
    if (tab.length) tab.tab('show');
}

function purchaseOrderErrorSection(field, input) {
    if (String(field).startsWith('seller_')) return 'seller';
    if (String(field).startsWith('documents')) return 'documents';
    if (String(field).startsWith('items')) return 'items';
    const pane = input.closest('.tab-pane').attr('id') || '';
    if (pane.includes('seller')) return 'seller';
    if (pane.includes('documents')) return 'documents';
    if (pane.includes('items')) return 'items';
    return 'data';
}

function showCustomerPurchaseOrderErrors(errors) {
    const errorMessages = [];
    const sections = [];

    Object.entries(errors).forEach(function ([field, fieldMessages]) {
        let input = $(`[name="${field}"]`);
        if (field === 'seller_user_id') {
            input = $('#purchase_order_seller_user_picker');
        }
        if (!input.length && field.includes('.')) {
            const bracketName = field.replace(/\.(\d+)\./g, '[$1][');
            const normalizedName = bracketName.includes('[') ? `${bracketName}]` : bracketName;
            input = $(`[name="${normalizedName}"]`);
        }
        const message = fieldMessages[0];
        const section = purchaseOrderErrorSection(field, input);
        if (!sections.includes(section)) sections.push(section);
        $(`.purchase-order-tabs .nav-link[data-section="${section}"] .purchase-order-tab-error`).removeClass('d-none');

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

    if (sections.length) activatePurchaseOrderTab(sections[0], true);
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

function formatPurchaseOrderRaw(value) {
    if (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value.trim())) {
        const normalized = value.trim().replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1');

        return normalized === '-0' ? '0' : normalized;
    }

    const number = Number(value);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return number.toLocaleString('en-US', {
        useGrouping: false,
        maximumFractionDigits: 10,
    });
}

function formatDecimalView(value, decimals = 3) {
    const number = Number.parseFloat(value || 0);

    return Number.isNaN(number) ? (0).toFixed(decimals) : number.toFixed(decimals);
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

let currentWarehouseDispatchData = null;
let warehouseDispatchStocksLoading = false;
let warehouseDispatchStocksError = false;
let warehouseDispatchSubmitting = false;
let warehouseDispatchStocksRequest = null;
let warehouseDispatchAssignmentSequence = 0;
let warehouseDispatchDocumentSequence = 0;
let currentWarehouseDispatchDocumentContext = null;
let currentWarehouseDispatchId = null;
let warehouseDispatchDirty = false;
let warehouseDispatchInitializing = false;

$(document).on('click', '.registerWarehouseDispatch, #vpo_register_dispatch', function () {
    openWarehouseDispatch($(this).data('id'), null);
});

$(document).on('click', '.editWarehouseDispatch', function () {
    openWarehouseDispatch($(this).data('order-id'), $(this).data('dispatch-id'));
});

$(document).on('change', '#warehouse_dispatch_warehouse_id', function () {
    const warehouseId = String($(this).val() || '');
    resetWarehouseDispatchStocks();
    if (warehouseId) {
        loadWarehouseDispatchStocks(warehouseId);
    } else {
        renderWarehouseDispatchItems();
    }
});

$(document).on('click', '.add-dispatch-assignment', function () {
    const item = warehouseDispatchItem($(this).closest('.warehouse-dispatch-item-block').data('item-id'));
    if (!item) return;
    item.assignments = item.assignments || [];
    item.assignments.push(newWarehouseDispatchAssignment());
    renderWarehouseDispatchItems();
});

$(document).on('click', '.remove-dispatch-assignment', function () {
    const block = $(this).closest('.warehouse-dispatch-item-block');
    const item = warehouseDispatchItem(block.data('item-id'));
    if (!item) return;
    const assignmentKey = Number($(this).closest('.warehouse-dispatch-assignment-row').data('assignment-key'));
    item.assignments = (item.assignments || []).filter(assignment => assignment.key !== assignmentKey);
    ensureWarehouseDispatchAssignments(item);
    renderWarehouseDispatchItems();
});

$(document).on('change', '.dispatch-stock-select', function () {
    const row = $(this).closest('.warehouse-dispatch-assignment-row');
    const item = warehouseDispatchItem($(this).closest('.warehouse-dispatch-item-block').data('item-id'));
    const assignment = warehouseDispatchAssignment(item, row.data('assignment-key'));
    if (!assignment) return;
    assignment.stock_id = String($(this).val() || '');
    assignment.quantity = '0';
    renderWarehouseDispatchItems();
});

$(document).on('input change', '.dispatch-quantity', function () {
    const row = $(this).closest('.warehouse-dispatch-assignment-row');
    const item = warehouseDispatchItem($(this).closest('.warehouse-dispatch-item-block').data('item-id'));
    const assignment = warehouseDispatchAssignment(item, row.data('assignment-key'));
    if (!assignment) return;
    assignment.quantity = String($(this).val() || '0');
    updateWarehouseDispatchItemTotals(item);
    updateWarehouseDispatchSubmitState();
});

$(document).on('submit', '#warehouseDispatchForm', function (event) {
    event.preventDefault();
    saveWarehouseDispatch();
});

$(document).on('input change', '#warehouseDispatchForm :input', function () {
    if (warehouseDispatchInitializing) return;
    warehouseDispatchDirty = true;
    updateWarehouseDispatchSubmitState();
});

$(document).on('click', '#btnConfirmWarehouseDispatch, .confirmWarehouseDispatch', function () {
    confirmWarehouseDispatch(
        $(this).data('order-id') || $('#warehouse_dispatch_order_id').val(),
        $(this).data('dispatch-id') || currentWarehouseDispatchId
    );
});

$(document).on('click', '.cancelDraftWarehouseDispatch', function () {
    cancelDraftWarehouseDispatch($(this).data('order-id'), $(this).data('dispatch-id'));
});

$(document).on('click', '.addWarehouseDispatchDocument', function () {
    addWarehouseDispatchDocumentRow($(this).data('target'));
});

$(document).on('click', '.removeWarehouseDispatchDocument', function () {
    if ($(this).closest('#warehouseDispatchCreateDocuments').length) warehouseDispatchDirty = true;
    $(this).closest('.warehouse-dispatch-document-row').remove();
    updateWarehouseDispatchDocumentFormState();
    updateWarehouseDispatchSubmitState();
});

$(document).on('change input', '.warehouse-dispatch-document-row input, .warehouse-dispatch-document-row select', function () {
    updateWarehouseDispatchDocumentFormState();
    updateWarehouseDispatchSubmitState();
});

$(document).on('click', '.manageWarehouseDispatchDocuments', function () {
    openWarehouseDispatchDocuments($(this).data('order-id'), $(this).data('dispatch-id'), $(this).data('dispatch-number'));
});

$(document).on('submit', '#warehouseDispatchDocumentsForm', function (event) {
    event.preventDefault();
    saveWarehouseDispatchDocuments();
});

$(document).on('click', '.deleteWarehouseDispatchDocument', function () {
    deleteWarehouseDispatchDocument($(this).data('document-id'));
});

$(document).on('click', '.reverseWarehouseDispatch', function () {
    const orderId = $(this).data('order-id');
    const dispatchId = $(this).data('dispatch-id');
    Swal.fire({
        icon: 'warning',
        title: 'Anular salida',
        text: 'Se restaurará el stock y se generará una reversa en Kardex.',
        input: 'textarea',
        inputLabel: 'Motivo de anulación',
        inputPlaceholder: 'Ingrese el motivo...',
        showCancelButton: true,
        confirmButtonText: 'Sí, anular y reversar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        inputValidator: value => (!value || value.trim().length < 5) ? 'Ingrese un motivo de al menos 5 caracteres.' : undefined
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.post(`${window.routes.customerPurchaseOrderDispatchReverse}/${orderId}/dispatches/${dispatchId}/reverse`, {
            reason: result.value
        }).done(function (response) {
            Swal.fire({ icon: 'success', title: 'Salida anulada', text: response.message, timer: 1800, showConfirmButton: false });
            tableCustomerPurchaseOrder?.ajax.reload(null, false);
            openWarehouseDispatch(orderId);
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'No se pudo anular', text: xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {}).flat()[0] || 'Ocurrió un error.' });
        });
    });
});

function openWarehouseDispatch(orderId, dispatchId = null) {
    if (!orderId) return;
    if (warehouseDispatchStocksRequest) warehouseDispatchStocksRequest.abort();
    warehouseDispatchStocksLoading = false;
    warehouseDispatchStocksError = false;
    warehouseDispatchSubmitting = false;
    warehouseDispatchInitializing = true;
    $.get(`${window.routes.customerPurchaseOrderDispatchData}/${orderId}/dispatch-data`, dispatchId ? { dispatch_id: dispatchId } : {})
        .done(function (response) {
            currentWarehouseDispatchData = response.data;
            currentWarehouseDispatchId = response.data.editing_dispatch?.id || null;
            const order = response.data.order;
            const editingDispatch = response.data.editing_dispatch;
            const now = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            $('#warehouseDispatchForm')[0].reset();
            $('#warehouseDispatchCreateDocuments').empty();
            $('#warehouseDispatchErrors').addClass('d-none').empty();
            $('#warehouse_dispatch_order_id').val(order.id);
            $('#warehouse_dispatch_id').val(currentWarehouseDispatchId || '');
            $('#warehouse_dispatch_idempotency_key').val(response.data.idempotency_key || '');
            $('#warehouseDispatchOrderCode').text(order.code || '—');
            $('#warehouseDispatchCustomer').text(customerPurchaseOrderName(order.customer));
            $('#warehouseDispatchBranch').html(`<i class="fas fa-map-marker-alt mr-1"></i>${escapePurchaseOrderHtml(order.customer_branch?.branch_name || 'Sin sucursal')}`);
            const dispatchOrderStatus = customerPurchaseOrderStatusMeta(order.status);
            $('#warehouseDispatchOrderStatus')
                .text(dispatchOrderStatus[0])
                .attr('class', `badge ${dispatchOrderStatus[1]}`)
                .attr('title', dispatchOrderStatus[2] || '');
            $('#warehouse_dispatch_date').val(now);
            $('#warehouse_dispatch_warehouse_id').html(
                '<option value="">Seleccione...</option>' + response.data.warehouses.map(warehouse =>
                    `<option value="${warehouse.id}">${escapePurchaseOrderHtml([warehouse.code, warehouse.name].filter(Boolean).join(' | '))}</option>`
                ).join('')
            );
            $('#warehouse_dispatch_responsible_user_id').html(
                '<option value="">Seleccione...</option>' + response.data.responsibles.map(user =>
                    `<option value="${user.id}">${escapePurchaseOrderHtml([user.name, user.lastname].filter(Boolean).join(' '))}</option>`
                ).join('')
            ).val(String(response.data.current_user_id || ''));
            if (editingDispatch) {
                $('#warehouseDispatchModalTitle').text(`Editar borrador ${editingDispatch.dispatch_number}`);
                $('#warehouseDispatchModalSubtitle').text('Puede ajustar cabecera, almacén, lotes y cantidades antes de confirmar.');
                $('#warehouseDispatchSaveLabel').text('Guardar cambios');
                $('#btnConfirmWarehouseDispatch').removeClass('d-none');
                $('#warehouse_dispatch_date').val(editingDispatch.dispatch_date_local || '');
                $('#warehouse_dispatch_warehouse_id').val(String(editingDispatch.warehouse_id || ''));
                $('#warehouse_dispatch_responsible_user_id').val(String(editingDispatch.responsible_user_id || ''));
                $('#warehouse_dispatch_destination').val(editingDispatch.destination || '');
                $('#warehouse_dispatch_document_type').val(editingDispatch.document_type || '');
                $('#warehouse_dispatch_document_number').val(editingDispatch.document_number || '');
                $('#warehouseDispatchForm [name="observation"]').val(editingDispatch.observation || '');
                (response.data.items || []).forEach(item => { item.assignments = []; });
                (editingDispatch.items || []).forEach(detail => {
                    const item = warehouseDispatchItem(detail.customer_purchase_order_item_id);
                    if (!item) return;
                    const assignment = newWarehouseDispatchAssignment();
                    assignment.stock_id = String(detail.warehouse_stock_id);
                    assignment.quantity = String(detail.quantity);
                    item.assignments.push(assignment);
                });
            } else {
                $('#warehouseDispatchModalTitle').text('Preparar salida de almacén');
                $('#warehouseDispatchModalSubtitle').text('Guarde la preparación como borrador antes de confirmar la salida física.');
                $('#warehouseDispatchSaveLabel').text('Guardar borrador');
                $('#btnConfirmWarehouseDispatch').addClass('d-none');
            }
            const dispatchItems = response.data.items || [];
            $('#warehouseDispatchRequestedTotal').text(formatDecimalView(dispatchItems.reduce((total, item) => total + Number(item.requested_quantity || 0), 0)));
            $('#warehouseDispatchEnteredTotal').text(formatDecimalView(dispatchItems.reduce((total, item) => total + Number(item.entered_quantity || 0), 0)));
            $('#warehouseDispatchDispatchedTotal').text(formatDecimalView(dispatchItems.reduce((total, item) => total + Number(item.dispatched_quantity || 0), 0)));
            $('#warehouseDispatchPendingTotal').text(formatDecimalView(dispatchItems.reduce((total, item) => total + Number(item.pending_dispatch_quantity || 0), 0)));
            renderWarehouseDispatchItems();
            if (editingDispatch?.warehouse_id) {
                loadWarehouseDispatchStocks(String(editingDispatch.warehouse_id));
            }
            const confirmedDispatches = (response.data.dispatches || []).filter(dispatch => dispatch.status === 'confirmed');
            $('#warehouseDispatchHistorySummary')
                .toggleClass('d-none', confirmedDispatches.length === 0)
                .html(`<i class="fas fa-info-circle mr-1"></i> ${confirmedDispatches.length === 1 ? 'Ya existe 1 despacho confirmado para esta orden.' : `Ya existen ${confirmedDispatches.length} despachos confirmados para esta orden.`}`);
            $('#warehouseDispatchHistory').html(dispatchHistoryHtml(response.data.dispatches || [], true));
            warehouseDispatchDirty = false;
            warehouseDispatchInitializing = false;
            updateWarehouseDispatchSubmitState();
            $('#warehouseDispatchModal').modal('show');
        })
        .fail(function (xhr) {
            warehouseDispatchInitializing = false;
            Swal.fire({ icon: 'error', title: 'No se pudo preparar la salida', text: xhr.responseJSON?.message || 'Verifique sus permisos y vuelva a intentar.' });
    });
}

function newWarehouseDispatchAssignment() {
    warehouseDispatchAssignmentSequence += 1;
    return { key: warehouseDispatchAssignmentSequence, stock_id: '', quantity: '0' };
}

function warehouseDispatchItem(itemId) {
    return (currentWarehouseDispatchData?.items || []).find(item => Number(item.id) === Number(itemId));
}

function warehouseDispatchAssignment(item, assignmentKey) {
    return (item?.assignments || []).find(assignment => assignment.key === Number(assignmentKey));
}

function ensureWarehouseDispatchAssignments(item) {
    item.assignments = item.assignments || [];
    if (!item.assignments.length) item.assignments.push(newWarehouseDispatchAssignment());
    return item.assignments;
}

function warehouseDispatchAssignedQuantity(item, exceptKey = null) {
    return (item.assignments || []).reduce((total, assignment) => {
        if (exceptKey !== null && assignment.key === Number(exceptKey)) return total;
        const quantity = Number(assignment.quantity || 0);
        return total + (Number.isFinite(quantity) && quantity > 0 ? quantity : 0);
    }, 0);
}

function warehouseDispatchStock(item, stockId) {
    return (item.stocks || []).find(stock => String(stock.id) === String(stockId || ''));
}

function warehouseDispatchAssignmentLimit(item, assignment) {
    const stock = warehouseDispatchStock(item, assignment.stock_id);
    if (!stock) return 0;
    const assignedElsewhere = warehouseDispatchAssignedQuantity(item, assignment.key);
    return Math.max(Math.min(
        Number(stock.current_quantity || 0),
        Number(item.pending_dispatch_quantity || 0) - assignedElsewhere,
        Number(item.available_dispatch_quantity || 0) - assignedElsewhere
    ), 0);
}

function updateWarehouseDispatchItemTotals(item) {
    const block = $(`.warehouse-dispatch-item-block[data-item-id="${item.id}"]`);
    const assigned = warehouseDispatchAssignedQuantity(item);
    const remaining = Math.max(Number(item.pending_dispatch_quantity || 0) - assigned, 0);
    block.find('.dispatch-assigned-total').text(formatDecimalView(assigned));
    block.find('.dispatch-assignment-pending').text(formatDecimalView(remaining));
    block.toggleClass('has-assignment-error', assigned > Number(item.pending_dispatch_quantity || 0) + 0.0000001
        || assigned > Number(item.available_dispatch_quantity || 0) + 0.0000001);
    block.find('.warehouse-dispatch-assignment-row').each(function () {
        const assignment = warehouseDispatchAssignment(item, $(this).data('assignment-key'));
        const limit = assignment ? warehouseDispatchAssignmentLimit(item, assignment) : 0;
        $(this).find('.dispatch-quantity')
            .prop('disabled', !assignment?.stock_id || limit <= 0)
            .attr('max', limit.toFixed(4));
    });
}

function resetWarehouseDispatchStocks() {
    if (warehouseDispatchStocksRequest) {
        warehouseDispatchStocksRequest.abort();
        warehouseDispatchStocksRequest = null;
    }
    warehouseDispatchStocksLoading = false;
    warehouseDispatchStocksError = false;
    (currentWarehouseDispatchData?.items || []).forEach(item => {
        item.stocks = [];
        item.assignments = [];
    });
}

function loadWarehouseDispatchStocks(warehouseId) {
    const orderId = $('#warehouse_dispatch_order_id').val();
    warehouseDispatchStocksLoading = true;
    warehouseDispatchStocksError = false;
    renderWarehouseDispatchItems();

    warehouseDispatchStocksRequest = $.get(
        `${window.routes.customerPurchaseOrderDispatchData}/${orderId}/dispatch-stocks`,
        { warehouse_id: warehouseId, dispatch_id: currentWarehouseDispatchId || '' }
    ).done(function (response) {
        if (String($('#warehouse_dispatch_warehouse_id').val() || '') !== String(warehouseId)) return;
        const stocksByItem = new Map((response.data.items || []).map(item => [
            Number(item.customer_purchase_order_item_id),
            item.stocks || []
        ]));
        (currentWarehouseDispatchData?.items || []).forEach(item => {
            item.stocks = stocksByItem.get(Number(item.id)) || [];
        });
    }).fail(function (xhr, status) {
        if (status === 'abort' || String($('#warehouse_dispatch_warehouse_id').val() || '') !== String(warehouseId)) return;
        warehouseDispatchStocksError = true;
        const messages = Object.values(xhr.responseJSON?.errors || {}).flat();
        $('#warehouseDispatchErrors').removeClass('d-none').html(
            `<ul class="mb-0 pl-3"><li>${escapePurchaseOrderHtml(messages[0] || xhr.responseJSON?.message || 'No se pudieron consultar los saldos del almacén.')}</li></ul>`
        );
    }).always(function () {
        if (String($('#warehouse_dispatch_warehouse_id').val() || '') !== String(warehouseId)) return;
        warehouseDispatchStocksLoading = false;
        warehouseDispatchStocksRequest = null;
        renderWarehouseDispatchItems();
    });
}

function renderWarehouseDispatchItems() {
    if (!currentWarehouseDispatchData) return;
    const warehouseId = String($('#warehouse_dispatch_warehouse_id').val() || '');
    const help = $('#warehouseDispatchWarehouseHelp');
    if (!warehouseId) {
        help.removeClass('is-warning is-success is-danger').addClass('is-info')
            .html('<i class="fas fa-info-circle"></i><div><strong>Seleccione un almacén</strong><span>Seleccione un almacén para ver los lotes disponibles</span></div>');
    } else if (warehouseDispatchStocksLoading) {
        help.removeClass('is-info is-success is-danger').addClass('is-warning')
            .html('<i class="fas fa-spinner fa-spin"></i><div><strong>Consultando saldos</strong><span>Estamos buscando lotes disponibles en el almacén seleccionado.</span></div>');
    } else if (warehouseDispatchStocksError) {
        help.removeClass('is-info is-warning is-success').addClass('is-danger')
            .html('<i class="fas fa-exclamation-circle"></i><div><strong>No se pudieron consultar los saldos</strong><span>Seleccione nuevamente el almacén o vuelva a intentarlo.</span></div>');
    } else {
        help.removeClass('is-info is-warning is-danger').addClass('is-success')
            .html('<i class="fas fa-check-circle"></i><div><strong>Saldos actualizados</strong><span>Solo se muestran lotes del almacén seleccionado.</span></div>');
    }

    const blocks = (currentWarehouseDispatchData.items || [])
        .filter(item => Number(item.pending_dispatch_quantity) > 0)
        .map(function (item) {
            const stocks = warehouseId
                ? (item.stocks || []).filter(stock => String(stock.warehouse_id) === warehouseId)
                : [];
            ensureWarehouseDispatchAssignments(item);
            const canAssign = Boolean(warehouseId) && !warehouseDispatchStocksLoading && !warehouseDispatchStocksError
                && Number(item.available_dispatch_quantity) > 0 && stocks.length > 0;
            const stockPrompt = !warehouseId
                ? 'Seleccione un almacén para ver lotes disponibles'
                : (warehouseDispatchStocksLoading ? 'Consultando saldos...'
                    : (warehouseDispatchStocksError ? 'No se pudieron consultar los saldos'
                    : (stocks.length ? 'Seleccione saldo / lote...' : 'Sin stock en este almacén')));
            const stockEmptyBadge = warehouseId && !warehouseDispatchStocksLoading && !warehouseDispatchStocksError && stocks.length === 0
                ? '<span class="warehouse-dispatch-stock-empty"><i class="fas fa-exclamation-triangle mr-1"></i>Sin stock en este almacén</span>'
                : '';

            const assignmentRows = item.assignments.map(function (assignment) {
                const selectedStockIds = new Set(item.assignments
                    .filter(other => other.key !== assignment.key && other.stock_id)
                    .map(other => String(other.stock_id)));
                const options = stocks.map(stock => {
                    const selected = String(stock.id) === String(assignment.stock_id);
                    const disabled = !selected && selectedStockIds.has(String(stock.id));
                    const label = [
                        stock.lot_number ? `Lote ${stock.lot_number}` : 'Stock sin lote',
                        `Disponible ${formatDecimalView(stock.current_quantity)}`
                    ].join(' · ');
                    return `<option value="${stock.id}" ${selected ? 'selected' : ''} ${disabled ? 'disabled' : ''}>${escapePurchaseOrderHtml(label)}</option>`;
                }).join('');
                const selectedStock = warehouseDispatchStock(item, assignment.stock_id);
                const limit = warehouseDispatchAssignmentLimit(item, assignment);
                const removeDisabled = item.assignments.length === 1 ? 'disabled' : '';

                return `<div class="warehouse-dispatch-assignment-row" data-assignment-key="${assignment.key}">
                    <div class="warehouse-dispatch-assignment-field warehouse-dispatch-stock-field"><label>Stock / lote</label><select class="form-control form-control-sm dispatch-stock-select" ${canAssign ? '' : 'disabled'}><option value="">${escapePurchaseOrderHtml(stockPrompt)}</option>${options}</select>${stockEmptyBadge}</div>
                    <div class="warehouse-dispatch-assignment-field"><label>Vencimiento</label><span class="warehouse-dispatch-assignment-static">${selectedStock?.expiration_date ? formatPurchaseOrderDisplayDate(selectedStock.expiration_date) : '—'}</span></div>
                    <div class="warehouse-dispatch-assignment-field"><label>Disponible</label><span class="warehouse-dispatch-assignment-static">${formatDecimalView(selectedStock?.current_quantity || 0)}</span></div>
                    <div class="warehouse-dispatch-assignment-field"><label>Cantidad</label><input type="number" min="0.0001" step="0.0001" max="${limit.toFixed(4)}" class="form-control form-control-sm dispatch-quantity" value="${escapePurchaseOrderHtml(assignment.quantity || '0')}" ${selectedStock && limit > 0 ? '' : 'disabled'}></div>
                    <button type="button" class="btn btn-outline-danger btn-sm remove-dispatch-assignment" title="Quitar asignación" ${removeDisabled}><i class="fas fa-times"></i></button>
                </div>`;
            }).join('');
            const assigned = warehouseDispatchAssignedQuantity(item);
            const remaining = Math.max(Number(item.pending_dispatch_quantity || 0) - assigned, 0);
            const canAdd = canAssign && item.assignments.length < stocks.length && item.assignments.every(assignment => assignment.stock_id);

            return `<article class="warehouse-dispatch-item-block" data-item-id="${item.id}" data-pending="${Number(item.pending_dispatch_quantity)}" data-available="${Number(item.available_dispatch_quantity)}">
                <div class="warehouse-dispatch-item-head">
                    <div class="warehouse-dispatch-item-title"><strong>${escapePurchaseOrderHtml(item.billing_name_snapshot || item.article?.billing_name || 'Artículo')}</strong><small>${escapePurchaseOrderHtml(item.unit?.abbreviation || item.unit?.description || '')}</small></div>
                    <div class="warehouse-dispatch-item-metrics"><div><span>Solicitado</span><strong>${formatDecimalView(item.requested_quantity)}</strong></div><div><span>Despachado</span><strong>${formatDecimalView(item.dispatched_quantity)}</strong></div><div class="is-pending"><span>Pendiente</span><strong>${formatDecimalView(item.pending_dispatch_quantity)}</strong></div></div>
                </div>
                <div class="warehouse-dispatch-assignments-title"><span>Asignaciones de stock / lotes</span><button type="button" class="btn btn-outline-success btn-sm warehouse-dispatch-add add-dispatch-assignment" ${canAdd ? '' : 'disabled'}><i class="fas fa-plus mr-1"></i>Agregar lote / stock</button></div>
                <div class="warehouse-dispatch-assignments">${assignmentRows}</div>
                <div class="warehouse-dispatch-assignment-summary"><small class="text-muted">Disponible para esta OC: ${formatDecimalView(item.available_dispatch_quantity)}</small><div class="warehouse-dispatch-assignment-totals"><div><span>Total asignado</span><strong class="dispatch-assigned-total">${formatDecimalView(assigned)}</strong></div><div class="is-pending"><span>Pendiente</span><strong class="dispatch-assignment-pending">${formatDecimalView(remaining)}</strong></div></div></div>
            </article>`;
        }).join('');
    $('#warehouseDispatchItemsBody').html(blocks || '<div class="warehouse-dispatch-empty"><i class="fas fa-check-circle"></i><strong>No hay cantidades disponibles para despacho.</strong><span>La orden no tiene mercadería pendiente con disponibilidad.</span></div>');
    updateWarehouseDispatchSubmitState();
}

function warehouseDispatchValidation() {
    if (!String($('#warehouse_dispatch_warehouse_id').val() || '')) {
        return { valid: false, message: 'Seleccione un almacén para registrar la salida.' };
    }
    if (warehouseDispatchStocksLoading) {
        return { valid: false, message: 'Espere mientras se consultan los saldos disponibles.' };
    }
    if (warehouseDispatchStocksError) {
        return { valid: false, message: 'No se pudieron consultar los saldos del almacén seleccionado.' };
    }

    let hasQuantity = false;
    let message = '';
    (currentWarehouseDispatchData?.items || []).forEach(item => {
        if (message || Number(item.pending_dispatch_quantity || 0) <= 0) return;
        const selectedStocks = new Set();
        let assigned = 0;

        (item.assignments || []).forEach(assignment => {
            if (message) return;
            const rawQuantity = String(assignment.quantity ?? '').trim();
            const quantity = Number(rawQuantity || 0);
            const stockId = String(assignment.stock_id || '');
            const stock = warehouseDispatchStock(item, stockId);

            if (!Number.isFinite(quantity) || quantity < 0) {
                message = 'Cada cantidad a despachar debe ser un número mayor a 0.';
                return;
            }
            if (stockId && selectedStocks.has(stockId)) {
                message = 'El lote/stock seleccionado está repetido para este artículo.';
                return;
            }
            if (stockId) selectedStocks.add(stockId);
            if (!stockId && quantity <= 0) return;
            if (!stockId) {
                message = 'Seleccione un saldo o lote para cada cantidad a despachar.';
                return;
            }
            if (quantity <= 0) {
                message = 'Ingrese una cantidad mayor a 0 para cada lote/stock seleccionado.';
                return;
            }
            if (!stock || quantity > Number(stock.current_quantity || 0) + 0.0000001) {
                message = 'La cantidad a despachar supera el stock disponible del lote seleccionado.';
                return;
            }
            hasQuantity = true;
            assigned += quantity;
        });

        if (!message && assigned > Number(item.pending_dispatch_quantity || 0) + 0.0000001) {
            message = 'La suma de lotes supera el pendiente de despacho.';
        }
        if (!message && assigned > Number(item.available_dispatch_quantity || 0) + 0.0000001) {
            message = 'La suma de lotes supera lo ingresado y disponible para la OC Cliente.';
        }
    });

    if (message) return { valid: false, message };
    if (!hasQuantity) return { valid: false, message: 'Ingrese al menos una cantidad a despachar.' };

    const documentValidation = warehouseDispatchDocumentRowsValidation('#warehouseDispatchCreateDocuments');
    if (!documentValidation.valid) return documentValidation;

    return { valid: true, message: '' };
}

function updateWarehouseDispatchSubmitState() {
    const validation = warehouseDispatchValidation();
    const canDispatch = Boolean(currentWarehouseDispatchData?.can_dispatch);
    $('#btnSaveWarehouseDispatch').prop('disabled', warehouseDispatchSubmitting || !canDispatch || !validation.valid);
    $('#btnConfirmWarehouseDispatch').prop('disabled', warehouseDispatchSubmitting || !currentWarehouseDispatchId || warehouseDispatchDirty);
    $('#warehouseDispatchFooterHelp').text(currentWarehouseDispatchId
        ? (warehouseDispatchDirty
            ? 'Guarde los cambios antes de confirmar la salida física.'
            : 'Al confirmar se descontará stock y se generará Kardex; el despacho dejará de ser editable.')
        : 'Guardar el borrador no descuenta stock ni genera Kardex.');
}

function saveWarehouseDispatch() {
    const validation = warehouseDispatchValidation();
    if (!validation.valid) {
        $('#warehouseDispatchErrors').removeClass('d-none').html(
            `<ul class="mb-0 pl-3"><li>${escapePurchaseOrderHtml(validation.message)}</li></ul>`
        );
        updateWarehouseDispatchSubmitState();
        return;
    }

    const orderId = $('#warehouse_dispatch_order_id').val();
    const formData = new FormData($('#warehouseDispatchForm')[0]);
    const items = [];
    (currentWarehouseDispatchData?.items || []).forEach(item => {
        (item.assignments || []).forEach(assignment => {
            const quantity = String(assignment.quantity || '0');
            if (Number(quantity) > 0 && assignment.stock_id) {
                items.push({ customer_purchase_order_item_id: item.id, warehouse_stock_id: assignment.stock_id, quantity });
            }
        });
    });
    items.forEach((item, index) => Object.entries(item).forEach(([key, value]) => formData.append(`items[${index}][${key}]`, value)));
    const editingDispatchId = currentWarehouseDispatchId;
    if (editingDispatchId) formData.append('_method', 'PUT');
    warehouseDispatchSubmitting = true;
    $('#btnSaveWarehouseDispatch').prop('disabled', true);
    $('#warehouseDispatchErrors').addClass('d-none').empty();

    $.ajax({
        url: editingDispatchId
            ? `${window.routes.customerPurchaseOrderDispatchStore}/${orderId}/dispatches/${editingDispatchId}`
            : `${window.routes.customerPurchaseOrderDispatchStore}/${orderId}/dispatches`,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false
    }).done(function (response) {
        warehouseDispatchDirty = false;
        Swal.fire({ icon: 'success', title: 'Borrador guardado', text: response.message, timer: 1800, showConfirmButton: false });
        tableCustomerPurchaseOrder?.ajax.reload(null, false);
        if ($('#viewCustomerPurchaseOrderModal').hasClass('show')) loadCustomerPurchaseOrderDetail(orderId);
        openWarehouseDispatch(orderId, response.data.id);
    }).fail(function (xhr) {
        const messages = Object.values(xhr.responseJSON?.errors || {}).flat();
        $('#warehouseDispatchErrors').removeClass('d-none').html(`<ul class="mb-0 pl-3">${(messages.length ? messages : [xhr.responseJSON?.message || 'No se pudo registrar la salida.']).map(message => `<li>${escapePurchaseOrderHtml(message)}</li>`).join('')}</ul>`);
    }).always(function () {
        warehouseDispatchSubmitting = false;
        updateWarehouseDispatchSubmitState();
    });
}

function confirmWarehouseDispatch(orderId, dispatchId) {
    if (!orderId || !dispatchId) return;
    if (warehouseDispatchDirty && Number(dispatchId) === Number(currentWarehouseDispatchId)) {
        Swal.fire({ icon: 'info', title: 'Cambios sin guardar', text: 'Guarde los cambios del borrador antes de confirmar.' });
        return;
    }
    Swal.fire({
        icon: 'warning',
        title: 'Confirmar salida física',
        text: 'Al confirmar, se descontará el stock y se generará el Kardex de salida. Esta operación no podrá editarse directamente.',
        showCancelButton: true,
        confirmButtonText: 'Sí, confirmar salida',
        cancelButtonText: 'Volver al borrador',
        confirmButtonColor: '#16805e'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        warehouseDispatchSubmitting = true;
        updateWarehouseDispatchSubmitState();
        $.post(`${window.routes.customerPurchaseOrderDispatchStore}/${orderId}/dispatches/${dispatchId}/confirm`)
            .done(function (response) {
                $('#warehouseDispatchModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Salida confirmada', text: response.message, timer: 1900, showConfirmButton: false });
                tableCustomerPurchaseOrder?.ajax.reload(null, false);
                if ($('#viewCustomerPurchaseOrderModal').hasClass('show')) loadCustomerPurchaseOrderDetail(orderId);
            })
            .fail(function (xhr) {
                const message = Object.values(xhr.responseJSON?.errors || {}).flat()[0]
                    || xhr.responseJSON?.message
                    || 'No se pudo confirmar la salida.';
                Swal.fire({ icon: 'error', title: 'No se pudo confirmar', text: message });
            })
            .always(function () {
                warehouseDispatchSubmitting = false;
                updateWarehouseDispatchSubmitState();
            });
    });
}

function cancelDraftWarehouseDispatch(orderId, dispatchId) {
    if (!orderId || !dispatchId) return;
    Swal.fire({
        icon: 'warning',
        title: 'Cancelar borrador',
        text: 'El borrador se conservará como anulado y no se modificará stock ni Kardex.',
        input: 'textarea',
        inputLabel: 'Motivo de cancelación',
        inputPlaceholder: 'Ingrese el motivo...',
        showCancelButton: true,
        confirmButtonText: 'Cancelar borrador',
        cancelButtonText: 'Volver',
        confirmButtonColor: '#dc3545',
        inputValidator: value => (!value || value.trim().length < 5) ? 'Ingrese un motivo de al menos 5 caracteres.' : undefined
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.post(`${window.routes.customerPurchaseOrderDispatchStore}/${orderId}/dispatches/${dispatchId}/cancel-draft`, { reason: result.value })
            .done(function (response) {
                $('#warehouseDispatchModal').modal('hide');
                Swal.fire({ icon: 'success', title: 'Borrador cancelado', text: response.message, timer: 1700, showConfirmButton: false });
                tableCustomerPurchaseOrder?.ajax.reload(null, false);
                if ($('#viewCustomerPurchaseOrderModal').hasClass('show')) loadCustomerPurchaseOrderDetail(orderId);
            })
            .fail(function (xhr) {
                Swal.fire({ icon: 'error', title: 'No se pudo cancelar', text: Object.values(xhr.responseJSON?.errors || {}).flat()[0] || xhr.responseJSON?.message || 'Ocurrió un error.' });
            });
    });
}

function renderCustomerOrderDispatches(dispatches) {
    $('#vpo_dispatches').html(dispatchHistoryHtml(dispatches, false));
}

$(document).off('click.customerReturnCta', '.registerCustomerReturn').on('click.customerReturnCta', '.registerCustomerReturn', function () {
    window.openCustomerReturnForDispatch?.($(this).data('dispatch-id'));
});

$(document).off('customer-return:changed.customerPurchaseOrder').on('customer-return:changed.customerPurchaseOrder', function (_event, orderId) {
    tableCustomerPurchaseOrder?.ajax.reload(null, false);
    if (orderId && $('#viewCustomerPurchaseOrderModal').hasClass('show')) {
        loadCustomerPurchaseOrderDetail(orderId, true);
    }
});

function dispatchHistoryHtml(dispatches, allowReverse) {
    if (!dispatches.length) {
        return '<div class="warehouse-dispatch-empty customer-order-view-empty"><i class="fas fa-truck-loading"></i><strong>Aún no hay salidas registradas.</strong><span>Los despachos de esta orden aparecerán aquí.</span></div>';
    }

    return dispatches.map(dispatch => {
        const items = (dispatch.items || []).map(item => {
            const article = item.customer_purchase_order_item?.billing_name_snapshot || item.customer_purchase_order_item?.article?.billing_name || 'Artículo';
            const lot = item.lot_number ? `<small class="text-muted ml-2"><i class="fas fa-barcode mr-1"></i>Lote ${escapePurchaseOrderHtml(item.lot_number)}</small>` : '';
            return `<li class="d-flex justify-content-between align-items-center flex-wrap py-1"><span>${escapePurchaseOrderHtml(article)}${lot}</span><strong class="text-success">${formatDecimalView(item.quantity)}</strong></li>`;
        }).join('');
        const responsible = [dispatch.responsible_user?.name, dispatch.responsible_user?.lastname].filter(Boolean).join(' ') || '—';
        const status = dispatch.status === 'confirmed'
            ? '<span class="badge badge-success rounded-pill px-2 py-1"><i class="fas fa-check-circle mr-1"></i>CONFIRMADA</span>'
            : (dispatch.status === 'draft'
                ? '<span class="badge badge-warning rounded-pill px-2 py-1"><i class="far fa-edit mr-1"></i>BORRADOR</span>'
                : '<span class="badge badge-secondary rounded-pill px-2 py-1"><i class="fas fa-ban mr-1"></i>ANULADA</span>');
        const documentCount = Number(dispatch.documents_count || 0) + (dispatch.document_url ? 1 : 0);
        const document = window.customerPurchaseOrderCanViewDispatchDocuments
            ? `<button type="button" class="btn btn-xs btn-outline-primary ml-1 manageWarehouseDispatchDocuments" data-order-id="${dispatch.customer_purchase_order_id}" data-dispatch-id="${dispatch.id}" data-dispatch-number="${escapePurchaseOrderHtml(dispatch.dispatch_number)}" title="Ver y gestionar documentos"><i class="fas fa-paperclip mr-1"></i>Documentos (${documentCount})</button>`
            : (dispatch.document_url ? `<a href="${escapePurchaseOrderHtml(dispatch.document_url)}" target="_blank" rel="noopener" class="btn btn-xs btn-outline-primary ml-1" title="Abrir sustento"><i class="fas fa-paperclip mr-1"></i>Sustento</a>` : '');
        const reverse = allowReverse && window.customerPurchaseOrderCanReverseDispatch && dispatch.can_reverse === true
            ? `<button type="button" class="btn btn-xs btn-outline-danger ml-1 reverseWarehouseDispatch" data-order-id="${dispatch.customer_purchase_order_id}" data-dispatch-id="${dispatch.id}" title="Anular esta salida y restaurar el stock"><i class="fas fa-undo mr-1"></i>Anular salida</button>` : '';
        const draftActions = dispatch.status === 'draft' && window.customerPurchaseOrderCanDispatch
            ? `<button type="button" class="btn btn-xs btn-outline-secondary ml-1 editWarehouseDispatch" data-order-id="${dispatch.customer_purchase_order_id}" data-dispatch-id="${dispatch.id}" title="Editar preparación"><i class="fas fa-edit mr-1"></i>Editar</button>
               <button type="button" class="btn btn-xs btn-success ml-1 confirmWarehouseDispatch" data-order-id="${dispatch.customer_purchase_order_id}" data-dispatch-id="${dispatch.id}" title="Confirmar salida física"><i class="fas fa-check-circle mr-1"></i>Confirmar</button>
               <button type="button" class="btn btn-xs btn-outline-danger ml-1 cancelDraftWarehouseDispatch" data-order-id="${dispatch.customer_purchase_order_id}" data-dispatch-id="${dispatch.id}" title="Cancelar borrador sin afectar stock"><i class="fas fa-ban mr-1"></i>Cancelar</button>`
            : '';
        const draftReturns = (dispatch.customer_returns || []).filter(ret => ret.status === 'draft');
        let registerReturn = '';
        if (draftReturns.length === 1) {
            const draft = draftReturns[0];
            registerReturn = draft.can_edit
                ? `<button type="button" class="btn btn-xs btn-warning ml-1 editCustomerReturn" data-id="${draft.id}" title="Continuar ${escapePurchaseOrderHtml(draft.return_number)} sin crear otra devolución"><i class="fas fa-edit mr-1"></i>Continuar devolución</button>`
                : '<span class="badge badge-warning ml-1"><i class="fas fa-hourglass-half mr-1"></i>Borrador pendiente</span>';
        } else if (draftReturns.length > 1) {
            registerReturn = `<span class="badge badge-warning ml-1"><i class="fas fa-hourglass-half mr-1"></i>${draftReturns.length} borradores pendientes</span>`;
        } else if (dispatch.can_return === true) {
            registerReturn = `<button type="button" class="btn btn-xs btn-primary ml-1 registerCustomerReturn" data-dispatch-id="${dispatch.id}" title="Registrar mercadería que regresó físicamente"><i class="fas fa-undo-alt mr-1"></i>Registrar devolución</button>`;
        }
        const returnTrace = (dispatch.customer_returns || []).map(ret => {
            const label = ret.status === 'confirmed' ? 'CONFIRMADA' : (ret.status === 'draft' ? 'BORRADOR' : (ret.status === 'reversed' ? 'REVERTIDA' : 'CANCELADA'));
            const statusDescription = ret.status === 'reversed' ? '<small class="d-block text-muted ml-2">Devolución dejada sin efecto</small>' : '';
            const number = ret.can_view
                ? `<button type="button" class="btn btn-link btn-sm p-0 font-weight-bold viewCustomerReturn" data-id="${ret.id}">${escapePurchaseOrderHtml(ret.return_number)}</button>`
                : `<strong>${escapePurchaseOrderHtml(ret.return_number)}</strong>`;
            const actions = [];
            if (ret.can_view) actions.push(`<button type="button" class="btn btn-xs btn-outline-primary viewCustomerReturn" data-id="${ret.id}"><i class="fas fa-eye mr-1"></i>Ver</button>`);
            if (ret.can_edit) actions.push(`<button type="button" class="btn btn-xs btn-outline-secondary editCustomerReturn" data-id="${ret.id}"><i class="fas fa-edit mr-1"></i>Editar</button>`);
            if (ret.can_documents) actions.push(`<button type="button" class="btn btn-xs btn-outline-info manageCustomerReturnDocuments" data-id="${ret.id}" data-number="${escapePurchaseOrderHtml(ret.return_number)}"><i class="fas fa-paperclip mr-1"></i>Documentos</button>`);
            if (ret.can_confirm) actions.push(`<button type="button" class="btn btn-xs btn-success confirmCustomerReturn" data-id="${ret.id}"><i class="fas fa-check-circle mr-1"></i>Confirmar devolución</button>`);
            if (ret.can_cancel) actions.push(`<button type="button" class="btn btn-xs btn-outline-danger cancelCustomerReturn" data-id="${ret.id}"><i class="fas fa-ban mr-1"></i>Cancelar</button>`);
            if (ret.can_reverse) actions.push(`<button type="button" class="btn btn-xs btn-danger reverseCustomerReturn" data-id="${ret.id}"><i class="fas fa-undo mr-1"></i>Anular / Revertir devolución</button>`);
            return `<div class="mt-2 ml-3 p-2 border-left border-primary bg-light"><div class="d-flex align-items-center flex-wrap">${number}<span class="badge badge-light ml-2">${label}</span><small class="ml-2">Cantidad: ${formatDecimalView(ret.total_quantity || 0)}</small>${statusDescription}</div>${actions.length ? `<div class="d-flex flex-wrap mt-2" style="gap:.25rem">${actions.join('')}</div>` : ''}</div>`;
        }).join('');
        const gross = (dispatch.items || []).reduce((total,item)=>total+Number(item.quantity||0),0);
        const netSummary = dispatch.status === 'confirmed' ? `<div class="small text-muted mt-2"><strong>Salida original:</strong> ${formatDecimalView(gross)} · <strong>Devuelto:</strong> ${formatDecimalView(dispatch.returned_quantity || 0)} · <strong>Entregado neto:</strong> ${formatDecimalView(dispatch.net_delivered_quantity ?? gross)}</div>` : '';
        const support = [dispatch.document_type, dispatch.document_number].filter(Boolean).join(' · ');

        return `<article class="dispatch-history-card border p-3 mb-2 bg-white">
            <div class="d-flex justify-content-between align-items-start flex-wrap mb-2"><div><strong class="d-block">${escapePurchaseOrderHtml(dispatch.dispatch_number)}</strong><small class="text-muted"><i class="far fa-calendar-alt mr-1"></i>${formatPurchaseOrderDisplayDate(dispatch.dispatch_date)}</small></div><div class="d-flex align-items-center flex-wrap">${status}${document}${registerReturn}${draftActions}${reverse}</div></div>
            <div class="d-flex flex-wrap text-muted small mb-2"><span class="mr-3"><i class="fas fa-warehouse mr-1"></i>${escapePurchaseOrderHtml(dispatch.warehouse?.name || 'Almacén')}</span><span class="mr-3"><i class="fas fa-user mr-1"></i>${escapePurchaseOrderHtml(responsible)}</span>${support ? `<span><i class="fas fa-file-alt mr-1"></i>${escapePurchaseOrderHtml(support)}</span>` : ''}</div>
            <ul class="list-unstyled mb-0 border-top border-bottom py-1">${items}</ul>
            ${netSummary}${returnTrace}
            ${dispatch.observation ? `<small class="d-block text-muted mt-2"><i class="far fa-comment-alt mr-1"></i>${escapePurchaseOrderHtml(dispatch.observation)}</small>` : ''}
        </article>`;
    }).join('');
}

function warehouseDispatchDocumentTypes(target) {
    if (target === '#warehouseDispatchManageDocuments' && currentWarehouseDispatchDocumentContext?.types) {
        return currentWarehouseDispatchDocumentContext.types;
    }

    return currentWarehouseDispatchData?.dispatch_document_types || {};
}

function addWarehouseDispatchDocumentRow(target) {
    const container = $(target);
    if (!container.length || container.children().length >= 10) return;
    warehouseDispatchDocumentSequence += 1;
    const index = warehouseDispatchDocumentSequence;
    const options = Object.entries(warehouseDispatchDocumentTypes(target))
        .map(([value, label]) => `<option value="${escapePurchaseOrderHtml(value)}">${escapePurchaseOrderHtml(label)}</option>`)
        .join('');

    container.append(`<div class="warehouse-dispatch-document-row" data-document-key="${index}">
        <select class="form-control form-control-sm document-type" name="documents[${index}][type]" required><option value="">Tipo de documento...</option>${options}</select>
        <input type="text" class="form-control form-control-sm document-description" name="documents[${index}][description]" maxlength="1000" placeholder="Descripción opcional">
        <button type="button" class="btn btn-sm btn-outline-danger removeWarehouseDispatchDocument" title="Quitar"><i class="fas fa-times"></i></button>
        <input type="file" class="form-control form-control-sm document-file" name="documents[${index}][file]" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx" required>
    </div>`);
    if (target === '#warehouseDispatchCreateDocuments') warehouseDispatchDirty = true;
    updateWarehouseDispatchDocumentFormState();
    updateWarehouseDispatchSubmitState();
}

function warehouseDispatchDocumentRowsValidation(target) {
    let valid = true;
    let message = '';
    $(`${target} .warehouse-dispatch-document-row`).each(function () {
        const type = String($(this).find('.document-type').val() || '');
        const file = $(this).find('.document-file')[0];
        if (!type || !file?.files?.length) {
            valid = false;
            message = 'Complete el tipo y archivo de cada documento agregado.';
            return false;
        }
    });

    return { valid, message };
}

function updateWarehouseDispatchDocumentFormState() {
    const rows = $('#warehouseDispatchManageDocuments .warehouse-dispatch-document-row').length;
    const validation = warehouseDispatchDocumentRowsValidation('#warehouseDispatchManageDocuments');
    $('#btnSaveWarehouseDispatchDocuments').prop('disabled', !rows || !validation.valid);
}

function openWarehouseDispatchDocuments(orderId, dispatchId, dispatchNumber) {
    if (!orderId || !dispatchId) return;
    currentWarehouseDispatchDocumentContext = { orderId, dispatchId, dispatchNumber, types: {} };
    $('#warehouseDispatchDocumentsNumber').text(dispatchNumber || '—');
    $('#warehouseDispatchDocumentsErrors').addClass('d-none').empty();
    $('#warehouseDispatchDocumentsList').html('<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin mr-1"></i>Cargando documentos...</div>');
    $('#warehouseDispatchManageDocuments').empty();
    updateWarehouseDispatchDocumentFormState();
    $('#warehouseDispatchDocumentsModal').modal('show');

    $.get(`${window.routes.customerPurchaseOrderDispatchDocuments}/${orderId}/dispatches/${dispatchId}/documents`)
        .done(function (response) {
            currentWarehouseDispatchDocumentContext.types = response.data.types || {};
            renderWarehouseDispatchDocuments(response.data);
        })
        .fail(function (xhr) {
            $('#warehouseDispatchDocumentsList').html('<div class="alert alert-danger mb-0">No se pudieron cargar los documentos del despacho.</div>');
            $('#warehouseDispatchDocumentsErrors').removeClass('d-none').text(xhr.responseJSON?.message || 'Verifique sus permisos e intente nuevamente.');
        });
}

function renderWarehouseDispatchDocuments(data) {
    const documents = [...(data.documents || [])];
    if (data.legacy_document) {
        documents.push({ ...data.legacy_document, type_label: 'Documento legacy', description: 'Archivo histórico conservado en la cabecera del despacho.' });
    }
    const canManage = Boolean(data.can_manage && window.customerPurchaseOrderCanManageDispatchDocuments);
    const html = documents.map(document => {
        const date = document.created_at ? formatPurchaseOrderDisplayDate(document.created_at) : 'Histórico';
        const deleteButton = canManage && !document.is_legacy
            ? `<button type="button" class="btn btn-sm btn-outline-danger deleteWarehouseDispatchDocument" data-document-id="${document.id}" title="Retirar documento"><i class="fas fa-trash-alt"></i></button>`
            : '';
        return `<article class="warehouse-dispatch-document-entry">
            <i class="fas ${String(document.mime_type || '').startsWith('image/') ? 'fa-image' : 'fa-file-alt'}"></i>
            <div class="document-meta"><small class="text-success font-weight-bold">${escapePurchaseOrderHtml(document.type_label || 'Otro')}</small><strong>${escapePurchaseOrderHtml(document.original_name || 'Documento')}</strong><small class="text-muted">${escapePurchaseOrderHtml(document.description || 'Sin descripción')} · ${escapePurchaseOrderHtml(document.creator_name || 'No registrado')} · ${escapePurchaseOrderHtml(date)}</small></div>
            <div class="document-actions"><a href="${escapePurchaseOrderHtml(document.view_url)}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary" title="Ver documento"><i class="fas fa-external-link-alt"></i></a>${deleteButton}</div>
        </article>`;
    }).join('');
    $('#warehouseDispatchDocumentsList').html(html || '<div class="warehouse-dispatch-empty"><i class="far fa-folder-open"></i><strong>Este despacho aún no tiene documentos.</strong><span>Puede agregarlos sin modificar la salida registrada.</span></div>');
    updateWarehouseDispatchDocumentCount(data.dispatch_id, Number(data.count || 0));
}

function updateWarehouseDispatchDocumentCount(dispatchId, count) {
    $(`.manageWarehouseDispatchDocuments[data-dispatch-id="${dispatchId}"]`).html(`<i class="fas fa-paperclip mr-1"></i>Documentos (${count})`);
    const dispatch = (currentWarehouseDispatchData?.dispatches || []).find(row => Number(row.id) === Number(dispatchId));
    if (dispatch) dispatch.documents_count = Math.max(count - (dispatch.document_url ? 1 : 0), 0);
}

function saveWarehouseDispatchDocuments() {
    const context = currentWarehouseDispatchDocumentContext;
    const validation = warehouseDispatchDocumentRowsValidation('#warehouseDispatchManageDocuments');
    if (!context || !validation.valid) {
        $('#warehouseDispatchDocumentsErrors').removeClass('d-none').text(validation.message || 'Agregue al menos un documento.');
        return;
    }

    const formData = new FormData($('#warehouseDispatchDocumentsForm')[0]);
    $('#btnSaveWarehouseDispatchDocuments').prop('disabled', true);
    $('#warehouseDispatchDocumentsErrors').addClass('d-none').empty();
    $.ajax({
        url: `${window.routes.customerPurchaseOrderDispatchDocuments}/${context.orderId}/dispatches/${context.dispatchId}/documents`,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false
    }).done(function (response) {
        currentWarehouseDispatchDocumentContext.types = response.data.types || currentWarehouseDispatchDocumentContext.types;
        $('#warehouseDispatchManageDocuments').empty();
        renderWarehouseDispatchDocuments(response.data);
        Swal.fire({ icon: 'success', title: 'Documentos adjuntados', text: response.message, timer: 1600, showConfirmButton: false });
    }).fail(function (xhr) {
        const messages = Object.values(xhr.responseJSON?.errors || {}).flat();
        $('#warehouseDispatchDocumentsErrors').removeClass('d-none').html((messages.length ? messages : [xhr.responseJSON?.message || 'No se pudieron adjuntar los documentos.']).map(message => `<div>${escapePurchaseOrderHtml(message)}</div>`).join(''));
    }).always(updateWarehouseDispatchDocumentFormState);
}

function deleteWarehouseDispatchDocument(documentId) {
    const context = currentWarehouseDispatchDocumentContext;
    if (!context || !documentId) return;
    Swal.fire({
        icon: 'warning',
        title: 'Retirar documento',
        text: 'El documento dejará de mostrarse, pero conservará su trazabilidad de auditoría.',
        showCancelButton: true,
        confirmButtonText: 'Sí, retirar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        $.ajax({
            url: `${window.routes.customerPurchaseOrderDispatchDocuments}/${context.orderId}/dispatches/${context.dispatchId}/documents/${documentId}`,
            method: 'DELETE'
        }).done(function (response) {
            renderWarehouseDispatchDocuments(response.data);
            Swal.fire({ icon: 'success', title: 'Documento retirado', text: response.message, timer: 1500, showConfirmButton: false });
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'No se pudo retirar', text: xhr.responseJSON?.message || 'Verifique sus permisos e intente nuevamente.' });
        });
    });
}

$('#warehouseDispatchDocumentsModal').on('hidden.bs.modal', function () {
    if ($('#warehouseDispatchModal').hasClass('show') || $('#viewCustomerPurchaseOrderModal').hasClass('show')) {
        $('body').addClass('modal-open');
    }
});
