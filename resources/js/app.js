/**
 * Adds the shared Dropaiv table presentation without changing DataTables data,
 * events, permissions or module-specific render callbacks.
 */
function decorateAdminTables(scope = document) {
    const tables = Array.from(scope.querySelectorAll?.('.content-wrapper table.table, .content table.table, .modal table.table, table.table') || []);
    if (scope instanceof Element && scope.matches('table.table')) tables.unshift(scope);

    tables.forEach((table) => {
        table.classList.add('dp-table');

        const responsive = table.closest('.table-responsive');
        responsive?.classList.add('dp-table-wrap');

        const card = table.closest('.card');
        if (card && !table.closest('.modal')) {
            card.classList.add('dp-table-card');
        }

        const headers = Array.from(table.querySelectorAll('thead th'));
        headers.forEach((header, index) => {
            const label = (header.textContent || '').trim().toLocaleLowerCase('es');
            const cells = table.querySelectorAll(`tbody tr td:nth-child(${index + 1})`);

            cells.forEach((cell) => decorateAdminTableCell(cell, label));
        });

        table.querySelectorAll('tbody td:last-child').forEach((cell) => {
            const buttons = cell.querySelectorAll('a.btn, button.btn');
            if (!buttons.length) return;

            cell.querySelector('[class*="actions"]')?.classList.add('dp-actions');
            buttons.forEach(decorateAdminActionButton);
        });

        table.querySelectorAll('.badge').forEach(decorateAdminBadge);
    });
}

function decorateAdminTableCell(cell, label) {
    if (/nombre|raz[oó]n social|cliente|proveedor|art[ií]culo|empresa|descripci[oó]n/.test(label)) {
        cell.classList.add('dp-main-cell');
    }

    if (/c[oó]digo|n[uú]mero|^n[°º]$|documento|serie|correlativo|ruc|dni/.test(label)) {
        cell.classList.add('dp-code-cell');
        const plainChild = cell.children.length === 0;
        if (plainChild && cell.textContent.trim() && cell.textContent.trim() !== '-') {
            const value = cell.textContent.trim();
            cell.textContent = '';
            const badge = document.createElement('span');
            badge.className = 'dp-code-badge';
            badge.textContent = value;
            cell.appendChild(badge);
        }
    }

    if (/total|monto|precio|costo|saldo|importe/.test(label)) cell.classList.add('dp-money');
    if (/fecha|emisi[oó]n|vencimiento/.test(label)) cell.classList.add('dp-date-text');
}

function decorateAdminBadge(badge) {
    badge.classList.add('dp-chip');
    if (badge.classList.contains('badge-success')) badge.classList.add('dp-chip-success');
    else if (badge.classList.contains('badge-warning')) badge.classList.add('dp-chip-warning');
    else if (badge.classList.contains('badge-danger')) badge.classList.add('dp-chip-danger');
    else if (badge.classList.contains('badge-info') || badge.classList.contains('badge-primary')) badge.classList.add('dp-chip-info');
    else badge.classList.add('dp-chip-muted');
}

function decorateAdminActionButton(button) {
    button.classList.add('dp-action-btn');
    const hint = `${button.className} ${button.getAttribute('title') || ''}`.toLocaleLowerCase('es');

    if (/eliminar|delete|trash/.test(hint)) button.classList.add('dp-action-delete');
    else if (/anular|cancel/.test(hint)) button.classList.add('dp-action-cancel');
    else if (/editar|edit|pen/.test(hint)) button.classList.add('dp-action-edit');
    else if (/pdf/.test(hint)) button.classList.add('dp-action-pdf');
    else if (/imprimir|print/.test(hint)) button.classList.add('dp-action-print');
    else if (/banco|cuenta|bank/.test(hint)) button.classList.add('dp-action-bank');
    else button.classList.add('dp-action-view');
}

document.addEventListener('DOMContentLoaded', () => {
    decorateAdminTables();

    if (window.jQuery) {
        window.jQuery(document).on('draw.dt shown.bs.modal', function (event) {
            decorateAdminTables(event.target instanceof Element ? event.target : document);
        });
    }
});
