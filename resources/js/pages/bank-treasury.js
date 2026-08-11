$(function () {
    const routes = window.bankTreasuryRoutes || {};
    const permissions = window.bankTreasuryPermissions || {};
    const sources = window.bankTreasurySources || {};
    let selectedAccount = null;

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const table = $('#tableBankAccounts').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        ajax: {
            url: routes.list,
            data: data => Object.assign(data, {
                company_id: $('#bankFilterCompany').val(),
                currency_id: $('#bankFilterCurrency').val(),
                status: $('#bankFilterStatus').val(),
                date_from: $('#bankFilterFrom').val(),
                date_to: $('#bankFilterTo').val()
            }),
            dataSrc: json => {
                renderSummary(json.summary || {});
                return json.data || [];
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'bank', name: 'bank.description' },
            { data: 'company', name: 'company.business_name' },
            { data: 'currency', name: 'currency.code' },
            { data: 'account_holder' },
            { data: 'account_number' },
            { data: 'cci', defaultContent: '-' },
            { data: 'opening_balance', className: 'text-right text-nowrap' },
            { data: 'income', orderable: false, searchable: false, className: 'text-right text-nowrap text-success' },
            { data: 'expense', orderable: false, searchable: false, className: 'text-right text-nowrap text-danger' },
            { data: 'current_balance', className: 'text-right text-nowrap' },
            { data: 'status' },
            { data: 'actions', orderable: false, searchable: false, className: 'text-nowrap' }
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json' },
        drawCallback: () => window.decorateAdminTables?.(document)
    });

    $('#btnBankFilter').on('click', () => table.ajax.reload());
    $('#bankFilterCompany,#bankFilterCurrency,#bankFilterStatus').on('change', () => table.ajax.reload());
    $(document).on('change', '.custom-file-input', function () {
        $(this).siblings('.custom-file-label').text(this.files?.[0]?.name || 'Seleccionar archivo');
    });

    $(document).on('click', '.btn-bank-view', function () { loadAccount($(this).data('id'), true); });
    $(document).on('click', '.btn-bank-opening', function () { loadAccount($(this).data('id')).done(openOpeningModal); });
    $(document).on('click', '.btn-bank-income', function () { openMovementModal($(this).data('id'), 'IN'); });
    $(document).on('click', '.btn-bank-expense', function () { openMovementModal($(this).data('id'), 'OUT'); });
    $(document).on('click', '.btn-bank-transfer', function () { openTransferModal($(this).data('id')); });
    $(document).on('click', '.btn-bank-reconcile', function () { openReconciliationModal($(this).data('id')); });
    $(document).on('click', '.btn-bank-cancel-movement', function () { openCancel(`${routes.cancelMovement}/${$(this).data('id')}/cancel`, $(this).data('code')); });
    $(document).on('click', '.btn-bank-cancel-transfer', function () { openCancel(`${routes.cancelTransfer}/${$(this).data('id')}/cancel`, $(this).data('code')); });

    $('#bankOpeningForm').on('submit', function (event) {
        event.preventDefault();
        submitForm(this, `${routes.opening}/${$('#bankOpeningAccountId').val()}/opening-balance`, 'PUT', '#bankOpeningModal');
    });
    $('#bankMovementForm').on('submit', function (event) {
        event.preventDefault();
        submitForm(this, routes.movements, 'POST', '#bankMovementModal');
    });
    $('#bankTransferForm').on('submit', function (event) {
        event.preventDefault();
        submitForm(this, routes.transfers, 'POST', '#bankTransferModal');
    });
    $('#bankReconciliationForm').on('submit', function (event) {
        event.preventDefault();
        submitForm(this, routes.reconciliations, 'POST', '#bankReconciliationModal');
    });
    $('#bankCancelForm').on('submit', function (event) {
        event.preventDefault();
        submitForm(this, $('#bankCancelUrl').val(), 'POST', '#bankCancelModal');
    });
    $('#bankMovementCategory').on('change', renderMovementSources);
    $('#bankMovementAccount').on('change', renderMovementSources);
    $('#bankReconcilePeriod').on('change', setReconciliationPeriodDates);
    $('#btnLoadReconciliationMovements').on('click', loadReconciliationMovements);

    function submitForm(form, url, method, modal) {
        const button = $(form).find('[type="submit"]');
        const formData = new FormData(form);
        if (method !== 'POST') formData.append('_method', method);
        button.prop('disabled', true);
        clearErrors(form);
        $.ajax({ url, method: 'POST', data: formData, processData: false, contentType: false })
            .done(response => {
                $(modal).modal('hide');
                Swal.fire({ icon: 'success', title: 'Operación completada', text: response.message, timer: 1800, showConfirmButton: false });
                table.ajax.reload(null, false);
                if (selectedAccount?.id) loadAccount(selectedAccount.id, $('#bankDetailModal').hasClass('show'));
            })
            .fail(xhr => showErrors(form, xhr))
            .always(() => button.prop('disabled', false));
    }

    function loadAccount(id, showModal = false) {
        const request = $.get(`${routes.show}/${id}`).done(response => {
            selectedAccount = response.data.account;
            renderDetail(response.data);
            if (showModal) {
                $('#bankDetailModal .nav-link').first().tab('show');
                $('#bankDetailModal').modal('show');
            }
        }).fail(showAjaxError);
        return request;
    }

    function renderSummary(summary) {
        $('#bankSummaryTotal').text(money(summary.total_banks_pen, 'S/'));
        $('#bankSummaryIncome').text(money(summary.period_income_pen, 'S/'));
        $('#bankSummaryExpense').text(money(summary.period_expense_pen, 'S/'));
        $('#bankSummaryAvailable').text(money(summary.available_balance_pen, 'S/'));
        $('#bankSummaryPending').text(Number(summary.pending_reconciliation || 0).toLocaleString('es-PE'));
    }

    function renderDetail(data) {
        const account = data.account;
        const symbol = account.currency?.symbol || account.currency?.code || '';
        $('#bankDetailTitle').text(account.bank?.short_name || account.bank?.description || 'Cuenta bancaria');
        $('#bankDetailSubtitle').text(`${account.company?.trade_name || account.company?.business_name || ''} · ${account.account_number}`);
        $('#bankTabSummary').html(`<div class="bank-detail-kpis">
            ${kpi('Banco', account.bank?.description, 'fa-university')}${kpi('Empresa', account.company?.trade_name || account.company?.business_name, 'fa-building')}${kpi('Moneda', account.currency?.description || account.currency?.code, 'fa-coins')}${kpi('Estado', account.status === 'ACTIVE' ? 'ACTIVA' : 'INACTIVA', 'fa-toggle-on')}
            ${kpi('Titular', account.account_holder, 'fa-user')}${kpi('Nro. cuenta', account.account_number, 'fa-hashtag')}${kpi('CCI', account.cci || '-', 'fa-barcode')}${kpi('Último movimiento', formatDate(account.last_movement_at), 'fa-clock')}
            ${kpi('Saldo inicial', money(account.opening_balance, symbol), 'fa-flag')}${kpi('Ingresos', money(sum(data.movements, 'IN'), symbol), 'fa-arrow-down')}${kpi('Egresos', money(sum(data.movements, 'OUT'), symbol), 'fa-arrow-up')}${kpi('Saldo actual', money(account.current_balance, symbol), 'fa-wallet')}
        </div>`);
        $('#bankTabMovements').html(renderMovements(data.movements, symbol));
        $('#bankTabTransfers').html(renderTransfers(data.transfers));
        $('#bankTabReconciliations').html(renderReconciliations(data.reconciliations, symbol));
        $('#bankTabTrace').html(renderTrace(data.trace));
        $('#bankDetailExports').html(permissions.export ? `<div class="btn-group btn-group-sm"><a class="btn btn-outline-success" href="${routes.exportAccount}/${account.id}/export/excel"><i class="fas fa-file-excel"></i> Excel</a><a class="btn btn-outline-danger" target="_blank" href="${routes.exportAccount}/${account.id}/export/pdf"><i class="fas fa-file-pdf"></i> PDF</a><a class="btn btn-outline-secondary" target="_blank" href="${routes.exportAccount}/${account.id}/export/print"><i class="fas fa-print"></i> Imprimir</a></div>` : '');
    }

    function renderMovements(rows, symbol) {
        if (!rows.length) return empty('Sin movimientos bancarios registrados.');
        return `<div class="table-responsive"><table class="table table-hover table-sm bank-detail-table"><thead><tr><th>Fecha</th><th>Código</th><th>Tipo</th><th>Concepto</th><th>Origen</th><th class="text-right">Ingreso</th><th class="text-right">Egreso</th><th class="text-right">Saldo</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>${rows.map(row => `<tr><td>${formatDate(row.movement_date)}</td><td><strong>${escapeHtml(row.code)}</strong></td><td>${escapeHtml(row.type_label)}</td><td>${escapeHtml(row.concept)}</td><td>${escapeHtml(row.source_label)}<small class="d-block text-muted">${escapeHtml(row.source_code || '')}</small></td><td class="text-right text-success">${row.direction === 'IN' ? money(row.amount, symbol) : ''}</td><td class="text-right text-danger">${row.direction === 'OUT' ? money(row.amount, symbol) : ''}</td><td class="text-right font-weight-bold">${money(row.balance_after, symbol)}</td><td><span class="bank-status ${row.status}">${row.status}</span></td><td>${movementActions(row)}</td></tr>`).join('')}</tbody></table></div>`;
    }

    function movementActions(row) {
        const view = row.file_url ? `<a href="${row.file_url}" target="_blank" class="btn btn-outline-info btn-xs" title="Ver sustento"><i class="fas fa-eye"></i></a>` : '';
        const cancel = permissions.cancel && row.status === 'REGISTRADO' && row.movement_type !== 'REVERSA' ? `<button class="btn btn-outline-danger btn-xs btn-bank-cancel-movement" data-id="${row.id}" data-code="${escapeHtml(row.code)}" title="Anular"><i class="fas fa-ban"></i></button>` : '';
        return `<div class="btn-group btn-group-sm">${view}${cancel}</div>` || '-';
    }

    function renderTransfers(rows) {
        if (!rows.length) return empty('Sin transferencias relacionadas.');
        return `<div class="table-responsive"><table class="table table-hover table-sm bank-detail-table"><thead><tr><th>Fecha</th><th>Código</th><th>Origen</th><th>Destino</th><th class="text-right">Enviado</th><th class="text-right">Recibido</th><th>Operación</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>${rows.map(row => `<tr><td>${formatDate(row.transfer_date)}</td><td><strong>${escapeHtml(row.code)}</strong></td><td>${accountName(row.from_account)}</td><td>${accountName(row.to_account)}</td><td class="text-right">${money(row.amount, row.currency?.symbol || row.currency?.code)}</td><td class="text-right">${money(row.destination_amount, row.destination_currency?.symbol || row.destination_currency?.code)}</td><td>${escapeHtml(row.operation_number || '-')}</td><td><span class="bank-status ${row.status}">${row.status}</span></td><td>${transferActions(row)}</td></tr>`).join('')}</tbody></table></div>`;
    }

    function transferActions(row) {
        const view = row.file_url ? `<a href="${row.file_url}" target="_blank" class="btn btn-outline-info btn-xs"><i class="fas fa-eye"></i></a>` : '';
        const cancel = permissions.cancel && row.status === 'REGISTRADO' ? `<button class="btn btn-outline-danger btn-xs btn-bank-cancel-transfer" data-id="${row.id}" data-code="${escapeHtml(row.code)}"><i class="fas fa-ban"></i></button>` : '';
        return `<div class="btn-group btn-group-sm">${view}${cancel}</div>`;
    }

    function renderReconciliations(rows, symbol) {
        if (!rows.length) return empty('Sin conciliaciones registradas.');
        return `<div class="table-responsive"><table class="table table-hover table-sm bank-detail-table"><thead><tr><th>Periodo</th><th>Código</th><th>Desde</th><th>Hasta</th><th class="text-right">Sistema</th><th class="text-right">Banco</th><th class="text-right">Diferencia</th><th>Movimientos</th><th>Estado</th><th>Sustento</th></tr></thead><tbody>${rows.map(row => `<tr><td>${escapeHtml(row.period)}</td><td><strong>${escapeHtml(row.code)}</strong></td><td>${formatDate(row.start_date, false)}</td><td>${formatDate(row.end_date, false)}</td><td class="text-right">${money(row.system_balance, symbol)}</td><td class="text-right">${money(row.bank_statement_balance, symbol)}</td><td class="text-right">${money(row.difference, symbol)}</td><td>${row.details_count}</td><td><span class="bank-status ${row.status}">${row.status}</span></td><td>${row.file_url ? `<a href="${row.file_url}" target="_blank" class="btn btn-outline-info btn-xs"><i class="fas fa-eye"></i></a>` : '-'}</td></tr>`).join('')}</tbody></table></div>`;
    }

    function renderTrace(rows) {
        if (!rows.length) return empty('Sin eventos de trazabilidad adicionales.');
        return `<div>${rows.map(row => `<div class="bank-trace-item"><span><i class="fas ${escapeHtml(row.icon || 'fa-history')}"></i></span><div><strong>${escapeHtml(row.title)}</strong><small>${formatDate(row.date)} · ${escapeHtml(row.detail || '')}</small></div></div>`).join('')}</div>`;
    }

    function openOpeningModal() {
        if (!selectedAccount) return;
        $('#bankOpeningForm')[0].reset();
        $('#bankOpeningAccountId').val(selectedAccount.id);
        $('#bankOpeningAccountLabel').text(`${selectedAccount.bank?.description || ''} · ${selectedAccount.account_number}`);
        $('#bankOpeningAmount').val(selectedAccount.opening_balance || 0);
        $('#bankOpeningDate').val(String(selectedAccount.opening_balance_date || '').slice(0, 10) || today());
        $('#bankOpeningObservation').val(selectedAccount.opening_balance_observation || '');
        $('#bankOpeningModal .bank-exchange-group').toggle(selectedAccount.currency?.code !== 'PEN');
        $('#bankOpeningModal').modal('show');
    }

    function openMovementModal(accountId, direction) {
        const form = $('#bankMovementForm')[0]; form.reset();
        $('#bankMovementDirection').val(direction);
        $('#bankMovementAccount').val(accountId || '');
        $('#bankMovementDate').val(nowLocal());
        $('#bankMovementTitle').text(direction === 'IN' ? 'Registrar ingreso bancario' : 'Registrar egreso bancario');
        const categories = direction === 'IN' ? [
            ['CUSTOMER_PAYMENT','Cobro de cliente'],['DEPOSIT','Depósito'],['ADJUSTMENT_POSITIVE','Ajuste positivo'],['OTHER_INCOME','Otro ingreso']
        ] : [
            ['SUPPLIER_PAYMENT','Pago a proveedor'],['SUPPLIER_ADVANCE','Anticipo a proveedor'],['PETTY_CASH_OPENING','Apertura de caja chica'],['PETTY_CASH_REPLENISHMENT','Reposición de caja chica'],['WAREHOUSE_ENTRY_EXPENSE','Costo de almacén pagado desde banco'],['BANK_FEE','Gasto bancario'],['ADJUSTMENT_NEGATIVE','Ajuste negativo'],['OTHER_EXPENSE','Otro egreso']
        ];
        $('#bankMovementCategory').html('<option value="">Seleccione</option>'+categories.map(item => `<option value="${item[0]}">${item[1]}</option>`).join(''));
        renderMovementSources();
        $('#bankMovementModal').modal('show');
    }

    function renderMovementSources() {
        const category = $('#bankMovementCategory').val();
        const companyId = Number($('#bankMovementAccount option:selected').data('company'));
        let rows = [], label = 'Origen vinculado';
        if (category === 'CUSTOMER_PAYMENT') { rows = sources.customerOrders || []; label = 'OC del cliente'; }
        if (category === 'SUPPLIER_PAYMENT' || category === 'SUPPLIER_ADVANCE') { rows = sources.supplierOrders || []; label = 'OC proveedor'; }
        if (category === 'PETTY_CASH_OPENING') { rows = sources.pettyCashBoxes || []; label = 'Caja chica'; }
        if (category === 'PETTY_CASH_REPLENISHMENT') { rows = sources.pettyCashReplenishments || []; label = 'Reposición'; }
        if (category === 'WAREHOUSE_ENTRY_EXPENSE') { rows = sources.warehouseExpenses || []; label = 'Costo vinculado de almacén'; }
        rows = rows.filter(row => Number(row.company_id || row.fund_source_company_id || row.warehouse_entry?.company_id) === companyId);
        $('#bankMovementSourceGroup label').text(label.toUpperCase());
        $('#bankMovementSource').html('<option value="">Seleccione</option>'+rows.map(row => `<option value="${row.id}">${escapeHtml(row.purchase_order_number || row.code || row.warehouse_entry?.entry_number || `#${row.id}`)}${row.description ? ` · ${escapeHtml(row.description)}` : ''}</option>`).join(''));
        $('#bankMovementSourceGroup').toggle(rows.length > 0 || ['CUSTOMER_PAYMENT','SUPPLIER_PAYMENT','SUPPLIER_ADVANCE','PETTY_CASH_OPENING','PETTY_CASH_REPLENISHMENT','WAREHOUSE_ENTRY_EXPENSE'].includes(category));
        $('#bankMovementConcept').val($('#bankMovementCategory option:selected').text() || '');
        const currency = $('#bankMovementAccount option:selected').data('currency');
        $('#bankMovementModal .bank-exchange-group').toggle(currency && currency !== 'PEN');
    }

    function openTransferModal(accountId) {
        $('#bankTransferForm')[0].reset();
        $('#bankTransferFrom').val(accountId || '');
        $('#bankTransferDate').val(nowLocal());
        filterTransferDestinations();
        $('#bankTransferModal').modal('show');
    }
    $('#bankTransferFrom').on('change', filterTransferDestinations);
    function filterTransferDestinations() {
        const origin = $('#bankTransferFrom option:selected');
        const company = origin.data('company');
        const id = String(origin.val() || '');
        $('#bankTransferTo option').each(function () {
            const visible = !this.value || (String($(this).data('company')) === String(company) && String(this.value) !== id);
            $(this).prop('disabled', !visible).toggle(visible);
        });
        if ($('#bankTransferTo option:selected').prop('disabled')) $('#bankTransferTo').val('');
    }

    function openReconciliationModal(accountId) {
        $('#bankReconciliationForm')[0].reset();
        $('#bankReconcileAccount').val(accountId || '');
        $('#bankReconcilePeriod').val(today().slice(0, 7));
        setReconciliationPeriodDates();
        $('#bankReconciliationMovements').html('<div class="bank-empty">Presione actualizar para cargar movimientos pendientes.</div>');
        $('#bankReconciliationModal').modal('show');
    }

    function setReconciliationPeriodDates() {
        const period = $('#bankReconcilePeriod').val();
        if (!period) return;
        const [year, month] = period.split('-').map(Number);
        $('#bankReconcileFrom').val(`${year}-${String(month).padStart(2,'0')}-01`);
        $('#bankReconcileTo').val(`${year}-${String(month).padStart(2,'0')}-${String(new Date(year, month, 0).getDate()).padStart(2,'0')}`);
    }

    function loadReconciliationMovements() {
        const account = $('#bankReconcileAccount').val(), from = $('#bankReconcileFrom').val(), to = $('#bankReconcileTo').val();
        if (!account || !from || !to) return Swal.fire('Datos incompletos', 'Seleccione cuenta y rango de fechas.', 'warning');
        $('#bankReconciliationMovements').html('<div class="bank-empty"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>');
        $.get(`${routes.show}/${account}/movements/available`, { start_date: from, end_date: to })
            .done(response => {
                const rows = response.data || [];
                if (!rows.length) return $('#bankReconciliationMovements').html('<div class="bank-empty">No existen movimientos pendientes en este periodo.</div>');
                $('#bankReconciliationMovements').html(`<table class="table table-sm mb-0 bank-detail-table"><thead><tr><th><input type="checkbox" id="bankReconcileAll" checked></th><th>Fecha</th><th>Código</th><th>Tipo</th><th>Origen</th><th class="text-right">Ingreso</th><th class="text-right">Egreso</th></tr></thead><tbody>${rows.map(row => `<tr><td><input type="checkbox" class="bank-reconcile-check" name="movement_ids[]" value="${row.id}" checked></td><td>${formatDate(row.movement_date)}</td><td>${escapeHtml(row.code)}</td><td>${escapeHtml(row.type_label)}</td><td>${escapeHtml(row.source_label)} ${escapeHtml(row.source_code || '')}</td><td class="text-right text-success">${row.direction==='IN'?Number(row.amount).toFixed(2):''}</td><td class="text-right text-danger">${row.direction==='OUT'?Number(row.amount).toFixed(2):''}</td></tr>`).join('')}</tbody></table>`);
            }).fail(showAjaxError);
    }
    $(document).on('change', '#bankReconcileAll', function () { $('.bank-reconcile-check').prop('checked', this.checked); });

    function openCancel(url, code) {
        $('#bankCancelForm')[0].reset();
        $('#bankCancelUrl').val(url);
        $('#bankCancelLabel').text(code || 'Movimiento bancario');
        $('#bankCancelModal').modal('show');
    }

    function kpi(label, value, icon) { return `<div class="bank-detail-kpi"><small><i class="fas ${icon} mr-1"></i>${label}</small><strong>${escapeHtml(value || '-')}</strong></div>`; }
    function empty(text) { return `<div class="bank-empty"><i class="fas fa-inbox d-block mb-2"></i>${text}</div>`; }
    function sum(rows, direction) { return (rows || []).filter(row => row.direction === direction).reduce((total, row) => total + Number(row.amount || 0), 0); }
    function accountName(account) { return `${escapeHtml(account?.bank?.short_name || account?.bank?.description || '')}<small class="d-block text-muted">${escapeHtml(account?.account_number || '')}</small>`; }
    function money(value, symbol = '') { return `${symbol || ''} ${Number(value || 0).toLocaleString('es-PE',{minimumFractionDigits:2,maximumFractionDigits:2})}`.trim(); }
    function today() { return new Date().toISOString().slice(0,10); }
    function nowLocal() { const date = new Date(); date.setMinutes(date.getMinutes()-date.getTimezoneOffset()); return date.toISOString().slice(0,16); }
    function formatDate(value, time = true) { if (!value) return '-'; const date = new Date(value); if (Number.isNaN(date.getTime())) return String(value).slice(0,10); return date.toLocaleString('es-PE', time ? {dateStyle:'short',timeStyle:'short'} : {dateStyle:'short'}); }
    function escapeHtml(value) { return $('<div>').text(value ?? '').html(); }
    function clearErrors(form) { $(form).find('.is-invalid').removeClass('is-invalid'); $(form).find('.invalid-feedback.bank-error').remove(); }
    function showErrors(form, xhr) {
        const errors = xhr.responseJSON?.errors || {};
        Object.entries(errors).forEach(([key, messages]) => {
            const name = key.replace(/\.\d+\./g, '[0][').replace(/$/,'');
            const field = $(form).find(`[name="${key}"],[name="${name}"]`).first();
            field.addClass('is-invalid').after(`<div class="invalid-feedback bank-error">${escapeHtml(messages[0])}</div>`);
        });
        Swal.fire('No se pudo completar', xhr.responseJSON?.message || Object.values(errors).flat()[0] || 'Revise los datos ingresados.', 'error');
    }
    function showAjaxError(xhr) { Swal.fire('No se pudo completar', xhr.responseJSON?.message || 'Ocurrió un error al procesar la solicitud.', 'error'); }
});
