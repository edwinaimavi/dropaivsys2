$(function () {
    const app = $('#pettyCashApp');
    if (!app.length) return;

    const base = app.data('base-url');
    const csrf = $('meta[name="csrf-token"]').attr('content');
    let currentBox = null;
    let table;
    const dniRequests = new Map();
    const dniTimers = {};

    const money = (value, symbol = '') => `${symbol || ''} ${Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();
    const date = value => value ? String(value).slice(0, 10).split('-').reverse().join('/') : '-';
    const escapeHtml = value => $('<div>').text(value ?? '').html();
    const notify = (icon, title) => window.Swal ? Swal.fire({ icon, title, confirmButtonColor: '#20765c' }) : alert(title);
    const errorMessage = xhr => xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {}).flat()[0] || 'No fue posible completar la operación.';
    const loading = (form, active) => form.find('[type="submit"]').prop('disabled', active).find('i').toggleClass('fa-spin', active);

    const api = (options) => $.ajax({ headers: { 'X-CSRF-TOKEN': csrf }, ...options });
    const loadBox = id => api({ url: `${base}/${id}`, method: 'GET' }).then(response => response.data);

    const personName = data => [data.nombres, data.apellidoPaterno, data.apellidoMaterno]
        .filter(Boolean).join(' ').trim().toLocaleUpperCase('es-PE');

    const showDniNotFound = message => {
        if (window.Swal) {
            Swal.fire({
                toast: true, position: 'top-end', timer: 3500, showConfirmButton: false,
                icon: 'info', title: message || 'No se encontró información para este DNI.'
            });
        }
    };

    const searchDni = (inputSelector, nameSelector, loadingSelector) => {
        const dni = $(inputSelector).val().replace(/\D/g, '').slice(0, 8);
        $(inputSelector).val(dni);
        if (dni.length !== 8) return;

        const previousDni = $(inputSelector).data('last-dni');
        if (previousDni === dni) return;
        $(inputSelector).data('last-dni', dni);
        $(loadingSelector).removeClass('d-none');

        const request = dniRequests.has(dni)
            ? $.Deferred().resolve(dniRequests.get(dni)).promise()
            : api({ url: String(app.data('dni-url')).replace('DNI_PLACEHOLDER', dni), method: 'GET' });

        request.done(response => {
            if (!response.status || response.type !== 'DNI') {
                showDniNotFound(response.message);
                return;
            }
            dniRequests.set(dni, response);
            const name = personName(response.data || {});
            if (name) $(nameSelector).val(name);
            else showDniNotFound();
        }).fail(xhr => {
            $(inputSelector).removeData('last-dni');
            showDniNotFound(xhr.responseJSON?.message || 'No se encontró información para este DNI.');
        }).always(() => $(loadingSelector).addClass('d-none'));
    };

    const bindDniSearch = (inputSelector, nameSelector, loadingSelector) => {
        $(document).on('input', inputSelector, function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 8);
            clearTimeout(dniTimers[inputSelector]);
            if (this.value.length === 8) {
                dniTimers[inputSelector] = setTimeout(() => searchDni(inputSelector, nameSelector, loadingSelector), 250);
            } else {
                $(this).removeData('last-dni');
            }
        });
        $(document).on('blur', inputSelector, () => searchDni(inputSelector, nameSelector, loadingSelector));
        $(document).on('keydown', inputSelector, function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                clearTimeout(dniTimers[inputSelector]);
                searchDni(inputSelector, nameSelector, loadingSelector);
            }
        });
    };

    bindDniSearch('#responsible_dni', '#responsible_name', '#responsible_dni_loading');
    bindDniSearch('#supervisor_dni', '#supervisor_name', '#supervisor_dni_loading');

    table = $('#tablePettyCash').DataTable({
        processing: true, serverSide: true, responsive: false,
        dom: "<'row mb-3'<'col-md-6'l><'col-md-6'f>>rt<'row mt-3'<'col-md-5'i><'col-md-7'p>><'row mt-2'<'col-12'B>>",
        buttons: [
            { extend: 'excelHtml5', text: '<i class="fas fa-file-excel mr-1"></i> Excel', className: 'btn btn-success btn-sm' },
            { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf mr-1"></i> PDF', className: 'btn btn-danger btn-sm', orientation: 'landscape' },
            { extend: 'print', text: '<i class="fas fa-print mr-1"></i> Imprimir', className: 'btn btn-secondary btn-sm' }
        ],
        ajax: app.data('list-url'),
        order: [[1, 'desc']],
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' }, { data: 'code' }, { data: 'company', name: 'company.business_name' },
            { data: 'period', orderable: false }, { data: 'start_date' }, { data: 'end_date' },
            { data: 'approved_fund' }, { data: 'total_expenses' }, { data: 'cash_balance' },
            { data: 'reimbursement_amount' }, { data: 'status' }, { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' },
        drawCallback: function (settings) {
            const summary = settings.json?.summary || {};
            $('#pcKpiOpen').text(summary.active_boxes || 0);
            $('#pcKpiFund').text(money(summary.visible_fund));
            $('#pcKpiSpent').text(money(summary.total_spent));
            $('#pcKpiPending').text(money(summary.pending_replenishment));
            $('#tablePettyCash [data-toggle="tooltip"]').tooltip();
        }
    });

    $(document).on('click', '#btnCreatePettyCash', function () {
        const form = $('#pettyCashForm')[0];
        form.reset();
        $('#responsible_dni,#supervisor_dni').removeData('last-dni');
        $('#petty_cash_id').val('');
        $('#pc_period_year').val(new Date().getFullYear());
        $('#pc_period_month').val(new Date().getMonth() + 1);
        $('#pc_side_code').text('Se generará al guardar');
        $('#pc_side_status').text('ABIERTA');
        $('#pc_side_fund,#pc_side_expenses,#pc_side_balance').text('0.00');
        $('#pettyCashModalLabel').text('Aperturar caja chica');
        $('#btnSavePettyCash span').text('Guardar Caja');
        $('#pettyCashModal').modal('show');
    });

    $('#pc_approved_fund').on('input', function () {
        $('#pc_side_fund,#pc_side_balance').text(money(this.value));
    });

    $('#pettyCashForm').on('submit', function (event) {
        event.preventDefault();
        const id = $('#petty_cash_id').val();
        const data = new FormData(this);
        if (id) data.append('_method', 'PUT');
        loading($(this), true);
        api({ url: id ? `${base}/${id}` : base, method: 'POST', data, processData: false, contentType: false })
            .done(response => { $('#pettyCashModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); })
            .fail(xhr => notify('error', errorMessage(xhr)))
            .always(() => loading($(this), false));
    });

    $(document).on('click', '.editPettyCash', function () {
        loadBox($(this).data('id')).done(box => {
            currentBox = box;
            $('#pettyCashForm')[0].reset();
            $('#petty_cash_id').val(box.id);
            ['company_id','currency_id','period_month','period_year','periodicity','start_date','end_date','approved_fund','observations']
                .forEach(field => $(`#pc_${field}`).val(String(box[field] ?? '').slice(0, field.includes('date') ? 10 : undefined)));
            $('#responsible_dni').val(box.responsible_dni).data('last-dni', box.responsible_dni);
            $('#responsible_name').val(box.responsible_name);
            $('#supervisor_dni').val(box.supervisor_dni).data('last-dni', box.supervisor_dni);
            $('#supervisor_name').val(box.supervisor_name);
            $('#pc_side_code').text(box.code); $('#pc_side_status').text(box.status_label);
            $('#pc_side_fund').text(money(box.approved_fund)); $('#pc_side_expenses').text(money(box.total_expenses)); $('#pc_side_balance').text(money(box.cash_balance));
            $('#pettyCashModalLabel').text('Editar caja chica'); $('#btnSavePettyCash span').text('Actualizar Caja'); $('#pettyCashModal').modal('show');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    const renderDetail = box => {
        currentBox = box;
        const symbol = box.currency?.symbol || '';
        $('#pcv_code').text(`${box.code} · ${box.status_label}`);
        $('#pcv_company').text(box.company?.trade_name || box.company?.business_name || '-');
        $('#pcv_summary').html([
            ['Fondo aprobado', money(box.approved_fund, symbol)], ['Total gastado', money(box.total_expenses, symbol)],
            ['Total repuesto', money(box.replenished_total, symbol)], ['Saldo actual', money(box.cash_balance, symbol)],
            ['Pendiente reposición', money(box.reimbursement_amount, symbol)]
        ].map(item => `<div class="petty-summary-item"><small>${item[0]}</small><strong>${item[1]}</strong></div>`).join(''));
        $('#pcv_responsibles').html(`<div class="col-md-6"><b>Responsable</b><p>${escapeHtml(box.responsible_name)} · DNI ${escapeHtml(box.responsible_dni)}</p></div><div class="col-md-6"><b>Supervisor</b><p>${escapeHtml(box.supervisor_name)} · DNI ${escapeHtml(box.supervisor_dni)}</p></div>`);
        $('#pcv_expenses').html(box.expenses.length ? box.expenses.map(expense => {
            const docs = (expense.documents || []).map(doc => `<a target="_blank" href="${doc.view_url}" class="btn btn-xs btn-outline-info"><i class="fas fa-paperclip"></i></a>`).join('') || '-';
            const actions = box.can_manage_expenses ? `${app.data('can-expense-update') ? `<button class="btn btn-xs btn-warning editPettyCashExpense" data-id="${expense.id}"><i class="fas fa-edit"></i></button>` : ''}${app.data('can-expense-delete') ? `<button class="btn btn-xs btn-danger deletePettyCashExpense" data-id="${expense.id}"><i class="fas fa-trash"></i></button>` : ''}` : '';
            return `<tr><td>${expense.item_number}</td><td>${date(expense.expense_date)}</td><td>${escapeHtml(`${expense.document_type || ''} ${expense.document_number || ''}`)}</td><td>${escapeHtml(expense.supplier_ruc || '-')}</td><td>${escapeHtml(expense.supplier_name)}</td><td>${escapeHtml(expense.concept)}</td><td class="text-right">${money(expense.amount, symbol)}</td><td>${docs}</td><td>${actions}</td></tr>`;
        }).join('') : '<tr><td colspan="9" class="text-center text-muted py-3">Sin gastos registrados.</td></tr>');
        $('#pcv_replenishments').html(box.replenishments.length ? box.replenishments.map(item => `<tr><td>${date(item.replenishment_date)}</td><td class="text-right">${money(item.amount, symbol)}</td><td>${escapeHtml(item.payment_method || '-')}</td><td>${escapeHtml(item.reference_number || '-')}</td><td>${escapeHtml(item.observation || '-')}</td><td>${(item.documents || []).map(doc => `<a target="_blank" href="${doc.view_url}" class="btn btn-xs btn-outline-info"><i class="fas fa-paperclip"></i></a>`).join('') || '-'}</td><td><span class="badge badge-success">${escapeHtml(item.status || 'ACTIVE')}</span></td><td>-</td></tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted">Sin reposiciones.</td></tr>');
    };

    $(document).on('click', '.viewPettyCash', function () {
        loadBox($(this).data('id')).done(box => { renderDetail(box); $('#viewPettyCashModal').modal('show'); }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    $(document).on('click', '.addPettyCashExpense', function () {
        $('#pettyCashExpenseForm')[0].reset(); $('#pc_expense_id').val(''); $('#pc_expense_box_id').val($(this).data('id'));
        $('#pcExpenseTitle').text('Registrar gasto'); $('#pettyCashExpenseModal').modal('show');
    });

    $(document).on('click', '.editPettyCashExpense', function () {
        const expense = currentBox?.expenses?.find(item => Number(item.id) === Number($(this).data('id')));
        if (!expense) return;
        $('#pettyCashExpenseForm')[0].reset(); $('#pc_expense_id').val(expense.id); $('#pc_expense_box_id').val(currentBox.id);
        ['expense_date','document_type','document_number','supplier_ruc','supplier_name','concept','amount','observation'].forEach(field => $(`#pce_${field}`).val(String(expense[field] ?? '').slice(0, field === 'expense_date' ? 10 : undefined)));
        $('#pcExpenseTitle').text('Editar gasto'); $('#pettyCashExpenseModal').modal('show');
    });

    $('#pce_supplier_ruc').on('blur', function () {
        const ruc = this.value.trim(); if (ruc.length !== 11) return;
        api({ url: `${app.data('ruc-url')}/${ruc}`, method: 'GET' }).done(response => {
            const data = response.data || response;
            $('#pce_supplier_name').val(data.razonSocial || data.razon_social || data.nombre_o_razon_social || data.name || '');
        });
    });

    $('#pettyCashExpenseForm').on('submit', function (event) {
        event.preventDefault();
        const id = $('#pc_expense_id').val(), boxId = $('#pc_expense_box_id').val(), data = new FormData(this);
        if (id) data.append('_method', 'PUT');
        loading($(this), true);
        api({ url: id ? `${base}/expenses/${id}` : `${base}/${boxId}/expenses`, method: 'POST', data, processData: false, contentType: false })
            .done(response => { $('#pettyCashExpenseModal,#viewPettyCashModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); })
            .fail(xhr => notify('error', errorMessage(xhr))).always(() => loading($(this), false));
    });

    $(document).on('click', '.deletePettyCashExpense', function () {
        const id = $(this).data('id');
        const run = () => api({ url: `${base}/expenses/${id}`, method: 'DELETE' }).done(response => { $('#viewPettyCashModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); }).fail(xhr => notify('error', errorMessage(xhr)));
        Swal.fire({ icon: 'warning', title: '¿Eliminar este gasto?', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' }).then(result => result.isConfirmed && run());
    });

    $(document).on('click', '.closePettyCash', function () {
        loadBox($(this).data('id')).done(box => {
            const symbol = box.currency?.symbol || '';
            $('#pcc_box_id').val(box.id);
            $('#pcc_summary').html([
                ['Fondo aprobado', money(box.approved_fund, symbol)], ['Total gastado', money(box.total_expenses, symbol)],
                ['Saldo efectivo', money(box.cash_balance, symbol)], ['Monto a reponer', money(box.reimbursement_amount, symbol)]
            ].map(item => `<div class="petty-summary-item"><small>${item[0]}</small><strong>${item[1]}</strong></div>`).join(''));
            $('#pettyCashCloseModal').modal('show');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    $('#btnConfirmClosePettyCash').on('click', function () {
        const button = $(this).prop('disabled', true);
        api({ url: `${base}/${$('#pcc_box_id').val()}/close`, method: 'POST' })
            .done(response => { $('#pettyCashCloseModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); })
            .fail(xhr => notify('error', errorMessage(xhr))).always(() => button.prop('disabled', false));
    });

    $(document).on('click', '.deletePettyCash', function () {
        const id = $(this).data('id');
        Swal.fire({ icon: 'warning', title: '¿Anular la caja chica?', text: 'Esta acción retirará la caja de la operación activa.', showCancelButton: true, confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' })
            .then(result => result.isConfirmed && api({ url: `${base}/${id}`, method: 'DELETE' }).done(response => { table.ajax.reload(null, false); notify('success', response.message); }).fail(xhr => notify('error', errorMessage(xhr))));
    });

    $(document).off('click', '.btn-replenish-petty-cash').on('click', '.btn-replenish-petty-cash', function () {
        loadBox($(this).data('id')).done(box => {
            $('#pettyCashReplenishmentForm')[0].reset(); $('#pcr_box_id').val(box.id);
            const symbol = box.currency?.symbol || '';
            const pending = Math.max(0, Number(box.reimbursement_amount));
            $('#pcr_code').text(box.code);
            $('#pcr_company').text(box.company?.trade_name || box.company?.business_name || '-');
            $('#pcr_summary').html([
                ['Fondo aprobado', money(box.approved_fund, symbol)], ['Total gastado', money(box.total_expenses, symbol)],
                ['Total repuesto', money(box.replenished_total, symbol)], ['Saldo actual', money(box.cash_balance, symbol)],
                ['Pendiente reposición', money(pending, symbol)]
            ].map(item => `<div class="petty-summary-item"><small>${item[0]}</small><strong>${item[1]}</strong></div>`).join(''));
            const hasPending = pending > 0;
            $('#pcr_no_pending').toggleClass('d-none', hasPending);
            $('#pcr_amount').attr('max', pending.toFixed(2)).val(pending.toFixed(2)).prop('disabled', !hasPending);
            $('#btnSavePettyCashReplenishment').prop('disabled', !hasPending);
            $('#pcr_date').val(new Date().toISOString().slice(0, 10));
            $('#pettyCashReplenishmentModal').modal('show');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    $('#pettyCashReplenishmentForm').on('submit', function (event) {
        event.preventDefault(); const data = new FormData(this); loading($(this), true);
        api({ url: `${base}/${$('#pcr_box_id').val()}/replenishments`, method: 'POST', data, processData: false, contentType: false })
            .done(response => { $('#pettyCashReplenishmentModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); })
            .fail(xhr => notify('error', errorMessage(xhr))).always(() => loading($(this), false));
    });
});
