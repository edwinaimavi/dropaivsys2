let tableCustomer;

let tableCustomerBranches;

const submitLocks = {
    customerSave: false
};

const customerLoading =
    document.getElementById('customerLoading');

function lock(action) {
    if (submitLocks[action]) return false;
    submitLocks[action] = true;
    return true;
}

function unlock(action) {
    submitLocks[action] = false;
}

document.addEventListener("DOMContentLoaded", function () {
    $('#customerForm').on('keypress', function (e) {

        if (e.key === 'Enter' &&
            $(e.target).attr('id') === 'document_number') {

            e.preventDefault();
            return false;
        }

    });
    // ============================
    // DATATABLE CUSTOMERS
    // ============================
    tableCustomer = $('#tableCustomer').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.customerList,
            type: 'GET'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' },
            { data: 'full_name' },
            { data: 'document_type' },
            { data: 'document_number' },
            { data: 'phone' },
            { data: 'email' },
            { data: 'status_badge', orderable: false },
            { data: 'actions', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        language: {
            url: "/vendor/datatables/js/i18n/es-ES.json"
        },
        preDrawCallback: function () {
            divLoading && divLoading.classList.remove('d-none');
        },
        drawCallback: function () {
            divLoading && divLoading.classList.add('d-none');
        }
    });

    // ============================
    // CSRF
    // ============================
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // =====================================
    // UBIGEO SELECT2
    // =====================================

    $('#branch_ubigeo_id').select2({

        theme: 'bootstrap4',

        width: '100%',

        dropdownParent: $('#customerBranchModal'),

        placeholder: 'Buscar ubigeo...',

        allowClear: true,

        minimumInputLength: 2,

        ajax: {

            url: window.routes.searchUbigeo,

            dataType: 'json',

            delay: 250,

            data: function (params) {

                return {
                    search: params.term
                };

            },

            processResults: function (data) {

                return {

                    results: $.map(data, function (item) {

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

    // ============================
    // GUARDAR / ACTUALIZAR
    // ============================
    $('#customerForm').on('submit', function (e) {
        e.preventDefault();

        if (!lock('customerSave')) return;

        const btn = $('#btnSaveCustomer');

        if (btn.prop('disabled')) {
            unlock('customerSave');
            return;
        }

        btn.prop('disabled', true);

        btn.html(`
    <span class="spinner-border spinner-border-sm mr-1"></span>
    Guardando...
`);

        let $form = $(this);
        let id = $form.attr('data-id');
        $('#document_type').prop('disabled', false);

        let formData = $form.serialize();

        let url;
        let method = 'POST';

        if (id) {
            url = `/admin/customers/${id}`;
            formData += '&_method=PUT';
        } else {
            url = '/admin/customers';
        }



        $.ajax({
            url: url,
            type: method,
            data: formData,

            success: function (res) {

                unlock('customerSave');

                $('#customerModal').modal('hide');
                $form.trigger('reset').removeAttr('data-id');

                tableCustomer.ajax.reload(null, false);

                btn.prop('disabled', false);

                btn.html(`
    <i class="fas fa-save mr-1"></i>
    Guardar Cliente
`);

                Swal.fire({
                    icon: 'success',
                    title: res.message || 'Cliente guardado',
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
            },

            error: function (xhr) {

                unlock('customerSave');

                btn.prop('disabled', false);

                btn.html(`
        <i class="fas fa-save mr-1"></i>
        Guardar Cliente
    `);

                if (xhr.status === 422) {

                    const errors = xhr.responseJSON.errors;

                    $('.is-invalid').removeClass('is-invalid');
                    $('.invalid-feedback').text('');

                    for (let field in errors) {

                        $('#' + field).addClass('is-invalid');
                        $('#' + field + '-error').text(errors[field][0]);

                    }

                } else {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'Error al guardar el cliente',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500
                    });

                }
            }
        });

    });

    // ============================
    // EDITAR
    // ============================
    $(document).on('click', '.editCustomer', function () {

        const $btn = $(this);

        $('#customerForm').attr('data-id', $btn.data('id'));

        // =========================
        // DATOS GENERALES
        // =========================

        $('#person_type').val(
            $btn.data('person_type')
        );

        $('#document_number').val(
            $btn.data('document_number')
        );

        $('#document_type').val(
            $btn.data('document_type')
        );

        // =========================
        // PERSONA NATURAL
        // =========================

        $('#first_name').val(
            $btn.data('first_name')
        );

        $('#last_name').val(
            $btn.data('last_name')
        );

        // =========================
        // PERSONA JURIDICA
        // =========================

        $('#business_name').val(
            $btn.data('business_name')
        );

        // =========================
        // COMERCIAL
        // =========================

        $('#channel').val(
            $btn.data('channel')
        );

        $('#subchannel').val(
            $btn.data('subchannel')
        );

        $('#withholding_agent').val(
            $btn.data('withholding_agent')
        );

        // =========================
        // CONTACTO
        // =========================

        $('#phone').val(
            $btn.data('phone')
        );

        $('#email').val(
            $btn.data('email')
        );

        $('#address').val(
            $btn.data('address')
        );

        // =========================
        // ESTADO
        // =========================

        $('#status').val(
            $btn.data('status') == 1 ? '1' : '0'
        );
        // IMPORTANTE
        toggleFields();

        $('#customerModal').modal('show');
    });

    // ============================
    // RESET MODAL
    // ============================
    $('#customerModal').on('show.bs.modal', function () {

        const $form = $('#customerForm');

        if (!$form.attr('data-id')) {

            $form[0].reset();
            $('#channel').val('');
            $('#subchannel').val('');
            $('#withholding_agent').val('0');

            $form.find('.is-invalid').removeClass('is-invalid');
            $form.find('.invalid-feedback').text('');

            $('#status').val('1');
        }
    });


    // ============================
    // VER CLIENTE
    // ============================

    $(document).on('click', '.viewCustomer', function () {

        const btn = $(this);

        const personType = btn.data('person_type');

        const fullName =
            personType === 'juridica'
                ? btn.data('business_name')
                : (btn.data('first_name') + ' ' + btn.data('last_name'));

        $('#vc_id').text(btn.data('id') || '—');

        $('#vc_full_name').text(fullName || '—');

        $('#vc_document').text(
            (btn.data('document_type') || '') +
            ' - ' +
            (btn.data('document_number') || '')
        );

        $('#vc_person_type').text(
            personType === 'juridica'
                ? 'Persona Jurídica'
                : 'Persona Natural'
        );

        $('#vc_document_type').text(btn.data('document_type') || '—');

        $('#vc_document_number').text(btn.data('document_number') || '—');

        $('#vc_phone').text(btn.data('phone') || '—');

        $('#vc_email').text(btn.data('email') || '—');
        $('#vc_channel').text(
            btn.data('channel') || '-'
        );

        $('#vc_channel_card').text(
            btn.data('channel') || '-'
        );

        $('#vc_subchannel').text(
            btn.data('subchannel') || '-'
        );

        $('#vc_withholding_agent').text(
            btn.data('withholding_agent') == 1 ? 'SI' : 'NO'
        );

        $('#vc_address').text(btn.data('address') || '—');

        $('#vc_created_at').text(btn.data('created_at') || '—');

        $('#vc_updated_at').text(btn.data('updated_at') || '—');

        $('#vc_created_by').text(btn.data('created_by') || '—');

        $('#vc_updated_by').text(btn.data('updated_by') || '—');

        $('#vc_created_by_user').text(btn.data('created_by') || '—');

        // STATUS
        if (btn.data('status') == 1) {

            $('#vc_status_badge')
                .removeClass()
                .addClass('badge badge-success py-2 px-3')
                .text('Activo');

        } else {

            $('#vc_status_badge')
                .removeClass()
                .addClass('badge badge-danger py-2 px-3')
                .text('Inactivo');
        }

        $('#viewCustomerModal').modal('show');

    });
    // ============================
    // ELIMINAR
    // ============================
    $(document).on('click', '.deleteCustomer', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar cliente?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: `/admin/customers/${id}`,
                type: 'DELETE',

                success: function (res) {

                    tableCustomer.ajax.reload(null, false);

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Cliente eliminado',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                },

                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar el cliente', 'error');
                }
            });
        });
    });


    function toggleFields() {

        let personType = $('#person_type').val();
        let docType = $('#document_type').val();

        // RESET
        $('#naturalFields').addClass('d-none');
        $('#businessFields').addClass('d-none');

        // =========================
        // PERSONA NATURAL
        // =========================
        if (personType === 'natural') {

            // habilitar selector
            $('#document_type').prop('disabled', false);

            if (docType === 'DNI' || docType === 'CE') {

                $('#naturalFields').removeClass('d-none');

            }

            if (docType === 'RUC') {

                $('#businessFields').removeClass('d-none');

            }

        }

        // =========================
        // PERSONA JURÍDICA
        // =========================
        if (personType === 'juridica') {

            // 🔥 dejar solo RUC
            $('#document_type')
                .html('<option value="RUC">RUC</option>')
                .val('RUC')
                .prop('disabled', true);

            $('#businessFields').removeClass('d-none');
        }
    }

    // EVENTOS
    $('#person_type, #document_type').on('change', function () {
        toggleFields();
    });

    // INICIAL
    toggleFields();

    $('#person_type').on('change', function () {

        if ($(this).val() === 'juridica') {

            $('#document_type')
                .html('<option value="RUC">RUC</option>')
                .val('RUC')
                .prop('disabled', true);

        } else {

            // 🔥 restaurar opciones
            $('#document_type')
                .html(`
                <option value="">-- Seleccionar --</option>
                <option value="DNI">DNI</option>
                <option value="CE">CE</option>
                <option value="RUC">RUC</option>
            `)
                .prop('disabled', false);
        }

        toggleFields();
    });


    // ==============================
    // CONSULTA DNI / RUC
    // ==============================

    const $documentType = $('#document_type');
    const $documentNumber = $('#document_number');

    function buscarDocumento() {

        let tipo = $documentType.val();
        let numero = $documentNumber.val().trim();

        let personType = $('#person_type').val();

        // ====================================
        // VALIDAR PERSONA JURIDICA
        // ====================================

        // ====================================
        // VALIDACIONES DOCUMENTOS
        // ====================================

        // 🔥 PERSONA JURIDICA
        if (personType === 'juridica') {

            // Debe tener 11 dígitos
            if (numero.length !== 11) {

                Swal.fire({
                    icon: 'warning',
                    title: 'RUC inválido',
                    text: 'El RUC debe tener 11 dígitos'
                });

                return;
            }

            // Debe empezar con 20
            if (!numero.startsWith('20')) {

                Swal.fire({
                    icon: 'warning',
                    title: 'RUC inválido',
                    text: 'Una empresa debe tener un RUC que empiece con 20'
                });

                return;
            }
        }

        // 🔥 PERSONA NATURAL
        if (personType === 'natural') {

            // ======================
            // DNI
            // ======================
            if (tipo === 'DNI') {

                if (numero.length !== 8) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'DNI inválido',
                        text: 'El DNI debe tener 8 dígitos'
                    });

                    return;
                }
            }

            // ======================
            // RUC NATURAL
            // ======================
            if (tipo === 'RUC') {

                // Debe tener 11
                if (numero.length !== 11) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'RUC inválido',
                        text: 'El RUC debe tener 11 dígitos'
                    });

                    return;
                }

                // Debe empezar con 10
                if (!numero.startsWith('10')) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'RUC inválido',
                        text: 'Una persona natural con RUC debe empezar con 10'
                    });

                    return;
                }
            }
        }

        if (!numero || !/^\d+$/.test(numero)) return;

        if (numero.length !== 8 && numero.length !== 11) return;

        let url = window.routes.consultarDocumento.replace('DOC_PLACEHOLDER', numero);


        // LOADER

        customerLoading.style.display = 'flex';

        $('#document_number').prop('disabled', true);

        if (numero.length === 8) {

            $('#loadingTitle').text('Consultando DNI');

            $('#loadingText').text(
                'Buscando información en RENIEC...'
            );

        } else {

            $('#loadingTitle').text('Consultando RUC');

            $('#loadingText').text(
                'Buscando información en SUNAT...'
            );

        }

        $.ajax({
            url: url,
            type: 'GET',

            success: function (res) {

                if (!res.status) {

                    Swal.fire({
                        icon: 'warning',
                        title: 'No encontrado',
                        text: res.message || 'No se encontró el documento',
                    });

                    return;
                }

                // ======================
                // DNI
                // ======================
                if (res.type === 'DNI') {

                    let p = res.data;

                    $('#first_name').val(p.nombres || '');
                    $('#last_name').val(
                        (p.apellidoPaterno || '') + ' ' + (p.apellidoMaterno || '')
                    );

                    $('#business_name').val('');
                }

                // ======================
                // RUC
                // ======================
                if (res.type === 'RUC') {

                    let e = res.data;

                    $('#business_name').val(e.razonSocial || e.nombre || '');

                    $('#first_name').val('');
                    $('#last_name').val('');
                    $('#address').val(
                        (e.direccion || '') +
                        ' - ' + (e.distrito || '') +
                        ' - ' + (e.provincia || '') +
                        ' - ' + (e.departamento || '')
                    );

                    // forzar tipo documento
                    $('#document_type').val('RUC');

                    // opcional: autoseleccionar jurídica
                    $('#person_type').val('juridica').trigger('change');
                }
            },

            error: function (xhr) {

                let msg = 'No se pudo consultar el documento';

                // 🔥 mensaje backend
                if (xhr.responseJSON) {

                    // mensaje normal
                    if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }

                    // errores laravel
                    if (xhr.responseJSON.errors) {

                        let firstError = Object.values(xhr.responseJSON.errors)[0];

                        if (Array.isArray(firstError)) {
                            msg = firstError[0];
                        }
                    }
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: msg,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3500
                });
                // 🔥 limpiar campos
                $('#first_name').val('');
                $('#last_name').val('');
                $('#business_name').val('');
                $('#address').val('');
            },

            complete: function () {

                customerLoading.style.display = 'none';

                $('#document_number').prop('disabled', false);

            }
        });
    }

    // ENTER
    $documentNumber.on('keydown', function (e) {

        if (e.key === 'Enter') {

            e.preventDefault();
            e.stopPropagation();

            buscarDocumento();

            return false;
        }

    });

    // ============================
    // ABRIR MODAL SEDES
    // ============================
    function loadCustomerBranches(customerId) {
        if ($.fn.DataTable.isDataTable('#tableCustomerBranches')) {
            tableCustomerBranches.destroy();
            $('#tableCustomerBranches tbody').empty();
        }

        tableCustomerBranches = $('#tableCustomerBranches').DataTable({
            processing: true,
            serverSide: true,
            ajax: `${window.routes.customerBranchList}/${customerId}`,
            responsive: true,
            autoWidth: false,
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'branch_name' },
                { data: 'branch_type' },
                { data: 'ubigeo_name' },
                { data: 'voucher_type' },
                { data: 'payment_condition' },
                { data: 'is_main_badge', orderable: false, searchable: false },
                { data: 'status_badge', orderable: false, searchable: false },
                { data: 'acciones', orderable: false, searchable: false }
            ]
        });
    }

    function resetBranchForm(keepCustomer = true) {
        const customerId = $('#branch_customer_id').val();

        $('#customerBranchForm')[0].reset();
        $('#customer_branch_id').val('');

        if (keepCustomer) {
            $('#branch_customer_id').val(customerId);
        } else {
            $('#branch_customer_id').val('');
        }

        $('#branch_ubigeo_id').val(null).trigger('change');

        $('#btnSaveCustomerBranch')
            .removeClass('btn-warning')
            .addClass('btn-success')
            .html('<i class="fas fa-save mr-1"></i> Guardar Sede');
    }
    $(document).on('click', '.branchCustomer', function () {
        const btn = $(this);
        const customerId = btn.data('id');

        let nombre = '';
        if (btn.data('person_type') === 'juridica') {
            nombre = btn.data('business_name') || '';
        } else {
            nombre = (btn.data('first_name') || '') + ' ' + (btn.data('last_name') || '');
        }

        $('#branch_customer_id').val(customerId);
        $('#branch_customer_name').text(nombre || '-');
        $('#branch_customer_document').text(btn.data('document_number') || '-');

        resetBranchForm(true);
        $('#customerBranchModal').modal('show');

        $('#customerBranchModal').one('shown.bs.modal', function () {
            loadCustomerBranches(customerId);
        });

        $('#contact_customer_name').text(nombre || '—');
        $('#contact_branch_name').text('—');
        $('#contact_branch_select').html('<option value="">Seleccione</option>');
        loadContactBranches(customerId);
    });

    $('#customerBranchModal').on('shown.bs.modal', function () {
        if ($.fn.DataTable.isDataTable('#tableCustomerBranches')) {
            tableCustomerBranches.columns.adjust().responsive.recalc();
        }
    });
    /*   $('#customerBranchModal').on('hidden.bs.modal', function () {
          resetBranchForm(false);
  
          if ($.fn.DataTable.isDataTable('#tableCustomerBranches')) {
              tableCustomerBranches.destroy();
              $('#tableCustomerBranches tbody').empty();
          }
      }); */

    //GUARDAR / ACTUALIZAR SEDE

    $('#customerBranchForm').on('submit', function (e) {

        e.preventDefault();

        const btn = $('#btnSaveCustomerBranch');

        if (btn.prop('disabled')) return;

        const form = $(this);
        const branchId = $('#customer_branch_id').val();
        const formData = new FormData(this);

        let url = window.routes.customerBranchStore;
        let method = 'POST';

        if (branchId) {
            url = `${window.routes.customerBranchUpdate}/${branchId}`;
            formData.append('_method', 'PUT');
        }

        btn.prop('disabled', true);
        btn.html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');

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
                    .html('<i class="fas fa-save mr-1"></i> Guardar Sede');

                if ($.fn.DataTable.isDataTable('#tableCustomerBranches')) {
                    tableCustomerBranches.ajax.reload(null, false);
                }


                // RECARGAR COMBO DE SEDES DE CONTACTOS
                loadContactBranches($('#branch_customer_id').val());
                resetBranchForm(true);

                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Sede guardada correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            },
            error: function (xhr) {

                btn.prop('disabled', false);
                btn
                    .removeClass('btn-warning')
                    .addClass('btn-success')
                    .html('<i class="fas fa-save mr-1"></i> Guardar Sede');

                let msg = xhr.responseJSON?.message || 'No se pudo guardar la sede.';

                if (xhr.status === 422 && xhr.responseJSON?.errors) {
                    const firstError = Object.values(xhr.responseJSON.errors)[0];
                    if (Array.isArray(firstError)) {
                        msg = firstError[0];
                    }
                }

                Swal.fire({
                    icon: 'warning',
                    title: 'Atención',
                    text: msg,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3500
                });
            }
        });
    });

    // EDITAR SEDE

    $(document).on('click', '.editCustomerBranch', function () {

        const btn = $(this);

        $('#customer_branch_id').val(btn.data('id'));
        $('#branch_customer_id').val(btn.data('customer_id'));

        $('#branch_name').val(btn.data('branch_name') || '');
        $('#branch_type').val(btn.data('branch_type') || '');
        $('#branch_phone').val(btn.data('phone') || '');
        $('#branch_email').val(btn.data('email') || '');
        $('#branch_address').val(btn.data('address') || '');
        $('#branch_reference').val(btn.data('reference') || '');
        $('#reference').val(btn.data('reference') || '');
        $('#voucher_type').val(btn.data('voucher_type') || 'FACTURA');
        $('#generate_guide').val(btn.data('generate_guide') || 'NO');
        $('#payment_condition').val(btn.data('payment_condition') || 'CONTADO');
        $('#is_main').val(btn.data('is_main') == 1 ? '1' : '0');
        $('#branch_status').val(btn.data('status') == 1 ? '1' : '0');

        const ubigeoId = btn.data('ubigeo_id');
        const ubigeoText = btn.data('ubigeo_text');

        $('#branch_ubigeo_id').empty().trigger('change');

        if (ubigeoId && ubigeoText) {
            const option = new Option(ubigeoText, ubigeoId, true, true);
            $('#branch_ubigeo_id').append(option).trigger('change');
        }

        $('#btnSaveCustomerBranch')
            .removeClass('btn-success')
            .addClass('btn-warning')
            .html('<i class="fas fa-save mr-1"></i> Actualizar Sede');
    });

    // ELIMINAR SEDE

    $(document).on('click', '.deleteCustomerBranch', function () {

        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar sede?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: `${window.routes.customerBranchDelete}/${id}`,
                type: 'DELETE',
                success: function (res) {
                    if ($.fn.DataTable.isDataTable('#tableCustomerBranches')) {
                        tableCustomerBranches.ajax.reload(null, false);
                    }

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Sede eliminada correctamente.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                },
                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar la sede.', 'error');
                }
            });
        });
    });

    // AL SALIR
    $documentNumber.on('blur', function () {
        buscarDocumento();
    });



    // ============================
    // CONTACTOS DE SEDE
    // ============================
    let tableCustomerBranchContacts = null;
    let currentBranchForContacts = null;

    function getContactBranchLabel(branchId) {
        if (!branchId) return '—';

        const $option = $('#contact_branch_select option[value="' + branchId + '"]');
        if ($option.length) {
            return $option.text();
        }

        return $('#branch_name').val() || $('#contact_branch_name').text() || '—';
    }

    function syncContactContext(branchId, branchName = '') {

        currentBranchForContacts =
            branchId ? String(branchId) : null;

        $('#contact_customer_branch_id')
            .val(currentBranchForContacts || '');

        $('#contact_customer_name')
            .text(
                $('#branch_customer_name').text() || '—'
            );

        $('#contact_branch_name')
            .text(
                branchName || getContactBranchLabel(branchId)
            );

    }
    function loadCustomerBranchContacts(branchId) {
        if (!branchId) {
            if ($.fn.DataTable.isDataTable('#tableCustomerBranchContacts')) {
                tableCustomerBranchContacts.destroy();
                $('#tableCustomerBranchContacts tbody').empty();
            }
            return;
        }

        if ($.fn.DataTable.isDataTable('#tableCustomerBranchContacts')) {
            tableCustomerBranchContacts.destroy();
            $('#tableCustomerBranchContacts tbody').empty();
        }

        tableCustomerBranchContacts = $('#tableCustomerBranchContacts').DataTable({
            processing: true,
            serverSide: true,
            ajax: `${window.routes.customerBranchContactList}/${branchId}`,
            responsive: true,
            autoWidth: false,
            language: {
                url: "/vendor/datatables/js/i18n/es-ES.json"
            },
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'contact_name' },
                { data: 'phone' },
                { data: 'email' },
                { data: 'address' },
                { data: 'reference' },
                { data: 'status_badge', orderable: false, searchable: false },
                { data: 'acciones', orderable: false, searchable: false }
            ]
        });
    }

    function resetContactForm(keepBranch = true) {
        const branchId =
            $('#contact_customer_branch_id').val() ||
            currentBranchForContacts ||
            '';

        $('#customerBranchContactForm')[0].reset();
        $('#customer_branch_contact_id').val('');

        if (keepBranch) {
            $('#contact_customer_branch_id').val(branchId);
        } else {
            $('#contact_customer_branch_id').val('');
        }

        $('#contact_status').val('1');

        $('#btnSaveCustomerBranchContact')
            .removeClass('btn-warning')
            .addClass('btn-primary')
            .html('<i class="fas fa-save mr-1"></i> Guardar Contacto');
    }

    // Cuando se abre la pestaña de contactos, carga la tabla si ya hay una sede seleccionada
    $('a[data-toggle="tab"][href="#contactsPane"]').on('shown.bs.tab', function () {
        const branchId =
            $('#contact_customer_branch_id').val() ||
            currentBranchForContacts ||
            '';

        if (branchId) {
            loadCustomerBranchContacts(branchId);

            setTimeout(function () {
                if ($.fn.DataTable.isDataTable('#tableCustomerBranchContacts')) {
                    tableCustomerBranchContacts.columns.adjust().responsive.recalc();
                }
            }, 100);
        }
    });

    /*     // Si el usuario cambia la sede desde el selector del tab Contactos
        $(document).on('change', '#contact_branch_select', function () {
            const branchId = $(this).val();
            const branchName = $('#contact_branch_select option:selected').text();
    
            syncContactContext(branchId, branchName);
    
            if (branchId) {
                loadCustomerBranchContacts(branchId);
            }
        }); */

    // Si editas una sede, deja esa sede como contexto activo para contactos
    $(document).on('click', '.editCustomerBranch', function () {
        const btn = $(this);

        syncContactContext(
            btn.data('id'),
            btn.data('branch_name') || ''
        );
    });

    // Si guardas una sede nueva, deja esa sede como contexto activo para contactos
    // (si el backend devuelve data.id y data.branch_name)
    // Esto puedes dejarlo tal cual dentro del success del guardado de sedes:
    // if (response.data && response.data.id) {
    //     syncContactContext(response.data.id, response.data.branch_name || $('#branch_name').val());
    // }

    // GUARDAR / ACTUALIZAR CONTACTO
    $('#customerBranchContactForm').on('submit', function (e) {
        e.preventDefault();

        const btn = $('#btnSaveCustomerBranchContact');

        if (btn.prop('disabled')) return;

        const branchId = $('#contact_branch_select').val();

        if (!branchId) {
            Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: 'Primero seleccione una sede.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            return;
        }

        $('#contact_customer_branch_id').val(branchId);

        const contactId = $('#customer_branch_contact_id').val();
        const formData = new FormData(this);
        formData.set('customer_branch_id', branchId);

        let url = window.routes.customerBranchContactStore;
        let method = 'POST';

        if (contactId) {
            url = `${window.routes.customerBranchContactUpdate}/${contactId}`;
            formData.append('_method', 'PUT');
        }

        btn.prop('disabled', true);
        btn.html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');

        $.ajax({
            url: url,
            type: method,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                btn.prop('disabled', false);
                btn.removeClass('btn-warning').addClass('btn-primary')
                    .html('<i class="fas fa-save mr-1"></i> Guardar Contacto');

                if ($.fn.DataTable.isDataTable('#tableCustomerBranchContacts')) {
                    tableCustomerBranchContacts.ajax.reload(null, false);
                } else {
                    loadCustomerBranchContacts(branchId);
                }
                resetContactForm(true);

                $('#contact_branch_select').val(branchId);
                $('#contact_customer_branch_id').val(branchId);

                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Contacto guardado correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            }
        });
    });

    // EDITAR CONTACTO
    $(document).on('click', '.editCustomerBranchContact', function () {
        const btn = $(this);

        $('#customer_branch_contact_id').val(btn.data('id'));
        $('#contact_customer_branch_id').val(btn.data('customer_branch_id'));

        const branchId = btn.data('customer_branch_id');
        const branchName = getContactBranchLabel(branchId);

        syncContactContext(branchId, branchName);

        $('#contact_name').val(btn.data('contact_name') || '');
        $('#contact_phone').val(btn.data('phone') || '');
        $('#contact_email').val(btn.data('email') || '');
        $('#contact_address').val(btn.data('address') || '');
        $('#contact_reference').val(btn.data('reference') || '');
        $('#contact_status').val(btn.data('status') == 1 ? '1' : '0');

        $('#btnSaveCustomerBranchContact')
            .removeClass('btn-primary')
            .addClass('btn-warning')
            .html('<i class="fas fa-save mr-1"></i> Actualizar Contacto');

        $('a[href="#contactsPane"]').tab('show');
    });

    // ELIMINAR CONTACTO
    $(document).on('click', '.deleteCustomerBranchContact', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Eliminar contacto?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: `${window.routes.customerBranchContactDelete}/${id}`,
                type: 'DELETE',
                success: function (res) {
                    const branchId =
                        $('#contact_customer_branch_id').val() ||
                        currentBranchForContacts ||
                        '';

                    if ($.fn.DataTable.isDataTable('#tableCustomerBranchContacts')) {
                        tableCustomerBranchContacts.ajax.reload(null, false);
                    }

                    Swal.fire({
                        icon: 'success',
                        title: res.message || 'Contacto eliminado correctamente.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });

                    if (branchId) {
                        syncContactContext(branchId, getContactBranchLabel(branchId));
                    }
                },
                error: function () {
                    Swal.fire('Error', 'No se pudo eliminar el contacto.', 'error');
                }
            });
        });
    });

    function loadContactBranches(customerId) {
        $.ajax({
            url: `${window.routes.customerBranchesByCustomer}/${customerId}`,
            type: 'GET',
            success: function (response) {
                const rows = Array.isArray(response)
                    ? response
                    : (response.data || []);

                $('#contact_branch_select').html('<option value="">Seleccione</option>');

                rows.forEach(function (branch) {
                    $('#contact_branch_select').append(`
                    <option value="${branch.id}">
                        ${branch.branch_name}
                    </option>
                `);
                });

                if (rows.length > 0) {
                    const firstBranch = rows[0];

                    $('#contact_branch_select').val(firstBranch.id);
                    $('#contact_customer_branch_id').val(firstBranch.id);
                    $('#contact_branch_name').text(firstBranch.branch_name);
                    currentBranchForContacts = firstBranch.id;

                    loadCustomerBranchContacts(firstBranch.id);
                } else {
                    $('#contact_customer_branch_id').val('');
                    $('#contact_branch_name').text('—');
                    currentBranchForContacts = null;

                    if ($.fn.DataTable.isDataTable('#tableCustomerBranchContacts')) {
                        tableCustomerBranchContacts.destroy();
                        $('#tableCustomerBranchContacts tbody').empty();
                    }
                }
            }
        });
    }


    $(document).on('change', '#contact_branch_select', function () {
        const branchId = $(this).val();
        const branchName = $('#contact_branch_select option:selected').text();

        $('#contact_customer_branch_id').val(branchId);
        $('#contact_branch_name').text(branchName);

        currentBranchForContacts = branchId || null;

        if (branchId) {
            loadCustomerBranchContacts(branchId);
        }
    });


    // =====================================================
    // VISTA DETALLADA: SEDES + CONTACTOS POR SEDE
    // =====================================================
    let tableViewCustomerBranches = null;
    let tableViewCustomerContacts = null;
    let viewCurrentBranchId = null;

    function destroyViewCustomerTables() {
        if ($.fn.DataTable.isDataTable('#vc_branches_table')) {
            tableViewCustomerBranches.destroy();
            $('#vc_branches_table tbody').empty();
        }

        if ($.fn.DataTable.isDataTable('#vc_contacts_table')) {
            tableViewCustomerContacts.destroy();
            $('#vc_contacts_table tbody').empty();
        }

        viewCurrentBranchId = null;
    }

    function loadViewCustomerContacts(branchId) {
        if (!branchId) {
            if ($.fn.DataTable.isDataTable('#vc_contacts_table')) {
                tableViewCustomerContacts.destroy();
                $('#vc_contacts_table tbody').empty();
            }
            return;
        }

        if ($.fn.DataTable.isDataTable('#vc_contacts_table')) {
            tableViewCustomerContacts.destroy();
            $('#vc_contacts_table tbody').empty();
        }

        tableViewCustomerContacts = $('#vc_contacts_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: `${window.routes.customerBranchContactList}/${branchId}`,
            responsive: true,
            autoWidth: false,
            pageLength: 5,
            language: {
                url: "/vendor/datatables/js/i18n/es-ES.json"
            },
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'contact_name' },
                { data: 'phone' },
                { data: 'email' },
                { data: 'address' },
                { data: 'reference' },
                { data: 'status_badge', orderable: false, searchable: false }
            ]
        });
    }

    function selectViewBranch(branchData, rowEl = null) {
        if (!branchData) return;

        viewCurrentBranchId = branchData.id;

        $('#vc_branches_table tbody tr').removeClass('table-primary');
        if (rowEl) {
            $(rowEl).addClass('table-primary');
        }

        loadViewCustomerContacts(branchData.id);
    }

    function loadViewCustomerBranches(customerId) {
        if ($.fn.DataTable.isDataTable('#vc_branches_table')) {
            tableViewCustomerBranches.destroy();
            $('#vc_branches_table tbody').empty();
        }

        viewCurrentBranchId = null;

        tableViewCustomerBranches = $('#vc_branches_table').DataTable({
            processing: true,
            serverSide: true,
            ajax: `${window.routes.customerBranchList}/${customerId}`,
            responsive: true,
            autoWidth: false,
            pageLength: 5,
            language: {
                url: "/vendor/datatables/js/i18n/es-ES.json"
            },
            columns: [
                { data: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'branch_name' },
                { data: 'branch_type' },
                { data: 'ubigeo_name' },
                { data: 'voucher_type' },
                { data: 'payment_condition' },
                { data: 'is_main_badge', orderable: false, searchable: false },
                { data: 'status_badge', orderable: false, searchable: false }
            ],
            drawCallback: function () {
                const api = this.api();
                const rows = api.rows({ page: 'current' }).data().toArray();

                if (!viewCurrentBranchId && rows.length > 0) {
                    selectViewBranch(rows[0]);
                }
            }
        });

        $('#vc_branches_table tbody')
            .off('click')
            .on('click', 'tr', function () {
                const data = tableViewCustomerBranches.row(this).data();
                if (!data) return;
                selectViewBranch(data, this);
            });
    }

    $(document).on('click', '.viewCustomer', function () {
        const btn = $(this);
        const customerId = btn.data('id');
        const personType = btn.data('person_type');

        const fullName =
            personType === 'juridica'
                ? btn.data('business_name')
                : (btn.data('first_name') + ' ' + btn.data('last_name'));

        $('#vc_id').text(btn.data('id') || '—');
        $('#vc_full_name').text(fullName || '—');
        $('#vc_document').text(
            (btn.data('document_type') || '') + ' - ' + (btn.data('document_number') || '')
        );

        $('#vc_person_type').text(
            personType === 'juridica' ? 'Persona Jurídica' : 'Persona Natural'
        );

        $('#vc_document_type').text(btn.data('document_type') || '—');
        $('#vc_document_number').text(btn.data('document_number') || '—');
        $('#vc_phone').text(btn.data('phone') || '—');
        $('#vc_email').text(btn.data('email') || '—');
        $('#vc_channel').text(btn.data('channel') || '-');
        $('#vc_channel_card').text(btn.data('channel') || '-');
        $('#vc_subchannel').text(btn.data('subchannel') || '-');
        $('#vc_withholding_agent').text(btn.data('withholding_agent') == 1 ? 'SI' : 'NO');
        $('#vc_address').text(btn.data('address') || '—');
        $('#vc_created_at').text(btn.data('created_at') || '—');
        $('#vc_updated_at').text(btn.data('updated_at') || '—');
        $('#vc_created_by').text(btn.data('created_by') || '—');
        $('#vc_updated_by').text(btn.data('updated_by') || '—');
        $('#vc_created_by_user').text(btn.data('created_by') || '—');

        if (btn.data('status') == 1) {
            $('#vc_status_badge')
                .removeClass()
                .addClass('badge badge-success py-2 px-3')
                .text('Activo');
        } else {
            $('#vc_status_badge')
                .removeClass()
                .addClass('badge badge-danger py-2 px-3')
                .text('Inactivo');
        }

        destroyViewCustomerTables();

        $('#viewCustomerModal')
            .off('shown.bs.modal.view')
            .one('shown.bs.modal.view', function () {
                loadViewCustomerBranches(customerId);
            });

        $('#viewCustomerModal').modal('show');
    });

    $('#viewCustomerModal').on('hidden.bs.modal', function () {
        destroyViewCustomerTables();
    });

});

