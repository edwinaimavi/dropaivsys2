@extends('layouts.app')

@section('subtitle', 'Series Facturacion Electronica')

@section('header')
    <div class="container-fluid">
        <h1 class="mb-1 font-weight-bold text-dark">
            <i class="fas fa-list-ol text-success"></i>
            Series de Facturaci&oacute;n Electr&oacute;nica
        </h1>
        <small class="text-muted">Control de series y correlativos por empresa, tipo y ambiente</small>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow">
        <div class="card-body">
            <p class="text-muted mb-0">
                Las rutas JSON para listar, crear, editar y obtener correlativo ya est&aacute;n disponibles. Puedes crear
                series desde API interna o desde la siguiente fase visual de administraci&oacute;n.
            </p>
        </div>
    </div>
@stop
