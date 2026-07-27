let tableCompanies;
let tableCompanyBankAccounts;
let lastCompanyRucLookup = '';

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    initCompaniesTable();

    $(document).on('click', '#btnCreateCompany', function () {
        resetCompanyForm();
        $('#companyModalLabel').text('Nueva empresa');
        $('#btnSaveCompany').html('<i class="fas fa-save mr-1"></i> Guardar empresa');
        $('#companyModal').modal('show');
    });

    $(document).on('submit', '#companyForm', function (event) {
        event.preventDefault();
        saveCompany();
    });

    $(document).on('click', '.btn-edit-company', function () {
        editCompany($(this).data('id'));
    });

    $(document).on('click', '.btn-view-company', function () {
        viewCompany($(this).data('id'));
    });

    $(document).on('click', '.btn-delete-company', function () {
        deleteCompany($(this).data('id'), $(this).data('name'));
    });

    $(document).on('click', '.btn-company-bank-accounts', openCompanyBankAccounts);
    $(document).on('click', '#btnManageCompanyBankAccounts', manageCompanyBankAccountsFromDetail);
    $(document).on('submit', '#companyBankAccountForm', saveCompanyBankAccount);
    $(document).on('click', '.btn-edit-company-bank-account', editCompanyBankAccount);
    $(document).on('click', '.btn-delete-company-bank-account', deleteCompanyBankAccount);

    $(document).on('click', '#btnSearchCompanyRuc', searchCompanyRuc);

    $(document).on('change', '#company_logo', function () {
        previewSelectedCompanyLogo(this);
    });

    $(document).on('keydown', '#company_ruc', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchCompanyRuc();
        }
    });

    $(document).on('blur', '#company_ruc', function () {
        const ruc = String($(this).val() || '').trim();

        if (/^\d{11}$/.test(ruc) && ruc !== lastCompanyRucLookup && !$('#company_id').val()) {
            searchCompanyRuc();
        }
    });

    $('#companyModal').on('hidden.bs.modal', resetCompanyForm);
    $('#companyBankAccountsModal').on('hidden.bs.modal', function () {
        resetCompanyBankAccountForm();
        if ($.fn.DataTable.isDataTable('#tableCompanyBankAccounts')) {
            tableCompanyBankAccounts.destroy();
            $('#tableCompanyBankAccounts tbody').empty();
        }
    });
});

function openCompanyBankAccounts() {
    const button = $(this);
    showCompanyBankAccountsModal({
        id: button.data('id'),
        name: button.data('name'),
        ruc: button.data('ruc'),
        status: button.data('status')
    });
}

function showCompanyBankAccountsModal(company) {
    const companyId = company.id;

    resetCompanyBankAccountForm();
    $('#company_bank_company_id').val(companyId);
    $('#company_bank_company_name').text(company.name || '—');
    $('#company_bank_company_ruc').text(company.ruc || '—');
    $('#company_bank_company_status')
        .toggleClass('badge-success', company.status === 'ACTIVO')
        .toggleClass('badge-danger', company.status !== 'ACTIVO')
        .text(company.status || '—');
    $('#companyBankAccountsModal').modal('show');
    initCompanyBankAccountsTable(companyId);
}

function manageCompanyBankAccountsFromDetail() {
    const company = $(this).data('company');

    $('#viewCompanyModal')
        .one('hidden.bs.modal', function () {
            showCompanyBankAccountsModal(company);
        })
        .modal('hide');
}

function initCompanyBankAccountsTable(companyId) {
    const listUrl = `${window.routes.companyBankAccountsBase}/${companyId}/bank-accounts/list`;

    if ($.fn.DataTable.isDataTable('#tableCompanyBankAccounts')) {
        tableCompanyBankAccounts.ajax.url(listUrl).load();
        return;
    }

    tableCompanyBankAccounts = $('#tableCompanyBankAccounts').DataTable({
        processing: true,
        serverSide: true,
        ajax: listUrl,
        autoWidth: false,
        responsive: true,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json',
            emptyTable: 'No hay cuentas bancarias registradas.'
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'bank', name: 'bank.description' },
            { data: 'currency', name: 'currency.description' },
            { data: 'account_holder', name: 'account_holder' },
            { data: 'account_number', name: 'account_number' },
            { data: 'cci', name: 'cci', defaultContent: '—' },
            { data: 'is_detraction', name: 'is_detraction' },
            { data: 'status', name: 'status' },
            { data: 'acciones', orderable: false, searchable: false }
        ]
    });
}

function saveCompanyBankAccount(event) {
    event.preventDefault();
    clearCompanyBankAccountErrors();

    const companyId = $('#company_bank_company_id').val();
    const accountId = $('#company_bank_account_id').val();
    const formData = new FormData(this);
    let url = `${window.routes.companyBankAccountsBase}/${companyId}/bank-accounts`;

    if (accountId) {
        url += `/${accountId}`;
        formData.append('_method', 'PUT');
    }

    const button = $('#btnSaveCompanyBankAccount');
    button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');

    $.ajax({
        url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            resetCompanyBankAccountForm();
            $('#company_bank_company_id').val(companyId);
            tableCompanyBankAccounts.ajax.reload(null, false);
            toastCompany('success', response.message);
        },
        error: handleCompanyBankAccountError,
        complete: function () {
            setCompanyBankAccountButton(false);
        }
    });
}

function editCompanyBankAccount() {
    const account = $(this).data('account');

    $('#company_bank_account_id').val(account.id);
    $('#company_bank_id').val(account.bank_id);
    $('#company_bank_currency_id').val(account.currency_id);
    $('#company_bank_account_holder').val(account.account_holder);
    $('#company_bank_account_number').val(account.account_number);
    $('#company_bank_cci').val(account.cci);
    $('#company_bank_is_detraction').val(account.is_detraction);
    $('#company_bank_status').val(account.status);
    $('#company_bank_observation').val(account.observation);
    setCompanyBankAccountButton(true);
    $('#companyBankAccountForm')[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function deleteCompanyBankAccount() {
    const accountId = $(this).data('id');
    const companyId = $('#company_bank_company_id').val();

    Swal.fire({
        icon: 'warning',
        title: 'Eliminar cuenta bancaria',
        text: '¿Está seguro de eliminar esta cuenta bancaria?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: `${window.routes.companyBankAccountsBase}/${companyId}/bank-accounts/${accountId}`,
            type: 'DELETE',
            success: function (response) {
                tableCompanyBankAccounts.ajax.reload(null, false);
                toastCompany('success', response.message);
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar la cuenta bancaria.', 'error');
            }
        });
    });
}

function resetCompanyBankAccountForm() {
    const form = $('#companyBankAccountForm')[0];
    if (form) form.reset();
    $('#company_bank_account_id').val('');
    $('#company_bank_is_detraction').val('NO');
    $('#company_bank_status').val('ACTIVE');
    clearCompanyBankAccountErrors();
    setCompanyBankAccountButton(false);
}

function clearCompanyBankAccountErrors() {
    $('#companyBankAccountForm .is-invalid').removeClass('is-invalid');
    $('#companyBankAccountErrors').addClass('d-none').empty();
}

function handleCompanyBankAccountError(xhr) {
    const errors = xhr.responseJSON?.errors || {};
    const messages = Object.values(errors).flat();

    Object.keys(errors).forEach(function (field) {
        $(`#companyBankAccountForm [name="${field}"]`).addClass('is-invalid');
    });

    $('#companyBankAccountErrors')
        .toggleClass('d-none', messages.length === 0)
        .html(messages.map(message => `<div>${escapeCompanyHtml(message)}</div>`).join(''));

    if (!messages.length) {
        Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar la cuenta bancaria.', 'error');
    }
}

function setCompanyBankAccountButton(isEditing) {
    $('#btnSaveCompanyBankAccount')
        .prop('disabled', false)
        .toggleClass('btn-warning', isEditing)
        .toggleClass('btn-success', !isEditing)
        .html(`<i class="fas fa-save mr-1"></i> ${isEditing ? 'Actualizar Cuenta' : 'Guardar Cuenta'}`);
}

function initCompaniesTable() {
    tableCompanies = $('#tableCompanies').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.companiesList,
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'logo_preview', orderable: false, searchable: false, className: 'text-center' },
            { data: 'ruc', name: 'ruc' },
            { data: 'business_name', name: 'business_name' },
            { data: 'trade_name', name: 'trade_name', defaultContent: '-' },
            { data: 'location', name: 'address', orderable: false },
            { data: 'phone', name: 'phone', defaultContent: '-' },
            { data: 'status', name: 'status', className: 'text-center' },
            { data: 'created_at', name: 'created_at' },
            { data: 'acciones', orderable: false, searchable: false, className: 'text-center' }
        ],
        responsive: true,
        autoWidth: false,
        order: [[1, 'desc']],
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        },
        dom: `
            <'row mb-3'
                <'col-sm-12 col-md-6'l>
                <'col-sm-12 col-md-6 text-md-end'f>
            >
            <'row'<'col-sm-12'tr>>
            <'row mt-3'
                <'col-sm-12 col-md-5'i>
                <'col-sm-12 col-md-7 d-flex justify-content-center justify-content-md-end'p>
            >
            <'row mt-3'<'col-sm-12 text-center'B>>
        `,
        buttons: [
            { extend: 'excel', className: 'btn btn-success btn-sm', text: '<i class="fas fa-file-excel"></i> Excel' },
            { extend: 'pdf', className: 'btn btn-danger btn-sm', text: '<i class="fas fa-file-pdf"></i> PDF' },
            { extend: 'print', className: 'btn btn-secondary btn-sm', text: '<i class="fas fa-print"></i> Imprimir' }
        ],
        drawCallback: function () {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });
}

function resetCompanyForm() {
    const form = $('#companyForm')[0];

    if (form) {
        form.reset();
    }

    $('#company_id').val('');
    $('#company_status').val('1');
    $('#companyCurrentLogo').text('');
    lastCompanyRucLookup = '';
    $('#companyRucExtraData').addClass('d-none');
    $('#company_sunat_location').text('-');
    $('#company_sunat_condition').text('');
    setCompanyLogoPreview();
    clearCompanyErrors();
    setCompanySaving(false, 'Guardar empresa');
    setCompanyRucLoading(false);
}

function saveCompany() {
    clearCompanyErrors();

    const id = $('#company_id').val();
    const formData = new FormData($('#companyForm')[0]);
    let url = window.routes.companiesStore;

    if (id) {
        url = `${window.routes.companiesUpdate}/${id}`;
        formData.append('_method', 'PUT');
    }

    setCompanySaving(true, id ? 'Actualizando...' : 'Guardando...');

    $.ajax({
        url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            $('#companyModal').modal('hide');
            tableCompanies.ajax.reload(null, false);
            toastCompany('success', response.message || 'Empresa guardada correctamente.');
        },
        error: handleCompanyError,
        complete: function () {
            setCompanySaving(false, id ? 'Actualizar empresa' : 'Guardar empresa');
        }
    });
}

function editCompany(id) {
    resetCompanyForm();
    $('#companyModalLabel').text('Editar empresa');
    $('#btnSaveCompany').html('<i class="fas fa-save mr-1"></i> Actualizar empresa');

    $.get(`${window.routes.companiesShow}/${id}/edit`)
        .done(function (response) {
            const company = response.data;
            $('#company_id').val(company.id);
            $('#company_ruc').val(company.ruc || '');
            $('#company_business_name').val(company.business_name || '');
            $('#company_trade_name').val(company.trade_name || '');
            $('#company_address').val(company.address || '');
            $('#company_phone').val(company.phone || '');
            $('#company_email').val(company.email || '');
            $('#company_status').val(company.status ? '1' : '0');
            setCompanyLogoPreview(company.logo_url, company.logo ? 'Logo actual registrado' : '');
            $('#companyModal').modal('show');
        })
        .fail(function (xhr) {
            toastCompany('error', xhr.responseJSON?.message || 'No se pudo cargar la empresa.');
        });
}

function viewCompany(id) {
    $.get(`${window.routes.companiesShow}/${id}`)
        .done(function (response) {
            const company = response.data;
            const logo = company.logo_url
                ? `<img src="${escapeCompanyHtml(company.logo_url)}" class="company-detail-logo" alt="Logo">`
                : '<i class="fas fa-building"></i>';

            $('#view_company_logo_box').html(logo);
            $('#view_company_logo_caption').text(company.logo_url ? 'Logo institucional registrado' : 'Sin logo registrado');
            $('#view_company_business_name').text(company.business_name || '-');
            $('#view_company_trade_name').text(company.trade_name || 'Sin nombre comercial');
            $('#view_company_ruc').text(company.ruc || '-');
            $('#view_company_header_ruc').text(company.ruc || '-');
            $('#view_company_status').html(company.status
                ? '<span class="badge badge-success rounded-pill px-3 py-2">ACTIVO</span>'
                : '<span class="badge badge-danger rounded-pill px-3 py-2">INACTIVO</span>');
            $('#view_company_header_status').html(company.status
                ? '<span class="badge badge-success rounded-pill px-3 py-1">ACTIVO</span>'
                : '<span class="badge badge-danger rounded-pill px-3 py-1">INACTIVO</span>');
            $('#view_company_phone').text(company.phone || '-');
            $('#view_company_email').text(company.email || '-');
            $('#view_company_address').text(company.address || '-');
            $('#view_company_created_at').text(company.created_at || '-');
            $('#view_company_updated_at').text(company.updated_at || '-');
            $('#view_company_usage').text((company.usage || []).length ? company.usage.join(', ') : 'Sin relaciones detectadas');
            $('#view_company_bank_accounts').html(renderCompanyBankAccounts(company.bank_accounts || []));
            $('#btnManageCompanyBankAccounts').data('company', {
                id: company.id,
                name: company.business_name,
                ruc: company.ruc,
                status: company.status_label
            });
            $('#viewCompanyModal').modal('show');
        })
        .fail(function (xhr) {
            toastCompany('error', xhr.responseJSON?.message || 'No se pudo cargar el detalle.');
        });
}

function renderCompanyBankAccounts(accounts) {
    if (!accounts.length) {
        return `
            <div class="company-bank-empty">
                <span><i class="fas fa-university"></i></span>
                <strong>No hay cuentas bancarias registradas para esta empresa.</strong>
                <small>Puede agregarlas desde la opción Gestionar cuentas.</small>
            </div>
        `;
    }

    const rows = accounts.map(function (account) {
        const bank = account.bank_short_name || account.bank || '-';
        const currency = [account.currency_code, account.currency].filter(Boolean).join(' | ') || '-';
        const detraction = account.is_detraction === 'YES'
            ? '<span class="badge badge-warning">SÍ</span>'
            : '<span class="badge badge-light border">NO</span>';
        const status = account.status === 'ACTIVE'
            ? '<span class="badge badge-success">ACTIVO</span>'
            : '<span class="badge badge-secondary">INACTIVO</span>';

        return `
            <tr>
                <td><strong>${escapeCompanyHtml(bank)}</strong></td>
                <td>${escapeCompanyHtml(currency)}</td>
                <td>${escapeCompanyHtml(account.account_holder || '-')}</td>
                <td class="text-nowrap">${escapeCompanyHtml(account.account_number || '-')}</td>
                <td class="text-nowrap">${escapeCompanyHtml(account.cci || '-')}</td>
                <td class="text-center">${detraction}</td>
                <td class="text-center">${status}</td>
            </tr>
        `;
    }).join('');

    return `
        <div class="table-responsive company-view-bank-table">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>BANCO</th><th>MONEDA</th><th>TITULAR</th>
                        <th>NRO CUENTA</th><th>CCI</th><th>DETRACCIÓN</th><th>ESTADO</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

function deleteCompany(id, name) {
    Swal.fire({
        icon: 'warning',
        title: 'Eliminar empresa',
        text: `¿Está seguro de eliminar la empresa "${name || 'seleccionada'}"?`,
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: `${window.routes.companiesDelete}/${id}`,
            type: 'DELETE',
            success: function (response) {
                tableCompanies.ajax.reload(null, false);
                toastCompany('success', response.message || 'Empresa eliminada correctamente.');
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar la empresa.', 'error');
            }
        });
    });
}

function searchCompanyRuc() {
    const ruc = String($('#company_ruc').val() || '').trim();

    if (!/^\d{11}$/.test(ruc)) {
        toastCompany('warning', 'Ingrese un RUC válido de 11 dígitos.');
        return;
    }

    setCompanyRucLoading(true);
    lastCompanyRucLookup = ruc;

    Swal.fire({
        title: 'Consultando RUC',
        text: 'Buscando información en SUNAT...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => Swal.showLoading()
    });

    $.get(`${window.routes.companiesConsultarRuc}/${ruc}`)
        .done(function (response) {
            $('#company_business_name').val(response.razon_social || '');
            $('#company_trade_name').val(response.razon_social || '');
            $('#company_address').val(response.direccion || '');
            $('#companyRucExtraData').removeClass('d-none');
            $('#company_sunat_location').text([response.distrito, response.provincia, response.departamento].filter(Boolean).join(' - ') || '-');
            $('#company_sunat_condition').text([response.estado, response.condicion].filter(Boolean).join(' | '));
            toastCompany('success', 'Datos del RUC cargados correctamente.');
        })
        .fail(function (xhr) {
            toastCompany('warning', xhr.responseJSON?.message || 'No se pudo consultar el RUC. Puede registrar los datos manualmente.');
        })
        .always(function () {
            setCompanyRucLoading(false);
            if (Swal.isLoading()) {
                Swal.close();
            }
        });
}

function handleCompanyError(xhr) {
    if (xhr.status === 422) {
        const errors = xhr.responseJSON?.errors || {};
        const messages = [];

        Object.entries(errors).forEach(function ([field, fieldMessages]) {
            const input = $(`[name="${field}"]`);
            const inputId = input.attr('id');
            const message = fieldMessages[0];

            input.addClass('is-invalid');
            if (inputId) {
                $(`#${inputId}-error`).text(message);
            }
            if (field === 'logo') {
                $('#companyLogoPreview').addClass('is-invalid');
            }
            messages.push(message);
        });

        $('#companyErrors')
            .removeClass('d-none')
            .html(`<ul class="mb-0 pl-3">${messages.map(message => `<li>${escapeCompanyHtml(message)}</li>`).join('')}</ul>`);
        return;
    }

    Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar la empresa.', 'error');
}

function clearCompanyErrors() {
    $('#companyForm').find('.is-invalid').removeClass('is-invalid');
    $('#companyForm').find('.invalid-feedback').text('');
    $('#companyErrors').addClass('d-none').empty();
    $('#companyLogoPreview').removeClass('is-invalid');
}

function previewSelectedCompanyLogo(input) {
    const file = input.files && input.files[0] ? input.files[0] : null;

    if (!file) {
        setCompanyLogoPreview();
        return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
        setCompanyLogoPreview(event.target.result, file.name);
    };
    reader.readAsDataURL(file);
}

function setCompanyLogoPreview(url = null, fileName = '') {
    const preview = $('#companyLogoPreview');
    const hasLogo = Boolean(url);
    const safeFileName = fileName || (hasLogo ? 'Logo seleccionado' : 'Sin archivo seleccionado');

    preview.html(hasLogo
        ? `<img src="${escapeCompanyHtml(url)}" alt="Vista previa del logo">`
        : `
            <div class="company-logo-placeholder">
                <i class="fas fa-image"></i>
                <span>Sin logo registrado</span>
            </div>
        `);

    $('#companyLogoButtonText').text(hasLogo ? 'Cambiar logo' : 'Seleccionar logo');
    $('#companyLogoFileName').text(safeFileName);
    $('#companyCurrentLogo').text(hasLogo && fileName === 'Logo actual registrado' ? 'Se conservará si no selecciona uno nuevo.' : '');
}

function setCompanySaving(isSaving, text) {
    $('#btnSaveCompany')
        .prop('disabled', isSaving)
        .html(isSaving
            ? '<span class="spinner-border spinner-border-sm mr-1"></span>' + text
            : `<i class="fas fa-save mr-1"></i> ${text}`);
}

function setCompanyRucLoading(isLoading) {
    $('#btnSearchCompanyRuc')
        .prop('disabled', isLoading)
        .html(isLoading
            ? '<span class="spinner-border spinner-border-sm mr-1"></span>Buscando'
            : '<i class="fas fa-search mr-1"></i> Buscar');
}

function toastCompany(icon, title) {
    Swal.fire({
        icon,
        title,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

function escapeCompanyHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
