<!-- VIEW SUPPLIER MODAL -->
<div class="modal fade" id="viewSupplierModal" tabindex="-1" role="dialog" aria-labelledby="viewSupplierModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">

        <div class="modal-content border-0 shadow overflow-hidden" style="border-radius:14px;">

            <!-- HEADER -->
            <div class="modal-header border-0 py-2 px-3"
                style="
                    background:
                    linear-gradient(
                        135deg,
                        #17a2b8,
                        #007bff
                    );
                ">

                <h5 class="modal-title text-white mb-0" id="viewSupplierModalLabel"
                    style="
                        font-size:15px;
                        font-weight:600;
                        letter-spacing:.3px;
                    ">

                    <i class="fas fa-truck mr-2"></i>
                    Información del Proveedor

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
                                        #17a2b8,
                                        #007bff
                                    );
                                    color:white;
                                    font-size:24px;
                                ">

                                <i class="fas fa-truck-loading"></i>

                            </div>

                            <h5 id="vs_business_name" class="text-dark mb-1"
                                style="
                                    font-size:17px;
                                    font-weight:600;
                                ">

                                —

                            </h5>

                            <div id="vs_ruc" class="text-muted mb-2"
                                style="
                                    font-size:12px;
                                    letter-spacing:.2px;
                                ">

                                —

                            </div>

                            <span id="vs_status" class="badge badge-info px-3 py-1 shadow-sm text-white"
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

                                    <div id="vs_created_by" class="text-dark mb-2"
                                        style="
                                            font-size:13px;
                                            font-weight:500;
                                        ">

                                        —

                                    </div>

                                    <small class="text-muted d-block mb-1" style="font-size:11px;">

                                        Última actualización

                                    </small>

                                    <div id="vs_updated_at" class="text-dark"
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

                                    <i class="fas fa-info-circle text-info mr-1"></i>
                                    Detalle del Proveedor

                                </h6>

                            </div>

                            <div class="table-responsive">

                                <table class="table table-sm mb-0">

                                    <tbody>

                                        <tr>

                                            <th width="220" class="border-top-0 text-muted py-2"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">

                                                Razón Social

                                            </th>

                                            <td id="vs_business_name_detail" class="border-top-0 text-dark py-2"
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

                                                Nombre Corto

                                            </th>

                                            <td id="vs_short_name" class="text-dark py-2"
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

                                                Dirección

                                            </th>

                                            <td id="vs_address" class="text-dark py-2"
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

                                                Ubigeo

                                            </th>

                                            <td id="vs_ubigeo" class="text-dark py-2"
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

                                                Tipo Proveedor

                                            </th>

                                            <td id="vs_supplier_type" class="text-dark py-2"
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

                                                Condición Pago

                                            </th>

                                            <td id="vs_payment_condition" class="text-dark py-2"
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

                                                Contacto

                                            </th>

                                            <td id="vs_contact_name" class="text-dark py-2"
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

                                                Correo

                                            </th>

                                            <td id="vs_email" class="text-dark py-2"
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

                                                Teléfono

                                            </th>

                                            <td id="vs_phone" class="text-dark py-2"
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

                                                IGV %

                                            </th>

                                            <td id="vs_igv_percentage" class="text-dark py-2"
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

                                            <td id="vs_observation" class="text-dark py-2"
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

                        <!-- CUENTAS BANCARIAS -->
                        <div class="card border-0 shadow-sm mt-3">

                            <div class="card-header bg-white border-0 py-2">

                                <h6 class="mb-0 text-dark"
                                    style="
                font-size:13px;
                font-weight:600;
            ">
                                    <i class="fas fa-university text-success mr-1"></i>
                                    Cuentas Bancarias
                                </h6>

                            </div>

                            <div class="card-body p-2">

                                <div class="table-responsive">

                                    <table class="table table-sm mb-0">

                                        <thead>

                                            <tr
                                                style="
                            font-size:11px;
                            text-transform:uppercase;
                        ">

                                                <th width="30">#</th>

                                                <th>Banco</th>

                                                <th>Moneda</th>

                                                <th>Titular</th>

                                                <th>N° Cuenta</th>

                                                <th>CCI</th>

                                                <th>Detracción</th>

                                                <th>Estado</th>

                                            </tr>

                                        </thead>

                                        <tbody id="vs_accounts_body">

                                            <tr>

                                                <td colspan="8" class="text-center text-muted py-2">

                                                    Sin cuentas registradas

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

                                        <div id="vs_id" class="text-dark"
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

                                        <div id="vs_updated_by" class="text-dark"
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

                                        <div id="vs_created_at" class="text-dark"
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
