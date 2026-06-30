
let tableMarketStudy;
let tableArticlePicker = null;
let marketStudyArticles = [];
let selectedArticlePickerItems = {};
let deletedMarketStudyDocuments = [];

let quoteItems = [];

let quoteSaved = false;

function resetQuoteDraft() {
    quoteItems = [];
    renderQuoteItems();

    $('#marketStudyQuoteForm')[0].reset();

    $('#market_study_quote_id').val('');
    $('#market_study_id_quote').val('');

    $('#quote_number').val('');
    $('#supplier_id').val('');
    $('#currency_id').val('');
    $('#payment_condition').val('');
    $('#exchange_rate').val('1.0000');

    $('#quote_market_study_info').html('—');
    $('#quote_supplier_info').html('—');
    $('#quote_currency_info').html('—');

    $('#quoteSelectedCount').text('0');
    $('#checkAllQuoteItems').prop('checked', false);

    $('#btnSaveMarketStudyQuote')
        .prop('disabled', false)
        .html('<i class="fas fa-save mr-1"></i> Guardar Cotización');
}

function escapeHtml(text) {
    return $('<div>').text(text ?? '').html();
}

$(function () {
    $('[data-toggle="tooltip"]').tooltip();
});

document.addEventListener('DOMContentLoaded', function () {

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // =====================================================
    // HELPERS
    // =====================================================



    function clearValidationErrors() {
        $('#marketStudyForm .form-control').removeClass('is-invalid');
        $('#marketStudyForm .invalid-feedback').text('');
    }

    function showValidationErrors(errors) {
        clearValidationErrors();

        Object.keys(errors || {}).forEach(function (field) {
            const message = Array.isArray(errors[field])
                ? errors[field][0]
                : errors[field];

            const input = $('#' + field);
            if (input.length) {
                input.addClass('is-invalid');
            }

            const feedback = $('#' + field + '-error');
            if (feedback.length) {
                feedback.text(message);
            }
        });
    }

    function resetMarketStudyModal() {
        $('#marketStudyForm')[0].reset();
        $('#market_study_id').val('');
        $('#code').val('');
        $('#selectedFiles').html('');
        $('#market_study_documents').val('');
        deletedMarketStudyDocuments = [];
        marketStudyArticles = [];
        renderMarketStudyArticles();
        clearValidationErrors();
        $('#marketStudyModalLabel').text('Nuevo Estudio de Mercado');
        $('#btnSaveMarketStudy')
            .prop('disabled', false)
            .html('<i class="fas fa-save mr-1"></i> Guardar Estudio');
    }

    function renderSelectedFiles(files = []) {
        if (!files.length) {
            $('#selectedFiles').html('');
            return;
        }

        let html = '';
        files.forEach(function (fileName) {
            html += `
                <span class="file-chip">
                    <i class="fas fa-file mr-1"></i>
                    ${escapeHtml(fileName)}
                </span>
            `;
        });

        $('#selectedFiles').html(html);
    }

    function renderFilesFromInput() {
        const files = Array.from($('#market_study_documents')[0]?.files || []);
        renderSelectedFiles(files.map(file => file.name));
    }

    function normalizeMarketStudyArticle(item) {
        return {
            article_id: item.article_id ?? item.id ?? null,
            article_code_snapshot: item.article_code_snapshot ?? item.code ?? item.article_code ?? '',
            billing_name_snapshot: item.billing_name_snapshot ?? item.billing_name ?? item.article?.billing_name ?? item.article?.commercial_name ?? '',
            category_snapshot: item.category_snapshot ?? item.article?.category?.description ?? item.category ?? '',
            subcategory_snapshot: item.subcategory_snapshot ?? item.article?.subcategory?.description ?? item.subcategory ?? '',
            presentation_snapshot: item.presentation_snapshot ?? item.article?.presentation?.description ?? item.presentation ?? '',
            weight_snapshot: item.weight_snapshot ?? item.weight ?? '',
            cost_condition_snapshot: item.cost_condition_snapshot ?? item.cost_condition ?? '',
            status: item.status ?? 1
        };
    }

    function renderMarketStudyArticles() {
        const tbody = $('#marketStudyArticlesTbody');

        if (!marketStudyArticles.length) {
            tbody.html(`
                <tr id="marketStudyArticlesEmptyRow">
                    <td colspan="7" class="text-muted py-4">
                        No hay artículos agregados aún.
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';

        marketStudyArticles.forEach(function (item, index) {
            html += `
                <tr data-index="${index}">
                    <td>${index + 1}</td>
                    <td class="text-left">
                        ${escapeHtml(item.article_code_snapshot)}${item.billing_name_snapshot ? ' | ' + escapeHtml(item.billing_name_snapshot) : ''}
                    </td>
                    <td class="text-left">
                        ${escapeHtml(item.category_snapshot)}${item.subcategory_snapshot ? ' | ' + escapeHtml(item.subcategory_snapshot) : ''}
                    </td>
                    <td>${escapeHtml(item.presentation_snapshot)}</td>
                    <td>${escapeHtml(item.weight_snapshot)}</td>
                    <td>${escapeHtml(item.cost_condition_snapshot)}</td>
                    <td>
                        <button type="button"
                            class="btn btn-outline-danger btn-sm removeMarketStudyArticle"
                            data-index="${index}"
                            title="Eliminar artículo">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.html(html);
    }

    function generateMarketStudyCode() {
        if (!window.routes.marketStudyGenerateCode) {
            return;
        }

        $.ajax({
            url: window.routes.marketStudyGenerateCode,
            type: 'GET',
            success: function (response) {
                $('#code').val(response.code || '');
            },
            error: function () {
                console.error('No se pudo generar el código del estudio de mercado.');
            }
        });
    }

    function loadMarketStudyForEdit(id) {
        $.ajax({
            url: `${window.routes.marketStudyUpdate}/${id}/edit`,
            type: 'GET',
            success: function (response) {
                const study = response.data || response;

                $('#market_study_id').val(study.id);
                $('#code').val(study.code || '');
                $('#description').val(study.description || '');
                $('#reference_terms').val(study.reference_terms || '');
                $('#status').val(study.status ? '1' : '0');

                marketStudyArticles = Array.isArray(study.items)
                    ? study.items.map(normalizeMarketStudyArticle)
                    : [];

                renderMarketStudyArticles();

                const docNames = Array.isArray(study.documents)
                    ? study.documents.map(doc => doc.original_name || doc.stored_name || 'Documento')
                    : [];

                renderSelectedFiles(docNames);

                $('#marketStudyModalLabel').text('Editar Estudio de Mercado');
                $('#btnSaveMarketStudy')
                    .prop('disabled', false)
                    .html('<i class="fas fa-save mr-1"></i> Actualizar Estudio');

                $('#marketStudyModal').modal('show');
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'No se pudo cargar el estudio.'
                });
            }
        });
    }

    // =====================================================
    // DATA TABLE
    // =====================================================

    tableMarketStudy = $('#tableMarketStudy').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.marketStudyList,
        responsive: true,
        autoWidth: false,
        language: {
            url: "/vendor/datatables/js/i18n/es-ES.json"
        },
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
                data: 'code',
                name: 'code'
            },
            {
                data: 'description',
                name: 'description'
            },
            {
                data: 'reference_terms',
                name: 'reference_terms'
            },
            {
                data: 'status_badge',
                name: 'status_badge',
                orderable: false,
                searchable: false
            },
            {
                data: 'acciones',
                name: 'acciones',
                orderable: false,
                searchable: false
            }
        ]
    });

    // =====================================================
    // NUEVO
    // =====================================================

    $('#btnCreateMarketStudy').on('click', function () {
        resetMarketStudyModal();
        generateMarketStudyCode();
        $('#marketStudyModal').modal('show');
    });

    // =====================================================
    // SUBIR ARCHIVOS
    // =====================================================

    $(document).on('change', '#market_study_documents', function () {
        renderFilesFromInput();
    });

    // =====================================================
    // AGREGAR ARTÍCULOS (HOOK)
    // =====================================================

    $(document).on('click', '#btnInsertMarketStudyArticle', function () {
        if ($('#articlePickerModal').length) {
            $('#articlePickerModal').modal('show');
            return;
        }

        if ($('#marketStudyArticleModal').length) {
            $('#marketStudyArticleModal').modal('show');
            return;
        }

        Swal.fire({
            icon: 'info',
            title: 'Selector pendiente',
            text: 'Aún no se ha agregado el selector de artículos.'
        });
    });

    // Este gancho queda listo para que el selector de artículos lo llame.
    window.addMarketStudyArticleToStudy = function (article) {
        const normalized = normalizeMarketStudyArticle(article);

        const exists = marketStudyArticles.find(
            item => parseInt(item.article_id) === parseInt(normalized.article_id)
        );

        if (exists) {
            return false;
        }

        marketStudyArticles.push(normalized);
        renderMarketStudyArticles();

        return true;
    };

    $(document).on('click', '.removeMarketStudyArticle', function () {
        const index = $(this).data('index');
        marketStudyArticles.splice(index, 1);
        renderMarketStudyArticles();
    });

    $(document).on('click', '.selectArticle', function () {

        const articleId = $(this).data('id');

        const exists = marketStudyArticles.find(
            item => parseInt(item.article_id) === parseInt(articleId)
        );

        if (exists) {

            Swal.fire({
                icon: 'warning',
                title: 'Artículo repetido',
                text: 'Este artículo ya fue agregado.'
            });

            return;
        }

        const article = {

            article_id: articleId,

            article_code_snapshot: $(this).data('code'),

            billing_name_snapshot: $(this).data('name'),

            category_snapshot: $(this).data('category'),

            subcategory_snapshot: $(this).data('subcategory'),

            presentation_snapshot: $(this).data('presentation'),

            weight_snapshot: $(this).data('weight'),

            cost_condition_snapshot: $(this).data('cost_condition'),

            status: 1

        };

        window.addMarketStudyArticleToStudy(article);

        $('#articlePickerModal').modal('hide');

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Artículo agregado',
            showConfirmButton: false,
            timer: 2000
        });

    });

    // =====================================================
    // GUARDAR
    // =====================================================

    $(document).on('submit', '#marketStudyForm', function (e) {
        e.preventDefault();
        clearValidationErrors();

        const btn = $('#btnSaveMarketStudy');
        const marketStudyId = $('#market_study_id').val();

        const url = marketStudyId
            ? `${window.routes.marketStudyUpdate}/${marketStudyId}`
            : window.routes.marketStudyStore;

        const formData = new FormData(this);

        formData.append(
            'articles_data',
            JSON.stringify(
                marketStudyArticles
                    .filter(Boolean)
                    .map(item => ({
                        article_id: item.article_id,
                        article_code_snapshot: item.article_code_snapshot,
                        billing_name_snapshot: item.billing_name_snapshot,
                        category_snapshot: item.category_snapshot,
                        subcategory_snapshot: item.subcategory_snapshot,
                        presentation_snapshot: item.presentation_snapshot,
                        weight_snapshot: item.weight_snapshot,
                        cost_condition_snapshot: item.cost_condition_snapshot,
                        status: item.status
                    }))
            )
        );

        if (marketStudyId) {
            formData.append('_method', 'PUT');
        }

        btn.prop('disabled', true);
        btn.html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

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
                    title: response.message || 'Guardado correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });

                $('#marketStudyModal').modal('hide');
                tableMarketStudy.ajax.reload(null, false);
                resetMarketStudyModal();
            },
            error: function (xhr) {
                btn.prop('disabled', false);
                btn.html('<i class="fas fa-save mr-1"></i> Guardar Estudio');

                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    showValidationErrors(xhr.responseJSON.errors);

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
                    text: xhr.responseJSON?.message || 'No se pudo guardar el estudio.'
                });
            }
        });
    });

    // =====================================================
    // EDITAR
    // =====================================================

    $(document).on('click', '.editMarketStudy', function () {
        const id = $(this).data('id');
        loadMarketStudyForEdit(id);
    });

    // =====================================================
    // ELIMINAR
    // =====================================================

    $(document).on('click', '.deleteMarketStudy', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar estudio?',
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
                url: `${window.routes.marketStudyDelete}/${id}`,
                type: 'POST',
                data: {
                    _method: 'DELETE'
                },
                success: function (response) {
                    Swal.fire({
                        icon: 'success',
                        title: response.message || 'Eliminado correctamente.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2500
                    });

                    tableMarketStudy.ajax.reload(null, false);
                },
                error: function (xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'No se pudo eliminar.'
                    });
                }
            });
        });
    });

    // =====================================================
    // LIMPIAR MODAL
    // =====================================================

    $('#marketStudyModal').on('hidden.bs.modal', function () {
        resetMarketStudyModal();
    });

    // =====================================================
    // BOTÓN EXAMINAR MODERNO: CHIP DE ARCHIVOS
    // =====================================================

    $(document).on('click', '.market-study-upload-label', function () {
        $('#market_study_documents').trigger('click');
    });
});

$('#articlePickerModal').on('shown.bs.modal', function () {

    selectedArticlePickerItems = {};
    updateArticlePickerSelectionState();

    if ($.fn.DataTable.isDataTable('#tableMarketStudyArticlePicker')) {
        tableArticlePicker.ajax.reload();
        return;
    }

    tableArticlePicker = $('#tableMarketStudyArticlePicker').DataTable({

        processing: true,
        serverSide: true,

        ajax: window.routes.articlePickerList,

        pageLength: 10,

        language: {
            url: "/vendor/datatables/js/i18n/es-ES.json"
        },

        columns: [

            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data, type, row) {
                    const articleId = parseInt(row.id);
                    const alreadyAdded = marketStudyArticles.some(
                        item => parseInt(item.article_id) === articleId
                    );
                    const checked = selectedArticlePickerItems[articleId]
                        ? 'checked'
                        : '';
                    const disabled = alreadyAdded
                        ? 'disabled'
                        : '';
                    const title = alreadyAdded
                        ? 'Agregado'
                        : 'Seleccionar';

                    return `
                        <input type="checkbox"
                            class="form-check-input article-picker-check"
                            value="${articleId}"
                            title="${title}"
                            ${checked}
                            ${disabled}>
                    `;
                }
            },

            {
                data: 'id'
            },

            {
                data: 'code'
            },

            {
                data: 'billing_name'
            },

            {
                data: 'category_name',
                orderable: false
            },

            {
                data: 'subcategory_name',
                orderable: false
            },

            {
                data: 'presentation_name',
                orderable: false
            },

        ]

    }).on('draw', function () {
        updateArticlePickerSelectionState();
    });

});

function buildArticlePickerItem(row) {
    return {
        article_id: row.id,
        article_code_snapshot: row.code,
        billing_name_snapshot: row.billing_name,
        category_snapshot: row.category_name,
        subcategory_snapshot: row.subcategory_name,
        presentation_snapshot: row.presentation_name,
        unit_snapshot: row.unit_name,
        brand_snapshot: row.brand_name,
        weight_snapshot: row.weight ?? '',
        cost_condition_snapshot: row.cost_condition ?? '',
        status: 1
    };
}

function updateArticlePickerSelectionState() {
    const selectedCount = Object.keys(selectedArticlePickerItems).length;

    $('#articlePickerSelectedCount').text(selectedCount);

    const visibleChecks = $('#tableMarketStudyArticlePicker tbody .article-picker-check:not(:disabled)');
    const checkedVisible = visibleChecks.filter(':checked');

    $('#checkAllArticlePicker').prop(
        'checked',
        visibleChecks.length > 0 && visibleChecks.length === checkedVisible.length
    );
}

$(document).on('change', '#tableMarketStudyArticlePicker .article-picker-check', function () {
    const articleId = parseInt($(this).val());
    const rowData = tableArticlePicker
        ? tableArticlePicker.row($(this).closest('tr')).data()
        : null;

    if (!rowData) {
        return;
    }

    if ($(this).is(':checked')) {
        selectedArticlePickerItems[articleId] = buildArticlePickerItem(rowData);
    } else {
        delete selectedArticlePickerItems[articleId];
    }

    updateArticlePickerSelectionState();
});

$(document).on('change', '#checkAllArticlePicker', function () {
    const checked = $(this).is(':checked');

    $('#tableMarketStudyArticlePicker tbody .article-picker-check:not(:disabled)').each(function () {
        const checkbox = $(this);
        const articleId = parseInt(checkbox.val());
        const rowData = tableArticlePicker
            ? tableArticlePicker.row(checkbox.closest('tr')).data()
            : null;

        checkbox.prop('checked', checked);

        if (!rowData) {
            return;
        }

        if (checked) {
            selectedArticlePickerItems[articleId] = buildArticlePickerItem(rowData);
        } else {
            delete selectedArticlePickerItems[articleId];
        }
    });

    updateArticlePickerSelectionState();
});

$(document).on('click', '#btnAddSelectedArticles, #btnAddSelectedArticlesFooter', function () {
    const selected = Object.values(selectedArticlePickerItems);

    if (!selected.length) {
        Swal.fire({
            icon: 'warning',
            title: 'Seleccione al menos un art\u00EDculo.'
        });

        return;
    }

    let addedCount = 0;

    selected.forEach(function (article) {
        if (window.addMarketStudyArticleToStudy(article)) {
            addedCount++;
        }
    });

    selectedArticlePickerItems = {};
    $('#checkAllArticlePicker').prop('checked', false);

    if (tableArticlePicker) {
        tableArticlePicker.ajax.reload(null, false);
    }

    updateArticlePickerSelectionState();

    if (!addedCount) {
        Swal.fire({
            icon: 'info',
            title: 'Los art\u00EDculos seleccionados ya fueron agregados.'
        });

        return;
    }

    $('#articlePickerModal').modal('hide');

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Art\u00EDculos agregados correctamente.',
        showConfirmButton: false,
        timer: 2500
    });

});


$('#market_study_documents').on('change', function () {

    let html = '';

    Array.from(this.files).forEach(file => {

        html += `
            <span class="file-chip">
                <i class="fas fa-file mr-1"></i>
                ${file.name}
            </span>
        `;
    });

    $('#selectedFiles').html(html);
});


// =====================================================
// ABRIR MODAL DE COTIZACIONES
// =====================================================
$(document).on('click', '.quoteMarketStudy', function () {

    let studyId = $(this).data('id');
    let code = $(this).data('code');
    let description = $(this).data('description');

    quoteSaved = false;
    resetQuoteDraft();

    $('#market_study_id_quote').val(studyId);

    $('#quote_market_study_info').html(code + ' - ' + description);

    generateQuoteNumber();
    loadSuppliers();
    loadCurrencies();

    $('#marketStudyQuoteModal').modal('show');
});

function generateQuoteNumber() {

    $.ajax({

        url: window.routes.marketStudyQuoteGenerateNumber,
        type: 'GET',

        success: function (response) {

            $('#quote_number').val(
                response.quote_number
            );

        }

    });

}

function loadSuppliers() {

    $.ajax({

        url: window.routes.marketStudyQuoteSuppliers,
        type: 'GET',

        success: function (response) {

            let html =
                '<option value="">Seleccione</option>';

            response.forEach(function (item) {

                html += `
                    <option value="${item.id}">
                        ${item.business_name}
                    </option>
                `;

            });

            $('#supplier_id').html(html);

        }

    });

}
function loadCurrencies() {

    $.ajax({

        url: window.routes.marketStudyQuoteCurrencies,
        type: 'GET',

        success: function (response) {

            let html = '<option value="">Seleccione</option>';

            let defaultCurrencyId = '';

            response.forEach(function (item) {

                html += `
                    <option value="${item.id}">
                        ${item.description} (${item.symbol})
                    </option>
                `;

                // Moneda por defecto
                if (
                    item.description.toUpperCase().includes('SOL') ||
                    item.symbol.toUpperCase() === 'S/' ||
                    item.code === 'PEN'
                ) {
                    defaultCurrencyId = item.id;
                }

            });

            $('#currency_id').html(html);

            // Seleccionar automáticamente Soles
            if (defaultCurrencyId) {

                $('#currency_id')
                    .val(defaultCurrencyId)
                    .trigger('change');

            }

        }

    });

}
$(document).on('change', '#supplier_id', function () {

    let supplierId = $(this).val();

    let supplierText = $(this)
        .find('option:selected')
        .text();

    if (!supplierId) {

        $('#quote_supplier_info').html('—');

        $('#payment_condition').val('');

        return;
    }

    $('#quote_supplier_info').html(
        supplierText
    );

    $.ajax({

        url:
            window.routes.marketStudyQuoteSupplierDetail +
            '/' +
            supplierId,

        type: 'GET',

        success: function (response) {

            $('#payment_condition').val(
                response.payment_condition ?? ''
            );

        }

    });

});

$(document).on('change', '#currency_id', function () {

    let currencyText = $(this)
        .find('option:selected')
        .text();

    if ($(this).val() === '') {

        currencyText = '—';

    }

    $('#quote_currency_info').html(
        currencyText
    );

    loadExchangeRate();
});

function loadExchangeRate() {
    let currencyText = $('#currency_id')
        .find('option:selected')
        .text()
        .toUpperCase();

    if (
        currencyText.includes('SOL') ||
        currencyText.includes('PEN')
    ) {

        $('#exchange_rate').val('1.0000');

        return;
    }

    $.ajax({

        url: window.routes.marketStudyQuoteExchangeRate,

        type: 'GET',

        success: function (response) {

            if (response.success) {

                $('#exchange_rate').val(
                    response.exchange_rate
                );

            }

        },

        error: function () {

            Swal.fire({
                icon: 'warning',
                title: 'Tipo de cambio',
                text: 'No se pudo obtener el tipo de cambio SUNAT.'
            });

        }

    });
}

// =====================================================
// ABRIR MODAL SELECCIONAR ÍTEMS DE COTIZACIÓN
// =====================================================
$(document).on('click', '#btnInsertQuoteItem', function () {

    let studyId = $('#market_study_id_quote').val();

    loadStudyItems(studyId);

    $('#studyQuotePickerModal').modal('show');

});

function loadStudyItems(studyId) {

    let url = window.routes.marketStudyQuoteStudyItems
        .replace(':id', studyId);

    $.ajax({

        url: url,
        type: 'GET',

        success: function (response) {

            let html = '';

            response.forEach(function (item) {

                html += `
                    <tr data-market-study-item-id="${item.id}"
        data-article-id="${item.article_id}">

                        <td>
                            <input
                                type="checkbox"
                                class="quote-item-check"
                                value="${item.id}">
                        </td>

                        <td>${item.id}</td>

                        <td>${item.article_code_snapshot ?? ''}</td>

                        <td>${item.billing_name_snapshot ?? ''}</td>

                        <td>${item.category_snapshot ?? ''}</td>

                        <td>${item.subcategory_snapshot ?? ''}</td>

                        <td>${item.presentation_snapshot ?? ''}</td>

                        <td>${item.cost_condition_snapshot ?? ''}</td>

                    </tr>
                `;
            });

            $('#tableArticlePicker tbody').html(html);

            $('#quoteSelectedCount').text('0');

            $('#checkAllQuoteItems').prop(
                'checked',
                false
            );



        }

    });

}

// =====================================================
// MARCAR / DESMARCAR TODOS
// =====================================================
$(document).on('change', '#checkAllQuoteItems', function () {

    let checked = $(this).is(':checked');

    $('.quote-item-check').prop(
        'checked',
        checked
    );

    updateQuoteSelectedCount();

});

// =====================================================
// CONTADOR DE SELECCIONADOS
// =====================================================
$(document).on('change', '.quote-item-check', function () {

    updateQuoteSelectedCount();

    let total =
        $('.quote-item-check').length;

    let selected =
        $('.quote-item-check:checked').length;

    $('#checkAllQuoteItems').prop(
        'checked',
        total > 0 && total === selected
    );

});

function updateQuoteSelectedCount() {

    let count =
        $('.quote-item-check:checked').length;

    $('#quoteSelectedCount').text(count);

}

$(document).on('click', '#btnAddSelectedQuoteItems', function () {

    let selected = [];

    $('.quote-item-check:checked').each(function () {

        let row = $(this).closest('tr');

        selected.push({
            market_study_item_id: row.data('market-study-item-id'),
            article_id: row.data('article-id'),

            article_code: row.find('td:eq(2)').text().trim(),
            article_name: row.find('td:eq(3)').text().trim(),
            presentation: row.find('td:eq(6)').text().trim(),

            brand_id: '',
            brand_text: '',
            unit_id: '',
            unit_text: '',
            presentation_id: '',

            manufacture_date: '',
            expiration_date: '',
            origin: '',
            sanitary_registration: '',

            tax_type: 'GRAVADA',
            quantity: 1,
            unit_price: 0,
            subtotal: 0,
            tax_amount: 0,
            total: 0,
            observation: '',
            status: 1,

            // NUEVO
            validationError: false
        });

    });

    if (selected.length === 0) {

        Swal.fire({
            icon: 'warning',
            title: 'Seleccione artículos',
            text: 'Debe seleccionar al menos un artículo.'
        });

        return;
    }

    selected.forEach(function (item) {

        let exists = quoteItems.find(x =>
            parseInt(x.article_id) === parseInt(item.article_id)
        );

        if (!exists) {
            quoteItems.push(item);
        }

    });

    renderQuoteItems();

    calculateQuoteSummary();

    $('#studyQuotePickerModal').modal('hide');

    $('#checkAllQuoteItems').prop('checked', false);
    updateQuoteSelectedCount();

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Ítems agregados',
        showConfirmButton: false,
        timer: 2000
    });

});
//funcion para agregar los items seleccionados a la cotización
function renderQuoteItems() {

    let tbody = $('#marketStudyQuoteItemsTbody');

    if (quoteItems.length === 0) {

        tbody.html(`
            <tr>
                <td colspan="8" class="text-center text-muted py-4">
                    No hay ítems agregados aún.
                </td>
            </tr>
        `);

        return;
    }

    let html = '';

    quoteItems.forEach(function (item, index) {

        let subtotal =
            (parseFloat(item.quantity || 0) *
                parseFloat(item.unit_price || 0))
                .toFixed(2);

        // NO SOBRESCRIBIR
        if (!item.total) {
            item.total = subtotal;
        }

        html += `
            <tr data-index="${index}">

                <td>
                    ${index + 1}
                </td>

                <td class="text-left">

                    <div class="font-weight-bold">
                        ${item.article_code}
                    </div>

                    <small class="text-muted">
                        ${item.article_name}
                    </small>

                </td>

                <td>

                    <select
                        class="form-control form-control-sm quote-tax-type"
                        data-index="${index}">

                        <option value="GRAVADA"
                            ${item.tax_type === 'GRAVADA' ? 'selected' : ''}>
                            Gravada
                        </option>

                        <option value="EXONERADA"
                            ${item.tax_type === 'EXONERADA' ? 'selected' : ''}>
                            Exonerada
                        </option>

                        <option value="INAFECTA"
                            ${item.tax_type === 'INAFECTA' ? 'selected' : ''}>
                            Inafecta
                        </option>

                    </select>

                </td>

                <td>

                    <input
                        type="number"
                        min="1"
                        class="form-control form-control-sm text-center quote-qty"
                        data-index="${index}"
                        value="${item.quantity || 1}">

                </td>

                <td>

                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        class="form-control form-control-sm text-right quote-price"
                        data-index="${index}"
                        value="${item.unit_price || 0}">

                </td>

                <td
                    class="font-weight-bold quote-subtotal"
                    data-index="${index}">

                    ${subtotal}

                </td>

                <td>

          ${isQuoteItemComplete(item)
                ?
                `<button type="button"
            class="btn btn-success btn-sm editQuoteItem"
            data-index="${index}"
            title="Configuración completa">
            <i class="fas fa-cog"></i>
        </button>`
                :
                item.validationError
                    ?
                    `<button type="button"
            class="btn btn-warning btn-sm editQuoteItem"
            data-index="${index}"
            title="Debe completar la configuración">
            <i class="fas fa-exclamation-triangle"></i>
        </button>`
                    :
                    `<button type="button"
            class="btn btn-success btn-sm editQuoteItem"
            data-index="${index}"
            title="Configurar ítem">
            <i class="fas fa-cog"></i>
        </button>`
            }

                </td>

                <td>

                    <button
                        type="button"
                        class="btn btn-danger btn-sm removeQuoteItem"
                        data-index="${index}">

                        <i class="fas fa-trash"></i>

                    </button>

                </td>

            </tr>
        `;
    });

    tbody.html(html);
}

//eliminar un item de la cotización

// =====================================================
// ELIMINAR ÍTEM DE LA COTIZACIÓN (MODAL)
// =====================================================
$(document).on('click', '.removeQuoteItem', function () {

    const index = $(this).data('index');

    Swal.fire({
        title: '¿Eliminar ítem?',
        text: 'El artículo será retirado de la cotización.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d'
    }).then((result) => {

        if (result.isConfirmed) {

            quoteItems.splice(index, 1);

            renderQuoteItems();
            calculateQuoteSummary();

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Ítem eliminado',
                showConfirmButton: false,
                timer: 2000
            });
        }

    });

});

$(document).on('click', '.editQuoteItem', function () {




    let index = $(this).data('index');
    let item = quoteItems[index];

    // ===============================
    // LIMPIAR SELECT2 ANTES DE CARGAR
    // ===============================
    $('#detail_brand_id')
        .empty()
        .val(null)
        .trigger('change');

    $('#detail_unit_id')
        .empty()
        .val(null)
        .trigger('change');

    $('#detail_presentation_id')
        .empty()
        .val(null)
        .trigger('change');

    $('#quoteItemIndex').val(index);

    // COMERCIALES
    $('#detail_brand_id').val(item.brand_id || '');
    $('#detail_unit_id').val(item.unit_id || '');
    $('#detail_presentation_id').val(item.presentation_id || '');

    // FECHAS
    $('#detail_manufacture_date').val(
        item.manufacture_date || ''
    );

    $('#detail_expiration_date').val(
        item.expiration_date || ''
    );

    // OTROS
    $('#detail_origin').val(
        item.origin || ''
    );

    $('#detail_sanitary_registration').val(
        item.sanitary_registration || ''
    );

    $('#detail_tax_type').val(
        item.tax_type || 'GRAVADA'
    );

    $('#detail_observation').val(
        item.observation || ''
    );

    $('#detail_subtotal').val(
        item.subtotal || 0
    );

    $('#detail_tax_amount').val(
        item.tax_amount || 0
    );

    $('#detail_total').val(
        item.total || 0
    );

    if (!$('#detail_brand_id').hasClass('select2-hidden-accessible')) {

        initQuoteItemSelects();

    }

    if (item.brand_id && item.brand_text) {

        let option = new Option(
            item.brand_text,
            item.brand_id,
            true,
            true
        );

        $('#detail_brand_id')
            .append(option)
            .trigger('change');
    }

    if (item.unit_id && item.unit_text) {

        let option = new Option(
            item.unit_text,
            item.unit_id,
            true,
            true
        );

        $('#detail_unit_id')
            .append(option)
            .trigger('change');
    }

    if (item.presentation_id && item.presentation_text) {

        let option = new Option(
            item.presentation_text,
            item.presentation_id,
            true,
            true
        );

        $('#detail_presentation_id')
            .append(option)
            .trigger('change');
    }
    $('#quoteItemDetailModal').modal('show');

});


$(document).on('click', '#btnSaveQuoteItemDetail', function () {

    let index = parseInt(
        $('#quoteItemIndex').val()
    );

    if (isNaN(index)) {
        return;
    }

    quoteItems[index].brand_id =
        $('#detail_brand_id').val();
    quoteItems[index].brand_text =
        $('#detail_brand_id option:selected').text();

    quoteItems[index].unit_id =
        $('#detail_unit_id').val();
    quoteItems[index].unit_text =
        $('#detail_unit_id option:selected').text();

    quoteItems[index].presentation_id =
        $('#detail_presentation_id').val();
    quoteItems[index].presentation_text =
        $('#detail_presentation_id option:selected').text();

    quoteItems[index].manufacture_date =
        $('#detail_manufacture_date').val();

    quoteItems[index].expiration_date =
        $('#detail_expiration_date').val();

    quoteItems[index].origin =
        $('#detail_origin').val();

    quoteItems[index].sanitary_registration =
        $('#detail_sanitary_registration').val();

    const taxType = $('#detail_tax_type').val();

    if (taxType) {

        quoteItems[index].tax_type = taxType;

    }

    quoteItems[index].observation =
        $('#detail_observation').val();

    const qty =
        parseFloat(quoteItems[index].quantity || 0);

    const price =
        parseFloat(quoteItems[index].unit_price || 0);

    const grossTotal = qty * price;

    quoteItems[index].subtotal =
        grossTotal.toFixed(3);

    quoteItems[index].total =
        grossTotal.toFixed(3);

    quoteItems[index].tax_amount = 0;

    // Si el ítem ya está completo quitamos el estado de error
    quoteItems[index].validationError = !isQuoteItemComplete(
        quoteItems[index]
    );


    // Refrescamos la tabla para que cambie el icono
    calculateQuoteSummary();

    setTimeout(function () {

        renderQuoteItems();

    }, 50);

    $('#quoteItemDetailModal').modal('hide');

    resetQuoteItemDetailModal();

    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Configuración guardada',
        showConfirmButton: false,
        timer: 2000
    });

});


function initBrandSearch() {

    $('.quote-brand-select').each(function () {

        const $select = $(this);
        const index = parseInt($select.data('index'));

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2({
            theme: 'bootstrap4',
            width: '100%',
            placeholder: 'Buscar marca...',
            allowClear: true,
            ajax: {
                url: window.routes.brandSearch,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                }
            }
        });

        const currentBrandId = $select.data('selected-id');
        const currentBrandText = $select.data('selected-text');

        if (currentBrandId && currentBrandText) {
            const option = new Option(currentBrandText, currentBrandId, true, true);
            $select.append(option).trigger('change');
        }

        $select.off('select2:select').on('select2:select', function (e) {
            quoteItems[index].brand_id = e.params.data.id;
            quoteItems[index].brand_text = e.params.data.text;
        });

        $select.off('select2:clear').on('select2:clear', function () {
            quoteItems[index].brand_id = '';
            quoteItems[index].brand_text = '';
        });

    });
}

function initQuoteItemSelects() {

    // ==========================
    // MARCAS
    // ==========================

    $('#detail_brand_id').select2({

        theme: 'bootstrap4',

        dropdownParent: $('#quoteItemDetailModal'),

        width: '100%',

        placeholder: 'Buscar marca...',

        allowClear: true,

        ajax: {

            url: window.routes.brandSearch,

            dataType: 'json',

            delay: 250,

            data: function (params) {

                return {
                    q: params.term
                };

            },

            processResults: function (data) {

                return {
                    results: data
                };

            }
        }

    });

    // ==========================
    // UNIDADES
    // ==========================

    $('#detail_unit_id').select2({

        theme: 'bootstrap4',

        dropdownParent: $('#quoteItemDetailModal'),

        width: '100%',

        placeholder: 'Buscar unidad...',

        allowClear: true,

        ajax: {

            url: window.routes.unitSearch,

            dataType: 'json',

            delay: 250,

            data: function (params) {

                return {
                    q: params.term
                };

            },

            processResults: function (data) {

                return {
                    results: data
                };

            }

        }

    });

    // ==========================
    // PRESENTACIONES
    // ==========================

    $('#detail_presentation_id').select2({

        theme: 'bootstrap4',

        dropdownParent: $('#quoteItemDetailModal'),

        width: '100%',

        placeholder: 'Buscar presentación...',

        allowClear: true,

        ajax: {

            url: window.routes.presentationSearch,

            dataType: 'json',

            delay: 250,

            data: function (params) {

                return {
                    q: params.term
                };

            },

            processResults: function (data) {

                return {
                    results: data
                };

            }

        }

    });

}

$(document).on('input change', '.quote-qty, .quote-price', function () {

    const index = parseInt($(this).data('index'));
    const qty = parseFloat($(`.quote-qty[data-index="${index}"]`).val() || 0);
    const price = parseFloat($(`.quote-price[data-index="${index}"]`).val() || 0);

    const grossTotal = qty * price;

    quoteItems[index].quantity = qty;
    quoteItems[index].unit_price = price;

    // subtotal visible en la fila = total bruto del ítem
    quoteItems[index].subtotal = grossTotal.toFixed(3);
    quoteItems[index].total = grossTotal.toFixed(3);

    $(`.quote-subtotal[data-index="${index}"]`).text(
        grossTotal.toFixed(2)
    );

    calculateQuoteSummary();

});


$(document).on(
    'change',
    '.quote-tax-type',
    function () {

        const index =
            parseInt($(this).data('index'));

        quoteItems[index].tax_type =
            $(this).val();

        calculateQuoteSummary();

    }
);

/* 
$(document).on('click', '.removeQuoteItem', function () {

    let index = $(this).data('index');

    quoteItems.splice(index, 1);

    renderQuoteItems();

}); */

$('#marketStudyQuoteModal').on('hidden.bs.modal', function () {
    resetQuoteDraft();
    quoteSaved = false;
});

function calculateQuoteSummary() {



    let gravada = 0;
    let exonerada = 0;
    let inafecta = 0;
    let igv = 0;
    let total = 0;

    quoteItems.forEach(function (item) {

        const gross = parseFloat(item.total || item.subtotal || 0) || 0;

        if (item.tax_type === 'GRAVADA') {

            const base = gross / 1.18;
            const tax = gross - base;

            gravada += base;
            igv += tax;
            total += gross;

        } else if (item.tax_type === 'EXONERADA') {

            exonerada += gross;
            total += gross;

        } else if (item.tax_type === 'INAFECTA') {

            inafecta += gross;
            total += gross;

        }

    });

    $('#summary_gravada').text(gravada.toFixed(3));
    $('#summary_exonerada').text(exonerada.toFixed(3));
    $('#summary_inafecta').text(inafecta.toFixed(3));
    $('#summary_igv').text(igv.toFixed(3));
    $('#summary_total').text(total.toFixed(3));
}

$(document).on('submit', '#marketStudyQuoteForm', function (e) {
    e.preventDefault();

    // ======================================
    // VALIDAR QUE EXISTAN ÍTEMS AGREGADOS
    // ======================================
    if (quoteItems.length === 0) {

        Swal.fire({
            icon: 'warning',
            title: 'No hay ítems cotizados',
            text: 'Debe agregar al menos un ítem a la cotización antes de guardar.'
        });

        return;
    }

    // ======================================
    // VALIDAR CONFIGURACIÓN DE ÍTEMS
    // ======================================
    // ======================================
    // VALIDAR CONFIGURACIÓN DE ÍTEMS
    // ======================================

    let itemsIncompletos = [];

    quoteItems.forEach(function (item, index) {

        if (isQuoteItemComplete(item)) {

            item.validationError = false;

        } else {

            item.validationError = true;

            itemsIncompletos.push(
                (index + 1) +
                ' - ' +
                item.article_code +
                ' ' +
                item.article_name
            );
        }

    });

    // IMPORTANTE: refrescar la tabla para actualizar los iconos
    renderQuoteItems();
    calculateQuoteSummary();

    if (itemsIncompletos.length > 0) {

        Swal.fire({
            icon: 'warning',
            title: 'Información incompleta',
            html:
                '<div style="text-align:left;">' +
                '<p>Debe completar la información de configuración en los siguientes ítems:</p>' +
                '<ul style="padding-left:20px;">' +
                itemsIncompletos.map(x => '<li>' + x + '</li>').join('') +
                '</ul>' +
                '<br><b>Complete Marca, Unidad, Presentación, Fechas, Procedencia y Registro Sanitario antes de guardar.</b>' +
                '</div>'
        });

        return;
    }

    if (itemsIncompletos.length > 0) {

        Swal.fire({
            icon: 'warning',
            title: 'Información incompleta',
            html:
                '<div style="text-align:left;">' +
                '<p>Debe completar la información de configuración en los siguientes ítems:</p>' +
                '<ul style="padding-left:20px;">' +
                itemsIncompletos.map(x => '<li>' + x + '</li>').join('') +
                '</ul>' +
                '<br><b>Complete Marca, Unidad, Presentación, Fechas, Procedencia y Registro Sanitario antes de guardar.</b>' +
                '</div>'
        });

        return;
    }

    const btn = $('#btnSaveMarketStudyQuote');
    const quoteId = $('#market_study_quote_id').val();

    const url = quoteId
        ? `${window.routes.marketStudyQuoteUpdate}/${quoteId}`
        : window.routes.marketStudyQuoteStore;

    const formData = new FormData(this);

    formData.append('items_data', JSON.stringify(quoteItems));

    formData.append('gravada', $('#summary_gravada').text() || '0.000');
    formData.append('exonerada', $('#summary_exonerada').text() || '0.000');
    formData.append('inafecta', $('#summary_inafecta').text() || '0.000');
    formData.append('igv', $('#summary_igv').text() || '0.000');
    formData.append('grand_total', $('#summary_total').text() || '0.000');

    if (quoteId) {
        formData.append('_method', 'PUT');
    }

    btn.prop('disabled', true)
        .html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    console.log(window.routes.marketStudyQuoteStore);
    console.log(url);
    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        cache: false,
        success: function (response) {

            btn.prop('disabled', false)
                .html('<i class="fas fa-save mr-1"></i> Guardar Cotización');

            Swal.fire({
                icon: 'success',
                title: response.message || 'Cotización guardada correctamente.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });

            $('#marketStudyQuoteModal').modal('hide');

            tableMarketStudy.ajax.reload(null, false);

            const studyId = $('#studyQuoteListModal')
                .data('market-study-id');

            loadStudyQuotes(studyId);

            resetQuoteDraft();
        },
        error: function (xhr) {
            btn.prop('disabled', false)
                .html('<i class="fas fa-save mr-1"></i> Guardar Cotización');

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo guardar la cotización.'
            });
        }
    });
});

// =====================================================
// ABRIR MODAL DE LISTA DE COTIZACIONES
// =====================================================
$(document).on('click', '.manageQuotes', function () {

    const studyId = $(this).data('id');
    const code = $(this).data('code');
    const description = $(this).data('description');

    $('#studyQuoteCount').text('0');
    $('#tableStudyQuotes tbody').html(`
        <tr>
            <td colspan="8" class="text-center text-muted py-4">
                Cargando cotizaciones...
            </td>
        </tr>
    `);

    $('#studyQuoteListModal')
        .data('market-study-id', studyId)
        .data('market-study-code', code)
        .data('market-study-description', description);

    loadStudyQuotes(studyId);

    $('#studyQuoteListModal').modal('show');

});

function loadStudyQuotes(studyId) {

    const url = window.routes.marketStudyQuoteList
        .replace(':id', studyId);

    $.ajax({

        url: url,
        type: 'GET',

        success: function (response) {

            const data = response.data || [];

            $('#studyQuoteCount').text(data.length);

            if (data.length === 0) {

                $('#tableStudyQuotes tbody').html(`
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Este estudio aún no tiene cotizaciones registradas.
                        </td>
                    </tr>
                `);

                return;
            }

            let html = '';

            data.forEach(function (item, index) {

                html += `
                    <tr>

                        <td>${index + 1}</td>

                        <td>
                            <strong>${item.quote_number ?? ''}</strong>
                        </td>

                        <td>
                            ${item.supplier?.business_name ?? '-'}
                        </td>

                        <td>
                            ${item.currency?.symbol ?? ''}
                        </td>

                        <td>
                            ${parseFloat(item.exchange_rate ?? 0).toFixed(3)}
                        </td>

                        <td>
                            <strong>
                                ${parseFloat(item.grand_total ?? 0).toFixed(2)}
                            </strong>
                        </td>

                        <td>
                            <span class="badge badge-success">
                                ACTIVO
                            </span>
                        </td>

                        <td>

                            <button
                                type="button"
                                class="btn btn-primary btn-sm editStudyQuote"
                                data-id="${item.id}"
                                title="Editar">

                                <i class="fas fa-pen"></i>

                            </button>

                            <button
                                type="button"
                                class="btn btn-danger btn-sm deleteStudyQuote"
                                data-id="${item.id}"
                                title="Eliminar">

                                <i class="fas fa-trash"></i>

                            </button>

                        </td>

                    </tr>
                `;
            });

            $('#tableStudyQuotes tbody').html(html);

        },

        error: function () {

            $('#tableStudyQuotes tbody').html(`
                <tr>
                    <td colspan="8"
                        class="text-center text-danger py-4">
                        Error al cargar las cotizaciones.
                    </td>
                </tr>
            `);

        }

    });

}

$(document).on('click', '#btnNewStudyQuote', function () {

    const studyId = $('#studyQuoteListModal').data('market-study-id');
    const code = $('#studyQuoteListModal').data('market-study-code');
    const description = $('#studyQuoteListModal').data('market-study-description');

    quoteSaved = false;
    resetQuoteDraft();

    $('#market_study_id_quote').val(studyId);
    $('#quote_market_study_info').html(code + ' - ' + description);

    generateQuoteNumber();
    loadSuppliers();
    loadCurrencies();

    $('#marketStudyQuoteModal').modal('show');
});

$(document).on('show.bs.modal', '.modal', function () {

    const zIndex = 1040 + (10 * $('.modal:visible').length);

    $(this).css('z-index', zIndex);

    setTimeout(function () {
        $('.modal-backdrop')
            .not('.modal-stack')
            .css('z-index', zIndex - 1)
            .addClass('modal-stack');
    }, 0);

});

$(document).on('hidden.bs.modal', '.modal', function () {
    if ($('.modal:visible').length) {
        $('body').addClass('modal-open');
    }
});

function isQuoteItemComplete(item) {
    return (
        item.brand_id &&
        item.unit_id &&
        item.presentation_id &&
        item.manufacture_date &&
        item.expiration_date &&
        item.origin &&
        item.sanitary_registration
    );
}

// =====================================================
// EDITAR COTIZACIÓN
// =====================================================
$(document).on('click', '.editStudyQuote', function () {

    const quoteId = $(this).data('id');

    loadQuoteForEdit(quoteId);

});

function loadQuoteForEdit(quoteId) {

    $.ajax({

        url: `${window.routes.marketStudyQuoteUpdate}/${quoteId}/edit`,
        type: 'GET',

        success: function (response) {

            const quote = response.data;

            resetQuoteDraft();

            // IDs
            $('#market_study_quote_id').val(quote.id);
            $('#market_study_id_quote').val(quote.market_study_id);

            // Información lateral
            $('#quote_market_study_info').html(
                quote.market_study.code + ' - ' + quote.market_study.description
            );

            // Cabecera
            $('#quote_number').val(quote.quote_number);

            loadSuppliers();
            loadCurrencies();

            setTimeout(function () {

                $('#supplier_id').val(quote.supplier_id).trigger('change');
                $('#currency_id').val(quote.currency_id).trigger('change');

            }, 300);

            $('#exchange_rate').val(quote.exchange_rate);
            $('#payment_condition').val(quote.payment_condition);
            $('#shipping_cost').val(quote.shipping_cost);
            $('#other_costs').val(quote.other_costs);
            $('#delivery_date').val(quote.delivery_date);
            $('#commercial_conditions').val(
                quote.commercial_conditions
            );
            $('#status').val(quote.status ? '1' : '0');

            // Ítems
            quoteItems = [];

            quote.items.forEach(function (item) {

                quoteItems.push({

                    market_study_item_id: item.market_study_item_id,
                    article_id: item.article_id,

                    article_code: item.article.code,
                    article_name: item.article.billing_name,

                    brand_id: item.brand_id,
                    brand_text: item.brand?.description ?? '',

                    unit_id: item.unit_id,
                    unit_text: item.unit?.description ?? '',

                    presentation_id: item.presentation_id,
                    presentation_text: item.presentation?.description ?? '',

                    manufacture_date: item.manufacture_date,
                    expiration_date: item.expiration_date,
                    origin: item.origin,
                    sanitary_registration: item.sanitary_registration,

                    tax_type: item.tax_type,
                    quantity: item.quantity,
                    unit_price: item.unit_price,
                    subtotal: item.subtotal,
                    tax_amount: item.tax_amount,
                    total: item.total,
                    observation: item.observation,
                    status: item.status,

                    validationError: false

                });

            });

            renderQuoteItems();
            calculateQuoteSummary();

            $('#marketStudyQuoteModal').modal('show');

        },

        error: function (xhr) {

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo cargar la cotización.'
            });

        }

    });


}

// =====================================================
// ELIMINAR COTIZACIÓN
// =====================================================
$(document).on('click', '.deleteStudyQuote', function () {

    const quoteId = $(this).data('id');

    Swal.fire({
        title: '¿Eliminar cotización?',
        text: 'Esta acción eliminará la cotización y todos sus ítems asociados.',
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

            url: `${window.routes.marketStudyQuoteDelete}/${quoteId}`,

            type: 'POST',

            data: {
                _method: 'DELETE'
            },

            success: function (response) {

                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Cotización eliminada correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500
                });

                // Recargar listado de cotizaciones
                const studyId = $('#studyQuoteListModal')
                    .data('market-study-id');

                loadStudyQuotes(studyId);

                // Refrescar la tabla principal
                tableMarketStudy.ajax.reload(null, false);

            },

            error: function (xhr) {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'No se pudo eliminar la cotización.'
                });

            }

        });

    });



});

// =====================================================
// COMPARATIVO DE COTIZACIONES
// =====================================================
// =====================================================
// COMPARATIVO DE COTIZACIONES
// =====================================================
// =====================================================
// COMPARATIVO DE COTIZACIONES
// =====================================================

function getComparisonOffers(item, quotes) {
    const itemId = parseInt(item.id);

    return (quotes || [])
        .map(function (quote) {
            const quoteItem = Array.isArray(quote.items)
                ? quote.items.find(function (qi) {
                    return parseInt(qi.market_study_item_id) === itemId;
                })
                : null;

            if (!quoteItem) {
                return null;
            }

            const unitPrice = parseFloat(quoteItem.unit_price || 0) || 0;
            const total = parseFloat(quoteItem.total || 0) || 0;

            return {
                quote_id: quote.id,
                quote_number: quote.quote_number ?? '',
                supplier_name: quote.supplier?.business_name ?? '-',
                currency_symbol: quote.currency?.symbol ?? '',
                unit_price: unitPrice,
                total: total,
                quote_item_id: quoteItem.id,

                // Datos comerciales
                brand: quoteItem.brand?.description ?? '-',
                unit: quoteItem.unit?.description ?? '-',
                presentation: quoteItem.presentation?.description ?? '-',
                origin: quoteItem.origin ?? '-',
                sanitary_registration: quoteItem.sanitary_registration ?? '-',

                // Fechas
                manufacture_date: quoteItem.manufacture_date ?? '-',
                expiration_date: quoteItem.expiration_date ?? '-',

                // Tributario
                tax_type: quoteItem.tax_type ?? '-',

                // Observación
                observation: quoteItem.observation ?? ''
            };
        })
        .filter(Boolean)
        .sort((a, b) => {
            if (a.unit_price !== b.unit_price) {
                return a.unit_price - b.unit_price;
            }
            return a.total - b.total;
        });
}

function renderComparisonTable(data) {

    const winners = Array.isArray(data.winners)
        ? data.winners
        : [];
    const items = Array.isArray(data.items) ? data.items : [];
    const quotes = Array.isArray(data.quotes) ? data.quotes : [];

    $('#comparisonItemsCount').text(items.length);
    $('#comparisonQuotesCount').text(quotes.length);

    if (!items.length) {
        $('#comparisonEmptyState').show();
        $('#comparisonBody').html(`
            <tr>
                <td colspan="9" class="text-center text-muted py-5">
                    No existen artículos registrados.
                </td>
            </tr>
        `);
        return;
    }

    $('#comparisonEmptyState').hide();

    let html = '';

    items.forEach(function (item, index) {

        const savedWinner = winners.find(function (w) {
            return parseInt(w.market_study_item_id) === parseInt(item.id);
        });
        const offers = getComparisonOffers(item, quotes);
        // Buscar si ya existe un ganador seleccionado previamente
        const selectedRadio = $(
            '#comparisonBody tr[data-item-id="' + item.id + '"] .comparison-winner-radio:checked'
        );

        let winner = null;

        if (savedWinner) {

            winner = offers.find(function (offer) {
                return String(offer.quote_item_id) ===
                    String(savedWinner.market_study_quote_item_id);
            }) || null;

        }

        let winnerHtml = '<span class="badge badge-secondary">Sin ofertas</span>';
        let priceHtml = '-';
        let totalHtml = '-';
        let statusHtml = '<span class="badge badge-warning">Sin adjudicar</span>';

        if (offers.length) {
            winnerHtml = offers.map(function (offer, offerIndex) {
                const checked =
                    savedWinner &&
                    String(savedWinner.market_study_quote_item_id) ===
                    String(offer.quote_item_id);
                const offerId = `winner_${item.id}_${offer.quote_item_id}`;

                return `
                    <label class="d-block mb-2 p-2 border rounded comparison-offer ${checked ? 'border-success bg-light' : ''}">
                        <div class="custom-control custom-radio">
                     <input
    type="radio"
    class="custom-control-input comparison-winner-radio"
    name="winner_${item.id}"
    id="${offerId}"
    data-item-id="${item.id}"
    data-quote-id="${offer.quote_id}"
    data-quote-item-id="${offer.quote_item_id}"
    value="${offer.quote_item_id}"
    ${checked ? 'checked' : ''}
>
                            <label class="custom-control-label w-100" for="${offerId}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="pr-2">
                                        <div class="font-weight-bold text-dark">
                                            ${escapeHtml(offer.supplier_name)}
                                        </div>
                                        <small class="text-muted">
                                            ${escapeHtml(offer.quote_number)}
                                        </small>
                                    </div>

                                    <div class="text-right">
                                        <div class="font-weight-bold text-success">
                                            ${escapeHtml(offer.currency_symbol)} ${offer.unit_price.toFixed(3)}
                                        </div>
                                        <small class="text-muted">
                                            Total: ${escapeHtml(offer.currency_symbol)} ${offer.total.toFixed(3)}
                                        </small>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </label>
                `;
            }).join('');

            priceHtml = winner ? `${escapeHtml(winner.currency_symbol)} ${winner.unit_price.toFixed(3)}` : '-';
            totalHtml = winner ? `${escapeHtml(winner.currency_symbol)} ${winner.total.toFixed(3)}` : '-';
            statusHtml = winner
                ? '<span class="badge badge-success">Adjudicado</span>'
                : '<span class="badge badge-warning">Sin adjudicar</span>';
        }

        html += `
    <tr class="comparison-item-row"
        data-item-id="${item.id}"
        style="cursor:pointer;">
                <td>${index + 1}</td>

                <td class="text-left">
                    <strong>${escapeHtml(item.article_code_snapshot ?? '')}</strong>
                    <br>
                    <small>${escapeHtml(item.billing_name_snapshot ?? '')}</small>
                </td>

                <td>${item.quantity ?? 1}</td>

                <td>${escapeHtml(item.unit_snapshot ?? '-')}</td>

                <td>${escapeHtml(item.presentation_snapshot ?? '-')}</td>

                <td class="comparison-winner-cell">
                    ${winnerHtml}
                </td>

                <td class="comparison-price-cell text-right font-weight-bold">
                    ${priceHtml}
                </td>

                <td class="comparison-total-cell text-right font-weight-bold">
                    ${totalHtml}
                </td>

                <td class="comparison-status-cell">
                    ${statusHtml}
                </td>
            </tr>
        `;
    });

    $('#comparisonBody').html(html);
}

function loadComparison(studyId, code, description) {
    $('#quoteComparisonModal')
        .data('market-study-id', studyId)
        .data('market-study-code', code)
        .data('market-study-description', description);

    $('#comparisonStudyInfo').html(
        '<strong>' + escapeHtml(code) + '</strong> - ' + escapeHtml(description)
    );

    $('#comparisonItemsCount').text('0');
    $('#comparisonQuotesCount').text('0');

    $('#comparisonEmptyState').hide();

    $('#comparisonBody').html(`
        <tr>
            <td colspan="9" class="text-center py-5">
                <i class="fas fa-spinner fa-spin fa-2x text-success mb-3"></i>
                <div class="text-muted mt-2">
                    Cargando comparativo de cotizaciones...
                </div>
            </td>
        </tr>
    `);

    $('#quoteComparisonModal').modal('show');

    const url = window.routes.marketStudyComparisonShow.replace(':id', studyId);

    $.ajax({
        url: url,
        type: 'GET',
        success: function (response) {

            const data = response.data || {};

            data.winners = response.winners || [];

            $('#quoteComparisonModal')
                .data('comparison-data', data);

            renderComparisonTable(data);

        },
        error: function (xhr) {
            $('#comparisonEmptyState').show();

            $('#comparisonBody').html(`
                <tr>
                    <td colspan="9" class="text-center text-danger py-4">
                        Error al cargar el comparativo de cotizaciones.
                    </td>
                </tr>
            `);

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo cargar el comparativo.'
            });
        }
    });
}

$(document).on('click', '.compareQuotes', function () {
    const studyId = $(this).data('id');
    const code = $(this).data('code');
    const description = $(this).data('description');

    loadComparison(studyId, code, description);
});

// =====================================================
// VER DETALLE DEL ARTÍCULO
// =====================================================
$(document).on('click', '.comparison-item-row', function () {

    $('.comparison-item-row')
        .removeClass('table-success');

    $(this).addClass('table-success');

    const itemId = $(this).data('item-id');

    showComparisonArticle(itemId);



});

$(document).on('change', '.comparison-winner-radio', function () {
    const itemId = $(this).data('item-id');
    const quoteItemId = $(this).data('quote-item-id');

    const data = $('#quoteComparisonModal').data('comparison-data') || {};
    const items = Array.isArray(data.items) ? data.items : [];
    const quotes = Array.isArray(data.quotes) ? data.quotes : [];

    const currentItem = items.find(function (item) {
        return parseInt(item.id) === parseInt(itemId);
    });

    if (!currentItem) {
        return;
    }

    const offers = getComparisonOffers(currentItem, quotes);
    const selected = offers.find(function (offer) {
        return String(offer.quote_item_id) === String(quoteItemId);
    });

    const $row = $('#comparisonBody tr[data-item-id="' + itemId + '"]');


    // Limpiar selección visual de todas las ofertas del artículo
    $row.find('.comparison-offer')
        .removeClass('border-success bg-light');

    $('#comparisonArticleDetail .comparison-detail-card')
        .removeClass('selected-winner');

    $('.btnSelectComparisonWinner').html(
        '<i class="fas fa-check-circle mr-1"></i> Seleccionar ganador'
    );
    // Limpiar selección visual de todas las ofertas del artículo
    $row.find('.comparison-offer')
        .removeClass('border-success bg-light');

    $('#comparisonArticleDetail .comparison-detail-card')
        .removeClass('selected-winner');

    $('.btnSelectComparisonWinner').html(
        '<i class="fas fa-check-circle mr-1"></i> Seleccionar ganador'
    );

    $row.find('.comparison-offer').removeClass('border-success bg-light');

    $(this).closest('.comparison-offer').addClass('border-success bg-light');

    $('#comparisonArticleDetail .comparison-detail-card')
        .removeClass('border-success shadow');

    $('#comparisonCard_' + quoteItemId)
        .addClass('selected-winner');

    $('#comparisonCard_' + quoteItemId)
        .find('.btnSelectComparisonWinner')
        .html('<i class="fas fa-trophy mr-1"></i> Proveedor seleccionado');

    if (selected) {
        $row.find('.comparison-price-cell').text(
            selected.currency_symbol + ' ' + selected.unit_price.toFixed(3)
        );

        $row.find('.comparison-total-cell').text(
            selected.currency_symbol + ' ' + selected.total.toFixed(3)
        );

        $row.find('.comparison-status-cell').html(
            '<span class="badge badge-success">Adjudicado</span>'
        );
    }
});

// =====================================================
// SELECCIONAR GANADOR DESDE LA TARJETA
// =====================================================
$(document).on(
    'click',
    '.btnSelectComparisonWinner',
    function () {

        const itemId = $(this).data('item-id');
        const quoteItemId = $(this).data('quote-item-id');

        // Desmarcar todos los radios del artículo
        $('#comparisonBody tr[data-item-id="' + itemId + '"] .comparison-winner-radio')
            .prop('checked', false);

        // Marcar el radio correspondiente
        const $radio = $(
            '#comparisonBody tr[data-item-id="' +
            itemId +
            '"] .comparison-winner-radio[data-quote-item-id="' +
            quoteItemId +
            '"]'
        );

        if (!$radio.length) {
            return;
        }

        $radio.prop('checked', true);

        // Ejecutar la lógica existente
        $radio.trigger('change');

        // Refrescar las tarjetas para reconstruirlas con el ganador
        showComparisonArticle(itemId);

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Proveedor seleccionado',
            showConfirmButton: false,
            timer: 1800
        });

    }
);

$(document).on('click', '#btnRefreshComparison', function () {
    const studyId = $('#quoteComparisonModal').data('market-study-id');
    const code = $('#quoteComparisonModal').data('market-study-code');
    const description = $('#quoteComparisonModal').data('market-study-description');

    if (!studyId) {
        return;
    }

    loadComparison(studyId, code, description);
});

$(document).on('click', '#btnSaveComparison', function () {
    const data = $('#quoteComparisonModal').data('comparison-data') || {};
    const items = Array.isArray(data.items) ? data.items : [];
    const quotes = Array.isArray(data.quotes) ? data.quotes : [];

    const selections = [];

    items.forEach(function (item) {
        const checked = $('#comparisonBody tr[data-item-id="' + item.id + '"] .comparison-winner-radio:checked');

        if (!checked.length) {
            return;
        }

        const quoteItemId = checked.data('quote-item-id');
        const offers = getComparisonOffers(item, quotes);

        const selected = offers.find(function (offer) {
            return String(offer.quote_item_id) === String(quoteItemId);
        });

        if (selected) {
            selections.push({
                market_study_item_id: item.id,
                quote_item_id: selected.quote_item_id,
                quote_id: selected.quote_id,
                supplier_name: selected.supplier_name,
                unit_price: selected.unit_price,
                total: selected.total
            });
        }
    });

    if (!selections.length) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin selección',
            text: 'Debe elegir al menos un ganador por artículo.'
        });
        return;
    }

    const studyId = $('#quoteComparisonModal')
        .data('market-study-id');

    $.ajax({

        url: window.routes.marketStudyComparisonSave
            .replace(':id', studyId),

        type: 'POST',

        data: {
            selections: selections
        },

        success: function (response) {

            Swal.fire({
                icon: 'success',
                title: 'Correcto',
                text: response.message
            });

        },

        error: function (xhr) {

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message
                    || 'No se pudo guardar el comparativo.'
            });

        }

    });
});

$('#quoteComparisonModal').on('hidden.bs.modal', function () {
    $('#comparisonEmptyState').show();
    $('#comparisonItemsCount').text('0');
    $('#comparisonQuotesCount').text('0');
    $('#comparisonStudyInfo').html('—');

    $('#comparisonBody').html(`
        <tr>
            <td colspan="9" class="text-center text-muted py-5">
                Seleccione un estudio para visualizar el comparativo.
            </td>
        </tr>
    `);
});

//FUNCION QUE CARGARA EL DETTALLE ARRIBA
function showComparisonArticle(itemId) {

    const data = $('#quoteComparisonModal')
        .data('comparison-data') || {};

    const items = data.items || [];
    const quotes = data.quotes || [];

    const item = items.find(x =>
        parseInt(x.id) === parseInt(itemId)
    );

    if (!item) {
        return;
    }

    const offers = getComparisonOffers(item, quotes);

    let html = `
        <div class="comparison-title-box">
            <div class="font-weight-bold text-success">
                ${escapeHtml(item.article_code_snapshot || '')}
            </div>
            <div class="text-muted">
                ${escapeHtml(item.billing_name_snapshot || '')}
            </div>
        </div>

        <div class="comparison-cards-container">
    `;

    if (offers.length === 0) {

        html += `
            <div class="w-100 text-center text-muted py-4">
                No existen cotizaciones para este artículo.
            </div>
        `;

    } else {


        // Buscar si ya existe un ganador seleccionado para este artículo
        const selectedRadio = $(
            '#comparisonBody tr[data-item-id="' + itemId + '"] .comparison-winner-radio:checked'
        );

        const selectedQuoteItemId = selectedRadio.length
            ? String(selectedRadio.data('quote-item-id'))
            : null;
        offers.forEach(function (offer) {

            const isWinner =
                selectedQuoteItemId &&
                String(offer.quote_item_id) === selectedQuoteItemId;

            html += `
        <div class="card comparison-detail-card ${isWinner ? 'selected-winner' : ''}"
             id="comparisonCard_${offer.quote_item_id}"
             data-quote-item-id="${offer.quote_item_id}">
                    <div class="card-header d-flex justify-content-between align-items-center">

                        <div>
                            <strong>
                                ${escapeHtml(offer.supplier_name)}
                            </strong>
                            <br>
                            <small class="text-muted">
                                ${escapeHtml(offer.quote_number)}
                            </small>
                        </div>

                        <div class="text-right">
                            <div class="font-weight-bold text-success">
                                ${offer.currency_symbol}
                                ${offer.unit_price.toFixed(3)}
                            </div>
                            <small class="text-muted">
                                Total:
                                ${offer.currency_symbol}
                                ${offer.total.toFixed(3)}
                            </small>
                        </div>

                    </div>

                    <div class="card-body">

                        <div class="row">

                            <div class="col-6 mb-2">
                                <label>Marca</label><br>
                                <strong>${offer.brand}</strong>
                            </div>

                            <div class="col-6 mb-2">
                                <label>Unidad</label><br>
                                <strong>${offer.unit}</strong>
                            </div>

                            <div class="col-6 mb-2">
                                <label>Presentación</label><br>
                                <strong>${offer.presentation}</strong>
                            </div>

                            <div class="col-6 mb-2">
                                <label>IGV</label><br>
                                <strong>${offer.tax_type}</strong>
                            </div>

                            <div class="col-6 mb-2">
                                <label>Procedencia</label><br>
                                <strong>${offer.origin}</strong>
                            </div>

                            <div class="col-6 mb-2">
                                <label>Registro Sanitario</label><br>
                                <strong>${offer.sanitary_registration}</strong>
                            </div>

                            <div class="col-6 mb-2">
                                <label>F. Fabricación</label><br>
                               <strong>${formatComparisonDate(offer.manufacture_date)}</strong>
                            </div>

                            <div class="col-6 mb-2">
                                <label>F. Vencimiento</label><br>
                                <strong>${formatComparisonDate(offer.expiration_date)}</strong>
                            </div>

                        </div>

                       <button
    type="button"
    class="btn btn-success btnSelectComparisonWinner"
    data-item-id="${item.id}"
    data-quote-item-id="${offer.quote_item_id}">

    ${isWinner
                    ? '<i class="fas fa-trophy mr-1"></i> Proveedor seleccionado'
                    : '<i class="fas fa-check-circle mr-1"></i> Seleccionar ganador'
                }

</button>

                    </div>

                </div>

            `;
        });

    }

    html += `
        </div>
    `;

    $('#comparisonEmptyState').hide();
    $('#comparisonArticleDetail')
        .html(html)
        .show();
}
// =====================================================
// LIMPIAR MODAL AL CERRAR
// =====================================================
$('#quoteComparisonModal').on('hidden.bs.modal', function () {

    $('#comparisonEmptyState').show();

    $('#comparisonItemsCount').text('0');
    $('#comparisonQuotesCount').text('0');

    $('#comparisonStudyInfo').html('—');

    $('#comparisonBody').html(`
        <tr>
            <td colspan="9" class="text-center text-muted py-5">
                Seleccione un estudio para visualizar el comparativo.
            </td>
        </tr>
    `);

    $('#comparisonArticleDetail')
        .hide()
        .html('');

    $('#comparisonEmptyState').show();
});


function formatComparisonDate(date) {

    if (!date || date === '-') {
        return '-';
    }

    // Solo tomar la parte de la fecha
    const onlyDate = String(date).substring(0, 10); // 2026-06-14

    const parts = onlyDate.split('-');

    if (parts.length !== 3) {
        return date;
    }

    return parts[2] + '-' + parts[1] + '-' + parts[0];
}


function resetQuoteItemDetailModal() {

    $('#quoteItemIndex').val('');

    // Select2
    $('#detail_brand_id')
        .empty()
        .val(null)
        .trigger('change');

    $('#detail_unit_id')
        .empty()
        .val(null)
        .trigger('change');

    $('#detail_presentation_id')
        .empty()
        .val(null)
        .trigger('change');

    // Inputs
    $('#detail_manufacture_date').val('');
    $('#detail_expiration_date').val('');
    $('#detail_origin').val('');
    $('#detail_sanitary_registration').val('');
    $('#detail_observation').val('');

}


// =====================================================
// VER ESTUDIO
// =====================================================
$(document).on('click', '.viewMarketStudy', function () {

    let id = $(this).data('id');

    $.ajax({

        url: window.routes.marketStudyShow + '/' + id,

        type: 'GET',

        success: function (response) {

            console.log(response.data);
            loadMarketStudyView(response.data);

            $('#viewMarketStudyModal').modal('show');

        }

    });

});

function loadMarketStudyView(data) {

    // ==========================
    // DATOS GENERALES
    // ==========================
    $('#view_code').text(data.code ?? '-');
    $('#view_description').text(data.description ?? '-');
    $('#view_terms').text(data.reference_terms ?? '-');

    // ==========================
    // RESUMEN
    // ==========================
    $('#view_total_items').text(data.items?.length ?? 0);
    $('#view_total_quotes').text(data.quotes?.length ?? 0);

    const suppliers =
        [...new Set(
            (data.quotes || [])
                .map(q => q.supplier?.id)
        )];

    $('#view_total_suppliers').text(
        suppliers.filter(Boolean).length
    );

    $('#view_total_winners').text(
        data.winners?.length ?? 0
    );

    // ==========================
    // PROVEEDORES
    // ==========================
    let suppliersHtml = '';

    (data.quotes || []).forEach(function (quote) {

        suppliersHtml += `
            <tr>
                <td>${quote.supplier?.business_name ?? '-'}</td>
                <td>${quote.currency?.description ?? '-'}</td>
                <td>${quote.payment_condition ?? '-'}</td>
                <td>
                    <span class="badge badge-success">
                        ACTIVO
                    </span>
                </td>
            </tr>
        `;
    });

    $('#viewSuppliersBody').html(
        suppliersHtml ||
        '<tr><td colspan="4" class="text-center">Sin registros</td></tr>'
    );

    // ==========================
    // GANADORES
    // ==========================
    let comparisonHtml = '';

    (data.items || []).forEach(function (item) {

        let winner = null;

        if (data.winners) {

            winner = data.winners.find(
                x => parseInt(x.market_study_item_id) === parseInt(item.id)
            );

        }

        const winnerItem =
            winner?.quote_item ??
            winner?.quoteItem ??
            null;

        comparisonHtml += `
        <tr>

            <td>
                ${item.billing_name_snapshot ?? ''}
            </td>

            <td>
                ${winnerItem?.quote?.supplier?.business_name ?? 'SIN GANADOR'}
            </td>

            <td>
                ${winnerItem?.brand?.description ?? '-'}
            </td>

            <td>
                ${winnerItem?.presentation?.description ?? '-'}
            </td>

            <td>
                ${winnerItem?.quantity ?? '-'}
            </td>

            <td>
                ${winnerItem?.unit_price ?? '-'}
            </td>

            <td>
                ${winnerItem?.total ?? '-'}
            </td>

        </tr>
    `;
    });

    $('#viewComparisonBody').html(
        comparisonHtml ||
        '<tr><td colspan="7" class="text-center">Sin datos</td></tr>'
    );

    // ==========================
    // SANITARIO
    // ==========================
    $('#viewSanitaryContainer').html(
        '<div class="alert alert-info mb-0">Información sanitaria pendiente.</div>'
    );

    // ==========================
    // RESUMEN ECONÓMICO
    // ==========================
    const economicSummary =
        data.economic_summary ||
        calculateWinnerEconomicSummary(data.winners || []);

    const totalGravada =
        parseFloat(economicSummary.gravada || 0);

    const totalInafecta =
        parseFloat(economicSummary.inafecta || 0);

    const totalExonerada =
        parseFloat(economicSummary.exonerada || 0);

    const totalIgv =
        parseFloat(economicSummary.igv || 0);

    const total =
        parseFloat(economicSummary.total || economicSummary.grand_total || 0);

    $('#view_gravada').text(
        'S/ ' + totalGravada.toFixed(2)
    );

    $('#view_inafecta').text(
        'S/ ' + totalInafecta.toFixed(2)
    );

    $('#view_exonerada').text(
        'S/ ' + totalExonerada.toFixed(2)
    );

    $('#view_igv').text(
        'S/ ' + totalIgv.toFixed(2)
    );

    $('#view_total').text(
        'S/ ' + total.toFixed(2)
    );
}

function calculateWinnerEconomicSummary(winners) {
    let gravada = 0;
    let exonerada = 0;
    let inafecta = 0;
    let igv = 0;
    let total = 0;

    (winners || []).forEach(function (winner) {
        const quoteItem =
            winner?.quote_item ??
            winner?.quoteItem ??
            null;

        if (!quoteItem) {
            return;
        }

        const quantity =
            parseFloat(quoteItem.quantity || 0) || 0;

        const unitPrice =
            parseFloat(quoteItem.unit_price || 0) || 0;

        let lineTotal =
            Math.round(quantity * unitPrice * 100) / 100;

        if (lineTotal <= 0) {
            lineTotal =
                parseFloat(quoteItem.total || 0) || 0;
        }

        const taxType =
            String(quoteItem.tax_type || 'GRAVADA').toUpperCase();

        if (taxType === 'GRAVADA') {
            const tax =
                Math.round(lineTotal * 0.18 * 100) / 100;

            gravada += lineTotal;
            igv += tax;
            total += lineTotal + tax;
        } else if (taxType === 'EXONERADA') {
            exonerada += lineTotal;
            total += lineTotal;
        } else {
            inafecta += lineTotal;
            total += lineTotal;
        }
    });

    return {
        gravada: Math.round(gravada * 100) / 100,
        exonerada: Math.round(exonerada * 100) / 100,
        inafecta: Math.round(inafecta * 100) / 100,
        igv: Math.round(igv * 100) / 100,
        total: Math.round(total * 100) / 100
    };
}

