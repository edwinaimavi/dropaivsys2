@extends('layouts.app')

@section('subtitle', 'Agenda de Trabajo')

@section('header')
<div class="container-fluid work-agenda-page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap">
        <div class="d-flex align-items-center mb-2 mb-md-0">
            <span class="work-agenda-header-icon"><i class="fas fa-calendar-check"></i></span>
            <div>
                <h1 class="mb-1 font-weight-bold">Agenda de Trabajo</h1>
                <p class="mb-0 text-muted">Organiza y da seguimiento a tus actividades diarias.</p>
            </div>
        </div>
        @can('agenda_trabajo.crear')
            <button type="button" id="btnNewWorkAgenda" class="btn btn-success work-agenda-primary-action"
                @disabled($companies->isEmpty()) title="{{ $companies->isEmpty() ? 'No tiene empresas autorizadas.' : 'Registrar una nueva actividad' }}">
                <i class="fas fa-plus mr-1"></i> Nueva actividad
            </button>
        @endcan
    </div>
    <nav class="mt-3" aria-label="breadcrumb">
        <ol class="breadcrumb bg-transparent p-0 mb-0">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
            <li class="breadcrumb-item">Agenda</li>
            <li class="breadcrumb-item active">Agenda de Trabajo</li>
        </ol>
    </nav>
</div>
@stop

@section('content_body')
@if($companies->isEmpty())
    <div class="alert work-agenda-access-alert">
        <i class="fas fa-building mr-2"></i>
        No tiene empresas autorizadas para gestionar la Agenda de Trabajo.
    </div>
@endif

<div class="work-agenda-view-switch" role="group" aria-label="Vista principal de la agenda">
    <button type="button" class="btn is-active" data-work-agenda-view="calendar" aria-pressed="true">
        <i class="far fa-calendar-alt mr-1"></i> Calendario
    </button>
    <button type="button" class="btn" data-work-agenda-view="list" aria-pressed="false">
        <i class="fas fa-list-ul mr-1"></i> Listado
    </button>
</div>

<div class="work-agenda-summary-grid">
    <article class="work-agenda-summary-card is-today"><span><i class="far fa-calendar-day"></i></span><div><small>Hoy</small><strong id="workAgendaSummaryToday">0</strong></div></article>
    <article class="work-agenda-summary-card is-pending"><span><i class="far fa-clock"></i></span><div><small>Pendientes</small><strong id="workAgendaSummaryPending">0</strong></div></article>
    <article class="work-agenda-summary-card is-progress"><span><i class="fas fa-spinner"></i></span><div><small>En proceso</small><strong id="workAgendaSummaryProgress">0</strong></div></article>
    <article class="work-agenda-summary-card is-completed"><span><i class="fas fa-check"></i></span><div><small>Concluidas</small><strong id="workAgendaSummaryCompleted">0</strong></div></article>
</div>

<section id="workAgendaCalendarPanel" class="work-agenda-primary-view">
    <div class="card border-0 shadow-sm work-agenda-calendar-filter-card">
        <div class="card-body">
            <div class="work-agenda-calendar-filters">
                <div class="work-agenda-filter-field">
                    <label for="workAgendaCalendarFilterCompany">Empresa</label>
                    <select id="workAgendaCalendarFilterCompany" class="form-control form-control-sm">
                        <option value="">Todas</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->trade_name ?: $company->business_name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($canViewAll)
                    <div class="work-agenda-filter-field">
                        <label for="workAgendaCalendarFilterResponsible">Responsable</label>
                        <select id="workAgendaCalendarFilterResponsible" class="form-control form-control-sm" disabled>
                            <option value="">Seleccione una empresa</option>
                        </select>
                    </div>
                @endif
                <div class="work-agenda-filter-field">
                    <label for="workAgendaCalendarFilterStatus">Estado</label>
                    <select id="workAgendaCalendarFilterStatus" class="form-control form-control-sm">
                        <option value="">Todos</option>
                        @foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="work-agenda-filter-field">
                    <label for="workAgendaCalendarFilterPriority">Prioridad</label>
                    <select id="workAgendaCalendarFilterPriority" class="form-control form-control-sm">
                        <option value="">Todas</option>
                        @foreach($priorities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <button type="button" id="btnResetWorkAgendaCalendarFilters" class="btn btn-light border work-agenda-reset" title="Limpiar filtros del calendario">
                    <i class="fas fa-undo-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm work-agenda-calendar-card">
        <div class="card-header bg-white border-0 work-agenda-calendar-header">
            <div class="work-agenda-calendar-navigation">
                <button type="button" id="workAgendaCalendarPrev" class="btn" title="Periodo anterior" aria-label="Periodo anterior"><i class="fas fa-chevron-left"></i></button>
                <button type="button" id="workAgendaCalendarToday" class="btn">Hoy</button>
                <button type="button" id="workAgendaCalendarNext" class="btn" title="Periodo siguiente" aria-label="Periodo siguiente"><i class="fas fa-chevron-right"></i></button>
            </div>
            <h2 id="workAgendaCalendarTitle" class="work-agenda-calendar-title" aria-live="polite"></h2>
            <div class="work-agenda-calendar-header-actions">
                <div class="work-agenda-calendar-modes" role="group" aria-label="Periodo del calendario">
                    <button type="button" class="btn is-active" data-calendar-view="dayGridMonth">Mes</button>
                    <button type="button" class="btn" data-calendar-view="timeGridWeek">Semana</button>
                    <button type="button" class="btn" data-calendar-view="timeGridDay">Día</button>
                    <button type="button" class="btn" data-calendar-view="listWeek">Lista</button>
                </div>
                @can('agenda_trabajo.crear')
                    <button type="button" id="btnNewWorkAgendaCalendar" class="btn btn-success work-agenda-primary-action"
                        @disabled($companies->isEmpty())>
                        <i class="fas fa-plus mr-1"></i> Nueva actividad
                    </button>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div id="workAgendaCalendar" aria-label="Calendario de actividades"></div>
        </div>
    </div>
</section>

<section id="workAgendaListPanel" class="work-agenda-primary-view d-none">
<div class="card border-0 shadow-sm work-agenda-filter-card">
    <div class="card-body">
        <div class="work-agenda-toolbar">
            <div class="work-agenda-search">
                <i class="fas fa-search"></i>
                <input type="search" id="workAgendaSearch" class="form-control" placeholder="Buscar actividad, lugar, tipo o responsable">
            </div>
            <div class="work-agenda-filter-field">
                <label for="workAgendaFilterCompany">Empresa</label>
                <select id="workAgendaFilterCompany" class="form-control form-control-sm">
                    <option value="">Todas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->trade_name ?: $company->business_name }}</option>
                    @endforeach
                </select>
            </div>
            @if($canViewAll)
                <div class="work-agenda-filter-field">
                    <label for="workAgendaFilterResponsible">Responsable</label>
                    <select id="workAgendaFilterResponsible" class="form-control form-control-sm" disabled>
                        <option value="">Seleccione una empresa</option>
                    </select>
                </div>
            @endif
            <div class="work-agenda-filter-field">
                <label for="workAgendaFilterStatus">Estado</label>
                <select id="workAgendaFilterStatus" class="form-control form-control-sm">
                    <option value="">Todos</option>
                    @foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="work-agenda-filter-field">
                <label for="workAgendaFilterPriority">Prioridad</label>
                <select id="workAgendaFilterPriority" class="form-control form-control-sm">
                    <option value="">Todas</option>
                    @foreach($priorities as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="work-agenda-filter-field is-date">
                <label for="workAgendaFilterFrom">Desde</label>
                <input type="date" id="workAgendaFilterFrom" class="form-control form-control-sm">
            </div>
            <div class="work-agenda-filter-field is-date">
                <label for="workAgendaFilterTo">Hasta</label>
                <input type="date" id="workAgendaFilterTo" class="form-control form-control-sm">
            </div>
            <div class="work-agenda-filter-field is-length">
                <label for="workAgendaPageLength">Mostrar</label>
                <select id="workAgendaPageLength" class="form-control form-control-sm">
                    @foreach([10, 25, 50, 100] as $length)<option value="{{ $length }}">{{ $length }}</option>@endforeach
                </select>
            </div>
            <button type="button" id="btnResetWorkAgendaFilters" class="btn btn-light border work-agenda-reset" title="Limpiar filtros">
                <i class="fas fa-undo-alt"></i>
            </button>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm work-agenda-table-card">
    <div class="card-header bg-white border-0">
        <h6 class="mb-1 font-weight-bold">Actividades registradas</h6>
        <small class="text-muted">La información se limita automáticamente a sus empresas y nivel de acceso.</small>
    </div>
    <div class="card-body pt-1">
        <div class="table-responsive work-agenda-table-wrap">
            <table id="workAgendaTable" class="table table-hover w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha / Hora</th>
                        <th>Actividad</th>
                        <th>Empresa</th>
                        <th>Responsable</th>
                        <th>Tipo</th>
                        <th>Prioridad</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
</section>

@include('admin.work-agenda.partials.form-modal')
@include('admin.work-agenda.partials.detail-modal')
@stop

@push('css')
@vite('resources/css/work-agenda.css')
@endpush

@push('js')
<script>
window.workAgendaConfig = {
    routes: {
        data: @json(route('admin.work-agenda.data')),
        calendar: @json(route('admin.work-agenda.calendar')),
        store: @json(route('admin.work-agenda.store')),
        base: @json(url('admin/work-agenda')),
        responsibles: @json(url('admin/work-agenda/companies'))
    },
    permissions: {
        create: @json(auth()->user()->can('agenda_trabajo.crear')),
        edit: @json(auth()->user()->can('agenda_trabajo.editar')),
        delete: @json(auth()->user()->can('agenda_trabajo.eliminar')),
        changeStatus: @json(auth()->user()->can('agenda_trabajo.cambiar_estado')),
        viewAll: @json($canViewAll),
        assign: @json($canAssign),
        derive: @json(auth()->user()->can('agenda_trabajo.derivar'))
    },
    defaultCompanyId: @json($defaultCompanyId),
    hasCompanies: @json($companies->isNotEmpty()),
    currentUser: @json(['id' => auth()->id(), 'name' => trim(auth()->user()->name.' '.auth()->user()->lastname)]),
    activityTypes: @json($activityTypes),
    today: @json(now(config('app.timezone'))->toDateString()),
    openActivityId: @json(request()->integer('activity') ?: null)
};
</script>
@vite('resources/js/pages/work-agenda.js')
@endpush
