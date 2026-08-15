<div class="modal fade petty-cash-modal petty-cash-open-modal" id="pettyCashModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 petty-modal-content petty-cash-premium">
            <form id="pettyCashForm">
                @csrf
                <input type="hidden" id="petty_cash_id">
                <input type="hidden" name="previous_petty_cash_id" id="pc_previous_petty_cash_id">

                <div class="modal-header petty-premium-header">
                    <div class="d-flex align-items-center">
                        <span class="petty-header-icon"><i class="fas fa-wallet"></i></span>
                        <div>
                            <span class="petty-eyebrow">GESTIÓN FINANCIERA</span>
                            <h4 id="pettyCashModalLabel" class="mb-1">Aperturar caja chica</h4>
                            <p class="mb-0">Configura el periodo, fondo disponible y responsables de la caja.</p>
                        </div>
                    </div>
                    <div class="petty-approved-header-actions">
                        <div class="petty-approved-reference">
                            <span><i class="fas fa-hand-holding-usd"></i></span>
                            <div><small>MONTO APROBADO</small><strong id="pc_approved_amount_display">Sin asignar</strong><em id="pc_approved_amount_caption">Seleccione empresa y moneda</em></div>
                            @can('admin.petty-cash.approved-amount.update')
                                <button type="button" id="btnConfigureApprovedAmountFromOpening" title="Configurar monto aprobado" aria-label="Configurar monto aprobado"><i class="fas fa-pen"></i></button>
                            @endcan
                        </div>
                        <button type="button" class="close petty-close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
                    </div>
                </div>

                <div class="modal-body petty-premium-body">
                    <div class="row">
                        <div class="col-lg-4 mb-3 mb-lg-0">
                            <aside class="petty-finance-summary">
                                <div class="petty-summary-hero">
                                    <span class="petty-summary-icon"><i class="fas fa-cash-register"></i></span>
                                    <small>CÓDIGO INTERNO</small>
                                    <strong id="pc_side_code">Se generará al guardar</strong>
                                    <span id="pc_side_status" class="badge badge-success">ABIERTA</span>
                                </div>
                                <div class="petty-summary-list">
                                    <div><span>Saldo disponible previo</span><b id="pc_side_previous">0.00</b></div>
                                    <div><span>Fondo por reponer</span><b id="pc_side_fund">0.00</b></div>
                                    <div class="petty-opening-total"><span>Fondo inicial</span><b id="pc_side_opening">0.00</b></div>
                                    <div><span>Total gastado</span><b id="pc_side_expenses">0.00</b></div>
                                </div>
                                <div class="petty-current-balance">
                                    <span>SALDO ACTUAL</span>
                                    <strong id="pc_side_balance">0.00</strong>
                                    <small>Disponible en caja</small>
                                </div>
                                <div class="petty-summary-note"><i class="fas fa-shield-alt"></i><span>Los importes se recalculan automáticamente.</span></div>
                            </aside>
                        </div>

                        <div class="col-lg-8">
                            <section class="petty-premium-card">
                                <div class="petty-section-heading">
                                    <span><i class="fas fa-building"></i></span>
                                    <div><h6>Datos principales</h6><small>Empresa, moneda y fecha real de apertura</small></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-5"><label>Empresa *</label><select name="company_id" id="pc_company_id" class="form-control" required><option value="">Seleccione empresa</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->trade_name ?? $company->business_name }}</option>@endforeach</select></div>
                                    <div class="form-group col-md-3"><label>Moneda *</label><select name="currency_id" id="pc_currency_id" class="form-control" data-default-currency-id="{{ $defaultCurrencyId }}" required>@foreach($currencies as $currency)<option value="{{ $currency->id }}" data-symbol="{{ $currency->symbol ?: $currency->code }}" @selected($currency->id === $defaultCurrencyId)>{{ $currency->code }} | {{ $currency->description }}</option>@endforeach</select></div>
                                    <div class="form-group col-md-4"><label>Fecha de apertura *</label><input type="date" name="start_date" id="pc_start_date" class="form-control" required><small class="petty-field-help">Fecha real en que se entrega el fondo de caja.</small></div>
                                </div>
                            </section>

                            <section class="petty-premium-card petty-fund-card">
                                <div class="petty-section-heading">
                                    <span><i class="fas fa-coins"></i></span>
                                    <div><h6>Fondo de apertura</h6><small>Composición del dinero disponible al iniciar</small></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4"><label>Saldo disponible actual</label><div class="petty-money-input"><span>S/</span><input type="number" name="previous_balance" id="pc_previous_balance" class="form-control" min="0" step="0.01" value="0" readonly></div><small class="petty-field-help">Dinero disponible antes de completar el fondo.</small></div>
                                    <div class="form-group col-md-4"><label>Fondo por reponer</label><div class="petty-money-input"><span>S/</span><input type="number" name="approved_fund" id="pc_approved_fund" class="form-control" min="0" step="0.01" value="0" readonly></div><small class="petty-field-help">Monto necesario para completar nuevamente el fondo aprobado.</small></div>
                                    <div class="form-group col-md-4"><label>Fondo disponible inicial</label><div class="petty-money-input petty-money-total"><span>S/</span><input type="text" id="pc_opening_amount" class="form-control" value="0.00" readonly></div></div>
                                </div>
                                <div class="petty-balance-help"><i class="fas fa-info-circle"></i><span id="pc_previous_balance_message">Seleccione empresa y moneda para calcular el fondo inicial.</span></div>
                                <div id="pc_approved_amount_warning" class="petty-approved-warning d-none"><i class="fas fa-exclamation-triangle"></i><span>El fondo por reponer supera el monto aprobado.</span></div>
                            </section>

                            <section id="pc_fund_source_section" class="petty-premium-card petty-fund-source-card d-none">
                                <div class="petty-section-heading">
                                    <span><i class="fas fa-university"></i></span>
                                    <div><h6>Origen del fondo</h6><small>Define de dónde proviene el dinero inicial de esta caja</small></div>
                                </div>
                                <div id="pc_carried_balance_source" class="alert alert-light border d-none">
                                    <div class="d-flex align-items-start">
                                        <i class="fas fa-link text-info mt-1 mr-3"></i>
                                        <div>
                                            <small class="d-block text-uppercase text-muted">Origen automático</small>
                                            <strong>Saldo arrastrado de caja anterior</strong>
                                            <div class="mt-1">Caja: <b id="pc_carried_balance_box">-</b> · Monto: <b id="pc_carried_balance_amount">S/ 0.00</b></div>
                                        </div>
                                    </div>
                                </div>
                                <div id="pc_replenishment_source_fields">
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label>Empresa origen *</label>
                                        <select name="fund_source_company_id" id="pc_fund_source_company_id" class="form-control">
                                            <option value="">Seleccione empresa</option>
                                            @foreach($companies as $company)
                                                <option value="{{ $company->id }}">{{ $company->trade_name ?? $company->business_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>Cuenta bancaria origen *</label>
                                        <select name="fund_source_bank_account_id" id="pc_fund_source_bank_account_id" class="form-control" disabled>
                                            <option value="">Seleccione primero una empresa</option>
                                        </select>
                                        <small id="pc_fund_source_account_help" class="petty-source-help"></small>
                                    </div>
                                    <div class="form-group col-md-4 d-none" id="pc_fund_source_exchange_rate_group">
                                        <label>Tipo de cambio *</label>
                                        <input type="number" step="0.000001" min="0.000001" name="fund_source_exchange_rate" id="pc_fund_source_exchange_rate" class="form-control">
                                        <small class="petty-source-help">Necesario para normalizar la salida bancaria en soles.</small>
                                    </div>
                                </div>
                                <label class="petty-source-upload" for="pc_fund_source_receipts">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span><strong>Arrastra o selecciona comprobantes</strong><small>PDF, JPG, JPEG o PNG hasta 10 MB por archivo</small></span>
                                    <input type="file" name="fund_source_receipts[]" id="pc_fund_source_receipts" accept=".pdf,.jpg,.jpeg,.png" multiple>
                                </label>
                                <div id="pc_fund_source_previews" class="petty-source-previews"></div>
                                </div>
                            </section>

                            <section class="petty-premium-card">
                                <div class="petty-section-heading">
                                    <span><i class="fas fa-user-shield"></i></span>
                                    <div><h6>Responsables</h6><small>Custodia y supervisión del fondo</small></div>
                                </div>
                                <div class="petty-person-row">
                                    <div class="form-row">
                                        <div class="form-group col-md-4"><label>DNI responsable * <i id="responsible_dni_loading" class="fas fa-spinner fa-spin text-success d-none"></i></label><input name="responsible_dni" id="responsible_dni" class="form-control" maxlength="8" inputmode="numeric" autocomplete="off" placeholder="8 dígitos" required></div>
                                        <div class="form-group col-md-8"><label>Responsable caja chica *</label><input name="responsible_name" id="responsible_name" class="form-control text-uppercase" placeholder="Nombre completo" required></div>
                                    </div>
                                </div>
                                <div class="petty-person-row">
                                    <div class="form-row">
                                        <div class="form-group col-md-4"><label>DNI supervisor * <i id="supervisor_dni_loading" class="fas fa-spinner fa-spin text-success d-none"></i></label><input name="supervisor_dni" id="supervisor_dni" class="form-control" maxlength="8" inputmode="numeric" autocomplete="off" placeholder="8 dígitos" required></div>
                                        <div class="form-group col-md-8"><label>Supervisor *</label><input name="supervisor_name" id="supervisor_name" class="form-control text-uppercase" placeholder="Nombre completo" required></div>
                                    </div>
                                </div>
                            </section>

                            <section class="petty-premium-card mb-0">
                                <div class="petty-section-heading">
                                    <span><i class="fas fa-align-left"></i></span>
                                    <div><h6>Observación</h6><small>Información adicional de la apertura</small></div>
                                </div>
                                <textarea name="observations" id="pc_observations" class="form-control text-uppercase petty-observation" rows="3" placeholder="Escriba una observación si corresponde..."></textarea>
                            </section>
                        </div>
                    </div>
                </div>

                <div class="modal-footer petty-premium-footer">
                    <button type="button" class="btn btn-light petty-btn-secondary" data-dismiss="modal"><i class="fas fa-times mr-1"></i> Cerrar</button>
                    <button id="btnSavePettyCash" type="submit" class="btn btn-success petty-btn-primary"><i class="fas fa-save mr-1"></i> <span>Guardar Caja</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
