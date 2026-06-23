let tableQuote;
let quoteItemIndex = 0;

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
    | GUARDAR COTIZACIÓN
    |--------------------------------------------------------------------------
    */
    $(document).on('submit', '#quoteForm', function (e) {

        e.preventDefault();

        clearQuoteErrors();

        calculateQuoteTotals();

        let formData = new FormData(this);

        let quoteId = $('#quote_id').val();

        let url = quoteId
            ? window.routes.updateQuote + '/' + quoteId
            : window.routes.storeQuote;

        if (quoteId) {

            formData.append('_method', 'PUT');

        }

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

                $('#quoteModal').modal('hide');

                tableQuote.ajax.reload(null, false);

            },

            error: function (xhr) {

                if (xhr.status === 422) {

                    showQuoteErrors(xhr.responseJSON.errors);

                    return;

                }

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text:
                        xhr.responseJSON?.message
                        || 'Error al guardar la cotización'

                });

            }

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
                text: 'Debe seleccionar un estudio para cargar los artículos ganadores.'
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

    scope.find('select').not('.item-unit-id').select2({

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

    $('#status').val('draft');

    $('#quote_number').val('');

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

        setDefaultCurrency('PEN');

    } else {

        $('#show_code_type').val('internal');
        $('#orientation').val('vertical');
        $('#billing_type').val('local');
        $('#affect_igv').val('0');
        $('#payment_condition').val('CONTADO');

        setDefaultCurrency('PEN');

    }

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
    row.find('.item-billing-name').val(data.billing_name_snapshot || '');
    row.find('.item-note').val(data.note || '');
    row.find('.item-unit-id').val(data.unit_id || '');
    row.find('.item-presentation-id').val(data.presentation_id || '');
    row.find('.item-brand-id').val(data.brand_id || '');
    row.find('.item-origin').val(data.origin || '');
    row.find('.item-expiration-date').val(data.expiration_date || '');
    row.find('.item-cost-type').val(data.cost_type || 'PESO');
    row.find('.item-cost-price').val(formatMoney(data.cost_price || 0));
    row.find('.item-quantity').val(data.quantity || 1);
    row.find('.item-unit-price').val(formatMoney(data.unit_price || 0));
    row.find('.item-discount-percentage').val(formatMoney(data.discount_percentage || 0));
    row.find('.item-discount-amount').val(formatMoney(data.discount_amount || 0));
    row.find('.item-line-total').val(formatMoney(data.line_total || 0));

    initSelect2ForQuoteScope(row);

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

        subtotalTaxed = subtotal;
        igv = subtotalTaxed * 0.18;
        grandTotal = subtotalTaxed + igv;

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
function loadCustomerBranches(customerId) {

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
            if (branches.length === 1) {

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