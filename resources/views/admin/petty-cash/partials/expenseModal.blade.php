<div class="modal fade" id="pettyCashExpenseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content border-0 petty-modal-content">
        <form id="pettyCashExpenseForm" enctype="multipart/form-data">
            <input type="hidden" id="pc_expense_box_id"><input type="hidden" id="pc_expense_id">
            <div class="modal-header petty-modal-header"><div><small>COMPROBANTE DE GASTO</small><h4 id="pcExpenseTitle">Registrar gasto</h4><p>El saldo se recalculará automáticamente.</p></div><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group col-md-4"><label>Fecha *</label><input type="date" name="expense_date" id="pce_expense_date" class="form-control" required></div>
                    <div class="form-group col-md-4"><label>Tipo comprobante</label><select name="document_type" id="pce_document_type" class="form-control"><option value="">Seleccione</option><option>FACTURA</option><option>BOLETA</option><option>RECIBO</option><option>TICKET</option><option>OTRO</option></select></div>
                    <div class="form-group col-md-4"><label>N° comprobante</label><input name="document_number" id="pce_document_number" class="form-control"></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label>RUC proveedor</label><input name="supplier_ruc" id="pce_supplier_ruc" class="form-control" maxlength="11"></div>
                    <div class="form-group col-md-8"><label>Proveedor *</label><input name="supplier_name" id="pce_supplier_name" class="form-control text-uppercase" required></div>
                </div>
                <div class="form-row"><div class="form-group col-md-8"><label>Concepto *</label><input name="concept" id="pce_concept" class="form-control text-uppercase" required></div><div class="form-group col-md-4"><label>Importe *</label><input type="number" name="amount" id="pce_amount" class="form-control" min="0.01" step="0.01" required></div></div>
                <div class="form-group"><label>Observación</label><textarea name="observation" id="pce_observation" class="form-control" rows="2"></textarea></div>
                <div class="form-group mb-0"><label>Comprobante adjunto</label><input type="file" name="document" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png"><small class="text-muted">PDF o imagen, máximo 10 MB.</small></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button><button class="btn btn-success" type="submit"><i class="fas fa-save mr-1"></i> Guardar gasto</button></div>
        </form>
    </div></div>
</div>
