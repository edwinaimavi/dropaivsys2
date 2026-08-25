<div class="modal fade" id="electronicInvoiceCollectionModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <form id="electronicInvoiceCollectionForm" class="modal-content border-0 shadow" enctype="multipart/form-data">
            @csrf
            <input type="hidden" id="eic_invoice_id">
            <input type="hidden" id="eic_idempotency_key" name="idempotency_key">
            <div class="modal-header bg-success text-white border-0">
                <div>
                    <h5 class="modal-title font-weight-bold"><i class="fas fa-university mr-2"></i>Confirmar ingreso bancario</h5>
                    <small>El movimiento bancario se crear&aacute; solamente al guardar esta confirmaci&oacute;n.</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <div id="electronicInvoiceCollectionErrors" class="alert alert-danger d-none"></div>
                <div class="row mb-3">
                    <div class="col-md-4"><small class="text-muted d-block">COMPROBANTE</small><strong id="eic_invoice_number">-</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">TOTAL</small><strong id="eic_invoice_total">0.00</strong></div>
                    <div class="col-md-4"><small class="text-muted d-block">SALDO PENDIENTE</small><strong id="eic_invoice_pending" class="text-danger">0.00</strong></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-7">
                        <label>Banco / cuenta bancaria destino *</label>
                        <select id="eic_company_bank_account_id" name="company_bank_account_id" class="form-control form-control-sm" required>
                            <option value="">Seleccione cuenta bancaria</option>
                            @foreach ($bankAccounts as $account)
                                <option value="{{ $account->id }}" data-company-id="{{ $account->company_id }}"
                                    data-currency-id="{{ $account->currency_id }}" data-currency-code="{{ $account->currency?->code }}">
                                    {{ $account->bank?->short_name ?? $account->bank?->description }} - {{ $account->currency?->code }} - {{ $account->account_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-5">
                        <label>Fecha de cobro *</label>
                        <input id="eic_collection_date" name="collection_date" type="date" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Moneda de cobro *</label>
                        <select id="eic_currency_id" name="currency_id" class="form-control form-control-sm" required>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" data-code="{{ $currency->code }}">{{ $currency->code }} - {{ $currency->description }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Monto cobrado *</label>
                        <input id="eic_amount" name="amount" type="number" min="0.01" step="0.01" class="form-control form-control-sm" required>
                    </div>
                    <div id="eic_exchange_rate_group" class="form-group col-md-4 d-none">
                        <label>Tipo de cambio *</label>
                        <input id="eic_exchange_rate" name="exchange_rate" type="number" min="0.000001" step="0.000001" class="form-control form-control-sm">
                    </div>
                    <div class="form-group col-md-5">
                        <label>Nro. operaci&oacute;n</label>
                        <input name="operation_number" type="text" maxlength="100" class="form-control form-control-sm text-uppercase">
                    </div>
                    <div class="form-group col-md-7">
                        <label>Constancia bancaria</label>
                        <input name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp" class="form-control-file">
                    </div>
                    <div class="form-group col-12">
                        <label>Observaci&oacute;n</label>
                        <textarea name="observation" rows="2" maxlength="1500" class="form-control form-control-sm"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light border" data-dismiss="modal">Cancelar</button>
                <button id="btnConfirmElectronicInvoiceCollection" type="submit" class="btn btn-success">
                    <i class="fas fa-check-circle mr-1"></i> Confirmar cobro e ingreso
                </button>
            </div>
        </form>
    </div>
</div>
