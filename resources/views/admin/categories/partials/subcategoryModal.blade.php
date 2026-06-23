{{-- =========================================================
= MODAL SUBCATEGORÍAS
========================================================= --}}
<div class="modal fade" id="subcategoryModal" tabindex="-1" role="dialog" aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">

        <div class="modal-content border-0 shadow rounded-lg overflow-hidden">

            {{-- HEADER --}}
            <div class="modal-header border-0 bg-white py-2 px-3">

                <div class="d-flex align-items-center">

                    <div class="icon-circle bg-success-light mr-2">

                        <i class="fas fa-layer-group text-success"></i>

                    </div>

                    <div>

                        <h5 class="modal-title font-weight-bold text-dark mb-0">

                            Subcategorías

                        </h5>

                        <small class="text-muted">

                            Gestión de subcategorías

                        </small>

                    </div>

                </div>

                <button type="button" class="close shadow-none m-0 p-0" data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            {{-- BODY --}}
            <div class="modal-body p-3">

                <div class="row">

                    {{-- LEFT INFO --}}
                    <div class="col-lg-3 mb-3">

                        <div class="subcategory-info-card h-100">

                            <div class="text-center mb-2">

                                <div class="subcategory-icon mx-auto mb-2">

                                    <i class="fas fa-tags"></i>

                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">

                                    Categoría

                                </h5>

                                <small class="text-muted">

                                    Clasificación principal

                                </small>

                            </div>

                            <hr class="my-2">

                            <div class="mb-2">

                                <small class="text-muted d-block">

                                    Seleccionada

                                </small>

                                <div class="font-weight-bold text-dark" id="subcategory_category_name">

                                    —

                                </div>

                            </div>

                            <div class="mb-2">

                                <small class="text-muted d-block">

                                    Estado

                                </small>

                                <span class="badge badge-success px-2 py-1 rounded-pill">

                                    ACTIVO

                                </span>

                            </div>

                            <div>

                                <small class="text-muted d-block">

                                    Función

                                </small>

                                <span class="small font-weight-semibold">

                                    Clasificación secundaria

                                </span>

                            </div>

                        </div>

                    </div>

                    {{-- RIGHT --}}
                    <div class="col-lg-9">

                        {{-- FORM --}}
                        <div class="subcategory-form-card mb-3">

                            <form id="subcategoryForm">

                                @csrf

                                <input type="hidden" id="subcategory_id">

                                <input type="hidden" id="subcategory_category_id">

                                <div class="row">

                                    {{-- DESCRIPTION --}}
                                    <div class="col-md-6">

                                        <div class="form-group mb-2">

                                            <label class="form-label">

                                                DESCRIPCIÓN <span class="text-danger">*</span>

                                            </label>

                                            <input type="text" class="form-control form-control-modern"
                                                id="subcategory_description" placeholder="Ingrese descripción">

                                        </div>

                                    </div>

                                    {{-- STATUS --}}
                                    <div class="col-md-6">

                                        <div class="form-group mb-2">

                                            <label class="form-label">

                                                ESTADO <span class="text-danger">*</span>

                                            </label>

                                            <select id="subcategory_status" class="form-control form-control-modern">

                                                <option value="ACTIVE">
                                                    ACTIVO
                                                </option>

                                                <option value="INACTIVE">
                                                    INACTIVO
                                                </option>

                                            </select>

                                        </div>

                                    </div>

                                    {{-- OBSERVATION --}}
                                    <div class="col-12">

                                        <div class="form-group mb-2">

                                            <label class="form-label">

                                                OBSERVACIÓN

                                            </label>

                                            <textarea id="subcategory_observation" rows="2" class="form-control form-control-modern"
                                                placeholder="Ingrese observaciones"></textarea>

                                        </div>

                                    </div>

                                </div>

                                {{-- BUTTONS --}}
                                <div class="d-flex justify-content-end border-top pt-2 mt-2">

                                    <button type="button" class="btn btn-light border px-3 mr-2" data-dismiss="modal">

                                        <i class="fas fa-times mr-1"></i>
                                        Cerrar

                                    </button>

                                    <button type="submit" id="btnSaveSubcategory"
                                        class="btn btn-success px-3 shadow-sm">

                                        <i class="fas fa-save mr-1"></i>
                                        Registrar

                                    </button>

                                </div>

                            </form>

                        </div>

                        {{-- TABLE --}}
                        <div class="subcategory-table-card">

                            <div class="d-flex justify-content-between align-items-center mb-2">

                                <div>

                                    <h5 class="font-weight-bold mb-0">

                                        Lista de Subcategorías

                                    </h5>

                                    <small class="text-muted">

                                        Registros disponibles

                                    </small>

                                </div>

                            </div>

                            <div class="table-responsive">

                                <table id="tableSubcategory"
                                    class="table table-hover align-middle w-100 nowrap text-center">

                                    <thead>

                                        <tr>

                                            <th width="40">
                                    #</th>

                                    <th>DESCRIPCIÓN</th>

                                    <th width="120">ESTADO</th>

                                    <th width="110">ACCIONES</th>

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

{{-- =========================================================
= STYLES
========================================================= --}}
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

    .subcategory-info-card {

        background: #fff;
        border-radius: 14px;
        padding: 15px;
        border: 1px solid #f1f1f1;
        box-shadow: 0 1px 8px rgba(0, 0, 0, .04);

    }

    .subcategory-icon {

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

    .subcategory-form-card,
    .subcategory-table-card {

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
        box-shadow: 0 0 0 0.12rem rgba(40, 167, 69, .12);

    }

    #tableSubcategory thead th {

        border: none !important;
        font-size: 11px;
        font-weight: 700;
        color: #666;
        background: #f8f9fa;

    }

    #tableSubcategory tbody td {

        vertical-align: middle !important;
        font-size: 13px;
        padding: 10px 8px;

    }

    @media(max-width: 991px) {

        .modal-dialog {

            margin: 8px;

        }

    }
</style>
