<!-- MODAL ARTICLE -->
<div class="modal fade" id="articleModal" tabindex="-1" role="dialog" aria-labelledby="articleModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">

        <div class="modal-content shadow-lg border-0 rounded-lg overflow-hidden">

            {{-- HEADER --}}
            <div class="modal-header align-items-center"
                style="
                    background:linear-gradient(
                        90deg,
                        #f8f9fa,
                        #e9ecef
                    );
                    border-bottom:1px solid #ced4da;
                ">

                <div class="d-flex align-items-center">

                    <div class="mr-3"
                        style="
                            width:42px;
                            height:42px;
                            border-radius:10px;
                            background:#cfe2ff;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                        ">

                        <i class="fas fa-box text-primary"></i>

                    </div>

                    <div>

                        <h5 class="modal-title mb-0 font-weight-bold" id="articleModalLabel">

                            Nuevo Artículo

                        </h5>

                        <small class="text-muted">

                            Registro y administración de artículos

                        </small>

                    </div>

                </div>

                <button type="button" class="close" data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            {{-- BODY --}}
            <div class="modal-body p-2" style="background:#fafafa;">

                <form id="articleForm" autocomplete="off">

                    @csrf

                    <input type="hidden" id="article_id">

                    <div class="row">

                        {{-- PANEL IZQUIERDO --}}
                        <div class="col-lg-3">

                            <div class="card border-0 shadow-sm h-100">

                                <div class="card-body text-center">

                                    <div class="mb-3">

                                        <div class="rounded-circle d-inline-flex align-items-center justify-content-center"
                                            style="
                                                width:90px;
                                                height:90px;
                                                background:
                                                linear-gradient(
                                                    135deg,
                                                    #0d6efd,
                                                    #0b5ed7
                                                );
                                                color:white;
                                                font-size:35px;
                                            ">

                                            <i class="fas fa-box-open"></i>

                                        </div>

                                    </div>

                                    <h5 class="font-weight-bold">

                                        Artículos

                                    </h5>

                                    <small class="text-muted">

                                        Gestión de productos e inventario

                                    </small>

                                    <hr>

                                    <div class="text-left small">

                                        <small class="text-muted">

                                            Fecha de registro

                                        </small>

                                        <div class="font-weight-bold mb-2">

                                            {{ now()->format('d/m/Y') }}

                                        </div>

                                        <small class="text-muted">

                                            Estado inicial

                                        </small>

                                        <div class="mt-1">

                                            <span class="badge badge-success">

                                                ACTIVO

                                            </span>

                                        </div>

                                        <small class="text-muted d-block mt-3">

                                            Módulo

                                        </small>

                                        <div>

                                            Inventario

                                        </div>

                                        <small class="text-muted d-block mt-3">

                                            Función

                                        </small>
                                        <div>
                                            Gestión de artículos
                                        </div>

                                        {{-- GALERÍA DE IMÁGENES --}}
                                        <hr>

                                        <div class="mt-3">

                                            <div class="d-flex justify-content-between align-items-center mb-2">

                                                <small class="text-muted font-weight-bold">

                                                    Imágenes del Artículo

                                                </small>

                                                <button type="button" class="btn btn-sm btn-primary" id="btnAddImage">

                                                    <i class="fas fa-plus"></i>

                                                </button>

                                                <input type="file" id="articleImageInput" accept="image/*" multiple
                                                    style="display:none;">

                                            </div>

                                            <div id="articleImagesContainer" class="border rounded bg-light p-2"
                                                style="
            min-height:250px;
            max-height:350px;
            overflow-y:auto;
        ">

                                                <div class="text-center text-muted py-5">

                                                    <i class="fas fa-images fa-3x mb-2"></i>

                                                    <div>

                                                        No hay imágenes agregadas

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>



                        </div>

                        {{-- PANEL DERECHO --}}
                        <div class="col-lg-9">

                            {{-- CLASIFICACION --}}
                            <div class="card border-0 shadow-sm mb-2">

                                <div class="section-title section-primary">

                                    <i class="fas fa-layer-group mr-2"></i>

                                    Clasificación

                                </div>

                                <div class="card-body">

                                    <div class="form-row">

                                        <div class="form-group col-md-2">

                                            <label>Código</label>

                                            <input type="hidden" id="code_mode" name="code_mode" value="automatic">

                                            <input type="text" id="code" name="code" readonly
                                                class="form-control form-control-sm">

                                            <small class="form-text text-muted">
                                                El código se confirmará automáticamente al guardar.
                                            </small>

                                        </div>

                                        <div class="form-group col-md-2">

                                            <label>Tipo Código</label>

                                            <select id="code_type" name="code_type"
                                                class="form-control form-control-sm">

                                                <option value="">
                                                    Seleccione
                                                </option>

                                                <option value="SIGA/SISMED">
                                                    SIGA / SISMED
                                                </option>

                                                <option value="SAP/IETSI">
                                                    SAP / IETSI
                                                </option>

                                            </select>

                                        </div>

                                        <div class="form-group col-md-2">

                                            <label>Código Institucional</label>

                                            <input type="text" id="institutional_code" name="institutional_code"
                                                class="form-control form-control-sm" maxlength="100">
                                            <span class="invalid-feedback" id="institutional_code-error"></span>

                                        </div>

                                        <div class="form-group col-md-3">

                                            <label>Categoría</label>

                                            <select id="category_id" name="category_id"
                                                class="form-control form-control-sm">

                                                <option value="">
                                                    Seleccione
                                                </option>

                                                @foreach ($categories as $category)
                                                    <option value="{{ $category->id }}">
                                                        {{ $category->description }}
                                                    </option>
                                                @endforeach

                                            </select>

                                        </div>

                                        <div class="form-group col-md-3">

                                            <label>Subcategoría</label>

                                            <select id="subcategory_id" name="subcategory_id"
                                                class="form-control form-control-sm">

                                                <option value="">
                                                    Seleccione una categoría
                                                </option>

                                            </select>

                                        </div>



                                    </div>
                                    <div class="form-row">

                                        <div class="form-group col-md-6">

                                            <label>Presentación</label>

                                            <select id="presentation_id" name="presentation_id"
                                                class="form-control form-control-sm">

                                                <option value="">
                                                    Seleccione
                                                </option>

                                                @foreach ($presentations as $presentation)
                                                    <option value="{{ $presentation->id }}">

                                                        {{ $presentation->description }}

                                                    </option>
                                                @endforeach

                                            </select>

                                        </div>

                                        <div class="form-group col-md-6">

                                            <label>Unidad</label>

                                            <select id="unit_id" name="unit_id"
                                                class="form-control form-control-sm">

                                                <option value="">
                                                    Seleccione
                                                </option>

                                                @foreach ($units as $unit)
                                                    <option value="{{ $unit->id }}">

                                                        {{ $unit->description }}

                                                    </option>
                                                @endforeach

                                            </select>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            {{-- DATOS ARTICULO --}}
                            <div class="card border-0 shadow-sm mb-2">

                                <div class="section-title section-info">

                                    <i class="fas fa-box-open mr-2"></i>

                                    Datos del Artículo

                                </div>

                                <div class="card-body">
                                    <div class="form-row">

                                        <div class="col-md-4">
                                            <label>Nombre Legal</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="legal_name">
                                            <span class="invalid-feedback" id="legal_name-error"></span>
                                        </div>

                                        <div class="col-md-4">
                                            <label>Nombre Comercial</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="commercial_name">
                                            <span class="invalid-feedback" id="commercial_name-error"></span>
                                        </div>

                                        <div class="col-md-4">
                                            <label>Nombre Facturación</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="billing_name">
                                            <span class="invalid-feedback" id="billing_name-error"></span>
                                        </div>

                                    </div>
                                </div>

                            </div>

                            <div class="card border-0 shadow-sm mb-2">
                                <div class="section-title section-secondary">
                                    <i class="fas fa-tags mr-2"></i>Clasificación operativa
                                </div>
                                <div class="card-body py-3">
                                    <div class="form-row">
                                        <div class="form-group col-md-6 mb-1">
                                            <label for="item_kind">Tipo de ítem <span class="text-danger">*</span></label>
                                            <select id="item_kind" name="item_kind" class="form-control form-control-sm" required>
                                                <option value="">Pendiente de clasificar</option>
                                                <option value="product">PRODUCTO</option>
                                                <option value="service">SERVICIO</option>
                                            </select>
                                            <span class="invalid-feedback" id="item_kind-error"></span>
                                        </div>
                                        <div class="form-group col-md-6 mb-1">
                                            <label for="is_inventory_item">Participa en inventario <span class="text-danger">*</span></label>
                                            <select id="is_inventory_item" name="is_inventory_item" class="form-control form-control-sm" required>
                                                <option value="">Seleccione</option>
                                                <option value="1">SÍ, PRODUCTO INVENTARIABLE</option>
                                                <option value="0">NO INVENTARIABLE</option>
                                            </select>
                                            <span class="invalid-feedback" id="is_inventory_item-error"></span>
                                            <small id="inventoryClassificationHelp" class="form-text text-muted">Los servicios nunca generan stock ni Kardex.</small>
                                        </div>
                                        <div class="form-group col-12 mb-1 d-none" id="sunatExistenceTypeGroup">
                                            <label for="sunat_existence_type_item_id">
                                                Tipo de Existencia SUNAT <span class="text-danger">*</span>
                                            </label>
                                            <select id="sunat_existence_type_item_id"
                                                name="sunat_existence_type_item_id"
                                                class="form-control form-control-sm"
                                                disabled>
                                                <option value="">Seleccione</option>
                                                @foreach ($sunatExistenceTypes as $sunatExistenceType)
                                                    <option value="{{ $sunatExistenceType->id }}">
                                                        {{ $sunatExistenceType->item_code }} — {{ $sunatExistenceType->description }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="invalid-feedback" id="sunat_existence_type_item_id-error"></span>
                                            <small class="form-text text-muted">Catálogo SUNAT 05 activo.</small>
                                        </div>
                                        <input type="hidden" id="sunat_inventory_catalog_use_internal_code" value="0">
                                    </div>
                                </div>
                            </div>

                            {{-- CONTROL DE ALMACÉN --}}
                            <div class="card border-0 shadow-sm mb-2">
                                <div class="section-title section-secondary">
                                    <i class="fas fa-boxes mr-2"></i>Control de almacén
                                </div>
                                <div class="card-body py-3">
                                    <div class="form-row">
                                        <div class="form-group col-md-6 mb-2">
                                            <label>Requiere control por lote</label>
                                            <select id="has_batch" name="has_batch" class="form-control form-control-sm">
                                                <option value="1">SÍ</option>
                                                <option value="0">NO</option>
                                            </select>
                                            <small class="form-text text-muted">
                                                Aquí solo se define si el producto exige lote. El número de lote se registra al ingresar mercadería al almacén.
                                            </small>
                                        </div>

                                        <div class="form-group col-md-6 mb-2">
                                            <label>Requiere fecha de vencimiento</label>
                                            <select id="has_expiration" name="has_expiration" class="form-control form-control-sm">
                                                <option value="1">SÍ</option>
                                                <option value="0">NO</option>
                                            </select>
                                            <small class="form-text text-muted">
                                                Aquí solo se define si debe controlarse vencimiento. La fecha concreta se registra por lote/ingreso en almacén.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- CONFIGURACIÓN SUNAT AVANZADA --}}
                            <div class="card border-0 shadow-sm mb-2 d-none" id="sunatInventoryIdentificationGroup">
                                <div class="card-header bg-white py-2 px-3">
                                    <button class="btn btn-link btn-sm p-0 text-left font-weight-bold text-dark collapsed"
                                        type="button" data-toggle="collapse" data-target="#articleSunatAdvancedCollapse"
                                        aria-expanded="false" aria-controls="articleSunatAdvancedCollapse">
                                        <i class="fas fa-fingerprint mr-1 text-info"></i>
                                        Configuración SUNAT avanzada
                                        <small class="text-muted ml-1">Catálogo/código de existencia y código internacional opcional</small>
                                    </button>
                                </div>
                                <div id="articleSunatAdvancedCollapse" class="collapse">
                                    <div class="card-body py-3">
                                        <div class="border rounded p-3 bg-light">
                                            <h6 class="font-weight-bold mb-3">Identificación SUNAT de existencia</h6>
                                            <div class="form-row">
                                                <div class="form-group col-md-5 mb-2">
                                                    <label for="sunat_inventory_catalog_item_id">Catálogo principal <span class="text-danger">*</span></label>
                                                    <select id="sunat_inventory_catalog_item_id" name="sunat_inventory_catalog_item_id"
                                                        class="form-control form-control-sm" disabled>
                                                        <option value="">Seleccione</option>
                                                        @foreach ($sunatInventoryCatalogItems as $item)
                                                            <option value="{{ $item->id }}" data-item-code="{{ $item->item_code }}">
                                                                {{ $item->item_code }} — {{ $item->description }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <span class="invalid-feedback" id="sunat_inventory_catalog_item_id-error"></span>
                                                </div>
                                                <div class="form-group col-md-5 mb-2">
                                                    <label for="sunat_inventory_catalog_code">Código de existencia <span class="text-danger">*</span></label>
                                                    <input id="sunat_inventory_catalog_code" name="sunat_inventory_catalog_code"
                                                        type="text" maxlength="24" class="form-control form-control-sm text-uppercase" disabled>
                                                    <span class="invalid-feedback" id="sunat_inventory_catalog_code-error"></span>
                                                    <small id="sunatInventoryOwnCodeHelp" class="form-text text-muted d-none">
                                                        Por defecto puede utilizarse el código interno propio del artículo.
                                                    </small>
                                                </div>
                                                <div class="form-group col-md-2 mb-2 d-flex align-items-end">
                                                    <button type="button" id="useInternalArticleCode" class="btn btn-outline-primary btn-sm btn-block" disabled>
                                                        Usar código interno
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="border rounded p-3 mt-2">
                                            <h6 class="font-weight-bold mb-1">
                                                Código internacional
                                                <span class="badge badge-secondary">OPCIONAL</span>
                                            </h6>
                                            <small class="text-muted d-block mb-3">
                                                Registre únicamente un UNSPSC o GTIN/EAN real. Si no lo tiene, déjelo vacío.
                                            </small>
                                            <div class="form-row">
                                                <div class="form-group col-md-6 mb-1">
                                                    <label for="sunat_standard_catalog_item_id">Catálogo internacional</label>
                                                    <select id="sunat_standard_catalog_item_id" name="sunat_standard_catalog_item_id"
                                                        class="form-control form-control-sm" disabled>
                                                        <option value="">No configurado</option>
                                                        @foreach ($sunatStandardCatalogItems as $item)
                                                            <option value="{{ $item->id }}">{{ $item->item_code }} — {{ $item->description }}</option>
                                                        @endforeach
                                                    </select>
                                                    <span class="invalid-feedback" id="sunat_standard_catalog_item_id-error"></span>
                                                </div>
                                                <div class="form-group col-md-6 mb-1">
                                                    <label for="sunat_standard_code">Código UNSPSC / GTIN</label>
                                                    <input id="sunat_standard_code" name="sunat_standard_code" type="text" maxlength="128"
                                                        class="form-control form-control-sm text-uppercase" disabled>
                                                    <span class="invalid-feedback" id="sunat_standard_code-error"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- CONFIGURACIÓN --}}
                            <div class="card border-0 shadow-sm mb-2">
                                <div class="section-title section-secondary">
                                    <i class="fas fa-cogs mr-2"></i>Configuración
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label>Estado</label>
                                            <select id="status" name="status" class="form-control form-control-sm">
                                                <option value="ACTIVE">ACTIVO</option>
                                                <option value="INACTIVE">INACTIVO</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-8">
                                            <label>Observación</label>
                                            <textarea id="observation" name="observation" rows="2"
                                                class="form-control form-control-sm" placeholder="Observación"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- DOCUMENTOS --}}
                            <div class="card border-0 shadow-sm">

                                <div
                                    class="section-title section-success d-flex justify-content-between align-items-center">

                                    <div>

                                        <i class="fas fa-file-pdf mr-2"></i>

                                        Documentación

                                    </div>

                                    <button type="button" class="btn btn-sm btn-light shadow-sm"
                                        id="btnAddDocument">

                                        <i class="fas fa-plus-circle text-success"></i>
                                        Agregar Documento

                                    </button>

                                </div>

                                <div class="card-body p-2">

                                    <div class="table-responsive article-documents-scroll">

                                        <table class="table table-sm table-bordered mb-0">

                                            <thead>

                                                <tr>

                                                    <th>Tipo Documento</th>
                                                    <th>Marca</th>
                                                    <th>Archivo</th>
                                                    <th>Emisión</th>
                                                    <th>Vencimiento</th>
                                                    <th width="100" class="text-center">Acciones</th>

                                                </tr>

                                            </thead>

                                            <tbody id="documentsTableBody">

                                                <tr>

                                                    <td colspan="6" class="text-center text-muted">

                                                        No hay documentos agregados

                                                    </td>

                                                </tr>

                                            </tbody>

                                        </table>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    {{-- FOOTER --}}
                    <div class="article-modal-footer text-right mt-3">

                        <button type="button" class="btn btn-light border" data-dismiss="modal">

                            <i class="fas fa-times"></i>
                            Cerrar

                        </button>

                        <button type="submit" class="btn btn-primary" id="btnSaveArticle">

                            <i class="fas fa-save"></i>
                            Guardar Artículo

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>
