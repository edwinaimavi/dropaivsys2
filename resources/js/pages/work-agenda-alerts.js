document.addEventListener('DOMContentLoaded', () => {
    const config = window.dpAgendaAlertsConfig || window.workAgendaAlertsConfig;
    const root = document.getElementById('dpAgendaAlertsRoot');
    const toggle = root?.querySelector('.dp-agenda-alert-toggle');
    const count = document.getElementById('dpAgendaAlertCount');
    const headerCount = document.getElementById('dpAgendaAlertHeaderCount');
    const buttonText = document.getElementById('dpAgendaAlertButtonText');
    const summary = document.getElementById('dpAgendaAlertSummary');
    const list = document.getElementById('dpAgendaAlertList');
    const banner = document.getElementById('dpAgendaUrgentBanner');

    if (!config || !root || !toggle || !count || !list) {
        return;
    }

    const runtimeKey = '__workAgendaAlertsRuntime';
    const previousRuntime = window[runtimeKey];

    if (previousRuntime && typeof previousRuntime.destroy === 'function') {
        previousRuntime.destroy();
    }

    const typeIcons = {
        overdue: 'fas fa-exclamation',
        urgent: 'fas fa-bolt',
        reminder: 'far fa-clock',
        upcoming: 'far fa-calendar-alt',
    };

    let previousTotal = null;
    let previousAlertIds = new Set();
    let lastBannerId = null;
    let lastResponseSignature = null;
    let alertsRequestInFlight = false;
    let refreshQueued = false;
    let pollingTimer = null;
    let activeRequestController = null;
    let isActive = true;

    const createElement = (tagName, className, text) => {
        const element = document.createElement(tagName);

        if (className) {
            element.className = className;
        }

        if (text !== undefined) {
            element.textContent = text;
        }

        return element;
    };

    const replayAnimation = (element, className, duration = 900) => {
        element.classList.remove(className);
        void element.offsetWidth;
        element.classList.add(className);
        window.setTimeout(() => element.classList.remove(className), duration);
    };

    const renderEmptyState = () => {
        const empty = createElement('div', 'dp-agenda-alert-empty');
        const icon = createElement('span');
        const title = createElement('strong', '', 'Todo al día');
        const description = createElement('small', '', 'No hay alertas activas.');

        icon.innerHTML = '<i class="far fa-bell-slash" aria-hidden="true"></i>';
        empty.append(icon, title, description);
        list.append(empty);
    };

    const renderAlertItem = (alert) => {
        const type = Object.prototype.hasOwnProperty.call(typeIcons, alert.type) ? alert.type : 'reminder';
        const item = createElement('a', `dp-agenda-alert-item is-${type}`);
        const icon = createElement('span', 'dp-agenda-alert-item-icon');
        const copy = createElement('span', 'dp-agenda-alert-copy');
        const topLine = createElement('span', 'dp-agenda-alert-topline');
        const label = createElement('b', 'dp-agenda-alert-label', alert.label || 'Alerta');
        const title = createElement('strong', '', alert.title || 'Actividad pendiente');
        const when = createElement('small');
        const view = createElement('span', 'dp-agenda-alert-view');

        item.href = alert.url;
        item.setAttribute('aria-label', `${alert.label || 'Alerta'}: ${alert.title || 'Actividad pendiente'}`);
        icon.innerHTML = `<i class="${typeIcons[type]}" aria-hidden="true"></i>`;
        topLine.append(label);

        if (alert.company) {
            topLine.append(createElement('span', 'dp-agenda-alert-company', alert.company));
        }

        when.innerHTML = '<i class="far fa-clock" aria-hidden="true"></i>';
        when.append(document.createTextNode(alert.when || 'Pendiente de atención'));
        view.innerHTML = '<i class="fas fa-chevron-right" aria-hidden="true"></i><span class="sr-only">Ver actividad</span>';
        copy.append(topLine, title, when);
        item.append(icon, copy, view);

        return item;
    };

    const renderBanner = (alert) => {
        if (!banner) {
            return;
        }

        const bannerId = alert ? String(alert.item_id || alert.assignment_id || '') : null;
        const isDismissed = alert && window.sessionStorage.getItem(`agenda-alert-banner-${bannerId}`) === '1';

        banner.classList.remove('is-overdue', 'is-urgent', 'is-reminder', 'is-upcoming');
        banner.classList.toggle('is-visible', Boolean(alert && !isDismissed));

        if (!alert) {
            banner.removeAttribute('data-alert-id');
            lastBannerId = null;
            return;
        }

        if (['overdue', 'urgent', 'reminder', 'upcoming'].includes(alert.type)) {
            banner.classList.add(`is-${alert.type}`);
        }

        banner.querySelector('[data-alert-title]').textContent = alert.title || 'Actividad pendiente';
        banner.querySelector('[data-alert-when]').textContent = alert.when || 'Requiere atención';
        banner.querySelector('[data-alert-link]').href = alert.url;
        banner.dataset.alertId = bannerId;
        banner.setAttribute('aria-label', `Alerta prioritaria: ${alert.title || 'Actividad pendiente'}`);

        if (!isDismissed && bannerId !== lastBannerId) {
            replayAnimation(banner, 'is-entering', 600);
        }

        lastBannerId = bannerId;
    };

    const render = (data) => {
        const alerts = Array.isArray(data.alerts) ? data.alerts : [];
        const total = Number.isFinite(Number(data.total)) ? Number(data.total) : Number(data.count) || 0;
        const visibleCount = total > 9 ? '9+' : String(total);
        const currentAlertIds = new Set(alerts.map((alert) => String(alert.assignment_id || alert.item_id || '')));
        const hasNewAlert = previousTotal !== null && [...currentAlertIds].some((id) => id && !previousAlertIds.has(id));
        const hasCriticalAlert = alerts.some((alert) => ['overdue', 'urgent'].includes(alert.type));

        root.classList.toggle('has-alerts', total > 0);
        root.classList.toggle('is-critical', hasCriticalAlert);
        count.classList.toggle('d-none', total === 0);
        count.textContent = visibleCount;

        if (headerCount) {
            headerCount.textContent = total > 99 ? '99+' : String(total);
        }

        if (buttonText) {
            buttonText.textContent = total > 0 ? `${visibleCount} pendiente${total === 1 ? '' : 's'}` : 'Alertas';
        }

        if (summary) {
            summary.textContent = total > 0
                ? `${total} alerta${total === 1 ? '' : 's'} pendiente${total === 1 ? '' : 's'}`
                : 'Sin alertas pendientes';
        }

        toggle.setAttribute(
            'aria-label',
            total > 0
                ? `Alertas de Agenda: ${total} pendiente${total === 1 ? '' : 's'}`
                : 'Alertas de Agenda: sin alertas pendientes',
        );
        toggle.title = total > 0
            ? `${total} alerta${total === 1 ? '' : 's'} pendiente${total === 1 ? '' : 's'}`
            : 'Sin alertas pendientes';

        if (previousTotal === null && total > 0) {
            replayAnimation(root, 'is-alert-entering');
        } else if (previousTotal !== null && previousTotal !== total) {
            replayAnimation(root, 'is-count-updating', 650);
        }

        if (hasNewAlert) {
            replayAnimation(root, 'has-new-alerts');
        }

        list.replaceChildren();

        if (alerts.length === 0) {
            renderEmptyState();
        } else {
            alerts.forEach((alert) => list.append(renderAlertItem(alert)));
        }

        renderBanner(data.banner || null);
        previousTotal = total;
        previousAlertIds = currentAlertIds;
    };

    const alertSignatureParts = (alert) => [
        alert?.assignment_id || null,
        alert?.item_id || null,
        alert?.type || null,
        alert?.classification || null,
        alert?.status || null,
        alert?.reminder_at || null,
        alert?.label || null,
        alert?.title || null,
        alert?.when || null,
        alert?.url || null,
        alert?.company || null,
    ];

    const responseSignature = (data) => JSON.stringify({
        total: Number.isFinite(Number(data.total)) ? Number(data.total) : Number(data.count) || 0,
        alerts: (Array.isArray(data.alerts) ? data.alerts : []).map(alertSignatureParts),
        banner: data.banner ? alertSignatureParts(data.banner) : null,
    });

    const setDropdownState = (isOpen) => {
        root.classList.toggle('is-open', isOpen);
        toggle.setAttribute('aria-expanded', String(isOpen));
    };

    if (window.jQuery) {
        window.jQuery(root)
            .off('.dpAgendaAlerts')
            .on('show.bs.dropdown.dpAgendaAlerts', () => setDropdownState(true))
            .on('hidden.bs.dropdown.dpAgendaAlerts', () => setDropdownState(false));
    }

    const bannerClose = banner?.querySelector('[data-alert-close]');
    const handleBannerClose = () => {
        const bannerId = banner.dataset.alertId;

        if (bannerId) {
            window.sessionStorage.setItem(`agenda-alert-banner-${bannerId}`, '1');
        }

        banner.classList.remove('is-visible', 'is-entering');
    };

    bannerClose?.addEventListener('click', handleBannerClose);

    const refresh = async (queueIfBusy = true) => {
        if (!isActive || alertsRequestInFlight) {
            if (queueIfBusy && isActive && alertsRequestInFlight) {
                refreshQueued = true;
            }

            return;
        }

        alertsRequestInFlight = true;
        activeRequestController = new AbortController();

        try {
            const response = await window.fetch(config.endpoint, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: activeRequestController.signal,
            });

            if (response.ok) {
                const data = await response.json();
                const signature = responseSignature(data);

                if (isActive && signature !== lastResponseSignature) {
                    render(data);
                    lastResponseSignature = signature;
                }
            }
        } catch (error) {
            // La próxima actualización automática vuelve a intentarlo.
        } finally {
            alertsRequestInFlight = false;
            activeRequestController = null;

            if (isActive && refreshQueued) {
                refreshQueued = false;
                refresh(false);
            }
        }
    };

    const stopPolling = () => {
        if (pollingTimer !== null) {
            window.clearInterval(pollingTimer);
            pollingTimer = null;
        }
    };

    const startPolling = () => {
        stopPolling();

        if (isActive && document.visibilityState === 'visible') {
            pollingTimer = window.setInterval(() => refresh(false), 5000);
        }
    };

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            startPolling();
            refresh(false);
            return;
        }

        stopPolling();
    };

    const handleFocus = () => {
        if (document.visibilityState === 'visible') {
            refresh(false);
        }
    };

    const handleAgendaChanged = () => refresh();
    const destroy = () => {
        isActive = false;
        stopPolling();
        activeRequestController?.abort();
        window.removeEventListener('focus', handleFocus);
        window.removeEventListener('work-agenda:changed', handleAgendaChanged);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
        bannerClose?.removeEventListener('click', handleBannerClose);

        if (window.jQuery) {
            window.jQuery(root).off('.dpAgendaAlerts');
        }

        if (window.refreshWorkAgendaAlerts === refresh) {
            delete window.refreshWorkAgendaAlerts;
        }
    };

    window[runtimeKey] = {destroy, refresh};
    window.refreshWorkAgendaAlerts = refresh;
    window.addEventListener('focus', handleFocus);
    window.addEventListener('work-agenda:changed', handleAgendaChanged);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    startPolling();

    if (document.visibilityState === 'visible') {
        refresh(false);
    }
});
