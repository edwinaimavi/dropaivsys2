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

                                            <input type="text" id="code" name="code" readonly
                                                class="form-control form-control-sm">

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

                                        <div class="form-group col-md-4">

                                            <label>Marca</label>

                                            <select id="brand_id" name="brand_id"
                                                class="form-control form-control-sm">

                                                <option value="">
                                                    Seleccione
                                                </option>

                                                @foreach ($brands as $brand)
                                                    <option value="{{ $brand->id }}">
                                                        {{ $brand->description }}
                                                    </option>
                                                @endforeach

                                            </select>

                                        </div>

                                        <div class="form-group col-md-4">

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

                                        <div class="form-group col-md-4">

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
                                        </div>

                                        <div class="col-md-4">
                                            <label>Nombre Comercial</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="commercial_name">
                                        </div>

                                        <div class="col-md-4">
                                            <label>Nombre Facturación</label>
                                            <input type="text" class="form-control form-control-sm"
                                                id="billing_name">
                                        </div>

                                    </div>
                                </div>

                            </div>

                            {{-- CONFIGURACION --}}
                            <div class="card border-0 shadow-sm mb-2">

                                <div class="section-title section-secondary">

                                    <i class="fas fa-cogs mr-2"></i>

                                    Configuración

                                </div>

                                <div class="card-body">

                                    <div class="form-row">

                                        <div class="form-group col-md-2">

                                            <label>Stock Mínimo</label>

                                            <input type="number" step="0.01" min="0" id="minimum_stock"
                                                name="minimum_stock" value="0.00"
                                                class="form-control form-control-sm">

                                        </div>

                                        <div class="form-group col-md-2">

                                            <label>Afecto IGV</label>

                                            <select id="is_taxable" name="is_taxable"
                                                class="form-control form-control-sm">

                                                <option value="1">
                                                    SI
                                                </option>

                                                <option value="0">
                                                    NO
                                                </option>

                                            </select>

                                        </div>

                                        <div class="form-group col-md-2">

                                            <label>Maneja Lote</label>

                                            <select id="has_batch" name="has_batch"
                                                class="form-control form-control-sm">

                                                <option value="1">
                                                    SI
                                                </option>

                                                <option value="0">
                                                    NO
                                                </option>

                                            </select>

                                        </div>

                                        <div class="form-group col-md-3">

                                            <label>Maneja Vencimiento</label>

                                            <select id="has_expiration" name="has_expiration"
                                                class="form-control form-control-sm">

                                                <option value="1">
                                                    SI
                                                </option>

                                                <option value="0">
                                                    NO
                                                </option>

                                            </select>

                                        </div>

                                        <div class="form-group col-md-3">

                                            <label>Estado</label>

                                            <select id="status" name="status"
                                                class="form-control form-control-sm">

                                                <option value="ACTIVE">
                                                    ACTIVO
                                                </option>

                                                <option value="INACTIVE">
                                                    INACTIVO
                                                </option>

                                            </select>

                                        </div>

                                    </div>

                                    <div class="form-group">

                                        <label>Observación</label>

                                        <textarea id="observation" name="observation" rows="1" class="form-control form-control-sm"
                                            placeholder="Observación">  </textarea>

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

                                    <div class="table-responsive">

                                        <table class="table table-sm table-bordered mb-0">

                                            <thead>

                                                <tr>

                                                    <th>Tipo Documento</th>
                                                    <th>Archivo</th>
                                                    <th>Emisión</th>
                                                    <th>Vencimiento</th>
                                                    <th width="80">Acción</th>

                                                </tr>

                                            </thead>

                                            <tbody id="documentsTableBody">

                                                <tr>

                                                    <td colspan="5" class="text-center text-muted">

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
                    <div class="text-right mt-3">

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
