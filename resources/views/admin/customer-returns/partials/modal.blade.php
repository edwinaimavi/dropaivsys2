<div class="modal fade" id="customerReturnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <div>
                    <h5 class="modal-title mb-0"><i class="fas fa-undo-alt mr-2"></i><span id="customerReturnModalTitle">Registrar devolución de cliente</span></h5>
                    <small id="customerReturnModalSubtitle">La SAL original permanecerá intacta</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="customerReturnForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="customerReturnId">
                    <input type="hidden" id="customerReturnDispatchId">
                    <input type="hidden" id="customerReturnIdempotencyKey">
                    <div id="customerReturnErrors" class="alert alert-danger d-none"></div>
                    <div class="customer-return-hero mb-3">
                        <div><small>OC CLIENTE</small><strong id="crOrder">—</strong></div>
                        <div><small>CLIENTE</small><strong id="crCustomer">—</strong></div>
                        <div><small>SAL RELACIONADA</small><strong id="crDispatch">—</strong></div>
                        <div><small>FECHA DESPACHO</small><strong id="crDispatchDate">—</strong></div>
                        <div><small>ALMACÉN (MISMO DE LA SAL)</small><strong id="crWarehouse">—</strong></div>
                        <div><small>GUÍA / SUSTENTO</small><strong id="crGuide">—</strong></div>
                    </div>
                    <div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle mr-2"></i><span id="crSafetyWarning">Confirme únicamente productos aptos para reintegrarse al stock disponible.</span></div>
                    <div id="crInvoiceWarning" class="alert alert-danger d-none">
                        <strong><i class="fas fa-file-invoice-dollar mr-1"></i> Mercadería facturada</strong>
                        <p class="mb-1 mt-1" id="crInvoiceWarningText"></p><div id="crInvoiceList" class="small"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 form-group"><label>Fecha de devolución *</label><input id="crReturnDate" type="datetime-local" class="form-control" required></div>
                        <div class="col-md-3 form-group"><label>Recibido por *</label><select id="crReceivedBy" class="form-control" required></select></div>
                        <div class="col-md-3 form-group"><label>Motivo *</label><select id="crReason" class="form-control" required></select></div>
                        <div class="col-md-3 form-group"><label>Descripción del motivo</label><input id="crReasonDescription" class="form-control" maxlength="2000" placeholder="Obligatoria si elige Otro"></div>
                        <div class="col-12 form-group"><label>Observación general</label><textarea id="crObservation" class="form-control" rows="2" maxlength="3000"></textarea></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover customer-return-items-table">
                            <thead><tr><th>ARTÍCULO</th><th>LOTE</th><th>VENCIMIENTO</th><th>DESPACHADO</th><th>YA DEVUELTO</th><th>DISPONIBLE</th><th style="min-width:150px">CANTIDAD A DEVOLVER</th></tr></thead>
                            <tbody id="crItemsBody"></tbody>
                        </table>
                    </div>
                    @can('devoluciones_clientes.documentos')
                    <div class="card bg-light border-0 mt-3"><div class="card-body py-3">
                        <div id="crExistingDocuments" class="mb-3"></div>
                        <label><i class="fas fa-paperclip mr-1"></i> Documento inicial opcional</label>
                        <div class="form-row"><div class="col-md-3"><select id="crDocumentType" class="form-control"><option value="">Tipo</option></select></div><div class="col-md-5"><input id="crDocumentFile" type="file" class="form-control-file mt-2"></div><div class="col-md-4"><input id="crDocumentDescription" class="form-control" placeholder="Descripción"></div></div>
                    </div></div>
                    @endcan
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button><button type="submit" id="btnSaveCustomerReturn" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Guardar borrador</button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="customerReturnDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-dark text-white"><div><h5 class="mb-0"><i class="fas fa-clipboard-check mr-2"></i><span id="crdNumber">DEV</span></h5><small>Trazabilidad de devolución</small></div><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body"><div id="crdContent"><div class="text-center py-5"><i class="fas fa-spinner fa-spin"></i></div></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button></div>
    </div></div>
</div>

<div class="modal fade" id="customerReturnDocumentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg"><div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-primary text-white"><h5 class="mb-0"><i class="fas fa-paperclip mr-2"></i>Documentos — <span id="crDocsNumber"></span></h5><button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body"><div id="crDocsList" class="mb-3"></div>
            @can('devoluciones_clientes.documentos')
            <form id="customerReturnDocumentsForm"><div class="form-row"><div class="col-md-3"><select id="crDocsType" class="form-control" required></select></div><div class="col-md-5"><input id="crDocsFile" type="file" class="form-control-file mt-2" required></div><div class="col-md-4"><input id="crDocsDescription" class="form-control" placeholder="Descripción"></div></div><button class="btn btn-primary btn-sm mt-3"><i class="fas fa-upload mr-1"></i>Adjuntar</button></form>
            @endcan
        </div>
    </div></div>
</div>
@once
@push('css')
<style>.customer-return-hero{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.customer-return-hero>div{padding:12px;border:1px solid #dfe7ef;border-radius:10px;background:#f8fafc}.customer-return-hero small,.customer-return-hero strong{display:block}.customer-return-hero small{font-size:10px;color:#64748b;font-weight:700}.customer-return-hero strong{margin-top:4px;color:#172b4d}.customer-return-items-table thead th{white-space:nowrap;font-size:11px}.cr-detail-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.cr-detail-metrics>div{padding:14px;border-radius:10px;background:#f3f7fb}.cr-document{display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid #eee}.cr-detail-actions{gap:.5rem}.customer-return-reverse-summary{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.customer-return-reverse-summary>div{padding:9px 11px;border:1px solid #e1e8ee;border-radius:9px;background:#f8fafc}.customer-return-reverse-summary>.cr-summary-wide{grid-column:1/-1}.customer-return-reverse-summary small,.customer-return-reverse-summary strong{display:block}.customer-return-reverse-summary small{color:#64748b;font-size:9px;font-weight:800;letter-spacing:.04em}.customer-return-reverse-alert .swal2-textarea{width:calc(100% - 2rem);min-height:120px;margin:10px 1rem 0;padding:12px;border:1px solid #ced7df;border-radius:10px;resize:vertical;font-size:.9rem}.customer-return-reverse-alert .swal2-input-label{margin-top:14px;color:#334155;font-size:.72rem;font-weight:800;letter-spacing:.04em}.customer-return-reverse-alert .swal2-validation-message{margin:10px 1rem 0;border-radius:8px}@media(max-width:768px){.customer-return-hero,.cr-detail-metrics,.customer-return-reverse-summary{grid-template-columns:1fr}.customer-return-reverse-summary>.cr-summary-wide{grid-column:auto}}</style>
@endpush
@endonce
