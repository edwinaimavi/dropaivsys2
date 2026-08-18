<style>
    .general-cash-modal .modal-dialog {
        max-width: min(1120px, calc(100vw - 32px));
        margin: 1rem auto;
    }

    .general-cash-modal .modal-content {
        border: 0;
        border-radius: 22px;
        box-shadow: 0 28px 80px rgba(15, 23, 42, .28);
        overflow: hidden;
    }

    .general-cash-modal form {
        min-height: 0;
    }

    .general-cash-funding-dialog {
        max-width: min(1040px, calc(100vw - 32px));
    }

    .general-cash-funding-dialog .modal-content {
        max-height: calc(100vh - 36px);
        display: flex;
        flex-direction: column;
    }

    .general-cash-funding-dialog form {
        max-height: calc(100vh - 36px);
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .general-cash-funding-header {
        flex: 0 0 auto;
        padding: 1.35rem 1.55rem;
        background: linear-gradient(135deg, #ffffff 0%, #f8fffc 45%, #eefbf6 100%);
        border-bottom: 1px solid rgba(15, 118, 110, .10);
    }

    .general-cash-funding-heading {
        display: flex;
        align-items: center;
        gap: 1rem;
        min-width: 0;
    }

    .general-cash-funding-title-icon,
    .general-cash-modal-title-icon {
        width: 54px;
        height: 54px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 18px;
        background: linear-gradient(135deg, #e8fff6, #eaf7ff);
        color: #0f766e;
        font-size: 1.35rem;
        box-shadow: inset 0 0 0 1px rgba(15, 118, 110, .08);
        flex: 0 0 auto;
    }

    .general-cash-modal-title-icon.is-expense {
        color: #dc2626;
        background: linear-gradient(135deg, #fff1f2, #fff7ed);
    }

    .general-cash-modal-title-icon.is-reconcile {
        color: #b45309;
        background: linear-gradient(135deg, #fffbeb, #fff7ed);
    }

    .general-cash-funding-eyebrow {
        display: inline-block;
        margin-bottom: .18rem;
        color: #059669;
        font-size: .72rem;
        font-weight: 900;
        letter-spacing: .12em;
    }

    .general-cash-funding-heading h5,
    .general-cash-modal .modal-title {
        color: #0f172a;
        font-weight: 900;
        line-height: 1.1;
    }

    .general-cash-funding-heading p,
    .general-cash-modal .modal-header small {
        margin: .25rem 0 0;
        color: #64748b;
        font-size: .86rem;
    }

    .general-cash-funding-close,
    .general-cash-modal .close {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: #fff;
        opacity: 1;
        color: #64748b;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
        transition: all .18s ease;
    }

    .general-cash-funding-close:hover,
    .general-cash-modal .close:hover {
        color: #0f766e;
        transform: translateY(-1px);
    }

    .general-cash-funding-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        padding: 1.35rem;
        background:
            radial-gradient(circle at top left, rgba(16, 185, 129, .08), transparent 32%),
            linear-gradient(180deg, #fbfefd 0%, #f8fafc 100%);
    }

    .general-cash-funding-body::-webkit-scrollbar,
    .general-cash-detail-content::-webkit-scrollbar {
        width: 9px;
    }

    .general-cash-funding-body::-webkit-scrollbar-track,
    .general-cash-detail-content::-webkit-scrollbar-track {
        background: #eef6f3;
        border-radius: 999px;
    }

    .general-cash-funding-body::-webkit-scrollbar-thumb,
    .general-cash-detail-content::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, #10b981, #0f766e);
        border-radius: 999px;
    }

    .general-cash-funding-notice {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem 1.1rem;
        margin-bottom: 1rem;
        border: 1px solid rgba(14, 165, 233, .22);
        border-radius: 18px;
        background: linear-gradient(135deg, #ecfeff, #f0fdfa);
        color: #134e4a;
    }

    .general-cash-funding-notice > span,
    .general-cash-funding-section header > span {
        width: 42px;
        height: 42px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: #dcfce7;
        color: #0f766e;
        flex: 0 0 auto;
    }

    .general-cash-funding-notice strong {
        display: block;
        font-weight: 900;
        color: #0f172a;
    }

    .general-cash-funding-notice p {
        margin: .15rem 0 0;
        color: #64748b;
        font-size: .84rem;
    }

    .general-cash-funding-notice small {
        margin-left: auto;
        padding: .5rem .85rem;
        border-radius: 999px;
        background: #fff;
        color: #047857;
        font-weight: 800;
        white-space: nowrap;
        box-shadow: 0 8px 18px rgba(15, 23, 42, .06);
    }

    .general-cash-funding-section {
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid rgba(15, 118, 110, .10);
        border-radius: 18px;
        background: rgba(255, 255, 255, .92);
        box-shadow: 0 14px 30px rgba(15, 23, 42, .045);
    }

    .general-cash-funding-section header {
        display: flex;
        align-items: center;
        gap: .8rem;
        padding-bottom: .8rem;
        margin-bottom: .85rem;
        border-bottom: 1px solid #edf2f7;
    }

    .general-cash-funding-section header h6 {
        margin: 0;
        color: #0f172a;
        font-weight: 900;
    }

    .general-cash-funding-section header p {
        margin: .15rem 0 0;
        color: #64748b;
        font-size: .82rem;
    }

    .general-cash-modal label,
    .general-cash-funding-section label {
        color: #475569;
        font-size: .76rem;
        font-weight: 900;
        letter-spacing: .025em;
        text-transform: uppercase;
    }

    .general-cash-funding-section label b {
        color: #ef4444;
    }

    .general-cash-funding-section label em {
        padding: .12rem .45rem;
        margin-left: .25rem;
        border-radius: 999px;
        background: #eef2ff;
        color: #64748b;
        font-style: normal;
        font-size: .68rem;
    }

    .general-cash-modal .form-control,
    .general-cash-funding-section .form-control {
        min-height: 43px;
        border-radius: 11px;
        border-color: #dbe7ef;
        color: #0f172a;
        box-shadow: none;
        transition: all .18s ease;
    }

    .general-cash-modal .form-control:focus,
    .general-cash-funding-section .form-control:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 .2rem rgba(16, 185, 129, .12);
    }

    .general-cash-funding-help,
    .general-cash-funding-file-name {
        display: block;
        margin-top: .35rem;
        color: #64748b;
        font-size: .82rem;
    }

    .general-cash-funding-amount {
        display: flex;
        align-items: center;
        border: 1px solid #dbe7ef;
        border-radius: 11px;
        background: #fff;
        overflow: hidden;
        transition: all .18s ease;
    }

    .general-cash-funding-amount:focus-within {
        border-color: #10b981;
        box-shadow: 0 0 0 .2rem rgba(16, 185, 129, .12);
    }

    .general-cash-funding-amount span {
        width: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #0f766e;
    }

    .general-cash-funding-amount .form-control {
        border: 0;
        box-shadow: none !important;
    }

    .general-cash-funding-upload {
        display: flex;
        align-items: center;
        gap: .85rem;
        min-height: 88px;
        padding: .9rem;
        margin: 0;
        border: 1px dashed rgba(15, 118, 110, .35);
        border-radius: 15px;
        background: linear-gradient(135deg, #f0fdfa, #ffffff);
        cursor: pointer;
        transition: all .18s ease;
        text-transform: none !important;
    }

    .general-cash-funding-upload:hover {
        border-color: #10b981;
        transform: translateY(-1px);
        box-shadow: 0 14px 28px rgba(16, 185, 129, .12);
    }

    .general-cash-funding-upload input {
        display: none;
    }

    .general-cash-funding-upload-icon {
        width: 46px;
        height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        background: #dcfce7;
        color: #059669;
        font-size: 1.2rem;
        flex: 0 0 auto;
    }

    .general-cash-funding-upload-copy {
        display: flex;
        flex-direction: column;
        min-width: 0;
        flex: 1;
    }

    .general-cash-funding-upload-copy strong {
        color: #0f172a;
        font-weight: 900;
    }

    .general-cash-funding-upload-copy small {
        color: #64748b;
        font-weight: 600;
    }

    .general-cash-funding-upload-action {
        padding: .45rem .75rem;
        border-radius: 10px;
        background: #fff;
        color: #0f766e;
        font-weight: 900;
        box-shadow: 0 6px 16px rgba(15, 23, 42, .08);
    }

    .general-cash-funding-observation {
        min-height: 88px;
        resize: vertical;
    }

    .general-cash-funding-counter {
        color: #94a3b8;
        font-weight: 800;
    }

    .general-cash-funding-footer {
        position: sticky;
        bottom: 0;
        z-index: 5;
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: .95rem 1.35rem;
        border-top: 1px solid rgba(15, 118, 110, .10);
        background: rgba(255, 255, 255, .96);
        backdrop-filter: blur(12px);
        box-shadow: 0 -12px 30px rgba(15, 23, 42, .08);
    }

    .general-cash-funding-footer small {
        color: #64748b;
        font-weight: 700;
    }

    .general-cash-funding-cancel,
    .general-cash-funding-submit {
        min-height: 42px;
        padding: .65rem 1rem;
        border-radius: 12px;
        font-weight: 900;
    }

    .general-cash-funding-cancel {
        background: #fff;
        color: #334155;
        border: 1px solid #dbe7ef;
    }

    .general-cash-funding-submit {
        background: linear-gradient(135deg, #0f766e, #10b981);
        color: #fff;
        border: 0;
        box-shadow: 0 12px 24px rgba(16, 185, 129, .24);
    }

    .general-cash-funding-submit:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 16px 30px rgba(16, 185, 129, .32);
    }

    .general-cash-modal .modal-footer {
        background: rgba(255, 255, 255, .96);
        border-top: 1px solid rgba(15, 118, 110, .10);
    }

    .general-cash-detail-content {
        max-height: calc(100vh - 230px);
        overflow-y: auto;
    }

    @media (max-width: 768px) {
        .general-cash-modal .modal-dialog,
        .general-cash-funding-dialog {
            max-width: calc(100vw - 16px);
            margin: .5rem auto;
        }

        .general-cash-funding-dialog .modal-content,
        .general-cash-funding-dialog form {
            max-height: calc(100vh - 16px);
        }

        .general-cash-funding-header {
            padding: 1rem;
        }

        .general-cash-funding-body {
            padding: .9rem;
        }

        .general-cash-funding-notice {
            align-items: flex-start;
        }

        .general-cash-funding-notice small {
            display: none;
        }

        .general-cash-funding-footer {
            flex-direction: column;
            align-items: stretch;
            padding: .85rem;
        }

        .general-cash-funding-footer > div {
            display: flex;
            gap: .5rem;
        }

        .general-cash-funding-footer .btn {
            flex: 1;
        }
    }
</style>

<div class="modal fade general-cash-modal" id="generalCashBoxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="generalCashBoxForm">
                @csrf
                <input type="hidden" id="general_cash_box_id">

                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <span class="general-cash-modal-title-icon"><i class="fas fa-cash-register"></i></span>
                        <div>
                            <h5 class="modal-title mb-0" id="generalCashBoxModalTitle">Nueva Caja General</h5>
                            <small>Configura una caja física independiente por empresa y moneda.</small>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>EMPRESA *</label>
                            <select name="company_id" id="general_cash_company_id" class="form-control">
                                <option value="">Seleccione</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}">{{ $company->trade_name ?: $company->business_name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-6">
                            <label>MONEDA *</label>
                            <select name="currency_id" id="general_cash_currency_id" class="form-control">
                                <option value="">Seleccione</option>
                                @foreach($currencies as $currency)
                                    <option value="{{ $currency->id }}">{{ $currency->code }} - {{ $currency->description }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-8">
                            <label>NOMBRE DE LA CAJA *</label>
                            <input name="name" id="general_cash_name" class="form-control" maxlength="120">
                        </div>

                        <div class="form-group col-md-4">
                            <label>ESTADO *</label>
                            <select name="status" id="general_cash_status" class="form-control">
                                <option value="ACTIVE">Activa</option>
                                <option value="INACTIVE">Inactiva</option>
                            </select>
                        </div>

                        <div class="form-group col-12">
                            <label>RESPONSABLE</label>
                            <select name="responsible_user_id" id="general_cash_responsible_user_id" class="form-control">
                                <option value="">No asignado</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ trim($user->name.' '.$user->lastname) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-12 mb-0">
                            <label>DESCRIPCIÓN</label>
                            <textarea name="description" id="general_cash_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success" type="submit"><i class="fas fa-save mr-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade general-cash-modal general-cash-funding-modal" id="generalCashFundingModal" tabindex="-1" aria-labelledby="generalCashFundingModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered general-cash-funding-dialog">
        <div class="modal-content">
            <form id="generalCashFundingForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="idempotency_key" id="general_cash_funding_key">

                <div class="modal-header general-cash-funding-header">
                    <div class="general-cash-funding-heading">
                        <span class="general-cash-funding-title-icon"><i class="fas fa-university"></i></span>
                        <div>
                            <span class="general-cash-funding-eyebrow">MOVIMIENTO DE EFECTIVO</span>
                            <h5 class="modal-title" id="generalCashFundingModalTitle">Ingresar efectivo desde banco</h5>
                            <p>Registra el retiro desde una cuenta bancaria y su ingreso a Caja General.</p>
                        </div>
                    </div>
                    <button type="button" class="close general-cash-funding-close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body general-cash-funding-body">
                    <div class="general-cash-funding-notice" role="note">
                        <span><i class="fas fa-shield-alt"></i></span>
                        <div>
                            <strong>Movimiento seguro y sincronizado</strong>
                            <p>Banco y Caja General se actualizarán dentro de una sola transacción.</p>
                        </div>
                        <small><i class="fas fa-link mr-1"></i>Trazabilidad automática</small>
                    </div>

                    <section class="general-cash-funding-section">
                        <header>
                            <span><i class="fas fa-exchange-alt"></i></span>
                            <div>
                                <h6>Origen y destino</h6>
                                <p>Selecciona la caja que recibirá el efectivo y la cuenta bancaria de origen.</p>
                            </div>
                        </header>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="general_cash_funding_box_id">Caja General <b>*</b></label>
                                <select name="general_cash_box_id" id="general_cash_funding_box_id" class="form-control general-cash-box-select" required>
                                    <option value="">Seleccione una caja</option>
                                    @foreach($boxes as $box)
                                        <option value="{{ $box->id }}" data-company="{{ $box->company_id }}" data-currency="{{ $box->currency_id }}">
                                            {{ $box->code }} · {{ $box->name }} · {{ $box->currency?->code }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="general_cash_bank_account_id">Cuenta bancaria origen <b>*</b></label>
                                <select name="company_bank_account_id" id="general_cash_bank_account_id" class="form-control" required>
                                    <option value="">Seleccione primero una caja</option>
                                </select>
                                <small id="generalCashBankBalance" class="general-cash-funding-help">
                                    <i class="fas fa-info-circle mr-1"></i>El saldo disponible aparecerá al seleccionar una cuenta.
                                </small>
                            </div>
                        </div>
                    </section>

                    <section class="general-cash-funding-section">
                        <header>
                            <span><i class="fas fa-file-invoice-dollar"></i></span>
                            <div>
                                <h6>Datos de la operación</h6>
                                <p>Identifica el retiro tal como figura en el movimiento bancario.</p>
                            </div>
                        </header>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Fecha <b>*</b></label>
                                <input type="date" name="movement_date" class="form-control" value="{{ now()->toDateString() }}" required>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Monto retirado <b>*</b></label>
                                <div class="general-cash-funding-amount">
                                    <span><i class="fas fa-coins"></i></span>
                                    <input type="number" name="amount" min="0.01" step="0.01" class="form-control" placeholder="0.00" required>
                                </div>
                            </div>

                            <div class="form-group col-md-4">
                                <label>Nro. de operación <b>*</b></label>
                                <input name="operation_number" maxlength="100" class="form-control" placeholder="Ej. OP-0001458" autocomplete="off" required>
                            </div>
                        </div>
                    </section>

                    <section class="general-cash-funding-section">
                        <header>
                            <span><i class="fas fa-user-check"></i></span>
                            <div>
                                <h6>Responsables</h6>
                                <p>Registra quién recibe o gestiona el efectivo.</p>
                            </div>
                        </header>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Responsable del sistema</label>
                                <select name="responsible_user_id" class="form-control">
                                    <option value="">Seleccione un responsable</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ trim($user->name.' '.$user->lastname) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="form-group col-md-6">
                                <label>Responsable externo / referencia</label>
                                <input name="responsible_name" maxlength="150" class="form-control" placeholder="Nombre o referencia adicional">
                            </div>
                        </div>
                    </section>

                    <section class="general-cash-funding-section mb-0">
                        <header>
                            <span><i class="fas fa-paperclip"></i></span>
                            <div>
                                <h6>Sustento y observación</h6>
                                <p>Adjunta evidencia de la operación y agrega información complementaria.</p>
                            </div>
                        </header>

                        <div class="form-row align-items-stretch">
                            <div class="form-group col-lg-5 mb-lg-0">
                                <label>Sustento <em>Opcional</em></label>
                                <label class="general-cash-funding-upload" for="general_cash_funding_support_file">
                                    <input type="file" name="support_file" id="general_cash_funding_support_file" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx">
                                    <span class="general-cash-funding-upload-icon"><i class="fas fa-cloud-upload-alt"></i></span>
                                    <span class="general-cash-funding-upload-copy">
                                        <strong>Seleccionar comprobante</strong>
                                        <small>PDF, imagen o Excel · máximo 15 MB</small>
                                    </span>
                                    <span class="general-cash-funding-upload-action">Examinar</span>
                                </label>
                                <div id="generalCashFundingFileName" class="general-cash-funding-file-name">
                                    <i class="far fa-file mr-1"></i>Ningún archivo seleccionado
                                </div>
                            </div>

                            <div class="form-group col-lg-7 mb-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label for="general_cash_funding_observation">Observación</label>
                                    <small id="generalCashFundingObservationCount" class="general-cash-funding-counter">0 / 1500</small>
                                </div>
                                <textarea name="observation" id="general_cash_funding_observation" class="form-control general-cash-funding-observation" rows="4" maxlength="1500" placeholder="Añade una referencia, detalle del retiro o indicación para auditoría."></textarea>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="modal-footer general-cash-funding-footer">
                    <small><i class="fas fa-lock mr-1"></i>La operación quedará registrada con fecha, usuario y trazabilidad.</small>
                    <div>
                        <button type="button" class="btn general-cash-funding-cancel" data-dismiss="modal">
                            <i class="fas fa-times mr-1"></i>Cancelar
                        </button>
                        <button class="btn general-cash-funding-submit" type="submit">
                            <i class="fas fa-arrow-right mr-1"></i><span>Ingresar efectivo</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade general-cash-modal" id="generalCashExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form id="generalCashExpenseForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="idempotency_key" id="general_cash_expense_key">

                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <span class="general-cash-modal-title-icon is-expense"><i class="fas fa-receipt"></i></span>
                        <div>
                            <h5 class="modal-title mb-0">Registrar gasto general</h5>
                            <small>El importe se descontará inmediatamente del efectivo disponible.</small>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-lg-4">
                            <label>CAJA GENERAL *</label>
                            <select name="general_cash_box_id" id="general_cash_expense_box_id" class="form-control general-cash-box-select">
                                <option value="">Seleccione</option>
                                @foreach($boxes as $box)
                                    <option value="{{ $box->id }}">
                                        {{ $box->code }} · {{ $box->name }} · Saldo {{ $box->currency?->symbol }} {{ number_format($box->current_balance,2) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-lg-3">
                            <label>FECHA *</label>
                            <input type="date" name="expense_date" class="form-control" value="{{ now()->toDateString() }}">
                        </div>

                        <div class="form-group col-lg-5">
                            <label>TIPO DE GASTO *</label>
                            <select name="expense_type" class="form-control">
                                <option value="GASOLINA">Gasolina / combustible</option>
                                <option value="MOVILIDAD">Movilidad</option>
                                <option value="PAGO_PERSONA">Pago a persona</option>
                                <option value="PAGO_PROVEEDOR">Pago a proveedor</option>
                                <option value="SERVICIO_MENOR">Servicio menor</option>
                                <option value="GASTO_ADMINISTRATIVO">Gasto administrativo</option>
                                <option value="OTRO">Otro gasto</option>
                            </select>
                        </div>

                        <div class="form-group col-lg-4">
                            <label>PROVEEDOR REGISTRADO</label>
                            <select name="supplier_id" id="general_cash_expense_supplier_id" class="form-control">
                                <option value="">No aplica</option>
                                @foreach($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}" data-name="{{ $supplier->business_name }}" data-ruc="{{ $supplier->ruc }}">
                                        {{ $supplier->ruc }} · {{ $supplier->short_name ?: $supplier->business_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-lg-5">
                            <label>PROVEEDOR / PERSONA / RESPONSABLE *</label>
                            <input name="person_name" id="general_cash_expense_person_name" maxlength="180" class="form-control">
                        </div>

                        <div class="form-group col-lg-3">
                            <label>RUC / DNI</label>
                            <input name="identity_document" id="general_cash_expense_identity" maxlength="20" class="form-control">
                        </div>

                        <div class="form-group col-12">
                            <label>CONCEPTO *</label>
                            <input name="concept" maxlength="255" class="form-control">
                        </div>

                        <div class="form-group col-lg-3">
                            <label>TIPO COMPROBANTE *</label>
                            <select name="document_type" id="general_cash_expense_document_type" class="form-control">
                                <option value="FACTURA">Factura</option>
                                <option value="BOLETA">Boleta</option>
                                <option value="RECIBO_HONORARIOS">Recibo por honorarios</option>
                                <option value="RECIBO_INTERNO">Recibo interno</option>
                                <option value="SIN_COMPROBANTE">Sin comprobante</option>
                            </select>
                        </div>

                        <div class="form-group col-lg-2">
                            <label>SERIE</label>
                            <input name="document_series" class="form-control" maxlength="30">
                        </div>

                        <div class="form-group col-lg-3">
                            <label>NÚMERO</label>
                            <input name="document_number" class="form-control" maxlength="80">
                        </div>

                        <div class="form-group col-lg-2">
                            <label>IMPORTE *</label>
                            <input type="number" name="amount" min="0.01" step="0.01" class="form-control">
                        </div>

                        <div class="form-group col-lg-2">
                            <label>AFECTA IGV *</label>
                            <select name="affects_igv" id="general_cash_expense_affects_igv" class="form-control">
                                <option value="0">No</option>
                                <option value="1">Sí</option>
                            </select>
                        </div>

                        <div class="form-group col-md-6">
                            <label>COMPROBANTE / RECIBO</label>
                            <input type="file" name="receipt_file" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>

                        <div class="form-group col-md-6">
                            <label>CONSTANCIA / VOUCHER DE PAGO</label>
                            <input type="file" name="payment_file" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png,.webp">
                        </div>

                        <div class="form-group col-12 mb-0">
                            <label>OBSERVACIÓN</label>
                            <textarea name="observation" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger" type="submit"><i class="fas fa-save mr-1"></i>Registrar gasto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade general-cash-modal" id="generalCashReconciliationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="generalCashReconciliationForm" enctype="multipart/form-data">
                @csrf

                <div class="modal-header">
                    <div class="d-flex align-items-center">
                        <span class="general-cash-modal-title-icon is-reconcile"><i class="fas fa-balance-scale"></i></span>
                        <div>
                            <h5 class="modal-title mb-0">Arqueo / cierre de Caja General</h5>
                            <small>Registra el saldo esperado, efectivo físico y cualquier diferencia.</small>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>CAJA GENERAL *</label>
                            <select name="general_cash_box_id" id="general_cash_reconciliation_box_id" class="form-control general-cash-box-select">
                                <option value="">Seleccione</option>
                                @foreach($boxes as $box)
                                    <option value="{{ $box->id }}" data-balance="{{ $box->current_balance }}">{{ $box->code }} · {{ $box->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-6">
                            <label>FECHA DE ARQUEO *</label>
                            <input type="datetime-local" name="reconciliation_date" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>

                        <div class="form-group col-md-4">
                            <label>SALDO ESPERADO</label>
                            <input id="general_cash_system_balance" class="form-control" readonly value="0.00">
                        </div>

                        <div class="form-group col-md-4">
                            <label>SALDO FÍSICO CONTADO *</label>
                            <input type="number" name="physical_balance" id="general_cash_physical_balance" min="0" step="0.01" class="form-control">
                        </div>

                        <div class="form-group col-md-4">
                            <label>DIFERENCIA</label>
                            <input id="general_cash_difference" class="form-control" readonly value="0.00">
                        </div>

                        <div class="form-group col-md-6">
                            <label>RESPONSABLE</label>
                            <select name="responsible_user_id" class="form-control">
                                <option value="">Seleccione</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ trim($user->name.' '.$user->lastname) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group col-md-6">
                            <label>RESPONSABLE EXTERNO</label>
                            <input name="responsible_name" maxlength="150" class="form-control">
                        </div>

                        <div class="form-group col-md-6">
                            <label>SUSTENTO OPCIONAL</label>
                            <input type="file" name="support_file" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx">
                        </div>

                        <div class="form-group col-12 mb-0">
                            <label>OBSERVACIÓN</label>
                            <textarea name="observation" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
                    <button class="btn btn-warning" type="submit"><i class="fas fa-balance-scale mr-1"></i>Registrar arqueo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade general-cash-modal" id="generalCashDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <span class="general-cash-modal-title-icon"><i class="fas fa-cash-register"></i></span>
                    <div>
                        <h5 class="modal-title mb-0" id="generalCashDetailTitle">Detalle de Caja General</h5>
                        <small id="generalCashDetailSubtitle">Movimientos, gastos, documentos, arqueos y auditoría.</small>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <ul class="nav general-cash-detail-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#generalCashSummaryTab">Resumen</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#generalCashMovementsTab">Movimientos</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#generalCashExpensesTab">Gastos</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#generalCashDocumentsTab">Documentos</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#generalCashReconciliationsTab">Arqueos</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#generalCashAuditTab">Auditoría</a></li>
            </ul>

            <div class="modal-body general-cash-detail-content">
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="generalCashSummaryTab"></div>
                    <div class="tab-pane fade" id="generalCashMovementsTab"></div>
                    <div class="tab-pane fade" id="generalCashExpensesTab"></div>
                    <div class="tab-pane fade" id="generalCashDocumentsTab"></div>
                    <div class="tab-pane fade" id="generalCashReconciliationsTab"></div>
                    <div class="tab-pane fade" id="generalCashAuditTab"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-light" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>