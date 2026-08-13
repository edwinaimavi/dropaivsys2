@can('admin.supplier-purchase-orders.trackings.index')
<div class="modal fade spo-tracking-modal" id="supplierPurchaseOrderTrackingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0">
            <div class="modal-header spo-tracking-header">
                <div class="spo-tracking-header-main">
                    <span class="spo-tracking-header-icon"><i class="fas fa-route"></i></span>
                    <div>
                        <span class="spo-tracking-kicker">Panel log&iacute;stico</span>
                        <h4 class="modal-title">Seguimiento log&iacute;stico</h4>
                        <p class="mb-0">Control de traslado, agencia y recepci&oacute;n de mercader&iacute;a.</p>
                    </div>
                </div>
                <div class="spo-tracking-header-status">
                    <small>ESTADO ACTUAL</small>
                    <span id="spoTrackingCurrentStatus" class="spo-current-badge">Sin seguimiento</span>
                </div>
                <button type="button" class="close spo-tracking-close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <div class="spo-tracking-summary">
                    <div class="spo-summary-card">
                        <span class="spo-summary-icon"><i class="fas fa-file-invoice"></i></span>
                        <div><small>ORDEN</small><strong id="spoTrackingOrderCode">-</strong></div>
                    </div>
                    <div class="spo-summary-card">
                        <span class="spo-summary-icon"><i class="fas fa-building"></i></span>
                        <div><small>PROVEEDOR</small><strong id="spoTrackingSupplier">-</strong></div>
                    </div>
                    <div class="spo-summary-card">
                        <span class="spo-summary-icon"><i class="far fa-clock"></i></span>
                        <div><small>REGISTRO</small><strong id="spoTrackingCreatedAt">-</strong></div>
                    </div>
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
                                <a href="{{ route('admin.warehouse-entries.index') }}" id="spoRegisterWarehouseEntry"
                                    data-base-url="{{ route('admin.warehouse-entries.index') }}"
                                    class="btn btn-sm btn-success">Registrar ingreso</a>
                            @endcan
                        </div>
                    </section>

                    @can('admin.supplier-purchase-orders.trackings.store')
                    <aside class="spo-tracking-form-panel">
                        <div class="spo-section-title"><div><small>NUEVO EVENTO</small><h5>Actualizar seguimiento</h5></div><i class="fas fa-plus-circle"></i></div>
                        <form id="supplierPurchaseOrderTrackingForm" enctype="multipart/form-data">
                            <input type="hidden" id="spo_tracking_order_id">
                            <div class="spo-form-section">
                                <div class="spo-form-section-label"><i class="far fa-calendar-alt"></i><span>Estado y fechas</span></div>
                                <div class="form-group"><label>Estado log&iacute;stico *</label><select class="form-control" name="status" id="spo_tracking_status" required></select><small class="invalid-feedback" data-error="status"></small></div>
                                <div class="form-row">
                                    <div class="form-group col-md-6"><label>Fecha y hora</label><input type="datetime-local" class="form-control" name="event_date" id="spo_tracking_event_date"><small class="invalid-feedback" data-error="event_date"></small></div>
                                    <div class="form-group col-md-6"><label>Llegada estimada</label><input type="date" class="form-control" name="estimated_date"><small class="invalid-feedback" data-error="estimated_date"></small></div>
                                </div>
                            </div>
                            <div class="spo-form-section">
                                <div class="spo-form-section-label"><i class="fas fa-truck"></i><span>Transporte</span></div>
                                <div class="form-group spo-tracking-field-block">
                                    <label>Courier / agencia</label>
                                    <div class="input-group spo-tracking-agency-control">
                                        <select class="form-control" name="shipping_agency_id" id="spo_tracking_shipping_agency_id">
                                            <option value="">Seleccione courier o agencia</option>
                                            @foreach ($shippingAgencies as $shippingAgency)
                                                <option value="{{ $shippingAgency->id }}" data-ruc="{{ $shippingAgency->ruc }}">
                                                    {{ $shippingAgency->trade_name ?? $shippingAgency->business_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @can('admin.shipping-agencies.store')
                                            <div class="input-group-append">
                                                <button type="button" id="btnQuickShippingAgencyForTracking"
                                                    class="btn btn-success" title="Agregar agencia">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        @endcan
                                    </div>
                                    <small class="invalid-feedback" data-error="shipping_agency_id"></small>
                                </div>
                                <div class="form-group spo-tracking-field-block mb-0">
                                    <label>Nro. tracking / gu&iacute;a</label>
                                    <div class="spo-tracking-input-icon">
                                        <i class="fas fa-barcode" aria-hidden="true"></i>
                                        <input type="text" class="form-control" name="tracking_number"
                                            maxlength="100" placeholder="Ingrese nro. de tracking o gu&iacute;a">
                                    </div>
                                    <small class="invalid-feedback" data-error="tracking_number"></small>
                                </div>
                            </div>
                            <div class="spo-form-section">
                                <div class="spo-form-section-label"><i class="fas fa-map-marker-alt"></i><span>Ubicaci&oacute;n y evidencia</span></div>
                                <div class="form-group"><label>Ubicaci&oacute;n actual</label><input type="text" class="form-control" name="location" maxlength="150" placeholder="Ciudad, agencia o almac&eacute;n"><small class="invalid-feedback" data-error="location"></small></div>
                                <div class="form-group"><label>Observaci&oacute;n</label><textarea class="form-control" name="description" rows="2" maxlength="1000"></textarea><small class="invalid-feedback" data-error="description"></small></div>
                                <div class="form-group mb-0"><label>Archivo opcional</label><div class="spo-file-control"><i class="fas fa-paperclip"></i><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp"></div><small class="text-muted">PDF o imagen, m&aacute;ximo 5 MB.</small><small class="invalid-feedback" data-error="document"></small></div>
                            </div>
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

@can('admin.shipping-agencies.store')
<div class="modal fade spo-quick-agency-modal" id="quickShippingAgencyTrackingModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0">
            <form id="quickShippingAgencyTrackingForm">
                <div class="modal-header spo-quick-agency-header">
                    <div class="spo-quick-agency-heading">
                        <span class="spo-quick-agency-icon"><i class="fas fa-shipping-fast"></i></span>
                        <div>
                            <h5 class="modal-title">Nueva agencia</h5>
                            <p>Registra una agencia de transporte para usarla en el seguimiento.</p>
                        </div>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="spo-quick-agency-hint">
                        <i class="fas fa-search"></i>
                        <span>Ingresa el RUC para completar autom&aacute;ticamente los datos disponibles.</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label>RUC</label>
                            <div class="input-group">
                                <input type="text" id="newAgencyRuc" name="ruc" class="form-control"
                                    maxlength="11" inputmode="numeric">
                                <div class="input-group-append">
                                    <button type="button" id="btnSearchNewAgencyRuc"
                                        class="btn btn-outline-success" title="Buscar RUC">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="form-text text-muted">Consulta autom&aacute;tica si el RUC tiene 11 d&iacute;gitos.</small>
                            <small class="invalid-feedback" data-error="ruc"></small>
                        </div>
                        <div class="form-group col-md-7">
                            <label>Raz&oacute;n social *</label>
                            <input type="text" id="newAgencyBusinessName" name="business_name"
                                class="form-control text-uppercase" required maxlength="255">
                            <small class="invalid-feedback" data-error="business_name"></small>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Tel&eacute;fono</label>
                            <input type="text" name="phone" class="form-control" maxlength="30">
                            <small class="form-text text-muted">Opcional</small>
                            <small class="invalid-feedback" data-error="phone"></small>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Correo</label>
                            <input type="email" name="email" class="form-control" maxlength="255">
                            <small class="form-text text-muted">Opcional</small>
                            <small class="invalid-feedback" data-error="email"></small>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Direcci&oacute;n</label>
                        <input type="text" id="newAgencyAddress" name="address"
                            class="form-control text-uppercase" maxlength="255">
                    </div>
                    <small id="newAgencyRucStatus" class="d-block mt-2"></small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light spo-quick-agency-cancel" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success spo-quick-agency-save">
                        <i class="fas fa-save mr-1"></i> Registrar agencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan
