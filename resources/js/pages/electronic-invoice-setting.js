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
            { data: 'ruc', name: 'electronic_invoice_settings.ruc', defaultContent: '-' },
            { data: 'provider_label', name: 'electronic_invoice_settings.provider' },
            { data: 'environment_label', name: 'electronic_invoice_settings.environment' },
            { data: 'credentials_status', name: 'credentials_status', orderable: false, searchable: false },
            { data: 'is_active', name: 'electronic_invoice_settings.is_active' },
            { data: 'created_at', name: 'electronic_invoice_settings.created_at' },
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

    $('#setting_company_id').on('change', fillSettingFromCompany);
    $('#setting_provider').on('change', syncSettingProviderLabel);
    $('#btnSearchSettingRuc').on('click', searchSettingRuc);
    $('#setting_ruc').on('input', function () {
        $(this).val(String($(this).val() || '').replace(/\D/g, '').slice(0, 11));
    }).on('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchSettingRuc();
        }
    });

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
        $('#setting_sol_user').val('').attr('placeholder', item.has_sol_user ? '******** (conservado)' : 'Completar solo si desea cambiar');
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
    $('#setting_provider').val('internal');
    $('#setting_environment').val('internal');
    $('#setting_is_active').val('1');
    $('#setting_api_token, #setting_user_token, #setting_sol_user, #setting_sol_password')
        .val('').attr('placeholder', 'Completar solo si desea cambiar');
    $('#electronicInvoiceSettingModalTitle').text('Nueva Configuración');
    $('#btnSaveElectronicInvoiceSetting').show();
    setSettingReadonly(false);
    clearSettingErrors();
    syncSettingProviderLabel();
}

function syncSettingProviderLabel() {
    const internal = $('#setting_provider').val() === 'internal';
    $('#settingProviderSummary').text(internal ? 'Modo interno / sin envío SUNAT' : 'APIs Perú / SUNAT');
}

function fillSettingFromCompany() {
    const companyId = String($('#setting_company_id').val() || '');
    const company = (window.electronicInvoiceSettingCompanies || [])
        .find(item => String(item.id) === companyId);

    if (!company) {
        return;
    }

    fillIssuerData(company);
    clearSettingErrors();
}

function fillIssuerData(company) {
    if (!company) {
        return;
    }

    $('#setting_ruc').val(company.ruc || '');
    $('#setting_business_name').val(company.business_name || '');
    $('#setting_trade_name').val(company.trade_name || company.business_name || '');
    $('#setting_address').val(company.address || '');
    $('#setting_ubigeo').val(company.ubigeo || '');
    $('#setting_department').val(company.department || '');
    $('#setting_province').val(company.province || '');
    $('#setting_district').val(company.district || '');

    if ((!company.department || !company.province || !company.district) && company.address) {
        const location = parseLocationFromAddress(company.address);
        if (!$('#setting_department').val()) $('#setting_department').val(location.department || '');
        if (!$('#setting_province').val()) $('#setting_province').val(location.province || '');
        if (!$('#setting_district').val()) $('#setting_district').val(location.district || '');
    }
}

function parseLocationFromAddress(address) {
    const parts = String(address || '')
        .split(/\s+-\s+/)
        .map(part => part.trim())
        .filter(Boolean);

    if (parts.length < 3) {
        return {};
    }

    const candidates = parts.slice(-3);
    const isLocationName = part =>
        part.length >= 2
        && part.length <= 80
        && /^[A-ZÁÉÍÓÚÜÑ .']+$/i.test(part)
    ;
    const looksLikeLocation = candidates.every(isLocationName);

    if (looksLikeLocation) {
        return {
            department: candidates[0],
            province: candidates[1],
            district: candidates[2]
        };
    }

    const province = parts.at(-2);
    const district = parts.at(-1);
    if (!isLocationName(province) || !isLocationName(district)) {
        return {};
    }

    const departments = [
        'AMAZONAS', 'ANCASH', 'APURIMAC', 'AREQUIPA', 'AYACUCHO', 'CAJAMARCA',
        'CALLAO', 'CUSCO', 'HUANCAVELICA', 'HUANUCO', 'ICA', 'JUNIN', 'LA LIBERTAD',
        'LAMBAYEQUE', 'LIMA', 'LORETO', 'MADRE DE DIOS', 'MOQUEGUA', 'PASCO',
        'PIURA', 'PUNO', 'SAN MARTIN', 'TACNA', 'TUMBES', 'UCAYALI'
    ];
    const prefix = parts.slice(0, -2).join(' - ').toUpperCase();
    const department = departments
        .sort((left, right) => right.length - left.length)
        .find(name => prefix === name || prefix.endsWith(` ${name}`));

    return department ? { department, province, district } : {};
}

function searchSettingRuc() {
    const ruc = String($('#setting_ruc').val() || '').trim();
    if (!/^\d{11}$/.test(ruc)) {
        toast('warning', 'El RUC debe tener 11 dígitos.');
        return;
    }

    const button = $('#btnSearchSettingRuc');
    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

    $.get(`${window.routes.electronicInvoiceSettingConsultRuc}/${ruc}`)
        .done(function (response) {
            const businessName = response.razon_social || '';
            fillIssuerData({
                ruc,
                business_name: businessName,
                trade_name: response.nombre_comercial || businessName,
                address: response.direccion || '',
                ubigeo: response.ubigeo || '',
                department: response.departamento || '',
                province: response.provincia || '',
                district: response.distrito || ''
            });
            toast('success', 'Datos del RUC cargados correctamente.');
        })
        .fail(function (xhr) {
            const message = xhr.status === 404
                ? 'No se encontraron datos para el RUC ingresado.'
                : (xhr.responseJSON?.message || 'No se pudo consultar el RUC. Puede completar los datos manualmente.');
            toast('warning', message);
        })
        .always(function () {
            button.prop('disabled', false).html('<i class="fas fa-search"></i>');
        });
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
