<div class="modal fade" id="warehouseEntryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <form id="warehouseEntryForm" class="modal-content border-0 shadow-lg warehouse-entry-modal">
            @csrf
            <input type="hidden" id="warehouse_entry_id" name="warehouse_entry_id">

            <div class="modal-header warehouse-entry-modal-header text-white">
                <div class="warehouse-entry-header-title">
                    <span class="warehouse-entry-header-icon">
                        <i class="fas fa-warehouse"></i>
                    </span>
                    <span>
                        <h5 class="modal-title" id="warehouseEntryModalLabel">
                            Registrar Ingreso de Almac&eacute;n
                        </h5>
                        <small>Registro f&iacute;sico y documental de mercader&iacute;a</small>
                    </span>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body warehouse-entry-modal-body">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="card border-0 shadow-sm h-100 warehouse-entry-side-card">
                            <div class="card-body text-center">
                                <div class="warehouse-entry-side-icon mx-auto mb-3">
                                    <i class="fas fa-dolly"></i>
                                </div>
                                <h5 class="font-weight-bold text-dark mb-1">Ingreso de Almac&eacute;n</h5>
                                <small class="text-muted">Recepci&oacute;n f&iacute;sica y documental</small>
                                <hr>

                                <div class="text-left small">
                                    <small class="text-muted d-block">N&deg; interno</small>
                                    <input type="text" id="warehouse_entry_number" class="form-control form-control-sm mb-2 text-center font-weight-bold"
                                        placeholder="Autom&aacute;tico" readonly>

                                    <small class="text-muted d-block">Fecha de registro</small>
                                    <div class="font-weight-600 mb-2">{{ now()->format('d/m/Y') }}</div>

                                    <small class="text-muted d-block">Estado inicial</small>
                                    <span class="badge badge-primary px-2 py-1 mb-2">Registrado</span>

                                    <small class="text-muted d-block">Proveedor</small>
                                    <div class="font-weight-600 mb-2 text-break" id="warehouseEntrySideSupplier">
                                        Seleccione proveedor
                                    </div>

                                    <small class="text-muted d-block">Almac&eacute;n</small>
                                    <div class="font-weight-600 mb-2" id="warehouseEntrySideWarehouse">
                                        Sin almac&eacute;n
                                    </div>

                                    <small class="text-muted d-block">Total ingreso</small>
                                    <div class="warehouse-entry-side-total mt-1">
                                        <span class="warehouse-entry-currency-symbol">S/</span>
                                        <span id="warehouseEntrySideGrandTotal">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm mb-3 warehouse-entry-card">
                            <div class="card-header border-0 py-2 warehouse-entry-section-header">
                                <h6 class="mb-0 font-weight-bold text-dark">
                                    <i class="fas fa-file-alt text-info mr-1"></i>
                                    Datos del ingreso
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="form-group col-md-6">
                                        <label>ORDEN DE COMPRA A PROVEEDOR</label>
                                        <select id="warehouse_entry_supplier_purchase_order_id"
                                            name="supplier_purchase_order_id"
                                            class="form-control form-control-sm js-warehouse-entry-select">
                                            <option value="">Seleccione orden</option>
                                            @foreach ($supplierPurchaseOrders as $order)
                                                <option value="{{ $order->id }}"
                                                    data-code="{{ $order->code }}"
                                                    data-company-id="{{ $order->company_id }}"
                                                    data-supplier-id="{{ $order->supplier_id }}"
                                                    data-currency-id="{{ $order->currency_id }}">
                                                    {{ $order->code }} | {{ $order->supplier?->short_name ?? $order->supplier?->business_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>ALMAC&Eacute;N</label>
                                        <select id="warehouse_entry_warehouse_id" name="warehouse_id"
                                            class="form-control form-control-sm js-warehouse-entry-select">
                                            <option value="">Seleccione almac&eacute;n</option>
                                            @foreach ($warehouses as $warehouse)
                                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>MONEDA</label>
                                        <select id="warehouse_entry_currency_id" name="currency_id"
                                            class="form-control form-control-sm js-warehouse-entry-select" required>
                                            <option value="">Seleccione</option>
                                            @foreach ($currencies as $currency)
                                                <option value="{{ $currency->id }}" data-symbol="{{ $currency->symbol ?? $currency->code }}">
                                                    {{ $currency->code }} - {{ $currency->description }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>EMPRESA</label>
                                        <select id="warehouse_entry_company_id" name="company_id"
                                            class="form-control form-control-sm js-warehouse-entry-select" required>
                                            <option value="">Seleccione empresa</option>
                                            @foreach ($companies as $company)
                                                <option value="{{ $company->id }}">{{ $company->trade_name ?? $company->business_name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>PROVEEDOR</label>
                                        <select id="warehouse_entry_supplier_id" name="supplier_id"
                                            class="form-control form-control-sm js-warehouse-entry-select" required>
                                            <option value="">Seleccione proveedor</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}">{{ $supplier->short_name ?? $supplier->business_name }}</option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>CLIENTE</label>
                                        <select id="warehouse_entry_customer_id" name="customer_id"
                                            class="form-control form-control-sm js-warehouse-entry-select">
                                            <option value="">Sin cliente</option>
                                            @foreach ($customers as $customer)
                                                <option value="{{ $customer->id }}">
                                                    {{ $customer->business_name ?? $customer->full_name ?? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>FORMA DE PAGO</label>
                                        <input type="text" id="warehouse_entry_payment_method" name="payment_method"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>CONDICI&Oacute;N DE PAGO</label>
                                        <input type="text" id="warehouse_entry_payment_condition" name="payment_condition"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>TIPO DOCUMENTO</label>
                                        <input type="text" id="warehouse_entry_document_type" name="document_type"
                                            class="form-control form-control-sm text-uppercase" placeholder="FACTURA">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>FECHA DOCUMENTO</label>
                                        <input type="date" id="warehouse_entry_document_date" name="document_date"
                                            class="form-control form-control-sm">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-2">
                                        <label>SERIE</label>
                                        <input type="text" id="warehouse_entry_document_series" name="document_series"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>N&deg; COMPROBANTE</label>
                                        <input type="text" id="warehouse_entry_document_number" name="document_number"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>NRO ORDEN COMPRA</label>
                                        <input type="text" id="warehouse_entry_purchase_order_number"
                                            name="purchase_order_number" class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-2">
                                        <label>AFECTO IGV</label>
                                        <select id="warehouse_entry_affect_igv" name="affect_igv"
                                            class="form-control form-control-sm">
                                            <option value="1">S&iacute;</option>
                                            <option value="0">No</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>VENDEDOR</label>
                                        <input type="text" id="warehouse_entry_seller_name" name="seller_name"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>CUENTA POR PAGAR</label>
                                        <select id="warehouse_entry_generate_account_payable" name="generate_account_payable"
                                            class="form-control form-control-sm">
                                            <option value="0">No</option>
                                            <option value="1">S&iacute;</option>
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>MONTO</label>
                                        <input type="number" step="0.01" min="0" id="warehouse_entry_payable_amount"
                                            name="payable_amount" class="form-control form-control-sm text-right" value="0.00">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>FECHA PAGO ESPERADA</label>
                                        <input type="date" id="warehouse_entry_expected_payment_date"
                                            name="expected_payment_date" class="form-control form-control-sm">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>SERIE GU&Iacute;A</label>
                                        <input type="text" id="warehouse_entry_guide_series" name="guide_series"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>N&deg; GU&Iacute;A</label>
                                        <input type="text" id="warehouse_entry_guide_number" name="guide_number"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>RUC GU&Iacute;A</label>
                                        <input type="text" id="warehouse_entry_guide_ruc" name="guide_ruc"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-12">
                                        <label>OBSERVACIONES</label>
                                        <textarea id="warehouse_entry_observations" name="observations"
                                            class="form-control form-control-sm text-uppercase" rows="2"></textarea>
                                        <span class="invalid-feedback"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm warehouse-entry-card">
                            <div class="card-header border-0 py-2 px-3 warehouse-entry-section-header">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div>
                                        <h6 class="mb-0 font-weight-bold text-dark">
                                            <i class="fas fa-boxes text-info mr-1"></i>
                                            Art&iacute;culos ingresados
                                        </h6>
                                        <small class="text-muted">Cantidades f&iacute;sicas recibidas</small>
                                    </div>

                                    <div class="mt-2 mt-md-0">
                                        <button type="button" id="btnLoadWarehouseEntrySource"
                                            class="btn btn-outline-info btn-sm mr-2">
                                            <i class="fas fa-download mr-1"></i>
                                            Cargar desde orden
                                        </button>
                                        <button type="button" id="btnAddWarehouseEntryItem"
                                            class="btn btn-info btn-sm">
                                            <i class="fas fa-plus mr-1"></i>
                                            Insertar art&iacute;culo manual
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="warehouse-entry-table-scroll">
                                <table class="table table-sm table-hover mb-0 warehouse-entry-items-table">
                                    <thead class="bg-light text-center">
                                        <tr>
                                            <th>#</th>
                                            <th>ART&Iacute;CULO</th>
                                            <th>NOTA</th>
                                            <th>U.M.</th>
                                            <th>PRESENT.</th>
                                            <th>MARCA</th>
                                            <th>PROCEDENCIA</th>
                                            <th>C. COSTEO</th>
                                            <th>F. VENC.</th>
                                            <th>LOTE</th>
                                            <th>CANT. ORDENADA</th>
                                            <th>CANT. INGRESO</th>
                                            <th>PRECIO</th>
                                            <th>P. TOTAL</th>
                                            <th>ACCI&Oacute;N</th>
                                        </tr>
                                    </thead>
                                    <tbody id="warehouseEntryItemsTbody">
                                        <tr id="warehouseEntryItemsEmptyRow">
                                            <td colspan="15" class="text-center text-muted py-4">
                                                <i class="fas fa-box-open d-block mb-2"></i>
                                                Carga una orden o inserta art&iacute;culos para registrar el ingreso.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-3 bg-white border-top">
                                <div class="row justify-content-end">
                                    <div class="col-lg-5 col-xl-4">
                                        <div class="warehouse-entry-total-line">
                                            <span>Subtotal</span>
                                            <input type="text" id="warehouse_entry_subtotal"
                                                class="form-control form-control-sm text-right" value="0.00" readonly>
                                        </div>
                                        <div class="warehouse-entry-total-line">
                                            <span>Total I.G.V.</span>
                                            <input type="text" id="warehouse_entry_igv"
                                                class="form-control form-control-sm text-right" value="0.00" readonly>
                                        </div>
                                        <div class="warehouse-entry-total-line font-weight-bold">
                                            <span>Total ingreso</span>
                                            <input type="text" id="warehouse_entry_grand_total"
                                                class="form-control form-control-sm text-right font-weight-bold" value="0.00" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template id="warehouseEntryItemRowTemplate">
                        <tr class="warehouse-entry-item-row">
                            <td class="warehouse-entry-item-index align-middle"></td>
                            <td>
                                <input type="hidden" name="items[__INDEX__][supplier_purchase_order_item_id]"
                                    class="item-supplier-purchase-order-item-id">
                                <input type="hidden" name="items[__INDEX__][article_id]" class="item-article-id">
                                <input type="hidden" name="items[__INDEX__][article_code]" class="item-article-code">
                                <input type="hidden" name="items[__INDEX__][billing_name_snapshot]" class="item-billing-name">
                                <select class="form-control form-control-sm item-article-picker js-warehouse-entry-row-select">
                                    <option value="">Seleccione art&iacute;culo</option>
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
                            <td><input type="text" name="items[__INDEX__][note]" class="form-control form-control-sm item-note text-uppercase"></td>
                            <td>
                                <select name="items[__INDEX__][unit_id]" class="form-control form-control-sm item-unit-id js-warehouse-entry-row-select">
                                    <option value="">-</option>
                                    @foreach ($units as $unit)
                                        <option value="{{ $unit->id }}">{{ $unit->description }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="items[__INDEX__][presentation_id]" class="form-control form-control-sm item-presentation-id js-warehouse-entry-row-select">
                                    <option value="">-</option>
                                    @foreach ($presentations as $presentation)
                                        <option value="{{ $presentation->id }}">{{ $presentation->description }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="items[__INDEX__][brand_id]" class="form-control form-control-sm item-brand-id js-warehouse-entry-row-select">
                                    <option value="">-</option>
                                    @foreach ($brands as $brand)
                                        <option value="{{ $brand->id }}">{{ $brand->description }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" name="items[__INDEX__][origin]" class="form-control form-control-sm item-origin text-uppercase"></td>
                            <td><input type="text" name="items[__INDEX__][cost_type]" class="form-control form-control-sm item-cost-type text-uppercase" value="PESO"></td>
                            <td><input type="date" name="items[__INDEX__][expiration_date]" class="form-control form-control-sm item-expiration-date"></td>
                            <td><input type="text" name="items[__INDEX__][lot_number]" class="form-control form-control-sm item-lot-number text-uppercase"></td>
                            <td><input type="number" step="0.01" min="0" name="items[__INDEX__][ordered_quantity]" class="form-control form-control-sm text-right item-ordered-quantity" value="0.00" readonly></td>
                            <td><input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]" class="form-control form-control-sm text-right item-quantity" value="1.00"></td>
                            <td><input type="number" step="0.01" min="0" name="items[__INDEX__][unit_price]" class="form-control form-control-sm text-right item-unit-price" value="0.00"></td>
                            <td class="text-right font-weight-bold item-line-total">0.00</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-sm btnRemoveWarehouseEntryItem">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </div>
            </div>

            <div class="modal-footer warehouse-entry-modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>
                <button type="submit" class="btn btn-info btn-sm">
                    <i class="fas fa-save mr-1"></i>
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>
