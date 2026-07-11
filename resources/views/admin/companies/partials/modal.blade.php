<div class="modal fade" id="companyModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static"
    data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered company-modal-dialog" role="document">
        <div class="modal-content company-modal border-0 shadow-lg">
            <form id="companyForm" autocomplete="off" enctype="multipart/form-data">
                <input type="hidden" id="company_id">

                <div class="modal-header company-modal-header text-white">
                    <div class="company-header-title">
                        <span class="company-header-icon">
                            <i class="fas fa-building"></i>
                        </span>
                        <div>
                            <h5 class="modal-title mb-0" id="companyModalLabel">Nueva empresa</h5>
                            <small>Informaci&oacute;n tributaria y de contacto</small>
                        </div>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body company-modal-body">
                    <div id="companyErrors" class="alert alert-danger d-none"></div>

                    <div class="row">
                        <div class="col-lg-3 mb-2">
                            <div class="card company-card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <div class="company-side-icon mx-auto mb-3">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <h5 class="font-weight-bold mb-1">Empresa</h5>
                                    <small class="text-muted">Datos fiscales del sistema</small>
                                    <hr>
                                    <div class="text-left small">
                                        <small class="text-muted d-block">Registro</small>
                                        <div class="font-weight-bold mb-2">{{ now()->format('d/m/Y') }}</div>
                                        <small class="text-muted d-block">Consulta</small>
                                        <div class="font-weight-bold mb-2">SUNAT por RUC</div>
                                        <small class="text-muted d-block">Estado inicial</small>
                                        <span class="badge badge-info rounded-pill px-3 py-2">ACTIVO</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-9">
                            <div class="card company-card border-0 shadow-sm mb-2">
                                <div class="card-header company-section-header border-0">
                                    <h6 class="mb-0">
                                        <i class="fas fa-id-card text-info mr-1"></i>
                                        Informaci&oacute;n tributaria
                                    </h6>
                                    <small class="text-muted">RUC, raz&oacute;n social y nombre comercial</small>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label>RUC <span class="text-danger">*</span></label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" id="company_ruc" name="ruc" class="form-control"
                                                    maxlength="11" placeholder="Ingrese RUC">
                                                <div class="input-group-append">
                                                    <button type="button" id="btnSearchCompanyRuc"
                                                        class="btn btn-outline-info">
                                                        <i class="fas fa-search mr-1"></i>
                                                        Buscar
                                                    </button>
                                                </div>
                                                <span class="invalid-feedback d-block" id="company_ruc-error"></span>
                                            </div>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Raz&oacute;n social <span class="text-danger">*</span></label>
                                            <input type="text" id="company_business_name" name="business_name"
                                                class="form-control form-control-sm text-uppercase">
                                            <span class="invalid-feedback" id="company_business_name-error"></span>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Nombre comercial</label>
                                            <input type="text" id="company_trade_name" name="trade_name"
                                                class="form-control form-control-sm text-uppercase">
                                            <span class="invalid-feedback" id="company_trade_name-error"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card company-card border-0 shadow-sm mb-2">
                                <div class="card-header company-section-header border-0">
                                    <h6 class="mb-0">
                                        <i class="fas fa-map-marker-alt text-success mr-1"></i>
                                        Ubicaci&oacute;n
                                    </h6>
                                    <small class="text-muted">Direcci&oacute;n fiscal o de operaciones</small>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-12">
                                            <label>Direcci&oacute;n</label>
                                            <textarea id="company_address" name="address" class="form-control form-control-sm text-uppercase"
                                                rows="2"></textarea>
                                            <span class="invalid-feedback" id="company_address-error"></span>
                                        </div>
                                    </div>
                                    <div id="companyRucExtraData" class="company-ruc-extra d-none">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        SUNAT:
                                        <span id="company_sunat_location">-</span>
                                        <span id="company_sunat_condition"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-lg-8 mb-2">
                                    <div class="card company-card border-0 shadow-sm h-100">
                                        <div class="card-header company-section-header border-0">
                                            <h6 class="mb-0">
                                                <i class="fas fa-phone-alt text-primary mr-1"></i>
                                                Contacto
                                            </h6>
                                            <small class="text-muted">Tel&eacute;fono y correo corporativo</small>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label>Tel&eacute;fono</label>
                                                    <input type="text" id="company_phone" name="phone"
                                                        class="form-control form-control-sm">
                                                    <span class="invalid-feedback" id="company_phone-error"></span>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label>Correo electr&oacute;nico</label>
                                                    <input type="email" id="company_email" name="email"
                                                        class="form-control form-control-sm">
                                                    <span class="invalid-feedback" id="company_email-error"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-4 mb-2">
                                    <div class="card company-card border-0 shadow-sm h-100">
                                        <div class="card-header company-section-header border-0">
                                            <h6 class="mb-0">
                                                <i class="fas fa-cog text-warning mr-1"></i>
                                                Configuraci&oacute;n
                                            </h6>
                                            <small class="text-muted">Estado y logo institucional</small>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>Estado <span class="text-danger">*</span></label>
                                                <select id="company_status" name="status"
                                                    class="form-control form-control-sm">
                                                    <option value="1">ACTIVO</option>
                                                    <option value="0">INACTIVO</option>
                                                </select>
                                                <span class="invalid-feedback" id="company_status-error"></span>
                                            </div>
                                            <div class="form-group mb-0">
                                                <div class="company-logo-uploader">
                                                    <div class="company-logo-uploader-header">
                                                        <div>
                                                            <label class="mb-0">Logo institucional</label>
                                                            <small>Imagen para documentos, PDF y reportes.</small>
                                                        </div>
                                                    </div>

                                                    <div class="company-logo-preview" id="companyLogoPreview">
                                                        <div class="company-logo-placeholder">
                                                            <i class="fas fa-image"></i>
                                                            <span>Sin logo registrado</span>
                                                        </div>
                                                    </div>

                                                    <div class="company-logo-actions">
                                                        <label for="company_logo" class="btn btn-outline-info btn-sm mb-0">
                                                            <i class="fas fa-upload mr-1"></i>
                                                            <span id="companyLogoButtonText">Seleccionar logo</span>
                                                        </label>
                                                        <span id="companyLogoFileName" class="company-logo-file-name">
                                                            Sin archivo seleccionado
                                                        </span>
                                                    </div>

                                                    <input type="file" id="company_logo" name="logo"
                                                        class="company-logo-input d-none" accept=".jpg,.jpeg,.png,.webp">
                                                    <small class="company-logo-help">
                                                        Formatos permitidos: JPG, PNG, WEBP. Tama&ntilde;o m&aacute;ximo: 2MB.
                                                    </small>
                                                    <span class="invalid-feedback d-block" id="company_logo-error"></span>
                                                    <small id="companyCurrentLogo" class="text-muted d-block mt-1"></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer company-modal-footer">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btnSaveCompany" class="btn btn-info btn-sm">
                        <i class="fas fa-save mr-1"></i>
                        Guardar empresa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
