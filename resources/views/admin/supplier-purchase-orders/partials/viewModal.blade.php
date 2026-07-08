<div class="modal fade" id="viewSupplierPurchaseOrderModal" tabindex="-1" role="dialog"
    aria-labelledby="viewSupplierPurchaseOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow overflow-hidden supplier-order-view-content">
            <div class="modal-header border-0 supplier-order-view-header">
                <div class="d-flex align-items-center">
                    <div class="supplier-order-view-header-icon mr-3">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h5 class="modal-title text-white mb-0 font-weight-bold"
                            id="viewSupplierPurchaseOrderModalLabel">
                            Detalle de Orden de Compra a Proveedor
                        </h5>
                        <small class="text-white-50">Compra registrada para abastecimiento</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-3 supplier-order-view-body">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="card border-0 shadow-sm h-100 supplier-order-view-side-card">
                            <div class="card-body text-center">
                                <div class="supplier-order-view-icon mx-auto mb-3">
                                    <i class="fas fa-truck-loading"></i>
                                </div>

                                <small class="text-muted text-uppercase font-weight-bold">C&oacute;digo interno</small>
                                <h3 id="vspo_code" class="font-weight-bold mb-2 text-dark">-</h3>
                                <span id="vspo_status"
                                    class="badge badge-primary rounded-pill px-3 py-2">REGISTRADO</span>

                                <hr class="my-3">

                                <div class="text-left supplier-order-view-summary">
                                    <small>Proveedor</small>
                                    <strong id="vspo_supplier">-</strong>

                                    <small>Empresa compradora</small>
                                    <strong id="vspo_company">-</strong>

                                    <small>Total compra</small>
                                    <div class="supplier-order-view-total">
                                        <span id="vspo_currency_symbol">S/</span>
                                        <span id="vspo_grand_total">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm mb-3 supplier-order-view-card">
                            <div class="card-header bg-white border-0 pb-0">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-info-circle text-success mr-1"></i>
                                    Informaci&oacute;n de la orden
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row supplier-order-view-grid">
                                    <div class="col-md-4">
                                        <small>Cuenta bancaria</small>
                                        <strong id="vspo_supplier_account">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Moneda</small>
                                        <strong id="vspo_currency">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Afecto IGV</small>
                                        <strong id="vspo_affect_igv">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Condici&oacute;n de pago</small>
                                        <strong id="vspo_payment_condition">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Tipo de entrega</small>
                                        <strong id="vspo_delivery_type">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Tipo de transporte</small>
                                        <strong id="vspo_transport_type">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Tipo documento</small>
                                        <strong id="vspo_document_type">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Forma de pago</small>
                                        <strong id="vspo_payment_method">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Destino</small>
                                        <strong id="vspo_destination">-</strong>
                                    </div>
                                    <div class="col-md-7">
                                        <small>Direcci&oacute;n de env&iacute;o</small>
                                        <strong id="vspo_shipping_address">-</strong>
                                    </div>
                                    <div class="col-md-5">
                                        <small>Observaci&oacute;n</small>
                                        <strong id="vspo_observations">Sin observaciones</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3 supplier-order-view-card" id="vspo_shipping_agency_card">
                            <div class="card-header bg-white border-0 pb-0">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-shipping-fast text-success mr-1"></i>
                                    Datos de agencia de env&iacute;o
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row supplier-order-view-grid">
                                    <div class="col-md-4">
                                        <small>Agencia</small>
                                        <strong id="vspo_shipping_agency">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Sede / direccion</small>
                                        <strong id="vspo_shipping_branch">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Contacto</small>
                                        <strong id="vspo_shipping_contact">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Telefono / WhatsApp</small>
                                        <strong id="vspo_shipping_contact_phone">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Correo contacto</small>
                                        <strong id="vspo_shipping_contact_email">-</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small>Referencia</small>
                                        <strong id="vspo_shipping_reference">-</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3 supplier-order-view-card">
                            <div class="card-header bg-white border-0 pb-0">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-file-signature text-success mr-1"></i>
                                    Datos internos para PDF
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row supplier-order-view-grid">
                                    <div class="col-md-3">
                                        <small>Solicitado por</small>
                                        <strong id="vspo_requested_by">-</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small>Departamento</small>
                                        <strong id="vspo_request_department">-</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small>Autorizado por</small>
                                        <strong id="vspo_authorized_by_name">-</strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small>Cargo autorizado</small>
                                        <strong id="vspo_authorized_by_position">-</strong>
                                    </div>
                                    <div class="col-md-6">
                                        <small>Delivery</small>
                                        <strong id="vspo_delivery_text">-</strong>
                                    </div>
                                    <div class="col-md-6">
                                        <small>Terminos de pago</small>
                                        <strong id="vspo_payment_terms_text">-</strong>
                                    </div>
                                    <div class="col-md-6">
                                        <small>Instrucciones</small>
                                        <strong id="vspo_purchase_instructions">-</strong>
                                    </div>
                                    <div class="col-md-6">
                                        <small>Nota importante</small>
                                        <strong id="vspo_important_note">-</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mb-3 supplier-order-view-card">
                            <div class="card-header bg-white border-0 pb-0">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-clipboard-list text-success mr-1"></i>
                                    &Oacute;rdenes cliente relacionadas
                                </h6>
                            </div>
                            <div class="card-body pt-2">
                                <div id="vspo_customer_orders" class="supplier-order-related-orders">-</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm supplier-order-view-card">
                    <div class="card-header bg-white border-0">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fas fa-boxes text-success mr-1"></i>
                                Detalle de compra
                            </h6>
                            <small class="text-muted">Art&iacute;culos registrados para el proveedor</small>
                        </div>
                    </div>
                    <div class="table-responsive supplier-order-view-table-wrap">
                        <table class="table table-sm table-hover mb-0 supplier-order-view-table">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th>C&Oacute;DIGO</th>
                                    <th>ART&Iacute;CULO</th>
                                    <th>U.M.</th>
                                    <th>PRESENT.</th>
                                    <th>MARCA</th>
                                    <th>PROCED.</th>
                                    <th class="text-right">CANT. ORDENADA</th>
                                    <th class="text-right">CANT. INGRESADA</th>
                                    <th class="text-right">PENDIENTE</th>
                                    <th class="text-center">ESTADO</th>
                                    <th class="text-right">P. REF.</th>
                                    <th class="text-right">PRECIO</th>
                                    <th class="text-right">IGV</th>
                                    <th class="text-right">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody id="vspo_items_body"></tbody>
                        </table>
                    </div>
                </div>

                <div class="row justify-content-end mt-3">
                    <div class="col-md-6 col-lg-4">
                        <div class="supplier-order-view-totals shadow-sm">
                            <div>
                                <span>Subtotal</span>
                                <strong id="vspo_subtotal">0.00</strong>
                            </div>
                            <div>
                                <span>IGV</span>
                                <strong id="vspo_igv">0.00</strong>
                            </div>
                            <div class="grand">
                                <span>Total compra</span>
                                <strong id="vspo_total">0.00</strong>
                            </div>
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
    #viewSupplierPurchaseOrderModal .supplier-order-view-content {
        border-radius: 14px;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-header {
        background: linear-gradient(135deg, #198754, #116c43);
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-header-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: rgba(255, 255, 255, .16);
        font-size: 20px;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-body {
        background: #f7fbf8;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-card,
    #viewSupplierPurchaseOrderModal .supplier-order-view-side-card {
        border-radius: 14px;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-icon {
        width: 78px;
        height: 78px;
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #198754, #116c43);
        box-shadow: 0 8px 20px rgba(25, 135, 84, .18);
        font-size: 30px;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-summary small,
    #viewSupplierPurchaseOrderModal .supplier-order-view-grid small {
        display: block;
        color: #7b8794;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-summary strong,
    #viewSupplierPurchaseOrderModal .supplier-order-view-grid strong {
        display: block;
        color: #263445;
        font-size: 12px;
        margin-bottom: 12px;
        word-break: break-word;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-total {
        padding: 12px 10px;
        border-radius: 12px;
        color: #fff;
        background: linear-gradient(135deg, #198754, #116c43);
        box-shadow: 0 8px 18px rgba(25, 135, 84, .18);
        font-size: 21px;
        font-weight: 800;
        text-align: center;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-grid>div {
        margin-bottom: 12px;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-related-orders {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-related-badge {
        display: inline-flex;
        align-items: center;
        max-width: 100%;
        padding: 6px 10px;
        border: 1px solid #b8e2c5;
        border-radius: 999px;
        color: #14532d;
        background: #eaf8ef;
        font-size: 12px;
        font-weight: 700;
        word-break: break-word;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-table-wrap {
        border-top: 1px solid #e5f3e9;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-table thead th {
        padding: 9px 8px;
        border: 0;
        color: #374151;
        background: #eef8f1;
        font-size: 10px;
        font-weight: 800;
        white-space: nowrap;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-table tbody td {
        padding: 8px;
        border-top: 1px solid #edf5ef;
        color: #2d3748;
        font-size: 12px;
        vertical-align: middle;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-table tbody tr:hover td {
        background: #fbfefc;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-item-name {
        min-width: 210px;
        font-weight: 700;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-entry-status {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 104px;
        padding: 5px 8px;
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 800;
        white-space: nowrap;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-entry-status.status-pending {
        color: #1f5f9e;
        background: #e7f3ff;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-entry-status.status-partial {
        color: #916000;
        background: #fff3cd;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-entry-status.status-entered {
        color: #166534;
        background: #dcfce7;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-totals {
        overflow: hidden;
        border: 1px solid #d9eadf;
        border-radius: 14px;
        background: #fff;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-totals>div {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        padding: 8px 12px;
        border-bottom: 1px solid #edf5ef;
        font-size: 12px;
    }

    #viewSupplierPurchaseOrderModal .supplier-order-view-totals .grand {
        color: #14532d;
        background: #dcfce7;
        border-bottom: 0;
        font-size: 15px;
        font-weight: 800;
    }

    @media (max-width: 767px) {
        #viewSupplierPurchaseOrderModal .supplier-order-view-header-icon {
            display: none;
        }

        #viewSupplierPurchaseOrderModal .supplier-order-view-table tbody td {
            font-size: 11px;
        }
    }
</style>
