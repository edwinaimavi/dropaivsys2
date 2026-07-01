@extends('layouts.app')

@section('subtitle', 'Kardex de Almac&eacute;n')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-clipboard-list text-info"></i>
                    Kardex de Almac&eacute;n
                </h1>
                <small class="text-muted">Control de movimientos y saldos de inventario</small>
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
    <div class="row kardex-summary-row">
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm kardex-summary-card">
                <div class="card-body">
                    <small>Art&iacute;culos con stock</small>
                    <strong>{{ number_format($stats['stock_articles']) }}</strong>
                    <i class="fas fa-boxes"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm kardex-summary-card">
                <div class="card-body">
                    <small>Entradas del mes</small>
                    <strong>{{ number_format((float) $stats['month_entries'], 2) }}</strong>
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm kardex-summary-card">
                <div class="card-body">
                    <small>Salidas del mes</small>
                    <strong>{{ number_format((float) $stats['month_exits'], 2) }}</strong>
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3 mb-3">
            <div class="card border-0 shadow-sm kardex-summary-card">
                <div class="card-body">
                    <small>Valor inventario</small>
                    <strong>S/ {{ number_format((float) $stats['inventory_value'], 2) }}</strong>
                    <i class="fas fa-coins"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-lg kardex-card mb-3">
        <div class="card-header bg-white border-0 pt-3 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-filter text-info mr-1"></i>
                Filtros
            </h5>
        </div>
        <div class="card-body pt-2">
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
                <div class="form-group col-md-3 d-flex align-items-end">
                    <button type="button" id="btnFilterKardex" class="btn btn-info btn-sm mr-2">
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

    <div class="card border-0 shadow-lg kardex-card">
        <div class="card-header bg-white border-0 pt-4 pb-2">
            <h5 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-list text-info mr-1"></i>
                Movimientos Kardex
            </h5>
            <small class="text-muted">Historial auditable de entradas, salidas, ajustes y reversas</small>
        </div>
        <div class="card-body pt-2">
            <div class="table-responsive">
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
@stop

@push('css')
    <style>
        .kardex-card,
        .kardex-summary-card {
            border-radius: 12px;
        }

        .kardex-summary-card .card-body {
            position: relative;
            min-height: 92px;
            padding: 16px;
            overflow: hidden;
        }

        .kardex-summary-card small {
            display: block;
            color: #6c7780;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .kardex-summary-card strong {
            display: block;
            margin-top: 8px;
            color: #11867a;
            font-size: 22px;
            font-weight: 800;
        }

        .kardex-summary-card i {
            position: absolute;
            right: 16px;
            bottom: 14px;
            color: #d9f0ed;
            font-size: 34px;
        }

        #tableKardex thead th {
            padding: 12px 8px;
            border: 0 !important;
            color: #59636d;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        #tableKardex tbody td {
            padding: 10px 7px;
            border-top: 1px solid #f1f1f1;
            font-size: 12px;
            vertical-align: middle !important;
        }

        .kardex-card label {
            margin-bottom: 3px;
            color: #68717a;
            font-size: 10px;
            font-weight: 700;
        }

        .kardex-card .form-control {
            height: 30px;
            border-color: #dfe6e8;
            border-radius: 6px;
            font-size: 12px;
        }

        .badge-purple {
            background: #6f42c1;
        }

        .kardex-view-modal {
            border-radius: 12px;
            overflow: hidden;
        }

        .kardex-view-modal .modal-header {
            background: linear-gradient(135deg, #11867a, #159f93) !important;
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

        .kardex-detail-grid > [class*="col-"] {
            margin-bottom: 10px;
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
            font-weight: 700;
            line-height: 1.15;
        }

        .kardex-detail-field strong {
            display: block;
            color: #2e3440;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.25;
            word-break: break-word;
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
