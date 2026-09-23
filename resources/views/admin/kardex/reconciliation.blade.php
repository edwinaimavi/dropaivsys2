@extends('layouts.app')

@section('subtitle', 'Cuadre de Inventario')

@section('header')
    <div class="container-fluid no-print">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="h3 mb-1 font-weight-bold text-dark">Cuadre de Inventario &mdash; Stock vs Kardex vs Valorizaci&oacute;n</h1>
                <small class="text-muted">Auditor&iacute;a de solo lectura de existencias, valorizaci&oacute;n y documentos origen</small>
            </div>
            <a href="{{ route('admin.kardex.index') }}" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
                <i class="fas fa-arrow-left mr-1"></i> Volver al Kardex
            </a>
        </div>
    </div>
@stop

@section('content_body')
    <div class="container-fluid reconciliation-report">
        <div class="card border-0 shadow-sm mb-4 no-print">
            <div class="card-header border-0 bg-white">
                <h5 class="mb-0 font-weight-bold"><i class="fas fa-filter text-warning mr-1"></i> Alcance de la auditor&iacute;a</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.kardex.reconciliation') }}">
                    <input type="hidden" name="consult" value="1">
                    <div class="row align-items-end">
                        <div class="form-group col-md-4">
                            <label for="company_id">EMPRESA</label>
                            <select id="company_id" name="company_id" class="form-control" required>
                                <option value="">Seleccione</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected((int) $companyId === (int) $company->id)>{{ $company->business_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="warehouse_id">ALMAC&Eacute;N</label>
                            <select id="warehouse_id" name="warehouse_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((int) $warehouseId === (int) $warehouse->id)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label for="article_id">ART&Iacute;CULO</label>
                            <select id="article_id" name="article_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($articles as $article)
                                    <option value="{{ $article->id }}" @selected((int) $articleId === (int) $article->id)>{{ $article->code }} | {{ $article->billing_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end flex-wrap">
                        <button type="submit" class="btn btn-warning mr-2 mb-2"><i class="fas fa-search mr-1"></i> Consultar</button>
                        <a href="{{ route('admin.kardex.reconciliation') }}" class="btn btn-outline-secondary mr-2 mb-2"><i class="fas fa-eraser mr-1"></i> Limpiar</a>
                        <button type="button" class="btn btn-outline-dark mb-2" onclick="window.print()"><i class="fas fa-print mr-1"></i> Imprimir</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger no-print">{{ $errors->first() }}</div>
        @endif

        @if ($report)
            @php($summary = $report['summary'])
            <div class="audit-heading mb-3">
                <h2>CUADRE DE INVENTARIO</h2>
                <p>Stock f&iacute;sico, Kardex hist&oacute;rico, pool de valorizaci&oacute;n y documentos origen</p>
            </div>

            <div class="row audit-summary no-print">
                <div class="col-6 col-lg-2 mb-3"><div class="summary-card summary-ok"><span>Grupos OK</span><strong>{{ $summary['ok_groups'] }}</strong></div></div>
                <div class="col-6 col-lg-2 mb-3"><div class="summary-card summary-warning"><span>Warnings</span><strong>{{ $summary['warning_groups'] }}</strong></div></div>
                <div class="col-6 col-lg-2 mb-3"><div class="summary-card summary-error"><span>Errores</span><strong>{{ $summary['error_groups'] }}</strong></div></div>
                <div class="col-6 col-lg-2 mb-3"><div class="summary-card"><span>Dif. f&iacute;sicas</span><strong>{{ $summary['stock_quantity_mismatches'] + $summary['pool_quantity_mismatches'] }}</strong></div></div>
                <div class="col-6 col-lg-2 mb-3"><div class="summary-card"><span>Dif. valorizadas</span><strong>{{ $summary['valuation_mismatches'] }}</strong></div></div>
                <div class="col-6 col-lg-2 mb-3"><div class="summary-card"><span>Documentos</span><strong>{{ $summary['document_issues'] }}</strong></div></div>
            </div>

            <div class="card border-0 shadow-sm audit-table-card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0 audit-table">
                            <thead>
                                <tr>
                                    <th>ART&Iacute;CULO</th>
                                    <th>ALMAC&Eacute;N</th>
                                    <th>STOCK F&Iacute;SICO</th>
                                    <th>KARDEX F&Iacute;SICO</th>
                                    <th>POOL F&Iacute;SICO</th>
                                    <th>VALOR KARDEX</th>
                                    <th>VALOR POOL</th>
                                    <th>DIF. CANTIDAD</th>
                                    <th>DIF. VALOR</th>
                                    <th>ESTADO</th>
                                    <th class="no-print">DETALLE</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($report['groups'] as $group)
                                    <tr>
                                        <td><strong>{{ $group['article_code'] ?: 'ID '.$group['article_id'] }}</strong><small>{{ $group['article_description'] }}</small></td>
                                        <td>{{ $group['warehouse_name'] ?: 'ID '.$group['warehouse_id'] }}</td>
                                        <td class="number">{{ $group['physical_quantity'] }}</td>
                                        <td class="number">{{ $group['kardex_quantity'] }}</td>
                                        <td class="number">{{ $group['pool_quantity'] }}</td>
                                        <td class="number">{{ $group['kardex_total_cost'] }}</td>
                                        <td class="number">{{ $group['pool_total_cost'] }}</td>
                                        <td class="number">
                                            S/K: {{ $group['quantity_difference_stock_vs_kardex'] }}<br>
                                            P/K: {{ $group['quantity_difference_pool_vs_kardex'] }}
                                        </td>
                                        <td class="number">{{ $group['value_difference_pool_vs_kardex'] }}</td>
                                        <td class="text-center"><span class="status-badge status-{{ strtolower($group['status']) }}">{{ $group['status'] }}</span></td>
                                        <td class="no-print text-center">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="collapse" data-target="#audit-detail-{{ $group['warehouse_id'] }}-{{ $group['article_id'] }}">
                                                {{ count($group['issues']) }} incidencia(s)
                                            </button>
                                        </td>
                                    </tr>
                                    <tr id="audit-detail-{{ $group['warehouse_id'] }}-{{ $group['article_id'] }}" class="collapse issue-row no-print">
                                        <td colspan="11">
                                            @forelse ($group['issues'] as $issue)
                                                <div class="issue-item issue-{{ $issue['severity'] }}">
                                                    <strong>{{ $issue['type'] }}</strong> &mdash; {{ $issue['message'] }}
                                                    <div>
                                                        Esperado: {{ $issue['expected'] ?? '—' }} |
                                                        Actual: {{ $issue['actual'] ?? '—' }} |
                                                        Diferencia: {{ $issue['difference'] ?? '—' }}
                                                        @if ($issue['movement_number']) | Movimiento: {{ $issue['movement_number'] }} @endif
                                                        @if ($issue['source_id']) | Origen: {{ class_basename($issue['source_type']) }} #{{ $issue['source_id'] }} @endif
                                                    </div>
                                                </div>
                                            @empty
                                                <span class="text-success">Sin incidencias.</span>
                                            @endforelse
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="11" class="text-center text-muted py-4">No existen datos de inventario para los filtros seleccionados.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@stop

@push('css')
    <style>
        .reconciliation-report { color: #29343d; }
        .reconciliation-report label { font-size: 11px; font-weight: 700; letter-spacing: .04em; }
        .audit-heading h2 { font-size: 19px; font-weight: 800; margin: 0; }
        .audit-heading p { color: #6c757d; font-size: 12px; margin: 3px 0 0; }
        .summary-card { align-items: center; background: #fff; border-left: 4px solid #607d8b; border-radius: 7px; box-shadow: 0 2px 9px rgba(30, 45, 60, .08); display: flex; justify-content: space-between; min-height: 72px; padding: 12px; }
        .summary-card span { color: #66737f; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .summary-card strong { font-size: 24px; }
        .summary-ok { border-left-color: #28a745; }
        .summary-warning { border-left-color: #f0ad4e; }
        .summary-error { border-left-color: #dc3545; }
        .audit-table { min-width: 1200px; }
        .audit-table thead th { background: #37474f; border-color: #526168; color: #fff; font-size: 9px; text-align: center; vertical-align: middle; }
        .audit-table td { font-size: 10px; vertical-align: middle; }
        .audit-table td small { color: #6c757d; display: block; }
        .audit-table .number { font-family: Consolas, monospace; text-align: right; white-space: nowrap; }
        .status-badge { border-radius: 12px; display: inline-block; font-size: 9px; font-weight: 800; padding: 4px 9px; }
        .status-ok { background: #d9f2df; color: #176629; }
        .status-warning { background: #fff1ca; color: #805f00; }
        .status-error { background: #f9d7db; color: #962434; }
        .issue-row td { background: #f8fafb; padding: 12px 18px; }
        .issue-item { border-left: 3px solid #f0ad4e; margin-bottom: 7px; padding-left: 10px; }
        .issue-item:last-child { margin-bottom: 0; }
        .issue-error { border-left-color: #dc3545; }
        .issue-item div { color: #6c757d; font-size: 9px; margin-top: 2px; }

        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body { background: #fff !important; color: #000; font-family: Arial, sans-serif; }
            .no-print, .main-header, .main-sidebar, .main-footer, .content-header { display: none !important; }
            .content-wrapper, .content, .container-fluid { margin: 0 !important; padding: 0 !important; }
            .audit-table-card { border: 0 !important; box-shadow: none !important; }
            .audit-table { min-width: 0; table-layout: fixed; width: 100%; }
            .audit-table tr { break-inside: avoid; page-break-inside: avoid; }
            .audit-table thead th { background: #e8ecef !important; border-color: #555 !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .audit-table th, .audit-table td { border-color: #555 !important; font-size: 8px; }
        }
    </style>
@endpush
