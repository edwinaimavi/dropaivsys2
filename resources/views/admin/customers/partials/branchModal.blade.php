{{-- =========================================================
    MODAL SEDES Y CONTACTOS DEL CLIENTE
========================================================= --}}
<div class="modal fade" id="customerBranchModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow rounded-lg overflow-hidden">

            {{-- HEADER --}}
            <div class="modal-header border-0 bg-white py-2 px-3">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-success-light mr-2">
                        <i class="fas fa-building text-success"></i>
                    </div>

                    <div>
                        <h5 class="modal-title font-weight-bold text-dark mb-0">
                            Sedes del Cliente
                        </h5>
                        <small class="text-muted">
                            Gestión de sucursales, puntos de atención y contactos
                        </small>
                    </div>
                </div>

                <button type="button" class="close shadow-none m-0 p-0" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>

            {{-- BODY --}}
            <div class="modal-body p-3">

                {{-- TABS --}}
                <ul class="nav nav-tabs mb-3" id="customerBranchTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="branches-tab" data-toggle="tab" href="#branchesPane"
                            role="tab" aria-controls="branchesPane" aria-selected="true">
                            <i class="fas fa-store mr-1"></i> Registrar Sedes
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="contacts-tab" data-toggle="tab" href="#contactsPane" role="tab"
                            aria-controls="contactsPane" aria-selected="false">
                            <i class="fas fa-address-book mr-1"></i> Registrar Contactos
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="customerBranchTabsContent">

                    {{-- =====================================================
                        TAB SEDES
                    ===================================================== --}}
                    <div class="tab-pane fade show active" id="branchesPane" role="tabpanel"
                        aria-labelledby="branches-tab">

                        <div class="row">

                            {{-- LEFT INFO --}}
                            <div class="col-lg-3 mb-3">
                                <div class="supplier-account-info-card h-100">
                                    <div class="text-center mb-2">
                                        <div class="supplier-account-icon mx-auto mb-2">
                                            <i class="fas fa-users"></i>
                                        </div>

                                        <h5 class="font-weight-bold text-dark mb-1">
                                            Cliente
                                        </h5>

                                        <small class="text-muted">
                                            Información principal
                                        </small>
                                    </div>

                                    <hr class="my-2">

                                    <div class="mb-3">
                                        <small class="text-muted d-block">
                                            Razón Social
                                        </small>

                                        <div class="font-weight-bold text-dark" id="branch_customer_name">
                                            —
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted d-block">
                                            Documento
                                        </small>

                                        <div class="font-weight-bold text-success" id="branch_customer_document">
                                            —
                                        </div>
                                    </div>

                                    <div>
                                        <small class="text-muted d-block">
                                            Estado
                                        </small>

                                        <span class="badge badge-success px-2 py-1 rounded-pill">
                                            ACTIVO
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- RIGHT --}}
                            <div class="col-lg-9">

                                {{-- FORM SEDE --}}
                                <div class="supplier-account-form-card mb-3">
                                    <form id="customerBranchForm">
                                        @csrf

                                        <input type="hidden" id="customer_branch_id">
                                        <input type="hidden" id="branch_customer_id" name="customer_id">

                                        <div class="row">

                                            {{-- NOMBRE SEDE --}}
                                            <div class="col-md-4">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">NOMBRE SEDE</label>
                                                    <input type="text" id="branch_name" name="branch_name"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            {{-- TIPO --}}
                                            <div class="col-md-3">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">TIPO</label>
                                                    <select id="branch_type" name="branch_type"
                                                        class="form-control form-control-modern">
                                                        <option value="">Seleccionar tipo</option>
                                                        <option value="CASA MATRIZ">CASA MATRIZ</option>
                                                        <option value="SUCURSAL">SUCURSAL</option>
                                                        <option value="OFICINA">OFICINA</option>
                                                        <option value="AGENCIA">AGENCIA</option>
                                                        <option value="ALMACEN">ALMACÉN</option>
                                                        <option value="PUNTO DE VENTA">PUNTO DE VENTA</option>
                                                    </select>
                                                </div>
                                            </div>

                                            {{-- TELEFONO --}}
                                            <div class="col-md-2">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">TELÉFONO</label>
                                                    <input type="text" id="branch_phone" name="phone"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            {{-- EMAIL --}}
                                            <div class="col-md-3">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">EMAIL</label>
                                                    <input type="email" id="branch_email" name="email"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            {{-- UBIGEO --}}
                                            <div class="col-md-4">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">UBIGEO</label>
                                                    <select id="branch_ubigeo_id" name="ubigeo_id"
                                                        class="form-control"></select>
                                                </div>
                                            </div>

                                            {{-- DIRECCION --}}
                                            <div class="col-md-5">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">DIRECCIÓN DE ENTREGA</label>
                                                    <input type="text" id="branch_address" name="address"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            {{-- REFERENCIA --}}
                                            <div class="col-md-3">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">REFERENCIA</label>
                                                    <input type="text" id="branch_reference" name="reference"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            {{-- COMPROBANTE --}}
                                            <div class="col-md-3">
                                                <label class="form-label">COMPROBANTE</label>
                                                <select id="voucher_type" name="voucher_type"
                                                    class="form-control form-control-modern">
                                                    <option value="FACTURA">FACTURA</option>
                                                    <option value="BOLETA">BOLETA</option>
                                                    <option value="NOTA DE PEDIDO">NOTA DE PEDIDO</option>
                                                    <option value="GUIA DE REMISION">GUÍA DE REMISIÓN</option>
                                                </select>
                                            </div>

                                            {{-- GUIA --}}
                                            <div class="col-md-2">
                                                <label class="form-label">GENERA GUÍA</label>
                                                <select id="generate_guide" name="generate_guide"
                                                    class="form-control form-control-modern">
                                                    <option value="SI">SI</option>
                                                    <option value="NO">NO</option>
                                                </select>
                                            </div>

                                            {{-- CONDICION --}}
                                            <div class="col-md-4">
                                                <label class="form-label">CONDICIÓN PAGO</label>
                                                <select id="payment_condition" name="payment_condition"
                                                    class="form-control form-control-modern">
                                                    <option value="CONTADO">CONTADO</option>
                                                    <option value="CREDITO 20 DIAS">CRÉDITO 20 DÍAS</option>
                                                    <option value="CREDITO 30 DIAS">CRÉDITO 30 DÍAS</option>
                                                    <option value="CREDITO 45 DIAS">CRÉDITO 45 DÍAS</option>
                                                    <option value="CREDITO 60 DIAS">CRÉDITO 60 DÍAS</option>
                                                </select>
                                            </div>

                                            {{-- PRINCIPAL --}}
                                            <div class="col-md-1">
                                                <label class="form-label">PRINCIPAL</label>
                                                <select id="is_main" name="is_main"
                                                    class="form-control form-control-modern">
                                                    <option value="1">SI</option>
                                                    <option value="0">NO</option>
                                                </select>
                                            </div>

                                            {{-- ESTADO --}}
                                            <div class="col-md-2">
                                                <label class="form-label">ESTADO</label>
                                                <select id="branch_status" name="status"
                                                    class="form-control form-control-modern">
                                                    <option value="1">ACTIVO</option>
                                                    <option value="0">INACTIVO</option>
                                                </select>
                                            </div>

                                        </div>

                                        <div class="d-flex justify-content-end border-top pt-2 mt-2">
                                            <button type="button" class="btn btn-light border px-3 mr-2"
                                                data-dismiss="modal">
                                                Cerrar
                                            </button>

                                            <button type="submit" id="btnSaveCustomerBranch"
                                                class="btn btn-success px-3 shadow-sm">
                                                <i class="fas fa-save mr-1"></i>
                                                Guardar Sede
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                {{-- TABLA --}}
                                <div class="supplier-account-table-card">
                                    <table id="tableCustomerBranches"
                                        class="table table-hover align-middle w-100 nowrap text-center">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>SEDE</th>
                                                <th>TIPO</th>
                                                <th>UBIGEO</th>
                                                <th>COMPROBANTE</th>
                                                <th>CONDICIÓN</th>
                                                <th>PRINCIPAL</th>
                                                <th>ESTADO</th>
                                                <th>ACCIONES</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- =====================================================
                        TAB CONTACTOS
                    ===================================================== --}}
                    <div class="tab-pane fade" id="contactsPane" role="tabpanel" aria-labelledby="contacts-tab">

                        <div class="row">

                            {{-- LEFT INFO --}}
                            <div class="col-lg-3 mb-3">
                                <div class="supplier-account-info-card h-100">
                                    <div class="text-center mb-2">
                                        <div class="supplier-account-icon mx-auto mb-2">
                                            <i class="fas fa-address-book"></i>
                                        </div>

                                        <h5 class="font-weight-bold text-dark mb-1">
                                            Contactos
                                        </h5>

                                        <small class="text-muted">
                                            Personas asociadas a la sede
                                        </small>
                                    </div>

                                    <hr class="my-2">

                                    <div class="mb-3">
                                        <small class="text-muted d-block">
                                            Cliente
                                        </small>

                                        <div class="font-weight-bold text-dark" id="contact_customer_name">
                                            —
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <small class="text-muted d-block">
                                            Sede seleccionada
                                        </small>

                                        <div class="font-weight-bold text-success" id="contact_branch_name">
                                            —
                                        </div>
                                    </div>

                                    <div>
                                        <small class="text-muted d-block">
                                            Estado
                                        </small>

                                        <span class="badge badge-success px-2 py-1 rounded-pill">
                                            ACTIVO
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- RIGHT --}}
                            <div class="col-lg-9">

                                {{-- FORM CONTACTO --}}
                                <div class="supplier-account-form-card mb-3">
                                    <form id="customerBranchContactForm">
                                        @csrf

                                        <input type="hidden" id="customer_branch_contact_id">
                                        <input type="hidden" id="contact_customer_branch_id"
                                            name="customer_branch_id">

                                        <div class="row">

                                            <div class="col-md-5">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">LOCAL CLIENTE</label>
                                                    <select id="contact_branch_select"
                                                        class="form-control form-control-modern">
                                                        <option value="">Seleccione</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-5">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">NOMBRE CONTACTO</label>
                                                    <input type="text" id="contact_name" name="contact_name"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            <div class="col-md-2">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">TELÉFONO</label>
                                                    <input type="text" id="contact_phone" name="phone"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">CORREO</label>
                                                    <input type="email" id="contact_email" name="email"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">DIRECCIÓN</label>
                                                    <input type="text" id="contact_address" name="address"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            <div class="col-md-10">
                                                <div class="form-group mb-2">
                                                    <label class="form-label">REFERENCIA</label>
                                                    <input type="text" id="contact_reference" name="reference"
                                                        class="form-control form-control-modern">
                                                </div>
                                            </div>

                                            <div class="col-md-2">
                                                <label class="form-label">ESTADO</label>
                                                <select id="contact_status" name="status"
                                                    class="form-control form-control-modern">
                                                    <option value="1">ACTIVO</option>
                                                    <option value="0">INACTIVO</option>
                                                </select>
                                            </div>

                                        </div>

                                        <div class="d-flex justify-content-end border-top pt-2 mt-2">
                                            <button type="button" class="btn btn-light border px-3 mr-2"
                                                data-dismiss="modal">
                                                Cerrar
                                            </button>

                                            <button type="submit" id="btnSaveCustomerBranchContact"
                                                class="btn btn-primary px-3 shadow-sm">
                                                <i class="fas fa-save mr-1"></i>
                                                Guardar Contacto
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                {{-- TABLA CONTACTOS --}}
                                <div class="supplier-account-table-card">
                                    <table id="tableCustomerBranchContacts"
                                        class="table table-hover align-middle w-100 nowrap text-center">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>CONTACTO</th>
                                                <th>TELÉFONO</th>
                                                <th>CORREO</th>
                                                <th>DIRECCIÓN</th>
                                                <th>REFERENCIA</th>
                                                <th>ESTADO</th>
                                                <th>ACCIONES</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<style>
    .bg-success-light {
        background: rgba(40, 167, 69, .12);
    }

    .icon-circle {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .icon-circle i {
        font-size: 18px;
    }

    .supplier-account-info-card {
        background: #fff;
        border-radius: 14px;
        padding: 15px;
        border: 1px solid #f1f1f1;
        box-shadow: 0 1px 8px rgba(0, 0, 0, .04);
        min-height: 100%;
    }

    .supplier-account-icon {
        width: 78px;
        height: 78px;
        border-radius: 50%;
        background: linear-gradient(135deg, #28a745, #20c997);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 28px;
    }

    .supplier-account-form-card,
    .supplier-account-table-card {
        background: #fff;
        border-radius: 14px;
        padding: 14px;
        border: 1px solid #f1f1f1;
        box-shadow: 0 1px 8px rgba(0, 0, 0, .04);
    }

    .form-label {
        font-size: 11px;
        font-weight: 700;
        color: #555;
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .form-control-modern {
        border-radius: 8px;
        border: 1px solid #dee2e6;
        min-height: 38px;
        box-shadow: none;
        font-size: 13px;
    }

    .form-control-modern:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 .12rem rgba(40, 167, 69, .12);
    }

    #tableCustomerBranches thead th,
    #tableCustomerBranchContacts thead th {
        border: none !important;
        font-size: 11px;
        font-weight: 700;
        color: #666;
        background: #f8f9fa;
        white-space: nowrap;
    }

    #tableCustomerBranches tbody td,
    #tableCustomerBranchContacts tbody td {
        vertical-align: middle !important;
        font-size: 13px;
        padding: 10px 8px;
    }

    .modal-xl {
        max-width: 1150px;
    }

    @media(max-width:991px) {
        .modal-dialog {
            margin: 8px;
        }
    }
</style>
