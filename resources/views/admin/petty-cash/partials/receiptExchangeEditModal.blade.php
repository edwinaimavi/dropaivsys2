<div class="modal fade petty-settlement-edit-modal" id="pettyCashSettlementEditModal" tabindex="-1" role="dialog" aria-labelledby="pcse_title" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <form id="pettyCashSettlementEditForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="pcse_update_url">
                <div class="modal-header">
                    <div><small class="text-uppercase">Caja Chica · Rendiciones</small><h5 class="modal-title" id="pcse_title"><i class="fas fa-file-signature mr-2"></i>Editar rendición</h5></div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="form-group col-md-4"><label>Tipo de documento</label><select name="document_type" id="pcse_document_type" class="form-control" required><option value="FACTURA">Factura</option><option value="BOLETA">Boleta</option><option value="RECIBO_HONORARIOS">Recibo por honorarios</option><option value="OTRO_OFICIAL">Otro oficial</option></select></div>
                        <div class="form-group col-md-4"><label>Serie</label><input name="series" id="pcse_series" class="form-control text-uppercase" maxlength="20" required></div>
                        <div class="form-group col-md-4"><label>Correlativo</label><input name="number" id="pcse_number" class="form-control text-uppercase" maxlength="50" required></div>
                        <div class="form-group col-md-4"><label>Fecha de emisión</label><input type="date" name="issue_date" id="pcse_issue_date" class="form-control" required></div>
                        <div class="form-group col-md-4"><label>RUC del emisor</label><input name="issuer_ruc" id="pcse_issuer_ruc" class="form-control" inputmode="numeric" maxlength="11" placeholder="11 dígitos"></div>
                        <div class="form-group col-md-4"><label>Importe</label><input type="number" name="amount" id="pcse_amount" class="form-control text-right" min="0.01" step="0.01" required></div>
                        <div class="form-group col-12"><label>Razón social / nombre del emisor</label><input name="issuer_name" id="pcse_issuer_name" class="form-control text-uppercase" maxlength="255" required></div>
                        <div class="form-group col-12"><label>Concepto</label><textarea name="concept" id="pcse_concept" class="form-control" rows="2" maxlength="500" required></textarea></div>
                        <div class="form-group col-12"><label>Observación</label><textarea name="observation" id="pcse_observation" class="form-control" rows="2" maxlength="1000" placeholder="Opcional"></textarea></div>
                        <div class="form-group col-12 mb-0">
                            <label>Archivo sustentatorio</label>
                            <div class="petty-settlement-file-box"><div><i class="fas fa-paperclip mr-2"></i><strong id="pcse_current_file">Sin archivo adjunto</strong><small>Si no selecciona otro archivo, se conservará el actual.</small></div><input type="file" name="file" id="pcse_file" accept=".pdf,.jpg,.jpeg,.png,.webp"></div>
                            <div class="custom-control custom-checkbox mt-2"><input type="checkbox" class="custom-control-input" name="remove_file" value="1" id="pcse_remove_file"><label class="custom-control-label text-danger" for="pcse_remove_file">Quitar archivo actual</label></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i>Guardar cambios</button></div>
            </form>
        </div>
    </div>
</div>
