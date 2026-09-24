@extends('layouts.app')

@section('subtitle', 'Kardex de Almacén')

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
                    <small class="text-muted">Control de movimientos, saldos y valorización del inventario</small>
                </div>
            </div>
            @can('admin.kardex.index')
                <div class="d-flex flex-wrap justify-content-end mt-2 mt-md-0">
                    <button type="button" id="btnOpenFormat12Modal"
                            class="btn btn-outline-info btn-sm shadow-sm mr-2 mb-1"
                            data-url="{{ route('admin.kardex.formato-12-1') }}">
                        <i class="fas fa-file-alt mr-1"></i>
                        Formato 12.1 &mdash; Unidades F&iacute;sicas
                    </button>
                    <a href="{{ route('admin.kardex.formato-13-1') }}" class="btn btn-outline-success btn-sm shadow-sm mb-1">
                        <i class="fas fa-file-invoice-dollar mr-1"></i>
                        Formato 13.1 &mdash; Inventario Valorizado
                    </a>
                    <a href="{{ route('admin.kardex.reconciliation') }}" class="btn btn-outline-warning btn-sm shadow-sm ml-2 mb-1">
                        <i class="fas fa-balance-scale mr-1"></i>
                        Cuadre Stock vs Kardex
                    </a>
                    <a href="{{ route('admin.kardex.period-closures') }}" class="btn btn-outline-danger btn-sm shadow-sm ml-2 mb-1">
                        <i class="fas fa-lock mr-1"></i>
                        Cierre mensual
                    </a>
                    <a href="{{ route('admin.kardex.ple') }}" class="btn btn-outline-primary btn-sm shadow-sm ml-2 mb-1">
                        <i class="fas fa-file-code mr-1"></i>
                        PLE / Exportaci&oacute;n electr&oacute;nica
                    </a>
                </div>
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
                        <option value="linked_cost">Costo vinculado</option>
                        <option value="cost_reversal">Reversa de costo</option>
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

    <div class="card border-0 shadow-sm kardex-card mb-3">
        <div class="card-body py-3">
            <div class="row align-items-end">
                @can('admin.kardex.stock')
                <div class="form-group col-md-3 mb-md-0">
                    <label>STOCK A LA FECHA</label>
                    <input type="date" id="kardex_stock_date" class="form-control form-control-sm" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <button type="button" id="btnKardexStockAtDate" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-calendar-check mr-1"></i> Consultar saldo histórico
                    </button>
                </div>
                @endcan
                <div class="col-md-6 text-md-right">
                    @can('admin.kardex.recalculate')
                        <button type="button" id="btnRecalculateKardex" class="btn btn-warning btn-sm">
                            <i class="fas fa-sync-alt mr-1"></i> Recalcular Kardex filtrado
                        </button>
                    @endcan
                    <small class="d-block text-muted mt-2">
                        Último recálculo:
                        {{ $lastRecalculation?->finished_at?->format('d/m/Y H:i') ?? 'sin ejecuciones' }}
                    </small>
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
            <div class="kardex-grid-navigation" aria-label="Navegaci&oacute;n horizontal de Movimientos Kardex">
                <div class="kardex-grid-zones" role="group" aria-label="Ir a un bloque de columnas">
                    <button type="button" class="kardex-grid-zone is-active" data-kardex-column="0" aria-pressed="true">Datos</button>
                    <button type="button" class="kardex-grid-zone" data-kardex-column="9" aria-pressed="false">Entradas</button>
                    <button type="button" class="kardex-grid-zone" data-kardex-column="12" aria-pressed="false">Salidas</button>
                    <button type="button" class="kardex-grid-zone" data-kardex-column="15" aria-pressed="false">Saldos</button>
                    <button type="button" class="kardex-grid-zone" data-kardex-column="18" aria-pressed="false">Auditor&iacute;a</button>
                </div>
                <div class="kardex-scroll-proxy" tabindex="0" role="scrollbar" aria-label="Desplazamiento horizontal de Movimientos Kardex" aria-orientation="horizontal">
                    <div class="kardex-scroll-proxy-track"></div>
                </div>
            </div>
            <div class="kardex-table-wrap">
                <table id="tableKardex" class="table table-hover align-middle text-center">
                    <colgroup>
                        <col class="kardex-col-index">
                        <col class="kardex-col-date">
                        <col class="kardex-col-movement">
                        <col class="kardex-col-warehouse">
                        <col class="kardex-col-article">
                        <col class="kardex-col-lot">
                        <col class="kardex-col-expiration">
                        <col class="kardex-col-type">
                        <col class="kardex-col-document">
                        <col class="kardex-col-quantity">
                        <col class="kardex-col-unit-cost">
                        <col class="kardex-col-total-cost">
                        <col class="kardex-col-quantity">
                        <col class="kardex-col-unit-cost">
                        <col class="kardex-col-total-cost">
                        <col class="kardex-col-quantity">
                        <col class="kardex-col-unit-cost">
                        <col class="kardex-col-total-cost">
                        <col class="kardex-col-user">
                        <col class="kardex-col-user">
                        <col class="kardex-col-status">
                        <col class="kardex-col-actions">
                    </colgroup>
                    <thead class="bg-light">
                        <tr>
                            <th rowspan="2">#</th>
                            <th rowspan="2">FECHA</th>
                            <th rowspan="2">N&deg; MOV.</th>
                            <th rowspan="2">ALMAC&Eacute;N</th>
                            <th rowspan="2">ART&Iacute;CULO</th>
                            <th rowspan="2">LOTE</th>
                            <th rowspan="2">F. VENC.</th>
                            <th rowspan="2">TIPO</th>
                            <th rowspan="2">DOCUMENTO</th>
                            <th colspan="3" class="kardex-group-heading kardex-group-entry">ENTRADAS</th>
                            <th colspan="3" class="kardex-group-heading kardex-group-exit">SALIDAS</th>
                            <th colspan="3" class="kardex-group-heading kardex-group-balance">SALDOS</th>
                            <th rowspan="2">REGISTRADO POR</th>
                            <th rowspan="2">CORREGIDO POR</th>
                            <th rowspan="2">ESTADO</th>
                            <th rowspan="2">ACCIONES</th>
                        </tr>
                        <tr>
                            <th class="kardex-subheading-entry">CANT.</th><th class="kardex-subheading-entry">C. UNIT.</th><th class="kardex-subheading-entry">C. TOTAL</th>
                            <th class="kardex-subheading-exit">CANT.</th><th class="kardex-subheading-exit">C. UNIT.</th><th class="kardex-subheading-exit">C. TOTAL</th>
                            <th class="kardex-subheading-balance">CANT.</th><th class="kardex-subheading-balance">C. PROM.</th><th class="kardex-subheading-balance">VALOR</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    @include('admin.kardex.partials.viewModal')

    <div class="modal fade" id="format12Modal" tabindex="-1" role="dialog" aria-labelledby="format12ModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable format12-modal-dialog" role="document">
            <div class="modal-content format12-modal-content-shell">
                <div class="modal-header format12-modal-header">
                    <div class="d-flex align-items-center">
                        <span class="format12-modal-icon mr-3" aria-hidden="true">
                            <i class="fas fa-file-alt"></i>
                        </span>
                        <div>
                            <h5 class="modal-title" id="format12ModalTitle">Formato 12.1</h5>
                            <div class="format12-modal-subtitle">Registro de Inventario Permanente en Unidades Físicas</div>
                            <small>Consulta mensual construida desde los movimientos históricos del Kardex.</small>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="format12ModalBody">
                    <div class="format12-modal-content" data-format12-modal-content>
                        <div class="format12-modal-loading">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                            <span>Preparando el registro...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer format12-modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
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
            padding: 10px 14px 14px;
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
            width: 100%;
            max-width: 100%;
            border: 1px solid #edf2f3;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .kardex-table-wrap .dataTables_wrapper {
            width: 100%;
        }

        .kardex-table-wrap .dataTables_scroll {
            position: relative;
            border-top: 1px solid #e7eef0;
            border-bottom: 1px solid #e7eef0;
            background: #fff;
        }

        .kardex-table-wrap .dataTables_scroll::before,
        .kardex-table-wrap .dataTables_scroll::after {
            content: "";
            position: absolute;
            z-index: 8;
            top: 0;
            bottom: 0;
            width: 12px;
            pointer-events: none;
            opacity: 0;
            transition: opacity .16s ease;
        }

        .kardex-table-wrap .dataTables_scroll::before {
            left: 0;
            background: linear-gradient(90deg, rgba(42, 62, 67, .12), transparent);
        }

        .kardex-table-wrap .dataTables_scroll::after {
            right: 0;
            background: linear-gradient(270deg, rgba(42, 62, 67, .12), transparent);
        }

        .kardex-table-wrap .dataTables_scroll.kardex-has-left-overflow::before,
        .kardex-table-wrap .dataTables_scroll.kardex-has-right-overflow::after {
            opacity: 1;
        }

        .kardex-table-wrap .dataTables_scrollHead {
            position: relative;
            z-index: 4;
            border-bottom: 0 !important;
            background: #f8fbfb;
        }

        .kardex-table-wrap .dataTables_scrollBody {
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: #9ebfba #edf4f3;
        }

        .kardex-table-wrap .dataTables_scrollBody::-webkit-scrollbar {
            width: 9px;
            height: 10px;
        }

        .kardex-table-wrap .dataTables_scrollBody::-webkit-scrollbar-track {
            background: #edf4f3;
        }

        .kardex-table-wrap .dataTables_scrollBody::-webkit-scrollbar-thumb {
            border: 2px solid #edf4f3;
            border-radius: 999px;
            background: #9ebfba;
        }

        .kardex-table-wrap .dataTables_scrollBody:focus-visible {
            outline: 2px solid rgba(17, 134, 122, .28);
            outline-offset: -2px;
        }

        .kardex-grid-navigation {
            display: none;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            padding: 7px 9px;
            border: 1px solid #e4ecee;
            border-radius: 9px;
            background: linear-gradient(180deg, #fbfdfd, #f7faf9);
        }

        .kardex-grid-navigation.is-visible {
            display: flex;
        }

        .kardex-grid-zones {
            display: flex;
            flex: 0 0 auto;
            flex-wrap: wrap;
            gap: 5px;
        }

        .kardex-grid-zone {
            padding: 4px 10px;
            border: 1px solid #dce7e8;
            border-radius: 999px;
            background: #fff;
            color: #65737a;
            font-size: 10.5px;
            font-weight: 750;
            line-height: 1.25;
            transition: border-color .15s ease, background .15s ease, color .15s ease;
        }

        .kardex-grid-zone:hover,
        .kardex-grid-zone:focus-visible {
            border-color: #9fcfc9;
            color: #0f766d;
            outline: none;
        }

        .kardex-grid-zone.is-active {
            border-color: #b9dbd7;
            background: #eaf5f3;
            color: #0f766d;
        }

        .kardex-scroll-proxy {
            min-width: 90px;
            flex: 1 1 auto;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
            scrollbar-color: #9ebfba #e8f0ef;
        }

        .kardex-scroll-proxy::-webkit-scrollbar {
            height: 8px;
        }

        .kardex-scroll-proxy::-webkit-scrollbar-track {
            border-radius: 999px;
            background: #e8f0ef;
        }

        .kardex-scroll-proxy::-webkit-scrollbar-thumb {
            border: 2px solid #e8f0ef;
            border-radius: 999px;
            background: #9ebfba;
        }

        .kardex-scroll-proxy:focus-visible {
            outline: 2px solid rgba(17, 134, 122, .25);
            outline-offset: 2px;
        }

        .kardex-scroll-proxy-track {
            height: 1px;
        }

        .kardex-table-wrap table.dataTable {
            width: 2160px !important;
            min-width: 2160px !important;
            table-layout: fixed;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 0 !important;
        }

        .kardex-table-wrap table.dataTable .kardex-col-index { width: 48px; }
        .kardex-table-wrap table.dataTable .kardex-col-date { width: 96px; }
        .kardex-table-wrap table.dataTable .kardex-col-movement { width: 96px; }
        .kardex-table-wrap table.dataTable .kardex-col-warehouse { width: 125px; }
        .kardex-table-wrap table.dataTable .kardex-col-article { width: 220px; }
        .kardex-table-wrap table.dataTable .kardex-col-lot { width: 82px; }
        .kardex-table-wrap table.dataTable .kardex-col-expiration { width: 88px; }
        .kardex-table-wrap table.dataTable .kardex-col-type { width: 135px; }
        .kardex-table-wrap table.dataTable .kardex-col-document { width: 170px; }
        .kardex-table-wrap table.dataTable .kardex-col-quantity { width: 72px; }
        .kardex-table-wrap table.dataTable .kardex-col-unit-cost { width: 80px; }
        .kardex-table-wrap table.dataTable .kardex-col-total-cost { width: 90px; }
        .kardex-table-wrap table.dataTable .kardex-col-user { width: 110px; }
        .kardex-table-wrap table.dataTable .kardex-col-status { width: 90px; }
        .kardex-table-wrap table.dataTable .kardex-col-actions { width: 64px; }

        .kardex-table-wrap table.dataTable thead th {
            padding: 10px 7px;
            border: 0 !important;
            border-bottom: 1px solid #e7eef0 !important;
            background: #f8fbfb;
            color: #59636d;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .18px;
            text-align: center;
            vertical-align: middle !important;
            white-space: nowrap;
        }

        .kardex-table-wrap table.dataTable thead tr:first-child th {
            height: 42px;
        }

        .kardex-table-wrap table.dataTable thead tr:nth-child(2) th {
            height: 34px;
            padding-top: 7px;
            padding-bottom: 7px;
            font-size: 10px;
            text-align: right;
        }

        .kardex-table-wrap table.dataTable thead .kardex-group-heading {
            border-bottom-color: transparent !important;
            color: #fff;
            font-size: 10.5px;
            letter-spacing: .55px;
        }

        .kardex-table-wrap table.dataTable thead .kardex-group-entry {
            background: #3f8067;
        }

        .kardex-table-wrap table.dataTable thead .kardex-group-exit {
            background: #b7625e;
        }

        .kardex-table-wrap table.dataTable thead .kardex-group-balance {
            background: #2d7c82;
        }

        .kardex-table-wrap table.dataTable thead .kardex-subheading-entry {
            background: #edf7f2;
            color: #2c6f55;
        }

        .kardex-table-wrap table.dataTable thead .kardex-subheading-exit {
            background: #fcf0ef;
            color: #9f4e4a;
        }

        .kardex-table-wrap table.dataTable thead .kardex-subheading-balance {
            background: #ebf6f6;
            color: #246c71;
        }

        .kardex-table-wrap table.dataTable tbody td {
            padding: 8px 7px;
            border-top: 1px solid #f0f3f4;
            color: #39434d;
            font-size: 12px;
            vertical-align: middle !important;
            overflow: hidden;
        }

        .kardex-table-wrap .kardex-sticky-left,
        .kardex-table-wrap .kardex-sticky-right {
            position: sticky !important;
            background: #fff;
        }

        .kardex-table-wrap tbody .kardex-sticky-left,
        .kardex-table-wrap tbody .kardex-sticky-right {
            z-index: 3;
        }

        .kardex-table-wrap thead .kardex-sticky-left,
        .kardex-table-wrap thead .kardex-sticky-right {
            z-index: 7 !important;
            background: #f8fbfb !important;
        }

        .kardex-table-wrap .kardex-sticky-right {
            right: 0;
        }

        .kardex-table-wrap .kardex-sticky-left-edge.kardex-shadow-visible {
            box-shadow: 9px 0 13px -11px rgba(30, 54, 59, .8);
        }

        .kardex-table-wrap .kardex-sticky-right.kardex-shadow-visible {
            box-shadow: -9px 0 13px -11px rgba(30, 54, 59, .8);
        }

        .kardex-table-wrap table.dataTable tbody tr:nth-child(even) {
            background: #fcfdfd;
        }

        .kardex-table-wrap table.dataTable tbody tr:nth-child(even) .kardex-sticky-left,
        .kardex-table-wrap table.dataTable tbody tr:nth-child(even) .kardex-sticky-right {
            background: #fcfdfd;
        }

        .kardex-table-wrap table.dataTable tbody tr:hover {
            background: #f5faf9;
        }

        .kardex-table-wrap table.dataTable tbody tr:hover .kardex-sticky-left,
        .kardex-table-wrap table.dataTable tbody tr:hover .kardex-sticky-right {
            background: #f5faf9;
        }

        .kardex-table-wrap table.dataTable tbody td:nth-child(10),
        .kardex-table-wrap table.dataTable tbody td:nth-child(13),
        .kardex-table-wrap table.dataTable tbody td:nth-child(16),
        .kardex-table-wrap table.dataTable tbody td:nth-child(19) {
            border-left: 1px solid #e3ebec;
        }

        .kardex-table-wrap table.dataTable tbody td:nth-child(n+10):nth-child(-n+18) {
            text-align: right !important;
            white-space: nowrap;
        }

        .kardex-table-wrap table.dataTable tbody td:nth-child(2),
        .kardex-table-wrap table.dataTable tbody td:nth-child(3),
        .kardex-table-wrap table.dataTable tbody td:nth-child(6),
        .kardex-table-wrap table.dataTable tbody td:nth-child(7),
        .kardex-table-wrap table.dataTable tbody td:nth-child(21),
        .kardex-table-wrap table.dataTable tbody td:nth-child(22) {
            white-space: nowrap;
        }

        .kardex-text-clamp,
        .kardex-text-ellipsis {
            display: block;
            width: 100%;
            min-width: 0;
            text-align: left;
            overflow: hidden;
        }

        .kardex-text-clamp {
            display: -webkit-box;
            line-height: 1.3;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
        }

        .kardex-text-ellipsis {
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .kardex-article-cell {
            width: 100%;
            min-width: 0;
            text-align: left;
            line-height: 1.2;
            overflow: hidden;
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
            display: -webkit-box;
            color: #26323b;
            font-size: 12px;
            font-weight: 750;
            line-height: 1.3;
            overflow: hidden;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
        }

        .kardex-document-pill {
            display: inline-flex;
            width: 100%;
            min-width: 0;
            align-items: center;
            max-width: 100%;
            padding: 4px 8px;
            border: 1px solid #e1e8ea;
            border-radius: 7px;
            background: #f4f7f8;
            color: #4d5963;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
        }

        .kardex-document-pill i {
            flex: 0 0 auto;
            color: #74858c;
        }

        .kardex-document-text {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
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
            white-space: nowrap;
        }

        .kardex-num-out {
            color: #cf4b45;
            font-weight: 800;
            white-space: nowrap;
        }

        .kardex-num-balance {
            color: #11867a;
            font-weight: 850;
            white-space: nowrap;
        }

        .kardex-money {
            color: #34404b;
            font-weight: 750;
            white-space: nowrap;
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

        .kardex-table-wrap table.dataTable .badge {
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 10.5px;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .35);
        }

        .kardex-table-wrap table.dataTable .badge-success {
            background: #e7f6ef;
            color: #14764e;
        }

        .kardex-table-wrap table.dataTable .badge-danger {
            background: #fdeceb;
            color: #bd3d39;
        }

        .kardex-table-wrap table.dataTable .badge-primary {
            background: #eaf3ff;
            color: #236db5;
        }

        .kardex-table-wrap table.dataTable .badge-warning {
            background: #fff2e8;
            color: #bf5d22 !important;
        }

        .kardex-table-wrap table.dataTable .badge-info {
            background: #e6f6f4;
            color: #11867a;
        }

        .kardex-table-wrap table.dataTable .badge-purple {
            background: #f0ebfb;
            color: #6942b5;
        }

        .kardex-table-wrap table.dataTable .badge-secondary,
        .kardex-table-wrap table.dataTable .badge-light {
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

        .kardex-table-card .kardex-dt-toolbar {
            margin: 0 !important;
            padding: 10px 9px;
            background: linear-gradient(180deg, #fbfdfd, #f7fbfa);
        }

        .kardex-table-card .kardex-dt-footer {
            margin: 0 !important;
            padding: 10px 9px;
            background: #fff;
        }

        .kardex-table-card .kardex-dt-toolbar > [class*="col-"],
        .kardex-table-card .kardex-dt-footer > [class*="col-"] {
            padding-right: 5px;
            padding-left: 5px;
        }

        .kardex-table-card .kardex-dt-toolbar .dt-buttons {
            float: none;
            margin: 0;
        }

        .kardex-table-card .kardex-dt-toolbar .dataTables_filter label,
        .kardex-table-card .kardex-dt-toolbar .dataTables_length label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            white-space: nowrap;
        }

        .kardex-table-card .kardex-dt-footer .dataTables_info {
            padding-top: 0;
            color: #718087;
            font-size: 11.5px;
        }

        .kardex-table-card .kardex-dt-footer .pagination {
            margin: 0;
        }

        .kardex-dashboard .dataTables_filter input,
        .kardex-dashboard .dataTables_length select {
            border: 1px solid #dfe8ea;
            border-radius: 7px;
            font-size: 12px;
            height: 30px;
        }

        .kardex-table-card .dataTables_wrapper > .row {
            margin-right: 0;
            margin-left: 0;
        }

        .kardex-table-card .dataTables_wrapper > .row > [class*="col-"] {
            padding-right: 5px;
            padding-left: 5px;
        }

        .kardex-table-card .dataTables_filter,
        .kardex-table-card .dataTables_length {
            margin-bottom: 0;
            color: #65727a;
            font-size: 12px;
        }

        @media (max-width: 1199.98px) {
            .kardex-table-card .kardex-dt-toolbar > [class*="col-"] {
                margin-bottom: 7px;
            }
        }

        @media (max-width: 767.98px) {
            .kardex-grid-navigation.is-visible {
                align-items: stretch;
                flex-direction: column;
                gap: 6px;
            }

            .kardex-grid-zones {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 2px;
            }

            .kardex-grid-zone {
                flex: 0 0 auto;
            }

            .kardex-scroll-proxy {
                flex-basis: auto;
                width: 100%;
            }
        }

        @media (min-width: 1200px) {
            .kardex-table-card .kardex-dt-toolbar .dataTables_filter {
                text-align: center;
            }

            .kardex-table-card .kardex-dt-toolbar .dt-buttons {
                text-align: right;
            }

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
        #tableKardex thead th.kardex-group-entry,
        #tableKardex_wrapper .dataTables_scrollHead thead th.kardex-group-entry {
            background: #256B52 !important;
            background-color: #256B52 !important;
            background-image: none !important;
            color: #FFFFFF !important;
            opacity: 1 !important;
            filter: none !important;
            font-weight: 800 !important;
            font-size: 11px;
            letter-spacing: .45px;
            text-align: center !important;
        }

        #tableKardex thead th.kardex-group-exit,
        #tableKardex_wrapper .dataTables_scrollHead thead th.kardex-group-exit {
            background: #A34843 !important;
            background-color: #A34843 !important;
            background-image: none !important;
            color: #FFFFFF !important;
            opacity: 1 !important;
            filter: none !important;
            font-weight: 800 !important;
            font-size: 11px;
            letter-spacing: .45px;
            text-align: center !important;
        }

        #tableKardex thead th.kardex-group-balance,
        #tableKardex_wrapper .dataTables_scrollHead thead th.kardex-group-balance {
            background: #216873 !important;
            background-color: #216873 !important;
            background-image: none !important;
            color: #FFFFFF !important;
            opacity: 1 !important;
            filter: none !important;
            font-weight: 800 !important;
            font-size: 11px;
            letter-spacing: .45px;
            text-align: center !important;
        }

        #tableKardex_wrapper .dataTables_scrollBody thead {
            visibility: hidden !important;
        }

        #tableKardex_wrapper .dataTables_scrollBody thead tr,
        #tableKardex_wrapper .dataTables_scrollBody thead th {
            height: 0 !important;
            min-height: 0 !important;
            max-height: 0 !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            border-top: 0 !important;
            border-bottom: 0 !important;
            background: transparent !important;
            color: transparent !important;
            line-height: 0 !important;
        }

        #format12Modal .format12-modal-dialog {
            width: min(95vw, 1600px);
            max-width: 1600px;
            min-height: calc(100% - 3.5rem);
            margin: 1.75rem auto;
        }

        #format12Modal .format12-modal-content-shell {
            max-height: 90vh;
            overflow: hidden;
            border: 0;
            border-radius: 12px;
            box-shadow: 0 20px 50px rgba(35, 51, 58, .18);
        }

        #format12Modal .format12-modal-header {
            align-items: flex-start;
            padding: 14px 18px;
            border-bottom: 1px solid #e4ebed;
            background: #fff;
        }

        #format12Modal .format12-modal-header .close {
            margin: -5px -5px -5px auto;
            color: #64727a;
        }

        #format12Modal .format12-modal-icon {
            display: inline-flex;
            width: 38px;
            height: 38px;
            flex: 0 0 auto;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            background: #e9f4f2;
            color: #167b70;
            font-size: 15px;
        }

        #format12Modal .modal-title {
            color: #26343d;
            font-size: 16px;
            font-weight: 800;
            line-height: 1.15;
        }

        #format12Modal .format12-modal-subtitle {
            margin-top: 2px;
            color: #4d5d64;
            font-size: 12px;
            font-weight: 700;
        }

        #format12Modal .format12-modal-header small {
            display: block;
            margin-top: 2px;
            color: #7a878d;
            font-size: 10.5px;
        }

        #format12Modal .modal-body {
            padding: 14px 16px;
            overflow-x: hidden;
            background: #f4f7f8;
        }

        #format12Modal .format12-modal-footer {
            padding: 8px 16px;
            border-top: 1px solid #e4ebed;
            background: #fff;
        }

        #format12Modal .format12-modal-loading {
            display: flex;
            min-height: 260px;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #64747b;
            font-size: 12px;
        }

        #format12Modal .format12-modal-loading .spinner-border {
            width: 1.5rem;
            height: 1.5rem;
            margin-bottom: 10px;
            color: #178579;
        }

        #format12Modal .select2-container {
            width: 100% !important;
        }

        @media (max-width: 767.98px) {
            #format12Modal .format12-modal-dialog {
                width: calc(100vw - 16px);
                min-height: calc(100% - 16px);
                margin: 8px auto;
            }

            #format12Modal .format12-modal-content-shell {
                max-height: calc(100vh - 16px);
            }

            #format12Modal .format12-modal-header {
                padding: 12px;
            }

            #format12Modal .format12-modal-icon {
                display: none;
            }

            #format12Modal .modal-body {
                padding: 10px;
            }
        }
    </style>
@endpush

@push('js')
    <script>
        window.routes = Object.assign(window.routes || {}, {
            kardexList: "{{ route('admin.kardex.list') }}",
            kardexShow: "{{ url('admin/kardex') }}",
            kardexStock: "{{ route('admin.kardex.stock') }}",
            kardexStockAtDate: "{{ route('admin.kardex.stock-at-date') }}",
            kardexRecalculate: "{{ route('admin.kardex.recalculate') }}",
            kardexExport: "{{ url('admin/kardex/export') }}",
            kardexCanExport: @json(auth()->user()?->can('admin.kardex.export') ?? false)
        });
    </script>
    @vite(['resources/js/pages/kardex.js'])
@endpush
