var supplierLoading = document.getElementById('supplierLoading');

let tableSupplier;

let tableSupplierAccounts;

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
    // SELECT2 UBIGEO
    // =========================================================

    $('#ubigeo_id').select2({

        theme: 'bootstrap4',

        width: '100%',

        dropdownParent: $('#supplierModal'),

        placeholder: 'Buscar ubigeo...',

        allowClear: true,

        minimumInputLength: 2,

        ajax: {

            url: window.routes.searchUbigeo,

            type: 'GET',

            dataType: 'json',

            delay: 250,

            data: function (params) {

                return {
                    search: params.term
                };

            },

            processResults: function (response) {

                return {

                    results: $.map(response, function (item) {

                        return {

                            id: item.id,
                            text: item.text

                        };

                    })

                };

            },

            cache: true

        }

    });

    $('#ubigeo_id').on('select2:open', function () {

        document.querySelector('.select2-search__field').focus();

    });
    // =========================================================
    // DATATABLE
    // =========================================================

    tableSupplier = $('#tableSupplier').DataTable({

        processing: true,

        serverSide: true,

        ajax: window.routes.supplierList,

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
                data: 'ruc',
                name: 'ruc'
            },

            {
                data: 'business_name',
                name: 'business_name'
            },

            {
                data: 'short_name',
                name: 'short_name'
            },

            {
                data: 'supplier_type',
                name: 'supplier_type'
            },

            {
                data: 'payment_condition',
                name: 'payment_condition'
            },

            {
                data: 'phone',
                name: 'phone'
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

        /*     preDrawCallback: function () {
    
                supplierLoading.style.display = 'flex';
    
            },
    
            drawCallback: function () {
                supplierLoading.style.display = 'none';
    
            } */

    });

    // =========================================================
    // SOLO NÚMEROS EN RUC
    // =========================================================

    $('#ruc').on('input', function () {

        this.value = this.value.replace(/[^0-9]/g, '');

    });

    // =========================================================
    // MAYÚSCULAS
    // =========================================================

    $(document).on('input',
        '#business_name, #short_name, #address, #contact_name, #observation',
        function () {

            this.value = this.value.toUpperCase();

        }
    );

    // =========================================================
    // GUARDAR / ACTUALIZAR
    // =========================================================

    $('#supplierForm').on('submit', function (e) {

        e.preventDefault();

        const btn = $('#btnSaveSupplier');

        if (btn.prop('disabled')) {
            return;
        }

        btn.prop('disabled', true);

        btn.html(`
            <span class="spinner-border spinner-border-sm mr-1"></span>
            Guardando...
        `);

        /*  supplierLoading.style.display = "flex"; */

        const id = $('#supplier_id').val();

        let url = '';

        let type = '';

        const formData = new FormData(this);

        if (id) {

            url = `${window.routes.updateSupplier}/${id}`;

            type = 'POST';

            formData.append('_method', 'PUT');

        } else {

            url = window.routes.storeSupplier;

            type = 'POST';

        }

        $.ajax({

            url: url,

            type: type,

            data: formData,

            processData: false,

            contentType: false,

            success: function (response) {

                /* supplierLoading.style.display = "none"; */

                btn.prop('disabled', false);

                btn.html(`
                    <i class="fas fa-save mr-1"></i>
                    Guardar Proveedor
                `);

                $('#supplierModal').modal('hide');

                tableSupplier.ajax.reload(null, false);

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

                supplierLoading.style.display = "none";

                btn.prop('disabled', false);

                btn.html(`
                    <i class="fas fa-save mr-1"></i>
                    Guardar Proveedor
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

    $(document).on('click', '.editSupplier', function () {

        $('#supplier_id').val($(this).data('id'));

        $('#ruc').val($(this).data('ruc'));

        $('#business_name').val($(this).data('business_name'));

        $('#short_name').val($(this).data('short_name'));

        $('#address').val($(this).data('address'));

        $('#supplier_type').val($(this).data('supplier_type'));

        $('#payment_condition').val($(this).data('payment_condition'));

        $('#contact_name').val($(this).data('contact_name'));

        $('#email').val($(this).data('email'));

        $('#phone').val($(this).data('phone'));

        $('#igv_percentage').val($(this).data('igv_percentage'));

        $('#observation').val($(this).data('observation'));

        $('#status').val($(this).data('status'));

        // UBIGEO
        let ubigeoId = $(this).data('ubigeo_id');
        let ubigeoText = $(this).data('ubigeo_text');

        if (ubigeoId && ubigeoText) {

            let option = new Option(
                ubigeoText,
                ubigeoId,
                true,
                true
            );

            $('#ubigeo_id')
                .append(option)
                .trigger('change');

        }

        $('#supplierModalLabel').html('EDITAR PROVEEDOR');

        $('#supplierModal').modal('show');

    });

    // =========================================================
    // LIMPIAR MODAL
    // =========================================================

    $('#supplierModal').on('hidden.bs.modal', function () {

        const $form = $('#supplierForm');

        $form[0].reset();

        $('#supplier_id').val('');

        $('#supplierModalLabel').html('NUEVO PROVEEDOR');

        $('#ubigeo_id').val(null).trigger('change');

        $form.find('.is-invalid').removeClass('is-invalid');

        $('.invalid-feedback').text('');

    });

    // =========================================================
    // VER DETALLE
    // =========================================================

    $(document).on('click', '.viewSupplier', function () {

        const status = $(this).data('status');

        $('#vs_id').text($(this).data('id'));

        $('#vs_ruc').text($(this).data('ruc'));

        $('#vs_business_name').text(
            $(this).data('business_name') || '—'
        );

        $('#vs_business_name_detail').text(
            $(this).data('business_name') || '—'
        );

        $('#vs_short_name').text(
            $(this).data('short_name') || '—'
        );

        $('#vs_address').text(
            $(this).data('address') || '—'
        );

        $('#vs_ubigeo').text(
            $(this).data('ubigeo') || '—'
        );

        $('#vs_supplier_type').text(
            $(this).data('supplier_type') || '—'
        );

        $('#vs_payment_condition').text(
            $(this).data('payment_condition') || '—'
        );

        $('#vs_contact_name').text(
            $(this).data('contact_name') || '—'
        );

        $('#vs_email').text(
            $(this).data('email') || '—'
        );

        $('#vs_phone').text(
            $(this).data('phone') || '—'
        );

        $('#vs_igv_percentage').text(
            $(this).data('igv_percentage') + '%'
        );

        $('#vs_observation').text(
            $(this).data('observation') || 'Sin observaciones'
        );

        $('#vs_created_by').text($(this).data('created_by'));

        $('#vs_updated_by').text($(this).data('updated_by'));

        $('#vs_created_at').text($(this).data('created_at'));

        $('#vs_updated_at').text($(this).data('updated_at'));

        // STATUS
        const statusText = status === 'ACTIVE'
            ? 'ACTIVO'
            : 'INACTIVO';

        $('#vs_status').text(statusText);

        if (status === 'ACTIVE') {

            $('#vs_status')
                .removeClass('badge-danger')
                .addClass('badge-info');

        } else {

            $('#vs_status')
                .removeClass('badge-info')
                .addClass('badge-danger');
        }

        const supplierId = $(this).data('id');

        $.ajax({

            url: `${window.routes.supplierAccountsView}/${supplierId}/accounts`,

            type: 'GET',

            success: function (accounts) {

                let html = '';

                if (!accounts.length) {

                    html = `
                <tr>
                    <td colspan="8"
                        class="text-center text-muted py-3">
                        Sin cuentas registradas
                    </td>
                </tr>
            `;

                } else {

                    $.each(accounts, function (i, item) {

                        html += `
<tr style="font-size:11px;">

    <td>${i + 1}</td>

    <td>${item.bank?.description ?? '—'}</td>

    <td>${item.currency?.description ?? '—'}</td>

    <td>${item.account_holder ?? '—'}</td>

    <td>${item.account_number ?? '—'}</td>

    <td>${item.cci ?? '—'}</td>

    <td>
        ${item.is_detraction === 'YES'
                                ? '<span class="badge badge-warning">SI</span>'
                                : '<span class="badge badge-secondary">NO</span>'
                            }
    </td>

    <td>
        ${item.status === 'ACTIVE'
                                ? '<span class="badge badge-success">ACTIVO</span>'
                                : '<span class="badge badge-danger">INACTIVO</span>'
                            }
    </td>

</tr>
`;
                    });
                }

                $('#vs_accounts_body').html(html);

            }

        });

        $('#viewSupplierModal').modal('show');

    });


    // =========================================================
    // CUENTAS BANCARIAS
    // =========================================================
    $(document).on('click', '.bankAccountsSupplier', function () {

        const supplierId = $(this).data('id');
        const supplierName = $(this).data('business_name');
        const supplierRuc = $(this).data('ruc');

        $('#supplierAccountForm')[0].reset();
        $('#supplier_account_id').val('');

        $('#account_supplier_id').val(supplierId);
        $('#account_supplier_name').text(supplierName || '—');
        $('#account_supplier_ruc').text(supplierRuc || '—');

        const ajaxUrl = `/admin/supplier-accounts/list/${supplierId}`;

        $('#supplierAccountModal')
            .off('shown.bs.modal.accounts')
            .on('shown.bs.modal.accounts', function () {

                if ($.fn.DataTable.isDataTable('#tableSupplierAccounts')) {
                    tableSupplierAccounts.destroy();
                    $('#tableSupplierAccounts tbody').empty();
                }

                tableSupplierAccounts = $('#tableSupplierAccounts').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: ajaxUrl,
                    destroy: true,
                    autoWidth: false,
                    responsive: true,
                    columns: [
                        { data: 'DT_RowIndex', orderable: false, searchable: false },
                        { data: 'bank', name: 'bank' },
                        { data: 'currency', name: 'currency' },
                        { data: 'account_holder', name: 'account_holder' },
                        { data: 'account_number', name: 'account_number' },
                        { data: 'cci', name: 'cci' },
                        { data: 'is_detraction', name: 'is_detraction' },
                        { data: 'status', name: 'status' },
                        { data: 'acciones', orderable: false, searchable: false }
                    ]
                });

                setTimeout(function () {
                    tableSupplierAccounts.columns.adjust().responsive.recalc();
                }, 150);
            });

        $('#supplierAccountModal').modal('show');
    });
    // =========================================================
    // ELIMINAR
    // =========================================================

    $(document).on('click', '.deleteSupplier', function () {

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

                    url: `${window.routes.deleteSupplier}/${id}`,

                    type: 'DELETE',

                    success: function (response) {

                        tableSupplier.ajax.reload(null, false);

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


    // =========================================================
    // CONSULTAR RUC Y AUTOCOMPLETAR
    // =========================================================

    let isConsultingRuc = false;

    function consultarRucProveedor() {

        const ruc = $('#ruc').val().trim();

        // 1) SOLO NÚMEROS
        if (!/^\d+$/.test(ruc)) {

            Swal.fire({
                icon: 'warning',
                title: 'RUC inválido',
                text: 'El RUC solo debe contener números.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500
            });

            return;
        }

        // 2) DEBE TENER 11 DÍGITOS
        if (ruc.length !== 11) {

            Swal.fire({
                icon: 'warning',
                title: 'RUC inválido',
                text: 'El RUC debe tener 11 dígitos.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500
            });

            return;
        }

        // 3) DEBE EMPEZAR CON 10 O 20
        if (!ruc.startsWith('10') && !ruc.startsWith('20')) {

            Swal.fire({
                icon: 'warning',
                title: 'RUC inválido',
                text: 'El RUC debe iniciar con 10 o 20.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500
            });

            return;
        }

        // 4) EVITAR DOBLE CONSULTA
        if (isConsultingRuc) {
            return;
        }

        isConsultingRuc = true;

        const $ruc = $('#ruc');
        const $btnSave = $('#btnSaveSupplier');

        $ruc.prop('disabled', true);
        $btnSave.prop('disabled', true);

        $('#supplierLoading').css('display', 'flex');

        $.ajax({
            url: `${window.routes.consultarRuc}/${ruc}`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {

                if (response.status) {

                    $('#business_name').val(response.razon_social || '').trigger('input');
                    $('#address').val(response.direccion || '').trigger('input');

                } else {

                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: response.message || 'No se pudo obtener el RUC.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500
                    });

                }

            },
            error: function (xhr) {

                $('#business_name').val('');
                $('#address').val('');

                let message = 'No se pudo consultar el RUC.';

                if (xhr.status === 404) {
                    message = 'El RUC ingresado no existe.';
                } else if (xhr.status === 422) {
                    message = xhr.responseJSON?.message || 'RUC inválido.';
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: message,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3500
                });

            },
            complete: function () {

                isConsultingRuc = false;

                $ruc.prop('disabled', false);
                $btnSave.prop('disabled', false);

                $('#supplierLoading').hide();

            }
        });
    }

    $(document).on('blur', '#ruc', function () {

        consultarRucProveedor();

    });

    $(document).on('keydown', '#ruc', function (e) {

        if (e.key === 'Enter') {

            e.preventDefault();

            consultarRucProveedor();

        }

    });

    $('#supplierAccountForm').on('submit', function (e) {

        e.preventDefault();

        const btn = $('#btnSaveSupplierAccount');

        if (btn.prop('disabled')) return;

        btn.prop('disabled', true);

        btn.html(
            '<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...'
        );

        const formData = new FormData(this);

        const accountId = $('#supplier_account_id').val();

        let url;
        let method;

        if (accountId) {

            // ACTUALIZAR
            url = `/admin/supplier-accounts/${accountId}`;

            method = 'POST';

            formData.append('_method', 'PUT');

        } else {

            // REGISTRAR
            url = window.routes.supplierAccountsStore;

            method = 'POST';
        }

        $.ajax({

            url: url,

            type: method,

            data: formData,

            processData: false,

            contentType: false,

            success: function (response) {

                btn.prop('disabled', false);

                btn
                    .removeClass('btn-warning')
                    .addClass('btn-success')
                    .html(
                        '<i class="fas fa-save mr-1"></i> Guardar Cuenta'
                    );

                // RECARGAR TABLA
                if ($.fn.DataTable.isDataTable('#tableSupplierAccounts')) {

                    tableSupplierAccounts.ajax.reload(null, false);
                }

                // LIMPIAR FORMULARIO
                $('#supplier_account_id').val('');

                $('#bank_id').val('').trigger('change');

                $('#currency_id').val('').trigger('change');

                $('#account_holder').val('');

                $('#account_number').val('');

                $('#cci').val('');

                $('#is_detraction').val('NO').trigger('change');

                $('#account_status').val('ACTIVE').trigger('change');

                $('#account_observation').val('');

                Swal.fire({

                    icon: 'success',

                    title: response.message,

                    toast: true,

                    position: 'top-end',

                    showConfirmButton: false,

                    timer: 3000

                });

            },

            error: function (xhr) {

                btn.prop('disabled', false);

                if ($('#supplier_account_id').val()) {

                    btn
                        .removeClass('btn-success')
                        .addClass('btn-warning')
                        .html(
                            '<i class="fas fa-save mr-1"></i> Actualizar Cuenta'
                        );

                } else {

                    btn
                        .removeClass('btn-warning')
                        .addClass('btn-success')
                        .html(
                            '<i class="fas fa-save mr-1"></i> Guardar Cuenta'
                        );
                }

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: xhr.responseJSON?.message ||
                        'No se pudo guardar la cuenta.',

                    toast: true,

                    position: 'top-end',

                    showConfirmButton: false,

                    timer: 3500

                });

            }

        });

    });

    $(document).on('click', '.bankAccountsSupplier', function () {

        const supplierId = $(this).data('id');
        const supplierName = $(this).data('business_name');
        const supplierRuc = $(this).data('ruc');

        $('#account_supplier_id').val(supplierId);
        $('#account_supplier_name').text(supplierName || '—');
        $('#account_supplier_ruc').text(supplierRuc || '—');

        $('#supplierAccountModal').modal('show');



        const ajaxUrl = `/admin/supplier-accounts/list/${supplierId}`;

        if ($.fn.DataTable.isDataTable('#tableSupplierAccounts')) {
            tableSupplierAccounts.ajax.url(ajaxUrl).load();
            return;
        }

        tableSupplierAccounts = $('#tableSupplierAccounts').DataTable({
            processing: true,
            serverSide: true,
            ajax: ajaxUrl,
            destroy: true,
            autoWidth: false,
            responsive: true,
            columns: [
                {
                    data: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },
                {
                    data: 'bank',
                    name: 'bank'
                },
                {
                    data: 'currency',
                    name: 'currency'
                },
                {
                    data: 'account_holder',
                    name: 'account_holder'
                },
                {
                    data: 'account_number',
                    name: 'account_number'
                },
                {
                    data: 'cci',
                    name: 'cci'
                },
                {
                    data: 'is_detraction',
                    name: 'is_detraction'
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
            ]
        });
    });

    // =========================================================
    // EDITAR CUENTA BANCARIA
    // =========================================================

    /*     $(document).on('click', '.editSupplierAccount', function () {
    
            $('#supplier_account_id').val($(this).data('id'));
    
            $('#bank_id').val($(this).data('bank_id'));
    
            $('#currency_id').val($(this).data('currency_id'));
    
            $('#account_holder').val($(this).data('account_holder'));
    
            $('#account_number').val($(this).data('account_number'));
    
            $('#cci').val($(this).data('cci'));
    
            $('#is_detraction').val($(this).data('is_detraction'));
    
            $('#account_status').val($(this).data('status'));
    
            $('#account_observation').val($(this).data('observation'));
    
            $('#btnSaveSupplierAccount')
                .removeClass('btn-success')
                .addClass('btn-warning')
                .html('<i class="fas fa-save mr-1"></i> Actualizar Cuenta');
    
        }); */

    $(document).on('click', '.editSupplierAccount', function () {

        $('#supplier_account_id').val(
            $(this).data('id')
        );

        $('#bank_id').val(
            $(this).data('bank_id')
        );

        $('#currency_id').val(
            $(this).data('currency_id')
        );

        $('#account_holder').val(
            $(this).data('account_holder')
        );

        $('#account_number').val(
            $(this).data('account_number')
        );

        $('#cci').val(
            $(this).data('cci')
        );

        $('#is_detraction').val(
            $(this).data('is_detraction')
        );

        $('#account_status').val(
            $(this).data('status')
        );

        $('#account_observation').val(
            $(this).data('observation')
        );

        $('#btnSaveSupplierAccount')
            .removeClass('btn-success')
            .addClass('btn-warning')
            .html(
                '<i class="fas fa-save mr-1"></i> Actualizar Cuenta'
            );

    });


    $('#supplierAccountModal').on('shown.bs.modal', function () {
        if ($.fn.DataTable.isDataTable('#tableSupplierAccounts')) {
            tableSupplierAccounts.columns.adjust().responsive.recalc();
        }
    });

    $('#supplierAccountModal').on('hidden.bs.modal', function () {

        $('#supplier_account_id').val('');
        $('#supplierAccountForm')[0].reset();

        $('#btnSaveSupplierAccount')
            .removeClass('btn-warning')
            .addClass('btn-success')
            .html('<i class="fas fa-save mr-1"></i> Guardar Cuenta');

        if ($.fn.DataTable.isDataTable('#tableSupplierAccounts')) {
            tableSupplierAccounts.destroy();
            $('#tableSupplierAccounts tbody').empty();
        }
    });

    // =========================================================
    // ELIMINAR CUENTA BANCARIA
    // =========================================================

    $(document).on('click', '.deleteSupplierAccount', function () {

        const id = $(this).data('id');

        Swal.fire({

            title: '¿Está seguro?',

            text: 'La cuenta bancaria será eliminada.',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonText: 'Sí, eliminar',

            cancelButtonText: 'Cancelar'

        }).then((result) => {

            if (result.isConfirmed) {

                $.ajax({

                    url: `${window.routes.supplierAccountsDelete}/${id}`,

                    type: 'DELETE',

                    success: function (response) {

                        tableSupplierAccounts.ajax.reload(null, false);

                        Swal.fire({

                            icon: 'success',

                            title: response.message,

                            toast: true,

                            position: 'top-end',

                            showConfirmButton: false,

                            timer: 3000

                        });

                    },

                    error: function (xhr) {

                        Swal.fire({

                            icon: 'error',

                            title: 'Error',

                            text: xhr.responseJSON?.message ||
                                'No se pudo eliminar la cuenta.'

                        });

                    }

                });

            }

        });

    });

});