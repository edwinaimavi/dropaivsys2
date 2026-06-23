<div class="modal fade" id="viewMarketStudyModal" tabindex="-1">

    <div class="modal-dialog modal-xl modal-dialog-scrollable">

        <div class="modal-content border-0 shadow">

            {{-- HEADER --}}
            <div class="modal-header bg-success py-2">

                <h5 class="modal-title text-white mb-0">

                    <i class="fas fa-chart-line mr-2"></i>
                    Información del Estudio de Mercado

                </h5>

                <button type="button" class="close text-white" data-dismiss="modal">

                    <span>&times;</span>

                </button>

            </div>

            <div class="modal-body p-3">

                {{-- DATOS GENERALES --}}
                <div class="card shadow-sm mb-3">

                    <div class="card-header view-section-header">

                        <i class="fas fa-file-alt mr-2"></i>
                        Datos Generales

                    </div>

                    <div class="card-body py-3">

                        <div class="row">

                            <div class="col-md-3">

                                <label class="text-muted small mb-1">
                                    Código
                                </label>

                                <div id="view_code" class="font-weight-bold">
                                    -
                                </div>

                            </div>

                            <div class="col-md-9">

                                <label class="text-muted small mb-1">
                                    Descripción
                                </label>

                                <div id="view_description">
                                    -
                                </div>

                            </div>

                        </div>

                        <hr>

                        <label class="text-muted small mb-1">
                            Términos de Referencia
                        </label>

                        <div id="view_terms" class="border rounded p-2 bg-light">
                            -
                        </div>

                    </div>

                </div>

                {{-- RESUMEN --}}
                <div class="row mb-3">

                    <div class="col-md-3">

                        <div class="small-box bg-success">

                            <div class="inner">

                                <h3 id="view_total_items">0</h3>
                                <p>Artículos</p>

                            </div>

                            <div class="icon">

                                <i class="fas fa-box"></i>

                            </div>

                        </div>

                    </div>

                    <div class="col-md-3">

                        <div class="small-box bg-info">

                            <div class="inner">

                                <h3 id="view_total_quotes">0</h3>
                                <p>Cotizaciones</p>

                            </div>

                            <div class="icon">

                                <i class="fas fa-file-invoice"></i>

                            </div>

                        </div>

                    </div>

                    <div class="col-md-3">

                        <div class="small-box bg-warning">

                            <div class="inner">

                                <h3 id="view_total_suppliers">0</h3>
                                <p>Proveedores</p>

                            </div>

                            <div class="icon">

                                <i class="fas fa-building"></i>

                            </div>

                        </div>

                    </div>

                    <div class="col-md-3">

                        <div class="small-box bg-primary">

                            <div class="inner">

                                <h3 id="view_total_winners">0</h3>
                                <p>Adjudicados</p>

                            </div>

                            <div class="icon">

                                <i class="fas fa-trophy"></i>

                            </div>

                        </div>

                    </div>

                </div>

                {{-- PROVEEDORES --}}
                <div class="card shadow-sm mb-3">

                    <div class="card-header view-section-header">

                        <i class="fas fa-building mr-2"></i>
                        Proveedores Participantes

                    </div>

                    <div class="card-body p-0">

                        <table class="table table-sm table-bordered mb-0">

                            <thead>
                                <tr>
                                    <th>Proveedor</th>
                                    <th>Moneda</th>
                                    <th>Condición</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>

                            <tbody id="viewSuppliersBody"></tbody>

                        </table>

                    </div>

                </div>

                {{-- ADJUDICACION --}}
                <div class="card shadow-sm mb-3">

                    <div class="card-header view-section-header">

                        <i class="fas fa-trophy mr-2"></i>
                        Resultado Final de Adjudicación

                    </div>

                    <div class="card-body p-0">

                        <table class="table table-sm table-bordered table-hover mb-0">

                            <thead>
                                <tr>
                                    <th>Artículo</th>
                                    <th>Proveedor Ganador</th>
                                    <th>Marca</th>
                                    <th>Presentación</th>
                                    <th>Cantidad</th>
                                    <th>P.Unit</th>
                                    <th>Total</th>
                                </tr>
                            </thead>

                            <tbody id="viewComparisonBody"></tbody>

                        </table>

                    </div>

                </div>

                {{-- SANITARIOS --}}
                <div class="card shadow-sm mb-3">

                    <div class="card-header view-section-header">

                        <i class="fas fa-shield-virus mr-2"></i>
                        Información Sanitaria de los Ganadores

                    </div>

                    <div class="card-body">

                        <div id="viewSanitaryContainer"></div>

                    </div>

                </div>

                {{-- RESUMEN ECONÓMICO --}}
                <div class="card shadow-sm">

                    <div class="card-header view-section-header">

                        <i class="fas fa-calculator mr-2"></i>
                        Resumen Económico

                    </div>

                    <div class="card-body py-3">

                        <div class="row text-center">

                            <div class="col-md-3">
                                <small>Inafecta</small>
                                <h4 id="view_inafecta">S/ 0.00</h4>
                            </div>

                            <div class="col-md-3">
                                <small>Exonerada</small>
                                <h4 id="view_exonerada">S/ 0.00</h4>
                            </div>

                            <div class="col-md-3">
                                <small>IGV</small>
                                <h4 id="view_igv">S/ 0.00</h4>
                            </div>

                            <div class="col-md-3">
                                <small>Total</small>
                                <h3 class="text-success" id="view_total">S/ 0.00</h3>
                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="modal-footer py-2">

                <button type="button" class="btn btn-secondary" data-dismiss="modal">

                    <i class="fas fa-times mr-1"></i>

                    Cerrar

                </button>

            </div>

        </div>

    </div>

</div>

<style>
    #viewMarketStudyModal .modal-content {
        border: none;
        border-radius: 14px;
        overflow: hidden;
    }

    #viewMarketStudyModal .modal-header {
        background: linear-gradient(135deg, #198754, #157347);
        padding: .75rem 1rem;
    }

    #viewMarketStudyModal .modal-title {
        font-size: 16px;
        font-weight: 600;
    }

    #viewMarketStudyModal .modal-body {
        padding: 12px;
    }

    #viewMarketStudyModal .card {
        border: none;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .08);
        margin-bottom: 12px;
    }

    #viewMarketStudyModal .card-header {
        padding: .65rem .9rem;
        background: #198754;
        color: #fff;
        border: none;
    }

    #viewMarketStudyModal .card-header h5,
    #viewMarketStudyModal .card-header h6 {
        margin: 0;
        font-size: 14px;
        font-weight: 600;
        color: #fff;
    }

    #viewMarketStudyModal .card-body {
        padding: .8rem;
    }

    #viewMarketStudyModal label {
        margin-bottom: 3px;
        font-size: 11px;
        font-weight: 600;
        color: #6c757d;
        text-transform: uppercase;
    }

    #viewMarketStudyModal h6 {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 0;
    }

    #viewMarketStudyModal .table {
        margin-bottom: 0;
        font-size: 12px;
    }

    #viewMarketStudyModal .table th {
        background: #f8f9fa;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 8px;
        vertical-align: middle;
    }

    #viewMarketStudyModal .table td {
        padding: 7px;
        vertical-align: middle;
    }

    #viewMarketStudyModal .small-box {
        border-radius: 10px;
        margin-bottom: 10px;
        box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .08);
    }

    #viewMarketStudyModal .small-box .inner h3 {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    #viewMarketStudyModal .small-box .inner p {
        font-size: 13px;
        margin-bottom: 0;
    }

    #viewMarketStudyModal .small-box .icon {
        font-size: 55px;
        opacity: .15;
    }

    #view_terms {
        font-size: 12px;
        min-height: 42px;
        padding: 8px;
        background: #f8f9fa;
    }

    #viewSanitaryContainer .card {
        margin-bottom: 8px;
    }

    #viewSanitaryContainer .card-body {
        padding: .6rem;
        font-size: 12px;
    }

    #viewMarketStudyModal .economic-box {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 12px;
    }

    #viewMarketStudyModal .economic-box h6 {
        font-size: 12px;
        color: #6c757d;
        margin-bottom: 5px;
    }

    #viewMarketStudyModal .economic-box h4 {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 0;
    }

    #viewMarketStudyModal .modal-footer {
        padding: .6rem .8rem;
    }

    #viewMarketStudyModal .btn {
        font-size: 12px;
    }


    .view-section-header {
        background: #198754 !important;
        color: #fff !important;
        font-size: 13px;
        font-weight: 600;
        padding: 10px 15px;
        border: none !important;
    }

    .view-section-header i {
        color: #fff !important;
    }

    #viewMarketStudyModal .card {
        border: none;
        border-radius: 10px;
        overflow: hidden;
    }

    #viewMarketStudyModal .table th {
        font-size: 11px;
        padding: 8px;
        background: #f8f9fa;
    }

    #viewMarketStudyModal .table td {
        font-size: 12px;
        padding: 7px;
    }

    #viewMarketStudyModal h4 {
        font-size: 22px;
        margin-bottom: 0;
    }

    #viewMarketStudyModal h3 {
        font-size: 28px;
        margin-bottom: 0;
    }

    #viewMarketStudyModal .card-header strong {
        color: #fff !important;
    }

    #viewMarketStudyModal .card-header strong i {
        color: #fff !important;
    }
</style>
