var divLoading = document.getElementById('divLoading');

let tableBrand;

$(function () {

    $('[data-toggle="tooltip"]').tooltip();

});

document.addEventListener("DOMContentLoaded", function () {

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

    tableBrand = $('#tableBrand').DataTable({

        processing: true,

        serverSide: true,

        ajax: window.routes.brandList,

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

    $('#brandForm').on('submit', function (e) {

        e.preventDefault();

        const btn = $('#btnSaveBrand');

        if (btn.prop('disabled')) {
            return;
        }

        btn.prop('disabled', true);

        btn.html(`
            <span class="spinner-border spinner-border-sm mr-1"></span>
            Guardando...
        `);

        divLoading.style.display = "flex";

        const id = $('#brand_id').val();

        let url = '';

        let type = '';

        const formData = new FormData(this);

        if (id) {

            url = `${window.routes.updateBrand}/${id}`;

            type = 'POST';

            formData.append('_method', 'PUT');

        } else {

            url = window.routes.storeBrand;

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
                    Guardar Marca
                `);

                $('#brandModal').modal('hide');

                tableBrand.ajax.reload(null, false);

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
                    Guardar Marca
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

    // =========================================================
    // EDITAR
    // =========================================================
    // =========================================================
    // EDITAR
    // =========================================================

    $(document).on('click', '.editBrand', function () {

        $('#brand_id').val($(this).data('id'));

        $('#code').val($(this).data('code'));

        $('#description').val($(this).data('description'));

        $('#status').val($(this).data('status'));

        $('#observation').val($(this).data('observation'));

        $('.icon_modal').html(`
        <i class="far fa-edit text-secondary"></i>
    `);

        $('#brandModalLabel').html('EDITAR MARCA');

        $('#brandModal').modal('show');

    });

    // =========================================================
    // LIMPIAR MODAL
    // =========================================================

    $('#brandModal').on('hidden.bs.modal', function () {

        const $form = $('#brandForm');

        $form[0].reset();

        $('#brand_id').val('');

        $('#brandModalLabel').html('NUEVA MARCA');

        $('.icon_modal').html(`
            <i class="fas fa-tags text-secondary"></i>
        `);

        $form.find('.is-invalid').removeClass('is-invalid');

        $('.invalid-feedback').text('');
        $('#code').val('');

    });

    $('#brandModal').on('show.bs.modal', function () {

        if (!$('#brand_id').val()) {

            generateBrandCode();

        }

    });

    // =========================================================
    // VER DETALLE
    // =========================================================

    // =========================================================
    // VER DETALLE
    // =========================================================

    $(document).on('click', '.viewBrand', function () {

        const status = $(this).data('status');

        $('#vb_id').text(
            $(this).data('id')
        );

        // CABECERA
        $('#vb_code').text(
            $(this).data('code') || '—'
        );

        $('#vb_description').text(
            $(this).data('description') || '—'
        );

        // DETALLE
        $('#vb_code_detail').text(
            $(this).data('code') || '—'
        );

        $('#vb_description_detail').text(
            $(this).data('description') || '—'
        );

        $('#vb_observation').text(
            $(this).data('observation') || 'Sin observaciones'
        );

        $('#vb_created_by').text(
            $(this).data('created_by') || '—'
        );

        $('#vb_updated_by').text(
            $(this).data('updated_by') || '—'
        );

        $('#vb_created_at').text(
            $(this).data('created_at') || '—'
        );

        $('#vb_updated_at').text(
            $(this).data('updated_at') || '—'
        );

        const statusText =
            status === 'ACTIVE'
                ? 'ACTIVO'
                : 'INACTIVO';

        $('#vb_status').text(statusText);

        $('#vb_status_text').text(statusText);

        if (status === 'ACTIVE') {

            $('#vb_status')
                .removeClass('badge-danger')
                .addClass('badge-success');

        } else {

            $('#vb_status')
                .removeClass('badge-success')
                .addClass('badge-danger');

        }

        $('#viewBrandModal').modal('show');

    });
    // =========================================================
    // ELIMINAR
    // =========================================================

    $(document).on('click', '.deleteBrand', function () {

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

                    url: `${window.routes.deleteBrand}/${id}`,

                    type: 'DELETE',

                    success: function (response) {

                        tableBrand.ajax.reload(null, false);

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

function generateBrandCode() {

    $.get(window.routes.generateBrandCode, function (response) {

        $('#code').val(response.code);

    });

}