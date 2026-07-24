<div class="modal fade petty-detail-modal" id="viewPettyCashModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0">
            <div class="modal-header petty-detail-header">
                <div class="d-flex align-items-center">
                    <span class="petty-detail-header-icon"><i class="fas fa-wallet"></i></span>
                    <div>
                        <small>DETALLE DE CAJA CHICA</small>
                        <h4 id="pcv_code">-</h4>
                        <p><span id="pcv_company">-</span><span id="pcv_meta"></span></p>
                    </div>
                </div>
                <button type="button" class="close petty-close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>

            <div class="modal-body petty-detail-body">
                <div id="pcv_summary" class="petty-financial-grid"></div>

                <section class="petty-detail-card">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-receipt"></i></span><div><h6>Gastos registrados</h6><small>Movimientos que reducen el saldo disponible</small></div></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table petty-detail-table">
                            <thead><tr><th>#</th><th>Fecha</th><th>Comprobante</th><th>RUC</th><th>Proveedor</th><th>Concepto</th><th class="text-right">Importe</th><th>Documento</th><th></th></tr></thead>
                            <tbody id="pcv_expenses"></tbody>
                        </table>
                    </div>
                </section>

                <section class="petty-detail-card">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-sync-alt"></i></span><div><h6>Reposiciones registradas</h6><small>Fondos restituidos a la caja chica</small></div></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table petty-detail-table">
                            <thead><tr><th>Fecha</th><th class="text-right">Monto</th><th>Método</th><th>Operación</th><th>Observación</th><th>Sustento</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody id="pcv_replenishments"></tbody>
                        </table>
                    </div>
                </section>

                <section class="petty-detail-card mb-0">
                    <div class="petty-detail-card-title">
                        <div><span><i class="fas fa-user-shield"></i></span><div><h6>Responsables</h6><small>Custodia y supervisión del fondo</small></div></div>
                    </div>
                    <div id="pcv_responsibles" class="row"></div>
                </section>
            </div>
        </div>
    </div>
</div>
