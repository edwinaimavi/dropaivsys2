<div class="modal fade" id="pettyCashModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content border-0 petty-modal-content">
            <form id="pettyCashForm">
                <input type="hidden" id="petty_cash_id">
                <div class="modal-header petty-modal-header">
                    <div><small>GESTIÓN FINANCIERA</small><h4 id="pettyCashModalLabel">Aperturar caja chica</h4><p>Control del fondo, periodo y responsables.</p></div>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="petty-form-card">
                                <h6><i class="fas fa-building"></i> Datos principales</h6>
                                <div class="form-row">
                                    <div class="form-group col-md-6"><label>Empresa *</label><select name="company_id" id="pc_company_id" class="form-control" required><option value="">Seleccione empresa</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->trade_name ?? $company->business_name }}</option>@endforeach</select></div>
                                    <div class="form-group col-md-3"><label>Moneda *</label><select name="currency_id" id="pc_currency_id" class="form-control" required><option value="">Seleccione</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }} - {{ $currency->description }}</option>@endforeach</select></div>
                                    <div class="form-group col-md-3"><label>Periodicidad *</label><select name="periodicity" id="pc_periodicity" class="form-control" required><option value="WEEKLY">Semanal</option><option value="BIWEEKLY">Quincenal</option><option value="MONTHLY" selected>Mensual</option><option value="OTHER">Otra</option></select></div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-3"><label>Mes *</label><select name="period_month" id="pc_period_month" class="form-control" required>@foreach(range(1,12) as $month)<option value="{{ $month }}">{{ str_pad($month, 2, '0', STR_PAD_LEFT) }}</option>@endforeach</select></div>
                                    <div class="form-group col-md-3"><label>Año *</label><input type="number" name="period_year" id="pc_period_year" class="form-control" min="2020" max="2100" required></div>
                                    <div class="form-group col-md-3"><label>Fecha inicio *</label><input type="date" name="start_date" id="pc_start_date" class="form-control" required></div>
                                    <div class="form-group col-md-3"><label>Fecha final *</label><input type="date" name="end_date" id="pc_end_date" class="form-control" required></div>
                                </div>
                                <div class="form-group"><label>Fondo aprobado por gerencia *</label><input type="number" name="approved_fund" id="pc_approved_fund" class="form-control" min="0.01" step="0.01" required></div>
                            </div>
                            <div class="petty-form-card">
                                <h6><i class="fas fa-user-shield"></i> Responsables</h6>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>DNI responsable * <i id="responsible_dni_loading" class="fas fa-spinner fa-spin text-success d-none"></i></label>
                                        <input name="responsible_dni" id="responsible_dni" class="form-control" maxlength="8" inputmode="numeric" autocomplete="off" required>
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label>Responsable caja chica *</label>
                                        <input name="responsible_name" id="responsible_name" class="form-control text-uppercase" required>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>DNI supervisor * <i id="supervisor_dni_loading" class="fas fa-spinner fa-spin text-success d-none"></i></label>
                                        <input name="supervisor_dni" id="supervisor_dni" class="form-control" maxlength="8" inputmode="numeric" autocomplete="off" required>
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label>Supervisor *</label>
                                        <input name="supervisor_name" id="supervisor_name" class="form-control text-uppercase" required>
                                    </div>
                                </div>
                                <div class="form-group mb-0"><label>Observación</label><textarea name="observations" id="pc_observations" class="form-control text-uppercase" rows="2"></textarea></div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <aside class="petty-side-panel">
                                <span class="petty-side-icon"><i class="fas fa-wallet"></i></span>
                                <small>CÓDIGO</small><strong id="pc_side_code">Se generará al guardar</strong>
                                <div class="petty-side-row"><span>Estado</span><b id="pc_side_status">ABIERTA</b></div>
                                <div class="petty-side-row"><span>Fondo aprobado</span><b id="pc_side_fund">0.00</b></div>
                                <div class="petty-side-row"><span>Total gastado</span><b id="pc_side_expenses">0.00</b></div>
                                <div class="petty-side-row total"><span>Saldo actual</span><b id="pc_side_balance">0.00</b></div>
                            </aside>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button><button id="btnSavePettyCash" type="submit" class="btn btn-success"><i class="fas fa-save mr-1"></i> <span>Guardar Caja</span></button></div>
            </form>
        </div>
    </div>
</div>
