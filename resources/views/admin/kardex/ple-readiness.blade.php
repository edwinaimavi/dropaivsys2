@extends('layouts.app')

@section('subtitle', 'PLE / Exportaci&oacute;n electr&oacute;nica')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-file-code text-primary mr-2"></i>
                    PLE / Exportaci&oacute;n electr&oacute;nica
                </h1>
                <small class="text-muted">Prevalidaci&oacute;n SUNAT de los registros 12.1 y 13.1 sin inventar referencias contables.</small>
            </div>
            <div class="d-flex flex-wrap mt-2 mt-md-0">
                <a href="{{ route('admin.kardex.accounting', request()->only(['company_id', 'warehouse_id', 'year', 'month'])) }}" class="btn btn-outline-primary btn-sm mr-2">
                    <i class="fas fa-balance-scale mr-1"></i> Contabilidad de inventario
                </a>
                <a href="{{ route('admin.kardex.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Volver al Kardex
                </a>
            </div>
        </div>
    </div>
@stop

@section('content_body')
<div class="container-fluid">
    <div class="alert alert-info shadow-sm border-0">
        <div class="d-flex align-items-start">
            <i class="fas fa-info-circle fa-lg mr-3 mt-1"></i>
            <div>
                <strong>Control previo al TXT oficial</strong>
                <div class="small mt-1">
                    Los registros 12.1 y 13.1 del PLE exigen CUO y correlativo del asiento contable. DROPAIVSYS2 no generar&aacute; valores ficticios.
                    Esta pantalla identifica exactamente qu&eacute; falta antes de habilitar el archivo oficial.
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0">
            <h5 class="mb-1 font-weight-bold"><i class="fas fa-filter text-primary mr-1"></i> Per&iacute;odo a evaluar</h5>
            <small class="text-muted">El per&iacute;odo debe estar cerrado para considerarse candidato a exportaci&oacute;n definitiva.</small>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.kardex.ple') }}">
                <input type="hidden" name="consult" value="1">
                <div class="row">
                    <div class="form-group col-lg-4 col-md-6">
                        <label class="font-weight-bold small">EMPRESA</label>
                        <select name="company_id" class="form-control form-control-sm" required>
                            <option value="">Seleccione...</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected((int) $companyId === (int) $company->id)>
                                    {{ $company->ruc }} | {{ $company->business_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-lg-4 col-md-6">
                        <label class="font-weight-bold small">ALMAC&Eacute;N</label>
                        <select name="warehouse_id" class="form-control form-control-sm" required>
                            <option value="">Seleccione...</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((int) $warehouseId === (int) $warehouse->id)>
                                    {{ $warehouse->code }} | {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-lg-2 col-md-3">
                        <label class="font-weight-bold small">A&Ntilde;O</label>
                        <input type="number" name="year" value="{{ $year }}" min="2000" max="2100" class="form-control form-control-sm" required>
                    </div>
                    <div class="form-group col-lg-2 col-md-3">
                        <label class="font-weight-bold small">MES</label>
                        <select name="month" class="form-control form-control-sm" required>
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((int) $month === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary btn-sm">
                    <i class="fas fa-search mr-1"></i> Evaluar preparaci&oacute;n PLE
                </button>
            </form>
        </div>
    </div>

    @if ($report)
        <div class="row mb-3">
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">PER&Iacute;ODO</div>
                        <div class="h4 mb-1">{{ str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT) }}/{{ $report['year'] }}</div>
                        <span class="badge badge-{{ $report['period_closed'] ? 'success' : 'warning' }}">
                            {{ $report['period_closed'] ? 'CERRADO' : 'ABIERTO' }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">MOVIMIENTOS DEL MES</div>
                        <div class="h4 mb-1">{{ number_format($report['movement_count']) }}</div>
                        <small class="text-muted">Contenido: {{ $report['has_content'] ? 'S&iacute;' : 'No' }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3 mb-md-0">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">BLOQUEOS</div>
                        <div class="h4 mb-1 text-{{ $report['blocker_count'] ? 'danger' : 'success' }}">{{ $report['blocker_count'] }}</div>
                        <small class="text-muted">Deben quedar en cero para TXT oficial.</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">ESTADO PLE</div>
                        @if ($report['can_generate_official_txt'])
                            <div class="h5 text-success mb-1"><i class="fas fa-check-circle mr-1"></i> LISTO</div>
                        @else
                            <div class="h5 text-danger mb-1"><i class="fas fa-ban mr-1"></i> BLOQUEADO</div>
                        @endif
                        <small class="text-muted">No se generan CUO ni asientos artificiales.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0">
                <h5 class="mb-1 font-weight-bold"><i class="fas fa-file-alt text-info mr-1"></i> Archivos esperados por SUNAT</h5>
                <small class="text-muted">Nombres construidos con RUC, per&iacute;odo, c&oacute;digo del libro e indicadores PLE.</small>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6 mb-3 mb-lg-0">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>12.1 &mdash; Inventario Permanente en Unidades F&iacute;sicas</strong>
                                <span class="badge badge-info">{{ $report['physical']['book_code'] }}</span>
                            </div>
                            <code class="d-block text-break">{{ $report['physical']['filename'] }}</code>
                            <div class="small text-muted mt-2">
                                Registros: {{ $report['physical']['register_count'] }} |
                                Snapshots incompletos: {{ $report['physical']['incomplete_snapshot_count'] }} |
                                Precisi&oacute;n &gt; 2 decimales: {{ $report['physical']['precision_issue_count'] }}
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>13.1 &mdash; Inventario Permanente Valorizado</strong>
                                <span class="badge badge-success">{{ $report['valued']['book_code'] }}</span>
                            </div>
                            <code class="d-block text-break">{{ $report['valued']['filename'] }}</code>
                            <div class="small text-muted mt-2">
                                Registros: {{ $report['valued']['register_count'] }} |
                                Snapshots incompletos: {{ $report['valued']['incomplete_snapshot_count'] }} |
                                Inconsistencias: {{ $report['valued']['valuation_inconsistency_count'] }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if ($report['blockers'])
            <div class="card border-danger shadow-sm mb-3">
                <div class="card-header bg-danger text-white border-0">
                    <strong><i class="fas fa-exclamation-triangle mr-1"></i> Bloqueos para el TXT oficial</strong>
                </div>
                <div class="card-body">
                    @foreach ($report['blockers'] as $blocker)
                        <div class="border-bottom pb-2 mb-2">
                            <div class="font-weight-bold text-danger">{{ $blocker['code'] }}</div>
                            <div>{{ $blocker['message'] }}</div>
                            @if (! empty($blocker['meta']))
                                <small class="text-muted">{{ collect($blocker['meta'])->map(fn ($value, $key) => $key.': '.$value)->implode(' | ') }}</small>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($report['warnings'])
            <div class="card border-warning shadow-sm mb-3">
                <div class="card-header bg-warning border-0">
                    <strong><i class="fas fa-shield-alt mr-1"></i> Advertencias de trazabilidad</strong>
                </div>
                <div class="card-body">
                    @foreach ($report['warnings'] as $warning)
                        <div class="border-bottom pb-2 mb-2">
                            <div class="font-weight-bold">{{ $warning['code'] }}</div>
                            <div>{{ $warning['message'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="alert alert-secondary border-0 shadow-sm mb-0">
            <strong>Conclusi&oacute;n t&eacute;cnica:</strong>
            @if ($report['can_generate_official_txt'])
                el per&iacute;odo no presenta bloqueos de prevalidaci&oacute;n. La generaci&oacute;n final debe mantener exactamente la estructura PLE aplicable.
            @else
                no se habilita un TXT que pueda confundirse con un archivo listo para SUNAT. Los bloqueos mostrados deben resolverse con datos reales, especialmente la referencia al Libro Diario cuando corresponda.
            @endif
        </div>
    @endif
</div>
@stop
