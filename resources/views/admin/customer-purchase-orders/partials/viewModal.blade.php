<div class="modal fade" id="viewCustomerPurchaseOrderModal" tabindex="-1" role="dialog"
    aria-labelledby="viewCustomerPurchaseOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow overflow-hidden purchase-order-view-content">
            <div class="modal-header border-0 purchase-order-view-header">
                <div>
                    <h5 class="modal-title text-white mb-0 font-weight-bold"
                        id="viewCustomerPurchaseOrderModalLabel">
                        <i class="fas fa-clipboard-check mr-2"></i>
                        Detalle de Orden de Compra
                    </h5>
                    <small class="text-white-50">Pedido adjudicado por el cliente</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-3 purchase-order-view-body">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body text-center">
                                <div class="purchase-order-view-icon mx-auto mb-3">
                                    <i class="fas fa-file-signature"></i>
                                </div>
                                <h5 id="vpo_code" class="font-weight-bold mb-1">—</h5>
                                <div id="vpo_purchase_order_number" class="text-muted small mb-2">—</div>
                                <span id="vpo_status" class="badge badge-secondary px-3 py-2">REGISTRADA</span>

                                <hr>

                                <div class="text-left">
                                    <small class="text-muted d-block">Cliente</small>
                                    <div id="vpo_customer" class="font-weight-bold mb-2">—</div>
                                    <small class="text-muted d-block">Sucursal / Tienda</small>
                                    <div id="vpo_branch" class="font-weight-bold mb-2">—</div>
                                    <small class="text-muted d-block">Total de venta</small>
                                    <div class="purchase-order-view-total">
                                        <span id="vpo_currency_symbol">S/</span>
                                        <span id="vpo_grand_total">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white border-0">
                                <h6 class="mb-0 font-weight-bold">
                                    <i class="fas fa-info-circle text-primary mr-1"></i>
                                    Información de la orden
                                </h6>
                            </div>
                            <div class="card-body pt-0">
                                <div class="row purchase-order-view-grid">
                                    <div class="col-md-4"><small>Empresa</small><strong id="vpo_company">—</strong></div>
                                    <div class="col-md-4"><small>Cotización</small><strong id="vpo_quote">—</strong></div>
                                    <div class="col-md-4"><small>Moneda</small><strong id="vpo_currency">—</strong></div>
                                    <div class="col-md-4"><small>Tipo de orden</small><strong id="vpo_order_type">—</strong></div>
                                    <div class="col-md-4"><small>Facturación</small><strong id="vpo_billing_type">—</strong></div>
                                    <div class="col-md-4"><small>Afecto IGV</small><strong id="vpo_affect_igv">—</strong></div>
                                    <div class="col-md-4"><small>Fecha notificación</small><strong id="vpo_notification_date">—</strong></div>
                                    <div class="col-md-4"><small>Entrega desde</small><strong id="vpo_delivery_start_date">—</strong></div>
                                    <div class="col-md-4"><small>Entrega hasta</small><strong id="vpo_delivery_end_date">—</strong></div>
                                    <div class="col-md-4"><small>Expediente SIAF</small><strong id="vpo_siaf">—</strong></div>
                                    <div class="col-md-4"><small>Cuadro adquisición</small><strong id="vpo_chart">—</strong></div>
                                    <div class="col-md-4"><small>Tipo de proceso</small><strong id="vpo_process">—</strong></div>
                                    <div class="col-12"><small>Observaciones</small><strong id="vpo_observations">—</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h6 class="mb-0 font-weight-bold">
                            <i class="fas fa-boxes text-primary mr-1"></i>
                            Ítems adjudicados
                        </h6>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>#</th>
                                    <th>ARTÍCULO</th>
                                    <th>U.M.</th>
                                    <th>PRESENTACIÓN</th>
                                    <th>MARCA</th>
                                    <th>CANT.</th>
                                    <th>P. UNIT.</th>
                                    <th>IGV</th>
                                    <th>TOTAL</th>
                                </tr>
                            </thead>
                            <tbody id="vpo_items_body"></tbody>
                        </table>
                    </div>
                </div>

                <div class="row justify-content-end mt-3">
                    <div class="col-md-5 col-lg-4">
                        <div class="purchase-order-view-totals">
                            <div><span>Venta exonerada</span><strong id="vpo_subtotal_exonerated">0.00</strong></div>
                            <div><span>Venta gravada</span><strong id="vpo_subtotal_taxed">0.00</strong></div>
                            <div><span>IGV</span><strong id="vpo_igv">0.00</strong></div>
                            <div class="grand"><span>Total</span><strong id="vpo_total">0.00</strong></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-light border px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #viewCustomerPurchaseOrderModal .purchase-order-view-content {
        border-radius: 14px;
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-header {
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-body {
        background: #f8fbff;
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-icon {
        width: 76px;
        height: 76px;
        border-radius: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        font-size: 28px;
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-total {
        padding: 10px;
        border-radius: 10px;
        color: #fff;
        background: #0d6efd;
        font-size: 19px;
        font-weight: 800;
        text-align: center;
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-grid>div {
        margin-bottom: 12px;
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-grid small {
        display: block;
        color: #7b8794;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-grid strong {
        display: block;
        color: #263445;
        font-size: 12px;
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-totals>div {
        display: flex;
        justify-content: space-between;
        padding: 6px 10px;
        border-bottom: 1px solid #edf1f5;
        font-size: 12px;
    }

    #viewCustomerPurchaseOrderModal .purchase-order-view-totals .grand {
        color: #0d6efd;
        background: #eaf3ff;
        font-size: 14px;
        font-weight: 800;
    }
</style>
