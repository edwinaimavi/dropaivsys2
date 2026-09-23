<div class="modal fade dp-detail-modal electronic-invoice-detail-modal" id="viewElectronicInvoiceModal" tabindex="-1"
    role="dialog" aria-labelledby="viewElectronicInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered electronic-invoice-detail-dialog" role="document">
        <div class="modal-content border-0 electronic-invoice-detail">
            <div class="modal-header dp-detail-modal-header electronic-invoice-detail__header">
                <div class="dp-detail-modal-heading">
                    <span class="dp-detail-modal-icon electronic-invoice-detail__header-icon" aria-hidden="true">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </span>
                    <div class="dp-detail-modal-copy">
                        <h5 class="modal-title dp-detail-modal-title" id="viewElectronicInvoiceModalLabel">
                            Detalle del comprobante
                        </h5>
                        <small class="dp-detail-modal-subtitle">Resumen comercial, tributario y de cobranza del documento</small>
                    </div>
                </div>
                <button type="button" class="close dp-detail-modal-close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body electronic-invoice-detail__body">
                <div class="electronic-invoice-detail__layout">
                    <aside class="electronic-invoice-summary" aria-label="Resumen del comprobante">
                        <div class="electronic-invoice-summary__accent"></div>
                        <div class="electronic-invoice-summary__icon" aria-hidden="true">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <span class="electronic-invoice-summary__eyebrow">Comprobante</span>
                        <h4 id="vei_full_number" class="electronic-invoice-summary__number">-</h4>
                        <div id="vei_document_type" class="electronic-invoice-summary__type">-</div>
                        <span id="vei_status" class="electronic-invoice-status electronic-invoice-status--neutral">Borrador</span>

                        <div class="electronic-invoice-summary__divider"></div>
                        <div class="electronic-invoice-summary__client">
                            <span class="electronic-invoice-summary__label">
                                <i class="far fa-building mr-1" aria-hidden="true"></i> Cliente
                            </span>
                            <strong id="vei_client_name">-</strong>
                        </div>
                        <div class="electronic-invoice-summary__total">
                            <span>Importe total</span>
                            <strong id="vei_total_amount">0.00</strong>
                        </div>
                    </aside>

                    <div class="electronic-invoice-detail__content">
                        <section class="electronic-invoice-panel" aria-labelledby="vei_general_title">
                            <div class="electronic-invoice-section-heading">
                                <span class="electronic-invoice-section-heading__icon" aria-hidden="true">
                                    <i class="fas fa-info-circle"></i>
                                </span>
                                <div>
                                    <h6 id="vei_general_title">Informaci&oacute;n general</h6>
                                    <p>Datos de emisi&oacute;n y trazabilidad del documento</p>
                                </div>
                            </div>

                            <div class="electronic-invoice-info-grid">
                                <div class="electronic-invoice-info electronic-invoice-info--wide">
                                    <span class="electronic-invoice-info__label">Empresa emisora</span>
                                    <strong id="vei_company">-</strong>
                                    <small>RUC <span id="vei_company_ruc">-</span></small>
                                </div>
                                <div class="electronic-invoice-info">
                                    <span class="electronic-invoice-info__label">Documento cliente</span>
                                    <strong id="vei_client_document">-</strong>
                                </div>
                                <div class="electronic-invoice-info electronic-invoice-info--highlight">
                                    <span class="electronic-invoice-info__label">Fecha de emisi&oacute;n</span>
                                    <strong id="vei_issue_date">-</strong>
                                </div>
                                <div class="electronic-invoice-info">
                                    <span class="electronic-invoice-info__label">Moneda</span>
                                    <strong id="vei_currency">-</strong>
                                </div>
                                <div class="electronic-invoice-info">
                                    <span class="electronic-invoice-info__label">Forma de pago</span>
                                    <strong id="vei_payment_type">-</strong>
                                </div>
                                <div class="electronic-invoice-info electronic-invoice-info--wide">
                                    <span class="electronic-invoice-info__label">Orden de compra del cliente</span>
                                    <strong id="vei_purchase_order">-</strong>
                                </div>
                                <div class="electronic-invoice-info electronic-invoice-info--status">
                                    <span class="electronic-invoice-info__label">Estado SUNAT</span>
                                    <span id="vei_sunat_status" class="electronic-invoice-status electronic-invoice-status--neutral">Pendiente</span>
                                </div>
                                <div class="electronic-invoice-info electronic-invoice-info--status">
                                    <span class="electronic-invoice-info__label">Estado de cobro</span>
                                    <span id="vei_payment_status" class="electronic-invoice-status electronic-invoice-status--warning">Pendiente</span>
                                </div>
                                <div class="electronic-invoice-info electronic-invoice-info--status">
                                    <span class="electronic-invoice-info__label">Despacho relacionado</span>
                                    <span id="vei_dispatch" class="electronic-invoice-status electronic-invoice-status--neutral">-</span>
                                </div>
                                <div class="electronic-invoice-info electronic-invoice-info--wide">
                                    <span class="electronic-invoice-info__label">Almac&eacute;n de salida</span>
                                    <strong id="vei_warehouse">-</strong>
                                    <small id="vei_warehouse_note" class="d-none"></small>
                                </div>
                                <div class="electronic-invoice-info electronic-invoice-info--full">
                                    <span class="electronic-invoice-info__label">Observaci&oacute;n</span>
                                    <strong id="vei_observations">-</strong>
                                </div>
                            </div>
                        </section>

                        <section class="electronic-invoice-kpis" aria-label="Resumen de cobranza">
                            <div class="electronic-invoice-kpi electronic-invoice-kpi--total">
                                <span class="electronic-invoice-kpi__icon"><i class="fas fa-coins"></i></span>
                                <div><small>Total</small><strong id="vei_kpi_total">0.00</strong></div>
                            </div>
                            <div class="electronic-invoice-kpi electronic-invoice-kpi--paid">
                                <span class="electronic-invoice-kpi__icon"><i class="fas fa-check"></i></span>
                                <div><small>Cobrado</small><strong id="vei_paid_amount">0.00</strong></div>
                            </div>
                            <div class="electronic-invoice-kpi electronic-invoice-kpi--pending">
                                <span class="electronic-invoice-kpi__icon"><i class="far fa-clock"></i></span>
                                <div><small>Saldo pendiente</small><strong id="vei_pending_amount">0.00</strong></div>
                            </div>
                            <div class="electronic-invoice-kpi electronic-invoice-kpi--status">
                                <span class="electronic-invoice-kpi__icon"><i class="fas fa-wallet"></i></span>
                                <div><small>Estado de cobro</small><span id="vei_kpi_payment_status" class="electronic-invoice-status electronic-invoice-status--warning">Pendiente</span></div>
                            </div>
                        </section>
                    </div>
                </div>

                <section class="electronic-invoice-panel electronic-invoice-panel--table" aria-labelledby="vei_items_title">
                    <div class="electronic-invoice-section-heading">
                        <span class="electronic-invoice-section-heading__icon" aria-hidden="true"><i class="fas fa-box-open"></i></span>
                        <div>
                            <h6 id="vei_items_title">Detalle de productos</h6>
                            <p>Art&iacute;culos, lotes y valores incluidos en el comprobante</p>
                        </div>
                    </div>
                    <div class="table-responsive electronic-invoice-table-wrap">
                        <table class="table electronic-invoice-detail-table mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>C&oacute;digo</th>
                                    <th>Descripci&oacute;n</th>
                                    <th>Lote</th>
                                    <th>F. venc.</th>
                                    <th class="text-right">Cantidad</th>
                                    <th class="text-right">Precio</th>
                                    <th class="text-right">IGV</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody id="vei_items_body"></tbody>
                        </table>
                    </div>
                </section>

                <div class="electronic-invoice-detail__bottom">
                    <section class="electronic-invoice-panel electronic-invoice-panel--collections" aria-labelledby="vei_collections_title">
                        <div class="electronic-invoice-section-heading">
                            <span class="electronic-invoice-section-heading__icon" aria-hidden="true"><i class="fas fa-hand-holding-usd"></i></span>
                            <div>
                                <h6 id="vei_collections_title">Historial de cobros</h6>
                                <p>Pagos confirmados asociados al comprobante</p>
                            </div>
                        </div>
                        <div class="table-responsive electronic-invoice-table-wrap">
                            <table class="table electronic-invoice-detail-table electronic-invoice-detail-table--collections mb-0">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Cuenta bancaria</th>
                                        <th>Operaci&oacute;n</th>
                                        <th class="text-right">Monto</th>
                                        <th>Registrado por</th>
                                        <th class="text-center">Constancia</th>
                                    </tr>
                                </thead>
                                <tbody id="vei_collections_body"></tbody>
                            </table>
                        </div>
                    </section>

                    <section class="electronic-invoice-tax-summary" aria-labelledby="vei_tax_title">
                        <div class="electronic-invoice-section-heading electronic-invoice-section-heading--compact">
                            <span class="electronic-invoice-section-heading__icon" aria-hidden="true"><i class="fas fa-calculator"></i></span>
                            <div>
                                <h6 id="vei_tax_title">Resumen tributario</h6>
                                <p>Desglose del comprobante</p>
                            </div>
                        </div>
                        <div class="electronic-invoice-tax-summary__lines">
                            <div><span>Gravada</span><strong id="vei_taxable_amount">0.00</strong></div>
                            <div><span>Exonerada</span><strong id="vei_exonerated_amount">0.00</strong></div>
                            <div><span>Inafecta</span><strong id="vei_unaffected_amount">0.00</strong></div>
                            <div><span>IGV</span><strong id="vei_igv_amount">0.00</strong></div>
                        </div>
                        <div class="electronic-invoice-tax-summary__total">
                            <span>Total del comprobante</span>
                            <strong id="vei_total_footer">0.00</strong>
                        </div>
                    </section>
                </div>
            </div>

            <div class="modal-footer electronic-invoice-detail__footer">
                <button type="button" class="btn btn-light border electronic-invoice-detail__close" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
