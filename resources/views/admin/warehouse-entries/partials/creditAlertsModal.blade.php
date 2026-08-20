<div class="modal fade" id="warehouseEntryCreditAlertsModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg warehouse-credit-alert-modal">
            <div class="modal-header warehouse-credit-alert-header text-white">
                <div>
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-file-invoice-dollar mr-2"></i>
                        Cr&eacute;ditos por vencer
                    </h5>
                    <small>Cuentas por pagar que requieren atenci&oacute;n inmediata o dentro de los pr&oacute;ximos 15 d&iacute;as.</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div id="warehouseCreditAlertLoading" class="warehouse-credit-alert-loading">
                    <i class="fas fa-circle-notch fa-spin"></i>
                    Consultando cr&eacute;ditos pendientes...
                </div>

                <div id="warehouseCreditAlertContent" class="d-none">
                    <div class="warehouse-credit-alert-metrics">
                        <div><small>Total pendientes</small><strong id="warehouseCreditAlertTotal">0</strong></div>
                        <div class="is-danger"><small>Vencidos</small><strong id="warehouseCreditAlertOverdue">0</strong></div>
                        <div class="is-danger"><small>Vencen hoy</small><strong id="warehouseCreditAlertToday">0</strong></div>
                        <div class="is-orange"><small>Pr&oacute;ximos 7 d&iacute;as</small><strong id="warehouseCreditAlertSevenDays">0</strong></div>
                        <div class="is-warning"><small>Pr&oacute;ximos 15 d&iacute;as</small><strong id="warehouseCreditAlertFifteenDays">0</strong></div>
                        <div class="is-amount"><small>Monto pendiente</small><strong id="warehouseCreditAlertAmount">-</strong></div>
                    </div>

                    <div class="table-responsive warehouse-credit-alert-table-wrap">
                        <table class="table table-hover table-sm mb-0 warehouse-credit-alert-table">
                            <thead>
                                <tr>
                                    <th>Estado</th>
                                    <th>OC cliente / proveedor</th>
                                    <th>Ingreso</th>
                                    <th>Proveedor / empresa</th>
                                    <th>Condici&oacute;n</th>
                                    <th>Fechas</th>
                                    <th class="text-right">Saldo pendiente</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="warehouseCreditAlertRows"></tbody>
                        </table>
                    </div>
                </div>

                <div id="warehouseCreditAlertEmpty" class="warehouse-credit-alert-empty d-none">
                    <i class="fas fa-check-circle"></i>
                    <strong>Sin cr&eacute;ditos por vencer</strong>
                    <span>No existen cuentas pendientes vencidas o con vencimiento dentro de los pr&oacute;ximos 15 d&iacute;as.</span>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="btnWarehouseCreditAlertsViewAll" class="btn btn-outline-info btn-sm">
                    <i class="fas fa-list mr-1"></i>
                    Ver todos en la tabla
                </button>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>
                    Cerrar
                </button>
            </div>
        </div>
    </div>
</div>
