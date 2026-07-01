<div class="modal fade users-modal" id="userModal" tabindex="-1" role="dialog" aria-hidden="true"
    data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg">
            <form id="userForm" enctype="multipart/form-data">
                @csrf

                <div class="modal-header border-0">
                    <div class="users-modal-title">
                        <div class="users-modal-title-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" id="exampleModalLabel">Nuevo Usuario</h5>
                            <small id="userModalSubtitle">Registro y administraci&oacute;n de usuarios del sistema</small>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div id="error-messages" class="alert alert-danger d-none"></div>

                    <div class="row">
                        <div class="col-lg-3 mb-3 mb-lg-0">
                            <aside class="users-side-panel text-center">
                                <div class="users-side-icon mx-auto mb-3">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <h6 class="font-weight-bold mb-1">Usuarios</h6>
                                <p class="text-muted small mb-3">Administra identidad, rol y acceso al sistema.</p>

                                <div class="users-side-avatar mx-auto mb-3">
                                    <img id="imgPreview"
                                        src="https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg"
                                        alt="Foto usuario">
                                </div>

                                <label class="btn btn-outline-success btn-sm users-photo-button mb-0">
                                    <i class="fas fa-upload mr-1"></i>
                                    Cargar foto
                                    <input type="file" class="d-none" name="image" id="image" accept="image/*"
                                        onchange="previewImage(event, '#imgPreview')">
                                </label>

                                <div class="text-muted small mt-2">
                                    JPG o PNG hasta 2 MB.
                                </div>
                            </aside>
                        </div>

                        <div class="col-lg-9">
                            <div class="card users-modal-card mb-3">
                                <div class="card-header border-0">
                                    <h6 class="mb-1 font-weight-bold">
                                        <i class="fas fa-id-card text-success mr-1"></i>
                                        Datos personales
                                    </h6>
                                    <small class="text-muted">Informaci&oacute;n principal del usuario</small>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label for="dni">DNI <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="dni"
                                                name="dni" placeholder="Ingrese DNI" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="name">Nombres <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="name"
                                                name="name" placeholder="Ingrese nombres" required>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="lastname">Apellidos <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="lastname"
                                                name="lastname" placeholder="Ingrese apellidos" required>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-5">
                                            <label for="phone">Celular</label>
                                            <input type="text" class="form-control form-control-sm" id="phone"
                                                name="phone" placeholder="N&uacute;mero de celular">
                                        </div>
                                        <div class="form-group col-md-7">
                                            <label for="address">Direcci&oacute;n</label>
                                            <input type="text" class="form-control form-control-sm" id="address"
                                                name="address" placeholder="Direcci&oacute;n del usuario">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card users-modal-card mb-0">
                                <div class="card-header border-0">
                                    <h6 class="mb-1 font-weight-bold">
                                        <i class="fas fa-key text-success mr-1"></i>
                                        Acceso al sistema
                                    </h6>
                                    <small class="text-muted">Credenciales, estado y rol asignado</small>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="email">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control form-control-sm" id="email"
                                                name="email" placeholder="correo@empresa.com" required>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="password">Contrase&ntilde;a</label>
                                            <input type="password" class="form-control form-control-sm" id="password"
                                                name="password" autocomplete="new-password" required>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label for="password_confirmation">Confirmar</label>
                                            <input type="password" class="form-control form-control-sm"
                                                id="password_confirmation" name="password_confirmation"
                                                autocomplete="new-password" required>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="role">Rol <span class="text-danger">*</span></label>
                                            <select class="form-control form-control-sm" name="role" id="role" required>
                                                <option value="">Seleccione un rol</option>
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="status">Estado <span class="text-danger">*</span></label>
                                            <select class="form-control form-control-sm" name="status" id="status">
                                                <option value="1">Activo</option>
                                                <option value="0">Inactivo</option>
                                            </select>
                                        </div>
                                    </div>

                                    <span class="users-password-help">
                                        <i class="fas fa-info-circle"></i>
                                        En edici&oacute;n, deja la contrase&ntilde;a vac&iacute;a si no deseas cambiarla.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>
                        Cerrar
                    </button>
                    <button type="submit" id="btnSaveUser" class="btn btn-success">
                        <i class="fas fa-save mr-1"></i>
                        Guardar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
