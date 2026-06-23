<!-- VIEW ARTICLE MODAL -->

<div class="modal fade" id="viewArticleModal" tabindex="-1" role="dialog" aria-labelledby="viewArticleModalLabel"
    aria-hidden="true">

    ```
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">

        <div class="modal-content border-0 shadow overflow-hidden" style="border-radius:14px;">

            <!-- HEADER -->
            <div class="modal-header border-0 py-2 px-3"
                style="
                background:
                linear-gradient(
                    135deg,
                    #0d6efd,
                    #1e88e5
                );
            ">

                <h5 class="modal-title text-white mb-0" id="viewArticleModalLabel"
                    style="
                    font-size:15px;
                    font-weight:600;
                    letter-spacing:.3px;
                ">

                    <i class="fas fa-box-open mr-2"></i>
                    Información del Artículo

                </h5>

                <button type="button" class="close text-white" data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-3">

                <div class="row">

                    <!-- LEFT -->
                    <div class="col-md-3">

                        <div class="text-center">

                            <div class="mx-auto mb-2 d-flex align-items-center justify-content-center shadow-sm"
                                style="
                                width:80px;
                                height:80px;
                                border-radius:18px;
                                background:
                                linear-gradient(
                                    135deg,
                                    #0d6efd,
                                    #1e88e5
                                );
                                color:white;
                                font-size:28px;
                            ">

                                <i class="fas fa-box"></i>

                            </div>

                            <h5 id="va_legal_name" class="text-dark mb-1"
                                style="
                                font-size:17px;
                                font-weight:600;
                            ">

                                —

                            </h5>

                            <div id="va_code" class="text-muted mb-2"
                                style="
                                font-size:12px;
                                letter-spacing:.2px;
                            ">

                                —

                            </div>

                            <span id="va_status" class="badge badge-success px-3 py-1 shadow-sm text-white"
                                style="
                                border-radius:7px;
                                font-size:10px;
                                font-weight:500;
                            ">

                                ACTIVO

                            </span>

                        </div>

                        <div class="mt-3">

                            <div class="card border-0 shadow-sm">

                                <div class="card-body py-2 px-3">

                                    <small class="text-muted d-block mb-1">

                                        Registrado por

                                    </small>

                                    <div id="va_created_by" class="mb-2">

                                        —

                                    </div>

                                    <small class="text-muted d-block mb-1">

                                        Última actualización

                                    </small>

                                    <div id="va_updated_at">

                                        —

                                    </div>

                                    <hr>

                                    <div class="mt-3">

                                        <small class="text-muted d-block mb-2">

                                            Imágenes del Artículo

                                        </small>

                                        <div id="va_images_container" class="row">

                                            <div class="col-12 text-center text-muted">

                                                Sin imágenes

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- RIGHT -->
                    <div class="col-md-9">

                        <!-- DATOS -->
                        <!-- DATOS DEL ARTÍCULO -->
                        <div class="card border-0 shadow-sm article-detail-card">

                            <div class="card-header border-0 bg-white py-3">

                                <h6 class="mb-0 font-weight-bold">

                                    <i class="fas fa-info-circle text-primary mr-2"></i>

                                    Datos del Artículo

                                </h6>

                            </div>

                            <div class="card-body p-3">

                                <div class="row">

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Código
                                        </label>

                                        <div class="detail-value" id="va_code_detail">
                                            —
                                        </div>

                                    </div>
                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Tipo de Código
                                        </label>

                                        <div class="detail-value" id="va_code_type">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Código Institucional
                                        </label>

                                        <div class="detail-value" id="va_institutional_code">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Categoría
                                        </label>

                                        <div class="detail-value" id="va_category">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Nombre Legal
                                        </label>

                                        <div class="detail-value" id="va_legal_name_detail">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Subcategoría
                                        </label>

                                        <div class="detail-value" id="va_subcategory">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Nombre Comercial
                                        </label>

                                        <div class="detail-value" id="va_commercial_name">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Marca
                                        </label>

                                        <div class="detail-value" id="va_brand">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Nombre Facturación
                                        </label>

                                        <div class="detail-value" id="va_billing_name">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Presentación
                                        </label>

                                        <div class="detail-value" id="va_presentation">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Unidad
                                        </label>

                                        <div class="detail-value" id="va_unit">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-6 mb-2">

                                        <label class="detail-label">
                                            Stock Mínimo
                                        </label>

                                        <div class="detail-value" id="va_minimum_stock">
                                            —
                                        </div>

                                    </div>

                                </div>

                                <hr>

                                <div class="row">

                                    <div class="col-md-4 mb-2">

                                        <label class="detail-label">
                                            Afecto IGV
                                        </label>

                                        <div class="detail-badge" id="va_is_taxable">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-4 mb-2">

                                        <label class="detail-label">
                                            Maneja Lote
                                        </label>

                                        <div class="detail-badge" id="va_has_batch">
                                            —
                                        </div>

                                    </div>

                                    <div class="col-md-4 mb-2">

                                        <label class="detail-label">
                                            Maneja Vencimiento
                                        </label>

                                        <div class="detail-badge" id="va_has_expiration">
                                            —
                                        </div>

                                    </div>

                                </div>

                                <div class="mt-2">

                                    <label class="detail-label">

                                        Observación

                                    </label>

                                    <div class="detail-observation" id="va_observation">

                                        —

                                    </div>

                                </div>

                            </div>

                        </div>

                        <!-- DOCUMENTOS -->
                        <!-- DOCUMENTOS -->
                        <div class="card border-0 shadow-sm mt-3">

                            <div class="card-header border-0 py-2 px-3"
                                style="
            background:linear-gradient(
                135deg,
                #f8fafc,
                #eef2f7
            );
        ">

                                <h6 class="mb-0 font-weight-bold text-dark">

                                    <i class="fas fa-file-pdf text-danger mr-2"></i>

                                    Documentación Adjunta

                                </h6>

                            </div>

                            <div class="card-body p-3">

                                <div class="table-responsive document-scroll">

                                    <table class="table table-hover align-middle mb-0"
                                        style="
            font-size:13px;
            min-width:950px;
        ">

                                        <thead>

                                            <tr
                                                style="
                            background:#f8fafc;
                            border-bottom:2px solid #e5e7eb;
                        ">

                                                <th width="50">
                                                    #
                                                </th>

                                                <th width="180">
                                                    Tipo
                                                </th>

                                                <th>
                                                    Documento
                                                </th>

                                                <th width="120">
                                                    Emisión
                                                </th>

                                                <th width="120">
                                                    Vencimiento
                                                </th>

                                                <th width="140" class="text-center">

                                                    Acciones

                                                </th>

                                            </tr>

                                        </thead>

                                        <tbody id="va_documents_body">

                                            <tr>

                                                <td colspan="6" class="text-center py-4 text-muted">

                                                    <i class="fas fa-folder-open fa-2x mb-2 d-block text-secondary">
                                                    </i>

                                                    No existen documentos registrados

                                                </td>

                                            </tr>

                                        </tbody>

                                    </table>

                                </div>

                            </div>

                        </div>
                        <!-- FOOT -->
                        <div class="row mt-3">

                            <div class="col-md-4">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block">

                                            ID

                                        </small>

                                        <div id="va_id">

                                            —

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block">

                                            Actualizado por

                                        </small>

                                        <div id="va_updated_by">

                                            —

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="col-md-4">

                                <div class="card border-0 bg-light shadow-sm">

                                    <div class="card-body py-2 px-3">

                                        <small class="text-muted d-block">

                                            Fecha Registro

                                        </small>

                                        <div id="va_created_at">

                                            —

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>
    ```

</div>

//MODAL PARA VER LA IMAGEN EN GRANDE
<div class="modal fade" id="imagePreviewModal" tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-centered">

        <div class="modal-content bg-dark border-0">

            <div class="modal-header border-0">

                <button type="button" class="close text-white" data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <div class="modal-body text-center p-2">

                <img id="previewLargeImage" src="" class="img-fluid rounded">

            </div>

        </div>

    </div>

</div>
