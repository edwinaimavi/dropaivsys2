<div class="modal fade" id="viewElectronicInvoiceModal" tabindex="-1" role="dialog"
    aria-labelledby="viewElectronicInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 electronic-invoice-view-header text-white">
                <div>
                    <h5 class="modal-title font-weight-bold mb-0" id="viewElectronicInvoiceModalLabel">
                        <i class="fas fa-file-invoice-dollar mr-2"></i>
                        Detalle de Comprobante
                    </h5>
                    <small class="text-white-50">Documento local preliminar, pendiente de integraci&oacute;n SUNAT</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body bg-light">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="electronic-invoice-card p-3 h-100 text-center">
                            <div class="mb-2 text-success" style="font-size:42px;">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </div>
                            <h5 id="vei_full_number" class="font-weight-bold mb-1">-</h5>
                            <div id="vei_document_type" class="text-muted mb-2">-</div>
                            <span id="vei_status" class="badge badge-secondary px-3 py-2">Borrador</span>
                            <hr>
                            <small class="text-muted d-block">Cliente</small>
                            <div id="vei_client_name" class="font-weight-bold mb-2">-</div>
                            <small class="text-muted d-block">Total</small>
                            <div id="vei_total_amount" class="font-weight-bold text-success" style="font-size:22px;">0.00</div>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="electronic-invoice-card p-3 mb-3">
                            <div class="electronic-invoice-card-title mb-2">Informaci&oacute;n general</div>
                            <div class="row electronic-invoice-view-grid">
                                <div class="col-md-3"><small>Empresa</small><strong id="vei_company">-</strong></div>
                                <div class="col-md-3"><small>RUC empresa</small><strong id="vei_company_ruc">-</strong></div>
                                <div class="col-md-3"><small>Documento cliente</small><strong id="vei_client_document">-</strong></div>
                                <div class="col-md-3"><small>Fecha emisi&oacute;n</small><strong id="vei_issue_date">-</strong></div>
                                <div class="col-md-3"><small>Moneda</small><strong id="vei_currency">-</strong></div>
                                <div class="col-md-3"><small>Forma pago</small><strong id="vei_payment_type">-</strong></div>
                                <div class="col-md-3"><small>OC cliente</small><strong id="vei_purchase_order">-</strong></div>
                                <div class="col-md-3"><small>Estado SUNAT</small><strong id="vei_sunat_status">Pendiente</strong></div>
                                <div class="col-12"><small>Observaci&oacute;n</small><strong id="vei_observations">-</strong></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="electronic-invoice-card p-3">
                    <div class="electronic-invoice-card-title mb-2">Detalle de productos</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th>
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
                </div>

                <div class="row justify-content-end mt-3">
                    <div class="col-md-5 col-lg-4">
                        <div class="electronic-invoice-card p-3">
                            <div class="d-flex justify-content-between"><span>Gravada</span><strong id="vei_taxable_amount">0.00</strong></div>
                            <div class="d-flex justify-content-between"><span>Exonerada</span><strong id="vei_exonerated_amount">0.00</strong></div>
                            <div class="d-flex justify-content-between"><span>Inafecta</span><strong id="vei_unaffected_amount">0.00</strong></div>
                            <div class="d-flex justify-content-between"><span>IGV</span><strong id="vei_igv_amount">0.00</strong></div>
                            <hr>
                            <div class="d-flex justify-content-between text-success"><span>Total</span><strong id="vei_total_footer">0.00</strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-light border" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
