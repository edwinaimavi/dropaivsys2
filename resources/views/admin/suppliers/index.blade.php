@extends('layouts.app')

@section('subtitle', 'Proveedores')

@section('header')

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

            <div>

                <h1 class="mb-1 font-weight-bold text-dark">

                    <i class="fas fa-truck text-info"></i>
                    Proveedores

                </h1>

                <small class="text-muted">
                    Gestión y administración de proveedores del sistema.
                </small>

            </div>

            <div>

                <button class="btn btn-primary shadow-sm px-4" type="button" data-toggle="modal" data-target="#supplierModal">

                    <i class="fas fa-plus-circle mr-1"></i>
                    Nuevo Proveedor

                </button>

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

                            Proveedores

                        </li>

                    </ol>

                </nav>

            </div>

        </div>

    </div>

@stop

@section('content_body')
    <!-- LOADING MODERNO -->
    <div id="supplierLoading" class="modern-loading">

        <div class="loading-card">

            <div class="loading-spinner">
                <i class="fas fa-search"></i>
            </div>

            <div class="loading-content">

                <h4 id="loadingTitle">
                    Consultando RUC
                </h4>

                <p id="loadingText">
                    Buscando información en SUNAT...
                </p>

            </div>

        </div>

    </div>

    <div class="card border-0 shadow-lg rounded-xl">

        <div class="card-header bg-white border-0 pt-4 pb-2">

            <div class="d-flex justify-content-between align-items-center flex-wrap">

                <div>

                    <h5 class="mb-1 font-weight-bold text-dark">

                        <i class="fas fa-list text-info"></i>
                        Lista de Proveedores

                    </h5>

                    <small class="text-muted">
                        Proveedores registrados en el sistema
                    </small>

                </div>

            </div>

        </div>

        <div class="card-body pt-2">

            <div class="table-responsive">

                <table id="tableSupplier" class="table table-hover align-middle text-center w-100">

                    <thead class="bg-light">

                        <tr>

                            <th>#</th>

                            <th>ID</th>

                            <th>RUC</th>

                            <th>RAZÓN SOCIAL</th>

                            <th>NOMBRE CORTO</th>

                            <th>TIPO</th>

                            <th>CONDICIÓN PAGO</th>

                            <th>TELÉFONO</th>

                            <th>ESTADO</th>

                            <th width="180px">ACCIONES</th>

                        </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

    {{-- MODALS --}}
    @include('admin.suppliers.partials.modal')

    @include('admin.suppliers.partials.viewModal')

    @include('admin.suppliers.partials.supplierAccountModal')

@stop

@push('css')
    <style>
        .rounded-xl {

            border-radius: 18px;

        }

        #tableSupplier thead th {

            border: none !important;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            padding: 15px;
            white-space: nowrap;

        }

        #tableSupplier tbody td {

            vertical-align: middle !important;
            padding: 14px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;

        }

        #tableSupplier tbody tr:hover {

            background: #fafafa;
            transition: .2s ease;

        }

        .breadcrumb {

            margin-bottom: 0;

        }

        .btn-info {

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

            #tableSupplier {

                font-size: 12px;

            }

            #tableSupplier tbody td {

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

            supplierList: "{{ route('admin.suppliers.list') }}",

            storeSupplier: "{{ route('admin.suppliers.store') }}",

            updateSupplier: "{{ url('admin/suppliers') }}",

            deleteSupplier: "{{ url('admin/suppliers') }}",

            searchUbigeo: "{{ route('admin.suppliers.searchUbigeo') }}",

            consultarRuc: "{{ url('admin/suppliers/consultar-ruc') }}",

            supplierAccountsList: "{{ url('admin/supplier-accounts/list') }}",

            supplierAccountsStore: "{{ route('admin.supplier-accounts.store') }}",

            supplierAccountsDelete: "{{ url('admin/supplier-accounts') }}",
            
            supplierAccountsView: "{{ url('admin/suppliers') }}"

        }
    </script>
    @vite(['resources/js/pages/supplier.js'])
@endpush
