@extends('layouts.app')

@section('subtitle', 'Ingresos de Almacén')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-warehouse text-info"></i>
                    Ingresos de Almac&eacute;n
                </h1>
                <small class="text-muted">Registro f&iacute;sico y documental de mercader&iacute;a ingresada</small>
            </div>

            @can('admin.warehouse-entries.store')
            <button id="btnCreateWarehouseEntry" class="btn btn-info shadow-sm px-4" type="button">
                <i class="fas fa-plus-circle mr-1"></i>
                Nuevo Ingreso
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
                <li class="breadcrumb-item active">Ingresos de Almac&eacute;n</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="card border-0 shadow-lg rounded-xl">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-info"></i>
                Lista de ingresos
            </h5>
            <small class="text-muted">Mercader&iacute;a registrada como ingreso a almac&eacute;n</small>
        </div>

        <div class="card-body pt-2">
            <div class="table-responsive">
                <table id="tableWarehouseEntry" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>ID</th>
                            <th>N&deg; INGRESO</th>
                            <th>ORDEN COMPRA</th>
                            <th>PROVEEDOR</th>
                            <th>EMPRESA</th>
                            <th>ALMAC&Eacute;N</th>
                            <th>MONEDA</th>
                            <th>TOTAL</th>
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

    @include('admin.warehouse-entries.partials.modal')
    @include('admin.warehouse-entries.partials.viewModal')
@stop

@push('css')
    <style>
        .rounded-xl {
            border-radius: 18px;
        }

        #tableWarehouseEntry thead th {
            padding: 14px 10px;
            border: 0 !important;
            color: #555;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        #tableWarehouseEntry tbody td {
            padding: 12px 8px;
            border-top: 1px solid #f1f1f1;
            font-size: 13px;
            vertical-align: middle !important;
        }

        #tableWarehouseEntry tbody tr:hover {
            background: #fafafa;
        }

        .warehouse-entry-side-card {
            background: #fff;
            border-radius: 10px;
        }

        .warehouse-entry-side-icon,
        .warehouse-entry-view-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e6f6f4;
            color: #11867a;
            font-size: 18px;
        }

        .warehouse-entry-table-scroll {
            overflow-x: auto;
        }

        .warehouse-entry-items-table th,
        .warehouse-entry-items-table td {
            white-space: nowrap;
            vertical-align: middle !important;
        }

        .warehouse-entry-items-table input,
        .warehouse-entry-items-table select {
            min-width: 120px;
        }

        .warehouse-entry-items-table .item-billing-name-text {
            min-width: 240px;
        }

        .warehouse-entry-total-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .warehouse-entry-side-total {
            font-size: 18px;
            font-weight: 700;
            color: #11867a;
            line-height: 1.2;
        }

        .warehouse-entry-modal {
            border-radius: 12px;
            overflow: hidden;
            color: #2e3440;
        }

        #warehouseEntryModal .modal-dialog {
            width: 96vw;
            max-width: 96vw;
        }

        .warehouse-entry-modal .modal-header {
            padding: 12px 16px;
        }

        .warehouse-entry-modal-header {
            background: linear-gradient(135deg, #11867a, #159f93);
            border-bottom: 0;
        }

        .warehouse-entry-modal-header .modal-title {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
            letter-spacing: 0;
        }

        .warehouse-entry-modal-header small {
            display: block;
            margin-top: 2px;
            color: rgba(255, 255, 255, .78);
            font-size: 11px;
            font-weight: 400;
        }

        .warehouse-entry-modal-header .close {
            font-size: 20px;
            padding: 12px 16px;
            text-shadow: none;
            opacity: .85;
        }

        .warehouse-entry-header-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warehouse-entry-header-icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, .16);
            color: #fff;
            font-size: 14px;
            flex: 0 0 auto;
        }

        .warehouse-entry-modal-body {
            background: #f4f7f8;
            padding: 14px;
        }

        .warehouse-entry-modal .card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(17, 134, 122, .06) !important;
        }

        .warehouse-entry-card .card-body {
            padding: 12px 14px 8px;
        }

        .warehouse-entry-section-header {
            background: #f8fafb;
            border-bottom: 1px solid #edf1f2 !important;
            padding: 8px 12px !important;
        }

        .warehouse-entry-section-header h6 {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.2;
        }

        .warehouse-entry-section-header small {
            font-size: 11px;
        }

        .warehouse-entry-modal label {
            margin-bottom: 3px;
            color: #68717a;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .2px;
        }

        .warehouse-entry-modal .form-group {
            margin-bottom: 8px;
        }

        .warehouse-entry-modal .form-control,
        .warehouse-entry-modal .custom-select {
            min-height: 30px;
            height: 30px;
            border-color: #dfe6e8;
            border-radius: 6px;
            font-size: 12px;
            padding: 4px 8px;
        }

        .warehouse-entry-modal textarea.form-control {
            height: auto;
            min-height: 46px;
        }

        .warehouse-entry-modal .select2-container--default .select2-selection--single {
            height: 30px;
            border-color: #dfe6e8;
            border-radius: 6px;
        }

        .warehouse-entry-modal .select2-container--default .select2-selection--single .select2-selection__rendered {
            padding-left: 8px;
            padding-right: 24px;
            color: #2e3440;
            font-size: 12px;
            line-height: 28px;
        }

        .warehouse-entry-modal .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 28px;
        }

        .warehouse-entry-side-card .card-body {
            padding: 15px 13px;
        }

        .warehouse-entry-side-card h5 {
            font-size: 13px;
            font-weight: 700;
        }

        .warehouse-entry-side-card small {
            font-size: 10.5px;
        }

        .warehouse-entry-side-card .font-weight-600,
        .warehouse-entry-side-card strong,
        .warehouse-entry-side-card .text-left div {
            font-size: 12px;
        }

        .warehouse-entry-side-card .badge {
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            padding: 4px 9px !important;
        }

        .warehouse-entry-side-card hr {
            margin: 10px 0;
        }

        .warehouse-entry-modal .btn {
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            padding: 5px 10px;
        }

        .warehouse-entry-modal .btn-sm {
            font-size: 11.5px;
            padding: 4px 9px;
        }

        .warehouse-entry-items-table {
            font-size: 11.5px;
        }

        .warehouse-entry-items-table thead th,
        .warehouse-entry-detail-table thead th {
            border-bottom: 1px solid #e9eef0 !important;
            color: #59636d;
            font-size: 10.5px;
            font-weight: 700;
            padding: 7px 6px;
        }

        .warehouse-entry-items-table tbody td,
        .warehouse-entry-detail-table tbody td {
            padding: 5px 6px;
            vertical-align: middle !important;
        }

        .warehouse-entry-items-table input,
        .warehouse-entry-items-table select {
            min-width: 104px;
            height: 28px;
            font-size: 11.5px;
            padding: 3px 6px;
        }

        .warehouse-entry-items-table .item-article-picker {
            min-width: 220px;
        }

        .warehouse-entry-items-table .item-note {
            min-width: 150px;
        }

        .warehouse-entry-items-table .item-line-total {
            color: #11867a;
            font-size: 12px;
        }

        .warehouse-entry-modal-footer {
            position: sticky;
            bottom: 0;
            z-index: 3;
            background: #fff;
            border-top: 1px solid #e8eef0;
            padding: 10px 14px;
        }

        #warehouseEntryModal .warehouse-entry-card > .p-3 {
            padding: 12px 14px !important;
            background: #fff !important;
        }

        #warehouseEntryModal .warehouse-entry-card > .p-3 .col-lg-5 {
            padding: 10px 12px;
            border: 1px solid #d8eeea;
            border-radius: 9px;
            background: #f9fdfc;
        }

        #warehouseEntryModal .warehouse-entry-total-line input {
            max-width: 145px;
            border-color: #d8eeea;
            background: #fff;
            font-weight: 700;
        }

        #warehouseEntryModal .warehouse-entry-total-line.font-weight-bold {
            margin-top: 7px;
            padding-top: 8px;
            border-top: 1px solid #d8eeea;
            color: #11867a;
        }

        #warehouseEntryModal .warehouse-entry-total-line.font-weight-bold input {
            color: #11867a;
            font-size: 13px;
        }

        .warehouse-entry-view-modal .card-body {
            font-size: 12px;
        }

        .warehouse-entry-view-modal .modal-body {
            padding: 16px;
        }

        .warehouse-entry-summary-card {
            padding: 16px 14px;
        }

        .warehouse-entry-summary-label {
            display: block;
            color: #7b8790;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .35px;
            text-transform: uppercase;
        }

        .warehouse-entry-summary-code {
            margin: 3px 0 8px;
            color: #27313a;
            font-size: 20px;
            font-weight: 800;
            line-height: 1.15;
        }

        .warehouse-entry-status-badge {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .25px;
            padding: 5px 10px !important;
        }

        .warehouse-entry-summary-separator {
            margin: 13px 0;
            border-top-color: #edf1f2;
        }

        .warehouse-entry-summary-list {
            display: grid;
            gap: 9px;
        }

        .warehouse-entry-summary-item {
            padding-bottom: 8px;
            border-bottom: 1px solid #edf1f2;
        }

        .warehouse-entry-summary-item small,
        .warehouse-entry-summary-total small {
            display: block;
            color: #7b8790;
            font-size: 10.5px;
            font-weight: 700;
            line-height: 1.2;
        }

        .warehouse-entry-summary-item strong {
            display: block;
            margin-top: 2px;
            color: #2e3440;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
        }

        .warehouse-entry-summary-total {
            margin-top: 3px;
            padding: 10px 12px;
            border: 1px solid #d8eeea;
            border-radius: 8px;
            background: #f3fbfa;
        }

        .warehouse-entry-summary-total div {
            margin-top: 2px;
            color: #11867a;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.15;
        }

        .warehouse-entry-detail-grid {
            margin-left: -5px;
            margin-right: -5px;
        }

        .warehouse-entry-detail-grid > [class*="col-"] {
            padding-left: 5px;
            padding-right: 5px;
            margin-bottom: 10px;
        }

        .warehouse-entry-detail-field {
            min-height: 54px;
            padding: 8px 10px;
            border: 1px solid #edf1f2;
            border-radius: 8px;
            background: #fff;
        }

        .warehouse-entry-detail-field-wide {
            min-height: 48px;
        }

        .warehouse-entry-detail-field small {
            display: block;
            margin-bottom: 3px;
            color: #7b8790;
            font-size: 10.5px;
            font-weight: 700;
            line-height: 1.15;
        }

        .warehouse-entry-detail-field strong {
            display: block;
            color: #2e3440;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
            word-break: break-word;
        }

        .warehouse-entry-detail-table-wrap {
            border-top: 1px solid #edf1f2;
        }

        .warehouse-entry-detail-table {
            font-size: 11.5px;
        }

        .warehouse-entry-detail-table thead th {
            background: #f8fafb;
            text-transform: uppercase;
            letter-spacing: .15px;
        }

        .warehouse-entry-detail-table tbody td {
            border-top: 1px solid #f0f3f4;
            color: #3d4650;
            font-size: 11.5px;
        }

        .warehouse-entry-detail-table tbody td:nth-child(2) {
            min-width: 220px;
            color: #26323b;
            font-weight: 700;
            text-align: left;
        }

        .warehouse-entry-detail-footer {
            background: #fff;
            border-top: 1px solid #edf1f2;
            padding: 12px 14px;
        }

        .warehouse-entry-totals-card {
            padding: 10px 12px;
            border: 1px solid #d8eeea;
            border-radius: 9px;
            background: #f9fdfc;
        }

        .warehouse-entry-total-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            color: #5e6973;
            font-size: 12px;
            line-height: 1.5;
        }

        .warehouse-entry-total-row strong {
            color: #2e3440;
            font-size: 12px;
            font-weight: 700;
        }

        .warehouse-entry-total-row-grand {
            margin-top: 6px;
            padding-top: 8px;
            border-top: 1px solid #d8eeea;
            color: #11867a;
            font-weight: 800;
        }

        .warehouse-entry-total-row-grand strong,
        .warehouse-entry-total-row-grand span {
            color: #11867a;
            font-size: 18px;
            font-weight: 800;
        }

        .warehouse-entry-view-modal h3 {
            font-size: 20px;
            line-height: 1.15;
        }

        .warehouse-entry-view-modal .font-weight-bold {
            font-size: 12px;
        }

        .warehouse-entry-view-modal small {
            font-size: 10.5px;
        }

        .warehouse-entry-view-modal .card-footer {
            font-size: 12px;
            padding: 10px 14px;
        }

        .warehouse-entry-view-modal .h5 {
            font-size: 15px;
        }

        @media (min-width: 1200px) {
            #warehouseEntryModal .modal-xl,
            #warehouseEntryViewModal .modal-xl {
                max-width: 1180px;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            warehouseEntryList: "{{ route('admin.warehouse-entries.list') }}",
            warehouseEntryStore: "{{ route('admin.warehouse-entries.store') }}",
            warehouseEntryUpdate: "{{ url('admin/warehouse-entries') }}",
            warehouseEntryDelete: "{{ url('admin/warehouse-entries') }}",
            warehouseEntryShow: "{{ url('admin/warehouse-entries') }}",
            warehouseEntryGenerateNumber: "{{ route('admin.warehouse-entries.generateNumber') }}",
            warehouseEntryLoadSupplierOrderItems: "{{ route('admin.warehouse-entries.loadSupplierOrderItems') }}"
        });
    </script>

    @vite(['resources/js/pages/warehouse-entry.js'])
@endpush
