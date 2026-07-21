<div class="modal fade" id="closeCustomerPurchaseOrderAttentionModal" tabindex="-1" role="dialog"
    aria-labelledby="closeCustomerPurchaseOrderAttentionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow attention-close-content">
            <div class="modal-header border-0 attention-close-header text-white">
                <div class="d-flex align-items-center">
                    <div class="attention-close-header-icon mr-3">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div>
                        <h5 class="modal-title font-weight-bold" id="closeCustomerPurchaseOrderAttentionModalLabel">
                            Cerrar atención del cliente
                        </h5>
                        <small class="text-white-50">
                            Registra el resultado final y adjunta el cargo o sustento correspondiente.
                        </small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="closeCustomerPurchaseOrderAttentionForm" enctype="multipart/form-data">
                <input type="hidden" id="close_attention_order_id">
                <div class="modal-body attention-close-body">
                    <div id="closeAttentionErrors" class="alert alert-danger d-none"></div>

                    <div class="attention-order-summary mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="font-weight-bold mb-0"><i class="fas fa-file-signature text-primary mr-1"></i> Resumen de la orden</h6>
                            <span class="badge badge-success px-3 py-2">ABASTECIDA</span>
                        </div>
                        <div class="row">
                            <div class="col-md-3"><small>Código interno</small><strong id="closeAttentionOrderCode">—</strong></div>
                            <div class="col-md-3"><small>Nro. Orden Compra</small><strong id="closeAttentionPurchaseNumber">—</strong></div>
                            <div class="col-md-3"><small>Cliente</small><strong id="closeAttentionCustomer">—</strong></div>
                            <div class="col-md-3"><small>Sucursal</small><strong id="closeAttentionBranch">—</strong></div>
                            <div class="col-12 mt-2"><small>Total</small><strong id="closeAttentionTotal" class="text-primary">—</strong></div>
                        </div>
                    </div>

                    <div class="attention-form-section mb-3">
                        <h6 class="font-weight-bold mb-3"><i class="fas fa-check-circle text-primary mr-1"></i> Resultado final</h6>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label for="attention_result">RESULTADO DE ATENCIÓN <span class="text-danger">*</span></label>
                            <select id="attention_result" name="result" class="form-control" required>
                                <option value="">Seleccione resultado</option>
                                <option value="attended">ATENDIDO</option>
                                <option value="not_attended">NO ATENDIDO</option>
                            </select>
                            <span class="invalid-feedback"></span>
                        </div>
                        <div class="form-group col-md-6">
                            <label for="attention_closed_at">FECHA DE CIERRE <span class="text-danger">*</span></label>
                            <input type="date" id="attention_closed_at" name="closed_at" class="form-control" required>
                            <span class="invalid-feedback"></span>
                        </div>
                    </div>
                    </div>

                    <div class="form-group">
                        <label for="attention_file">CARGO / SUSTENTO</label>
                        <div id="attentionFileDropzone" class="attention-file-dropzone" tabindex="0">
                            <input type="file" id="attention_file" name="file" class="d-none"
                                accept=".pdf,.jpg,.jpeg,.png">
                            <label for="attention_file" class="mb-0 w-100">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <strong>Selecciona o arrastra el cargo de atención</strong>
                                <span id="attentionFileName">Ningún archivo seleccionado</span>
                                <small>PDF o imagen hasta 10 MB</small>
                            </label>
                        </div>
                        <span class="invalid-feedback"></span>
                    </div>
                    <div class="form-group mb-0">
                        <label for="attention_observation">MOTIVO / OBSERVACIÓN
                            <span id="attentionObservationRequired" class="text-danger d-none">*</span>
                        </label>
                        <textarea id="attention_observation" name="observation" rows="4" class="form-control"
                            placeholder="Detalle el resultado de la atención"></textarea>
                        <small class="form-text text-muted">Obligatorio cuando el resultado es No atendido.</small>
                        <span class="invalid-feedback"></span>
                    </div>
                </div>
                <div class="modal-footer border-0 attention-close-footer">
                    <button type="button" class="btn btn-light border" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cancelar
                    </button>
                    <button type="submit" id="btnSaveAttentionClosure" class="btn btn-success px-4">
                        <i class="fas fa-save mr-1"></i> Guardar cierre
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    #closeCustomerPurchaseOrderAttentionModal .attention-close-content { border-radius: 18px; overflow: hidden; }
    #closeCustomerPurchaseOrderAttentionModal .attention-close-header { background: linear-gradient(135deg, #1666d8, #0d47a1); padding: 20px 24px; }
    #closeCustomerPurchaseOrderAttentionModal .attention-close-header-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.16); font-size: 24px; }
    #closeCustomerPurchaseOrderAttentionModal .attention-close-body { background: #f5f8fc; padding: 22px; }
    #closeCustomerPurchaseOrderAttentionModal .attention-order-summary,
    #closeCustomerPurchaseOrderAttentionModal .attention-form-section { background: #fff; border: 1px solid #e5ebf3; border-radius: 14px; padding: 18px; box-shadow: 0 4px 14px rgba(34,60,90,.05); }
    #closeCustomerPurchaseOrderAttentionModal .attention-order-summary small { display: block; color: #77869a; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    #closeCustomerPurchaseOrderAttentionModal .attention-order-summary strong { display: block; color: #25364a; font-size: 12px; overflow-wrap: anywhere; }
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone { border: 2px dashed #9eb9da; border-radius: 14px; background: #f7fbff; text-align: center; transition: .2s ease; }
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone:hover,
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone.is-dragging { border-color: #0d6efd; background: #eaf3ff; }
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone label { cursor: pointer; padding: 20px; }
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone i { display: block; color: #0d6efd; font-size: 30px; margin-bottom: 8px; }
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone strong,
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone span,
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone small { display: block; }
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone span { color: #52657a; margin-top: 4px; }
    #closeCustomerPurchaseOrderAttentionModal .attention-file-dropzone small { color: #8492a5; }
    #closeCustomerPurchaseOrderAttentionModal .attention-close-footer { background: #fff; padding: 15px 22px; }
</style>
