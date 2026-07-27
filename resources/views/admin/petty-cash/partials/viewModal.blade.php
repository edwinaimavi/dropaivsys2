<div class="modal fade petty-detail-modal petty-cash-detail-modal" id="viewPettyCashModal" tabindex="-1" role="dialog" aria-labelledby="pettyCashDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable petty-detail-dialog" role="document">
        <div class="modal-content border-0">
            <div class="modal-header petty-detail-header">
                <div class="d-flex align-items-center">
                    <span class="petty-detail-header-icon"><i class="fas fa-wallet"></i></span>
                    <div class="petty-detail-heading">
                        <small id="pettyCashDetailTitle">DETALLE DE CAJA CHICA</small>
                        <div class="petty-detail-title-row">
                            <h4 id="pcv_code">-</h4>
                            <span id="pcv_status" class="petty-status-badge">-</span>
                        </div>
                        <p><span id="pcv_company">-</span><span id="pcv_meta"></span></p>
                    </div>
                </div>
                <button type="button" class="close petty-close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>

            <div class="modal-body petty-detail-body">
                <section class="petty-financial-overview">
                    <div class="petty-overview-heading">
                        <div>
                            <span>RESUMEN FINANCIERO</span>
                            <small>Estado consolidado del fondo</small>
                        </div>
                        <i class="fas fa-chart-line" aria-hidden="true"></i>
                    </div>
                    <div id="pcv_summary" class="petty-financial-grid"></div>
                </section>
                <div id="pcv_pending_expenses_alert" class="petty-pending-alert d-none"></div>

                <section id="pcv_closure_section" class="petty-detail-card petty-closure-card">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-calendar-check"></i></span><div><h6>Estado de vigencia</h6><small>La caja permanece abierta hasta decisión de gerencia</small></div></div>
                    </div>
                    <div id="pcv_closure_info" class="petty-source-detail"></div>
                </section>

                <section id="pcv_fund_source_section" class="petty-detail-card petty-detail-source d-none">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-university"></i></span><div><h6>Origen del fondo</h6><small>Procedencia del fondo entregado para la apertura</small></div></div>
                    </div>
                    <div id="pcv_fund_source"></div>
                </section>

                <section class="petty-detail-card">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-receipt"></i></span><div><h6>Gastos registrados</h6><small>Movimientos que reducen el saldo disponible</small></div></div>
                        <div>
                            <button id="btnExchangePettyCashReceipts" type="button" class="btn btn-sm btn-outline-success d-none"><i class="fas fa-exchange-alt mr-1"></i> Canjear recibos</button>
                            <span id="pcv_expenses_count" class="petty-section-count">0 movimientos</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table petty-detail-table">
                            <thead><tr><th>#</th><th>Fecha</th><th>Comprobante</th><th>Proveedor</th><th>Concepto</th><th class="text-right">Importe</th><th>Estado de aprobación</th><th>Canje</th><th>Documento</th><th></th></tr></thead>
                            <tbody id="pcv_expenses"></tbody>
                        </table>
                    </div>
                </section>

                <section id="pcv_exchange_history_section" class="petty-detail-card d-none">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-exchange-alt"></i></span><div><h6>Canjes realizados</h6><small>Historial documental de recibos reemplazados</small></div></div>
                        <span id="pcv_exchange_history_count" class="petty-section-count">0 canjes</span>
                    </div>
                    <div id="pcv_exchange_history" class="petty-exchange-history"></div>
                </section>

                <section class="petty-detail-card">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-sync-alt"></i></span><div><h6>Reposiciones registradas</h6><small>Fondos restituidos a la caja chica</small></div></div>
                        <span id="pcv_replenishments_count" class="petty-section-count">0 movimientos</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table petty-detail-table">
                            <thead><tr><th>Fecha</th><th class="text-right">Monto</th><th>Método</th><th>Operación</th><th>Observación</th><th>Sustento</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody id="pcv_replenishments"></tbody>
                        </table>
                    </div>
                </section>

                <section class="petty-detail-card mb-0">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-user-shield"></i></span><div><h6>Responsables</h6><small>Custodia y supervisión del fondo</small></div></div>
                        <span class="petty-section-count">2 asignados</span>
                    </div>
                    <div id="pcv_responsibles" class="row"></div>
                </section>
            </div>
        </div>
    </div>
</div>
