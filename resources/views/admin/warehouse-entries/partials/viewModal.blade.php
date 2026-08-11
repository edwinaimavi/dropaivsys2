<div class="modal fade" id="warehouseEntryViewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg warehouse-entry-modal warehouse-entry-view-modal">
            <div class="modal-header warehouse-entry-modal-header text-white">
                <div><h5 class="modal-title">Informaci&oacute;n del Ingreso de Almac&eacute;n</h5><small>Detalle f&iacute;sico, documental y econ&oacute;mico del ingreso</small></div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body warehouse-entry-modal-body">
                <div class="warehouse-entry-view-heading">
                    <div class="warehouse-entry-view-identity">
                        <div class="warehouse-entry-view-icon"><i class="fas fa-warehouse"></i></div>
                        <div><small>C&oacute;digo interno</small><h3 id="vwe_entry_number">-</h3></div>
                        <span id="vwe_status" class="badge badge-primary rounded-pill px-3 py-2">REGISTRADO</span>
                    </div>
                    <div class="warehouse-entry-view-facts">
                        <div><small>Proveedor</small><strong id="vwe_supplier">-</strong></div>
                        <div><small>Empresa</small><strong id="vwe_company">-</strong></div>
                        <div><small>Almac&eacute;n</small><strong id="vwe_warehouse">-</strong></div>
                        <div class="warehouse-entry-view-total"><small>Total ingreso</small><strong><span id="vwe_currency_symbol">S/</span> <span id="vwe_grand_total">0.00</span></strong></div>
                    </div>
                </div>

                <div class="warehouse-entry-tabs-scroll"><ul class="nav nav-pills warehouse-entry-view-tabs" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-toggle="pill" href="#vwe_summary_tab"><i class="fas fa-file-invoice mr-1"></i>Resumen</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#vwe_items_tab"><i class="fas fa-boxes mr-1"></i>Art&iacute;culos y lotes</a></li>
                    @can('admin.warehouse-entries.expenses.index')
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#vwe_expenses_tab"><i class="fas fa-truck-loading mr-1"></i>Costos vinculados</a></li>
                    @endcan
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#vwe_documents_tab"><i class="fas fa-folder-open mr-1"></i>Documentos adjuntos</a></li>
                    <li class="nav-item"><a class="nav-link" data-toggle="pill" href="#vwe_audit_tab"><i class="fas fa-history mr-1"></i>Trazabilidad</a></li>
                </ul></div>

                <div class="tab-content warehouse-entry-view-tab-content">
                    <div class="tab-pane fade show active" id="vwe_summary_tab"><div class="card border-0 shadow-sm warehouse-entry-card"><div class="card-body"><div class="row warehouse-entry-detail-grid">
                        <div class="col-12"><div id="vwe_customer_orders" class="warehouse-entry-customer-orders"><div class="warehouse-entry-customer-order-card"><span>Orden de Compra del Cliente</span><strong>Sin OC cliente relacionada</strong></div></div></div>
                        @foreach ([['Orden proveedor','vwe_purchase_order'],['Empresa','vwe_detail_company'],['Proveedor','vwe_detail_supplier'],['Almacén','vwe_detail_warehouse'],['Moneda','vwe_currency'],['Tipo documento','vwe_document_type'],['Serie / N° comprobante','vwe_document_number'],['Fecha documento','vwe_document_date'],['Forma de pago','vwe_payment_method'],['Condición de pago','vwe_payment_condition'],['Cuenta por pagar','vwe_payable'],['Monto','vwe_payable_amount'],['Guía remisión','vwe_guide']] as [$label,$id])
                            <div class="col-sm-6 col-lg-4"><div class="warehouse-entry-detail-field"><small>{{ $label }}</small><strong id="{{ $id }}">-</strong></div></div>
                        @endforeach
                        <div class="col-12"><div class="warehouse-entry-detail-field warehouse-entry-detail-field-wide"><small>Observaciones</small><strong id="vwe_observations">-</strong></div></div>
                    </div></div></div></div>

                    <div class="tab-pane fade" id="vwe_items_tab"><div class="card border-0 shadow-sm warehouse-entry-card">
                        <div class="card-header border-0 py-2 warehouse-entry-section-header"><h6 class="mb-0 font-weight-bold"><i class="fas fa-boxes text-info mr-1"></i>Detalle por art&iacute;culo y lote</h6><small class="text-muted">Cada lote se muestra en una fila independiente.</small></div>
                        <div class="card-body p-0"><div class="table-responsive warehouse-entry-detail-table-wrap"><table class="table table-sm table-hover mb-0 warehouse-entry-detail-table"><thead><tr><th>#</th><th>Art&iacute;culo</th><th>U.M.</th><th>Present.</th><th>Marca</th><th>Procedencia</th><th>Lote</th><th class="text-right">Cantidad</th><th class="text-right">Costo compra</th><th class="text-right">Costo adicional</th><th class="text-right">Costo real unit.</th><th class="text-right">Costo real</th></tr></thead><tbody id="vwe_items"><tr><td colspan="12" class="text-center text-muted py-4">Sin art&iacute;culos ingresados.</td></tr></tbody></table></div></div>
                        <div class="card-footer warehouse-entry-detail-footer"><div class="row justify-content-end"><div class="col-sm-7 col-md-5 col-lg-4"><div class="warehouse-entry-totals-card"><div class="warehouse-entry-total-row"><span>Subtotal</span><strong id="vwe_subtotal">0.00</strong></div><div class="warehouse-entry-total-row"><span>IGV</span><strong id="vwe_igv">0.00</strong></div><div class="warehouse-entry-total-row warehouse-entry-total-row-grand"><span>Total ingreso</span><strong id="vwe_total">0.00</strong></div></div></div></div></div>
                    </div></div>

                    @can('admin.warehouse-entries.expenses.index')
                    <div class="tab-pane fade" id="vwe_expenses_tab"><div class="card border-0 shadow-sm warehouse-entry-card">
                        <div class="card-header border-0 py-2 warehouse-entry-section-header"><h6 class="mb-0 font-weight-bold"><i class="fas fa-truck-loading text-info mr-1"></i>Costos vinculados al ingreso</h6></div>
                        <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Categoría</th><th>Tipo</th><th>Origen</th><th>Proveedor</th><th>Comprobante</th><th class="text-right">Importe</th><th>IGV</th><th>Clasificación</th><th>Inventario</th><th>Distribución</th><th>Documentos</th></tr></thead><tbody id="vwe_expenses"></tbody></table></div></div>
                        <div class="card-footer"><div class="row"><div class="col-md-5 ml-auto"><div class="warehouse-entry-total-row"><span>Flete / transporte con comprobante</span><strong id="vwe_expenses_freight">0.00</strong></div><div class="warehouse-entry-total-row"><span>Otros gastos / sin comprobante</span><strong id="vwe_expenses_other">0.00</strong></div><div class="warehouse-entry-total-row"><span>Total vinculado</span><strong id="vwe_expenses_linked">0.00</strong></div><div class="warehouse-entry-total-row"><span>Gastos informativos</span><strong id="vwe_expenses_informative">0.00</strong></div><div class="warehouse-entry-total-row"><span>Afectan inventario</span><strong id="vwe_expenses_inventory">0.00</strong></div><div class="warehouse-entry-total-row warehouse-entry-total-row-grand"><span>Costo real de ingreso</span><strong id="vwe_real_total">0.00</strong></div></div></div></div>
                    </div></div>
                    @endcan

                    <div class="tab-pane fade" id="vwe_documents_tab"><div class="card border-0 shadow-sm warehouse-entry-card"><div class="card-header border-0 py-2 warehouse-entry-section-header"><h6 class="mb-0 font-weight-bold"><i class="fas fa-folder-open text-info mr-1"></i>Documentos generales</h6><small class="text-muted">Comprobantes, gu&iacute;as y documentos que aplican a todo el ingreso</small></div><div class="card-body p-0"><div class="table-responsive warehouse-entry-documents-table-wrap"><table class="table table-sm table-hover mb-0 warehouse-entry-documents-table"><thead><tr><th>#</th><th>Tipo</th><th>Descripci&oacute;n</th><th>Archivo</th><th>Fecha</th><th class="text-center">Acciones</th></tr></thead><tbody id="vwe_documents"><tr><td colspan="6" class="text-center text-muted py-4">No hay documentos adjuntos para este ingreso.</td></tr></tbody></table></div><div class="warehouse-entry-detail-lot-documents p-3 border-top"><h6 class="font-weight-bold"><i class="fas fa-tags text-info mr-1"></i>Documentos por lote</h6><div id="vwe_lot_documents"></div></div></div></div></div>

                    <div class="tab-pane fade" id="vwe_audit_tab"><div class="card border-0 shadow-sm warehouse-entry-card"><div class="card-header border-0 py-2 warehouse-entry-section-header"><h6 class="mb-0 font-weight-bold"><i class="fas fa-history text-info mr-1"></i>Auditor&iacute;a del registro</h6></div><div class="card-body"><div class="row warehouse-entry-detail-grid">
                        @foreach ([['Registrado por','vwe_created_by'],['Fecha de registro','vwe_created_at'],['Estado actual','vwe_audit_status'],['Actualizado por','vwe_updated_by'],['Última actualización','vwe_updated_at']] as [$label,$id])
                            <div class="col-sm-6 col-lg-4"><div class="warehouse-entry-detail-field"><small>{{ $label }}</small><strong id="{{ $id }}">-</strong></div></div>
                        @endforeach
                    </div></div></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
