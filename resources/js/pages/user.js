//alamacenamos en una variable el div de loading
var divLoading = document.getElementById('divLoading');
let tableUser;

//Esta a la espera de que se cargue el DOM
document.addEventListener("DOMContentLoaded", function () {

    // CSRF token para AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // GUARDAR / ACTUALIZAR
    // GUARDAR / ACTUALIZAR
    $('#userForm').on('submit', function (e) {
        e.preventDefault();
        $('#error-messages').addClass('d-none').empty();
        divLoading.style.display = "flex"; // Mostrar el loading
        const $form = $(this);
        const id = $form.attr('data-id'); 
        const btn = $('#btnSaveUser');

        btn.prop('disabled', true).html(`
            <span class="spinner-border spinner-border-sm mr-1"></span>
            Guardando...
        `);
 
        let url = '';
        let type = '';
        //importante para subir imagenes/ eliminar el serializador de datos
        const formData = new FormData(this);
        if (id) {
            url = "/admin/users/" + id;
            type = 'POST';
            formData.append('_method', 'PUT'); // Laravel necesita esto
        } else {
            url = window.routes.storeUser;
            type = 'POST';
        }

        

        $.ajax({
            url: url,
            type: type,
            data: formData,
            processData: false, // necesario para enviar FormData
            contentType: false, // necesario para enviar FormData
            success: function (response) {
                divLoading.style.display = "none"; // Ocultar el loading
                btn.prop('disabled', false).html(`
                    <i class="fas fa-save mr-1"></i>
                    Guardar Usuario
                `);
                $('#userModal').modal('hide');
                tableUser.ajax.reload(null, false);
                Swal.fire({
                    title: response.message,
                    icon: "success", // o "error", según el contexto
                    toast: true,
                    position: "top-end", // puedes cambiar a "bottom-end", "top-start", etc.
                    showConfirmButton: false,
                    timer: 3000, // duración en milisegundos
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.addEventListener('mouseenter', Swal.stopTimer);
                        toast.addEventListener('mouseleave', Swal.resumeTimer);
                    }
                });

            },
            error: function (xhr) {
                divLoading.style.display = "none"; // Ocultar el loading
                btn.prop('disabled', false).html(`
                    <i class="fas fa-save mr-1"></i>
                    Guardar Usuario
                `);
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    let errorList = '<ul>';
                    $.each(errors, function (key, messages) {
                        errorList += `<li>${messages[0]}</li>`;
                    });
                    errorList += '</ul>';
                    $('#error-messages').removeClass('d-none').html(errorList);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: xhr.responseJSON?.message || 'No se pudo guardar el usuario.'
                    });
                }
            }
        });
    });

 
    // CARGAR DATOS PARA EDITAR
    $(document).on('click', '.editUser', function () {
        const id = $(this).data('id');
        const dni = $(this).data('dni');
        const name = $(this).data('name');
        const lastname = $(this).data('lastname');
        const email = $(this).data('email');
        const phone = $(this).data('phone');
        const address = $(this).data('address');
        const status = $(this).data('status');
        const role = $(this).data('role'); // Obtener el rol del usuario
        const photo = $(this).data('photo');
        const defaultImage = 'https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg';
        const isValidImage = photo && /\.(jpg|jpeg|png|gif|webp)$/i.test(photo);
        const validImage = isValidImage ? photo : defaultImage;      
 
        $('#userForm').attr('data-id', id);
        $('#dni').val(dni);
        $('#name').val(name);
        $('#lastname').val(lastname);
        $('#email').val(email);
        $('#phone').val(phone);
        $('#address').val(address);
        $('select[name="status"]').val(status);
        $('#role').val($(this).data('role'));
        $('#imgPreview').attr('src', validImage);
        $('#password').prop('required', false);
        $('#password_confirmation').prop('required', false);
        $('#btnSaveUser').html('<i class="fas fa-save mr-1"></i> Actualizar Usuario');
        $('#exampleModalLabel').text('Editar Usuario');       
        $('#userModal').modal('show'); 
    });

    $(document).on('click', '.viewUser', function () {
        const button = $(this);
        const name = button.data('name') || '';
        const lastname = button.data('lastname') || '';
        const fullName = `${name} ${lastname}`.trim() || 'Sin nombre';
        const isActive = Number(button.data('status')) === 1;
        const photo = button.data('photo');
        const hasPhoto = photo && /\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i.test(photo);

        $('#vu_name').text(fullName);
        $('#vu_dni').text(button.data('dni') || '—');
        $('#vu_email').text(button.data('email') || '—');
        $('#vu_phone').text(button.data('phone') || '—');
        $('#vu_address').text(button.data('address') || 'Sin dirección registrada');
        const roleName = button.attr('data-role-name') || 'Sin rol';

        $('#vu_role_summary').text(roleName);
        $('#vu_role_detail').text(roleName);
        $('#vu_created_at').text(button.attr('data-created-at') || '—');
        $('#vu_updated_at').text(button.attr('data-updated-at') || '—');

        $('#vu_status')
            .text(isActive ? 'ACTIVO' : 'INACTIVO')
            .toggleClass('badge-success', isActive)
            .toggleClass('badge-danger', !isActive);

        $('#vu_status_text')
            .text(isActive ? 'Activo' : 'Inactivo')
            .toggleClass('text-success', isActive)
            .toggleClass('text-danger', !isActive);

        $('#vu_photo').toggleClass('d-none', !hasPhoto);
        $('#vu_photo_placeholder').toggleClass('d-none', hasPhoto);

        if (hasPhoto) {
            $('#vu_photo').attr('src', photo);
        } else {
            $('#vu_photo').removeAttr('src');
        }

        $('#viewUserModal').modal('show');
    });

    // LIMPIAR AL CERRAR MODAL
    $('#userModal').on('hidden.bs.modal', function () {
        const $form = $('#userForm');
        $form[0].reset();
        $form.removeAttr('data-id');
        $('#exampleModalLabel').text('Nuevo Usuario');
        $('#btnSaveUser').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar Usuario');
        $('#password').prop('required', true);
        $('#password_confirmation').prop('required', true);
        $('#error-messages').addClass('d-none').empty();

        const defaultImage = 'https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg';
        $('#imgPreview').attr('src', defaultImage);
        $('#image').val('');
    });



    $(document).on('click', '#btnCreateUser', function () {
        $('#password').prop('required', true);
        $('#password_confirmation').prop('required', true);
        $('#btnSaveUser').html('<i class="fas fa-save mr-1"></i> Guardar Usuario');
    });

    // DATATABLE USUARIOS
        tableUser = $('#tableUser').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.usersList,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex' },
            { data: 'id', name: 'id' },
            { data: 'dni', name: 'dni' },
            { data: 'name', name: 'name' },
            { data: 'email', name: 'email' },
            { data: 'phone', name: 'phone' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
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
        ]
        
    });

    
    // Eliminar Usuario
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
                    error: function () {
                        Swal.fire('Error', 'Ocurrió un error al eliminar el Tipo', 'error');
                    }
                });
            }
        });
    });
        

});



