<div class="modal fade" id="labelingModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static"
    data-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content labeling-modal border-0 shadow-lg">
            <form id="labelingForm" autocomplete="off">
                <input type="hidden" id="labeling_id">

                <div class="modal-header labeling-modal-header text-white">
                    <div class="labeling-header-title">
                        <span class="labeling-header-icon">
                            <i class="fas fa-tags"></i>
                        </span>
                        <div>
                            <h5 class="modal-title" id="labelingModalLabel">Registrar Rotulación</h5>
                            <small>Distribuya los artículos abastecidos en cajas para generar rótulos.</small>
                        </div>
                    </div>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body labeling-modal-body">
                    <div id="labelingErrors" class="alert alert-danger d-none"></div>

                    <div class="row">
                        <div class="col-lg-3 mb-2">
                            <div class="card labeling-card border-0 shadow-sm h-100">
                                <div class="card-body text-center">
                                    <div class="labeling-side-icon mx-auto mb-3">
                                        <i class="fas fa-box-open"></i>
                                    </div>
                                    <h5 class="font-weight-bold mb-1">Rotulación</h5>
                                    <small class="text-muted">Rótulos para cajas abastecidas</small>
                                    <hr>
                                    <div class="text-left small">
                                        <small class="text-muted d-block">Cliente</small>
                                        <div id="labelingSideCustomer" class="font-weight-bold mb-2">Seleccione orden</div>
                                        <small class="text-muted d-block">Empresa</small>
                                        <div id="labelingSideCompany" class="font-weight-bold mb-2">-</div>
                                        <small class="text-muted d-block">Cajas</small>
                                        <div class="labeling-side-total">
                                            <span id="labelingSideBoxes">1</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-9 mb-2">
                            <div class="card labeling-card border-0 shadow-sm">
                                <div class="card-header labeling-section-header border-0">
                                    <h6 class="mb-0">
                                        <i class="fas fa-clipboard-check mr-1 text-warning"></i>
                                        Datos principales
                                    </h6>
                                    <small class="text-muted">Orden abastecida, comprobantes y cajas</small>
                                </div>
                                <div class="card-body">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>ORDEN DE COMPRA DEL CLIENTE <span class="text-danger">*</span></label>
                                            <select id="labeling_customer_purchase_order_id" name="customer_purchase_order_id"
                                                class="form-control form-control-sm" required>
                                                <option value="">Seleccione orden abastecida</option>
                                            </select>
                                            <span class="invalid-feedback"></span>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>CLIENTE</label>
                                            <input type="text" id="labeling_customer_name" class="form-control form-control-sm" readonly>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>EMPRESA</label>
                                            <input type="text" id="labeling_company_name" class="form-control form-control-sm" readonly>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-3">
                                            <label>NRO ORDEN COMPRA</label>
                                            <input type="text" id="labeling_order_number" class="form-control form-control-sm" readonly>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>FACTURA</label>
                                            <input type="text" id="labeling_invoice_number" name="invoice_number"
                                                class="form-control form-control-sm text-uppercase" placeholder="E001-944">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>GUÍA</label>
                                            <input type="text" id="labeling_guide_number" name="guide_number"
                                                class="form-control form-control-sm text-uppercase" placeholder="EG07-661">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>CANTIDAD DE CAJAS <span class="text-danger">*</span></label>
                                            <input type="number" id="labeling_boxes_count" name="boxes_count"
                                                class="form-control form-control-sm" min="1" step="1" value="1" required>
                                            <span class="invalid-feedback"></span>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label>DESTINO <span class="text-danger">*</span></label>
                                            <input type="text" id="labeling_destination" name="destination"
                                                class="form-control form-control-sm text-uppercase"
                                                placeholder="Ej. ALMACEN CENTRAL / FARMACIA / TARAPOTO" required>
                                            <span class="invalid-feedback"></span>
                                        </div>
                                    </div>
                                    <div class="form-group mb-0">
                                        <label>OBSERVACIONES</label>
                                        <textarea id="labeling_observations" name="observations"
                                            class="form-control form-control-sm text-uppercase" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card labeling-card border-0 shadow-sm mb-2">
                        <div class="card-header labeling-section-header border-0">
                            <h6 class="mb-0">
                                <i class="fas fa-boxes mr-1 text-info"></i>
                                Artículos de la orden
                            </h6>
                            <small class="text-muted">Cantidades abastecidas, ya rotuladas y disponibles para rotular</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive labeling-table-scroll">
                                <table class="table table-sm table-bordered mb-0 labeling-order-items-table" id="labelingOrderItemsTable">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Artículo</th>
                                            <th>Descripción</th>
                                            <th>Unidad</th>
                                            <th>Marca</th>
                                            <th>Presentación</th>
                                            <th>Lote</th>
                                            <th>Vencimiento</th>
                                            <th class="text-right">Cantidad abastecida</th>
                                            <th class="text-right">Cantidad ya rotulada</th>
                                            <th class="text-right">Cantidad disponible</th>
                                            <th class="text-right">Cantidad a rotular</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="11" class="text-center text-muted py-3">Seleccione una orden abastecida.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card labeling-card border-0 shadow-sm mb-0">
                        <div class="card-header labeling-section-header border-0">
                            <div>
                                <h6 class="mb-0">
                                    <i class="fas fa-tags mr-1 text-success"></i>
                                    Distribución por cajas
                                </h6>
                                <small class="text-muted">Reparto automático o edición manual</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="labeling-quick-distribution mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <span class="labeling-quick-icon mr-2"><i class="fas fa-magic"></i></span>
                                    <div>
                                        <h6 class="font-weight-bold mb-0">Distribución rápida</h6>
                                        <small class="text-muted">La última caja recibirá el saldo para cuadrar el total.</small>
                                    </div>
                                </div>
                                <div class="form-row align-items-end">
                                    <div class="form-group col-md-3 mb-2">
                                        <label>MODO DE DISTRIBUCIÓN</label>
                                        <select id="labeling_distribution_mode" class="form-control form-control-sm">
                                            <option value="fixed" selected>Cajas fijas del proveedor</option>
                                            <option value="automatic">Calcular cajas por capacidad</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4 mb-2">
                                        <label>ARTÍCULO A DISTRIBUIR</label>
                                        <select id="labeling_quick_item_id" class="form-control form-control-sm">
                                            <option value="">Seleccione un artículo</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-2 mb-2">
                                        <label>TOTAL A ROTULAR</label>
                                        <input type="text" id="labeling_quick_total" class="form-control form-control-sm text-right" readonly value="0.00">
                                    </div>
                                    <div class="form-group col-md-2 mb-2">
                                        <label id="labeling_quantity_per_box_label">CANTIDAD REFERENCIAL POR CAJA</label>
                                        <input type="number" id="labeling_quantity_per_box" class="form-control form-control-sm text-right"
                                            min="0.01" step="0.01" placeholder="Ej. 1800">
                                    </div>
                                    <div class="form-group col-md-1 mb-2">
                                        <label>CAJAS</label>
                                        <input type="number" id="labeling_quick_boxes_count" class="form-control form-control-sm text-right"
                                            min="1" step="1" value="1" readonly>
                                    </div>
                                    <div class="form-group col-md-12 mb-0">
                                        <button type="button" class="btn btn-success btn-sm btn-block" id="btnAutoDistributeLabeling">
                                            <i class="fas fa-magic mr-1"></i> Distribuir automáticamente
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div id="labelingBoxesContainer" class="row">
                                <div class="col-12 text-center text-muted py-3">Ingrese la cantidad de cajas.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer labeling-modal-footer">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning btn-sm" id="btnSaveLabeling">
                        <i class="fas fa-save mr-1"></i>
                        Guardar rotulación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
