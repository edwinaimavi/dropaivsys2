<div class="modal fade" id="supplierPurchaseOrderModal" tabindex="-1" role="dialog"
    aria-labelledby="supplierPurchaseOrderModalLabel" aria-hidden="true" data-backdrop="static"
    data-keyboard="false">

    <div class="modal-dialog modal-xl modal-dialog-centered supplier-order-modal-dialog" role="document">
        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header align-items-center supplier-order-modal-header">
                <div class="d-flex align-items-center">
                    <div class="supplier-order-header-icon mr-3">
                        <i class="fas fa-file-invoice text-success"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 font-weight-bold" id="supplierPurchaseOrderModalLabel">
                            Registrar Orden de Compra a Proveedor
                        </h5>
                        <small class="text-muted">
                            Registro de productos o servicios comprados al proveedor
                        </small>
                    </div>
                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-2">
                <form id="supplierPurchaseOrderForm" autocomplete="off" class="row" novalidate>
                    @csrf

                    <input type="hidden" id="supplier_purchase_order_id" name="supplier_purchase_order_id">
                    <input type="hidden" id="supplier_order_type" name="order_type" value="articles">

                    <div class="col-12">
                        <div id="supplierPurchaseOrderErrors" class="alert alert-danger d-none mb-2"></div>
                    </div>

                    <div class="col-lg-3 mb-2">
                        <div class="card border-0 shadow-sm h-100 supplier-order-side-card">
                            <div class="card-body text-center py-3 px-3">
                                <div class="supplier-order-side-icon mx-auto mb-3">
                                    <i class="fas fa-truck-loading"></i>
                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">Orden a Proveedor</h5>
                                <small class="text-muted">Compra para abastecimiento</small>

                                <hr class="my-2">

                                <div class="text-left small">
                                    <small class="text-muted d-block">N&deg; interno</small>
                                    <input type="text" id="supplier_order_code" name="code"
                                        class="form-control form-control-sm mb-2 text-center font-weight-bold"
                                        placeholder="Autom&aacute;tico" readonly>

                                    <small class="text-muted d-block">Fecha de registro</small>
                                    <div class="font-weight-600 mb-2" id="supplierOrderSideDate">{{ now()->format('d/m/Y') }}</div>

                                    <small class="text-muted d-block">Estado inicial</small>
                                    <span class="badge badge-primary px-2 py-1 mb-2" id="supplierOrderSideStatus">Registrado</span>

                                    <small class="text-muted d-block">Proveedor</small>
                                    <div class="font-weight-600 mb-2 text-break" id="supplierOrderSideSupplier">
                                        Seleccione proveedor
                                    </div>

                                    <small class="text-muted d-block">Total de compra</small>
                                    <div class="supplier-order-side-total mt-1">
                                        <span class="supplier-order-currency-symbol">S/</span>
                                        <span id="supplierOrderSideGrandTotal">0.00</span>
                                    </div>

                                    <div class="supplier-order-side-finance mt-3">
                                        <div><span>Moneda</span><strong id="supplierOrderSideCurrency">PEN</strong></div>
                                        <div><span>Anticipo</span><strong id="supplierOrderSideAdvance">No aplica</strong></div>
                                        <div><span>Estado financiero</span><strong id="supplierOrderSideFinancialStatus">Sin anticipo</strong></div>
                                    </div>
                                </div>

                                <div class="alert border-0 shadow-sm mt-3 mb-0 text-left supplier-order-info-alert">
                                    <i class="fas fa-info-circle text-success mr-1"></i>
                                    <span class="small">
                                        Carga un origen o inserta art&iacute;culos para registrar la compra.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9 mb-2 supplier-order-tabs-column">
                        <div class="supplier-order-tabs-shell d-none" id="supplierOrderTabsShell">
                            <nav class="supplier-order-form-tabs" aria-label="Secciones de la orden a proveedor">
                                <div class="nav nav-pills flex-nowrap" role="tablist">
                                    <a class="nav-link active" data-toggle="pill" href="#supplier_order_tab_data" role="tab" data-section="data"><i class="fas fa-clipboard-list"></i><span>Datos de la orden</span><b class="supplier-order-tab-error d-none">!</b></a>
                                    <a class="nav-link" data-toggle="pill" href="#supplier_order_tab_finance" role="tab" data-section="finance"><i class="fas fa-coins"></i><span>Condiciones financieras</span><b class="supplier-order-tab-error d-none">!</b></a>
                                    <a class="nav-link" data-toggle="pill" href="#supplier_order_tab_logistics" role="tab" data-section="logistics"><i class="fas fa-shipping-fast"></i><span>Env&iacute;o / log&iacute;stica</span><b class="supplier-order-tab-error d-none">!</b></a>
                                    <a class="nav-link" data-toggle="pill" href="#supplier_order_tab_documents" role="tab" data-section="documents"><i class="fas fa-folder-open"></i><span>Documentaci&oacute;n</span><b class="supplier-order-tab-error d-none">!</b></a>
                                    <a class="nav-link" data-toggle="pill" href="#supplier_order_tab_items" role="tab" data-section="items"><i class="fas fa-boxes"></i><span>Art&iacute;culos y servicios</span><b class="supplier-order-tab-error d-none">!</b></a>
                                    <a class="nav-link" data-toggle="pill" href="#supplier_order_tab_pdf" role="tab" data-section="pdf"><i class="fas fa-file-pdf"></i><span>Datos para PDF</span><b class="supplier-order-tab-error d-none">!</b></a>
                                    <a class="nav-link" data-toggle="pill" href="#supplier_order_tab_summary" role="tab" data-section="summary"><i class="fas fa-chart-pie"></i><span>Resumen</span></a>
                                </div>
                            </nav>
                            <div class="tab-content supplier-order-form-tab-content">
                                <div class="tab-pane fade show active" id="supplier_order_tab_data" role="tabpanel"><div class="card supplier-order-tab-card"><div class="card-header"><h6><i class="fas fa-clipboard-list"></i> Datos esenciales de la compra</h6><small>Empresa, proveedor y condiciones principales de la orden.</small></div><div class="card-body"><div class="form-row supplier-order-data-grid"></div></div></div></div>
                                <div class="tab-pane fade" id="supplier_order_tab_finance" role="tabpanel"><div class="supplier-order-finance-container"></div></div>
                                <div class="tab-pane fade" id="supplier_order_tab_logistics" role="tabpanel"><div class="card supplier-order-tab-card"><div class="card-header"><h6><i class="fas fa-shipping-fast"></i> Env&iacute;o y log&iacute;stica</h6><small>Entrega, destino y datos de agencia cuando corresponda.</small></div><div class="card-body"><div class="form-row supplier-order-logistics-grid"></div><div class="supplier-order-agency-container"></div></div></div></div>
                                <div class="tab-pane fade" id="supplier_order_tab_documents" role="tabpanel"><div class="supplier-order-documents-container"></div></div>
                                <div class="tab-pane fade" id="supplier_order_tab_items" role="tabpanel"><div class="supplier-order-items-container"></div></div>
                                <div class="tab-pane fade" id="supplier_order_tab_pdf" role="tabpanel"><div class="supplier-order-pdf-container"></div></div>
                                <div class="tab-pane fade" id="supplier_order_tab_summary" role="tabpanel"><div id="supplierOrderFormSummary"></div></div>
                            </div>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-dark">
                                            <i class="fas fa-clipboard-list text-success mr-1"></i>
                                            Datos principales de la orden
                                        </h6>
                                        <small class="text-muted">
                                            Proveedor, origen de compra y condiciones de entrega
                                        </small>
                                    </div>

                                </div>
                            </div>

                            <div class="card-body py-3">
                                <div class="form-row">
                                    <div class="form-group col-md-4 supplier-order-customer-orders-field">
                                        <label>EMPRESA <span class="text-danger">*</span></label>
                                        <select id="supplier_order_company_id" name="company_id"
                                            class="form-control form-control-sm js-supplier-order-select" required>
                                            <option value="">Seleccione empresa</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company->id }}"
                                                    data-ruc="{{ $company->ruc }}"
                                                    data-business-name="{{ $company->business_name }}"
                                                    data-trade-name="{{ $company->trade_name }}"
                                                    data-email="{{ $company->email }}">
                                                    {{ $company->trade_name ?? $company->business_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>ORDEN CLIENTE / PEDIDO RELACIONADO <span class="text-danger">*</span></label>
                                        <select id="supplier_order_customer_purchase_order_ids"
                                            name="customer_purchase_order_ids[]"
                                            class="form-control form-control-sm js-supplier-order-select" multiple
                                            required data-placeholder="Seleccione uno o varios pedidos">
                                            @foreach ($customerPurchaseOrders as $customerOrder)
                                                @php
                                                    $customerOrderNumber = $customerOrder->purchase_order_number
                                                        ?: $customerOrder->code;
                                                    $customerName = $customerOrder->customer?->business_name
                                                        ?? $customerOrder->customer?->full_name
                                                        ?? trim(
                                                            ($customerOrder->customer?->first_name ?? '') .
                                                                ' ' .
                                                                ($customerOrder->customer?->last_name ?? ''),
                                                        )
                                                        ?: 'Sin cliente';
                                                @endphp
                                                <option value="{{ $customerOrder->id }}">
                                                    {{ $customerOrderNumber }} | {{ $customerName }} | {{ trim(($customerOrder->currency?->symbol ?? '') . ' ' . number_format((float) $customerOrder->grand_total, 2)) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>MONEDA <span class="text-danger">*</span></label>
                                        <select id="supplier_order_currency_id" name="currency_id"
                                            class="form-control form-control-sm js-supplier-order-select" required>
                                            <option value="">Seleccione moneda</option>
                                            @foreach ($currencies as $currency)
                                                <option value="{{ $currency->id }}" data-code="{{ $currency->code }}"
                                                    data-symbol="{{ $currency->symbol }}" @selected($currency->code === 'PEN')>
                                                    {{ $currency->code }} | {{ $currency->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>PROVEEDOR <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm supplier-order-supplier-picker">
                                        <select id="supplier_order_supplier_id" name="supplier_id"
                                            class="form-control form-control-sm js-supplier-order-select" required>
                                            <option value="">Seleccione proveedor</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}"
                                                    data-payment-condition="{{ $supplier->payment_condition }}">
                                                    {{ $supplier->ruc ? $supplier->ruc . ' | ' : '' }}{{ $supplier->short_name ?? $supplier->business_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append">
                                            <button type="button" id="btnQuickSupplierForOrder"
                                                class="btn btn-outline-success" data-toggle="tooltip"
                                                title="Registrar nuevo proveedor"><i class="fas fa-plus"></i></button>
                                        </div>
                                        </div>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label>CUENTA DE BANCO <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm supplier-order-account-picker">
                                        <select id="supplier_order_supplier_account_id" name="supplier_account_id"
                                            class="form-control form-control-sm js-supplier-order-select" required>
                                            <option value="">Seleccione cuenta</option>
                                            @foreach ($supplierAccounts as $account)
                                                <option value="{{ $account->id }}"
                                                    data-supplier-id="{{ $account->supplier_id }}"
                                                    data-bank="{{ $account->bank?->short_name ?? $account->bank?->description ?? 'Banco' }}">
                                                    {{ $account->bank?->description ?? 'Banco' }} |
                                                    {{ $account->account_number }} |
                                                    {{ $account->currency?->code }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="input-group-append">
                                            <button type="button" id="btnQuickSupplierAccountForOrder"
                                                class="btn btn-outline-success" data-toggle="tooltip"
                                                title="Agregar cuenta bancaria"><i class="fas fa-plus"></i></button>
                                        </div>
                                        </div>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label>TIPO TRANSPORTE</label>
                                        <select id="supplier_order_transport_type" name="transport_type"
                                            class="form-control form-control-sm js-supplier-order-select">
                                            <option value="">Seleccione</option>
                                            <option value="terrestre">Terrestre</option>
                                            <option value="aereo">A&eacute;reo</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>CONDICI&Oacute;N DE PAGO</label>
                                        <select id="supplier_order_payment_condition" name="payment_condition"
                                            class="form-control form-control-sm js-supplier-order-select">
                                            <option value="">Seleccione</option>
                                            <option value="contado">Contado</option>
                                            <option value="credito_20_dias">Cr&eacute;dito 20 d&iacute;as</option>
                                            <option value="credito_30_dias">Cr&eacute;dito 30 d&iacute;as</option>
                                            <option value="credito_45_dias">Cr&eacute;dito 45 d&iacute;as</option>
                                            <option value="credito_60_dias">Cr&eacute;dito 60 d&iacute;as</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>TIPO ENTREGA <span class="text-danger">*</span></label>
                                        <select id="supplier_order_delivery_type" name="delivery_type"
                                            class="form-control form-control-sm js-supplier-order-select" required>
                                            <option value="">Seleccione</option>
                                            <option value="agencia">Agencia</option>
                                            <option value="recojo_almacen">Recojo de almac&eacute;n</option>
                                            <option value="transportista_proveedor">Transportista del proveedor</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>AFECTO IGV</label>
                                        <select id="supplier_order_affect_igv" name="affect_igv"
                                            class="form-control form-control-sm js-supplier-order-select">
                                            <option value="1" selected>SI</option>
                                            <option value="0">NO</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="col-12 d-none" id="supplierOrderShippingAgencySection">
                                        <div class="card border-0 shadow-sm mb-2" style="background:#f4fff8;border-left:4px solid #198754 !important;">
                                            <div class="card-body py-2">
                                                <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                                    <h6 class="mb-0 font-weight-bold text-dark">
                                                        <i class="fas fa-shipping-fast text-success mr-1"></i>
                                                        Datos de agencia de env&iacute;o
                                                    </h6>
                                                    <small class="text-muted">Visible cuando el tipo de entrega es agencia</small>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group col-md-4">
                                                        <label>AGENCIA DE ENV&Iacute;O <span class="text-danger">*</span></label>
                                                        <select id="supplier_order_shipping_agency_id" name="shipping_agency_id"
                                                            class="form-control form-control-sm js-supplier-order-select">
                                                            <option value="">Seleccione agencia</option>
                                                            @foreach ($shippingAgencies as $shippingAgency)
                                                                <option value="{{ $shippingAgency->id }}">
                                                                    {{ $shippingAgency->ruc ? $shippingAgency->ruc . ' | ' : '' }}{{ $shippingAgency->trade_name ?? $shippingAgency->business_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        <span class="invalid-feedback"></span>
                                                    </div>
                                                    <div class="form-group col-md-4">
                                                        <label>SEDE / DIRECCI&Oacute;N <span class="text-danger">*</span></label>
                                                        <select id="supplier_order_shipping_agency_branch_id" name="shipping_agency_branch_id"
                                                            class="form-control form-control-sm js-supplier-order-select">
                                                            <option value="">Seleccione agencia primero</option>
                                                        </select>
                                                        <span class="invalid-feedback"></span>
                                                    </div>
                                                    <div class="form-group col-md-4">
                                                        <label>CONTACTO</label>
                                                        <select id="supplier_order_shipping_agency_contact_id" name="shipping_agency_contact_id"
                                                            class="form-control form-control-sm js-supplier-order-select">
                                                            <option value="">Seleccione sede primero</option>
                                                        </select>
                                                        <span class="invalid-feedback"></span>
                                                    </div>
                                                </div>
                                                <div class="form-row">
                                                    <div class="form-group col-md-4">
                                                        <label>DIRECCI&Oacute;N / UBICACI&Oacute;N DE AGENCIA</label>
                                                        <input type="text" id="supplier_order_shipping_agency_address"
                                                            class="form-control form-control-sm" readonly>
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <label>TEL&Eacute;FONO / WHATSAPP CONTACTO</label>
                                                        <input type="text" id="supplier_order_shipping_contact_phone"
                                                            class="form-control form-control-sm" readonly>
                                                    </div>
                                                    <div class="form-group col-md-2">
                                                        <label>CORREO CONTACTO</label>
                                                        <input type="text" id="supplier_order_shipping_contact_email"
                                                            class="form-control form-control-sm" readonly>
                                                    </div>
                                                    <div class="form-group col-md-3">
                                                        <label>REFERENCIA ENV&Iacute;O</label>
                                                        <input type="text" id="supplier_order_shipping_reference"
                                                            name="shipping_reference" class="form-control form-control-sm text-uppercase">
                                                        <span class="invalid-feedback"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>FORMA DE PAGO</label>
                                        <select id="supplier_order_payment_method" name="payment_method"
                                            class="form-control form-control-sm js-supplier-order-select">
                                            <option value="">Seleccione</option>
                                            <option value="efectivo">Efectivo</option>
                                            <option value="tarjeta">Tarjeta</option>
                                            <option value="deposito_cuenta">Dep&oacute;sito en cuenta</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>TIPO DOCUMENTO</label>
                                        <select id="supplier_order_document_type" name="document_type"
                                            class="form-control form-control-sm js-supplier-order-select">
                                            <option value="">Seleccione</option>
                                            <option value="factura">Factura</option>
                                            <option value="boleta">Boleta</option>
                                            <option value="nota_pedido">Nota de pedido</option>
                                            <option value="guia_remision">Gu&iacute;a de remisi&oacute;n</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label>DIRECCI&Oacute;N DE ENV&Iacute;O</label>
                                        <input type="text" id="supplier_order_shipping_address"
                                            name="shipping_address" class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm mb-3 supplier-order-financial-card">
                                    <div class="card-header border-0 d-flex align-items-center justify-content-between">
                                        <div><h6 class="mb-0"><i class="fas fa-coins mr-1"></i> Condiciones financieras</h6><small>Control del anticipo en moneda de compra y pagos con tipo de cambio individual</small></div>
                                        <span id="supplierOrderAdvanceStatusBadge" class="badge badge-light">Sin anticipo</span>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="font-weight-bold text-uppercase mb-2">1. Resumen de la compra</h6>
                                        <div class="form-row align-items-end">
                                            <div class="form-group col-md-3">
                                                <label>MONEDA DE LA COMPRA</label>
                                                <input id="supplier_order_purchase_currency_label" class="form-control form-control-sm" readonly value="PEN | SOL">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>MONEDA DE PAGO REFERENCIAL <span class="text-danger">*</span></label>
                                                <select id="supplier_order_payment_currency_id" name="payment_currency_id" class="form-control form-control-sm js-supplier-order-select" required>
                                                    <option value="">Seleccione moneda</option>
                                                    @foreach ($currencies as $currency)
                                                        <option value="{{ $currency->id }}" data-code="{{ $currency->code }}" data-symbol="{{ $currency->symbol }}">
                                                            {{ $currency->code }} | {{ $currency->description }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-3" id="supplierOrderExchangeRateGroup">
                                                <label>TIPO DE CAMBIO REFERENCIAL</label>
                                                <input type="number" step="0.000001" min="0.000001" id="supplier_order_exchange_rate" name="exchange_rate" class="form-control form-control-sm text-right" placeholder="3.7500">
                                                <small class="text-muted">Solo sirve para precargar un pago nuevo.</small>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                        </div>

                                        <div class="form-row align-items-end">
                                            <div class="form-group col-md-3">
                                                <div class="custom-control custom-switch mb-2">
                                                    <input type="hidden" name="apply_advance" value="0">
                                                    <input type="checkbox" class="custom-control-input" id="supplier_order_apply_advance" name="apply_advance" value="1">
                                                    <label class="custom-control-label" for="supplier_order_apply_advance">Aplicar anticipo</label>
                                                </div>
                                            </div>
                                            <div class="form-group col-md-3 supplier-order-advance-field">
                                                <label>TIPO DE ANTICIPO</label>
                                                <select id="supplier_order_advance_type" name="advance_type" class="form-control form-control-sm">
                                                    <option value="">Seleccione</option>
                                                    <option value="fixed_amount">Monto fijo</option>
                                                    <option value="percentage">Porcentaje</option>
                                                </select>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-3 supplier-order-advance-field" id="supplierOrderAdvancePercentageGroup">
                                                <label>PORCENTAJE</label>
                                                <div class="input-group input-group-sm"><input type="number" step="0.0001" min="0.0001" max="100" id="supplier_order_advance_percentage" name="advance_percentage" class="form-control text-right"><div class="input-group-append"><span class="input-group-text">%</span></div></div>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-3 supplier-order-advance-field" id="supplierOrderAdvanceAmountGroup">
                                                <label>MONTO FIJO</label>
                                                <input type="number" step="0.01" min="0.01" id="supplier_order_advance_amount" name="advance_amount" class="form-control form-control-sm text-right">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                        </div>
                                        <div class="supplier-order-advance-summary mb-3">
                                            <div><small>Total compra</small><strong id="supplierOrderFinancialPurchaseTotal">PEN 0.00</strong></div>
                                            <div><small>Anticipo requerido</small><strong id="supplierOrderAdvanceRequired">0.00</strong></div>
                                            <div><small>Pagado aplicado</small><strong id="supplierOrderAdvancePaid">0.00</strong></div>
                                            <div><small>Saldo anticipo</small><strong id="supplierOrderAdvanceBalance">0.00</strong></div>
                                            <div><small>Saldo de compra</small><strong id="supplierOrderPurchaseBalance">0.00</strong></div>
                                            <div class="is-pen"><small>Total pagado en PEN</small><strong id="supplierOrderPaidPenTotal">S/ 0.00</strong></div>
                                        </div>

                                        <div id="supplierOrderAdvancePaymentsSection" class="supplier-order-advance-field">
                                            <div class="d-flex justify-content-between align-items-center mb-2"><div><strong class="d-block">2. Pagos registrados</strong><small class="text-muted">El monto aplicado está expresado en moneda de compra; el monto pagado corresponde a la salida bancaria.</small></div></div>
                                            <div id="supplierOrderExistingAdvancePayments" class="mb-2"></div>
                                            <div class="supplier-order-new-advance-payment">
                                                <small class="d-block font-weight-bold mb-2 text-uppercase">3. Registrar nuevo pago al guardar</small>
                                                <div class="form-row">
                                                    <input type="hidden" name="advance_payments[0][purchase_currency_id]" id="supplier_order_new_advance_purchase_currency_id">
                                                    <div class="form-group col-md-3"><label>MONEDA DEL PAGO</label><select name="advance_payments[0][payment_currency_id]" id="supplier_order_new_advance_payment_currency_id" class="form-control form-control-sm"><option value="">Seleccione moneda</option>@foreach ($currencies as $currency)<option value="{{ $currency->id }}" data-code="{{ $currency->code }}" data-symbol="{{ $currency->symbol }}">{{ $currency->code }} | {{ $currency->description }}</option>@endforeach</select><span class="invalid-feedback"></span></div>
                                                    <div class="form-group col-md-5"><label>CUENTA BANCARIA ORIGEN</label><select name="advance_payments[0][company_bank_account_id]" id="supplier_order_new_advance_bank_account_id" class="form-control form-control-sm" disabled><option value="">Seleccione empresa y moneda de pago</option></select><span class="invalid-feedback"></span></div>
                                                    <div class="form-group col-md-2"><label>MONTO APLICADO</label><input type="number" step="0.01" min="0.01" name="advance_payments[0][applied_amount]" id="supplier_order_new_advance_applied_amount" class="form-control form-control-sm text-right"><small id="supplierOrderAppliedAmountHelp" class="text-muted"></small><span class="invalid-feedback"></span></div>
                                                    <div class="form-group col-md-2" id="supplierOrderPaymentExchangeRateGroup"><label>TC DEL PAGO</label><input type="number" step="0.000001" min="0.000001" name="advance_payments[0][exchange_rate]" id="supplier_order_new_advance_exchange_rate" class="form-control form-control-sm text-right"><span class="invalid-feedback"></span></div>
                                                    <div class="form-group col-md-3"><label>MONTO PAGADO</label><input type="number" step="0.01" min="0.01" name="advance_payments[0][amount]" id="supplier_order_new_advance_amount" class="form-control form-control-sm text-right" readonly><small id="supplierOrderPaidAmountHelp" class="text-muted"></small><span class="invalid-feedback"></span></div>
                                                    <div class="form-group col-md-2"><label>FECHA</label><input type="date" name="advance_payments[0][payment_date]" id="supplier_order_new_advance_date" class="form-control form-control-sm"><span class="invalid-feedback"></span></div>
                                                    <div class="form-group col-md-2"><label>MEDIO DE PAGO</label><select name="advance_payments[0][payment_method]" id="supplier_order_new_advance_method" class="form-control form-control-sm"><option value="">Seleccione</option><option value="efectivo">Efectivo</option><option value="tarjeta">Tarjeta</option><option value="deposito_cuenta">Dep&oacute;sito en cuenta</option></select><span class="invalid-feedback"></span></div>
                                                    <div class="form-group col-md-2"><label>NRO. OPERACIÓN</label><input type="text" name="advance_payments[0][operation_number]" class="form-control form-control-sm text-uppercase" maxlength="100"></div>
                                                    <div class="form-group col-md-4"><label>CONSTANCIA</label><div class="custom-file custom-file-sm"><input type="file" name="advance_payments[0][proof]" id="supplier_order_new_advance_proof" class="custom-file-input" accept=".pdf,.jpg,.jpeg,.png,.webp"><label class="custom-file-label" for="supplier_order_new_advance_proof">Seleccionar archivo</label></div><small class="text-muted">PDF, JPG, JPEG, PNG o WEBP. M&aacute;x. 10 MB.</small><span class="invalid-feedback"></span></div>
                                                    <div class="form-group col-12"><label>OBSERVACI&Oacute;N</label><input type="text" name="advance_payments[0][observation]" class="form-control form-control-sm text-uppercase" maxlength="1000"><small id="supplierOrderAdvanceAccountHelp" class="text-muted">La cuenta origen debe pertenecer a la empresa y usar la moneda de pago seleccionada.</small></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>UBIGEO DESTINO</label>
                                        <select id="supplier_order_destination_ubigeo_id" name="destination_ubigeo_id"
                                            class="form-control form-control-sm js-supplier-order-select">
                                            <option value="">Seleccione ubigeo</option>
                                            @foreach ($ubigeos as $ubigeo)
                                                <option value="{{ $ubigeo->id }}"
                                                    data-department="{{ $ubigeo->department }}"
                                                    data-province="{{ $ubigeo->province }}"
                                                    data-district="{{ $ubigeo->district }}">
                                                    {{ $ubigeo->department }} / {{ $ubigeo->province }} /
                                                    {{ $ubigeo->district }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>DESTINO OPCIONAL</label>
                                        <input type="text" id="supplier_order_destination_text"
                                            name="destination_text" class="form-control form-control-sm text-uppercase"
                                            maxlength="255">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>OBSERVACI&Oacute;N</label>
                                        <textarea id="supplier_order_observations" name="observations"
                                            class="form-control form-control-sm text-uppercase" rows="2"></textarea>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>

                                <div class="card border-0 shadow-sm mb-2" style="background:#fff;border-left:4px solid #6c757d !important;">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
                                            <h6 class="mb-0 font-weight-bold text-dark">
                                                <i class="fas fa-file-signature text-secondary mr-1"></i>
                                                Datos internos para PDF
                                            </h6>
                                            <small class="text-muted">Informaci&oacute;n complementaria para el PDF</small>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-3">
                                                <label>DEPARTAMENTO</label>
                                                <input type="text" id="supplier_order_request_department" name="request_department"
                                                    class="form-control form-control-sm text-uppercase" value="COMPRAS">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>AUTORIZADO POR</label>
                                                <input type="text" id="supplier_order_authorized_by_name" name="authorized_by_name"
                                                    class="form-control form-control-sm text-uppercase" value="IVAN CUBAS BINCES">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>CARGO AUTORIZADO</label>
                                                <input type="text" id="supplier_order_authorized_by_position" name="authorized_by_position"
                                                    class="form-control form-control-sm text-uppercase" value="GERENTE GENERAL">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-12">
                                                <label>DELIVERY</label>
                                                <input type="text" id="supplier_order_delivery_text" name="delivery_text"
                                                    class="form-control form-control-sm text-uppercase"
                                                    value="EN AGENCIA DE TRANSPORTES - ENVIO A PROVINCIA">
                                                <span class="invalid-feedback"></span>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group col-md-6">
                                                <label>INSTRUCCIONES</label>
                                                <textarea id="supplier_order_purchase_instructions" name="purchase_instructions"
                                                    class="form-control form-control-sm supplier-order-informative-textarea" rows="4"></textarea>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                            <div class="form-group col-md-6">
                                                <label>NOTA IMPORTANTE</label>
                                                <textarea id="supplier_order_important_note" name="important_note"
                                                    class="form-control form-control-sm text-uppercase supplier-order-informative-textarea" rows="4">ADJUNTAR JUNTAMENTE CON LA FACTURA Y GUIA DE REMISION AL CORREO: LOGISTICA@DROPAIV.COM, LOS DOCUMENTOS LEGALES NECESARIOS TALES COMO:
1. BPM O ISO DEL BIEN ADQUIRIDO O SU EQUIVALENTE - VIGENTE
2. CERTIFICADO O PROTOCOLO DE ANALISIS DEL BIEN ADQUIRIDO - VIGENTE
3. REGISTRO SANITARIO DEL BIEN ADQUIRIDO - VIGENTE</textarea>
                                                <span class="invalid-feedback"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card supplier-order-documents-card supplier-doc-section">
                            <div class="card-header supplier-doc-section-header d-flex justify-content-between align-items-center flex-wrap">
                                <div>
                                    <h6 class="mb-0 font-weight-bold text-dark">
                                        <span class="supplier-doc-section-icon">
                                            <i class="fas fa-folder-open"></i>
                                        </span>
                                        Documentaci&oacute;n del proveedor
                                    </h6>
                                    <small class="text-muted">Adjunte cotizaciones, sustento de pago u otros documentos enviados por el proveedor.</small>
                                </div>
                                <button type="button" class="btn btn-sm supplier-doc-add" id="btnAddSupplierOrderDocument">
                                    <i class="fas fa-plus mr-1"></i> Agregar documento
                                </button>
                            </div>
                            <div class="card-body supplier-doc-section-body">
                                <div id="supplierOrderExistingDocuments" class="supplier-doc-list"></div>
                                <div id="supplierOrderDocumentsContainer" class="supplier-doc-list"></div>
                                <small class="supplier-doc-section-help d-block">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Formatos permitidos: PDF, JPG, JPEG y PNG. Tama&ntilde;o m&aacute;ximo: 10 MB por archivo.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm supplier-order-items-full">
                            <div class="card-header bg-white border-0 py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-dark">
                                            <i class="fas fa-boxes text-success mr-1"></i>
                                            Art&iacute;culos / servicios a comprar
                                        </h6>
                                        <small class="text-muted">
                                            Detalle de compra al proveedor
                                        </small>
                                    </div>

                                    <div class="mt-2 mt-md-0">
                                        <button type="button" id="btnLoadSupplierOrderSource"
                                            class="btn btn-outline-success btn-sm mr-2">
                                            <i class="fas fa-download mr-1"></i>
                                            Cargar origen
                                        </button>
                                        <button type="button" id="btnAddSupplierOrderItem"
                                            class="btn btn-success btn-sm">
                                            <i class="fas fa-plus mr-1"></i>
                                            Insertar art&iacute;culo
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="supplier-order-table-scroll">
                                <table id="supplierOrderItemsTable" class="table table-sm table-hover mb-0">
                                    <thead class="bg-light text-center">
                                        <tr>
                                            <th>#</th>
                                            <th>ART&Iacute;CULO</th>
                                            <th>NOTA</th>
                                            <th>U.M.</th>
                                            <th>PRESENT.</th>
                                            <th>MARCA</th>
                                            <th>PROCEDENCIA</th>
                                            <th>F. VENC.</th>
                                            <th>C. COSTEO</th>
                                            <th>P. REF. COMPRA</th>
                                            <th>CANTIDAD</th>
                                            <th>PRECIO</th>
                                            <th>P. TOTAL IGV</th>
                                            <th>B. IMPONIBLE</th>
                                            <th>% IGV</th>
                                            <th>IGV</th>
                                            <th>ACCI&Oacute;N</th>
                                        </tr>
                                    </thead>
                                    <tbody id="supplierOrderItemsTbody">
                                        <tr id="supplierOrderItemsEmptyRow">
                                            <td colspan="17" class="text-center text-muted py-4">
                                                <i class="fas fa-box-open d-block mb-2"></i>
                                                Carga un origen o inserta art&iacute;culos para registrar la compra.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="supplier-order-totals-area">
                                <div class="row justify-content-end">
                                    <div class="col-lg-5 col-xl-4">
                                        <div class="supplier-order-total-line">
                                            <span>Base imponible</span>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text supplier-order-currency-code">PEN</span>
                                                </div>
                                                <input type="text" id="supplier_order_subtotal"
                                                    class="form-control text-right" value="0.00" readonly>
                                            </div>
                                        </div>

                                        <div class="supplier-order-total-line">
                                            <span>Total I.G.V.</span>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text supplier-order-currency-code">PEN</span>
                                                </div>
                                                <input type="text" id="supplier_order_igv"
                                                    class="form-control text-right" value="0.00" readonly>
                                            </div>
                                        </div>

                                        <div class="supplier-order-total-line supplier-order-total-grand">
                                            <span>Total compra</span>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text supplier-order-currency-code">PEN</span>
                                                </div>
                                                <input type="text" id="supplier_order_grand_total"
                                                    class="form-control text-right font-weight-bold" value="0.00"
                                                    readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template id="supplierOrderItemRowTemplate">
                        <tr class="supplier-order-item-row">
                            <td class="supplier-order-item-index align-middle"></td>
                            <td>
                                <input type="hidden" name="items[__INDEX__][id]" class="item-id">
                                <input type="hidden" name="items[__INDEX__][market_study_item_id]"
                                    class="item-market-study-item-id">
                                <input type="hidden" name="items[__INDEX__][quote_item_id]"
                                    class="item-quote-item-id">
                                <input type="hidden" name="items[__INDEX__][customer_purchase_order_item_id]"
                                    class="item-customer-purchase-order-item-id">
                                <input type="hidden" class="item-customer-unit-price">
                                <input type="hidden" name="items[__INDEX__][article_id]"
                                    class="item-article-id">
                                <input type="hidden" name="items[__INDEX__][article_code]"
                                    class="item-article-code">
                                <input type="hidden" name="items[__INDEX__][billing_name_snapshot]"
                                    class="item-billing-name">
                                <select
                                    class="form-control form-control-sm item-article-picker js-supplier-order-row-select">
                                    <option value="">Seleccione art&iacute;culo</option>
                                    @foreach ($articles as $article)
                                        @php
                                            $institutionalLabel = $article->institutional_code
                                                ? ($article->code_type ?: 'C.I.') . ': ' . $article->institutional_code
                                                : null;
                                            $articleOptionText = implode(' | ', array_filter([
                                                $article->code,
                                                $institutionalLabel,
                                                $article->billing_name,
                                            ]));
                                        @endphp
                                        <option value="{{ $article->id }}" data-code="{{ $article->code }}"
                                            data-billing-name="{{ $article->billing_name }}"
                                            data-search="{{ implode(' ', array_filter([$article->code, $article->legal_name, $article->commercial_name, $article->billing_name, $article->institutional_code])) }}"
                                            data-unit-id="{{ $article->unit_id }}"
                                            data-presentation-id="{{ $article->presentation_id }}"
                                            data-brand-id="{{ $article->brand_id }}">
                                            {{ $articleOptionText }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" name="items[__INDEX__][note]"
                                    class="form-control form-control-sm item-note" placeholder="Nota">
                            </td>
                            <td>
                                <select name="items[__INDEX__][unit_id]"
                                    class="form-control form-control-sm item-unit-id js-supplier-order-row-select">
                                    <option value="">Seleccione</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->abbreviation ?? $unit->description }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="items[__INDEX__][presentation_id]"
                                    class="form-control form-control-sm item-presentation-id js-supplier-order-row-select">
                                    <option value="">Seleccione</option>
                                    @foreach ($presentations as $presentation)
                                        <option value="{{ $presentation->id }}">{{ $presentation->description }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="items[__INDEX__][brand_id]"
                                    class="form-control form-control-sm item-brand-id js-supplier-order-row-select">
                                    <option value="">Seleccione</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->description }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" name="items[__INDEX__][origin]"
                                    class="form-control form-control-sm item-origin text-uppercase"
                                    placeholder="NACIONAL">
                            </td>
                            <td>
                                <input type="date" name="items[__INDEX__][expiration_date]"
                                    class="form-control form-control-sm item-expiration-date">
                            </td>
                            <td>
                                <select name="items[__INDEX__][cost_type]"
                                    class="form-control form-control-sm item-cost-type">
                                    <option value="PESO">PESO</option>
                                    <option value="UNIDAD">UNIDAD</option>
                                    <option value="CAJA">CAJA</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="any" min="0" inputmode="decimal"
                                    name="items[__INDEX__][reference_purchase_price]"
                                    class="form-control form-control-sm text-right item-reference-purchase-price"
                                    value="0.00">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0.01"
                                    name="items[__INDEX__][quantity]"
                                    class="form-control form-control-sm text-right item-quantity" value="1.00">
                            </td>
                            <td>
                                <input type="number" step="any" min="0" inputmode="decimal"
                                    name="items[__INDEX__][unit_price]"
                                    class="form-control form-control-sm text-right item-unit-price" value="0">
                                <small class="text-muted item-max-price-reference d-none"></small>
                                <span class="invalid-feedback"></span>
                            </td>
                            <td>
                                <input type="number"
                                    class="form-control form-control-sm text-right font-weight-bold item-line-total"
                                    value="0" min="0" step="0.000001" readonly>
                            </td>
                            <td>
                                <input type="number"
                                    class="form-control form-control-sm text-right item-taxable-base"
                                    value="0" min="0" step="0.000001" readonly>
                            </td>
                            <td>
                                <input type="number"
                                    class="form-control form-control-sm text-right item-igv-percent"
                                    value="18.00" min="0" step="0.01" readonly>
                            </td>
                            <td>
                                <input type="number"
                                    class="form-control form-control-sm text-right item-igv-amount"
                                    value="0" min="0" step="0.000001" readonly>
                            </td>
                            <td class="align-middle text-center">
                                <button type="button"
                                    class="btn btn-outline-danger btn-sm btnRemoveSupplierOrderItem"
                                    title="Quitar &iacute;tem">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </form>
            </div>
            <div class="modal-footer supplier-order-modal-footer">
                <div class="supplier-order-footer-hint"><i class="fas fa-info-circle"></i><span>Revise las secciones marcadas antes de guardar.</span></div>
                <div class="d-flex supplier-order-footer-actions">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal"><i class="fas fa-times mr-1"></i>Cancelar</button>
                    <button type="submit" form="supplierPurchaseOrderForm" id="btnSaveSupplierPurchaseOrder" class="btn btn-success btn-sm"><i class="fas fa-save mr-1"></i>Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="supplierOrderPendingItemsModal" tabindex="-1" role="dialog"
    aria-labelledby="supplierOrderPendingItemsModalLabel" aria-hidden="true" data-backdrop="static"
    data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow-lg supplier-order-pending-modal">
            <div class="modal-header align-items-center supplier-order-modal-header">
                <div>
                    <h5 class="modal-title mb-0 font-weight-bold" id="supplierOrderPendingItemsModalLabel">
                        Ítems pendientes de la orden del cliente
                    </h5>
                    <small class="text-muted">
                        Seleccione los artículos que comprará al proveedor elegido. Puede modificar la cantidad y el precio de compra antes de agregarlos.
                    </small>
                </div>
                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-2">
                <div class="supplier-order-table-scroll">
                    <table class="table table-sm table-hover mb-0" id="supplierOrderPendingItemsTable">
                        <thead class="bg-light text-center">
                            <tr>
                                <th>
                                    <input type="checkbox" id="supplierOrderPendingCheckAll">
                                </th>
                                <th>N° Orden Cliente</th>
                                <th>Artículo</th>
                                <th>Presentación</th>
                                <th>Marca</th>
                                <th>Procedencia</th>
                                <th>F. Venc.</th>
                                <th>Solicitada</th>
                                <th>Comprada</th>
                                <th>Pendiente</th>
                                <th>Cant. a comprar</th>
                                <th>Precio compra</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody id="supplierOrderPendingItemsTbody">
                            <tr>
                                <td colspan="13" class="text-center text-muted py-4">
                                    Sin ítems pendientes para mostrar.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">
                    Cancelar
                </button>
                <button type="button" id="btnAddSelectedSupplierPendingItems" class="btn btn-success btn-sm">
                    <i class="fas fa-plus-circle mr-1"></i>
                    Agregar seleccionados
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #supplierPurchaseOrderModal .supplier-order-modal-dialog {
        max-width: 94%;
    }

    #supplierPurchaseOrderModal .modal-content,
    #supplierPurchaseOrderModal .card {
        border-radius: 14px;
    }

    #supplierPurchaseOrderModal .supplier-order-modal-header {
        background: linear-gradient(90deg, #f4fff8, #eaf8ef);
        border-bottom: 1px solid #b8e2c5;
    }

    #supplierPurchaseOrderModal .supplier-order-header-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #dff5e7;
    }

    #supplierPurchaseOrderModal .modal-body {
        max-height: calc(100vh - 85px);
        overflow-y: auto;
        overflow-x: hidden;
        background: #f8fffb;
    }

    #supplierPurchaseOrderModal .supplier-order-side-card {
        background: #fff;
    }

    #supplierPurchaseOrderModal .supplier-order-side-icon {
        width: 85px;
        height: 85px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #198754, #157347);
        box-shadow: 0 6px 18px rgba(25, 135, 84, .22);
        font-size: 32px;
    }

    #supplierPurchaseOrderModal .supplier-order-side-total {
        padding: 10px 12px;
        border-radius: 12px;
        color: #fff;
        background: linear-gradient(135deg, #198754, #157347);
        box-shadow: 0 6px 16px rgba(25, 135, 84, .22);
        text-align: center;
        font-size: 20px;
        font-weight: 800;
    }

    #supplierPurchaseOrderModal .supplier-order-currency-symbol {
        font-size: 13px;
        font-weight: 600;
        opacity: .9;
    }

    #supplierPurchaseOrderModal .supplier-order-info-alert {
        color: #0f5132;
        background: #eaf8ef;
    }

    #supplierPurchaseOrderModal label {
        margin-bottom: 2px;
        color: #495057;
        font-size: 11px;
        font-weight: 700;
    }

    #supplierPurchaseOrderModal .form-group {
        margin-bottom: .5rem;
    }

    #supplierPurchaseOrderModal .form-control,
    #supplierPurchaseOrderModal .custom-select {
        height: 31px;
        font-size: 12px;
    }

    #supplierPurchaseOrderModal textarea.form-control {
        min-height: 58px;
        height: auto;
        resize: vertical;
    }

    #supplierPurchaseOrderModal .supplier-order-informative-textarea {
        min-height: 112px;
        border-color: #b8e2c5;
        background: #f4fff8;
        color: #14532d;
        font-size: 12px;
        line-height: 1.45;
        box-shadow: inset 0 1px 2px rgba(20, 83, 45, .04);
    }

    #supplierPurchaseOrderModal .form-control:focus,
    #supplierPurchaseOrderModal .custom-select:focus {
        border-color: #75b798;
        box-shadow: 0 0 0 .12rem rgba(25, 135, 84, .15);
    }

    #supplierPurchaseOrderModal .font-weight-600 {
        font-weight: 600;
    }

    #supplierPurchaseOrderModal .supplier-order-table-scroll {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    #supplierPurchaseOrderModal .supplier-order-table-scroll::-webkit-scrollbar {
        height: 8px;
    }

    #supplierPurchaseOrderModal .supplier-order-table-scroll::-webkit-scrollbar-thumb {
        border-radius: 20px;
        background: #b8e2c5;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable {
        min-width: 1810px;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable thead th {
        padding: .5rem .35rem;
        border-top: 0;
        border-bottom: 2px solid #d7f0df;
        color: #374151;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .25px;
        white-space: nowrap;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable tbody td {
        padding: .3rem;
        border-top: 1px solid #eef7f1;
        vertical-align: middle;
        white-space: nowrap;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-article-picker {
        min-width: 290px;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-note {
        min-width: 90px;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable select {
        min-width: 90px;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-presentation-id,
    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-brand-id {
        min-width: 115px;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-origin,
    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-expiration-date {
        min-width: 110px;
    }

    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-reference-purchase-price,
    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-quantity,
    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-unit-price,
    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-line-total,
    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-taxable-base,
    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-igv-percent,
    #supplierPurchaseOrderModal #supplierOrderItemsTable .item-igv-amount {
        min-width: 90px;
    }

    #supplierPurchaseOrderModal .supplier-order-totals-area {
        padding: 12px 16px;
        border-top: 1px solid #d7f0df;
        background: #fff;
    }

    #supplierPurchaseOrderModal .supplier-order-total-line {
        display: grid;
        grid-template-columns: 1fr 155px;
        gap: 10px;
        align-items: center;
        margin-bottom: 7px;
    }

    #supplierPurchaseOrderModal .supplier-order-total-line>span {
        color: #495057;
        font-size: 12px;
        font-weight: 700;
        text-align: right;
    }

    #supplierPurchaseOrderModal .supplier-order-total-line .input-group-text {
        color: #198754;
        border-color: #d7f0df;
        background: #f8fffb;
        font-size: 11px;
        font-weight: 700;
    }

    #supplierPurchaseOrderModal .supplier-order-total-grand>span,
    #supplierPurchaseOrderModal .supplier-order-total-grand input {
        color: #198754;
    }

    #supplierPurchaseOrderModal .supplier-order-total-grand input {
        border-color: #b8e2c5;
        background: #f4fff8;
    }

    #supplierPurchaseOrderModal .select2-container--bootstrap4 .select2-selection {
        min-height: 31px;
    }

    #supplierPurchaseOrderModal .select2-container--bootstrap4 .select2-selection--single {
        height: 31px !important;
        display: flex;
        align-items: center;
    }

    #supplierPurchaseOrderModal .select2-container--bootstrap4 .select2-selection--multiple {
        min-height: 42px !important;
        padding: 4px 8px 2px;
        border-color: #cfe8d8;
        background: #fff;
    }

    #supplierPurchaseOrderModal .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice {
        margin: 2px 5px 4px 0;
        padding: 3px 8px 3px 20px;
        border: 1px solid #a7d9ba;
        border-radius: 999px;
        color: #14532d;
        background: #eaf8ef;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.25;
        max-width: 100%;
        white-space: normal;
        word-break: break-word;
    }

    #supplierPurchaseOrderModal .select2-container--bootstrap4 .select2-selection--multiple .select2-selection__choice__remove {
        left: 7px;
        top: 3px;
        color: #198754;
        font-weight: 800;
    }

    #supplierPurchaseOrderModal .supplier-order-customer-orders-field .select2-container--bootstrap4 .select2-search--inline .select2-search__field {
        min-width: 170px;
        height: 24px;
        margin-top: 2px;
        font-size: 12px;
    }

    #supplierPurchaseOrderModal .select2-container--bootstrap4 .select2-selection__rendered {
        padding-left: 8px !important;
        padding-right: 18px !important;
        font-size: 12px;
        line-height: 29px !important;
    }

    #supplierPurchaseOrderModal .supplier-order-supplier-picker { flex-wrap: nowrap; }
    #supplierPurchaseOrderModal .supplier-order-account-picker { flex-wrap: nowrap; }
    #supplierPurchaseOrderModal .supplier-order-supplier-picker .select2-container,
    #supplierPurchaseOrderModal .supplier-order-account-picker .select2-container { flex: 1 1 auto; width: 1% !important; }
    #supplierPurchaseOrderModal .supplier-order-supplier-picker .btn,
    #supplierPurchaseOrderModal .supplier-order-account-picker .btn { height: 31px; min-width: 36px; }

    #supplierOrderPendingItemsModal .supplier-order-pending-modal {
        border-radius: 14px;
    }

    #supplierOrderPendingItemsModal .modal-body {
        max-height: calc(100vh - 180px);
        overflow-y: auto;
        overflow-x: hidden;
        background: #f8fffb;
    }

    #supplierOrderPendingItemsModal .supplier-order-table-scroll {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    #supplierOrderPendingItemsModal table {
        min-width: 1420px;
    }

    #supplierOrderPendingItemsModal th {
        color: #374151;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }

    #supplierOrderPendingItemsModal td {
        font-size: 11px;
        vertical-align: middle;
        white-space: nowrap;
    }

    #supplierOrderPendingItemsModal .pending-badge {
        border-radius: 999px;
        padding: 3px 8px;
        color: #14532d;
        background: #dcfce7;
        font-weight: 800;
    }

    #supplierPurchaseOrderModal .modal-body {
        max-height: calc(100vh - 148px);
    }

    #supplierPurchaseOrderModal .supplier-order-tabs-column {
        min-width: 0;
    }

    #supplierPurchaseOrderModal .supplier-order-tabs-shell {
        overflow: hidden;
        border: 1px solid #dce9e3;
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(25, 78, 58, .07);
    }

    #supplierPurchaseOrderModal .supplier-order-form-tabs {
        overflow-x: auto;
        padding: 8px 9px 0;
        border-bottom: 1px solid #e2ece7;
        background: #f7fbf9;
        scrollbar-width: thin;
    }

    #supplierPurchaseOrderModal .supplier-order-form-tabs .nav {
        width: max-content;
        min-width: 100%;
        gap: 4px;
    }

    #supplierPurchaseOrderModal .supplier-order-form-tabs .nav-link {
        position: relative;
        display: inline-flex;
        min-height: 43px;
        align-items: center;
        gap: 7px;
        padding: 8px 11px;
        border: 1px solid transparent;
        border-radius: 9px 9px 0 0;
        color: #60736b;
        font-size: 11px;
        font-weight: 750;
        white-space: nowrap;
        transition: background-color .18s ease, color .18s ease, border-color .18s ease;
    }

    #supplierPurchaseOrderModal .supplier-order-form-tabs .nav-link i {
        font-size: 12px;
    }

    #supplierPurchaseOrderModal .supplier-order-form-tabs .nav-link.active {
        border-color: #cbe4d8 #cbe4d8 #fff;
        background: #fff;
        color: #147553;
        box-shadow: 0 -2px 8px rgba(20, 117, 83, .06);
    }

    #supplierPurchaseOrderModal .supplier-order-tab-error {
        display: inline-grid;
        width: 17px;
        height: 17px;
        place-items: center;
        border-radius: 50%;
        background: #dc3545;
        color: #fff;
        font-size: 9px;
    }

    #supplierPurchaseOrderModal .supplier-order-form-tab-content {
        min-height: 480px;
        padding: 12px;
        background: #f8fbfa;
    }

    #supplierPurchaseOrderModal .supplier-order-tab-card,
    #supplierPurchaseOrderModal .supplier-order-form-tab-content > .tab-pane > div > .card {
        margin: 0;
        border: 1px solid #e0ebe6;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(23, 76, 56, .055);
    }

    #supplierPurchaseOrderModal .supplier-order-tab-card > .card-header {
        padding: 12px 15px;
        border: 0;
        border-bottom: 1px solid #e7efeb;
        background: #fff;
    }

    #supplierPurchaseOrderModal .supplier-order-tab-card > .card-header h6 {
        margin: 0 0 2px;
        color: #28473b;
        font-size: 13px;
        font-weight: 800;
    }

    #supplierPurchaseOrderModal .supplier-order-tab-card > .card-header h6 i {
        margin-right: 6px;
        color: #198754;
    }

    #supplierPurchaseOrderModal .supplier-order-tab-card > .card-header small {
        color: #7b8c85;
        font-size: 10px;
    }

    #supplierPurchaseOrderModal .supplier-order-tab-card > .card-body {
        padding: 15px;
    }

    #supplierPurchaseOrderModal .supplier-order-financial-card,
    #supplierPurchaseOrderModal .supplier-order-documents-card,
    #supplierPurchaseOrderModal .supplier-order-items-full,
    #supplierPurchaseOrderModal .supplier-order-pdf-container > .card {
        margin-bottom: 0 !important;
    }

    #supplierPurchaseOrderModal .supplier-order-items-container .supplier-order-table-scroll {
        max-height: calc(100vh - 390px);
        min-height: 245px;
        overflow: auto;
    }

    #supplierPurchaseOrderModal .supplier-order-side-finance {
        display: grid;
        gap: 7px;
        padding: 10px;
        border: 1px solid #dcebe4;
        border-radius: 10px;
        background: #f7fbf9;
        text-align: left;
    }

    #supplierPurchaseOrderModal .supplier-order-side-finance > div {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }

    #supplierPurchaseOrderModal .supplier-order-side-finance span {
        color: #789087;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    #supplierPurchaseOrderModal .supplier-order-side-finance strong {
        color: #214f3d;
        font-size: 10px;
        text-align: right;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 13px;
        padding: 14px 16px;
        border: 1px solid #d9e9e2;
        border-radius: 12px;
        background: linear-gradient(135deg, #fff, #f0faf5);
    }

    #supplierPurchaseOrderModal .supplier-order-summary-heading span:first-child {
        color: #198754;
        font-size: 9px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-heading h6 {
        margin: 2px 0;
        color: #25483a;
        font-weight: 800;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-heading small {
        color: #71837c;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-code {
        padding: 6px 10px;
        border: 1px solid #c7e5d8;
        border-radius: 999px;
        background: #e9f8f1;
        color: #147553;
        font-size: 10px !important;
        letter-spacing: 0 !important;
        text-transform: none !important;
        white-space: nowrap;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-card {
        display: flex;
        min-width: 0;
        align-items: flex-start;
        gap: 9px;
        padding: 11px;
        border: 1px solid #e0ebe6;
        border-radius: 11px;
        background: #fff;
        box-shadow: 0 3px 10px rgba(25, 78, 58, .045);
    }

    #supplierPurchaseOrderModal .supplier-order-summary-card > span {
        display: inline-grid;
        flex: 0 0 30px;
        width: 30px;
        height: 30px;
        place-items: center;
        border-radius: 9px;
        background: #e9f7f1;
        color: #198754;
        font-size: 12px;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-card div {
        min-width: 0;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-card small,
    #supplierPurchaseOrderModal .supplier-order-summary-card strong {
        display: block;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-card small {
        margin-bottom: 3px;
        color: #81928b;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-card strong {
        overflow: hidden;
        color: #29483c;
        font-size: 11px;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-alerts {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 8px;
        margin-top: 12px;
    }

    #supplierPurchaseOrderModal .supplier-order-summary-alerts .alert {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        padding: 9px 11px;
        border-radius: 9px;
        font-size: 10px;
    }

    #supplierPurchaseOrderModal .supplier-order-modal-footer {
        display: flex;
        min-height: 62px;
        align-items: center;
        justify-content: space-between;
        padding: 10px 15px;
        border-top: 1px solid #dce9e3;
        background: rgba(255, 255, 255, .98);
        box-shadow: 0 -7px 20px rgba(30, 73, 57, .07);
    }

    #supplierPurchaseOrderModal .supplier-order-footer-hint {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #71857c;
        font-size: 10px;
    }

    #supplierPurchaseOrderModal .supplier-order-footer-hint i {
        color: #198754;
    }

    #supplierPurchaseOrderModal .supplier-order-footer-actions {
        gap: 8px;
    }

    #supplierPurchaseOrderModal .supplier-order-modal-footer .btn {
        min-width: 112px;
        padding: 7px 14px;
        border-radius: 9px;
        font-weight: 750;
    }

    @media (max-width: 1199px) {
        #supplierPurchaseOrderModal .supplier-order-summary-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }
    }

    @media (max-width: 991px) {
        #supplierPurchaseOrderModal .supplier-order-modal-dialog {
            max-width: 98%;
            margin: .5rem auto;
        }

        #supplierPurchaseOrderModal .supplier-order-total-line {
            grid-template-columns: 1fr;
            gap: 3px;
        }

        #supplierPurchaseOrderModal .supplier-order-total-line>span {
            text-align: left;
        }

        #supplierPurchaseOrderModal .supplier-order-form-tab-content {
            min-height: 420px;
        }

        #supplierPurchaseOrderModal .supplier-order-summary-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        #supplierPurchaseOrderModal .supplier-order-summary-alerts {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        #supplierPurchaseOrderModal .supplier-order-modal-dialog {
            max-width: 100%;
            margin: 0;
        }

        #supplierPurchaseOrderModal .modal-content {
            min-height: 100vh;
            border-radius: 0;
        }

        #supplierPurchaseOrderModal .modal-body {
            max-height: calc(100vh - 124px);
            padding: .5rem !important;
        }

        #supplierPurchaseOrderModal .modal-header {
            padding: .65rem .85rem;
        }

        #supplierPurchaseOrderModal .supplier-order-header-icon {
            display: none;
        }

        #supplierPurchaseOrderModal #supplierOrderItemsTable {
            min-width: 1260px;
        }

        #supplierPurchaseOrderModal .supplier-order-form-tabs .nav-link {
            min-height: 39px;
            padding: 7px 9px;
        }

        #supplierPurchaseOrderModal .supplier-order-form-tabs .nav-link span {
            font-size: 10px;
        }

        #supplierPurchaseOrderModal .supplier-order-form-tab-content {
            min-height: 0;
            padding: 8px;
        }

        #supplierPurchaseOrderModal .supplier-order-summary-grid {
            grid-template-columns: 1fr;
        }

        #supplierPurchaseOrderModal .supplier-order-summary-heading {
            align-items: flex-start;
            flex-direction: column;
        }

        #supplierPurchaseOrderModal .supplier-order-modal-footer {
            padding: 8px;
        }

        #supplierPurchaseOrderModal .supplier-order-footer-hint {
            display: none;
        }

        #supplierPurchaseOrderModal .supplier-order-footer-actions,
        #supplierPurchaseOrderModal .supplier-order-footer-actions .btn {
            flex: 1;
        }
    }
</style>
