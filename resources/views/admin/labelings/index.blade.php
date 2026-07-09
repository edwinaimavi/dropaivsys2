@extends('layouts.app')

@section('subtitle', 'Rotulación')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-tags text-warning"></i>
                    Rotulación
                </h1>
                <small class="text-muted">Generación de rótulos para cajas abastecidas.</small>
            </div>

            @can('admin.labelings.store')
                <button id="btnCreateLabeling" class="btn btn-warning shadow-sm px-4" type="button">
                    <i class="fas fa-plus-circle mr-1"></i>
                    Nueva Rotulación
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
                <li class="breadcrumb-item active">Rotulación</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-warning"></i>
                Lista de Rotulaciones
            </h5>
            <small class="text-muted">Rótulos generados para cajas abastecidas.</small>
        </div>

        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableLabelings" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>CÓDIGO</th>
                            <th>ORDEN CLIENTE</th>
                            <th>CLIENTE</th>
                            <th>FACTURA</th>
                            <th>GUÍA</th>
                            <th>N° CAJAS</th>
                            <th>ESTADO</th>
                            <th>F. REGISTRO</th>
                            <th width="150">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.labelings.partials.modal')
    @include('admin.labelings.partials.viewModal')
@stop

@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableLabelings thead th {
            padding: 14px 10px;
            border: 0 !important;
            color: #555;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        #tableLabelings tbody td {
            padding: 12px 8px;
            border-top: 1px solid #f1f1f1;
            font-size: 13px;
            vertical-align: middle !important;
        }

        #tableLabelings tbody tr:hover {
            background: #fafafa;
        }

        .breadcrumb {
            margin-bottom: 0;
        }

        .labeling-modal {
            border-radius: 12px;
            overflow: hidden;
            color: #2e3440;
        }

        #labelingModal .modal-dialog {
            width: 96vw;
            max-width: 96vw;
        }

        .labeling-modal-header {
            background: linear-gradient(135deg, #d89300, #f2b42b);
            border-bottom: 0;
            padding: 12px 16px;
        }

        .labeling-modal-header .modal-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
        }

        .labeling-modal-header small {
            display: block;
            margin-top: 2px;
            color: rgba(255, 255, 255, .84);
            font-size: 11px;
        }

        .labeling-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .labeling-header-icon,
        .labeling-view-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .18);
            color: #fff;
            flex: 0 0 auto;
        }

        .labeling-view-icon {
            background: #fff4d8;
            color: #b77a00;
        }

        .labeling-modal-body {
            background: #f7f8fb;
            padding: 14px;
        }

        .labeling-card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(216, 147, 0, .06) !important;
        }

        .labeling-section-header {
            background: #fffdf8;
            border-bottom: 1px solid #f1eadb !important;
            padding: 8px 12px !important;
        }

        .labeling-section-header h6 {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .labeling-section-header small,
        .labeling-modal label {
            font-size: 10.5px;
        }

        .labeling-modal label {
            margin-bottom: 3px;
            color: #68717a;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .labeling-modal .form-group {
            margin-bottom: 8px;
        }

        .labeling-modal .form-control,
        .labeling-modal .custom-select {
            min-height: 30px;
            height: 30px;
            border-color: #dfe6e8;
            border-radius: 6px;
            font-size: 12px;
            padding: 4px 8px;
        }

        .labeling-modal textarea.form-control {
            height: auto;
            min-height: 46px;
        }

        .labeling-table-scroll {
            overflow-x: auto;
        }

        .labeling-order-items-table,
        .labeling-detail-table {
            font-size: 11.5px;
        }

        .labeling-order-items-table thead th,
        .labeling-detail-table thead th {
            border-bottom: 1px solid #e9eef0 !important;
            color: #59636d;
            font-size: 10.5px;
            font-weight: 700;
            padding: 7px 6px;
            white-space: nowrap;
        }

        .labeling-order-items-table tbody td,
        .labeling-detail-table tbody td {
            padding: 5px 6px;
            vertical-align: middle !important;
            white-space: nowrap;
        }

        .labeling-box-card {
            border: 1px solid #efe1bf;
            border-radius: 8px;
            background: #fff;
        }

        .labeling-box-card .card-header {
            border-radius: 8px 8px 0 0;
            background: #fffaf0;
        }

        .labeling-box-item-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 92px 34px;
            gap: 6px;
            align-items: center;
            margin-bottom: 6px;
        }

        .labeling-modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 3;
            background: #fff;
            border-top: 1px solid #f0e7d5;
            padding: 10px 14px;
        }

        @media (min-width: 1200px) {
            #labelingModal .modal-xl {
                max-width: 1180px;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            labelingsList: "{{ route('admin.labelings.list') }}",
            labelingsCreateData: "{{ route('admin.labelings.create-data') }}",
            labelingsCustomerOrder: "{{ url('admin/labelings/customer-order/:id') }}",
            labelingsStore: "{{ route('admin.labelings.store') }}",
            labelingsShow: "{{ url('admin/labelings/:id/show') }}",
            labelingsEdit: "{{ url('admin/labelings/:id/edit') }}",
            labelingsUpdate: "{{ url('admin/labelings/:id/update') }}",
            labelingsDestroy: "{{ url('admin/labelings/:id/destroy') }}",
            labelingsPdf: "{{ url('admin/labelings/:id/pdf') }}"
        });
    </script>
    @vite(['resources/js/pages/labeling.js'])
@endpush
