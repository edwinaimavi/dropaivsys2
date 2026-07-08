<div class="modal fade" id="shippingAgencyModal" tabindex="-1" role="dialog" aria-labelledby="shippingAgencyModalLabel"
    aria-hidden="true" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered shipping-agency-modal-dialog" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header align-items-center"
                style="background:linear-gradient(90deg,#f4fff8,#eaf8ef);border-bottom:1px solid #b8e2c5;">
                <div class="d-flex align-items-center">
                    <div class="mr-3 d-flex align-items-center justify-content-center"
                        style="width:42px;height:42px;border-radius:10px;background:#dff5e7;">
                        <i class="fas fa-shipping-fast text-success"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 font-weight-bold" id="shippingAgencyModalLabel">Nueva Agencia de
                            Env&iacute;o</h5>
                        <small class="text-muted">Datos principales, sedes y contactos de transporte</small>
                    </div>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-2" style="background:#f8fffb;">
                <form id="shippingAgencyForm" autocomplete="off" class="row">
                    @csrf
                    <input type="hidden" id="shipping_agency_id">

                    <div class="col-12">
                        <div id="shippingAgencyErrors" class="alert alert-danger d-none mb-2"></div>
                    </div>

                    <div class="col-lg-3 mb-2">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center py-3 px-3">
                                <div class="mx-auto mb-3 d-flex align-items-center justify-content-center"
                                    style="width:82px;height:82px;border-radius:50%;color:#fff;background:linear-gradient(135deg,#198754,#157347);font-size:30px;">
                                    <i class="fas fa-route"></i>
                                </div>
                                <h5 class="font-weight-bold text-dark mb-1">Agencias</h5>
                                <small class="text-muted">Despacho y recojo de mercader&iacute;a</small>
                                <hr class="my-2">
                                <div class="text-left small">
                                    <small class="text-muted d-block">Fecha de registro</small>
                                    <div class="font-weight-bold mb-2">{{ now()->format('d/m/Y') }}</div>
                                    <small class="text-muted d-block">Estado inicial</small>
                                    <span class="badge badge-info text-white px-2 py-1 mb-2">Activo</span>
                                    <small class="text-muted d-block mt-2">Sedes registradas</small>
                                    <div id="shippingAgencyBranchCount" class="shipping-agency-side-total mb-2">0</div>
                                    <small class="text-muted d-block">Contactos registrados</small>
                                    <div id="shippingAgencyContactCount" class="shipping-agency-side-total">0</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9 mb-2">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <ul class="nav nav-pills card-header-pills" id="shippingAgencyTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" data-toggle="pill" href="#shippingAgencyDataTab"
                                                role="tab">
                                                <i class="fas fa-building mr-1"></i> Agencia
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="pill" href="#shippingAgencyBranchesTab"
                                                role="tab">
                                                <i class="fas fa-map-marker-alt mr-1"></i> Sedes
                                            </a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" data-toggle="pill" href="#shippingAgencyContactsTab"
                                                role="tab">
                                                <i class="fas fa-address-book mr-1"></i> Contactos
                                            </a>
                                        </li>
                                    </ul>
                                    <div class="mt-2 mt-md-0">
                                        <button type="button" class="btn btn-light border btn-sm mr-2"
                                            data-dismiss="modal">
                                            <i class="fas fa-times mr-1"></i> Cerrar
                                        </button>
                                        <button type="submit" id="btnSaveShippingAgency"
                                            class="btn btn-success btn-sm">
                                            <i class="fas fa-save mr-1"></i> Guardar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body py-3">
                                <div class="tab-content">
                                    <div class="tab-pane fade show active shipping-agency-tab-pane"
                                        id="shippingAgencyDataTab" role="tabpanel">
                                        <div class="form-row">
                                            <div class="form-group col-md-3">
                                                <label>RUC</label>
                                                <input type="text" id="shipping_ruc" name="ruc"
                                                    class="form-control form-control-sm" placeholder="Ingrese RUC"
                                                    maxlength="11" inputmode="numeric">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-5">
                                                <label>RAZ&Oacute;N SOCIAL <span class="text-danger">*</span></label>
                                                <input type="text" id="shipping_business_name"
                                                    name="business_name"
                                                    class="form-control form-control-sm text-uppercase">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>NOMBRE COMERCIAL</label>
                                                <input type="text" id="shipping_trade_name" name="trade_name"
                                                    class="form-control form-control-sm text-uppercase">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-3">
                                                <label>TIPO <span class="text-danger">*</span></label>
                                                <select id="shipping_agency_type" name="agency_type"
                                                    class="form-control form-control-sm">
                                                    <option value="">Seleccione</option>
                                                    <option value="TRANSPORTISTA">Transportista</option>
                                                    <option value="COURIER">Courier</option>
                                                    <option value="CARGA">Carga</option>
                                                    <option value="AEREO">A&eacute;reo</option>
                                                    <option value="TERRESTRE">Terrestre</option>
                                                    <option value="MIXTO">Mixto</option>
                                                    <option value="OTRO">Otro</option>
                                                </select>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>TEL&Eacute;FONO</label>
                                                <input type="text" id="shipping_phone" name="phone"
                                                    class="form-control form-control-sm">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>CORREO</label>
                                                <input type="email" id="shipping_email" name="email"
                                                    class="form-control form-control-sm">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>ESTADO <span class="text-danger">*</span></label>
                                                <select id="shipping_status" name="status"
                                                    class="form-control form-control-sm">
                                                    <option value="ACTIVE">ACTIVO</option>
                                                    <option value="INACTIVE">INACTIVO</option>
                                                </select>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-5">
                                                <label>WEB</label>
                                                <input type="text" id="shipping_website" name="website"
                                                    class="form-control form-control-sm">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-7">
                                                <label>OBSERVACI&Oacute;N</label>
                                                <textarea id="shipping_observations" name="observations" rows="2"
                                                    class="form-control form-control-sm text-uppercase"></textarea>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade shipping-agency-tab-pane" id="shippingAgencyBranchesTab"
                                        role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 font-weight-bold text-dark">Sedes de agencia</h6>
                                            <button type="button" id="btnAddShippingBranch"
                                                class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-plus"></i> Agregar sede
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered shipping-agency-row-table"
                                                id="shippingBranchesTable">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Nombre sede</th>
                                                        <th>Direcci&oacute;n</th>
                                                        <th>Departamento</th>
                                                        <th>Provincia</th>
                                                        <th>Distrito</th>
                                                        <th>Referencia</th>
                                                        <th>Principal</th>
                                                        <th>Tel&eacute;fono</th>
                                                        <th>Correo</th>
                                                        <th>Estado</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="shippingBranchesBody"></tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade shipping-agency-tab-pane" id="shippingAgencyContactsTab"
                                        role="tabpanel">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0 font-weight-bold text-dark">Contactos de despacho</h6>
                                            <button type="button" id="btnAddShippingContact"
                                                class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-plus"></i> Agregar contacto
                                            </button>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered shipping-agency-row-table"
                                                id="shippingContactsTable">
                                                <thead class="bg-light">
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Contacto</th>
                                                        <th>Cargo / &aacute;rea</th>
                                                        <th>Sede</th>
                                                        <th>Tel&eacute;fono</th>
                                                        <th>WhatsApp</th>
                                                        <th>Correo</th>
                                                        <th>Principal</th>
                                                        <th>Estado</th>
                                                        <th>Observaci&oacute;n</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="shippingContactsBody"></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<template id="shippingBranchRowTemplate">
    <tr class="shipping-branch-row">
        <td class="shipping-row-index text-center"></td>
        <td><input name="branches[__INDEX__][branch_name]"
                class="form-control form-control-sm text-uppercase wide-field"><span class="invalid-feedback"></span>
        </td>
        <td><input name="branches[__INDEX__][address]"
                class="form-control form-control-sm text-uppercase wide-field"><span class="invalid-feedback"></span>
        </td>
        <td><input name="branches[__INDEX__][department]" class="form-control form-control-sm text-uppercase"></td>
        <td><input name="branches[__INDEX__][province]" class="form-control form-control-sm text-uppercase"></td>
        <td><input name="branches[__INDEX__][district]" class="form-control form-control-sm text-uppercase"></td>
        <td><input name="branches[__INDEX__][reference]"
                class="form-control form-control-sm text-uppercase wide-field"></td>
        <td class="text-center"><input type="checkbox" name="branches[__INDEX__][is_main]" value="1"
                class="shipping-branch-main"></td>
        <td><input name="branches[__INDEX__][phone]" class="form-control form-control-sm"></td>
        <td><input type="email" name="branches[__INDEX__][email]" class="form-control form-control-sm"></td>
        <td>
            <select name="branches[__INDEX__][status]" class="form-control form-control-sm">
                <option value="ACTIVE">ACTIVO</option>
                <option value="INACTIVE">INACTIVO</option>
            </select>
        </td>
        <td class="text-center"><button type="button"
                class="btn btn-outline-danger btn-sm btnRemoveShippingBranch"><i class="fas fa-times"></i></button>
        </td>
    </tr>
</template>

<template id="shippingContactRowTemplate">
    <tr class="shipping-contact-row">
        <td class="shipping-row-index text-center"></td>
        <td><input name="contacts[__INDEX__][contact_name]"
                class="form-control form-control-sm text-uppercase wide-field"><span class="invalid-feedback"></span>
        </td>
        <td><input name="contacts[__INDEX__][position]" class="form-control form-control-sm text-uppercase"></td>
        <td><select name="contacts[__INDEX__][branch_index]"
                class="form-control form-control-sm shipping-contact-branch">
                <option value="">Agencia principal</option>
            </select></td>
        <td><input name="contacts[__INDEX__][phone]" class="form-control form-control-sm"></td>
        <td><input name="contacts[__INDEX__][whatsapp]" class="form-control form-control-sm"></td>
        <td><input type="email" name="contacts[__INDEX__][email]" class="form-control form-control-sm"></td>
        <td class="text-center"><input type="checkbox" name="contacts[__INDEX__][is_primary]" value="1"></td>
        <td>
            <select name="contacts[__INDEX__][status]" class="form-control form-control-sm">
                <option value="ACTIVE">ACTIVO</option>
                <option value="INACTIVE">INACTIVO</option>
            </select>
        </td>
        <td><input name="contacts[__INDEX__][observations]"
                class="form-control form-control-sm text-uppercase wide-field"></td>
        <td class="text-center"><button type="button"
                class="btn btn-outline-danger btn-sm btnRemoveShippingContact"><i class="fas fa-times"></i></button>
        </td>
    </tr>
</template>
