<!-- MODAL CLIENTE -->
<div class="modal fade" id="customerModal" tabindex="-1" role="dialog" aria-labelledby="customerModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg overflow-hidden">

            <!-- HEADER -->
            <div class="modal-header align-items-center py-2"
                style="background: linear-gradient(90deg,#ffffff,#f3f6f8); border-bottom:1px solid #e6eaee;">

                <div class="d-flex align-items-center">

                    <div class="icon-circle bg-light mr-3 icon_modal">
                        <i class="fas fa-users text-primary"></i>
                    </div>

                    <div>
                        <h5 class="modal-title mb-0" id="customerModalLabel" style="font-size:15px; font-weight:700;">
                            Nuevo Cliente
                        </h5>

                        <small class="text-muted" style="font-size:11px;">
                            Gestión de clientes del sistema
                        </small>
                    </div>

                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">
                    <span aria-hidden="true">&times;</span>
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2" style="background: #f8fbfc;">

                <form id="customerForm" autocomplete="off" class="row">

                    @csrf

                    <!-- PANEL IZQUIERDO -->
                    <div class="col-lg-4 mb-2">

                        <div class="card border-0 rounded-lg shadow-sm h-100">

                            <div class="card-body text-center py-3 px-3">

                                <div class="mb-2">

                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                        style="
                                            width:108px;
                                            height:108px;
                                            background:linear-gradient(135deg,#007bff,#0056b3);
                                            color:white;
                                            font-size:40px;
                                            box-shadow:0 6px 18px rgba(0,0,0,.1);
                                        ">
                                        <i class="fas fa-user-friends"></i>
                                    </div>

                                </div>

                                <h5 class="font-weight-bold text-dark mb-1" style="font-size:18px;">
                                    Cliente
                                </h5>

                                <small class="text-muted" style="font-size:11px;">
                                    Gestión de clientes inmobiliarios
                                </small>

                                <hr class="my-2">

                                <div class="text-left" style="font-size:12px;">

                                    <small class="text-muted" style="font-size:10px;">
                                        Fecha de registro
                                    </small>

                                    <div class="font-weight-600" style="font-size:12px;">
                                        {{ now()->format('d/m/Y') }}
                                    </div>

                                    <small class="text-muted d-block mt-2" style="font-size:10px;">
                                        Estado
                                    </small>

                                    <div class="badge badge-success py-1 px-2 mt-1" style="font-size:10px;">
                                        Activo
                                    </div>

                                    <small class="text-muted d-block mt-2" style="font-size:10px;">
                                        Módulo
                                    </small>

                                    <div class="font-weight-600" style="font-size:12px;">
                                        Clientes
                                    </div>

                                    <small class="text-muted d-block mt-2" style="font-size:10px;">
                                        Descripción
                                    </small>

                                    <div style="font-size:12px; line-height:1.2;">
                                        Gestión de clientes del sistema
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- PANEL DERECHO -->
                    <div class="col-lg-8">

                        <!-- INFORMACION GENERAL -->
                        <div class="card border-0 shadow-sm mb-2">

                            <div class="bg-primary text-white px-3 py-2 rounded-top"
                                style="font-size:13px; font-weight:700;">

                                <i class="fas fa-id-card mr-2"></i>
                                Información General

                            </div>

                            <div class="card-body py-2 px-3">

                                <div class="form-row">

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            TIPO PERSONA <span class="text-danger">*</span>
                                        </label>

                                        <select name="person_type" id="person_type"
                                            class="form-control form-control-sm">

                                            <option value="natural">
                                                Persona Natural
                                            </option>

                                            <option value="juridica">
                                                Persona Jurídica
                                            </option>

                                        </select>

                                    </div>

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            TIPO DOCUMENTO <span class="text-danger">*</span>
                                        </label>

                                        <select name="document_type" id="document_type"
                                            class="form-control form-control-sm">

                                            <option value="">
                                                Seleccione
                                            </option>

                                            <option value="DNI">
                                                DNI
                                            </option>

                                            <option value="CE">
                                                CE
                                            </option>

                                            <option value="RUC">
                                                RUC
                                            </option>

                                        </select>

                                    </div>

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            N° DOCUMENTO <span class="text-danger">*</span>
                                        </label>

                                        <input type="text" name="document_number" id="document_number"
                                            class="form-control form-control-sm">

                                        <div id="document_number-error" class="invalid-feedback d-block">
                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- CLASIFICACION COMERCIAL -->
                        <div class="card border-0 shadow-sm mb-2">

                            <div class="bg-info text-white px-3 py-2 rounded-top"
                                style="font-size:13px; font-weight:700;">

                                <i class="fas fa-briefcase mr-2"></i>
                                Clasificación Comercial

                            </div>

                            <div class="card-body py-2 px-3">

                                <div class="form-row">

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            CANAL
                                        </label>

                                        <select name="channel" id="channel" class="form-control form-control-sm">

                                            <option value="">
                                                Seleccione
                                            </option>

                                            <option value="PRESTADOR DE SALUD">
                                                PRESTADOR DE SALUD
                                            </option>

                                            <option value="DISTRIBUIDOR">
                                                DISTRIBUIDOR
                                            </option>

                                        </select>

                                    </div>

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            SUB CANAL
                                        </label>

                                        <select name="subchannel" id="subchannel"
                                            class="form-control form-control-sm">

                                            <option value="">
                                                Seleccione
                                            </option>

                                            <option value="PUBLICO">
                                                PÚBLICO
                                            </option>

                                            <option value="PRIVADO">
                                                PRIVADO
                                            </option>

                                        </select>

                                    </div>

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            ¿AGENTE DE RETENCIÓN?
                                        </label>

                                        <select name="withholding_agent" id="withholding_agent"
                                            class="form-control form-control-sm">

                                            <option value="0">
                                                NO
                                            </option>

                                            <option value="1">
                                                SI
                                            </option>

                                        </select>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- DATOS DEL CLIENTE -->
                        <div class="card border-0 shadow-sm mb-2">

                            <div class="bg-success text-white px-3 py-2 rounded-top"
                                style="font-size:13px; font-weight:700;">

                                <i class="fas fa-user mr-2"></i>
                                Datos del Cliente

                            </div>

                            <div class="card-body py-2 px-3">

                                <!-- PERSONA NATURAL -->
                                <div id="naturalFields">

                                    <div class="form-row">

                                        <div class="form-group col-md-6 mb-2">

                                            <label class="font-weight-bold text-secondary mb-1"
                                                style="font-size:11px;">
                                                NOMBRES <span class="text-danger">*</span>
                                            </label>

                                            <input type="text" name="first_name" id="first_name"
                                                class="form-control form-control-sm">

                                            <div id="first_name-error" class="invalid-feedback d-block">
                                            </div>

                                        </div>

                                        <div class="form-group col-md-6 mb-2">

                                            <label class="font-weight-bold text-secondary mb-1"
                                                style="font-size:11px;">
                                                APELLIDOS <span class="text-danger">*</span>
                                            </label>

                                            <input type="text" name="last_name" id="last_name"
                                                class="form-control form-control-sm">

                                        </div>

                                    </div>

                                </div>

                                <!-- PERSONA JURIDICA -->
                                <div id="businessFields" class="d-none">

                                    <div class="form-row">

                                        <div class="form-group col-md-12 mb-2">

                                            <label class="font-weight-bold text-secondary mb-1"
                                                style="font-size:11px;">
                                                RAZÓN SOCIAL <span class="text-danger">*</span>
                                            </label>

                                            <input type="text" name="business_name" id="business_name"
                                                class="form-control form-control-sm">

                                            <div id="business_name-error" class="invalid-feedback d-block">
                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- CONTACTO -->
                        <div class="card border-0 shadow-sm mb-2">

                            <div class="bg-teal text-white px-3 py-2 rounded-top"
                                style="background:#17a2b8; font-size:13px; font-weight:700;">

                                <i class="fas fa-phone mr-2"></i>
                                Información de Contacto

                            </div>

                            <div class="card-body py-2 px-3">

                                <div class="form-row">

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            TELÉFONO
                                        </label>

                                        <input type="text" name="phone" id="phone"
                                            class="form-control form-control-sm">

                                    </div>

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            EMAIL
                                        </label>

                                        <input type="email" name="email" id="email"
                                            class="form-control form-control-sm">

                                    </div>

                                    <div class="form-group col-md-4 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            DIRECCIÓN
                                        </label>

                                        <input type="text" name="address" id="address"
                                            class="form-control form-control-sm">

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- CONFIGURACION -->
                        <div class="card border-0 shadow-sm">

                            <div class="bg-secondary text-white px-3 py-2 rounded-top"
                                style="font-size:13px; font-weight:700;">

                                <i class="fas fa-cog mr-2"></i>
                                Configuración

                            </div>

                            <div class="card-body py-2 px-3">

                                <div class="form-row">

                                    <div class="form-group col-md-6 mb-2">

                                        <label class="font-weight-bold text-secondary mb-1" style="font-size:11px;">
                                            ESTADO
                                        </label>

                                        <select id="status" name="status" class="form-control form-control-sm">

                                            <option value="1" selected>
                                                Activo
                                            </option>

                                            <option value="0">
                                                Inactivo
                                            </option>

                                        </select>

                                    </div>

                                </div>

                                <div class="form-row">
                                    <div class="col-12">
                                        <small class="text-muted" style="font-size:11px;">
                                            La información registrada aquí será utilizada para ventas, contratos y
                                            seguimiento de clientes.
                                        </small>
                                    </div>
                                </div>

                            </div>

                        </div>

                        <!-- ACCIONES -->
                        <div class="form-row mt-3">

                            <div class="col-12 d-flex justify-content-end">

                                <button type="button" class="btn btn-light border mr-2 btn-sm" data-dismiss="modal">
                                    <i class="fas fa-times mr-1"></i>
                                    Cerrar
                                </button>

                                <button type="submit" class="btn btn-primary btn-sm" id="btnSaveCustomer">
                                    <i class="fas fa-save mr-1"></i>
                                    Guardar Cliente
                                </button>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>
