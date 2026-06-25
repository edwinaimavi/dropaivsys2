@extends('layouts.app')

@section('subtitle', 'Usuarios')

@section('header')

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

            <div>

                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-users-cog text-primary"></i>
                    Usuarios
                </h1>

                <small class="text-muted">
                    Gestion y administracion de accesos al sistema.
                </small>

            </div>

            @can('admin.users.store')
                <div>
                    <button id="btnCreateUser" class="btn btn-primary shadow-sm px-4" type="button" data-toggle="modal"
                        data-target="#userModal">
                        <i class="fas fa-plus-circle mr-1"></i>
                        Nuevo Usuario
                    </button>
                </div>
            @endcan

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
                            Usuarios
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
                        Lista de Usuarios
                    </h5>
                    <small class="text-muted">
                        Usuarios registrados en el sistema
                    </small>
                </div>
            </div>

        </div>

        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableUser" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>DNI</th>
                            <th>NOMBRE</th>
                            <th>EMAIL</th>
                            <th>CELULAR</th>
                            <th>ESTADO</th>
                            <th width="160px">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

    </div>

    @include('admin.users.partials.modal')
    @include('admin.users.partials.viewModal')

@stop

@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableUser thead th {
            border: none !important;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            padding: 15px;
            white-space: nowrap;
        }

        #tableUser tbody td {
            vertical-align: middle !important;
            padding: 14px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;
        }

        #tableUser tbody tr:hover {
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

        .form-control-sm {
            font-size: .82rem;
        }

        .modal-title {
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .modal-dialog {
                margin: 5px;
                max-width: 100%;
            }

            #tableUser {
                font-size: 12px;
            }

            #tableUser tbody td {
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
            storeUser: "{{ route('admin.users.store') }}",
            usersList: "{{ route('admin.users.list') }}",
            deleteUser: "{{ url('admin/users') }}"
        }

        function previewImage(event, querySelector) {
            let input = event.target;
            let imgPreview = document.querySelector(querySelector);
            if (!input.files.length) return;
            let file = input.files[0];
            let objectURL = URL.createObjectURL(file);
            imgPreview.src = objectURL;
        }
    </script>
    @vite(['resources/js/pages/user.js'])
@endpush
