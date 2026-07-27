<div class="modal fade petty-expense-modal" id="pettyCashExpenseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0">
            <form id="pettyCashExpenseForm" enctype="multipart/form-data">
                <input type="hidden" id="pc_expense_box_id">
                <input type="hidden" id="pc_expense_id">

                <div class="modal-header petty-expense-header">
                    <div class="d-flex align-items-center">
                        <span class="petty-expense-header-icon"><i class="fas fa-receipt"></i></span>
                        <div>
                            <small>COMPROBANTE DE GASTO</small>
                            <h4 id="pcExpenseTitle">Registrar gasto</h4>
                            <p>El gasto quedará pendiente hasta su aprobación administrativa.</p>
                        </div>
                    </div>
                    <button type="button" class="close petty-close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
                </div>

                <div class="modal-body petty-expense-body">
                    <div class="row petty-expense-layout">
                        <div class="col-lg-4 mb-3 mb-lg-0">
                            <aside class="petty-receipts-panel">
                                <div class="petty-receipts-heading">
                                    <span><i class="fas fa-paperclip"></i></span>
                                    <div><h6>Comprobantes</h6><small>Adjunta sustentos del gasto</small></div>
                                    <b id="pce_receipts_count">0</b>
                                </div>
                                <label class="petty-receipts-dropzone" for="pce_documents">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <strong>Seleccionar comprobantes</strong>
                                    <small>PDF, JPG, JPEG o PNG · máx. 10 MB c/u</small>
                                    <input type="file" name="documents[]" id="pce_documents" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                </label>
                                <div id="pce_documents_preview" class="petty-receipts-list" aria-live="polite"></div>
                            </aside>
                        </div>

                        <div class="col-lg-8">
                            <section class="petty-expense-card">
                                <div class="petty-expense-section-title">
                                    <span><i class="fas fa-file-invoice"></i></span>
                                    <div><h6>Datos del comprobante</h6><small>Identificación del documento que sustenta el gasto</small></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-3"><label>Fecha *</label><input type="date" name="expense_date" id="pce_expense_date" class="form-control" required></div>
                                    <div class="form-group col-md-3"><label>Tipo comprobante</label><select name="document_type" id="pce_document_type" class="form-control"><option value="">Seleccione</option><option>FACTURA</option><option>BOLETA</option><option>RECIBO</option><option>TICKET</option><option>OTRO</option></select></div>
                                    <div class="form-group col-md-3"><label>Serie</label><input name="document_series" id="pce_document_series" class="form-control text-uppercase" maxlength="20" placeholder="F001"></div>
                                    <div class="form-group col-md-3"><label>Correlativo</label><input name="document_correlative" id="pce_document_correlative" class="form-control text-uppercase" maxlength="50" placeholder="000123"></div>
                                </div>
                            </section>

                            <section class="petty-expense-card">
                                <div class="petty-expense-section-title">
                                    <span><i class="fas fa-building"></i></span>
                                    <div><h6>Datos del proveedor</h6><small>Consulta automática mediante el RUC</small></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4"><label>RUC proveedor</label><input name="supplier_ruc" id="pce_supplier_ruc" class="form-control" maxlength="11" inputmode="numeric" placeholder="11 dígitos"></div>
                                    <div class="form-group col-md-8"><label>Proveedor *</label><input name="supplier_name" id="pce_supplier_name" class="form-control text-uppercase" required placeholder="Razón social"></div>
                                </div>
                            </section>

                            <section class="petty-expense-card mb-0">
                                <div class="petty-expense-section-title">
                                    <span><i class="fas fa-coins"></i></span>
                                    <div><h6>Detalle del gasto</h6><small>Solo afectará el saldo cuando sea aprobado</small></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-8"><label>Concepto *</label><input name="concept" id="pce_concept" class="form-control text-uppercase" required placeholder="Descripción del gasto"></div>
                                    <div class="form-group col-md-4"><label>Importe *</label><div class="petty-expense-amount"><span>S/</span><input type="number" name="amount" id="pce_amount" class="form-control" min="0.01" step="0.01" required></div></div>
                                </div>
                                <div class="form-group mb-0"><label>Observación</label><textarea name="observation" id="pce_observation" class="form-control" rows="2" placeholder="Información adicional si corresponde"></textarea></div>
                            </section>
                        </div>
                    </div>
                </div>

                <div class="modal-footer petty-expense-footer">
                    <button type="button" class="btn btn-light petty-btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cancelar</button>
                    <button class="btn btn-success petty-btn-primary" type="submit"><i class="fas fa-save mr-1"></i> Guardar gasto</button>
                </div>
            </form>
        </div>
    </div>
</div>
