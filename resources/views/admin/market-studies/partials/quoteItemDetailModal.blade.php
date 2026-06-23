<!-- =========================================================
     MODAL CONFIGURAR ÍTEM
========================================================= -->
<div class="modal fade" id="quoteItemDetailModal" tabindex="-1" data-backdrop="static" data-keyboard="false"
    aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content quote-detail-modal">

            <!-- HEADER -->
            <div class="quote-detail-header">

                <div class="d-flex align-items-center">

                    <div class="quote-detail-icon">
                        <i class="fas fa-cogs"></i>
                    </div>

                    <div class="ml-3">
                        <h5 class="mb-0 font-weight-bold text-white">
                            Configurar ítem
                        </h5>
                        <small class="text-white-50">
                            Complete los datos comerciales y sanitarios del producto
                        </small>
                    </div>
                </div>

                <button type="button" class="quote-detail-close" data-dismiss="modal" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body quote-detail-body">

                <input type="hidden" id="quoteItemIndex">

                <div class="row">

                    <!-- PANEL IZQUIERDO -->
                    <div class="col-md-4 mb-3 mb-md-0">
                        <div class="quote-detail-sidecard h-100">

                            <div class="quote-detail-sideicon">
                                <i class="fas fa-box-open"></i>
                            </div>

                            <h6 class="quote-side-title mb-1">Detalle del ítem</h6>
                            <p class="quote-side-subtitle mb-3">
                                Ajuste la información antes de guardar
                            </p>

                            <div class="quote-mini-info">
                                <span class="quote-mini-label">Marca</span>
                                <div class="quote-mini-value">
                                    <select id="detail_brand_id" class="form-control form-control-sm">
                                    </select>
                                </div>
                            </div>

                            <div class="quote-mini-info">
                                <span class="quote-mini-label">Unidad</span>
                                <div class="quote-mini-value">
                                    <select id="detail_unit_id" class="form-control form-control-sm">
                                    </select>
                                </div>
                            </div>

                            <div class="quote-mini-info">
                                <span class="quote-mini-label">Presentación</span>
                                <div class="quote-mini-value">
                                    <select id="detail_presentation_id" class="form-control form-control-sm">
                                    </select>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- PANEL DERECHO -->
                    <div class="col-md-8">
                        <div class="quote-detail-panel">

                            <div class="quote-section-title">
                                <i class="fas fa-shield-alt mr-2"></i>
                                Datos sanitarios
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="quote-label">Fecha fabricación</label>
                                    <input type="date" id="detail_manufacture_date"
                                        class="form-control form-control-sm">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="quote-label">Fecha vencimiento</label>
                                    <input type="date" id="detail_expiration_date"
                                        class="form-control form-control-sm">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="quote-label">Procedencia</label>
                                    <input type="text" id="detail_origin" class="form-control form-control-sm"
                                        placeholder="Ingrese procedencia">
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="quote-label">Registro sanitario</label>
                                    <input type="text" id="detail_sanitary_registration"
                                        class="form-control form-control-sm" placeholder="Ingrese registro sanitario">
                                </div>
                            </div>

                            <div class="quote-section-title mt-2">
                                <i class="fas fa-sticky-note mr-2"></i>
                                Observación
                            </div>

                            <div class="mb-2">
                                <textarea id="detail_observation" rows="4" class="form-control form-control-sm"
                                    placeholder="Ingrese observaciones del ítem"></textarea>
                            </div>

                        </div>
                    </div>

                </div>
            </div>

            <!-- FOOTER -->
            <div class="modal-footer quote-detail-footer">
                <button type="button" class="btn btn-light px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cancelar
                </button>

                <button type="button" id="btnSaveQuoteItemDetail" class="btn btn-success px-4">
                    <i class="fas fa-save mr-1"></i>
                    Guardar
                </button>
            </div>

        </div>
    </div>
</div>

<style>
    .quote-detail-modal {
        border: none;
        border-radius: 18px;
        overflow: hidden;
        box-shadow: 0 24px 70px rgba(0, 0, 0, .22);
        background: #fff;
    }

    .quote-detail-header {
        background: linear-gradient(135deg, #198754, #146c43);
        padding: 12px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .quote-detail-icon {
        width: 38px;
        height: 38px;
        border-radius: 12px;
        background: rgba(255, 255, 255, .14);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .quote-detail-close {
        border: none;
        width: 34px;
        height: 34px;
        border-radius: 10px;
        background: rgba(255, 255, 255, .14);
        color: #fff;
        transition: .2s ease;
    }

    .quote-detail-close:hover {
        background: rgba(255, 255, 255, .25);
        transform: scale(1.03);
    }

    .quote-detail-body {
        padding: 14px;
        background: #f7faf8;
    }

    .quote-detail-sidecard {
        background: linear-gradient(180deg, #ffffff, #f8fbf8);
        border: 1px solid #e7eee9;
        border-radius: 16px;
        padding: 16px 14px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .04);
    }

    .quote-detail-sideicon {
        width: 66px;
        height: 66px;
        border-radius: 18px;
        margin: 0 auto 12px auto;
        background: linear-gradient(135deg, #198754, #1e9b5a);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }

    .quote-side-title {
        font-weight: 700;
        color: #2f3640;
        text-align: center;
        font-size: 15px;
    }

    .quote-side-subtitle {
        text-align: center;
        color: #7a8088;
        font-size: 12px;
        line-height: 1.35;
    }

    .quote-mini-info {
        margin-bottom: 10px;
    }

    .quote-mini-label {
        display: block;
        font-size: 10px;
        font-weight: 700;
        color: #6c757d;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .quote-mini-value .form-control {
        border-radius: 10px;
        border-color: #dbe2e8;
        font-size: 12px;
    }

    .quote-detail-panel {
        background: #fff;
        border: 1px solid #e8edf0;
        border-radius: 16px;
        padding: 15px;
        box-shadow: 0 4px 14px rgba(0, 0, 0, .04);
    }

    .quote-section-title {
        font-size: 12px;
        font-weight: 800;
        color: #198754;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .quote-label {
        font-size: 10px;
        font-weight: 700;
        color: #6c757d;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .quote-detail-panel .form-control {
        border-radius: 10px;
        border-color: #dbe2e8;
        background: #fff;
        font-size: 12px;
    }

    .quote-detail-panel .form-control:focus {
        border-color: #7cc89a;
        box-shadow: 0 0 0 .2rem rgba(25, 135, 84, .12);
    }

    .quote-detail-footer {
        padding: 12px 16px;
        background: #fff;
        border-top: 1px solid #eef2f4;
    }

    .modal-backdrop.show {
        opacity: .42 !important;
    }

    @media (max-width: 767px) {
        .quote-detail-body {
            padding: 12px;
        }

        .quote-detail-panel,
        .quote-detail-sidecard {
            border-radius: 14px;
        }
    }
</style>
