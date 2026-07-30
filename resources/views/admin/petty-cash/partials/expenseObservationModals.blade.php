<div class="modal fade petty-approval-modal" id="observedPettyCashExpensesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0">
            <div class="modal-header petty-detail-header">
                <div class="d-flex align-items-center">
                    <span class="petty-detail-header-icon"><i class="fas fa-exclamation-circle"></i></span>
                    <div>
                        <small>CORRECCIONES PENDIENTES</small>
                        <h4>Gastos observados por corregir</h4>
                        <p>Corrige la información solicitada para volver a enviarla a aprobación.</p>
                    </div>
                </div>
                <button type="button" class="close petty-close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body petty-detail-body">
                <div class="table-responsive">
                    <table class="table petty-detail-table">
                        <thead><tr><th>Fecha</th><th>Caja</th><th>Proveedor</th><th>Concepto</th><th class="text-right">Importe</th><th>Observación</th><th>Acciones</th></tr></thead>
                        <tbody id="pc_observed_expenses_body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white"><button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>

<div class="modal fade petty-observation-detail-modal" id="pettyCashObservationDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0">
            <div class="modal-header petty-detail-header">
                <div class="d-flex align-items-center">
                    <span class="petty-detail-header-icon"><i class="fas fa-comment-alt"></i></span>
                    <div><small>TRAZABILIDAD DEL GASTO</small><h4>Historial de observación del gasto</h4></div>
                </div>
                <button type="button" class="close petty-close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body petty-detail-body">
                <div id="pc_observation_current_status" class="mb-3"></div>
                <div id="pc_observation_expense_summary" class="petty-approval-expense-grid"></div>
                <div id="pc_observation_timeline" class="petty-observation-timeline mt-3"></div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
                <button id="btnCorrectObservedExpense" type="button" class="btn btn-warning"><i class="fas fa-edit mr-1"></i> Corregir gasto</button>
            </div>
        </div>
    </div>
</div>
