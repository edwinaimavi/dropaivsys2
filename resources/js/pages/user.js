var divLoading = document.getElementById('divLoading');
let tableUser;
let userDniLookupRequest = null;
let userDniLookupTimer = null;
let lastUserDniLookup = '';

const defaultUserImage = 'https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg';

document.addEventListener("DOMContentLoaded", function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    initUserTable();

    $('#dni').on('input', function () {
        const sanitizedDni = String(this.value || '').replace(/\D/g, '').slice(0, 8);
        this.value = sanitizedDni;

        if (sanitizedDni !== lastUserDniLookup) {
            lastUserDniLookup = '';
        }

        clearTimeout(userDniLookupTimer);
        if (sanitizedDni.length === 8) {
            userDniLookupTimer = setTimeout(() => lookupUserDni(), 450);
        }
    });

    $('#btnSearchUserDni').on('click', function () {
        lookupUserDni(true);
    });

    $('#userForm').on('submit', function (e) {
        e.preventDefault();
        $('#error-messages').addClass('d-none').empty();
        divLoading.style.display = "flex";

        const $form = $(this);
        const id = $form.attr('data-id');
        const btn = $('#btnSaveUser');
        const formData = new FormData(this);
        const url = id ? `/admin/users/${id}` : window.routes.storeUser;

        if (id) {
            formData.append('_method', 'PUT');
        }

        btn.prop('disabled', true).html(`
            <span class="spinner-border spinner-border-sm mr-1"></span>
            Guardando...
        `);

        $.ajax({
            url: url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                divLoading.style.display = "none";
                restoreUserSaveButton(id);
                $('#userModal').modal('hide');
                tableUser.ajax.reload(null, false);
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
                restoreUserSaveButton(id);

                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    let errorList = '<ul class="mb-0 pl-3">';
                    $.each(errors, function (key, messages) {
                        errorList += `<li>${escapeUserHtml(messages[0])}</li>`;
                    });
                    errorList += '</ul>';
                    $('#error-messages').removeClass('d-none').html(errorList);
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: xhr.responseJSON?.message || 'No se pudo guardar el usuario.'
                });
            }
        });
    });

    $(document).on('click', '.editUser', function () {
        const button = $(this);
        const photo = button.data('photo');

        $('#userForm').attr('data-id', button.data('id'));
        $('#dni').val(button.data('dni'));
        $('#name').val(button.data('name'));
        $('#lastname').val(button.data('lastname'));
        $('#email').val(button.data('email'));
        $('#phone').val(button.data('phone'));
        $('#address').val(button.data('address'));
        $('select[name="status"]').val(button.data('status'));
        $('#role').val(button.data('role'));
        $('#imgPreview').attr('src', getValidUserImage(photo));
        $('#password').prop('required', false).val('');
        $('#password_confirmation').prop('required', false).val('');
        configurePrincipalUserForm(Number(button.data('is-principal')) === 1);
        $('#btnSaveUser').html('<i class="fas fa-save mr-1"></i>Actualizar Usuario');
        $('#exampleModalLabel').text('Editar Usuario');
        $('#userModalSubtitle').text('Actualización de datos y permisos del usuario');
        $('#userModal').modal('show');
    });

    $(document).on('click', '.viewUser', function () {
        const button = $(this);
        const name = button.data('name') || '';
        const lastname = button.data('lastname') || '';
        const fullName = `${name} ${lastname}`.trim() || 'Sin nombre';
        const isActive = Number(button.data('status')) === 1;
        const photo = button.data('photo');
        const hasPhoto = Number(button.data('has-photo')) === 1;
        const isPrincipal = Number(button.data('is-principal')) === 1;
        const roleName = button.attr('data-role-name') || 'Sin rol';
        const historical = 'No registrado / histórico';

        $('#vu_name').text(fullName);
        $('#vu_firstname').text(name || historical).toggleClass('users-detail-historical', !name);
        $('#vu_lastname').text(lastname || historical).toggleClass('users-detail-historical', !lastname);
        $('#vu_dni').text(button.data('dni') || historical).toggleClass('users-detail-historical', !button.data('dni'));
        $('#vu_email, #vu_access_email, #vu_summary_email').text(button.data('email') || historical);
        $('#vu_phone, #vu_summary_phone').text(button.data('phone') || historical)
            .toggleClass('users-detail-historical', !button.data('phone'));
        $('#vu_address').text(button.data('address') || historical)
            .toggleClass('users-detail-historical', !button.data('address'));
        $('#vu_role_summary').text(roleName).toggleClass('users-role-chip-empty', roleName === 'Sin rol');
        $('#vu_role_detail').text(roleName).toggleClass('users-role-chip-empty', roleName === 'Sin rol');
        $('#vu_created_at').text(button.attr('data-created-at') || '-');
        $('#vu_updated_at').text(button.attr('data-updated-at') || '-');
        setUserPrincipalIndicator(isPrincipal);
        setUserAuditLoading();

        $('#vu_status')
            .text(isActive ? 'ACTIVO' : 'INACTIVO')
            .toggleClass('users-status-active', isActive)
            .toggleClass('users-status-inactive', !isActive);

        $('#vu_status_text')
            .text(isActive ? 'Activo' : 'Inactivo')
            .toggleClass('text-success', isActive)
            .toggleClass('text-danger', !isActive);

        $('#vu_photo').toggleClass('d-none', !hasPhoto);
        $('#vu_photo_placeholder').toggleClass('d-none', hasPhoto);
        $('#vu_photo_placeholder').text(getUserInitials(name, lastname));

        if (hasPhoto) {
            $('#vu_photo').attr('src', photo);
        } else {
            $('#vu_photo').removeAttr('src');
        }

        $('#user-detail-personal-tab').tab('show');
        $('#viewUserModal').modal('show');

        $.get(`${window.routes.showUser}/${button.data('id')}`)
            .done(function (response) {
                const audit = response.data || {};
                setUserHistoricalValue('#vu_created_by', audit.created_by, historical);
                setUserHistoricalValue('#vu_updated_by', audit.updated_by, historical);
                setUserHistoricalValue('#vu_role_changed_by', audit.last_role_changed_by, historical);
                setUserHistoricalValue('#vu_audit_created_at', audit.created_at, historical);
                setUserHistoricalValue('#vu_audit_updated_at', audit.updated_at, historical);
                setUserHistoricalValue('#vu_role_changed_at', audit.last_role_changed_at, historical);
                $('#vu_created_at').text(audit.created_at || historical);
                $('#vu_updated_at').text(audit.updated_at || historical);
                setUserPrincipalIndicator(Boolean(audit.is_principal));
            })
            .fail(function () {
                setUserAuditFallback();
                Swal.fire({
                    icon: 'warning',
                    title: 'Trazabilidad no disponible',
                    text: 'No se pudo cargar la información de auditoría del usuario.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3500
                });
            });
    });

    $('#userModal').on('hidden.bs.modal', function () {
        resetUserModal();
    });

    $(document).on('click', '#btnCreateUser', function () {
        resetUserModal();
        $('#password').prop('required', true);
        $('#password_confirmation').prop('required', true);
        $('#userModal').modal('show');
    });

    $(document).on('click', '.deleteUser', function () {
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
                    url: `${window.routes.deleteUser}/${id}`,
                    type: 'DELETE',
                    success: function (response) {
                        tableUser.ajax.reload(null, false);
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
                        Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo eliminar el usuario.', 'error');
                    }
                });
            }
        });
    });
});

function initUserTable() {
    tableUser = $('#tableUser').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.usersList,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'dni', name: 'dni' },
            { data: 'name', name: 'name', render: renderUserNameCell },
            { data: 'email', name: 'email', render: renderUserEmailCell },
            { data: 'phone', name: 'phone', defaultContent: '-' },
            { data: 'roles_display', name: 'roles_display', orderable: false, searchable: false },
            { data: 'status', name: 'status', orderable: false, searchable: false },
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
            <'row mt-3'<'col-sm-12 text-center'B>>
        `,
        buttons: [
            { extend: 'excel', className: 'btn btn-success btn-sm', text: '<i class="fas fa-file-excel"></i> Excel' },
            { extend: 'pdf', className: 'btn btn-danger btn-sm', text: '<i class="fas fa-file-pdf"></i> PDF' },
            { extend: 'print', className: 'btn btn-secondary btn-sm', text: '<i class="fas fa-print"></i> Imprimir' }
        ]
    });
}

function resetUserModal() {
    const $form = $('#userForm');

    if ($form.length && $form[0]) {
        $form[0].reset();
    }

    $form.removeAttr('data-id');
    $('#exampleModalLabel').text('Nuevo Usuario');
    $('#userModalSubtitle').text('Registro y administración de usuarios del sistema');
    $('#btnSaveUser').prop('disabled', false).html('<i class="fas fa-save mr-1"></i>Guardar Usuario');
    $('#password').prop('required', true);
    $('#password_confirmation').prop('required', true);
    configurePrincipalUserForm(false);
    $('#error-messages').addClass('d-none').empty();
    $('#imgPreview').attr('src', defaultUserImage);
    $('#image').val('');
    clearTimeout(userDniLookupTimer);
    lastUserDniLookup = '';
    setUserDniLookupLoading(false);
}

function configurePrincipalUserForm(isPrincipal) {
    const protectedFields = '#role, #status';

    $(protectedFields).prop('disabled', isPrincipal);
    $('#principal-user-notice').toggleClass('d-none', !isPrincipal);
    $('#dni, #email, #role').prop('required', true);
}

function setUserAuditLoading() {
    $('#vu_created_by, #vu_updated_by, #vu_role_changed_by, #vu_audit_created_at, #vu_audit_updated_at, #vu_role_changed_at')
        .removeClass('users-detail-historical')
        .text('Cargando...');
}

function setUserAuditFallback() {
    const historical = 'No registrado / histórico';
    $('#vu_created_by, #vu_updated_by, #vu_role_changed_by, #vu_audit_created_at, #vu_audit_updated_at, #vu_role_changed_at')
        .text(historical)
        .addClass('users-detail-historical');
}

function setUserHistoricalValue(selector, value, fallback) {
    $(selector)
        .text(value || fallback)
        .toggleClass('users-detail-historical', !value || value === fallback);
}

function setUserPrincipalIndicator(isPrincipal) {
    $('#vu_principal_indicator').text(isPrincipal ? 'Usuario principal protegido' : 'Usuario regular');
    $('#vu_principal_field').toggleClass('is-principal', isPrincipal);
}

function lookupUserDni(forceLookup = false) {
    const $dni = $('#dni');
    const dni = String($dni.val() || '').replace(/\D/g, '').slice(0, 8);
    $dni.val(dni);

    if (!/^\d{8}$/.test(dni)) {
        if (forceLookup) {
            showUserDniMessage('El DNI debe tener exactamente 8 dígitos.', 'warning');
        }
        return;
    }

    if (!forceLookup && lastUserDniLookup === dni) {
        return;
    }

    if (userDniLookupRequest) {
        userDniLookupRequest.abort();
    }

    lastUserDniLookup = dni;
    setUserDniLookupLoading(true);

    userDniLookupRequest = $.ajax({
        url: window.routes.userDniLookup.replace('DNI_PLACEHOLDER', dni),
        type: 'GET',
        dataType: 'json'
    }).done(function (response) {
        if (String($('#dni').val()) !== dni) return;

        const person = response.data || response;
        const names = person.nombres || response.nombres || '';
        const lastnames = person.apellidos
            || response.apellidos
            || [person.apellidoPaterno, person.apellidoMaterno].filter(Boolean).join(' ')
            || [response.apellidoPaterno, response.apellidoMaterno].filter(Boolean).join(' ');

        if (!response.success || (!names && !lastnames)) {
            showUserDniMessage();
            return;
        }

        if (names) $('#name').val(names);
        if (lastnames) $('#lastname').val(lastnames);

        Swal.fire({
            icon: 'success',
            title: 'Datos encontrados',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2200
        });
    }).fail(function (xhr, status) {
        if (status === 'abort') return;
        showUserDniMessage(xhr.responseJSON?.message);
    }).always(function () {
        userDniLookupRequest = null;
        setUserDniLookupLoading(false);
    });
}

function setUserDniLookupLoading(isLoading) {
    $('#btnSearchUserDni').prop('disabled', isLoading);
    $('#userDniSearchIcon').toggleClass('d-none', isLoading);
    $('#userDniSearchLoading').toggleClass('d-none', !isLoading);
}

function showUserDniMessage(message, icon = 'warning') {
    const fallbackMessage = 'No se pudo consultar el DNI. Complete los datos manualmente.';
    const detail = message && !String(message).includes('Complete los datos manualmente')
        ? `${message} Complete los datos manualmente.`
        : (message || fallbackMessage);

    Swal.fire({
        icon: icon,
        title: 'Consulta de DNI',
        text: detail,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000
    });
}

function restoreUserSaveButton(id) {
    $('#btnSaveUser')
        .prop('disabled', false)
        .html(`<i class="fas fa-save mr-1"></i>${id ? 'Actualizar Usuario' : 'Guardar Usuario'}`);
}

function renderUserNameCell(data, type, row) {
    if (type !== 'display') {
        return data;
    }

    const name = escapeUserHtml([row.name, row.lastname].filter(Boolean).join(' ') || '-');
    const dni = escapeUserHtml(row.dni || 'Sin DNI');
    const initials = escapeUserHtml(getUserInitials(row.name, row.lastname));

    return `
        <span class="users-name-cell">
            <span class="users-name-avatar">${initials}</span>
            <span>
                <span class="users-name-main">${name}</span>
                <span class="users-name-sub">DNI: ${dni}</span>
            </span>
        </span>
    `;
}

function renderUserEmailCell(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="users-email-cell"><i class="fas fa-envelope text-muted"></i>${escapeUserHtml(data || '-')}</span>`;
}

function getUserInitials(name, lastname) {
    const first = String(name || '').trim().charAt(0);
    const last = String(lastname || '').trim().charAt(0);

    return `${first}${last}`.toUpperCase() || 'U';
}

function isValidUserImage(photo) {
    return Boolean(photo && /\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(photo));
}

function getValidUserImage(photo) {
    return isValidUserImage(photo) ? photo : defaultUserImage;
}

function escapeUserHtml(value) {
    return $('<div>').text(value ?? '').html();
}
