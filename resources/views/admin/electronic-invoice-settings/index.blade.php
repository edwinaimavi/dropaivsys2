@extends('layouts.app')

@section('subtitle', 'Configuracion Facturacion Electronica')

@section('header')
    <div class="container-fluid">
        <h1 class="mb-1 font-weight-bold text-dark">
            <i class="fas fa-cogs text-success"></i>
            Configuraci&oacute;n de Facturaci&oacute;n Electr&oacute;nica
        </h1>
        <small class="text-muted">Credenciales y datos de empresa para futura integraci&oacute;n con APIs Per&uacute;</small>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow">
        <div class="card-body">
            <p class="text-muted mb-0">
                La estructura backend de configuraci&oacute;n ya est&aacute; disponible. La pantalla operativa principal est&aacute; en
                <a href="{{ route('admin.electronic-invoices.index') }}">Facturaci&oacute;n Electr&oacute;nica</a>.
            </p>
        </div>
    </div>
@stop
