<div class="modal fade" id="viewSupplierPurchaseOrderModal" tabindex="-1" role="dialog"
    aria-labelledby="viewSupplierPurchaseOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered supplier-order-view-dialog" role="document">
        <div class="modal-content border-0 shadow supplier-order-view-content">
            <div class="modal-header border-0 supplier-order-view-header">
                <div class="d-flex align-items-center min-width-0 supplier-order-view-header-copy">
                    <span class="supplier-order-view-header-icon mr-3"><i class="fas fa-file-invoice-dollar"></i></span>
                    <div class="min-width-0">
                        <h5 class="modal-title mb-0" id="viewSupplierPurchaseOrderModalLabel">
                            Informaci&oacute;n de la Orden de Compra a Proveedor
                        </h5>
                        <small class="supplier-order-view-subtitle" id="vspo_header_subtitle">Vista ejecutiva, documentaria y de trazabilidad</small>
                    </div>
                </div>
                <button type="button" class="close supplier-order-view-close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>

            <div class="modal-body supplier-order-view-body">
                <div class="row no-gutters supplier-order-view-workspace">
                    <aside class="col-lg-3 supplier-order-view-sidebar-wrap">
                        <div class="supplier-order-view-sidebar">
                            <span class="supplier-order-view-icon mx-auto mb-3"><i class="fas fa-truck-loading"></i></span>
                            <small class="supplier-order-view-eyebrow">C&oacute;digo interno</small>
                            <h3 id="vspo_code" class="supplier-order-view-code">-</h3>
                            <span id="vspo_status" class="badge badge-primary rounded-pill px-3 py-2">REGISTRADO</span>

                            <div class="supplier-order-view-identity">
                                <div><span><i class="fas fa-building mr-1"></i>Proveedor</span><strong id="vspo_supplier">-</strong></div>
                                <div><span><i class="fas fa-landmark mr-1"></i>Empresa compradora</span><strong id="vspo_company">-</strong></div>
                            </div>

                            <div class="supplier-order-view-side-total">
                                <span>Total compra</span>
                                <strong><span id="vspo_currency_symbol">S/</span> <span id="vspo_grand_total">0.00</span></strong>
                            </div>
                            <div class="supplier-order-view-side-facts">
                                <div><span>Moneda</span><strong id="vspo_side_currency">-</strong></div>
                                <div><span>Condici&oacute;n de pago</span><strong id="vspo_side_payment_condition">-</strong></div>
                                <div><span>Estado financiero</span><strong id="vspo_side_financial_status">-</strong></div>
                            </div>
                            <div class="supplier-order-view-counters">
                                <span title="Art&iacute;culos"><i class="fas fa-boxes"></i><strong id="vspo_side_item_count">0</strong><small>Art&iacute;culos</small></span>
                                <span title="Documentos"><i class="fas fa-folder-open"></i><strong id="vspo_side_document_count">0</strong><small>Documentos</small></span>
                                <span title="Ingresos"><i class="fas fa-warehouse"></i><strong id="vspo_side_entry_count">0</strong><small>Ingresos</small></span>
                            </div>
                        </div>
                    </aside>

                    <section class="col-lg-9 supplier-order-view-main">
                        <nav class="supplier-order-view-tabs-wrap" aria-label="Secciones del detalle">
                            <div class="nav nav-pills supplier-order-view-tabs" role="tablist">
                                <a class="nav-link active" data-toggle="pill" href="#vspo_tab_summary" role="tab"><i class="fas fa-chart-pie"></i>Resumen</a>
                                <a class="nav-link" data-toggle="pill" href="#vspo_tab_order" role="tab"><i class="fas fa-clipboard-list"></i>Datos de la orden</a>
                                <a class="nav-link" data-toggle="pill" href="#vspo_tab_finance" role="tab"><i class="fas fa-wallet"></i>Condiciones financieras</a>
                                <a class="nav-link" data-toggle="pill" href="#vspo_tab_logistics" role="tab"><i class="fas fa-shipping-fast"></i>Env&iacute;o / log&iacute;stica</a>
                                <a class="nav-link" data-toggle="pill" href="#vspo_tab_customer_orders" role="tab"><i class="fas fa-link"></i>&Oacute;rdenes cliente</a>
                                <a class="nav-link" data-toggle="pill" href="#vspo_tab_documents" role="tab"><i class="fas fa-folder-open"></i>Documentaci&oacute;n</a>
                                <a class="nav-link" data-toggle="pill" href="#vspo_tab_items" role="tab"><i class="fas fa-boxes"></i>Art&iacute;culos y servicios</a>
                                <a class="nav-link" data-toggle="pill" href="#vspo_tab_entries" role="tab"><i class="fas fa-route"></i>Ingresos / trazabilidad</a>
                            </div>
                        </nav>

                        <div class="tab-content supplier-order-view-tab-content">
                            <div class="tab-pane fade show active" id="vspo_tab_summary" role="tabpanel">
                                <div class="supplier-order-view-section-heading"><div><span>Vista ejecutiva</span><h6>Resumen de la compra</h6><small>Informaci&oacute;n principal y cifras clave de la orden.</small></div><i class="fas fa-chart-line"></i></div>
                                <div class="supplier-order-view-kpis">
                                    <div class="is-total"><span><i class="fas fa-shopping-cart"></i>Total compra</span><strong id="vspo_summary_total">0.00</strong></div>
                                    <div><span><i class="fas fa-calculator"></i>Base imponible</span><strong id="vspo_summary_subtotal">0.00</strong></div>
                                    <div><span><i class="fas fa-receipt"></i>IGV</span><strong id="vspo_summary_igv">0.00</strong></div>
                                    <div class="is-finance"><span><i class="fas fa-hand-holding-usd"></i>Pagado / anticipo</span><strong id="vspo_summary_paid">0.00</strong><small id="vspo_summary_balance">Saldo: 0.00</small></div>
                                </div>
                                <div class="supplier-order-view-info-grid summary-grid">
                                    <div><span>C&oacute;digo interno</span><strong id="vspo_summary_code">-</strong></div>
                                    <div><span>Proveedor</span><strong id="vspo_summary_supplier">-</strong></div>
                                    <div><span>Empresa</span><strong id="vspo_summary_company">-</strong></div>
                                    <div><span>Moneda</span><strong id="vspo_summary_currency">-</strong></div>
                                    <div><span>Estado</span><strong id="vspo_summary_status">-</strong></div>
                                    <div><span>Condici&oacute;n de pago</span><strong id="vspo_summary_payment_condition">-</strong></div>
                                    <div><span>Tipo documento</span><strong id="vspo_summary_document_type">-</strong></div>
                                    <div><span>Forma de pago</span><strong id="vspo_summary_payment_method">-</strong></div>
                                    <div><span>Afecto IGV</span><strong id="vspo_summary_affect_igv">-</strong></div>
                                    <div><span>Fecha registro</span><strong id="vspo_summary_created_at">-</strong></div>
                                </div>
                                <div class="supplier-order-view-summary-counts">
                                    <div><i class="fas fa-box-open"></i><span>Art&iacute;culos y servicios</span><strong id="vspo_summary_item_count">0</strong></div>
                                    <div><i class="fas fa-file-alt"></i><span>Documentos adjuntos</span><strong id="vspo_summary_document_count">0</strong></div>
                                    <div><i class="fas fa-dolly-flatbed"></i><span>Ingresos de almac&eacute;n</span><strong id="vspo_summary_entry_count">0</strong></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="vspo_tab_order" role="tabpanel">
                                <div class="supplier-order-view-section-heading"><div><span>Configuraci&oacute;n</span><h6>Datos de la orden</h6><small>Condiciones comerciales y datos de emisi&oacute;n.</small></div><i class="fas fa-clipboard-check"></i></div>
                                <div class="supplier-order-view-info-grid">
                                    <div><span>Cuenta bancaria</span><strong id="vspo_supplier_account">-</strong></div>
                                    <div><span>Moneda</span><strong id="vspo_currency">-</strong></div>
                                    <div><span>Afecto IGV</span><strong id="vspo_affect_igv">-</strong></div>
                                    <div><span>Condici&oacute;n de pago</span><strong id="vspo_payment_condition">-</strong></div>
                                    <div><span>Tipo de entrega</span><strong id="vspo_delivery_type">-</strong></div>
                                    <div><span>Tipo de transporte</span><strong id="vspo_transport_type">-</strong></div>
                                    <div><span>Tipo documento</span><strong id="vspo_document_type">-</strong></div>
                                    <div><span>Forma de pago</span><strong id="vspo_payment_method">-</strong></div>
                                    <div><span>Destino</span><strong id="vspo_destination">-</strong></div>
                                    <div class="is-wide"><span>Direcci&oacute;n de env&iacute;o</span><strong id="vspo_shipping_address">-</strong></div>
                                    <div class="is-wide"><span>Observaci&oacute;n</span><strong id="vspo_observations">Sin observaciones</strong></div>
                                </div>
                                <div class="supplier-order-view-subheading"><i class="fas fa-file-signature"></i><div><strong>Datos internos para PDF</strong><small>Informaci&oacute;n administrativa conservada en la orden.</small></div></div>
                                <div class="supplier-order-view-info-grid">
                                    <div><span>Solicitado por</span><strong id="vspo_requested_by">-</strong></div>
                                    <div><span>Departamento</span><strong id="vspo_request_department">-</strong></div>
                                    <div><span>Autorizado por</span><strong id="vspo_authorized_by_name">-</strong></div>
                                    <div><span>Cargo autorizado</span><strong id="vspo_authorized_by_position">-</strong></div>
                                    <div class="is-wide"><span>Delivery</span><strong id="vspo_delivery_text">-</strong></div>
                                    <div class="is-wide"><span>T&eacute;rminos de pago</span><strong id="vspo_payment_terms_text">-</strong></div>
                                    <div class="is-wide"><span>Instrucciones</span><strong id="vspo_purchase_instructions">-</strong></div>
                                    <div class="is-wide"><span>Nota importante</span><strong id="vspo_important_note">-</strong></div>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="vspo_tab_finance" role="tabpanel">
                                <div class="supplier-order-view-section-heading"><div><span>Control financiero</span><h6>Condiciones financieras</h6><small>Importes registrados, anticipos y saldo informativo.</small></div><i class="fas fa-coins"></i></div>
                                <div class="supplier-order-view-finance-grid">
                                    <div><span>Moneda compra</span><strong id="vspo_fin_purchase_currency">-</strong></div>
                                    <div><span>Moneda pago</span><strong id="vspo_fin_payment_currency">-</strong></div>
                                    <div><span>Total compra</span><strong id="vspo_fin_total">0.00</strong></div>
                                    <div><span>Anticipo requerido</span><strong id="vspo_fin_advance_required">0.00</strong></div>
                                    <div><span>Pagado</span><strong id="vspo_fin_paid">0.00</strong></div>
                                    <div class="is-balance"><span>Saldo pendiente</span><strong id="vspo_fin_balance">0.00</strong></div>
                                </div>
                                <div class="supplier-order-view-finance-status">
                                    <div><span>Estado financiero</span><strong id="vspo_fin_status">-</strong></div>
                                    <div><span>Tipo de cambio referencial</span><strong id="vspo_fin_exchange_rate">-</strong></div>
                                </div>
                                <div class="supplier-order-view-subheading"><i class="fas fa-money-check-alt"></i><div><strong>Pagos y anticipos</strong><small>Registros existentes, sin modificar c&aacute;lculos.</small></div></div>
                                <div id="vspo_advance_payments"></div>
                            </div>

                            <div class="tab-pane fade" id="vspo_tab_logistics" role="tabpanel">
                                <div class="supplier-order-view-section-heading"><div><span>Despacho</span><h6>Env&iacute;o y log&iacute;stica</h6><small>Agencia, contacto y destino registrados.</small></div><i class="fas fa-truck"></i></div>
                                <div id="vspo_logistics_empty" class="supplier-order-view-empty d-none"><i class="fas fa-shipping-fast"></i><strong>No hay informaci&oacute;n registrada.</strong><span>Esta orden no requiere datos de agencia de env&iacute;o.</span></div>
                                <div id="vspo_shipping_agency_card" class="supplier-order-view-logistics-grid">
                                    <article class="vspo-agency-only"><i class="fas fa-shipping-fast"></i><span>Agencia de env&iacute;o</span><strong id="vspo_shipping_agency">-</strong><small id="vspo_shipping_branch">-</small></article>
                                    <article class="vspo-agency-only"><i class="fas fa-address-card"></i><span>Contacto</span><strong id="vspo_shipping_contact">-</strong><small id="vspo_shipping_contact_phone">-</small><small id="vspo_shipping_contact_email">-</small></article>
                                    <article><i class="fas fa-map-marked-alt"></i><span>Destino</span><strong id="vspo_logistics_destination">-</strong><small id="vspo_logistics_address">-</small><small id="vspo_shipping_reference">-</small></article>
                                </div>
                            </div>

                            <div class="tab-pane fade" id="vspo_tab_customer_orders" role="tabpanel">
                                <div class="supplier-order-view-section-heading"><div><span>Relaciones comerciales</span><h6>&Oacute;rdenes cliente relacionadas</h6><small>Origen comercial asociado con esta compra.</small></div><i class="fas fa-project-diagram"></i></div>
                                <div id="vspo_customer_orders" class="supplier-order-view-card-grid"></div>
                            </div>

                            <div class="tab-pane fade" id="vspo_tab_documents" role="tabpanel">
                                <div class="supplier-order-view-section-heading"><div><span>Expediente digital</span><h6>Documentaci&oacute;n del proveedor</h6><small>Cotizaciones, sustentos y archivos relacionados.</small></div><i class="fas fa-file-archive"></i></div>
                                <div id="vspo_documents" class="supplier-order-view-card-grid"></div>
                            </div>

                            <div class="tab-pane fade" id="vspo_tab_items" role="tabpanel">
                                <div class="supplier-order-view-section-heading"><div><span>Detalle de compra</span><h6>Art&iacute;culos y servicios</h6><small>Cantidades ordenadas, ingresadas y pendientes.</small></div><i class="fas fa-box-open"></i></div>
                                <div class="supplier-order-view-table-wrap">
                                    <table class="table table-sm table-hover mb-0 supplier-order-view-table">
                                        <thead><tr>
                                            <th>C&Oacute;DIGO</th><th>ART&Iacute;CULO</th><th>U.M.</th><th>PRESENT.</th><th>MARCA</th><th>PROCED.</th>
                                            <th class="text-right">CANT. ORDENADA</th><th class="text-right">CANT. INGRESADA</th><th class="text-right">PENDIENTE</th>
                                            <th class="text-center">ESTADO</th><th class="text-right">P. REF.</th><th class="text-right">PRECIO</th>
                                        </tr></thead>
                                        <tbody id="vspo_items_body"></tbody>
                                    </table>
                                </div>
                                <div class="row justify-content-end mt-3"><div class="col-md-7 col-xl-5">
                                    <div class="supplier-order-view-totals">
                                        <div><span>Base imponible</span><strong id="vspo_subtotal">0.00</strong></div>
                                        <div><span>IGV</span><strong id="vspo_igv">0.00</strong></div>
                                        <div class="grand"><span>Total compra</span><strong id="vspo_total">0.00</strong></div>
                                    </div>
                                </div></div>
                            </div>

                            <div class="tab-pane fade" id="vspo_tab_entries" role="tabpanel">
                                <div class="supplier-order-view-section-heading"><div><span>Seguimiento</span><h6>Ingresos de almac&eacute;n y trazabilidad</h6><small>Hitos e ingresos vinculados con esta orden.</small></div><i class="fas fa-route"></i></div>
                                <div id="vspo_timeline" class="supplier-order-view-timeline"></div>
                                <div class="supplier-order-view-subheading"><i class="fas fa-warehouse"></i><div><strong>Ingresos relacionados</strong><small>Registros de almac&eacute;n asociados a esta compra.</small></div></div>
                                <div id="vspo_warehouse_entries" class="supplier-order-view-card-grid"></div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="modal-footer border-0 supplier-order-view-footer">
                <small class="text-muted mr-auto d-none d-md-inline"><i class="fas fa-info-circle mr-1"></i>Seleccione una pesta&ntilde;a para consultar cada secci&oacute;n.</small>
                <button type="button" class="btn btn-light border px-4" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
    #viewSupplierPurchaseOrderModal{--spo-green:#16805e;--spo-dark:#116348;--spo-soft:#edf8f3;--spo-border:#dfece6;--spo-text:#263a33;--spo-heading:#0f172a;--spo-muted:#64748b}
    #viewSupplierPurchaseOrderModal .supplier-order-view-dialog{margin:16px auto}
    #viewSupplierPurchaseOrderModal .min-width-0{min-width:0}
    #viewSupplierPurchaseOrderModal .supplier-order-view-content{display:flex;max-height:calc(100vh - 32px);overflow:hidden;flex-direction:column;border-radius:16px;background:#f5f9f7}
    #viewSupplierPurchaseOrderModal .supplier-order-view-header{flex:0 0 auto;align-items:center;padding:14px 20px;border-bottom:1px solid #dce9e3!important;background:linear-gradient(135deg,#fff 0%,#f1f8f5 100%)!important}
    #viewSupplierPurchaseOrderModal .supplier-order-view-header-copy{flex:1 1 auto;padding-right:14px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-header .modal-title{color:var(--spo-heading)!important;font-size:18px;font-weight:700;line-height:1.3;white-space:normal;overflow-wrap:anywhere}
    #viewSupplierPurchaseOrderModal .supplier-order-view-subtitle{display:block;margin-top:3px;color:var(--spo-muted)!important;font-size:11px;font-weight:400;line-height:1.35;white-space:normal;overflow-wrap:anywhere}
    #viewSupplierPurchaseOrderModal .supplier-order-view-header-icon,#viewSupplierPurchaseOrderModal .supplier-order-view-icon{display:grid;place-items:center}
    #viewSupplierPurchaseOrderModal .supplier-order-view-header-icon{flex:0 0 45px;width:45px;height:45px;border:1px solid #cfe6dc;border-radius:13px;color:var(--spo-green);background:var(--spo-soft);font-size:20px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-close{display:grid;flex:0 0 34px;width:34px;height:34px;margin:0!important;padding:0!important;place-items:center;border:1px solid #dce6e1;border-radius:10px;color:#475569!important;background:#fff!important;font-size:22px;line-height:1;opacity:1;transition:.18s ease}
    #viewSupplierPurchaseOrderModal .supplier-order-view-close:hover{border-color:#c7d8d0;color:var(--spo-dark)!important;background:#f7faf9!important;transform:translateY(-1px)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-body{flex:1 1 auto;min-height:0;padding:0;overflow:hidden}
    #viewSupplierPurchaseOrderModal .supplier-order-view-workspace{height:100%;min-height:0}
    #viewSupplierPurchaseOrderModal .supplier-order-view-sidebar-wrap{min-height:0;padding:16px 8px 16px 16px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-sidebar{height:100%;padding:22px 18px;overflow-y:auto;border:1px solid var(--spo-border);border-radius:16px;background:linear-gradient(160deg,#fff 0%,#f6fbf8 100%);text-align:center;box-shadow:0 10px 28px rgba(26,71,54,.075)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-icon{position:relative;isolation:isolate;width:76px;height:76px;overflow:hidden;border:1px solid rgba(255,255,255,.3);border-radius:22px;color:#fff!important;background:linear-gradient(145deg,#1b9a70 0%,#116348 100%);box-shadow:0 12px 26px rgba(17,99,72,.25),inset 0 1px 0 rgba(255,255,255,.22);font-size:30px;transition:transform .22s ease,box-shadow .22s ease}
    #viewSupplierPurchaseOrderModal .supplier-order-view-icon:before{position:absolute;z-index:-1;top:-22px;right:-20px;width:58px;height:58px;border-radius:50%;content:'';background:rgba(255,255,255,.16);filter:blur(1px)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-icon>i{position:relative;z-index:1;color:#fff!important;-webkit-text-fill-color:#fff;filter:drop-shadow(0 3px 4px rgba(5,45,32,.22));animation:supplierOrderIconFloat 3s ease-in-out infinite}
    #viewSupplierPurchaseOrderModal .supplier-order-view-icon svg,#viewSupplierPurchaseOrderModal .supplier-order-view-icon svg path{color:#fff!important;fill:#fff!important;stroke:#fff}
    #viewSupplierPurchaseOrderModal .supplier-order-view-icon:hover{transform:scale(1.04);box-shadow:0 15px 30px rgba(17,99,72,.3),inset 0 1px 0 rgba(255,255,255,.25)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-eyebrow{color:var(--spo-muted);font-size:9px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
    #viewSupplierPurchaseOrderModal .supplier-order-view-code{margin:3px 0 8px;color:var(--spo-text);font-size:22px;font-weight:700;overflow-wrap:anywhere}
    #viewSupplierPurchaseOrderModal .supplier-order-view-identity{margin:18px 0 14px;padding:13px 0 1px;border-top:1px solid var(--spo-border);text-align:left}
    #viewSupplierPurchaseOrderModal .supplier-order-view-identity div{margin-bottom:11px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-identity span,#viewSupplierPurchaseOrderModal .supplier-order-view-side-facts span{display:block;margin-bottom:2px;color:var(--spo-muted);font-size:9px;font-weight:600;text-transform:uppercase}
    #viewSupplierPurchaseOrderModal .supplier-order-view-identity strong,#viewSupplierPurchaseOrderModal .supplier-order-view-side-facts strong{display:block;color:var(--spo-text);font-size:12px;font-weight:500;line-height:1.45;white-space:normal;overflow-wrap:anywhere;word-break:break-word}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-total{position:relative;display:flex;min-height:108px;padding:18px 16px;justify-content:center;overflow:hidden;flex-direction:column;border:1px solid rgba(255,255,255,.2);border-radius:16px;color:#fff;background:linear-gradient(135deg,#19966e 0%,#116f51 52%,#0d5d44 100%);text-align:left;box-shadow:0 10px 24px rgba(17,99,72,.2),inset 0 1px 0 rgba(255,255,255,.18);transition:transform .2s ease,box-shadow .2s ease}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-total:before{position:absolute;top:-48px;right:-42px;width:130px;height:130px;border-radius:50%;content:'';background:radial-gradient(circle,rgba(255,255,255,.22) 0%,rgba(255,255,255,0) 68%);pointer-events:none}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-total:after{position:absolute;top:-55%;left:-45%;width:34%;height:210%;content:'';background:linear-gradient(90deg,transparent,rgba(255,255,255,.09),transparent);transform:rotate(18deg);pointer-events:none}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-total:hover{transform:translateY(-2px);box-shadow:0 14px 28px rgba(17,99,72,.25),inset 0 1px 0 rgba(255,255,255,.2)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-total>span{position:relative;z-index:1;display:block;font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;opacity:.9}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-total strong{position:relative;z-index:1;display:flex;margin-top:6px;align-items:baseline;gap:6px;color:#fff;font-size:28px;font-weight:700;letter-spacing:-.025em;line-height:1.08;font-variant-numeric:tabular-nums;white-space:nowrap;text-shadow:0 2px 6px rgba(4,45,31,.2)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-total strong span{display:inline;color:inherit;font-size:inherit;font-weight:inherit;line-height:inherit;text-transform:none;opacity:1}
    #viewSupplierPurchaseOrderModal #vspo_currency_symbol{font-size:.68em;font-weight:600;letter-spacing:0}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-facts{display:grid;gap:8px;margin-top:14px;text-align:left}
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-facts div{padding:8px 10px;border:1px solid #e5eee9;border-radius:10px;background:#fff}
    #viewSupplierPurchaseOrderModal .supplier-order-view-counters{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:14px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-counters>span{display:grid;min-width:0;padding:8px 3px 7px;place-items:center;border:1px solid #dcebe4;border-radius:10px;color:var(--spo-dark);background:var(--spo-soft)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-counters i{margin-bottom:3px;font-size:13px}#viewSupplierPurchaseOrderModal .supplier-order-view-counters strong{font-size:14px;font-weight:700;line-height:1}#viewSupplierPurchaseOrderModal .supplier-order-view-counters small{margin-top:3px;color:#61756c;font-size:8px;font-weight:400}
    #viewSupplierPurchaseOrderModal .supplier-order-view-main{display:flex;height:100%;min-height:0;padding:16px 16px 16px 8px;overflow:hidden;flex-direction:column}
    #viewSupplierPurchaseOrderModal .supplier-order-view-tabs-wrap{flex:0 0 auto;padding:7px;border:1px solid var(--spo-border);border-radius:13px;background:#fff;box-shadow:0 6px 18px rgba(27,67,52,.05)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-tabs{display:flex;flex-wrap:nowrap;gap:5px;overflow-x:auto;scrollbar-width:thin}
    #viewSupplierPurchaseOrderModal .supplier-order-view-tabs .nav-link{display:inline-flex;flex:0 0 auto;align-items:center;gap:6px;padding:8px 10px;border-radius:9px;color:#60746b;font-size:10.5px;font-weight:600;white-space:nowrap;transition:.18s ease}
    #viewSupplierPurchaseOrderModal .supplier-order-view-tabs .nav-link:hover{color:var(--spo-green);background:#f3faf6;transform:translateY(-1px)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-tabs .nav-link.active{color:#fff;background:linear-gradient(135deg,var(--spo-green),var(--spo-dark));box-shadow:0 5px 12px rgba(22,128,94,.18)}
    #viewSupplierPurchaseOrderModal .badge{font-weight:600}
    #viewSupplierPurchaseOrderModal .supplier-order-view-tab-content{flex:1 1 auto;min-height:0;margin-top:10px;padding:16px 16px 24px;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable;border:1px solid var(--spo-border);border-radius:15px;background:#fff;box-shadow:0 8px 24px rgba(27,67,52,.055)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-tab-content>.tab-pane{min-width:0;padding-bottom:2px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-section-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:15px;padding-bottom:12px;border-bottom:1px solid #e8f1ec}
    #viewSupplierPurchaseOrderModal .supplier-order-view-section-heading span{display:block;color:var(--spo-green);font-size:9px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
    #viewSupplierPurchaseOrderModal .supplier-order-view-section-heading h6{margin:1px 0;color:var(--spo-text);font-size:17px;font-weight:700}#viewSupplierPurchaseOrderModal .supplier-order-view-section-heading small{color:var(--spo-muted);font-size:11px;font-weight:400}
    #viewSupplierPurchaseOrderModal .supplier-order-view-section-heading>i{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;border-radius:12px;color:var(--spo-green);background:var(--spo-soft);font-size:17px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:13px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-kpis>div{min-width:0;padding:13px;border:1px solid #e1ece7;border-radius:12px;background:#fbfdfc;transition:.18s ease}
    #viewSupplierPurchaseOrderModal .supplier-order-view-kpis>div:hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(31,68,54,.08)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-kpis span{display:block;color:var(--spo-muted);font-size:9px;font-weight:600;text-transform:uppercase}#viewSupplierPurchaseOrderModal .supplier-order-view-kpis span i{margin-right:5px;color:var(--spo-green)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-kpis strong{display:block;margin-top:5px;color:var(--spo-text);font-size:17px;font-weight:700;overflow-wrap:anywhere}#viewSupplierPurchaseOrderModal .supplier-order-view-kpis small{display:block;margin-top:2px;color:var(--spo-muted);font-size:10px;font-weight:400}
    #viewSupplierPurchaseOrderModal .supplier-order-view-kpis .is-total{border-color:#cde7dc;background:var(--spo-soft)}#viewSupplierPurchaseOrderModal .supplier-order-view-kpis .is-total strong{color:var(--spo-dark)}#viewSupplierPurchaseOrderModal .supplier-order-view-kpis .is-finance{border-color:#e8dfc5;background:#fffaf0}
    #viewSupplierPurchaseOrderModal .supplier-order-view-info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}#viewSupplierPurchaseOrderModal .supplier-order-view-info-grid.summary-grid{grid-template-columns:repeat(5,minmax(0,1fr))}
    #viewSupplierPurchaseOrderModal .supplier-order-view-info-grid>div{min-width:0;min-height:66px;padding:11px 12px;border:1px solid #e4ede9;border-radius:11px;background:#fafcfb;transition:.18s ease}#viewSupplierPurchaseOrderModal .supplier-order-view-info-grid>div:hover{border-color:#c8e2d6;background:#fff}#viewSupplierPurchaseOrderModal .supplier-order-view-info-grid .is-wide{grid-column:span 2}
    #viewSupplierPurchaseOrderModal .supplier-order-view-info-grid span,#viewSupplierPurchaseOrderModal .supplier-order-view-finance-grid span,#viewSupplierPurchaseOrderModal .supplier-order-view-finance-status span{display:block;margin-bottom:4px;color:var(--spo-muted);font-size:9px;font-weight:600;text-transform:uppercase}
    #viewSupplierPurchaseOrderModal .supplier-order-view-info-grid strong,#viewSupplierPurchaseOrderModal .supplier-order-view-finance-grid strong,#viewSupplierPurchaseOrderModal .supplier-order-view-finance-status strong{display:block;min-width:0;color:var(--spo-text);font-size:12px;font-weight:500;line-height:1.5;white-space:normal;overflow-wrap:anywhere;word-break:break-word}
    #viewSupplierPurchaseOrderModal .supplier-order-view-summary-counts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:13px}#viewSupplierPurchaseOrderModal .supplier-order-view-summary-counts>div{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:9px;padding:10px 12px;border:1px solid #dcebe4;border-radius:11px;color:var(--spo-dark);background:var(--spo-soft)}#viewSupplierPurchaseOrderModal .supplier-order-view-summary-counts span{color:#5d7168;font-size:10px;font-weight:500}#viewSupplierPurchaseOrderModal .supplier-order-view-summary-counts strong{font-size:16px;font-weight:700}
    #viewSupplierPurchaseOrderModal .supplier-order-view-subheading{display:flex;align-items:center;gap:10px;margin:18px 0 10px}#viewSupplierPurchaseOrderModal .supplier-order-view-subheading>i{display:grid;width:34px;height:34px;place-items:center;border-radius:10px;color:var(--spo-green);background:var(--spo-soft)}#viewSupplierPurchaseOrderModal .supplier-order-view-subheading strong,#viewSupplierPurchaseOrderModal .supplier-order-view-subheading small{display:block}#viewSupplierPurchaseOrderModal .supplier-order-view-subheading strong{color:var(--spo-text);font-size:13px;font-weight:700}#viewSupplierPurchaseOrderModal .supplier-order-view-subheading small{color:var(--spo-muted);font-size:10px;font-weight:400}
    #viewSupplierPurchaseOrderModal .supplier-order-view-finance-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}#viewSupplierPurchaseOrderModal .supplier-order-view-finance-grid>div{padding:13px;border:1px solid #dfece6;border-radius:12px;background:#f9fcfa}#viewSupplierPurchaseOrderModal .supplier-order-view-finance-grid strong{font-size:16px;font-weight:700}#viewSupplierPurchaseOrderModal .supplier-order-view-finance-grid .is-balance{border-color:#eddcae;background:#fff8e8}
    #viewSupplierPurchaseOrderModal .supplier-order-view-finance-status{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px}#viewSupplierPurchaseOrderModal .supplier-order-view-finance-status>div{padding:11px 13px;border-left:3px solid var(--spo-green);border-radius:8px;background:#f6faf8}
    #viewSupplierPurchaseOrderModal .supplier-order-view-empty{display:flex;min-height:160px;padding:24px;align-items:center;justify-content:center;flex-direction:column;border:1px dashed #cdded6;border-radius:13px;color:var(--spo-muted);background:#fafcfb;text-align:center}#viewSupplierPurchaseOrderModal .supplier-order-view-empty i{margin-bottom:9px;color:#a8bdb3;font-size:30px}#viewSupplierPurchaseOrderModal .supplier-order-view-empty strong{color:#50645b;font-size:13px;font-weight:600}#viewSupplierPurchaseOrderModal .supplier-order-view-empty span{margin-top:3px;font-size:11px}
    #viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}#viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid.without-agency{grid-template-columns:1fr}#viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid article{min-height:180px;padding:18px;border:1px solid #dfeae5;border-radius:14px;background:#fbfdfc;transition:.18s ease}#viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid article:hover{transform:translateY(-2px);box-shadow:0 9px 20px rgba(30,68,53,.08)}
    #viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid article>i{display:grid;width:42px;height:42px;margin-bottom:18px;place-items:center;border-radius:12px;color:var(--spo-green);background:var(--spo-soft);font-size:18px}#viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid article>span{display:block;color:var(--spo-muted);font-size:9px;font-weight:600;text-transform:uppercase}#viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid article>strong{display:block;margin:4px 0 7px;color:var(--spo-text);font-size:13px;font-weight:500;overflow-wrap:anywhere}#viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid article>small{display:block;margin-top:4px;color:#65786f;font-size:10.5px;font-weight:400;line-height:1.4;overflow-wrap:anywhere}
    #viewSupplierPurchaseOrderModal .supplier-order-view-card-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(265px,1fr));gap:11px}#viewSupplierPurchaseOrderModal .supplier-order-view-card-grid>.supplier-order-view-empty{grid-column:1/-1}#viewSupplierPurchaseOrderModal .supplier-order-related-card,#viewSupplierPurchaseOrderModal .supplier-order-view-document,#viewSupplierPurchaseOrderModal .supplier-order-view-entry-card{min-width:0;padding:13px 14px;border:1px solid #dfeae5;border-radius:12px;background:#fbfdfc;transition:.18s ease}#viewSupplierPurchaseOrderModal .supplier-order-related-card:hover,#viewSupplierPurchaseOrderModal .supplier-order-view-document:hover,#viewSupplierPurchaseOrderModal .supplier-order-view-entry-card:hover{transform:translateY(-2px);border-color:#c5ded2;box-shadow:0 8px 18px rgba(31,68,54,.07)}#viewSupplierPurchaseOrderModal .supplier-order-related-card strong{font-weight:600}#viewSupplierPurchaseOrderModal .supplier-order-related-card small{display:block;margin-top:5px;color:#61746b;font-size:10.5px;font-weight:400}
    #viewSupplierPurchaseOrderModal .supplier-order-view-document{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:11px}#viewSupplierPurchaseOrderModal .supplier-order-view-document-icon{display:grid;width:40px;height:40px;place-items:center;border-radius:11px;color:#c63d3d;background:#fff0f0;font-size:18px}#viewSupplierPurchaseOrderModal .supplier-order-view-document-icon.is-image{color:#276d8d;background:#edf8fd}#viewSupplierPurchaseOrderModal .supplier-order-view-document strong,#viewSupplierPurchaseOrderModal .supplier-order-view-document small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}#viewSupplierPurchaseOrderModal .supplier-order-view-document strong{color:var(--spo-text);font-size:11.5px;font-weight:600}#viewSupplierPurchaseOrderModal .supplier-order-view-document small{margin-top:2px;color:var(--spo-muted);font-size:9.5px;font-weight:400}#viewSupplierPurchaseOrderModal .supplier-order-view-document.is-missing{opacity:.8;background:#f8faf9}
    #viewSupplierPurchaseOrderModal .supplier-order-view-table-wrap{max-width:100%;max-height:370px;overflow:auto;border:1px solid #e1ebe6;border-radius:12px}#viewSupplierPurchaseOrderModal .supplier-order-view-table{min-width:1160px}#viewSupplierPurchaseOrderModal .supplier-order-view-table thead th{position:sticky;top:0;z-index:2;padding:10px 8px;border:0;border-bottom:1px solid #dce9e2;color:#40564d;background:#edf7f2;font-size:9px;font-weight:700;white-space:nowrap}#viewSupplierPurchaseOrderModal .supplier-order-view-table tbody td{padding:9px 8px;border-top:1px solid #edf3f0;color:#2f423a;font-size:11px;font-weight:400;vertical-align:middle}#viewSupplierPurchaseOrderModal .supplier-order-view-table tbody tr:hover td{background:#f9fcfa}#viewSupplierPurchaseOrderModal .supplier-order-item-name{min-width:210px;font-weight:500}
    #viewSupplierPurchaseOrderModal .supplier-order-entry-status{display:inline-flex;min-width:92px;padding:5px 8px;align-items:center;justify-content:center;border-radius:999px;font-size:9.5px;font-weight:600;white-space:nowrap}#viewSupplierPurchaseOrderModal .supplier-order-entry-status.status-pending{color:#1f5f9e;background:#e7f3ff}#viewSupplierPurchaseOrderModal .supplier-order-entry-status.status-partial{color:#8b5d00;background:#fff3cd}#viewSupplierPurchaseOrderModal .supplier-order-entry-status.status-entered{color:#166534;background:#dcfce7}
    #viewSupplierPurchaseOrderModal .supplier-order-view-totals{overflow:hidden;border:1px solid #d9e8e1;border-radius:13px;background:#fff}#viewSupplierPurchaseOrderModal .supplier-order-view-totals>div{display:flex;padding:9px 12px;justify-content:space-between;gap:12px;border-bottom:1px solid #edf3f0;color:#50645b;font-size:11px}#viewSupplierPurchaseOrderModal .supplier-order-view-totals strong{font-weight:600}#viewSupplierPurchaseOrderModal .supplier-order-view-totals .grand{border-bottom:0;color:var(--spo-dark);background:var(--spo-soft);font-size:14px;font-weight:600}#viewSupplierPurchaseOrderModal .supplier-order-view-totals .grand strong{font-weight:700}
    #viewSupplierPurchaseOrderModal .supplier-order-view-payment-table{overflow:auto;border:1px solid #e1ebe6;border-radius:12px}#viewSupplierPurchaseOrderModal .supplier-order-view-payment-table table{min-width:780px;margin-bottom:0}#viewSupplierPurchaseOrderModal .supplier-order-view-payment-table th{border-top:0;color:#50645b;background:#f1f7f4;font-size:9px;font-weight:600;white-space:nowrap}#viewSupplierPurchaseOrderModal .supplier-order-view-payment-table td{font-size:10.5px;font-weight:400;vertical-align:middle}
    #viewSupplierPurchaseOrderModal .supplier-order-view-timeline{display:flex;margin:6px 0 22px;overflow-x:auto}#viewSupplierPurchaseOrderModal .supplier-order-view-timeline-step{position:relative;flex:1 0 140px;padding:34px 8px 0;color:#89978f;text-align:center}#viewSupplierPurchaseOrderModal .supplier-order-view-timeline-step:before{position:absolute;top:13px;left:0;width:100%;height:3px;content:'';background:#e2e9e5}#viewSupplierPurchaseOrderModal .supplier-order-view-timeline-step:after{position:absolute;top:7px;left:calc(50% - 8px);width:16px;height:16px;border:3px solid #fff;border-radius:50%;content:'';background:#cdd8d2;box-shadow:0 0 0 1px #d9e4de}#viewSupplierPurchaseOrderModal .supplier-order-view-timeline-step.is-complete{color:var(--spo-dark)}#viewSupplierPurchaseOrderModal .supplier-order-view-timeline-step.is-complete:before,#viewSupplierPurchaseOrderModal .supplier-order-view-timeline-step.is-complete:after{background:var(--spo-green)}#viewSupplierPurchaseOrderModal .supplier-order-view-timeline-step strong{display:block;font-size:10px;font-weight:600}
    #viewSupplierPurchaseOrderModal .supplier-order-view-entry-card>div{display:flex;align-items:flex-start;justify-content:space-between;gap:9px}#viewSupplierPurchaseOrderModal .supplier-order-view-entry-card h6{margin:0;color:var(--spo-text);font-size:13px;font-weight:700}#viewSupplierPurchaseOrderModal .supplier-order-view-entry-card dl{display:grid;grid-template-columns:auto 1fr;gap:5px 10px;margin:11px 0 0;font-size:10.5px}#viewSupplierPurchaseOrderModal .supplier-order-view-entry-card dt{color:var(--spo-muted);font-weight:600}#viewSupplierPurchaseOrderModal .supplier-order-view-entry-card dd{margin:0;color:var(--spo-text);font-weight:500;text-align:right;overflow-wrap:anywhere}
    #viewSupplierPurchaseOrderModal .supplier-order-view-footer{position:relative;z-index:2;flex:0 0 auto;padding:10px 18px;border-top:1px solid #e4ece8!important;background:#fff;box-shadow:0 -4px 14px rgba(29,63,50,.035)}#viewSupplierPurchaseOrderModal .supplier-order-view-footer .btn{border-radius:9px;font-weight:500;transition:.18s ease}#viewSupplierPurchaseOrderModal .supplier-order-view-footer .btn:hover{transform:translateY(-1px);box-shadow:0 5px 12px rgba(29,63,50,.08)}
    @keyframes supplierOrderIconFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-4px)}}
    @media(prefers-reduced-motion:reduce){#viewSupplierPurchaseOrderModal .supplier-order-view-icon>i{animation:none}#viewSupplierPurchaseOrderModal .supplier-order-view-icon,#viewSupplierPurchaseOrderModal .supplier-order-view-side-total{transition:none}}
    @media(min-width:992px){#viewSupplierPurchaseOrderModal .supplier-order-view-content{height:calc(100vh - 32px)}}
    @media(min-width:1200px){#viewSupplierPurchaseOrderModal .supplier-order-view-dialog{width:calc(100vw - 50px);max-width:1540px}}
    @media(max-width:1199px){#viewSupplierPurchaseOrderModal .supplier-order-view-info-grid.summary-grid{grid-template-columns:repeat(3,minmax(0,1fr))}#viewSupplierPurchaseOrderModal .supplier-order-view-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:991px){#viewSupplierPurchaseOrderModal .supplier-order-view-dialog{margin:8px auto}#viewSupplierPurchaseOrderModal .supplier-order-view-content{height:calc(100vh - 16px);max-height:calc(100vh - 16px)}#viewSupplierPurchaseOrderModal .supplier-order-view-body{overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain}#viewSupplierPurchaseOrderModal .supplier-order-view-workspace{height:auto}#viewSupplierPurchaseOrderModal .supplier-order-view-sidebar-wrap{padding:12px 12px 5px}#viewSupplierPurchaseOrderModal .supplier-order-view-sidebar{height:auto;overflow:visible}#viewSupplierPurchaseOrderModal .supplier-order-view-main{height:auto;min-height:0;padding:7px 12px 16px;overflow:visible}#viewSupplierPurchaseOrderModal .supplier-order-view-tab-content{padding-bottom:22px;overflow:visible}}
    @media(max-width:767px){#viewSupplierPurchaseOrderModal .supplier-order-view-header{padding:12px 14px}#viewSupplierPurchaseOrderModal .supplier-order-view-header-icon,#viewSupplierPurchaseOrderModal .supplier-order-view-section-heading>i{display:none}#viewSupplierPurchaseOrderModal .supplier-order-view-tab-content{padding:12px}#viewSupplierPurchaseOrderModal .supplier-order-view-kpis,#viewSupplierPurchaseOrderModal .supplier-order-view-info-grid,#viewSupplierPurchaseOrderModal .supplier-order-view-info-grid.summary-grid,#viewSupplierPurchaseOrderModal .supplier-order-view-finance-grid,#viewSupplierPurchaseOrderModal .supplier-order-view-finance-status,#viewSupplierPurchaseOrderModal .supplier-order-view-logistics-grid{grid-template-columns:1fr}#viewSupplierPurchaseOrderModal .supplier-order-view-info-grid .is-wide{grid-column:auto}#viewSupplierPurchaseOrderModal .supplier-order-view-summary-counts{grid-template-columns:1fr}}
</style>
