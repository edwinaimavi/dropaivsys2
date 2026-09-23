@extends('layouts.app')

@section('subtitle', 'Contabilidad de inventario')

@section('header')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
            <div>
                <h1 class="mb-1 font-weight-bold text-dark">
                    <i class="fas fa-balance-scale text-primary mr-2"></i>
                    Contabilidad de inventario
                </h1>
                <small class="text-muted">Base contable mínima para generar CUO y correlativo reales desde movimientos del Kardex.</small>
            </div>
            <div class="d-flex flex-wrap mt-2 mt-md-0">
                <a href="{{ route('admin.kardex.ple') }}" class="btn btn-outline-primary btn-sm mr-2">
                    <i class="fas fa-file-code mr-1"></i> PLE
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
    <div class="alert alert-warning border-0 shadow-sm">
        <strong><i class="fas fa-shield-alt mr-1"></i> Control contable:</strong>
        el sistema no asigna cuentas PCGE por su cuenta. Las cuentas y sus roles deben ser definidos con criterio contable antes de contabilizar un período.
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white border-0">
            <h5 class="mb-1 font-weight-bold"><i class="fas fa-building text-primary mr-1"></i> Empresa y período</h5>
            <small class="text-muted">La contabilización definitiva solo se permite sobre períodos de inventario cerrados.</small>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.kardex.accounting') }}">
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
                        <label class="font-weight-bold small">ALMACÉN</label>
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
                        <label class="font-weight-bold small">AÑO</label>
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
                    <i class="fas fa-search mr-1"></i> Auditar período
                </button>
            </form>
        </div>
    </div>

    @if ($companyId)
        <div class="row">
            <div class="col-lg-5 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-1 font-weight-bold"><i class="fas fa-list-ol text-info mr-1"></i> Cuentas disponibles</h5>
                        <small class="text-muted">Solo se registran códigos informados por el responsable contable.</small>
                    </div>
                    <div class="card-body">
                        @can('admin.kardex.recalculate')
                            <form method="POST" action="{{ route('admin.kardex.accounting.accounts') }}" class="mb-3">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $companyId }}">
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label class="small font-weight-bold">Código</label>
                                        <input name="code" class="form-control form-control-sm" maxlength="30" required placeholder="Ej. código PCGE">
                                    </div>
                                    <div class="form-group col-md-8">
                                        <label class="small font-weight-bold">Nombre</label>
                                        <input name="name" class="form-control form-control-sm" maxlength="180" required placeholder="Nombre de la cuenta">
                                    </div>
                                </div>
                                <button class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-plus mr-1"></i> Registrar cuenta
                                </button>
                            </form>
                        @endcan

                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Cuenta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($configuration['accounts'] as $account)
                                        <tr>
                                            <td><code>{{ $account->code }}</code></td>
                                            <td>{{ $account->name }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="2" class="text-muted text-center">Todavía no hay cuentas registradas.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-1 font-weight-bold"><i class="fas fa-project-diagram text-success mr-1"></i> Mapeo contable del inventario</h5>
                        <small class="text-muted">La cuenta de existencias y la contrapartida se toman de este mapeo; nunca se infieren del código.</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.kardex.accounting.settings') }}">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $companyId }}">

                            <div class="row">
                                @foreach ($configuration['roles'] as $field => $label)
                                    <div class="form-group col-md-6">
                                        <label class="small font-weight-bold">{{ $label }}</label>
                                        <select name="{{ $field }}" class="form-control form-control-sm">
                                            <option value="">Sin configurar</option>
                                            @foreach ($configuration['accounts'] as $account)
                                                <option value="{{ $account->id }}" @selected((int) old($field, $configuration['settings']?->{$field}) === (int) $account->id)>
                                                    {{ $account->code }} | {{ $account->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>

                            @can('admin.kardex.recalculate')
                                <button class="btn btn-success btn-sm">
                                    <i class="fas fa-save mr-1"></i> Guardar mapeo
                                </button>
                            @endcan
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($report)
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h5 class="mb-1 font-weight-bold"><i class="fas fa-clipboard-check text-primary mr-1"></i> Auditoría contable del período</h5>
                    <small class="text-muted">{{ str_pad((string) $report['month'], 2, '0', STR_PAD_LEFT) }}/{{ $report['year'] }}</small>
                </div>
                <div>
                    <span class="badge badge-secondary p-2">Movimientos: {{ $report['movement_count'] }}</span>
                    <span class="badge badge-success p-2">Contabilizados: {{ $report['posted_count'] }}</span>
                    <span class="badge badge-info p-2">Pendientes: {{ $report['postable_count'] }}</span>
                </div>
            </div>
            <div class="card-body">
                @if ($report['blockers'])
                    <div class="alert alert-danger">
                        <strong>No se puede contabilizar todavía.</strong>
                        @foreach ($report['blockers'] as $blocker)
                            <div class="mt-2">
                                <code>{{ $blocker['code'] }}</code> — {{ $blocker['message'] }}
                                @if (! empty($blocker['meta']))
                                    <div class="small">{{ collect($blocker['meta'])->map(fn ($value, $key) => $key.': '.$value)->implode(' | ') }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle mr-1"></i>
                        El período no presenta bloqueos contables para los movimientos de inventario detectados.
                    </div>
                @endif

                @foreach ($report['warnings'] as $warning)
                    <div class="alert alert-warning py-2">
                        <code>{{ $warning['code'] }}</code> — {{ $warning['message'] }}
                    </div>
                @endforeach

                @can('admin.kardex.recalculate')
                    @if ($report['can_post'])
                        <form method="POST" action="{{ route('admin.kardex.accounting.post') }}" onsubmit="return confirm('¿Contabilizar definitivamente los movimientos pendientes de este período?');">
                            @csrf
                            <input type="hidden" name="company_id" value="{{ $companyId }}">
                            <input type="hidden" name="warehouse_id" value="{{ $warehouseId }}">
                            <input type="hidden" name="year" value="{{ $year }}">
                            <input type="hidden" name="month" value="{{ $month }}">
                            <button class="btn btn-primary">
                                <i class="fas fa-book mr-1"></i> Generar asientos y CUO
                            </button>
                        </form>
                    @elseif ($report['blocker_count'] === 0 && $report['movement_count'] > 0 && $report['postable_count'] === 0)
                        <div class="text-success font-weight-bold">
                            <i class="fas fa-check-double mr-1"></i> Todos los movimientos del período ya tienen asiento y referencia contable.
                        </div>
                    @endif
                @endcan
            </div>
        </div>
    @endif

    <div class="alert alert-secondary border-0 shadow-sm">
        <strong>Alcance:</strong> este módulo crea el núcleo contable del inventario y conserva CUO/correlativo en el Kardex.
        No intenta reemplazar por sí solo un Libro Diario completo de ventas, caja, bancos, impuestos u otras operaciones no relacionadas con inventario.
    </div>
</div>
@stop
