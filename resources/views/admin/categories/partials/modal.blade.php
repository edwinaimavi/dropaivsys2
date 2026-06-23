<!-- MODAL CATEGORÍA -->
<div class="modal fade" id="categoryModal" tabindex="-1" role="dialog" aria-labelledby="categoryModalLabel"
    aria-hidden="true">

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

                        <i class="fas fa-tags text-success"></i>

                    </div>

                    <div>

                        <h5 class="modal-title mb-0 font-weight-bold" id="categoryModalLabel">

                            Nueva Categoría

                        </h5>

                        <small class="text-muted">

                            Registro y administración de categorías

                        </small>

                    </div>

                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2" style="background: #f8fbfc;">

                <form id="categoryForm" autocomplete="off" class="row">

                    @csrf

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
                                                #28a745,
                                                #1e7e34
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

                                    Categorías

                                </h5>

                                <small class="text-muted">

                                    Organización de artículos y productos

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

                                    <div class="badge badge-success py-1 px-2 mt-1">

                                        Activo

                                    </div>

                                    <small class="text-muted d-block mt-2">

                                        Módulo

                                    </small>

                                    <div class="font-weight-600 mb-2">

                                        Categorías

                                    </div>

                                    <small class="text-muted d-block">

                                        Función

                                    </small>

                                    <div class="font-weight-600">

                                        Clasificación de artículos

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

                                    <!-- DESCRIPCIÓN -->
                                    <div class="form-group col-md-6">

                                        <label for="description" class="small font-weight-bold text-secondary">

                                            DESCRIPCIÓN
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="description" name="description"
                                            class="form-control form-control-sm" placeholder="Ingrese descripción">

                                        <span class="invalid-feedback" id="description-error"></span>

                                    </div>

                                    <!-- CÓDIGO -->
                                    <div class="form-group col-md-6">

                                        <label for="code" class="small font-weight-bold text-secondary">

                                            CÓDIGO
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="code" name="code"
                                            class="form-control form-control-sm bg-light" readonly>

                                        <span class="invalid-feedback" id="code-error"></span>

                                    </div>

                                </div>

                                <!-- FILA 2 -->
                                <div class="form-row">

                                    <!-- TIPO -->
                                    <div class="form-group col-md-6">

                                        <label for="type" class="small font-weight-bold text-secondary">

                                            TIPO
                                            <span class="text-danger">*</span>

                                        </label>

                                        <select id="type" name="type" class="form-control form-control-sm">

                                            <option value="">

                                                Seleccione

                                            </option>

                                            <option value="PRODUCTO COMERCIAL">

                                                PRODUCTO COMERCIAL

                                            </option>

                                            <option value="SERVICIO">

                                                SUMINISTRO

                                            </option>
{{-- 
                                            <option value="INSUMO">

                                                INSUMO
 --}}
                                            </option>

                                        </select>

                                        <span class="invalid-feedback" id="type-error"></span>

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

                                        <span class="invalid-feedback" id="status-error"></span>

                                    </div>

                                </div>

                                <!-- OBSERVACIÓN -->
                                <div class="form-row">

                                    <div class="form-group col-md-12">

                                        <label for="observation" class="small font-weight-bold text-secondary">

                                            OBSERVACIÓN

                                        </label>

                                        <textarea id="observation" name="observation" rows="2" class="form-control form-control-sm"
                                            placeholder="Ingrese observaciones de la categoría"></textarea>

                                    </div>

                                </div>

                                <!-- ALERTA -->
                                <div class="alert alert-success border-0 shadow-sm mb-2">

                                    <div class="d-flex align-items-center">

                                        <div class="mr-2">

                                            <i class="fas fa-info-circle text-success"></i>

                                        </div>

                                        <div class="small">

                                            <strong>
                                                Importante:
                                            </strong>

                                            Las categorías permitirán organizar
                                            artículos, productos y subcategorías
                                            dentro del sistema.

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

                                        <button type="submit" class="btn btn-success btn-sm" id="btnSaveCategory">

                                            <i class="fas fa-save mr-1"></i>
                                            Guardar Categoría

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
