<!-- VIEW PRESENTATION MODAL -->
<div class="modal fade" id="viewPresentationModal" tabindex="-1" role="dialog" aria-labelledby="viewPresentationModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">

        <div class="modal-content border-0 shadow overflow-hidden" style="border-radius:14px;">

            <!-- HEADER -->
            <div class="modal-header border-0 py-2 px-3"
                style="
                    background:
                    linear-gradient(
                        135deg,
                        #fbc02d,
                        #f57f17
                    );
                ">

                <h5 class="modal-title text-white mb-0" id="viewPresentationModalLabel"
                    style="
                        font-size:15px;
                        font-weight:600;
                        letter-spacing:.3px;
                    ">

                    <i class="fas fa-box-open mr-2"></i>
                    Información de Presentación

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
                                    background:
                                    linear-gradient(
                                        135deg,
                                        #fbc02d,
                                        #f57f17
                                    );
                                    color:white;
                                    font-size:24px;
                                ">

                                <i class="fas fa-box-open"></i>

                            </div>

                            <h5 id="vp_description" class="text-dark mb-1"
                                style="
                                    font-size:18px;
                                    font-weight:600;
                                ">

                                —

                            </h5>

                            <div id="vp_unit" class="text-muted mb-2"
                                style="
                                    font-size:12px;
                                    letter-spacing:.2px;
                                ">

                                —

                            </div>

                            <span id="vp_status" class="badge badge-warning px-3 py-1 shadow-sm text-white"
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

                                    <div id="vp_created_by" class="text-dark mb-2"
                                        style="
                                            font-size:13px;
                                            font-weight:500;
                                        ">

                                        —

                                    </div>

                                    <small class="text-muted d-block mb-1" style="font-size:11px;">

                                        Última actualización

                                    </small>

                                    <div id="vp_updated_at" class="text-dark"
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

                                    <i class="fas fa-info-circle text-warning mr-1"></i>
                                    Detalle de Presentación

                                </h6>

                            </div>

                            <div class="table-responsive">

                                <table class="table table-sm mb-0">

                                    <tbody>

                                        <tr>

                                            <th width="180" class="border-top-0 text-muted py-2"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">

                                                Descripción

                                            </th>

                                            <td id="vp_description_detail" class="border-top-0 text-dark py-2"
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

                                                Cantidad

                                            </th>

                                            <td id="vp_quantity" class="text-dark py-2"
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

                                                Unidad

                                            </th>

                                            <td id="vp_unit_detail" class="text-dark py-2"
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

                                            <td id="vp_status_text" class="text-dark py-2"
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

                                            <td id="vp_observation" class="text-dark py-2"
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

                        <!-- FOOT -->
                        <div class="row mt-2">

                            <div class="col-md-4 mb-2">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block" style="font-size:10px;">

                                            ID

                                        </small>

                                        <div id="vp_id" class="text-dark"
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

                                        <div id="vp_updated_by_user" class="text-dark"
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

                                        <div id="vp_created_at" class="text-dark"
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
