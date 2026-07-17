let tableElectronicInvoiceSetting;

document.addEventListener('DOMContentLoaded', function () {
    $.fn.dataTable.ext.errMode = 'none';

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    tableElectronicInvoiceSetting = $('#tableElectronicInvoiceSetting').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.electronicInvoiceSettingList,
            error: function () {
                toast('error', 'No se pudo cargar la información. Revise la conexión o contacte al administrador.');
            }
        },
        columns: [
            { data: 'company', name: 'companies.business_name' },
            { data: 'provider', name: 'electronic_invoice_settings.provider' },
            { data: 'environment_label', name: 'electronic_invoice_settings.environment' },
            { data: 'ruc', name: 'electronic_invoice_settings.ruc', defaultContent: '-' },
            { data: 'business_name', name: 'electronic_invoice_settings.business_name', defaultContent: '-' },
            { data: 'is_active', name: 'electronic_invoice_settings.is_active' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        }
    });

    $('#electronicInvoiceSettingForm').on('submit', function (event) {
        event.preventDefault();
        clearSettingErrors();

        const id = $('#setting_id').val();
        const url = id
            ? `${window.routes.electronicInvoiceSettingBase}/${id}`
            : window.routes.electronicInvoiceSettingStore;
        const data = $(this).serializeArray();

        if (id) {
            data.push({ name: '_method', value: 'PUT' });
        }

        $.ajax({
            url,
            type: 'POST',
            data: $.param(data),
            success: function (response) {
                $('#electronicInvoiceSettingModal').modal('hide');
                tableElectronicInvoiceSetting.ajax.reload(null, false);
                toast('success', response.message || 'Configuración guardada correctamente.');
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    showSettingErrors(xhr.responseJSON.errors || {});
                    return;
                }

                toast('error', xhr.responseJSON?.message || 'No se pudo guardar la configuración.');
            }
        });
    });

    $('#electronicInvoiceSettingModal').on('hidden.bs.modal', resetSettingForm);

    $(document).on('click', '.editElectronicInvoiceSetting', function () {
        loadSetting($(this).data('id'), true);
    });

    $(document).on('click', '.viewElectronicInvoiceSetting', function () {
        loadSetting($(this).data('id'), false);
    });
});

function loadSetting(id, editable) {
    $.get(`${window.routes.electronicInvoiceSettingBase}/${id}`, function (response) {
        const item = response.data;

        $('#setting_id').val(item.id);
        $('#setting_company_id').val(item.company_id || '');
        $('#setting_provider').val(item.provider || 'apisperu');
        $('#setting_environment').val(item.environment || 'beta');
        $('#setting_api_base_url').val(item.api_base_url || '');
        $('#setting_api_token').val('').attr('placeholder', item.has_api_token ? '******** (conservado)' : '');
        $('#setting_user_token').val('').attr('placeholder', item.has_user_token ? '******** (conservado)' : '');
        $('#setting_ruc').val(item.ruc || '');
        $('#setting_business_name').val(item.business_name || '');
        $('#setting_trade_name').val(item.trade_name || '');
        $('#setting_address').val(item.address || '');
        $('#setting_ubigeo').val(item.ubigeo || '');
        $('#setting_department').val(item.department || '');
        $('#setting_province').val(item.province || '');
        $('#setting_district').val(item.district || '');
        $('#setting_sol_user').val(item.sol_user || '');
        $('#setting_sol_password').val('').attr('placeholder', item.has_sol_password ? '******** (conservada)' : '');
        $('#setting_is_active').val(item.is_active ? '1' : '0');

        setSettingReadonly(!editable);
        $('#electronicInvoiceSettingModalTitle').text(editable ? 'Editar Configuración' : 'Detalle de Configuración');
        $('#btnSaveElectronicInvoiceSetting').toggle(editable);
        $('#electronicInvoiceSettingModal').modal('show');
    });
}

function resetSettingForm() {
    const form = $('#electronicInvoiceSettingForm')[0];
    if (form) {
        form.reset();
    }

    $('#setting_id').val('');
    $('#setting_provider').val('apisperu');
    $('#setting_environment').val('beta');
    $('#setting_is_active').val('1');
    $('#electronicInvoiceSettingModalTitle').text('Nueva Configuración');
    $('#btnSaveElectronicInvoiceSetting').show();
    setSettingReadonly(false);
    clearSettingErrors();
}

function setSettingReadonly(readonly) {
    $('#electronicInvoiceSettingForm')
        .find('input, select, textarea')
        .not('[name="_token"], #setting_id')
        .prop('disabled', readonly);
}

function clearSettingErrors() {
    $('#electronicInvoiceSettingForm .is-invalid').removeClass('is-invalid');
    $('#setting_general_errors').addClass('d-none').empty();
}

function showSettingErrors(errors) {
    const messages = [];

    $.each(errors, function (key, value) {
        const message = value[0] || 'Revise el dato ingresado.';
        const input = $(`#setting_${key}`);

        if (input.length) {
            input.addClass('is-invalid');
        }

        messages.push(message);
    });

    $('#setting_general_errors').removeClass('d-none').html(messages.join('<br>'));
}

function toast(icon, title) {
    Swal.fire({
        icon,
        title,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
}
