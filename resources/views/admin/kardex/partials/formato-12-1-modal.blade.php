@php($embedded = $embedded ?? false)

<div class="format12-interface {{ $embedded ? 'format12-embedded' : '' }}"
     data-format12-root
     data-source-url="{{ route('admin.kardex.formato-12-1') }}"
     data-articles-url="{{ route('admin.kardex.inventory-register.articles') }}">
    <div class="format12-filter-card no-print">
        <div class="format12-section-heading">
            <div>
                <span class="format12-section-kicker">Parámetros de consulta</span>
                <h6>Filtros del registro</h6>
            </div>
            <small>Selecciona empresa, período y almacén para construir el registro.</small>
        </div>

        <form method="GET" action="{{ route('admin.kardex.formato-12-1') }}" data-format12-form>
            <input type="hidden" name="consult" value="1">
            @if ($embedded)
                <input type="hidden" name="modal" value="1">
            @endif

            <div class="row align-items-end format12-filter-row">
                <div class="form-group col-sm-6 col-xl-3">
                    <label for="company_id">EMPRESA</label>
                    <select id="company_id" name="company_id" class="form-control form-control-sm" required>
                        <option value="">Seleccione</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((int) $companyId === (int) $company->id)>
                                {{ $company->business_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-6 col-sm-3 col-xl-1">
                    <label for="year">AÑO</label>
                    <input id="year" name="year" type="number" min="2000" max="2100" value="{{ $year }}" class="form-control form-control-sm" required>
                </div>
                <div class="form-group col-6 col-sm-3 col-xl-1">
                    <label for="month">MES</label>
                    <select id="month" name="month" class="form-control form-control-sm" required>
                        @foreach (range(1, 12) as $monthOption)
                            <option value="{{ $monthOption }}" @selected((int) $month === $monthOption)>
                                {{ str_pad((string) $monthOption, 2, '0', STR_PAD_LEFT) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-sm-6 col-xl-3">
                    <label for="warehouse_id">ALMACÉN</label>
                    <select id="warehouse_id" name="warehouse_id" class="form-control form-control-sm" required>
                        <option value="">Seleccione</option>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected((int) $warehouseId === (int) $warehouse->id)>
                                {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-sm-6 col-xl-4">
                    <label for="article_id">ARTÍCULO</label>
                    <select id="article_id" name="article_id" class="form-control form-control-sm">
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

            <div class="format12-validation d-none" role="alert" data-format12-validation></div>

            <div class="format12-actions">
                <button type="submit" class="btn btn-info btn-sm">
                    <i class="fas fa-search mr-1"></i> Consultar
                </button>
                @if ($embedded)
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-format12-clear>
                        <i class="fas fa-eraser mr-1"></i> Limpiar
                    </button>
                @else
                    <a href="{{ route('admin.kardex.formato-12-1') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-eraser mr-1"></i> Limpiar
                    </a>
                @endif
                @if ($report && (auth()->user()?->can('admin.kardex.export') ?? false))
                    <a href="{{ route('admin.kardex.formato-12-1.export', ['format' => 'excel', 'company_id' => $companyId, 'year' => $year, 'month' => $month, 'warehouse_id' => $warehouseId, 'article_id' => $articleId]) }}" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-file-excel mr-1"></i> Excel
                    </a>
                    <a href="{{ route('admin.kardex.formato-12-1.export', ['format' => 'pdf', 'company_id' => $companyId, 'year' => $year, 'month' => $month, 'warehouse_id' => $warehouseId, 'article_id' => $articleId]) }}" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-file-pdf mr-1"></i> PDF
                    </a>
                @endif
                <button type="button" class="btn btn-outline-dark btn-sm" data-format12-print @if (! $embedded) onclick="window.print()" @endif>
                    <i class="fas fa-print mr-1"></i> Imprimir
                </button>
            </div>
        </form>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger py-2 no-print">{{ $errors->first() }}</div>
    @endif

    <div class="format12-result" data-format12-result>
        @if ($report)
            @if ($report['incomplete_snapshot_count'] > 0)
                <div class="alert alert-warning py-2 no-print">
                    <strong><i class="fas fa-exclamation-triangle mr-1"></i> Información histórica incompleta.</strong>
                    Este período contiene movimientos anteriores a la implementación de snapshots SUNAT. Algunos datos tributarios requieren regularización antes de considerar el registro definitivo.
                    <div class="small mt-1">
                        Movimientos afectados ({{ $report['incomplete_snapshot_count'] }}):
                        {{ collect($report['incomplete_movements'])->pluck('movement_number')->filter()->implode(', ') }}
                    </div>
                </div>
            @endif

            <div class="format12-report">
                @forelse ($report['registers'] as $register)
                    <section class="register-sheet">
                        <div class="sunat-title text-center">
                            <strong>FORMATO 12.1</strong><br>
                            REGISTRO DEL INVENTARIO PERMANENTE EN UNIDADES FÍSICAS
                        </div>

                        <div class="table-responsive format12-meta-wrap">
                            <table class="table table-sm register-meta mb-3">
                                <tbody>
                                    <tr><th>PERÍODO:</th><td>{{ $report['period'] }}</td><th>RUC:</th><td>{{ $report['company']->ruc }}</td></tr>
                                    <tr><th>RAZÓN SOCIAL:</th><td colspan="3">{{ $report['company']->business_name }}</td></tr>
                                    <tr><th>ESTABLECIMIENTO:</th><td>{{ $register['establishment_code'] ?: '—' }}</td><th>CÓDIGO DE LA EXISTENCIA:</th><td>{{ $register['existence_code'] ?: '—' }}</td></tr>
                                    <tr><th>TIPO (TABLA 5):</th><td>{{ $register['existence_type_code'] ?: '—' }}</td><th>DESCRIPCIÓN:</th><td>{{ $register['description'] ?: '—' }}</td></tr>
                                    <tr><th>CÓDIGO UNIDAD MEDIDA (TABLA 6):</th><td colspan="3">{{ $register['unit_code'] ?: '—' }}</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="format12-order-note no-print">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            <span>Las filas se presentan según la fecha del movimiento Kardex. La fecha mostrada corresponde a la fecha de emisión del documento.</span>
                        </div>

                        <div class="table-responsive format12-detail-wrap">
                            <table class="table table-bordered table-sm register-detail mb-0">
                                <thead>
                                    <tr>
                                        <th colspan="4">DOCUMENTO DE TRASLADO / COMPROBANTE / DOCUMENTO INTERNO</th>
                                        <th rowspan="2">TIPO DE OPERACIÓN<br>TABLA 12</th>
                                        <th rowspan="2">ENTRADAS</th>
                                        <th rowspan="2">SALIDAS</th>
                                        <th rowspan="2">SALDO FINAL</th>
                                    </tr>
                                    <tr>
                                        <th title="Fecha de emisión del documento">FECHA EMISIÓN</th>
                                        <th>TIPO TABLA 10</th>
                                        <th>SERIE</th>
                                        <th>NÚMERO</th>
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
                    <div class="format12-empty-state">
                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        <h6>Sin movimientos en el período</h6>
                        <p>No existen movimientos Kardex para los filtros seleccionados.</p>
                    </div>
                @endforelse
            </div>
        @else
            <div class="format12-empty-state">
                <i class="fas fa-boxes" aria-hidden="true"></i>
                <h6>Consulta el registro</h6>
                <p>Selecciona empresa, período y almacén para visualizar el Formato 12.1.</p>
            </div>
        @endif
    </div>

    <style>
        .format12-interface { color: #273444; }
        .format12-filter-card { margin-bottom: 14px; padding: 14px 15px 12px; border: 1px solid #e3eaed; border-radius: 10px; background: #f9fbfb; }
        .format12-section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 11px; }
        .format12-section-heading h6 { margin: 1px 0 0; color: #26343d; font-size: 14px; font-weight: 800; }
        .format12-section-heading small { color: #748188; font-size: 11px; }
        .format12-section-kicker { color: #168276; font-size: 9px; font-weight: 800; letter-spacing: .55px; text-transform: uppercase; }
        .format12-filter-row { margin-right: -5px; margin-left: -5px; }
        .format12-filter-row > [class*="col-"] { padding-right: 5px; padding-left: 5px; }
        .format12-interface .form-group { margin-bottom: 9px; }
        .format12-interface label { margin-bottom: 4px; color: #65727a; font-size: 9.5px; font-weight: 800; letter-spacing: .35px; }
        .format12-interface .form-control { height: 31px; border-color: #dbe5e7; border-radius: 7px; font-size: 11.5px; box-shadow: none; }
        .format12-interface .select2-container .select2-selection--single { height: 31px; border-color: #dbe5e7; border-radius: 7px; font-size: 11.5px; }
        .format12-interface .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 29px; }
        .format12-interface .select2-container--default .select2-selection--single .select2-selection__arrow { height: 29px; }
        .format12-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 6px; padding-top: 2px; }
        .format12-actions .btn { min-width: 82px; border-radius: 7px; font-size: 11.5px; font-weight: 700; }
        .format12-validation { margin: 0 0 8px; padding: 7px 9px; border: 1px solid #f0c9c6; border-radius: 7px; background: #fff4f3; color: #a13e39; font-size: 11.5px; }
        .format12-result { min-height: 190px; overflow-x: hidden; }
        .format12-empty-state { display: flex; min-height: 190px; align-items: center; justify-content: center; flex-direction: column; padding: 28px 18px; border: 1px dashed #d8e3e5; border-radius: 10px; background: #fbfcfc; text-align: center; }
        .format12-empty-state i { margin-bottom: 10px; color: #7ea49f; font-size: 26px; }
        .format12-empty-state h6 { margin-bottom: 5px; color: #304048; font-size: 14px; font-weight: 800; }
        .format12-empty-state p { max-width: 480px; margin: 0; color: #78858c; font-size: 11.5px; }
        .register-sheet { margin-bottom: 15px; padding: 18px; border: 1px solid #dfe7e9; border-radius: 10px; background: #fff; box-shadow: 0 3px 12px rgba(31, 45, 61, .05); }
        .register-sheet:last-child { margin-bottom: 0; }
        .sunat-title { margin-bottom: 14px; color: #394950; font-size: 12px; letter-spacing: .025em; line-height: 1.5; }
        .sunat-title strong { color: #176b72; font-size: 16px; }
        .format12-meta-wrap, .format12-detail-wrap { width: 100%; overflow-x: auto; }
        .format12-order-note { display: flex; align-items: center; gap: 6px; margin-bottom: 7px; padding: 6px 8px; border: 1px solid #e1e9eb; border-radius: 6px; background: #f8fafb; color: #66757d; font-size: 10.5px; line-height: 1.35; }
        .format12-order-note i { color: #47828a; flex: 0 0 auto; }
        .register-meta { min-width: 760px; }
        .register-meta th { width: 20%; background: #f3f6f7; font-size: 10px; }
        .register-meta td { font-size: 11px; }
        .register-meta th, .register-meta td { padding: 6px 8px; border: 1px solid #d7e0e3; }
        .register-detail { width: 100%; min-width: 880px; }
        .register-detail th { padding: 7px 6px; border-color: #2c747a; background: #276f75; color: #fff; font-size: 9.5px; text-align: center; vertical-align: middle; }
        .register-detail td { padding: 5px 6px; font-size: 10.5px; vertical-align: middle; }
        .register-detail .number { font-family: Consolas, monospace; text-align: right; white-space: nowrap; }
        .register-detail .opening-row td { background: #eef6f7; color: #31575b; font-weight: 600; }
        .register-detail tfoot th { background: #263f44; }
        .internal-reference { display: block; color: #6c757d; white-space: nowrap; }
        .format12-modal-loading { display: flex; min-height: 260px; align-items: center; justify-content: center; flex-direction: column; color: #64747b; font-size: 12px; }
        .format12-modal-loading .spinner-border { width: 1.5rem; height: 1.5rem; margin-bottom: 10px; color: #178579; }

        @media (max-width: 767.98px) {
            .format12-section-heading { align-items: flex-start; flex-direction: column; gap: 3px; }
            .format12-actions { justify-content: flex-start; }
            .register-sheet { padding: 12px; }
        }

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background: #fff !important; color: #000; font-family: Arial, sans-serif; }
            .no-print, .main-header, .main-sidebar, .main-footer, .content-header, .modal-header, .modal-footer { display: none !important; }
            .content-wrapper, .content, .container-fluid { margin: 0 !important; padding: 0 !important; }
            .register-sheet { margin: 0; padding: 0; border: 0; border-radius: 0; box-shadow: none; break-after: page; page-break-after: always; }
            .register-sheet:last-child { break-after: auto; page-break-after: auto; }
            .register-detail { width: 100%; min-width: 0; }
            .register-detail tr { break-inside: avoid; page-break-inside: avoid; }
            .register-detail th { border-color: #555 !important; background: #e8ecef !important; color: #000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .register-meta th, .register-meta td, .register-detail th, .register-detail td { border-color: #555 !important; }
            .sunat-title { margin-top: 0; }
            body.format12-modal-printing * { visibility: hidden !important; }
            body.format12-modal-printing #format12Modal,
            body.format12-modal-printing #format12Modal .format12-report,
            body.format12-modal-printing #format12Modal .format12-report * { visibility: visible !important; }
            body.format12-modal-printing #format12Modal { position: absolute !important; inset: 0 !important; display: block !important; width: 100% !important; height: auto !important; overflow: visible !important; }
            body.format12-modal-printing #format12Modal .modal-dialog,
            body.format12-modal-printing #format12Modal .modal-content,
            body.format12-modal-printing #format12Modal .modal-body { width: 100% !important; max-width: none !important; height: auto !important; max-height: none !important; margin: 0 !important; padding: 0 !important; overflow: visible !important; border: 0 !important; box-shadow: none !important; }
        }
    </style>
</div>
