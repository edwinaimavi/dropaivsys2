<div class="modal fade" id="customerPurchaseOrderModal" tabindex="-1" role="dialog"
    aria-labelledby="customerPurchaseOrderModalLabel" aria-hidden="true" data-backdrop="static"
    data-keyboard="false">

    <div class="modal-dialog modal-lg modal-dialog-centered purchase-order-modal-dialog" role="document">
        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header align-items-center purchase-order-modal-header">
                <div class="d-flex align-items-center">
                    <div class="purchase-order-header-icon mr-3">
                        <i class="fas fa-file-signature text-primary"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0 font-weight-bold" id="customerPurchaseOrderModalLabel">
                            Registrar Orden de Compra del Cliente
                        </h5>
                        <small class="text-muted">
                            Registro de productos o servicios adjudicados por el cliente
                        </small>
                    </div>
                </div>

                <button type="button" class="close ml-3" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body p-2">
                <form id="customerPurchaseOrderForm" autocomplete="off" class="row">
                    @csrf

                    <input type="hidden" id="customer_purchase_order_id" name="customer_purchase_order_id">
                    <input type="hidden" id="purchase_order_status" name="status" value="registered">

                    <div class="col-12">
                        <div id="customerPurchaseOrderErrors" class="alert alert-danger d-none mb-2"></div>
                    </div>

                    <div class="col-lg-3 mb-2">
                        <div class="card border-0 shadow-sm h-100 purchase-order-side-card">
                            <div class="card-body text-center py-3 px-3">
                                <div class="purchase-order-side-icon mx-auto mb-3">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>

                                <h5 class="font-weight-bold text-dark mb-1">Orden de Compra</h5>
                                <small class="text-muted">Pedido adjudicado por cliente</small>

                                <hr class="my-2">

                                <div class="text-left small">
                                    <small class="text-muted d-block">N° interno</small>
                                    <input type="text" id="purchase_order_code" name="code"
                                        class="form-control form-control-sm mb-2 text-center font-weight-bold"
                                        placeholder="Automático" readonly>

                                    <small class="text-muted d-block">Fecha de registro</small>
                                    <div class="font-weight-600 mb-2">{{ now()->format('d/m/Y') }}</div>

                                    <small class="text-muted d-block">Estado inicial</small>
                                    <span class="badge badge-secondary px-2 py-1 mb-2">Registrada</span>

                                    <small class="text-muted d-block">Cliente</small>
                                    <div class="font-weight-600 mb-2 text-break" id="purchaseOrderSideCustomer">
                                        Seleccione cliente
                                    </div>

                                    <small class="text-muted d-block">Sucursal / Tienda</small>
                                    <div class="font-weight-600 mb-2 text-break" id="purchaseOrderSideBranch">
                                        Seleccione sucursal
                                    </div>

                                    <small class="text-muted d-block">Total de venta</small>
                                    <div class="purchase-order-side-total mt-1">
                                        <span class="purchase-order-currency-symbol">S/</span>
                                        <span id="purchaseOrderSideGrandTotal">0.00</span>
                                    </div>
                                </div>

                                <div class="alert border-0 shadow-sm mt-3 mb-0 text-left purchase-order-info-alert">
                                    <i class="fas fa-info-circle text-primary mr-1"></i>
                                    <span class="small">
                                        Filtra una cotización y elimina los ítems que no fueron adjudicados.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9 mb-2">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-dark">
                                            <i class="fas fa-clipboard-list text-primary mr-1"></i>
                                            Datos principales de la orden
                                        </h6>
                                        <small class="text-muted">
                                            Información del pedido, cliente y condiciones de entrega
                                        </small>
                                    </div>

                                    <div class="mt-2 mt-md-0">
                                        <button type="button" class="btn btn-light border btn-sm mr-2"
                                            data-dismiss="modal">
                                            <i class="fas fa-times mr-1"></i>
                                            Cerrar
                                        </button>
                                        <button type="submit" id="btnSaveCustomerPurchaseOrder"
                                            class="btn btn-primary btn-sm">
                                            <i class="fas fa-save mr-1"></i>
                                            Guardar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body py-3">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>EMPRESA <span class="text-danger">*</span></label>
                                        <select id="purchase_order_company_id" name="company_id"
                                            class="form-control form-control-sm js-purchase-order-select">
                                            <option value="">Seleccione empresa</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company->id }}">
                                                    {{ $company->trade_name ?: $company->business_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback" id="purchase_order_company_id-error"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>TIPO DE ORDEN <span class="text-danger">*</span></label>
                                        <select id="purchase_order_type" name="order_type"
                                            class="form-control form-control-sm js-purchase-order-select">
                                            <option value="articles" selected>ARTÍCULOS</option>
                                            <option value="services">SERVICIOS</option>
                                        </select>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>NRO ORDEN DE COMPRA</label>
                                        <input type="text" id="purchase_order_number" name="purchase_order_number"
                                            class="form-control form-control-sm text-uppercase"
                                            placeholder="NRO ORDEN DE COMPRA DEL CLIENTE">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-8">
                                        <label>COTIZACIÓN RELACIONADA</label>
                                        <div class="input-group input-group-sm">
                                            <select id="purchase_order_quote_id" name="quote_id"
                                                class="form-control form-control-sm js-purchase-order-select">
                                                <option value="">Seleccione cotización</option>
                                                @foreach ($quotes as $quote)
                                                    @php
                                                        $quoteCustomer = $quote->customer?->business_name
                                                            ?? $quote->customer?->full_name
                                                            ?? trim(
                                                                ($quote->customer?->first_name ?? '') .
                                                                    ' ' .
                                                                    ($quote->customer?->last_name ?? ''),
                                                            )
                                                            ?: 'Sin cliente';
                                                    @endphp
                                                    <option value="{{ $quote->id }}"
                                                        data-customer-id="{{ $quote->customer_id }}"
                                                        data-currency-id="{{ $quote->currency_id }}"
                                                        data-status="{{ $quote->status }}">
                                                        {{ $quote->quote_number }} | {{ $quoteCustomer }} |
                                                        {{ optional($quote->created_at)->format('d/m/Y') }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <div class="input-group-append">
                                                <button type="button" id="btnFilterQuote"
                                                    class="btn btn-outline-primary">
                                                    <i class="fas fa-filter mr-1"></i>
                                                    Filtrar Cotización
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>MONEDA <span class="text-danger">*</span></label>
                                        <select id="purchase_order_currency_id" name="currency_id"
                                            class="form-control form-control-sm js-purchase-order-select">
                                            <option value="">Seleccione moneda</option>
                                            @foreach ($currencies as $currency)
                                                <option value="{{ $currency->id }}" data-code="{{ $currency->code }}"
                                                    data-symbol="{{ $currency->symbol }}" @selected($currency->code === 'PEN')>
                                                    {{ $currency->code }} | {{ $currency->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>CLIENTE <span class="text-danger">*</span></label>
                                        <select id="purchase_order_customer_id" name="customer_id"
                                            class="form-control form-control-sm js-purchase-order-select">
                                            <option value="">Seleccione cliente</option>
                                            @foreach ($customers as $customer)
                                                @php
                                                    $customerName = $customer->business_name
                                                        ?? $customer->full_name
                                                        ?? trim(
                                                            ($customer->first_name ?? '') .
                                                                ' ' .
                                                                ($customer->last_name ?? ''),
                                                        )
                                                        ?: ($customer->name ?? 'Cliente');
                                                    $customerDocument = $customer->document_number ?? $customer->ruc;
                                                @endphp
                                                <option value="{{ $customer->id }}">
                                                    {{ $customerDocument ? $customerDocument . ' | ' : '' }}{{ $customerName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="form-group col-md-6">
                                        <label>SUCURSAL / TIENDA</label>
                                        <select id="purchase_order_customer_branch_id" name="customer_branch_id"
                                            class="form-control form-control-sm js-purchase-order-select" disabled>
                                            <option value="">Seleccione cliente primero</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>FECHA NOTIFICACIÓN</label>
                                        <input type="date" id="purchase_order_notification_date"
                                            name="notification_date" class="form-control form-control-sm">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>PLAZO ENTREGA - DESDE</label>
                                        <input type="date" id="purchase_order_delivery_start_date"
                                            name="delivery_start_date" class="form-control form-control-sm">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>HASTA</label>
                                        <input type="date" id="purchase_order_delivery_end_date"
                                            name="delivery_end_date" class="form-control form-control-sm">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>NRO EXPEDIENTE SIAF</label>
                                        <input type="text" id="purchase_order_siaf_file_number"
                                            name="siaf_file_number"
                                            class="form-control form-control-sm text-uppercase">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>NRO CUADRO ADQUI.</label>
                                        <input type="text" id="purchase_order_acquisition_chart_number"
                                            name="acquisition_chart_number"
                                            class="form-control form-control-sm text-uppercase">
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>TIPO DE PROCESO</label>
                                        <input type="text" id="purchase_order_process_type" name="process_type"
                                            class="form-control form-control-sm text-uppercase">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-3">
                                        <label>TIPO FACTURACIÓN</label>
                                        <select id="purchase_order_billing_type" name="billing_type"
                                            class="form-control form-control-sm js-purchase-order-select">
                                            <option value="local" selected>Facturación local</option>
                                            <option value="export">Exportación</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>AFECTO IGV</label>
                                        <select id="purchase_order_affect_igv" name="affect_igv"
                                            class="form-control form-control-sm js-purchase-order-select">
                                            <option value="1">SI</option>
                                            <option value="0" selected>NO</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>OBSERVACIÓN</label>
                                        <textarea id="purchase_order_observations" name="observations"
                                            class="form-control form-control-sm text-uppercase" rows="2"
                                            placeholder="Observaciones de la orden"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm purchase-order-items-full">
                            <div class="card-header bg-white border-0 py-2 px-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-dark">
                                            <i class="fas fa-boxes text-primary mr-1"></i>
                                            Artículos adjudicados
                                        </h6>
                                        <small class="text-muted">
                                            Conserva únicamente las líneas adjudicadas por el cliente
                                        </small>
                                    </div>

                                    <button type="button" id="btnAddPurchaseOrderItem"
                                        class="btn btn-outline-primary btn-sm mt-2 mt-md-0">
                                        <i class="fas fa-plus-circle mr-1"></i>
                                        Insertar artículo
                                    </button>
                                </div>
                            </div>

                            <div class="purchase-order-table-scroll">
                                <table id="purchaseOrderItemsTable" class="table table-sm mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>#</th>
                                            <th>ARTÍCULO</th>
                                            <th>NOTA</th>
                                            <th>U.M.</th>
                                            <th>PRESENT.</th>
                                            <th>MARCA</th>
                                            <th>PROCEDENCIA</th>
                                            <th>F. VENC.</th>
                                            <th>C. COSTEO</th>
                                            <th>CANTIDAD</th>
                                            <th>PRECIO</th>
                                            <th>P. TOTAL</th>
                                            <th>ACCIÓN</th>
                                        </tr>
                                    </thead>
                                    <tbody id="purchaseOrderItemsTbody">
                                        <tr id="purchaseOrderItemsEmptyRow">
                                            <td colspan="13" class="text-center text-muted py-4">
                                                <i class="fas fa-box-open d-block mb-2"></i>
                                                Selecciona y filtra una cotización para cargar sus ítems.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="purchase-order-totals-area">
                                <div class="row justify-content-end">
                                    <div class="col-lg-5 col-xl-4">
                                        <div class="purchase-order-total-line">
                                            <span>Venta Exonerada</span>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text purchase-order-currency-code">PEN</span>
                                                </div>
                                                <input type="text" id="purchase_order_subtotal_exonerated"
                                                    name="subtotal_exonerated" class="form-control text-right"
                                                    value="0.00" readonly>
                                            </div>
                                        </div>

                                        <div class="purchase-order-total-line">
                                            <span>Venta Gravada</span>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text purchase-order-currency-code">PEN</span>
                                                </div>
                                                <input type="text" id="purchase_order_subtotal_taxed"
                                                    name="subtotal_taxed" class="form-control text-right"
                                                    value="0.00" readonly>
                                            </div>
                                        </div>

                                        <div class="purchase-order-total-line">
                                            <span>Total I.G.V.</span>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text purchase-order-currency-code">PEN</span>
                                                </div>
                                                <input type="text" id="purchase_order_igv" name="igv"
                                                    class="form-control text-right" value="0.00" readonly>
                                            </div>
                                        </div>

                                        <div class="purchase-order-total-line purchase-order-total-grand">
                                            <span>Total Precio de Venta</span>
                                            <div class="input-group input-group-sm">
                                                <div class="input-group-prepend">
                                                    <span
                                                        class="input-group-text purchase-order-currency-code">PEN</span>
                                                </div>
                                                <input type="text" id="purchase_order_grand_total" name="grand_total"
                                                    class="form-control text-right font-weight-bold" value="0.00"
                                                    readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template id="purchaseOrderItemRowTemplate">
                        <tr class="purchase-order-item-row">
                            <td class="purchase-order-item-index align-middle"></td>
                            <td>
                                <input type="hidden" class="item-quote-item-id"
                                    name="items[__INDEX__][quote_item_id]">
                                <input type="hidden" class="item-market-study-item-id"
                                    name="items[__INDEX__][market_study_item_id]">
                                <input type="hidden" class="item-article-id"
                                    name="items[__INDEX__][article_id]">
                                <input type="hidden" class="item-article-code"
                                    name="items[__INDEX__][article_code]">
                                <input type="hidden" class="item-billing-name"
                                    name="items[__INDEX__][billing_name_snapshot]">
                                <select class="form-control form-control-sm item-article-picker js-purchase-order-row-select">
                                    <option value="">Seleccione artículo</option>
                                    @foreach ($articles as $article)
                                        <option value="{{ $article->id }}" data-code="{{ $article->code }}"
                                            data-billing-name="{{ $article->billing_name }}"
                                            data-unit-id="{{ $article->unit_id }}"
                                            data-presentation-id="{{ $article->presentation_id }}"
                                            data-brand-id="{{ $article->brand_id }}">
                                            {{ $article->code }} | {{ $article->billing_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm item-note"
                                    name="items[__INDEX__][note]" placeholder="Nota">
                            </td>
                            <td>
                                <select class="form-control form-control-sm item-unit-id js-purchase-order-row-select"
                                    name="items[__INDEX__][unit_id]">
                                    <option value="">Seleccione</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->abbreviation }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select
                                    class="form-control form-control-sm item-presentation-id js-purchase-order-row-select"
                                    name="items[__INDEX__][presentation_id]">
                                    <option value="">Seleccione</option>
                                    @foreach ($presentations as $presentation)
                                        <option value="{{ $presentation->id }}">{{ $presentation->description }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select class="form-control form-control-sm item-brand-id js-purchase-order-row-select"
                                    name="items[__INDEX__][brand_id]">
                                    <option value="">Seleccione</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->description }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm item-origin text-uppercase"
                                    name="items[__INDEX__][origin]" placeholder="NACIONAL">
                            </td>
                            <td>
                                <input type="date" class="form-control form-control-sm item-expiration-date"
                                    name="items[__INDEX__][expiration_date]">
                            </td>
                            <td>
                                <select class="form-control form-control-sm item-cost-type"
                                    name="items[__INDEX__][cost_type]">
                                    <option value="PESO">PESO</option>
                                    <option value="UNIDAD">UNIDAD</option>
                                    <option value="CAJA">CAJA</option>
                                </select>
                            </td>
                            <td>
                                <input type="hidden" class="item-quoted-quantity"
                                    name="items[__INDEX__][quoted_quantity]" value="0.00">
                                <input type="number" class="form-control form-control-sm text-right item-quantity"
                                    name="items[__INDEX__][quantity]" value="1.00" min="0.01" step="0.01">
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm text-right item-unit-price"
                                    name="items[__INDEX__][unit_price]" value="0.00" min="0" step="0.01">
                            </td>
                            <td>
                                <input type="hidden" class="item-subtotal" name="items[__INDEX__][subtotal]"
                                    value="0.00">
                                <input type="hidden" class="item-tax-amount" name="items[__INDEX__][tax_amount]"
                                    value="0.00">
                                <input type="number"
                                    class="form-control form-control-sm text-right font-weight-bold item-line-total"
                                    name="items[__INDEX__][line_total]" value="0.00" min="0" step="0.01"
                                    readonly>
                            </td>
                            <td class="align-middle text-center">
                                <button type="button"
                                    class="btn btn-outline-danger btn-sm btnRemovePurchaseOrderItem"
                                    title="Quitar ítem">
                                    <i class="fas fa-times"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    #customerPurchaseOrderModal .purchase-order-modal-dialog {
        max-width: 94%;
    }

    #customerPurchaseOrderModal .modal-content,
    #customerPurchaseOrderModal .card {
        border-radius: 14px;
    }

    #customerPurchaseOrderModal .purchase-order-modal-header {
        background: linear-gradient(90deg, #f4f9ff, #eaf3ff);
        border-bottom: 1px solid #b8d7ff;
    }

    #customerPurchaseOrderModal .purchase-order-header-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #dff0ff;
    }

    #customerPurchaseOrderModal .modal-body {
        max-height: calc(100vh - 85px);
        overflow-y: auto;
        overflow-x: hidden;
        background: #f8fbff;
    }

    #customerPurchaseOrderModal .purchase-order-side-card {
        background: #fff;
    }

    #customerPurchaseOrderModal .purchase-order-side-icon {
        width: 85px;
        height: 85px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        box-shadow: 0 6px 18px rgba(13, 110, 253, .22);
        font-size: 32px;
    }

    #customerPurchaseOrderModal .purchase-order-side-total {
        padding: 10px 12px;
        border-radius: 12px;
        color: #fff;
        background: linear-gradient(135deg, #0d6efd, #0b5ed7);
        box-shadow: 0 6px 16px rgba(13, 110, 253, .22);
        text-align: center;
        font-size: 20px;
        font-weight: 800;
    }

    #customerPurchaseOrderModal .purchase-order-currency-symbol {
        font-size: 13px;
        font-weight: 600;
        opacity: .9;
    }

    #customerPurchaseOrderModal .purchase-order-info-alert {
        color: #084298;
        background: #eaf3ff;
    }

    #customerPurchaseOrderModal label {
        margin-bottom: 2px;
        color: #495057;
        font-size: 11px;
        font-weight: 700;
    }

    #customerPurchaseOrderModal .form-group {
        margin-bottom: .5rem;
    }

    #customerPurchaseOrderModal .form-control,
    #customerPurchaseOrderModal .custom-select {
        height: 31px;
        font-size: 12px;
    }

    #customerPurchaseOrderModal textarea.form-control {
        min-height: 58px;
        height: auto;
        resize: vertical;
    }

    #customerPurchaseOrderModal .form-control:focus,
    #customerPurchaseOrderModal .custom-select:focus {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .12rem rgba(13, 110, 253, .15);
    }

    #customerPurchaseOrderModal .font-weight-600 {
        font-weight: 600;
    }

    #customerPurchaseOrderModal .purchase-order-table-scroll {
        width: 100%;
        overflow-x: auto;
        scrollbar-width: thin;
    }

    #customerPurchaseOrderModal .purchase-order-table-scroll::-webkit-scrollbar {
        height: 8px;
    }

    #customerPurchaseOrderModal .purchase-order-table-scroll::-webkit-scrollbar-thumb {
        border-radius: 20px;
        background: #b8d7ff;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable {
        min-width: 1430px;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable thead th {
        padding: .5rem .35rem;
        border-top: 0;
        border-bottom: 2px solid #dbeafe;
        color: #374151;
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .25px;
        white-space: nowrap;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable tbody td {
        padding: .3rem;
        border-top: 1px solid #eef2f7;
        vertical-align: middle;
        white-space: nowrap;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-article-picker {
        min-width: 290px;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-note {
        min-width: 90px;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable select {
        min-width: 90px;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-presentation-id,
    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-brand-id {
        min-width: 115px;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-origin,
    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-expiration-date {
        min-width: 110px;
    }

    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-quantity,
    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-unit-price,
    #customerPurchaseOrderModal #purchaseOrderItemsTable .item-line-total {
        min-width: 90px;
    }

    #customerPurchaseOrderModal .purchase-order-totals-area {
        padding: 12px 16px;
        border-top: 1px solid #dbeafe;
        background: #fff;
    }

    #customerPurchaseOrderModal .purchase-order-total-line {
        display: grid;
        grid-template-columns: 1fr 155px;
        gap: 10px;
        align-items: center;
        margin-bottom: 7px;
    }

    #customerPurchaseOrderModal .purchase-order-total-line>span {
        color: #495057;
        font-size: 12px;
        font-weight: 700;
        text-align: right;
    }

    #customerPurchaseOrderModal .purchase-order-total-line .input-group-text {
        color: #0d6efd;
        border-color: #dbeafe;
        background: #f8fbff;
        font-size: 11px;
        font-weight: 700;
    }

    #customerPurchaseOrderModal .purchase-order-total-grand>span,
    #customerPurchaseOrderModal .purchase-order-total-grand input {
        color: #0d6efd;
    }

    #customerPurchaseOrderModal .purchase-order-total-grand input {
        border-color: #b8d7ff;
        background: #f4f9ff;
    }

    #customerPurchaseOrderModal .select2-container--bootstrap4 .select2-selection {
        min-height: 31px;
    }

    #customerPurchaseOrderModal .select2-container--bootstrap4 .select2-selection--single {
        height: 31px !important;
        display: flex;
        align-items: center;
    }

    #customerPurchaseOrderModal .select2-container--bootstrap4 .select2-selection__rendered {
        padding-left: 8px !important;
        padding-right: 18px !important;
        font-size: 12px;
        line-height: 29px !important;
    }

    @media (max-width: 991px) {
        #customerPurchaseOrderModal .purchase-order-modal-dialog {
            max-width: 98%;
            margin: .5rem auto;
        }

        #customerPurchaseOrderModal .purchase-order-total-line {
            grid-template-columns: 1fr;
            gap: 3px;
        }

        #customerPurchaseOrderModal .purchase-order-total-line>span {
            text-align: left;
        }
    }

    @media (max-width: 576px) {
        #customerPurchaseOrderModal .purchase-order-modal-dialog {
            max-width: 100%;
            margin: 0;
        }

        #customerPurchaseOrderModal .modal-content {
            min-height: 100vh;
            border-radius: 0;
        }

        #customerPurchaseOrderModal .modal-body {
            max-height: calc(100vh - 60px);
            padding: .5rem !important;
        }

        #customerPurchaseOrderModal .modal-header {
            padding: .65rem .85rem;
        }

        #customerPurchaseOrderModal .purchase-order-header-icon {
            display: none;
        }

        #customerPurchaseOrderModal #purchaseOrderItemsTable {
            min-width: 1160px;
        }
    }
</style>
