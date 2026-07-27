@extends('layouts.app')

@section('subtitle', 'Caja Chica')

@section('header')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <div>
            <h1 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-wallet text-success"></i>
                Caja Chica
            </h1>
            <small class="text-muted">Control de fondos, rendiciones y reposiciones.</small>
        </div>
        <div class="d-flex flex-wrap align-items-center">
            @can('admin.petty-cash.expenses.approve')
            <button id="btnPendingPettyCashExpenses" class="btn petty-pending-bell shadow-sm mr-2" type="button">
                <i class="fas fa-bell mr-1"></i> Gastos por aprobar
                <span id="pcPendingExpensesBadge" class="badge badge-light ml-1">0</span>
            </button>
            @endcan
            @can('admin.petty-cash.approved-amount.update')
            <button id="btnConfigureApprovedAmount" class="btn btn-outline-success shadow-sm mr-2" type="button">
                <i class="fas fa-sliders-h mr-1"></i> Configurar monto aprobado
            </button>
            @endcan
            @can('admin.petty-cash.store')
            <button id="btnCreatePettyCash" class="btn btn-success shadow-sm px-4" type="button"
                data-toggle="modal" data-target="#pettyCashModal">
                <i class="fas fa-plus-circle mr-1"></i> Aperturar caja
            </button>
            @endcan
        </div>
    </div>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">
            <li class="breadcrumb-item">
                <a href="{{ route('home') }}" class="text-decoration-none"><i class="fas fa-house-user"></i> Home</a>
            </li>
            <li class="breadcrumb-item active">Caja Chica</li>
        </ol>
    </nav>
</div>
@stop

@section('content_body')
<div id="pettyCashApp"
    data-list-url="{{ route('admin.petty-cash.list') }}"
    data-base-url="{{ url('admin/petty-cash') }}"
    data-previous-balance-url="{{ route('admin.petty-cash.previous-balance') }}"
    data-approved-amount-active-url="{{ route('admin.petty-cash.approved-amount.active') }}"
    @can('admin.petty-cash.approved-amount.update')
    data-approved-amount-show-url="{{ route('admin.petty-cash.approved-amount.show') }}"
    data-approved-amount-update-url="{{ route('admin.petty-cash.approved-amount.update') }}"
    @endcan
    data-ruc-url="{{ url('admin/suppliers/consultar-ruc') }}"
    data-dni-url="{{ route('admin.customers.consultar', 'DNI_PLACEHOLDER') }}"
    data-can-expense-update="{{ auth()->user()->can('admin.petty-cash.expenses.update') ? 1 : 0 }}"
    data-can-expense-delete="{{ auth()->user()->can('admin.petty-cash.expenses.destroy') ? 1 : 0 }}"
    data-can-expense-approve="{{ auth()->user()->can('admin.petty-cash.expenses.approve') ? 1 : 0 }}"
    data-can-receipt-exchange-store="{{ auth()->user()->can('admin.petty-cash.receipt-exchanges.store') ? 1 : 0 }}"
    data-can-receipt-exchange-show="{{ auth()->user()->can('admin.petty-cash.receipt-exchanges.show') ? 1 : 0 }}"
    data-pending-expenses-url="{{ route('admin.petty-cash.expenses.pending') }}">
    <div class="row mb-3">
        <div class="col-lg-3 col-sm-6"><div class="info-box shadow-sm border-0"><span class="info-box-icon bg-success"><i class="fas fa-wallet"></i></span><div class="info-box-content"><span class="info-box-text">Cajas activas</span><span id="pcKpiOpen" class="info-box-number">0</span></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="info-box shadow-sm border-0"><span class="info-box-icon bg-info"><i class="fas fa-coins"></i></span><div class="info-box-content"><span class="info-box-text">Fondo visible</span><span id="pcKpiFund" class="info-box-number">0.00</span></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="info-box shadow-sm border-0"><span class="info-box-icon bg-warning"><i class="fas fa-receipt"></i></span><div class="info-box-content"><span class="info-box-text">Total gastado</span><span id="pcKpiSpent" class="info-box-number">0.00</span></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="info-box shadow-sm border-0"><span class="info-box-icon bg-secondary"><i class="fas fa-sync-alt"></i></span><div class="info-box-content"><span class="info-box-text">Pendiente de reposición</span><span id="pcKpiPending" class="info-box-number">0.00</span></div></div></div>
    </div>
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-success"></i> Lista de Cajas Chicas
            </h5>
            <small class="text-muted">Fondos registrados en el sistema</small>
        </div>
        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tablePettyCash" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th><th>ID</th><th>CÓDIGO</th><th>EMPRESA</th><th>PERIODO</th>
                            <th>APERTURA</th><th>CIERRE</th><th>FONDO INICIAL</th><th>GASTADO</th><th>SALDO</th>
                            <th>PENDIENTE DE REPOSICIÓN</th><th>ESTADO</th><th>F. REGISTRO</th><th width="240">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('admin.petty-cash.partials.modal')
@include('admin.petty-cash.partials.expenseModal')
@include('admin.petty-cash.partials.replenishmentModal')
@include('admin.petty-cash.partials.closeModal')
@include('admin.petty-cash.partials.viewModal')
@if(auth()->user()->can('admin.petty-cash.receipt-exchanges.index') || auth()->user()->can('admin.petty-cash.receipt-exchanges.store'))
@include('admin.petty-cash.partials.receiptExchangeModal')
@endif
@can('admin.petty-cash.expenses.approve')
@include('admin.petty-cash.partials.expenseApprovalModals')
@endcan
@can('admin.petty-cash.approved-amount.update')
@include('admin.petty-cash.partials.approvedAmountModal')
@endcan
@stop

@push('css')
<style>
    .petty-pending-bell{border:1px solid #dccb9d;background:#fffaf0;color:#755719;font-weight:700}.petty-pending-bell:hover{background:#f8efd9;color:#654810}.petty-pending-bell .badge{background:#9b7424;color:#fff}
    .petty-pending-alert{display:flex;align-items:center;margin-bottom:9px;padding:11px 13px;border:1px solid #e4d6af;border-radius:12px;background:#fffaf0;color:#755719;font-size:.76rem}.petty-pending-alert i{margin-right:9px}
    .petty-approval-expense-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}.petty-approval-expense-grid>div,.petty-approval-documents{padding:10px;border:1px solid #e3e9e6;border-radius:10px;background:#fff}.petty-approval-expense-grid small,.petty-approval-documents>small{display:block;color:#84908c;font-size:.62rem;font-weight:800;letter-spacing:.05em}.petty-approval-expense-grid strong{display:block;margin-top:3px;color:#293b36;font-size:.8rem}.petty-approval-documents a{display:inline-flex;align-items:center;margin:7px 6px 0 0;padding:6px 9px;border-radius:8px;background:#edf6f2;color:#237c5c;font-size:.72rem}.petty-approval-badge{display:inline-block;padding:4px 7px;border-radius:20px;font-size:.58rem;font-weight:800}.petty-approval-badge.is-pending{background:#f7edd3;color:#795a18}.petty-approval-badge.is-approved{background:#dff2e8;color:#216c4f}.petty-approval-badge.is-rejected{background:#f8e4e4;color:#9a3d3d}.petty-approval-badge.is-cancelled{background:#ecefee;color:#66716d}.petty-approval-trace{display:block;margin-top:4px;color:#8a9692;font-size:.57rem;line-height:1.3}
    .petty-modal-content{border-radius:.5rem;overflow:hidden}.petty-modal-header{background:linear-gradient(90deg,#fff,#f3f6f8);border-bottom:1px solid #e6eaee;color:#343a40;padding:1rem 1.25rem}.petty-modal-header p{margin:0;color:#6c757d}.petty-modal-header small{color:#28a745;font-weight:700;letter-spacing:.08em}.petty-form-card,.petty-detail-section{background:#fff;border:1px solid #e6eaee;border-radius:.5rem;padding:14px;margin-bottom:12px;box-shadow:0 .125rem .25rem rgba(0,0,0,.04)}.petty-opening-fund{background:#f2faf6;border-color:#cfe8da}.petty-form-card label{font-size:.75rem;text-transform:uppercase;color:#59636b}.petty-form-card h6,.petty-detail-section h6{color:#343a40;font-weight:700;margin-bottom:14px}.petty-side-panel{background:#fff;border-radius:.5rem;padding:18px;box-shadow:0 .125rem .5rem rgba(0,0,0,.08);height:100%}.petty-side-icon{width:80px;height:80px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;border-radius:50%;background:linear-gradient(135deg,#28a745,#1e7e34);color:#fff;font-size:30px}.petty-side-panel>small,.petty-side-panel>strong{display:block;text-align:center}.petty-side-panel>strong{margin:4px 0 16px}.petty-side-row{display:flex;justify-content:space-between;padding:9px 0;border-top:1px solid #edf0ee}.petty-side-row.total{color:#1e7e34}.petty-detail-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.petty-summary-item{padding:12px;border:1px solid #e6eaee;border-radius:.5rem;background:#f8fbf9}.petty-summary-item small{display:block;color:#6c757d}.btn-group .btn{margin-right:2px}.modal-body{background:#fafafa}#pettyCashModal .modal-body>.row>.col-lg-8{order:2}#pettyCashModal .modal-body>.row>.col-lg-4{order:1}@media(max-width:767px){.petty-detail-summary{grid-template-columns:repeat(2,1fr)}}

    .petty-cash-modal .modal-dialog{width:calc(100% - 40px);max-width:1180px;margin:20px auto}
    .petty-cash-modal .modal-content{display:flex;max-height:calc(100vh - 40px);overflow:hidden}
    .petty-cash-modal .modal-content>form{display:flex;flex:1 1 auto;min-height:0;flex-direction:column;overflow:hidden}
    .petty-cash-modal .modal-header{flex:0 0 auto}
    .petty-cash-modal .modal-body{flex:1 1 auto;min-height:0;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain}
    .petty-cash-modal .modal-body .petty-premium-card:last-child{margin-bottom:4px!important}
    .petty-cash-modal .modal-footer{position:relative;z-index:20;flex:0 0 auto}
    .petty-cash-premium{border-radius:18px!important;box-shadow:0 24px 65px rgba(20,46,39,.2);background:#f5f7f6}
    .petty-premium-header{display:flex;align-items:center;justify-content:space-between;padding:20px 24px;background:#fff;border-bottom:1px solid #e9eeeb}
    .petty-header-icon{width:48px;height:48px;margin-right:14px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:linear-gradient(145deg,#e3f5ed,#ccebdc);color:#197652;font-size:21px;box-shadow:inset 0 0 0 1px rgba(32,118,92,.08)}
    .petty-eyebrow{display:block;margin-bottom:2px;color:#23805f;font-size:10px;font-weight:800;letter-spacing:.16em}
    .petty-premium-header h4{font-size:1.35rem;font-weight:800;color:#21332e;letter-spacing:-.02em}
    .petty-premium-header p{color:#7b8783;font-size:.82rem}
    .petty-close{width:36px;height:36px;padding:0!important;border-radius:10px;background:#f2f4f3!important;color:#60706a!important;opacity:1!important;font-weight:400;transition:.2s}
    .petty-close:hover{background:#e7ecea!important;color:#243b34!important}
    .petty-premium-body{padding:18px!important;background:#f5f7f6!important}
    .petty-finance-summary{height:100%;min-height:590px;padding:20px;border:1px solid #e1e9e5;border-radius:16px;background:linear-gradient(180deg,#fff 0%,#f9fcfa 100%);box-shadow:0 10px 28px rgba(38,66,57,.08)}
    .petty-summary-hero{text-align:center;padding:4px 0 18px;border-bottom:1px solid #e8eeeb}
    .petty-summary-icon{width:72px;height:72px;margin:0 auto 13px;border-radius:22px;display:flex;align-items:center;justify-content:center;background:linear-gradient(145deg,#238361,#17694c);color:#fff;font-size:28px;box-shadow:0 12px 24px rgba(29,117,84,.22)}
    .petty-summary-hero small{display:block;font-size:9px;font-weight:800;letter-spacing:.15em;color:#94a09c}
    .petty-summary-hero strong{display:block;margin:4px 0 8px;color:#263b35;font-size:1.05rem}
    .petty-summary-hero .badge{padding:6px 10px;border-radius:20px;font-size:9px;letter-spacing:.08em}
    .petty-summary-list{padding:13px 0}
    .petty-summary-list>div{display:flex;align-items:center;justify-content:space-between;padding:9px 3px;color:#6c7974;font-size:.79rem}
    .petty-summary-list b{color:#293d37;font-size:.9rem}
    .petty-summary-list .petty-opening-total{margin:5px 0;padding:11px 12px;border-radius:10px;background:#edf8f3;color:#286b54}
    .petty-summary-list .petty-opening-total b{color:#16704f;font-size:1rem}
    .petty-current-balance{padding:16px;border-radius:14px;background:linear-gradient(145deg,#173f34,#22694f);color:#fff;box-shadow:0 12px 22px rgba(26,82,63,.17)}
    .petty-current-balance span,.petty-current-balance small{display:block;opacity:.68;font-size:9px;font-weight:700;letter-spacing:.11em}
    .petty-current-balance strong{display:block;margin:3px 0;font-size:1.65rem;letter-spacing:-.03em}
    .petty-summary-note{display:flex;gap:8px;align-items:flex-start;margin-top:15px;padding:10px;border-radius:10px;background:#f0f5f2;color:#718079;font-size:.72rem}
    .petty-summary-note i{margin-top:2px;color:#4b9175}
    .petty-premium-card{margin-bottom:12px;padding:15px 16px 4px;border:1px solid #e3e9e6;border-radius:14px;background:#fff;box-shadow:0 5px 16px rgba(37,62,54,.045)}
    .petty-section-heading{display:flex;align-items:center;margin-bottom:13px}
    .petty-section-heading>span{width:34px;height:34px;margin-right:10px;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#edf6f2;color:#268160;font-size:13px}
    .petty-section-heading h6{margin:0;color:#293b36;font-size:.92rem;font-weight:800}
    .petty-section-heading small{display:block;margin-top:1px;color:#98a19e;font-size:.68rem}
    .petty-cash-premium .form-group{margin-bottom:12px}
    .petty-cash-premium label{margin-bottom:5px;color:#6d7975;font-size:.65rem;font-weight:800;letter-spacing:.055em;text-transform:uppercase}
    .petty-cash-premium .form-control{height:38px;padding:.45rem .7rem;border:1px solid #dfe6e3;border-radius:9px;background:#fbfcfc;color:#2f403b;font-size:.8rem;box-shadow:none;transition:border-color .2s,box-shadow .2s,background .2s}
    .petty-cash-premium .form-control:focus{border-color:#58a98b;background:#fff;box-shadow:0 0 0 3px rgba(39,139,100,.09)}
    .petty-fund-card{border-color:#d3e8df;background:linear-gradient(135deg,#f7fcf9,#f0f8f5)}
    .petty-money-input{display:flex;align-items:center;border:1px solid #d7e4de;border-radius:10px;background:#fff;overflow:hidden}
    .petty-money-input>span{padding:0 10px;color:#51806e;font-size:.78rem;font-weight:800}
    .petty-money-input .form-control{border:0;border-left:1px solid #edf1ef;border-radius:0;background:transparent}
    .petty-money-total{border-color:#92c9b4;background:#e8f6f0}
    .petty-money-total>span,.petty-money-total .form-control{color:#126c4b;font-weight:800}
    .petty-balance-help{display:flex;align-items:center;gap:7px;margin:0 0 10px;padding:8px 10px;border-radius:9px;background:rgba(255,255,255,.72);color:#688078;font-size:.7rem}
    .petty-balance-help i{color:#29916b}
    .petty-field-help{display:block;margin-top:4px;color:#89958f;font-size:.56rem;line-height:1.25}
    .petty-fund-source-card{border-color:#d4e7de;background:linear-gradient(145deg,#fff,#f3faf6)}
    .petty-source-help{display:block;margin-top:4px;color:#a06a20;font-size:.63rem}
    .petty-source-upload{display:flex!important;align-items:center;margin:0!important;padding:11px 13px;border:1px dashed #9fc8b7;border-radius:11px;background:rgba(255,255,255,.72);cursor:pointer;text-transform:none!important}
    .petty-source-upload>i{margin-right:10px;color:#278160;font-size:20px}
    .petty-source-upload.is-dragging{border-color:#278160;background:#e9f7f0;box-shadow:0 0 0 3px rgba(39,129,96,.08)}
    .petty-source-upload span strong,.petty-source-upload span small{display:block}
    .petty-source-upload span strong{color:#40514b;font-size:.72rem}
    .petty-source-upload span small{margin-top:2px;color:#8b9692;font-size:.62rem;font-weight:500;letter-spacing:0;text-transform:none}
    .petty-source-upload input{position:absolute;width:1px;height:1px;opacity:0}
    .petty-source-previews{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin-top:8px}
    .petty-source-file{display:flex;min-width:0;padding:7px;align-items:center;border:1px solid #e0e8e4;border-radius:9px;background:#fff}
    .petty-source-file>img,.petty-source-file>span{display:flex;width:36px;height:36px;flex:0 0 36px;align-items:center;justify-content:center;border-radius:8px;object-fit:cover;background:#fae9eb;color:#bd3f49}
    .petty-source-file>div{min-width:0;margin-left:7px}
    .petty-source-file strong,.petty-source-file small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .petty-source-file strong{color:#42534d;font-size:.62rem}
    .petty-source-file small{color:#98a29e;font-size:.54rem}
    .petty-source-file>a,.petty-source-file>button{display:flex;width:24px;height:24px;margin-left:auto;align-items:center;justify-content:center;border:0;border-radius:7px;background:#eef5f2;color:#33745c;font-size:8px;cursor:pointer}
    .petty-source-detail{display:grid;grid-template-columns:1fr 1.4fr auto;gap:8px}
    .petty-source-detail>div{padding:9px 10px;border:1px solid #e5ebe8;border-radius:9px;background:#f9fbfa}
    .petty-source-detail small,.petty-source-detail strong{display:block}
    .petty-source-detail small{color:#8b9692;font-size:.58rem;font-weight:800;text-transform:uppercase}
    .petty-source-detail strong{margin-top:2px;color:#34463f;font-size:.68rem}
    .petty-person-row{padding:2px 10px 0;border-radius:10px;background:#fafcfb}
    .petty-person-row+.petty-person-row{margin-top:7px}
    .petty-observation{height:auto!important;min-height:76px;resize:vertical;padding:10px 12px!important;line-height:1.45}
    .petty-premium-footer{padding:14px 22px;border-top:1px solid #e4eae7;background:#fff}
    .petty-premium-footer .btn{min-width:118px;padding:9px 18px;border-radius:10px;font-size:.8rem;font-weight:700}
    .petty-btn-secondary{border:1px solid #e1e6e4;background:#f7f8f8;color:#63716c}
    .petty-btn-primary{border-color:#21805e;background:linear-gradient(135deg,#258762,#1d7052);box-shadow:0 7px 16px rgba(32,123,89,.18)}

    .petty-cash-open-modal .modal-dialog{margin:12px auto}
    .petty-cash-open-modal .modal-content{max-height:calc(100vh - 24px)}
    .petty-cash-open-modal .petty-premium-header{padding:13px 18px}
    .petty-cash-open-modal .petty-header-icon{width:40px;height:40px;margin-right:11px;border-radius:11px;font-size:17px}
    .petty-cash-open-modal .petty-eyebrow{margin-bottom:1px;font-size:8px;letter-spacing:.14em}
    .petty-cash-open-modal .petty-premium-header h4{font-size:1.08rem}
    .petty-cash-open-modal .petty-premium-header p{font-size:.7rem}
    .petty-cash-open-modal .petty-close{width:32px;height:32px;border-radius:9px}
    .petty-cash-open-modal .petty-premium-body{padding:11px!important}
    .petty-cash-open-modal .petty-finance-summary{min-height:0;padding:13px;border-radius:13px;box-shadow:0 6px 18px rgba(38,66,57,.055)}
    .petty-cash-open-modal .petty-summary-hero{padding:0 0 10px}
    .petty-cash-open-modal .petty-summary-icon{width:48px;height:48px;margin-bottom:7px;border-radius:14px;font-size:19px;box-shadow:0 7px 15px rgba(29,117,84,.16)}
    .petty-cash-open-modal .petty-summary-hero small{font-size:8px}
    .petty-cash-open-modal .petty-summary-hero strong{margin:2px 0 5px;font-size:.9rem}
    .petty-cash-open-modal .petty-summary-hero .badge{padding:4px 8px;font-size:8px}
    .petty-cash-open-modal .petty-summary-list{padding:7px 0}
    .petty-cash-open-modal .petty-summary-list>div{padding:5px 2px;font-size:.7rem}
    .petty-cash-open-modal .petty-summary-list b{font-size:.78rem}
    .petty-cash-open-modal .petty-summary-list .petty-opening-total{margin:2px 0;padding:7px 9px;border-radius:8px}
    .petty-cash-open-modal .petty-summary-list .petty-opening-total b{font-size:.86rem}
    .petty-cash-open-modal .petty-current-balance{padding:10px 12px;border-radius:11px;box-shadow:0 7px 15px rgba(26,82,63,.12)}
    .petty-cash-open-modal .petty-current-balance span,.petty-cash-open-modal .petty-current-balance small{font-size:8px}
    .petty-cash-open-modal .petty-current-balance strong{margin:1px 0;font-size:1.3rem}
    .petty-cash-open-modal .petty-summary-note{margin-top:8px;padding:7px 8px;font-size:.63rem}
    .petty-cash-open-modal .petty-premium-card{margin-bottom:7px;padding:10px 11px 1px;border-radius:11px;box-shadow:0 3px 10px rgba(37,62,54,.035)}
    .petty-cash-open-modal .petty-section-heading{margin-bottom:8px}
    .petty-cash-open-modal .petty-section-heading>span{width:28px;height:28px;margin-right:8px;border-radius:8px;font-size:10px}
    .petty-cash-open-modal .petty-section-heading h6{font-size:.78rem}
    .petty-cash-open-modal .petty-section-heading small{font-size:.6rem}
    .petty-cash-open-modal .form-group{margin-bottom:7px}
    .petty-cash-open-modal label{margin-bottom:3px;font-size:.58rem;letter-spacing:.045em}
    .petty-cash-open-modal .form-control{height:33px;padding:.32rem .58rem;border-radius:8px;font-size:.72rem}
    .petty-cash-open-modal .petty-money-input{border-radius:8px}
    .petty-cash-open-modal .petty-money-input>span{padding:0 8px;font-size:.7rem}
    .petty-cash-open-modal .petty-balance-help{gap:5px;margin:0 0 6px;padding:6px 8px;border-radius:7px;font-size:.62rem}
    .petty-cash-open-modal .petty-fund-source-card{padding-bottom:9px}
    .petty-cash-open-modal .petty-source-upload{padding:7px 10px;border-radius:9px}
    .petty-cash-open-modal .petty-source-upload>i{margin-right:8px;font-size:16px}
    .petty-cash-open-modal .petty-source-upload span strong{font-size:.65rem}
    .petty-cash-open-modal .petty-source-upload span small{font-size:.56rem}
    .petty-cash-open-modal .petty-source-previews{grid-template-columns:repeat(3,minmax(0,1fr));gap:5px;margin-top:6px}
    .petty-cash-open-modal .petty-source-file{padding:5px;border-radius:8px}
    .petty-cash-open-modal .petty-source-file>img,.petty-cash-open-modal .petty-source-file>span{width:30px;height:30px;flex-basis:30px;border-radius:7px}
    .petty-cash-open-modal .petty-source-file strong{font-size:.56rem}
    .petty-cash-open-modal .petty-source-file small{font-size:.5rem}
    .petty-cash-open-modal .petty-source-file>a,.petty-cash-open-modal .petty-source-file>button{width:21px;height:21px}
    .petty-cash-open-modal .petty-person-row{padding:0 7px;border-radius:8px}
    .petty-cash-open-modal .petty-person-row+.petty-person-row{margin-top:4px}
    .petty-cash-open-modal .petty-observation{min-height:50px;padding:7px 9px!important;line-height:1.3}
    .petty-cash-open-modal .petty-premium-footer{position:relative;z-index:20;padding:9px 16px;background:#fff;box-shadow:0 -4px 14px rgba(30,56,47,.04)}
    .petty-cash-open-modal .petty-premium-footer .btn{min-width:105px;padding:7px 14px;border-radius:8px;font-size:.72rem}
    .petty-approved-header-actions{display:flex;align-items:center;gap:10px;margin-left:auto}
    .petty-approved-reference{display:flex;min-width:235px;padding:8px 10px;align-items:center;border:1px solid rgba(24,112,79,.2);border-left:3px solid #2b8a67;border-radius:11px;background:linear-gradient(135deg,#edf9f3 0%,#fffaf0 100%);box-shadow:0 6px 18px rgba(18,53,36,.08)}
    .petty-approved-reference>span{display:flex;width:31px;height:31px;margin-right:8px;flex:0 0 31px;align-items:center;justify-content:center;border-radius:8px;background:#d9f0e5;color:#176b4c;font-size:12px}
    .petty-approved-reference>div{min-width:0}
    .petty-approved-reference small,.petty-approved-reference strong,.petty-approved-reference em{display:block}
    .petty-approved-reference small{color:#4b6358;font-size:7px;font-weight:900;letter-spacing:.12em;opacity:1}
    .petty-approved-reference strong{margin:1px 0;color:#123524!important;font-size:.92rem;font-weight:900;line-height:1.1;opacity:1!important;-webkit-text-fill-color:#123524}
    .petty-approved-reference em{color:#64748b!important;font-size:.56rem;font-weight:600;font-style:normal;opacity:1!important}
    .petty-approved-reference>button{display:flex;width:25px;height:25px;margin-left:auto;padding:0;align-items:center;justify-content:center;border:1px solid rgba(24,112,79,.18);border-radius:7px;background:#fff;color:#176b4c;font-size:9px;cursor:pointer;box-shadow:0 2px 6px rgba(18,53,36,.07)}
    .petty-approved-reference>button:hover{border-color:#2b8a67;background:#e5f5ed;color:#0f593e}
    .petty-approved-warning{display:flex;margin-top:5px;padding:6px 8px;align-items:center;border:1px solid #f0d49a;border-radius:8px;background:#fff8e7;color:#8a6417;font-size:.64rem;font-weight:700}
    .petty-approved-warning i{margin-right:6px}
    .petty-approved-modal .modal-content{overflow:hidden;border-radius:16px;background:#f6f8f7;box-shadow:0 24px 60px rgba(20,46,39,.22)}
    .petty-approved-modal .modal-dialog{max-width:820px}
    .petty-approved-modal form{display:flex;max-height:calc(100vh - 40px);flex-direction:column}
    .petty-approved-modal .modal-header{align-items:center;padding:14px 17px;border-bottom:1px solid #e5ebe8;background:#fff}
    .petty-approved-modal-icon{display:flex;width:38px;height:38px;margin-right:10px;align-items:center;justify-content:center;border-radius:11px;background:#e1f3ea;color:#217757}
    .petty-approved-modal .modal-header small{display:block;color:#27805f;font-size:8px;font-weight:900;letter-spacing:.13em}
    .petty-approved-modal .modal-header h5{color:#263a34;font-size:1rem;font-weight:800}
    .petty-approved-modal .modal-header p{margin-top:2px;color:#85918d;font-size:.65rem}
    .petty-approved-modal .modal-body{min-height:0;padding:15px 17px;overflow-y:auto;background:#f7f9f8}
    .petty-approved-modal .form-group{margin-bottom:10px}
    .petty-approved-modal label{margin-bottom:4px;color:#697772;font-size:.6rem;font-weight:900;letter-spacing:.06em;text-transform:uppercase}
    .petty-approved-modal .form-control{height:35px;border:1px solid #dce5e1;border-radius:9px;background:#fff;font-size:.75rem;box-shadow:none}
    .petty-approved-modal textarea.form-control{height:auto;min-height:65px;resize:vertical}
    .petty-approved-input{display:flex;align-items:center;overflow:hidden;border:1px solid #b9d9cc;border-radius:9px;background:#edf8f3}
    .petty-approved-input span{padding:0 10px;color:#1b7152;font-size:.74rem;font-weight:900}
    .petty-approved-input .form-control{border:0;border-left:1px solid #d7eae2;border-radius:0;background:transparent;font-weight:800}
    .petty-approved-modal .modal-footer{padding:11px 17px;border-top:1px solid #e3e9e6;background:#fff;box-shadow:0 -5px 16px rgba(18,53,36,.035)}
    .petty-approved-modal .modal-footer .btn{min-width:108px;padding:8px 15px;border-radius:9px;font-size:.73rem;font-weight:800;transition:transform .16s ease,box-shadow .16s ease}
    .petty-approved-modal .modal-footer .btn:hover{transform:translateY(-1px);box-shadow:0 5px 12px rgba(18,53,36,.1)}
    .petty-approval-trace,.petty-approval-history{margin-top:14px;padding:14px;border:1px solid rgba(18,53,36,.1);border-radius:16px;background:#fff;box-shadow:0 10px 25px rgba(18,53,36,.055)}
    .petty-approval-trace-title{display:flex;align-items:center;margin-bottom:12px}
    .petty-approval-trace-title>i{display:flex;width:34px;height:34px;margin-right:10px;align-items:center;justify-content:center;border-radius:10px;background:#e8f7ef;color:#0f7a4f;font-size:12px;box-shadow:inset 0 0 0 1px rgba(15,122,79,.04)}
    .petty-approval-trace-title strong,.petty-approval-trace-title small{display:block}
    .petty-approval-trace-title strong{color:#123524;font-size:.78rem;font-weight:900;letter-spacing:.025em;text-transform:uppercase}
    .petty-approval-trace-title small{margin-top:2px;color:#7b8a83;font-size:.64rem}
    .petty-approval-trace-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:9px}
    .petty-approval-trace-grid>div{min-height:58px;padding:9px 11px;border:1px solid rgba(18,53,36,.06);border-radius:11px;background:linear-gradient(145deg,#f7fbf8,#fbfdfc)}
    .petty-approval-trace-grid small,.petty-approval-trace-grid strong{display:block}
    .petty-approval-trace-grid small{color:#718078;font-size:.57rem;font-weight:900;letter-spacing:.045em;text-transform:uppercase}
    .petty-approval-trace-grid strong{margin-top:4px;overflow:hidden;color:#123524;font-size:.72rem;font-weight:800;text-overflow:ellipsis;white-space:nowrap}
    .petty-approval-trace-grid .is-amount{border-color:rgba(15,122,79,.13);background:linear-gradient(145deg,#edf9f3,#f8fcfa)}
    .petty-approval-trace-grid .is-amount strong{color:#008554;font-size:.88rem;font-weight:900}
    .approval-status-badge{display:inline-flex;align-items:center;padding:4px 9px;border-radius:999px;font-size:.57rem;font-weight:900;letter-spacing:.035em}
    .approval-status-badge i{margin-right:4px;font-size:.52rem}
    .approval-status-badge.is-active{border:1px solid #bde7d0;background:#dff7ea;color:#087a45}
    .approval-status-badge.is-inactive{border:1px solid #dce3e0;background:#eef2f0;color:#66736e}
    .petty-approval-trace-grid .petty-approval-trace-notes{grid-column:1/-1}
    .petty-approval-trace-grid .petty-approval-trace-notes strong{white-space:normal}
    .petty-approval-history{padding-bottom:13px}
    .approved-history-table-wrapper{max-height:220px;overflow:auto;border:1px solid rgba(18,53,36,.075);border-radius:11px;scrollbar-width:thin;scrollbar-color:#bfd0c8 transparent}
    .petty-approval-history .table{min-width:720px;font-size:.65rem;border-collapse:separate;border-spacing:0}
    .petty-approval-history thead th{position:sticky;top:0;z-index:1;padding:9px 8px;border:0;border-bottom:1px solid rgba(18,53,36,.09);background:#f1f7f4;color:#53645c;font-size:.54rem;font-weight:900;letter-spacing:.035em;text-transform:uppercase;white-space:nowrap}
    .petty-approval-history tbody td{padding:10px 8px;border:0;border-bottom:1px solid rgba(18,53,36,.06);vertical-align:middle;color:#293b35;line-height:1.35}
    .petty-approval-history tbody tr:last-child td{border-bottom:0}
    .petty-approval-history tbody tr{transition:background .15s ease}
    .petty-approval-history tbody tr:hover{background:#f9fcfa}
    .petty-approval-history tbody td:nth-child(1),.petty-approval-history tbody td:nth-child(3),.petty-approval-history tbody td:nth-child(4){white-space:nowrap}
    .petty-approval-history tbody td:nth-child(2){min-width:125px;font-weight:700}
    .petty-approval-history tbody td:nth-child(3){color:#475569;font-weight:700}
    .petty-approval-history tbody td:nth-child(4){color:#008554;font-size:.69rem;font-weight:900}
    .petty-approval-history tbody td:nth-child(5){font-weight:800}

    .petty-detail-modal .modal-dialog{width:calc(100% - 32px);max-width:1180px;margin:16px auto}
    .petty-detail-modal .modal-content,.petty-expense-modal .modal-content{max-height:calc(100vh - 40px);overflow:hidden;border-radius:18px;background:#f4f7f6;box-shadow:0 24px 65px rgba(20,46,39,.2)}
    .petty-detail-modal .modal-content{height:auto;max-height:min(90vh,calc(100vh - 32px));border:0;border-radius:20px;background:#f6f8f7;box-shadow:0 28px 72px rgba(15,23,42,.2)}
    .petty-detail-header,.petty-expense-header{flex:0 0 auto;align-items:center;padding:14px 20px;border-bottom:1px solid #e5ebe8;background:#fff}
    .petty-detail-header{position:relative;overflow:hidden;background:linear-gradient(120deg,#fff 0%,#f8fbfa 68%,#f1f7f4 100%)}
    .petty-detail-header::after{content:"";position:absolute;right:70px;bottom:-70px;width:190px;height:190px;border-radius:50%;background:radial-gradient(circle,rgba(47,118,91,.065),rgba(47,118,91,0) 68%);pointer-events:none}
    .petty-detail-header-icon,.petty-expense-header-icon{display:flex;align-items:center;justify-content:center;width:42px;height:42px;margin-right:12px;border-radius:12px;background:linear-gradient(145deg,#e1f4eb,#cceada);color:#197652;font-size:17px}
    .petty-detail-header small,.petty-expense-header small{display:block;margin-bottom:2px;color:#23805f;font-size:10px;font-weight:800;letter-spacing:.15em}
    .petty-detail-header h4,.petty-expense-header h4{margin:0;color:#21332e;font-size:1.18rem;font-weight:800;letter-spacing:-.02em}
    .petty-detail-header p,.petty-expense-header p{margin:3px 0 0;color:#7c8884;font-size:.78rem}
    .petty-detail-heading{position:relative;z-index:1;min-width:0}
    .petty-detail-title-row{display:flex;align-items:center;gap:9px}
    .petty-status-badge{display:inline-flex;align-items:center;padding:4px 9px;border:1px solid #dfe6e3;border-radius:999px;background:#f3f5f4;color:#65726d;font-size:.55rem;font-weight:900;letter-spacing:.075em;line-height:1;text-transform:uppercase}
    .petty-status-badge::before{content:"";width:6px;height:6px;margin-right:5px;border-radius:50%;background:currentColor;box-shadow:0 0 0 3px rgba(101,114,109,.1)}
    .petty-status-badge.is-open{border-color:#bfe3d2;background:#e9f8f1;color:#167451}
    .petty-status-badge.is-open::before{box-shadow:0 0 0 3px rgba(22,116,81,.12)}
    .petty-status-badge.is-closed{border-color:#dce1df;background:#eff2f1;color:#65716d}
    #pcv_meta:not(:empty)::before{content:"•";margin:0 7px;color:#a5afab}
    .petty-detail-body,.petty-expense-body{min-height:0;overflow-x:hidden;overflow-y:auto;padding:14px 16px!important;background:#f4f7f6!important;overscroll-behavior:contain}
    .petty-detail-body{padding:13px 15px 15px!important;background:linear-gradient(180deg,#f3f6f5 0%,#f8faf9 100%)!important;scrollbar-width:thin;scrollbar-color:#c8d3ce transparent}
    .petty-detail-body::-webkit-scrollbar{width:7px}
    .petty-detail-body::-webkit-scrollbar-thumb{border-radius:10px;background:#c8d3ce}

    .petty-financial-overview{margin-bottom:9px;padding:11px 12px 12px;border:1px solid rgba(15,23,42,.065);border-radius:14px;background:rgba(255,255,255,.94);box-shadow:0 7px 22px rgba(15,23,42,.045)}
    .petty-overview-heading{display:flex;align-items:center;justify-content:space-between;margin-bottom:9px;padding:0 2px}
    .petty-overview-heading span,.petty-overview-heading small{display:block}
    .petty-overview-heading span{color:#33453f;font-size:.64rem;font-weight:900;letter-spacing:.09em}
    .petty-overview-heading small{margin-top:1px;color:#929d99;font-size:.62rem}
    .petty-overview-heading>i{display:flex;width:27px;height:27px;align-items:center;justify-content:center;border-radius:8px;background:#eef4f1;color:#55766a;font-size:10px}
    .petty-financial-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:7px;margin:0}
    .petty-financial-card{position:relative;min-height:70px;padding:10px 11px 9px 40px;overflow:hidden;border:1px solid rgba(15,23,42,.07);border-radius:11px;background:#fff;box-shadow:0 2px 8px rgba(15,23,42,.025);transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease}
    .petty-financial-card::after{content:"";position:absolute;right:-18px;bottom:-28px;width:68px;height:68px;border-radius:50%;background:currentColor;opacity:.035}
    .petty-financial-card:hover{transform:translateY(-1px);border-color:#ccd8d3;box-shadow:0 6px 16px rgba(15,23,42,.055)}
    .petty-financial-card>i{position:absolute;top:10px;left:10px;display:flex;align-items:center;justify-content:center;width:23px;height:23px;border-radius:50%;background:#edf3f0;color:#547469;font-size:9px}
    .petty-financial-card small{display:block;color:#84908c;font-size:.62rem;font-weight:800;letter-spacing:.045em;text-transform:uppercase}
    .petty-financial-card strong{position:relative;z-index:1;display:block;margin-top:4px;color:#263a34;font-size:1.02rem;font-weight:800;line-height:1.1;letter-spacing:-.02em}
    .petty-financial-card em{position:relative;z-index:1;display:block;margin-top:4px;color:#8a9692;font-size:.54rem;font-style:normal;line-height:1.25}
    .petty-financial-card.is-history>i{background:#f0f2f7;color:#66718a}
    .petty-financial-card.is-approved>i{background:#edf8f3;color:#237c5c}
    .petty-financial-card.is-opening{border-color:#dce8e3;background:linear-gradient(145deg,#fff,#f5faf7)}
    .petty-financial-card.is-opening>i{background:#dff3e9;color:#19704f}
    .petty-financial-card.is-spent{border-color:#eee2d8;background:linear-gradient(145deg,#fff,#fdf9f5)}
    .petty-financial-card.is-spent>i{background:#f8ebe1;color:#a65f36}
    .petty-financial-card.is-spent strong{color:#874925}
    .petty-financial-card.is-replenished>i{background:#e9f4fb;color:#28779f}
    .petty-financial-card.is-balance{border-color:#bcd8cc;background:linear-gradient(145deg,#f5fbf8,#eaf5f0);color:#185e47;box-shadow:inset 3px 0 0 #3e9272,0 3px 10px rgba(29,103,77,.06)}
    .petty-financial-card.is-balance>i{background:#d8eee4;color:#216e52}
    .petty-financial-card.is-balance small{color:#668178}
    .petty-financial-card.is-balance strong{color:#155c43}
    .petty-financial-card.is-pending{border-color:#ebe1c7;background:linear-gradient(145deg,#fff,#fdfaf2)}
    .petty-financial-card.is-pending>i{background:#f6edda;color:#8c6a24}
    .petty-financial-card.is-pending strong{color:#725519}
    .petty-financial-card.is-muted>i{background:#f0f2f1;color:#68746f}

    .petty-detail-card,.petty-expense-card{margin-bottom:9px;padding:11px 12px;border:1px solid rgba(15,23,42,.065);border-radius:14px;background:rgba(255,255,255,.96);box-shadow:0 7px 22px rgba(15,23,42,.04)}
    .petty-detail-card-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
    .petty-detail-card-title>div,.petty-expense-section-title{display:flex;align-items:center}
    .petty-detail-card-title>div>span,.petty-expense-section-title>span{display:flex;align-items:center;justify-content:center;width:29px;height:29px;margin-right:8px;border-radius:9px;background:#edf6f2;color:#268160;font-size:11px}
    .petty-detail-card-title h6,.petty-expense-section-title h6{margin:0;color:#293b36;font-size:.82rem;font-weight:800}
    .petty-detail-card-title small,.petty-expense-section-title small{display:block;margin-top:1px;color:#98a19e;font-size:.67rem}
    .petty-section-count{padding:4px 8px;border:1px solid #e2e9e6;border-radius:999px;background:#f7faf8;color:#75827d;font-size:.58rem;font-weight:700;white-space:nowrap}
    .petty-detail-table{margin:0;font-size:.69rem}
    .petty-detail-table{border-collapse:separate;border-spacing:0}
    .petty-detail-table thead th{padding:7px;border:0;border-bottom:1px solid #e4ebe8;background:#f3f7f5;color:#6d7c76;font-size:.58rem;font-weight:800;letter-spacing:.045em;text-transform:uppercase;white-space:nowrap}
    .petty-detail-table thead th:first-child{border-radius:8px 0 0 8px}
    .petty-detail-table thead th:last-child{border-radius:0 8px 8px 0}
    .petty-detail-table tbody td{padding:7px;border-top:1px solid #edf1ef;vertical-align:middle;color:#44534e;transition:background .15s ease}
    .petty-detail-table tbody tr:first-child td{border-top:0}
    .petty-detail-table tbody tr:hover td{background:#f9fcfa}
    .petty-row-number{display:inline-flex;align-items:center;justify-content:center;min-width:23px;height:23px;padding:0 6px;border-radius:7px;background:#eef4f1;color:#557068;font-size:.62rem;font-weight:800}
    .petty-date-cell{color:#687873!important;white-space:nowrap}
    .petty-supplier-cell{color:#30443d!important;font-weight:700}
    .petty-concept-cell{max-width:220px;color:#5b6964!important}
    .petty-amount-cell{color:#176f50!important;font-size:.73rem;font-weight:900;white-space:nowrap}
    .petty-empty-state{padding:24px 12px!important;text-align:center;background:linear-gradient(180deg,#fbfdfc,#f7faf8)}
    .petty-empty-state i{display:flex;width:42px;height:42px;margin:0 auto 8px;align-items:center;justify-content:center;border-radius:13px;background:#eaf4ef;color:#79a491;font-size:17px}
    .petty-empty-state strong{display:block;color:#64736d;font-size:.78rem}
    .petty-empty-state small{display:block;margin-top:2px;color:#a0aaa6}
    .petty-person-card{position:relative;display:flex;align-items:center;height:100%;padding:11px 42px 11px 12px;overflow:hidden;border:1px solid #dfe9e5;border-radius:11px;background:linear-gradient(145deg,#fff,#f7fbf9)}
    .petty-person-card>span{display:flex;flex:0 0 auto;align-items:center;justify-content:center;width:36px;height:36px;margin-right:10px;border-radius:11px;background:linear-gradient(145deg,#e5f5ed,#d4ebdf);color:#237b5b;box-shadow:inset 0 0 0 1px rgba(35,123,91,.05)}
    .petty-person-card small{display:block;color:#8a9692;font-size:.62rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
    .petty-person-card strong{display:block;margin:2px 0;color:#2c3e38;font-size:.82rem}
    .petty-person-card em{color:#75827d;font-size:.68rem;font-style:normal}
    .petty-person-card em i{margin-right:3px;color:#8da099}
    .petty-person-check{position:absolute;right:13px;display:flex;width:20px;height:20px;align-items:center;justify-content:center;border-radius:50%;background:#e3f4eb;color:#27815f;font-size:8px}
    .petty-row-actions{display:inline-flex;align-items:center;gap:5px;white-space:nowrap}
    .petty-row-actions .btn,.petty-document-btn{position:relative;z-index:2;display:inline-flex;align-items:center;justify-content:center;width:27px;height:27px;padding:0;border:1px solid transparent;border-radius:8px;font-size:10px;cursor:pointer;transition:transform .15s ease,box-shadow .15s ease,background .15s ease}
    .petty-row-actions .editPettyCashExpense{background:#fff3cd;color:#8a6400}
    .petty-row-actions .deletePettyCashExpense{background:#fde8e9;color:#b52a37}
    .petty-row-actions .btn:hover,.petty-document-btn:hover{transform:translateY(-1px);box-shadow:0 4px 9px rgba(42,70,61,.11);text-decoration:none}
    .petty-document-btn{border-color:#cfe5dd;background:#edf8f4;color:#27785b}
    .petty-document-btn+.petty-document-btn{margin-left:3px}
    .petty-document-btn:hover{background:#dff2ea;color:#176447}
    .petty-no-document{color:#a0aaa6;font-size:.58rem;white-space:nowrap}
    .petty-table-status{display:inline-flex;padding:3px 7px;border-radius:999px;background:#e7f6ef;color:#207556;font-size:.55rem;font-weight:800;text-transform:uppercase}
    .petty-cash-tooltip,.petty-cash-tooltip *{pointer-events:none!important}

    .petty-expense-modal .modal-dialog{width:calc(100% - 32px);max-width:1120px;margin:16px auto}
    .petty-expense-modal .modal-content>form{display:flex;flex:1 1 auto;min-height:0;flex-direction:column;overflow:hidden}
    .petty-expense-modal .modal-content{border-radius:20px;background:#f5f8f6;box-shadow:0 28px 72px rgba(15,23,42,.2)}
    .petty-expense-layout{align-items:stretch}
    .petty-receipts-panel{display:flex;min-height:100%;padding:13px;flex-direction:column;border:1px solid rgba(15,23,42,.07);border-radius:14px;background:linear-gradient(160deg,#fff,#f7faf8);box-shadow:0 7px 22px rgba(15,23,42,.045)}
    .petty-receipts-heading{display:flex;align-items:center;margin-bottom:11px}
    .petty-receipts-heading>span{display:flex;width:31px;height:31px;margin-right:9px;align-items:center;justify-content:center;border-radius:9px;background:#e7f4ee;color:#26785a;font-size:11px}
    .petty-receipts-heading h6{margin:0;color:#293b36;font-size:.84rem;font-weight:800}
    .petty-receipts-heading small{display:block;margin-top:1px;color:#929d99;font-size:.64rem}
    .petty-receipts-heading b{display:flex;min-width:23px;height:23px;margin-left:auto;padding:0 7px;align-items:center;justify-content:center;border-radius:999px;background:#edf3f0;color:#597168;font-size:.62rem}
    .petty-receipts-dropzone{display:flex!important;margin:0 0 10px!important;padding:15px 10px;align-items:center;flex-direction:column;border:1px dashed #a9cabb;border-radius:12px;background:#f4faf7;color:#526a61;cursor:pointer;text-align:center;text-transform:none!important;transition:border-color .18s ease,background .18s ease}
    .petty-receipts-dropzone:hover{border-color:#4c9a7b;background:#edf8f3}
    .petty-receipts-dropzone>i{margin-bottom:6px;color:#398567;font-size:22px}
    .petty-receipts-dropzone strong{font-size:.72rem}
    .petty-receipts-dropzone small{margin-top:2px;color:#8a9893;font-size:.6rem;font-weight:500;letter-spacing:0;text-transform:none}
    .petty-receipts-dropzone input{position:absolute;width:1px;height:1px;opacity:0}
    .petty-receipts-list{display:flex;max-height:338px;gap:7px;flex-direction:column;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#c8d3ce transparent}
    .petty-receipt-item{display:flex;min-height:54px;padding:7px;align-items:center;border:1px solid #e1e8e5;border-radius:10px;background:#fff}
    .petty-receipt-item>img,.petty-receipt-pdf{display:flex;width:40px;height:40px;flex:0 0 40px;align-items:center;justify-content:center;border-radius:8px;object-fit:cover}
    .petty-receipt-pdf{background:#fbeaea;color:#bd3f49;font-size:18px}
    .petty-receipt-meta{min-width:0;margin-left:8px}
    .petty-receipt-meta strong,.petty-receipt-meta small{display:block}
    .petty-receipt-meta strong{max-width:150px;overflow:hidden;color:#3b4c46;font-size:.66rem;text-overflow:ellipsis;white-space:nowrap}
    .petty-receipt-meta small{margin-top:2px;color:#98a29e;font-size:.56rem}
    .petty-receipt-actions{display:flex;gap:4px;margin-left:auto}
    .petty-receipt-action{display:flex;width:25px;height:25px;padding:0;align-items:center;justify-content:center;border:0;border-radius:7px;cursor:pointer;font-size:9px;transition:.15s ease}
    .petty-receipt-action.is-view{background:#e8f4ef;color:#267657}
    .petty-receipt-action.is-remove{background:#faecee;color:#b53d49}
    .petty-receipt-action:hover{transform:translateY(-1px);filter:brightness(.97);text-decoration:none}
    .petty-receipts-empty{display:flex;min-height:130px;padding:18px;align-items:center;justify-content:center;flex-direction:column;border-radius:11px;background:#f8faf9;text-align:center}
    .petty-receipts-empty>i{display:flex;width:38px;height:38px;margin-bottom:7px;align-items:center;justify-content:center;border-radius:11px;background:#eaf1ee;color:#8ba097}
    .petty-receipts-empty strong{color:#687873;font-size:.69rem}
    .petty-receipts-empty small{margin-top:2px;color:#a0aaa6;font-size:.59rem}
    .petty-expense-section-title{margin-bottom:13px}
    .petty-expense-modal .form-group{margin-bottom:11px}
    .petty-expense-modal label{margin-bottom:5px;color:#6d7975;font-size:.64rem;font-weight:800;letter-spacing:.055em;text-transform:uppercase}
    .petty-expense-modal .form-control{height:38px;border:1px solid #dfe6e3;border-radius:9px;background:#fbfcfc;color:#2f403b;font-size:.8rem;box-shadow:none}
    .petty-expense-modal textarea.form-control{height:auto;min-height:64px;resize:vertical}
    .petty-expense-modal .form-control:focus{border-color:#58a98b;background:#fff;box-shadow:0 0 0 3px rgba(39,139,100,.09)}
    .petty-expense-amount{display:flex;align-items:center;overflow:hidden;border:1px solid #a9d2c2;border-radius:9px;background:#eef8f3}
    .petty-expense-amount span{padding:0 10px;color:#176d4e;font-size:.78rem;font-weight:800}
    .petty-expense-amount .form-control{border:0;border-left:1px solid #d9ebe4;border-radius:0;background:transparent;font-weight:800}
    .petty-file-control{display:flex!important;align-items:center;margin:0!important;padding:13px 15px;border:1px dashed #acd0c1;border-radius:11px;background:#f7fbf9;cursor:pointer;text-transform:none!important}
    .petty-file-control>i{margin-right:12px;color:#278160;font-size:24px}
    .petty-file-control span strong,.petty-file-control span small{display:block}
    .petty-file-control span strong{color:#40514b;font-size:.77rem}
    .petty-file-control span small{margin-top:2px;color:#8b9692;font-size:.68rem}
    .petty-file-control input{position:absolute;width:1px;height:1px;opacity:0}
    .petty-expense-footer{flex:0 0 auto;padding:13px 20px;border-top:1px solid #e3e9e6;background:#fff}
    .petty-expense-footer .btn{min-width:125px;padding:9px 17px;border-radius:10px;font-size:.78rem;font-weight:700}

    .petty-replenishment-modal .modal-dialog{width:calc(100% - 28px);max-width:980px;margin:14px auto}
    .petty-replenishment-modal .modal-content{max-height:calc(100vh - 28px);overflow:hidden;border:0;border-radius:18px;background:#f5f8f6;box-shadow:0 28px 72px rgba(15,23,42,.22)}
    .petty-replenishment-modal form{display:flex;min-height:0;flex:1 1 auto;flex-direction:column;overflow:hidden}
    .petty-replenishment-header{flex:0 0 auto;align-items:center;padding:13px 18px;border-bottom:1px solid #e2e9e6;background:linear-gradient(120deg,#fff,#f3f8f5)}
    .petty-replenishment-title{display:flex;min-width:0;align-items:center}
    .petty-replenishment-header-icon{display:flex;width:40px;height:40px;margin-right:11px;align-items:center;justify-content:center;border-radius:12px;background:linear-gradient(145deg,#e3f4ec,#d1eade);color:#237a59;font-size:15px;box-shadow:inset 0 0 0 1px rgba(35,122,89,.06)}
    .petty-replenishment-title small{display:block;color:#43816c;font-size:.57rem;font-weight:900;letter-spacing:.1em}
    .petty-replenishment-title h4{margin:1px 0 0;color:#263b34;font-size:1.05rem;font-weight:800;line-height:1.2}
    .petty-replenishment-title p{margin:2px 0 0;color:#84918c;font-size:.66rem}
    .petty-replenishment-header .close{display:flex;width:32px;height:32px;padding:0;align-items:center;justify-content:center;border-radius:9px;color:#64736e;font-size:1.25rem;opacity:1}
    .petty-replenishment-header .close:hover{background:#eaf1ee;color:#29463b}
    .petty-replenishment-body{min-height:0;max-height:calc(100vh - 150px);padding:11px 13px!important;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#bdcbc5 transparent}
    .petty-replenishment-summary,.petty-replenishment-section{margin-bottom:9px;padding:11px 12px;border:1px solid rgba(15,23,42,.065);border-radius:14px;background:#fff;box-shadow:0 6px 18px rgba(15,23,42,.04)}
    .petty-replenishment-identity{display:flex;align-items:center;justify-content:space-between;margin-bottom:9px;padding-bottom:8px;border-bottom:1px solid #edf1ef}
    .petty-replenishment-identity small{display:block;color:#8b9893;font-size:.55rem;font-weight:800;letter-spacing:.07em}
    .petty-replenishment-identity h5{margin:1px 0 0;color:#236e53;font-size:.9rem;font-weight:900}
    .petty-replenishment-identity strong{display:block;max-width:380px;margin-top:1px;overflow:hidden;color:#354a42;font-size:.72rem;text-overflow:ellipsis;white-space:nowrap}
    .petty-replenishment-kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:6px}
    .petty-replenishment-kpi{position:relative;min-width:0;padding:8px 7px 7px 30px;overflow:hidden;border:1px solid #e6ece9;border-radius:10px;background:#fafcfb}
    .petty-replenishment-kpi>i{position:absolute;top:8px;left:7px;display:flex;width:18px;height:18px;align-items:center;justify-content:center;border-radius:6px;background:#eaf2ee;color:#59756b;font-size:7px}
    .petty-replenishment-kpi small{display:block;overflow:hidden;color:#87938e;font-size:.48rem;font-weight:900;letter-spacing:.035em;text-overflow:ellipsis;text-transform:uppercase;white-space:nowrap}
    .petty-replenishment-kpi strong{display:block;margin-top:3px;overflow:hidden;color:#334840;font-size:.7rem;font-weight:900;text-overflow:ellipsis;white-space:nowrap}
    .petty-replenishment-kpi.is-approved>i{background:#e6f1fb;color:#34749a}
    .petty-replenishment-kpi.is-spent{border-color:#eee2d4;background:#fdfaf6}.petty-replenishment-kpi.is-spent>i{background:#f8ead9;color:#a66a2f}
    .petty-replenishment-kpi.is-replenished>i{background:#e8f2fb;color:#3479a1}
    .petty-replenishment-kpi.is-balance{border-color:#cae2d8;background:#f2faf6}.petty-replenishment-kpi.is-balance>i{background:#dcefe6;color:#247357}.petty-replenishment-kpi.is-balance strong{color:#176347}
    .petty-replenishment-kpi.is-pending{border-color:#ecdcc4;background:#fff9f1}.petty-replenishment-kpi.is-pending>i{background:#f7e7d2;color:#aa672d}.petty-replenishment-kpi.is-pending strong{color:#97541e}
    .petty-replenishment-status{display:flex;width:max-content;max-width:100%;margin-top:8px;padding:5px 9px;align-items:center;border:1px solid #efd8bd;border-radius:999px;background:#fff8ef;color:#9a5a25;font-size:.62rem;font-weight:700}
    .petty-replenishment-status i{margin-right:6px}.petty-replenishment-status strong{margin-left:4px}
    .petty-replenishment-status.is-complete{border-color:#cfe5db;background:#f0faf5;color:#277458}
    .petty-replenishment-section-title{display:flex;align-items:center;margin-bottom:9px}
    .petty-replenishment-section-title>span{display:flex;width:29px;height:29px;margin-right:8px;align-items:center;justify-content:center;border-radius:9px;background:#e7f3ed;color:#26795a;font-size:10px}
    .petty-replenishment-section-title h6{margin:0;color:#2c4039;font-size:.8rem;font-weight:800}
    .petty-replenishment-section-title small{display:block;margin-top:1px;color:#929d99;font-size:.59rem}
    .petty-replenishment-modal .form-group{margin-bottom:5px}
    .petty-replenishment-modal label{margin-bottom:4px;color:#6f7d78;font-size:.58rem;font-weight:900;letter-spacing:.055em;text-transform:uppercase}
    .petty-replenishment-modal .form-control{height:35px;border:1px solid #dce5e1;border-radius:9px;background:#fbfdfc;color:#30443d;font-size:.74rem;box-shadow:none}
    .petty-replenishment-modal .form-control:focus{border-color:#62aa8e;background:#fff;box-shadow:0 0 0 3px rgba(40,137,101,.08)}
    .petty-replenishment-amount{display:flex;align-items:center;border:1px solid #bcdccc;border-radius:9px;background:#f0f9f4}
    .petty-replenishment-amount>i{padding:0 10px;color:#26795a;font-size:10px}
    .petty-replenishment-amount .form-control{border:0;border-left:1px solid #d9eae3;border-radius:0;background:transparent;font-weight:900}
    .petty-replenishment-source{border-color:#d8e9e1;background:linear-gradient(145deg,#fff,#f4faf7)}
    .petty-replenishment-upload{min-height:54px;margin:5px 0 0!important;padding:9px 12px!important;border-radius:11px!important;background:#f6fbf8!important}
    .petty-replenishment-upload>i{font-size:20px!important}.petty-replenishment-upload strong{font-size:.68rem!important}.petty-replenishment-upload small{font-size:.57rem!important}
    .petty-replenishment-modal .petty-source-previews{grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;margin-top:7px}
    .petty-replenishment-modal .petty-source-file{min-height:48px;padding:6px;border-radius:9px}
    .petty-replenishment-modal .petty-source-file>img,.petty-replenishment-modal .petty-source-file>span{width:34px;height:34px}
    .petty-replenishment-modal .petty-source-file strong{font-size:.59rem}.petty-replenishment-modal .petty-source-file small{font-size:.52rem}
    .petty-replenishment-observation textarea.form-control{height:48px;min-height:48px;resize:vertical}
    .petty-replenishment-footer{position:relative;z-index:2;flex:0 0 auto;padding:10px 14px;border-top:1px solid #e0e8e4;background:#fff;box-shadow:0 -6px 16px rgba(15,23,42,.035)}
    .petty-replenishment-footer .btn{min-width:118px;padding:8px 15px;border-radius:9px;font-size:.72rem;font-weight:800}
    .petty-exchange-badge{display:inline-flex;padding:4px 7px;border-radius:999px;font-size:.55rem;font-weight:900;white-space:nowrap}.petty-exchange-badge.is-pending{background:#fff2d8;color:#8a631d}.petty-exchange-badge.is-completed{background:#def3e8;color:#1f7152}
    .petty-exchange-history{display:grid;gap:7px}.petty-exchange-history-item{display:grid;grid-template-columns:190px 1fr 120px;gap:10px;padding:9px;border:1px solid #e2e9e6;border-radius:10px;background:#fafcfb}.petty-exchange-history-item small,.petty-exchange-history-item strong,.petty-exchange-history-item span{display:block}.petty-exchange-history-item strong{color:#2f463e;font-size:.72rem}.petty-exchange-history-item span{color:#1e7051;font-size:.7rem;font-weight:900}.petty-exchange-history-item small{color:#8d9995;font-size:.56rem}.petty-exchange-history-item ul{margin:0;padding-left:17px;color:#5d6d67;font-size:.62rem}
    .petty-receipt-exchange-modal .modal-content{max-height:calc(100vh - 28px);overflow:hidden;border:0;border-radius:18px;background:#f5f8f6;box-shadow:0 28px 72px rgba(15,23,42,.22)}.petty-receipt-exchange-modal form{display:flex;min-height:0;flex-direction:column}.petty-receipt-exchange-modal .modal-body{min-height:0;overflow-y:auto;padding:11px 13px}.petty-receipt-exchange-modal label{margin-bottom:4px;color:#6f7d78;font-size:.58rem;font-weight:900;letter-spacing:.05em;text-transform:uppercase}.petty-receipt-exchange-modal .form-control{height:35px;border:1px solid #dce5e1;border-radius:9px;font-size:.73rem;box-shadow:none}.petty-receipt-exchange-modal textarea.form-control{height:48px}.petty-exchange-total{padding:12px;border-radius:12px;background:linear-gradient(145deg,#eaf7f1,#dff1e8);text-align:center}.petty-exchange-total small{display:block;color:#618075;font-size:.55rem;font-weight:900;letter-spacing:.08em}.petty-exchange-total strong{display:block;margin:3px 0;color:#176247;font-size:1.35rem;font-weight:900}.petty-exchange-total span{color:#789087;font-size:.56rem}

    @media(max-width:991px){.petty-cash-modal .modal-dialog{width:calc(100% - 20px);max-height:calc(100vh - 20px);margin:10px auto}.petty-cash-modal .modal-content{max-height:calc(100vh - 20px)}.petty-finance-summary{min-height:auto}.petty-premium-body{padding:14px!important}.petty-cash-open-modal .petty-premium-body{padding:9px!important}.petty-cash-open-modal .petty-source-previews{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media(max-width:991px){.petty-financial-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.petty-detail-modal .modal-content,.petty-expense-modal .modal-content{max-height:calc(100vh - 20px)}.petty-receipts-panel{min-height:auto}.petty-receipts-list{max-height:220px}}
    @media(max-width:768px){.petty-approval-trace-grid{grid-template-columns:1fr}.petty-approval-trace-grid .petty-approval-trace-notes{grid-column:auto}.approved-history-table-wrapper{max-height:210px;overflow:auto}.petty-approved-modal .modal-dialog{margin:10px}.petty-approved-modal form{max-height:calc(100vh - 20px)}}
    @media(max-width:575px){.petty-premium-header{padding:16px}.petty-header-icon{width:42px;height:42px}.petty-premium-header h4{font-size:1.08rem}.petty-premium-header p{display:none}.petty-premium-footer{justify-content:stretch}.petty-premium-footer .btn{flex:1}.petty-approved-reference{min-width:0}.petty-approved-reference>span,.petty-approved-reference em{display:none}.petty-approved-reference strong{font-size:.75rem}}
    @media(max-width:575px){.petty-detail-header,.petty-expense-header{padding:14px}.petty-detail-header-icon,.petty-expense-header-icon{width:40px;height:40px}.petty-detail-header h4,.petty-expense-header h4{font-size:1.05rem}.petty-detail-header p,.petty-expense-header p{font-size:.68rem}.petty-detail-body,.petty-expense-body{padding:12px!important}.petty-financial-grid,.petty-source-previews,.petty-source-detail{grid-template-columns:1fr}.petty-section-count{display:none}.petty-expense-footer{justify-content:stretch}.petty-expense-footer .btn{flex:1}}
    @media(max-width:767px){.petty-replenishment-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.petty-replenishment-modal .petty-source-previews{grid-template-columns:1fr}.petty-replenishment-title p{display:none}.petty-replenishment-footer{justify-content:stretch}.petty-replenishment-footer .btn{flex:1}.petty-exchange-history-item{grid-template-columns:1fr}}
</style>
@endpush

@push('js')
<script>
window.pettyCashRoutes = {
    list: @json(route('admin.petty-cash.list')),
    base: @json(url('admin/petty-cash'))
};
</script>
@vite(['resources/js/pages/petty-cash.js'])
@endpush
