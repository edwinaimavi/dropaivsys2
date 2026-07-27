<div class="modal fade" id="viewCompanyModal" tabindex="-1" role="dialog" aria-labelledby="viewCompanyModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered company-view-dialog" role="document">
        <div class="modal-content company-view-modal border-0 shadow-lg">
            <div class="modal-header company-view-header border-0">
                <div class="d-flex align-items-center min-width-0">
                    <span class="company-view-logo-box mr-3" id="view_company_logo_box">
                        <i class="fas fa-building"></i>
                    </span>
                    <div class="company-view-identity min-width-0">
                        <small class="company-view-eyebrow">DETALLE DE EMPRESA</small>
                        <h4 class="modal-title font-weight-bold mb-1 text-truncate" id="view_company_business_name">-</h4>
                        <div class="text-muted text-truncate" id="view_company_trade_name">-</div>
                        <div class="company-view-meta mt-2">
                            <span><i class="far fa-id-card mr-1"></i>RUC: <strong id="view_company_header_ruc">-</strong></span>
                            <span id="view_company_header_status"></span>
                        </div>
                    </div>
                </div>
                <button type="button" class="close company-view-close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body company-view-body">
                <section class="company-view-section">
                    <div class="company-view-section-title">
                        <span class="company-view-section-icon"><i class="fas fa-info-circle"></i></span>
                        <div>
                            <h5>Información principal</h5>
                            <small>Datos generales y de contacto de la empresa.</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="company-info-block"><small>RUC</small><div id="view_company_ruc">-</div></div>
                        </div>
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="company-info-block"><small>Estado</small><div id="view_company_status">-</div></div>
                        </div>
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="company-info-block"><small>Teléfono</small><div id="view_company_phone">-</div></div>
                        </div>
                        <div class="col-sm-6 col-lg-3 mb-3">
                            <div class="company-info-block"><small>Correo</small><div id="view_company_email">-</div></div>
                        </div>
                        <div class="col-sm-6 col-lg-3 mb-0">
                            <div class="company-info-block"><small>Fecha de registro</small><div id="view_company_created_at">-</div></div>
                        </div>
                        <div class="col-sm-6 col-lg-3 mb-0">
                            <div class="company-info-block"><small>Última actualización</small><div id="view_company_updated_at">-</div></div>
                        </div>
                        <div class="col-lg-6 mt-3 mt-lg-0">
                            <div class="company-info-block"><small>Uso en módulos</small><div id="view_company_usage">-</div></div>
                        </div>
                    </div>
                </section>

                <section class="company-view-section">
                    <div class="company-view-section-title">
                        <span class="company-view-section-icon"><i class="fas fa-map-marker-alt"></i></span>
                        <div><h5>Dirección fiscal</h5><small>Ubicación registrada para operaciones y documentos.</small></div>
                    </div>
                    <div class="company-address-card">
                        <i class="fas fa-map-marked-alt"></i>
                        <span id="view_company_address">-</span>
                    </div>
                </section>

                <section class="company-view-section mb-0">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div class="company-view-section-title mb-0">
                            <span class="company-view-section-icon"><i class="fas fa-university"></i></span>
                            <div>
                                <h5>Cuentas bancarias registradas</h5>
                                <small>Cuentas disponibles para operaciones, compras y pagos.</small>
                            </div>
                        </div>
                        @can('admin.company-bank-accounts.index')
                            <button type="button" id="btnManageCompanyBankAccounts"
                                class="btn btn-outline-success btn-sm mt-2 mt-sm-0">
                                <i class="fas fa-cog mr-1"></i> Gestionar cuentas
                            </button>
                        @endcan
                    </div>
                    <div id="view_company_bank_accounts"></div>
                </section>
            </div>

            <div class="modal-footer company-modal-footer">
                <button type="button" class="btn btn-light border px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
