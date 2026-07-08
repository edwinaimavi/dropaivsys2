<div class="modal fade" id="viewShippingAgencyModal" tabindex="-1" role="dialog"
    aria-labelledby="viewShippingAgencyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow overflow-hidden" style="border-radius:14px;">
            <div class="modal-header border-0" style="background:linear-gradient(135deg,#198754,#116c43);">
                <div class="d-flex align-items-center">
                    <div class="mr-3 d-flex align-items-center justify-content-center"
                        style="width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.16);color:#fff;">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 font-weight-bold" id="viewShippingAgencyModalLabel">
                            Detalle de Agencia de Env&iacute;o
                        </h5>
                        <small class="text-white-50">Sedes y contactos de despacho</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-3" style="background:#f7fbf8;">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                                    style="width:78px;height:78px;border-radius:20px;color:#fff;background:linear-gradient(135deg,#198754,#116c43);font-size:30px;">
                                    <i class="fas fa-route"></i>
                                </div>
                                <small class="text-muted text-uppercase font-weight-bold">Codigo interno</small>
                                <h3 id="vsa_code" class="font-weight-bold mb-2 text-dark">-</h3>
                                <span id="vsa_status" class="badge badge-primary rounded-pill px-3 py-2">ACTIVO</span>
                                <hr>
                                <small class="text-muted d-block">RUC</small>
                                <strong id="vsa_ruc" class="d-block mb-2">-</strong>
                                <small class="text-muted d-block">Tipo</small>
                                <strong id="vsa_agency_type" class="d-block mb-2">-</strong>
                                <small class="text-muted d-block">Telefono</small>
                                <strong id="vsa_phone" class="d-block">-</strong>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white border-0 pb-0">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-info-circle text-success mr-1"></i>
                                    Informaci&oacute;n de la agencia
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Razon social</small><strong id="vsa_business_name">-</strong></div>
                                    <div class="col-md-6 mb-2"><small class="text-muted d-block">Nombre comercial</small><strong id="vsa_trade_name">-</strong></div>
                                    <div class="col-md-4 mb-2"><small class="text-muted d-block">Correo</small><strong id="vsa_email">-</strong></div>
                                    <div class="col-md-4 mb-2"><small class="text-muted d-block">Web</small><strong id="vsa_website">-</strong></div>
                                    <div class="col-md-4 mb-2"><small class="text-muted d-block">Actualizado por</small><strong id="vsa_updated_by">-</strong></div>
                                    <div class="col-12 mb-2"><small class="text-muted d-block">Observacion</small><strong id="vsa_observations">Sin observaciones</strong></div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white border-0 pb-0">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-map-marker-alt text-success mr-1"></i>
                                    Sedes
                                </h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Sede</th>
                                            <th>Direccion</th>
                                            <th>Ubicacion</th>
                                            <th>Referencia</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vsa_branches_body"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 pb-0">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-address-book text-success mr-1"></i>
                                    Contactos
                                </h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Contacto</th>
                                            <th>Sede</th>
                                            <th>Telefono</th>
                                            <th>WhatsApp</th>
                                            <th>Correo</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody id="vsa_contacts_body"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-light border px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
