<div class="modal fade users-detail-modal" id="viewUserModal" tabindex="-1" role="dialog"
    aria-labelledby="viewUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0">
            <div class="modal-header users-detail-header">
                <div class="users-detail-heading">
                    <span class="users-detail-heading-icon"><i class="fas fa-user-shield"></i></span>
                    <span>
                        <span class="modal-title" id="viewUserModalLabel">Informaci&oacute;n del Usuario</span>
                        <small>Detalle de identidad, rol, acceso y trazabilidad</small>
                    </span>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-4 mb-3 mb-lg-0">
                        <aside class="users-detail-summary">
                            <div class="users-detail-avatar mb-3">
                                <img id="vu_photo" class="d-none" alt="Foto del usuario">
                                <div id="vu_photo_placeholder" class="users-detail-avatar-initials">U</div>
                            </div>

                            <h5 id="vu_name" class="users-detail-summary-name">-</h5>
                            <span id="vu_role_summary" class="users-role-chip mb-2">Sin rol</span>
                            <span id="vu_status" class="users-status-badge users-status-active">ACTIVO</span>

                            <div class="users-detail-contact-list">
                                <div><i class="fas fa-envelope"></i><span id="vu_summary_email">-</span></div>
                                <div><i class="fas fa-mobile-alt"></i><span id="vu_summary_phone">-</span></div>
                            </div>
                        </aside>
                    </div>

                    <div class="col-lg-8">
                        <ul class="nav nav-pills users-detail-tabs" id="userDetailTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="user-detail-personal-tab" data-toggle="pill"
                                    href="#user-detail-personal" role="tab" aria-controls="user-detail-personal"
                                    aria-selected="true"><i class="fas fa-id-card"></i> Datos personales</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="user-detail-access-tab" data-toggle="pill"
                                    href="#user-detail-access" role="tab" aria-controls="user-detail-access"
                                    aria-selected="false"><i class="fas fa-user-lock"></i> Acceso y rol</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="user-detail-trace-tab" data-toggle="pill"
                                    href="#user-detail-trace" role="tab" aria-controls="user-detail-trace"
                                    aria-selected="false"><i class="fas fa-history"></i> Trazabilidad</a>
                            </li>
                        </ul>

                        <div class="tab-content users-detail-tab-content" id="userDetailTabsContent">
                            <div class="tab-pane fade show active" id="user-detail-personal" role="tabpanel"
                                aria-labelledby="user-detail-personal-tab">
                                <div class="users-detail-section-title">
                                    <span><strong>Datos personales</strong><small>Información registrada del usuario.</small></span>
                                    <i class="fas fa-address-card"></i>
                                </div>
                                <div class="row users-detail-grid">
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="fas fa-fingerprint"></i><span><small>DNI</small><strong id="vu_dni">-</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="fas fa-user"></i><span><small>Nombres</small><strong id="vu_firstname">-</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="far fa-user"></i><span><small>Apellidos</small><strong id="vu_lastname">-</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="fas fa-mobile-alt"></i><span><small>Celular</small><strong id="vu_phone">-</strong></span></div></div>
                                    <div class="col-12"><div class="users-detail-field"><i class="fas fa-envelope"></i><span><small>Email</small><strong id="vu_email" class="text-break">-</strong></span></div></div>
                                    <div class="col-12"><div class="users-detail-field"><i class="fas fa-map-marker-alt"></i><span><small>Direcci&oacute;n</small><strong id="vu_address" class="text-break">-</strong></span></div></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="user-detail-access" role="tabpanel"
                                aria-labelledby="user-detail-access-tab">
                                <div class="users-detail-section-title">
                                    <span><strong>Acceso y rol</strong><small>Estado actual e información de acceso.</small></span>
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="row users-detail-grid">
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="fas fa-user-tag"></i><span><small>Rol asignado</small><strong><span id="vu_role_detail" class="users-role-chip">Sin rol</span></strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="fas fa-toggle-on"></i><span><small>Estado</small><strong id="vu_status_text">-</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="far fa-calendar-plus"></i><span><small>Fecha de creaci&oacute;n</small><strong id="vu_created_at">-</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="far fa-calendar-check"></i><span><small>&Uacute;ltima actualizaci&oacute;n</small><strong id="vu_updated_at">-</strong></span></div></div>
                                    <div class="col-12"><div class="users-detail-field"><i class="fas fa-at"></i><span><small>Email de acceso</small><strong id="vu_access_email" class="text-break">-</strong></span></div></div>
                                    <div class="col-12"><div class="users-detail-field users-detail-principal-field" id="vu_principal_field"><i class="fas fa-shield-alt"></i><span><small>Protecci&oacute;n de cuenta</small><strong id="vu_principal_indicator">Usuario regular</strong></span></div></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="user-detail-trace" role="tabpanel"
                                aria-labelledby="user-detail-trace-tab">
                                <div class="users-detail-section-title">
                                    <span><strong>Trazabilidad</strong><small>Responsables y fechas de los últimos cambios.</small></span>
                                    <i class="fas fa-route"></i>
                                </div>
                                <div class="row users-detail-grid">
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="fas fa-user-plus"></i><span><small>Creado por</small><strong id="vu_created_by">No registrado / hist&oacute;rico</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="fas fa-user-edit"></i><span><small>&Uacute;ltima edici&oacute;n por</small><strong id="vu_updated_by">No registrado / hist&oacute;rico</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="far fa-calendar-plus"></i><span><small>Fecha de creaci&oacute;n</small><strong id="vu_audit_created_at">No registrado / hist&oacute;rico</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="far fa-calendar-check"></i><span><small>Fecha de &uacute;ltima edici&oacute;n</small><strong id="vu_audit_updated_at">No registrado / hist&oacute;rico</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="fas fa-exchange-alt"></i><span><small>&Uacute;ltimo cambio de rol por</small><strong id="vu_role_changed_by">No registrado / hist&oacute;rico</strong></span></div></div>
                                    <div class="col-sm-6"><div class="users-detail-field"><i class="far fa-clock"></i><span><small>Fecha del cambio de rol</small><strong id="vu_role_changed_at">No registrado / hist&oacute;rico</strong></span></div></div>
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
            </div>
        </div>
    </div>
</div>
