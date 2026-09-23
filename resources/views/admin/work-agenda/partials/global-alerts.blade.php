<li class="nav-item dropdown dp-agenda-alerts" id="dpAgendaAlertsRoot">
    <a id="dpAgendaAlertToggle" class="nav-link dp-agenda-alert-toggle" data-toggle="dropdown" href="#" role="button"
        aria-label="Alertas de Agenda: sin alertas pendientes" aria-haspopup="true" aria-expanded="false"
        title="Sin alertas pendientes">
        <span class="dp-agenda-alert-icon" aria-hidden="true">
            <i class="far fa-bell"></i>
            <span class="dp-agenda-alert-ring"></span>
        </span>
        <span class="dp-agenda-alert-button-copy d-none d-xl-flex" aria-hidden="true">
            <small>AGENDA</small><strong id="dpAgendaAlertButtonText">Alertas</strong>
        </span>
        <span id="dpAgendaAlertCount" class="badge dp-agenda-alert-count d-none" aria-live="polite">0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-right dp-agenda-alert-menu" aria-labelledby="dpAgendaAlertToggle">
        <header class="dp-agenda-alert-header">
            <span class="dp-agenda-alert-header-icon"><i class="far fa-bell"></i></span>
            <span class="dp-agenda-alert-heading">
                <strong>Mis alertas</strong><small id="dpAgendaAlertSummary">Sin alertas pendientes</small>
            </span>
            <span id="dpAgendaAlertHeaderCount" class="dp-agenda-alert-header-count">0</span>
        </header>
        <div id="dpAgendaAlertList" class="dp-agenda-alert-list" aria-live="polite">
            <div class="dp-agenda-alert-empty"><span><i class="far fa-bell-slash"></i></span><strong>Todo al día</strong><small>No hay alertas activas.</small></div>
        </div>
        <a href="{{ route('admin.work-agenda.index') }}" class="dp-agenda-alert-footer">
            <span>Ir a Agenda de Trabajo</span><i class="fas fa-arrow-right"></i>
        </a>
    </div>
</li>
