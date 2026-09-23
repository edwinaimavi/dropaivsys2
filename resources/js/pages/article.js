let tableArticle;


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

    $(document).on(
        'submit',
        '#articleForm',
        function (e) {

            e.preventDefault();

            clearArticleValidationErrors();

            let formData =
                new FormData();

            formData.append(
                'code',
                $('#code').val()
            );

            formData.append(
                'code_mode',
                $('#code_mode').val()
            );

            formData.append(
                'code_type',
                $('#code_type').val()
            );

            formData.append(
                'institutional_code',
                $('#institutional_code').val()
            );

            formData.append(
                'category_id',
                $('#category_id').val()
            );

            formData.append(
                'subcategory_id',
                $('#subcategory_id').val()
            );

            formData.append(
                'presentation_id',
                $('#presentation_id').val()
            );

            formData.append(
                'unit_id',
                $('#unit_id').val()
            );

            formData.append(
                'legal_name',
                $('#legal_name').val()
            );

            formData.append(
                'commercial_name',
                $('#commercial_name').val()
            );

            formData.append(
                'billing_name',
                $('#billing_name').val()
            );

            formData.append('item_kind', $('#item_kind').val());
            formData.append('is_inventory_item', $('#is_inventory_item').val());
            formData.append('sunat_existence_type_item_id', $('#sunat_existence_type_item_id').val());
            formData.append('sunat_inventory_catalog_item_id', $('#sunat_inventory_catalog_item_id').val());
            formData.append('sunat_inventory_catalog_code', $('#sunat_inventory_catalog_code').val());
            formData.append('sunat_inventory_catalog_use_internal_code', $('#sunat_inventory_catalog_use_internal_code').val() || '0');
            formData.append('sunat_standard_catalog_item_id', $('#sunat_standard_catalog_item_id').val());
            formData.append('sunat_standard_code', $('#sunat_standard_code').val());

            formData.append(
                'has_batch',
                $('#has_batch').val()
            );

            formData.append(
                'has_expiration',
                $('#has_expiration').val()
            );

            formData.append(
                'status',
                $('#status').val()
            );

            formData.append(
                'observation',
                $('#observation').val()
            );

            let cleanDocuments =
                documents.filter(
                    item => item !== null
                );

            formData.append(
                'documents_data',
                JSON.stringify(cleanDocuments.map(d => ({

                    id:
                        d.id || null,

                    document_type_id:
                        d.document_type_id,

                    brand_id:
                        d.brand_id || null,

                    issue_date:
                        d.issue_date || null,

                    expiration_date:
                        d.expiration_date || null,

                    observation:
                        d.observation || null

                })))
            );
            formData.append(
                'deleted_images',
                JSON.stringify(deletedImages)
            );
            formData.append(
                'deleted_documents',
                JSON.stringify(deletedDocuments)
            );

            /*
  |--------------------------------------------------------------------------
  | IMAGENES
  |--------------------------------------------------------------------------
  */
            images.forEach(function (image, index) {

                if (image.file) {

                    formData.append(
                        'images[' + index + ']',
                        image.file
                    );

                }

            });


            /*
            |--------------------------------------------------------------------------
            | DOCUMENTOS
            |--------------------------------------------------------------------------
            */
            cleanDocuments.forEach(function (doc, index) {

                if (doc.file) {

                    formData.append(
                        'documents_files[' + index + ']',
                        doc.file
                    );

                }

            });

            let articleId =
                $('#article_id').val();

            let url =
                articleId
                    ? window.routes.updateArticle +
                    '/' +
                    articleId
                    : window.routes.storeArticle;

            if (articleId) {

                formData.append(
                    '_method',
                    'PUT'
                );

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

                        title: response.message,

                        toast: true,

                        position: 'top-end',

                        showConfirmButton: false,

                        timer: 3000

                    });

                    $('#articleModal').modal('hide');

                    tableArticle.ajax.reload(null, false);

                    documents = [];

                    $('#documentsTableBody').html(`
            <tr>
                <td colspan="6" class="text-center text-muted">
                    No hay documentos agregados
                </td>
            </tr>
        `);

                },

                error: function (xhr) {

                    if (xhr.status === 422) {

                        const errors =
                            xhr.responseJSON?.errors || {};

                        let hasFieldError = false;

                        $.each(errors, function (key, messages) {

                            const input = $('#' + key);
                            const feedback = $('#' + key + '-error');

                            if (!input.length || !feedback.length) {
                                return;
                            }

                            input.addClass('is-invalid');

                            feedback.text(messages[0]);

                            hasFieldError = true;

                        });

                        if (!hasFieldError) {

                            Swal.fire({

                                icon: 'error',

                                title: 'Error',

                                text:
                                    Object.values(errors)[0]?.[0]
                                    || xhr.responseJSON?.message
                                    || 'Error al guardar'

                            });

                        }

                        return;
                    }

                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text:
                            xhr.responseJSON?.message
                            || 'Error al guardar'

                    });

                }

            });
        }
    );

    // =========================================================
    // LIMPIAR MODAL
    // =========================================================

    $('#articleModal').on('hidden.bs.modal', function () {

        if (articleCodeRequest) {
            articleCodeRequest.abort();
            articleCodeRequest = null;
        }

        $('#articleForm')[0].reset();

        $('#item_kind, #is_inventory_item').val('');
        $('#sunat_existence_type_item_id').val(null).trigger('change');
        $('#sunat_inventory_catalog_item_id, #sunat_standard_catalog_item_id').val(null).trigger('change');
        $('#sunat_inventory_catalog_code, #sunat_standard_code').val('');
        $('#sunat_inventory_catalog_use_internal_code').val('0');
        $('#articleSunatAdvancedCollapse').collapse('hide');
        syncInventoryClassification();

        $('#article_id').val('');

        $('#code').val('');

        $('#code_mode').val('automatic');

        clearArticleValidationErrors();

        /*
        |--------------------------------------------------------------------------
        | LIMPIAR ARRAYS
        |--------------------------------------------------------------------------
        */
        documents = [];
        images = [];

        deletedImages = [];
        deletedDocuments = [];

        /*
        |--------------------------------------------------------------------------
        | LIMPIAR INPUT FILE
        |--------------------------------------------------------------------------
        */
        $('#articleImageInput').val('');

        /*
        |--------------------------------------------------------------------------
        | RESTAURAR CONTENEDOR DE IMAGENES
        |--------------------------------------------------------------------------
        */
        $('#articleImagesContainer').html(`
        <div class="text-center text-muted py-5">

            <i class="fas fa-images fa-3x mb-2"></i>

            <div>
                No hay imágenes agregadas
            </div>

        </div>
    `);

        /*
        |--------------------------------------------------------------------------
        | RESTAURAR TABLA DOCUMENTOS
        |--------------------------------------------------------------------------
        */
        $('#documentsTableBody').html(`
        <tr>
            <td colspan="6" class="text-center text-muted">
                No hay documentos agregados
            </td>
        </tr>
    `);

        /*
        |--------------------------------------------------------------------------
        | TITULO MODAL
        |--------------------------------------------------------------------------
        */
        $('#articleModalLabel').text(
            'Registrar Artículo'
        );

    });

    function clearArticleValidationErrors() {

        $('#articleForm')
            .find('.is-invalid')
            .removeClass('is-invalid');

        $('#articleForm')
            .find('.invalid-feedback')
            .text('');

    }

    function syncInventoryClassification() {
        const service = $('#item_kind').val() === 'service';
        const inventorySelect = $('#is_inventory_item');

        if (service) {
            inventorySelect.val('0').prop('disabled', true);
            $('#inventoryClassificationHelp').text('Los servicios no participan en stock ni generan Kardex.');
        } else {
            inventorySelect.prop('disabled', false);
            $('#inventoryClassificationHelp').text('Defina expresamente si el producto participa en inventario.');
        }

        const requiresSunatType = !service
            && $('#item_kind').val() === 'product'
            && inventorySelect.val() === '1';
        const sunatType = $('#sunat_existence_type_item_id');
        const mainCatalog = $('#sunat_inventory_catalog_item_id');
        const mainCode = $('#sunat_inventory_catalog_code');
        const standardCatalog = $('#sunat_standard_catalog_item_id');
        const standardCode = $('#sunat_standard_code');

        $('#sunatExistenceTypeGroup').toggleClass('d-none', !requiresSunatType);
        sunatType.prop('disabled', !requiresSunatType).prop('required', requiresSunatType);
        $('#sunatInventoryIdentificationGroup').toggleClass('d-none', !requiresSunatType);
        mainCatalog.prop('disabled', !requiresSunatType).prop('required', requiresSunatType);
        mainCode.prop('disabled', !requiresSunatType).prop('required', requiresSunatType);
        standardCatalog.prop('disabled', !requiresSunatType);
        standardCode.prop('disabled', !requiresSunatType);
        $('#useInternalArticleCode').prop('disabled', !requiresSunatType);

        if (!requiresSunatType) {
            sunatType.val(null).trigger('change');
            mainCatalog.val(null).trigger('change.select2');
            mainCode.val('');
            standardCatalog.val(null).trigger('change.select2');
            standardCode.val('');
            $('#sunat_inventory_catalog_use_internal_code').val('0');
        } else {
            // Para el uso normal del sistema se propone automáticamente
            // "9 — OTROS" + código interno. El usuario puede abrir la
            // configuración SUNAT avanzada y cambiarlo si realmente utiliza
            // un catálogo estándar.
            if (!mainCatalog.val()) {
                const ownCatalogOption = mainCatalog.find('option').filter(function () {
                    return String($(this).data('item-code')) === '9';
                }).first();

                if (ownCatalogOption.length) {
                    mainCatalog.val(ownCatalogOption.val()).trigger('change.select2');
                }
            }

            const selectedCode = String(mainCatalog.find(':selected').data('item-code') || '');
            if (selectedCode === '9' && !$.trim(mainCode.val())) {
                mainCode.val($.trim($('#code').val()));
                $('#sunat_inventory_catalog_use_internal_code').val('1');
            }
        }

        const isOther = mainCatalog.find(':selected').data('item-code') === 9
            || String(mainCatalog.find(':selected').data('item-code')) === '9';
        $('#sunatInventoryOwnCodeHelp').toggleClass('d-none', !requiresSunatType || !isOther);
    }

    $(document).on('change', '#item_kind, #is_inventory_item, #sunat_inventory_catalog_item_id', syncInventoryClassification);

    $(document).on('input', '#sunat_inventory_catalog_code', function () {
        $('#sunat_inventory_catalog_use_internal_code').val('0');
    });

    $(document).on('input', '#code', function () {
        if ($('#sunat_inventory_catalog_use_internal_code').val() === '1') {
            $('#sunat_inventory_catalog_code').val($.trim($(this).val()));
        }
    });

    $(document).on('click', '#useInternalArticleCode', function () {
        $('#sunat_inventory_catalog_code').val($.trim($('#code').val())).trigger('input');
        $('#sunat_inventory_catalog_use_internal_code').val('1');
    });

    if ($.fn.select2) {
        $('#sunat_existence_type_item_id').select2({
            dropdownParent: $('#articleModal'),
            width: '100%',
            placeholder: 'Buscar por código o descripción',
            allowClear: true
        });
        $('#sunat_inventory_catalog_item_id, #sunat_standard_catalog_item_id').select2({
            dropdownParent: $('#articleModal'),
            width: '100%',
            placeholder: 'Buscar por código o descripción',
            allowClear: true
        });
    }



    // =========================================================
    // DATATABLE
    // =========================================================

    tableArticle = $('#tableArticle').DataTable({

        processing: true,

        serverSide: true,

        ajax: window.routes.articleList,

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
                data: 'code_type',
                name: 'code_type',
                defaultContent: '-',
                render: function (data, type) {
                    if (type !== 'display') {
                        return data || '';
                    }

                    const value = $('<div>').text(data || '-').html();
                    return `<span class="badge badge-light border text-secondary px-2 py-1">${value}</span>`;
                }
            },

            {
                data: 'institutional_code',
                name: 'institutional_code',
                defaultContent: '-',
                render: function (data, type) {
                    if (type !== 'display') {
                        return data || '';
                    }

                    const value = $('<div>').text(data || '-').html();
                    return `<span class="badge badge-info px-2 py-1">${value}</span>`;
                }
            },

            {
                data: 'brand',
                name: 'brand.description'
            },

            {
                data: 'legal_name',
                name: 'legal_name'
            },

            {
                data: 'commercial_name',
                name: 'commercial_name'
            },

            {
                data: 'inventory_classification',
                name: 'inventory_classification',
                orderable: false,
                searchable: false
            },

            {
                data: 'status',
                name: 'status'
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

            divLoading && divLoading.classList.remove('d-none');

        },

        drawCallback: function () {

            divLoading && divLoading.classList.add('d-none');

        }

    });



    $(document).on(
        'change',
        '#category_id',
        function () {

            let categoryId = $(this).val();

            $('#subcategory_id').html(`
            <option value="">
                Cargando...
            </option>
        `);

            if (!categoryId) {

                $('#subcategory_id').html(`
                <option value="">
                    Seleccione
                </option>
            `);

                return;
            }

            let url =
                window.routes.subcategoriesByCategory
                    .replace(':id', categoryId);

            $.get(url, function (response) {

                let options = `
                <option value="">
                    Seleccione
                </option>
            `;

                response.forEach(function (item) {

                    options += `
                    <option value="${item.id}">
                        ${item.description}
                    </option>
                `;

                });

                $('#subcategory_id').html(options);

            });

        }
    );


    let articleCodeRequest = null;

    //FUNCION PARA GENERAR CODIGO DE ARTICULO
    function generateArticleCode() {

        if (articleCodeRequest) {
            articleCodeRequest.abort();
        }

        $('#code').val('');

        articleCodeRequest = $.ajax({

            url: window.routes.generateArticleCode,

            type: 'GET',

            cache: false,

            success: function (response) {

                $('#code').val(response.code).trigger('input');

            },

            error: function (xhr) {

                if (xhr.statusText === 'abort') {
                    return;
                }

                console.error(
                    'Error al generar código'
                );

            },

            complete: function () {
                articleCodeRequest = null;
            }

        });



    }

    $('#btnCreateArticle').on('click', function () {

        images = [];
        documents = [];

        deletedImages = [];
        deletedDocuments = [];

        $('#articleImagesContainer').html(`
    <div class="text-center text-muted py-5">
        <i class="fas fa-images fa-3x mb-2"></i>
        <div>No hay imágenes agregadas</div>
    </div>
`);

        $('#documentsTableBody').html(`
    <tr>
        <td colspan="6" class="text-center text-muted">
            No hay documentos agregados
        </td>
    </tr>
`);

        $('#articleForm')[0].reset();

        $('#item_kind, #is_inventory_item').val('');
        $('#sunat_existence_type_item_id').val(null).trigger('change');
        syncInventoryClassification();

        $('#article_id').val('');

        $('#code_mode').val('automatic');

        generateArticleCode();

    });


    let documents = [];
    let images = [];
    let editingDocumentIndex = null;

    const renderArticleDocuments = () => {
        const activeDocuments = documents.map((document, index) => ({ document, index })).filter(item => item.document !== null);
        if (!activeDocuments.length) {
            $('#documentsTableBody').html('<tr><td colspan="6" class="text-center text-muted">No hay documentos agregados</td></tr>');
            return;
        }
        $('#documentsTableBody').html(activeDocuments.map(({ document, index }) => `
            <tr data-index="${index}">
                <td>${$('<div>').text(document.document_type || '-').html()}</td>
                <td>${$('<div>').text(document.brand || 'Sin marca').html()}</td>
                <td>${$('<div>').text(document.file?.name || document.original_name || '-').html()}</td>
                <td>${$('<div>').text(document.issue_date || '-').html()}</td>
                <td>${$('<div>').text(document.expiration_date || '-').html()}</td>
                <td><div class="article-document-actions">
                    <button type="button" class="btn btn-info btn-sm editArticleDocument" title="Editar documento"><i class="fas fa-pencil-alt"></i></button>
                    <button type="button" class="btn btn-danger btn-sm ${document.id ? 'removeExistingDocument' : 'removeDocument'}" ${document.id ? `data-id="${document.id}"` : ''} title="Eliminar documento"><i class="fas fa-trash"></i></button>
                </div></td>
            </tr>`).join(''));
    };

    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL DOCUMENTO
    |--------------------------------------------------------------------------
    */
    $(document).on(
        'click',
        '#btnAddDocument',
        function () {

            $('#documentForm')[0].reset();
            editingDocumentIndex = null;
            $('#documentModalTitle').text('Agregar Documento');
            $('#documentSaveText').text('Agregar');
            $('#selectedFileName').text('Ningún archivo seleccionado');
            $('#documentFileHelp').text('PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX.');

            if ($.fn.select2) {
                const brandSelect = $('#document_brand_id');

                if (!brandSelect.hasClass('select2-hidden-accessible')) {
                    brandSelect.select2({
                        dropdownParent: $('#documentModal'),
                        theme: 'bootstrap4',
                        width: '100%',
                        placeholder: 'Seleccione marca',
                        allowClear: true
                    });
                }

                brandSelect.val('').trigger('change');
            }

            $('#documentModal').modal('show');

        }
    );

    /*
    |--------------------------------------------------------------------------
    | AGREGAR DOCUMENTO A LA TABLA
    |--------------------------------------------------------------------------
    */
    $(document).on(
        'click',
        '#btnSaveDocument',
        function () {

            let documentTypeId =
                $('#document_type_id').val();

            let documentTypeText =
                $('#document_type_id option:selected').text();

            let documentBrandId =
                $('#document_brand_id').val();

            let documentBrandText = documentBrandId
                ? $('#document_brand_id option:selected').text()
                : 'Sin marca';

            let issueDate =
                $('#issue_date').val();

            let expirationDate =
                $('#expiration_date').val();

            if (issueDate && expirationDate && expirationDate < issueDate) {
                Swal.fire('Fechas no válidas', 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.', 'warning');
                return;
            }

            let observation =
                $('#document_observation').val();

            let fileInput =
                $('#document_file')[0];

            if (!documentTypeId) {

                Swal.fire(
                    'Atención',
                    'Seleccione tipo de documento',
                    'warning'
                );

                return;
            }

            const currentDocument = editingDocumentIndex === null ? null : documents[editingDocumentIndex];
            if ((!fileInput.files || !fileInput.files.length) && !currentDocument?.original_name && !currentDocument?.file) {

                Swal.fire(
                    'Atención',
                    'Seleccione un archivo',
                    'warning'
                );

                return;
            }

            let file = fileInput.files?.[0] || null;
            const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx'];
            if (file && !allowedExtensions.includes((file.name.split('.').pop() || '').toLowerCase())) {
                Swal.fire('Archivo no permitido', 'Use PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX.', 'warning');
                return;
            }

            const documentData = {
                id: currentDocument?.id || null,

                document_type_id:
                    documentTypeId,

                document_type:
                    documentTypeText,

                brand_id:
                    documentBrandId || null,

                brand:
                    documentBrandText,

                file: file || currentDocument?.file || null,
                original_name: file?.name || currentDocument?.original_name || null,

                issue_date:
                    issueDate || null,

                expiration_date:
                    expirationDate || null,

                observation:
                    observation || null
            };
            if (editingDocumentIndex === null) documents.push(documentData);
            else documents[editingDocumentIndex] = documentData;
            renderArticleDocuments();

            $('#documentModal')
                .modal('hide');

        }
    );

    $(document).on('click', '.editArticleDocument', function () {
        const index = Number($(this).closest('tr').data('index'));
        const document = documents[index];
        if (!document) return;
        editingDocumentIndex = index;
        $('#documentForm')[0].reset();
        $('#document_type_id').val(String(document.document_type_id));
        $('#document_brand_id').val(document.brand_id || '').trigger('change');
        $('#issue_date').val(document.issue_date || '');
        $('#expiration_date').val(document.expiration_date || '');
        $('#document_observation').val(document.observation || '');
        $('#selectedFileName').text(document.file?.name || document.original_name || 'Archivo actual');
        $('#documentFileHelp').text('Opcional: seleccione otro archivo para reemplazar el actual.');
        $('#documentModalTitle').text('Editar Documento');
        $('#documentSaveText').text('Guardar cambios');
        $('#documentModal').modal('show');
    });

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR DOCUMENTO
    |--------------------------------------------------------------------------
    */
    $(document).on(
        'click',
        '.removeDocument',
        function () {
            console.log('CLICK AGREGAR DOCUMENTO');
            let row =
                $(this).closest('tr');

            let index =
                row.data('index');

            documents[index] =
                null;

            renderArticleDocuments();

        }
    );

    $(document).on(
        'change',
        '#document_file',
        function () {

            let fileName =
                this.files.length
                    ? this.files[0].name
                    : 'Ningún archivo seleccionado';

            $('#selectedFileName')
                .text(fileName);

        }
    );


    /*
|--------------------------------------------------------------------------
| VER ARTICULO
|--------------------------------------------------------------------------
*/
    $(document).on(
        'click',
        '.viewArticle',
        function () {

            let id = $(this).data('id');

            let url =
                window.routes.showArticle
                    .replace(':id', id);

            $.get(url, function (response) {

                let article =
                    response.data;

                $('#va_header_subtitle')
                    .text([article.code, article.legal_name].filter(Boolean).join(' · ') || '-');

                $('#va_id')
                    .text(article.id);

                $('#va_code')
                    .text(article.code);

                $('#va_code_detail')
                    .text(article.code);

                $('#va_code_type')
                    .text(
                        article.code_type ?? '-'
                    );

                $('#va_institutional_code')
                    .text(
                        article.institutional_code ?? '-'
                    );

                $('#va_legal_name')
                    .text(article.legal_name);

                $('#va_legal_name_detail')
                    .text(article.legal_name);

                $('#va_commercial_name')
                    .text(article.commercial_name);

                $('#va_billing_name')
                    .text(article.billing_name);

                $('#va_category')
                    .text(
                        article.category
                            ?.description ?? '-'
                    );

                $('#va_subcategory')
                    .text(
                        article.subcategory
                            ?.description ?? '-'
                    );

                $('#va_brand')
                    .text(
                        article.brand
                            ?.description ?? '-'
                    );

                $('#va_presentation')
                    .text(
                        article.presentation
                            ?.description ?? '-'
                    );

                $('#va_unit')
                    .text(
                        article.unit
                            ?.description ?? '-'
                    );

                const pendingClassification = article.item_kind === null && article.is_inventory_item === null;
                $('#va_item_kind').text(pendingClassification
                    ? 'PENDIENTE (LEGACY)'
                    : (article.item_kind === 'service' ? 'SERVICIO' : 'PRODUCTO'));
                $('#va_is_inventory_item').text(pendingClassification
                    ? 'PENDIENTE (LEGACY)'
                    : (article.is_inventory_item ? 'INVENTARIABLE' : 'NO INVENTARIABLE'));
                $('#va_sunat_existence_type').text(article.item_kind === 'product' && article.is_inventory_item
                    ? (article.sunat_existence_type
                        ? `${article.sunat_existence_type.item_code} — ${article.sunat_existence_type.description}`
                        : 'PENDIENTE')
                    : 'NO APLICA');
                const inventoryApplies = article.item_kind === 'product' && article.is_inventory_item;
                $('#va_sunat_inventory_catalog').text(inventoryApplies
                    ? (article.sunat_inventory_catalog_item
                        ? `${article.sunat_inventory_catalog_item.item_code} — ${article.sunat_inventory_catalog_item.description}`
                        : 'PENDIENTE')
                    : 'NO APLICA');
                $('#va_sunat_inventory_code').text(inventoryApplies
                    ? (article.sunat_inventory_catalog_code || 'PENDIENTE')
                    : 'NO APLICA');
                $('#va_sunat_standard_code').text(article.sunat_standard_catalog_item && article.sunat_standard_code
                    ? `${article.sunat_standard_catalog_item.item_code} — ${article.sunat_standard_catalog_item.description}: ${article.sunat_standard_code}`
                    : 'No configurado');

                $('#va_has_batch')
                    .text(
                        article.has_batch
                            ? 'SI'
                            : 'NO'
                    );

                $('#va_has_expiration')
                    .text(
                        article.has_expiration
                            ? 'SI'
                            : 'NO'
                    );

                $('#va_observation')
                    .text(
                        article.observation ?? '-'
                    );

                $('#va_created_by')
                    .text(
                        article.creator?.name
                        ?? '-'
                    );

                $('#va_updated_by')
                    .text(
                        article.editor?.name
                        ?? '-'
                    );

                $('#va_created_at')
                    .text(
                        article.created_at
                    );

                $('#va_updated_at')
                    .text(
                        article.updated_at
                    );

                $('#va_status')
                    .removeClass()
                    .addClass(
                        article.status === 'ACTIVE'
                            ? 'badge badge-success px-3 py-1'
                            : 'badge badge-danger px-3 py-1'
                    )
                    .text(article.status);

                /*
|--------------------------------------------------------------------------
| IMAGENES
|--------------------------------------------------------------------------
*/

                let imagesHtml = '';

                if (
                    article.images &&
                    article.images.length > 0
                ) {

                    article.images.forEach(function (img) {

                        imagesHtml += `

            <div class="col-6 mb-2">

                <img
                    src="/storage/${img.file_path}"
                    class="
                        img-fluid
                        rounded
                        border
                        shadow-sm
                        article-preview-image
                    "

                    data-image="/storage/${img.file_path}"

                    style="
                        cursor:pointer;
                        height:120px;
                        width:100%;
                        object-fit:cover;
                    ">
            </div>

        `;

                    });

                }
                else {

                    imagesHtml = `

        <div class="col-12 text-center text-muted">

            No existen imágenes registradas

        </div>

    `;

                }

                $('#va_images_container')
                    .html(imagesHtml);

                /*
                |--------------------------------------------------------------------------
                | DOCUMENTOS
                |--------------------------------------------------------------------------
                */
                let html = '';

                if (
                    article.documents &&
                    article.documents.length > 0
                ) {

                    article.documents.forEach(
                        function (
                            doc,
                            index
                        ) {

                            html += `
<tr>

    <td>
        ${index + 1}
    </td>

    <td>

        <span class="document-badge">

            ${doc.document_type?.description ?? '-'}

        </span>

    </td>

    <td>
        ${doc.brand?.description ?? 'Sin marca'}
    </td>

    <td title="${doc.original_name}">

        <i class="fas fa-file-pdf text-danger mr-1"></i>

        ${doc.original_name}

    </td>

    <td>

        ${doc.issue_date
                                    ? doc.issue_date.substring(0, 10)
                                    : '-'}

    </td>

    <td>

        ${doc.expiration_date
                                    ? doc.expiration_date.substring(0, 10)
                                    : '-'}

    </td>

    <td class="text-center">

        <a
            href="/storage/${doc.file_path}"
            target="_blank"
            class="btn btn-info btn-sm btn-document"
            title="Ver PDF">

            <i class="fas fa-eye"></i>

        </a>

        <a
            href="/storage/${doc.file_path}"
            download
            class="btn btn-danger btn-sm btn-document"
            title="Descargar">

            <i class="fas fa-download"></i>

        </a>

    </td>

</tr>
`;

                        }
                    );

                } else {

                    html = `
                    <tr>

                        <td colspan="7"
                            class="text-center text-muted">

                            No existen documentos registrados

                        </td>

                    </tr>
                `;
                }

                $('#va_documents_body')
                    .html(html);

                $('#viewArticleModal')
                    .modal('show');

            });

        }
    );

    //ABRIR EL SELECTOR DE IMAGENES 
    $(document).on(
        'click',
        '#btnAddImage',
        function () {

            $('#articleImageInput').click();

        }
    );

    $(document).on(
        'change',
        '#articleImageInput',
        function () {

            let files = this.files;

            if (!files.length) {
                return;
            }

            for (
                let i = 0;
                i < files.length;
                i++
            ) {

                images.push({
                    file: files[i]
                });

            }

            renderImages();

            $(this).val('');

        }
    );

    function renderImages() {

        let html = '';

        if (!images.length) {

            html = `
            <div class="text-center text-muted py-5">

                <i class="fas fa-images fa-3x mb-2"></i>

                <div>

                    No hay imágenes agregadas

                </div>

            </div>
        `;

            $('#articleImagesContainer')
                .html(html);

            return;
        }

        images.forEach(function (item, index) {

            let url =
                URL.createObjectURL(
                    item.file
                );

            html += `
            <div
                class="position-relative mb-2">

                <img
                    src="${url}"
                    class="img-fluid rounded border">

                <button
                    type="button"
                    class="btn btn-danger btn-sm position-absolute removeImage"
                    data-index="${index}"
                    style="
                        top:5px;
                        right:5px;
                    ">

                    <i class="fas fa-trash"></i>

                </button>

            </div>
        `;

        });

        $('#articleImagesContainer')
            .html(html);

    }

    $(document).on(
        'click',
        '.removeImage',
        function () {

            let index =
                $(this).data('index');

            images.splice(index, 1);

            renderImages();

        }
    );

    $(document).on(
        'click',
        '.article-preview-image',
        function () {

            $('#previewLargeImage')
                .attr(
                    'src',
                    $(this).data('image')
                );

            $('#imagePreviewModal')
                .modal('show');

        }
    );


    //editar articulo
    /*
|--------------------------------------------------------------------------
| EDITAR ARTICULO
|--------------------------------------------------------------------------
*/
    $(document).on(
        'click',
        '.editArticle',
        function () {

            let id = $(this).data('id');

            $.ajax({

                url:
                    window.routes.updateArticle +
                    '/' +
                    id +
                    '/edit',

                type: 'GET',

                success: function (response) {

                    let article =
                        response.data;

                    $('#article_id')
                        .val(article.id);

                    $('#code')
                        .val(article.code);

                    $('#code_mode')
                        .val('manual');

                    $('#code_type')
                        .val(article.code_type);

                    $('#institutional_code')
                        .val(article.institutional_code);

                    $('#category_id')
                        .val(article.category_id)
                        .trigger('change');

                    setTimeout(function () {

                        $('#subcategory_id')
                            .val(
                                article.subcategory_id
                            );

                    }, 500);

                    $('#presentation_id')
                        .val(
                            article.presentation_id
                        );

                    $('#unit_id')
                        .val(
                            article.unit_id
                        );

                    $('#legal_name')
                        .val(
                            article.legal_name
                        );

                    $('#commercial_name')
                        .val(
                            article.commercial_name
                        );

                    $('#billing_name')
                        .val(
                            article.billing_name
                        );

                    $('#item_kind').val(article.item_kind ?? '');
                    $('#is_inventory_item').val(article.is_inventory_item === null
                        ? ''
                        : (article.is_inventory_item ? '1' : '0'));
                    syncInventoryClassification();
                    $('#sunat_existence_type_item_id')
                        .val(article.sunat_existence_type_item_id ?? null)
                        .trigger('change');
                    $('#sunat_inventory_catalog_item_id')
                        .val(article.sunat_inventory_catalog_item_id ?? null)
                        .trigger('change');
                    $('#sunat_inventory_catalog_code').val(article.sunat_inventory_catalog_code ?? '');
                    $('#sunat_inventory_catalog_use_internal_code').val('0');
                    $('#sunat_standard_catalog_item_id')
                        .val(article.sunat_standard_catalog_item_id ?? null)
                        .trigger('change');
                    $('#sunat_standard_code').val(article.sunat_standard_code ?? '');

                    $('#has_batch').val(
                        article.has_batch ? '1' : '0'
                    );

                    $('#has_expiration').val(
                        article.has_expiration ? '1' : '0'
                    );

                    $('#status')
                        .val(
                            article.status
                        );

                    $('#observation')
                        .val(
                            article.observation

                        );

                    /*
|--------------------------------------------------------------------------
| CARGAR IMAGENES
|--------------------------------------------------------------------------
*/

                    images = [];

                    if (
                        article.images &&
                        article.images.length
                    ) {

                        let html = '';

                        article.images.forEach(function (img, index) {

                            html += `
            <div class="position-relative mb-2">

                <img
                    src="/storage/${img.file_path}"
                    class="img-fluid rounded border">

                <button
                    type="button"
                    class="btn btn-danger btn-sm position-absolute removeExistingImage"
                    data-id="${img.id}"
                    style="
                        top:5px;
                        right:5px;
                        z-index:10;
                    ">

                    <i class="fas fa-trash"></i>

                </button>

            </div>
        `;

                        });

                        $('#articleImagesContainer').html(html);



                    } else {

                        $('#articleImagesContainer').html(`
        <div class="text-center text-muted py-5">

            <i class="fas fa-images fa-3x mb-2"></i>

            <div>
                No hay imágenes agregadas
            </div>

        </div>
    `);

                    }

                    /*
|--------------------------------------------------------------------------
| CARGAR DOCUMENTOS
|--------------------------------------------------------------------------
*/

                    documents = [];

                    if (
                        article.documents &&
                        article.documents.length
                    ) {

                        documents = article.documents.map(doc => ({
                            id: doc.id,
                            document_type_id: doc.document_type_id,
                            document_type: doc.document_type?.description ?? '-',
                            brand_id: doc.brand_id || null,
                            brand: doc.brand?.description ?? 'Sin marca',
                            original_name: doc.original_name,
                            file: null,
                            issue_date: doc.issue_date ? String(doc.issue_date).slice(0, 10) : null,
                            expiration_date: doc.expiration_date ? String(doc.expiration_date).slice(0, 10) : null,
                            observation: doc.observation || null
                        }));
                        renderArticleDocuments();

                    }
                    else {

                        $('#documentsTableBody').html(`
        <tr>
            <td colspan="6"
                class="text-center text-muted">

                No hay documentos agregados

            </td>
        </tr>
    `);

                    }


                    $('#articleModalLabel')
                        .text(
                            'Editar Artículo'
                        );

                    $('#articleModal')
                        .modal('show');

                }

            });

        }
    );

    let deletedImages = [];
    $(document).on(
        'click',
        '.removeExistingImage',
        function () {

            let imageId = $(this).data('id');

            deletedImages.push(imageId);

            $(this)
                .closest('.position-relative')
                .remove();

        }
    );


    let deletedDocuments = [];
    $(document).on(
        'click',
        '.removeExistingDocument',
        function () {

            let documentId = $(this).data('id');
            const index = Number($(this).closest('tr').data('index'));

            deletedDocuments.push(documentId);
            documents[index] = null;
            renderArticleDocuments();

        }
    );

    /*
|--------------------------------------------------------------------------
| ELIMINAR ARTICULO
|--------------------------------------------------------------------------
*/
    $(document).on(
        'click',
        '.deleteArticle',
        function () {

            let id = $(this).data('id');

            Swal.fire({

                title: '¿Eliminar artículo?',

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

                    url:
                        window.routes.deleteArticle +
                        '/' +
                        id,

                    type: 'POST',

                    data: {

                        _method: 'DELETE'

                    },

                    success: function (response) {

                        Swal.fire({

                            icon: 'success',

                            title: response.message,

                            timer: 2000,

                            showConfirmButton: false

                        });

                        tableArticle.ajax.reload(
                            null,
                            false
                        );

                    },

                    error: function (xhr) {

                        Swal.fire({

                            icon: 'error',

                            title: 'Error',

                            text:
                                xhr.responseJSON?.message
                                || 'No se pudo eliminar'

                        });

                    }

                });

            });

        }
    );
});
