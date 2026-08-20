<div class="modal fade" id="warehouseEntryCreditPaymentModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
        <form id="warehouseEntryCreditPaymentForm" class="modal-content border-0 shadow-lg" novalidate>
            @csrf
            <input type="hidden" id="warehouse_credit_payment_entry_id">
            <input type="hidden" id="warehouse_credit_payment_idempotency_key" name="idempotency_key">

            <div class="modal-header warehouse-credit-payment-header text-white">
                <div>
                    <h5 class="modal-title font-weight-bold">
                        <i class="fas fa-money-check-alt mr-2"></i>
                        Registrar pago de cr&eacute;dito
                    </h5>
                    <small>Selecciona la cuenta bancaria de salida y registra la constancia del pago.</small>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="warehouse-credit-payment-debt-grid mb-3">
                    <div><small>Ingreso</small><strong id="warehouseCreditPaymentEntryCode">-</strong></div>
                    <div><small>OC proveedor</small><strong id="warehouseCreditPaymentOrderCode">-</strong></div>
                    <div><small>Proveedor</small><strong id="warehouseCreditPaymentSupplier">-</strong></div>
                    <div><small>Empresa</small><strong id="warehouseCreditPaymentCompany">-</strong></div>
                    <div><small>Condici&oacute;n</small><strong id="warehouseCreditPaymentCondition">-</strong></div>
                    <div><small>Vencimiento</small><strong id="warehouseCreditPaymentDueDate">-</strong></div>
                    <div><small>Total compra</small><strong id="warehouseCreditPaymentTotal">-</strong></div>
                    <div><small>Pagado</small><strong id="warehouseCreditPaymentPaid">-</strong></div>
                    <div class="is-pending"><small>Saldo pendiente</small><strong id="warehouseCreditPaymentPending">-</strong></div>
                </div>

                <div class="row">
                    <div class="form-group col-md-5">
                        <label>MONEDA DEL PAGO *</label>
                        <select id="warehouse_credit_payment_currency_id" name="payment_currency_id" class="form-control form-control-sm">
                            <option value="">Seleccione moneda</option>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}" data-code="{{ $currency->code }}" data-symbol="{{ $currency->symbol }}">
                                    {{ $currency->code }} - {{ $currency->description }}
                                </option>
                            @endforeach
                        </select>
                        <span class="invalid-feedback"></span>
                    </div>
                    <div class="form-group col-md-7">
                        <label>BANCO / CUENTA BANCARIA DE SALIDA *</label>
                        <select id="warehouse_credit_payment_bank_account_id" name="company_bank_account_id" class="form-control form-control-sm">
                            <option value="">Seleccione cuenta bancaria</option>
                        </select>
                        <small id="warehouseCreditPaymentBankHelp" class="form-text text-muted">Las cuentas se filtran por empresa y moneda del pago.</small>
                        <span class="invalid-feedback"></span>
                    </div>
                    <div class="form-group col-sm-6 col-md-4">
                        <label>MONTO APLICADO A LA DEUDA *</label>
                        <input type="number" id="warehouse_credit_payment_applied_amount" name="applied_amount"
                            class="form-control form-control-sm text-right" min="0.0001" step="0.0001">
                        <small class="form-text text-muted">Expresado en la moneda de la compra.</small>
                        <span class="invalid-feedback"></span>
                    </div>
                    <div id="warehouseCreditPaymentExchangeRateGroup" class="form-group col-sm-6 col-md-4">
                        <label>TIPO DE CAMBIO *</label>
                        <input type="number" id="warehouse_credit_payment_exchange_rate" name="exchange_rate"
                            class="form-control form-control-sm text-right" min="0.000001" step="0.000001" value="1">
                        <small class="form-text text-muted">Soles por cada USD.</small>
                        <span class="invalid-feedback"></span>
                    </div>
                    <div class="form-group col-md-4">
                        <label>MONTO QUE SALE DEL BANCO</label>
                        <div class="input-group input-group-sm">
                            <div class="input-group-prepend"><span id="warehouseCreditPaymentOutputSymbol" class="input-group-text">-</span></div>
                            <input type="text" id="warehouse_credit_payment_amount" class="form-control text-right font-weight-bold" value="0.00" readonly>
                        </div>
                    </div>
                    <div class="form-group col-sm-6 col-md-4">
                        <label>FECHA DE PAGO *</label>
                        <input type="date" id="warehouse_credit_payment_date" name="payment_date"
                            class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                        <span class="invalid-feedback"></span>
                    </div>
                    <div class="form-group col-sm-6 col-md-4">
                        <label>MEDIO DE PAGO *</label>
                        <select id="warehouse_credit_payment_method" name="payment_method" class="form-control form-control-sm">
                            <option value="transferencia">Transferencia</option>
                            <option value="deposito">Dep&oacute;sito</option>
                            <option value="cheque">Cheque</option>
                            <option value="otro">Otro</option>
                        </select>
                        <span class="invalid-feedback"></span>
                    </div>
                    <div class="form-group col-md-4">
                        <label>NRO. OPERACI&Oacute;N / CONSTANCIA *</label>
                        <input type="text" id="warehouse_credit_payment_operation_number" name="operation_number"
                            class="form-control form-control-sm text-uppercase" maxlength="100">
                        <span class="invalid-feedback"></span>
                    </div>
                    <div class="form-group col-md-6">
                        <label>ARCHIVO DE CONSTANCIA</label>
                        <div class="custom-file custom-file-sm">
                            <input type="file" id="warehouse_credit_payment_proof" name="proof"
                                class="custom-file-input" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            <label class="custom-file-label" for="warehouse_credit_payment_proof">Seleccionar archivo</label>
                        </div>
                        <span class="invalid-feedback"></span>
                    </div>
                    <div class="form-group col-md-6">
                        <label>OBSERVACI&Oacute;N</label>
                        <textarea id="warehouse_credit_payment_observation" name="observation"
                            class="form-control form-control-sm text-uppercase" rows="2" maxlength="1500"></textarea>
                        <span class="invalid-feedback"></span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i>Cancelar
                </button>
                <button type="submit" id="btnSaveWarehouseCreditPayment" class="btn btn-warning btn-sm">
                    <i class="fas fa-save mr-1"></i>Guardar pago
                </button>
            </div>
        </form>
    </div>
</div>
