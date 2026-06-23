var divLoading = document.getElementById('divLoading');

let tablePresentation;

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

    tablePresentation = $('#tablePresentation').DataTable({

        processing: true,

        serverSide: true,

        ajax: window.routes.presentationList,

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

    $('#presentationForm').on('submit', function (e) {

        e.preventDefault();

        const btn = $('#btnSavePresentation');

        if (btn.prop('disabled')) {
            return;
        }

        btn.prop('disabled', true);

        btn.html(`
            <span class="spinner-border spinner-border-sm mr-1"></span>
            Guardando...
        `);

        divLoading.style.display = "flex";

        const id = $('#presentation_id').val();

        let url = '';

        let type = '';

        const formData = new FormData(this);

        if (id) {

            url = `${window.routes.updatePresentation}/${id}`;

            type = 'POST';

            formData.append('_method', 'PUT');

        } else {

            url = window.routes.storePresentation;

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
                    Guardar Presentación
                `);

                $('#presentationModal').modal('hide');

                tablePresentation.ajax.reload(null, false);

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
                    Guardar Presentación
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

    $(document).on('click', '.editPresentation', function () {

        $('#presentation_id').val($(this).data('id'));

        $('#description').val($(this).data('description'));

        $('#status').val($(this).data('status'));

        $('#observation').val($(this).data('observation'));

        $('.icon_modal').html(`
            <i class="far fa-edit text-warning"></i>
        `);

        $('#presentationModalLabel').html('EDITAR PRESENTACIÓN');

        $('#presentationModal').modal('show');

    });

    // =========================================================
    // LIMPIAR MODAL
    // =========================================================

    $('#presentationModal').on('hidden.bs.modal', function () {

        const $form = $('#presentationForm');

        $form[0].reset();

        $('#presentation_id').val('');

        $('#presentationModalLabel').html('NUEVA PRESENTACIÓN');

        $('.icon_modal').html(`
            <i class="fas fa-box-open text-warning"></i>
        `);

        $form.find('.is-invalid').removeClass('is-invalid');

        $('.invalid-feedback').text('');

    });

    // =========================================================
    // VER DETALLE
    // =========================================================

    $(document).on('click', '.viewPresentation', function () {

        const status = $(this).data('status');

        $('#vp_id').text($(this).data('id'));

        $('#vp_description').text($(this).data('description'));

        $('#vp_description_detail').text($(this).data('description'));

        $('#vp_quantity').text(
            $(this).data('quantity') || '—'
        );

        $('#vp_unit').text(
            $(this).data('unit') || '—'
        );

        $('#vp_unit_detail').text(
            $(this).data('unit') || '—'
        );

        $('#vp_observation').text(
            $(this).data('observation') || 'Sin observaciones'
        );

        $('#vp_created_by').text($(this).data('created_by'));

        $('#vp_updated_by_user').text($(this).data('updated_by'));

        $('#vp_created_at').text($(this).data('created_at'));

        $('#vp_updated_at').text($(this).data('updated_at'));

        // STATUS
        const statusText = status === 'ACTIVE'
            ? 'ACTIVO'
            : 'INACTIVO';

        $('#vp_status').text(statusText);

        $('#vp_status_text').text(statusText);

        if (status === 'ACTIVE') {

            $('#vp_status')
                .removeClass('badge-danger')
                .addClass('badge-warning');

        } else {

            $('#vp_status')
                .removeClass('badge-warning')
                .addClass('badge-danger');
        }

        $('#viewPresentationModal').modal('show');

    });

    // =========================================================
    // ELIMINAR
    // =========================================================

    $(document).on('click', '.deletePresentation', function () {

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

                    url: `${window.routes.deletePresentation}/${id}`,

                    type: 'DELETE',

                    success: function (response) {

                        tablePresentation.ajax.reload(null, false);

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