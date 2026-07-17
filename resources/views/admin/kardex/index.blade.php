@extends('layouts.app')

@section('subtitle', 'Kardex de Almac&eacute;n')

@section('header')
    <div class="container-fluid">
        <div class="kardex-page-heading d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div class="d-flex align-items-center">
                <div class="kardex-heading-icon mr-3">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div>
                    <h1 class="mb-1 font-weight-bold text-dark">
                        Kardex de Almac&eacute;n
                    </h1>
                    <small class="text-muted">Control de movimientos, saldos y valorizaci&oacute;n del inventario</small>
                </div>
            </div>
        </div>

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb bg-white shadow-sm rounded-pill px-3 py-2">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}" class="text-decoration-none">
                        <i class="fas fa-house-user"></i>
                        Home
                    </a>
                </li>
                <li class="breadcrumb-item active">Kardex</li>
            </ol>
        </nav>
    </div>
@stop

@section('content_body')
    <div class="kardex-dashboard">
    <div class="row kardex-summary-row">
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm kardex-stat-card kardex-stat-card-stock">
                <div class="card-body">
                    <div class="kardex-stat-meta">
                        <span>Art&iacute;culos con stock</span>
                        <strong>{{ number_format($stats['stock_articles']) }}</strong>
                    </div>
                    <div class="kardex-stat-icon"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm kardex-stat-card kardex-stat-card-entry">
                <div class="card-body">
                    <div class="kardex-stat-meta">
                        <span>Entradas del mes</span>
                        <strong>{{ number_format((float) $stats['month_entries'], 2) }}</strong>
                    </div>
                    <div class="kardex-stat-icon"><i class="fas fa-sign-in-alt"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm kardex-stat-card kardex-stat-card-exit">
                <div class="card-body">
                    <div class="kardex-stat-meta">
                        <span>Salidas del mes</span>
                        <strong>{{ number_format((float) $stats['month_exits'], 2) }}</strong>
                    </div>
                    <div class="kardex-stat-icon"><i class="fas fa-sign-out-alt"></i></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm kardex-stat-card kardex-stat-card-value">
                <div class="card-body">
                    <div class="kardex-stat-meta">
                        <span>Valor inventario</span>
                        <strong>S/ {{ number_format((float) $stats['inventory_value'], 2) }}</strong>
                    </div>
                    <div class="kardex-stat-icon"><i class="fas fa-coins"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm kardex-card kardex-filter-card mb-3">
        <div class="card-header border-0">
            <div>
                <h5 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-filter text-info mr-1"></i>
                    Filtros de b&uacute;squeda
                </h5>
                <small class="text-muted">Filtra los movimientos por almac&eacute;n, art&iacute;culo, fechas o documento</small>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="form-group col-md-3">
                    <label>ALMAC&Eacute;N</label>
                    <select id="kardex_filter_warehouse_id" class="form-control form-control-sm js-kardex-filter">
                        <option value="">Todos</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>ART&Iacute;CULO</label>
                    <select id="kardex_filter_article_id" class="form-control form-control-sm js-kardex-filter">
                        <option value="">Todos</option>
                        @foreach ($articles as $article)
                            <option value="{{ $article->id }}">{{ $article->code }} | {{ $article->billing_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label>FECHA DESDE</label>
                    <input type="date" id="kardex_filter_date_from" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2">
                    <label>FECHA HASTA</label>
                    <input type="date" id="kardex_filter_date_to" class="form-control form-control-sm">
                </div>
                <div class="form-group col-md-2">
                    <label>TIPO</label>
                    <select id="kardex_filter_movement_type" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        <option value="entry">Entrada</option>
                        <option value="exit">Salida</option>
                        <option value="adjustment_in">Ajuste Entrada</option>
                        <option value="adjustment_out">Ajuste Salida</option>
                        <option value="transfer_in">Transferencia Entrada</option>
                        <option value="transfer_out">Transferencia Salida</option>
                        <option value="reversal">Reversa</option>
                        <option value="exit_reversal">Reversa de salida</option>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label>LOTE</label>
                    <input type="text" id="kardex_filter_lot_number" class="form-control form-control-sm text-uppercase">
                </div>
                <div class="form-group col-md-3">
                    <label>DOCUMENTO</label>
                    <input type="text" id="kardex_filter_document" class="form-control form-control-sm text-uppercase">
                </div>
                <div class="form-group col-md-3">
                    <label>PROVEEDOR / RELACIONADO</label>
                    <input type="text" id="kardex_filter_related_party" class="form-control form-control-sm text-uppercase">
                </div>
                <div class="form-group col-md-3 d-flex align-items-end justify-content-md-end kardex-filter-actions">
                    <button type="button" id="btnFilterKardex" class="btn btn-info btn-sm shadow-sm mr-2">
                        <i class="fas fa-search mr-1"></i>
                        Filtrar
                    </button>
                    <button type="button" id="btnClearKardexFilters" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-eraser mr-1"></i>
                        Limpiar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm kardex-card kardex-table-card">
        <div class="card-header border-0">
            <div>
                <h5 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-list text-info mr-1"></i>
                    Movimientos Kardex
                </h5>
                <small class="text-muted">Historial auditable de entradas, salidas, ajustes y reversas</small>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive kardex-table-wrap">
                <table id="tableKardex" class="table table-hover align-middle text-center w-100">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>FECHA</th>
                            <th>N&deg; MOV.</th>
                            <th>ALMAC&Eacute;N</th>
                            <th>ART&Iacute;CULO</th>
                            <th>LOTE</th>
                            <th>F. VENC.</th>
                            <th>TIPO</th>
                            <th>DOCUMENTO</th>
                            <th>ENTRADA</th>
                            <th>SALIDA</th>
                            <th>SALDO</th>
                            <th>COSTO UNIT.</th>
                            <th>VALOR SALDO</th>
                            <th>ESTADO</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.kardex.partials.viewModal')
    </div>
@stop

@push('css')
    <style>
        .kardex-dashboard {
            color: #2e3440;
        }

        .kardex-page-heading h1 {
            font-size: 25px;
            letter-spacing: 0;
            line-height: 1.12;
        }

        .kardex-page-heading small {
            font-size: 12px;
        }

        .kardex-heading-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            background: linear-gradient(135deg, #11867a, #16a394);
            box-shadow: 0 10px 22px rgba(17, 134, 122, .22);
            font-size: 18px;
            flex: 0 0 auto;
        }

        .kardex-page-heading + nav .breadcrumb {
            border: 1px solid #edf4f3;
        }

        .kardex-card,
        .kardex-stat-card {
            border-radius: 12px;
        }

        .kardex-stat-card {
            position: relative;
            overflow: hidden;
            transition: transform .18s ease, box-shadow .18s ease;
            border-left: 4px solid #11867a !important;
            box-shadow: 0 10px 24px rgba(36, 52, 64, .07) !important;
        }

        .kardex-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(36, 52, 64, .10) !important;
        }

        .kardex-stat-card .card-body {
            min-height: 98px;
            padding: 16px 16px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }

        .kardex-stat-meta span {
            display: block;
            color: #6f7a83;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .25px;
            text-transform: uppercase;
        }

        .kardex-stat-meta strong {
            display: block;
            margin-top: 7px;
            color: #11867a;
            font-size: 23px;
            font-weight: 850;
            line-height: 1.05;
        }

        .kardex-stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e6f6f4;
            color: #11867a;
            font-size: 21px;
            position: relative;
            z-index: 1;
        }

        .kardex-stat-card::after {
            content: "";
            position: absolute;
            width: 96px;
            height: 96px;
            right: -30px;
            bottom: -34px;
            border-radius: 50%;
            background: rgba(17, 134, 122, .08);
        }

        .kardex-stat-card-entry {
            border-left-color: #138fc2 !important;
        }

        .kardex-stat-card-entry .kardex-stat-meta strong,
        .kardex-stat-card-entry .kardex-stat-icon {
            color: #138fc2;
        }

        .kardex-stat-card-entry .kardex-stat-icon {
            background: #e8f5fb;
        }

        .kardex-stat-card-exit {
            border-left-color: #dc6b35 !important;
        }

        .kardex-stat-card-exit .kardex-stat-meta strong,
        .kardex-stat-card-exit .kardex-stat-icon {
            color: #dc6b35;
        }

        .kardex-stat-card-exit .kardex-stat-icon {
            background: #fff1e9;
        }

        .kardex-stat-card-value {
            border-left-color: #0f6f64 !important;
        }

        .kardex-filter-card,
        .kardex-table-card {
            box-shadow: 0 10px 24px rgba(36, 52, 64, .07) !important;
            border: 1px solid #edf4f3 !important;
        }

        .kardex-filter-card .card-header,
        .kardex-table-card .card-header {
            padding: 15px 17px 7px;
            background: #fff;
        }

        .kardex-filter-card .card-body {
            padding: 10px 17px 13px;
            background: linear-gradient(180deg, #fbfdfd, #fff);
        }

        .kardex-table-card .card-body {
            padding: 8px 14px 14px;
        }

        .kardex-card h5 {
            font-size: 15px;
        }

        .kardex-card label {
            margin-bottom: 4px;
            color: #68717a;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .22px;
        }

        .kardex-card .form-group {
            margin-bottom: 9px;
        }

        .kardex-card .form-control,
        .kardex-card .select2-container--default .select2-selection--single {
            height: 31px;
            border-color: #dfe8ea;
            border-radius: 7px;
            font-size: 12px;
            box-shadow: none;
        }

        .kardex-card .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 29px;
            font-size: 12px;
        }

        .kardex-card .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 29px;
        }

        .kardex-filter-actions .btn {
            min-width: 86px;
            border-radius: 7px;
            font-size: 12px;
            font-weight: 700;
        }

        .kardex-table-wrap {
            border: 1px solid #edf2f3;
            border-radius: 10px;
            overflow-x: auto;
            background: #fff;
        }

        #tableKardex {
            margin-bottom: 0 !important;
        }

        #tableKardex thead th {
            padding: 11px 8px;
            border: 0 !important;
            border-bottom: 1px solid #e7eef0 !important;
            background: #f8fbfb;
            color: #59636d;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .18px;
            white-space: nowrap;
        }

        #tableKardex tbody td {
            padding: 9px 8px;
            border-top: 1px solid #f0f3f4;
            color: #39434d;
            font-size: 12px;
            vertical-align: middle !important;
        }

        #tableKardex tbody tr:hover {
            background: #f8fcfb;
        }

        .kardex-article-cell {
            min-width: 230px;
            text-align: left;
            line-height: 1.2;
        }

        .kardex-article-code {
            display: inline-block;
            margin-bottom: 3px;
            padding: 2px 6px;
            border-radius: 999px;
            background: #edf7f5;
            color: #11867a;
            font-size: 10px;
            font-weight: 800;
        }

        .kardex-article-name {
            display: block;
            color: #26323b;
            font-size: 12px;
            font-weight: 750;
        }

        .kardex-document-pill {
            display: inline-flex;
            align-items: center;
            max-width: 170px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #f4f7f8;
            color: #4d5963;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .kardex-movement-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 92px;
            padding: 4px 8px;
            border: 1px solid #d9eeeb;
            border-radius: 999px;
            background: #fbfefd;
            color: #0f766d;
            font-size: 11px;
            font-weight: 850;
            white-space: nowrap;
        }

        .kardex-num-in {
            color: #15925f;
            font-weight: 800;
        }

        .kardex-num-out {
            color: #cf4b45;
            font-weight: 800;
        }

        .kardex-num-balance {
            color: #11867a;
            font-weight: 850;
        }

        .kardex-money {
            color: #34404b;
            font-weight: 750;
        }

        .kardex-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: 10.5px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
        }

        .kardex-badge-entry,
        .kardex-badge-registered {
            background: #e7f6ef;
            color: #14764e;
        }

        .kardex-badge-exit,
        .kardex-badge-cancelled {
            background: #fdeceb;
            color: #bd3d39;
        }

        .kardex-badge-adjustment-in {
            background: #eaf3ff;
            color: #236db5;
        }

        .kardex-badge-adjustment-out {
            background: #fff2e8;
            color: #bf5d22;
        }

        .kardex-badge-transfer-in {
            background: #e6f6f4;
            color: #11867a;
        }

        .kardex-badge-transfer-out {
            background: #f0ebfb;
            color: #6942b5;
        }

        .kardex-badge-reversal,
        .kardex-badge-reversed {
            background: #eef0f2;
            color: #5b646d;
        }

        #tableKardex .badge {
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 10.5px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .35);
        }

        #tableKardex .badge-success {
            background: #e7f6ef;
            color: #14764e;
        }

        #tableKardex .badge-danger {
            background: #fdeceb;
            color: #bd3d39;
        }

        #tableKardex .badge-primary {
            background: #eaf3ff;
            color: #236db5;
        }

        #tableKardex .badge-warning {
            background: #fff2e8;
            color: #bf5d22 !important;
        }

        #tableKardex .badge-info {
            background: #e6f6f4;
            color: #11867a;
        }

        #tableKardex .badge-purple {
            background: #f0ebfb;
            color: #6942b5;
        }

        #tableKardex .badge-secondary,
        #tableKardex .badge-light {
            background: #eef0f2;
            color: #5b646d !important;
        }

        .kardex-action-btn {
            width: 31px;
            height: 31px;
            padding: 0;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #f6fbfb;
            border-color: #bfe6e1;
            color: #11867a;
            transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
        }

        .kardex-action-btn:hover,
        .kardex-action-btn:focus {
            transform: translateY(-1px);
            background: #11867a;
            border-color: #11867a;
            color: #fff;
            box-shadow: 0 8px 16px rgba(17, 134, 122, .22);
        }

        .kardex-dashboard .dt-buttons .btn {
            border-radius: 7px !important;
            margin: 0 3px 5px;
            padding: 5px 10px;
            font-size: 11.5px;
            font-weight: 700;
            box-shadow: 0 6px 12px rgba(36, 52, 64, .08);
        }

        .kardex-dashboard .dataTables_filter input,
        .kardex-dashboard .dataTables_length select {
            border: 1px solid #dfe8ea;
            border-radius: 7px;
            font-size: 12px;
            height: 30px;
        }

        .kardex-dashboard .badge-purple {
            background: #6f42c1;
        }

        .kardex-view-modal {
            border-radius: 12px;
            overflow: hidden;
            color: #2e3440;
        }

        .kardex-view-modal .modal-header {
            padding: 13px 16px;
            background: linear-gradient(135deg, #11867a, #159f93) !important;
        }

        .kardex-view-modal .modal-title {
            font-size: 15px;
            font-weight: 800;
        }

        .kardex-view-modal .modal-header small {
            color: rgba(255, 255, 255, .78);
            font-size: 11px;
        }

        .kardex-view-modal .modal-body {
            background: #f4f7f8 !important;
            padding: 14px;
        }

        .kardex-view-modal .card {
            border-radius: 10px;
            box-shadow: 0 8px 20px rgba(17, 134, 122, .06) !important;
        }

        .kardex-view-icon {
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

        .kardex-modal-side-card {
            background: linear-gradient(180deg, #ffffff, #f9fcfc);
        }

        .kardex-modal-movement-number {
            margin-top: 3px;
            margin-bottom: 8px;
            font-size: 22px;
            letter-spacing: 0;
        }

        .kardex-modal-summary small {
            margin-bottom: 2px;
            color: #7a858e !important;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .2px;
        }

        .kardex-modal-summary strong {
            color: #2e3440;
            font-size: 12px;
            line-height: 1.25;
            word-break: break-word;
        }

        .kardex-modal-balance {
            display: inline-flex !important;
            width: 100%;
            align-items: center;
            justify-content: center;
            min-height: 43px;
            border-radius: 10px;
            background: #e6f6f4;
            color: #11867a;
            font-weight: 850;
        }

        .kardex-detail-grid > [class*="col-"] {
            margin-bottom: 9px;
        }

        .kardex-detail-field {
            min-height: 54px;
            padding: 8px 10px;
            border: 1px solid #edf1f2;
            border-radius: 8px;
            background: #fff;
        }

        .kardex-detail-field small {
            display: block;
            margin-bottom: 3px;
            color: #7b8790;
            font-size: 10.5px;
            font-weight: 800;
            line-height: 1.15;
        }

        .kardex-detail-field strong {
            display: block;
            color: #2e3440;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.25;
            word-break: break-word;
        }

        .kardex-trace-table th {
            width: 18%;
            border-top: 1px solid #edf1f2 !important;
            color: #7b8790;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .kardex-trace-table td {
            border-top: 1px solid #edf1f2 !important;
            color: #2e3440;
            font-size: 12px;
            font-weight: 700;
        }

        @media (max-width: 767.98px) {
            .kardex-page-heading h1 {
                font-size: 21px;
            }

            .kardex-heading-icon {
                width: 40px;
                height: 40px;
            }

            .kardex-filter-actions {
                justify-content: flex-start !important;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            kardexList: "{{ route('admin.kardex.list') }}",
            kardexShow: "{{ url('admin/kardex') }}",
            kardexStock: "{{ route('admin.kardex.stock') }}"
        });
    </script>
    @vite(['resources/js/pages/kardex.js'])
@endpush
