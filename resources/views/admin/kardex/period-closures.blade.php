@extends('layouts.app')

@section('subtitle', 'Cierre mensual de inventario')

@section('header')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
        <div>
            <h1 class="mb-1 font-weight-bold text-dark">
                <i class="fas fa-lock text-warning mr-2"></i>Cierre mensual de inventario
            </h1>
            <small class="text-muted">Control de períodos cerrados y trazabilidad de cierres/reaperturas.</small>
        </div>
        <a href="{{ route('admin.kardex.index') }}" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i> Volver al Kardex
        </a>
    </div>
</div>
@stop

@section('content_body')
<div class="container-fluid">
    @if (session('success'))
        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm">
            <strong>No se pudo completar la operación.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header border-0 bg-white">
            <h5 class="mb-1 font-weight-bold"><i class="fas fa-calendar-alt text-info mr-1"></i> Período</h5>
            <small class="text-muted">Seleccione empresa, almacén y mes para consultar su estado.</small>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.kardex.period-closures') }}">
                <div class="row">
                    <div class="form-group col-md-4">
                        <label>EMPRESA</label>
                        <select name="company_id" class="form-control" required onchange="this.form.querySelector('[name=warehouse_id]').value=''; this.form.submit()">
                            <option value="">Seleccione</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected((int) $companyId === (int) $company->id)>
                                    {{ $company->business_name }} — {{ $company->ruc }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>ALMACÉN</label>
                        <select name="warehouse_id" class="form-control" required>
                            <option value="">Seleccione</option>
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((int) $warehouseId === (int) $warehouse->id)>
                                    {{ $warehouse->code }} — {{ $warehouse->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-2">
                        <label>AÑO</label>
                        <input type="number" min="2000" max="2100" name="year" value="{{ $year }}" class="form-control" required>
                    </div>
                    <div class="form-group col-md-2">
                        <label>MES</label>
                        <select name="month" class="form-control" required>
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" @selected((int) $month === $m)>{{ str_pad((string) $m, 2, '0', STR_PAD_LEFT) }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
                <button class="btn btn-info btn-sm"><i class="fas fa-search mr-1"></i> Consultar estado</button>
            </form>
        </div>
    </div>

    @if ($companyId && $warehouseId)
        @php
            $closed = $currentState?->action === \App\Models\WarehouseInventoryPeriodClosure::ACTION_CLOSE;
            $periodEndExclusive = \Carbon\CarbonImmutable::create($year, $month, 1)->addMonth();
            $periodEnded = now()->gte($periodEndExclusive);
        @endphp

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-start flex-wrap">
                <div>
                    <div class="text-muted small">ESTADO {{ str_pad((string) $month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</div>
                    @if ($closed)
                        <h4 class="mb-1 text-danger"><i class="fas fa-lock mr-1"></i> CERRADO</h4>
                        <small class="text-muted">Último cierre: {{ $currentState->created_at?->format('d/m/Y H:i') }} por {{ trim(($currentState->creator?->name ?? '').' '.($currentState->creator?->lastname ?? '')) ?: 'usuario no disponible' }}</small>
                    @else
                        <h4 class="mb-1 text-success"><i class="fas fa-lock-open mr-1"></i> ABIERTO</h4>
                        @if (! $periodEnded)
                            <small class="text-warning">El mes aún no ha finalizado; no puede cerrarse todavía.</small>
                        @endif
                    @endif
                </div>

                @can('admin.kardex.recalculate')
                    <div class="mt-3 mt-md-0" style="min-width:320px;max-width:520px;width:100%">
                        @if ($closed)
                            <form method="POST" action="{{ route('admin.kardex.period-reopen') }}">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $companyId }}">
                                <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
                                <input type="hidden" name="year" value="{{ $year }}">
                                <input type="hidden" name="month" value="{{ $month }}">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold">MOTIVO DE REAPERTURA</label>
                                    <textarea name="reason" class="form-control form-control-sm" rows="2" minlength="5" maxlength="2000" required></textarea>
                                </div>
                                <button class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Reabrir este período? Las nuevas correcciones quedarán permitidas hasta volver a cerrarlo.')">
                                    <i class="fas fa-lock-open mr-1"></i> Reabrir período
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.kardex.period-close') }}">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $companyId }}">
                                <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
                                <input type="hidden" name="year" value="{{ $year }}">
                                <input type="hidden" name="month" value="{{ $month }}">
                                <div class="form-group mb-2">
                                    <label class="small font-weight-bold">OBSERVACIÓN DEL CIERRE</label>
                                    <textarea name="reason" class="form-control form-control-sm" rows="2" maxlength="2000" placeholder="Opcional"></textarea>
                                </div>
                                <button class="btn btn-danger btn-sm" @disabled(! $periodEnded) onclick="return confirm('¿Cerrar definitivamente este período? Los movimientos del mes quedarán protegidos hasta una reapertura autorizada.')">
                                    <i class="fas fa-lock mr-1"></i> Cerrar período
                                </button>
                            </form>
                        @endif
                    </div>
                @endcan
            </div>
        </div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header border-0 bg-white">
            <h5 class="mb-1 font-weight-bold"><i class="fas fa-history text-secondary mr-1"></i> Historial de control</h5>
            <small class="text-muted">Registro inmutable de cierres y reaperturas.</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Fecha</th><th>Empresa</th><th>Almacén</th><th>Período</th><th>Acción</th><th>Usuario</th><th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td>{{ $event->created_at?->format('d/m/Y H:i') }}</td>
                            <td>{{ $event->company?->business_name ?? '-' }}</td>
                            <td>{{ $event->warehouse?->name ?? '-' }}</td>
                            <td>{{ str_pad((string) $event->month, 2, '0', STR_PAD_LEFT) }}/{{ $event->year }}</td>
                            <td>
                                @if ($event->action === \App\Models\WarehouseInventoryPeriodClosure::ACTION_CLOSE)
                                    <span class="badge badge-danger"><i class="fas fa-lock mr-1"></i>Cierre</span>
                                @else
                                    <span class="badge badge-success"><i class="fas fa-lock-open mr-1"></i>Reapertura</span>
                                @endif
                            </td>
                            <td>{{ trim(($event->creator?->name ?? '').' '.($event->creator?->lastname ?? '')) ?: '-' }}</td>
                            <td>{{ $event->reason ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Sin eventos de cierre registrados.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@stop
