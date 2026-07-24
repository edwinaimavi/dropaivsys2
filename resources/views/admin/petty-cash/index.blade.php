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
        @can('admin.petty-cash.store')
        <button id="btnCreatePettyCash" class="btn btn-success shadow-sm px-4" type="button"
            data-toggle="modal" data-target="#pettyCashModal">
            <i class="fas fa-plus-circle mr-1"></i> Aperturar caja
        </button>
        @endcan
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
    data-ruc-url="{{ url('admin/suppliers/consultar-ruc') }}"
    data-dni-url="{{ route('admin.customers.consultar', 'DNI_PLACEHOLDER') }}"
    data-can-expense-update="{{ auth()->user()->can('admin.petty-cash.expenses.update') ? 1 : 0 }}"
    data-can-expense-delete="{{ auth()->user()->can('admin.petty-cash.expenses.destroy') ? 1 : 0 }}">
    <div class="row mb-3">
        <div class="col-lg-3 col-sm-6"><div class="info-box shadow-sm border-0"><span class="info-box-icon bg-success"><i class="fas fa-wallet"></i></span><div class="info-box-content"><span class="info-box-text">Cajas activas</span><span id="pcKpiOpen" class="info-box-number">0</span></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="info-box shadow-sm border-0"><span class="info-box-icon bg-info"><i class="fas fa-coins"></i></span><div class="info-box-content"><span class="info-box-text">Fondo visible</span><span id="pcKpiFund" class="info-box-number">0.00</span></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="info-box shadow-sm border-0"><span class="info-box-icon bg-warning"><i class="fas fa-receipt"></i></span><div class="info-box-content"><span class="info-box-text">Total gastado</span><span id="pcKpiSpent" class="info-box-number">0.00</span></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="info-box shadow-sm border-0"><span class="info-box-icon bg-secondary"><i class="fas fa-sync-alt"></i></span><div class="info-box-content"><span class="info-box-text">Pendiente reposición</span><span id="pcKpiPending" class="info-box-number">0.00</span></div></div></div>
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
                            <th>INICIO</th><th>FIN</th><th>FONDO</th><th>GASTADO</th><th>SALDO</th>
                            <th>REPOSICIÓN</th><th>ESTADO</th><th>F. REGISTRO</th><th width="240">ACCIONES</th>
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
@stop

@push('css')
<style>
    .petty-modal-content{border-radius:.5rem;overflow:hidden}.petty-modal-header{background:linear-gradient(90deg,#fff,#f3f6f8);border-bottom:1px solid #e6eaee;color:#343a40;padding:1rem 1.25rem}.petty-modal-header p{margin:0;color:#6c757d}.petty-modal-header small{color:#28a745;font-weight:700;letter-spacing:.08em}.petty-form-card,.petty-detail-section{background:#fff;border:1px solid #e6eaee;border-radius:.5rem;padding:14px;margin-bottom:12px;box-shadow:0 .125rem .25rem rgba(0,0,0,.04)}.petty-opening-fund{background:#f2faf6;border-color:#cfe8da}.petty-form-card label{font-size:.75rem;text-transform:uppercase;color:#59636b}.petty-form-card h6,.petty-detail-section h6{color:#343a40;font-weight:700;margin-bottom:14px}.petty-side-panel{background:#fff;border-radius:.5rem;padding:18px;box-shadow:0 .125rem .5rem rgba(0,0,0,.08);height:100%}.petty-side-icon{width:80px;height:80px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;border-radius:50%;background:linear-gradient(135deg,#28a745,#1e7e34);color:#fff;font-size:30px}.petty-side-panel>small,.petty-side-panel>strong{display:block;text-align:center}.petty-side-panel>strong{margin:4px 0 16px}.petty-side-row{display:flex;justify-content:space-between;padding:9px 0;border-top:1px solid #edf0ee}.petty-side-row.total{color:#1e7e34}.petty-detail-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.petty-summary-item{padding:12px;border:1px solid #e6eaee;border-radius:.5rem;background:#f8fbf9}.petty-summary-item small{display:block;color:#6c757d}.btn-group .btn{margin-right:2px}.modal-body{background:#fafafa}#pettyCashModal .modal-body>.row>.col-lg-8{order:2}#pettyCashModal .modal-body>.row>.col-lg-4{order:1}@media(max-width:767px){.petty-detail-summary{grid-template-columns:repeat(2,1fr)}}

    #pettyCashModal .modal-dialog{max-width:1180px}
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
    .petty-person-row{padding:2px 10px 0;border-radius:10px;background:#fafcfb}
    .petty-person-row+.petty-person-row{margin-top:7px}
    .petty-observation{height:auto!important;min-height:76px;resize:vertical;padding:10px 12px!important;line-height:1.45}
    .petty-premium-footer{padding:14px 22px;border-top:1px solid #e4eae7;background:#fff}
    .petty-premium-footer .btn{min-width:118px;padding:9px 18px;border-radius:10px;font-size:.8rem;font-weight:700}
    .petty-btn-secondary{border:1px solid #e1e6e4;background:#f7f8f8;color:#63716c}
    .petty-btn-primary{border-color:#21805e;background:linear-gradient(135deg,#258762,#1d7052);box-shadow:0 7px 16px rgba(32,123,89,.18)}
    @media(max-width:991px){.petty-finance-summary{min-height:auto}.petty-premium-body{padding:14px!important}}
    @media(max-width:575px){.petty-premium-header{padding:16px}.petty-header-icon{width:42px;height:42px}.petty-premium-header h4{font-size:1.08rem}.petty-premium-header p{display:none}.petty-premium-footer{justify-content:stretch}.petty-premium-footer .btn{flex:1}}
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
