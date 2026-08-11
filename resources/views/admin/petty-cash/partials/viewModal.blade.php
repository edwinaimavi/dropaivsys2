<div class="modal fade petty-detail-modal petty-cash-detail-modal" id="viewPettyCashModal" tabindex="-1" role="dialog" aria-labelledby="pettyCashDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered petty-detail-dialog" role="document">
        <div class="modal-content border-0">
            <div class="modal-header petty-detail-header">
                <div class="d-flex align-items-center">
                    <span class="petty-detail-header-icon"><i class="fas fa-wallet"></i></span>
                    <div class="petty-detail-heading">
                        <small id="pettyCashDetailTitle">DETALLE DE CAJA CHICA</small>
                        <div class="petty-detail-title-row"><h4 id="pcv_code">-</h4><span id="pcv_status" class="petty-status-badge">-</span></div>
                        <p><span id="pcv_company">-</span><span id="pcv_meta"></span></p>
                    </div>
                </div>
                <button type="button" class="close petty-close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>

            <nav class="petty-detail-tabs">
                <div class="nav nav-pills" role="tablist">
                    <a class="nav-link active" data-toggle="pill" href="#pcv_tab_summary"><i class="fas fa-chart-pie"></i><span>Resumen</span></a>
                    <a class="nav-link" data-toggle="pill" href="#pcv_tab_expenses"><i class="fas fa-receipt"></i><span>Gastos</span><span id="pcv_expenses_tab_count" class="badge petty-tab-count">0</span></a>
                    <a class="nav-link" data-toggle="pill" href="#pcv_tab_replenishments"><i class="fas fa-sync-alt"></i><span>Reposiciones</span><span id="pcv_replenishments_tab_count" class="badge petty-tab-count">0</span></a>
                    <a class="nav-link" data-toggle="pill" href="#pcv_tab_exchanges"><i class="fas fa-exchange-alt"></i><span>Canjes</span><span id="pcv_exchanges_tab_count" class="badge petty-tab-count">0</span></a>
                    <a class="nav-link" data-toggle="pill" href="#pcv_tab_audit"><i class="fas fa-user-shield"></i><span>Auditoría</span></a>
                </div>
            </nav>

            <div class="modal-body petty-detail-body">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="pcv_tab_summary">
                        <section class="petty-financial-overview">
                            <div class="petty-overview-heading"><div><span>RESUMEN FINANCIERO</span><small>Estado consolidado del fondo</small></div><i class="fas fa-chart-line"></i></div>
                            <div id="pcv_summary" class="petty-financial-grid"></div>
                        </section>
                        <div id="pcv_pending_expenses_alert" class="petty-pending-alert d-none"></div>
                        <div class="row">
                            <div class="col-lg-6">
                                <section class="petty-detail-card petty-closure-card h-100">
                                    <div class="petty-detail-card-title"><div><span><i class="fas fa-calendar-check"></i></span><div><h6>Estado y periodo</h6><small>Vigencia de la caja chica</small></div></div></div>
                                    <div id="pcv_closure_info" class="petty-source-detail"></div>
                                </section>
                            </div>
                            <div class="col-lg-6">
                                <section id="pcv_fund_source_section" class="petty-detail-card petty-detail-source h-100 d-none">
                                    <div class="petty-detail-card-title"><div><span><i class="fas fa-university"></i></span><div><h6>Origen del fondo</h6><small>Procedencia del fondo inicial</small></div></div></div>
                                    <div id="pcv_fund_source"></div>
                                </section>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="pcv_tab_expenses">
                        <section class="petty-detail-card mb-0">
                            <div class="petty-tab-toolbar">
                                <div><h6>Gastos registrados</h6><small>Movimientos y control de aprobación documental</small></div>
                                <div>
                                    <button id="btnAddExpenseFromDetail" class="btn btn-sm btn-success addPettyCashExpense d-none"><i class="fas fa-plus mr-1"></i> Registrar gasto</button>
                                    <button id="btnApproveExpensesFromDetail" class="btn btn-sm btn-outline-warning d-none"><i class="fas fa-check-double mr-1"></i> Aprobar pendientes</button>
                                    <button id="btnExchangePettyCashReceipts" class="btn btn-sm btn-outline-success exchangePettyCashReceipts d-none"><i class="fas fa-exchange-alt mr-1"></i> Canjear recibos</button>
                                    <span id="pcv_expenses_count" class="petty-section-count">0 movimientos</span>
                                </div>
                            </div>
                            <div class="table-responsive petty-tab-table"><table class="table petty-detail-table"><thead><tr><th>#</th><th>Fecha</th><th>Comprobante</th><th>Proveedor</th><th>Concepto</th><th class="text-right">Importe</th><th>Aprobación</th><th>Canje</th><th>Vínculo almacén</th><th>Documento</th><th></th></tr></thead><tbody id="pcv_expenses"></tbody></table></div>
                        </section>
                    </div>

                    <div class="tab-pane fade" id="pcv_tab_replenishments">
                        <section class="petty-detail-card mb-0">
                            <div class="petty-tab-toolbar">
                                <div><h6>Reposiciones registradas</h6><small>Fondos restituidos a la caja</small></div>
                                <div><button id="btnReplenishFromDetail" class="btn btn-sm btn-success btn-replenish-petty-cash d-none"><i class="fas fa-plus mr-1"></i> Nueva reposición</button><span id="pcv_replenishments_count" class="petty-section-count">0 movimientos</span></div>
                            </div>
                            <div class="table-responsive petty-tab-table"><table class="table petty-detail-table"><thead><tr><th>Fecha</th><th class="text-right">Monto</th><th>Empresa origen</th><th>Cuenta bancaria</th><th>Observación</th><th>Sustento</th><th>Estado</th></tr></thead><tbody id="pcv_replenishments"></tbody></table></div>
                        </section>
                    </div>

                    <div class="tab-pane fade" id="pcv_tab_exchanges">
                        <section class="petty-detail-card mb-0">
                            <div class="petty-tab-toolbar">
                                <div><h6>Canjes realizados</h6><small>Historial de recibos reemplazados</small></div>
                                <div><button id="btnExchangeReceiptsFromHistory" class="btn btn-sm btn-success exchangePettyCashReceipts d-none"><i class="fas fa-exchange-alt mr-1"></i> Canjear recibos</button><span id="pcv_exchange_history_count" class="petty-section-count">0 canjes</span></div>
                            </div>
                            <div id="pcv_exchange_history" class="petty-exchange-history"></div>
                            <div id="pcv_exchange_empty" class="petty-empty-state d-none"><i class="fas fa-exchange-alt"></i><strong>No hay canjes realizados.</strong><small>Los canjes registrados aparecerán aquí.</small></div>
                        </section>
                    </div>

                    <div class="tab-pane fade" id="pcv_tab_audit">
                        <section class="petty-detail-card">
                            <div class="petty-detail-card-title"><div><span><i class="fas fa-users"></i></span><div><h6>Responsables</h6><small>Custodia y supervisión del fondo</small></div></div></div>
                            <div id="pcv_responsibles" class="row"></div>
                        </section>
                        <section class="petty-detail-card mb-0">
                            <div class="petty-detail-card-title"><div><span><i class="fas fa-history"></i></span><div><h6>Trazabilidad</h6><small>Creación, actualización y cierre</small></div></div></div>
                            <div id="pcv_audit_info" class="petty-audit-grid"></div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
