<!-- =========================================================
     MODAL LISTA DE COTIZACIONES DEL ESTUDIO
========================================================= -->
<div class="modal fade" id="studyQuoteListModal" tabindex="-1" data-backdrop="static" data-keyboard="false"
    aria-hidden="true">

    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">

        <div class="modal-content study-quote-list-modal">

            <!-- HEADER -->
            <div class="study-quote-list-header">

                <div class="d-flex align-items-center">

                    <div class="study-quote-list-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>

                    <div class="ml-3">
                        <h5 class="mb-0 font-weight-bold text-white">
                            Cotizaciones del Estudio
                        </h5>
                        <small class="text-white-50">
                            Gestión de ofertas vinculadas al estudio de mercado
                        </small>
                    </div>

                </div>

                <button type="button" class="study-quote-list-close" data-dismiss="modal" aria-label="Cerrar">
                    <i class="fas fa-times"></i>
                </button>

            </div>

            <!-- BODY -->
            <div class="modal-body p-4 bg-light">

                <!-- INFO RESUMEN -->
                <div class="study-quote-info-box">

                    <div class="study-quote-info-icon">
                        <i class="fas fa-info"></i>
                    </div>

                    <div class="flex-grow-1">
                        <h6 class="mb-1 font-weight-bold text-dark" style="font-size:14px;">
                            Cotizaciones registradas
                        </h6>
                        <p class="mb-0 text-muted" style="font-size:12px;">
                            Aquí podrá ver, editar o eliminar las cotizaciones asociadas al estudio de mercado.
                            También puede registrar una nueva cotización.
                        </p>
                    </div>

                    <div class="ml-3 text-right">
                        <small class="text-muted d-block">Total cotizaciones</small>
                        <span class="badge badge-success px-2 py-1" id="studyQuoteCount">0</span>
                    </div>
                </div>

                <!-- CARD TABLA -->
                <div class="study-quote-table-card">

                    <div class="study-quote-table-header d-flex justify-content-between align-items-center flex-wrap">

                        <div>
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-clipboard-list text-success mr-2"></i>
                                Lista de Cotizaciones
                            </h6>
                            <small class="text-muted">
                                Seleccione una cotización para administrarla
                            </small>
                        </div>

                        <div class="mt-2 mt-md-0">
                            <button type="button" id="btnNewStudyQuote" class="btn btn-success btn-sm px-3">
                                <i class="fas fa-plus-circle mr-1"></i>
                                Nueva Cotización
                            </button>
                        </div>

                    </div>

                    <div class="table-responsive">

                        <table id="tableStudyQuotes" class="table table-hover align-middle w-100 mb-0">

                            <thead>
                                <tr>
                                    <th width="60">#</th>
                                    <th width="140">COTIZACIÓN</th>
                                    <th>PROVEEDOR</th>
                                    <th width="100">MONEDA</th>
                                    <th width="120">T.C.</th>
                                    <th width="120">TOTAL</th>
                                    <th width="100">ESTADO</th>
                                    <th width="140">ACCIONES</th>
                                </tr>
                            </thead>

                            <tbody></tbody>

                        </table>

                    </div>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="modal-footer border-0 bg-white px-4 py-3">

                <button type="button" class="btn btn-light px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>

            </div>

        </div>

    </div>

</div>

<style>
    /* BACKDROP */
    .modal-backdrop.show {
        opacity: .50 !important;
    }

    /* MODAL */
    .study-quote-list-modal {
        border: none;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 28px 90px rgba(0, 0, 0, .30);
        background: #fff;
    }

    /* HEADER */
    .study-quote-list-header {
        background: linear-gradient(135deg, #198754 0%, #146c43 100%);
        color: #fff;
        padding: 16px 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .study-quote-list-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        background: rgba(255, 255, 255, .14);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .15);
    }

    .study-quote-list-close {
        border: none;
        background: rgba(255, 255, 255, .14);
        color: #fff;
        width: 36px;
        height: 36px;
        border-radius: 12px;
        transition: .2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .study-quote-list-close:hover {
        background: rgba(255, 255, 255, .25);
        transform: scale(1.04);
    }

    /* INFO BOX */
    .study-quote-info-box {
        background: linear-gradient(180deg, #f8fffb 0%, #f5fbf7 100%);
        border: 1px solid #d4edda;
        border-radius: 18px;
        padding: 14px 16px;
        display: flex;
        align-items: center;
        margin-bottom: 16px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, .04);
    }

    .study-quote-info-icon {
        width: 38px;
        height: 38px;
        border-radius: 50%;
        background: #198754;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 13px;
        margin-right: 12px;
        flex-shrink: 0;
        box-shadow: 0 6px 14px rgba(25, 135, 84, .25);
    }

    /* TABLE CARD */
    .study-quote-table-card {
        background: #fff;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 8px 28px rgba(0, 0, 0, .06);
        border: 1px solid #eef1f4;
    }

    .study-quote-table-header {
        padding: 14px 18px;
        border-bottom: 1px solid #eef1f4;
        background: #fff;
    }

    /* TABLE */
    #tableStudyQuotes thead th {
        background: #f8f9fa;
        border: none;
        font-size: 11px;
        font-weight: 700;
        color: #5b6472;
        text-transform: uppercase;
        white-space: nowrap;
        vertical-align: middle;
        padding: 14px 10px;
        letter-spacing: .2px;
    }

    #tableStudyQuotes tbody td {
        padding: 13px 10px;
        vertical-align: middle;
        font-size: 12px;
        white-space: nowrap;
        border-top: 1px solid #f1f3f5;
    }

    #tableStudyQuotes tbody tr:hover {
        background: #f8fff9;
        transition: .2s ease;
    }

    /* BADGES */
    #studyQuoteListModal .badge {
        font-size: 10px;
        font-weight: 700;
        border-radius: 999px;
        padding: .45rem .6rem;
    }

    /* BOTÓN NUEVO */
    #btnNewStudyQuote {
        border-radius: 12px;
        box-shadow: 0 6px 14px rgba(25, 135, 84, .18);
    }

    #btnNewStudyQuote:hover {
        transform: translateY(-1px);
    }

    /* BOTÓN CERRAR FOOTER */
    #studyQuoteListModal .modal-footer {
        background: #fff;
        border-top: 1px solid #eef1f4 !important;
    }

    /* ANIMACIÓN */
    #studyQuoteListModal .modal-dialog {
        transform: scale(.97);
        transition: .2s ease;
    }

    #studyQuoteListModal.show .modal-dialog {
        transform: scale(1);
    }

    /* RESPONSIVE */
    @media (max-width: 991px) {
        #studyQuoteListModal .modal-dialog {
            max-width: 100%;
            margin: .5rem;
        }
    }

    @media (max-width: 767px) {
        .study-quote-list-header {
            padding: 14px 16px;
        }

        .study-quote-info-box {
            padding: 12px;
        }

        .study-quote-table-header {
            padding: 12px 14px;
        }

        #tableStudyQuotes thead th,
        #tableStudyQuotes tbody td {
            white-space: nowrap;
        }
    }
</style>
