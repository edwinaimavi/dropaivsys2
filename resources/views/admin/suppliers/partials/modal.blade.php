<!-- MODAL SUPPLIER -->
<div class="modal fade" id="supplierModal" tabindex="-1" role="dialog" aria-labelledby="supplierModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg">

            <!-- HEADER -->
            <div class="modal-header align-items-center"
                style="
                    background:linear-gradient(
                        90deg,
                        #f5fcff,
                        #e8f7ff
                    );
                    border-bottom:1px solid #b6e6ff;
                ">

                <div class="d-flex align-items-center">

                    <div class="icon-circle mr-3"
                        style="
                            background:#dff4ff;
                            width:42px;
                            height:42px;
                            border-radius:10px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                        ">

                        <i class="fas fa-truck text-info"></i>

                    </div>

                    <div>

                        <h5 class="modal-title mb-0 font-weight-bold" id="supplierModalLabel">

                            Nuevo Proveedor

                        </h5>

                        <small class="text-muted">

                            Registro y administración de proveedores

                        </small>

                    </div>

                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2" style="background:#fafdff;">

                <form id="supplierForm" autocomplete="off" class="row">

                    @csrf

                    <input type="hidden" id="supplier_id">

                    <!-- PANEL IZQUIERDO -->
                    <div class="col-lg-3 mb-2">

                        <div class="card border-0 rounded-lg shadow-sm h-100">

                            <div class="card-body text-center py-2 px-2">

                                <div class="mb-2">

                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                        style="
                                            width:80px;
                                            height:80px;
                                            background:
                                            linear-gradient(
                                                135deg,
                                                #17a2b8,
                                                #007bff
                                            );
                                            color:white;
                                            font-size:30px;
                                            box-shadow:
                                            0 6px 18px rgba(0,0,0,.1);
                                        ">

                                        <i class="fas fa-truck-loading"></i>

                                    </div>

                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">

                                    Proveedores

                                </h5>

                                <small class="text-muted">

                                    Gestión comercial y abastecimiento

                                </small>

                                <hr class="my-2">

                                <div class="text-left small">

                                    <small class="text-muted">

                                        Fecha de registro

                                    </small>

                                    <div class="font-weight-600 mb-2">

                                        {{ now()->format('d/m/Y') }}

                                    </div>

                                    <small class="text-muted d-block">

                                        Estado inicial

                                    </small>

                                    <div class="badge badge-info py-1 px-2 mt-1 text-white">

                                        Activo

                                    </div>

                                    <small class="text-muted d-block mt-2">

                                        Módulo

                                    </small>

                                    <div class="font-weight-600 mb-2">

                                        Proveedores

                                    </div>

                                    <small class="text-muted d-block">

                                        Función

                                    </small>

                                    <div class="font-weight-600">

                                        Gestión de compras

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- PANEL DERECHO -->
                    <div class="col-lg-9">

                        <div class="card border-0 rounded-lg shadow-sm">

                            <div class="card-body py-3">

                                <!-- FILA 1 -->
                                <div class="form-row">

                                    <!-- RUC -->
                                    <div class="form-group col-md-3">

                                        <label class="small font-weight-bold text-secondary">

                                            RUC
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="ruc" name="ruc"
                                            class="form-control form-control-sm" placeholder="Ingrese RUC"
                                            maxlength="11">

                                        <span class="invalid-feedback" id="ruc-error"></span>

                                    </div>

                                    <!-- RAZON SOCIAL -->
                                    <div class="form-group col-md-6">

                                        <label class="small font-weight-bold text-secondary">

                                            RAZÓN SOCIAL
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="business_name" name="business_name"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ingrese razón social">

                                        <span class="invalid-feedback" id="business_name-error"></span>

                                    </div>

                                    <!-- NOMBRE CORTO -->
                                    <div class="form-group col-md-3">

                                        <label class="small font-weight-bold text-secondary">

                                            NOMBRE CORTO

                                        </label>

                                        <input type="text" id="short_name" name="short_name"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ingrese nombre corto">

                                    </div>

                                </div>

                                <!-- FILA 2 -->
                                <div class="form-row">

                                    <!-- DIRECCION -->
                                    <div class="form-group col-md-7">

                                        <label class="small font-weight-bold text-secondary">

                                            DIRECCIÓN

                                        </label>

                                        <input type="text" id="address" name="address"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ingrese dirección">

                                    </div>

                                    <!-- UBIGEO -->
                                    <div class="form-group col-md-5">

                                        <label class="small font-weight-bold text-secondary">

                                            UBIGEO

                                        </label>

                                        <select id="ubigeo_id" name="ubigeo_id" class="form-control form-control-sm">

                                            <option value="">

                                                Seleccione

                                            </option>

                                        </select>

                                        <span class="invalid-feedback" id="ubigeo_id-error"></span>

                                    </div>

                                </div>

                                <!-- FILA 3 -->
                                <div class="form-row">

                                    <!-- TIPO -->
                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">

                                            TIPO PROVEEDOR
                                            <span class="text-danger">*</span>

                                        </label>

                                        <select id="supplier_type" name="supplier_type"
                                            class="form-control form-control-sm">

                                            <option value="">

                                                Seleccione

                                            </option>

                                            <option value="NACIONAL">

                                                NACIONAL

                                            </option>

                                            <option value="IMPORTADOR">

                                                IMPORTADOR

                                            </option>

                                            <option value="DISTRIBUIDOR">

                                                DISTRIBUIDOR

                                            </option>

                                            <option value="FABRICANTE">

                                                FABRICANTE

                                            </option>

                                            <option value="LABORATORIO">

                                                LABORATORIO

                                            </option>

                                            <option value="OTRO">

                                                OTRO

                                            </option>

                                        </select>

                                        <span class="invalid-feedback" id="supplier_type-error"></span>

                                    </div>

                                    <!-- CONDICION -->
                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">

                                            CONDICIÓN PAGO
                                            <span class="text-danger">*</span>

                                        </label>

                                        <select id="payment_condition" name="payment_condition"
                                            class="form-control form-control-sm">

                                            <option value="">

                                                Seleccione

                                            </option>

                                            <option value="CONTADO">

                                                CONTADO

                                            </option>

                                            <option value="CREDITO">

                                                CRÉDITO

                                            </option>

                                        </select>

                                        <span class="invalid-feedback" id="payment_condition-error"></span>

                                    </div>

                                    <!-- IGV -->
                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">

                                            IGV %

                                        </label>

                                        <input type="number" step="0.01" id="igv_percentage"
                                            name="igv_percentage" class="form-control form-control-sm"
                                            value="18.00">

                                    </div>

                                </div>

                                <!-- FILA 4 -->
                                <div class="form-row">

                                    <!-- CONTACTO -->
                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">

                                            CONTACTO

                                        </label>

                                        <input type="text" id="contact_name" name="contact_name"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ingrese contacto">

                                    </div>

                                    <!-- EMAIL -->
                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">

                                            CORREO

                                        </label>

                                        <input type="email" id="email" name="email"
                                            class="form-control form-control-sm" placeholder="Ingrese correo">

                                    </div>

                                    <!-- TELEFONO -->
                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">

                                            TELÉFONO

                                        </label>

                                        <input type="text" id="phone" name="phone"
                                            class="form-control form-control-sm" placeholder="Ingrese teléfono">

                                    </div>

                                </div>

                                <!-- FILA 5 -->
                                <div class="form-row">

                                    <!-- ESTADO -->
                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">

                                            ESTADO
                                            <span class="text-danger">*</span>

                                        </label>

                                        <select id="status" name="status" class="form-control form-control-sm">

                                            <option value="ACTIVE">

                                                ACTIVO

                                            </option>

                                            <option value="INACTIVE">

                                                INACTIVO

                                            </option>

                                        </select>

                                    </div>

                                    <!-- OBSERVACION -->
                                    <div class="form-group col-md-8">

                                        <label class="small font-weight-bold text-secondary">

                                            OBSERVACIÓN

                                        </label>

                                        <textarea id="observation" name="observation" rows="2" class="form-control form-control-sm"
                                            placeholder="Ingrese observaciones"></textarea>

                                    </div>

                                </div>

                                <!-- ALERT -->
                                <div class="alert border-0 shadow-sm mb-2"
                                    style="
                                        background:#dff6ff;
                                        color:#0c5460;
                                    ">

                                    <div class="d-flex align-items-center">

                                        <div class="mr-2">

                                            <i class="fas fa-info-circle text-info"></i>

                                        </div>

                                        <div class="small">

                                            <strong>

                                                Importante:

                                            </strong>

                                            Los proveedores permitirán gestionar
                                            compras, abastecimiento y control
                                            comercial dentro del sistema.

                                        </div>

                                    </div>

                                </div>

                                <!-- BOTONES -->
                                <div class="d-flex justify-content-end mt-2">

                                    <button type="button" class="btn btn-light border btn-sm mr-2"
                                        data-dismiss="modal">

                                        <i class="fas fa-times mr-1"></i>
                                        Cerrar

                                    </button>

                                    <button type="submit" class="btn btn-info btn-sm text-white"
                                        id="btnSaveSupplier">

                                        <i class="fas fa-save mr-1"></i>
                                        Guardar Proveedor

                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>
