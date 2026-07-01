@extends('layouts.app')

@section('subtitle', 'Unidades')

@section('header')

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

            <div>

                <h1 class="mb-1 font-weight-bold text-dark">

                    <i class="fas fa-balance-scale text-primary"></i>
                    Unidades

                </h1>

                <small class="text-muted">
                    Gestión y administración de unidades del sistema.
                </small>

            </div>

            <div>

                @can('admin.units.store')
                <button class="btn btn-primary shadow-sm px-4" type="button" data-toggle="modal" data-target="#unitModal">

                    <i class="fas fa-plus-circle mr-1"></i>
                    Nueva Unidad

                </button>
                @endcan

            </div>

        </div>

        <div class="row">

            <div class="col-12">

                <nav aria-label="breadcrumb">

                    <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">

                        <li class="breadcrumb-item">

                            <a href="{{ route('home') }}" class="text-decoration-none">

                                <i class="fas fa-house-user"></i>
                                Home

                            </a>

                        </li>

                        <li class="breadcrumb-item active">

                            Unidades

                        </li>

                    </ol>

                </nav>

            </div>

        </div>

    </div>

@stop

@section('content_body')

    <div class="card border-0 shadow-lg rounded-xl">

        <div class="card-header bg-white border-0 pt-4 pb-2">

            <div class="d-flex justify-content-between align-items-center flex-wrap">

                <div>

                    <h5 class="mb-1 font-weight-bold text-dark">

                        <i class="fas fa-list text-primary"></i>
                        Lista de Unidades

                    </h5>

                    <small class="text-muted">
                        Unidades registradas en el sistema
                    </small>

                </div>

            </div>

        </div>

        <div class="card-body pt-2">

            <div class="table-responsive">

                <table id="tableUnit" class="table table-hover align-middle text-center w-100">

                    <thead class="bg-light">

                        <tr>

                            <th>#</th>

                            <th>ID</th>

                            <th>ABREVIATURA</th>

                            <th>DESCRIPCIÓN</th>

                        {{--     <th>DECIMAL</th> --}}

                            <th>ESTADO</th>

                            <th width="160px">ACCIONES</th>

                        </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

    {{-- MODALS --}}
    @include('admin.units.partials.modal')

    @include('admin.units.partials.viewModal')

@stop

@push('css')
    <style>
        .rounded-xl {

            border-radius: 18px;

        }

        #tableUnit thead th {

            border: none !important;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            padding: 15px;
            white-space: nowrap;

        }

        #tableUnit tbody td {

            vertical-align: middle !important;
            padding: 14px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;

        }

        #tableUnit tbody tr:hover {

            background: #fafafa;
            transition: .2s ease;

        }

        .breadcrumb {

            margin-bottom: 0;

        }

        .btn-primary {

            border-radius: 10px;

        }

        .card {

            overflow: hidden;

        }

        .form-group {

            margin-bottom: .7rem;

        }

        .modal-body {

            padding: .8rem !important;

        }

        .card-body {

            padding: .9rem !important;

        }

        .alert {

            padding: .7rem .9rem;

        }

        .form-control-sm {

            font-size: .82rem;

        }

        .modal-title {

            font-size: 1rem;

        }

        .icon_modal {

            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;

        }

        @media (max-width: 768px) {

            .modal-dialog {

                margin: 5px;
                max-width: 100%;

            }

            .modal-body {

                padding: 10px !important;

            }

            #tableUnit {

                font-size: 12px;

            }

            #tableUnit tbody td {

                padding: 8px 6px;

            }

            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_length {

                text-align: center !important;
                width: 100%;
                margin-bottom: 10px;

            }

            .dataTables_wrapper .dataTables_filter input {

                width: 100% !important;
                margin-left: 0 !important;

            }

            .btn-group-sm>.btn,
            .btn-sm {

                padding: .20rem .35rem;
                font-size: 11px;

            }

        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = {

            unitList: "{{ route('admin.units.list') }}",

            storeUnit: "{{ route('admin.units.store') }}",

            updateUnit: "{{ url('admin/units') }}",

            deleteUnit: "{{ url('admin/units') }}",

        }
    </script>

    @vite(['resources/js/pages/unit.js'])
@endpush
