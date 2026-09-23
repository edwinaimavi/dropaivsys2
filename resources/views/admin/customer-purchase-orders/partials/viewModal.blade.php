<div class="modal fade" id="viewCustomerPurchaseOrderModal" tabindex="-1" role="dialog" aria-labelledby="viewCustomerPurchaseOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered customer-order-view-dialog" role="document">
        <div class="modal-content border-0 shadow customer-order-view-content">
            <div class="modal-header border-0 customer-order-view-header">
                <div class="d-flex align-items-center min-width-0">
                    <span class="customer-order-view-header-icon mr-3"><i class="fas fa-clipboard-check"></i></span>
                    <div class="min-width-0">
                        <h5 class="modal-title mb-0" id="viewCustomerPurchaseOrderModalLabel">Orden de Compra del Cliente</h5>
                        <small>Vista ejecutiva, abastecimiento y seguimiento operativo</small>
                    </div>
                </div>
                <button type="button" class="close customer-order-view-close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>

            <div class="modal-body customer-order-view-body">
                <div class="row no-gutters customer-order-view-workspace">
                    <aside class="col-lg-3 customer-order-view-sidebar-wrap">
                        <div class="customer-order-view-sidebar">
                            <span class="customer-order-view-icon mx-auto"><i class="fas fa-file-signature"></i></span>
                            <small class="customer-order-view-eyebrow">Código interno</small>
                            <h3 id="vpo_code" class="customer-order-view-code">—</h3>
                            <div id="vpo_purchase_order_number" class="customer-order-view-number">—</div>
                            <span id="vpo_status" class="badge badge-secondary rounded-pill px-3 py-2">REGISTRADA</span>
                            <small id="vpo_status_help" class="customer-order-view-status-help">Orden registrada, pendiente de compra al proveedor.</small>
                            <div class="customer-order-view-identity">
                                <div><span><i class="fas fa-building mr-1"></i>Cliente</span><strong id="vpo_customer">—</strong></div>
                                <div><span><i class="fas fa-map-marker-alt mr-1"></i>Sucursal / tienda</span><strong id="vpo_branch">—</strong></div>
                            </div>
                            <div class="customer-order-view-side-total"><span>Total de venta</span><strong><span id="vpo_currency_symbol">S/</span> <span id="vpo_grand_total">0.000</span></strong></div>
                            <div class="customer-order-view-side-facts">
                                <div><span>Estado operativo</span><strong id="vpo_side_operational_status">—</strong></div>
                                <div><span>Facturación / cobro</span><strong id="vpo_side_billing_status">—</strong></div>
                            </div>
                            <div class="customer-order-view-counters">
                                <span><i class="fas fa-boxes"></i><strong id="vpo_side_item_count">0</strong><small>Ítems</small></span>
                                <span><i class="fas fa-shopping-cart"></i><strong id="vpo_side_purchase_count">0</strong><small>Compras</small></span>
                                <span><i class="fas fa-truck"></i><strong id="vpo_side_dispatch_count">0</strong><small>Salidas</small></span>
                            </div>
                        </div>
                    </aside>

                    <section class="col-lg-9 customer-order-view-main">
                        <nav class="customer-order-view-tabs-wrap" aria-label="Secciones del detalle">
                            <div class="nav nav-pills customer-order-view-tabs" id="customerOrderViewTabs" role="tablist">
                                <a class="nav-link active" data-toggle="pill" href="#vpo_tab_summary" role="tab"><i class="fas fa-chart-pie"></i>Resumen</a>
                                <a class="nav-link" data-toggle="pill" href="#vpo_tab_general" role="tab"><i class="fas fa-clipboard-list"></i>Datos generales</a>
                                <a class="nav-link" data-toggle="pill" href="#vpo_tab_seller" role="tab"><i class="fas fa-user-tie"></i>Gestor / vendedor</a>
                                <a class="nav-link" data-toggle="pill" href="#vpo_tab_items" role="tab"><i class="fas fa-boxes"></i>Ítems adjudicados</a>
                                <a class="nav-link" data-toggle="pill" href="#vpo_tab_supply" role="tab"><i class="fas fa-route"></i>Abastecimiento</a>
                                <a class="nav-link" data-toggle="pill" href="#vpo_tab_dispatches" role="tab"><i class="fas fa-truck-loading"></i>Despachos</a>
                                <a class="nav-link" data-toggle="pill" href="#vpo_tab_documents" role="tab"><i class="fas fa-folder-open"></i>Documentos / facturación</a>
                            </div>
                        </nav>

                        <div class="tab-content customer-order-view-tab-content">
                            <div class="tab-pane fade show active" id="vpo_tab_summary" role="tabpanel">
                                <div class="customer-order-view-section-heading"><div><span>Vista ejecutiva</span><h6>Resumen de la orden</h6><small>Cifras clave y etapa operativa actual.</small></div><i class="fas fa-chart-line"></i></div>
                                <div class="customer-order-view-kpis">
                                    <div><span><i class="fas fa-clipboard-list"></i>Solicitado</span><strong id="vpo_requested_total">0.00</strong></div>
                                    <div class="is-entered"><span><i class="fas fa-warehouse"></i>Ingresado</span><strong id="vpo_entered_total">0.00</strong></div>
                                    <div class="is-dispatched"><span><i class="fas fa-truck"></i>Despachado</span><strong id="vpo_dispatched_total">0.00</strong></div>
                                    <div class="is-pending"><span><i class="fas fa-hourglass-half"></i>Pendiente</span><strong id="vpo_pending_dispatch_total">0.00</strong></div>
                                </div>
                                <div class="customer-order-view-info-grid summary-grid">
                                    <div><span>Cliente</span><strong id="vpo_summary_customer">—</strong></div>
                                    <div><span>Gestor / vendedor</span><strong id="vpo_summary_seller">—</strong></div>
                                    <div><span>Empresa</span><strong id="vpo_summary_company">—</strong></div>
                                    <div><span>Moneda</span><strong id="vpo_summary_currency">—</strong></div>
                                    <div><span>Total</span><strong id="vpo_summary_total">—</strong></div>
                                    <div><span>Plazo de entrega</span><strong id="vpo_summary_delivery">—</strong></div>
                                    <div><span>Estado actual</span><strong id="vpo_summary_status">—</strong></div>
                                    <div><span>Facturación / cobro</span><strong id="vpo_summary_billing">—</strong></div>
                                </div>
                                <div class="customer-order-view-subheading"><i class="fas fa-stream"></i><div><strong>Progreso operativo</strong><small>Lectura rápida desde el registro hasta la atención.</small></div></div>
                                <div id="vpo_operational_timeline" class="customer-order-view-timeline"></div>
                                <div class="customer-order-view-summary-counts">
                                    <div><i class="fas fa-shopping-basket"></i><span>OC Proveedor</span><strong id="vpo_supplier_order_count">0</strong></div>
                                    <div><i class="fas fa-dolly-flatbed"></i><span>Ingresos relacionados</span><strong id="vpo_entry_count">0</strong></div>
                                    <div><i class="fas fa-truck"></i><span>Despachos</span><strong id="vpo_dispatch_count">0</strong></div>
                                    <div><i class="fas fa-file-invoice"></i><span>Facturas</span><strong id="vpo_invoice_count">0</strong></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="vpo_tab_general" role="tabpanel">
                                <div class="customer-order-view-section-heading"><div><span>Cabecera comercial</span><h6>Datos generales</h6><small>Identificación, fechas y condiciones de la OC Cliente.</small></div><i class="fas fa-clipboard-check"></i></div>
                                <div class="customer-order-view-info-grid">
                                    <div><span>Empresa</span><strong id="vpo_company">—</strong></div><div><span>Cotización</span><strong id="vpo_quote">—</strong></div><div><span>Moneda</span><strong id="vpo_currency">—</strong></div>
                                    <div><span>Tipo de orden</span><strong id="vpo_order_type">—</strong></div><div><span>Facturación</span><strong id="vpo_billing_type">—</strong></div><div><span>Tributación</span><strong id="vpo_affect_igv">POR ÍTEM</strong></div>
                                    <div><span>Fecha notificación</span><strong id="vpo_notification_date">—</strong></div><div><span>Entrega desde</span><strong id="vpo_delivery_start_date">—</strong></div><div><span>Entrega hasta</span><strong id="vpo_delivery_end_date">—</strong></div>
                                    <div><span>Expediente SIAF</span><strong id="vpo_siaf">—</strong></div><div><span>Cuadro adquisición</span><strong id="vpo_chart">—</strong></div><div><span>Tipo de proceso</span><strong id="vpo_process">—</strong></div>
                                    <div class="is-wide"><span>Observaciones</span><strong id="vpo_observations">—</strong></div>
                                </div>
                                <div class="row justify-content-end mt-3"><div class="col-md-7 col-xl-5"><div class="customer-order-view-totals">
                                    <div><span>Venta exonerada</span><strong id="vpo_subtotal_exonerated">0.000</strong></div><div><span>Venta inafecta</span><strong id="vpo_subtotal_unaffected">0.000</strong></div><div><span>Venta gravada</span><strong id="vpo_subtotal_taxed">0.000</strong></div><div><span>IGV</span><strong id="vpo_igv">0.000</strong></div><div class="grand"><span>Total</span><strong id="vpo_total">0.000</strong></div>
                                </div></div></div>
                            </div>

                            <div class="tab-pane fade" id="vpo_tab_seller" role="tabpanel">
                                <div class="customer-order-view-section-heading"><div><span>Responsable comercial</span><h6>Gestor / vendedor</h6><small>Contacto y usuario que registró la orden.</small></div><i class="fas fa-user-tie"></i></div>
                                <div id="vpo_seller_empty" class="customer-order-view-empty d-none"><i class="fas fa-user-slash"></i><strong>No hay gestor o vendedor registrado.</strong><span>La orden conserva el resto de su información operativa.</span></div>
                                <div id="vpo_seller_card" class="customer-order-view-info-grid">
                                    <div><span>Tipo</span><strong id="vpo_seller_type">—</strong></div><div><span>DNI</span><strong id="vpo_seller_dni">—</strong></div><div><span>Nombre completo</span><strong id="vpo_seller_full_name">—</strong></div>
                                    <div><span>Teléfono</span><strong id="vpo_seller_phone">—</strong></div><div><span>Correo</span><strong id="vpo_seller_email">—</strong></div><div><span>Registrado por</span><strong id="vpo_created_by">—</strong></div>
                                    <div class="is-wide"><span>Observación</span><strong id="vpo_seller_observation">—</strong></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="vpo_tab_items" role="tabpanel">
                                <div class="customer-order-view-section-heading"><div><span>Detalle operativo</span><h6>Ítems adjudicados</h6><small>Compra, ingreso, despacho y pendiente por artículo.</small></div><i class="fas fa-box-open"></i></div>
                                <div class="customer-order-view-table-wrap"><table class="table table-sm table-hover mb-0 customer-order-view-table"><thead><tr><th>ARTÍCULO</th><th class="text-right">SOLICITADO</th><th class="text-right">EN COMPRA</th><th class="text-right">INGRESADO</th><th class="text-right">DESPACHADO</th><th class="text-right">PENDIENTE</th><th class="text-center">ESTADO</th></tr></thead><tbody id="vpo_items_body"></tbody></table></div>
                            </div>

                            <div class="tab-pane fade" id="vpo_tab_supply" role="tabpanel">
                                <div class="customer-order-view-section-heading"><div><span>Compras relacionadas</span><h6>Abastecimiento y proveedores</h6><small>Una OC Cliente puede abastecerse mediante uno o varios proveedores.</small></div><i class="fas fa-project-diagram"></i></div>
                                <div id="vpo_supplier_orders" class="customer-order-view-card-grid"></div>
                                <div class="customer-order-view-subheading"><i class="fas fa-warehouse"></i><div><strong>Ingresos relacionados</strong><small>Cronología de mercadería asignada a esta orden.</small></div></div>
                                <div id="vpo_warehouse_trace" class="customer-order-view-card-grid"></div>
                            </div>

                            <div class="tab-pane fade" id="vpo_tab_dispatches" role="tabpanel">
                                <div class="customer-order-view-section-heading"><div><span>Salida física</span><h6>Despachos / salidas</h6><small>Historial de mercadería despachada contra esta OC Cliente.</small></div>@can('admin.customer-purchase-orders.dispatch')<button type="button" id="vpo_register_dispatch" class="btn btn-sm btn-success d-none"><i class="fas fa-plus-circle mr-1"></i>Registrar salida</button>@endcan</div>
                                <div id="vpo_dispatches"></div>
                            </div>

                            <div class="tab-pane fade" id="vpo_tab_documents" role="tabpanel">
                                <div class="customer-order-view-section-heading"><div><span>Expediente y cobranza</span><h6>Documentos / facturación</h6><small>Sustentos, comprobantes emitidos y cobros registrados.</small></div><i class="fas fa-file-invoice-dollar"></i></div>
                                <div class="customer-order-view-billing-kpis">
                                    <div><span>Total OC</span><strong id="vpo_billing_order_total">0.00</strong></div><div><span>Facturado</span><strong id="vpo_billed_amount">0.00</strong></div><div><span>Por facturar</span><strong id="vpo_unbilled_amount">0.00</strong></div><div class="is-paid"><span>Cobrado</span><strong id="vpo_paid_amount">0.00</strong></div><div class="is-pending"><span>Por cobrar</span><strong id="vpo_pending_amount">0.00</strong></div>
                                </div>
                                <div class="customer-order-view-subheading"><i class="fas fa-file-invoice"></i><div><strong>Facturas emitidas</strong><small>Comprobantes generados desde esta OC Cliente.</small></div></div>
                                <div class="customer-order-view-table-wrap"><table class="table table-sm table-hover mb-0 customer-order-view-table billing-table"><thead><tr><th>FACTURA</th><th>EMISIÓN</th><th>VENCIMIENTO</th><th>ESTADO</th><th class="text-right">TOTAL</th><th class="text-right">COBRADO</th><th class="text-right">SALDO</th><th>FACTURÓ</th><th class="text-center">ACCIÓN</th></tr></thead><tbody id="vpo_invoices_body"></tbody></table></div>
                                <div class="customer-order-view-subheading"><i class="fas fa-hand-holding-usd"></i><div><strong>Cobros confirmados</strong><small>Movimientos vinculados con los comprobantes.</small></div></div>
                                <div class="customer-order-view-table-wrap"><table class="table table-sm table-hover mb-0 customer-order-view-table collections-table"><thead><tr><th>FECHA</th><th>FACTURA</th><th>BANCO / CUENTA</th><th>OPERACIÓN</th><th class="text-right">MONTO</th><th>USUARIO</th><th class="text-center">CONSTANCIA</th></tr></thead><tbody id="vpo_collections_body"></tbody></table></div>
                                <div class="customer-order-view-subheading"><i class="fas fa-paperclip"></i><div><strong>Documentación de la orden</strong><small>Archivos y sustentos adjuntos.</small></div></div>
                                <div class="customer-order-view-table-wrap compact"><table class="table table-sm table-hover mb-0 customer-order-view-table"><thead><tr><th>TIPO</th><th>ARCHIVO</th><th class="text-center">ACCIÓN</th></tr></thead><tbody id="vpo_documents_body"></tbody></table></div>
                                <div id="vpo_attention_closure" class="customer-order-view-attention d-none">
                                    <div class="customer-order-view-subheading mt-0"><i class="fas fa-clipboard-check"></i><div><strong>Cierre de atención</strong><small>Resultado y sustento del cierre operativo.</small></div></div>
                                    <div class="customer-order-view-info-grid"><div><span>Resultado</span><strong id="vpo_attention_result">—</strong></div><div><span>Fecha de cierre</span><strong id="vpo_attention_closed_at">—</strong></div><div><span>Cerrado por</span><strong id="vpo_attention_closed_by">—</strong></div><div class="is-wide"><span>Motivo / observación</span><strong id="vpo_attention_observation">—</strong></div><div id="vpo_attention_document_wrapper" class="is-wide d-none"><span>Cargo / sustento</span><a id="vpo_attention_document" href="#" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-1"><i class="fas fa-external-link-alt mr-1"></i>Abrir sustento</a></div></div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
            <div class="modal-footer border-0 customer-order-view-footer"><small class="text-muted mr-auto d-none d-md-inline"><i class="fas fa-info-circle mr-1"></i>Seleccione una pestaña para consultar cada etapa de la orden.</small><button type="button" class="btn btn-light border px-4" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button></div>
        </div>
    </div>
</div>

<style>
    #viewCustomerPurchaseOrderModal{--cov-blue:#2563a9;--cov-dark:#173f6a;--cov-soft:#edf5ff;--cov-border:#dce8f4;--cov-text:#22364b;--cov-muted:#6f8091}
    #viewCustomerPurchaseOrderModal .customer-order-view-content{height:calc(100vh - 32px);max-height:920px;overflow:hidden;border-radius:17px;background:#f4f8fc}
    #viewCustomerPurchaseOrderModal .customer-order-view-header{flex:0 0 auto;padding:13px 18px;color:#fff;background:linear-gradient(135deg,var(--cov-blue),var(--cov-dark))}.customer-order-view-header-icon{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;border-radius:12px;background:rgba(255,255,255,.14);font-size:18px}.customer-order-view-header h5{font-size:17px;font-weight:700}.customer-order-view-header small{color:rgba(255,255,255,.78);font-size:10.5px}.customer-order-view-close{color:#fff!important;opacity:.85;text-shadow:none!important}
    #viewCustomerPurchaseOrderModal .customer-order-view-body{flex:1 1 auto;min-height:0;padding:0;overflow:hidden}.customer-order-view-workspace{height:100%}.customer-order-view-sidebar-wrap{height:100%;padding:14px 7px 14px 14px}.customer-order-view-sidebar{height:100%;padding:18px 15px;overflow-y:auto;border:1px solid var(--cov-border);border-radius:15px;background:linear-gradient(180deg,#fff,#f8fbff);box-shadow:0 8px 24px rgba(35,70,105,.06);text-align:center}
    #viewCustomerPurchaseOrderModal .customer-order-view-icon{display:grid;width:66px;height:66px;place-items:center;border-radius:18px;color:#fff;background:linear-gradient(135deg,#3987d3,var(--cov-dark));box-shadow:0 9px 20px rgba(37,99,169,.2);font-size:25px}.customer-order-view-eyebrow{display:block;margin-top:12px;color:var(--cov-muted);font-size:8.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.customer-order-view-code{margin:2px 0;color:var(--cov-text);font-size:21px;font-weight:800}.customer-order-view-number{margin-bottom:9px;color:var(--cov-muted);font-size:10.5px}.customer-order-view-status-help{display:block;margin:8px 4px 0;color:var(--cov-muted);font-size:9.5px;line-height:1.45}
    #viewCustomerPurchaseOrderModal .customer-order-view-identity,.customer-order-view-side-facts{display:grid;gap:7px;margin-top:10px;text-align:left}.customer-order-view-identity{margin-top:14px!important}.customer-order-view-identity div,.customer-order-view-side-facts div{padding:9px 10px;border:1px solid #e4edf6;border-radius:10px;background:#fff}.customer-order-view-identity span,.customer-order-view-side-facts span{display:block;color:var(--cov-muted);font-size:8.5px;font-weight:600;text-transform:uppercase}.customer-order-view-identity strong,.customer-order-view-side-facts strong{display:block;margin-top:3px;color:var(--cov-text);font-size:10.5px;font-weight:600;overflow-wrap:anywhere}.customer-order-view-side-total{margin-top:9px;padding:11px;border-radius:11px;color:#fff;background:linear-gradient(135deg,var(--cov-blue),var(--cov-dark));text-align:left}.customer-order-view-side-total span{display:block;font-size:8.5px;text-transform:uppercase}.customer-order-view-side-total strong{display:block;margin-top:2px;font-size:19px;font-weight:800}
    #viewCustomerPurchaseOrderModal .customer-order-view-counters{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:10px}.customer-order-view-counters>span{display:grid;padding:8px 2px 7px;place-items:center;border:1px solid var(--cov-border);border-radius:10px;color:var(--cov-dark);background:var(--cov-soft)}.customer-order-view-counters i{font-size:12px}.customer-order-view-counters strong{font-size:14px;line-height:1.2}.customer-order-view-counters small{color:var(--cov-muted);font-size:8px}
    #viewCustomerPurchaseOrderModal .customer-order-view-main{display:flex;height:100%;min-height:0;padding:14px 14px 14px 7px;overflow:hidden;flex-direction:column}.customer-order-view-tabs-wrap{flex:0 0 auto;padding:7px;border:1px solid var(--cov-border);border-radius:13px;background:#fff;box-shadow:0 5px 18px rgba(35,70,105,.05)}.customer-order-view-tabs{display:flex;flex-wrap:nowrap;gap:5px;overflow-x:auto;scrollbar-width:thin}.customer-order-view-tabs .nav-link{display:inline-flex;flex:0 0 auto;align-items:center;gap:6px;padding:8px 10px;border-radius:9px;color:#60768d;font-size:10.5px;font-weight:600;white-space:nowrap;transition:.18s ease}.customer-order-view-tabs .nav-link:hover{color:var(--cov-blue);background:#f2f7fd}.customer-order-view-tabs .nav-link.active{color:#fff;background:linear-gradient(135deg,var(--cov-blue),var(--cov-dark));box-shadow:0 5px 12px rgba(37,99,169,.18)}
    #viewCustomerPurchaseOrderModal .customer-order-view-tab-content{flex:1 1 auto;min-height:0;margin-top:10px;padding:16px 16px 23px;overflow-x:hidden;overflow-y:auto;scrollbar-gutter:stable;border:1px solid var(--cov-border);border-radius:15px;background:#fff;box-shadow:0 8px 24px rgba(35,70,105,.05)}.customer-order-view-section-heading{display:flex;align-items:center;justify-content:space-between;gap:13px;margin-bottom:15px;padding-bottom:12px;border-bottom:1px solid #e8eef5}.customer-order-view-section-heading span{display:block;color:var(--cov-blue);font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.customer-order-view-section-heading h6{margin:1px 0;color:var(--cov-text);font-size:17px;font-weight:700}.customer-order-view-section-heading small{color:var(--cov-muted);font-size:10.5px}.customer-order-view-section-heading>i{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;border-radius:12px;color:var(--cov-blue);background:var(--cov-soft);font-size:17px}
    #viewCustomerPurchaseOrderModal .customer-order-view-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:13px}.customer-order-view-kpis>div{padding:13px;border:1px solid #e0e9f3;border-radius:12px;background:#fbfdff}.customer-order-view-kpis span{display:block;color:var(--cov-muted);font-size:9px;font-weight:700;text-transform:uppercase}.customer-order-view-kpis span i{margin-right:5px}.customer-order-view-kpis strong{display:block;margin-top:5px;color:var(--cov-text);font-size:18px;font-weight:800}.customer-order-view-kpis .is-entered{border-color:#bfe0f0;background:#f0faff}.customer-order-view-kpis .is-dispatched{border-color:#cce8d7;background:#f2fbf6}.customer-order-view-kpis .is-pending{border-color:#f1d4c9;background:#fff7f3}.customer-order-view-kpis .is-pending strong{color:#bd4f31}
    #viewCustomerPurchaseOrderModal .customer-order-view-info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.customer-order-view-info-grid.summary-grid{grid-template-columns:repeat(4,minmax(0,1fr))}.customer-order-view-info-grid>div{min-width:0;min-height:66px;padding:11px 12px;border:1px solid #e4ebf3;border-radius:11px;background:#fafcff}.customer-order-view-info-grid .is-wide{grid-column:span 2}.customer-order-view-info-grid span{display:block;margin-bottom:4px;color:var(--cov-muted);font-size:9px;font-weight:700;text-transform:uppercase}.customer-order-view-info-grid strong{display:block;color:var(--cov-text);font-size:11.5px;font-weight:600;line-height:1.5;overflow-wrap:anywhere}.customer-order-view-subheading{display:flex;align-items:center;gap:10px;margin:18px 0 10px}.customer-order-view-subheading>i{display:grid;width:34px;height:34px;place-items:center;border-radius:10px;color:var(--cov-blue);background:var(--cov-soft)}.customer-order-view-subheading strong,.customer-order-view-subheading small{display:block}.customer-order-view-subheading strong{color:var(--cov-text);font-size:13px}.customer-order-view-subheading small{color:var(--cov-muted);font-size:10px}
    #viewCustomerPurchaseOrderModal .customer-order-view-timeline{display:flex;margin:5px 0 18px;overflow-x:auto}.customer-order-view-timeline-step{position:relative;flex:1 0 145px;padding:34px 7px 0;color:#8a99a8;text-align:center}.customer-order-view-timeline-step:before{position:absolute;top:13px;left:0;width:100%;height:3px;content:'';background:#e2e8ef}.customer-order-view-timeline-step:after{position:absolute;top:7px;left:calc(50% - 8px);width:16px;height:16px;border:3px solid #fff;border-radius:50%;content:'';background:#c9d4df;box-shadow:0 0 0 1px #d9e2eb}.customer-order-view-timeline-step.is-complete{color:var(--cov-dark)}.customer-order-view-timeline-step.is-complete:before,.customer-order-view-timeline-step.is-complete:after{background:var(--cov-blue)}.customer-order-view-timeline-step.is-current:after{box-shadow:0 0 0 4px rgba(37,99,169,.15)}.customer-order-view-timeline-step strong{display:block;font-size:10px}.customer-order-view-timeline-step small{font-size:8.5px}.customer-order-view-summary-counts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px}.customer-order-view-summary-counts>div{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:8px;padding:10px 11px;border:1px solid var(--cov-border);border-radius:11px;color:var(--cov-dark);background:var(--cov-soft)}.customer-order-view-summary-counts span{color:#62788d;font-size:9.5px}.customer-order-view-summary-counts strong{font-size:15px}
    #viewCustomerPurchaseOrderModal .customer-order-view-table-wrap{max-width:100%;overflow:auto;border:1px solid #e0e8f1;border-radius:12px}.customer-order-view-table{min-width:850px}.customer-order-view-table.billing-table{min-width:1050px}.customer-order-view-table.collections-table{min-width:850px}.customer-order-view-table-wrap.compact .customer-order-view-table{min-width:600px}.customer-order-view-table thead th{position:sticky;top:0;z-index:2;padding:10px 8px;border:0;border-bottom:1px solid #d9e4ef;color:#40566d;background:#edf5fc;font-size:9px;font-weight:700;white-space:nowrap}.customer-order-view-table tbody td{padding:10px 8px;border-top:1px solid #edf2f7;color:#2e4052;font-size:10.5px;vertical-align:middle}
    #viewCustomerPurchaseOrderModal .customer-order-view-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:10px}.customer-order-view-card{min-width:0;padding:13px 14px;border:1px solid #dfe8f1;border-radius:12px;background:#fbfdff}.customer-order-view-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:9px}.customer-order-view-card h6{margin:0;color:var(--cov-text);font-size:12.5px;font-weight:700}.customer-order-view-card small{display:block;margin-top:3px;color:var(--cov-muted);font-size:9.5px}.customer-order-view-card dl{display:grid;grid-template-columns:auto 1fr;gap:5px 10px;margin:11px 0 0;font-size:10px}.customer-order-view-card dt{color:var(--cov-muted)}.customer-order-view-card dd{margin:0;color:var(--cov-text);font-weight:600;text-align:right;overflow-wrap:anywhere}.customer-order-view-card ul{font-size:10px}.customer-order-view-empty{display:flex;min-height:155px;padding:24px;align-items:center;justify-content:center;flex-direction:column;border:1px dashed #cddae7;border-radius:13px;color:var(--cov-muted);background:#fafcff;text-align:center}.customer-order-view-empty i{margin-bottom:9px;color:#a5b6c6;font-size:29px}.customer-order-view-empty strong{color:#506579;font-size:13px}.customer-order-view-empty span{margin-top:3px;font-size:10.5px}.customer-order-view-card-grid>.customer-order-view-empty{grid-column:1/-1}
    #viewCustomerPurchaseOrderModal .customer-order-view-billing-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:9px}.customer-order-view-billing-kpis>div{padding:11px;border:1px solid #e1e9f1;border-radius:11px;background:#fbfdff}.customer-order-view-billing-kpis span{display:block;color:var(--cov-muted);font-size:8.5px;font-weight:700;text-transform:uppercase}.customer-order-view-billing-kpis strong{display:block;margin-top:4px;color:var(--cov-text);font-size:14px}.customer-order-view-billing-kpis .is-paid{border-color:#cce7d7;background:#f2fbf6}.customer-order-view-billing-kpis .is-pending{border-color:#f0d4ca;background:#fff7f3}.customer-order-view-attention{margin-top:18px;padding:14px;border:1px solid var(--cov-border);border-radius:13px;background:#f8fbff}.customer-order-view-totals{overflow:hidden;border:1px solid var(--cov-border);border-radius:12px}.customer-order-view-totals>div{display:flex;padding:8px 11px;justify-content:space-between;border-bottom:1px solid #edf2f7;color:#52677b;font-size:10.5px}.customer-order-view-totals .grand{border-bottom:0;color:var(--cov-dark);background:var(--cov-soft);font-size:13px;font-weight:700}.customer-order-view-footer{flex:0 0 auto;padding:10px 18px;border-top:1px solid #e1e9f1!important;background:#fff}#viewCustomerPurchaseOrderModal .dispatch-history-card{border-color:#dfe8f1!important;border-radius:12px!important;background:#fbfdff!important}
    @media(min-width:1200px){#viewCustomerPurchaseOrderModal .customer-order-view-dialog{width:calc(100vw - 50px);max-width:1540px}}
    @media(max-width:1199px){#viewCustomerPurchaseOrderModal .customer-order-view-info-grid.summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}#viewCustomerPurchaseOrderModal .customer-order-view-summary-counts{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:991px){#viewCustomerPurchaseOrderModal .customer-order-view-dialog{margin:8px auto}#viewCustomerPurchaseOrderModal .customer-order-view-content{height:calc(100vh - 16px)}#viewCustomerPurchaseOrderModal .customer-order-view-body{overflow-y:auto}#viewCustomerPurchaseOrderModal .customer-order-view-workspace{height:auto}#viewCustomerPurchaseOrderModal .customer-order-view-sidebar-wrap{height:auto;padding:12px 12px 5px}#viewCustomerPurchaseOrderModal .customer-order-view-sidebar{height:auto;overflow:visible}#viewCustomerPurchaseOrderModal .customer-order-view-main{height:auto;padding:7px 12px 16px;overflow:visible}#viewCustomerPurchaseOrderModal .customer-order-view-tab-content{overflow:visible}}
    @media(max-width:767px){#viewCustomerPurchaseOrderModal .customer-order-view-header-icon,#viewCustomerPurchaseOrderModal .customer-order-view-section-heading>i{display:none}#viewCustomerPurchaseOrderModal .customer-order-view-tab-content{padding:12px}#viewCustomerPurchaseOrderModal .customer-order-view-kpis,#viewCustomerPurchaseOrderModal .customer-order-view-info-grid,#viewCustomerPurchaseOrderModal .customer-order-view-info-grid.summary-grid,#viewCustomerPurchaseOrderModal .customer-order-view-billing-kpis,#viewCustomerPurchaseOrderModal .customer-order-view-summary-counts{grid-template-columns:1fr}#viewCustomerPurchaseOrderModal .customer-order-view-info-grid .is-wide{grid-column:auto}}
</style>
