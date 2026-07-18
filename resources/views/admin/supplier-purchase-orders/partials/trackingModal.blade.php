@can('admin.supplier-purchase-orders.trackings.index')
<div class="modal fade spo-tracking-modal" id="supplierPurchaseOrderTrackingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0">
            <div class="modal-header spo-tracking-header">
                <div>
                    <span class="spo-tracking-kicker"><i class="fas fa-route mr-1"></i> Control de transporte</span>
                    <h4 class="modal-title">Seguimiento log&iacute;stico</h4>
                    <p class="mb-0">Informaci&oacute;n de traslado sin movimientos de inventario.</p>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div class="spo-tracking-summary">
                    <div><small>ORDEN</small><strong id="spoTrackingOrderCode">-</strong></div>
                    <div><small>PROVEEDOR</small><strong id="spoTrackingSupplier">-</strong></div>
                    <div><small>REGISTRO</small><strong id="spoTrackingCreatedAt">-</strong></div>
                    <div><small>ESTADO ACTUAL</small><span id="spoTrackingCurrentStatus" class="spo-current-badge">Sin seguimiento</span></div>
                </div>

                <div class="spo-tracking-layout">
                    <section class="spo-tracking-history">
                        <div class="spo-section-title"><div><small>TRAZABILIDAD</small><h5>Ruta de la mercader&iacute;a</h5></div><i class="fas fa-map-marked-alt"></i></div>
                        <div id="spoTrackingLoading" class="spo-tracking-loading"><i class="fas fa-circle-notch fa-spin"></i> Cargando seguimiento...</div>
                        <div id="spoTrackingTimeline" class="spo-tracking-timeline"></div>
                        <div id="spoWarehouseSuggestion" class="spo-warehouse-suggestion d-none">
                            <i class="fas fa-warehouse"></i>
                            <div><strong>Mercader&iacute;a marcada como recibida</strong><span>Registra el ingreso de almac&eacute;n para generar stock.</span></div>
                            @can('admin.warehouse-entries.store')
                                <a href="{{ route('admin.warehouse-entries.index') }}" class="btn btn-sm btn-success">Registrar ingreso</a>
                            @endcan
                        </div>
                    </section>

                    @can('admin.supplier-purchase-orders.trackings.store')
                    <aside class="spo-tracking-form-panel">
                        <div class="spo-section-title"><div><small>NUEVO EVENTO</small><h5>Actualizar seguimiento</h5></div><i class="fas fa-plus-circle"></i></div>
                        <form id="supplierPurchaseOrderTrackingForm" enctype="multipart/form-data">
                            <input type="hidden" id="spo_tracking_order_id">
                            <div class="form-group"><label>Estado log&iacute;stico *</label><select class="form-control" name="status" id="spo_tracking_status" required></select><small class="invalid-feedback" data-error="status"></small></div>
                            <div class="form-row">
                                <div class="form-group col-md-6"><label>Fecha y hora</label><input type="datetime-local" class="form-control" name="event_date" id="spo_tracking_event_date"><small class="invalid-feedback" data-error="event_date"></small></div>
                                <div class="form-group col-md-6"><label>Llegada estimada</label><input type="date" class="form-control" name="estimated_date"><small class="invalid-feedback" data-error="estimated_date"></small></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-6"><label>Courier / agencia</label><input type="text" class="form-control" name="carrier_name" maxlength="150" placeholder="Ej. Shalom"><small class="invalid-feedback" data-error="carrier_name"></small></div>
                                <div class="form-group col-md-6"><label>Nro. tracking / gu&iacute;a</label><input type="text" class="form-control" name="tracking_number" maxlength="100"><small class="invalid-feedback" data-error="tracking_number"></small></div>
                            </div>
                            <div class="form-group"><label>Ubicaci&oacute;n actual</label><input type="text" class="form-control" name="location" maxlength="150" placeholder="Ciudad, agencia o almac&eacute;n"><small class="invalid-feedback" data-error="location"></small></div>
                            <div class="form-group"><label>Observaci&oacute;n</label><textarea class="form-control" name="description" rows="3" maxlength="1000"></textarea><small class="invalid-feedback" data-error="description"></small></div>
                            <div class="form-group"><label>Archivo opcional</label><div class="spo-file-control"><i class="fas fa-paperclip"></i><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><small class="text-muted">PDF o imagen, m&aacute;ximo 5 MB.</small><small class="invalid-feedback" data-error="document"></small></div>
                            <button class="btn btn-success btn-block spo-save-tracking" type="submit"><i class="fas fa-save mr-1"></i> Guardar seguimiento</button>
                        </form>
                    </aside>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
@endcan
