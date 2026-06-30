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
                'brand_id',
                $('#brand_id').val()
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

            formData.append(
                'minimum_stock',
                $('#minimum_stock').val()
            );

            formData.append(
                'is_taxable',
                $('#is_taxable').val()
            );

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

                    document_type_id:
                        d.document_type_id,

                    issue_date:
                        d.issue_date,

                    expiration_date:
                        d.expiration_date,

                    observation:
                        d.observation

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
                <td colspan="5" class="text-center text-muted">
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

        $('#articleForm')[0].reset();

        $('#article_id').val('');

        $('#code').val('');

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
            <td colspan="5" class="text-center text-muted">
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
                data: 'brand',
                name: 'brand'
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
                data: 'is_taxable',
                name: 'is_taxable'
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


    //FUNCION PARA GENERAR CODIGO DE ARTICULO
    function generateArticleCode() {

        $.ajax({

            url: window.routes.generateArticleCode,

            type: 'GET',

            success: function (response) {

                $('#code').val(response.code);

            },

            error: function () {

                console.error(
                    'Error al generar código'
                );

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
        <td colspan="5" class="text-center text-muted">
            No hay documentos agregados
        </td>
    </tr>
`);

        $('#articleForm')[0].reset();

        $('#article_id').val('');

        generateArticleCode();

    });


    let documents = [];
    let images = [];

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

            let issueDate =
                $('#issue_date').val();

            let expirationDate =
                $('#expiration_date').val();

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

            if (
                !fileInput.files ||
                !fileInput.files.length
            ) {

                Swal.fire(
                    'Atención',
                    'Seleccione un archivo',
                    'warning'
                );

                return;
            }

            let file =
                fileInput.files[0];

            let index =
                documents.length;

            documents.push({

                document_type_id:
                    documentTypeId,

                document_type:
                    documentTypeText,

                file:
                    file,

                issue_date:
                    issueDate,

                expiration_date:
                    expirationDate,

                observation:
                    observation

            });

            if ($('#documentsTableBody td[colspan]').length) {

                $('#documentsTableBody').empty();

            }

            $('#documentsTableBody').append(`
    <tr data-index="${index}">

        <td>${documentTypeText}</td>

        <td>${file.name}</td>

        <td>${issueDate || '-'}</td>

        <td>${expirationDate || '-'}</td>

        <td class="text-center">

            <button
                type="button"
                class="btn btn-danger btn-sm removeDocument">

                <i class="fas fa-trash"></i>

            </button>

        </td>

    </tr>
`);

            $('#documentModal')
                .modal('hide');

        }
    );

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

            row.remove();

            if (
                $('#documentsTableBody tr').length === 0
            ) {

                $('#documentsTableBody').html(`
                <tr>

                    <td
                        colspan="5"
                        class="text-center text-muted">

                        No hay documentos agregados

                    </td>

                </tr>
            `);

            }

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

                $('#va_minimum_stock')
                    .text(
                        article.minimum_stock
                    );

                $('#va_is_taxable')
                    .text(
                        article.is_taxable
                            ? 'SI'
                            : 'NO'
                    );

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

                        <td colspan="6"
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

                    $('#brand_id')
                        .val(
                            article.brand_id
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

                    $('#minimum_stock')
                        .val(
                            article.minimum_stock
                        );

                    $('#is_taxable').val(
                        article.is_taxable ? '1' : '0'
                    );

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

                        let html = '';

                        article.documents.forEach(function (doc, index) {

                            html += `
    <tr>

        <td>
            ${doc.document_type?.description ?? '-'}
        </td>

        <td>
            ${doc.original_name}
        </td>

        <td>
            ${doc.issue_date ?? '-'}
        </td>

        <td>
            ${doc.expiration_date ?? '-'}
        </td>

        <td class="text-center">

            <button
                type="button"
                class="btn btn-danger btn-sm removeExistingDocument"
                data-id="${doc.id}">

                <i class="fas fa-trash"></i>

            </button>

        </td>

    </tr>
`;
                        });

                        $('#documentsTableBody')
                            .html(html);

                    }
                    else {

                        $('#documentsTableBody').html(`
        <tr>
            <td colspan="5"
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

            deletedDocuments.push(documentId);

            $(this)
                .closest('tr')
                .remove();

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
