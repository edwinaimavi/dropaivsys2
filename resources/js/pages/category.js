var divLoading = document.getElementById('divLoading');

let tableCategory;

$(function () {

    $('[data-toggle="tooltip"]').tooltip();

});

document.addEventListener("DOMContentLoaded", function () {

    // =========================================================
    // GENERAR CÓDIGO AUTOMÁTICO
    // =========================================================

    $('#categoryModal').on('show.bs.modal', function () {

        // SOLO PARA NUEVO
        if (!$('#categoryForm').attr('data-id')) {

            $.ajax({

                url: window.routes.generateCode,

                type: 'GET',

                success: function (response) {

                    $('#code').val(response.code);

                }

            });

        }

    });

    // =========================================================
    // CSRF TOKEN
    // =========================================================

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // =========================================================
    // DATATABLE
    // =========================================================

    tableCategory = $('#tableCategory').DataTable({

        processing: true,

        serverSide: true,

        ajax: window.routes.categoryList,

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
                data: 'description',
                name: 'description'
            },

            {
                data: 'code',
                name: 'code'
            },

            {
                data: 'type',
                name: 'type'
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

    // =========================================================
    // GUARDAR / ACTUALIZAR
    // =========================================================

    $('#categoryForm').on('submit', function (e) {

        e.preventDefault();

        const btn = $('#btnSaveCategory');

        if (btn.prop('disabled')) {
            return;
        }

        btn.prop('disabled', true);

        btn.html(`
            <span class="spinner-border spinner-border-sm mr-1"></span>
            Guardando...
        `);

        divLoading.style.display = "flex";

        const $form = $(this);

        const id = $form.attr('data-id');

        let url = '';

        let type = '';

        const formData = new FormData(this);

        if (id) {

            url = "/admin/categories/" + id;

            type = 'POST';

            formData.append('_method', 'PUT');

        } else {

            url = window.routes.storeCategory;

            type = 'POST';

        }

        $.ajax({

            url: url,

            type: type,

            data: formData,

            processData: false,

            contentType: false,

            success: function (response) {

                divLoading.style.display = "none";

                btn.prop('disabled', false);

                btn.html(`
                    <i class="fas fa-save mr-1"></i>
                    Guardar Categoría
                `);

                $('#categoryModal').modal('hide');

                tableCategory.ajax.reload(null, false);

                Swal.fire({

                    title: response.message,

                    icon: "success",

                    toast: true,

                    position: "top-end",

                    showConfirmButton: false,

                    timer: 3000,

                    timerProgressBar: true

                });

            },

            error: function (xhr) {

                divLoading.style.display = "none";

                btn.prop('disabled', false);

                btn.html(`
                    <i class="fas fa-save mr-1"></i>
                    Guardar Categoría
                `);

                if (xhr.status === 422) {

                    const errors = xhr.responseJSON.errors || {};

                    $('.is-invalid').removeClass('is-invalid');

                    $('.invalid-feedback').text('');

                    $.each(errors, function (key, messages) {

                        const input = $(`#${key}`);

                        input.addClass('is-invalid');

                        $(`#${key}-error`).text(messages[0]);

                    });

                } else {

                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text: xhr.responseJSON?.message || 'Unexpected error',

                        toast: true,

                        position: 'top-end',

                        showConfirmButton: false,

                        timer: 3500

                    });

                }

            }

        });

    });

    // =========================================================
    // EDITAR
    // =========================================================

    $(document).on('click', '.editCategory', function () {

        const id = $(this).data('id');

        $('#categoryForm').attr('data-id', id);

        $('#description').val($(this).data('description'));

        $('#code').val($(this).data('code'));

        $('#type').val($(this).data('type'));

        $('#status').val($(this).data('status'));

        $('#observation').val($(this).data('observation'));

        $('.icon_modal').html(`
            <i class="far fa-edit text-primary"></i>
        `);

        $('#categoryModalLabel').html('EDITAR CATEGORÍA');

        $('#categoryModal').modal('show');

    });

    // =========================================================
    // VER DETALLE
    // =========================================================

    $(document).on('click', '.viewCategory', function () {

        const button = $(this);

        // HEADER
        $('#vc_description').text(button.data('description') || '—');

        $('#vc_type').text(button.data('type') || '—');

        // DETAILS
        $('#vc_id').text(button.data('id') || '—');

        $('#vc_description_detail').text(button.data('description') || '—');

        $('#vc_code').text(button.data('code') || '—');

        $('#vc_type_detail').text(button.data('type') || '—');

        $('#vc_status_text').text(button.data('status') || '—');

        $('#vc_observation').text(
            button.data('observation') || 'Sin observaciones'
        );

        // USERS
        $('#vc_created_by').text(
            button.data('created_by') || 'No registrado'
        );

        $('#vc_created_by_user').text(
            button.data('created_by') || 'No registrado'
        );

        $('#vc_updated_by_user').text(
            button.data('updated_by') || 'No registrado'
        );

        // DATES
        $('#vc_created_at').text(
            button.data('created_at') || '—'
        );

        $('#vc_updated_at').text(
            button.data('updated_at') || '—'
        );

        $('#vc_updated_at_footer').text(
            button.data('updated_at') || '—'
        );

        // STATUS BADGE
        const status = button.data('status');

        let badgeClass = 'badge-secondary';

        if (status === 'ACTIVE') {

            badgeClass = 'badge-success';

        } else if (status === 'INACTIVE') {

            badgeClass = 'badge-danger';

        }

        $('#vc_status')
            .removeClass('badge-success badge-danger badge-secondary')
            .addClass(badgeClass)
            .text(status);

        // =========================================================
        // SUBCATEGORÍAS
        // =========================================================

        let subcategories = button.data('subcategories');

        if (typeof subcategories === 'string') {

            try {

                subcategories = JSON.parse(subcategories);

            } catch (e) {

                subcategories = [];
            }
        }

        let html = '';

        if (subcategories && subcategories.length > 0) {

            subcategories.forEach((item, index) => {

                const badge =
                    item.status === 'ACTIVE'
                        ? `<span class="badge badge-success px-2 py-1">ACTIVO</span>`
                        : `<span class="badge badge-danger px-2 py-1">INACTIVO</span>`;

                html += `
            <tr>

                <td style="font-size:12px;">
                    ${index + 1}
                </td>

                <td style="font-size:12px;font-weight:500;">
                    ${item.description}
                </td>

                <td>
                    ${badge}
                </td>

            </tr>
        `;
            });

        } else {

            html = `
        <tr>

            <td colspan="3"
                class="text-center text-muted py-3">

                No hay subcategorías registradas

            </td>

        </tr>
    `;
        }

        $('#vc_subcategories_table').html(html);

        $('#vc_total_subcategories').text(
            subcategories ? subcategories.length : 0
        );

        // OPEN MODAL
        $('#viewCategoryModal').modal('show');

    });

    // =========================================================
    // ABRIR MODAL SUBCATEGORÍAS
    // =========================================================

    $(document).on('click', '.subcategoryCategory', function () {

        const categoryId = $(this).data('id');

        const categoryName = $(this).data('description');

        // SET DATA
        $('#subcategory_category_id').val(categoryId);

        $('#subcategory_category_name').text(categoryName);

        // RESET FORM
        $('#subcategoryForm')[0].reset();

        // ABRIR MODAL
        $('#subcategoryModal').modal({

            backdrop: 'static',
            keyboard: false

        });

    });

    // =========================================================
    // DATATABLE SUBCATEGORÍAS
    // =========================================================

    let tableSubcategory;

    $(document).on('click', '.subcategoryCategory', function () {

        const categoryId = $(this).data('id');

        const categoryName = $(this).data('description');

        $('#subcategory_category_id').val(categoryId);

        $('#subcategory_category_name').text(categoryName);

        $('#subcategoryForm')[0].reset();

        // DESTRUIR SI YA EXISTE
        if ($.fn.DataTable.isDataTable('#tableSubcategory')) {

            $('#tableSubcategory').DataTable().destroy();

        }

        // CREAR TABLA
        tableSubcategory = $('#tableSubcategory').DataTable({

            processing: true,

            serverSide: true,

            ajax: `${window.routes.subcategoryList}/${categoryId}/subcategories`,

            columns: [

                {
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },

                {
                    data: 'description',
                    name: 'description'
                },

                {
                    data: 'status',
                    name: 'status'
                },

                {
                    data: 'acciones',
                    orderable: false,
                    searchable: false
                }

            ],

            responsive: true,

            autoWidth: false,

            scrollX: true,

            language: {
                url: "/vendor/datatables/js/i18n/es-ES.json"
            }

        });

        $('#subcategoryModal').modal({

            backdrop: 'static',
            keyboard: false

        });

    });

    // =========================================================
    // GUARDAR SUBCATEGORÍA
    // =========================================================

    // =========================================================
    // GUARDAR / EDITAR SUBCATEGORÍA
    // =========================================================

    $('#subcategoryForm').on('submit', function (e) {

        e.preventDefault();

        const btn = $('#btnSaveSubcategory');

        btn.prop('disabled', true);

        const subcategoryId = $('#subcategory_id').val();

        let url = '';
        let type = '';

        if (subcategoryId) {

            url = `${window.routes.updateSubcategory}/${subcategoryId}`;

            type = 'PUT';

        } else {

            url = window.routes.storeSubcategory;

            type = 'POST';
        }

        btn.html(`
        <span class="spinner-border spinner-border-sm mr-1"></span>
        Guardando...
    `);

        $.ajax({

            url: url,

            type: type,

            data: {

                category_id: $('#subcategory_category_id').val(),

                description: $('#subcategory_description').val(),

                status: $('#subcategory_status').val(),

                observation: $('#subcategory_observation').val(),

            },

            success: function (response) {

                btn.prop('disabled', false);

                btn.html(`
                <i class="fas fa-save mr-1"></i>
                Registrar
            `);

                $('#subcategoryForm')[0].reset();

                $('#subcategory_id').val('');

                tableSubcategory.ajax.reload(null, false);

                Swal.fire({

                    icon: 'success',

                    title: response.message,

                    toast: true,

                    position: 'top-end',

                    showConfirmButton: false,

                    timer: 2500

                });

            },

            error: function (xhr) {

                btn.prop('disabled', false);

                btn.html(`
                <i class="fas fa-save mr-1"></i>
                Registrar
            `);

                let message = 'Error al registrar';

                if (xhr.responseJSON?.errors) {

                    const errors = xhr.responseJSON.errors;

                    message = Object.values(errors)[0][0];

                }

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: message

                });

            }

        });

    });

    // =========================================================
    // EDITAR SUBCATEGORÍA
    // =========================================================

    $(document).on('click', '.editSubcategory', function () {

        $('#subcategory_id').val($(this).data('id'));

        $('#subcategory_description').val(
            $(this).data('description')
        );

        $('#subcategory_status').val(
            $(this).data('status')
        );

        $('#subcategory_observation').val(
            $(this).data('observation')
        );

        $('#btnSaveSubcategory').html(`
        <i class="fas fa-save mr-1"></i>
        Actualizar
    `);

    });

    // =========================================================
    // ELIMINAR SUBCATEGORÍA
    // =========================================================

    $(document).on('click', '.deleteSubcategory', function () {

        const id = $(this).data('id');

        Swal.fire({

            title: '¿Eliminar subcategoría?',

            text: 'Esta acción no podrá revertirse.',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonText: 'Sí, eliminar',

            cancelButtonText: 'Cancelar'

        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({

                    url: `${window.routes.deleteSubcategory}/${id}`,

                    type: 'DELETE',

                    success: function (response) {

                        tableSubcategory.ajax.reload(null, false);

                        Swal.fire({

                            icon: 'success',

                            title: response.message,

                            toast: true,

                            position: 'top-end',

                            showConfirmButton: false,

                            timer: 2500

                        });

                    },

                    error: function () {

                        Swal.fire({

                            icon: 'error',

                            title: 'Error',

                            text: 'No se pudo eliminar.'

                        });

                    }

                });

            }

        });

    });
    // =========================================================
    // LIMPIAR MODAL
    // =========================================================

    $('#categoryModal').on('hidden.bs.modal', function () {

        const $form = $('#categoryForm');

        $form[0].reset();

        $form.removeAttr('data-id');

        $('#categoryModalLabel').html('NUEVA CATEGORÍA');

        $form.find('.is-invalid').removeClass('is-invalid');

        $('.invalid-feedback').text('');

    });

    // =========================================================
    // ELIMINAR
    // =========================================================

    $(document).on('click', '.deleteCategory', function () {

        const id = $(this).data('id');

        Swal.fire({

            title: '¿Está seguro?',

            text: 'Esta acción no podrá revertirse.',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonText: 'Sí, eliminar',

            cancelButtonText: 'Cancelar'

        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({

                    url: `${window.routes.deleteCategory}/${id}`,

                    type: 'DELETE',

                    success: function (response) {

                        tableCategory.ajax.reload(null, false);

                        Swal.fire({

                            icon: 'success',

                            title: response.message,

                            toast: true,

                            position: 'top-end',

                            showConfirmButton: false,

                            timer: 3000

                        });

                    },

                    error: function () {

                        Swal.fire({

                            icon: 'error',

                            title: 'Error',

                            text: 'Ocurrió un error al eliminar.'

                        });

                    }

                });

            }

        });

    });

});