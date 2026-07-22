let tableQuote;
let quoteItemIndex = 0;
let quickQuoteArticleRow = null;
let quickQuoteBrandRow = null;
let lastQuickCustomerDocument = '';
let quickCustomerDocumentTimer = null;
let quickCustomerConsulting = false;

$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});

document.addEventListener('DOMContentLoaded', function () {

    $.ajaxSetup({

        headers: {

            'X-CSRF-TOKEN':
                $('meta[name="csrf-token"]').attr('content')

        }

    });

    initQuoteSelects();

    /*
    |--------------------------------------------------------------------------
    | DATATABLE COTIZACIONES
    |--------------------------------------------------------------------------
    */
    tableQuote = $('#tableQuote').DataTable({

        processing: true,

        serverSide: true,

        ajax: window.routes.quoteList,

        columns: [

            {
                data: 'DT_RowIndex',
                name: 'DT_RowIndex',
                orderable: false,
                searchable: false
            },

            {
                data: 'id',
                name: 'id'
            },

            {
                data: 'quote_number',
                name: 'quote_number'
            },

            {
                data: 'customer',
                name: 'customer'
            },

            {
                data: 'company',
                name: 'company'
            },

            {
                data: 'currency',
                name: 'currency'
            },

            {
                data: 'grand_total',
                name: 'grand_total'
            },

            {
                data: 'status',
                name: 'status'
            },

            {
                data: 'created_at',
                name: 'created_at'
            },

            {
                data: 'acciones',
                name: 'acciones',
                orderable: false,
                searchable: false
            }

        ],

        responsive: true,

        autoWidth: false,

        language: {
            url: "/vendor/datatables/js/i18n/es-ES.json"
        },

        dom: `
        <'row mb-3'
            <'col-sm-12 col-md-6'l>
            <'col-sm-12 col-md-6 text-md-end'f>
        >

        <'row'
            <'col-sm-12'tr>
        >

        <'row mt-3'
            <'col-sm-12 col-md-5'i>
            <'col-sm-12 col-md-7 d-flex justify-content-center justify-content-md-end'p>
        >

        <'row mt-3'
            <'col-sm-12 text-center'B>
        >
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
                text: '<i class="fas fa-print"></i> Print'
            }

        ],

        preDrawCallback: function () {

            if (typeof divLoading !== 'undefined' && divLoading) {
                divLoading.classList.remove('d-none');
            }

        },

        drawCallback: function () {

            if (typeof divLoading !== 'undefined' && divLoading) {
                divLoading.classList.add('d-none');
            }

        }

    });

    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL NUEVA COTIZACIÓN
    |--------------------------------------------------------------------------
    */
    /*
   |--------------------------------------------------------------------------
   | CONFIGURACIÓN SEGURA DEL MODAL
   |--------------------------------------------------------------------------
   */
    $('#quoteModal').modal({
        backdrop: 'static',
        keyboard: false,
        show: false
    });

    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL NUEVA COTIZACIÓN
    |--------------------------------------------------------------------------
    */
    $(document).off('click.quoteCreate', '#btnCreateQuote');

    $(document).on('click.quoteCreate', '#btnCreateQuote', function (e) {

        e.preventDefault();
        e.stopPropagation();

        resetQuoteForm();

        $('#quoteModalLabel').text('Registrar Cotización');

        generateQuoteNumber();

        $('#quoteModal').modal('show');

    });

    /*
    |--------------------------------------------------------------------------
    | REINICIAR AL CERRAR MODAL
    |--------------------------------------------------------------------------
    */
    $('#quoteModal').on('hidden.bs.modal', function () {

        resetQuoteForm();

    });

    /*
    |--------------------------------------------------------------------------
    | EDITAR COTIZACION
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.editQuote', function () {

        let id = $(this).data('id');

        clearQuoteErrors();

        $.ajax({

            url: `${window.routes.showQuote}/${id}/edit`,

            type: 'GET',

            success: function (response) {

                fillQuoteForm(response.data);

                $('#quoteModalLabel').text('Editar Cotizacion');

                $('#btnSaveQuote')
                    .prop('disabled', false)
                    .html('<i class="fas fa-save mr-1"></i> Actualizar Cotizacion');

                $('#quoteModal').modal('show');

            },

            error: function (xhr) {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'No se pudo cargar la cotizacion.'
                });

            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | GUARDAR COTIZACIÓN
    |--------------------------------------------------------------------------
    */
    $(document).on('submit', '#quoteForm', function (e) {

        e.preventDefault();

        clearQuoteErrors();

        calculateQuoteTotals();

        let formData = new FormData(this);

        let quoteId = $('#quote_id').val();

        let btn = $('#btnSaveQuote');

        let saveButtonText = quoteId
            ? 'Actualizar Cotizacion'
            : 'Guardar Cotizacion';

        let url = quoteId
            ? window.routes.updateQuote + '/' + quoteId
            : window.routes.storeQuote;

        if ($('#quoteItemsTbody tr.quote-item-row').length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin artículos',
                text: 'Debe agregar al menos un artículo a la cotización.'
            });

            return;
        }

        if (quoteId) {

            formData.append('_method', 'PUT');

        }

        btn.prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

        $.ajax({

            url: url,

            type: 'POST',

            data: formData,

            processData: false,

            contentType: false,

            cache: false,

            success: function (response) {

                Swal.fire({

                    icon: 'success',

                    title: response.message || 'Cotización guardada correctamente',

                    toast: true,

                    position: 'top-end',

                    showConfirmButton: false,

                    timer: 3000

                });

                if (response.pdf_url) {
                    window.open(response.pdf_url, '_blank');
                }

                $('#quoteModal').modal('hide');

                resetQuoteForm();

                tableQuote.ajax.reload(null, false);

            },

            error: function (xhr) {

                console.error('Error al guardar la cotización.', xhr.responseJSON || xhr.responseText);

                btn.prop('disabled', false)
                    .html('<i class="fas fa-save mr-1"></i> ' + saveButtonText);

                const response = xhr.responseJSON || {};
                const validationErrors = response.errors || {};
                const firstErrorKey = Object.keys(validationErrors)[0];
                const firstError = firstErrorKey ? validationErrors[firstErrorKey] : null;
                const errorMessage = Array.isArray(firstError)
                    ? firstError[0]
                    : (firstError || response.message || 'Error al guardar la cotización.');

                if (xhr.status === 422) {

                    showQuoteErrors(validationErrors);

                    Swal.fire({

                        icon: 'warning',

                        title: 'Revisa el formulario',

                        text: errorMessage

                    });

                    return;

                }

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: errorMessage

                });

            }

        });

    });

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR COTIZACIÓN
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.deleteQuote', function () {

        let id = $(this).data('id');

        Swal.fire({

            title: '¿Eliminar cotización?',

            text: 'Esta acción no se puede deshacer.',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonColor: '#d33',

            cancelButtonColor: '#6c757d',

            confirmButtonText: 'Sí, eliminar',

            cancelButtonText: 'Cancelar'

        }).then((result) => {

            if (!result.isConfirmed) {
                return;
            }

            $.ajax({

                url: `${window.routes.deleteQuote}/${id}`,

                type: 'POST',

                data: {
                    _method: 'DELETE'
                },

                success: function (response) {

                    Swal.fire({

                        icon: 'success',

                        title: response.message || 'Cotización eliminada correctamente',

                        toast: true,

                        position: 'top-end',

                        showConfirmButton: false,

                        timer: 2500

                    });

                    tableQuote.ajax.reload(null, false);

                },

                error: function (xhr) {

                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text: xhr.responseJSON?.message
                            || 'No se pudo eliminar la cotización.'

                    });

                }

            });

        });

    });

    /*
    |--------------------------------------------------------------------------
    | AGREGAR ARTÍCULO
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '#btnAddQuoteItem', function () {

        addQuoteItemRow();

    });

    /*
    |--------------------------------------------------------------------------
    | QUITAR ARTÍCULO
    |--------------------------------------------------------------------------
    */
    $(document).on('click', '.btnRemoveQuoteItem', function () {

        $(this).closest('tr').remove();

        refreshQuoteItemIndexes();

        calculateQuoteTotals();

        showEmptyQuoteItemsRow();

    });

    /*
    |--------------------------------------------------------------------------
    | CALCULAR TOTALES
    |--------------------------------------------------------------------------
    */
    $(document).on(
        'input change',
        '.item-quantity, .item-unit-price, .item-discount-percentage, #affect_igv',
        function () {

            calculateQuoteTotals();

        }
    );

    /*
    |--------------------------------------------------------------------------
    | CARGAR SUCURSALES POR CLIENTE
    |--------------------------------------------------------------------------
    */


    /*
    |--------------------------------------------------------------------------
    | SELECCIONAR SUCURSAL
    |--------------------------------------------------------------------------
    */
    /*     $(document).on('change', '#customer_branch_id', function () {
    
            let selected = $(this).find('option:selected');
    
            let branchName = selected.data('branch-name') || '';
            let address = selected.data('address') || '';
            let reference = selected.data('reference') || '';
            let paymentCondition = selected.data('payment-condition') || '';
    
            $('#quoteSideBranch').text(
                branchName || 'Seleccione sucursal'
            );
    
            if (address) {
    
                let fullAddress = address;
    
                if (reference) {
                    fullAddress += ' - ' + reference;
                }
    
                $('#delivery_address').val(
                    fullAddress.toUpperCase()
                );
    
            }
    
            if (paymentCondition) {
    
                $('#payment_condition')
                    .val(paymentCondition.toUpperCase())
                    .trigger('change');
    
            }
    
        });
     */
    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR EMPRESA PANEL IZQUIERDO
    |--------------------------------------------------------------------------
    */
    /*     $(document).on('change', '#company_id', function () {
    
            let companyText = $('#company_id option:selected').text().trim();
    
            $('#quoteSideCompany').text(
                companyText || 'Seleccione empresa'
            );
    
        });
     */
    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR MONEDA
    |--------------------------------------------------------------------------
    */
    $(document).on('change', '#currency_id', function () {

        let selected = $(this).find('option:selected');

        let code = selected.data('code') || 'PEN';
        let symbol = selected.data('symbol') || 'S/';

        $('.quote-currency-code').text(code);
        $('.quote-currency-symbol').text(symbol);

    });


    /*
    |--------------------------------------------------------------------------
    | ACTUALIZAR EMPRESA PANEL IZQUIERDO
    |--------------------------------------------------------------------------
    */
    /*
   |--------------------------------------------------------------------------
   | CARGAR SUCURSALES POR CLIENTE
   |--------------------------------------------------------------------------
   */
    /*
    |--------------------------------------------------------------------------
    | CARGAR SUCURSALES POR CLIENTE
    |--------------------------------------------------------------------------
    */
    $(document).on('change', '#customer_id', function () {

        let customerId = $(this).val();

        let customerText = $('#customer_id option:selected').text().trim();

        $('#quoteSideCustomer').text(
            customerId ? customerText : 'Seleccione cliente'
        );

        resetCustomerBranches();

        $('#delivery_address').val('');
        $('#payment_condition').val('CONTADO').trigger('change');
        $('#quoteSideBranch').text('Seleccione sucursal');

        if (!customerId) {
            return;
        }

        loadCustomerBranches(customerId);

    });
    /*
    |--------------------------------------------------------------------------
    | SELECCIONAR SUCURSAL Y LLENAR DATOS
    |--------------------------------------------------------------------------
    */
    $(document).on('change', '#customer_branch_id', function () {

        let selected = $(this).find('option:selected');

        let branchName = selected.data('branch-name') || '';
        let address = selected.data('address') || '';
        let reference = selected.data('reference') || '';
        let paymentCondition = selected.data('payment-condition') || '';

        $('#quoteSideBranch').text(branchName || 'Seleccione sucursal');

        if (address) {

            let fullAddress = address;

            if (reference) {
                fullAddress += ' - ' + reference;
            }

            $('#delivery_address').val(fullAddress.toUpperCase());

        } else {

            $('#delivery_address').val('');

        }

        if (paymentCondition) {

            $('#payment_condition')
                .val(paymentCondition.toUpperCase())
                .trigger('change');

        } else {

            $('#payment_condition')
                .val('CONTADO')
                .trigger('change');

        }

    });

    /*
    |--------------------------------------------------------------------------
    | SELECCIONAR SUCURSAL
    |--------------------------------------------------------------------------
    */


    /*
|--------------------------------------------------------------------------
| GENERAR TIEMPO DE ENTREGA AUTOMÁTICO
|--------------------------------------------------------------------------
*/
    $(document).on('input change', '#delivery_days', function () {

        updateDeliveryTimeFromDays();

    });



    /*
|--------------------------------------------------------------------------
| FILTRAR ESTUDIO DE MERCADO Y CARGAR GANADORES
|--------------------------------------------------------------------------
*/
    $(document).on('click', '#btnFilterMarketStudy', function () {

        let marketStudyId = $('#market_study_id').val();

        if (!marketStudyId) {

            Swal.fire({
                icon: 'warning',
                title: 'Seleccione un estudio de mercado',
                text: 'Seleccione un estudio de mercado para cargar artículos.',
            });

            return;

        }

        let currentItems = $('#quoteItemsTbody tr.quote-item-row').length;

        if (currentItems > 0) {

            Swal.fire({
                icon: 'question',
                title: 'Reemplazar artículos',
                text: 'Ya existen artículos en la cotización. ¿Desea reemplazarlos con los ganadores del estudio?',
                showCancelButton: true,
                confirmButtonText: 'Sí, reemplazar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
            }).then((result) => {

                if (result.isConfirmed) {
                    loadMarketStudyWinnerItems(marketStudyId);
                }

            });

            return;

        }

        loadMarketStudyWinnerItems(marketStudyId);

    });




});


/*
|--------------------------------------------------------------------------
| SELECTS
|--------------------------------------------------------------------------
*/
function initQuoteSelects() {

    initSelect2ForQuoteScope($('#quoteModal'));
    initQuoteMarketStudySelect();
    initQuoteCustomerSelect();

}


/*
|--------------------------------------------------------------------------
| EVITAR QUE SELECT2 CIERRE EL MODAL POR CLIC EXTERNO
|--------------------------------------------------------------------------
*/
$(document).off('mousedown.quoteSelect2 click.quoteSelect2');

$(document).on(
    'mousedown.quoteSelect2 click.quoteSelect2',
    '.select2-container, .select2-dropdown, .select2-search__field',
    function (e) {
        e.stopPropagation();
    }
);
/*
|--------------------------------------------------------------------------
| SELECT2 PERSONALIZADO PARA COTIZACIÓN
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| SELECT2 PERSONALIZADO PARA COTIZACIÓN
|--------------------------------------------------------------------------
*/
function initSelect2ForQuoteScope(scope) {

    if (!$.fn.select2) {
        return;
    }

    let dropdownParent = scope.closest('.modal').length
        ? scope.closest('.modal')
        : $('#quoteModal');

    scope.find('select')
        .not('.item-unit-id, .item-article-select, .item-brand-id, #market_study_id, #customer_id')
        .select2({

            dropdownParent: dropdownParent,

            width: '100%',

            theme: 'bootstrap4'

        });

    scope.find('.item-unit-id').select2({

        dropdownParent: dropdownParent,

        width: '100%',

        theme: 'bootstrap4',

        dropdownAutoWidth: true,

        dropdownCssClass: 'quote-unit-dropdown',

        templateResult: function (option) {

            if (!option.id) {
                return option.text;
            }

            let abbreviation = $(option.element).data('abbreviation') || '';
            let description = $(option.element).data('description') || '';

            if (abbreviation && description) {
                return $('<span class="quote-unit-option"><strong>' + abbreviation + '</strong> | ' + description + '</span>');
            }

            return option.text;

        },

        templateSelection: function (option) {

            if (!option.id) {
                return option.text;
            }

            let abbreviation = $(option.element).data('abbreviation') || '';

            return abbreviation || option.text;

        }

    });

}
/*
|--------------------------------------------------------------------------
| GENERAR NÚMERO DE COTIZACIÓN
|--------------------------------------------------------------------------
*/
function generateQuoteNumber() {

    if (!window.routes.generateQuoteNumber) {
        return;
    }

    $.ajax({

        url: window.routes.generateQuoteNumber,

        type: 'GET',

        success: function (response) {

            $('#quote_number').val(
                response.quote_number
                || response.number
                || response.code
                || ''
            );

        },

        error: function () {

            console.error('Error al generar número de cotización');

        }

    });

}


/*
|--------------------------------------------------------------------------
| LIMPIAR FORMULARIO
|--------------------------------------------------------------------------
*/
function resetQuoteForm() {

    if ($('#quoteForm').length) {

        $('#quoteForm')[0].reset();

    }

    $('#quote_id').val('');

    $('#status').val('sent');

    $('#quote_number').val('');

    $('#btnSaveQuote')
        .prop('disabled', false)
        .html('<i class="fas fa-save mr-1"></i> Guardar Cotizacion');

    $('#quoteItemsTbody').html(`
        <tr id="quoteItemsEmptyRow">

            <td colspan="15" class="text-muted py-4">
                No hay artículos agregados aún.
            </td>

        </tr>
    `);

    quoteItemIndex = 0;

    $('#subtotal_exonerated').val('0.00');
    $('#subtotal_taxed').val('0.00');
    $('#igv').val('0.00');
    $('#grand_total').val('0.00');
    $('#quoteSideGrandTotal').text('0.00');

    $('#quoteSideCustomer').text('Seleccione cliente');
    $('#quoteSideBranch').text('Seleccione sucursal');
    $('.quote-currency-code').text('PEN');
    $('.quote-currency-symbol').text('S/');
    resetCustomerBranches();

    clearQuoteErrors();

    if ($.fn.select2) {

        $('#quoteModal select').val('').trigger('change');

        $('#show_code_type').val('internal');
        $('#orientation').val('vertical').trigger('change');
        $('#billing_type').val('local').trigger('change');
        $('#affect_igv').val('0').trigger('change');
        $('#payment_condition').val('CONTADO').trigger('change');
        $('#issuer_department').val('').trigger('change');

        setDefaultCurrency('PEN');

    } else {

        $('#show_code_type').val('internal');
        $('#orientation').val('vertical');
        $('#billing_type').val('local');
        $('#affect_igv').val('0');
        $('#payment_condition').val('CONTADO');
        $('#issuer_department').val('');

        setDefaultCurrency('PEN');

    }

}

function initQuoteMarketStudySelect() {
    if (!$.fn.select2 || !window.routes.quoteMarketStudySearch) {
        return;
    }

    const select = $('#market_study_id');

    if (select.hasClass('select2-hidden-accessible')) {
        select.select2('destroy');
    }

    select.select2({
        dropdownParent: $('#quoteModal'),
        width: '100%',
        theme: 'bootstrap4',
        placeholder: 'Buscar por código o descripción...',
        allowClear: true,
        ajax: {
            url: window.routes.quoteMarketStudySearch,
            type: 'GET',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (response) {
                return { results: response.results || [] };
            },
            cache: true
        },
        language: {
            noResults: function () {
                return 'No se encontraron estudios de mercado';
            },
            searching: function () {
                return 'Buscando...';
            }
        }
    });
}

function initQuoteCustomerSelect() {
    if (!$.fn.select2 || !window.routes.quoteCustomerSearch) {
        return;
    }

    const select = $('#customer_id');

    if (select.hasClass('select2-hidden-accessible')) {
        select.select2('destroy');
    }

    select.select2({
        dropdownParent: $('#quoteModal'),
        width: '100%',
        theme: 'bootstrap4',
        placeholder: 'Buscar cliente...',
        allowClear: true,
        ajax: {
            url: window.routes.quoteCustomerSearch,
            type: 'GET',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (response) {
                return { results: response.results || [] };
            },
            cache: true
        },
        language: {
            noResults: function () {
                return 'No se encontraron clientes';
            },
            searching: function () {
                return 'Buscando...';
            }
        }
    });
}

function selectQuoteCustomer(customer, branch = null) {
    if (!customer || !customer.id) {
        return;
    }

    const select = $('#customer_id');

    if (!select.find('option[value="' + customer.id + '"]').length) {
        select.append(new Option(customer.text || customer.name || customer.id, customer.id, true, true));
    }

    select.val(String(customer.id)).trigger('change.select2');
    $('#quoteSideCustomer').text(customer.text || 'Seleccione cliente');
    resetCustomerBranches();

    loadCustomerBranches(customer.id, {
        selectedBranchId: branch?.id || null,
        autoSelectSingle: !branch
    });

    if (branch?.address) {
        $('#delivery_address').val(String(branch.address).toUpperCase());
    }
}

function initQuoteArticleSelect(row) {
    const select = row.find('.item-article-select');

    if (!$.fn.select2 || !select.length) {
        return;
    }

    if (select.hasClass('select2-hidden-accessible')) {
        select.select2('destroy');
    }

    select.select2({
        dropdownParent: $('#quoteModal'),
        width: '100%',
        theme: 'bootstrap4',
        placeholder: select.data('placeholder') || 'Buscar artículo...',
        allowClear: true,
        ajax: {
            url: window.routes.quoteArticleSearch,
            type: 'GET',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (response) {
                return { results: response.results || [] };
            },
            cache: true
        },
        language: {
            noResults: function () {
                return 'No se encontraron artículos';
            },
            searching: function () {
                return 'Buscando...';
            }
        }
    });
}

function initQuoteBrandSelect(row) {
    const select = row.find('.item-brand-id');

    if (!$.fn.select2 || !select.length) {
        return;
    }

    if (select.hasClass('select2-hidden-accessible')) {
        select.select2('destroy');
    }

    select.select2({
        dropdownParent: $('#quoteModal'),
        width: '100%',
        theme: 'bootstrap4',
        placeholder: 'Buscar marca...',
        allowClear: true,
        ajax: {
            url: window.routes.quoteBrandSearch,
            type: 'GET',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (response) {
                return { results: response.results || [] };
            },
            cache: true
        }
    });
}

function setQuoteArticle(row, article) {
    if (!row.length || !article) {
        return;
    }

    const text = article.text || [article.code, article.billing_name].filter(Boolean).join(' | ');
    const select = row.find('.item-article-select');

    if (select.length) {
        if (!select.find('option[value="' + article.id + '"]').length) {
            select.append(new Option(text, article.id, true, true));
        }

        select.val(String(article.id)).trigger('change.select2');
        select.data('selected-article', article);
    }

    row.find('.item-article-id').val(article.id || '');
    row.find('.item-article-code').val(article.code || '');
    row.find('.item-billing-name-value').val(article.billing_name || article.name || '');
    row.find('.item-billing-name').prop('disabled', true).val(article.billing_name || article.name || '');

    setRowSelectValue(row.find('.item-unit-id'), article.unit_id, article.unit_text);
    setRowSelectValue(row.find('.item-presentation-id'), article.presentation_id, article.presentation_text);
    setRowSelectValue(row.find('.item-brand-id'), article.brand_id, article.brand_text);

    if (article.origin) {
        row.find('.item-origin').val(article.origin);
    }

    if (article.cost_price !== undefined && article.cost_price !== null) {
        row.find('.item-cost-price').val(formatMoney(article.cost_price));
    }

    calculateQuoteTotals();
}

function setRowSelectValue(select, value, text = '') {
    if (!select.length) {
        return;
    }

    if (value && !select.find('option[value="' + value + '"]').length) {
        select.append(new Option(text || value, value, true, true));
    }

    select.val(value || '').trigger('change.select2');
}

function showQuickQuoteToast(icon, title) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icon,
        title: title,
        showConfirmButton: false,
        timer: 3000
    });
}

/*
|--------------------------------------------------------------------------
| LLENAR FORMULARIO PARA EDICION
|--------------------------------------------------------------------------
*/
function fillQuoteForm(quote) {

    resetQuoteForm();

    $('#quote_id').val(quote.id || '');
    $('#quote_number').val(quote.quote_number || '');
    $('#status').val(quote.status || 'sent');

    if (quote.customer_id && quote.customer) {
        const customerText = [
            quote.customer.ruc || quote.customer.document_number,
            quote.customer.business_name || quote.customer.full_name || quote.customer.first_name
        ].filter(Boolean).join(' | ');

        if (!$('#customer_id option[value="' + quote.customer_id + '"]').length) {
            $('#customer_id').append(new Option(customerText, quote.customer_id, true, true));
        }
    }

    $('#customer_id')
        .val(quote.customer_id || '')
        .trigger('change.select2');

    $('#company_id')
        .val(quote.company_id || '')
        .trigger('change.select2');

    $('#currency_id')
        .val(quote.currency_id || '')
        .trigger('change');

    $('#payment_condition')
        .val(quote.payment_condition || '')
        .trigger('change.select2');

    $('#issuer_department')
        .val(quote.issuer_department || '')
        .trigger('change.select2');

    $('#contact_number').val(quote.contact_number || '');

    $('#delivery_address').val(quote.delivery_address || '');
    $('#show_code_type').val(quote.show_code_type || 'internal');

    $('#orientation')
        .val(quote.orientation || 'vertical')
        .trigger('change.select2');

    $('#billing_type')
        .val(quote.billing_type || 'local')
        .trigger('change.select2');

    $('#affect_igv')
        .val(quote.affect_igv ? '1' : '0')
        .trigger('change.select2');

    $('#validity_date').val(formatDateForInput(quote.validity_date));
    $('#delivery_days').val(quote.delivery_days || '');
    $('#delivery_time').val(quote.delivery_time || '');
    if (quote.market_study_id && quote.market_study) {
        const studyText = [
            quote.market_study.code,
            quote.market_study.description
        ].filter(Boolean).join(' | ');

        if (!$('#market_study_id option[value="' + quote.market_study_id + '"]').length) {
            $('#market_study_id').append(new Option(studyText, quote.market_study_id, true, true));
        }
    }

    $('#market_study_id')
        .val(quote.market_study_id || '')
        .trigger('change.select2');
    $('#observations').val(quote.observations || '');
    $('#additional_observations').val(quote.additional_observations || '');

    $('#quoteSideCustomer').text(getSelectedText('#customer_id', 'Seleccione cliente'));
    $('#quoteSideBranch').text('Seleccione sucursal');

    if (quote.customer_id) {
        loadCustomerBranches(quote.customer_id, {
            autoSelectSingle: false,
            selectedBranchId: quote.customer_branch_id || null
        });
    }

    $('#quoteItemsTbody').empty();
    quoteItemIndex = 0;

    (quote.items || []).forEach(function (item) {
        addQuoteItemRow({
            market_study_item_id: item.market_study_item_id,
            article_id: item.article_id,
            article_code: item.article_code,
            billing_name_snapshot: item.billing_name_snapshot,
            note: item.note,
            unit_id: item.unit_id,
            presentation_id: item.presentation_id,
            brand_id: item.brand_id,
            origin: item.origin,
            expiration_date: formatDateForInput(item.expiration_date),
            cost_type: item.cost_type || 'PESO',
            cost_price: item.cost_price || 0,
            quantity: item.quantity || 1,
            unit_price: item.unit_price || 0,
            discount_percentage: item.discount_percentage || 0,
            discount_amount: item.discount_amount || 0,
            line_total: item.line_total || 0,
            is_winner: item.is_winner ? 1 : 0,
        });
    });

    showEmptyQuoteItemsRow();
    calculateQuoteTotals();

}


/*
|--------------------------------------------------------------------------
| AGREGAR FILA DE ARTÍCULO
|--------------------------------------------------------------------------
*/
function addQuoteItemRow(data = {}) {

    $('#quoteItemsEmptyRow').remove();

    let template =
        $('#quoteItemRowTemplate')
            .html()
            .replaceAll('__INDEX__', quoteItemIndex);

    $('#quoteItemsTbody').append(template);

    let row =
        $('#quoteItemsTbody tr').last();

    row.find('.item-market-study-item-id').val(data.market_study_item_id || '');
    row.find('.item-article-id').val(data.article_id || '');
    row.find('.item-article-code').val(data.article_code || '');
    row.find('.item-is-winner').val(data.is_winner || 0);
    row.find('.item-billing-name-value').val(data.billing_name_snapshot || '');
    row.find('.item-billing-name').prop('disabled', true).val(data.billing_name_snapshot || '');
    row.find('.item-note').val(data.note || '');
    row.find('.item-unit-id').val(data.unit_id || '');
    row.find('.item-presentation-id').val(data.presentation_id || '');
    row.find('.item-brand-id').val(data.brand_id || '');
    row.find('.item-origin').val(data.origin || '');
    row.find('.item-expiration-date').val(data.expiration_date || '');
    row.find('.item-cost-type').val(data.cost_type || 'PESO');
    row.find('.item-cost-price').val(formatMoney(data.cost_price || 0));
    row.find('.item-quantity').val(data.quantity || 1);
    row.find('.item-unit-price').val(formatQuoteUnitPrice(data.unit_price || 0));
    row.find('.item-discount-percentage').val(formatMoney(data.discount_percentage || 0));
    row.find('.item-discount-amount').val(formatMoney(data.discount_amount || 0));
    row.find('.item-line-total').val(formatMoney(data.line_total || 0));

    initSelect2ForQuoteScope(row);
    initQuoteArticleSelect(row);
    initQuoteBrandSelect(row);

    if (data.article_id) {
        setQuoteArticle(row, {
            id: data.article_id,
            code: data.article_code,
            billing_name: data.billing_name_snapshot,
            text: [data.article_code, data.billing_name_snapshot].filter(Boolean).join(' | '),
            unit_id: data.unit_id,
            presentation_id: data.presentation_id,
            brand_id: data.brand_id,
            origin: data.origin,
            cost_price: data.cost_price || 0
        });
    }

    quoteItemIndex++;

    refreshQuoteItemIndexes();

    calculateQuoteTotals();

}


/*
|--------------------------------------------------------------------------
| REFRESCAR ÍNDICES VISUALES
|--------------------------------------------------------------------------
*/
function refreshQuoteItemIndexes() {

    $('#quoteItemsTbody tr.quote-item-row').each(function (index) {

        $(this)
            .find('.quote-item-index')
            .text(index + 1);

    });

}


/*
|--------------------------------------------------------------------------
| MOSTRAR FILA VACÍA
|--------------------------------------------------------------------------
*/
function showEmptyQuoteItemsRow() {

    if ($('#quoteItemsTbody tr.quote-item-row').length === 0) {

        $('#quoteItemsTbody').html(`
            <tr id="quoteItemsEmptyRow">

                <td colspan="15" class="text-muted py-4">
                    No hay artículos agregados aún.
                </td>

            </tr>
        `);

    }

}


/*
|--------------------------------------------------------------------------
| CALCULAR TOTALES
|--------------------------------------------------------------------------
*/
function calculateQuoteTotals() {

    let subtotal = 0;

    $('#quoteItemsTbody tr.quote-item-row').each(function () {

        let row = $(this);

        let quantity =
            parseFloat(row.find('.item-quantity').val()) || 0;

        let unitPrice =
            parseFloat(row.find('.item-unit-price').val()) || 0;

        let discountPercentage =
            parseFloat(row.find('.item-discount-percentage').val()) || 0;

        let gross =
            quantity * unitPrice;

        let discountAmount =
            gross * (discountPercentage / 100);

        let lineTotal =
            gross - discountAmount;

        row.find('.item-discount-amount').val(formatMoney(discountAmount));

        row.find('.item-line-total').val(formatMoney(lineTotal));

        subtotal += lineTotal;

    });

    let affectIgv =
        $('#affect_igv').val() === '1';

    let subtotalExonerated = 0;
    let subtotalTaxed = 0;
    let igv = 0;
    let grandTotal = 0;

    if (affectIgv) {

        // El precio de venta ya incluye IGV: se desglosa sin sumarlo al total.
        grandTotal = subtotal;
        subtotalTaxed = grandTotal / 1.18;
        igv = grandTotal - subtotalTaxed;

    } else {

        subtotalExonerated = subtotal;
        grandTotal = subtotal;

    }

    $('#subtotal_exonerated').val(formatMoney(subtotalExonerated));
    $('#subtotal_taxed').val(formatMoney(subtotalTaxed));
    $('#igv').val(formatMoney(igv));
    $('#grand_total').val(formatMoney(grandTotal));

    $('#quoteSideGrandTotal').text(formatMoney(grandTotal));

}


/*
|--------------------------------------------------------------------------
| ERRORES DE VALIDACIÓN
|--------------------------------------------------------------------------
*/
function clearQuoteErrors() {

    $('#quoteForm .is-invalid').removeClass('is-invalid');

    $('#quoteForm .invalid-feedback').text('');

}

function showQuoteErrors(errors) {

    $.each(errors, function (field, messages) {

        let inputName =
            field.replace(/\./g, '_');

        let input =
            $('[name="' + field + '"]');

        if (!input.length) {

            input =
                $('#' + inputName);

        }

        input.addClass('is-invalid');

        $('#' + inputName + '-error').text(messages[0]);

    });

}


/*
|--------------------------------------------------------------------------
| FORMATO MONEDA
|--------------------------------------------------------------------------
*/
function formatMoney(value) {

    return (parseFloat(value) || 0).toFixed(2);

}

function formatDateForInput(value) {

    if (!value) {
        return '';
    }

    return String(value).substring(0, 10);

}

function getSelectedText(selector, fallback = '') {

    let text = $(selector).find('option:selected').text().trim();

    return text || fallback;

}


/*
|--------------------------------------------------------------------------
| REINICIAR SELECT SUCURSALES
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| REINICIAR SELECT SUCURSALES
|--------------------------------------------------------------------------
*/
function resetCustomerBranches() {

    $('#customer_branch_id')
        .prop('disabled', true)
        .html(`
            <option value="">
                Seleccione cliente primero
            </option>
        `);

    if ($.fn.select2) {
        $('#customer_branch_id').trigger('change.select2');
    }

}

/*
|--------------------------------------------------------------------------
| CARGAR SUCURSALES DEL CLIENTE
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| CARGAR SUCURSALES DEL CLIENTE
|--------------------------------------------------------------------------
*/
function loadCustomerBranches(customerId, options = {}) {

    let settings = Object.assign({
        selectedBranchId: null,
        autoSelectSingle: true,
    }, options);

    let url = window.routes.quoteCustomerBranches.replace(':id', customerId);

    $('#customer_branch_id')
        .prop('disabled', true)
        .html(`
            <option value="">
                Cargando sucursales...
            </option>
        `);

    if ($.fn.select2) {
        $('#customer_branch_id').trigger('change.select2');
    }

    $.ajax({

        url: url,
        type: 'GET',

        success: function (response) {

            let branches = response.branches || [];

            if (!branches.length) {

                $('#customer_branch_id')
                    .prop('disabled', true)
                    .html(`
                        <option value="">
                            Este cliente no tiene sucursales
                        </option>
                    `);

                if ($.fn.select2) {
                    $('#customer_branch_id').trigger('change.select2');
                }

                return;

            }

            let options = `
                <option value="">
                    Seleccione sucursal
                </option>
            `;

            branches.forEach(function (branch) {

                let mainText = branch.is_main == 1 ? ' - PRINCIPAL' : '';

                options += `
                    <option
                        value="${branch.id}"
                        data-branch-name="${branch.branch_name || ''}"
                        data-address="${branch.address || ''}"
                        data-reference="${branch.reference || ''}"
                        data-payment-condition="${branch.payment_condition || ''}"
                    >
                        ${branch.branch_name || 'SIN NOMBRE'}${mainText}
                    </option>
                `;

            });

            $('#customer_branch_id')
                .prop('disabled', false)
                .html(options);

            if ($.fn.select2) {
                $('#customer_branch_id').trigger('change.select2');
            }

            /*
            |--------------------------------------------------------------------------
            | SI SOLO TIENE UNA SUCURSAL, SELECCIONAR AUTOMÁTICAMENTE
            |--------------------------------------------------------------------------
            */
            if (settings.selectedBranchId) {

                $('#customer_branch_id')
                    .val(settings.selectedBranchId)
                    .trigger('change');

                return;

            }

            if (settings.autoSelectSingle && branches.length === 1) {

                $('#customer_branch_id')
                    .val(branches[0].id)
                    .trigger('change');

            }

        },

        error: function () {

            $('#customer_branch_id')
                .prop('disabled', true)
                .html(`
                    <option value="">
                        Error al cargar sucursales
                    </option>
                `);

            if ($.fn.select2) {
                $('#customer_branch_id').trigger('change.select2');
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudieron cargar las sucursales del cliente.'
            });

        }

    });

}

/*
|--------------------------------------------------------------------------
| SELECCIONAR MONEDA POR DEFECTO
|--------------------------------------------------------------------------
*/
function setDefaultCurrency(defaultCode = 'PEN') {

    let option = $('#currency_id option').filter(function () {

        return String($(this).data('code')).toUpperCase() === defaultCode;

    }).first();

    if (option.length) {

        $('#currency_id')
            .val(option.val())
            .trigger('change');

        let code = option.data('code') || 'PEN';
        let symbol = option.data('symbol') || 'S/';

        $('.quote-currency-code').text(code);
        $('.quote-currency-symbol').text(symbol);

    }

}

/*
|--------------------------------------------------------------------------
| ACTUALIZAR TIEMPO DE ENTREGA SEGÚN DÍAS
|--------------------------------------------------------------------------
*/
function updateDeliveryTimeFromDays() {

    let days = parseInt($('#delivery_days').val(), 10);

    if (!days || days <= 0) {

        $('#delivery_time').val('');

        return;

    }

    let text = days === 1
        ? '1 DÍA CALENDARIO'
        : days + ' DÍAS CALENDARIOS';

    $('#delivery_time').val(text);

}

/*
|--------------------------------------------------------------------------
| CARGAR ARTÍCULOS GANADORES DEL ESTUDIO DE MERCADO
|--------------------------------------------------------------------------
*/
function loadMarketStudyWinnerItems(marketStudyId) {

    let url = window.routes.quoteMarketStudyWinners.replace(':id', marketStudyId);

    $('#btnFilterMarketStudy')
        .prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Cargando');

    $.ajax({

        url: url,
        type: 'GET',

        success: function (response) {

            let items = response.items || [];

            if (!items.length) {

                $('#quoteItemsTbody').html(`
                    <tr id="quoteItemsEmptyRow">
                        <td colspan="15" class="text-muted py-4">
                            Este estudio no tiene artículos ganadores.
                        </td>
                    </tr>
                `);

                quoteItemIndex = 0;

                calculateQuoteTotals();

                Swal.fire({
                    icon: 'info',
                    title: 'Sin ganadores',
                    text: 'El estudio seleccionado no tiene artículos ganadores registrados.'
                });

                return;

            }

            $('#quoteItemsTbody').empty();

            quoteItemIndex = 0;

            items.forEach(function (item) {

                addQuoteItemRow({
                    market_study_item_id: item.market_study_item_id,
                    article_id: item.article_id,
                    article_code: item.article_code,
                    billing_name_snapshot: item.billing_name_snapshot,

                    unit_id: item.unit_id,
                    presentation_id: item.presentation_id,
                    brand_id: item.brand_id,

                    origin: item.origin,
                    expiration_date: item.expiration_date,

                    cost_type: item.cost_type || 'PESO',
                    cost_price: item.cost_price || 0,

                    quantity: item.quantity || 1,
                    unit_price: item.unit_price || 0,

                    discount_percentage: item.discount_percentage || 0,
                    discount_amount: item.discount_amount || 0,
                    line_total: item.line_total || 0,

                    is_winner: 1,
                });

            });

            calculateQuoteTotals();

            Swal.fire({
                icon: 'success',
                title: 'Ganadores cargados',
                text: 'Los artículos ganadores fueron agregados a la cotización.',
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
                text: xhr.responseJSON?.message || 'No se pudieron cargar los artículos ganadores.'
            });

        },

        complete: function () {

            $('#btnFilterMarketStudy')
                .prop('disabled', false)
                .html('<i class="fas fa-filter mr-1"></i> Filtrar');

        }

    });

}

$(document).on('select2:select', '.item-article-select', function (event) {
    setQuoteArticle($(this).closest('tr.quote-item-row'), event.params.data || {});
});

$(document).on('select2:clear', '.item-article-select', function () {
    const row = $(this).closest('tr.quote-item-row');

    row.find('.item-article-id').val('');
    row.find('.item-article-code').val('');
    row.find('.item-billing-name-value').val('');
    row.find('.item-billing-name').prop('disabled', true).val('');
});

$(document).on('click', '.btnOpenQuickQuoteArticle', function () {
    quickQuoteArticleRow = $(this).closest('tr.quote-item-row');
    resetQuickQuoteArticleForm();
    $('#quickQuoteArticleModal').modal('show');
});

$(document).on('click', '.btnOpenQuickQuoteBrand', function () {
    quickQuoteBrandRow = $(this).closest('tr.quote-item-row');
    resetQuickQuoteBrandForm();
    $('#quickQuoteBrandModal').modal('show');
});

$('#quickQuoteArticleModal, #quickQuoteBrandModal').on('hidden.bs.modal', function () {
    if ($('#quoteModal').hasClass('show')) {
        $('body').addClass('modal-open');
    }
});

$(document).on('click', '#btnOpenQuickCustomerModal', function () {
    resetQuickCustomerForm();
    $('#quickCustomerModal').modal('show');
});

$('#quickCustomerModal').on('shown.bs.modal', function () {
    $('#quick_customer_document_number').trigger('focus');
});

$('#quickCustomerModal').on('hidden.bs.modal', function () {
    if ($('#quoteModal').hasClass('show')) {
        $('body').addClass('modal-open');
    }
});

$(document).on('change', '#quick_customer_document_type', function () {
    const type = $(this).val();

    if (type === 'RUC') {
        $('#quick_customer_person_type').val('juridica').trigger('change.select2');
        $('#quick_customer_document_number').attr('maxlength', 11);
    } else if (type === 'DNI') {
        $('#quick_customer_person_type').val('natural').trigger('change.select2');
        $('#quick_customer_document_number').attr('maxlength', 8);
    } else {
        $('#quick_customer_document_number').attr('maxlength', 20);
    }

    lastQuickCustomerDocument = '';
    $('#quick_customer_document_number').val('');
});

$(document).on('input', '#quick_customer_document_number', function () {
    const documentType = $('#quick_customer_document_type').val();
    const maxLength = documentType === 'RUC' ? 11 : (documentType === 'DNI' ? 8 : 20);
    const number = $(this).val().replace(/\D/g, '').slice(0, maxLength);

    $(this).val(number);
    clearTimeout(quickCustomerDocumentTimer);

    if (!['RUC', 'DNI'].includes(documentType)) {
        return;
    }

    const expectedLength = documentType === 'RUC' ? 11 : 8;

    if (number.length !== expectedLength || number === lastQuickCustomerDocument) {
        return;
    }

    quickCustomerDocumentTimer = setTimeout(function () {
        consultQuickCustomerDocument(documentType, number);
    }, 350);
});

$(document).on('submit', '#quickCustomerForm', function (event) {
    event.preventDefault();

    clearQuickQuoteFormErrors('#quickCustomerForm');

    const button = $('#btnSaveQuickCustomer');
    const formData = new FormData(this);

    button.prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.quoteCustomerQuickStore,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            selectQuoteCustomer(response.customer, response.branch);
            $('#quickCustomerModal').modal('hide');
            showQuickQuoteToast('success', 'Cliente registrado y seleccionado correctamente.');
        },
        error: function (xhr) {
            if (xhr.status === 409) {
                const response = xhr.responseJSON || {};

                Swal.fire({
                    icon: 'warning',
                    title: 'Cliente existente',
                    text: response.message || 'Este cliente ya está registrado.',
                    showCancelButton: true,
                    confirmButtonText: 'Seleccionarlo',
                    cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        selectQuoteCustomer(response.customer, response.branch);
                        $('#quickCustomerModal').modal('hide');
                    }
                });
                return;
            }

            if (xhr.status === 422) {
                showQuickQuoteFormErrors('#quickCustomerForm', xhr.responseJSON?.errors || {});
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo guardar el cliente.'
            });
        },
        complete: function () {
            button.prop('disabled', false)
                .html('<i class="fas fa-save mr-1"></i> Guardar Cliente');
        }
    });
});

$(document).on('show.bs.modal', '#quoteModal, #quickCustomerModal, #quickQuoteArticleModal, #quickQuoteBrandModal', function () {
    const zIndex = 1040 + (10 * $('.modal:visible').length);

    $(this).css('z-index', zIndex);

    setTimeout(function () {
        $('.modal-backdrop')
            .not('.modal-stack')
            .css('z-index', zIndex - 1)
            .addClass('modal-stack');
    }, 0);
});

$(document).on('input', '#quick_quote_article_legal_name', function () {
    const value = $(this).val();

    if (!$('#quick_quote_article_commercial_name').val()) {
        $('#quick_quote_article_commercial_name').val(value);
    }

    if (!$('#quick_quote_article_billing_name').val()) {
        $('#quick_quote_article_billing_name').val(value);
    }
});

$(document).on('input', '#quick_quote_article_commercial_name', function () {
    if (!$('#quick_quote_article_billing_name').val()) {
        $('#quick_quote_article_billing_name').val($(this).val());
    }
});

$(document).on('submit', '#quickQuoteArticleForm', function (event) {
    event.preventDefault();

    clearQuickQuoteFormErrors('#quickQuoteArticleForm');

    const button = $('#btnSaveQuickQuoteArticle');
    const formData = new FormData(this);

    button.prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.quoteArticleQuickStore,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            const article = response.data || {};

            if (quickQuoteArticleRow && quickQuoteArticleRow.length) {
                setQuoteArticle(quickQuoteArticleRow, article);
            }

            $('#quickQuoteArticleModal').modal('hide');
            showQuickQuoteToast('success', 'Artículo registrado y seleccionado correctamente.');
        },
        error: function (xhr) {
            if (xhr.status === 422) {
                showQuickQuoteFormErrors('#quickQuoteArticleForm', xhr.responseJSON?.errors || {});
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo registrar el artículo.'
            });
        },
        complete: function () {
            button.prop('disabled', false)
                .html('<i class="fas fa-save mr-1"></i> Guardar Artículo');
        }
    });
});

$(document).on('submit', '#quickQuoteBrandForm', function (event) {
    event.preventDefault();

    clearQuickQuoteFormErrors('#quickQuoteBrandForm');

    const button = $('#btnSaveQuickQuoteBrand');
    const formData = new FormData(this);

    button.prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: window.routes.quoteBrandQuickStore,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            const brand = response.data || {};

            if (quickQuoteBrandRow && quickQuoteBrandRow.length) {
                const select = quickQuoteBrandRow.find('.item-brand-id');

                if (!select.find('option[value="' + brand.id + '"]').length) {
                    select.append(new Option(brand.text || brand.description, brand.id, true, true));
                }

                select.val(String(brand.id)).trigger('change.select2');
            }

            $('#quickQuoteBrandModal').modal('hide');
            showQuickQuoteToast('success', 'Marca registrada y seleccionada correctamente.');
        },
        error: function (xhr) {
            if (xhr.status === 422) {
                showQuickQuoteFormErrors('#quickQuoteBrandForm', xhr.responseJSON?.errors || {});
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo registrar la marca.'
            });
        },
        complete: function () {
            button.prop('disabled', false)
                .html('<i class="fas fa-save mr-1"></i> Guardar Marca');
        }
    });
});

function resetQuickQuoteArticleForm() {
    const form = $('#quickQuoteArticleForm');

    form[0].reset();
    clearQuickQuoteFormErrors('#quickQuoteArticleForm');
    $('#quick_quote_article_code').val('Cargando...');
    $('#quick_quote_article_code_type').val('SIGA/SISMED');

    $.get(window.routes.quoteArticleGenerateCode)
        .done(function (response) {
            $('#quick_quote_article_code').val(response.code || '');
        })
        .fail(function () {
            $('#quick_quote_article_code').val('');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudo generar el código del artículo.'
            });
        });
}

function formatQuoteUnitPrice(value) {
    const formatted = (parseFloat(value) || 0).toFixed(4);

    return formatted.replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1');
}

function resetQuickCustomerForm() {
    const form = $('#quickCustomerForm');

    form[0].reset();
    clearQuickQuoteFormErrors('#quickCustomerForm');
    $('#quick_customer_person_type').val('natural').trigger('change.select2');
    $('#quick_customer_document_type').val('DNI').trigger('change.select2');
    $('#quick_customer_withholding_agent').val('0').trigger('change.select2');
    $('#quickCustomerDocumentLoading').addClass('d-none');
    $('#btnSaveQuickCustomer')
        .prop('disabled', false)
        .html('<i class="fas fa-save mr-1"></i> Guardar Cliente');
    lastQuickCustomerDocument = '';
}

function consultQuickCustomerDocument(documentType, number) {
    if (quickCustomerConsulting || !window.routes.quoteCustomerDocumentConsult) {
        return;
    }

    quickCustomerConsulting = true;
    lastQuickCustomerDocument = number;

    $('#quickCustomerDocumentLoading').removeClass('d-none');

    const url = window.routes.quoteCustomerDocumentConsult.replace('DOC_PLACEHOLDER', number);

    $.ajax({
        url: url,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            if (!response.status) {
                showQuickQuoteToast('warning', response.message || 'No se pudo consultar el documento.');
                return;
            }

            fillQuickCustomerDocument(response, documentType);
        },
        error: function (xhr) {
            showQuickQuoteToast(
                'warning',
                xhr.responseJSON?.message || 'No se pudo consultar el documento.'
            );
        },
        complete: function () {
            quickCustomerConsulting = false;
            $('#quickCustomerDocumentLoading').addClass('d-none');
        }
    });
}

function fillQuickCustomerDocument(response, documentType) {
    const data = response.data || {};

    if (documentType === 'RUC') {
        const name = response.razon_social
            || data.nombre
            || data.razonSocial
            || '';
        const address = response.direccion
            || data.direccion
            || data.domicilioFiscal
            || '';

        if (name) {
            $('#quick_customer_name').val(name).trigger('input');
        }

        if (address) {
            $('#quick_customer_address').val(address).trigger('input');
        }

        return;
    }

    const fullName = [
        data.nombres,
        data.apellidoPaterno,
        data.apellidoMaterno
    ].filter(Boolean).join(' ');

    if (fullName) {
        $('#quick_customer_name').val(fullName).trigger('input');
    }
}

function resetQuickQuoteBrandForm() {
    const form = $('#quickQuoteBrandForm');

    form[0].reset();
    clearQuickQuoteFormErrors('#quickQuoteBrandForm');
}

function clearQuickQuoteFormErrors(formSelector) {
    $(formSelector + ' .is-invalid').removeClass('is-invalid');
    $(formSelector + ' .invalid-feedback').text('');
}

function showQuickQuoteFormErrors(formSelector, errors) {
    Object.entries(errors || {}).forEach(function ([field, messages]) {
        const message = Array.isArray(messages) ? messages[0] : messages;
        const input = $(formSelector + ' [name="' + field + '"]');

        if (input.length) {
            input.addClass('is-invalid');
            input.closest('.form-group').find('.invalid-feedback').first().text(message);
        }
    });
}
