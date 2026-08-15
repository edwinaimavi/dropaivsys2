<div class="modal fade petty-approval-modal" id="pendingPettyCashExpensesModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content border-0">
        <div class="modal-header petty-detail-header"><div class="d-flex align-items-center"><span class="petty-detail-header-icon"><i class="fas fa-bell"></i></span><div><small>CONTROL ADMINISTRATIVO</small><h4>Gastos pendientes de aprobación</h4><p>Revisa el sustento antes de aprobar o rechazar.</p></div></div><button type="button" class="close petty-close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body petty-detail-body"><div class="table-responsive"><table class="table petty-detail-table"><thead><tr><th>Fecha</th><th>Caja / Empresa</th><th>Proveedor</th><th>Concepto</th><th>Registrado por</th><th>Comprobante</th><th class="text-right">Importe</th><th>Acciones</th></tr></thead><tbody id="pc_pending_expenses_body"></tbody></table></div></div>
        <div class="modal-footer bg-white"><button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>
<div class="modal fade petty-approval-modal" id="pettyCashExpenseApprovalModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0"><form id="pettyCashExpenseApprovalForm">
        @csrf
        <input type="hidden" id="pca_expense_id"><input type="hidden" id="pca_action">
        <div class="modal-header petty-detail-header"><div class="d-flex align-items-center"><span id="pca_icon" class="petty-detail-header-icon"><i class="fas fa-clipboard-check"></i></span><div><small>CONTROL ADMINISTRATIVO</small><h4 id="pca_title">Revisar gasto pendiente</h4><p>Revisa toda la información y elige una decisión administrativa.</p></div></div><button type="button" class="close petty-close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body petty-detail-body">
            <div id="pca_expense_data" class="petty-approval-expense-grid"></div>
            <div class="petty-approval-documents mt-3"><small>COMPROBANTES</small><div id="pca_documents"></div></div>
            <div class="expense-admin-summary-note mt-3" aria-label="Observación original del gasto">
                <span>OBSERVACIÓN DEL GASTO</span>
                <p id="pca_expense_observation">Sin observación registrada.</p>
            </div>
            <div id="pca_lifted_observation" class="petty-lifted-observation d-none mt-3">
                <div class="petty-lifted-observation-heading">
                    <span>LEVANTAMIENTO DE OBSERVACIÓN</span>
                    <button type="button" id="btnViewApprovalObservationHistory" class="btn btn-link btn-sm">Ver historial completo</button>
                </div>
                <p id="pca_lifted_observation_message"></p>
                <small><i class="fas fa-user-edit mr-1"></i><span id="pca_lifted_observation_user"></span> · <span id="pca_lifted_observation_date"></span></small>
            </div>
            <div class="petty-review-history mt-3"><small>HISTORIAL ADMINISTRATIVO</small><div id="pca_history"></div></div>
            <div id="pca_decision_field" class="form-group mt-3 mb-0 d-none"><label id="pca_observation_label" for="pca_review_observation">Observación de aprobación (opcional)</label><textarea id="pca_review_observation" class="form-control" rows="3"></textarea><small id="pca_observation_help" class="form-text text-muted"></small></div>
        </div>
        <div class="modal-footer bg-white petty-review-actions">
            <button type="button" class="btn btn-light mr-auto" data-dismiss="modal">Cerrar</button>
            @can('admin.petty-cash.expenses.observe')
                <button type="button" class="btn btn-warning selectExpenseReviewAction" data-action="observe"><i class="fas fa-comment-alt mr-1"></i> Observar gasto</button>
            @endcan
            @can('admin.petty-cash.expenses.approve')
                <button type="button" class="btn btn-danger selectExpenseReviewAction" data-action="reject"><i class="fas fa-times mr-1"></i> Rechazar gasto</button>
                <button type="button" class="btn btn-success selectExpenseReviewAction" data-action="approve"><i class="fas fa-check mr-1"></i> Aprobar gasto</button>
            @endcan
            <button id="btnConfirmExpenseApproval" type="submit" class="btn d-none"><i class="fas fa-check mr-1"></i> <span>Confirmar</span></button>
        </div>
    </form></div></div>
</div>
