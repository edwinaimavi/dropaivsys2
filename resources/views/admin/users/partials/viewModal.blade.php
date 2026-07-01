<div class="modal fade users-detail-modal" id="viewUserModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0">
                <div class="users-modal-title">
                    <div class="users-modal-title-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="viewUserModalLabel">Informaci&oacute;n del Usuario</h5>
                        <small>Detalle de identidad, rol y acceso al sistema</small>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-3 mb-3 mb-lg-0">
                        <div class="card border-0 shadow-sm h-100 users-detail-profile">
                            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                                <div class="users-detail-avatar mb-3">
                                    <img id="vu_photo" class="d-none" alt="Foto del usuario">
                                    <div id="vu_photo_placeholder"
                                        class="h-100 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>

                                <h5 id="vu_name" class="font-weight-bold text-dark mb-1">-</h5>
                                <span id="vu_role_summary" class="users-role-chip mb-3">Sin rol</span>
                                <span id="vu_status" class="users-status-badge users-status-active">ACTIVO</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm mb-3 users-detail-card">
                            <div class="card-header border-0 bg-white">
                                <h6 class="mb-1 font-weight-bold text-dark">
                                    <i class="fas fa-id-card text-success mr-1"></i>
                                    Datos personales
                                </h6>
                                <small class="text-muted">Informaci&oacute;n registrada del usuario</small>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="users-detail-field">
                                            <span class="users-detail-label">DNI</span>
                                            <span id="vu_dni" class="users-detail-value">-</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="users-detail-field">
                                            <span class="users-detail-label">Celular</span>
                                            <span id="vu_phone" class="users-detail-value">-</span>
                                        </div>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="users-detail-field">
                                            <span class="users-detail-label">Email</span>
                                            <span id="vu_email" class="users-detail-value text-break">-</span>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div class="users-detail-field">
                                            <span class="users-detail-label">Direcci&oacute;n</span>
                                            <span id="vu_address" class="users-detail-value text-break">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-0 users-detail-card">
                            <div class="card-header border-0 bg-white">
                                <h6 class="mb-1 font-weight-bold text-dark">
                                    <i class="fas fa-user-lock text-success mr-1"></i>
                                    Informaci&oacute;n de acceso
                                </h6>
                                <small class="text-muted">Rol asignado, estado y trazabilidad</small>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="users-detail-field">
                                            <span class="users-detail-label">Rol asignado</span>
                                            <span class="users-detail-value">
                                                <span id="vu_role_detail" class="users-role-chip">Sin rol</span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="users-detail-field">
                                            <span class="users-detail-label">Estado</span>
                                            <span id="vu_status_text" class="users-detail-value font-weight-bold">-</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-2 mb-md-0">
                                        <div class="users-detail-date">
                                            <i class="far fa-calendar-plus text-success mr-2"></i>
                                            <div>
                                                <small>Fecha de creaci&oacute;n</small>
                                                <strong id="vu_created_at">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="users-detail-date">
                                            <i class="far fa-calendar-check text-success mr-2"></i>
                                            <div>
                                                <small>&Uacute;ltima actualizaci&oacute;n</small>
                                                <strong id="vu_updated_at">-</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
