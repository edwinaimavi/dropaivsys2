<div class="modal fade" id="companyWarehouseModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <div>
                    <h5 class="modal-title mb-1"><i class="fas fa-building mr-2"></i>Empresas y establecimientos de almac&eacute;n</h5>
                    <small>Habilitaci&oacute;n empresarial de almacenes y c&oacute;digo de establecimiento SUNAT</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>
            <div class="modal-body bg-light">
                @can('admin.warehouse-entries.update')
                <form id="companyWarehouseForm" class="card border-0 shadow-sm mb-3" novalidate>
                    @csrf
                    <input type="hidden" id="company_warehouse_id">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="form-group col-md-4">
                                <label>EMPRESA</label>
                                <select id="company_warehouse_company_id" class="form-control form-control-sm" required></select>
                                <span class="invalid-feedback"></span>
                            </div>
                            <div class="form-group col-md-3">
                                <label>ALMAC&Eacute;N</label>
                                <select id="company_warehouse_warehouse_id" class="form-control form-control-sm" required></select>
                                <span class="invalid-feedback"></span>
                            </div>
                            <div class="form-group col-md-3">
                                <label>C&Oacute;DIGO ESTABLECIMIENTO SUNAT</label>
                                <input id="company_warehouse_sunat_code" class="form-control form-control-sm" maxlength="20" inputmode="numeric" placeholder="Pendiente de configurar">
                                <small class="form-text text-muted">Se conserva como texto. No se asignan c&oacute;digos autom&aacute;ticos.</small>
                                <span class="invalid-feedback"></span>
                            </div>
                            <div class="form-group col-md-2">
                                <div class="custom-control custom-switch mb-2">
                                    <input type="checkbox" class="custom-control-input" id="company_warehouse_is_active" checked>
                                    <label class="custom-control-label" for="company_warehouse_is_active">Activo</label>
                                </div>
                                <button class="btn btn-info btn-sm btn-block" type="submit"><i class="fas fa-save mr-1"></i>Guardar</button>
                                <button class="btn btn-light btn-sm btn-block d-none" id="btnCancelCompanyWarehouseEdit" type="button">Cancelar edici&oacute;n</button>
                            </div>
                        </div>
                    </div>
                </form>
                @endcan

                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-header bg-white"><strong>Configuraciones registradas</strong></div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="companyWarehouseTable">
                            <thead class="thead-light"><tr><th>Empresa</th><th>Almac&eacute;n</th><th>C&oacute;digo SUNAT</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                            <tbody><tr><td colspan="5" class="text-center text-muted py-4">Cargando configuraciones...</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
