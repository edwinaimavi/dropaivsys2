@extends('layouts.app')

@section('subtitle', 'Catálogos SUNAT')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-book text-primary"></i>
                    Catálogos SUNAT
                </h1>
                <small class="text-muted">Valores base para documentos, tributos, monedas y unidades.</small>
            </div>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}" class="text-decoration-none">
                        <i class="fas fa-home"></i>
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('admin.electronic-invoices.index') }}" class="text-decoration-none">Facturación Electrónica</a>
                </li>
                <li class="breadcrumb-item active">Catálogos SUNAT</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-table text-primary mr-1"></i>
                Listado de catálogos
            </h5>
            <small class="text-muted">Catálogos técnicos utilizados por el módulo de facturación electrónica.</small>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="tableSunatCatalogs" class="table table-hover table-sm text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>Catálogo</th>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th>Nombre corto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@stop

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            sunatCatalogList: "{{ route('admin.sunat-catalogs.list') }}"
        });
    </script>
    @vite(['resources/js/pages/sunat-catalog.js'])
@endpush
