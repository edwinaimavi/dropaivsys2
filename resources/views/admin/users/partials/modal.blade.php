<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true" data-backdrop="static" data-keyboard="false">

    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg">

            <div class="modal-header align-items-center"
                style="background:linear-gradient(90deg,#f4f9ff,#eaf3ff); border-bottom:1px solid #b8d7ff;">

                <div class="d-flex align-items-center">
                    <div class="mr-3 bg-white shadow-sm d-flex align-items-center justify-content-center"
                        style="width:42px;height:42px;border-radius:10px;">
                        <i class="fas fa-user-shield text-primary"></i>
                    </div>

                    <div>
                        <h5 class="modal-title mb-0 font-weight-bold" id="exampleModalLabel">
                            Nuevo Usuario
                        </h5>
                        <small class="text-muted">
                            Registro y mantenimiento de accesos al sistema
                        </small>
                    </div>
                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">
                    <span aria-hidden="true">&times;</span>
                </button>

            </div>

            <div class="modal-body" style="background:#f8fbff;">

                <form id="userForm" enctype="multipart/form-data">
                    @csrf

                    <div id="error-messages" class="alert alert-danger d-none"></div>

                    <div class="row">

                        <div class="col-lg-8">

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-header bg-white border-0 py-2 px-3">
                                    <h6 class="mb-0 font-weight-bold text-dark">
                                        <i class="fas fa-id-card text-primary mr-1"></i>
                                        Datos personales
                                    </h6>
                                </div>

                                <div class="card-body py-3">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label class="small font-weight-bold text-secondary" for="dni">
                                                DNI <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="dni"
                                                name="dni" placeholder="Ingrese DNI" required>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label class="small font-weight-bold text-secondary" for="name">
                                                Nombres <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="name"
                                                name="name" placeholder="Ingrese nombres" required>
                                        </div>

                                        <div class="form-group col-md-4">
                                            <label class="small font-weight-bold text-secondary" for="lastname">
                                                Apellidos <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="lastname"
                                                name="lastname" placeholder="Ingrese apellidos" required>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-5">
                                            <label class="small font-weight-bold text-secondary" for="phone">
                                                Celular
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="phone"
                                                name="phone" placeholder="Numero de celular">
                                        </div>

                                        <div class="form-group col-md-7">
                                            <label class="small font-weight-bold text-secondary" for="address">
                                                Direccion
                                            </label>
                                            <input type="text" class="form-control form-control-sm" id="address"
                                                name="address" placeholder="Direccion del usuario">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-0">
                                <div class="card-header bg-white border-0 py-2 px-3">
                                    <h6 class="mb-0 font-weight-bold text-dark">
                                        <i class="fas fa-key text-primary mr-1"></i>
                                        Acceso al sistema
                                    </h6>
                                </div>

                                <div class="card-body py-3">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label class="small font-weight-bold text-secondary" for="email">
                                                Email <span class="text-danger">*</span>
                                            </label>
                                            <input type="email" class="form-control form-control-sm" id="email"
                                                name="email" placeholder="correo@empresa.com" required>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label class="small font-weight-bold text-secondary" for="password">
                                                Password
                                            </label>
                                            <input type="password" class="form-control form-control-sm" id="password"
                                                name="password" autocomplete="new-password" required>
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label class="small font-weight-bold text-secondary"
                                                for="password_confirmation">
                                                Repetir
                                            </label>
                                            <input type="password" class="form-control form-control-sm"
                                                id="password_confirmation" name="password_confirmation"
                                                autocomplete="new-password" required>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label class="small font-weight-bold text-secondary" for="role">
                                                Rol <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control form-control-sm" name="role" id="role" required>
                                                <option value="">Seleccione un rol</option>
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label class="small font-weight-bold text-secondary">
                                                Estado <span class="text-danger">*</span>
                                            </label>
                                            <select class="form-control form-control-sm" name="status">
                                                <option value="1">Activo</option>
                                                <option value="0">Inactivo</option>
                                            </select>
                                        </div>
                                    </div>

                                    <small class="text-muted">
                                        En edicion, deja el password vacio si no deseas cambiarlo.
                                    </small>
                                </div>
                            </div>

                        </div>

                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-header bg-white border-0 py-2 px-3">
                                    <h6 class="mb-0 font-weight-bold text-dark">
                                        <i class="fas fa-camera text-primary mr-1"></i>
                                        Foto
                                    </h6>
                                </div>

                                <div class="card-body text-center">
                                    <div class="position-relative mx-auto mb-3 user-photo-box">
                                        <img id="imgPreview"
                                            src="https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg"
                                            class="w-100 h-100 rounded border bg-white" alt="Foto usuario">
                                    </div>

                                    <label class="btn btn-outline-primary btn-sm mb-0">
                                        <i class="fas fa-upload mr-1"></i>
                                        Cargar foto
                                        <input type="file" class="d-none" name="image" id="image"
                                            accept="image/*" onchange="previewImage(event, '#imgPreview')">
                                    </label>

                                    <div class="text-muted small mt-2">
                                        JPG o PNG hasta 2 MB.
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="d-flex justify-content-end mt-3">
                        <button type="button" class="btn btn-light border btn-sm mr-2" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>
                            Cerrar
                        </button>

                        <button type="submit" id="btnSaveUser" class="btn btn-primary btn-sm">
                            <i class="fas fa-save mr-1"></i>
                            Guardar Usuario
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<style>
    #userModal .modal-content {
        border-radius: 14px;
    }

    #userModal .card {
        border-radius: 12px;
    }

    #userModal label {
        font-size: 11px;
        margin-bottom: 2px;
    }

    #userModal .form-control,
    #userModal .custom-select {
        height: 31px;
        font-size: 12px;
    }

    #userModal .user-photo-box {
        width: 170px;
        height: 170px;
    }

    #userModal .user-photo-box img {
        object-fit: cover;
    }

    @media (max-width: 991px) {
        #userModal .modal-dialog {
            max-width: 96%;
        }

        #userModal .user-photo-box {
            width: 140px;
            height: 140px;
        }
    }
</style>
