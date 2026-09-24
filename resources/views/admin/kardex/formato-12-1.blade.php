@extends('layouts.app')

@section('subtitle', 'Formato 12.1 - Unidades Físicas')

@section('header')
    <div class="container-fluid no-print">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="h3 mb-1 font-weight-bold text-dark">Formato 12.1 &mdash; Registro de Inventario Permanente en Unidades F&iacute;sicas</h1>
                <small class="text-muted">Registro mensual construido exclusivamente desde los movimientos hist&oacute;ricos del Kardex</small>
            </div>
            <a href="{{ route('admin.kardex.index') }}" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
                <i class="fas fa-arrow-left mr-1"></i> Volver al Kardex
            </a>
        </div>
    </div>
@stop

@section('content_body')
    <div class="container-fluid physical-register">
        <div class="card border-0 shadow-sm mb-4 no-print">
            <div class="card-header border-0 bg-white">
                <h5 class="mb-0 font-weight-bold"><i class="fas fa-filter text-info mr-1"></i> Filtros del registro</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('admin.kardex.formato-12-1') }}">
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
                            <select id="article_id" name="article_id" class="form-control"
                                    data-articles-url="{{ route('admin.kardex.inventory-register.articles') }}">
                                <option value="">Todos</option>
                                @foreach ($articles as $article)
                                    <option value="{{ $article->article_id }}" @selected((int) $articleId === (int) $article->article_id)>
                                        {{ $article->article_code_snapshot }} | {{ $article->article_description_snapshot }}
                                    </option>
                                @endforeach
                                @if ($articles->isEmpty())
                                    <option value="" disabled>
                                        {{ $companyId && $warehouseId ? 'Sin artículos con movimientos o saldo inicial en el período' : 'Seleccione empresa y almacén' }}
                                    </option>
                                @endif
                            </select>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end flex-wrap">
                        <button type="submit" class="btn btn-info mr-2 mb-2"><i class="fas fa-search mr-1"></i> Consultar</button>
                        <a href="{{ route('admin.kardex.formato-12-1') }}" class="btn btn-outline-secondary mr-2 mb-2"><i class="fas fa-eraser mr-1"></i> Limpiar</a>
                        @if ($report && (auth()->user()?->can('admin.kardex.export') ?? false))
                            <a href="{{ route('admin.kardex.formato-12-1.export', ['format' => 'excel', 'company_id' => $companyId, 'year' => $year, 'month' => $month, 'warehouse_id' => $warehouseId, 'article_id' => $articleId]) }}" class="btn btn-outline-success mr-2 mb-2">
                                <i class="fas fa-file-excel mr-1"></i> Excel
                            </a>
                            <a href="{{ route('admin.kardex.formato-12-1.export', ['format' => 'pdf', 'company_id' => $companyId, 'year' => $year, 'month' => $month, 'warehouse_id' => $warehouseId, 'article_id' => $articleId]) }}" class="btn btn-outline-danger mr-2 mb-2">
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
                    Este per&iacute;odo contiene movimientos anteriores a la implementaci&oacute;n de snapshots SUNAT. Algunos datos tributarios requieren regularizaci&oacute;n mediante el proceso de auditor&iacute;a/backfill antes de considerar el registro definitivo.
                    <div class="small mt-1">
                        Movimientos afectados ({{ $report['incomplete_snapshot_count'] }}):
                        {{ collect($report['incomplete_movements'])->pluck('movement_number')->filter()->implode(', ') }}
                    </div>
                </div>
            @endif

            @forelse ($report['registers'] as $register)
                <section class="register-sheet">
                    <div class="sunat-title text-center">
                        <strong>FORMATO 12.1</strong><br>
                        REGISTRO DEL INVENTARIO PERMANENTE EN UNIDADES F&Iacute;SICAS
                    </div>

                    <table class="table table-sm register-meta mb-3">
                        <tbody>
                            <tr><th>PER&Iacute;ODO:</th><td>{{ $report['period'] }}</td><th>RUC:</th><td>{{ $report['company']->ruc }}</td></tr>
                            <tr><th>RAZ&Oacute;N SOCIAL:</th><td colspan="3">{{ $report['company']->business_name }}</td></tr>
                            <tr><th>ESTABLECIMIENTO:</th><td>{{ $register['establishment_code'] ?: '—' }}</td><th>C&Oacute;DIGO DE LA EXISTENCIA:</th><td>{{ $register['existence_code'] ?: '—' }}</td></tr>
                            <tr><th>TIPO (TABLA 5):</th><td>{{ $register['existence_type_code'] ?: '—' }}</td><th>DESCRIPCI&Oacute;N:</th><td>{{ $register['description'] ?: '—' }}</td></tr>
                            <tr><th>C&Oacute;DIGO UNIDAD MEDIDA (TABLA 6):</th><td colspan="3">{{ $register['unit_code'] ?: '—' }}</td></tr>
                        </tbody>
                    </table>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm register-detail mb-0">
                            <thead>
                                <tr>
                                    <th colspan="4">DOCUMENTO DE TRASLADO / COMPROBANTE / DOCUMENTO INTERNO</th>
                                    <th rowspan="2">TIPO DE OPERACI&Oacute;N<br>TABLA 12</th>
                                    <th rowspan="2">ENTRADAS</th>
                                    <th rowspan="2">SALIDAS</th>
                                    <th rowspan="2">SALDO FINAL</th>
                                </tr>
                                <tr>
                                    <th>FECHA</th>
                                    <th>TIPO TABLA 10</th>
                                    <th>SERIE</th>
                                    <th>N&Uacute;MERO</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($register['opening_balance'] !== '0.0000')
                                    <tr class="opening-row">
                                        <td>{{ $report['period_start']->format('d/m/Y') }}</td>
                                        <td></td>
                                        <td></td>
                                        <td>SALDO INICIAL</td>
                                        <td>SALDO INICIAL</td>
                                        <td class="number">0.0000</td>
                                        <td class="number">0.0000</td>
                                        <td class="number">{{ $register['opening_balance'] }}</td>
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
                                        <td class="number">{{ $row['quantity_out'] }}</td>
                                        <td class="number font-weight-bold">{{ $row['balance'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-right">TOTALES</th>
                                    <th class="number">{{ $register['total_entries'] }}</th>
                                    <th class="number">{{ $register['total_exits'] }}</th>
                                    <th class="number">{{ $register['final_balance'] }}</th>
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
        .physical-register { color: #273444; }
        .physical-register label { font-size: 11px; font-weight: 700; letter-spacing: .04em; }
        .register-sheet { background: #fff; border: 1px solid #dfe5eb; border-radius: 8px; box-shadow: 0 3px 12px rgba(31, 45, 61, .07); margin-bottom: 24px; padding: 22px; }
        .sunat-title { font-size: 14px; letter-spacing: .025em; line-height: 1.55; margin-bottom: 18px; }
        .sunat-title strong { font-size: 18px; color: #176b72; }
        .register-meta th { background: #f4f7f9; font-size: 11px; width: 20%; }
        .register-meta td { font-size: 12px; }
        .register-meta th, .register-meta td { border: 1px solid #d7dee5; padding: 6px 8px; }
        .register-detail { min-width: 880px; }
        .register-detail th { background: #176b72; border-color: #2c7d83; color: #fff; font-size: 10px; text-align: center; vertical-align: middle; }
        .register-detail td { font-size: 11px; padding: 5px 6px; vertical-align: middle; }
        .register-detail .number { font-family: Consolas, monospace; text-align: right; white-space: nowrap; }
        .register-detail .opening-row td { background: #eef6f7; color: #31575b; font-weight: 600; }
        .register-detail tfoot th { background: #263f44; }
        .internal-reference { color: #6c757d; display: block; white-space: nowrap; }

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff !important; color: #000; font-family: Arial, sans-serif; }
            .no-print, .main-header, .main-sidebar, .main-footer, .content-header { display: none !important; }
            .content-wrapper, .content, .container-fluid { margin: 0 !important; padding: 0 !important; }
            .register-sheet { border: 0; border-radius: 0; box-shadow: none; break-after: page; margin: 0; padding: 0; page-break-after: always; }
            .register-sheet:last-child { break-after: auto; page-break-after: auto; }
            .register-detail { min-width: 0; width: 100%; }
            .register-detail tr { break-inside: avoid; page-break-inside: avoid; }
            .register-detail th { background: #e8ecef !important; border-color: #555 !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .register-meta th, .register-meta td, .register-detail th, .register-detail td { border-color: #555 !important; }
            .sunat-title { margin-top: 0; }
        }
    </style>
@endpush

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const company = document.getElementById('company_id');
            const year = document.getElementById('year');
            const month = document.getElementById('month');
            const warehouse = document.getElementById('warehouse_id');
            const article = document.getElementById('article_id');

            if (!company || !year || !month || !warehouse || !article) {
                return;
            }

            let requestController = null;
            const initialArticleId = String(@json($articleId ?: ''));

            const resetArticles = (message = null, selectedId = '') => {
                article.innerHTML = '';

                const allOption = new Option('Todos', '', false, !selectedId);
                article.add(allOption);

                if (message) {
                    const messageOption = new Option(message, '', false, false);
                    messageOption.disabled = true;
                    article.add(messageOption);
                }
            };

            const loadArticles = async (preferredArticleId = '') => {
                const companyId = company.value;
                const warehouseId = warehouse.value;
                const yearValue = year.value;
                const monthValue = month.value;

                if (!companyId || !warehouseId || !yearValue || !monthValue) {
                    resetArticles('Seleccione empresa y almacén');
                    article.disabled = false;
                    return;
                }

                requestController?.abort();
                requestController = new AbortController();

                const currentValue = preferredArticleId || article.value || '';
                resetArticles('Cargando artículos...', currentValue);
                article.disabled = true;

                const params = new URLSearchParams({
                    company_id: companyId,
                    warehouse_id: warehouseId,
                    year: yearValue,
                    month: monthValue,
                });

                try {
                    const response = await fetch(`${article.dataset.articlesUrl}?${params.toString()}`, {
                        headers: { 'Accept': 'application/json' },
                        signal: requestController.signal,
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const payload = await response.json();
                    const articles = Array.isArray(payload.articles) ? payload.articles : [];

                    resetArticles(
                        articles.length ? null : 'Sin artículos con movimientos o saldo inicial en el período',
                        currentValue
                    );

                    articles.forEach((item) => {
                        const id = String(item.id);
                        article.add(new Option(item.label, id, false, id === currentValue));
                    });

                    if (currentValue && !articles.some((item) => String(item.id) === currentValue)) {
                        article.value = '';
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        resetArticles('No se pudieron cargar los artículos');
                    }
                } finally {
                    article.disabled = false;
                }
            };

            [company, year, month, warehouse].forEach((control) => {
                control.addEventListener('change', () => loadArticles(''));
            });

            if (company.value && warehouse.value) {
                loadArticles(initialArticleId);
            }
        });
    </script>
@endpush
