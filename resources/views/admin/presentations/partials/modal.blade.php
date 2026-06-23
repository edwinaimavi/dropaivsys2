<!-- MODAL PRESENTATION -->
<div class="modal fade" id="presentationModal" tabindex="-1" role="dialog" aria-labelledby="presentationModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg overflow-hidden">

            <!-- HEADER -->
            <div class="modal-header align-items-center"
                style="
                    background:linear-gradient(
                        90deg,
                        #fffdf5,
                        #fff8e1
                    );
                    border-bottom:1px solid #ffe082;
                ">

                <div class="d-flex align-items-center">

                    <div class="icon-circle mr-3"
                        style="
                            background:#fff3cd;
                            width:42px;
                            height:42px;
                            border-radius:10px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                        ">

                        <i class="fas fa-box-open text-warning"></i>

                    </div>

                    <div>

                        <h5 class="modal-title mb-0 font-weight-bold" id="presentationModalLabel">

                            Nueva Presentación

                        </h5>

                        <small class="text-muted">

                            Registro y administración de presentaciones

                        </small>

                    </div>

                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2" style="background:#fffdf8;">

                <form id="presentationForm" autocomplete="off" class="row">

                    @csrf

                    <input type="hidden" id="presentation_id">

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
                                                #fbc02d,
                                                #f57f17
                                            );
                                            color:white;
                                            font-size:30px;
                                            box-shadow:
                                            0 6px 18px rgba(0,0,0,.1);
                                        ">

                                        <i class="fas fa-box-open"></i>

                                    </div>

                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">

                                    Presentaciones

                                </h5>

                                <small class="text-muted">

                                    Control de empaques y formatos

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

                                    <div class="badge badge-warning py-1 px-2 mt-1 text-white">

                                        Activo

                                    </div>

                                    <small class="text-muted d-block mt-2">

                                        Módulo

                                    </small>

                                    <div class="font-weight-600 mb-2">

                                        Presentaciones

                                    </div>

                                    <small class="text-muted d-block">

                                        Función

                                    </small>

                                    <div class="font-weight-600">

                                        Gestión de empaques

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

                                    <!-- DESCRIPCIÓN -->
                                    <div class="form-group col-md-6">

                                        <label class="small font-weight-bold text-secondary">

                                            DESCRIPCIÓN
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="text" id="description" name="description"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ej: CAJA X 100">

                                        <span class="invalid-feedback" id="description-error"></span>

                                    </div>

                                    <!-- UNIDAD -->
                                    <div class="form-group col-md-6">

                                        <label class="small font-weight-bold text-secondary">

                                            UNIDAD
                                            <span class="text-danger">*</span>

                                        </label>

                                        <select id="unit_id" name="unit_id" class="form-control form-control-sm">

                                            <option value="">

                                                Seleccione

                                            </option>

                                            @foreach ($units as $unit)
                                                <option value="{{ $unit->id }}">

                                                    {{ $unit->description }}

                                                    @if ($unit->abbreviation)
                                                        ({{ $unit->abbreviation }})
                                                    @endif

                                                </option>
                                            @endforeach

                                        </select>

                                        <span class="invalid-feedback" id="unit_id-error"></span>

                                    </div>

                                </div>

                                <!-- FILA -->
                                <div class="form-row">

                                    <!-- CANTIDAD -->
                                    <div class="form-group col-md-6">

                                        <label class="small font-weight-bold text-secondary">

                                            CANTIDAD
                                            <span class="text-danger">*</span>

                                        </label>

                                        <input type="number" step="0.01" min="0" id="quantity"
                                            name="quantity" class="form-control form-control-sm" placeholder="Ej: 100">

                                        <span class="invalid-feedback" id="quantity-error"></span>

                                    </div>

                                    <!-- ESTADO -->
                                    <div class="form-group col-md-6">

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

                                        <textarea id="observation" name="observation" rows="2" class="form-control form-control-sm"
                                            placeholder="Ingrese observaciones"></textarea>

                                    </div>

                                </div>

                                <!-- ALERT -->
                                <div class="alert border-0 shadow-sm mb-2"
                                    style="
                                        background:#fff3cd;
                                        color:#7c5700;
                                    ">

                                    <div class="d-flex align-items-center">

                                        <div class="mr-2">

                                            <i class="fas fa-info-circle text-warning"></i>

                                        </div>

                                        <div class="small">

                                            <strong>

                                                Importante:

                                            </strong>

                                            Las presentaciones permitirán
                                            definir empaques, formatos y
                                            cantidades comerciales de los
                                            productos.

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

                                    <button type="submit" class="btn btn-warning btn-sm text-white"
                                        id="btnSavePresentation">

                                        <i class="fas fa-save mr-1"></i>
                                        Guardar Presentación

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
