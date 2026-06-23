<!-- MODAL BRAND -->
<div class="modal fade" id="brandModal" tabindex="-1" role="dialog" aria-labelledby="brandModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg overflow-hidden">

            <!-- HEADER -->
            <div class="modal-header align-items-center"
                style="
                    background:linear-gradient(
                        90deg,
                        #f8f9fa,
                        #e9ecef
                    );
                    border-bottom:1px solid #ced4da;
                ">

                <div class="d-flex align-items-center">

                    <div class="icon-circle mr-3"
                        style="
                            background:#dee2e6;
                            width:42px;
                            height:42px;
                            border-radius:10px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                        ">

                        <i class="fas fa-tags text-secondary"></i>

                    </div>

                    <div>

                        <h5 class="modal-title mb-0 font-weight-bold" id="brandModalLabel">

                            Nueva Marca

                        </h5>

                        <small class="text-muted">

                            Registro y administración de marcas

                        </small>

                    </div>

                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2" style="background:#fafafa;">

                <form id="brandForm" autocomplete="off" class="row">

                    @csrf

                    <input type="hidden" id="brand_id">

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
                                                #6c757d,
                                                #495057
                                            );
                                            color:white;
                                            font-size:30px;
                                            box-shadow:
                                            0 6px 18px rgba(0,0,0,.1);
                                        ">

                                        <i class="fas fa-tags"></i>

                                    </div>

                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">

                                    Marcas

                                </h5>

                                <small class="text-muted">

                                    Gestión de marcas comerciales

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

                                    <div class="badge badge-secondary py-1 px-2 mt-1">

                                        Activo

                                    </div>

                                    <small class="text-muted d-block mt-2">

                                        Módulo

                                    </small>

                                    <div class="font-weight-600 mb-2">

                                        Marcas

                                    </div>

                                    <small class="text-muted d-block">

                                        Función

                                    </small>

                                    <div class="font-weight-600">

                                        Gestión comercial

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- PANEL DERECHO -->
                    <div class="col-lg-9">

                        <div class="card border-0 rounded-lg shadow-sm">

                            <div class="card-body py-3">

                                <!-- FILA -->
                                <div class="form-row">

                                    <!-- CÓDIGO -->
                                    <div class="form-group col-md-4">

                                        <label class="small font-weight-bold text-secondary">

                                            CÓDIGO
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="code" name="code"
                                            class="form-control form-control-sm text-uppercase" readonly>

                                        <span class="invalid-feedback" id="code-error"></span>

                                    </div>

                                    <!-- DESCRIPCIÓN -->
                                    <div class="form-group col-md-8">

                                        <label class="small font-weight-bold text-secondary">

                                            DESCRIPCIÓN
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="description" name="description"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ej: JOHNSON & JOHNSON">

                                        <span class="invalid-feedback" id="description-error"></span>

                                    </div>

                                </div>

                                <!-- FILA -->
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

                                </div>

                                <!-- OBSERVACIÓN -->
                                <div class="form-row">

                                    <div class="form-group col-md-12">

                                        <label class="small font-weight-bold text-secondary">

                                            OBSERVACIÓN

                                        </label>

                                        <textarea id="observation" name="observation" rows="3" class="form-control form-control-sm"
                                            placeholder="Ingrese observaciones"></textarea>

                                    </div>

                                </div>

                                <!-- ALERT -->
                                <div class="alert border-0 shadow-sm mb-2"
                                    style="
                                        background:#f1f3f5;
                                        color:#495057;
                                    ">

                                    <div class="d-flex align-items-center">

                                        <div class="mr-2">

                                            <i class="fas fa-info-circle text-secondary"></i>

                                        </div>

                                        <div class="small">

                                            <strong>

                                                Importante:

                                            </strong>

                                            Las marcas permiten clasificar y
                                            organizar los productos del
                                            inventario, facilitando búsquedas,
                                            reportes y control comercial.

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

                                    <button type="submit" class="btn btn-secondary btn-sm" id="btnSaveBrand">

                                        <i class="fas fa-save mr-1"></i>
                                        Guardar Marca

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
