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
    .petty-modal-content{border-radius:.5rem;overflow:hidden}.petty-modal-header{background:linear-gradient(90deg,#fff,#f3f6f8);border-bottom:1px solid #e6eaee;color:#343a40;padding:1rem 1.25rem}.petty-modal-header p{margin:0;color:#6c757d}.petty-modal-header small{color:#28a745;font-weight:700;letter-spacing:.08em}.petty-form-card,.petty-detail-section{background:#fff;border:1px solid #e6eaee;border-radius:.5rem;padding:14px;margin-bottom:12px;box-shadow:0 .125rem .25rem rgba(0,0,0,.04)}.petty-form-card h6,.petty-detail-section h6{color:#343a40;font-weight:700;margin-bottom:14px}.petty-side-panel{background:#fff;border-radius:.5rem;padding:18px;box-shadow:0 .125rem .5rem rgba(0,0,0,.08);height:100%}.petty-side-icon{width:80px;height:80px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;border-radius:50%;background:linear-gradient(135deg,#28a745,#1e7e34);color:#fff;font-size:30px}.petty-side-panel>small,.petty-side-panel>strong{display:block;text-align:center}.petty-side-panel>strong{margin:4px 0 16px}.petty-side-row{display:flex;justify-content:space-between;padding:9px 0;border-top:1px solid #edf0ee}.petty-side-row.total{color:#1e7e34}.petty-detail-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px}.petty-summary-item{padding:12px;border:1px solid #e6eaee;border-radius:.5rem;background:#f8fbf9}.petty-summary-item small{display:block;color:#6c757d}.btn-group .btn{margin-right:2px}.modal-body{background:#fafafa}#pettyCashModal .modal-body>.row>.col-lg-8{order:2}#pettyCashModal .modal-body>.row>.col-lg-4{order:1}@media(max-width:767px){.petty-detail-summary{grid-template-columns:repeat(2,1fr)}}
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
