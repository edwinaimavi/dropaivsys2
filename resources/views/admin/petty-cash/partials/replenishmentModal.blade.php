<div class="modal fade petty-replenishment-modal" id="pettyCashReplenishmentModal" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="pettyCashReplenishmentForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" id="pcr_box_id">
                <div class="modal-header petty-replenishment-header">
                    <div class="petty-replenishment-title">
                        <span class="petty-replenishment-header-icon"><i class="fas fa-sync-alt"></i></span>
                        <div><small>GESTIÓN FINANCIERA</small><h4>Reposición de Caja Chica</h4><p>Restituye el fondo utilizado de la caja seleccionada.</p></div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
                </div>
                <div class="modal-body petty-replenishment-body">
                    <section class="petty-replenishment-summary">
                        <div class="petty-replenishment-identity">
                            <div><small>CAJA SELECCIONADA</small><h5 id="pcr_code">-</h5></div>
                            <div class="text-right"><small>EMPRESA</small><strong id="pcr_company">-</strong></div>
                        </div>
                        <div id="pcr_summary" class="petty-replenishment-kpis"></div>
                        <div id="pcr_pending_status" class="petty-replenishment-status"></div>
                        <div id="pcr_no_pending" class="petty-replenishment-status is-complete d-none"><i class="fas fa-check-circle"></i><span>Esta caja no tiene monto pendiente de reposición.</span></div>
                    </section>
                    <section class="petty-replenishment-section">
                        <div class="petty-replenishment-section-title">
                            <span><i class="far fa-calendar-check"></i></span>
                            <div><h6>Datos de la reposición</h6><small>Indique cuándo y cuánto se restituirá a la caja</small></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6"><label>Fecha de reposición *</label><input type="date" name="replenishment_date" id="pcr_date" class="form-control" required></div>
                            <div class="form-group col-md-6"><label>Monto a reponer *</label><div class="petty-replenishment-amount"><i class="fas fa-coins"></i><input type="number" name="amount" id="pcr_amount" class="form-control" min="0.01" step="0.01" required></div></div>
                        </div>
                        <div id="pcr_excess_warning" class="alert alert-warning d-none"><i class="fas fa-exclamation-triangle mr-1"></i> El monto a reponer supera el pendiente calculado. Revise si corresponde.</div>
                    </section>
                    <section class="petty-replenishment-section petty-replenishment-source">
                        <div class="petty-replenishment-section-title">
                            <span><i class="fas fa-university"></i></span>
                            <div><h6>Origen de la reposición</h6><small>Empresa y cuenta desde donde se restituye el fondo</small></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-6"><label>Empresa origen *</label><select name="fund_source_company_id" id="pcr_fund_source_company_id" class="form-control" required><option value="">Seleccione empresa</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->trade_name ?? $company->business_name }}</option>@endforeach</select></div>
                            <div class="form-group col-md-6"><label>Cuenta bancaria origen *</label><select name="fund_source_bank_account_id" id="pcr_fund_source_bank_account_id" class="form-control" required disabled><option value="">Seleccione primero una empresa</option></select><small id="pcr_fund_source_account_help" class="petty-source-help"></small></div>
                            <div class="form-group col-md-4 d-none" id="pcr_fund_source_exchange_rate_group"><label>Tipo de cambio *</label><input type="number" step="0.000001" min="0.000001" name="fund_source_exchange_rate" id="pcr_fund_source_exchange_rate" class="form-control"><small class="petty-source-help">Necesario para normalizar la salida bancaria en soles.</small></div>
                        </div>
                        <label class="petty-source-upload petty-replenishment-upload" for="pcr_fund_source_receipts">
                            <i class="fas fa-cloud-upload-alt"></i><span><strong>Seleccionar comprobantes de reposición</strong><small>PDF, JPG, JPEG o PNG hasta 10 MB</small></span>
                            <input type="file" name="fund_source_receipts[]" id="pcr_fund_source_receipts" accept=".pdf,.jpg,.jpeg,.png" multiple>
                        </label>
                        <div id="pcr_fund_source_previews" class="petty-source-previews"></div>
                    </section>
                    <section class="petty-replenishment-section petty-replenishment-observation">
                        <div class="form-group mb-0"><label>Observación</label><textarea name="observation" class="form-control" rows="2" placeholder="Agregue una observación si corresponde..."></textarea></div>
                    </section>
                </div>
                <div class="modal-footer petty-replenishment-footer"><button type="button" class="btn btn-light" data-dismiss="modal">Cerrar</button><button id="btnSavePettyCashReplenishment" class="btn btn-success" type="submit"><i class="fas fa-save mr-1"></i> Guardar reposición</button></div>
            </form>
        </div>
    </div>
</div>
