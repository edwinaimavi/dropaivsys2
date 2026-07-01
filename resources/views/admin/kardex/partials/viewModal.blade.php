<div class="modal fade" id="kardexViewModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg kardex-view-modal">
            <div class="modal-header bg-info text-white border-0">
                <div>
                    <h5 class="modal-title mb-0">Informaci&oacute;n del Movimiento Kardex</h5>
                    <small>Trazabilidad f&iacute;sica y econ&oacute;mica del inventario</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body bg-light">
                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <div class="card border-0 shadow-sm h-100 kardex-modal-side-card">
                            <div class="card-body text-center">
                                <div class="kardex-view-icon mx-auto mb-2">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <small class="text-muted d-block font-weight-bold">N&deg; Movimiento</small>
                                <h4 id="vk_movement_number" class="font-weight-bold text-dark kardex-modal-movement-number">-</h4>
                                <div id="vk_status" class="mb-2">-</div>
                                <hr>
                                <div class="text-left small kardex-modal-summary">
                                    <small class="text-muted d-block">Tipo movimiento</small>
                                    <strong id="vk_movement_type" class="d-block mb-2">-</strong>
                                    <small class="text-muted d-block">Almac&eacute;n</small>
                                    <strong id="vk_warehouse" class="d-block mb-2">-</strong>
                                    <small class="text-muted d-block">Art&iacute;culo</small>
                                    <strong id="vk_article" class="d-block mb-2">-</strong>
                                    <small class="text-muted d-block">Saldo despu&eacute;s del movimiento</small>
                                    <strong id="vk_balance_quantity" class="h5 d-block kardex-modal-balance">0.00</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-9">
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-header bg-white border-0 py-2">
                                <h6 class="mb-0 font-weight-bold">
                                    <i class="fas fa-info-circle text-info mr-1"></i>
                                    Detalle del movimiento
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row kardex-detail-grid">
                                    @foreach ([
                                        'Fecha movimiento' => 'vk_movement_date',
                                        'Operaci&oacute;n' => 'vk_operation_type',
                                        'Documento' => 'vk_document',
                                        'Proveedor / relacionado' => 'vk_related_party',
                                        'Lote' => 'vk_lot_number',
                                        'Fecha vencimiento' => 'vk_expiration_date',
                                        'Unidad' => 'vk_unit',
                                        'Presentaci&oacute;n' => 'vk_presentation',
                                        'Marca' => 'vk_brand',
                                        'Procedencia' => 'vk_origin',
                                        'C. Costeo' => 'vk_cost_type',
                                        'Entrada' => 'vk_quantity_in',
                                        'Salida' => 'vk_quantity_out',
                                        'Costo unitario' => 'vk_unit_cost',
                                        'Costo promedio' => 'vk_average_unit_cost',
                                        'Valor entrada' => 'vk_total_cost_in',
                                        'Valor salida' => 'vk_total_cost_out',
                                        'Valor saldo' => 'vk_balance_total_cost',
                                    ] as $label => $id)
                                        <div class="col-md-4">
                                            <div class="kardex-detail-field">
                                                <small>{!! $label !!}</small>
                                                <strong id="{{ $id }}">-</strong>
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="col-md-12">
                                        <div class="kardex-detail-field">
                                            <small>Observaciones</small>
                                            <strong id="vk_observations">-</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white border-0 py-2">
                                <h6 class="mb-0 font-weight-bold">
                                    <i class="fas fa-project-diagram text-info mr-1"></i>
                                    Trazabilidad
                                </h6>
                            </div>
                            <div class="card-body py-2">
                                <div class="table-responsive">
                                    <table class="table table-sm mb-0 kardex-trace-table">
                                        <tbody>
                                            <tr>
                                                <th>Origen</th>
                                                <td id="vk_source_type">-</td>
                                                <th>ID origen</th>
                                                <td id="vk_source_id">-</td>
                                            </tr>
                                            <tr>
                                                <th>Item origen</th>
                                                <td id="vk_source_item_type">-</td>
                                                <th>ID item</th>
                                                <td id="vk_source_item_id">-</td>
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
