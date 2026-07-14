<div class="modal fade supplier-order-quick-modal" id="quickSupplierAccountForOrderModal" tabindex="-1"
    data-backdrop="static" data-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <div><h5 class="mb-0 font-weight-bold"><i class="fas fa-university mr-1"></i> Nueva Cuenta Bancaria</h5><small class="text-white-50">Cuenta bancaria del proveedor seleccionado</small></div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form id="quickSupplierAccountForOrderForm" autocomplete="off">
                @csrf
                <div class="modal-body bg-light">
                    <div id="quickSupplierAccountForOrderErrors" class="alert alert-danger d-none"></div>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>BANCO <span class="text-danger">*</span></label><select name="bank_id" class="form-control form-control-sm" required><option value="">Seleccione</option>@foreach($banks as $bank)<option value="{{ $bank->id }}">{{ $bank->short_name ?: $bank->description }}</option>@endforeach</select><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-3"><label>MONEDA <span class="text-danger">*</span></label><select name="currency_id" class="form-control form-control-sm" required><option value="">Seleccione</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }} | {{ $currency->description }}</option>@endforeach</select><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-2"><label>DETRACCIÓN</label><select name="is_detraction" class="form-control form-control-sm" required><option value="NO">NO</option><option value="YES">SÍ</option></select></div>
                        <div class="form-group col-md-3"><label>TITULAR <span class="text-danger">*</span></label><input type="text" name="account_holder" class="form-control form-control-sm text-uppercase" required><span class="invalid-feedback"></span></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4"><label>NRO CUENTA <span class="text-danger">*</span></label><input type="text" name="account_number" class="form-control form-control-sm" required><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-4"><label>CCI</label><input type="text" name="cci" class="form-control form-control-sm"><span class="invalid-feedback"></span></div>
                        <div class="form-group col-md-4"><label>OBSERVACIÓN</label><input type="text" name="observation" class="form-control form-control-sm text-uppercase"><span class="invalid-feedback"></span></div>
                    </div>
                </div>
                <div class="modal-footer bg-white"><button type="button" class="btn btn-light border btn-sm" data-dismiss="modal">Cerrar</button><button type="submit" id="btnSaveQuickSupplierAccountForOrder" class="btn btn-success btn-sm"><i class="fas fa-save mr-1"></i> Guardar cuenta</button></div>
            </form>
        </div>
    </div>
</div>
<style>
    #quickSupplierAccountForOrderModal .modal-content { border-radius:14px;overflow:hidden; }
    #quickSupplierAccountForOrderModal label { margin-bottom:2px;color:#495057;font-size:11px;font-weight:700; }
    #quickSupplierAccountForOrderModal .form-control { height:31px;font-size:12px; }
</style>
