@extends('layouts.app')

@section('subtitle', 'Catálogos SUNAT')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-book-open text-primary mr-1"></i> Catálogos SUNAT
                </h1>
                <small class="text-muted">Administración centralizada de tablas y códigos tributarios.</small>
            </div>
            @can('catalogos_sunat.crear')
                <button id="btnNewCatalog" type="button" class="btn btn-primary shadow-sm">
                    <i class="fas fa-plus-circle mr-1"></i> Nuevo catálogo
                </button>
            @endcan
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">
                <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="fas fa-home"></i> Home</a></li>
                <li class="breadcrumb-item">Facturación Electrónica</li>
                <li class="breadcrumb-item active">Catálogos SUNAT</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="sunat-workspace">
        <aside class="card border-0 shadow-sm sunat-sidebar">
            <div class="card-header bg-white border-0 pb-2">
                <label for="catalogSearch" class="small font-weight-bold text-muted mb-2">BUSCAR CATÁLOGO</label>
                <div class="input-group input-group-sm">
                    <div class="input-group-prepend"><span class="input-group-text bg-white"><i class="fas fa-search"></i></span></div>
                    <input id="catalogSearch" type="search" class="form-control" placeholder="Código o nombre..." autocomplete="off">
                </div>
            </div>
            <div id="catalogList" class="card-body sunat-catalog-list pt-2" aria-live="polite">
                <div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando...</div>
            </div>
        </aside>

        <section class="card border-0 shadow-sm sunat-detail">
            <div id="emptyCatalogState" class="card-body sunat-empty-state">
                <div class="sunat-empty-icon"><i class="fas fa-layer-group"></i></div>
                <h4>Selecciona un catálogo</h4>
                <p class="text-muted mb-0">Elige una tabla del panel para consultar y administrar sus códigos.</p>
            </div>

            <div id="catalogDetail" class="d-none">
                <div class="card-header bg-white sunat-detail-header">
                    <div class="d-flex justify-content-between align-items-start flex-wrap">
                        <div>
                            <div class="small text-primary font-weight-bold mb-1">TABLA <span id="selectedCatalogCode"></span></div>
                            <h4 id="selectedCatalogName" class="font-weight-bold text-dark mb-1"></h4>
                            <p id="selectedCatalogDescription" class="text-muted small mb-0"></p>
                        </div>
                        <div class="sunat-header-actions mt-2 mt-md-0">
                            <span id="selectedCatalogStatus" class="badge mr-1"></span>
                            @can('catalogos_sunat.editar')
                                <button id="btnEditCatalog" type="button" class="btn btn-outline-primary btn-sm" title="Editar catálogo">
                                    <i class="fas fa-pen"></i>
                                </button>
                            @endcan
                            @can('catalogos_sunat.cambiar_estado')
                                <button id="btnCatalogStatus" type="button" class="btn btn-outline-secondary btn-sm" title="Cambiar estado">
                                    <i class="fas fa-power-off"></i>
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                        <div>
                            <h5 class="font-weight-bold mb-1"><i class="fas fa-list-ol text-primary mr-1"></i> Códigos del catálogo</h5>
                            <small class="text-muted">La búsqueda y paginación se procesan en el servidor.</small>
                        </div>
                        @can('catalogos_sunat.crear')
                            <button id="btnNewItem" type="button" class="btn btn-primary btn-sm mt-2 mt-md-0">
                                <i class="fas fa-plus mr-1"></i> Agregar código
                            </button>
                        @endcan
                    </div>

                    <div class="table-responsive">
                        <table id="tableSunatItems" class="table table-hover w-100">
                            <thead class="bg-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Origen</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div class="modal fade" id="catalogModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow">
                <form id="catalogForm" novalidate>
                    <div class="modal-header border-0">
                        <div><h5 id="catalogModalTitle" class="modal-title font-weight-bold">Nuevo catálogo SUNAT</h5><small class="text-muted">Los códigos se guardan como texto.</small></div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input id="catalogId" type="hidden">
                        <div class="form-group">
                            <label for="catalogCode">Código <span class="text-danger">*</span></label>
                            <input id="catalogCode" name="code" type="text" maxlength="50" class="form-control" placeholder="Ej. 12" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group">
                            <label for="catalogName">Nombre <span class="text-danger">*</span></label>
                            <input id="catalogName" name="name" type="text" maxlength="255" class="form-control" placeholder="TIPO DE OPERACIÓN" required>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="catalogDescription">Descripción</label>
                            <textarea id="catalogDescription" name="description" rows="3" maxlength="2000" class="form-control" placeholder="Alcance o notas del catálogo"></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                        <button id="btnSaveCatalog" type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="itemModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content border-0 shadow">
                <form id="itemForm" novalidate>
                    <div class="modal-header border-0">
                        <div><h5 id="itemModalTitle" class="modal-title font-weight-bold">Agregar código SUNAT</h5><small id="itemCatalogLabel" class="text-muted"></small></div>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <input id="itemId" type="hidden">
                        <div class="row">
                            <div class="col-md-4 form-group">
                                <label for="itemCode">Código <span class="text-danger">*</span></label>
                                <input id="itemCode" name="item_code" type="text" maxlength="20" class="form-control" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-8 form-group">
                                <label for="itemShortName">Nombre corto</label>
                                <input id="itemShortName" name="short_name" type="text" maxlength="255" class="form-control">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="itemDescription">Descripción <span class="text-danger">*</span></label>
                            <textarea id="itemDescription" name="description" rows="4" maxlength="5000" class="form-control" required></textarea>
                            <div class="invalid-feedback"></div>
                        </div>
                        <div class="form-group mb-0">
                            <label for="itemExtraData">Datos adicionales (JSON)</label>
                            <textarea id="itemExtraData" rows="4" class="form-control font-monospace" placeholder='{"campo_adicional": "valor"}'></textarea>
                            <small class="form-text text-muted">Opcional. Permite conservar columnas particulares de la tabla SUNAT.</small>
                            <div id="itemExtraDataError" class="invalid-feedback"></div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                        <button id="btnSaveItem" type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="statusModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
            <div class="modal-content border-0 shadow text-center">
                <div class="modal-body px-4 pt-4">
                    <div class="sunat-status-icon mb-3"><i class="fas fa-power-off"></i></div>
                    <h5 id="statusModalTitle" class="font-weight-bold"></h5>
                    <p id="statusModalText" class="text-muted small mb-0"></p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                    <button id="btnConfirmStatus" type="button" class="btn btn-primary">Confirmar</button>
                </div>
            </div>
        </div>
    </div>
@stop

@push('css')
    <style>
        .sunat-workspace { display:grid; grid-template-columns:minmax(270px, 330px) minmax(0, 1fr); gap:1.25rem; align-items:start; }
        .sunat-sidebar, .sunat-detail { border-radius:16px; overflow:hidden; }
        .sunat-sidebar { position:sticky; top:1rem; }
        .sunat-catalog-list { max-height:68vh; overflow-y:auto; }
        .sunat-catalog-card { width:100%; border:1px solid #edf0f5; background:#fff; border-radius:12px; padding:.8rem; margin-bottom:.65rem; text-align:left; transition:.18s ease; }
        .sunat-catalog-card:hover { border-color:#80bdff; transform:translateY(-1px); }
        .sunat-catalog-card.active { color:#fff; border-color:#007bff; background:linear-gradient(135deg,#007bff,#2855c5); box-shadow:0 8px 18px rgba(0,123,255,.22); }
        .sunat-catalog-code { display:inline-flex; min-width:42px; height:30px; padding:0 .5rem; border-radius:8px; background:#eef5ff; color:#1769c2; align-items:center; justify-content:center; font-weight:800; }
        .sunat-catalog-card.active .sunat-catalog-code { background:rgba(255,255,255,.18); color:#fff; }
        .sunat-catalog-meta { font-size:.72rem; opacity:.8; }
        .sunat-detail-header { padding:1.25rem; border-bottom:1px solid #f0f2f5; }
        .sunat-empty-state { min-height:430px; display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; }
        .sunat-empty-icon, .sunat-status-icon { width:68px; height:68px; display:flex; align-items:center; justify-content:center; border-radius:20px; background:#eef5ff; color:#007bff; font-size:1.7rem; }
        .sunat-status-icon { margin-left:auto; margin-right:auto; width:56px; height:56px; }
        #tableSunatItems th { border-top:0; white-space:nowrap; font-size:.78rem; text-transform:uppercase; color:#5f6875; }
        #tableSunatItems td { vertical-align:middle; }
        .sunat-item-description { white-space:normal; min-width:260px; }
        .font-monospace { font-family:SFMono-Regular,Consolas,Liberation Mono,monospace; }
        @media (max-width: 991.98px) { .sunat-workspace { grid-template-columns:1fr; } .sunat-sidebar { position:static; } .sunat-catalog-list { max-height:310px; } }
    </style>
@endpush

@push('js')
    @php
        $sunatCatalogConfig = [
            'routes' => [
                'catalogs' => route('admin.sunat-catalogs.catalogs'),
                'catalogStore' => route('admin.sunat-catalogs.catalogs.store'),
                'catalogUpdate' => route('admin.sunat-catalogs.catalogs.update', ['sunatCatalog' => '__CATALOG__']),
                'catalogStatus' => route('admin.sunat-catalogs.catalogs.status', ['sunatCatalog' => '__CATALOG__']),
                'items' => route('admin.sunat-catalogs.items', ['sunatCatalog' => '__CATALOG__']),
                'itemStore' => route('admin.sunat-catalogs.items.store', ['sunatCatalog' => '__CATALOG__']),
                'itemUpdate' => route('admin.sunat-catalogs.items.update', ['sunatCatalog' => '__CATALOG__', 'sunatCatalogItem' => '__ITEM__']),
                'itemStatus' => route('admin.sunat-catalogs.items.status', ['sunatCatalog' => '__CATALOG__', 'sunatCatalogItem' => '__ITEM__']),
            ],
            'permissions' => [
                'create' => auth()->user()->can('catalogos_sunat.crear'),
                'edit' => auth()->user()->can('catalogos_sunat.editar'),
                'status' => auth()->user()->can('catalogos_sunat.cambiar_estado'),
            ],
        ];
    @endphp
    <script>
        window.sunatCatalogConfig = {{ Illuminate\Support\Js::from($sunatCatalogConfig) }};
    </script>
    @vite(['resources/js/pages/sunat-catalog.js'])
@endpush
