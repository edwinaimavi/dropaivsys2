<!-- MODAL MARKET STUDY -->
<div class="modal fade" id="marketStudyModal" tabindex="-1" role="dialog" aria-labelledby="marketStudyModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg">

            <!-- HEADER -->
            <div class="modal-header align-items-center"
                style="
                    background:linear-gradient(90deg,#f4fff7,#e8fff0);
                    border-bottom:1px solid #b9efcb;
                ">

                <div class="d-flex align-items-center">

                    <div class="mr-3"
                        style="
                            background:#dff8e8;
                            width:42px;
                            height:42px;
                            border-radius:10px;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                        ">

                        <i class="fas fa-chart-line text-success"></i>

                    </div>

                    <div>

                        <h5 class="modal-title mb-0 font-weight-bold" id="marketStudyModalLabel">
                            Nuevo Estudio de Mercado
                        </h5>

                        <small class="text-muted">
                            Registro y administración de estudios de mercado
                        </small>

                    </div>

                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Close" style="opacity:.9;">
                    <span aria-hidden="true">&times;</span>
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-2" style="background:#fbfffc;">

                <form id="marketStudyForm" autocomplete="off" enctype="multipart/form-data" class="row">

                    @csrf

                    <input type="hidden" id="market_study_id" name="market_study_id">

                    <!-- PANEL IZQUIERDO -->
                    <div class="col-lg-3 mb-2">

                        <div class="card border-0 rounded-lg shadow-sm h-100">

                            <div class="card-body text-center py-3 px-3">

                                <div class="mb-3">

                                    <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                        style="
                                            width:85px;
                                            height:85px;
                                            background:linear-gradient(135deg,#198754,#28a745);
                                            color:white;
                                            font-size:32px;
                                            box-shadow:0 6px 18px rgba(0,0,0,.1);
                                        ">

                                        <i class="fas fa-search-dollar"></i>

                                    </div>

                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">
                                    Estudios de Mercado
                                </h5>

                                <small class="text-muted">
                                    Comparación y evaluación de artículos
                                </small>

                                <hr class="my-2">

                                <div class="text-left small">

                                    <small class="text-muted d-block">
                                        Fecha de registro
                                    </small>

                                    <div class="font-weight-600 mb-2">
                                        {{ now()->format('d/m/Y') }}
                                    </div>

                                    <small class="text-muted d-block">
                                        Estado inicial
                                    </small>

                                    <div class="badge badge-success py-1 px-2 mt-1 text-white">
                                        Activo
                                    </div>

                                    <small class="text-muted d-block mt-2">
                                        Módulo
                                    </small>

                                    <div class="font-weight-600 mb-2">
                                        Estudios de Mercado
                                    </div>

                                    <small class="text-muted d-block">
                                        Función
                                    </small>

                                    <div class="font-weight-600">
                                        Gestión de cotizaciones y artículos
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- PANEL DERECHO -->
                    <div class="col-lg-9">

                        <div class="card border-0 rounded-lg shadow-sm">

                            <div class="card-body py-3">

                                <!-- FILA 1 -->
                                <div class="form-row">

                                    <div class="form-group col-md-3">

                                        <label class="small font-weight-bold text-secondary">
                                            CÓDIGO
                                        </label>

                                        <input type="text" id="code" name="code"
                                            class="form-control form-control-sm" placeholder="Código automático"
                                            readonly>

                                        <span class="invalid-feedback" id="code-error"></span>

                                    </div>

                                    <div class="form-group col-md-9">

                                        <label class="small font-weight-bold text-secondary">
                                            DESCRIPCIÓN <span class="text-danger">*</span>
                                        </label>

                                        <input type="text" id="description" name="description"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="Ingrese descripción">

                                        <span class="invalid-feedback" id="description-error"></span>

                                    </div>

                                </div>

                                <!-- FILA 2 -->
                                <div class="form-row">

                                    <div class="form-group col-md-12">

                                        <label class="small font-weight-bold text-secondary">
                                            TÉRMINOS DE REFERENCIA
                                        </label>

                                        <textarea id="reference_terms" name="reference_terms" rows="3" class="form-control form-control-sm text-uppercase"
                                            placeholder="Ingrese términos de referencia"></textarea>

                                        <span class="invalid-feedback" id="reference_terms-error"></span>

                                    </div>

                                </div>

                                <!-- FILA 3 -->
                                <div class="form-row">

                                    <div class="form-group col-md-6">

                                        <label class="small font-weight-bold text-secondary">
                                            DOCUMENTOS ADJUNTOS
                                        </label>

                                        <div class="market-study-upload">

                                            <input type="file" id="market_study_documents" name="documents[]"
                                                multiple hidden>

                                            <label for="market_study_documents" class="market-study-upload-label mb-0">

                                                <div class="upload-icon">

                                                    <i class="fas fa-cloud-upload-alt"></i>

                                                </div>

                                                <div>

                                                    <div class="font-weight-bold text-success">
                                                        Seleccionar archivos
                                                    </div>

                                                    <small class="text-muted">
                                                        PDF, JPG, PNG, DOCX
                                                    </small>

                                                </div>

                                            </label>

                                            <div id="selectedFiles" class="mt-2"></div>

                                        </div>

                                    </div>

                                    <div class="form-group col-md-3">

                                        <label class="small font-weight-bold text-secondary">
                                            ESTADO
                                        </label>

                                        <select id="status" name="status" class="form-control form-control-sm">

                                            <option value="1">ACTIVO</option>
                                            <option value="0">INACTIVO</option>

                                        </select>

                                        <span class="invalid-feedback" id="status-error"></span>

                                    </div>

                                    <div class="form-group col-md-3 d-flex align-items-end">

                                        <div class="w-100 text-right">

                                            <small class="text-muted d-block mb-1">
                                                Estado inicial sugerido
                                            </small>

                                            <div class="badge badge-success py-1 px-2">
                                                ACTIVO
                                            </div>

                                        </div>

                                    </div>

                                </div>

                                <!-- ALERT -->
                                <div class="alert border-0 shadow-sm mb-3"
                                    style="
                                        background:#e8fff0;
                                        color:#146c43;
                                    ">

                                    <div class="d-flex align-items-center">

                                        <div class="mr-2">
                                            <i class="fas fa-info-circle text-success"></i>
                                        </div>

                                        <div class="small">
                                            <strong>Importante:</strong>
                                            Los artículos se agregan directamente en esta ventana,
                                            y los documentos adjuntos quedarán vinculados al estudio.
                                        </div>

                                    </div>

                                </div>

                                <!-- BOTONES -->
                                <div class="d-flex justify-content-end">

                                    <button type="button" class="btn btn-light border btn-sm mr-2"
                                        data-dismiss="modal">

                                        <i class="fas fa-times mr-1"></i>
                                        Cerrar

                                    </button>

                                    <button type="submit" id="btnSaveMarketStudy" class="btn btn-success btn-sm">

                                        <i class="fas fa-save mr-1"></i>
                                        Guardar Estudio

                                    </button>

                                </div>

                            </div>

                        </div>

                        <!-- ARTÍCULOS -->
                        <div class="card border-0 rounded-lg shadow-sm mt-2">

                            <div
                                class="card-header bg-white border-0 py-2 px-3 d-flex justify-content-between align-items-center">

                                <div>

                                    <h6 class="mb-0 text-dark font-weight-bold">
                                        <i class="fas fa-boxes text-success mr-1"></i>
                                        Artículos del Estudio
                                    </h6>

                                    <small class="text-muted">
                                        Agrega los artículos comparados en este estudio
                                    </small>

                                </div>

                                <div>

                                    <button type="button" id="btnInsertMarketStudyArticle"
                                        class="btn btn-outline-success btn-sm">

                                        <i class="fas fa-plus-circle mr-1"></i>
                                        Insertar artículo

                                    </button>

                                </div>

                            </div>

                            <div class="table-responsive">

                                <table id="marketStudyArticlesTable"
                                    class="table table-hover table-sm mb-0 w-100 text-center">

                                    <thead class="bg-light">

                                        <tr>

                                            <th width="5%">#</th>

                                            <th>CÓDIGO | NOMBRE DE FACTURACIÓN</th>

                                            <th>CATEGORÍA | SUBCATEGORÍA</th>

                                            <th>PRESENTACIÓN</th>

                                            <th>PESO</th>

                                            <th>CONDICIÓN DE COSTEO</th>

                                            <th width="10%">ACCIONES</th>

                                        </tr>

                                    </thead>

                                    <tbody id="marketStudyArticlesTbody">

                                        <tr id="marketStudyArticlesEmptyRow">

                                            <td colspan="7" class="text-muted py-4">
                                                No hay artículos agregados aún.
                                            </td>

                                        </tr>

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<style>
    #marketStudyModal .modal-content {
        border-radius: 14px;
    }

    #marketStudyModal .card {
        border-radius: 12px;
    }

    #marketStudyModal .table td,
    #marketStudyModal .table th {
        padding-top: .42rem;
        padding-bottom: .42rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    #marketStudyModal .table thead th {
        font-size: 11px;
        font-weight: 700;
        color: #666;
    }

    #marketStudyModal .table tbody td {
        font-size: 12px;
    }

    #marketStudyModal .form-control-sm {
        font-size: 12px;
    }

    #marketStudyModal .small {
        font-size: 11px;
    }

    #marketStudyModal .btn-success {
        border-radius: 8px;
    }

    #marketStudyModal .btn-outline-success {
        border-radius: 8px;
    }

    #marketStudyModal .badge {
        font-size: 10px;
        font-weight: 600;
    }

    #marketStudyModal .font-weight-600 {
        font-weight: 600;
    }

    @media (max-width: 991px) {
        #marketStudyModal .modal-dialog {
            max-width: 100%;
            margin: .5rem;
        }
    }



    .market-study-upload {
        width: 100%;
    }

    .market-study-upload-label {
        width: 100%;
        border: 2px dashed #b9efcb;
        background: #f8fff9;
        border-radius: 12px;
        padding: 15px;
        cursor: pointer;
        display: flex;
        align-items: center;
        transition: .25s;
    }

    .market-study-upload-label:hover {
        background: #eefdf3;
        border-color: #198754;
    }

    .upload-icon {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        background: #dff8e8;
        color: #198754;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        margin-right: 15px;
    }

    .file-chip {
        display: inline-flex;
        align-items: center;
        padding: 6px 12px;
        border-radius: 20px;
        background: #e8fff0;
        color: #146c43;
        margin: 3px;
        font-size: 12px;
        font-weight: 600;
    }
</style>
