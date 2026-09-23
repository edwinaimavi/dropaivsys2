import {Calendar} from '@fullcalendar/core';
import esLocale from '@fullcalendar/core/locales/es';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';

document.addEventListener('DOMContentLoaded', () => {
    const config = window.workAgendaConfig;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const form = document.getElementById('workAgendaForm');
    const calendarElement = document.getElementById('workAgendaCalendar');
    let table;
    let calendar;
    let searchTimer;
    const statusPresentation = {
        pending: {label: 'Pendiente', icon: 'fa-clock'},
        in_progress: {label: 'En proceso', icon: 'fa-play'},
        completed: {label: 'Concluida', icon: 'fa-check'},
        cancelled: {label: 'Cancelada', icon: 'fa-ban'}
    };
    const priorityLabels = {
        low: 'Baja',
        normal: 'Normal',
        high: 'Alta',
        urgent: 'Urgente'
    };

    if (!config || !form || !calendarElement || !document.getElementById('workAgendaTable')) {
        return;
    }

    $.ajaxSetup({headers: {'X-CSRF-TOKEN': csrf}});

    initializeCalendar();
    initializeTable();
    bindInterface();
    initializeEnhancedSelects();
    if (config.openActivityId) showDetail(config.openActivityId);

    function initializeCalendar() {
        calendar = new Calendar(calendarElement, {
            plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
            locale: esLocale,
            timeZone: 'local',
            firstDay: 1,
            initialView: window.matchMedia('(max-width: 575.98px)').matches ? 'listWeek' : 'dayGridMonth',
            headerToolbar: false,
            height: 'auto',
            contentHeight: 'auto',
            fixedWeekCount: false,
            nowIndicator: true,
            allDayText: 'Todo el día',
            noEventsText: 'No hay actividades en este periodo',
            dayMaxEvents: 4,
            moreLinkText: count => `+${count} más`,
            slotMinTime: '06:00:00',
            slotMaxTime: '22:00:00',
            slotDuration: '00:30:00',
            slotLabelInterval: '01:00:00',
            slotLabelFormat: {hour: '2-digit', minute: '2-digit', hour12: false},
            eventTimeFormat: {hour: '2-digit', minute: '2-digit', hour12: false},
            views: {
                dayGridMonth: {
                    dayHeaderFormat: {weekday: 'short'}
                },
                timeGridWeek: {
                    dayHeaderFormat: {weekday: 'short', day: 'numeric', omitCommas: true}
                },
                timeGridDay: {
                    dayHeaderFormat: {weekday: 'long', day: 'numeric', month: 'long'}
                }
            },
            selectable: Boolean(config.permissions.create && config.hasCompanies),
            selectMirror: true,
            selectMinDistance: 8,
            editable: false,
            eventStartEditable: false,
            eventDurationEditable: false,
            droppable: false,
            events: {
                url: config.routes.calendar,
                method: 'GET',
                extraParams: calendarFilters,
                failure: () => Swal.fire({
                    icon: 'error',
                    title: 'No se pudo cargar el calendario',
                    text: 'Actualice la página o vuelva a intentarlo.'
                })
            },
            loading: isLoading => calendarElement.classList.toggle('is-loading', isLoading),
            datesSet: info => updateCalendarHeading(info.view),
            dateClick: handleCalendarDateClick,
            select: handleCalendarSelection,
            eventClick: info => {
                info.jsEvent.preventDefault();
                showDetail(info.event.id);
            },
            eventClassNames: info => {
                const {status, priority} = info.event.extendedProps;
                return [
                    'agenda-event-shell',
                    `agenda-event--status-${status || 'pending'}`,
                    `agenda-event--priority-${priority || 'normal'}`
                ];
            },
            eventContent: renderCalendarEvent,
            eventDidMount: info => {
                const status = info.event.extendedProps.status || 'pending';
                const priority = info.event.extendedProps.priority || 'normal';
                const responsible = info.event.extendedProps.responsible || '';
                const company = info.event.extendedProps.company || '';
                const accessibleText = [
                    info.timeText || (info.event.allDay ? 'Todo el día' : ''),
                    info.event.title,
                    `Estado: ${statusPresentation[status]?.label || status}`,
                    `Prioridad: ${priorityLabels[priority] || priority}`,
                    responsible,
                    company
                ].filter(Boolean).join('. ');

                info.el.title = [info.event.title, responsible, company].filter(Boolean).join(' · ');
                info.el.setAttribute('aria-label', accessibleText);
            }
        });

        calendar.render();
    }

    function initializeEnhancedSelects() {
        if (!$.fn.select2) return;
        if (config.permissions.assign) {
            $('#work_agenda_responsible_user_id').select2({
                width: '100%', placeholder: 'Seleccione uno o varios responsables',
                dropdownParent: $('#workAgendaFormModal'), closeOnSelect: false
            });
        }
        $('#workAgendaDeriveUser').select2({width: '100%', placeholder: 'Seleccione un responsable', dropdownParent: $('#workAgendaDeriveModal')});
    }

    function initializeTable() {
        const labels = ['#', 'Fecha / Hora', 'Actividad', 'Empresa', 'Responsable', 'Tipo', 'Prioridad', 'Estado', 'Acciones'];

        table = $('#workAgendaTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            searching: false,
            lengthChange: false,
            pageLength: Number($('#workAgendaPageLength').val() || 10),
            ajax: {
                url: config.routes.data,
                data: data => Object.assign(data, currentListFilters()),
                dataSrc: response => {
                    renderSummary(response.summary || {});
                    return response.data || [];
                }
            },
            columns: [
                {data: 'DT_RowIndex', orderable: false},
                {data: 'schedule', name: 'activity_date'},
                {data: 'activity', name: 'title'},
                {data: 'company_name', orderable: false},
                {data: 'responsible_name', orderable: false},
                {data: 'type_label', name: 'activity_type'},
                {data: 'priority_badge', name: 'priority'},
                {data: 'status_badge', name: 'status'},
                {data: 'actions', orderable: false}
            ],
            order: [[1, 'asc']],
            createdRow: row => {
                $('td', row).each((index, cell) => cell.setAttribute('data-label', labels[index] || ''));
            },
            language: {url: '/vendor/datatables/js/i18n/es-ES.json'},
            dom: "<'row'<'col-12'tr>><'row mt-2'<'col-md-5'i><'col-md-7 d-flex justify-content-md-end'p>>"
        });
    }

    function bindInterface() {
        $('[data-work-agenda-view]').on('click', function () {
            setPrimaryView($(this).data('work-agenda-view'));
        });

        $('#workAgendaCalendarPrev').on('click', () => calendar.prev());
        $('#workAgendaCalendarToday').on('click', () => calendar.today());
        $('#workAgendaCalendarNext').on('click', () => calendar.next());
        $('[data-calendar-view]').on('click', function () {
            calendar.changeView($(this).data('calendar-view'));
        });

        $('#workAgendaCalendarFilterStatus, #workAgendaCalendarFilterPriority').on('change', refetchCalendar);
        $('#workAgendaCalendarFilterResponsible').on('change', refetchCalendar);
        $('#workAgendaCalendarFilterCompany').on('change', function () {
            if (config.permissions.viewAll) {
                loadResponsibles(this.value, $('#workAgendaCalendarFilterResponsible'), null, true);
            }
            refetchCalendar();
        });

        $('#btnResetWorkAgendaCalendarFilters').on('click', () => {
            $('#workAgendaCalendarFilterCompany, #workAgendaCalendarFilterStatus, #workAgendaCalendarFilterPriority').val('');
            if (config.permissions.viewAll) {
                $('#workAgendaCalendarFilterResponsible')
                    .html('<option value="">Seleccione una empresa</option>')
                    .prop('disabled', true);
            }
            refetchCalendar();
        });

        $('#workAgendaSearch').on('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => reloadTable(true), 350);
        });

        $('#workAgendaFilterStatus, #workAgendaFilterPriority, #workAgendaFilterFrom, #workAgendaFilterTo')
            .on('change', () => reloadTable(true));

        $('#workAgendaPageLength').on('change', function () {
            table.page.len(Number(this.value)).draw();
        });

        $('#workAgendaFilterCompany').on('change', function () {
            if (config.permissions.viewAll) {
                loadResponsibles(this.value, $('#workAgendaFilterResponsible'), null, true);
            }
            reloadTable(true);
        });

        $('#workAgendaFilterResponsible').on('change', () => reloadTable(true));

        $('#btnResetWorkAgendaFilters').on('click', () => {
            $('#workAgendaSearch, #workAgendaFilterFrom, #workAgendaFilterTo').val('');
            $('#workAgendaFilterCompany, #workAgendaFilterStatus, #workAgendaFilterPriority').val('');
            if (config.permissions.viewAll) {
                $('#workAgendaFilterResponsible')
                    .html('<option value="">Seleccione una empresa</option>')
                    .prop('disabled', true);
            }
            reloadTable(true);
        });

        $('#btnNewWorkAgenda, #btnNewWorkAgendaCalendar').on('click', () => openCreate());

        $('#work_agenda_company_id').on('change', function () {
            if (config.permissions.assign) {
                loadResponsibles(this.value, $('#work_agenda_responsible_user_id'), config.currentUser.id, false);
            }
        });

        $('#work_agenda_is_all_day').on('change', toggleAllDay);
        $('#work_agenda_activity_type').on('change', toggleOtherType);
        $('#workAgendaForm').on('submit', submitForm);

        $(document).on('click', '.btn-work-agenda-view', function () {
            showDetail($(this).data('id'));
        });

        $(document).on('click', '.btn-work-agenda-edit', function () {
            openEdit($(this).data('id'));
        });

        $('#btnEditWorkAgendaFromDetail').on('click', function () {
            const id = $(this).data('id');
            $('#workAgendaDetailModal').modal('hide');
            openEdit(id);
        });

        $(document).on('click', '.btn-work-agenda-status', handleStatusChange);
        $(document).on('click', '.btn-work-agenda-derive', openDerivation);
        $('#workAgendaDeriveForm').on('submit', submitDerivation);
        $(document).on('click', '.btn-work-agenda-delete', handleDelete);
    }

    function currentListFilters() {
        return {
            search_term: $('#workAgendaSearch').val(),
            company_id: $('#workAgendaFilterCompany').val(),
            responsible_user_id: config.permissions.viewAll ? $('#workAgendaFilterResponsible').val() : '',
            status: $('#workAgendaFilterStatus').val(),
            priority: $('#workAgendaFilterPriority').val(),
            date_from: $('#workAgendaFilterFrom').val(),
            date_to: $('#workAgendaFilterTo').val()
        };
    }

    function calendarFilters() {
        return {
            company_id: $('#workAgendaCalendarFilterCompany').val() || '',
            responsible_user_id: config.permissions.viewAll ? ($('#workAgendaCalendarFilterResponsible').val() || '') : '',
            status: $('#workAgendaCalendarFilterStatus').val() || '',
            priority: $('#workAgendaCalendarFilterPriority').val() || ''
        };
    }

    function setPrimaryView(view) {
        const showCalendar = view === 'calendar';
        $('#workAgendaCalendarPanel').toggleClass('d-none', !showCalendar);
        $('#workAgendaListPanel').toggleClass('d-none', showCalendar);
        $('[data-work-agenda-view]').each(function () {
            const active = $(this).data('work-agenda-view') === view;
            $(this).toggleClass('is-active', active).attr('aria-pressed', String(active));
        });

        if (showCalendar) {
            window.setTimeout(() => calendar.updateSize(), 0);
        } else {
            window.setTimeout(() => table.columns.adjust(), 0);
        }
    }

    function updateCalendarHeading(view) {
        $('#workAgendaCalendarTitle').text(view.title);
        $('[data-calendar-view]').each(function () {
            const active = $(this).data('calendar-view') === view.type;
            $(this).toggleClass('is-active', active).attr('aria-pressed', String(active));
        });
    }

    function renderCalendarEvent(info) {
        const status = info.event.extendedProps.status || 'pending';
        const priority = info.event.extendedProps.priority || 'normal';
        const statusUi = statusPresentation[status] || {label: status, icon: 'fa-circle'};
        const isMonth = info.view.type === 'dayGridMonth';
        const isList = info.view.type.startsWith('list');
        const showTime = !isList && Boolean(info.timeText || (info.event.allDay && !isMonth));
        const content = document.createElement('div');
        content.className = [
            'agenda-event',
            `agenda-event--status-${status}`,
            `agenda-event--priority-${priority}`,
            `agenda-event--view-${isMonth ? 'month' : (isList ? 'list' : 'time')}`
        ].join(' ');
        content.classList.toggle('agenda-event--no-time', !showTime);

        const top = document.createElement('div');
        top.className = 'agenda-event__top';

        if (showTime) {
            const time = document.createElement('span');
            time.className = 'agenda-event__time';
            const timeIcon = document.createElement('i');
            timeIcon.className = 'far fa-clock';
            timeIcon.setAttribute('aria-hidden', 'true');
            time.appendChild(timeIcon);
            time.appendChild(document.createTextNode(info.timeText || 'Todo el día'));
            top.appendChild(time);
        }

        const statusBadge = document.createElement('span');
        statusBadge.className = 'agenda-event__status';
        statusBadge.title = `Estado: ${statusUi.label}`;
        const statusIcon = document.createElement('i');
        statusIcon.className = `fas ${statusUi.icon}`;
        statusIcon.setAttribute('aria-hidden', 'true');
        const statusText = document.createElement('span');
        statusText.className = 'agenda-event__status-text';
        statusText.textContent = statusUi.label;
        statusBadge.append(statusIcon, statusText);
        top.appendChild(statusBadge);

        if (priority !== 'normal') {
            const priorityBadge = document.createElement('span');
            priorityBadge.className = 'agenda-event__priority';
            priorityBadge.textContent = priorityLabels[priority] || priority;
            priorityBadge.title = `Prioridad: ${priorityLabels[priority] || priority}`;
            top.appendChild(priorityBadge);
        }

        content.appendChild(top);

        const title = document.createElement('div');
        title.className = 'agenda-event__title';
        title.textContent = info.event.title;
        content.appendChild(title);

        if (!isMonth) {
            const metaText = config.permissions.viewAll
                ? info.event.extendedProps.responsible
                : info.event.extendedProps.company;

            if (metaText) {
                const meta = document.createElement('div');
                meta.className = 'agenda-event__meta';
                const metaIcon = document.createElement('i');
                metaIcon.className = config.permissions.viewAll ? 'far fa-user' : 'far fa-building';
                metaIcon.setAttribute('aria-hidden', 'true');
                const metaValue = document.createElement('span');
                metaValue.textContent = metaText;
                meta.append(metaIcon, metaValue);
                content.appendChild(meta);
            }
        }

        return {domNodes: [content]};
    }

    function handleCalendarDateClick(info) {
        if (!config.permissions.create || !config.hasCompanies) return;

        const prefill = {activityDate: datePart(info.dateStr)};
        if (!info.allDay) {
            prefill.startTime = timePart(info.dateStr);
            prefill.endTime = '';
        }
        openCreate(prefill);
    }

    function handleCalendarSelection(info) {
        if (!config.permissions.create || !config.hasCompanies) return;

        const startDate = datePart(info.startStr);
        const endDate = datePart(info.endStr);
        const prefill = {activityDate: startDate};
        if (!info.allDay) {
            prefill.startTime = timePart(info.startStr);
            prefill.endTime = startDate === endDate ? timePart(info.endStr) : '';
        }
        openCreate(prefill);
        calendar.unselect();
    }

    function datePart(value) {
        return String(value || '').slice(0, 10);
    }

    function timePart(value) {
        const match = String(value || '').match(/T(\d{2}:\d{2})/);
        return match ? match[1] : '';
    }

    function openCreate(prefill = {}) {
        resetForm(prefill);
        $('#workAgendaFormModal').modal('show');
    }

    function submitForm(event) {
        event.preventDefault();
        clearErrors();

        const id = $('#workAgendaItemId').val();
        const data = new FormData(form);
        if (id) {
            data.append('_method', 'PUT');
            data.set('company_id', $('#work_agenda_company_id').val());
        }

        setFormBusy(true);
        $.ajax({
            url: id ? `${config.routes.base}/${id}` : config.routes.store,
            method: 'POST',
            data,
            processData: false,
            contentType: false
        }).done(response => {
            $('#workAgendaFormModal').modal('hide');
            notifyAgendaChanged();
            reloadAgendaViews(false);
            toast('success', response.message || 'Actividad guardada correctamente.');
        }).fail(handleFormError).always(() => setFormBusy(false));
    }

    function handleStatusChange() {
        const id = $(this).data('id');
        const assignmentId = $(this).data('assignment-id');
        const status = $(this).data('status');
        const detailWasOpen = $('#workAgendaDetailModal').hasClass('show');
        const copy = {
            in_progress: ['Iniciar actividad', 'La actividad pasará a En proceso.'],
            completed: ['Concluir actividad', 'Se registrará la fecha y hora de conclusión.'],
            cancelled: ['Cancelar actividad', 'La actividad quedará cancelada.']
        }[status] || ['Cambiar estado', 'Se actualizará el estado de la actividad.'];

        Swal.fire({
            title: copy[0], text: copy[1], icon: 'question', showCancelButton: true,
            confirmButtonText: 'Confirmar', cancelButtonText: 'Volver', confirmButtonColor: '#287e5e'
        }).then(result => {
            if (!result.isConfirmed) return;
            if (detailWasOpen) $('#workAgendaDetailModal').modal('hide');

            const url = assignmentId
                ? `${config.routes.base}/${id}/assignments/${assignmentId}/status`
                : `${config.routes.base}/${id}/status`;
            $.ajax({url, method: 'PATCH', data: {status}})
                .done(response => { notifyAgendaChanged(); reloadAgendaViews(false); toast('success', response.message); })
                .fail(notifyError);
        });
    }

    function handleDelete() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Eliminar actividad', text: 'La actividad dejará de aparecer en la agenda.', icon: 'warning',
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#a9434c'
        }).then(result => {
            if (!result.isConfirmed) return;
            $.ajax({url: `${config.routes.base}/${id}`, method: 'DELETE'})
                .done(response => { notifyAgendaChanged(); reloadAgendaViews(false); toast('success', response.message); })
                .fail(notifyError);
        });
    }

    function renderSummary(summary) {
        $('#workAgendaSummaryToday').text(summary.today ?? 0);
        $('#workAgendaSummaryPending').text(summary.pending ?? 0);
        $('#workAgendaSummaryProgress').text(summary.in_progress ?? 0);
        $('#workAgendaSummaryCompleted').text(summary.completed ?? 0);
    }

    function reloadAgendaViews(resetPage = false) {
        reloadTable(resetPage);
        refetchCalendar();
    }

    function reloadTable(resetPage = false) {
        table.ajax.reload(null, resetPage);
    }

    function refetchCalendar() {
        calendar.refetchEvents();
    }

    function resetForm(prefill = {}) {
        form.reset();
        clearErrors();
        $('#workAgendaItemId').val('');
        $('#workAgendaFormModalTitle').text('Nueva actividad');
        $('#work_agenda_activity_date').val(prefill.activityDate || config.today);
        $('#work_agenda_starts_at').val(prefill.startTime === undefined ? '09:00' : (prefill.startTime || ''));
        $('#work_agenda_ends_at').val(prefill.endTime === undefined ? '10:00' : (prefill.endTime || ''));
        $('#work_agenda_priority').val('normal');
        setStatusValue('pending');
        configureStatusOptions(null);
        $('#work_agenda_company_id').val(config.defaultCompanyId || '');
        $('#work_agenda_is_all_day').prop('checked', false);
        toggleAllDay();
        toggleOtherType();

        $('#work_agenda_company_id').prop('disabled', false);
        if (config.permissions.assign) {
            loadResponsibles(config.defaultCompanyId, $('#work_agenda_responsible_user_id'), [config.currentUser.id], false);
        }
    }

    function openEdit(id) {
        resetForm();
        $.get(`${config.routes.base}/${id}`).done(response => {
            const item = response.data;
            $('#workAgendaItemId').val(item.id);
            $('#workAgendaFormModalTitle').text('Editar actividad');
            $('#work_agenda_company_id').val(item.company_id).prop('disabled', true);
            $('#work_agenda_title').val(item.title);
            $('#work_agenda_description').val(item.description || '');
            $('#work_agenda_activity_date').val(item.activity_date);
            $('#work_agenda_starts_at').val(item.starts_at || '');
            $('#work_agenda_ends_at').val(item.ends_at || '');
            $('#work_agenda_is_all_day').prop('checked', Boolean(item.is_all_day));
            $('#work_agenda_priority').val(item.priority);
            setStatusValue(item.status);
            configureStatusOptions(item);
            $('#work_agenda_location').val(item.location || '');
            $('#work_agenda_reminder_at').val(item.reminder_at || '');
            setActivityType(item.activity_type);
            toggleAllDay();

            if (config.permissions.assign) {
                loadResponsibles(item.company_id, $('#work_agenda_responsible_user_id'), item.responsible_user_ids, false);
            }

            $('#workAgendaFormModal').modal('show');
        }).fail(notifyError);
    }

    function showDetail(id) {
        $.get(`${config.routes.base}/${id}`).done(response => {
            const item = response.data;
            $('#workAgendaDetailTitle').text(item.title);
            $('#workAgendaDetailSubtitle').text(`${item.company} · ${item.responsible}`);
            $('#workAgendaDetailDescription').text(item.description || 'Sin descripción registrada.');
            $('#workAgendaDetailCompany').text(item.company);
            $('#workAgendaDetailResponsible').text(item.responsible);
            $('#workAgendaDetailDate').text(item.activity_date_display || '—');
            $('#workAgendaDetailSchedule').text(item.schedule || '—');
            $('#workAgendaDetailType').text(item.activity_type || 'Sin tipo');
            $('#workAgendaDetailLocation').text(item.location || 'Sin lugar registrado');
            $('#workAgendaDetailReminder').text(item.reminder_display || 'Sin recordatorio');
            $('#workAgendaDetailCreator').text(item.created_by);
            $('#workAgendaDetailCreatedAt').text(item.created_at || '—');
            $('#workAgendaDetailCompletedAt').text(item.completed_at || 'No concluida');
            $('#workAgendaDetailPriority').attr('class', `work-agenda-badge priority-${item.priority}`).text(item.priority_label);
            $('#workAgendaDetailStatus').attr('class', `work-agenda-badge status-${item.status}`).text(item.status_label);
            $('#workAgendaDetailTimingFlag').attr('class', timingClass(item)).text(timingText(item));
            $('#workAgendaDetailStatusActions').empty();
            $('#btnEditWorkAgendaFromDetail').data('id', item.id).toggle(Boolean(item.can_edit) && item.status !== 'cancelled');
            renderAssignments(item);
            renderTimeline(item.timeline || []);
            $('#workAgendaDetailModal').modal('show');
        }).fail(notifyError);
    }

    function renderDetailStatusActions(item) {
        const container = $('#workAgendaDetailStatusActions').empty();
        if (!item.can_change_status) return;

        const actions = {
            in_progress: [item.status === 'completed' ? 'Reabrir' : 'Iniciar', 'fa-play', 'btn-outline-info'],
            completed: ['Concluir', 'fa-check', 'btn-outline-success'],
            cancelled: ['Cancelar', 'fa-ban', 'btn-outline-secondary']
        };

        (item.available_transitions || []).forEach(status => {
            if (!actions[status]) return;
            const [label, icon, buttonClass] = actions[status];
            $('<button>', {
                type: 'button',
                class: `btn ${buttonClass} btn-work-agenda-status mr-1`,
                'data-id': item.id,
                'data-status': status
            }).append($('<i>', {class: `fas ${icon} mr-1`})).append(document.createTextNode(label)).appendTo(container);
        });
    }

    function renderAssignments(item) {
        const list = $('#workAgendaAssignmentList').empty();
        (item.responsibles || []).forEach(assignment => {
            const card = $('<article>', {class: `work-agenda-assignment status-${assignment.status}`});
            $('<div>', {class: 'work-agenda-assignment-meta'}).append(
                $('<strong>').text(assignment.user),
                $('<span>', {class: `work-agenda-badge status-${assignment.status}`}).text(assignment.status_label)
            ).appendTo(card);
            const actions = $('<div>', {class: 'work-agenda-assignment-actions'});
            const labels = {in_progress: 'Iniciar', completed: 'Concluir', cancelled: 'Cancelar'};
            (assignment.available_transitions || []).forEach(status => $('<button>', {
                type: 'button', class: 'btn btn-sm btn-outline-secondary btn-work-agenda-status',
                'data-id': item.id, 'data-assignment-id': assignment.id, 'data-status': status,
                text: labels[status] || status
            }).appendTo(actions));
            if (assignment.can_derive) $('<button>', {
                type: 'button', class: 'btn btn-sm btn-outline-primary btn-work-agenda-derive',
                'data-id': item.id, 'data-assignment-id': assignment.id, 'data-company-id': item.company_id,
                'data-user-id': assignment.user_id, text: 'Derivar'
            }).appendTo(actions);
            card.append(actions).appendTo(list);
        });
        if (!list.children().length) list.append($('<p>', {class: 'text-muted mb-0', text: 'Sin asignaciones registradas.'}));
    }

    function renderTimeline(events) {
        const timeline = $('#workAgendaTimeline').empty();
        events.forEach(event => {
            const node = $('<article>', {class: `work-agenda-timeline-item is-${event.type}`});
            $('<time>').text(event.at).appendTo(node);
            $('<p>').text(event.text).appendTo(node);
            if (event.reason) $('<small>').text(`Motivo: ${event.reason}`).appendTo(node);
            timeline.append(node);
        });
    }

    function openDerivation() {
        const button = $(this);
        $('#workAgendaDeriveForm')[0].reset();
        $('#workAgendaDeriveForm .is-invalid').removeClass('is-invalid');
        $('#workAgendaDeriveForm .invalid-feedback').text('');
        $('#workAgendaDeriveItemId').val(button.data('id'));
        $('#workAgendaDeriveAssignmentId').val(button.data('assignment-id'));
        loadResponsibles(button.data('company-id'), $('#workAgendaDeriveUser'), null, false).done(() => {
            $(`#workAgendaDeriveUser option[value="${button.data('user-id')}"]`).remove();
            $('#workAgendaDetailModal').modal('hide');
            $('#workAgendaDeriveModal').modal('show');
        });
    }

    function submitDerivation(event) {
        event.preventDefault();
        const itemId = $('#workAgendaDeriveItemId').val();
        const assignmentId = $('#workAgendaDeriveAssignmentId').val();
        $.ajax({url: `${config.routes.base}/${itemId}/assignments/${assignmentId}/derive`, method: 'POST', data: $(this).serialize()})
            .done(response => { $('#workAgendaDeriveModal').modal('hide'); notifyAgendaChanged(); reloadAgendaViews(false); toast('success', response.message); })
            .fail(handleFormError);
    }

    function notifyAgendaChanged() {
        window.dispatchEvent(new CustomEvent('work-agenda:changed'));
    }

    function loadResponsibles(companyId, select, selectedId, isFilter) {
        const emptyText = isFilter ? 'Todos los responsables' : 'Seleccione un responsable';
        if (!companyId) {
            select.html(`<option value="">${isFilter ? 'Seleccione una empresa' : 'Seleccione primero una empresa'}</option>`).prop('disabled', true);
            return $.Deferred().resolve().promise();
        }

        select.html('<option value="">Cargando...</option>').prop('disabled', true);
        return $.get(`${config.routes.responsibles}/${companyId}/responsibles`).done(response => {
            select.html(`<option value="">${emptyText}</option>`);
            (response.data || []).forEach(user => select.append(new Option(user.text, user.id)));
            const selected = Array.isArray(selectedId) ? selectedId.map(String) : (selectedId ? [String(selectedId)] : []);
            if (selected.length) select.val(selected);
            select.trigger('change');
            select.prop('disabled', false);
        }).fail(xhr => {
            select.html('<option value="">No se pudieron cargar usuarios</option>');
            notifyError(xhr);
        });
    }

    function toggleAllDay() {
        const allDay = $('#work_agenda_is_all_day').is(':checked');
        $('.work-agenda-time-field').toggleClass('is-disabled', allDay);
        $('#work_agenda_starts_at, #work_agenda_ends_at').prop('disabled', allDay);
        if (allDay) $('#work_agenda_starts_at, #work_agenda_ends_at').val('');
    }

    function toggleOtherType() {
        const isOther = $('#work_agenda_activity_type').val() === 'Otro';
        $('#workAgendaOtherTypeWrap').toggleClass('d-none', !isOther);
        $('#work_agenda_activity_type_other').prop('disabled', !isOther);
        if (!isOther) $('#work_agenda_activity_type_other').val('');
    }

    function setActivityType(type) {
        const known = !type || config.activityTypes.includes(type);
        $('#work_agenda_activity_type').val(known ? (type || '') : 'Otro');
        $('#work_agenda_activity_type_other').val(known ? '' : type);
        toggleOtherType();
    }

    function setStatusValue(status) {
        $('#work_agenda_status, #work_agenda_status_hidden').val(status);
    }

    function configureStatusOptions(item) {
        const options = $('#work_agenda_status option');
        options.prop('disabled', false);
        if (!item || !config.permissions.changeStatus) return;
        const allowed = [item.status, ...(item.available_transitions || [])];
        options.each(function () { $(this).prop('disabled', !allowed.includes(this.value)); });
    }

    function setFormBusy(busy) {
        $('#btnSaveWorkAgenda').prop('disabled', busy).find('span').text(busy ? 'Guardando...' : 'Guardar actividad');
        $('#btnSaveWorkAgenda i').toggleClass('fa-save', !busy).toggleClass('fa-spinner fa-spin', busy);
    }

    function clearErrors() {
        $('#workAgendaForm .is-invalid').removeClass('is-invalid');
        $('#workAgendaForm .invalid-feedback').text('');
    }

    function handleFormError(xhr) {
        if (xhr.status !== 422) return notifyError(xhr);
        Object.entries(xhr.responseJSON?.errors || {}).forEach(([field, messages]) => {
            field = field.split('.')[0];
            const input = $(`#work_agenda_${field}`);
            input.addClass('is-invalid');
            $(`[data-error-for="${field}"]`).text(messages[0] || 'Dato no válido.');
        });
        toast('warning', xhr.responseJSON?.message || 'Revise los datos ingresados.');
    }

    function notifyError(xhr) {
        const message = xhr.status === 403
            ? 'No tiene autorización para realizar esta acción.'
            : (xhr.status === 404 ? 'La actividad no está disponible.' : (xhr.responseJSON?.message || 'No se pudo completar la operación.'));
        Swal.fire({icon: 'error', title: 'No se pudo continuar', text: message});
    }

    function toast(icon, message) {
        Swal.fire({icon, title: message, toast: true, position: 'top-end', showConfirmButton: false, timer: 3200, timerProgressBar: true});
    }

    function timingClass(item) {
        if (item.is_overdue) return 'work-agenda-timing-flag is-overdue';
        if (item.is_today) return 'work-agenda-timing-flag is-today';
        return 'work-agenda-timing-flag';
    }

    function timingText(item) {
        if (item.is_overdue) return 'Actividad atrasada';
        if (item.is_today) return 'Actividad de hoy';
        return 'Actividad programada';
    }
});
