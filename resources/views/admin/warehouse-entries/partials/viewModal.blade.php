<div class="modal fade" id="warehouseEntryViewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg warehouse-entry-modal warehouse-entry-view-modal">
            <div class="modal-header warehouse-entry-modal-header text-white">
                <div>
                    <h5 class="modal-title">
                        Informaci&oacute;n del Ingreso de Almac&eacute;n
                    </h5>
                    <small>Detalle f&iacute;sico, documental y econ&oacute;mico del ingreso</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body warehouse-entry-modal-body">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="card border-0 shadow-sm h-100 warehouse-entry-side-card">
                            <div class="card-body warehouse-entry-summary-card text-center">
                                <div class="warehouse-entry-view-icon mx-auto mb-2">
                                    <i class="fas fa-warehouse"></i>
                                </div>
                                <small class="warehouse-entry-summary-label">C&oacute;digo interno</small>
                                <h3 id="vwe_entry_number" class="warehouse-entry-summary-code">-</h3>
                                <span id="vwe_status" class="badge badge-primary rounded-pill px-3 py-2 warehouse-entry-status-badge">
                                    REGISTRADO
                                </span>
                                <hr class="warehouse-entry-summary-separator">

                                <div class="text-left warehouse-entry-summary-list">
                                    <div class="warehouse-entry-summary-item">
                                        <small>Proveedor</small>
                                        <strong id="vwe_supplier">-</strong>
                                    </div>

                                    <div class="warehouse-entry-summary-item">
                                        <small>Empresa</small>
                                        <strong id="vwe_company">-</strong>
                                    </div>

                                    <div class="warehouse-entry-summary-item">
                                        <small>Almac&eacute;n</small>
                                        <strong id="vwe_warehouse">-</strong>
                                    </div>

                                    <div class="warehouse-entry-summary-total">
                                        <small>Total ingreso</small>
                                        <div>
                                            <span id="vwe_currency_symbol">S/</span>
                                            <span id="vwe_grand_total">0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm mb-3 warehouse-entry-card">
                            <div class="card-header border-0 py-2 warehouse-entry-section-header">
                                <h6 class="mb-0 font-weight-bold">
                                    <i class="fas fa-file-invoice text-info mr-1"></i>
                                    Datos documentales
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row warehouse-entry-detail-grid">
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Orden proveedor</small>
                                            <strong id="vwe_purchase_order">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Empresa</small>
                                            <strong id="vwe_detail_company">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Proveedor</small>
                                            <strong id="vwe_detail_supplier">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Almac&eacute;n</small>
                                            <strong id="vwe_detail_warehouse">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Moneda</small>
                                            <strong id="vwe_currency">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Tipo documento</small>
                                            <strong id="vwe_document_type">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Serie / N&deg; comprobante</small>
                                            <strong id="vwe_document_number">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Fecha documento</small>
                                            <strong id="vwe_document_date">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Forma de pago</small>
                                            <strong id="vwe_payment_method">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Condici&oacute;n de pago</small>
                                            <strong id="vwe_payment_condition">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Cuenta por pagar</small>
                                            <strong id="vwe_payable">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Monto</small>
                                            <strong id="vwe_payable_amount">0.00</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="warehouse-entry-detail-field">
                                            <small>Gu&iacute;a remisi&oacute;n</small>
                                            <strong id="vwe_guide">-</strong>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="warehouse-entry-detail-field warehouse-entry-detail-field-wide">
                                            <small>Observaciones</small>
                                            <strong id="vwe_observations">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm warehouse-entry-card">
                            <div class="card-header border-0 py-2 warehouse-entry-section-header">
                                <h6 class="mb-0 font-weight-bold">
                                    <i class="fas fa-boxes text-info mr-1"></i>
                                    Art&iacute;culos ingresados
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive warehouse-entry-detail-table-wrap">
                                    <table class="table table-sm table-hover mb-0 warehouse-entry-detail-table">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Art&iacute;culo</th>
                                                <th>U.M.</th>
                                                <th>Present.</th>
                                                <th>Marca</th>
                                                <th>Lote</th>
                                                <th class="text-right">Cant. Ord.</th>
                                                <th class="text-right">Cant. Ing.</th>
                                                <th class="text-right">Precio</th>
                                                <th class="text-right">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="vwe_items">
                                            <tr>
                                                <td colspan="10" class="text-center text-muted py-3">
                                                    Sin art&iacute;culos ingresados.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="card-footer warehouse-entry-detail-footer">
                                <div class="row justify-content-end">
                                    <div class="col-md-5 col-lg-4">
                                        <div class="warehouse-entry-totals-card">
                                            <div class="warehouse-entry-total-row">
                                                <span>Subtotal</span>
                                                <strong id="vwe_subtotal">0.00</strong>
                                            </div>
                                            <div class="warehouse-entry-total-row">
                                                <span>IGV</span>
                                                <strong id="vwe_igv">0.00</strong>
                                            </div>
                                            <div class="warehouse-entry-total-row warehouse-entry-total-row-grand">
                                                <span>Total</span>
                                                <strong id="vwe_total">0.00</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card border-0 shadow-sm mt-3 warehouse-entry-card warehouse-entry-documents-card">
                            <div class="card-header border-0 py-2 warehouse-entry-section-header">
                                <h6 class="mb-0 font-weight-bold">
                                    <i class="fas fa-folder-open text-info mr-1"></i>
                                    Documentos adjuntos
                                </h6>
                                <small class="text-muted">Comprobantes, gu&iacute;as y documentos sanitarios vinculados al ingreso</small>
                            </div>
                            <div class="card-body p-0">
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
                                        <tbody id="vwe_documents">
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-3">
                                                    No hay documentos adjuntos.
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
