<div class="modal fade" id="viewPettyCashModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content border-0 petty-modal-content">
        <div class="modal-header petty-modal-header"><div><small>DETALLE DE CAJA CHICA</small><h4 id="pcv_code">-</h4><p id="pcv_company">-</p></div><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
        <div class="modal-body">
            <div id="pcv_summary" class="petty-detail-summary"></div>
            <div class="petty-detail-section"><h6><i class="fas fa-receipt"></i> Gastos registrados</h6><div class="table-responsive"><table class="table table-sm table-hover"><thead><tr><th>#</th><th>Fecha</th><th>Comprobante</th><th>RUC</th><th>Proveedor</th><th>Concepto</th><th class="text-right">Importe</th><th>Documento</th><th></th></tr></thead><tbody id="pcv_expenses"></tbody></table></div></div>
            <div class="petty-detail-section"><h6><i class="fas fa-sync-alt"></i> Reposiciones registradas</h6><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Fecha</th><th>Monto</th><th>Método</th><th>Operación</th><th>Observación</th><th>Sustento</th><th>Estado</th><th>Acciones</th></tr></thead><tbody id="pcv_replenishments"></tbody></table></div></div>
            <div class="petty-detail-section"><h6><i class="fas fa-user-shield"></i> Responsables</h6><div id="pcv_responsibles" class="row"></div></div>
        </div>
    </div></div>
</div>
