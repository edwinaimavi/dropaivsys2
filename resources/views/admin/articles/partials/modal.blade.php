<style>
    #articleModal .article-modal-dialog {
        max-width: 1460px;
        width: calc(100% - 32px);
        margin: 1rem auto;
    }

    #articleModal .article-modal-shell {
        border: 0;
        border-radius: 18px;
        overflow: hidden;
        background: #f5f7fb;
        box-shadow: 0 22px 60px rgba(15, 23, 42, .22);
    }

    #articleModal .article-modal-header {
        padding: 16px 20px;
        background: #ffffff;
        border-bottom: 1px solid #e6ebf2;
    }

    #articleModal .article-header-icon {
        width: 44px;
        height: 44px;
        border-radius: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #0d6efd;
        background: linear-gradient(145deg, #eaf3ff, #d9eaff);
        box-shadow: inset 0 0 0 1px rgba(13, 110, 253, .08);
        font-size: 18px;
    }

    #articleModal .article-modal-title {
        font-size: 1.13rem;
        color: #152238;
        letter-spacing: -.01em;
    }

    #articleModal .article-modal-subtitle {
        color: #718096;
        font-size: .78rem;
    }

    #articleModal .article-close {
        width: 36px;
        height: 36px;
        border: 1px solid #e5eaf0;
        border-radius: 50%;
        color: #64748b;
        background: #fff;
        opacity: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: .18s ease;
    }

    #articleModal .article-close:hover {
        color: #1e293b;
        border-color: #cfd7e3;
        background: #f8fafc;
        transform: translateY(-1px);
    }

    #articleModal .article-modal-body {
        padding: 14px;
        background: #f5f7fb;
        max-height: calc(100vh - 126px);
        overflow-y: auto;
    }

    #articleModal .article-sidebar {
        position: sticky;
        top: 0;
    }

    #articleModal .article-side-card,
    #articleModal .article-section-card {
        border: 1px solid #e6ebf2;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 4px 16px rgba(15, 23, 42, .045);
    }

    #articleModal .article-side-card {
        padding: 18px;
    }

    #articleModal .article-avatar {
        width: 76px;
        height: 76px;
        border-radius: 22px;
        margin: 0 auto 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(145deg, #1976ff, #0b5ed7);
        color: #fff;
        font-size: 30px;
        box-shadow: 0 12px 28px rgba(13, 110, 253, .22);
    }

    #articleModal .article-side-title {
        font-weight: 700;
        color: #172033;
        margin-bottom: 2px;
    }

    #articleModal .article-side-subtitle {
        color: #7b8798;
        font-size: .76rem;
    }

    #articleModal .article-meta-list {
        border-top: 1px solid #edf0f4;
        border-bottom: 1px solid #edf0f4;
        padding: 12px 0;
        margin: 15px 0;
    }

    #articleModal .article-meta-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 11px;
    }

    #articleModal .article-meta-item:last-child {
        margin-bottom: 0;
    }

    #articleModal .article-meta-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 9px;
        background: #f2f6fb;
        color: #5a6f8f;
        font-size: 12px;
        flex: 0 0 auto;
    }

    #articleModal .article-meta-label {
        color: #8994a4;
        font-size: .68rem;
        line-height: 1.1;
    }

    #articleModal .article-meta-value {
        color: #243247;
        font-size: .79rem;
        font-weight: 600;
        margin-top: 2px;
    }

    #articleModal .article-image-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 9px;
    }

    #articleModal .article-image-title {
        font-size: .76rem;
        font-weight: 700;
        color: #4b5c73;
    }

    #articleModal #btnAddImage {
        width: 31px;
        height: 31px;
        border-radius: 9px;
        padding: 0;
    }

    #articleModal #articleImagesContainer {
        min-height: 205px;
        max-height: 285px;
        overflow-y: auto;
        border: 1.5px dashed #cfd9e8 !important;
        border-radius: 12px !important;
        background: #fbfcfe !important;
    }

    #articleModal .article-section-card {
        overflow: visible;
        margin-bottom: 12px;
    }

    #articleModal .article-section-head {
        min-height: 44px;
        padding: 10px 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-radius: 13px 13px 0 0;
        border-bottom: 1px solid transparent;
    }

    #articleModal .article-section-head-left {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    #articleModal .article-section-icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 9px;
        font-size: 12px;
    }

    #articleModal .article-section-title {
        font-size: .83rem;
        font-weight: 700;
        color: #1f2c3f;
        margin: 0;
    }

    #articleModal .article-section-hint {
        color: #7c899a;
        font-size: .68rem;
        margin-left: 12px;
        white-space: nowrap;
    }

    #articleModal .article-section-body {
        padding: 14px;
    }

    #articleModal .tone-blue {
        background: linear-gradient(90deg, #edf5ff, #f5f9ff);
        border-bottom-color: #d9e9ff;
    }

    #articleModal .tone-blue .article-section-icon {
        color: #0d6efd;
        background: #dcecff;
    }

    #articleModal .tone-cyan {
        background: linear-gradient(90deg, #ecfbfb, #f6fdfd);
        border-bottom-color: #d8f2f1;
    }

    #articleModal .tone-cyan .article-section-icon {
        color: #0f8f92;
        background: #d9f4f3;
    }

    #articleModal .tone-slate {
        background: linear-gradient(90deg, #f1f4f8, #f8fafc);
        border-bottom-color: #e2e8f0;
    }

    #articleModal .tone-slate .article-section-icon {
        color: #53657d;
        background: #e5ebf2;
    }

    #articleModal .tone-green {
        background: linear-gradient(90deg, #edf9f4, #f8fcfa);
        border-bottom-color: #d9efe5;
    }

    #articleModal .tone-green .article-section-icon {
        color: #15805e;
        background: #dcf3e9;
    }

    #articleModal .article-section-body label {
        font-size: .69rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .02em;
        color: #445269;
        margin-bottom: 5px;
    }

    #articleModal .article-section-body .form-control {
        min-height: 36px;
        border-radius: 8px;
        border-color: #d9e1eb;
        color: #2b3748;
        background-color: #fff;
        box-shadow: none;
    }

    #articleModal .article-section-body .form-control:focus {
        border-color: #8fbcff;
        box-shadow: 0 0 0 3px rgba(13, 110, 253, .08);
    }

    #articleModal .article-section-body textarea.form-control {
        min-height: 70px;
        resize: vertical;
    }

    #articleModal .form-text {
        font-size: .66rem;
        line-height: 1.35;
    }

    #articleModal .article-warehouse-box {
        border: 1px solid #e7ebf0;
        border-radius: 11px;
        padding: 11px 12px;
        height: 100%;
        background: #fbfcfe;
    }

    #articleModal .article-warehouse-box .warehouse-title {
        display: flex;
        align-items: center;
        color: #2f4057;
        font-size: .75rem;
        font-weight: 700;
        margin-bottom: 7px;
    }

    #articleModal .article-warehouse-box .warehouse-title i {
        color: #66788f;
        margin-right: 7px;
    }

    #articleModal .article-sunat-advanced {
        border: 1px solid #dce5f1;
        background: #fbfcff;
    }

    #articleModal .article-sunat-toggle {
        width: 100%;
        padding: 11px 13px;
        border: 0;
        background: transparent;
        color: #334155;
        display: flex;
        align-items: center;
        justify-content: space-between;
        text-decoration: none !important;
    }

    #articleModal .article-sunat-toggle:hover {
        background: #f7f9fc;
        color: #1f2a3d;
    }

    #articleModal .article-sunat-toggle-main {
        display: flex;
        align-items: center;
        min-width: 0;
    }

    #articleModal .article-sunat-toggle-icon {
        width: 29px;
        height: 29px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 9px;
        color: #5577a6;
        background: #eaf0f8;
    }

    #articleModal .article-sunat-toggle-title {
        font-size: .78rem;
        font-weight: 700;
    }

    #articleModal .article-sunat-toggle-subtitle {
        font-size: .65rem;
        color: #8793a3;
        margin-left: 6px;
    }

    #articleModal .article-sunat-box {
        border: 1px solid #e5eaf2;
        border-radius: 11px;
        padding: 12px;
        background: #fff;
    }

    #articleModal .article-sunat-box + .article-sunat-box {
        margin-top: 10px;
    }

    #articleModal .article-sunat-box-title {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 10px;
        color: #2d3c52;
        font-size: .76rem;
        font-weight: 700;
    }

    #articleModal .article-documents-scroll {
        max-height: 230px;
        overflow: auto;
        border: 1px solid #e8edf3;
        border-radius: 10px;
    }

    #articleModal .article-documents-scroll table {
        margin-bottom: 0;
    }

    #articleModal .article-documents-scroll thead th {
        border-top: 0;
        background: #f8fafc;
        color: #6b7789;
        font-size: .66rem;
        text-transform: uppercase;
        letter-spacing: .035em;
        vertical-align: middle;
        white-space: nowrap;
    }

    #articleModal .article-documents-scroll tbody td {
        vertical-align: middle;
        font-size: .75rem;
    }

    #articleModal .article-footer {
        position: sticky;
        bottom: -14px;
        z-index: 8;
        margin: 14px -14px -14px;
        padding: 12px 16px;
        background: rgba(255, 255, 255, .97);
        border-top: 1px solid #e5eaf1;
        backdrop-filter: blur(8px);
    }

    #articleModal .article-footer .btn {
        min-width: 112px;
        border-radius: 9px;
        font-weight: 600;
        padding: .48rem .9rem;
    }

    #articleModal .article-footer .btn-primary {
        background: #0f8f80;
        border-color: #0f8f80;
    }

    #articleModal .article-footer .btn-primary:hover {
        background: #0c786c;
        border-color: #0c786c;
    }

    #articleModal .badge-soft-success {
        color: #177259;
        background: #def5ea;
        border-radius: 20px;
        padding: .28rem .55rem;
        font-size: .65rem;
    }

    @media (max-width: 1199.98px) {
        #articleModal .article-modal-dialog {
            max-width: 98vw;
            width: 98vw;
        }

        #articleModal .article-sidebar {
            position: static;
            margin-bottom: 12px;
        }
    }

    @media (max-width: 767.98px) {
        #articleModal .article-modal-dialog {
            width: calc(100% - 12px);
            margin: 6px auto;
        }

        #articleModal .article-modal-shell {
            border-radius: 12px;
        }

        #articleModal .article-modal-body {
            padding: 9px;
            max-height: calc(100vh - 92px);
        }

        #articleModal .article-section-hint,
        #articleModal .article-sunat-toggle-subtitle {
            display: none;
        }

        #articleModal .article-footer {
            margin: 10px -9px -9px;
        }
    }
</style>

<!-- MODAL ARTICLE -->
<div class="modal fade" id="articleModal" tabindex="-1" role="dialog" aria-labelledby="articleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered article-modal-dialog" role="document">
        <div class="modal-content article-modal-shell">
            <div class="modal-header article-modal-header align-items-center">
                <div class="d-flex align-items-center">
                    <div class="article-header-icon mr-3">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div>
                        <h5 class="modal-title article-modal-title mb-0 font-weight-bold" id="articleModalLabel">Nuevo Artículo</h5>
                        <div class="article-modal-subtitle">Registro y administración del maestro de artículos</div>
                    </div>
                </div>
                <button type="button" class="close article-close" data-dismiss="modal" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="modal-body article-modal-body">
                <form id="articleForm" autocomplete="off">
                    @csrf
                    <input type="hidden" id="article_id">
                    <input type="hidden" id="sunat_inventory_catalog_use_internal_code" value="0">

                    <div class="row">
                        <div class="col-xl-2 col-lg-3">
                            <aside class="article-sidebar">
                                <div class="article-side-card text-center">
                                    <div class="article-avatar"><i class="fas fa-box"></i></div>
                                    <div class="article-side-title">Artículo</div>
                                    <div class="article-side-subtitle">Producto, servicio e inventario</div>

                                    <div class="article-meta-list text-left">
                                        <div class="article-meta-item">
                                            <span class="article-meta-icon"><i class="far fa-calendar-alt"></i></span>
                                            <div>
                                                <div class="article-meta-label">Fecha de registro</div>
                                                <div class="article-meta-value">{{ now()->format('d/m/Y') }}</div>
                                            </div>
                                        </div>
                                        <div class="article-meta-item">
                                            <span class="article-meta-icon"><i class="fas fa-circle"></i></span>
                                            <div>
                                                <div class="article-meta-label">Estado inicial</div>
                                                <div class="article-meta-value"><span class="badge-soft-success">ACTIVO</span></div>
                                            </div>
                                        </div>
                                        <div class="article-meta-item">
                                            <span class="article-meta-icon"><i class="fas fa-layer-group"></i></span>
                                            <div>
                                                <div class="article-meta-label">Módulo</div>
                                                <div class="article-meta-value">Inventario</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="article-image-heading">
                                        <span class="article-image-title">Imágenes del artículo</span>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnAddImage" title="Agregar imagen">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                        <input type="file" id="articleImageInput" accept="image/*" multiple style="display:none;">
                                    </div>

                                    <div id="articleImagesContainer" class="p-2">
                                        <div class="text-center text-muted py-5">
                                            <i class="far fa-images fa-2x mb-2"></i>
                                            <div class="font-weight-bold" style="font-size:.76rem;">Sin imágenes</div>
                                            <small>Agregue fotografías del artículo.</small>
                                        </div>
                                    </div>
                                </div>
                            </aside>
                        </div>

                        <div class="col-xl-10 col-lg-9">
                            <section class="article-section-card">
                                <div class="article-section-head tone-blue">
                                    <div class="article-section-head-left">
                                        <span class="article-section-icon"><i class="fas fa-layer-group"></i></span>
                                        <h6 class="article-section-title">Clasificación</h6>
                                    </div>
                                    <span class="article-section-hint">Datos base para organizar e identificar el artículo</span>
                                </div>
                                <div class="article-section-body">
                                    <div class="form-row">
                                        <div class="form-group col-xl-2 col-md-4">
                                            <label>Código</label>
                                            <input type="hidden" id="code_mode" name="code_mode" value="automatic">
                                            <input type="text" id="code" name="code" readonly class="form-control form-control-sm">
                                            <small class="form-text text-muted">Se confirma automáticamente al guardar.</small>
                                        </div>
                                        <div class="form-group col-xl-2 col-md-4">
                                            <label>Tipo código</label>
                                            <select id="code_type" name="code_type" class="form-control form-control-sm">
                                                <option value="">Seleccione</option>
                                                <option value="SIGA/SISMED">SIGA / SISMED</option>
                                                <option value="SAP/IETSI">SAP / IETSI</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-xl-2 col-md-4">
                                            <label>Código institucional</label>
                                            <input type="text" id="institutional_code" name="institutional_code" class="form-control form-control-sm" maxlength="100">
                                            <span class="invalid-feedback" id="institutional_code-error"></span>
                                        </div>
                                        <div class="form-group col-xl-3 col-md-6">
                                            <label>Categoría</label>
                                            <select id="category_id" name="category_id" class="form-control form-control-sm">
                                                <option value="">Seleccione</option>
                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->description }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-xl-3 col-md-6">
                                            <label>Subcategoría</label>
                                            <select id="subcategory_id" name="subcategory_id" class="form-control form-control-sm">
                                                <option value="">Seleccione una categoría</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6 mb-0">
                                            <label>Presentación</label>
                                            <select id="presentation_id" name="presentation_id" class="form-control form-control-sm">
                                                <option value="">Seleccione</option>
                                                @foreach ($presentations as $presentation)
                                                    <option value="{{ $presentation->id }}">{{ $presentation->description }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-6 mb-0">
                                            <label>Unidad</label>
                                            <select id="unit_id" name="unit_id" class="form-control form-control-sm">
                                                <option value="">Seleccione</option>
                                                @foreach ($units as $unit)
                                                    <option value="{{ $unit->id }}">{{ $unit->description }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="article-section-card">
                                <div class="article-section-head tone-cyan">
                                    <div class="article-section-head-left">
                                        <span class="article-section-icon"><i class="fas fa-tag"></i></span>
                                        <h6 class="article-section-title">Datos del artículo</h6>
                                    </div>
                                    <span class="article-section-hint">Nombres usados en registros, búsquedas y documentos</span>
                                </div>
                                <div class="article-section-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-4 mb-0">
                                            <label>Nombre legal</label>
                                            <input type="text" class="form-control form-control-sm" id="legal_name">
                                            <span class="invalid-feedback" id="legal_name-error"></span>
                                        </div>
                                        <div class="form-group col-md-4 mb-0">
                                            <label>Nombre comercial</label>
                                            <input type="text" class="form-control form-control-sm" id="commercial_name">
                                            <span class="invalid-feedback" id="commercial_name-error"></span>
                                        </div>
                                        <div class="form-group col-md-4 mb-0">
                                            <label>Nombre facturación</label>
                                            <input type="text" class="form-control form-control-sm" id="billing_name">
                                            <span class="invalid-feedback" id="billing_name-error"></span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="article-section-card">
                                <div class="article-section-head tone-slate">
                                    <div class="article-section-head-left">
                                        <span class="article-section-icon"><i class="fas fa-sliders-h"></i></span>
                                        <h6 class="article-section-title">Clasificación operativa</h6>
                                    </div>
                                    <span class="article-section-hint">Define si es producto/servicio y si participa en inventario</span>
                                </div>
                                <div class="article-section-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label for="item_kind">Tipo de ítem <span class="text-danger">*</span></label>
                                            <select id="item_kind" name="item_kind" class="form-control form-control-sm" required>
                                                <option value="">Pendiente de clasificar</option>
                                                <option value="product">PRODUCTO</option>
                                                <option value="service">SERVICIO</option>
                                            </select>
                                            <span class="invalid-feedback" id="item_kind-error"></span>
                                        </div>
                                        <div class="form-group col-md-6">
                                            <label for="is_inventory_item">Participa en inventario <span class="text-danger">*</span></label>
                                            <select id="is_inventory_item" name="is_inventory_item" class="form-control form-control-sm" required>
                                                <option value="">Seleccione</option>
                                                <option value="1">SÍ, PRODUCTO INVENTARIABLE</option>
                                                <option value="0">NO INVENTARIABLE</option>
                                            </select>
                                            <span class="invalid-feedback" id="is_inventory_item-error"></span>
                                            <small id="inventoryClassificationHelp" class="form-text text-muted">Los servicios nunca generan stock ni Kardex.</small>
                                        </div>
                                        <div class="form-group col-12 mb-0 d-none" id="sunatExistenceTypeGroup">
                                            <label for="sunat_existence_type_item_id">Tipo de existencia SUNAT <span class="text-danger">*</span></label>
                                            <select id="sunat_existence_type_item_id" name="sunat_existence_type_item_id" class="form-control form-control-sm" disabled>
                                                <option value="">Seleccione</option>
                                                @foreach ($sunatExistenceTypes as $sunatExistenceType)
                                                    <option value="{{ $sunatExistenceType->id }}">
                                                        {{ $sunatExistenceType->item_code }} — {{ $sunatExistenceType->description }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="invalid-feedback" id="sunat_existence_type_item_id-error"></span>
                                            <small class="form-text text-muted">Clasificación SUNAT 05 aplicable al producto inventariable.</small>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="article-section-card">
                                <div class="article-section-head tone-green">
                                    <div class="article-section-head-left">
                                        <span class="article-section-icon"><i class="fas fa-warehouse"></i></span>
                                        <h6 class="article-section-title">Control de almacén</h6>
                                    </div>
                                    <span class="article-section-hint">Indica qué controles exige el producto al ingresar al almacén</span>
                                </div>
                                <div class="article-section-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-2 mb-md-0">
                                            <div class="article-warehouse-box">
                                                <div class="warehouse-title"><i class="fas fa-barcode"></i>Control por lote</div>
                                                <select id="has_batch" name="has_batch" class="form-control form-control-sm">
                                                    <option value="1">SÍ, REQUIERE LOTE</option>
                                                    <option value="0">NO REQUIERE LOTE</option>
                                                </select>
                                                <small class="form-text text-muted">El número de lote se registra en cada ingreso de almacén.</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="article-warehouse-box">
                                                <div class="warehouse-title"><i class="far fa-calendar-check"></i>Control de vencimiento</div>
                                                <select id="has_expiration" name="has_expiration" class="form-control form-control-sm">
                                                    <option value="1">SÍ, REQUIERE VENCIMIENTO</option>
                                                    <option value="0">NO REQUIERE VENCIMIENTO</option>
                                                </select>
                                                <small class="form-text text-muted">La fecha concreta se registra por lote/ingreso, no en el maestro.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="article-section-card article-sunat-advanced d-none" id="sunatInventoryIdentificationGroup">
                                <button class="article-sunat-toggle collapsed" type="button" data-toggle="collapse"
                                    data-target="#articleSunatAdvancedCollapse" aria-expanded="false" aria-controls="articleSunatAdvancedCollapse">
                                    <span class="article-sunat-toggle-main">
                                        <span class="article-sunat-toggle-icon"><i class="fas fa-fingerprint"></i></span>
                                        <span>
                                            <span class="article-sunat-toggle-title">Configuración SUNAT avanzada</span>
                                            <span class="article-sunat-toggle-subtitle">Identificación de existencia y código internacional opcional</span>
                                        </span>
                                    </span>
                                    <i class="fas fa-chevron-down text-muted"></i>
                                </button>
                                <div id="articleSunatAdvancedCollapse" class="collapse">
                                    <div class="article-section-body pt-1">
                                        <div class="article-sunat-box">
                                            <div class="article-sunat-box-title">
                                                <span><i class="fas fa-id-card-alt mr-2 text-primary"></i>Identificación SUNAT de existencia</span>
                                                <span class="badge badge-light border">Kardex / PLE</span>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-lg-5 mb-2">
                                                    <label for="sunat_inventory_catalog_item_id">Catálogo principal <span class="text-danger">*</span></label>
                                                    <select id="sunat_inventory_catalog_item_id" name="sunat_inventory_catalog_item_id" class="form-control form-control-sm" disabled>
                                                        <option value="">Seleccione</option>
                                                        @foreach ($sunatInventoryCatalogItems as $item)
                                                            <option value="{{ $item->id }}" data-item-code="{{ $item->item_code }}">
                                                                {{ $item->item_code }} — {{ $item->description }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <span class="invalid-feedback" id="sunat_inventory_catalog_item_id-error"></span>
                                                </div>
                                                <div class="form-group col-lg-5 mb-2">
                                                    <label for="sunat_inventory_catalog_code">Código de existencia <span class="text-danger">*</span></label>
                                                    <input id="sunat_inventory_catalog_code" name="sunat_inventory_catalog_code" type="text" maxlength="24"
                                                        class="form-control form-control-sm text-uppercase" disabled>
                                                    <span class="invalid-feedback" id="sunat_inventory_catalog_code-error"></span>
                                                    <small id="sunatInventoryOwnCodeHelp" class="form-text text-muted d-none">Puede utilizar el código interno propio del artículo.</small>
                                                </div>
                                                <div class="form-group col-lg-2 mb-2 d-flex align-items-end">
                                                    <button type="button" id="useInternalArticleCode" class="btn btn-outline-primary btn-sm btn-block" disabled>
                                                        <i class="fas fa-copy mr-1"></i>Usar código
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="article-sunat-box">
                                            <div class="article-sunat-box-title">
                                                <span><i class="fas fa-globe mr-2 text-info"></i>Código internacional</span>
                                                <span class="badge badge-secondary">OPCIONAL</span>
                                            </div>
                                            <small class="form-text text-muted mb-2">Registre únicamente un UNSPSC o GTIN/EAN real. Si no lo tiene, déjelo vacío.</small>
                                            <div class="form-row">
                                                <div class="form-group col-md-6 mb-0">
                                                    <label for="sunat_standard_catalog_item_id">Catálogo internacional</label>
                                                    <select id="sunat_standard_catalog_item_id" name="sunat_standard_catalog_item_id" class="form-control form-control-sm" disabled>
                                                        <option value="">No configurado</option>
                                                        @foreach ($sunatStandardCatalogItems as $item)
                                                            <option value="{{ $item->id }}">{{ $item->item_code }} — {{ $item->description }}</option>
                                                        @endforeach
                                                    </select>
                                                    <span class="invalid-feedback" id="sunat_standard_catalog_item_id-error"></span>
                                                </div>
                                                <div class="form-group col-md-6 mb-0">
                                                    <label for="sunat_standard_code">Código UNSPSC / GTIN</label>
                                                    <input id="sunat_standard_code" name="sunat_standard_code" type="text" maxlength="128"
                                                        class="form-control form-control-sm text-uppercase" disabled>
                                                    <span class="invalid-feedback" id="sunat_standard_code-error"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="article-section-card">
                                <div class="article-section-head tone-slate">
                                    <div class="article-section-head-left">
                                        <span class="article-section-icon"><i class="fas fa-cog"></i></span>
                                        <h6 class="article-section-title">Configuración</h6>
                                    </div>
                                    <span class="article-section-hint">Estado general y observaciones del artículo</span>
                                </div>
                                <div class="article-section-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-3 mb-0">
                                            <label>Estado</label>
                                            <select id="status" name="status" class="form-control form-control-sm">
                                                <option value="ACTIVE">ACTIVO</option>
                                                <option value="INACTIVE">INACTIVO</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-9 mb-0">
                                            <label>Observación</label>
                                            <textarea id="observation" name="observation" rows="2" class="form-control form-control-sm"
                                                placeholder="Agregue una observación si es necesario..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="article-section-card mb-0">
                                <div class="article-section-head tone-green">
                                    <div class="article-section-head-left">
                                        <span class="article-section-icon"><i class="far fa-file-alt"></i></span>
                                        <h6 class="article-section-title">Documentación</h6>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnAddDocument">
                                        <i class="fas fa-plus-circle mr-1"></i>Agregar documento
                                    </button>
                                </div>
                                <div class="article-section-body p-2">
                                    <div class="table-responsive article-documents-scroll">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Tipo documento</th>
                                                    <th>Marca</th>
                                                    <th>Archivo</th>
                                                    <th>Emisión</th>
                                                    <th>Vencimiento</th>
                                                    <th width="100" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="documentsTableBody">
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-3">No hay documentos agregados</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="article-footer d-flex justify-content-end align-items-center">
                        <button type="button" class="btn btn-light border mr-2" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Cerrar
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSaveArticle">
                            <i class="fas fa-save mr-1"></i>Guardar artículo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
