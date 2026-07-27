<div class="modal fade petty-receipt-exchange-modal" id="pettyCashReceiptExchangeModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="pettyCashReceiptExchangeForm" enctype="multipart/form-data">
                <input type="hidden" id="pcre_box_id">
                <div class="modal-header petty-detail-header">
                    <div class="d-flex align-items-center">
                        <span class="petty-detail-header-icon"><i class="fas fa-exchange-alt"></i></span>
                        <div class="petty-detail-heading"><small>CONTROL DOCUMENTAL</small><h4 class="mb-0">Canjear recibos</h4><p>Seleccione los recibos que serán reemplazados por una factura o boleta.</p></div>
                    </div>
                    <button type="button" class="close petty-close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <section class="petty-detail-card">
                        <div class="petty-detail-card-title"><div><span><i class="fas fa-receipt"></i></span><div><h6>Recibos pendientes</h6><small>Solo se muestran recibos aprobados y pendientes de canje</small></div></div></div>
                        <div class="table-responsive">
                            <table class="table petty-detail-table">
                                <thead><tr><th></th><th>Fecha</th><th>Recibo</th><th>Proveedor</th><th>Concepto</th><th class="text-right">Importe</th><th>Aprobación</th></tr></thead>
                                <tbody id="pcre_receipts"></tbody>
                            </table>
                        </div>
                        <div id="pcre_supplier_warning" class="alert alert-warning d-none mt-2 mb-0">Está seleccionando recibos de diferentes proveedores.</div>
                    </section>
                    <div class="row">
                        <div class="col-lg-8">
                            <section class="petty-detail-card h-100">
                                <div class="petty-detail-card-title"><div><span><i class="fas fa-file-invoice"></i></span><div><h6>Comprobante real</h6><small>Documento que reemplaza los recibos seleccionados</small></div></div></div>
                                <div class="form-row">
                                    <div class="form-group col-md-3"><label>Fecha de canje *</label><input type="date" name="exchange_date" id="pcre_date" class="form-control" required></div>
                                    <div class="form-group col-md-3"><label>Tipo *</label><select name="document_type" class="form-control" required><option value="">Seleccione</option><option value="FACTURA">Factura</option><option value="BOLETA">Boleta</option></select></div>
                                    <div class="form-group col-md-3"><label>Serie *</label><input name="document_series" class="form-control text-uppercase" maxlength="20" required></div>
                                    <div class="form-group col-md-3"><label>Correlativo *</label><input name="document_correlative" class="form-control text-uppercase" maxlength="50" required></div>
                                </div>
                                <div class="form-group mb-0"><label>Observación</label><textarea name="observation" class="form-control" rows="2" placeholder="Agregue una observación si corresponde..."></textarea></div>
                            </section>
                        </div>
                        <div class="col-lg-4">
                            <section class="petty-detail-card h-100">
                                <div class="petty-exchange-total"><small>TOTAL A CANJEAR</small><strong id="pcre_total">S/ 0.00</strong><span>Calculado desde los recibos seleccionados</span></div>
                                <label class="petty-source-upload mt-2" for="pcre_documents"><i class="fas fa-cloud-upload-alt"></i><span><strong>Adjuntar comprobante real</strong><small>PDF, JPG, JPEG o PNG hasta 10 MB</small></span><input type="file" name="documents[]" id="pcre_documents" accept=".pdf,.jpg,.jpeg,.png" multiple></label>
                                <div id="pcre_document_previews" class="petty-source-previews"></div>
                            </section>
                        </div>
                    </div>
                </div>
                <div class="modal-footer petty-replenishment-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Guardar canje</button>
                </div>
            </form>
        </div>
    </div>
</div>
