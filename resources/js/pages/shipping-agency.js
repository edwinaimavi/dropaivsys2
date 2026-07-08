let tableShippingAgency;
let shippingBranchIndex = 0;
let shippingContactIndex = 0;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    initShippingAgencyTable();

    $(document).on('click', '#btnCreateShippingAgency', function () {
        resetShippingAgencyForm();
        $('#shippingAgencyModalLabel').text('Nueva Agencia de Envio');
        $('#shippingAgencyModal').modal('show');
    });

    $('#shippingAgencyModal').on('hidden.bs.modal', resetShippingAgencyForm);

    $(document).on('submit', '#shippingAgencyForm', function (event) {
        event.preventDefault();
        saveShippingAgency(this);
    });

    $(document).on('click', '#btnAddShippingBranch', function () {
        addShippingBranchRow();
    });

    $(document).on('click', '#btnAddShippingContact', function () {
        addShippingContactRow();
    });

    $(document).on('click', '.btnRemoveShippingBranch', function () {
        $(this).closest('tr').remove();
        refreshShippingBranchIndexes();
        refreshShippingContactBranchOptions();
    });

    $(document).on('click', '.btnRemoveShippingContact', function () {
        $(this).closest('tr').remove();
        refreshShippingContactIndexes();
    });

    $(document).on('change', '.shipping-branch-main', function () {
        if (this.checked) {
            $('.shipping-branch-main').not(this).prop('checked', false);
        }
    });

    $(document).on('input', '.shipping-branch-row input, .shipping-contact-row input, #shipping_business_name, #shipping_trade_name, #shipping_observations', function () {
        if ($(this).hasClass('text-uppercase')) {
            this.value = this.value.toUpperCase();
        }
        refreshShippingContactBranchOptions();
    });

    // =========================================================
    // SOLO NÚMEROS EN RUC
    // =========================================================
    $(document).on('input', '#shipping_ruc', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // =========================================================
    // CONSULTAR RUC Y AUTOCOMPLETAR AGENCIA
    // =========================================================
    let isConsultingShippingRuc = false;

    function consultarRucShippingAgency() {
        const ruc = $('#shipping_ruc').val().trim();

        if (!ruc) {
            return;
        }

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

        if (isConsultingShippingRuc) {
            return;
        }

        isConsultingShippingRuc = true;

        const $ruc = $('#shipping_ruc');
        const $btnSave = $('#btnSaveShippingAgency');

        $ruc.prop('disabled', true);
        $btnSave.prop('disabled', true);

        Swal.fire({
            title: 'Consultando RUC',
            text: 'Buscando información en SUNAT...',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: `${window.routes.consultarRucShippingAgency}/${ruc}`,
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                Swal.close();

                if (!response.status) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Atención',
                        text: response.message || 'No se pudo obtener el RUC.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3500
                    });

                    return;
                }

                $('#shipping_business_name')
                    .val(response.razon_social || '')
                    .trigger('input');

                if (!$('#shipping_trade_name').val()) {
                    $('#shipping_trade_name')
                        .val(response.razon_social || '')
                        .trigger('input');
                }

                if (!$('#shipping_agency_type').val()) {
                    $('#shipping_agency_type').val('TRANSPORTISTA');
                }

                llenarSedePrincipalConRuc(response);

                Swal.fire({
                    icon: 'success',
                    title: 'RUC encontrado',
                    text: 'Los datos fueron cargados correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500
                });
            },
            error: function (xhr) {
                Swal.close();

                $('#shipping_business_name').val('');
                $('#shipping_trade_name').val('');

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
                isConsultingShippingRuc = false;

                $ruc.prop('disabled', false);
                $btnSave.prop('disabled', false);
            }
        });
    }

    function llenarSedePrincipalConRuc(response) {
        let $row = $('#shippingBranchesBody tr.shipping-branch-row').first();

        if (!$row.length) {
            addShippingBranchRow({
                branch_name: 'SEDE PRINCIPAL',
                address: response.direccion || '',
                department: response.departamento || '',
                province: response.provincia || '',
                district: response.distrito || '',
                reference: '',
                is_main: true,
                status: 'ACTIVE'
            });

            return;
        }

        const branchNameInput = $row.find('[name$="[branch_name]"]');
        const addressInput = $row.find('[name$="[address]"]');
        const departmentInput = $row.find('[name$="[department]"]');
        const provinceInput = $row.find('[name$="[province]"]');
        const districtInput = $row.find('[name$="[district]"]');

        if (!branchNameInput.val()) {
            branchNameInput.val('SEDE PRINCIPAL');
        }

        if (response.direccion) {
            addressInput.val(response.direccion);
        }

        if (response.departamento) {
            departmentInput.val(response.departamento);
        }

        if (response.provincia) {
            provinceInput.val(response.provincia);
        }

        if (response.distrito) {
            districtInput.val(response.distrito);
        }

        $row.find('[name$="[is_main]"]').prop('checked', true);
        $row.find('[name$="[status]"]').val('ACTIVE');

        $('.shipping-branch-main').not($row.find('[name$="[is_main]"]')).prop('checked', false);

        refreshShippingBranchIndexes();
        refreshShippingContactBranchOptions();
    }

    $(document).on('blur', '#shipping_ruc', function () {
        consultarRucShippingAgency();
    });

    $(document).on('keydown', '#shipping_ruc', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            consultarRucShippingAgency();
        }
    });

    $(document).on('click', '.editShippingAgency', function () {
        loadShippingAgencyForEdit($(this).data('id'));
    });

    $(document).on('click', '.viewShippingAgency', function () {
        loadShippingAgencyDetail($(this).data('id'));
    });

    $(document).on('click', '.deleteShippingAgency', function () {
        deleteShippingAgency($(this).data('id'));
    });
});

function initShippingAgencyTable() {
    tableShippingAgency = $('#tableShippingAgency').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.shippingAgencyList,
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'code', name: 'code' },
            { data: 'ruc', name: 'ruc', defaultContent: '-' },
            { data: 'business_name', name: 'business_name' },
            { data: 'trade_name', name: 'trade_name', defaultContent: '-' },
            { data: 'agency_type', name: 'agency_type' },
            { data: 'phone', name: 'phone', defaultContent: '-' },
            { data: 'status', name: 'status' },
            { data: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
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

function resetShippingAgencyForm() {
    const form = $('#shippingAgencyForm');

    if (!form.length) {
        return;
    }

    form[0].reset();
    $('#shipping_agency_id').val('');
    $('#shippingBranchesBody').empty();
    $('#shippingContactsBody').empty();
    shippingBranchIndex = 0;
    shippingContactIndex = 0;
    $('#shippingAgencyModalLabel').text('Nueva Agencia de Envio');
    $('#btnSaveShippingAgency').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar');
    clearShippingAgencyErrors();
    refreshShippingCounts();
    $('#shippingAgencyTabs a:first').tab('show');
}

function saveShippingAgency(formElement) {
    clearShippingAgencyErrors();
    refreshShippingBranchIndexes();
    refreshShippingContactIndexes();

    const id = $('#shipping_agency_id').val();
    const formData = new FormData(formElement);
    const button = $('#btnSaveShippingAgency');
    const url = id ? `${window.routes.shippingAgencyUpdate}/${id}` : window.routes.shippingAgencyStore;

    if (id) {
        formData.append('_method', 'PUT');
    }

    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url: url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            $('#shippingAgencyModal').modal('hide');
            tableShippingAgency.ajax.reload(null, false);
            Swal.fire({
                icon: 'success',
                title: response.message || 'Agencia guardada correctamente.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        },
        error: function (xhr) {
            button.prop('disabled', false).html(`<i class="fas fa-save mr-1"></i> ${id ? 'Actualizar' : 'Guardar'}`);

            if (xhr.status === 422) {
                showShippingAgencyErrors(xhr.responseJSON.errors || {});
                Swal.fire({
                    icon: 'warning',
                    title: 'Revisa el formulario',
                    text: 'Hay campos obligatorios o con formato incorrecto.'
                });
                return;
            }

            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo guardar la agencia.'
            });
        }
    });
}

function addShippingBranchRow(data = {}) {
    const html = $('#shippingBranchRowTemplate').html().replaceAll('__INDEX__', shippingBranchIndex);
    $('#shippingBranchesBody').append(html);

    const row = $('#shippingBranchesBody tr.shipping-branch-row').last();
    row.find('[name$="[branch_name]"]').val(data.branch_name || '');
    row.find('[name$="[address]"]').val(data.address || '');
    row.find('[name$="[department]"]').val(data.department || '');
    row.find('[name$="[province]"]').val(data.province || '');
    row.find('[name$="[district]"]').val(data.district || '');
    row.find('[name$="[reference]"]').val(data.reference || '');
    row.find('[name$="[is_main]"]').prop('checked', Boolean(data.is_main));
    row.find('[name$="[phone]"]').val(data.phone || '');
    row.find('[name$="[email]"]').val(data.email || '');
    row.find('[name$="[status]"]').val(data.status || 'ACTIVE');

    shippingBranchIndex++;
    refreshShippingBranchIndexes();
    refreshShippingContactBranchOptions();
}

function addShippingContactRow(data = {}) {
    const html = $('#shippingContactRowTemplate').html().replaceAll('__INDEX__', shippingContactIndex);
    $('#shippingContactsBody').append(html);

    const row = $('#shippingContactsBody tr.shipping-contact-row').last();
    row.find('[name$="[contact_name]"]').val(data.contact_name || '');
    row.find('[name$="[position]"]').val(data.position || '');
    row.find('[name$="[branch_index]"]').attr('data-selected-branch-id', data.shipping_agency_branch_id || '');
    row.find('[name$="[phone]"]').val(data.phone || '');
    row.find('[name$="[whatsapp]"]').val(data.whatsapp || '');
    row.find('[name$="[email]"]').val(data.email || '');
    row.find('[name$="[is_primary]"]').prop('checked', Boolean(data.is_primary));
    row.find('[name$="[status]"]').val(data.status || 'ACTIVE');
    row.find('[name$="[observations]"]').val(data.observations || '');

    shippingContactIndex++;
    refreshShippingContactIndexes();
    refreshShippingContactBranchOptions();
}

function refreshShippingBranchIndexes() {
    $('#shippingBranchesBody tr.shipping-branch-row').each(function (index) {
        const row = $(this);
        row.attr('data-branch-index', index);
        row.find('.shipping-row-index').text(index + 1);
        row.find('[name]').each(function () {
            this.name = this.name.replace(/branches\[\d+]\[/, `branches[${index}][`);
        });
    });

    shippingBranchIndex = $('#shippingBranchesBody tr.shipping-branch-row').length;
    refreshShippingCounts();
}

function refreshShippingContactIndexes() {
    $('#shippingContactsBody tr.shipping-contact-row').each(function (index) {
        const row = $(this);
        row.find('.shipping-row-index').text(index + 1);
        row.find('[name]').each(function () {
            this.name = this.name.replace(/contacts\[\d+]\[/, `contacts[${index}][`);
        });
    });

    shippingContactIndex = $('#shippingContactsBody tr.shipping-contact-row').length;
    refreshShippingCounts();
}

function refreshShippingContactBranchOptions() {
    const branches = $('#shippingBranchesBody tr.shipping-branch-row').map(function (index) {
        const name = $(this).find('[name$="[branch_name]"]').val() || `Sede ${index + 1}`;
        const branchId = $(this).data('original-id') || '';

        return { index, name, branchId };
    }).get();

    $('.shipping-contact-branch').each(function () {
        const select = $(this);
        const current = select.val();
        const selectedBranchId = select.attr('data-selected-branch-id');
        let options = '<option value="">Agencia principal</option>';

        branches.forEach(function (branch) {
            const selected = selectedBranchId && String(selectedBranchId) === String(branch.branchId)
                ? ' selected'
                : (!selectedBranchId && String(current) === String(branch.index) ? ' selected' : '');

            options += `<option value="${branch.index}"${selected}>${escapeShippingAgencyHtml(branch.name)}</option>`;
        });

        select.html(options);
        select.removeAttr('data-selected-branch-id');
    });
}

function refreshShippingCounts() {
    $('#shippingAgencyBranchCount').text($('#shippingBranchesBody tr.shipping-branch-row').length);
    $('#shippingAgencyContactCount').text($('#shippingContactsBody tr.shipping-contact-row').length);
}

function loadShippingAgencyForEdit(id) {
    clearShippingAgencyErrors();

    $.get(`${window.routes.shippingAgencyShow}/${id}/edit`)
        .done(function (response) {
            fillShippingAgencyForm(response.data);
            $('#shippingAgencyModalLabel').text('Editar Agencia de Envio');
            $('#btnSaveShippingAgency').html('<i class="fas fa-save mr-1"></i> Actualizar');
            $('#shippingAgencyModal').modal('show');
        })
        .fail(function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo cargar la agencia.'
            });
        });
}

function fillShippingAgencyForm(agency) {
    resetShippingAgencyForm();
    $('#shipping_agency_id').val(agency.id || '');
    $('#shipping_ruc').val(agency.ruc || '');
    $('#shipping_business_name').val(agency.business_name || '');
    $('#shipping_trade_name').val(agency.trade_name || '');
    $('#shipping_agency_type').val(agency.agency_type || '');
    $('#shipping_phone').val(agency.phone || '');
    $('#shipping_email').val(agency.email || '');
    $('#shipping_website').val(agency.website || '');
    $('#shipping_status').val(agency.status || 'ACTIVE');
    $('#shipping_observations').val(agency.observations || '');

    (agency.branches || []).forEach(function (branch) {
        addShippingBranchRow(branch);
        $('#shippingBranchesBody tr.shipping-branch-row').last().data('original-id', branch.id);
    });

    (agency.contacts || []).forEach(addShippingContactRow);
    refreshShippingContactBranchOptions();
}

function loadShippingAgencyDetail(id) {
    $.get(`${window.routes.shippingAgencyShow}/${id}`)
        .done(function (response) {
            fillShippingAgencyDetail(response.data, response.agency_type_label);
            $('#viewShippingAgencyModal').modal('show');
        })
        .fail(function (xhr) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: xhr.responseJSON?.message || 'No se pudo cargar el detalle.'
            });
        });
}

function fillShippingAgencyDetail(agency, agencyTypeLabel) {
    const statusText = agency.status === 'ACTIVE' ? 'ACTIVO' : 'INACTIVO';
    const statusClass = agency.status === 'ACTIVE' ? 'badge-info' : 'badge-danger';

    $('#vsa_code').text(agency.code || '-');
    $('#vsa_status').text(statusText).attr('class', `badge ${statusClass} rounded-pill px-3 py-2`);
    $('#vsa_ruc').text(agency.ruc || '-');
    $('#vsa_agency_type').text(agencyTypeLabel || agency.agency_type || '-');
    $('#vsa_phone').text(agency.phone || '-');
    $('#vsa_business_name').text(agency.business_name || '-');
    $('#vsa_trade_name').text(agency.trade_name || '-');
    $('#vsa_email').text(agency.email || '-');
    $('#vsa_website').text(agency.website || '-');
    $('#vsa_updated_by').text(agency.updater?.name || '-');
    $('#vsa_observations').text(agency.observations || 'Sin observaciones');

    const branchRows = (agency.branches || []).map(function (branch, index) {
        const location = [branch.district, branch.province, branch.department].filter(Boolean).join(' / ') || '-';

        return `<tr>
            <td>${index + 1}</td>
            <td>${escapeShippingAgencyHtml(branch.branch_name || '-')} ${branch.is_main ? '<span class="badge badge-success ml-1">Principal</span>' : ''}</td>
            <td>${escapeShippingAgencyHtml(branch.address || '-')}</td>
            <td>${escapeShippingAgencyHtml(location)}</td>
            <td>${escapeShippingAgencyHtml(branch.reference || '-')}</td>
            <td>${branch.status === 'ACTIVE' ? '<span class="badge badge-info">ACTIVO</span>' : '<span class="badge badge-danger">INACTIVO</span>'}</td>
        </tr>`;
    }).join('');

    const contactRows = (agency.contacts || []).map(function (contact, index) {
        return `<tr>
            <td>${index + 1}</td>
            <td>${escapeShippingAgencyHtml(contact.contact_name || '-')} ${contact.is_primary ? '<span class="badge badge-success ml-1">Principal</span>' : ''}</td>
            <td>${escapeShippingAgencyHtml(contact.branch?.branch_name || 'Agencia principal')}</td>
            <td>${escapeShippingAgencyHtml(contact.phone || '-')}</td>
            <td>${escapeShippingAgencyHtml(contact.whatsapp || '-')}</td>
            <td>${escapeShippingAgencyHtml(contact.email || '-')}</td>
            <td>${contact.status === 'ACTIVE' ? '<span class="badge badge-info">ACTIVO</span>' : '<span class="badge badge-danger">INACTIVO</span>'}</td>
        </tr>`;
    }).join('');

    $('#vsa_branches_body').html(branchRows || '<tr><td colspan="6" class="text-center text-muted py-3">Sin sedes registradas</td></tr>');
    $('#vsa_contacts_body').html(contactRows || '<tr><td colspan="7" class="text-center text-muted py-3">Sin contactos registrados</td></tr>');
}

function deleteShippingAgency(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Eliminar agencia de envio',
        text: 'La agencia quedara eliminada de forma logica.',
        showCancelButton: true,
        confirmButtonText: 'Si, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#d33'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: `${window.routes.shippingAgencyDelete}/${id}`,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: function (response) {
                tableShippingAgency.ajax.reload(null, false);
                Swal.fire({
                    icon: 'success',
                    title: response.message || 'Agencia eliminada correctamente.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2500
                });
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'No se pudo eliminar la agencia.'
                });
            }
        });
    });
}

function clearShippingAgencyErrors() {
    $('#shippingAgencyForm .is-invalid').removeClass('is-invalid');
    $('#shippingAgencyForm .invalid-feedback').text('');
    $('#shippingAgencyErrors').addClass('d-none').empty();
}

function showShippingAgencyErrors(errors) {
    const messages = [];

    Object.entries(errors).forEach(function ([field, fieldMessages]) {
        const message = fieldMessages[0];
        const fieldName = field.replace(/\.(\d+)\./g, '[$1][') + (field.match(/\.\d+\./) ? ']' : '');
        const input = $(`[name="${field}"], [name="${fieldName}"]`);

        if (input.length) {
            input.addClass('is-invalid');
            input.closest('.form-group, td').find('.invalid-feedback').first().text(message);
        }

        messages.push(message);
    });

    $('#shippingAgencyErrors')
        .removeClass('d-none')
        .html(`<ul class="mb-0 pl-3">${messages.map(message => `<li>${escapeShippingAgencyHtml(message)}</li>`).join('')}</ul>`);
}

function escapeShippingAgencyHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
