<div class="modal fade petty-expense-detail-modal" id="pettyCashExpenseDetailModal" tabindex="-1" role="dialog" aria-labelledby="pced_title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0">
            <div class="modal-header petty-detail-header">
                <div class="d-flex align-items-center">
                    <span class="petty-detail-header-icon"><i class="fas fa-eye"></i></span>
                    <div>
                        <small>CONSULTA DEL MOVIMIENTO</small>
                        <h4 id="pced_title">Detalle del gasto</h4>
                        <p>Información, comprobantes e historial administrativo</p>
                    </div>
                </div>
                <button type="button" class="close petty-close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body petty-detail-body">
                <div id="pced_loading" class="petty-expense-detail-loading">
                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    Cargando detalle del gasto...
                </div>
                <div id="pced_content" class="d-none">
                    <nav class="petty-expense-detail-tabs-wrap" aria-label="Secciones del detalle del gasto">
                        <div class="nav nav-pills petty-expense-detail-tabs" role="tablist">
                            <a class="nav-link active" id="pced_summary_tab" data-toggle="pill" href="#pced_summary_panel" role="tab" aria-controls="pced_summary_panel" aria-selected="true"><i class="fas fa-receipt"></i><span>Resumen</span></a>
                            <a class="nav-link" id="pced_documents_tab" data-toggle="pill" href="#pced_documents_panel" role="tab" aria-controls="pced_documents_panel" aria-selected="false"><i class="fas fa-paperclip"></i><span>Comprobantes</span><b id="pced_documents_count">0</b></a>
                            <a class="nav-link" id="pced_history_tab" data-toggle="pill" href="#pced_history_panel" role="tab" aria-controls="pced_history_panel" aria-selected="false"><i class="fas fa-history"></i><span>Historial</span><b id="pced_history_count">0</b></a>
                            <a class="nav-link" id="pced_approval_tab" data-toggle="pill" href="#pced_approval_panel" role="tab" aria-controls="pced_approval_panel" aria-selected="false"><i class="fas fa-check-circle"></i><span>Canje / Aprobación</span></a>
                        </div>
                    </nav>
                    <div class="tab-content petty-expense-detail-tab-content">
                        <div class="tab-pane fade show active" id="pced_summary_panel" role="tabpanel" aria-labelledby="pced_summary_tab">
                            <section class="petty-expense-detail-section mb-0">
                                <div class="petty-expense-detail-heading"><div><small>DATOS DEL GASTO</small><h6>Información registrada</h6></div><span id="pced_status"></span></div>
                                <div id="pced_data" class="petty-expense-detail-grid"></div>
                                <div class="expense-admin-summary-note mt-2">
                                    <span>OBSERVACIÓN REGISTRADA POR EL USUARIO</span>
                                    <p id="pced_observation">Sin observación registrada.</p>
                                </div>
                            </section>
                        </div>
                        <div class="tab-pane fade" id="pced_documents_panel" role="tabpanel" aria-labelledby="pced_documents_tab">
                            <section class="petty-expense-detail-section mb-0">
                                <div class="petty-expense-detail-heading"><div><small>COMPROBANTES ADJUNTOS</small><h6>Archivos de sustento</h6></div><i class="fas fa-paperclip"></i></div>
                                <div id="pced_documents" class="petty-expense-detail-documents"></div>
                            </section>
                        </div>
                        <div class="tab-pane fade" id="pced_history_panel" role="tabpanel" aria-labelledby="pced_history_tab">
                            <section class="petty-expense-detail-section mb-0">
                                <div class="petty-expense-detail-heading"><div><small>HISTORIAL ADMINISTRATIVO</small><h6>Seguimiento del gasto</h6></div><i class="fas fa-history"></i></div>
                                <div id="pced_admin_empty" class="petty-expense-detail-empty d-none">Este gasto aún no tiene movimientos administrativos.</div>
                                <div id="pced_timeline" class="petty-expense-admin-timeline"></div>
                            </section>
                        </div>
                        <div class="tab-pane fade" id="pced_approval_panel" role="tabpanel" aria-labelledby="pced_approval_tab">
                            <div id="pced_approval_exchange" class="petty-expense-approval-grid"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white"><button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>
