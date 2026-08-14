<div class="modal fade dp-profile-modal" id="modalUserProfile" tabindex="-1" role="dialog"
    aria-labelledby="modalUserProfileLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0">
            <form id="userProfileForm" enctype="multipart/form-data" novalidate>
                @csrf

                <div class="modal-header border-0">
                    <div class="dp-profile-heading">
                        <span class="dp-profile-heading-icon"><i class="fas fa-user-circle"></i></span>
                        <span>
                            <span class="modal-title" id="modalUserProfileLabel">Mi Perfil</span>
                            <small>Administra tu informaci&oacute;n personal y seguridad</small>
                        </span>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body position-relative">
                    <div id="profileModalLoading" class="dp-profile-loading">
                        <span class="spinner-border text-success" role="status" aria-hidden="true"></span>
                        <strong>Cargando tu perfil...</strong>
                    </div>

                    <div class="row">
                        <div class="col-lg-4 mb-3 mb-lg-0">
                            <aside class="dp-profile-summary">
                                <div class="dp-profile-avatar-wrap">
                                    <img id="profileAvatarImage" class="dp-profile-avatar d-none" alt="Foto de perfil">
                                    <div id="profileAvatarInitials" class="dp-profile-avatar dp-profile-avatar-initials">U</div>
                                    <label for="profileImage" class="dp-profile-avatar-edit" title="Cambiar foto">
                                        <i class="fas fa-camera"></i>
                                        <span class="sr-only">Cambiar foto de perfil</span>
                                    </label>
                                </div>

                                <h5 id="profileSummaryName" class="dp-profile-summary-name">Usuario</h5>
                                <span id="profileSummaryRole" class="dp-profile-role-chip">Sin rol</span>
                                <span id="profileSummaryStatus" class="dp-profile-status-chip is-active">
                                    <i class="fas fa-check-circle"></i> Activo
                                </span>
                                <p id="profileSummaryEmail" class="dp-profile-summary-email">-</p>

                                <label for="profileImage" class="btn btn-outline-success btn-sm dp-profile-photo-button">
                                    <i class="fas fa-upload mr-1"></i> Cambiar foto
                                </label>
                                <input type="file" class="d-none" id="profileImage" name="image"
                                    accept="image/jpeg,image/png,image/webp">
                                <small class="dp-profile-photo-help">JPG, PNG o WEBP. M&aacute;ximo 2 MB.</small>
                                <div class="dp-profile-field-error" data-profile-error="image"></div>
                            </aside>
                        </div>

                        <div class="col-lg-8">
                            <ul class="nav nav-pills dp-profile-tabs" id="profileTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link active" id="profile-personal-tab" data-toggle="pill"
                                        href="#profile-personal" role="tab" aria-controls="profile-personal"
                                        aria-selected="true">
                                        <i class="fas fa-id-card"></i> Datos personales
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" id="profile-security-tab" data-toggle="pill"
                                        href="#profile-security" role="tab" aria-controls="profile-security"
                                        aria-selected="false">
                                        <i class="fas fa-shield-alt"></i> Seguridad
                                    </a>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link" id="profile-trace-tab" data-toggle="pill"
                                        href="#profile-trace" role="tab" aria-controls="profile-trace"
                                        aria-selected="false">
                                        <i class="fas fa-history"></i> Trazabilidad
                                    </a>
                                </li>
                            </ul>

                            <div class="tab-content dp-profile-tab-content" id="profileTabsContent">
                                <div class="tab-pane fade show active" id="profile-personal" role="tabpanel"
                                    aria-labelledby="profile-personal-tab">
                                    <div class="dp-profile-section-heading">
                                        <div><strong>Informaci&oacute;n personal</strong><small>Actualiza tus datos de contacto e identidad.</small></div>
                                        <i class="fas fa-user-edit"></i>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label for="profileDni">DNI</label>
                                            <input type="text" class="form-control form-control-sm" id="profileDni"
                                                name="dni" inputmode="numeric" maxlength="8" autocomplete="off">
                                            <div class="dp-profile-field-error" data-profile-error="dni"></div>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="profileName">Nombres <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="profileName"
                                                name="name" autocomplete="given-name" required>
                                            <div class="dp-profile-field-error" data-profile-error="name"></div>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label for="profileLastname">Apellidos</label>
                                            <input type="text" class="form-control form-control-sm" id="profileLastname"
                                                name="lastname" autocomplete="family-name">
                                            <div class="dp-profile-field-error" data-profile-error="lastname"></div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-7">
                                            <label for="profileEmail">Correo electr&oacute;nico <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control form-control-sm" id="profileEmail"
                                                name="email" autocomplete="email" required>
                                            <div class="dp-profile-field-error" data-profile-error="email"></div>
                                        </div>
                                        <div class="form-group col-md-5">
                                            <label for="profilePhone">Celular</label>
                                            <input type="text" class="form-control form-control-sm" id="profilePhone"
                                                name="phone" autocomplete="tel">
                                            <div class="dp-profile-field-error" data-profile-error="phone"></div>
                                        </div>
                                    </div>

                                    <div class="form-group mb-0">
                                        <label for="profileAddress">Direcci&oacute;n</label>
                                        <textarea class="form-control form-control-sm" id="profileAddress" name="address" rows="3"
                                            autocomplete="street-address"></textarea>
                                        <div class="dp-profile-field-error" data-profile-error="address"></div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="profile-security" role="tabpanel"
                                    aria-labelledby="profile-security-tab">
                                    <div class="dp-profile-section-heading">
                                        <div><strong>Cambiar contrase&ntilde;a</strong><small>Completa estos campos solo si deseas actualizarla.</small></div>
                                        <i class="fas fa-lock"></i>
                                    </div>

                                    <div class="dp-profile-security-note">
                                        <i class="fas fa-info-circle"></i>
                                        La contrase&ntilde;a actual es obligatoria para confirmar cualquier cambio de clave.
                                    </div>

                                    <div class="form-group">
                                        <label for="profileCurrentPassword">Contrase&ntilde;a actual</label>
                                        <div class="input-group input-group-sm">
                                            <input type="password" class="form-control" id="profileCurrentPassword"
                                                name="current_password" autocomplete="current-password">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary"
                                                    data-profile-password-toggle="#profileCurrentPassword" aria-label="Mostrar contrase&ntilde;a">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="dp-profile-field-error" data-profile-error="current_password"></div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="profilePassword">Nueva contrase&ntilde;a</label>
                                            <div class="input-group input-group-sm">
                                                <input type="password" class="form-control" id="profilePassword"
                                                    name="password" autocomplete="new-password">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary"
                                                        data-profile-password-toggle="#profilePassword" aria-label="Mostrar contrase&ntilde;a">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="dp-profile-field-error" data-profile-error="password"></div>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="profilePasswordConfirmation">Confirmar contrase&ntilde;a</label>
                                            <div class="input-group input-group-sm">
                                                <input type="password" class="form-control" id="profilePasswordConfirmation"
                                                    name="password_confirmation" autocomplete="new-password">
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-secondary"
                                                        data-profile-password-toggle="#profilePasswordConfirmation" aria-label="Mostrar contrase&ntilde;a">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="dp-profile-field-error" data-profile-error="password_confirmation"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="profile-trace" role="tabpanel"
                                    aria-labelledby="profile-trace-tab">
                                    <div class="dp-profile-section-heading">
                                        <div><strong>Trazabilidad de acceso</strong><small>Información informativa; estos datos no son editables.</small></div>
                                        <i class="fas fa-fingerprint"></i>
                                    </div>

                                    <div class="row dp-profile-trace-grid">
                                        <div class="col-md-6"><div class="dp-profile-trace-item"><i class="fas fa-user-plus"></i><span><small>Creado por</small><strong id="profileTraceCreatedBy">-</strong></span></div></div>
                                        <div class="col-md-6"><div class="dp-profile-trace-item"><i class="far fa-calendar-plus"></i><span><small>Fecha de creaci&oacute;n</small><strong id="profileTraceCreatedAt">-</strong></span></div></div>
                                        <div class="col-md-6"><div class="dp-profile-trace-item"><i class="fas fa-user-edit"></i><span><small>Editado por</small><strong id="profileTraceUpdatedBy">-</strong></span></div></div>
                                        <div class="col-md-6"><div class="dp-profile-trace-item"><i class="far fa-calendar-check"></i><span><small>&Uacute;ltima actualizaci&oacute;n</small><strong id="profileTraceUpdatedAt">-</strong></span></div></div>
                                        <div class="col-md-6"><div class="dp-profile-trace-item"><i class="fas fa-user-tag"></i><span><small>Rol actual</small><strong id="profileTraceRole">-</strong></span></div></div>
                                        <div class="col-md-6"><div class="dp-profile-trace-item"><i class="fas fa-exchange-alt"></i><span><small>&Uacute;ltimo cambio de rol por</small><strong id="profileTraceRoleBy">-</strong><em id="profileTraceRoleAt">-</em></span></div></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cerrar
                    </button>
                    <button type="submit" class="btn btn-success" id="btnSaveUserProfile">
                        <i class="fas fa-save mr-1"></i> Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
