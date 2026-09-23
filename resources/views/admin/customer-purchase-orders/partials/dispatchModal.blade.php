<div class="modal fade" id="warehouseDispatchModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered warehouse-dispatch-dialog" role="document">
        <div class="modal-content border-0 shadow-lg warehouse-dispatch-modal-content">
            <div class="modal-header warehouse-dispatch-header border-0">
                <div class="d-flex align-items-center min-width-0">
                    <span class="warehouse-dispatch-header-icon mr-3"><i class="fas fa-truck-loading"></i></span>
                    <div class="min-width-0">
                        <h5 class="modal-title mb-0" id="warehouseDispatchModalTitle">Preparar salida de almacén</h5>
                        <small id="warehouseDispatchModalSubtitle">Guarde la preparación como borrador antes de confirmar la salida física.</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>

            <form id="warehouseDispatchForm" enctype="multipart/form-data" class="warehouse-dispatch-form">
                <input type="hidden" id="warehouse_dispatch_order_id">
                <input type="hidden" id="warehouse_dispatch_id">
                <input type="hidden" id="warehouse_dispatch_idempotency_key" name="idempotency_key">
                <div class="modal-body warehouse-dispatch-body">
                    <div id="warehouseDispatchErrors" class="alert alert-danger d-none"></div>
                    <div class="row no-gutters warehouse-dispatch-workspace">
                        <aside class="col-lg-4 warehouse-dispatch-sidebar-wrap">
                            <div class="warehouse-dispatch-sidebar">
                                <div class="warehouse-dispatch-identity">
                                    <span class="warehouse-dispatch-order-icon"><i class="fas fa-dolly-flatbed"></i></span>
                                    <div class="min-width-0">
                                        <small>Orden de Compra Cliente</small>
                                        <h5 id="warehouseDispatchOrderCode">—</h5>
                                        <strong id="warehouseDispatchCustomer">—</strong>
                                        <span id="warehouseDispatchBranch"><i class="fas fa-map-marker-alt mr-1"></i>Sin sucursal</span>
                                    </div>
                                    <span id="warehouseDispatchOrderStatus" class="badge badge-secondary">REGISTRADA</span>
                                </div>

                                <div class="warehouse-dispatch-metrics" aria-label="Resumen operativo">
                                    <div><span>Solicitado</span><strong id="warehouseDispatchRequestedTotal">0.00</strong></div>
                                    <div class="is-entered"><span>Ingresado</span><strong id="warehouseDispatchEnteredTotal">0.00</strong></div>
                                    <div class="is-dispatched"><span>Despachado</span><strong id="warehouseDispatchDispatchedTotal">0.00</strong></div>
                                    <div class="is-pending"><span>Pendiente</span><strong id="warehouseDispatchPendingTotal">0.00</strong></div>
                                </div>

                                <div class="warehouse-dispatch-form-card">
                                    <div class="warehouse-dispatch-section-title"><i class="fas fa-clipboard-list"></i><div><strong>Datos de la salida</strong><small>Complete la información general del despacho.</small></div></div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6"><label>Fecha y hora <span class="text-danger">*</span></label><input type="datetime-local" class="form-control form-control-sm" id="warehouse_dispatch_date" name="dispatch_date" required></div>
                                        <div class="form-group col-md-6"><label>Responsable <span class="text-danger">*</span></label><select class="form-control form-control-sm" id="warehouse_dispatch_responsible_user_id" name="responsible_user_id" required></select></div>
                                    </div>
                                    <div class="form-group warehouse-dispatch-warehouse-field">
                                        <label><i class="fas fa-warehouse mr-1"></i>Almacén de salida <span class="text-danger">*</span></label>
                                        <select class="form-control" id="warehouse_dispatch_warehouse_id" name="warehouse_id" required></select>
                                        <small>Seleccione primero el almacén para consultar sus lotes y saldos.</small>
                                    </div>
                                    <div class="form-group"><label>Destino</label><input type="text" class="form-control form-control-sm" id="warehouse_dispatch_destination" name="destination" maxlength="255" placeholder="Dirección, establecimiento, área o lugar de entrega"></div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6"><label>Tipo de sustento</label><select class="form-control form-control-sm" name="document_type" id="warehouse_dispatch_document_type"><option value="">Sin documento</option><option>GUÍA</option><option>ACTA</option><option>CONSTANCIA DE ENTREGA</option><option>OTRO</option></select></div>
                                        <div class="form-group col-md-6"><label>Número</label><input class="form-control form-control-sm text-uppercase" name="document_number" id="warehouse_dispatch_document_number" placeholder="Ej. GR-001-25"></div>
                                    </div>
                                    @can('admin.customer-purchase-orders.dispatch.documents.manage')
                                        <div class="warehouse-dispatch-documents-section">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div><label class="mb-0">Documentos del despacho</label><small>Puede adjuntar varios archivos ahora o agregarlos después.</small></div>
                                                <button type="button" class="btn btn-xs btn-outline-success addWarehouseDispatchDocument" data-target="#warehouseDispatchCreateDocuments">+ Agregar documento</button>
                                            </div>
                                            <div id="warehouseDispatchCreateDocuments" class="warehouse-dispatch-document-rows" data-name="documents"></div>
                                        </div>
                                    @endcan
                                    <div class="form-group mb-0"><label>Observación</label><textarea class="form-control form-control-sm" name="observation" rows="2" placeholder="Indicaciones o referencia del despacho"></textarea></div>
                                </div>
                            </div>
                        </aside>

                        <section class="col-lg-8 warehouse-dispatch-main">
                            <div class="warehouse-dispatch-panel warehouse-dispatch-items-panel">
                                <div class="warehouse-dispatch-panel-head">
                                    <div><span>Preparación de salida</span><h6>Artículos pendientes de despacho</h6><small>Distribuya cada artículo entre uno o varios lotes disponibles.</small></div>
                                    <i class="fas fa-boxes"></i>
                                </div>
                                <div id="warehouseDispatchWarehouseHelp" class="warehouse-dispatch-guidance is-info" aria-label="Seleccione un almacén para consultar saldos disponibles"><i class="fas fa-info-circle"></i><div><strong>Seleccione un almacén</strong><span>Seleccione un almacén para ver los lotes disponibles</span></div></div>
                                <div id="warehouseDispatchItemsBody" class="warehouse-dispatch-items-list" aria-label="Asignaciones de stock y lotes"></div>
                            </div>

                            <div class="warehouse-dispatch-panel warehouse-dispatch-history-panel">
                                <div class="warehouse-dispatch-panel-head">
                                    <div><span>Trazabilidad</span><h6>Historial de salidas</h6><small>Despachos registrados y anulaciones de esta orden.</small></div>
                                    <i class="fas fa-history"></i>
                                </div>
                                <div id="warehouseDispatchHistorySummary" class="warehouse-dispatch-history-summary d-none"></div>
                                <div id="warehouseDispatchHistory" class="warehouse-dispatch-history"></div>
                            </div>
                        </section>
                    </div>
                </div>

                <div class="modal-footer warehouse-dispatch-footer border-0">
                    <button type="button" class="btn btn-light border px-4" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <div class="ml-auto text-right">
                        <small id="warehouseDispatchFooterHelp" class="d-none d-md-block text-muted mb-1">Guardar el borrador no descuenta stock ni genera Kardex.</small>
                        <div class="d-flex align-items-center justify-content-end">
                            <button type="submit" class="btn btn-outline-success px-4" id="btnSaveWarehouseDispatch" disabled><i class="fas fa-save mr-1"></i><span id="warehouseDispatchSaveLabel">Guardar borrador</span></button>
                            <button type="button" class="btn btn-success px-4 ml-2 d-none" id="btnConfirmWarehouseDispatch" disabled><i class="fas fa-check-circle mr-1"></i>Confirmar salida</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="warehouseDispatchDocumentsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header warehouse-dispatch-documents-header border-0">
                <div><small>EXPEDIENTE DOCUMENTAL</small><h5 class="modal-title mb-0">Documentos de <span id="warehouseDispatchDocumentsNumber">—</span></h5></div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>
            <div class="modal-body bg-light">
                <div id="warehouseDispatchDocumentsErrors" class="alert alert-danger d-none"></div>
                <div id="warehouseDispatchDocumentsList" class="warehouse-dispatch-documents-list"></div>
                @can('admin.customer-purchase-orders.dispatch.documents.manage')
                    <form id="warehouseDispatchDocumentsForm" enctype="multipart/form-data" class="card border-0 shadow-sm mt-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div><strong class="d-block">Agregar documentos</strong><small class="text-muted">PDF, imágenes u ofimática segura. Máximo 10 MB por archivo.</small></div>
                                <button type="button" class="btn btn-sm btn-outline-success addWarehouseDispatchDocument" data-target="#warehouseDispatchManageDocuments">+ Agregar documento</button>
                            </div>
                            <div id="warehouseDispatchManageDocuments" class="warehouse-dispatch-document-rows" data-name="documents"></div>
                            <div class="text-right mt-3"><button type="submit" id="btnSaveWarehouseDispatchDocuments" class="btn btn-success" disabled><i class="fas fa-upload mr-1"></i>Adjuntar documentos</button></div>
                        </div>
                    </form>
                @endcan
            </div>
            <div class="modal-footer border-0"><button type="button" class="btn btn-light border px-4" data-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>

<style>
    #warehouseDispatchModal{--wd-green:#16805e;--wd-dark:#165641;--wd-soft:#edf8f3;--wd-border:#dcebe4;--wd-text:#263b33;--wd-muted:#6c7d75}
    #warehouseDispatchModal .warehouse-dispatch-modal-content{height:calc(100vh - 32px);max-height:920px;overflow:hidden;border-radius:17px;background:#f4f8f6}.warehouse-dispatch-form{display:flex;flex:1 1 auto;min-height:0;flex-direction:column}.warehouse-dispatch-header{flex:0 0 auto;padding:13px 18px;color:#fff;background:linear-gradient(135deg,var(--wd-green),var(--wd-dark))}.warehouse-dispatch-header-icon{display:grid;flex:0 0 44px;width:44px;height:44px;place-items:center;border-radius:13px;background:rgba(255,255,255,.14);font-size:19px}.warehouse-dispatch-header h5{font-size:17px;font-weight:700}.warehouse-dispatch-header small{color:rgba(255,255,255,.8);font-size:10.5px}.warehouse-dispatch-header .close{opacity:.86;text-shadow:none}
    #warehouseDispatchModal .warehouse-dispatch-body{flex:1 1 auto;min-height:0;padding:0;overflow:hidden}.warehouse-dispatch-workspace{height:100%}.warehouse-dispatch-sidebar-wrap{height:100%;padding:13px 7px 13px 13px}.warehouse-dispatch-sidebar{height:100%;padding:13px;overflow-y:auto;border:1px solid var(--wd-border);border-radius:15px;background:#fff;box-shadow:0 7px 22px rgba(30,70,54,.05)}
    #warehouseDispatchModal .warehouse-dispatch-identity{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:10px;padding:11px;border:1px solid #dbeae3;border-radius:13px;background:linear-gradient(135deg,#f7fcf9,#edf8f3)}.warehouse-dispatch-order-icon{display:grid;width:44px;height:44px;place-items:center;border-radius:12px;color:#fff;background:linear-gradient(135deg,var(--wd-green),var(--wd-dark));font-size:18px}.warehouse-dispatch-identity small,.warehouse-dispatch-identity strong,.warehouse-dispatch-identity span{display:block}.warehouse-dispatch-identity small{color:var(--wd-muted);font-size:8px;font-weight:700;text-transform:uppercase}.warehouse-dispatch-identity h5{margin:1px 0;color:var(--wd-text);font-size:16px;font-weight:800}.warehouse-dispatch-identity strong{color:#3f554b;font-size:10.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.warehouse-dispatch-identity span:not(.warehouse-dispatch-order-icon):not(.badge){margin-top:2px;color:var(--wd-muted);font-size:9px}.warehouse-dispatch-identity .badge{align-self:start;margin-top:0;font-size:8px;white-space:nowrap}
    #warehouseDispatchModal .warehouse-dispatch-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin:9px 0}.warehouse-dispatch-metrics>div{padding:9px 7px;border:1px solid #e0eae5;border-radius:10px;background:#fafcfb}.warehouse-dispatch-metrics span{display:block;color:var(--wd-muted);font-size:7.5px;font-weight:700;text-transform:uppercase}.warehouse-dispatch-metrics strong{display:block;margin-top:3px;color:var(--wd-text);font-size:13px;font-weight:800}.warehouse-dispatch-metrics .is-entered{border-color:#bee1ed;background:#f1fbfe}.warehouse-dispatch-metrics .is-dispatched{border-color:#c7e6d5;background:#f2fbf6}.warehouse-dispatch-metrics .is-pending{border-color:#f0d1c3;background:#fff6f2}.warehouse-dispatch-metrics .is-pending strong{color:#bd4f31}
    #warehouseDispatchModal .warehouse-dispatch-form-card{padding:12px;border:1px solid #e0e9e4;border-radius:13px;background:#fbfdfc}.warehouse-dispatch-section-title{display:flex;align-items:center;gap:9px;margin-bottom:11px}.warehouse-dispatch-section-title>i{display:grid;width:32px;height:32px;place-items:center;border-radius:9px;color:var(--wd-green);background:var(--wd-soft)}.warehouse-dispatch-section-title strong,.warehouse-dispatch-section-title small{display:block}.warehouse-dispatch-section-title strong{color:var(--wd-text);font-size:12px}.warehouse-dispatch-section-title small{color:var(--wd-muted);font-size:8.5px}.warehouse-dispatch-form-card label{margin-bottom:4px;color:#56675f;font-size:8px;font-weight:700;text-transform:uppercase}.warehouse-dispatch-form-card .form-group{margin-bottom:9px}.warehouse-dispatch-form-card .form-control{border-color:#dce6e1;border-radius:8px;font-size:10.5px}.warehouse-dispatch-form-card .form-control:focus{border-color:#76bca3;box-shadow:0 0 0 .15rem rgba(22,128,94,.12)}.warehouse-dispatch-warehouse-field{padding:10px;border:1px solid #b9dfd0;border-radius:11px;background:var(--wd-soft)}.warehouse-dispatch-warehouse-field label{color:var(--wd-dark)}.warehouse-dispatch-warehouse-field small{display:block;margin-top:4px;color:#537568;font-size:8.5px}.warehouse-dispatch-documents-section{margin-bottom:9px;padding:9px;border:1px dashed #bcd8cc;border-radius:10px;background:#f4fbf7}.warehouse-dispatch-documents-section small{display:block;color:#647a70;font-size:8px}.warehouse-dispatch-document-row{display:grid;grid-template-columns:minmax(130px,.8fr) minmax(150px,1fr) 34px;gap:6px;margin-bottom:6px;padding:7px;border:1px solid #dce8e2;border-radius:9px;background:#fff}.warehouse-dispatch-document-row .document-file{grid-column:1/-1}.warehouse-dispatch-document-row .form-control{height:31px}.warehouse-dispatch-document-row input[type=file]{height:auto;padding:4px;font-size:9px}.warehouse-dispatch-document-row .removeWarehouseDispatchDocument{width:34px;height:31px;padding:0}
    #warehouseDispatchModal .warehouse-dispatch-main{display:flex;height:100%;min-height:0;padding:13px 13px 13px 7px;overflow:hidden;flex-direction:column;gap:10px}.warehouse-dispatch-panel{display:flex;min-height:0;overflow:hidden;border:1px solid var(--wd-border);border-radius:15px;background:#fff;box-shadow:0 7px 22px rgba(30,70,54,.05);flex-direction:column}.warehouse-dispatch-items-panel{flex:1 1 60%}.warehouse-dispatch-history-panel{flex:1 1 40%}.warehouse-dispatch-panel-head{display:flex;flex:0 0 auto;padding:12px 14px;align-items:center;justify-content:space-between;border-bottom:1px solid #e8f0ec}.warehouse-dispatch-panel-head span{display:block;color:var(--wd-green);font-size:8px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.warehouse-dispatch-panel-head h6{margin:1px 0;color:var(--wd-text);font-size:14px;font-weight:700}.warehouse-dispatch-panel-head small{color:var(--wd-muted);font-size:9.5px}.warehouse-dispatch-panel-head>i{display:grid;width:37px;height:37px;place-items:center;border-radius:11px;color:var(--wd-green);background:var(--wd-soft);font-size:15px}
    #warehouseDispatchModal .warehouse-dispatch-guidance{display:flex;flex:0 0 auto;align-items:center;gap:10px;margin:9px 12px 0;padding:9px 11px;border:1px solid #cfe1ef;border-radius:11px;color:#315f82;background:#f0f8fd}.warehouse-dispatch-guidance>i{font-size:18px}.warehouse-dispatch-guidance strong,.warehouse-dispatch-guidance span{display:block}.warehouse-dispatch-guidance strong{font-size:10.5px}.warehouse-dispatch-guidance span{font-size:9px}.warehouse-dispatch-guidance.is-warning{border-color:#eedca5;color:#7b5a00;background:#fff9e8}.warehouse-dispatch-guidance.is-success{border-color:#c6e4d5;color:#236448;background:#f1faf5}.warehouse-dispatch-guidance.is-danger{border-color:#efc9c9;color:#8b3434;background:#fff4f4}
    #warehouseDispatchModal .warehouse-dispatch-items-list{flex:1 1 auto;min-height:0;margin:9px 12px 12px;padding:9px;overflow-y:auto;border:1px solid #e0e9e4;border-radius:11px;background:#f6faf8}.warehouse-dispatch-item-block{margin-bottom:9px;padding:11px;border:1px solid #dce8e2;border-radius:12px;background:#fff;box-shadow:0 3px 10px rgba(30,70,54,.04)}.warehouse-dispatch-item-block:last-child{margin-bottom:0}.warehouse-dispatch-item-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding-bottom:9px;border-bottom:1px solid #eaf1ed}.warehouse-dispatch-item-title{min-width:0}.warehouse-dispatch-item-title strong{display:block;overflow:hidden;color:#294339;font-size:11.5px;text-overflow:ellipsis;white-space:nowrap}.warehouse-dispatch-item-title small{color:var(--wd-muted);font-size:8.5px}.warehouse-dispatch-item-metrics{display:grid;grid-template-columns:repeat(3,minmax(70px,1fr));gap:6px}.warehouse-dispatch-item-metrics span,.warehouse-dispatch-assignment-totals span{display:block;color:#718178;font-size:7px;font-weight:700;text-transform:uppercase}.warehouse-dispatch-item-metrics strong,.warehouse-dispatch-assignment-totals strong{display:block;margin-top:2px;color:#31483e;font-size:10.5px}.warehouse-dispatch-item-metrics .is-pending strong,.warehouse-dispatch-assignment-totals .is-pending strong{color:#bd4f31}.warehouse-dispatch-assignments-title{display:flex;align-items:center;justify-content:space-between;gap:8px;margin:9px 0 6px;color:#557066;font-size:8px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}.warehouse-dispatch-assignment-row{display:grid;grid-template-columns:minmax(210px,2.2fr) minmax(92px,.8fr) minmax(82px,.7fr) minmax(88px,.75fr) 32px;align-items:end;gap:7px;margin-bottom:6px;padding:7px;border:1px solid #e2ebe6;border-radius:9px;background:#fbfdfc}.warehouse-dispatch-assignment-field label{display:block;margin-bottom:3px;color:#74837c;font-size:7px;font-weight:700;text-transform:uppercase}.warehouse-dispatch-assignment-field .form-control{height:31px;border-color:#d9e5df;border-radius:7px;font-size:9px}.warehouse-dispatch-assignment-static{display:flex;min-height:31px;align-items:center;color:#3e554b;font-size:9px;font-weight:700}.warehouse-dispatch-assignment-row .remove-dispatch-assignment{width:32px;height:31px;padding:0;border-radius:7px}.warehouse-dispatch-add{padding:3px 8px;border-radius:7px;font-size:8px;font-weight:700}.warehouse-dispatch-assignment-summary{display:flex;align-items:center;justify-content:flex-end;gap:18px;margin-top:8px;padding-top:8px;border-top:1px dashed #dce7e1}.warehouse-dispatch-assignment-totals{display:flex;gap:16px;text-align:right}.warehouse-dispatch-stock-empty{display:inline-flex;margin-top:4px;padding:3px 6px;border-radius:999px;color:#9a4b31;background:#fff0ea;font-size:8px;font-weight:700}
    #warehouseDispatchModal .warehouse-dispatch-history-summary{flex:0 0 auto;margin:9px 12px 0;padding:8px 10px;border-left:3px solid var(--wd-green);border-radius:8px;color:#315f4e;background:var(--wd-soft);font-size:9.5px}.warehouse-dispatch-history{flex:1 1 auto;min-height:0;padding:9px 12px 12px;overflow-y:auto}.warehouse-dispatch-history .dispatch-history-card{border-color:#dfe9e4!important;border-radius:11px!important;background:#fbfdfc!important}.warehouse-dispatch-history .dispatch-history-card:last-child{margin-bottom:0!important}.warehouse-dispatch-empty{display:flex;min-height:120px;align-items:center;justify-content:center;flex-direction:column;border:1px dashed #cdded6;border-radius:12px;color:var(--wd-muted);background:#fafcfb;text-align:center}.warehouse-dispatch-empty i{margin-bottom:7px;color:#a9bbb2;font-size:26px}.warehouse-dispatch-empty strong{color:#53675e;font-size:11.5px}.warehouse-dispatch-empty span{margin-top:2px;font-size:9px}
    #warehouseDispatchModal .warehouse-dispatch-footer{flex:0 0 auto;padding:9px 17px;border-top:1px solid #e2ebe6!important;background:#fff;box-shadow:0 -4px 14px rgba(29,63,50,.035)}.warehouse-dispatch-footer .btn{border-radius:9px;font-weight:600}.warehouse-dispatch-footer small{font-size:8.5px}.warehouse-dispatch-footer #btnSaveWarehouseDispatch:disabled{cursor:not-allowed;filter:grayscale(.25);opacity:.52}
    @media(min-width:1200px){#warehouseDispatchModal .warehouse-dispatch-dialog{width:calc(100vw - 48px);max-width:1540px}}
    @media(max-width:991px){#warehouseDispatchModal .warehouse-dispatch-dialog{margin:8px auto}#warehouseDispatchModal .warehouse-dispatch-modal-content{height:calc(100vh - 16px)}#warehouseDispatchModal .warehouse-dispatch-body{overflow-y:auto}#warehouseDispatchModal .warehouse-dispatch-workspace{height:auto}#warehouseDispatchModal .warehouse-dispatch-sidebar-wrap{height:auto;padding:12px 12px 5px}#warehouseDispatchModal .warehouse-dispatch-sidebar{height:auto;overflow:visible}#warehouseDispatchModal .warehouse-dispatch-main{height:auto;min-height:650px;padding:7px 12px 14px;overflow:visible}}
    @media(max-width:767px){#warehouseDispatchModal .warehouse-dispatch-header-icon{display:none}#warehouseDispatchModal .warehouse-dispatch-identity{grid-template-columns:auto minmax(0,1fr)}#warehouseDispatchModal .warehouse-dispatch-identity .badge{grid-column:1/-1;justify-self:start}#warehouseDispatchModal .warehouse-dispatch-metrics{grid-template-columns:repeat(2,1fr)}#warehouseDispatchModal .warehouse-dispatch-main{min-height:720px}.warehouse-dispatch-footer>div small{display:none!important}.warehouse-dispatch-item-head{flex-direction:column}.warehouse-dispatch-item-metrics{width:100%}.warehouse-dispatch-assignment-row{grid-template-columns:1fr 1fr}.warehouse-dispatch-assignment-row .warehouse-dispatch-stock-field{grid-column:1/-1}.warehouse-dispatch-assignment-row .remove-dispatch-assignment{align-self:end}.warehouse-dispatch-assignment-summary{align-items:flex-end;flex-direction:column}.warehouse-dispatch-assignment-totals{width:100%;justify-content:space-between;text-align:left}}
</style>

<style>
    #warehouseDispatchDocumentsModal .warehouse-dispatch-documents-header{color:#fff;background:linear-gradient(135deg,#16805e,#165641)}
    #warehouseDispatchDocumentsModal .warehouse-dispatch-documents-header small{font-size:8px;font-weight:700;letter-spacing:.08em;opacity:.8}
    #warehouseDispatchDocumentsModal .warehouse-dispatch-documents-list{max-height:310px;overflow-y:auto}
    #warehouseDispatchDocumentsModal .warehouse-dispatch-document-entry{display:flex;align-items:center;gap:12px;margin-bottom:8px;padding:11px;border:1px solid #deebe5;border-radius:11px;background:#fff}
    #warehouseDispatchDocumentsModal .warehouse-dispatch-document-entry>i{display:grid;flex:0 0 38px;width:38px;height:38px;place-items:center;border-radius:10px;color:#16805e;background:#edf8f3}
    #warehouseDispatchDocumentsModal .warehouse-dispatch-document-entry .document-meta{min-width:0;flex:1}
    #warehouseDispatchDocumentsModal .warehouse-dispatch-document-entry strong,#warehouseDispatchDocumentsModal .warehouse-dispatch-document-entry small{display:block}
    #warehouseDispatchDocumentsModal .warehouse-dispatch-document-entry strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    #warehouseDispatchDocumentsModal .warehouse-dispatch-document-row{grid-template-columns:minmax(160px,.8fr) minmax(220px,1.2fr) 34px}
    @media(max-width:575px){#warehouseDispatchDocumentsModal .warehouse-dispatch-document-entry{align-items:flex-start;flex-wrap:wrap}#warehouseDispatchDocumentsModal .warehouse-dispatch-document-entry .document-actions{width:100%;text-align:right}#warehouseDispatchDocumentsModal .warehouse-dispatch-document-row{grid-template-columns:1fr 34px}#warehouseDispatchDocumentsModal .warehouse-dispatch-document-row .document-description{grid-column:1/-1}}
</style>
