<div class="modal fade" id="pettyCashReplenishmentModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0 rounded-lg overflow-hidden">
            <form id="pettyCashReplenishmentForm" enctype="multipart/form-data">
                <input type="hidden" id="pcr_box_id">
                <div class="modal-header petty-modal-header">
                    <div><small>GESTIÓN FINANCIERA</small><h4 class="mb-0">Reposición de Caja Chica</h4><p>Devuelve a caja el importe utilizado sin modificar el fondo aprobado.</p></div>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="petty-detail-section">
                        <div class="d-flex justify-content-between flex-wrap mb-3">
                            <div><small class="text-muted">CAJA</small><h5 id="pcr_code" class="font-weight-bold mb-0">-</h5></div>
                            <div class="text-right"><small class="text-muted">EMPRESA</small><div id="pcr_company" class="font-weight-bold">-</div></div>
                        </div>
                        <div id="pcr_summary" class="petty-detail-summary"></div>
                        <div id="pcr_no_pending" class="alert alert-info mb-0 d-none">
                            <i class="fas fa-info-circle mr-1"></i>
                            Esta caja no tiene monto pendiente de reposición.
                        </div>
                    </div>
                    <div class="petty-form-card">
                        <h6><i class="fas fa-sync-alt text-success mr-1"></i> Datos de la reposición</h6>
                        <div class="form-row">
                            <div class="form-group col-md-4"><label>Fecha de reposición *</label><input type="date" name="replenishment_date" id="pcr_date" class="form-control" required></div>
                            <div class="form-group col-md-4"><label>Monto a reponer *</label><input type="number" name="amount" id="pcr_amount" class="form-control" min="0.01" step="0.01" required></div>
                            <div class="form-group col-md-4"><label>Método de pago *</label><select name="payment_method" class="form-control" required><option value="">Seleccione</option><option value="CASH">Efectivo</option><option value="TRANSFER">Transferencia</option><option value="YAPE">Yape</option><option value="PLIN">Plin</option><option value="DEPOSIT">Depósito</option><option value="OTHER">Otro</option></select></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4"><label>Banco</label><select name="bank_id" class="form-control"><option value="">Seleccione</option>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->short_name ?? $bank->description }}</option>@endforeach</select></div>
                            <div class="form-group col-md-4"><label>Cuenta</label><input name="bank_account" class="form-control"></div>
                            <div class="form-group col-md-4"><label>N.º operación / referencia</label><input name="reference_number" class="form-control"></div>
                        </div>
                        <div class="form-group"><label>Observación</label><textarea name="observation" class="form-control" rows="2"></textarea></div>
                        <div class="form-group mb-0"><label>Archivo sustento</label><input type="file" name="document" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png"><small class="text-muted">PDF o imagen, máximo 10 MB.</small></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button><button id="btnSavePettyCashReplenishment" class="btn btn-success" type="submit"><i class="fas fa-save mr-1"></i> Guardar reposición</button></div>
            </form>
        </div>
    </div>
</div>
