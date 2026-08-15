@extends('layouts.app')

@section('subtitle', 'Caja General')

@section('header')
<div class="container-fluid general-cash-page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div class="d-flex align-items-center mb-2 mb-md-0">
            <span class="general-cash-header-icon"><i class="fas fa-cash-register"></i></span>
            <div><h1 class="mb-1 font-weight-bold">Caja General</h1><p class="mb-0 text-muted">Control de efectivo, gastos generales y movimientos.</p></div>
        </div>
        <div class="d-flex flex-wrap general-cash-header-actions">
            @can('admin.general-cash.reports')
                <div class="btn-group btn-group-sm mr-2 mb-2">
                    <a class="btn btn-outline-success" href="{{ route('admin.general-cash.export', 'excel') }}"><i class="fas fa-file-excel mr-1"></i>Excel</a>
                    <a class="btn btn-outline-danger" href="{{ route('admin.general-cash.export', 'pdf') }}" target="_blank"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
                    <a class="btn btn-outline-secondary" href="{{ route('admin.general-cash.export', 'print') }}" target="_blank"><i class="fas fa-print mr-1"></i>Imprimir</a>
                </div>
            @endcan
            @can('admin.general-cash.store')<button id="btnNewGeneralCash" class="btn btn-success btn-sm mb-2"><i class="fas fa-plus mr-1"></i>Nueva caja general</button>@endcan
        </div>
    </div>
    <nav class="mt-3"><ol class="breadcrumb bg-transparent p-0 mb-0"><li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li><li class="breadcrumb-item">Finanzas</li><li class="breadcrumb-item active">Caja General</li></ol></nav>
</div>
@stop

@section('content_body')
<div class="general-cash-summary-grid">
    @foreach([
        ['generalCashSummaryTotal','Total Caja General','fa-cash-register','is-total'],
        ['generalCashSummaryIncome','Ingresos del periodo','fa-arrow-down','is-income'],
        ['generalCashSummaryExpense','Egresos del periodo','fa-arrow-up','is-expense'],
        ['generalCashSummaryAvailable','Saldo disponible','fa-wallet','is-balance'],
        ['generalCashSummaryPending','Pendientes / observados','fa-exclamation-circle','is-pending'],
    ] as [$id,$label,$icon,$tone])
        <article class="general-cash-summary-card {{ $tone }}"><span><i class="fas {{ $icon }}"></i></span><div><small>{{ $label }}</small><strong id="{{ $id }}">—</strong></div></article>
    @endforeach
</div>

<div class="card border-0 shadow-sm general-cash-filter-card">
    <div class="card-body py-3"><div class="form-row align-items-end">
        <div class="form-group col-lg-3 col-md-4 mb-md-0"><label>EMPRESA</label><select id="generalCashFilterCompany" class="form-control form-control-sm"><option value="">Todas</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->trade_name ?: $company->business_name }}</option>@endforeach</select></div>
        <div class="form-group col-lg-2 col-md-4 mb-md-0"><label>MONEDA</label><select id="generalCashFilterCurrency" class="form-control form-control-sm"><option value="">Todas</option>@foreach($currencies as $currency)<option value="{{ $currency->id }}">{{ $currency->code }}</option>@endforeach</select></div>
        <div class="form-group col-lg-2 col-md-4 mb-md-0"><label>ESTADO</label><select id="generalCashFilterStatus" class="form-control form-control-sm"><option value="">Todos</option><option value="ACTIVE">Activa</option><option value="INACTIVE">Inactiva</option></select></div>
        <div class="form-group col-lg-2 col-md-4 mb-md-0"><label>DESDE</label><input type="date" id="generalCashFilterFrom" class="form-control form-control-sm" value="{{ now()->startOfMonth()->toDateString() }}"></div>
        <div class="form-group col-lg-2 col-md-4 mb-md-0"><label>HASTA</label><input type="date" id="generalCashFilterTo" class="form-control form-control-sm" value="{{ now()->endOfMonth()->toDateString() }}"></div>
        <div class="form-group col-lg-1 col-md-4 mb-0"><button id="btnGeneralCashFilter" class="btn btn-info btn-sm btn-block"><i class="fas fa-search"></i></button></div>
    </div></div>
</div>

<div class="card border-0 shadow-sm general-cash-table-card">
    <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap">
        <div><h6 class="mb-1 font-weight-bold">Cajas físicas registradas</h6><small class="text-muted">Cada caja mantiene su propio libro, moneda, responsable y trazabilidad.</small></div>
        <div class="btn-group btn-group-sm mt-2 mt-md-0">
            @can('admin.general-cash.replenishments')<button id="btnGeneralCashFunding" class="btn btn-outline-primary"><i class="fas fa-university mr-1"></i>Ingresar efectivo</button>@endcan
            @can('admin.general-cash.expenses.store')<button id="btnGeneralCashExpense" class="btn btn-outline-danger"><i class="fas fa-receipt mr-1"></i>Registrar gasto</button>@endcan
            @can('admin.general-cash.close')<button id="btnGeneralCashReconciliation" class="btn btn-outline-warning"><i class="fas fa-balance-scale mr-1"></i>Arqueo</button>@endcan
        </div>
    </div>
    <div class="card-body pt-2"><div class="table-responsive"><table id="tableGeneralCash" class="table table-hover w-100">
        <thead><tr><th>#</th><th>Código</th><th>Caja</th><th>Empresa</th><th>Moneda</th><th>Responsable</th><th>Ingresos</th><th>Egresos</th><th>Saldo</th><th>Estado</th><th>Acciones</th></tr></thead>
    </table></div></div>
</div>

@include('admin.general-cash.partials.modals')
@stop

@push('css')
@vite('resources/css/general-cash.css')
@endpush

@push('js')
<script>
window.generalCashRoutes = {
    list: @json(route('admin.general-cash.list')),
    store: @json(route('admin.general-cash.store')),
    show: @json(url('admin/general-cash/boxes')),
    bankAccounts: @json(route('admin.general-cash.bank-accounts')),
    funding: @json(route('admin.general-cash.fundings.store')),
    cancelFunding: @json(url('admin/general-cash/fundings')),
    expense: @json(route('admin.general-cash.expenses.store')),
    expenses: @json(url('admin/general-cash/expenses')),
    reconciliation: @json(route('admin.general-cash.reconciliations.store'))
};
window.generalCashPermissions = {
    approve: @json(auth()->user()->can('admin.general-cash.expenses.approve')),
    cancelExpense: @json(auth()->user()->can('admin.general-cash.expenses.annul')),
    cancelFunding: @json(auth()->user()->can('admin.general-cash.annul')),
    documents: @json(auth()->user()->can('admin.general-cash.documents'))
};
window.generalCashBoxes = @json($boxes);
window.generalCashAutoOpenBoxId = @json($autoOpenBoxId);
</script>
@vite('resources/js/pages/general-cash.js')
@endpush
