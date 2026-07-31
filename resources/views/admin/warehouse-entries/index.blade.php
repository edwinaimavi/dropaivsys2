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

        .warehouse-entry-lots-cell {
            min-width: 190px;
            white-space: normal !important;
        }

        .warehouse-entry-lots-cell .btn {
            white-space: nowrap;
        }

        .warehouse-entry-lot-visual-row {
            background: #fbfdfd;
        }

        .warehouse-entry-lot-visual-row td {
            border-top: 1px dashed #dce8e7 !important;
            color: #56636b;
            font-size: 11px;
        }

        .warehouse-entry-lot-visual-row:hover {
            background: #f2faf8 !important;
        }

        .warehouse-entry-lot-badge {
            border: 1px solid #bfe2dc;
            border-radius: 10px;
            background: #eaf8f5;
            color: #11786f;
            font-size: 10px;
            font-weight: 700;
            padding: 5px 8px;
        }

        .warehouse-entry-lot-branch {
            color: #77aaa4;
            font-size: 11px;
        }

        .warehouse-entry-lot-repeat {
            display: inline-block;
            max-width: 240px;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        .warehouse-entry-lot-quantity-display {
            display: inline-block;
            min-width: 90px;
            padding: 5px 8px;
            border-radius: 6px;
            background: #edf9f4;
            text-align: right;
        }

        .warehouse-entry-lot-reference-total {
            color: #66727a !important;
            font-weight: 600;
        }

        #warehouseEntryModal {
            z-index: 2060 !important;
        }

        .warehouse-entry-backdrop-main {
            z-index: 2050 !important;
        }

        #warehouseEntryLotsModal,
        .warehouse-entry-lots-modal {
            z-index: 2080 !important;
        }

        .warehouse-entry-backdrop-lots {
            z-index: 2070 !important;
        }

        #warehouseEntryModal.show,
        #warehouseEntryModal .modal-dialog,
        #warehouseEntryModal .modal-content,
        #warehouseEntryLotsModal.show,
        #warehouseEntryLotsModal .modal-dialog,
        #warehouseEntryLotsModal .modal-content {
            pointer-events: auto;
        }

        body.warehouse-entry-active .swal2-container {
            z-index: 3000 !important;
        }

        .warehouse-entry-lots-modal-content {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 18px 45px rgba(25, 39, 52, .22);
        }

        .warehouse-entry-lots-metrics {
            display: grid;
            grid-template-columns: minmax(180px, 2fr) repeat(3, minmax(110px, 1fr));
            gap: 8px;
        }

        .warehouse-entry-lots-metrics > div {
            padding: 9px 11px;
            border: 1px solid #e5ecee;
            border-radius: 8px;
            background: #f8fafb;
        }

        .warehouse-entry-lots-metrics small,
        .warehouse-entry-lots-metrics strong {
            display: block;
        }

        .warehouse-entry-lots-metrics small { color: #7a858d; font-size: 9px; }
        .warehouse-entry-lots-metrics strong { font-size: 13px; }
        .warehouse-entry-lots-table input { min-width: 120px; }

        @media (max-width: 767.98px) {
            .warehouse-entry-lots-metrics { grid-template-columns: 1fr 1fr; }
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
            width: calc(100vw - 120px);
            max-width: 1700px;
            margin: 12px auto;
        }

        #warehouseEntryModal .modal-content {
            display: flex;
            max-height: 90vh;
            flex-direction: column;
            background: #f5f8f9;
        }

        #warehouseEntryModal .modal-body {
            min-height: 0;
            padding: 10px 12px;
            overflow-x: hidden;
            overflow-y: auto;
            flex: 0 1 auto;
        }
        #warehouseEntryModal .warehouse-entry-modal-footer {
            position: relative;
            z-index: 15;
            flex: 0 0 auto;
            border-top: 1px solid #dde7e9;
            background: rgba(255, 255, 255, .98);
            box-shadow: 0 -6px 18px rgba(32, 56, 65, .07);
        }

        #warehouseEntryModal .warehouse-entry-tabs-column { display: flex; min-width: 0; flex-direction: column; }
        #warehouseEntryModal .warehouse-entry-form-tabs { padding: 6px; border: 1px solid #dce8e8; border-radius: 12px; background: linear-gradient(145deg, #fff, #f7faf9); box-shadow: 0 6px 18px rgba(31, 85, 79, .06); }
        #warehouseEntryModal .warehouse-entry-form-tabs .nav { gap: 6px; overflow-x: auto; scrollbar-width: thin; }
        #warehouseEntryModal .warehouse-entry-form-tabs .nav-link { display: flex; min-width: max-content; gap: 7px; padding: 8px 11px; align-items: center; border: 1px solid #e4ecec; border-radius: 8px; color: #647572; background: #f4f7f7; font-size: 11px; font-weight: 800; white-space: nowrap; }
        #warehouseEntryModal .warehouse-entry-form-tabs .nav-link.active { border-color: #b9dfda; color: #0c756a; background: linear-gradient(135deg, #e8f7f5, #d9f0ed); box-shadow: 0 4px 11px rgba(17, 134, 122, .12); }
        #warehouseEntryModal .warehouse-entry-form-tab-content { padding-top: 8px; }
        #warehouseEntryModal .warehouse-entry-form-tab-content > .tab-pane > .card { margin-bottom: 0; }
        #warehouseEntryModal #warehouse_entry_tab_data .card-body { padding: 12px 14px 6px; }
        #warehouseEntryModal #warehouse_entry_tab_data .row { margin-right: -5px; margin-left: -5px; }
        #warehouseEntryModal #warehouse_entry_tab_data .form-group { margin-bottom: 9px; padding-right: 5px; padding-left: 5px; }

        @media (min-width: 1200px) {
            #warehouseEntryModal #warehouse_entry_tab_data .form-group:not(.col-md-12) {
                width: 25%;
                max-width: 25%;
                flex: 0 0 25%;
            }
        }
        #warehouseEntryModal .warehouse-entry-table-scroll { max-height: 48vh; }

        #warehouseEntryModal .warehouse-entry-side-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 7px; }
        #warehouseEntryModal .warehouse-entry-side-metrics > div { padding: 7px; border: 1px solid #e2eceb; border-radius: 8px; background: #f7fbfa; text-align: center; }
        #warehouseEntryModal .warehouse-entry-side-metrics strong, #warehouseEntryModal .warehouse-entry-side-metrics small { display: block; }
        #warehouseEntryModal .warehouse-entry-side-metrics strong { color: #11867a; font-size: 15px; }
        #warehouseEntryModal .warehouse-entry-side-metrics small { color: #788681; font-size: 9px; text-transform: uppercase; }

        #warehouseEntryModal .warehouse-entry-review-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 9px; }
        #warehouseEntryModal .warehouse-entry-review-grid > div { min-height: 58px; padding: 9px 11px; border: 1px solid #e4ecec; border-radius: 9px; background: #fff; }
        #warehouseEntryModal .warehouse-entry-review-grid small, #warehouseEntryModal .warehouse-entry-review-grid strong { display: block; }
        #warehouseEntryModal .warehouse-entry-review-grid small { color: #7b8885; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        #warehouseEntryModal .warehouse-entry-review-grid strong { margin-top: 3px; color: #34423f; font-size: 12px; word-break: break-word; }
        #warehouseEntryModal .warehouse-entry-review-grid .warehouse-entry-review-grand { border-color: #c9e5e1; background: #edf9f7; }
        #warehouseEntryModal .warehouse-entry-review-grand strong { color: #11867a; font-size: 17px; }
        #warehouseEntryModal .warehouse-entry-review-alert, #warehouseEntryModal .warehouse-entry-review-ok { display: flex; gap: 10px; margin-top: 12px; padding: 11px 13px; border-radius: 9px; font-size: 11px; }
        #warehouseEntryModal .warehouse-entry-review-alert { border: 1px solid #f1d89b; color: #725b20; background: #fff9e9; }
        #warehouseEntryModal .warehouse-entry-review-ok { border: 1px solid #bfe2d4; color: #216a50; background: #effaf5; }
        #warehouseEntryModal .warehouse-entry-review-alert ul { margin: 3px 0 0; padding-left: 17px; }
        #warehouseEntryModal .warehouse-entry-review-ok small { display: block; }

        @media (max-width: 991.98px) {
            #warehouseEntryModal .modal-dialog { width: calc(100vw - 20px); max-width: calc(100vw - 20px); margin: 10px auto; }
            #warehouseEntryModal .modal-content { max-height: calc(100vh - 12px); }
            #warehouseEntryModal .warehouse-entry-review-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 575.98px) {
            #warehouseEntryModal .warehouse-entry-review-grid { grid-template-columns: 1fr; }
            #warehouseEntryModal .warehouse-entry-modal-footer .btn { flex: 1; }
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

        .warehouse-entry-documents-card {
            border: 1px solid #e6f0f1 !important;
        }

        .warehouse-entry-document-title {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .warehouse-entry-document-icon {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e6f6f4;
            color: #11867a;
            flex: 0 0 auto;
        }

        .warehouse-entry-document-counter {
            padding: 5px 10px;
            border-radius: 999px;
            background: #eef8f7;
            color: #11867a;
            font-size: 11px;
            font-weight: 700;
        }

        .warehouse-entry-document-form {
            padding: 12px;
            border: 1px solid #edf1f2;
            border-radius: 10px;
            background: #fbfdfd;
        }

        .warehouse-entry-document-file .custom-file-label {
            height: 30px;
            padding: 4px 75px 4px 8px;
            border-color: #dfe6e8;
            border-radius: 6px;
            color: #7b8790;
            font-size: 11.5px;
            line-height: 20px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .warehouse-entry-document-file .custom-file-label::after {
            height: 28px;
            padding: 4px 10px;
            color: #11867a;
            background: #e6f6f4;
            border-left-color: #dfe6e8;
            font-size: 11px;
            font-weight: 700;
            line-height: 20px;
            content: "Buscar";
        }

        .warehouse-entry-document-add {
            height: 30px;
            border: 0;
            background: linear-gradient(135deg, #11867a, #159f93);
            box-shadow: 0 8px 18px rgba(17, 134, 122, .16);
        }

        .warehouse-entry-document-add:hover {
            background: linear-gradient(135deg, #0f766e, #11867a);
        }

        .warehouse-entry-documents-table-wrap {
            margin-top: 12px;
            border: 1px solid #edf1f2;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .warehouse-entry-documents-table {
            font-size: 11.5px;
        }

        .warehouse-entry-documents-table thead th {
            padding: 8px 7px;
            border-bottom: 1px solid #e9eef0 !important;
            background: #f8fafb;
            color: #59636d;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .15px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .warehouse-entry-documents-table tbody td {
            padding: 7px;
            border-top: 1px solid #f0f3f4;
            color: #3d4650;
            vertical-align: middle !important;
        }

        .warehouse-entry-document-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 8px;
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 800;
            white-space: nowrap;
        }

        .warehouse-entry-document-badge.badge-doc-green {
            background: #e8f8ef;
            color: #16733c;
        }

        .warehouse-entry-document-badge.badge-doc-blue {
            background: #e7f3ff;
            color: #1f6fb2;
        }

        .warehouse-entry-document-badge.badge-doc-teal {
            background: #e6f6f4;
            color: #11867a;
        }

        .warehouse-entry-document-badge.badge-doc-yellow {
            background: #fff7df;
            color: #9a6a00;
        }

        .warehouse-entry-document-badge.badge-doc-gray {
            background: #eef1f4;
            color: #59636d;
        }

        .warehouse-entry-document-file-name {
            display: block;
            max-width: 230px;
            color: #26323b;
            font-weight: 700;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .warehouse-entry-document-actions {
            display: inline-flex;
            justify-content: center;
            gap: 5px;
        }

        .warehouse-entry-document-actions .btn {
            width: 28px;
            height: 28px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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

        .warehouse-entry-view-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 12px;
            padding: 12px 14px;
            border: 1px solid #e5ecee;
            border-radius: 11px;
            background: #fff;
            box-shadow: 0 3px 12px rgba(32, 49, 58, .05);
        }

        .warehouse-entry-view-identity,
        .warehouse-entry-view-facts {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .warehouse-entry-view-identity h3 {
            margin: 1px 0 0;
            color: #27313a;
            font-weight: 800;
        }

        .warehouse-entry-view-identity small,
        .warehouse-entry-view-facts small {
            display: block;
            color: #7b8790;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .warehouse-entry-view-facts > div {
            min-width: 105px;
            padding-left: 12px;
            border-left: 1px solid #edf1f2;
        }

        .warehouse-entry-view-facts strong {
            display: block;
            max-width: 150px;
            color: #34404a;
            font-size: 11.5px;
            line-height: 1.25;
        }

        .warehouse-entry-view-facts .warehouse-entry-view-total strong {
            color: #11867a;
            font-size: 16px;
        }

        .warehouse-entry-tabs-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            margin-bottom: 12px;
            scrollbar-width: thin;
        }

        .warehouse-entry-view-tabs {
            flex-wrap: nowrap;
            min-width: max-content;
            padding: 4px;
            border: 1px solid #e5ecee;
            border-radius: 10px;
            background: #f5f8f9;
        }

        .warehouse-entry-view-tabs .nav-link {
            margin-right: 3px;
            padding: 7px 13px;
            border-radius: 7px;
            color: #66727b;
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .warehouse-entry-view-tabs .nav-link.active {
            color: #fff;
            background: #11867a;
            box-shadow: 0 2px 7px rgba(17, 134, 122, .2);
        }

        .warehouse-entry-view-tab-content {
            min-height: 280px;
        }

        .warehouse-entry-detail-lot-row td {
            background: #fbfdfd;
        }

        .warehouse-entry-detail-lot-row:hover td {
            background: #f3faf8 !important;
        }

        .warehouse-entry-empty-state i {
            color: #a6b3b8;
            font-size: 22px;
        }

        @media (max-width: 991.98px) {
            .warehouse-entry-view-heading,
            .warehouse-entry-view-facts {
                align-items: stretch;
                flex-direction: column;
            }

            .warehouse-entry-view-facts {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                width: 100%;
                gap: 8px;
            }

            .warehouse-entry-view-facts > div {
                padding: 7px 9px;
                border: 1px solid #edf1f2;
                border-radius: 7px;
            }

            .warehouse-entry-view-facts strong {
                max-width: none;
            }
        }

        @media (max-width: 575.98px) {
            .warehouse-entry-view-modal .modal-body { padding: 10px; }
            .warehouse-entry-view-identity { flex-wrap: wrap; }
            .warehouse-entry-view-facts { grid-template-columns: 1fr; }
            .warehouse-entry-view-modal .warehouse-entry-detail-table-wrap,
            .warehouse-entry-view-modal .warehouse-entry-documents-table-wrap { max-width: 100%; overflow-x: auto; }
        }

        @media (min-width: 1200px) {
            #warehouseEntryModal .modal-xl {
                width: calc(100vw - 120px);
                max-width: 1700px;
            }

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
