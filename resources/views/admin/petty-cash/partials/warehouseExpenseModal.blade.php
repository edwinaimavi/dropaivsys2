<div class="modal fade petty-warehouse-expense-modal" id="pettyCashWarehouseExpenseModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0">
            <div class="modal-header petty-expense-header">
                <div class="d-flex align-items-center">
                    <span class="petty-expense-header-icon"><i class="fas fa-warehouse"></i></span>
                    <div><small>INTEGRACIÓN CON ALMACÉN</small><h4 class="mb-0">Costos de almacén pendientes</h4><p>Seleccione costos no oficiales para registrarlos como gastos de Caja Chica.</p></div>
                </div>
                <button type="button" class="close petty-close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="petty-warehouse-filters">
                    <div class="form-row align-items-end">
                        <div class="form-group col-lg-5 col-md-6"><label>BUSCAR</label><input id="pcWarehouseExpenseSearch" class="form-control form-control-sm" placeholder="Ingreso, OC, proveedor, documento u observación"></div>
                        <div class="form-group col-lg-2 col-md-3"><label>FECHA DESDE</label><input type="date" id="pcWarehouseExpenseDateFrom" class="form-control form-control-sm"></div>
                        <div class="form-group col-lg-2 col-md-3"><label>FECHA HASTA</label><input type="date" id="pcWarehouseExpenseDateTo" class="form-control form-control-sm"></div>
                        <div class="form-group col-lg-3 text-right"><button type="button" id="btnSearchWarehouseExpenses" class="btn btn-info btn-sm"><i class="fas fa-search mr-1"></i>Buscar</button><button type="button" id="btnClearWarehouseExpenseFilters" class="btn btn-light btn-sm ml-1">Limpiar</button></div>
                    </div>
                </div>
                <div class="alert alert-info py-2 mb-3"><i class="fas fa-info-circle mr-1"></i>Los gastos creados quedarán pendientes de aprobación y el costo seguirá visible en Almacén.</div>
                <div class="table-responsive petty-warehouse-table-wrap">
                    <table class="table table-sm table-hover mb-0 petty-warehouse-table">
                        <thead><tr><th class="text-center"><input type="checkbox" id="pcWarehouseExpenseSelectAll"></th><th>Fecha</th><th>Ingreso / órdenes</th><th>Cliente</th><th>Responsable</th><th>Tipo</th><th>Documento</th><th class="text-right">Importe</th><th>Observación / origen</th></tr></thead>
                        <tbody id="pcWarehouseExpenseRows"><tr><td colspan="9" class="text-center text-muted py-4">Use los filtros para consultar costos pendientes.</td></tr></tbody>
                    </table>
                </div>
                <div class="petty-warehouse-pagination"><button type="button" id="btnWarehouseExpensePrevious" class="btn btn-light btn-sm"><i class="fas fa-chevron-left"></i></button><span id="pcWarehouseExpensePage">Página 1 de 1</span><button type="button" id="btnWarehouseExpenseNext" class="btn btn-light btn-sm"><i class="fas fa-chevron-right"></i></button></div>
            </div>
            <div class="modal-footer petty-expense-footer justify-content-between">
                <div><strong id="pcWarehouseExpenseSelection">0 costos seleccionados</strong><small id="pcWarehouseExpenseTotal" class="d-block text-muted">Total: 0.00</small></div>
                <div><button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button><button type="button" id="btnConfirmWarehouseExpenses" class="btn btn-info"><i class="fas fa-link mr-1"></i>Registrar en Caja Chica</button></div>
            </div>
        </div>
    </div>
</div>

@push('css')
<style>
    .petty-warehouse-expense-modal{z-index:1070}.modal-backdrop.petty-warehouse-backdrop{z-index:1065}
    .petty-warehouse-expense-modal .modal-content{border-radius:18px;overflow:hidden;box-shadow:0 24px 65px rgba(20,46,39,.22)}
    .petty-warehouse-filters{padding:13px 14px 2px;border:1px solid #e1e8e5;border-radius:12px;background:#fff}.petty-warehouse-filters label{color:#6a7973;font-size:.66rem;font-weight:800;letter-spacing:.045em}
    .petty-warehouse-table-wrap{max-height:430px;border:1px solid #e1e8e5;border-radius:12px;background:#fff}.petty-warehouse-table{min-width:1180px}.petty-warehouse-table thead th{position:sticky;top:0;z-index:2;border:0;background:#eef5f2;color:#496259;font-size:.64rem;letter-spacing:.035em;text-transform:uppercase}.petty-warehouse-table td{vertical-align:middle;color:#3f514b;font-size:.72rem}.petty-warehouse-table small{display:block;color:#83908b;font-size:.62rem;line-height:1.35}.petty-warehouse-table .badge{font-size:.58rem}
    .petty-warehouse-pagination{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:12px;color:#708079;font-size:.72rem}.petty-warehouse-pagination .btn{width:32px;height:30px;padding:0}
    #pcWarehouseExpenseSelection{color:#29483e;font-size:.78rem}#pcWarehouseExpenseTotal{font-size:.68rem}
    @media(max-width:767px){.petty-warehouse-expense-modal .modal-dialog{margin:8px}.petty-warehouse-expense-modal .modal-header{padding:14px}.petty-warehouse-expense-modal .modal-footer{align-items:flex-end}}
</style>
@endpush
