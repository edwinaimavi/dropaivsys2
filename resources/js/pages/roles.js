var divLoading = document.querySelector("#divLoading");
let tableRole;

document.addEventListener("DOMContentLoaded", function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    initRoleTable();
    initRoleModalUi();

    $('#roleForm').on('submit', function (e) {
        e.preventDefault();
        divLoading.style.display = "flex";

        const $form = $(this);
        clearRoleValidationErrors();
        const id = $form.attr('data-id');
        const url = id ? `/admin/roles/${id}` : window.routes.storeRole;
        const type = id ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: type,
            data: $form.serialize(),
            success: function (response) {
                divLoading.style.display = "none";
                $('#roleModal').modal('hide');
                tableRole.ajax.reload(null, false);
                Swal.fire({
                    title: response.message,
                    icon: "success",
                    toast: true,
                    position: "top-end",
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                });
            },
            error: function (xhr) {
                divLoading.style.display = "none";
                if (xhr.status === 422) {
                    showRoleValidationErrors(xhr.responseJSON.errors || {});
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'No se pudo guardar el rol.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3500
                });
            }
        });
    });

    $('#roleModal').on('hidden.bs.modal', function () {
        resetRoleModal();
    });

    $('#roleModal').on('shown.bs.modal', function () {
        updateRolePermissionSummary();
        $('#name').trigger('focus');
    });

    $(document).on('click', '.editRole', function () {
        const id = $(this).data('id');
        const name = $(this).data('name');

        resetRoleModal(false);
        $('#roleForm').attr('data-id', id);
        $('#name').val(name);
        $('#exampleModalLabel').text('Editar Rol');
        $('#btnSaveRole').html('<i class="fas fa-save mr-1"></i>Actualizar Rol');

        $('input[name="permissions[]"]').prop('checked', false);

        $.ajax({
            url: `/admin/roles/${id}/permissions`,
            method: 'GET',
            success: function (data) {
                data.forEach(function (permissionName) {
                    $('input[name="permissions[]"]').filter(function () {
                        return this.value === permissionName;
                    }).prop('checked', true);
                });
            },
            complete: function () {
                updateRolePermissionSummary();
                $('#roleModal').modal('show');
            }
        });
    });

    $(document).on('click', '.deleteRole', function () {
        const id = $(this).data('id');
        Swal.fire({
            title: '¿Estás seguro?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `${window.routes.deleteRole}/${id}`,
                    type: 'DELETE',
                    success: function (response) {
                        tableRole.ajax.reload(null, false);
                        Swal.fire({
                            icon: 'success',
                            title: response.message || 'Rol eliminado correctamente.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    },
                    error: function () {
                        Swal.fire('Error', 'No se puede eliminar este rol porque está asignado a uno o más usuarios.', 'error');
                    }
                });
            }
        });
    });
});

function initRoleTable() {
    tableRole = $('#tableRole').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.rolesList,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name', render: renderRoleName },
            { data: 'guard_name', name: 'guard_name', render: renderRoleGuard },
            { data: 'permissions_count', name: 'permissions_count', orderable: false, searchable: false, render: renderRolePermissionsCount },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        language: {
            processing: 'Procesando...',
            lengthMenu: 'Mostrar _MENU_ registros',
            zeroRecords: 'No se encontraron resultados',
            emptyTable: 'No hay registros disponibles',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
            infoEmpty: 'Mostrando 0 a 0 de 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros totales)',
            search: 'Buscar:',
            loadingRecords: 'Cargando...',
            paginate: {
                first: 'Primero',
                last: 'Último',
                next: 'Siguiente',
                previous: 'Anterior'
            },
            aria: {
                sortAscending: ': activar para ordenar la columna ascendente',
                sortDescending: ': activar para ordenar la columna descendente'
            }
        },
        dom: `
            <'row mb-3'
                <'col-sm-12 col-md-6'l>
                <'col-sm-12 col-md-6 text-md-right'f>
            >
            <'row'<'col-sm-12'tr>>
            <'row mt-3'
                <'col-sm-12 col-md-5'i>
                <'col-sm-12 col-md-7 d-flex justify-content-center justify-content-md-end'p>
            >
        `
    });
}

function initRoleModalUi() {
    $(document).on('change', 'input[name="permissions[]"]', updateRolePermissionSummary);

    $('#rolePermissionSearch').on('input', function () {
        filterRolePermissions($(this).val());
    });

    $('#btnSelectAllPermissions').on('click', function () {
        getVisiblePermissionInputs().prop('checked', true);
        updateRolePermissionSummary();
    });

    $('#btnClearAllPermissions').on('click', function () {
        getVisiblePermissionInputs().prop('checked', false);
        updateRolePermissionSummary();
    });

    $(document).on('click', '.btnSelectPermissionGroup', function () {
        $(this)
            .closest('[data-permission-group]')
            .find('[data-permission-item]:visible input[name="permissions[]"]')
            .prop('checked', true);
        updateRolePermissionSummary();
    });
}

function resetRoleModal(resetForm = true) {
    const $form = $('#roleForm');

    if (resetForm && $form.length && $form[0]) {
        $form[0].reset();
    }

    $form.removeAttr('data-id');
    $('#exampleModalLabel').text('Nuevo Rol');
    $('#btnSaveRole').html('<i class="fas fa-save mr-1"></i>Guardar Rol');
    clearRoleValidationErrors();
    $('#rolePermissionSearch').val('');
    filterRolePermissions('');
    updateRolePermissionSummary();
}

function clearRoleValidationErrors() {
    $('#name').removeClass('is-invalid');
    $('#name-error').text('');
    $('#permissions-error').addClass('d-none').empty();
    $('#error-messages').addClass('d-none').empty();
}

function showRoleValidationErrors(errors) {
    const generalErrors = [];

    $.each(errors, function (key, messages) {
        const message = messages[0] || 'Revise los datos ingresados.';

        if (key === 'name') {
            $('#name').addClass('is-invalid');
            $('#name-error').text(message);
            return;
        }

        if (key === 'permissions' || key.startsWith('permissions.')) {
            $('#permissions-error')
                .removeClass('d-none')
                .text(message);
            return;
        }

        generalErrors.push(message);
    });

    if (generalErrors.length) {
        const errorList = generalErrors
            .map((message) => `<li>${escapeRoleHtml(message)}</li>`)
            .join('');

        $('#error-messages')
            .removeClass('d-none')
            .html(`<ul class="mb-0 pl-3">${errorList}</ul>`);
    }
}

function filterRolePermissions(term) {
    const normalizedTerm = String(term || '').toLowerCase().trim();
    let visibleItems = 0;

    $('[data-permission-group]').each(function () {
        const $group = $(this);
        let groupVisibleItems = 0;

        $group.find('[data-permission-item]').each(function () {
            const $item = $(this);
            const text = String($item.data('permission-text') || '').toLowerCase();
            const matches = !normalizedTerm || text.includes(normalizedTerm);

            $item.toggle(matches);
            if (matches) {
                groupVisibleItems++;
                visibleItems++;
            }
        });

        $group.toggle(groupVisibleItems > 0);
    });

    $('#rolePermissionEmpty').toggle(visibleItems === 0);
}

function updateRolePermissionSummary() {
    const total = $('input[name="permissions[]"]').length;
    const selected = $('input[name="permissions[]"]:checked').length;

    $('#roleTotalPermissions').text(total);
    $('#roleSelectedPermissions').text(selected);
}

function getVisiblePermissionInputs() {
    return $('[data-permission-item]:visible input[name="permissions[]"]');
}

function renderRoleName(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `
        <span class="roles-name-cell">
            <span class="roles-name-icon"><i class="fas fa-user-shield"></i></span>
            <span class="roles-name-text">${escapeRoleHtml(data || '-')}</span>
        </span>
    `;
}

function renderRoleGuard(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="roles-guard-pill"><i class="fas fa-shield-alt"></i>${escapeRoleHtml(data || '-')}</span>`;
}

function renderRolePermissionsCount(data, type) {
    if (type !== 'display') {
        return data;
    }

    const count = parseInt(data, 10) || 0;
    const label = count === 1 ? 'permiso' : 'permisos';

    return `<span class="roles-permissions-pill"><i class="fas fa-key"></i>${count} ${label}</span>`;
}

function escapeRoleHtml(value) {
    return $('<div>').text(value ?? '').html();
}
