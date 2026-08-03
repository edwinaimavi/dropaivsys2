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
                            Nuevo Ingreso de Almac&eacute;n
                        </h5>
                        <small>Registro f&iacute;sico y documental de mercader&iacute;a ingresada</small>
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

                                    <small class="text-muted d-block">Empresa</small>
                                    <div class="font-weight-600 mb-2 text-break" id="warehouseEntrySideCompany">Seleccione empresa</div>

                                    <small class="text-muted d-block">Almac&eacute;n</small>
                                    <div class="font-weight-600 mb-2" id="warehouseEntrySideWarehouse">
                                        Sin almac&eacute;n
                                    </div>

                                    <small class="text-muted d-block">Total ingreso</small>
                                    <div class="warehouse-entry-side-total mt-1">
                                        <span class="warehouse-entry-currency-symbol">S/</span>
                                        <span id="warehouseEntrySideGrandTotal">0.00</span>
                                    </div>

                                    <div class="warehouse-entry-side-metrics mt-3">
                                        <div><strong id="warehouseEntrySideItemCount">0</strong><small>Art&iacute;culos</small></div>
                                        <div><strong id="warehouseEntrySideDocumentCount">0</strong><small>Documentos</small></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9 warehouse-entry-tabs-column">
                        <nav class="warehouse-entry-form-tabs" aria-label="Secciones del ingreso">
                            <div class="nav nav-pills flex-nowrap" role="tablist">
                                <a class="nav-link active" data-toggle="pill" href="#warehouse_entry_tab_data"><i class="fas fa-clipboard-list"></i><span>Datos del ingreso</span></a>
                                <a class="nav-link" data-toggle="pill" href="#warehouse_entry_tab_items"><i class="fas fa-boxes"></i><span>Art&iacute;culos y lotes</span></a>
                                @canany(['admin.warehouse-entries.expenses.store', 'admin.warehouse-entries.expenses.update'])
                                <a class="nav-link" data-toggle="pill" href="#warehouse_entry_tab_expenses"><i class="fas fa-truck-loading"></i><span>Costos vinculados</span></a>
                                @endcanany
                                <a class="nav-link" data-toggle="pill" href="#warehouse_entry_tab_documents"><i class="fas fa-paperclip"></i><span>Documentos adjuntos</span></a>
                                <a class="nav-link" data-toggle="pill" href="#warehouse_entry_tab_summary"><i class="fas fa-check-circle"></i><span>Resumen</span></a>
                            </div>
                        </nav>
                        <div class="tab-content warehouse-entry-form-tab-content">
                            <div class="tab-pane fade show active" id="warehouse_entry_tab_data"></div>
                            <div class="tab-pane fade" id="warehouse_entry_tab_items"></div>
                            @canany(['admin.warehouse-entries.expenses.store', 'admin.warehouse-entries.expenses.update'])
                            <div class="tab-pane fade" id="warehouse_entry_tab_expenses"></div>
                            @endcanany
                            <div class="tab-pane fade" id="warehouse_entry_tab_documents"></div>
                            <div class="tab-pane fade" id="warehouse_entry_tab_summary"><div id="warehouseEntryReview"></div></div>
                        </div>
                        <div id="warehouseEntryOriginalDataCard" class="card border-0 shadow-sm mb-3 warehouse-entry-card">
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
                                            class="form-control form-control-sm js-warehouse-entry-select" required>
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
                                        <input type="hidden" id="warehouse_entry_supplier_id_hidden" name="supplier_id" disabled>
                                        <select id="warehouse_entry_supplier_id" name="supplier_id"
                                            class="form-control form-control-sm js-warehouse-entry-select" required>
                                            <option value="">Seleccione proveedor</option>
                                            @foreach ($suppliers as $supplier)
                                                <option value="{{ $supplier->id }}" data-ruc="{{ $supplier->ruc }}">
                                                    {{ $supplier->short_name ?? $supplier->business_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>RUC PROVEEDOR</label>
                                        <input type="text" id="warehouse_entry_supplier_ruc"
                                            class="form-control form-control-sm" readonly>
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
                                        <select id="warehouse_entry_document_type" name="document_type"
                                            class="form-control form-control-sm text-uppercase" required>
                                            <option value="FACTURA">FACTURA</option>
                                            <option value="BOLETA">BOLETA</option>
                                        </select>
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
                                            name="payable_amount" class="form-control form-control-sm text-right" value="0.00" readonly>
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-3">
                                        <label>FECHA PAGO ESPERADA</label>
                                        <input type="date" id="warehouse_entry_expected_payment_date"
                                            name="expected_payment_date" class="form-control form-control-sm">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>SERIE GU&Iacute;A</label>
                                        <input type="text" id="warehouse_entry_guide_series" name="guide_series"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>N&deg; GU&Iacute;A</label>
                                        <input type="text" id="warehouse_entry_guide_number" name="guide_number"
                                            class="form-control form-control-sm text-uppercase">
                                        <span class="invalid-feedback"></span>
                                    </div>

                                    <div class="form-group col-md-4">
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
                        <div id="warehouseEntryOriginalItemsCard" class="card border-0 shadow-sm warehouse-entry-card">
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
                                            <th>LOTES</th>
                                            <th>CANT. ORDENADA</th>
                                            <th>CANT. INGRESO</th>
                                            <th>PRECIO</th>
                                            <th>P. TOTAL</th>
                                            <th>ACCI&Oacute;N</th>
                                        </tr>
                                    </thead>
                                    <tbody id="warehouseEntryItemsTbody">
                                        <tr id="warehouseEntryItemsEmptyRow">
                                            <td colspan="14" class="text-center text-muted py-4">
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

                    <div class="col-12 mt-3">
                        @canany(['admin.warehouse-entries.expenses.store', 'admin.warehouse-entries.expenses.update'])
                        <input type="hidden" name="expense_management" value="1">
                        <div id="warehouseEntryOriginalExpensesCard" class="card border-0 shadow-sm warehouse-entry-card" data-can-destroy="{{ auth()->user()->can('admin.warehouse-entries.expenses.destroy') ? 1 : 0 }}">
                            <div class="card-header border-0 py-2 warehouse-entry-section-header"><h6 class="mb-0 font-weight-bold"><i class="fas fa-truck-loading text-info mr-1"></i>Costos vinculados</h6><small class="text-muted">Separe el transporte de otros costos y evite duplicar importes ya incluidos en la compra.</small></div>
                            <div class="card-body warehouse-entry-expense-body">
                                <div class="row warehouse-entry-expense-kpis">
                                    <div class="col-6 col-xl-3"><div class="warehouse-entry-expense-kpi"><i class="fas fa-truck"></i><span>Flete / transporte<strong id="warehouseEntryFreightTotal">0.00</strong></span></div></div>
                                    <div class="col-6 col-xl-3"><div class="warehouse-entry-expense-kpi"><i class="fas fa-receipt"></i><span>Otros gastos<strong id="warehouseEntryOtherExpenseTotal">0.00</strong></span></div></div>
                                    <div class="col-6 col-xl-3"><div class="warehouse-entry-expense-kpi"><i class="fas fa-boxes"></i><span>Afecta inventario<strong id="warehouseEntryExpenseInventoryTotal">0.00</strong></span></div></div>
                                    <div class="col-6 col-xl-3"><div class="warehouse-entry-expense-kpi"><i class="fas fa-info-circle"></i><span>Total informativo<strong id="warehouseEntryExpenseInformativeTotal">0.00</strong></span></div></div>
                                </div>
                                <div class="warehouse-entry-expense-form">
                                <h6 class="font-weight-bold mb-3"><i class="fas fa-plus-circle text-info mr-1"></i>Registrar costo vinculado</h6>
                                <div class="row">
                                    <input type="hidden" id="warehouse_entry_expense_edit_index">
                                    <div class="form-group col-md-3"><label>CATEGOR&Iacute;A *</label><select id="warehouse_entry_expense_category" class="form-control form-control-sm"><option value="freight_transport">Flete / Transporte</option><option value="other_expense">Otro gasto adicional</option></select></div>
                                    <div class="form-group col-md-6"><label>ORIGEN DEL COSTO *</label><select id="warehouse_entry_expense_cost_origin" class="form-control form-control-sm"><option value="third_party">Cobrado por tercero / agencia externa</option><option value="same_purchase_document">Cobrado en el mismo comprobante de compra</option><option value="included_in_purchase_price">Incluido en el precio de compra</option><option value="internal_without_document">Gasto interno sin comprobante</option></select></div>
                                    <div class="form-group col-md-3"><label>TIPO DE COSTO *</label><select id="warehouse_entry_expense_type" class="form-control form-control-sm"></select></div>
                                    <div class="form-group col-md-4"><label>AGENCIA / PROVEEDOR DEL COSTO</label><select id="warehouse_entry_expense_provider_id" class="form-control form-control-sm"><option value="">Sin proveedor registrado</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" data-ruc="{{ $supplier->ruc }}">{{ $supplier->short_name ?? $supplier->business_name }}</option>@endforeach</select></div>
                                    <div class="form-group col-md-3"><label>NOMBRE DEL PROVEEDOR O RESPONSABLE</label><input id="warehouse_entry_expense_provider_name" class="form-control form-control-sm text-uppercase" placeholder="Nombre libre"></div>
                                    <div class="form-group col-md-2"><label>RUC / DNI</label><input id="warehouse_entry_expense_provider_ruc" class="form-control form-control-sm text-uppercase"></div>
                                    <div class="form-group col-md-2"><label>IMPORTE *</label><input type="number" min="0" step="0.01" id="warehouse_entry_expense_amount" class="form-control form-control-sm text-right"></div>
                                    <div class="form-group col-md-2"><label>TIPO DOCUMENTO</label><input id="warehouse_entry_expense_document_type" class="form-control form-control-sm text-uppercase" placeholder="FACTURA"></div>
                                    <div class="form-group col-md-2"><label>SERIE</label><input id="warehouse_entry_expense_document_series" class="form-control form-control-sm text-uppercase"></div>
                                    <div class="form-group col-md-2"><label>NÚMERO</label><input id="warehouse_entry_expense_document_number" class="form-control form-control-sm text-uppercase"></div>
                                    <div class="form-group col-md-2"><label>FECHA</label><input type="date" id="warehouse_entry_expense_document_date" class="form-control form-control-sm"></div>
                                    <div class="form-group col-md-3"><label>AFECTA COSTO DE INVENTARIO</label><select id="warehouse_entry_expense_affects_cost" class="form-control form-control-sm"><option value="0">No</option><option value="1">Sí</option></select></div>
                                    <div class="form-group col-md-2"><label>DISTRIBUCIÓN</label><select id="warehouse_entry_expense_distribution_method" class="form-control form-control-sm" disabled><option value="">Seleccione</option><option value="quantity">Por cantidad</option><option value="amount">Por valor</option><option value="weight">Por peso</option><option value="manual">Manual</option></select></div>
                                    <div class="form-group col-lg-6"><label>DESCRIPCIÓN / OBSERVACIÓN</label><input id="warehouse_entry_expense_description" class="form-control form-control-sm" placeholder="Detalle del servicio o costo vinculado"></div>
                                    <div class="form-group col-lg-6">
                                        <label>COMPROBANTE ADJUNTO</label>
                                        <div id="warehouseEntryExpenseFilePicker" class="warehouse-entry-expense-file-picker">
                                            <input type="file" id="warehouse_entry_expense_file" class="warehouse-entry-expense-file-input" accept=".pdf,.jpg,.jpeg,.png" tabindex="-1">
                                            <label for="warehouse_entry_expense_file" class="warehouse-entry-expense-file-empty mb-0">
                                                <span class="warehouse-entry-expense-file-icon"><i class="fas fa-paperclip"></i></span>
                                                <span><strong>Seleccionar comprobante</strong><small>PDF, JPG, JPEG o PNG &middot; m&aacute;x. 10 MB</small></span>
                                                <i class="fas fa-chevron-right warehouse-entry-expense-file-arrow"></i>
                                            </label>
                                            <div class="warehouse-entry-expense-file-selected d-none">
                                                <span class="warehouse-entry-expense-file-icon"><i id="warehouseEntryExpenseFileTypeIcon" class="fas fa-file-alt"></i></span>
                                                <span class="warehouse-entry-expense-file-info"><strong id="warehouseEntryExpenseFileName"></strong><small id="warehouseEntryExpenseFileSize"></small></span>
                                                <label for="warehouse_entry_expense_file" class="btn btn-outline-info btn-sm mb-0"><i class="fas fa-sync-alt mr-1"></i>Cambiar</label>
                                                <button type="button" id="btnRemoveWarehouseEntryExpenseFile" class="btn btn-light btn-sm"><i class="fas fa-times mr-1"></i>Quitar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="warehouseEntryExpenseManualDistribution" class="d-none mb-3"></div>
                                <div class="warehouse-entry-expense-action"><small><i class="fas fa-info-circle mr-1"></i>El costo se incorporar&aacute; a la lista antes de guardar el ingreso.</small><button type="button" id="btnAddWarehouseEntryExpense" class="btn btn-info btn-sm"><i class="fas fa-plus mr-1"></i>Agregar costo</button></div>
                                </div>
                                <div class="warehouse-entry-expenses-table-wrap"><table class="table table-sm table-hover warehouse-entry-expenses-table"><thead><tr><th>Categoría</th><th>Tipo</th><th>Origen</th><th>Proveedor / responsable</th><th>Comprobante</th><th class="text-right">Importe</th><th>Afecta inventario</th><th>Distribución</th><th>Adjunto</th><th class="text-center">Acciones</th></tr></thead><tbody id="warehouseEntryExpensesBody"></tbody></table></div>
                            </div>
                        </div>
                        @endcanany

                        <div id="warehouseEntryOriginalDocumentsCard" class="card border-0 shadow-sm warehouse-entry-card warehouse-entry-documents-card">
                            <div class="card-header border-0 py-2 px-3 warehouse-entry-section-header">
                                <div class="d-flex justify-content-between align-items-center flex-wrap">
                                    <div class="warehouse-entry-document-title">
                                        <span class="warehouse-entry-document-icon">
                                            <i class="fas fa-folder-open"></i>
                                        </span>
                                        <div>
                                            <h6 class="mb-0 font-weight-bold text-dark">Documentos generales del ingreso</h6>
                                            <small class="text-muted">Adjunta comprobantes, gu&iacute;as o documentos que aplican a todo el ingreso.</small>
                                        </div>
                                    </div>
                                    <span class="warehouse-entry-document-counter">
                                        <span id="warehouseEntryDocumentCount">0</span> documentos
                                    </span>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="warehouse-entry-document-form">
                                    <div class="row align-items-end">
                                        <div class="form-group col-lg-3 col-md-6">
                                            <label>TIPO DE DOCUMENTO</label>
                                            <select id="warehouse_entry_document_attachment_type"
                                                class="form-control form-control-sm">
                                                <option value="purchase_invoice">Factura</option>
                                                <option value="receipt">Boleta</option>
                                                <option value="dispatch_guide">Gu&iacute;a de remisi&oacute;n</option>
                                                <option value="analysis_certificate">Certificado de an&aacute;lisis</option>
                                                <option value="sanitary_registration">Registro sanitario</option>
                                                <option value="quality_certificate">Certificado de calidad</option>
                                                <option value="bpm_bpa_certificate">Certificado BPM / BPA</option>
                                                <option value="technical_sheet">Ficha t&eacute;cnica</option>
                                                <option value="medicine_document">Documento del medicamento</option>
                                                <option value="other">Otro</option>
                                            </select>
                                        </div>

                                        <div class="form-group col-lg-4 col-md-6">
                                            <label>DESCRIPCI&Oacute;N / OBSERVACI&Oacute;N</label>
                                            <input type="text" id="warehouse_entry_document_attachment_description"
                                                class="form-control form-control-sm"
                                                placeholder="Factura F001-000123, certificado del lote...">
                                        </div>

                                        <div class="form-group col-lg-3 col-md-7">
                                            <label>ARCHIVO</label>
                                            <div class="custom-file warehouse-entry-document-file">
                                                <input type="file" id="warehouse_entry_document_attachment_file"
                                                    class="custom-file-input"
                                                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                                                <label class="custom-file-label" for="warehouse_entry_document_attachment_file">
                                                    Seleccionar archivo
                                                </label>
                                            </div>
                                        </div>

                                        <div class="form-group col-lg-2 col-md-5">
                                            <button type="button" id="btnAddWarehouseEntryDocument"
                                                class="btn btn-info btn-sm btn-block warehouse-entry-document-add">
                                                <i class="fas fa-paperclip mr-1"></i>
                                                Adjuntar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive warehouse-entry-documents-table-wrap">
                                    <table class="table table-sm table-hover mb-0 warehouse-entry-documents-table">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Tipo</th>
                                                <th>Descripci&oacute;n</th>
                                                <th>Archivo</th>
                                                <th>Fecha</th>
                                                <th class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="warehouseEntryDocumentsTbody">
                                            <tr id="warehouseEntryDocumentsEmptyRow">
                                                <td colspan="6" class="text-center text-muted py-3">
                                                    <i class="fas fa-folder-open d-block mb-2"></i>
                                                    No hay documentos adjuntos para este ingreso.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="warehouse-entry-lot-documents-section mt-3 pt-3 border-top">
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="warehouse-entry-document-icon mr-2"><i class="fas fa-tags"></i></span>
                                        <div><h6 class="mb-0 font-weight-bold">Documentos por lote</h6><small class="text-muted">Adjunta documentos espec&iacute;ficos para cada lote recibido.</small></div>
                                    </div>
                                    <div class="warehouse-entry-lot-document-form"><div class="row align-items-end">
                                        <div class="form-group col-lg-4 col-md-6"><label>ART&Iacute;CULO</label><select id="warehouse_entry_lot_document_item" class="form-control form-control-sm"><option value="">Seleccione art&iacute;culo</option></select></div>
                                        <div class="form-group col-lg-3 col-md-6"><label>LOTE</label><select id="warehouse_entry_lot_document_lot" class="form-control form-control-sm"><option value="">Seleccione lote</option></select></div>
                                        <div class="form-group col-lg-5 col-md-6"><label>TIPO</label><select id="warehouse_entry_lot_document_type" class="form-control form-control-sm">
                                            <option value="dispatch_guide">Gu&iacute;a de remisi&oacute;n</option><option value="analysis_certificate">Certificado de an&aacute;lisis</option><option value="sanitary_registration">Registro sanitario</option><option value="quality_certificate">Certificado de calidad</option><option value="bpm_bpa_certificate">Certificado BPM / BPA</option><option value="technical_sheet">Ficha t&eacute;cnica</option><option value="medicine_document">Documento del medicamento</option><option value="other">Otro</option>
                                        </select></div>
                                        <div class="w-100 d-none d-lg-block"></div>
                                        <div class="form-group col-lg-4 col-md-6"><label>DESCRIPCI&Oacute;N</label><input id="warehouse_entry_lot_document_description" class="form-control form-control-sm" maxlength="255"></div>
                                        <div class="form-group col-lg-5 col-md-7">
                                            <label>ARCHIVO</label>
                                            <div class="custom-file warehouse-entry-document-file">
                                                <input type="file" id="warehouse_entry_lot_document_file"
                                                    class="custom-file-input" accept=".pdf,.jpg,.jpeg,.png">
                                                <label class="custom-file-label" for="warehouse_entry_lot_document_file">
                                                    Seleccionar archivo
                                                </label>
                                            </div>
                                        </div>
                                        <div class="form-group col-lg-3 col-md-5"><button type="button" id="btnAddWarehouseEntryLotDocument" class="btn btn-info btn-sm btn-block warehouse-entry-document-add"><i class="fas fa-paperclip mr-1"></i>Adjuntar</button></div>
                                    </div></div>
                                    <div id="warehouseEntryLotSelectedInfo" class="warehouse-entry-lot-selected-info d-none"></div>
                                    <div id="warehouseEntryLotDocumentsList" class="warehouse-entry-lot-documents-list mt-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template id="warehouseEntryItemRowTemplate">
                        <tr class="warehouse-entry-item-row">
                            <td class="warehouse-entry-item-index align-middle"></td>
                            <td>
                                <input type="hidden" name="items[__INDEX__][id]" class="item-entry-id">
                                <input type="hidden" name="items[__INDEX__][supplier_purchase_order_item_id]"
                                    class="item-supplier-purchase-order-item-id">
                                <input type="hidden" name="items[__INDEX__][article_id]" class="item-article-id">
                                <input type="hidden" name="items[__INDEX__][article_code]" class="item-article-code">
                                <input type="hidden" name="items[__INDEX__][billing_name_snapshot]" class="item-billing-name">
                                <select class="form-control form-control-sm item-article-picker js-warehouse-entry-row-select">
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
                                            data-brand-id="{{ $article->brand_id }}"
                                            data-has-batch="{{ $article->has_batch ? 1 : 0 }}"
                                            data-has-expiration="{{ $article->has_expiration ? 1 : 0 }}">
                                            {{ $articleOptionText }}
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
                            <td class="warehouse-entry-lots-cell">
                                <input type="hidden" name="items[__INDEX__][lot_number]" class="item-lot-number">
                                <input type="hidden" name="items[__INDEX__][expiration_date]" class="item-expiration-date">
                                <input type="hidden" class="item-has-batch" value="0">
                                <input type="hidden" class="item-has-expiration" value="0">
                                <button type="button" class="btn btn-outline-info btn-sm btnManageWarehouseEntryLots">
                                    <i class="fas fa-boxes mr-1"></i> Gestionar lotes
                                </button>
                                <div class="warehouse-entry-first-lot mt-1"></div>
                                <div class="warehouse-entry-lots-summary mt-1 text-muted small">Sin lotes</div>
                                <div class="warehouse-entry-lots-inputs"></div>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="items[__INDEX__][ordered_quantity]" class="form-control form-control-sm text-right item-ordered-quantity" value="0.00" readonly></td>
                            <td class="warehouse-entry-item-quantity-cell">
                                <input type="number" step="0.01" min="0.01" name="items[__INDEX__][quantity]" class="form-control form-control-sm text-right item-quantity" value="1.00">
                                <span class="warehouse-entry-lot-quantity-display text-success font-weight-bold d-none"></span>
                            </td>
                            <td><input type="number" step="0.01" min="0" name="items[__INDEX__][unit_price]" class="form-control form-control-sm text-right item-unit-price" value="0.00"></td>
                            <td class="text-right font-weight-bold item-line-total">0.00</td>
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-danger btn-sm btnRemoveWarehouseEntryItem">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </td>
                        </tr>
                    </template>

                    <div class="modal fade warehouse-entry-lots-modal" id="warehouseEntryLotsModal" tabindex="-1" role="dialog" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content warehouse-entry-lots-modal-content">
                                <div class="modal-header">
                                    <div>
                                        <h6 class="modal-title font-weight-bold">Gestionar lotes del art&iacute;culo</h6>
                                        <small class="text-muted">Distribuye la cantidad ingresada entre uno o m&aacute;s lotes.</small>
                                    </div>
                                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                                </div>
                                <div class="modal-body">
                                    <div class="warehouse-entry-lots-metrics mb-3">
                                        <div><small>ART&Iacute;CULO</small><strong id="warehouseEntryLotsArticle">-</strong></div>
                                        <div><small>CANTIDAD INGRESO</small><strong id="warehouseEntryLotsQuantity">0.00</strong></div>
                                        <div><small>TOTAL DISTRIBUIDO</small><strong id="warehouseEntryLotsTotal">0.00</strong></div>
                                        <div><small>DIFERENCIA</small><strong id="warehouseEntryLotsDifference">0.00</strong></div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-sm warehouse-entry-lots-table mb-2">
                                            <thead><tr><th>#</th><th>Lote</th><th>Cantidad</th><th>F. vencimiento</th><th></th></tr></thead>
                                            <tbody id="warehouseEntryLotsTbody"></tbody>
                                        </table>
                                    </div>
                                    <button type="button" id="btnAddWarehouseEntryLot" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-plus mr-1"></i> Agregar lote
                                    </button>
                                    <div id="warehouseEntryLotsError" class="text-danger small mt-2"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                                    <button type="button" id="btnApplyWarehouseEntryLots" class="btn btn-info btn-sm">Aplicar lotes</button>
                                </div>
                            </div>
                        </div>
                    </div>
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
