@extends('layouts.app')

@section('subtitle', 'Bancos / Tesorería')

@section('header')
<div class="container-fluid bank-page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div class="d-flex align-items-center mb-2 mb-md-0">
            <span class="bank-header-icon"><i class="fas fa-university"></i></span>
            <div><h1 class="mb-1 font-weight-bold">Bancos / Tesorería</h1><p class="mb-0 text-muted">Control de saldos, movimientos, transferencias y conciliación bancaria.</p></div>
        </div>
        @can('admin.banks.export')
        <div class="btn-group btn-group-sm bank-export-actions">
            <a class="btn btn-outline-success" href="{{route('admin.banks.export','excel')}}"><i class="fas fa-file-excel mr-1"></i>Excel</a>
            <a class="btn btn-outline-danger" href="{{route('admin.banks.export','pdf')}}" target="_blank"><i class="fas fa-file-pdf mr-1"></i>PDF</a>
            <a class="btn btn-outline-secondary" href="{{route('admin.banks.export','print')}}" target="_blank"><i class="fas fa-print mr-1"></i>Imprimir</a>
        </div>
        @endcan
    </div>
    <nav class="mt-3"><ol class="breadcrumb bg-transparent p-0 mb-0"><li class="breadcrumb-item"><a href="{{route('home')}}">Home</a></li><li class="breadcrumb-item active">Bancos / Tesorería</li></ol></nav>
</div>
@stop

@section('content_body')
<div class="bank-summary-grid">
    @foreach([
        ['bankSummaryTotal','Total bancos en soles','fa-landmark','is-total'],
        ['bankSummaryIncome','Ingresos del periodo','fa-arrow-circle-down','is-income'],
        ['bankSummaryExpense','Egresos del periodo','fa-arrow-circle-up','is-expense'],
        ['bankSummaryAvailable','Saldo disponible','fa-wallet','is-balance'],
        ['bankSummaryPending','Pendientes de conciliación','fa-clock','is-pending'],
    ] as [$id,$label,$icon,$tone])
    <article class="bank-summary-card {{$tone}}"><span><i class="fas {{$icon}}"></i></span><div><small>{{$label}}</small><strong id="{{$id}}">—</strong></div></article>
    @endforeach
</div>

<div class="card border-0 shadow-sm bank-filter-card">
    <div class="card-body py-3"><div class="form-row align-items-end">
        <div class="form-group col-md-3 mb-md-0"><label>EMPRESA</label><select id="bankFilterCompany" class="form-control form-control-sm"><option value="">Todas</option>@foreach($companies as $company)<option value="{{$company->id}}">{{$company->trade_name ?: $company->business_name}}</option>@endforeach</select></div>
        <div class="form-group col-md-2 mb-md-0"><label>MONEDA</label><select id="bankFilterCurrency" class="form-control form-control-sm"><option value="">Todas</option>@foreach($currencies as $currency)<option value="{{$currency->id}}">{{$currency->code}}</option>@endforeach</select></div>
        <div class="form-group col-md-2 mb-md-0"><label>ESTADO</label><select id="bankFilterStatus" class="form-control form-control-sm"><option value="">Todos</option><option value="ACTIVE">Activa</option><option value="INACTIVE">Inactiva</option></select></div>
        <div class="form-group col-md-2 mb-md-0"><label>DESDE</label><input type="date" id="bankFilterFrom" class="form-control form-control-sm" value="{{now()->startOfMonth()->toDateString()}}"></div>
        <div class="form-group col-md-2 mb-md-0"><label>HASTA</label><input type="date" id="bankFilterTo" class="form-control form-control-sm" value="{{now()->endOfMonth()->toDateString()}}"></div>
        <div class="form-group col-md-1 mb-0"><button id="btnBankFilter" class="btn btn-info btn-sm btn-block" title="Aplicar"><i class="fas fa-search"></i></button></div>
    </div></div>
</div>

<div class="card border-0 shadow-sm bank-table-card">
    <div class="card-header bg-white border-0"><h6 class="mb-1 font-weight-bold">Cuentas bancarias</h6><small class="text-muted">Las cuentas provienen del maestro configurado en Empresas.</small></div>
    <div class="card-body pt-2"><div class="table-responsive"><table id="tableBankAccounts" class="table table-hover w-100">
        <thead><tr><th>#</th><th>Banco</th><th>Empresa</th><th>Moneda</th><th>Titular</th><th>Nro. cuenta</th><th>CCI</th><th>Saldo inicial</th><th>Ingresos</th><th>Egresos</th><th>Saldo actual</th><th>Estado</th><th>Acciones</th></tr></thead>
    </table></div></div>
</div>

@include('admin.banks.partials.modals')
@stop

@push('css')
<style>
.bank-page-header h1{font-size:1.7rem;color:#263d36}.bank-header-icon{width:48px;height:48px;display:inline-flex;align-items:center;justify-content:center;border-radius:14px;margin-right:13px;background:#e3f4ed;color:#187657;font-size:21px}.bank-summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:13px;margin-bottom:16px}.bank-summary-card{display:flex;align-items:center;gap:11px;padding:15px;border:1px solid #e1eae6;border-radius:13px;background:#fff;box-shadow:0 5px 16px rgba(36,67,57,.05)}.bank-summary-card>span{width:39px;height:39px;display:flex;align-items:center;justify-content:center;border-radius:11px;background:#edf5f2;color:#27775d}.bank-summary-card small{display:block;color:#7b8984;font-size:9px;text-transform:uppercase;font-weight:800;letter-spacing:.04em}.bank-summary-card strong{display:block;color:#2c443b;font-size:17px;white-space:nowrap}.bank-summary-card.is-income>span{background:#e5f6ed;color:#16754c}.bank-summary-card.is-expense>span{background:#fcebec;color:#a63b45}.bank-summary-card.is-pending>span{background:#fff5df;color:#9a6b15}.bank-filter-card,.bank-table-card{border-radius:14px}.bank-filter-card label{font-size:10px;color:#687871;font-weight:800}.bank-actions-menu i{width:20px}.bank-modal .modal-content{border:0;border-radius:16px;overflow:hidden;box-shadow:0 22px 65px rgba(28,55,47,.23)}.bank-modal .modal-header{background:linear-gradient(135deg,#fff,#f0f8f4);border-bottom:1px solid #e1ebe7}.bank-modal-title-icon{width:42px;height:42px;border-radius:12px;display:inline-flex;align-items:center;justify-content:center;background:#dff2e9;color:#187457;margin-right:11px}.bank-modal .modal-body{background:#fafcfc}.bank-modal label{font-size:10px;font-weight:800;color:#63736d}.bank-transfer-help{min-height:15px}.bank-modal .select2-selection.is-invalid{border-color:#dc3545!important;box-shadow:0 0 0 .12rem rgba(220,53,69,.08)}.bank-detail-tabs{display:flex;flex-wrap:nowrap;gap:6px;overflow-x:auto;padding:10px 14px;background:#f2f7f5}.bank-detail-tabs .nav-link{white-space:nowrap;border-radius:8px;color:#60716a;font-size:11px;font-weight:700}.bank-detail-tabs .nav-link.active{background:#fff;color:#187457;box-shadow:0 3px 10px rgba(31,88,68,.1)}.bank-detail-content{padding:18px;max-height:67vh;overflow-y:auto}.bank-detail-kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.bank-detail-kpi{padding:13px;border:1px solid #e1eae6;border-radius:10px;background:#fff}.bank-detail-kpi small{display:block;color:#7b8984;font-size:9px;text-transform:uppercase}.bank-detail-kpi strong{display:block;color:#2d493f;font-size:16px}.bank-detail-table{font-size:11px}.bank-detail-table thead th{white-space:nowrap;background:#f0f6f3;color:#61716b;font-size:9px;text-transform:uppercase}.bank-status{display:inline-flex;padding:4px 7px;border-radius:999px;font-size:9px;font-weight:800}.bank-status.REGISTRADO,.bank-status.ACTIVE{background:#e4f2fb;color:#2c709a}.bank-status.CONCILIADO,.bank-status.CERRADA{background:#e4f5ec;color:#16714b}.bank-status.ANULADO,.bank-status.ANULADA{background:#fae7e9;color:#a53d47}.bank-trace-item{display:flex;gap:11px;padding:10px 0;border-bottom:1px solid #edf1ef}.bank-trace-item>span{width:31px;height:31px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:#edf6f2;color:#26755a}.bank-trace-item small{display:block;color:#84908c}.bank-reconcile-list{max-height:260px;overflow:auto;border:1px solid #e0e9e5;border-radius:9px}.bank-empty{padding:25px;text-align:center;color:#8a9792}@media(max-width:1199.98px){.bank-summary-grid{grid-template-columns:repeat(3,1fr)}}@media(max-width:767.98px){.bank-summary-grid{grid-template-columns:repeat(2,1fr)}.bank-summary-card:last-child{grid-column:1/-1}.bank-export-actions{width:100%;margin-top:10px}.bank-export-actions .btn{flex:1}.bank-modal .modal-dialog{margin:6px}.bank-detail-kpis{grid-template-columns:repeat(2,1fr)}.bank-detail-content{padding:12px;max-height:64vh}}@media(max-width:420px){.bank-summary-grid{grid-template-columns:1fr}.bank-summary-card:last-child{grid-column:auto}}
</style>
@endpush

@push('js')
<script>
window.bankTreasuryRoutes={
 list:@json(route('admin.banks.list')),
 show:@json(url('admin/banks/accounts')),
 opening:@json(url('admin/banks/accounts')),
 movements:@json(route('admin.banks.movements.store')),
 transfers:@json(route('admin.banks.transfers.store')),
 reconciliations:@json(route('admin.banks.reconciliations.store')),
 cancelMovement:@json(url('admin/banks/movements')),
 cancelTransfer:@json(url('admin/banks/transfers')),
 exportAccount:@json(url('admin/banks/accounts')),
 files:@json(url('admin/banks/files'))
};
window.bankTreasuryPermissions={cancel:@json(auth()->user()->can('admin.banks.movements.cancel')),export:@json(auth()->user()->can('admin.banks.export'))};
window.bankTreasurySources={
 customerOrders:@json($customerOrders),
 supplierOrders:@json($supplierOrders),
 pettyCashBoxes:@json($pettyCashBoxes),
 pettyCashReplenishments:@json($pettyCashReplenishments),
 warehouseExpenses:@json($warehouseExpenses)
};
</script>
@vite('resources/js/pages/bank-treasury.js')
@endpush
