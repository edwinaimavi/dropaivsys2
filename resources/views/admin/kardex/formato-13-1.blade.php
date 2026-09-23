@extends('layouts.app')

@section('subtitle', 'Formato 13.1 - Inventario Valorizado')

@section('header')
    <div class="container-fluid no-print">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="h3 mb-1 font-weight-bold text-dark">Formato 13.1 &mdash; Registro de Inventario Permanente Valorizado</h1>
                <small class="text-muted">Detalle valorizado mensual construido exclusivamente desde los movimientos hist&oacute;ricos del Kardex</small>
            </div>
            <a href="{{ route('admin.kardex.index') }}" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
                <i class="fas fa-arrow-left mr-1"></i> Volver al Kardex
            </a>
        </div>
    </div>
@stop

@section('content_body')
    <div class="container-fluid valued-register">
        <div class="card border-0 shadow-sm mb-4 no-print">
            <div class="card-header border-0 bg-white">
                <h5 class="mb-0 font-weight-bold"><i class="fas fa-filter text-success mr-1"></i> Filtros del registro</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.kardex.formato-13-1') }}">
                    <input type="hidden" name="consult" value="1">
                    <div class="row align-items-end">
                        <div class="form-group col-md-3">
                            <label for="company_id">EMPRESA</label>
                            <select id="company_id" name="company_id" class="form-control" required>
                                <option value="">Seleccione</option>
                                @foreach ($companies as $company)
                                    <option value="{{ $company->id }}" @selected((int) $companyId === (int) $company->id)>
                                        {{ $company->business_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="year">A&Ntilde;O</label>
                            <input id="year" name="year" type="number" min="2000" max="2100" value="{{ $year }}" class="form-control" required>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="month">MES</label>
                            <select id="month" name="month" class="form-control" required>
                                @foreach (range(1, 12) as $monthOption)
                                    <option value="{{ $monthOption }}" @selected((int) $month === $monthOption)>
                                        {{ str_pad((string) $monthOption, 2, '0', STR_PAD_LEFT) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-3">
                            <label for="warehouse_id">ALMAC&Eacute;N</label>
                            <select id="warehouse_id" name="warehouse_id" class="form-control" required>
                                <option value="">Seleccione</option>
                                @foreach ($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected((int) $warehouseId === (int) $warehouse->id)>
                                        {{ $warehouse->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-2">
                            <label for="article_id">ART&Iacute;CULO</label>
                            <select id="article_id" name="article_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach ($articles as $article)
                                    <option value="{{ $article->article_id }}" @selected((int) $articleId === (int) $article->article_id)>
                                        {{ $article->article_code_snapshot }} | {{ $article->article_description_snapshot }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end flex-wrap">
                        <button type="submit" class="btn btn-success mr-2 mb-2"><i class="fas fa-search mr-1"></i> Consultar</button>
                        <a href="{{ route('admin.kardex.formato-13-1') }}" class="btn btn-outline-secondary mr-2 mb-2"><i class="fas fa-eraser mr-1"></i> Limpiar</a>
                        @if ($report && (auth()->user()?->can('admin.kardex.export') ?? false))
                            <a href="{{ route('admin.kardex.formato-13-1.export', ['format' => 'excel', 'company_id' => $companyId, 'year' => $year, 'month' => $month, 'warehouse_id' => $warehouseId, 'article_id' => $articleId]) }}" class="btn btn-outline-success mr-2 mb-2">
                                <i class="fas fa-file-excel mr-1"></i> Excel
                            </a>
                            <a href="{{ route('admin.kardex.formato-13-1.export', ['format' => 'pdf', 'company_id' => $companyId, 'year' => $year, 'month' => $month, 'warehouse_id' => $warehouseId, 'article_id' => $articleId]) }}" class="btn btn-outline-danger mr-2 mb-2">
                                <i class="fas fa-file-pdf mr-1"></i> PDF
                            </a>
                        @endif
                        <button type="button" class="btn btn-outline-dark mb-2" onclick="window.print()"><i class="fas fa-print mr-1"></i> Imprimir</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger no-print">{{ $errors->first() }}</div>
        @endif

        @if ($report)
            @if ($report['incomplete_snapshot_count'] > 0)
                <div class="alert alert-warning no-print">
                    <strong><i class="fas fa-exclamation-triangle mr-1"></i> Informaci&oacute;n hist&oacute;rica incompleta.</strong>
                    Este per&iacute;odo contiene movimientos hist&oacute;ricos anteriores a la implementaci&oacute;n de snapshots SUNAT. Algunos datos tributarios requieren regularizaci&oacute;n mediante auditor&iacute;a/backfill antes de considerar el registro definitivo.
                    <div class="small mt-1">
                        Movimientos afectados ({{ $report['incomplete_snapshot_count'] }}):
                        {{ collect($report['incomplete_movements'])->pluck('movement_number')->filter()->implode(', ') }}
                    </div>
                </div>
            @endif

            @if ($report['valuation_inconsistency_count'] > 0)
                <div class="alert alert-danger no-print">
                    <strong><i class="fas fa-balance-scale-right mr-1"></i> Inconsistencia de valorizaci&oacute;n detectada.</strong>
                    Existen saldos con cantidad final cero y valor residual. El reporte conserva el hist&oacute;rico sin corregirlo.
                    <div class="small mt-1">
                        Existencias afectadas ({{ $report['valuation_inconsistency_count'] }}):
                        @foreach ($report['valuation_inconsistencies'] as $inconsistency)
                            {{ $inconsistency['article_code'] ?: 'Artículo '.$inconsistency['article_id'] }}
                            (residuo {{ $inconsistency['residual_total_cost'] }})@if (! $loop->last), @endif
                        @endforeach
                    </div>
                </div>
            @endif

            @forelse ($report['registers'] as $register)
                <section class="valued-register-sheet">
                    <div class="sunat-title text-center">
                        <strong>FORMATO 13.1</strong><br>
                        REGISTRO DEL INVENTARIO PERMANENTE VALORIZADO<br>
                        <span>DETALLE DEL INVENTARIO VALORIZADO</span>
                    </div>

                    <table class="table table-sm register-meta mb-3">
                        <tbody>
                            <tr><th>PER&Iacute;ODO:</th><td>{{ $report['period'] }}</td><th>RUC:</th><td>{{ $report['company']->ruc }}</td></tr>
                            <tr><th>RAZ&Oacute;N SOCIAL:</th><td colspan="3">{{ $report['company']->business_name }}</td></tr>
                            <tr><th>ESTABLECIMIENTO:</th><td>{{ $register['establishment_code'] ?: '—' }}</td><th>C&Oacute;DIGO DE LA EXISTENCIA:</th><td>{{ $register['existence_code'] ?: '—' }}</td></tr>
                            <tr><th>TIPO (TABLA 5):</th><td>{{ $register['existence_type_code'] ?: '—' }}</td><th>DESCRIPCI&Oacute;N:</th><td>{{ $register['description'] ?: '—' }}</td></tr>
                            <tr><th>C&Oacute;DIGO UNIDAD MEDIDA (TABLA 6):</th><td>{{ $register['unit_code'] ?: '—' }}</td><th>M&Eacute;TODO DE VALUACI&Oacute;N (TABLA 14):</th><td>{{ collect([$register['valuation_method_code'], $register['valuation_method_description']])->filter()->implode(' — ') ?: '—' }}</td></tr>
                        </tbody>
                    </table>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm valued-detail mb-0">
                            <thead>
                                <tr>
                                    <th colspan="4">DOCUMENTO</th>
                                    <th rowspan="2">TIPO OPERACI&Oacute;N<br>TABLA 12</th>
                                    <th colspan="3">ENTRADAS</th>
                                    <th colspan="3">SALIDAS</th>
                                    <th colspan="3">SALDO FINAL</th>
                                </tr>
                                <tr>
                                    <th>FECHA</th>
                                    <th>TIPO<br>TABLA 10</th>
                                    <th>SERIE</th>
                                    <th>N&Uacute;MERO</th>
                                    <th>CANTIDAD</th>
                                    <th>COSTO UNITARIO</th>
                                    <th>COSTO TOTAL</th>
                                    <th>CANTIDAD</th>
                                    <th>COSTO UNITARIO</th>
                                    <th>COSTO TOTAL</th>
                                    <th>CANTIDAD</th>
                                    <th>COSTO UNITARIO</th>
                                    <th>COSTO TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($register['initial_quantity'] !== '0.0000' || $register['initial_total_cost'] !== '0.00')
                                    <tr class="opening-row">
                                        <td>{{ $report['period_start']->format('d/m/Y') }}</td>
                                        <td></td><td></td><td>SALDO INICIAL</td><td>SALDO INICIAL</td>
                                        <td class="number">0.0000</td><td></td><td class="number">0.00</td>
                                        <td class="number">0.0000</td><td></td><td class="number">0.00</td>
                                        <td class="number">{{ $register['initial_quantity'] }}</td>
                                        <td class="number">{{ $register['initial_average_unit_cost'] }}</td>
                                        <td class="number">{{ $register['initial_total_cost'] }}</td>
                                    </tr>
                                @endif
                                @foreach ($register['rows'] as $row)
                                    <tr>
                                        <td>{{ $row['document_date']?->format('d/m/Y') }}</td>
                                        <td>{{ $row['document_type_code'] }}</td>
                                        <td>{{ $row['document_series'] }}</td>
                                        <td>
                                            {{ $row['document_number'] }}
                                            @if (! $row['document_number'])
                                                <small class="internal-reference">Mov. {{ $row['movement_number'] }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $row['operation_type_code'] }}</td>
                                        <td class="number">{{ $row['quantity_in'] }}</td>
                                        <td class="number">{{ $row['entry_unit_cost'] ?? '' }}</td>
                                        <td class="number">{{ $row['total_cost_in'] }}</td>
                                        <td class="number">{{ $row['quantity_out'] }}</td>
                                        <td class="number">{{ $row['exit_unit_cost'] ?? '' }}</td>
                                        <td class="number">{{ $row['total_cost_out'] }}</td>
                                        <td class="number font-weight-bold">{{ $row['balance_quantity'] }}</td>
                                        <td class="number font-weight-bold">{{ $row['balance_average_unit_cost'] }}</td>
                                        <td class="number font-weight-bold">{{ $row['balance_total_cost'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-right">TOTALES</th>
                                    <th class="number">{{ $register['total_quantity_in'] }}</th>
                                    <th></th>
                                    <th class="number">{{ $register['total_cost_in'] }}</th>
                                    <th class="number">{{ $register['total_quantity_out'] }}</th>
                                    <th></th>
                                    <th class="number">{{ $register['total_cost_out'] }}</th>
                                    <th class="number">{{ $register['final_quantity'] }}</th>
                                    <th class="number">{{ $register['final_average_unit_cost'] }}</th>
                                    <th class="number">{{ $register['final_total_cost'] }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </section>
            @empty
                <div class="alert alert-info">No existen movimientos Kardex para los filtros seleccionados.</div>
            @endforelse
        @endif
    </div>
@stop

@push('css')
    <style>
        .valued-register { color: #273444; }
        .valued-register label { font-size: 11px; font-weight: 700; letter-spacing: .04em; }
        .valued-register-sheet { background: #fff; border: 1px solid #dfe5eb; border-radius: 8px; box-shadow: 0 3px 12px rgba(31, 45, 61, .07); margin-bottom: 24px; padding: 20px; }
        .sunat-title { font-size: 13px; letter-spacing: .025em; line-height: 1.45; margin-bottom: 16px; }
        .sunat-title strong { color: #26734d; font-size: 18px; }
        .sunat-title span { font-size: 11px; }
        .register-meta th { background: #f4f7f9; font-size: 10px; width: 20%; }
        .register-meta td { font-size: 11px; }
        .register-meta th, .register-meta td { border: 1px solid #d7dee5; padding: 5px 7px; }
        .valued-detail { min-width: 1320px; }
        .valued-detail th { background: #26734d; border-color: #398461; color: #fff; font-size: 9px; text-align: center; vertical-align: middle; }
        .valued-detail td { font-size: 9px; padding: 4px; vertical-align: middle; }
        .valued-detail .number { font-family: Consolas, monospace; text-align: right; white-space: nowrap; }
        .valued-detail .opening-row td { background: #edf6f1; color: #315a45; font-weight: 600; }
        .valued-detail tfoot th { background: #294b3a; }
        .internal-reference { color: #6c757d; display: block; white-space: nowrap; }

        @media print {
            @page { size: A4 landscape; margin: 7mm; }
            body { background: #fff !important; color: #000; font-family: Arial, sans-serif; }
            .no-print, .main-header, .main-sidebar, .main-footer, .content-header { display: none !important; }
            .content-wrapper, .content, .container-fluid { margin: 0 !important; padding: 0 !important; }
            .valued-register-sheet { border: 0; border-radius: 0; box-shadow: none; break-after: page; margin: 0; padding: 0; page-break-after: always; }
            .valued-register-sheet:last-child { break-after: auto; page-break-after: auto; }
            .valued-detail { min-width: 0; table-layout: fixed; width: 100%; }
            .valued-detail tr { break-inside: avoid; page-break-inside: avoid; }
            .valued-detail th { background: #e8ecef !important; border-color: #555 !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .register-meta th, .register-meta td, .valued-detail th, .valued-detail td { border-color: #555 !important; }
            .sunat-title { margin-top: 0; }
        }
    </style>
@endpush
