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
                updateRolePermissionSummary();
                $('#roleModal').modal('show');
            },
            error: function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudieron cargar los permisos del rol.', 'error');
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
    const $document = $(document);
    $document.off('.rolePermissions');
    $document.on('change.rolePermissions', 'input[name="permissions[]"]', updateRolePermissionSummary);

    $('#rolePermissionSearch').off('.rolePermissions').on('input.rolePermissions', function () {
        filterRolePermissions($(this).val());
    });

    $('#btnSelectAllPermissions').off('.rolePermissions').on('click.rolePermissions', function () {
        getAllPermissionInputs().prop('checked', true);
        updateRolePermissionSummary();
    });

    $('#btnClearAllPermissions').off('.rolePermissions').on('click.rolePermissions', function () {
        getAllPermissionInputs().prop('checked', false);
        updateRolePermissionSummary();
    });

    $document.on('click.rolePermissions', '[data-scope-toggle]', function () {
        const $button = $(this);
        const selector = $button.data('scope-toggle') === 'module' ? '[data-permission-module]' : '[data-permission-subgroup]';
        const $inputs = $button.closest(selector).find('input[name="permissions[]"]');
        const shouldSelect = $inputs.filter(':checked').length !== $inputs.length;
        $inputs.prop('checked', shouldSelect);
        updateRolePermissionSummary();
    });

    $document.on('click.rolePermissions', '[data-role-collapse]', function () {
        const $module = $(this).closest('[data-permission-module]');
        const collapsed = !$module.hasClass('is-collapsed');
        $module.toggleClass('is-collapsed', collapsed);
        $(this).attr('aria-expanded', String(!collapsed));
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
    $('[data-permission-module]').addClass('is-collapsed').find('[data-role-collapse]').attr('aria-expanded', 'false');
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
    const normalizedTerm = normalizePermissionText(term);
    let visibleItems = 0;

    $('[data-permission-module]').each(function () {
        const $module = $(this);
        let moduleVisibleItems = 0;

        $module.find('[data-permission-subgroup]').each(function () {
            const $subgroup = $(this);
            let subgroupVisibleItems = 0;
            $subgroup.find('[data-permission-item]').each(function () {
                const $item = $(this);
                const matches = !normalizedTerm || normalizePermissionText($item.data('permission-text')).includes(normalizedTerm);

                $item.toggle(matches);
                if (matches) {
                    subgroupVisibleItems++;
                    moduleVisibleItems++;
                    visibleItems++;
                }
            });
            $subgroup.toggle(subgroupVisibleItems > 0);
        });

        $module.toggle(moduleVisibleItems > 0);
        if (normalizedTerm && moduleVisibleItems > 0) {
            $module.removeClass('is-collapsed').find('[data-role-collapse]').attr('aria-expanded', 'true');
        }
    });

    $('#rolePermissionEmpty').toggle(visibleItems === 0);
}

function updateRolePermissionSummary() {
    const $all = getAllPermissionInputs();
    const total = $all.length;
    const selected = $all.filter(':checked').length;

    $('#roleTotalPermissions').text(total);
    $('#roleSelectedPermissions').text(selected);

    $('[data-permission-subgroup]').each(function () {
        updatePermissionScope($(this), '[data-subgroup-selected]', 'Seleccionar submódulo', 'Quitar submódulo');
    });
    $('[data-permission-module]').each(function () {
        updatePermissionScope($(this), '[data-module-selected]', 'Seleccionar módulo', 'Quitar módulo');
    });
}

function updatePermissionScope($scope, counterSelector, selectLabel, clearLabel) {
    const $inputs = $scope.find('input[name="permissions[]"]');
    const selected = $inputs.filter(':checked').length;
    $scope.find(counterSelector).first().text(selected);
    $scope.find('[data-scope-toggle]').first().text(selected === $inputs.length && $inputs.length ? clearLabel : selectLabel);
}

function getAllPermissionInputs() {
    return $('#rolePermissionGroups input[name="permissions[]"]');
}

function normalizePermissionText(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
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
