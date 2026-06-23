{{-- VIEW CUSTOMER MODAL --}}
<div class="modal fade" id="viewCustomerModal" tabindex="-1" role="dialog" aria-labelledby="viewCustomerModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">

        <div class="modal-content border-0 shadow overflow-hidden" style="border-radius:14px;">

            {{-- HEADER --}}
            <div class="modal-header border-0 py-2 px-3" style="background:linear-gradient(135deg,#1d4ed8,#2563eb);">

                <h5 class="modal-title text-white mb-0" id="viewCustomerModalLabel"
                    style="
                        font-size:15px;
                        font-weight:600;
                        letter-spacing:.3px;
                    ">

                    <i class="fas fa-users mr-2"></i>
                    Información del Cliente

                </h5>

                <button type="button" class="close text-white" data-dismiss="modal"
                    style="
                        opacity:1;
                        font-size:22px;
                    ">

                    <span>&times;</span>

                </button>

            </div>

            {{-- BODY --}}
            <div class="modal-body p-3">

                <div class="row">

                    {{-- LEFT --}}
                    <div class="col-md-4">

                        <div class="text-center">

                            <div class="mx-auto mb-2 d-flex align-items-center justify-content-center shadow-sm"
                                style="
                                    width:80px;
                                    height:80px;
                                    border-radius:18px;
                                    background:linear-gradient(135deg,#2563eb,#1d4ed8);
                                    color:white;
                                    font-size:28px;
                                ">

                                <i class="fas fa-user-tie"></i>

                            </div>

                            <h5 id="vc_full_name" class="text-dark mb-1"
                                style="
                                    font-size:22px;
                                    font-weight:600;
                                ">

                                —

                            </h5>

                            <div id="vc_document" class="text-muted mb-2" style="font-size:13px;">

                                —

                            </div>

                            <span id="vc_status_badge" class="badge badge-success px-3 py-1 shadow-sm"
                                style="
                                    border-radius:7px;
                                    font-size:10px;
                                    font-weight:500;
                                ">

                                ACTIVO

                            </span>

                        </div>

                        {{-- INFO CARD --}}
                        <div class="mt-3">

                            <div class="card border-0 shadow-sm">

                                <div class="card-body py-2 px-3">

                                    <small class="text-muted d-block mb-1" style="font-size:11px;">
                                        Registrado por
                                    </small>

                                    <div id="vc_created_by_user" class="text-dark mb-2"
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

                    {{-- RIGHT --}}
                    <div class="col-md-8">

                        {{-- DETALLE GENERAL --}}
                        <div class="card border-0 shadow-sm">

                            <div class="card-header bg-white border-0 py-2">

                                <h6 class="mb-0 text-dark"
                                    style="
                                        font-size:14px;
                                        font-weight:600;
                                    ">

                                    <i class="fas fa-info-circle text-primary mr-1"></i>
                                    Detalle del Cliente

                                </h6>

                            </div>

                            <div class="table-responsive">

                                <table class="table table-sm mb-0"
                                    style="
                                        font-size:13px;
                                        line-height:1.1;
                                    ">

                                    <tbody>

                                        <tr>
                                            <th width="180" class="text-muted py-1"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                    vertical-align:middle;
                                                ">
                                                Tipo Persona
                                            </th>
                                            <td id="vc_person_type"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="text-muted"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">
                                                Tipo Documento
                                            </th>
                                            <td id="vc_document_type"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="text-muted"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">
                                                Número Documento
                                            </th>
                                            <td id="vc_document_number"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="text-muted"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">
                                                Canal
                                            </th>
                                            <td id="vc_channel"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="text-muted"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">
                                                Sub Canal
                                            </th>
                                            <td id="vc_subchannel"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="text-muted"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">
                                                Agente Retención
                                            </th>
                                            <td id="vc_withholding_agent"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="text-muted"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">
                                                Teléfono
                                            </th>
                                            <td id="vc_phone"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="text-muted"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">
                                                Correo Electrónico
                                            </th>
                                            <td id="vc_email"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                        <tr>
                                            <th class="text-muted"
                                                style="
                                                    font-size:12px;
                                                    font-weight:600;
                                                ">
                                                Dirección
                                            </th>
                                            <td id="vc_address"
                                                style="
                                                    font-size:12px;
                                                    font-weight:500;
                                                    vertical-align:middle;
                                                ">
                                                —
                                            </td>
                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                        {{-- FOOT CARDS --}}
                        <div class="row mt-2">

                            <div class="col-md-3 mb-2">

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

                            <div class="col-md-3 mb-2">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block" style="font-size:10px;">
                                            Canal
                                        </small>

                                        <div id="vc_channel_card" class="text-dark"
                                            style="
                                                font-size:12px;
                                                font-weight:500;
                                            ">
                                            —
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="col-md-3 mb-2">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block" style="font-size:10px;">
                                            Última edición
                                        </small>

                                        <div id="vc_updated_by" class="text-dark"
                                            style="
                                                font-size:12px;
                                                font-weight:500;
                                            ">
                                            —
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="col-md-3 mb-2">

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

                        {{-- SEDES --}}
                        <div class="card border-0 shadow-sm mt-2">

                            <div class="card-header bg-white border-0 py-2">

                                <h6 class="mb-0 text-dark"
                                    style="
                                        font-size:14px;
                                        font-weight:600;
                                    ">

                                    <i class="fas fa-building text-primary mr-1"></i>
                                    Sedes del Cliente

                                </h6>

                                <small class="text-muted" style="font-size:11px;">
                                    Sucursales, oficinas o puntos de atención registrados
                                </small>

                            </div>

                            <div class="table-responsive">

                                <table id="vc_branches_table"
                                    class="table table-hover table-sm mb-0 text-center w-100">
                                    <thead class="bg-light">
                                        <tr>
                                            <th width="5%">#</th>
                                            <th>SEDE</th>
                                            <th width="14%">TIPO</th>
                                            <th>UBIGEO</th>
                                            <th width="12%">COMPROBANTE</th>
                                            <th width="12%">CONDICIÓN</th>
                                            <th width="10%">PRINCIPAL</th>
                                            <th width="10%">ESTADO</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>

                            </div>

                        </div>

                        {{-- CONTACTOS --}}
                        <div class="card border-0 shadow-sm mt-2">

                            <div class="card-header bg-white border-0 py-2">

                                <h6 class="mb-0 text-dark"
                                    style="
                                        font-size:14px;
                                        font-weight:600;
                                    ">

                                    <i class="fas fa-address-book text-primary mr-1"></i>
                                    Contactos por Sede

                                </h6>

                                <small class="text-muted" style="font-size:11px;">
                                    Contactos asociados a cada sede
                                </small>

                            </div>

                            <div class="table-responsive">

                                <table id="vc_contacts_table"
                                    class="table table-hover table-sm mb-0 text-center w-100">
                                    <thead class="bg-light">
                                        <tr>
                                            <th width="5%">#</th>
                                            <th>SEDE</th>
                                            <th>CONTACTO</th>
                                            <th width="12%">TELÉFONO</th>
                                            <th width="18%">CORREO</th>
                                            <th>DIRECCIÓN</th>
                                            <th>REFERENCIA</th>
                                            <th width="10%">ESTADO</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>

                            </div>

                        </div>

                    </div>
                    {{-- END RIGHT --}}

                </div>

            </div>

        </div>

    </div>

</div>

<style>
    #viewCustomerModal .modal-content {
        border-radius: 14px;
    }

    #viewCustomerModal .table td,
    #viewCustomerModal .table th {
        padding-top: .42rem;
        padding-bottom: .42rem;
        vertical-align: middle;
    }

    #viewCustomerModal .card {
        border-radius: 12px;
    }

    #viewCustomerModal .table thead th {
        font-size: 11px;
        font-weight: 700;
        color: #666;
        white-space: nowrap;
    }

    #viewCustomerModal .table tbody td {
        font-size: 12px;
        white-space: nowrap;
    }
</style>
