<!-- =========================================================
     MODAL COMPARATIVO DE COTIZACIONES
========================================================= -->
<div class="modal fade" id="quoteComparisonModal" tabindex="-1" data-backdrop="static" data-keyboard="false"
    aria-hidden="true">

    <div class="modal-dialog modal-xxl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content quote-comparison-modal">

            <!-- HEADER -->
            <div class="quote-comparison-header">

                <div class="d-flex align-items-center">

                    <div class="quote-comparison-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>

                    <div class="ml-3">
                        <h5 class="mb-0 font-weight-bold text-white">
                            Comparativo de Cotizaciones
                        </h5>
                        <small class="text-white-50">
                            Adjudicación y evaluación por ítem del estudio de mercado
                        </small>
                    </div>
                </div>

                <button type="button" class="quote-comparison-close" data-dismiss="modal" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-4 bg-light">

                <div class="mb-3">
                    <h6 class="font-weight-bold text-success mb-1">
                        Estudio de Mercado
                    </h6>
                    <div id="comparisonStudyInfo" class="text-muted">
                        —
                    </div>
                </div>

                <hr>

                <!-- PANEL DE DETALLE DEL ARTÍCULO -->
                <div id="comparisonDetailPanel" class="mb-3">

                    <div id="comparisonEmptyState" class="text-center py-5">
                        <i class="fas fa-hand-pointer fa-2x text-success mb-3"></i>
                        <div class="text-muted">
                            Seleccione un artículo de la matriz inferior para visualizar las cotizaciones de cada
                            proveedor y elegir al ganador.
                        </div>
                    </div>

                    <div id="comparisonArticleDetail" style="display:none;">
                        <!-- Aquí se cargará dinámicamente el comparativo del artículo -->
                    </div>

                </div>

                <!-- RESUMEN -->
                <div class="comparison-info-box">

                    <div class="comparison-info-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>

                    <div class="flex-grow-1">
                        <h6 class="mb-1 font-weight-bold text-dark" style="font-size:14px;">
                            Comparativo del estudio
                        </h6>
                        <p class="mb-0 text-muted" style="font-size:12px;">
                            Aquí podrá visualizar los artículos del estudio y comparar las cotizaciones por proveedor.
                            Seleccione el ganador por cada ítem.
                        </p>
                    </div>

                    <div class="comparison-stats">
                        <div class="comparison-stat">
                            <small class="text-muted d-block">Ítems</small>
                            <span class="badge badge-success px-2 py-1" id="comparisonItemsCount">0</span>
                        </div>

                        <div class="comparison-stat ml-3">
                            <small class="text-muted d-block">Cotizaciones</small>
                            <span class="badge badge-primary px-2 py-1" id="comparisonQuotesCount">0</span>
                        </div>
                    </div>

                </div>

                <!-- CONTENIDO -->
                <div class="quote-comparison-card">

                    <!-- CABECERA INTERNA -->
                    <div
                        class="quote-comparison-card-header d-flex justify-content-between align-items-center flex-wrap">

                        <div>
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-clipboard-list text-success mr-2"></i>
                                Matriz de Comparación
                            </h6>
                            <small class="text-muted">
                                Seleccione el proveedor ganador por cada artículo
                            </small>
                        </div>

                        <div class="mt-2 mt-md-0 d-flex gap-2 flex-wrap">

                            <button type="button" id="btnRefreshComparison" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-sync-alt mr-1"></i>
                                Actualizar
                            </button>

                            <button type="button" id="btnSaveComparison" class="btn btn-success btn-sm">
                                <i class="fas fa-save mr-1"></i>
                                Guardar Comparativo
                            </button>

                        </div>

                    </div>

                    <!-- TABLA -->
                    <div class="table-responsive comparison-table-wrapper">

                        <table id="tableQuoteComparison" class="table table-hover align-middle w-100 mb-0">

                            <thead>
                                <tr>
                                    <th width="60">#</th>
                                    <th width="180">ARTÍCULO</th>
                                    <th width="140">CANTIDAD</th>
                                    <th width="160">UNIDAD</th>
                                    <th width="180">PRESENTACIÓN</th>
                                    <th width="180">GANADOR</th>
                                    <th width="140">PRECIO</th>
                                    <th width="120">TOTAL</th>
                                    <th width="90">ESTADO</th>
                                </tr>
                            </thead>

                            <tbody id="comparisonBody">
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        No hay información para mostrar.
                                    </td>
                                </tr>
                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer border-0 bg-white">

                <button type="button" class="btn btn-light px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>

            </div>

        </div>

    </div>

</div>

<style>
    .modal-backdrop.show {
        opacity: .50 !important;
    }

    .modal-xxl {
        max-width: 96%;
    }

    .quote-comparison-modal {
        border: none;
        border-radius: 22px;
        overflow: hidden;
        box-shadow: 0 25px 80px rgba(0, 0, 0, .30);
    }

    .quote-comparison-header {
        background: linear-gradient(135deg, #198754, #146c43);
        color: #fff;
        padding: 14px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .quote-comparison-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .15);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        flex-shrink: 0;
    }

    .quote-comparison-close {
        border: none;
        background: rgba(255, 255, 255, .15);
        color: #fff;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        transition: .2s ease;
    }

    .quote-comparison-close:hover {
        background: rgba(255, 255, 255, .25);
        transform: scale(1.03);
    }

    .comparison-info-box {
        background: #f8fffb;
        border: 1px solid #d4edda;
        border-radius: 16px;
        padding: 12px 14px;
        display: flex;
        align-items: center;
        margin-bottom: 14px;
        box-shadow: 0 3px 12px rgba(0, 0, 0, .04);
        gap: 12px;
    }

    .comparison-info-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: #198754;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        flex-shrink: 0;
    }

    .comparison-stats {
        display: flex;
        align-items: center;
        flex-shrink: 0;
    }

    .comparison-stat {
        text-align: right;
    }

    .quote-comparison-card {
        background: #fff;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 5px 20px rgba(0, 0, 0, .06);
        border: 1px solid #eef1f4;
    }

    .quote-comparison-card-header {
        padding: 12px 16px;
        border-bottom: 1px solid #eef1f4;
        background: #fff;
    }

    .comparison-table-wrapper {
        max-height: 62vh;
        overflow: auto;
    }

    #tableQuoteComparison thead th {
        background: #f8f9fa;
        border: none;
        font-size: 11px;
        font-weight: 700;
        color: #555;
        text-transform: uppercase;
        white-space: nowrap;
        vertical-align: middle;
        position: sticky;
        top: 0;
        z-index: 2;
    }

    #tableQuoteComparison tbody td {
        padding: 12px 10px;
        vertical-align: middle;
        font-size: 12px;
        white-space: nowrap;
    }

    #tableQuoteComparison tbody tr:hover {
        background: #f8fff9;
        transition: .2s ease;
    }

    #tableQuoteComparison .form-check-input {
        transform: scale(1.05);
        cursor: pointer;
    }

    #studyQuoteComparisonModal .badge {
        font-size: 10px;
        font-weight: 600;
    }

    #btnSaveComparison,
    #btnRefreshComparison {
        border-radius: 10px;
    }

    #studyQuoteComparisonModal .modal-dialog {
        transform: scale(.96);
        transition: .2s ease;
    }

    #studyQuoteComparisonModal.show .modal-dialog {
        transform: scale(1);
    }

    @media (max-width: 991px) {
        .modal-xxl {
            max-width: 100%;
            margin: .5rem;
        }
    }

    /* ==========================================================
   TARJETAS DEL COMPARATIVO
========================================================== */

    #comparisonArticleDetail {
        width: 100%;
        margin: 0 auto;
    }

    .comparison-cards-container {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 20px;
        padding: 10px 0;
    }

    .comparison-detail-card {
        width: 450px;
        max-width: 100%;
        border-radius: 15px;
        border: 1px solid #e5e5e5;
        box-shadow: 0 4px 10px rgba(0, 0, 0, .08);
        overflow: visible !important;
    }

    .comparison-detail-card .card-header {
        padding: 10px 15px;
        padding-top: 24px;
    }

    .comparison-detail-card .card-body {
        padding: 12px 15px;
    }

    .comparison-detail-card h5,
    .comparison-detail-card h6 {
        font-size: 15px !important;
        margin-bottom: 2px;
    }

    .comparison-detail-card small {
        font-size: 11px !important;
    }

    .comparison-detail-card .row {
        margin-bottom: 8px;
    }

    .comparison-detail-card label {
        font-size: 10px;
        color: #888;
        margin-bottom: 0;
    }

    .comparison-detail-card strong {
        font-size: 12px;
        color: #263238;
    }

    .btnSelectComparisonWinner {
        width: 100%;
        margin-top: 10px;
        border-radius: 8px;
        font-size: 12px;
        padding: 8px;
    }

    /* =====================================================
   DETALLE DEL COMPARATIVO
===================================================== */

    .comparison-title-box {
        text-align: center;
        margin-bottom: 15px;
    }

    .comparison-title-box .font-weight-bold {
        font-size: 16px;
    }

    .comparison-title-box .text-muted {
        font-size: 13px;
    }

    .comparison-cards-container {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 18px;
        width: 100%;
    }

    .comparison-detail-card {
        width: 420px;
        max-width: 100%;
        border-radius: 15px;
        border: 1px solid #e5e5e5;
        box-shadow: 0 4px 12px rgba(0, 0, 0, .08);
        transition: .2s;
    }

    .comparison-detail-card:hover {
        transform: translateY(-2px);
    }

    .comparison-detail-card .card-header {
        background: #fafafa;
        padding: 10px 14px;
    }

    .comparison-detail-card .card-body {
        padding: 12px 14px;
    }

    .comparison-detail-card label {
        margin: 0;
        font-size: 10px;
        color: #888;
        font-weight: 600;
    }

    .comparison-detail-card strong {
        font-size: 12px;
        color: #2d3436;
    }

    .btnSelectComparisonWinner {
        border-radius: 8px;
        font-size: 12px;
    }

    /* ============================================
   TARJETA GANADORA
============================================ */

    .comparison-detail-card.selected-winner::before {
        content: "🏆 GANADOR";
        position: absolute;
        top: 8px;
        right: 10px;
        background: #28a745;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 12px;
        z-index: 5;
    }

    .comparison-detail-card.selected-winner {
        border: 3px solid #28a745 !important;
        background: linear-gradient(to bottom, #f8fff9, #eefcf1);
        box-shadow: 0 8px 24px rgba(40, 167, 69, .25);
        transform: scale(1.02);
        transition: all .25s ease;
        position: relative;
    }

    .comparison-detail-card.selected-winner .card-header {
        background: #eaf8ee;
    }

    .comparison-detail-card.selected-winner .btnSelectComparisonWinner {
        background: #198754;
        border-color: #198754;
        color: #fff;
    }

    .comparison-detail-card .custom-control {
        position: relative;
        z-index: 30;
    }

    .comparison-detail-card .custom-control-input,
    .comparison-detail-card .custom-control-label::before,
    .comparison-detail-card .custom-control-label::after {
        z-index: 30;
    }
</style>
