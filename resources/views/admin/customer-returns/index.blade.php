@extends('layouts.app')

@section('subtitle', 'Devoluciones de clientes')

@section('header')
<div class="container-fluid"><h1 class="font-weight-bold mb-1"><i class="fas fa-undo-alt text-primary mr-2"></i>Devoluciones de clientes</h1><small class="text-muted">Reingreso físico trazable desde salidas confirmadas</small></div>
@stop

@section('content_body')
<div class="card border-0 shadow-sm customer-return-filter"><div class="card-body"><div class="form-row align-items-end">
    <div class="col-lg-3 col-md-6 form-group mb-lg-0"><label>Búsqueda</label><input id="crFilterSearch" class="form-control" placeholder="DEV, OC, SAL o cliente"></div>
    <div class="col-lg-3 col-md-6 form-group mb-lg-0"><label>Empresa</label><select id="crFilterCompany" class="form-control"><option value="">Todas las autorizadas</option>@foreach($companies as $company)<option value="{{ $company->id }}">{{ $company->trade_name ?: $company->business_name }}</option>@endforeach</select></div>
    <div class="col-lg-2 col-md-4 form-group mb-lg-0"><label>Estado</label><select id="crFilterStatus" class="form-control"><option value="">Todos</option><option value="draft">Borrador</option><option value="confirmed">Confirmada</option><option value="cancelled">Cancelada</option><option value="reversed">Reversada</option></select></div>
    <div class="col-lg-2 col-md-4 form-group mb-lg-0"><label>Desde</label><input id="crFilterFrom" type="date" class="form-control"></div>
    <div class="col-lg-2 col-md-4 form-group mb-lg-0"><label>Hasta</label><input id="crFilterTo" type="date" class="form-control"></div>
</div></div></div>
<div class="card border-0 shadow-lg"><div class="card-header bg-white border-0"><h5 class="mb-0 font-weight-bold">Historial de devoluciones</h5></div><div class="card-body"><div class="table-responsive"><table id="customerReturnsTable" class="table table-hover w-100"><thead class="bg-light"><tr><th>DEV</th><th>FECHA</th><th>EMPRESA</th><th>CLIENTE</th><th>OC CLIENTE</th><th>SAL</th><th>ALMACÉN</th><th>CANTIDAD</th><th>ESTADO</th><th>COMPROBANTE</th><th>ACCIONES</th></tr></thead></table></div></div></div>
@include('admin.customer-returns.partials.modal')
@stop

@push('css')
<style>
.customer-return-filter,.customer-return-items-table{font-size:.86rem}.customer-return-hero{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.customer-return-hero>div{padding:12px;border:1px solid #dfe7ef;border-radius:10px;background:#f8fafc}.customer-return-hero small,.customer-return-hero strong{display:block}.customer-return-hero small{font-size:10px;color:#64748b;font-weight:700}.customer-return-hero strong{margin-top:4px;color:#172b4d}.customer-return-items-table thead th{white-space:nowrap;font-size:11px}.cr-detail-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}.cr-detail-metrics>div{padding:14px;border-radius:10px;background:#f3f7fb}.cr-document{display:flex;justify-content:space-between;align-items:center;padding:10px;border-bottom:1px solid #eee}@media(max-width:768px){.customer-return-hero,.cr-detail-metrics{grid-template-columns:1fr}.modal-xl{max-width:98%}}
</style>
@endpush

@push('js')
<script>window.customerReturnRoutes={list:@json(route('admin.customer-returns.list')),base:@json(url('admin/customer-returns')),dispatchData:@json(url('admin/customer-returns/dispatches'))};window.customerReturnPermissions={create:@json(auth()->user()->can('devoluciones_clientes.crear')),edit:@json(auth()->user()->can('devoluciones_clientes.editar')),confirm:@json(auth()->user()->can('devoluciones_clientes.confirmar')),cancel:@json(auth()->user()->can('devoluciones_clientes.cancelar')),reverse:@json(auth()->user()->can('devoluciones_clientes.reversar')),documents:@json(auth()->user()->can('devoluciones_clientes.documentos'))};window.customerReturnDocumentTypes=@json($documentTypes);window.customerReturnDeepLink=@json(request('return_id'));</script>
@endpush
