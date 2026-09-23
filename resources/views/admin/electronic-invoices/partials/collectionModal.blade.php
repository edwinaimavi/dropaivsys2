<div class="modal fade dp-detail-modal electronic-invoice-collection-modal" id="electronicInvoiceCollectionModal"
    tabindex="-1" role="dialog" aria-labelledby="electronicInvoiceCollectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered electronic-invoice-collection-dialog" role="document">
        <form id="electronicInvoiceCollectionForm" class="modal-content border-0 electronic-invoice-collection" enctype="multipart/form-data">
            @csrf
            <input type="hidden" id="eic_invoice_id">
            <input type="hidden" id="eic_idempotency_key" name="idempotency_key">

            <div class="modal-header dp-detail-modal-header electronic-invoice-collection__header">
                <div class="dp-detail-modal-heading">
                    <span class="dp-detail-modal-icon electronic-invoice-collection__header-icon" aria-hidden="true">
                        <i class="fas fa-university"></i>
                    </span>
                    <div class="dp-detail-modal-copy">
                        <h5 class="modal-title dp-detail-modal-title" id="electronicInvoiceCollectionModalLabel">
                            Confirmar ingreso bancario
                        </h5>
                        <small class="dp-detail-modal-subtitle">Registre el cobro y su constancia en la cuenta bancaria seleccionada</small>
                    </div>
                </div>
                <button type="button" class="close dp-detail-modal-close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body electronic-invoice-collection__body">
                <div id="electronicInvoiceCollectionErrors" class="alert alert-danger d-none"></div>

                <section class="electronic-invoice-collection-summary" aria-label="Resumen del comprobante">
                    <div class="electronic-invoice-collection-summary__item">
                        <span class="electronic-invoice-collection-summary__icon" aria-hidden="true"><i class="fas fa-file-invoice"></i></span>
                        <div>
                            <small>Comprobante</small>
                            <strong id="eic_invoice_number">-</strong>
                        </div>
                    </div>
                    <div class="electronic-invoice-collection-summary__item">
                        <span class="electronic-invoice-collection-summary__icon" aria-hidden="true"><i class="fas fa-coins"></i></span>
                        <div>
                            <small>Total</small>
                            <strong id="eic_invoice_total">0.00</strong>
                        </div>
                    </div>
                    <div class="electronic-invoice-collection-summary__item electronic-invoice-collection-summary__item--pending">
                        <span class="electronic-invoice-collection-summary__icon" aria-hidden="true"><i class="far fa-clock"></i></span>
                        <div>
                            <small>Saldo pendiente</small>
                            <strong id="eic_invoice_pending">0.00</strong>
                        </div>
                    </div>
                </section>

                <section class="electronic-invoice-collection-section" aria-labelledby="eic_destination_title">
                    <div class="electronic-invoice-collection-section__heading">
                        <span aria-hidden="true"><i class="fas fa-landmark"></i></span>
                        <div>
                            <h6 id="eic_destination_title">Destino del ingreso</h6>
                            <p>Seleccione la cuenta donde se recibi&oacute; el cobro.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="eic_company_bank_account_id">Banco / cuenta bancaria destino *</label>
                            <select id="eic_company_bank_account_id" name="company_bank_account_id"
                                class="form-control form-control-sm" required>
                                <option value="">Seleccione cuenta bancaria</option>
                                @foreach ($bankAccounts as $account)
                                    <option value="{{ $account->id }}" data-company-id="{{ $account->company_id }}"
                                        data-currency-id="{{ $account->currency_id }}" data-currency-code="{{ $account->currency?->code }}">
                                        {{ $account->company?->business_name ?: ($account->account_holder ?: 'Empresa '.$account->company_id) }} — {{ $account->bank?->short_name ?? $account->bank?->description }} - {{ $account->currency?->code }} - {{ $account->account_number }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">La cuenta debe pertenecer a la empresa emisora del comprobante.</small>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="eic_collection_date">Fecha de cobro *</label>
                            <input id="eic_collection_date" name="collection_date" type="date"
                                class="form-control form-control-sm" required>
                        </div>
                    </div>
                </section>

                <section class="electronic-invoice-collection-section" aria-labelledby="eic_detail_title">
                    <div class="electronic-invoice-collection-section__heading">
                        <span aria-hidden="true"><i class="fas fa-hand-holding-usd"></i></span>
                        <div>
                            <h6 id="eic_detail_title">Detalle del cobro</h6>
                            <p>Indique el importe recibido y la referencia bancaria.</p>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label for="eic_currency_id">Moneda de cobro *</label>
                            <select id="eic_currency_id" name="currency_id" class="form-control form-control-sm" required>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->id }}" data-code="{{ $currency->code }}">
                                        {{ $currency->code }} - {{ $currency->description }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="eic_amount">Monto cobrado *</label>
                            <div class="electronic-invoice-collection-input-icon">
                                <i class="fas fa-coins" aria-hidden="true"></i>
                                <input id="eic_amount" name="amount" type="number" min="0.01" step="0.01"
                                    class="form-control form-control-sm" required>
                            </div>
                        </div>
                        <div id="eic_exchange_rate_group" class="form-group col-md-4 d-none">
                            <label for="eic_exchange_rate">Tipo de cambio *</label>
                            <input id="eic_exchange_rate" name="exchange_rate" type="number" min="0.000001"
                                step="0.000001" class="form-control form-control-sm">
                        </div>
                        <div class="form-group col-md-4">
                            <label for="eic_operation_number">Nro. operaci&oacute;n</label>
                            <input id="eic_operation_number" name="operation_number" type="text" maxlength="100"
                                class="form-control form-control-sm text-uppercase" placeholder="Ej. OP-000123">
                        </div>
                        <div class="form-group col-md-8">
                            <label>Constancia bancaria</label>
                            <div id="eic_proof_uploader" class="electronic-invoice-file-uploader">
                                <input id="eic_proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp"
                                    class="electronic-invoice-file-uploader__input">
                                <span class="electronic-invoice-file-uploader__icon" aria-hidden="true">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </span>
                                <div class="electronic-invoice-file-uploader__copy" aria-live="polite">
                                    <strong id="eic_proof_name">Adjuntar constancia</strong>
                                    <span id="eic_proof_help">PDF, JPG, PNG o WEBP &middot; M&aacute;ximo 10 MB</span>
                                </div>
                                <label for="eic_proof" id="eic_proof_action" class="electronic-invoice-file-uploader__action">
                                    <i class="fas fa-paperclip mr-1"></i> Seleccionar
                                </label>
                                <button type="button" id="eic_proof_remove"
                                    class="electronic-invoice-file-uploader__remove d-none" aria-label="Quitar archivo"
                                    title="Quitar archivo">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group col-12 mb-0">
                            <label for="eic_observation">Observaci&oacute;n</label>
                            <textarea id="eic_observation" name="observation" rows="2" maxlength="1500"
                                class="form-control form-control-sm"
                                placeholder="Agregue una referencia adicional si es necesario"></textarea>
                        </div>
                    </div>
                </section>
            </div>

            <div class="modal-footer electronic-invoice-collection__footer">
                <button type="button" class="btn electronic-invoice-collection__cancel" data-dismiss="modal">
                    Cancelar
                </button>
                <button id="btnConfirmElectronicInvoiceCollection" type="submit"
                    class="btn btn-success electronic-invoice-collection__confirm">
                    <i class="fas fa-check-circle mr-1"></i> Confirmar cobro e ingreso
                </button>
            </div>
        </form>
    </div>
</div>
