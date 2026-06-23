@extends('layouts.app')

@section('subtitle', 'Clientes')

@section('header')

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-users text-primary"></i>
                    Clientes
                </h1>

                <small class="text-muted">
                    Gestión y administración de clientes del sistema.
                </small>
            </div>

            <div>
                <button class="btn btn-primary shadow-sm px-4" type="button" data-toggle="modal" data-target="#customerModal">

                    <i class="fas fa-plus-circle mr-1"></i>
                    Nuevo Cliente
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
                            Clientes
                        </li>

                    </ol>

                </nav>

            </div>
        </div>

    </div>

@stop


@section('content_body')


    <div id="customerLoading" class="modern-loading">

        <div class="loading-card">

            <div class="loading-spinner">
                <i class="fas fa-search"></i>
            </div>

            <div class="loading-content">

                <h4 id="loadingTitle">
                    Consultando Documento
                </h4>

                <p id="loadingText">
                    Buscando información en SUNAT / RENIEC...
                </p>

            </div>

        </div>

    </div>

    <div class="card border-0 shadow-lg rounded-xl">

        <div class="card-header bg-white border-0 pt-4 pb-2">

            <div class="d-flex justify-content-between align-items-center flex-wrap">

                <div>

                    <h5 class="mb-1 font-weight-bold text-dark">

                        <i class="fas fa-list text-primary"></i>
                        Lista de Clientes

                    </h5>

                    <small class="text-muted">
                        Clientes registrados en el sistema
                    </small>

                </div>

            </div>

        </div>

        <div class="card-body pt-2">

            <div class="table-responsive">

                <table id="tableCustomer" class="table table-hover align-middle text-center w-100">

                    <thead class="bg-light">

                        <tr>

                            <th width="5%">#</th>

                            <th width="8%">ID</th>

                            <th>NOMBRE COMPLETO</th>

                            <th width="12%">DOCUMENTO</th>

                            <th width="12%">N° DOC</th>

                            <th width="12%">TELÉFONO</th>

                            <th width="18%">EMAIL</th>

                            <th width="10%">ESTADO</th>

                            <th width="13%">ACCIONES</th>

                        </tr>

                    </thead>

                    <tbody></tbody>

                </table>

            </div>

        </div>

    </div>

    {{-- MODAL --}}
    @include('admin.customers.partials.modal')
    @include('admin.customers.partials.viewModal')
    @include('admin.customers.partials.branchModal')

@stop


@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableCustomer thead th {
            border: none !important;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            padding: 15px;
            white-space: nowrap;
        }

        #tableCustomer tbody td {
            vertical-align: middle !important;
            padding: 14px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;
        }

        #tableCustomer tbody tr:hover {
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
    </style>
@endpush


@push('js')
    <script>
        window.routes = {

            /* ==========================
             * CLIENTES
             * ========================== */
            customerList: "{{ route('admin.customers.list') }}",
            consultarDocumento: "{{ route('admin.customers.consultar', 'DOC_PLACEHOLDER') }}",
            searchUbigeo: "{{ route('admin.customers.searchUbigeo') }}",

            /* ==========================
             * SEDES
             * ========================== */
            customerBranchList: "{{ url('admin/customer-branches/list') }}",
            customerBranchStore: "{{ route('admin.customer-branches.store') }}",
            customerBranchUpdate: "{{ url('admin/customer-branches') }}",
            customerBranchDelete: "{{ url('admin/customer-branches') }}",

            /* ==========================
             * CONTACTOS DE SEDE
             * ========================== */
            customerBranchContactList: "{{ url('admin/customer-branch-contacts/list') }}",
            customerBranchContactStore: "{{ route('admin.customer-branch-contacts.store') }}",
            customerBranchContactUpdate: "{{ url('admin/customer-branch-contacts') }}",
            customerBranchContactDelete: "{{ url('admin/customer-branch-contacts') }}",
            customerBranchesByCustomer: "{{ url('admin/customer-branches/by-customer') }}",
        };
    </script>

    @vite(['resources/js/pages/customer.js'])
@endpush
