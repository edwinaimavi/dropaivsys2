let companyWarehouseRows = [];

const companyWarehouseEscape = value => $('<div>').text(value ?? '').html();

function companyWarehouseResetForm() {
    $('#company_warehouse_id').val('');
    $('#company_warehouse_company_id,#company_warehouse_warehouse_id').prop('disabled', false).val('');
    $('#company_warehouse_sunat_code').val('');
    $('#company_warehouse_is_active').prop('checked', true);
    $('#companyWarehouseForm .is-invalid').removeClass('is-invalid');
    $('#companyWarehouseForm .invalid-feedback').text('');
    $('#btnCancelCompanyWarehouseEdit').addClass('d-none');
}

function companyWarehouseRender(response) {
    companyWarehouseRows = response.data || [];
    const companySelect = $('#company_warehouse_company_id');
    const warehouseSelect = $('#company_warehouse_warehouse_id');
    companySelect.html('<option value="">Seleccione empresa</option>');
    warehouseSelect.html('<option value="">Seleccione almacén</option>');
    (response.companies || []).forEach(company => companySelect.append(new Option(
        company.trade_name || company.business_name,
        company.id
    )));
    (response.warehouses || []).forEach(warehouse => warehouseSelect.append(new Option(
        [warehouse.code, warehouse.name].filter(Boolean).join(' | '),
        warehouse.id
    )));

    const rows = companyWarehouseRows.map(relation => {
        const company = relation.company || {};
        const warehouse = relation.warehouse || {};
        const active = Boolean(relation.is_active);
        const actions = window.companyWarehouseCanManage ? `
                <button type="button" class="btn btn-outline-info btn-sm btn-edit-company-warehouse" data-id="${relation.id}" title="Editar"><i class="fas fa-edit"></i></button>
                <button type="button" class="btn btn-outline-${active ? 'secondary' : 'success'} btn-sm btn-toggle-company-warehouse" data-id="${relation.id}" title="${active ? 'Desactivar' : 'Activar'}"><i class="fas fa-${active ? 'ban' : 'check'}"></i></button>
            ` : '<span class="text-muted">Solo lectura</span>';
        return `<tr>
            <td><strong>${companyWarehouseEscape(company.trade_name || company.business_name)}</strong><small class="d-block text-muted">${companyWarehouseEscape(company.ruc || '')}</small></td>
            <td><strong>${companyWarehouseEscape(warehouse.name)}</strong><small class="d-block text-muted">${companyWarehouseEscape(warehouse.code || '')}</small></td>
            <td>${relation.sunat_establishment_code ? `<code>${companyWarehouseEscape(relation.sunat_establishment_code)}</code>` : '<span class="badge badge-warning">Pendiente</span>'}</td>
            <td><span class="badge badge-${active ? 'success' : 'secondary'}">${active ? 'Activo' : 'Inactivo'}</span></td>
            <td class="text-right">${actions}</td>
        </tr>`;
    }).join('');
    $('#companyWarehouseTable tbody').html(rows || '<tr><td colspan="5" class="text-center text-muted py-4">No hay asociaciones configuradas.</td></tr>');
}

function companyWarehouseLoad() {
    return $.get(window.routes.companyWarehouses).done(companyWarehouseRender).fail(() => {
        $('#companyWarehouseTable tbody').html('<tr><td colspan="5" class="text-center text-danger py-4">No se pudieron cargar las configuraciones.</td></tr>');
    });
}

$(document).on('click', '#btnCompanyWarehouses', function () {
    companyWarehouseResetForm();
    $('#companyWarehouseModal').modal('show');
    companyWarehouseLoad();
});

$(document).on('click', '.btn-edit-company-warehouse', function () {
    const row = companyWarehouseRows.find(item => String(item.id) === String($(this).data('id')));
    if (!row) return;
    $('#company_warehouse_id').val(row.id);
    $('#company_warehouse_company_id').val(row.company_id).prop('disabled', true);
    $('#company_warehouse_warehouse_id').val(row.warehouse_id).prop('disabled', true);
    $('#company_warehouse_sunat_code').val(row.sunat_establishment_code || '');
    $('#company_warehouse_is_active').prop('checked', Boolean(row.is_active));
    $('#btnCancelCompanyWarehouseEdit').removeClass('d-none');
});

$(document).on('click', '#btnCancelCompanyWarehouseEdit', companyWarehouseResetForm);

$(document).on('submit', '#companyWarehouseForm', function (event) {
    event.preventDefault();
    const id = $('#company_warehouse_id').val();
    const payload = {
        company_id: $('#company_warehouse_company_id').val(),
        warehouse_id: $('#company_warehouse_warehouse_id').val(),
        sunat_establishment_code: $('#company_warehouse_sunat_code').val().trim() || null,
        is_active: $('#company_warehouse_is_active').is(':checked') ? 1 : 0,
    };
    if (id) payload._method = 'PUT';
    $('#companyWarehouseForm .is-invalid').removeClass('is-invalid');
    $.post(id ? `${window.routes.companyWarehouses}/${id}` : window.routes.companyWarehouses, payload)
        .done(response => {
            companyWarehouseResetForm();
            companyWarehouseLoad();
            if (window.Swal) Swal.fire({icon: 'success', title: 'Configuración guardada', text: response.message, timer: 1800, showConfirmButton: false});
        })
        .fail(xhr => {
            const errors = xhr.responseJSON?.errors || {};
            const map = {company_id: '#company_warehouse_company_id', warehouse_id: '#company_warehouse_warehouse_id', sunat_establishment_code: '#company_warehouse_sunat_code', is_active: '#company_warehouse_is_active'};
            Object.entries(errors).forEach(([key, messages]) => {
                const input = $(map[key]);
                input.addClass('is-invalid');
                input.siblings('.invalid-feedback').text(messages[0]);
            });
        });
});

$(document).on('click', '.btn-toggle-company-warehouse', function () {
    const row = companyWarehouseRows.find(item => String(item.id) === String($(this).data('id')));
    if (!row) return;
    $.post(`${window.routes.companyWarehouses}/${row.id}`, {
        _method: 'PUT',
        sunat_establishment_code: row.sunat_establishment_code || null,
        is_active: row.is_active ? 0 : 1,
    }).done(companyWarehouseLoad);
});
