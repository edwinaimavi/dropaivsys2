<!-- MODAL UNIT -->
<div class="modal fade" id="unitModal" tabindex="-1" role="dialog" aria-labelledby="unitModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg overflow-hidden">

            <!-- HEADER -->
            <div class="modal-header align-items-center"
                style="
                    background: linear-gradient(90deg,#ffffff,#f3f6f8);
                    border-bottom:1px solid #e6eaee;
                ">

                <div class="d-flex align-items-center">

                    <div class="icon-circle bg-light mr-3 icon_modal">

                        <i class="fas fa-balance-scale text-primary"></i>

                    </div>

                    <div>

                        <h5 class="modal-title mb-0 font-weight-bold" id="unitModalLabel">

                            Nueva Unidad

                        </h5>

                        <small class="text-muted">

                            Registro y administración de unidades

                        </small>

                    </div>

                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2" style="background: #f8fbfc;">

                <form id="unitForm" autocomplete="off" class="row">

                    @csrf

                    <input type="hidden" id="unit_id">

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
                                                #007bff,
                                                #0056b3
                                            );
                                            color:white;
                                            font-size:30px;
                                            box-shadow:
                                            0 6px 18px rgba(0,0,0,.1);
                                        ">

                                        <i class="fas fa-balance-scale"></i>

                                    </div>

                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">

                                    Unidades

                                </h5>

                                <small class="text-muted">

                                    Control de medidas y cantidades

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

                                    <div class="badge badge-primary py-1 px-2 mt-1">

                                        Activo

                                    </div>

                                    <small class="text-muted d-block mt-2">

                                        Módulo

                                    </small>

                                    <div class="font-weight-600 mb-2">

                                        Unidades

                                    </div>

                                    <small class="text-muted d-block">

                                        Función

                                    </small>

                                    <div class="font-weight-600">

                                        Control de medidas

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- PANEL DERECHO -->
                    <div class="col-lg-9">

                        <div class="card border-0 rounded-lg shadow-sm">

                            <div class="card-body">

                                <!-- FILA 1 -->
                                <div class="form-row">

                                    <!-- ABREVIATURA -->
                                    <div class="form-group col-md-4">

                                        <label for="abbreviation" class="small font-weight-bold text-secondary">

                                            ABREVIATURA
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="abbreviation" name="abbreviation"
                                            class="form-control form-control-sm text-uppercase" placeholder="Ej: KG">

                                        <span class="invalid-feedback" id="abbreviation-error"></span>

                                    </div>

                                    <!-- DESCRIPCIÓN -->
                                    <div class="form-group col-md-8">

                                        <label for="description" class="small font-weight-bold text-secondary">

                                            DESCRIPCIÓN
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="description" name="description"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ingrese descripción">

                                        <span class="invalid-feedback" id="description-error"></span>

                                    </div>

                                </div>

                                <!-- FILA 2 -->
                                <div class="form-row">

                                    <!-- DECIMALES -->
                                    <div class="form-group col-md-6">

                                        <label for="decimal_quantity" class="small font-weight-bold text-secondary">

                                            ¿PERMITE DECIMALES?
                                            <span class="text-danger">*</span>

                                        </label>

                                        <select id="decimal_quantity" name="decimal_quantity"
                                            class="form-control form-control-sm">

                                            <option value="1">

                                                SI

                                            </option>

                                            <option value="0">

                                                NO

                                            </option>

                                        </select>

                                    </div>

                                    <!-- ESTADO -->
                                    <div class="form-group col-md-6">

                                        <label for="status" class="small font-weight-bold text-secondary">

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

                                </div>

                                <!-- OBSERVACIÓN -->
                                <div class="form-row">

                                    <div class="form-group col-md-12">

                                        <label for="observation" class="small font-weight-bold text-secondary">

                                            OBSERVACIÓN

                                        </label>

                                        <textarea id="observation" name="observation" rows="2" class="form-control form-control-sm"
                                            placeholder="Ingrese observaciones"></textarea>

                                    </div>

                                </div>

                                <!-- ALERTA -->
                                <div class="alert alert-primary border-0 shadow-sm mb-2">

                                    <div class="d-flex align-items-center">

                                        <div class="mr-2">

                                            <i class="fas fa-info-circle text-primary"></i>

                                        </div>

                                        <div class="small">

                                            <strong>
                                                Importante:
                                            </strong>

                                            Las unidades permitirán controlar
                                            cantidades, inventario y movimientos
                                            de artículos dentro del sistema.

                                        </div>

                                    </div>

                                </div>

                                <!-- ACCIONES -->
                                <div class="form-row mt-2">

                                    <div class="col-12 d-flex justify-content-end">

                                        <button type="button" class="btn btn-light border btn-sm mr-2"
                                            data-dismiss="modal">

                                            <i class="fas fa-times mr-1"></i>
                                            Cerrar

                                        </button>

                                        <button type="submit" class="btn btn-primary btn-sm" id="btnSaveUnit">

                                            <i class="fas fa-save mr-1"></i>
                                            Guardar Unidad

                                        </button>

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
