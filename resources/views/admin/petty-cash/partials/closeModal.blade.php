<div class="modal fade" id="pettyCashCloseModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0 rounded-lg overflow-hidden">
            <div class="modal-header petty-modal-header">
                <div class="d-flex align-items-center">
                    <div class="mr-3 rounded-circle d-flex align-items-center justify-content-center bg-light" style="width:42px;height:42px">
                        <i class="fas fa-balance-scale text-success"></i>
                    </div>
                    <div><h5 class="modal-title mb-0 font-weight-bold">Cierre y cuadre</h5><small>CONFIRMACIÓN DE CAJA CHICA</small></div>
                </div>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="pcc_box_id">
                <div id="pcc_summary" class="petty-detail-summary"></div>
                <div id="pcc_pending_expenses_warning" class="alert alert-danger d-none"></div>
                <div id="pcc_pending_link_warning" class="alert alert-warning d-none"></div>
                <div id="pcc_pending_warning" class="alert alert-warning d-none"></div>
                <div class="form-group"><label for="pcc_close_observation">Observación de cierre</label><textarea id="pcc_close_observation" class="form-control" rows="3" maxlength="2000" placeholder="Motivo o comentario de la decisión de cierre..."></textarea></div>
                <div class="alert alert-warning mb-0"><i class="fas fa-exclamation-triangle mr-1"></i> Después del cierre no podrá modificar los gastos registrados.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                <button type="button" id="btnConfirmClosePettyCash" class="btn btn-success"><i class="fas fa-lock mr-1"></i> Cerrar caja</button>
            </div>
        </div>
    </div>
</div>
