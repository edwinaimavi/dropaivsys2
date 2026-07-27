<div class="modal fade petty-approved-modal" id="pettyCashApprovedAmountModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content border-0">
            <form id="pettyCashApprovedAmountForm">
                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <span class="petty-approved-modal-icon"><i class="fas fa-hand-holding-usd"></i></span>
                        <div>
                            <small>CONFIGURACIÓN FINANCIERA</small>
                            <h5 class="mb-0">Configurar monto aprobado</h5>
                            <p class="mb-0">Defina el monto autorizado para la apertura de caja chica.</p>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-7">
                            <label>Empresa *</label>
                            <select name="company_id" id="pca_company_id" class="form-control" required>
                                <option value="">Seleccione empresa</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->trade_name ?? $company->business_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-5">
                            <label>Moneda *</label>
                            <select name="currency_id" id="pca_currency_id" class="form-control" required>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}" data-symbol="{{ $currency->symbol ?: $currency->code }}" @selected($currency->id === $defaultCurrencyId)>{{ $currency->code }} | {{ $currency->description }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-7">
                            <label>Monto aprobado *</label>
                            <div class="petty-approved-input"><span id="pca_currency_symbol">S/</span><input type="number" name="amount" id="pca_amount" class="form-control" min="0.01" step="0.01" required></div>
                        </div>
                        <div class="form-group col-md-5">
                            <label>Estado *</label>
                            <select name="active" id="pca_active" class="form-control" required>
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label>Observación</label>
                        <textarea name="observation" id="pca_observation" class="form-control" rows="3" maxlength="1000" placeholder="Información adicional de la autorización..."></textarea>
                    </div>
                    <section id="pca_approval_info" class="petty-approval-trace approval-info-card d-none">
                        <div class="petty-approval-trace-title approval-section-header"><i class="fas fa-user-check approval-section-icon"></i><div><strong class="approval-section-title">Información de aprobación</strong><small class="approval-section-subtitle">Última autorización registrada</small></div></div>
                        <div class="petty-approval-trace-grid">
                            <div class="approval-info-item"><small>Empresa</small><strong id="pca_info_company">-</strong></div>
                            <div class="approval-info-item"><small>Moneda</small><strong id="pca_info_currency">-</strong></div>
                            <div class="approval-info-item is-amount"><small>Monto actual</small><strong id="pca_info_amount">-</strong></div>
                            <div class="approval-info-item"><small>Estado</small><strong id="pca_info_status">-</strong></div>
                            <div class="approval-info-item"><small>Fecha de aprobación</small><strong id="pca_info_approved_at">-</strong></div>
                            <div class="approval-info-item"><small>Aprobado por</small><strong id="pca_info_approved_by">-</strong></div>
                            <div class="petty-approval-trace-notes approval-info-item"><small>Observación</small><strong id="pca_info_observation">-</strong></div>
                        </div>
                    </section>
                    <section id="pca_history_section" class="petty-approval-history approved-history-card d-none">
                        <div class="petty-approval-trace-title approval-section-header"><i class="fas fa-history approval-section-icon"></i><div><strong class="approval-section-title">Historial de montos aprobados</strong><small class="approval-section-subtitle">Cada autorización se conserva de forma independiente</small></div></div>
                        <div class="table-responsive approved-history-table-wrapper">
                            <table class="table mb-0 approved-history-table">
                                <thead><tr><th>Fecha</th><th>Usuario</th><th class="text-right">Monto anterior</th><th class="text-right">Monto aprobado</th><th>Moneda</th><th>Observación</th></tr></thead>
                                <tbody id="pca_history_body"></tbody>
                            </table>
                        </div>
                    </section>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
