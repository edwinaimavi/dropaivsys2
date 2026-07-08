@extends('layouts.app')

@section('subtitle', 'Agencias de Envio')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-shipping-fast text-success"></i>
                    Agencias de Env&iacute;o
                </h1>
                <small class="text-muted">
                    Gesti&oacute;n de agencias, sedes y contactos para despacho de mercader&iacute;a.
                </small>
            </div>

            @can('admin.shipping-agencies.store')
                <button id="btnCreateShippingAgency" class="btn btn-success shadow-sm px-4" type="button">
                    <i class="fas fa-plus-circle mr-1"></i>
                    Nueva Agencia
                </button>
            @endcan
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}" class="text-decoration-none">
                        <i class="fas fa-house-user"></i>
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item active">Agencias de Env&iacute;o</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-success"></i>
                Lista de Agencias
            </h5>
            <small class="text-muted">Empresas de transporte y despacho registradas</small>
        </div>

        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableShippingAgency" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>C&Oacute;DIGO</th>
                            <th>RUC</th>
                            <th>RAZ&Oacute;N SOCIAL</th>
                            <th>NOMBRE COMERCIAL</th>
                            <th>TIPO</th>
                            <th>TEL&Eacute;FONO</th>
                            <th>ESTADO</th>
                            <th width="150">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.shipping-agencies.partials.modal')
    @include('admin.shipping-agencies.partials.viewModal')
@stop

@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableShippingAgency thead th {
            padding: 14px 10px;
            border: 0 !important;
            color: #555;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        #tableShippingAgency tbody td {
            padding: 12px 8px;
            border-top: 1px solid #f1f1f1;
            font-size: 13px;
            vertical-align: middle !important;
        }

        #tableShippingAgency tbody tr:hover {
            background: #fafafa;
        }

        .shipping-agency-modal-dialog {
            max-width: 94%;
        }

        .shipping-agency-tab-pane {
            min-height: 330px;
        }

        .shipping-agency-row-table th,
        .shipping-agency-row-table td {
            white-space: nowrap;
            vertical-align: middle !important;
        }

        .shipping-agency-row-table input,
        .shipping-agency-row-table select {
            min-width: 120px;
        }

        .shipping-agency-row-table .wide-field {
            min-width: 230px;
        }

        .shipping-agency-side-total {
            padding: 10px;
            border-radius: 12px;
            color: #fff;
            background: linear-gradient(135deg, #198754, #157347);
            font-size: 18px;
            font-weight: 800;
        }

        .breadcrumb {
            margin-bottom: 0;
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            shippingAgencyList: "{{ route('admin.shipping-agencies.list') }}",
            shippingAgencyStore: "{{ route('admin.shipping-agencies.store') }}",
            shippingAgencyShow: "{{ url('admin/shipping-agencies') }}",
            shippingAgencyUpdate: "{{ url('admin/shipping-agencies') }}",
            shippingAgencyDelete: "{{ url('admin/shipping-agencies') }}",
            consultarRucShippingAgency: "{{ url('admin/shipping-agencies/consultar-ruc') }}",
        });
    </script>
    @vite(['resources/js/pages/shipping-agency.js'])
@endpush
