<div class="modal fade supplier-order-quick-modal" id="quickSupplierForOrderModal" tabindex="-1"
    data-backdrop="static" data-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header quick-supplier-order-header text-white">
                <div class="d-flex align-items-center">
                    <div class="quick-supplier-order-icon mr-3"><i class="fas fa-truck"></i></div>
                    <div><h5 class="mb-0 font-weight-bold">Nuevo Proveedor</h5><small class="text-white-50">Registro rápido para orden de compra</small></div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="quickSupplierForOrderForm" autocomplete="off">
                @csrf
                <input type="hidden" name="status" value="ACTIVE">
                <div class="modal-body bg-light">
                    <div id="quickSupplierForOrderErrors" class="alert alert-danger d-none"></div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>RUC <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="spo_quick_supplier_ruc" name="ruc" class="form-control" maxlength="11" inputmode="numeric" required>
                                <div class="input-group-append"><button type="button" id="btnSearchQuickSupplierRuc" class="btn btn-outline-success" title="Buscar RUC"><i class="fas fa-search"></i></button></div>
                            </div><span class="invalid-feedback"></span>
                        </div>
                        <div class="form-group col-md-5"><label>RAZÓN SOCIAL <span class="text-danger">*</span></label><input type="text" id="spo_quick_supplier_business_name" name="business_name" class="form-control form-control-sm text-uppercase" required><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-3"><label>NOMBRE CORTO</label><input type="text" id="spo_quick_supplier_short_name" name="short_name" class="form-control form-control-sm text-uppercase"><span class="invalid-feedback"></span></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-7"><label>DIRECCIÓN</label><input type="text" id="spo_quick_supplier_address" name="address" class="form-control form-control-sm text-uppercase"><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-5"><label>UBIGEO</label><select id="spo_quick_supplier_ubigeo_id" name="ubigeo_id" class="form-control form-control-sm"><option value="">Buscar ubigeo...</option></select><span class="invalid-feedback"></span></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>TIPO PROVEEDOR <span class="text-danger">*</span></label><select name="supplier_type" class="form-control form-control-sm" required><option value="">Seleccione</option><option value="NACIONAL">NACIONAL</option><option value="IMPORTADOR">IMPORTADOR</option><option value="DISTRIBUIDOR">DISTRIBUIDOR</option><option value="FABRICANTE">FABRICANTE</option><option value="LABORATORIO">LABORATORIO</option><option value="OTRO">OTRO</option></select><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-4"><label>CONDICIÓN DE PAGO <span class="text-danger">*</span></label><select name="payment_condition" class="form-control form-control-sm" required><option value="">Seleccione</option><option value="CONTADO">CONTADO</option><option value="CREDITO">CRÉDITO</option></select><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-4"><label>IGV %</label><input type="number" name="igv_percentage" class="form-control form-control-sm" value="18.00" min="0" step="0.01"><span class="invalid-feedback"></span></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>CONTACTO</label><input type="text" name="contact_name" class="form-control form-control-sm text-uppercase"><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-4"><label>CORREO</label><input type="email" name="email" class="form-control form-control-sm"><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-4"><label>TELÉFONO</label><input type="text" name="phone" class="form-control form-control-sm"><span class="invalid-feedback"></span></div>
                    </div>
                    <div class="card border-0 shadow-sm mt-2 mb-2 quick-bank-card">
                        <div class="card-header bg-white py-2 font-weight-bold text-success"><i class="fas fa-university mr-1"></i> Cuenta bancaria principal</div>
                        <div class="card-body py-2">
                            <div class="form-row">
                                <div class="form-group col-md-4"><label>BANCO <span class="text-danger">*</span></label><select name="bank_account[bank_id]" class="form-control form-control-sm" required><option value="">Seleccione</option>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->short_name ?: $bank->description }}</option>@endforeach</select><span class="invalid-feedback"></span></div>
                                <div class="form-group col-md-3"><label>MONEDA <span class="text-danger">*</span></label><select name="bank_account[currency_id]" class="form-control form-control-sm" required><option value="">Seleccione</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }} | {{ $currency->description }}</option>@endforeach</select><span class="invalid-feedback"></span></div>
                                <div class="form-group col-md-2"><label>DETRACCIÓN</label><select name="bank_account[is_detraction]" class="form-control form-control-sm" required><option value="NO">NO</option><option value="YES">SÍ</option></select></div>
                                <div class="form-group col-md-3"><label>TITULAR <span class="text-danger">*</span></label><input type="text" name="bank_account[account_holder]" class="form-control form-control-sm text-uppercase" required><span class="invalid-feedback"></span></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-4"><label>NRO CUENTA <span class="text-danger">*</span></label><input type="text" name="bank_account[account_number]" class="form-control form-control-sm" required><span class="invalid-feedback"></span></div>
                                <div class="form-group col-md-4"><label>CCI</label><input type="text" name="bank_account[cci]" class="form-control form-control-sm"><span class="invalid-feedback"></span></div>
                                <div class="form-group col-md-4"><label>OBSERVACIÓN</label><input type="text" name="bank_account[observation]" class="form-control form-control-sm text-uppercase"><span class="invalid-feedback"></span></div>
                            </div>
                        </div>
                    </div>
                    <span class="badge badge-success px-3 py-2">ESTADO: ACTIVO</span>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cerrar</button>
                    <button type="submit" id="btnSaveQuickSupplierForOrder" class="btn btn-success btn-sm"><i class="fas fa-save mr-1"></i> Guardar proveedor</button>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    .supplier-order-quick-modal { z-index: 1065; }
    .supplier-order-quick-modal + .modal-backdrop { z-index: 1060; }
    #quickSupplierForOrderModal .modal-content { border-radius: 14px; overflow: hidden; }
    #quickSupplierForOrderModal .quick-supplier-order-header { background: linear-gradient(135deg, #198754, #146c43); }
    #quickSupplierForOrderModal .quick-supplier-order-icon { display:flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:10px;background:rgba(255,255,255,.16);font-size:19px; }
    #quickSupplierForOrderModal label { margin-bottom:2px;color:#495057;font-size:11px;font-weight:700; }
    #quickSupplierForOrderModal .form-control { height:31px;font-size:12px; }
    #quickSupplierForOrderModal .quick-bank-card { border: 1px solid #d7eee1 !important; }
</style>
