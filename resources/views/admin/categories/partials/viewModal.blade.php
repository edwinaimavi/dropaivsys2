<!-- VIEW CATEGORY MODAL -->
<div class="modal fade" id="viewCategoryModal" tabindex="-1" role="dialog" aria-labelledby="viewCategoryModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">

        <div class="modal-content border-0 shadow overflow-hidden" style="border-radius:14px;">

            <!-- HEADER -->
            <div class="modal-header border-0 py-2 px-3" style="background:linear-gradient(135deg,#166534,#15803d);">

                <h5 class="modal-title text-white mb-0" id="viewCategoryModalLabel"
                    style="
                        font-size:15px;
                        font-weight:600;
                        letter-spacing:.3px;
                    ">

                    <i class="fas fa-tags mr-2"></i>
                    Información de Categoría

                </h5>

                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"
                    style="
                        opacity:1;
                        font-size:22px;
                    ">

                    <span aria-hidden="true">&times;</span>

                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-3">

                <div class="row">

                    <!-- LEFT -->
                    <div class="col-md-4">

                        <div class="text-center">

                            <div class="mx-auto mb-2 d-flex align-items-center justify-content-center shadow-sm"
                                style="
                                    width:70px;
                                    height:70px;
                                    border-radius:16px;
                                    background:linear-gradient(135deg,#28a745,#1e7e34);
                                    color:white;
                                    font-size:24px;
                                ">

                                <i class="fas fa-tags"></i>

                            </div>

                            <h5 id="vc_description" class="text-dark mb-1"
                                style="
                                    font-size:18px;
                                    font-weight:600;
                                ">

                                —

                            </h5>

                            <div id="vc_type" class="text-muted mb-2"
                                style="
                                    font-size:12px;
                                    letter-spacing:.2px;
                                ">

                                —

                            </div>

                            <span id="vc_status" class="badge badge-success px-3 py-1 shadow-sm"
                                style="
                                    border-radius:7px;
                                    font-size:10px;
                                    font-weight:500;
                                ">

                                ACTIVO

                            </span>

                        </div>

                        <!-- INFO -->
                        <div class="mt-3">

                            <div class="card border-0 shadow-sm">

                                <div class="card-body py-2 px-3">

                                    <small class="text-muted d-block mb-1" style="font-size:11px;">

                                        Registrado por

                                    </small>

                                    <div id="vc_created_by" class="text-dark mb-2"
                                        style="
                                            font-size:13px;
                                            font-weight:500;
                                        ">

                                        —

                                    </div>

                                    <small class="text-muted d-block mb-1" style="font-size:11px;">

                                        Última actualización

                                    </small>

                                    <div id="vc_updated_at" class="text-dark"
                                        style="
                                            font-size:12px;
                                            font-weight:500;
                                        ">

                                        —

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- RIGHT -->
                    <div class="col-md-8">

                        <!-- DETAILS -->
                        <div class="card border-0 shadow-sm">

                            <div class="card-header bg-white border-0 py-2">

                                <h6 class="mb-0 text-dark"
                                    style="
                                        font-size:14px;
                                        font-weight:600;
                                    ">

                                    <i class="fas fa-info-circle text-success mr-1"></i>
                                    Detalle de Categoría

                                </h6>

                            </div>

                            <div class="table-responsive">

                                <table class="table table-sm mb-0">

                                    <tbody>

                                        <tr>

                                            <th width="170" class="border-top-0 text-muted py-2"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">

                                                Descripción

                                            </th>

                                            <td id="vc_description_detail" class="border-top-0 text-dark py-2"
                                                style="
                                                    font-size:13px;
                                                    font-weight:500;
                                                ">

                                                —

                                            </td>

                                        </tr>

                                        <tr>

                                            <th class="text-muted py-2"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">

                                                Código

                                            </th>

                                            <td id="vc_code" class="text-dark py-2"
                                                style="
                                                    font-size:13px;
                                                    font-weight:500;
                                                ">

                                                —

                                            </td>

                                        </tr>

                                        <tr>

                                            <th class="text-muted py-2"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">

                                                Tipo

                                            </th>

                                            <td id="vc_type_detail" class="text-dark py-2"
                                                style="
                                                    font-size:13px;
                                                    font-weight:500;
                                                ">

                                                —

                                            </td>

                                        </tr>

                                        <tr>

                                            <th class="text-muted py-2"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">

                                                Estado

                                            </th>

                                            <td id="vc_status_text" class="text-dark py-2"
                                                style="
                                                    font-size:13px;
                                                    font-weight:500;
                                                ">

                                                —

                                            </td>

                                        </tr>

                                        <tr>

                                            <th class="text-muted py-2"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">

                                                Observación

                                            </th>

                                            <td id="vc_observation" class="text-dark py-2"
                                                style="
                                                    font-size:13px;
                                                    line-height:1.5;
                                                ">

                                                —

                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                        </div>
                        <!-- SUBCATEGORÍAS -->
                        <div class="card border-0 shadow-sm mt-3">

                            <div class="card-header bg-white border-0 py-2">

                                <div class="d-flex justify-content-between align-items-center flex-wrap">

                                    <h6 class="mb-0 text-dark"
                                        style="
                    font-size:14px;
                    font-weight:600;
                ">

                                        <i class="fas fa-layer-group text-success mr-1"></i>
                                        Lista de Subcategorías

                                    </h6>

                                    <span class="badge badge-light border px-2 py-1"
                                        style="
                    font-size:10px;
                    font-weight:600;
                ">

                                        <span id="vc_total_subcategories">0</span>
                                        registros

                                    </span>

                                </div>

                            </div>

                            <div class="card-body p-2">

                                <div class="table-responsive">

                                    <table class="table table-sm table-hover mb-0">

                                        <thead
                                            style="
                        background:#f8f9fa;
                    ">

                                            <tr>

                                                <th style="font-size:11px;">
                                                    #
                                                </th>

                                                <th style="font-size:11px;">
                                                    DESCRIPCIÓN
                                                </th>

                                                <th style="font-size:11px;">
                                                    ESTADO
                                                </th>

                                            </tr>

                                        </thead>

                                        <tbody id="vc_subcategories_table">

                                            <tr>

                                                <td colspan="3" class="text-center text-muted py-3">

                                                    No hay subcategorías registradas

                                                </td>

                                            </tr>

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>


                        <!-- FOOT -->
                        <div class="row mt-2">

                            <div class="col-md-4 mb-2">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block" style="font-size:10px;">

                                            ID

                                        </small>

                                        <div id="vc_id" class="text-dark"
                                            style="
                                                font-size:12px;
                                                font-weight:600;
                                            ">

                                            —

                                        </div>

                                    </div>

                                </div>

                            </div>

                         

                            <div class="col-md-4 mb-2">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block" style="font-size:10px;">

                                            Última edición

                                        </small>

                                        <div id="vc_updated_by_user" class="text-dark"
                                            style="
                                                font-size:12px;
                                                font-weight:500;
                                            ">

                                            —

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="col-md-4 mb-2">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block" style="font-size:10px;">

                                            Fecha Registro

                                        </small>

                                        <div id="vc_created_at" class="text-dark"
                                            style="
                                                font-size:12px;
                                                font-weight:500;
                                            ">

                                            —

                                        </div>

                                    </div>

                                </div>

                            </div>

                 

                        </div>

                    </div>
                    <!-- END RIGHT -->

                </div>

            </div>

        </div>

    </div>

</div>
