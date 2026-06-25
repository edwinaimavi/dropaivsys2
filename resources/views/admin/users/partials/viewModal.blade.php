<div class="modal fade" id="viewUserModal" tabindex="-1" role="dialog" aria-labelledby="viewUserModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">

        <div class="modal-content border-0 shadow overflow-hidden">

            <div class="modal-header border-0 py-3 px-4 user-detail-header">
                <div class="d-flex align-items-center">
                    <span class="user-detail-header-icon mr-3">
                        <i class="fas fa-user-shield"></i>
                    </span>
                    <div>
                        <h5 class="modal-title text-white mb-0 font-weight-bold" id="viewUserModalLabel">
                            Información del Usuario
                        </h5>
                        <small class="text-white-50">Detalle de identidad y acceso al sistema</small>
                    </div>
                </div>

                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-3 p-md-4 user-detail-body">
                <div class="row">

                    <div class="col-lg-4 mb-3 mb-lg-0">
                        <div class="card border-0 shadow-sm h-100 user-detail-profile">
                            <div class="card-body text-center d-flex flex-column align-items-center justify-content-center">
                                <div class="user-detail-avatar mb-3">
                                    <img id="vu_photo" class="d-none" alt="Foto del usuario">
                                    <div id="vu_photo_placeholder"
                                        class="h-100 d-flex align-items-center justify-content-center">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>

                                <h5 id="vu_name" class="font-weight-bold text-dark mb-1">—</h5>
                                <span id="vu_role_summary" class="text-muted small mb-3">—</span>
                                <span id="vu_status" class="badge badge-success px-3 py-2">ACTIVO</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="card border-0 shadow-sm mb-3 user-detail-card">
                            <div class="card-header bg-white border-0 py-3">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-id-card text-primary mr-2"></i>
                                    Datos personales
                                </h6>
                            </div>

                            <div class="card-body pt-0">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="user-detail-field">
                                            <span class="user-detail-label">DNI</span>
                                            <span id="vu_dni" class="user-detail-value">—</span>
                                        </div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <div class="user-detail-field">
                                            <span class="user-detail-label">Celular</span>
                                            <span id="vu_phone" class="user-detail-value">—</span>
                                        </div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <div class="user-detail-field">
                                            <span class="user-detail-label">Email</span>
                                            <span id="vu_email" class="user-detail-value text-break">—</span>
                                        </div>
                                    </div>

                                    <div class="col-12">
                                        <div class="user-detail-field">
                                            <span class="user-detail-label">Dirección</span>
                                            <span id="vu_address" class="user-detail-value text-break">—</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-0 user-detail-card">
                            <div class="card-header bg-white border-0 py-3">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-user-lock text-primary mr-2"></i>
                                    Información de acceso
                                </h6>
                            </div>

                            <div class="card-body pt-0">
                                <div class="row">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <div class="user-detail-field">
                                            <span class="user-detail-label">Rol asignado</span>
                                            <span class="user-detail-value">
                                                <i class="fas fa-user-tag text-muted mr-1"></i>
                                                <span id="vu_role_detail">—</span>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="user-detail-field">
                                            <span class="user-detail-label">Estado</span>
                                            <span id="vu_status_text" class="user-detail-value font-weight-bold">—</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6 mb-2 mb-md-0">
                        <div class="user-detail-date shadow-sm">
                            <i class="far fa-calendar-plus text-primary mr-2"></i>
                            <div>
                                <small>Fecha de creación</small>
                                <strong id="vu_created_at">—</strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="user-detail-date shadow-sm">
                            <i class="far fa-calendar-check text-primary mr-2"></i>
                            <div>
                                <small>Última actualización</small>
                                <strong id="vu_updated_at">—</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white border-0 px-4 py-3">
                <button type="button" class="btn btn-light border px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #viewUserModal .modal-content {
        border-radius: 16px;
    }

    #viewUserModal .user-detail-header {
        background: linear-gradient(135deg, #1659a5, #2484d8);
    }

    #viewUserModal .user-detail-header-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: rgba(255, 255, 255, .16);
        font-size: 18px;
    }

    #viewUserModal .close {
        opacity: .9;
        text-shadow: none;
    }

    #viewUserModal .user-detail-body {
        background: #f5f8fc;
        padding: 1.5rem !important;
    }

    #viewUserModal .user-detail-profile,
    #viewUserModal .user-detail-card {
        border-radius: 13px;
    }

    #viewUserModal .user-detail-avatar {
        width: 112px;
        height: 112px;
        overflow: hidden;
        border-radius: 50%;
        color: #fff;
        background: linear-gradient(135deg, #5aa8eb, #1659a5);
        border: 5px solid #fff;
        box-shadow: 0 8px 20px rgba(22, 89, 165, .2);
        font-size: 42px;
    }

    #viewUserModal .user-detail-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    #viewUserModal .user-detail-field {
        height: 100%;
        padding: 11px 13px;
        border: 1px solid #edf1f5;
        border-radius: 10px;
        background: #fbfcfe;
    }

    #viewUserModal .user-detail-label,
    #viewUserModal .user-detail-date small {
        display: block;
        margin-bottom: 3px;
        color: #7a8694;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .35px;
        text-transform: uppercase;
    }

    #viewUserModal .user-detail-value {
        display: block;
        color: #263445;
        font-size: 13px;
        font-weight: 600;
    }

    #viewUserModal .user-detail-date {
        display: flex;
        align-items: center;
        min-height: 62px;
        padding: 11px 14px;
        border-radius: 11px;
        background: #fff;
    }

    #viewUserModal .user-detail-date i {
        font-size: 22px;
    }

    #viewUserModal .user-detail-date strong {
        display: block;
        color: #344050;
        font-size: 12px;
    }

    @media (max-width: 767.98px) {
        #viewUserModal .modal-dialog {
            margin: 8px;
        }

        #viewUserModal .modal-header {
            padding: 12px 15px !important;
        }

        #viewUserModal .user-detail-body {
            padding: 1rem !important;
        }

        #viewUserModal .user-detail-header-icon {
            display: none;
        }

        #viewUserModal .user-detail-avatar {
            width: 90px;
            height: 90px;
            font-size: 34px;
        }
    }
</style>
