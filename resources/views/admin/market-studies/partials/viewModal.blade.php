<div class="modal fade" id="viewMarketStudyModal" tabindex="-1" role="dialog"
    aria-labelledby="viewMarketStudyModalLabel" aria-hidden="true" data-storage-base="{{ asset('storage') }}">
    <div class="modal-dialog modal-xl modal-dialog-centered market-study-view-dialog" role="document">
        <div class="modal-content border-0 shadow market-study-view-content">
            <div class="modal-header market-study-view-header">
                <div class="d-flex align-items-center min-width-0 market-study-view-header-copy">
                    <span class="market-study-view-header-icon mr-3"><i class="fas fa-chart-line"></i></span>
                    <div class="min-width-0">
                        <h5 class="modal-title mb-0" id="viewMarketStudyModalLabel">
                            Informaci&oacute;n del Estudio de Mercado
                        </h5>
                        <small id="view_header_subtitle" class="market-study-view-subtitle">
                            Vista ejecutiva, comparativa y documentaria
                        </small>
                    </div>
                </div>
                <button type="button" class="close market-study-view-close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body market-study-view-body">
                <nav class="market-study-view-tabs-wrap" aria-label="Secciones del estudio de mercado">
                    <div class="nav nav-pills market-study-view-tabs" role="tablist">
                        <a class="nav-link active" data-toggle="pill" href="#view_market_summary" role="tab"><i class="fas fa-chart-pie"></i>Resumen</a>
                        <a class="nav-link" data-toggle="pill" href="#view_market_general" role="tab"><i class="fas fa-file-alt"></i>Datos generales</a>
                        <a class="nav-link" data-toggle="pill" href="#view_market_suppliers" role="tab"><i class="fas fa-building"></i>Proveedores</a>
                        <a class="nav-link" data-toggle="pill" href="#view_market_award" role="tab"><i class="fas fa-trophy"></i>Adjudicaci&oacute;n</a>
                        <a class="nav-link" data-toggle="pill" href="#view_market_sanitary" role="tab"><i class="fas fa-shield-virus"></i>Informaci&oacute;n sanitaria</a>
                        <a class="nav-link" data-toggle="pill" href="#view_market_economic" role="tab"><i class="fas fa-calculator"></i>Resumen econ&oacute;mico</a>
                        <a class="nav-link" data-toggle="pill" href="#view_market_documents" role="tab"><i class="fas fa-folder-open"></i>Documentos</a>
                    </div>
                </nav>

                <div class="tab-content market-study-view-tab-content">
                    <section class="tab-pane fade show active" id="view_market_summary" role="tabpanel">
                        <div class="market-study-view-section-heading">
                            <div><span>Vista ejecutiva</span><h6>Resumen del estudio</h6><small>Indicadores principales y resultado econ&oacute;mico consolidado.</small></div>
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="market-study-view-summary-grid">
                            <article class="market-study-view-summary-card is-code"><span class="market-study-view-card-icon"><i class="fas fa-barcode"></i></span><div><small>C&oacute;digo</small><strong id="view_summary_code">-</strong></div></article>
                            <article class="market-study-view-summary-card is-description"><span class="market-study-view-card-icon"><i class="fas fa-align-left"></i></span><div><small>Descripci&oacute;n</small><strong id="view_summary_description">-</strong></div></article>
                            <article class="market-study-view-summary-card"><span class="market-study-view-card-icon"><i class="fas fa-building"></i></span><div><small>Proveedores</small><strong><span id="view_total_suppliers">0</span> proveedores</strong></div></article>
                            <article class="market-study-view-summary-card"><span class="market-study-view-card-icon"><i class="fas fa-boxes"></i></span><div><small>Art&iacute;culos evaluados</small><strong id="view_total_items">0</strong></div></article>
                            <article class="market-study-view-summary-card"><span class="market-study-view-card-icon"><i class="fas fa-file-invoice"></i></span><div><small>Cotizaciones</small><strong id="view_total_quotes">0</strong></div></article>
                            <article class="market-study-view-summary-card"><span class="market-study-view-card-icon"><i class="fas fa-toggle-on"></i></span><div><small>Estado</small><strong id="view_summary_status">-</strong></div></article>
                            <article class="market-study-view-summary-card"><span class="market-study-view-card-icon"><i class="fas fa-award"></i></span><div><small>Total adjudicado</small><strong id="view_award_status" class="market-study-view-inline-badge">Sin adjudicaci&oacute;n</strong></div></article>
                            <article class="market-study-view-summary-card is-money"><span class="market-study-view-card-icon"><i class="fas fa-receipt"></i></span><div><small>IGV</small><strong id="view_summary_igv">S/ 0.00</strong></div></article>
                            <article class="market-study-view-summary-card is-total"><span class="market-study-view-card-icon"><i class="fas fa-coins"></i></span><div><small>Total general</small><strong id="view_summary_total">S/ 0.00</strong></div></article>
                        </div>
                    </section>

                    <section class="tab-pane fade" id="view_market_general" role="tabpanel">
                        <div class="market-study-view-section-heading">
                            <div><span>Identificaci&oacute;n</span><h6>Datos generales</h6><small>Informaci&oacute;n registrada para este estudio de mercado.</small></div>
                            <i class="fas fa-file-signature"></i>
                        </div>
                        <div class="market-study-view-info-grid">
                            <div><span>C&oacute;digo</span><strong id="view_code">-</strong></div>
                            <div><span>Estado</span><strong id="view_general_status">-</strong></div>
                            <div><span>Fecha de registro</span><strong id="view_created_at">-</strong></div>
                            <div class="is-wide"><span>Descripci&oacute;n</span><strong id="view_description">-</strong></div>
                            <div id="view_responsible_card" class="d-none"><span>Usuario responsable</span><strong id="view_responsible">-</strong></div>
                        </div>
                        <div class="market-study-view-reference-card">
                            <span><i class="fas fa-clipboard-list mr-1"></i>T&eacute;rminos de referencia</span>
                            <div id="view_terms">No hay t&eacute;rminos de referencia registrados.</div>
                        </div>
                    </section>

                    <section class="tab-pane fade" id="view_market_suppliers" role="tabpanel">
                        <div class="market-study-view-section-heading">
                            <div><span>Participantes</span><h6>Proveedores participantes</h6><small>Cotizaciones y condiciones comerciales registradas.</small></div>
                            <i class="fas fa-users"></i>
                        </div>
                        <div id="viewSuppliersTableWrap" class="market-study-view-table-wrap">
                            <table class="table table-hover market-study-view-table mb-0">
                                <thead><tr><th>Proveedor</th><th>Moneda</th><th>Condici&oacute;n</th><th class="text-center">Estado</th></tr></thead>
                                <tbody id="viewSuppliersBody"></tbody>
                            </table>
                        </div>
                        <div id="viewSuppliersEmpty" class="market-study-view-empty d-none">
                            <i class="fas fa-building"></i><strong>No hay proveedores participantes registrados.</strong><span>Cuando se agreguen cotizaciones, aparecer&aacute;n en esta secci&oacute;n.</span>
                        </div>
                    </section>

                    <section class="tab-pane fade" id="view_market_award" role="tabpanel">
                        <div class="market-study-view-section-heading">
                            <div><span>Resultado final</span><h6>Adjudicaci&oacute;n por art&iacute;culo</h6><small>Proveedor seleccionado y valores registrados en cada propuesta.</small></div>
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div id="viewComparisonTableWrap" class="market-study-view-table-wrap">
                            <table class="table table-hover market-study-view-table is-award-table mb-0">
                                <thead><tr><th>Art&iacute;culo</th><th>Proveedor ganador</th><th>Marca</th><th>Presentaci&oacute;n</th><th class="text-right">Cantidad</th><th class="text-right">P. unitario</th><th class="text-right">Total</th><th class="text-center">Estado</th></tr></thead>
                                <tbody id="viewComparisonBody"></tbody>
                            </table>
                        </div>
                        <div id="viewComparisonEmpty" class="market-study-view-empty d-none">
                            <i class="fas fa-box-open"></i><strong>No hay art&iacute;culos para evaluar.</strong><span>La adjudicaci&oacute;n aparecer&aacute; cuando existan art&iacute;culos registrados.</span>
                        </div>
                    </section>

                    <section class="tab-pane fade" id="view_market_sanitary" role="tabpanel">
                        <div class="market-study-view-section-heading">
                            <div><span>Cumplimiento</span><h6>Informaci&oacute;n sanitaria</h6><small>Situaci&oacute;n sanitaria de los proveedores adjudicados.</small></div>
                            <i class="fas fa-shield-virus"></i>
                        </div>
                        <div id="viewSanitaryContainer"></div>
                    </section>

                    <section class="tab-pane fade" id="view_market_economic" role="tabpanel">
                        <div class="market-study-view-section-heading">
                            <div><span>Consolidado</span><h6>Resumen econ&oacute;mico</h6><small>Importes calculados a partir de la adjudicaci&oacute;n existente.</small></div>
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="market-study-view-economic-grid">
                            <article><span>Base gravada</span><strong id="view_gravada">S/ 0.00</strong></article>
                            <article><span>Inafecta</span><strong id="view_inafecta">S/ 0.00</strong></article>
                            <article><span>Exonerada</span><strong id="view_exonerada">S/ 0.00</strong></article>
                            <article><span>IGV</span><strong id="view_igv">S/ 0.00</strong></article>
                            <article class="is-total"><span>Total general</span><strong id="view_total">S/ 0.00</strong></article>
                        </div>
                    </section>

                    <section class="tab-pane fade" id="view_market_documents" role="tabpanel">
                        <div class="market-study-view-section-heading">
                            <div><span>Expediente digital</span><h6>Documentos adjuntos</h6><small>Archivos existentes vinculados con el estudio de mercado.</small></div>
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <div id="viewDocumentsContainer" class="market-study-view-document-grid"></div>
                        <div id="viewDocumentsEmpty" class="market-study-view-empty d-none">
                            <i class="fas fa-folder-open"></i><strong>No hay documentos adjuntos.</strong><span>Este estudio no tiene archivos registrados.</span>
                        </div>
                    </section>
                </div>
            </div>

            <div class="modal-footer market-study-view-footer">
                <small class="text-muted mr-auto d-none d-md-inline"><i class="fas fa-info-circle mr-1"></i>Seleccione una pesta&ntilde;a para consultar cada secci&oacute;n.</small>
                <button type="button" class="btn btn-light border px-4" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
    #viewMarketStudyModal{--ms-green:#16805e;--ms-dark:#116348;--ms-soft:#edf8f3;--ms-border:#dfeae4;--ms-heading:#0f172a;--ms-text:#263a33;--ms-muted:#64748b}
    #viewMarketStudyModal .min-width-0{min-width:0}
    #viewMarketStudyModal .market-study-view-dialog{margin:16px auto}
    #viewMarketStudyModal .market-study-view-content{display:flex;max-height:calc(100vh - 32px);overflow:hidden;flex-direction:column;border-radius:16px;background:#f5f8f7}
    #viewMarketStudyModal .market-study-view-header{flex:0 0 auto;align-items:center;padding:14px 20px;border:0;border-bottom:1px solid #dce9e3;background:linear-gradient(135deg,#fff 0%,#f1f8f5 100%)}
    #viewMarketStudyModal .market-study-view-header-copy{flex:1 1 auto;padding-right:14px}
    #viewMarketStudyModal .market-study-view-header-icon{display:grid;flex:0 0 46px;width:46px;height:46px;place-items:center;border:1px solid #cfe6dc;border-radius:13px;color:var(--ms-green);background:var(--ms-soft);font-size:20px}
    #viewMarketStudyModal .modal-title{color:var(--ms-heading);font-size:18px;font-weight:700;line-height:1.3;white-space:normal;overflow-wrap:anywhere}
    #viewMarketStudyModal .market-study-view-subtitle{display:block;margin-top:3px;color:var(--ms-muted);font-size:11px;font-weight:400;line-height:1.4;white-space:normal;overflow-wrap:anywhere}
    #viewMarketStudyModal .market-study-view-close{display:grid;flex:0 0 34px;width:34px;height:34px;margin:0;padding:0;place-items:center;border:1px solid #dce6e1;border-radius:10px;color:#475569;background:#fff;font-size:22px;line-height:1;opacity:1;transition:.18s ease}
    #viewMarketStudyModal .market-study-view-close:hover{border-color:#c7d8d0;color:var(--ms-dark);background:#f7faf9;transform:translateY(-1px)}
    #viewMarketStudyModal .market-study-view-body{flex:1 1 auto;min-height:0;padding:0 18px 24px;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;scrollbar-gutter:stable}
    #viewMarketStudyModal .market-study-view-tabs-wrap{position:sticky;z-index:5;top:0;padding:12px 0 10px;background:#f5f8f7}
    #viewMarketStudyModal .market-study-view-tabs{display:flex;flex-wrap:nowrap;gap:6px;padding:7px;overflow-x:auto;border:1px solid var(--ms-border);border-radius:13px;background:#fff;box-shadow:0 6px 18px rgba(27,67,52,.05);scrollbar-width:thin}
    #viewMarketStudyModal .market-study-view-tabs .nav-link{display:inline-flex;flex:0 0 auto;align-items:center;gap:6px;padding:8px 11px;border-radius:9px;color:#60746b;font-size:10.5px;font-weight:600;white-space:nowrap;transition:.18s ease}
    #viewMarketStudyModal .market-study-view-tabs .nav-link:hover{color:var(--ms-green);background:#f3faf6;transform:translateY(-1px)}
    #viewMarketStudyModal .market-study-view-tabs .nav-link.active{color:#fff;background:linear-gradient(135deg,var(--ms-green),var(--ms-dark));box-shadow:0 5px 12px rgba(22,128,94,.18)}
    #viewMarketStudyModal .market-study-view-tab-content{padding:6px 0 2px}
    #viewMarketStudyModal .market-study-view-tab-content>.tab-pane{min-width:0;padding-bottom:6px}
    #viewMarketStudyModal .market-study-view-section-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:15px;padding:15px 16px;border:1px solid var(--ms-border);border-radius:14px;background:#fff;box-shadow:0 7px 20px rgba(27,67,52,.045)}
    #viewMarketStudyModal .market-study-view-section-heading span{display:block;color:var(--ms-green);font-size:9px;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
    #viewMarketStudyModal .market-study-view-section-heading h6{margin:2px 0;color:var(--ms-heading);font-size:17px;font-weight:700}
    #viewMarketStudyModal .market-study-view-section-heading small{display:block;color:var(--ms-muted);font-size:11px;font-weight:400}
    #viewMarketStudyModal .market-study-view-section-heading>i{display:grid;flex:0 0 42px;width:42px;height:42px;place-items:center;border-radius:12px;color:var(--ms-green);background:var(--ms-soft);font-size:17px}
    #viewMarketStudyModal .market-study-view-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px}
    #viewMarketStudyModal .market-study-view-summary-card{display:flex;min-width:0;min-height:92px;padding:14px;align-items:center;gap:12px;border:1px solid #e1ebe6;border-radius:13px;background:#fff;box-shadow:0 6px 16px rgba(27,67,52,.035);transition:.18s ease}
    #viewMarketStudyModal .market-study-view-summary-card:hover{border-color:#c9dfd5;transform:translateY(-2px);box-shadow:0 9px 20px rgba(27,67,52,.075)}
    #viewMarketStudyModal .market-study-view-summary-card.is-description{grid-column:span 2}
    #viewMarketStudyModal .market-study-view-summary-card.is-total{border-color:#cce5da;background:linear-gradient(145deg,#f3fbf7,#e9f7f1)}
    #viewMarketStudyModal .market-study-view-card-icon{display:grid;flex:0 0 40px;width:40px;height:40px;place-items:center;border-radius:11px;color:var(--ms-green);background:var(--ms-soft);font-size:16px}
    #viewMarketStudyModal .market-study-view-summary-card>div{min-width:0}
    #viewMarketStudyModal .market-study-view-summary-card small{display:block;margin-bottom:4px;color:var(--ms-muted);font-size:9px;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
    #viewMarketStudyModal .market-study-view-summary-card strong{display:block;color:var(--ms-text);font-size:14px;font-weight:500;line-height:1.4;white-space:normal;overflow-wrap:anywhere;word-break:break-word}
    #viewMarketStudyModal .market-study-view-summary-card.is-code strong,#viewMarketStudyModal .market-study-view-summary-card.is-money strong{font-size:18px;font-weight:700}
    #viewMarketStudyModal .market-study-view-summary-card.is-total strong{color:var(--ms-dark);font-size:21px;font-weight:700}
    #viewMarketStudyModal .market-study-view-inline-badge{display:inline-flex!important;width:max-content;max-width:100%;padding:5px 9px;align-items:center;border-radius:999px;color:#7c5c13!important;background:#fff3cd;font-size:10px!important;font-weight:600!important}
    #viewMarketStudyModal .market-study-view-inline-badge.has-winners{color:#176244!important;background:#def4e9}
    #viewMarketStudyModal .market-study-view-info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:11px}
    #viewMarketStudyModal .market-study-view-info-grid>div{min-width:0;min-height:72px;padding:13px 14px;border:1px solid #e1ebe6;border-radius:12px;background:#fff}
    #viewMarketStudyModal .market-study-view-info-grid .is-wide{grid-column:span 2}
    #viewMarketStudyModal .market-study-view-info-grid span,#viewMarketStudyModal .market-study-view-reference-card>span{display:block;margin-bottom:5px;color:var(--ms-muted);font-size:9px;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
    #viewMarketStudyModal .market-study-view-info-grid strong{display:block;color:var(--ms-text);font-size:12px;font-weight:500;line-height:1.5;white-space:normal;overflow-wrap:anywhere;word-break:break-word}
    #viewMarketStudyModal .market-study-view-reference-card{margin-top:11px;padding:15px 16px;border:1px solid #e1ebe6;border-radius:13px;background:#fff}
    #viewMarketStudyModal .market-study-view-reference-card>div{min-height:54px;padding:12px;border-radius:10px;color:#40534b;background:#f7faf8;font-size:12px;font-weight:400;line-height:1.6;white-space:pre-wrap;overflow-wrap:anywhere}
    #viewMarketStudyModal .market-study-view-table-wrap{max-width:100%;overflow:auto;border:1px solid #dfe9e4;border-radius:13px;background:#fff;box-shadow:0 6px 18px rgba(27,67,52,.04)}
    #viewMarketStudyModal .market-study-view-table{min-width:720px;font-size:11.5px}
    #viewMarketStudyModal .market-study-view-table.is-award-table{min-width:1080px}
    #viewMarketStudyModal .market-study-view-table thead th{padding:10px 9px;border:0;border-bottom:1px solid #dbe7e1;color:#40564d;background:#edf6f2;font-size:9px;font-weight:700;vertical-align:middle;white-space:nowrap;text-transform:uppercase}
    #viewMarketStudyModal .market-study-view-table tbody td{padding:10px 9px;border-top:1px solid #edf2ef;color:#30433b;font-weight:400;vertical-align:middle}
    #viewMarketStudyModal .market-study-view-table tbody tr:first-child td{border-top:0}
    #viewMarketStudyModal .market-study-view-table tbody tr:hover td{background:#fafcfb}
    #viewMarketStudyModal .market-study-view-status{display:inline-flex;padding:5px 9px;align-items:center;border-radius:999px;font-size:9.5px;font-weight:600;white-space:nowrap}
    #viewMarketStudyModal .market-study-view-status.is-active,#viewMarketStudyModal .market-study-view-status.has-winner{color:#176244;background:#def4e9}
    #viewMarketStudyModal .market-study-view-status.is-inactive{color:#5e6b65;background:#e9eeeb}
    #viewMarketStudyModal .market-study-view-status.no-winner{color:#795c18;background:#fff1c7}
    #viewMarketStudyModal .market-study-view-empty{display:flex;min-height:190px;padding:26px;align-items:center;justify-content:center;flex-direction:column;border:1px dashed #cadbd3;border-radius:14px;color:var(--ms-muted);background:#fafcfb;text-align:center}
    #viewMarketStudyModal .market-study-view-empty i{margin-bottom:10px;color:#9fb6ab;font-size:31px}
    #viewMarketStudyModal .market-study-view-empty strong{color:#43574e;font-size:13px;font-weight:600}
    #viewMarketStudyModal .market-study-view-empty span{margin-top:4px;font-size:11px;font-weight:400}
    #viewMarketStudyModal .market-study-view-sanitary-state{display:flex;min-height:150px;padding:22px;align-items:center;gap:15px;border:1px solid #d9e7e1;border-radius:14px;color:#4f625a;background:#f8fbfa}
    #viewMarketStudyModal .market-study-view-sanitary-state>i{display:grid;flex:0 0 46px;width:46px;height:46px;place-items:center;border-radius:13px;color:#53796a;background:#e8f3ee;font-size:19px}
    #viewMarketStudyModal .market-study-view-sanitary-state strong,#viewMarketStudyModal .market-study-view-sanitary-state span{display:block}
    #viewMarketStudyModal .market-study-view-sanitary-state strong{color:#354b42;font-size:13px;font-weight:600}
    #viewMarketStudyModal .market-study-view-sanitary-state span{margin-top:3px;font-size:11px;font-weight:400}
    #viewMarketStudyModal .market-study-view-sanitary-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:11px}
    #viewMarketStudyModal .market-study-view-sanitary-card{min-width:0;padding:15px;border:1px solid #dfe9e4;border-radius:13px;background:#fff;box-shadow:0 6px 18px rgba(27,67,52,.04)}
    #viewMarketStudyModal .market-study-view-sanitary-card-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding-bottom:10px;border-bottom:1px solid #edf2ef}
    #viewMarketStudyModal .market-study-view-sanitary-card-heading>span:first-child{color:var(--ms-text);font-size:12px;font-weight:600;line-height:1.4;overflow-wrap:anywhere}
    #viewMarketStudyModal .market-study-view-sanitary-card dl{display:grid;margin:11px 0 0;grid-template-columns:auto minmax(0,1fr);gap:6px 12px;font-size:10.5px}
    #viewMarketStudyModal .market-study-view-sanitary-card dt{color:var(--ms-muted);font-weight:600}
    #viewMarketStudyModal .market-study-view-sanitary-card dd{margin:0;color:var(--ms-text);font-weight:400;text-align:right;overflow-wrap:anywhere}
    #viewMarketStudyModal .market-study-view-economic-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:11px;padding-bottom:8px}
    #viewMarketStudyModal .market-study-view-economic-grid article{min-width:0;padding:18px 15px;border:1px solid #e1ebe6;border-radius:13px;background:#fff;text-align:center;box-shadow:0 6px 18px rgba(27,67,52,.04)}
    #viewMarketStudyModal .market-study-view-economic-grid article span{display:block;margin-bottom:7px;color:var(--ms-muted);font-size:9px;font-weight:600;letter-spacing:.04em;text-transform:uppercase}
    #viewMarketStudyModal .market-study-view-economic-grid article strong{display:block;color:var(--ms-text);font-size:19px;font-weight:700;line-height:1.25;overflow-wrap:anywhere}
    #viewMarketStudyModal .market-study-view-economic-grid article.is-total{border-color:#bcdcca;background:linear-gradient(145deg,#edf9f3,#dff2e9)}
    #viewMarketStudyModal .market-study-view-economic-grid article.is-total strong{color:var(--ms-dark);font-size:23px}
    #viewMarketStudyModal .market-study-view-document-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:11px}
    #viewMarketStudyModal .market-study-view-document{display:grid;min-width:0;padding:13px 14px;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:11px;border:1px solid #dfe9e4;border-radius:12px;background:#fff;transition:.18s ease}
    #viewMarketStudyModal .market-study-view-document:hover{border-color:#c6ddd2;transform:translateY(-2px);box-shadow:0 8px 18px rgba(27,67,52,.07)}
    #viewMarketStudyModal .market-study-view-document-icon{display:grid;width:40px;height:40px;place-items:center;border-radius:11px;color:#b23a3a;background:#fff0f0;font-size:17px}
    #viewMarketStudyModal .market-study-view-document>div{min-width:0}
    #viewMarketStudyModal .market-study-view-document strong,#viewMarketStudyModal .market-study-view-document small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    #viewMarketStudyModal .market-study-view-document strong{color:var(--ms-text);font-size:11.5px;font-weight:600}
    #viewMarketStudyModal .market-study-view-document small{margin-top:3px;color:var(--ms-muted);font-size:9.5px;font-weight:400}
    #viewMarketStudyModal .market-study-view-document .btn{border-radius:9px;font-size:10px;font-weight:500}
    #viewMarketStudyModal .market-study-view-footer{position:relative;z-index:6;flex:0 0 auto;padding:10px 18px;border:0;border-top:1px solid #e2ebe6;background:#fff;box-shadow:0 -4px 14px rgba(29,63,50,.035)}
    #viewMarketStudyModal .market-study-view-footer .btn{border-radius:9px;font-size:12px;font-weight:500;transition:.18s ease}
    #viewMarketStudyModal .market-study-view-footer .btn:hover{transform:translateY(-1px);box-shadow:0 5px 12px rgba(29,63,50,.08)}
    @media(min-width:1200px){#viewMarketStudyModal .market-study-view-dialog{width:calc(100vw - 50px);max-width:1500px}}
    @media(max-width:1199px){#viewMarketStudyModal .market-study-view-summary-grid{grid-template-columns:repeat(3,minmax(0,1fr))}#viewMarketStudyModal .market-study-view-economic-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:991px){#viewMarketStudyModal .market-study-view-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}#viewMarketStudyModal .market-study-view-info-grid{grid-template-columns:repeat(2,minmax(0,1fr))}#viewMarketStudyModal .market-study-view-economic-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:767px){#viewMarketStudyModal .market-study-view-dialog{margin:8px auto}#viewMarketStudyModal .market-study-view-content{max-height:calc(100vh - 16px)}#viewMarketStudyModal .market-study-view-header{padding:12px 14px}#viewMarketStudyModal .market-study-view-header-icon,#viewMarketStudyModal .market-study-view-section-heading>i{display:none}#viewMarketStudyModal .market-study-view-body{padding:0 12px 18px}#viewMarketStudyModal .market-study-view-summary-grid,#viewMarketStudyModal .market-study-view-info-grid,#viewMarketStudyModal .market-study-view-economic-grid{grid-template-columns:1fr}#viewMarketStudyModal .market-study-view-summary-card.is-description,#viewMarketStudyModal .market-study-view-info-grid .is-wide{grid-column:auto}#viewMarketStudyModal .market-study-view-section-heading{padding:13px}#viewMarketStudyModal .market-study-view-economic-grid article{text-align:left}}
</style>
