<div class="modal fade" id="electronicInvoiceModal" tabindex="-1" role="dialog"
    aria-labelledby="electronicInvoiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered invoice-modal-dialog" role="document">
        <form id="electronicInvoiceForm" class="modal-content border-0 shadow electronic-invoice-modal">
            @csrf
            <input type="hidden" id="electronic_invoice_id">
            <input type="hidden" id="ei_requested_status" name="requested_status" value="draft">

            <div class="modal-header border-0 text-white invoice-modal-header">
                <div class="d-flex align-items-center">
                    <span class="invoice-header-icon mr-3">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </span>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" id="electronicInvoiceModalLabel">
                            Nuevo Comprobante
                        </h5>
                        <small class="text-white-50">Documento local preliminar, listo para futura integraci&oacute;n APIs Per&uacute;</small>
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body invoice-modal-body">
                <div id="electronicInvoiceErrors" class="alert alert-danger d-none mb-2"></div>

                <div class="invoice-shell">
                    <aside class="invoice-summary-card">
                        <div class="invoice-summary-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <span id="ei_summary_status" class="badge badge-light text-success border px-3 py-2 mb-3">GENERADO LOCAL</span>

                        <div class="invoice-summary-block">
                            <small>Tipo</small>
                            <strong id="ei_summary_type">Factura</strong>
                        </div>
                        <div class="invoice-summary-block">
                            <small>N&uacute;mero</small>
                            <strong id="ei_summary_number">-</strong>
                        </div>
                        <div class="invoice-summary-block">
                            <small>Cliente</small>
                            <strong id="ei_summary_customer">Seleccione cliente</strong>
                        </div>
                        <div class="invoice-summary-block">
                            <small>Fecha emisi&oacute;n</small>
                            <strong id="ei_summary_issue_date">-</strong>
                        </div>

                        <div class="invoice-summary-total">
                            <small>Total</small>
                            <strong id="ei_summary_total">0.00</strong>
                        </div>
                    </aside>

                    <main class="invoice-main-panel">
                        <section class="invoice-section">
                            <div class="invoice-section-header">
                                <span><i class="fas fa-receipt"></i> Datos del comprobante</span>
                            </div>
                            <div class="row compact-row">
                                <div class="form-group col-xl-3 col-md-6">
                                    <label>Empresa</label>
                                    <select id="ei_company_id" name="company_id" class="form-control form-control-sm invoice-compact-input" required>
                                        <option value="">Seleccione</option>
                                        @foreach ($companies as $company)
                                            <option value="{{ $company->id }}"
                                                data-ruc="{{ $company->ruc }}"
                                                data-business-name="{{ $company->business_name }}"
                                                data-trade-name="{{ $company->trade_name }}"
                                                data-address="{{ $company->address }}">
                                                {{ $company->trade_name ?? $company->business_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="form-group col-xl-2 col-md-3 col-6">
                                    <label>Tipo documento</label>
                                    <select id="ei_document_type" name="document_type" class="form-control form-control-sm invoice-compact-input" required>
                                        <option value="01">Factura</option>
                                        <option value="03">Boleta</option>
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="form-group col-xl-2 col-md-3 col-6">
                                    <label>Serie</label>
                                    <select id="ei_serie_id" name="serie_id" class="form-control form-control-sm invoice-compact-input" required>
                                        <option value="">Seleccione</option>
                                        @foreach ($series as $serie)
                                            <option value="{{ $serie->id }}"
                                                data-company-id="{{ $serie->company_id }}"
                                                data-document-type="{{ $serie->document_type }}"
                                                data-serie="{{ $serie->serie }}"
                                                data-next-number="{{ str_pad((string) $serie->next_number, 8, '0', STR_PAD_LEFT) }}">
                                                {{ $serie->serie }} | {{ $serie->environment }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="form-group col-xl-2 col-md-3 col-6">
                                    <label>Correlativo</label>
                                    <input id="ei_correlativo_preview" type="text" class="form-control form-control-sm invoice-compact-input" readonly>
                                </div>
                                <div class="form-group col-xl-3 col-md-3 col-6">
                                    <label>Moneda</label>
                                    <select id="ei_currency_id" name="currency_id" class="form-control form-control-sm invoice-compact-input" required>
                                        <option value="">Seleccione</option>
                                        @foreach ($currencies as $currency)
                                            <option value="{{ $currency->id }}" data-code="{{ $currency->code }}" data-symbol="{{ $currency->symbol }}">
                                                {{ $currency->code }} | {{ $currency->description }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="form-group col-xl-2 col-md-3 col-6">
                                    <label>F. emisi&oacute;n</label>
                                    <input id="ei_issue_date" name="issue_date" type="date" class="form-control form-control-sm invoice-compact-input" required>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="form-group col-xl-2 col-md-3 col-6">
                                    <label>F. vencimiento</label>
                                    <input id="ei_due_date" name="due_date" type="date" class="form-control form-control-sm invoice-compact-input">
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="form-group col-xl-2 col-md-3 col-6">
                                    <label>Forma pago</label>
                                    <select id="ei_payment_type" name="payment_type" class="form-control form-control-sm invoice-compact-input">
                                        <option value="Contado">Contado</option>
                                        <option value="Credito">Cr&eacute;dito</option>
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="form-group col-xl-3 col-md-3 col-6">
                                    <label>Condici&oacute;n pago</label>
                                    <input id="ei_payment_condition" name="payment_condition" type="text" class="form-control form-control-sm invoice-compact-input text-uppercase">
                                </div>
                                <div class="form-group col-xl-3 col-md-12">
                                    <label>Observaci&oacute;n breve</label>
                                    <textarea id="ei_observations" name="observations" class="form-control form-control-sm invoice-compact-input text-uppercase" rows="1"></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="invoice-section">
                            <div class="invoice-section-header">
                                <span><i class="fas fa-user-tie"></i> Cliente</span>
                            </div>
                            <div class="row compact-row">
                                <div class="form-group col-lg-4 col-md-6">
                                    <label>Cliente</label>
                                    <select id="ei_customer_id" name="customer_id" class="form-control form-control-sm invoice-compact-input" required>
                                        <option value="">Seleccione</option>
                                        @foreach ($customers as $customer)
                                            <option value="{{ $customer->id }}"
                                                data-document-type="{{ $customer->document_type }}"
                                                data-document-number="{{ $customer->ruc ?? $customer->document_number }}"
                                                data-name="{{ $customer->business_name ?? $customer->full_name ?? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) }}"
                                                data-address="{{ $customer->address }}"
                                                data-email="{{ $customer->email }}"
                                                data-phone="{{ $customer->phone }}">
                                                {{ $customer->business_name ?? $customer->full_name ?? $customer->document_number }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="invalid-feedback"></span>
                                </div>
                                <div class="form-group col-lg-2 col-md-3 col-6">
                                    <label>RUC/DNI</label>
                                    <input id="ei_client_document" type="text" class="form-control form-control-sm invoice-compact-input" readonly>
                                </div>
                                <div class="form-group col-lg-3 col-md-5 col-6">
                                    <label>Raz&oacute;n social</label>
                                    <input id="ei_client_name" type="text" class="form-control form-control-sm invoice-compact-input" readonly>
                                </div>
                                <div class="form-group col-lg-3 col-md-4">
                                    <label>Email</label>
                                    <input id="ei_client_email" type="text" class="form-control form-control-sm invoice-compact-input" readonly>
                                </div>
                                <div class="form-group col-lg-4 col-md-6">
                                    <label>Sucursal / direcci&oacute;n</label>
                                    <select id="ei_customer_branch_id" name="customer_branch_id"
                                        class="form-control form-control-sm invoice-compact-input">
                                        <option value="">Direcci&oacute;n principal</option>
                                        @foreach ($customerBranches as $branch)
                                            <option value="{{ $branch->id }}" data-customer-id="{{ $branch->customer_id }}"
                                                data-address="{{ $branch->address }}">
                                                {{ $branch->branch_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-lg-8 col-md-6">
                                    <label>Direcci&oacute;n</label>
                                    <input id="ei_client_address" type="text" class="form-control form-control-sm invoice-compact-input" readonly>
                                </div>
                            </div>
                        </section>

                        <section class="invoice-section">
                            <div class="invoice-section-header">
                                <span><i class="fas fa-folder-open"></i> Documento origen</span>
                            </div>
                            <div class="row compact-row">
                                <div class="form-group col-lg-3 col-md-6">
                                    <label>Orden compra cliente</label>
                                    <select id="ei_customer_purchase_order_id" name="customer_purchase_order_id" class="form-control form-control-sm invoice-compact-input">
                                        <option value="">Sin orden</option>
                                        @foreach ($customerPurchaseOrders as $order)
                                            <option value="{{ $order->id }}"
                                                data-code="{{ $order->code }}"
                                                data-purchase-order-number="{{ $order->purchase_order_number }}"
                                                data-quote-id="{{ $order->quote_id }}"
                                                data-customer-id="{{ $order->customer_id }}"
                                                data-siaf="{{ $order->siaf_file_number }}"
                                                data-process="{{ $order->process_type }}">
                                                {{ $order->code }} {{ $order->purchase_order_number ? '| ' . $order->purchase_order_number : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-lg-3 col-md-6">
                                    <label>Cotizaci&oacute;n</label>
                                    <select id="ei_quote_id" name="quote_id" class="form-control form-control-sm invoice-compact-input">
                                        <option value="">Sin cotizaci&oacute;n</option>
                                        @foreach ($quotes as $quote)
                                            <option value="{{ $quote->id }}" data-customer-id="{{ $quote->customer_id }}">{{ $quote->quote_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-lg-3 col-md-6">
                                    <label>Ingreso almac&eacute;n</label>
                                    <select id="ei_warehouse_entry_id" name="warehouse_entry_id" class="form-control form-control-sm invoice-compact-input">
                                        <option value="">Sin ingreso</option>
                                        @foreach ($warehouseEntries as $entry)
                                            <option value="{{ $entry->id }}">{{ $entry->entry_number }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-lg-3 col-md-6">
                                    <label>Nro OC cliente</label>
                                    <input id="ei_purchase_order_number" name="purchase_order_number" type="text" class="form-control form-control-sm invoice-compact-input text-uppercase">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Expediente SIAF</label>
                                    <input id="ei_siaf_number" name="siaf_number" type="text" class="form-control form-control-sm invoice-compact-input text-uppercase">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Proceso</label>
                                    <input id="ei_process_number" name="process_number" type="text" class="form-control form-control-sm invoice-compact-input text-uppercase">
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Contrato</label>
                                    <input id="ei_contract_number" name="contract_number" type="text" class="form-control form-control-sm invoice-compact-input text-uppercase">
                                </div>
                            </div>
                        </section>

                        <section class="invoice-section invoice-items-section">
                            <div class="invoice-section-header">
                                <span><i class="fas fa-boxes"></i> Art&iacute;culos</span>
                                <button type="button" id="btnAddElectronicInvoiceItem" class="btn btn-sm btn-outline-success invoice-add-btn">
                                    <i class="fas fa-plus"></i> Agregar
                                </button>
                            </div>
                            <div class="invoice-items-scroll">
                                <table class="table table-sm table-bordered electronic-invoice-items-table mb-0">
                                    <thead>
                                        <tr>
                                            <th>Art&iacute;culo</th>
                                            <th>C&oacute;digo</th>
                                            <th>Lote</th>
                                            <th>F. Venc.</th>
                                            <th>Marca</th>
                                            <th>Present.</th>
                                            <th>Proced.</th>
                                            <th>U.M.</th>
                                            <th>Cant.</th>
                                            <th>Precio</th>
                                            <th>Afect. IGV</th>
                                            <th>IGV</th>
                                            <th>Total</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="electronicInvoiceItemsTbody"></tbody>
                                </table>
                            </div>
                        </section>

                        <section class="invoice-bottom-grid">
                            <div class="invoice-total-card">
                                <div class="invoice-section-header mb-2">
                                    <span><i class="fas fa-calculator"></i> Totales</span>
                                </div>
                                <div class="invoice-total-line"><span>Gravada</span><strong id="ei_taxable_amount">0.00</strong></div>
                                <div class="invoice-total-line"><span>Exonerada</span><strong id="ei_exonerated_amount">0.00</strong></div>
                                <div class="invoice-total-line"><span>Inafecta</span><strong id="ei_unaffected_amount">0.00</strong></div>
                                <div class="invoice-total-line"><span>IGV</span><strong id="ei_igv_amount">0.00</strong></div>
                                <div class="invoice-grand-total"><span>Total</span><strong id="ei_total_amount">0.00</strong></div>
                            </div>

                            <div id="electronicInvoicePaymentsBox" class="invoice-section d-none mb-0">
                                <div class="invoice-section-header">
                                    <span><i class="fas fa-calendar-alt"></i> Cuotas</span>
                                    <button type="button" id="btnAddElectronicInvoicePayment" class="btn btn-xs btn-outline-success">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div id="electronicInvoicePaymentsList"></div>
                            </div>

                            <div class="invoice-section mb-0">
                                <div class="invoice-section-header">
                                    <span><i class="fas fa-quote-left"></i> Leyendas</span>
                                </div>
                                <small class="text-muted d-block mb-2">Monto en letras autom&aacute;tico al guardar.</small>
                                <textarea name="legends[0][value]" class="form-control form-control-sm invoice-compact-input text-uppercase" rows="2"
                                    placeholder="Leyenda adicional"></textarea>
                                <input type="hidden" name="legends[0][code]" value="9999">
                            </div>
                        </section>
                    </main>
                </div>
            </div>

            <div class="modal-footer sticky-modal-footer">
                <button type="button" class="btn btn-light border px-4" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cancelar
                </button>
                <button type="submit" id="btnSaveElectronicInvoiceDraft" class="btn btn-outline-secondary px-4"
                    data-status="draft">
                    <i class="fas fa-pencil-alt mr-1"></i> Guardar borrador
                </button>
                <button type="submit" id="btnGenerateElectronicInvoice" class="btn btn-success px-4"
                    data-status="generated">
                    <i class="fas fa-check-circle mr-1"></i> Generar interno
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/template" id="electronicInvoiceItemRowTemplate">
    <tr class="electronic-invoice-item-row">
        <td class="invoice-article-cell">
            <select name="items[__INDEX__][article_id]" class="form-control form-control-sm item-article">
                <option value="">Manual</option>
                @foreach ($articles as $article)
                    <option value="{{ $article->id }}"
                        data-code="{{ $article->code }}"
                        data-name="{{ $article->billing_name }}"
                        data-commercial-name="{{ $article->commercial_name }}">
                        {{ $article->code }} | {{ $article->billing_name }}
                    </option>
                @endforeach
            </select>
            <input name="items[__INDEX__][description]" class="form-control form-control-sm item-description mt-1 text-uppercase" placeholder="Descripcion" required>
        </td>
        <td><input name="items[__INDEX__][product_code]" class="form-control form-control-sm item-code text-uppercase"></td>
        <td><input name="items[__INDEX__][lot_number]" class="form-control form-control-sm text-uppercase"></td>
        <td><input name="items[__INDEX__][expiration_date]" type="date" class="form-control form-control-sm"></td>
        <td><input name="items[__INDEX__][brand_name]" class="form-control form-control-sm text-uppercase"></td>
        <td><input name="items[__INDEX__][presentation_name]" class="form-control form-control-sm text-uppercase"></td>
        <td><input name="items[__INDEX__][origin]" class="form-control form-control-sm text-uppercase"></td>
        <td><input name="items[__INDEX__][unit_code]" class="form-control form-control-sm text-uppercase" value="NIU"></td>
        <td><input name="items[__INDEX__][quantity]" type="number" min="0.0000000001" step="0.0000000001" class="form-control form-control-sm item-quantity" value="1"></td>
        <td><input name="items[__INDEX__][unit_price]" type="number" min="0" step="0.0000000001" class="form-control form-control-sm item-price" value="0"></td>
        <td>
            <select name="items[__INDEX__][tax_affectation_code]" class="form-control form-control-sm item-tax-affectation">
                @foreach ($taxAffectations as $tax)
                    <option value="{{ $tax->item_code }}">{{ $tax->item_code }} | {{ $tax->short_name ?? $tax->description }}</option>
                @endforeach
            </select>
        </td>
        <td class="text-right item-igv">0.00</td>
        <td class="text-right font-weight-bold item-total">0.00</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger removeElectronicInvoiceItem">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    </tr>
</script>
