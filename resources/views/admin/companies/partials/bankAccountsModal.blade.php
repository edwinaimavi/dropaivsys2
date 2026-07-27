<div class="modal fade company-bank-accounts-modal" id="companyBankAccountsModal" tabindex="-1" role="dialog"
    aria-labelledby="companyBankAccountsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 bg-white">
                <div class="d-flex align-items-center">
                    <span class="company-bank-modal-icon mr-3"><i class="fas fa-university"></i></span>
                    <div>
                        <h5 class="modal-title font-weight-bold mb-0" id="companyBankAccountsModalLabel">Cuentas Bancarias</h5>
                        <small class="text-muted">Gestión de cuentas bancarias de la empresa</small>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
            </div>

            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-3 mb-3 mb-lg-0">
                        <div class="company-bank-card h-100 text-center">
                            <div class="company-bank-company-icon mx-auto mb-3"><i class="fas fa-building"></i></div>
                            <h5 class="font-weight-bold">Empresa</h5>
                            <small class="text-muted">Información principal</small>
                            <hr>
                            <div class="text-left mb-3">
                                <small class="text-muted d-block">Razón social</small>
                                <strong id="company_bank_company_name">—</strong>
                            </div>
                            <div class="text-left mb-3">
                                <small class="text-muted d-block">RUC</small>
                                <strong class="text-success" id="company_bank_company_ruc">—</strong>
                            </div>
                            <div class="text-left">
                                <small class="text-muted d-block">Estado</small>
                                <span id="company_bank_company_status" class="badge badge-success">ACTIVO</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="company-bank-card mb-3">
                            <form id="companyBankAccountForm">
                                @csrf
                                <input type="hidden" id="company_bank_company_id">
                                <input type="hidden" id="company_bank_account_id">
                                <div id="companyBankAccountErrors" class="alert alert-danger d-none"></div>
                                <div class="row">
                                    <div class="form-group col-md-4">
                                        <label>BANCO <span class="text-danger">*</span></label>
                                        <select name="bank_id" id="company_bank_id" class="form-control" required>
                                            <option value="">Seleccione</option>
                                            @foreach ($banks as $bank)
                                                <option value="{{ $bank->id }}">{{ $bank->short_name ?: $bank->description }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>MONEDA <span class="text-danger">*</span></label>
                                        <select name="currency_id" id="company_bank_currency_id" class="form-control" required>
                                            <option value="">Seleccione</option>
                                            @foreach ($currencies as $currency)
                                                <option value="{{ $currency->id }}">{{ $currency->code }} | {{ $currency->description }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="form-group col-md-4">
                                        <label>DETRACCIÓN</label>
                                        <select name="is_detraction" id="company_bank_is_detraction" class="form-control">
                                            <option value="NO">NO</option>
                                            <option value="YES">SÍ</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label>TITULAR <span class="text-danger">*</span></label>
                                        <input name="account_holder" id="company_bank_account_holder"
                                            class="form-control text-uppercase" maxlength="255" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>NRO CUENTA <span class="text-danger">*</span></label>
                                        <input name="account_number" id="company_bank_account_number"
                                            class="form-control" maxlength="100" required>
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>CCI</label>
                                        <input name="cci" id="company_bank_cci" class="form-control" maxlength="100">
                                    </div>
                                    <div class="form-group col-md-3">
                                        <label>ESTADO</label>
                                        <select name="status" id="company_bank_status" class="form-control">
                                            <option value="ACTIVE">ACTIVO</option>
                                            <option value="INACTIVE">INACTIVO</option>
                                        </select>
                                    </div>
                                    <div class="form-group col-md-9">
                                        <label>OBSERVACIÓN</label>
                                        <textarea name="observation" id="company_bank_observation" class="form-control text-uppercase" rows="2"></textarea>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end border-top pt-3">
                                    <button type="button" class="btn btn-light border mr-2" data-dismiss="modal">
                                        <i class="fas fa-times mr-1"></i> Cerrar
                                    </button>
                                    @can('admin.company-bank-accounts.store')
                                        <button type="submit" id="btnSaveCompanyBankAccount" class="btn btn-success">
                                            <i class="fas fa-save mr-1"></i> Guardar Cuenta
                                        </button>
                                    @endcan
                                </div>
                            </form>
                        </div>

                        <div class="company-bank-card">
                            <h5 class="font-weight-bold mb-0">Lista de Cuentas Bancarias</h5>
                            <small class="text-muted">Registros disponibles</small>
                            <div class="table-responsive mt-3">
                                <table id="tableCompanyBankAccounts" class="table table-hover table-sm w-100 text-center">
                                    <thead>
                                        <tr>
                                            <th>#</th><th>BANCO</th><th>MONEDA</th><th>TITULAR</th>
                                            <th>NRO CUENTA</th><th>CCI</th><th>DETRACCIÓN</th>
                                            <th>ESTADO</th><th>ACCIONES</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .company-bank-accounts-modal .modal-content { max-height: calc(100vh - 40px); border-radius: 16px; overflow: hidden; }
    .company-bank-accounts-modal .modal-body { overflow-y: auto; background: #f5f7fa; }
    .company-bank-card { padding: 18px; border: 1px solid #e8edf2; border-radius: 14px; background: #fff; box-shadow: 0 5px 18px rgba(31, 45, 61, .05); }
    .company-bank-modal-icon, .company-bank-company-icon { display: flex; align-items: center; justify-content: center; color: #28a745; background: rgba(40, 167, 69, .12); }
    .company-bank-modal-icon { width: 42px; height: 42px; border-radius: 12px; }
    .company-bank-company-icon { width: 68px; height: 68px; border-radius: 50%; font-size: 28px; }
    .company-bank-accounts-modal label { font-size: .75rem; font-weight: 700; color: #4b5563; }
    @media (max-width: 767.98px) {
        .company-bank-accounts-modal .modal-dialog { margin: 10px; }
        .company-bank-accounts-modal .modal-content { max-height: calc(100vh - 20px); }
    }
</style>
