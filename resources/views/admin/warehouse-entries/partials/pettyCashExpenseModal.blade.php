<div class="modal fade warehouse-entry-petty-cash-modal" id="warehouseEntryPettyCashModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header warehouse-entry-section-header align-items-center">
                <div class="d-flex align-items-center"><span class="warehouse-entry-petty-cash-icon"><i class="fas fa-cash-register"></i></span><div><h5 class="modal-title mb-0">Jalar gastos desde Caja Chica</h5><small class="text-muted">Solo gastos activos, aprobados, no vinculados y provenientes de cajas abiertas.</small></div></div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="card border-0 warehouse-entry-petty-cash-filters mb-3"><div class="card-body py-3"><div class="form-row align-items-end">
                    <div class="form-group col-lg-3 col-md-6"><label>PROVEEDOR / RESPONSABLE</label><input id="wePettyCashProvider" class="form-control form-control-sm" placeholder="Nombre o RUC"></div>
                    <div class="form-group col-lg-2 col-md-3"><label>N.º DE RECIBO</label><input id="wePettyCashReceiptNumber" class="form-control form-control-sm" placeholder="Serie o número"></div>
                    <div class="form-group col-lg-3 col-md-6"><label>CONCEPTO</label><input id="wePettyCashSearch" class="form-control form-control-sm" placeholder="Buscar en el concepto"></div>
                    <div class="form-group col-lg-2 col-md-3"><label>FECHA DESDE</label><input type="date" id="wePettyCashDateFrom" class="form-control form-control-sm"></div>
                    <div class="form-group col-lg-2 col-md-3"><label>FECHA HASTA</label><input type="date" id="wePettyCashDateTo" class="form-control form-control-sm"></div>
                    <div class="form-group col-lg-2 col-md-3"><label>IMPORTE</label><input type="number" min="0.01" step="0.01" id="wePettyCashAmount" class="form-control form-control-sm"></div>
                    <div class="form-group col-lg-2 col-md-3"><label>ESTADO</label><select id="wePettyCashExchangeStatus" class="form-control form-control-sm"><option value="all">Todos</option><option value="NO_APLICA">Documento directo</option><option value="PENDIENTE_CANJE">Pendiente de canje</option><option value="CANJEADO">Canjeado</option></select></div>
                    <div class="col-12 text-right"><button type="button" id="btnFilterPettyCashExpenses" class="btn btn-info btn-sm"><i class="fas fa-search mr-1"></i>Buscar</button><button type="button" id="btnClearPettyCashExpenseFilters" class="btn btn-light btn-sm ml-1">Limpiar</button></div>
                </div></div></div>
                <div class="alert alert-info py-2"><i class="fas fa-info-circle mr-1"></i>Seleccione uno o varios gastos y defina su tipo de costo. El importe, responsable y documento se conservarán desde Caja Chica.</div>
                <div class="table-responsive warehouse-entry-petty-cash-table"><table class="table table-sm table-hover mb-0"><thead><tr><th class="text-center"><input type="checkbox" id="wePettyCashSelectAll"></th><th>Fecha</th><th>Recibo / Caja</th><th>Proveedor / responsable</th><th>Concepto</th><th class="text-right">Importe</th><th>Estado</th><th>Comprobante</th><th>Tipo de costo</th></tr></thead><tbody id="wePettyCashExpenseRows"><tr><td colspan="9" class="text-center text-muted py-4">Use los filtros para consultar gastos disponibles.</td></tr></tbody></table></div>
            </div>
            <div class="modal-footer justify-content-between"><small id="wePettyCashSelectionSummary" class="text-muted">0 gastos seleccionados</small><div><button type="button" class="btn btn-light btn-sm" data-dismiss="modal">Cancelar</button><button type="button" id="btnConfirmPettyCashExpenses" class="btn btn-info btn-sm"><i class="fas fa-link mr-1"></i>Vincular seleccionados</button></div></div>
        </div>
    </div>
</div>
