var divLoading = document.querySelector("#divLoading");
let tableRole;
document.addEventListener("DOMContentLoaded", function () {

     $.ajaxSetup({
        headers: {
            // CSRF token for AJAX
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });


 // GUARDAR / ACTUALIZAR
    $('#roleForm').on('submit', function (e) {
        e.preventDefault();
        divLoading.style.display = "flex"; // Mostrar el loading
        const $form = $(this);
        const id = $form.attr('data-id'); 
        let url = '';
        let type = '';

        if (id) {
            url = "/admin/roles/" + id;
            type = 'PUT';
        } else {
            url = window.routes.storeRole;
            type = 'POST';
        }

        const formData = $form.serialize();

        $.ajax({
            url: url,
            type: type,
            data: formData,
            success: function (response) {
                divLoading.style.display = "none"; // Ocultar el loading
                $('#roleModal').modal('hide');
                tableRole.ajax.reload(null, false);
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
                if (xhr.status === 422) {
                    const errors = xhr.responseJSON.errors;
                    let errorList = '<ul>';
                    $.each(errors, function (key, messages) {
                        errorList += `<li>${messages[0]}</li>`;
                    });
                    errorList += '</ul>';
                    $('#error-messages').removeClass('d-none').html(errorList);
                }
            }
        });
    });



           // LIMPIAR AL CERRAR MODAL
    $('#roleModal').on('hidden.bs.modal', function () {
        const $form = $('#roleForm');
        $form[0].reset();
        $form.removeAttr('data-id');
        $('#exampleModalLabel').text('Nuevo Rol');
        $('#error-messages').addClass('d-none').empty();
    });

  
 // CARGAR DATOS PARA EDITAR
        $(document).on('click', '.editRole', function () {
            const id = $(this).data('id');
            const name = $(this).data('name');

            $('#roleForm').attr('data-id', id);
            $('#name').val(name);
            $('#exampleModalLabel').text('Editar Rol');
            
            // 1. Limpiar todos los checkboxes primero (evitar que queden marcados de otra edición)
            $('input[name="permissions[]"]').prop('checked', false);

            // 2. Obtener los permisos asignados desde el servidor
            $.ajax({
                url: `/admin/roles/${id}/permissions`,
                method: 'GET',
                success: function (data) {
                    console.log("Permisos asignados:", data);
                    data.forEach(function (permissionName) {
                        $(`input[name="permissions[]"][value="${permissionName}"]`).prop('checked', true);
                    });
                },

                complete: function () {
                    $('#roleModal').modal('show');
                }
            });
        });

$(document).ready(function(){
    tableRole =$('#tableRole').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.rolesList,
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex' },
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'guard_name', name: 'guard_name' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true
    })
});

// Eliminar marca
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
                        title: response.message,
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


