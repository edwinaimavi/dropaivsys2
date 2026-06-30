var divLoading = document.getElementById('divLoading');

let tableUnit;

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

    tableUnit = $('#tableUnit').DataTable({

        processing: true,

        serverSide: true,

        ajax: window.routes.unitList,

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
                data: 'abbreviation',
                name: 'abbreviation'
            },

            {
                data: 'description',
                name: 'description'
            },

        /*     {
                data: 'decimal_quantity',
                name: 'decimal_quantity'
            }, */

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

    $('#unitForm').on('submit', function (e) {

        e.preventDefault();

        const btn = $('#btnSaveUnit');

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

        const id = $('#unit_id').val();

        let url = '';

        let type = '';

        const formData = new FormData(this);

        if (id) {

            url = `${window.routes.updateUnit}/${id}`;

            type = 'POST';

            formData.append('_method', 'PUT');

        } else {

            url = window.routes.storeUnit;

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
                    Guardar Unidad
                `);

                $('#unitModal').modal('hide');

                tableUnit.ajax.reload(null, false);

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
                    Guardar Unidad
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

    $(document).on('click', '.editUnit', function () {

        $('#unit_id').val($(this).data('id'));

        $('#abbreviation').val($(this).data('abbreviation'));

        $('#description').val($(this).data('description'));

        $('#decimal_quantity').val($(this).data('decimal_quantity'));

        $('#status').val($(this).data('status'));

        $('#observation').val($(this).data('observation'));

        $('.icon_modal').html(`
            <i class="far fa-edit text-primary"></i>
        `);

        $('#unitModalLabel').html('EDITAR UNIDAD');

        $('#unitModal').modal('show');

    });

    // =========================================================
    // LIMPIAR MODAL
    // =========================================================

    $('#unitModal').on('hidden.bs.modal', function () {

        const $form = $('#unitForm');

        $form[0].reset();

        $('#unit_id').val('');

        $('#unitModalLabel').html('NUEVA UNIDAD');

        $('.icon_modal').html(`
            <i class="fas fa-balance-scale text-primary"></i>
        `);

        $form.find('.is-invalid').removeClass('is-invalid');

        $('.invalid-feedback').text('');

    });

    // =========================================================
    // VER DETALLE
    // =========================================================

    $(document).on('click', '.viewUnit', function () {

        const status = $(this).data('status');

        $('#vu_id').text($(this).data('id'));

        $('#vu_abbreviation').text($(this).data('abbreviation'));

        $('#vu_abbreviation_detail').text($(this).data('abbreviation'));

        $('#vu_description').text($(this).data('description'));

        $('#vu_description_detail').text($(this).data('description'));

        $('#vu_observation').text(
            $(this).data('observation') || 'Sin observaciones'
        );

        $('#vu_created_by').text($(this).data('created_by'));

        $('#vu_updated_by_user').text($(this).data('updated_by'));

        $('#vu_created_at').text($(this).data('created_at'));

        $('#vu_updated_at').text($(this).data('updated_at'));

        // DECIMALES
        const decimalText = $(this).data('decimal_quantity') == 1
            ? 'SI'
            : 'NO';

        $('#vu_decimal_quantity').text(decimalText);

        // STATUS
        const statusText = status === 'ACTIVE'
            ? 'ACTIVO'
            : 'INACTIVO';

        $('#vu_status').text(statusText);

        $('#vu_status_text').text(statusText);

        if (status === 'ACTIVE') {

            $('#vu_status')
                .removeClass('badge-danger')
                .addClass('badge-success');

        } else {

            $('#vu_status')
                .removeClass('badge-success')
                .addClass('badge-danger');
        }

        $('#viewUnitModal').modal('show');

    });

    // =========================================================
    // ELIMINAR
    // =========================================================

    $(document).on('click', '.deleteUnit', function () {

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

                    url: `${window.routes.deleteUnit}/${id}`,

                    type: 'DELETE',

                    success: function (response) {

                        tableUnit.ajax.reload(null, false);

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