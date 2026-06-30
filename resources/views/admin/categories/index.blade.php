@extends('layouts.app')

@section('subtitle', 'Categorías')

@section('header')

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">

            <div>

                <h1 class="mb-1 font-weight-bold text-dark">

                    <i class="fas fa-tags text-success"></i>
                    Categorías

                </h1>

                <small class="text-muted">
                    Gestión y administración de categorías del sistema.
                </small>

            </div>

            <div>

                <button class="btn btn-success shadow-sm px-4" type="button" data-toggle="modal" data-target="#categoryModal">

                    <i class="fas fa-plus-circle mr-1"></i>
                    Nueva Categoría

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

                            Categorías

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

                        <i class="fas fa-list text-success"></i>
                        Lista de Categorías

                    </h5>

                    <small class="text-muted">
                        Categorías registradas en el sistema
                    </small>

                </div>

            </div>

        </div>

        <div class="card-body pt-2">

            <div class="table-responsive">

                <table id="tableCategory" class="table table-hover align-middle text-center w-100">

                    <thead class="bg-light">

                        <tr>

                            <th>#</th>

                            <th>ID</th>

                            <th>DESCRIPCIÓN</th>

                            <th>CÓDIGO</th>

                            <th>TIPO</th>

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
    @include('admin.categories.partials.modal')

    @include('admin.categories.partials.viewModal')

    @include('admin.categories.partials.subcategoryModal')

@stop

@push('css')
    <style>
        .rounded-xl {

            border-radius: 18px;

        }

        #tableCategory thead th {

            border: none !important;
            font-size: 13px;
            font-weight: 700;
            color: #555;
            padding: 15px;
            white-space: nowrap;

        }

        #tableCategory tbody td {

            vertical-align: middle !important;
            padding: 14px;
            border-top: 1px solid #f1f1f1;
            font-size: 14px;

        }

        #tableCategory tbody tr:hover {

            background: #fafafa;
            transition: .2s ease;

        }

        .breadcrumb {

            margin-bottom: 0;

        }

        .btn-success {

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

        /* =========================================================
    RESPONSIVE TABLE SUBCATEGORY
    ========================================================= */

        #tableSubcategory {

            width: 100% !important;

        }

        #tableSubcategory thead th,
        #tableSubcategory tbody td {

            white-space: nowrap;

        }

        .dataTables_wrapper .dataTables_filter {

            text-align: right;

        }

        .dataTables_wrapper .dataTables_length {

            text-align: left;

        }

        @media (max-width: 768px) {

            .modal-dialog {

                margin: 5px;
                max-width: 100%;

            }

            .modal-body {

                padding: 10px !important;

            }

            .subcategory-info-card {

                margin-bottom: 10px;

            }

            .subcategory-form-card,
            .subcategory-table-card {

                padding: 10px;

            }

            #tableSubcategory {

                font-size: 12px;

            }

            #tableSubcategory tbody td {

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
            categoryList: "{{ route('admin.categories.list') }}",

            storeCategory: "{{ route('admin.categories.store') }}",

            updateCategory: "{{ url('admin/categories') }}",

            deleteCategory: "{{ url('admin/categories') }}",

            showCategory: "{{ url('admin/categories') }}",

            generateCode: "{{ route('admin.categories.generateCode') }}",

            subcategoryList: "{{ url('admin/categories') }}",

            storeSubcategory: "{{ route('admin.categories.subcategories.store') }}",

            updateSubcategory: "{{ url('admin/categories/subcategories') }}",

            deleteSubcategory: "{{ url('admin/categories/subcategories') }}",


        }
    </script>

    @vite(['resources/js/pages/category.js'])
@endpush
