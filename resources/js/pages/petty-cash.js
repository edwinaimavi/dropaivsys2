$(function () {
    const app = $('#pettyCashApp');
    if (!app.length) return;

    const base = app.data('base-url');
    const csrf = $('meta[name="csrf-token"]').attr('content');
    let currentBox = null;
    let table;
    let applyingPreviousBalance = false;
    let currentApprovedAmount = null;
    let pendingExpenseFiles = [];
    let existingExpenseDocuments = [];
    let expensePreviewUrls = [];
    let approvalExpense = null;
    let pendingExpenses = [];
    let observedExpenses = [];
    let receiptExchangeFiles = [];
    let receiptExchangePreviewUrls = [];
    let pendingExchangeReceipts = [];
    const sourceReceipts = {
        opening: { files: [], existing: [], urls: [] },
        replenishment: { files: [], existing: [], urls: [] }
    };
    const dniRequests = new Map();
    const dniTimers = {};

    const money = (value, symbol = '') => `${symbol || ''} ${Number(value || 0).toLocaleString('es-PE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`.trim();
    const date = value => value ? String(value).slice(0, 10).split('-').reverse().join('/') : '-';
    const dateTime = value => value
        ? new Date(value).toLocaleString('es-PE', { dateStyle: 'short', timeStyle: 'short' })
        : '-';
    const escapeHtml = value => $('<div>').text(value ?? '').html();
    const fileSize = bytes => {
        const value = Number(bytes || 0);
        if (value < 1024) return `${value} B`;
        if (value < 1048576) return `${(value / 1024).toFixed(1)} KB`;
        return `${(value / 1048576).toFixed(1)} MB`;
    };
    const notify = (icon, title) => window.Swal ? Swal.fire({ icon, title, confirmButtonColor: '#20765c' }) : alert(title);
    const errorMessage = xhr => xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {}).flat()[0] || 'No fue posible completar la operación.';
    const loading = (form, active) => form.find('[type="submit"]').prop('disabled', active).find('i').toggleClass('fa-spin', active);
    const userName = user => user ? [user.name, user.lastname].filter(Boolean).join(' ') : '-';
    const resolvedObservations = expense => (expense?.observations || [])
        .filter(observation => observation.status === 'RESOLVED' && observation.resolved_at);
    const hasLiftedObservation = expense => resolvedObservations(expense).length > 0;
    const liftedObservationBadge = expense => hasLiftedObservation(expense)
        ? `<span class="petty-approval-badge is-lifted">Observación levantada</span>
           <button type="button" class="btn btn-link btn-sm p-0 ml-1 viewPettyCashObservationHistory" data-id="${expense.id}">
               <i class="fas fa-history mr-1"></i>Ver historial
           </button>`
        : '';
    const updateAttentionCounter = (buttonSelector, badgeSelector, value, label) => {
        const count = Math.max(0, Number(value || 0));
        $(badgeSelector).text(count);
        $(buttonSelector)
            .toggleClass('petty-cash-alert-attention', count > 0)
            .attr('aria-label', `${label}: ${count}`);
    };
    const expenseActionUrl = (action, expenseId) => ({
        approve: `${base}/expenses/${expenseId}/approve`,
        reject: `${base}/expenses/${expenseId}/reject`,
        observe: `${base}/expenses/${expenseId}/observe`
    })[action] || null;
    const approvalStatusHtml = expense => {
        const states = {
            pendiente_aprobacion: ['Pendiente de aprobación', 'is-pending'],
            observado: ['Observado', 'is-observed'],
            aprobado: ['Aprobado', 'is-approved'],
            rechazado: ['Rechazado', 'is-rejected'],
            anulado: ['Anulado', 'is-cancelled']
        };
        const state = states[expense.approval_status] || [expense.approval_status || 'Pendiente', 'is-pending'];
        let trace = 'No afecta el saldo hasta ser aprobado.';
        if (expense.approval_status === 'aprobado') trace = `${escapeHtml(userName(expense.approved_by))} · ${dateTime(expense.approved_at)}`;
        if (expense.approval_status === 'rechazado') trace = `${escapeHtml(userName(expense.rejected_by))} · ${escapeHtml(expense.approval_observation || 'Sin motivo')}`;
        if (expense.approval_status === 'observado' && expense.current_observation) {
            trace = `<button type="button" class="btn btn-link btn-sm p-0 viewPettyCashObservation" data-id="${expense.id}"><i class="fas fa-comment-alt mr-1"></i>Ver observación</button>`;
        }
        const lifted = expense.approval_status === 'pendiente_aprobacion' ? liftedObservationBadge(expense) : '';
        return `<span class="petty-approval-badge ${state[1]}">${state[0]}</span>${lifted}<small class="petty-approval-trace">${trace}</small>`;
    };

    const api = (options) => $.ajax({ headers: { 'X-CSRF-TOKEN': csrf }, ...options });
    const loadBox = id => api({ url: `${base}/${id}`, method: 'GET' }).then(response => response.data);
    const stackedModalSelector = '.petty-detail-modal, .petty-expense-modal, .petty-approved-modal, .petty-replenishment-modal, .petty-receipt-exchange-modal, .petty-approval-modal, .petty-observation-detail-modal';
    const detailTooltipTemplate = '<div class="tooltip petty-cash-tooltip" role="tooltip"><div class="arrow"></div><div class="tooltip-inner"></div></div>';
    const renderReceiptExchangeFiles = () => {
        receiptExchangePreviewUrls.forEach(url => URL.revokeObjectURL(url));
        receiptExchangePreviewUrls = [];
        const transfer = new DataTransfer();
        receiptExchangeFiles.forEach(file => transfer.items.add(file));
        document.getElementById('pcre_documents').files = transfer.files;
        $('#pcre_document_previews').html(receiptExchangeFiles.map((file, index) => {
            const isImage = file.type.startsWith('image/');
            let visual = '<span><i class="fas fa-file-pdf"></i></span>';
            if (isImage) {
                const url = URL.createObjectURL(file);
                receiptExchangePreviewUrls.push(url);
                visual = `<img src="${url}" alt="">`;
            }
            return `<article class="petty-source-file">${visual}<div><strong>${escapeHtml(file.name)}</strong><small>${fileSize(file.size)}</small></div><button type="button" class="removeReceiptExchangeFile" data-index="${index}"><i class="fas fa-times"></i></button></article>`;
        }).join(''));
    };
    const updateReceiptExchangeSelection = () => {
        const selected = $('.pcre-receipt:checked').map((_, checkbox) => Number(checkbox.value)).get();
        const receipts = pendingExchangeReceipts.filter(receipt => selected.includes(Number(receipt.id)));
        const total = receipts.reduce((sum, receipt) => sum + Number(receipt.amount || 0), 0);
        const suppliers = new Set(receipts.map(receipt => String(receipt.supplier_id || receipt.supplier_name || '').trim()).filter(Boolean));
        $('#pcre_total').text(money(total, currentBox?.currency?.symbol || ''));
        $('#pcre_supplier_warning').toggleClass('d-none', suppliers.size <= 1);
    };

    const initializeDetailTooltips = () => {
        const tooltips = $('#viewPettyCashModal [data-toggle="tooltip"]');
        tooltips.tooltip('dispose').tooltip({
            container: 'body',
            boundary: 'window',
            trigger: 'hover focus',
            template: detailTooltipTemplate
        });
    };
    const syncExpenseFileInput = () => {
        const transfer = new DataTransfer();
        pendingExpenseFiles.forEach(file => transfer.items.add(file));
        document.getElementById('pce_documents').files = transfer.files;
    };
    const resetExpenseDocuments = (documents = []) => {
        expensePreviewUrls.forEach(url => URL.revokeObjectURL(url));
        expensePreviewUrls = [];
        pendingExpenseFiles = [];
        existingExpenseDocuments = [...documents];
        syncExpenseFileInput();
        renderExpenseDocuments();
    };
    const receiptPreview = (source, isExisting, index) => {
        const extension = String(source.extension || source.name?.split('.').pop() || '').toLowerCase();
        const mime = String(source.mime_type || source.type || '');
        const isImage = mime.startsWith('image/') || ['jpg', 'jpeg', 'png'].includes(extension);
        let preview;

        if (isImage) {
            const url = isExisting ? source.view_url : URL.createObjectURL(source);
            if (!isExisting) expensePreviewUrls.push(url);
            preview = `<img src="${url}" alt="">`;
        } else {
            preview = '<span class="petty-receipt-pdf"><i class="fas fa-file-pdf"></i></span>';
        }

        const name = escapeHtml(source.original_name || source.name || 'Comprobante');
        const size = fileSize(source.file_size ?? source.size);
        const actions = isExisting
            ? `<a href="${source.view_url}" target="_blank" class="petty-receipt-action is-view" title="Abrir comprobante"><i class="fas fa-external-link-alt"></i></a><button type="button" class="petty-receipt-action is-remove removeExistingExpenseDocument" data-id="${source.id}" title="Eliminar comprobante"><i class="fas fa-trash"></i></button>`
            : `<button type="button" class="petty-receipt-action is-remove removePendingExpenseDocument" data-index="${index}" title="Quitar archivo"><i class="fas fa-times"></i></button>`;

        return `<article class="petty-receipt-item">${preview}<div class="petty-receipt-meta"><strong title="${name}">${name}</strong><small>${isExisting ? 'Guardado' : 'Nuevo'} · ${size}</small></div><div class="petty-receipt-actions">${actions}</div></article>`;
    };
    function renderExpenseDocuments() {
        expensePreviewUrls.forEach(url => URL.revokeObjectURL(url));
        expensePreviewUrls = [];
        const items = [
            ...existingExpenseDocuments.map((document, index) => receiptPreview(document, true, index)),
            ...pendingExpenseFiles.map((file, index) => receiptPreview(file, false, index))
        ];
        $('#pce_receipts_count').text(items.length);
        $('#pce_documents_preview').html(items.join('') || `
            <div class="petty-receipts-empty">
                <i class="far fa-file-alt"></i>
                <strong>No hay comprobantes adjuntos</strong>
                <small>Los archivos seleccionados aparecerán aquí.</small>
            </div>
        `);
    }

    const sourceReceiptConfig = key => key === 'opening'
        ? { input: '#pc_fund_source_receipts', preview: '#pc_fund_source_previews' }
        : { input: '#pcr_fund_source_receipts', preview: '#pcr_fund_source_previews' };
    const syncSourceReceipts = key => {
        const transfer = new DataTransfer();
        sourceReceipts[key].files.forEach(file => transfer.items.add(file));
        document.querySelector(sourceReceiptConfig(key).input).files = transfer.files;
    };
    const renderSourceReceipts = key => {
        const state = sourceReceipts[key], config = sourceReceiptConfig(key);
        state.urls.forEach(url => URL.revokeObjectURL(url));
        state.urls = [];
        const saved = state.existing.map(document => {
            const visual = String(document.mime_type || '').startsWith('image/')
                ? `<img src="${document.view_url}" alt="">`
                : '<span><i class="fas fa-file-pdf"></i></span>';
            return `<article class="petty-source-file">${visual}<div><strong>${escapeHtml(document.original_name)}</strong><small>Guardado · ${fileSize(document.file_size)}</small></div><a href="${document.view_url}" target="_blank"><i class="fas fa-external-link-alt"></i></a></article>`;
        });
        const pending = state.files.map((file, index) => {
            let visual = '<span><i class="fas fa-file-pdf"></i></span>';
            if (file.type.startsWith('image/')) {
                const url = URL.createObjectURL(file);
                state.urls.push(url);
                visual = `<img src="${url}" alt="">`;
            }
            return `<article class="petty-source-file">${visual}<div><strong>${escapeHtml(file.name)}</strong><small>Nuevo · ${fileSize(file.size)}</small></div><button type="button" class="removeSourceReceipt" data-key="${key}" data-index="${index}"><i class="fas fa-times"></i></button></article>`;
        });
        $(config.preview).html([...saved, ...pending].join(''));
    };
    const resetSourceReceipts = (key, existing = []) => {
        sourceReceipts[key].urls.forEach(url => URL.revokeObjectURL(url));
        sourceReceipts[key] = { files: [], existing: [...existing], urls: [] };
        syncSourceReceipts(key);
        renderSourceReceipts(key);
    };
    const addSourceReceipts = (key, files) => {
        Array.from(files).forEach(file => {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'jpg', 'jpeg', 'png'].includes(extension)) notify('error', `Formato no permitido: ${file.name}`);
            else if (file.size > 10 * 1024 * 1024) notify('error', `El archivo supera el tamaño permitido: ${file.name}`);
            else sourceReceipts[key].files.push(file);
        });
        syncSourceReceipts(key);
        renderSourceReceipts(key);
    };
    const loadSourceAccounts = (companyId, selectSelector, helpSelector, selectedId = '') => {
        const select = $(selectSelector).prop('disabled', true);
        $(helpSelector).text('');
        if (!companyId) {
            select.html('<option value="">Seleccione primero una empresa</option>');
            return;
        }
        select.html('<option value="">Cargando cuentas...</option>');
        api({ url: `${base}/source-companies/${companyId}/bank-accounts`, method: 'GET' })
            .done(response => {
                const accounts = response.data || [];
                select.html('<option value="">Seleccione cuenta bancaria</option>' + accounts.map(account => `<option value="${account.id}">${escapeHtml(account.label)}</option>`).join(''))
                    .prop('disabled', !accounts.length).val(String(selectedId || ''));
                $(helpSelector).text(accounts.length ? '' : 'Esta empresa no tiene cuentas bancarias registradas.');
            })
            .fail(xhr => $(helpSelector).text(errorMessage(xhr)));
    };

    $(document).on('show.bs.modal.pettyCashStack', stackedModalSelector, function () {
        const zIndex = 1050 + (10 * $('.modal.show:visible').length);
        $(this).css('z-index', zIndex);

        window.setTimeout(() => {
            $('.modal-backdrop').not('.petty-modal-backdrop')
                .last()
                .css('z-index', zIndex - 1)
                .addClass('petty-modal-backdrop');
        }, 0);
    });

    $(document).on('hidden.bs.modal.pettyCashStack', stackedModalSelector, function () {
        $(this).css('z-index', '');
        if ($('.modal.show:visible').length) {
            $('body').addClass('modal-open');
        }
    });
    const updateOpeningAmount = () => {
        const previous = Math.max(0, Number($('#pc_previous_balance').val()) || 0);
        const approvedAmount = Math.max(0, Number(currentApprovedAmount?.amount) || 0);
        const hasPreviousBox = Boolean($('#pc_previous_petty_cash_id').val());
        const approved = hasPreviousBox ? Math.max(0, approvedAmount - previous) : 0;
        const opening = hasPreviousBox ? previous + approved : approvedAmount;
        $('#pc_approved_fund').val(approved.toFixed(2));
        $('#pc_opening_amount').val(opening.toFixed(2));
        $('#pc_side_previous').text(money(previous));
        $('#pc_side_fund').text(money(approved));
        $('#pc_side_opening').text(money(opening));
        if (!$('#petty_cash_id').val()) $('#pc_side_balance').text(money(opening));
        const hasInitialFund = opening > 0;
        const requiresSource = approved > 0;
        const hasCarriedBalance = hasPreviousBox && previous > 0;
        $('#pc_fund_source_section').toggleClass('d-none', !hasInitialFund);
        $('#pc_carried_balance_source').toggleClass('d-none', !hasCarriedBalance);
        $('#pc_carried_balance_box').text(hasCarriedBalance
            ? ($('#pc_previous_balance_message').data('previous-code') || 'Caja anterior')
            : '-');
        $('#pc_carried_balance_amount').text(money(previous));
        $('#pc_replenishment_source_fields').toggleClass('d-none', !requiresSource);
        $('#pc_fund_source_company_id,#pc_fund_source_bank_account_id').prop('required', requiresSource);
        if (!requiresSource) {
            $('#pc_fund_source_company_id').val('');
            $('#pc_fund_source_bank_account_id').prop('disabled', true).html('<option value="">Seleccione primero una empresa</option>');
            $('#pc_fund_source_account_help').text('');
            resetSourceReceipts('opening');
        }
        $('#pc_approved_amount_warning').addClass('d-none');
    };

    const resetApprovedAmount = (caption = 'Seleccione empresa y moneda') => {
        currentApprovedAmount = null;
        $('#pc_approved_amount_display').text('Sin asignar');
        $('#pc_approved_amount_caption').text(caption);
        $('#pc_approved_amount_warning').addClass('d-none');
    };

    const loadApprovedAmount = () => {
        const companyId = $('#pc_company_id').val();
        const currencyId = $('#pc_currency_id').val();
        if (!companyId || !currencyId) {
            resetApprovedAmount();
            return $.Deferred().resolve().promise();
        }

        $('#pc_approved_amount_display').html('<i class="fas fa-spinner fa-spin"></i>');
        $('#pc_approved_amount_caption').text('Consultando autorización...');
        return api({
            url: app.data('approved-amount-active-url'),
            method: 'GET',
            data: { company_id: companyId, currency_id: currencyId }
        }).done(response => {
            currentApprovedAmount = response.status === 'success' ? response.data : null;
            $('#pc_approved_amount_display').text(currentApprovedAmount?.formatted_amount || 'Sin asignar');
            $('#pc_approved_amount_caption').text(currentApprovedAmount
                ? 'Autorizado para esta empresa'
                : 'No existe una configuración activa');
            updateOpeningAmount();
        }).fail(xhr => {
            resetApprovedAmount('No fue posible consultar el monto');
            if (xhr.status !== 403) notify('error', errorMessage(xhr));
        });
    };

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
        processing: true,
        serverSide: true,
        responsive: {
            details: {
                type: 'column',
                target: 0
            }
        },
        dom: "<'row mb-3'<'col-md-6'l><'col-md-6'f>>rt<'row mt-3'<'col-md-5'i><'col-md-7'p>><'row mt-2'<'col-12'B>>",
        buttons: [
            { extend: 'excelHtml5', text: '<i class="fas fa-file-excel mr-1"></i> Excel', className: 'btn btn-success btn-sm' },
            { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf mr-1"></i> PDF', className: 'btn btn-danger btn-sm', orientation: 'landscape' },
            { extend: 'print', text: '<i class="fas fa-print mr-1"></i> Imprimir', className: 'btn btn-secondary btn-sm' }
        ],
        ajax: app.data('list-url'),
        order: [[2, 'desc']],
        columns: [
            { data: null, defaultContent: '', className: 'dtr-control petty-responsive-control', orderable: false, searchable: false },
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id' }, { data: 'code' }, { data: 'company', name: 'company.business_name' },
            { data: 'period', orderable: false }, { data: 'start_date' }, { data: 'end_date' },
            { data: 'opening_amount' }, { data: 'total_expenses' }, { data: 'cash_balance' },
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
            updateAttentionCounter('#btnPendingPettyCashExpenses', '#pcPendingExpensesBadge', summary.pending_expenses_count, 'Gastos por aprobar');
            updateAttentionCounter('#btnObservedPettyCashExpenses', '#pcObservedExpensesBadge', summary.observed_expenses_count, 'Gastos observados');
            $('#tablePettyCash [data-toggle="tooltip"]').tooltip();
        }
    });

    const preparePettyCashActionsMenu = dropdown => {
        const container = $(dropdown);
        const button = container.find('.petty-actions-trigger').get(0);
        const menu = container.find('.petty-cash-actions-menu');
        if (!button || !menu.length) return;

        const buttonRect = button.getBoundingClientRect();
        const viewportPadding = 12;
        const originalStyle = menu.attr('style');
        menu.css({
            display: 'block',
            position: 'absolute',
            visibility: 'hidden',
            maxHeight: 'none'
        });
        const menuHeight = menu.outerHeight();
        if (originalStyle === undefined) menu.removeAttr('style');
        else menu.attr('style', originalStyle);

        const spaceBelow = Math.max(0, window.innerHeight - buttonRect.bottom - viewportPadding);
        const spaceAbove = Math.max(0, buttonRect.top - viewportPadding);
        const openUp = menuHeight > spaceBelow && spaceAbove > spaceBelow;
        const availableHeight = Math.max(120, openUp ? spaceAbove : spaceBelow);
        container.toggleClass('dropup', openUp);
        menu.css('max-height', `${availableHeight}px`);
    };

    $('#tablePettyCash')
        .on('show.bs.dropdown', '.dropdown', function () {
            $(this).closest('.table-responsive').addClass('petty-dropdown-is-open');
            preparePettyCashActionsMenu(this);
        })
        .on('hidden.bs.dropdown', '.dropdown', function () {
            $(this).removeClass('dropup')
                .find('.petty-cash-actions-menu')
                .css('max-height', '');
            $(this).closest('.table-responsive').removeClass('petty-dropdown-is-open');
        })
        .on('click', '.petty-cash-actions-menu .dropdown-item', function () {
            $(this).closest('.dropdown').find('.petty-actions-trigger').dropdown('hide');
        });

    $(window).on('resize.pettyCashActions scroll.pettyCashActions', function () {
        $('#tablePettyCash .petty-actions-trigger[aria-expanded="true"]').dropdown('hide');
    });

    $('#tablePettyCash').on('preDraw.dt', function () {
        $(this).find('.petty-actions-trigger[aria-expanded="true"]').dropdown('hide');
    });

    $(document).on('click', '#btnCreatePettyCash', function () {
        const form = $('#pettyCashForm')[0];
        form.reset();
        $('#responsible_dni,#supervisor_dni').removeData('last-dni');
        $('#petty_cash_id').val('');
        $('#pc_previous_petty_cash_id').val('');
        $('#pc_previous_balance').val('0');
        resetSourceReceipts('opening');
        $('#pc_currency_id').val($('#pc_currency_id').data('default-currency-id'));
        resetApprovedAmount();
        $('#pc_previous_balance_message').text('Seleccione empresa y moneda para calcular el fondo inicial.');
        $('#pc_start_date').val(new Date().toISOString().slice(0, 10));
        $('#pc_side_code').text('Se generará al guardar');
        $('#pc_side_status').text('ABIERTA');
        $('#pc_side_previous,#pc_side_fund,#pc_side_opening,#pc_side_expenses,#pc_side_balance').text('0.00');
        $('#pettyCashModalLabel').text('Aperturar caja chica');
        $('#btnSavePettyCash span').text('Guardar Caja');
        $('#pettyCashModal').modal('show');
    });

    $('#pc_fund_source_company_id').on('change', function () {
        loadSourceAccounts(this.value, '#pc_fund_source_bank_account_id', '#pc_fund_source_account_help');
    });
    $('#pcr_fund_source_company_id').on('change', function () {
        loadSourceAccounts(this.value, '#pcr_fund_source_bank_account_id', '#pcr_fund_source_account_help');
    });
    $('#pc_fund_source_receipts').on('change', function () { addSourceReceipts('opening', this.files); });
    $('#pcr_fund_source_receipts').on('change', function () { addSourceReceipts('replenishment', this.files); });
    $('.petty-source-upload').on('dragenter dragover', function (event) {
        event.preventDefault();
        $(this).addClass('is-dragging');
    }).on('dragleave drop', function (event) {
        event.preventDefault();
        $(this).removeClass('is-dragging');
        if (event.type === 'drop') {
            const key = $(this).attr('for') === 'pc_fund_source_receipts' ? 'opening' : 'replenishment';
            addSourceReceipts(key, event.originalEvent.dataTransfer.files);
        }
    });
    $(document).on('click', '.removeSourceReceipt', function () {
        const key = $(this).data('key');
        sourceReceipts[key].files.splice(Number($(this).data('index')), 1);
        syncSourceReceipts(key);
        renderSourceReceipts(key);
    });
    $(document).off('change', '#pc_company_id').on('change', '#pc_company_id', function () {
        const companyId = this.value;
        loadApprovedAmount();
        if (!companyId) {
            applyingPreviousBalance = true;
            $('#pc_previous_petty_cash_id').val('');
            $('#pc_previous_balance').val('0');
            applyingPreviousBalance = false;
            $('#pc_previous_balance_message').text('Seleccione empresa y moneda para calcular el fondo inicial.');
            updateOpeningAmount();
            return;
        }
        $('#pc_previous_balance_message').html('<i class="fas fa-spinner fa-spin mr-1"></i> Buscando saldo anterior...');
        api({ url: app.data('previous-balance-url'), method: 'GET', data: { company_id: companyId, currency_id: $('#pc_currency_id').val(), exclude_id: $('#petty_cash_id').val() || null } })
            .done(response => {
                const data = response.data || {};
                applyingPreviousBalance = true;
                $('#pc_previous_petty_cash_id').val(data.previous_petty_cash_id || '');
                $('#pc_previous_balance').val(Number(data.previous_balance || 0).toFixed(2));
                applyingPreviousBalance = false;
                $('#pc_previous_balance_message')
                    .text(data.message || 'No se detectó saldo anterior.')
                    .data('previous-code', data.previous_code || '');
                updateOpeningAmount();
            })
            .fail(xhr => {
                $('#pc_previous_balance_message').text(errorMessage(xhr));
                $('#pc_previous_petty_cash_id').val('');
            });
    });
    $(document).off('change', '#pc_currency_id').on('change', '#pc_currency_id', function () {
        $('#pc_company_id').trigger('change');
    });

    const updateApprovedAmountSymbol = () => {
        $('#pca_currency_symbol').text($('#pca_currency_id option:selected').data('symbol') || '');
    };
    const loadApprovedAmountConfiguration = () => {
        const companyId = $('#pca_company_id').val();
        const currencyId = $('#pca_currency_id').val();
        updateApprovedAmountSymbol();
        $('#pca_amount').val('');
        $('#pca_active').val('1');
        $('#pca_observation').val('');
        $('#pca_approval_info,#pca_history_section').addClass('d-none');
        $('#pca_history_body').empty();
        if (!companyId || !currencyId || !app.data('approved-amount-show-url')) return;

        api({
            url: app.data('approved-amount-show-url'),
            method: 'GET',
            data: { company_id: companyId, currency_id: currencyId }
        }).done(response => {
            if (response.status !== 'success' || !response.data) return;
            $('#pca_amount').val(Number(response.data.amount || 0).toFixed(2));
            $('#pca_active').val(response.data.active ? '1' : '0');
            $('#pca_observation').val(response.data.observation || '');
            $('#pca_info_company').text(response.data.company || $('#pca_company_id option:selected').text());
            $('#pca_info_currency').text(response.data.currency || $('#pca_currency_id option:selected').text());
            $('#pca_info_amount').text(response.data.formatted_amount || '-');
            $('#pca_info_status').html(response.data.active
                ? '<span class="approval-status-badge is-active"><i class="fas fa-check-circle"></i> ACTIVO</span>'
                : '<span class="approval-status-badge is-inactive"><i class="fas fa-minus-circle"></i> INACTIVO</span>');
            $('#pca_info_approved_at').text(response.data.approved_at || 'Sin información');
            $('#pca_info_approved_by').text(response.data.approved_by || 'Sin información');
            $('#pca_info_observation').text(response.data.observation || 'Sin observación');
            $('#pca_approval_info').removeClass('d-none');

            const history = response.data.history || [];
            $('#pca_history_body').html(history.length ? history.map(item => {
                const itemSymbol = item.currency === response.data.currency
                    ? ($('#pca_currency_id option:selected').data('symbol') || item.currency)
                    : item.currency;
                return `<tr>
                    <td>${escapeHtml(item.approved_at || '-')}</td>
                    <td>${escapeHtml(item.approved_by || '-')}</td>
                    <td class="text-right">${item.previous_amount === null ? '—' : money(item.previous_amount, itemSymbol)}</td>
                    <td class="text-right font-weight-bold text-success">${money(item.approved_amount, itemSymbol)}</td>
                    <td>${escapeHtml(item.currency || '-')}</td>
                    <td>${escapeHtml(item.notes || 'Sin observación')}</td>
                </tr>`;
            }).join('') : '<tr><td colspan="6" class="text-center text-muted py-3">Aún no existe historial de aprobaciones.</td></tr>');
            $('#pca_history_section').removeClass('d-none');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    };
    const openApprovedAmountModal = useOpeningSelection => {
        const form = $('#pettyCashApprovedAmountForm');
        if (!form.length) return;
        form[0].reset();
        $('#pca_company_id').val(useOpeningSelection ? ($('#pc_company_id').val() || '') : '');
        $('#pca_currency_id').val(useOpeningSelection
            ? ($('#pc_currency_id').val() || $('#pc_currency_id').data('default-currency-id'))
            : $('#pc_currency_id').data('default-currency-id'));
        loadApprovedAmountConfiguration();
        $('#pettyCashApprovedAmountModal').modal('show');
    };
    $(document).on('click', '#btnConfigureApprovedAmount', () => openApprovedAmountModal(false));
    $(document).on('click', '#btnConfigureApprovedAmountFromOpening', () => openApprovedAmountModal(true));
    $(document).on('change', '#pca_company_id,#pca_currency_id', loadApprovedAmountConfiguration);
    $('#pettyCashApprovedAmountForm').on('submit', function (event) {
        event.preventDefault();
        const form = $(this);
        loading(form, true);
        api({
            url: app.data('approved-amount-update-url'),
            method: 'PUT',
            data: form.serialize()
        }).done(response => {
            if (String($('#pc_company_id').val()) === String($('#pca_company_id').val())
                && String($('#pc_currency_id').val()) === String($('#pca_currency_id').val())) {
                loadApprovedAmount();
            }
            loadApprovedAmountConfiguration();
            if (window.Swal) {
                Swal.fire({ icon: 'success', title: response.message, timer: 1800, showConfirmButton: false });
            }
        }).fail(xhr => notify('error', errorMessage(xhr)))
            .always(() => loading(form, false));
    });

    $('#pettyCashForm').on('submit', function (event) {
        event.preventDefault();
        if (!currentApprovedAmount || Number(currentApprovedAmount.amount) <= 0) {
            notify('error', 'No existe un monto aprobado activo para esta empresa y moneda. Configure el monto aprobado antes de aperturar caja.');
            return;
        }
        const id = $('#petty_cash_id').val();
        const data = new FormData(this);
        if (id) data.append('_method', 'PUT');
        loading($(this), true);
        api({ url: id ? `${base}/${id}` : base, method: 'POST', data, processData: false, contentType: false })
            .done(response => { $('#pettyCashModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); })
            .fail(xhr => notify('error', errorMessage(xhr)))
            .always(() => loading($(this), false));
    });

    $(document).on('click', '.editPettyCash, .btn-edit-petty-cash', function (event) {
        event.preventDefault();
        event.stopPropagation();
        loadBox($(this).data('id')).done(box => {
            currentBox = box;
            $('#pettyCashForm')[0].reset();
            $('#petty_cash_id').val(box.id);
            ['company_id','currency_id','start_date','approved_fund','observations']
                .forEach(field => $(`#pc_${field}`).val(String(box[field] ?? '').slice(0, field.includes('date') ? 10 : undefined)));
            applyingPreviousBalance = true;
            $('#pc_previous_balance').val(Number(box.previous_balance || 0).toFixed(2));
            $('#pc_previous_petty_cash_id').val(box.previous_petty_cash_id || '');
            applyingPreviousBalance = false;
            $('#pc_previous_balance_message')
                .text(box.previous_petty_cash
                    ? `Saldo disponible arrastrado desde ${box.previous_petty_cash.code}.`
                    : 'Primera apertura: el fondo inicial corresponde al monto aprobado.')
                .data('previous-code', box.previous_petty_cash?.code || '');
            $('#responsible_dni').val(box.responsible_dni).data('last-dni', box.responsible_dni);
            $('#responsible_name').val(box.responsible_name);
            $('#supervisor_dni').val(box.supervisor_dni).data('last-dni', box.supervisor_dni);
            $('#supervisor_name').val(box.supervisor_name);
            $('#pc_side_code').text(box.code); $('#pc_side_status').text(box.status_label);
            loadApprovedAmount();
            updateOpeningAmount();
            resetSourceReceipts('opening', box.documents || []);
            $('#pc_fund_source_company_id').val(box.fund_source_company_id || '');
            if (box.fund_source_company_id) {
                loadSourceAccounts(box.fund_source_company_id, '#pc_fund_source_bank_account_id', '#pc_fund_source_account_help', box.fund_source_bank_account_id);
            }
            $('#pc_side_expenses').text(money(box.total_expenses)); $('#pc_side_balance').text(money(box.cash_balance));
            $('#pettyCashModalLabel').text('Editar caja chica'); $('#btnSavePettyCash span').text('Actualizar Caja'); $('#pettyCashModal').modal('show');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    const renderDetail = box => {
        currentBox = box;
        const symbol = box.currency?.symbol || '';
        const statusKey = String(box.status || box.status_label || '').toLowerCase();
        $('#pcv_code').text(box.code);
        $('#pcv_status')
            .text(box.status_label || box.status || '-')
            .toggleClass('is-open', statusKey.includes('open') || statusKey.includes('abiert'))
            .toggleClass('is-closed', statusKey.includes('clos') || statusKey.includes('cerrad'));
        $('#pcv_company').text(box.company?.trade_name || box.company?.business_name || '-');
        $('#pcv_meta').text(box.closed_at
            ? `Aperturada el ${date(box.start_date)} · Cerrada el ${dateTime(box.closed_at)}`
            : `Aperturada el ${date(box.start_date)} · Caja abierta`);
        const summary = box.financial_summary || {};
        $('#pcv_summary').html([
            ['Monto aprobado', money(summary.approved_amount ?? box.approved_amount_snapshot, symbol), 'Fondo autorizado por administración', 'fa-hand-holding-usd', 'is-approved-amount'],
            ['Fondo inicial entregado', money(summary.initial_fund ?? box.opening_amount, symbol), 'Dinero entregado al aperturar la caja', 'fa-coins', 'is-opening'],
            ['Total gastado aprobado', money(summary.total_expenses ?? box.total_expenses, symbol), 'Solo gastos aprobados', 'fa-receipt', 'is-spent'],
            ['Gastos pendientes de aprobación', money(summary.pending_approval_expenses, symbol), 'Registrados, pero aún no afectan la caja', 'fa-clock', 'is-pending'],
            ['Total repuesto', money(summary.total_replenished ?? box.replenished_total, symbol), 'Reposiciones realizadas', 'fa-sync-alt', 'is-replenished'],
            ['Saldo disponible actual', money(summary.current_balance ?? box.cash_balance, symbol), 'Dinero disponible en caja', 'fa-wallet', 'is-balance'],
            ['Pendiente de reposición', money(summary.pending_replenishment ?? box.reimbursement_amount, symbol), 'Monto necesario para volver al fondo aprobado', 'fa-hourglass-half', 'is-pending']
        ].map(item => `<div class="petty-financial-card ${item[4]}"><i class="fas ${item[3]}"></i><small>${item[0]}</small><strong>${item[1]}</strong><em>${item[2]}</em></div>`).join(''));
        const pendingCount = Number(box.pending_expenses_count || 0);
        $('#pcv_pending_expenses_alert').toggleClass('d-none', pendingCount === 0).html(
            '<i class="fas fa-exclamation-triangle"></i><span><strong>Pendientes de aprobación.</strong> Esta caja tiene gastos pendientes de aprobación. Debe resolverlos antes de cerrar.</span>'
        );
        const closerName = box.closer ? [box.closer.name, box.closer.lastname].filter(Boolean).join(' ') : '-';
        $('#pcv_closure_info').html(`
            <div><small>Fecha de apertura</small><strong>${date(box.start_date)}</strong></div>
            <div><small>Fecha de cierre</small><strong>${box.closed_at ? dateTime(box.closed_at) : 'Pendiente de cierre por gerencia'}</strong></div>
            <div><small>Cerrado por</small><strong>${box.closed_at ? escapeHtml(closerName) : 'Pendiente'}</strong></div>
            <div><small>Observación de cierre</small><strong>${box.closed_at ? escapeHtml(box.close_observation || 'Sin observación') : 'Se registrará cuando gerencia cierre la caja.'}</strong></div>
        `);
        if (box.previous_petty_cash) {
            $('#pcv_summary').append(`<div class="petty-financial-card is-muted"><i class="fas fa-link"></i><small>Saldo arrastrado desde</small><strong>${escapeHtml(box.previous_petty_cash.code)}</strong></div>`);
        }
        const sourceAccount = box.source_bank_account;
        const hasCarriedSource = Number(box.previous_balance) > 0 && box.previous_petty_cash;
        const hasReplenishmentSource = Number(box.approved_fund) > 0 && box.source_company && sourceAccount;
        const hasSource = Number(box.opening_amount) > 0 && (hasCarriedSource || hasReplenishmentSource);
        $('#pcv_fund_source_section').toggleClass('d-none', !hasSource);
        if (hasSource) {
            const accountLabel = sourceAccount ? [
                sourceAccount.bank?.short_name || sourceAccount.bank?.description,
                sourceAccount.currency?.code,
                sourceAccount.account_number
            ].filter(Boolean).join(' - ') + (sourceAccount.cci ? ` | CCI: ${sourceAccount.cci}` : '') : '';
            const documents = (box.documents || []).map(document => `<a class="petty-document-btn" target="_blank" href="${document.view_url}" title="Ver comprobante"><i class="fas fa-paperclip"></i></a>`).join('') || '<span class="petty-no-document">Sin comprobante</span>';
            const carried = hasCarriedSource
                ? `<div><small>Saldo arrastrado de caja anterior</small><strong>${escapeHtml(box.previous_petty_cash.code)}</strong><span>${money(box.previous_balance, symbol)}</span></div>`
                : '';
            const replenished = hasReplenishmentSource
                ? `<div><small>Fondo por reponer</small><strong>${money(box.approved_fund, symbol)} · ${escapeHtml(box.source_company.trade_name || box.source_company.business_name)}</strong><span>${escapeHtml(accountLabel)} · ${documents}</span></div>`
                : '';
            $('#pcv_fund_source').html(`<div class="petty-source-detail">${carried}${replenished}</div>`);
        }
        $('#pcv_responsibles').html(`
            <div class="col-md-6 mb-2 mb-md-0"><div class="petty-person-card is-responsible"><span><i class="fas fa-user"></i></span><div><small>Responsable del fondo</small><strong>${escapeHtml(box.responsible_name)}</strong><em><i class="far fa-id-card"></i> DNI ${escapeHtml(box.responsible_dni)}</em></div><i class="fas fa-check petty-person-check"></i></div></div>
            <div class="col-md-6"><div class="petty-person-card is-supervisor"><span><i class="fas fa-user-check"></i></span><div><small>Supervisor asignado</small><strong>${escapeHtml(box.supervisor_name)}</strong><em><i class="far fa-id-card"></i> DNI ${escapeHtml(box.supervisor_dni)}</em></div><i class="fas fa-check petty-person-check"></i></div></div>
        `);
        $('#pcv_expenses_count').text(`${box.expenses.length} ${box.expenses.length === 1 ? 'movimiento' : 'movimientos'}`);
        $('#pcv_replenishments_count').text(`${box.replenishments.length} ${box.replenishments.length === 1 ? 'movimiento' : 'movimientos'}`);
        $('#pcv_expenses_tab_count').text(box.expenses.length);
        $('#pcv_replenishments_tab_count').text(box.replenishments.length);
        const pendingExchangeCount = Number(box.pending_exchange_receipts_count || 0);
        $('#btnExchangePettyCashReceipts')
            .toggleClass('d-none', !box.can_create_receipt_exchanges || pendingExchangeCount === 0)
            .data('id', box.id);
        $('#btnExchangeReceiptsFromHistory')
            .toggleClass('d-none', !box.can_create_receipt_exchanges || pendingExchangeCount === 0)
            .data('id', box.id);
        $('#btnAddExpenseFromDetail').toggleClass('d-none', !box.can_manage_expenses || !app.data('can-expense-store')).data('id', box.id);
        $('#btnApproveExpensesFromDetail').toggleClass('d-none', !box.can_approve_expenses || pendingCount === 0);
        $('#btnReplenishFromDetail').toggleClass('d-none', !box.can_replenish).data('id', box.id);
        $('#pcv_expenses').html(box.expenses.length ? box.expenses.map(expense => {
            const docs = (expense.documents || []).map(doc => `<a target="_blank" href="${doc.view_url}" class="petty-document-btn" data-toggle="tooltip" title="Ver documento" aria-label="Ver documento"><i class="fas fa-paperclip"></i></a>`).join('') || '<span class="petty-no-document">Sin archivo</span>';
            const isPending = expense.approval_status === 'pendiente_aprobacion';
            const isObserved = expense.approval_status === 'observado';
            const canCorrectObserved = isObserved && (app.data('can-expense-update') || Number(expense.created_by) === Number(app.data('current-user-id')));
            const actions = box.can_manage_expenses ? `<span class="petty-row-actions">${isPending && app.data('can-expense-approve') ? `<button class="btn approvePettyCashExpense" data-id="${expense.id}" title="Aprobar"><i class="fas fa-check"></i></button><button class="btn rejectPettyCashExpense" data-id="${expense.id}" title="Rechazar"><i class="fas fa-times"></i></button>` : ''}${isPending && app.data('can-expense-observe') ? `<button class="btn petty-observe-btn observePettyCashExpense" data-id="${expense.id}" title="Observar gasto"><i class="fas fa-comment-alt"></i></button>` : ''}${((isPending && app.data('can-expense-update')) || canCorrectObserved) ? `<button class="btn editPettyCashExpense" data-id="${expense.id}" title="${isObserved ? 'Corregir gasto observado' : 'Editar gasto'}"><i class="fas fa-edit"></i></button>` : ''}${app.data('can-expense-delete') ? `<button class="btn deletePettyCashExpense" data-id="${expense.id}" title="Eliminar gasto"><i class="fas fa-trash"></i></button>` : ''}</span>` : '';
            const number = expense.document_full_number || expense.document_number || '';
            const voucher = number ? [expense.document_type, number].filter(Boolean).join(' ') : '-';
            const approval = approvalStatusHtml(expense);
            let exchange = '<span class="petty-no-document">No aplica</span>';
            if (expense.exchange_status === 'PENDIENTE_CANJE') exchange = '<span class="petty-exchange-badge is-pending">Pendiente de canje</span>';
            if (expense.exchange_status === 'CANJEADO') {
                const realDocument = expense.exchange ? `${expense.exchange.document_type} ${expense.exchange.document_series}-${expense.exchange.document_correlative}` : 'Canjeado';
                exchange = `<span class="petty-exchange-badge is-completed">Canjeado</span><small class="petty-approval-trace">${escapeHtml(realDocument)}</small>`;
            }
            return `<tr><td><span class="petty-row-number">${expense.item_number}</span></td><td class="petty-date-cell">${date(expense.expense_date)}</td><td>${escapeHtml(voucher)}</td><td class="petty-supplier-cell">${escapeHtml(expense.supplier_name)}</td><td class="petty-concept-cell">${escapeHtml(expense.concept)}</td><td class="text-right petty-amount-cell">${money(expense.amount, symbol)}</td><td>${approval}</td><td>${exchange}</td><td class="text-center">${docs}</td><td class="text-center">${actions}</td></tr>`;
        }).join('') : '<tr><td colspan="10" class="petty-empty-state"><i class="fas fa-receipt"></i><strong>No hay gastos registrados para esta caja.</strong><small>Los nuevos gastos aparecerán en esta sección.</small></td></tr>');
        $('#pcv_replenishments').html(box.replenishments.length ? box.replenishments.map(item => {
            const sourceAccount = item.source_bank_account;
            const sourceLabel = sourceAccount ? [
                sourceAccount.bank?.short_name || sourceAccount.bank?.description,
                sourceAccount.currency?.code,
                sourceAccount.account_number
            ].filter(Boolean).join(' - ') : '';
            const source = item.source_company
                ? `<small class="d-block text-muted">${escapeHtml(item.source_company.trade_name || item.source_company.business_name)}${sourceLabel ? ` · ${escapeHtml(sourceLabel)}` : ''}</small>`
                : '';
            return `<tr><td class="petty-date-cell">${date(item.replenishment_date)}</td><td class="text-right petty-amount-cell">${money(item.amount, symbol)}</td><td>${escapeHtml(item.source_company?.trade_name || item.source_company?.business_name || '-')}</td><td>${escapeHtml(sourceLabel || '-')}</td><td class="petty-concept-cell">${escapeHtml(item.observation || '-')}</td><td class="text-center">${(item.documents || []).map(doc => `<a target="_blank" href="${doc.view_url}" class="petty-document-btn" data-toggle="tooltip" title="Ver sustento" aria-label="Ver sustento"><i class="fas fa-paperclip"></i></a>`).join('') || '<span class="petty-no-document">Sin archivo</span>'}</td><td><span class="petty-table-status">${escapeHtml(item.status || 'ACTIVE')}</span></td></tr>`;
        }).join('') : '<tr><td colspan="7" class="petty-empty-state"><i class="fas fa-sync-alt"></i><strong>No hay reposiciones registradas.</strong><small>Las reposiciones realizadas aparecerán aquí.</small></td></tr>');
        const exchanges = box.expense_exchanges || [];
        $('#pcv_exchanges_tab_count').text(exchanges.length);
        $('#pcv_exchange_history_count').text(`${exchanges.length} ${exchanges.length === 1 ? 'canje' : 'canjes'}`);
        $('#pcv_exchange_history').toggleClass('d-none', exchanges.length === 0);
        $('#pcv_exchange_empty').toggleClass('d-none', exchanges.length > 0);
        $('#pcv_exchange_history').html(exchanges.map(exchange => {
            const exchangeDocs = (exchange.documents || []).map(doc => `<a target="_blank" href="${doc.view_url}" class="petty-document-btn"><i class="fas fa-paperclip"></i></a>`).join('') || '<span class="petty-no-document">Sin archivo</span>';
            const receipts = (exchange.items || []).map(item => `<li>${escapeHtml(item.receipt_type || 'RECIBO')} ${escapeHtml([item.receipt_series, item.receipt_correlative].filter(Boolean).join('-'))} · ${escapeHtml(item.expense?.supplier_name || '')} · ${money(item.amount, symbol)}</li>`).join('');
            return `<article class="petty-exchange-history-item"><div><small>${date(exchange.exchange_date)}</small><strong>${escapeHtml(exchange.document_type)} ${escapeHtml(exchange.document_full_number)}</strong><span>${money(exchange.total_amount, symbol)}</span></div><ul>${receipts}</ul><div>${exchangeDocs}<small>${escapeHtml(userName(exchange.creator))}</small></div></article>`;
        }).join(''));
        $('#pcv_audit_info').html([
            ['Creado por', userName(box.creator), dateTime(box.created_at)],
            ['Actualizado por', userName(box.updater), dateTime(box.updated_at)],
            ['Cerrado por', box.closed_at ? userName(box.closer) : 'Pendiente', box.closed_at ? dateTime(box.closed_at) : '-'],
            ['Periodo', `${String(box.period_month || '').padStart(2, '0')}/${box.period_year || '-'}`, `Apertura: ${date(box.start_date)}`]
        ].map(item => `<div><small>${item[0]}</small><strong>${escapeHtml(item[1])}</strong><span>${escapeHtml(item[2])}</span></div>`).join(''));
        initializeDetailTooltips();
    };

    $(document).on('click', '#viewPettyCashModal .petty-document-btn', function () {
        $(this).tooltip('hide');
    });

    $(document).on('click', '.viewPettyCash', function () {
        loadBox($(this).data('id')).done(box => {
            renderDetail(box);
            $('#viewPettyCashModal .nav-link[href="#pcv_tab_summary"]').tab('show');
            $('#viewPettyCashModal').modal('show');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    const openApprovalModal = (expense, action) => {
        approvalExpense = expense;
        const reject = action === 'reject';
        const observe = action === 'observe';
        const box = expense.petty_cash_box || currentBox;
        const symbol = box?.currency?.symbol || currentBox?.currency?.symbol || '';
        const number = expense.document_full_number || expense.document_number || '-';
        $('#pca_expense_id').val(expense.id);
        $('#pca_action').val(action);
        $('#pca_observation').val('').prop('required', reject || observe).attr({
            maxlength: observe ? 2000 : 1000,
            placeholder: observe ? 'Ejemplo: Detallar qué transporte se pagó, origen/destino, mercadería trasladada y motivo del gasto.' : ''
        });
        $('#pca_title').text(observe ? 'Observar gasto' : (reject ? 'Rechazar gasto' : 'Aprobar gasto'));
        $('#pettyCashExpenseApprovalModal .modal-header p').text(observe
            ? 'Registra el motivo de la observación para que el usuario corrija la información.'
            : 'Confirma los datos y revisa sus comprobantes.');
        $('#pca_observation_label').text(reject ? 'Motivo de rechazo *' : 'Observación de aprobación (opcional)');
        $('#pca_observation_help').text(reject ? 'El motivo es obligatorio y quedará en la auditoría.' : 'Puedes registrar una nota administrativa.');
        $('#btnConfirmExpenseApproval').toggleClass('btn-success', !reject).toggleClass('btn-danger', reject)
            .find('span').text(reject ? 'Confirmar rechazo' : 'Confirmar aprobación');
        if (observe) {
            $('#pca_observation_label').text('Observación del administrador *');
            $('#pca_observation_help').text('Indica claramente qué información o sustento debe corregirse.');
        }
        $('#pca_icon i').attr('class', `fas ${observe ? 'fa-comment-alt' : (reject ? 'fa-times' : 'fa-check')}`);
        $('#btnConfirmExpenseApproval')
            .removeClass('btn-success btn-danger btn-warning')
            .addClass(observe ? 'btn-warning' : (reject ? 'btn-danger' : 'btn-success'))
            .find('span').text(observe ? 'Enviar observación' : (reject ? 'Confirmar rechazo' : 'Confirmar aprobación'));
        $('#pca_expense_data').html([
            ['Fecha', date(expense.expense_date)], ['Proveedor', expense.supplier_name],
            ['Concepto', expense.concept], ['Importe', money(expense.amount, symbol)],
            ['Comprobante', [expense.document_type, number].filter(Boolean).join(' ')],
            ['Registrado por', userName(expense.creator)],
            ['Estado actual', expense.approval_status === 'observado' ? 'Observado' : 'Pendiente de aprobación']
        ].map(item => `<div><small>${item[0]}</small><strong>${escapeHtml(item[1] || '-')}</strong></div>`).join(''));
        $('#pca_documents').html((expense.documents || []).map((document, index) =>
            `<a target="_blank" href="${document.view_url}"><i class="fas ${String(document.mime_type).includes('pdf') ? 'fa-file-pdf' : 'fa-image'} mr-1"></i>${String(document.mime_type).includes('pdf') ? 'Ver PDF' : `Ver imagen ${index + 1}`}</a>`
        ).join('') || '<span class="text-muted d-block mt-2">Sin comprobantes adjuntos.</span>');
        $('#pettyCashExpenseApprovalModal').modal('show');
    };

    const loadPendingExpenses = () => api({ url: app.data('pending-expenses-url'), method: 'GET' }).done(response => {
        const expenses = response.data || [];
        pendingExpenses = expenses;
        updateAttentionCounter('#btnPendingPettyCashExpenses', '#pcPendingExpensesBadge', response.count, 'Gastos por aprobar');
        $('#pc_pending_expenses_body').html(expenses.length ? expenses.map(expense => {
            const box = expense.petty_cash_box;
            const docs = (expense.documents || []).map(document => `<a target="_blank" href="${document.view_url}" class="petty-document-btn"><i class="fas fa-paperclip"></i></a>`).join('') || '-';
            const history = liftedObservationBadge(expense);
            const actions = `${app.data('can-expense-approve') ? `<button class="btn btn-sm btn-outline-success approvePendingPettyCashExpense" data-id="${expense.id}" title="Aprobar"><i class="fas fa-check"></i></button> <button class="btn btn-sm btn-outline-danger rejectPendingPettyCashExpense" data-id="${expense.id}" title="Rechazar"><i class="fas fa-times"></i></button>` : ''} ${app.data('can-expense-observe') ? `<button class="btn btn-sm btn-outline-warning observePendingPettyCashExpense" data-id="${expense.id}" title="Observar gasto"><i class="fas fa-comment-alt"></i></button>` : ''}`;
            return `<tr><td>${date(expense.expense_date)}</td><td><strong>${escapeHtml(box?.code || '-')}</strong><small class="d-block text-muted">${escapeHtml(box?.company?.trade_name || box?.company?.business_name || '-')}</small></td><td>${escapeHtml(expense.supplier_name)}</td><td>${escapeHtml(expense.concept)}${history ? `<div class="mt-1">${history}</div>` : ''}</td><td>${escapeHtml(userName(expense.creator))}</td><td class="text-center">${docs}</td><td class="text-right petty-amount-cell">${money(expense.amount, box?.currency?.symbol)}</td><td>${actions}</td></tr>`;
        }).join('') : '<tr><td colspan="8" class="petty-empty-state"><i class="fas fa-check-circle"></i><strong>No hay gastos pendientes de aprobación.</strong></td></tr>');
        $('#pendingPettyCashExpensesModal').data('expenses', expenses);
    });

    const findObservedExpense = id => {
        const numericId = Number(id);
        return currentBox?.expenses?.find(item => Number(item.id) === numericId)
            || pendingExpenses.find(item => Number(item.id) === numericId)
            || observedExpenses.find(item => Number(item.id) === numericId);
    };

    const openObservationDetail = expense => {
        const observations = expense?.observations || (expense?.current_observation ? [expense.current_observation] : []);
        if (!expense || !observations.length) return notify('warning', 'No se encontró historial de observaciones para este gasto.');
        const box = expense.petty_cash_box || currentBox;
        const symbol = box?.currency?.symbol || currentBox?.currency?.symbol || '';
        const number = expense.document_full_number || expense.document_number || '-';
        $('#pc_observation_expense_summary').html([
            ['Fecha del gasto', date(expense.expense_date)],
            ['Proveedor', expense.supplier_name],
            ['Concepto', expense.concept],
            ['Importe', money(expense.amount, symbol)],
            ['Comprobante', [expense.document_type, number].filter(Boolean).join(' ')],
            ['Registrado por', userName(expense.creator)]
        ].map(item => `<div><small>${item[0]}</small><strong>${escapeHtml(item[1] || '-')}</strong></div>`).join(''));
        $('#pc_observation_current_status').html(expense.approval_status === 'observado'
            ? '<span class="petty-approval-badge is-observed">Observado</span>'
            : '<span class="petty-approval-badge is-pending">Pendiente</span> <span class="petty-approval-badge is-lifted">Observación levantada</span>');
        $('#pc_observation_timeline').html([...observations].reverse().map((observation, index) => `
            <article class="petty-observation-timeline-item ${observation.status === 'OPEN' ? 'is-open' : 'is-resolved'}">
                <span class="petty-observation-timeline-marker"><i class="fas ${observation.status === 'OPEN' ? 'fa-exclamation' : 'fa-check'}"></i></span>
                <div class="petty-observation-timeline-card">
                    <small>OBSERVACIÓN ${index + 1}</small>
                    <h6>Gasto observado</h6>
                    <p>${escapeHtml(observation.observation || '-')}</p>
                    <div><i class="fas fa-user-shield mr-1"></i>${escapeHtml(userName(observation.observer))} · ${dateTime(observation.observed_at)}</div>
                    ${observation.status === 'RESOLVED' ? `
                        <section>
                            <small>OBSERVACIÓN LEVANTADA</small>
                            <p>${escapeHtml(observation.correction_comment || 'Corrección registrada sin comentario histórico.')}</p>
                            <div><i class="fas fa-user-edit mr-1"></i>${escapeHtml(userName(observation.resolver))} · ${dateTime(observation.resolved_at)}</div>
                        </section>` : '<span class="petty-observation-open-label">Pendiente de corrección</span>'}
                </div>
            </article>`).join(''));
        $('#btnCorrectObservedExpense')
            .data('id', expense.id)
            .toggle(Boolean(app.data('can-expense-update')) || Number(expense.created_by) === Number(app.data('current-user-id')));
        $('#pettyCashObservationDetailModal').modal('show');
    };

    const loadObservedExpenses = () => api({ url: app.data('observed-expenses-url'), method: 'GET' }).done(response => {
        observedExpenses = response.data || [];
        const count = Number(response.count || 0);
        updateAttentionCounter('#btnObservedPettyCashExpenses', '#pcObservedExpensesBadge', count, 'Gastos observados');
        $('#pc_observed_expenses_body').html(observedExpenses.length ? observedExpenses.map(expense => {
            const box = expense.petty_cash_box;
            const observation = expense.current_observation?.observation || '';
            const excerpt = observation.length > 52 ? `${observation.slice(0, 52)}…` : observation;
            const canCorrect = Boolean(app.data('can-expense-update'))
                || Number(expense.created_by) === Number(app.data('current-user-id'));
            const correctAction = canCorrect
                ? ` <button class="btn btn-sm btn-warning correctObservedPettyCashExpense" data-id="${expense.id}"><i class="fas fa-edit mr-1"></i>Corregir</button>`
                : '';
            return `<tr><td>${date(expense.expense_date)}</td><td><strong>${escapeHtml(box?.code || '-')}</strong><small class="d-block text-muted">${escapeHtml(box?.company?.trade_name || box?.company?.business_name || '-')}</small></td><td>${escapeHtml(expense.supplier_name)}</td><td class="petty-concept-cell">${escapeHtml(expense.concept)}</td><td class="text-right petty-amount-cell">${money(expense.amount, box?.currency?.symbol)}</td><td><small class="petty-observation-excerpt">${escapeHtml(excerpt || '-')}</small></td><td><button class="btn btn-sm btn-outline-warning viewPettyCashObservation" data-id="${expense.id}"><i class="fas fa-eye mr-1"></i>Ver detalle</button>${correctAction}</td></tr>`;
        }).join('') : '<tr><td colspan="7" class="petty-empty-state"><i class="fas fa-check-circle"></i><strong>No tienes gastos observados por corregir.</strong></td></tr>');
    });

    $(document).on('click', '.viewPettyCashObservation', function () {
        const expense = findObservedExpense($(this).data('id'));
        if (expense) openObservationDetail(expense);
    });
    $(document).on('click', '.viewPettyCashObservationHistory', function () {
        const expense = findObservedExpense($(this).data('id'));
        if (expense) openObservationDetail(expense);
    });
    $('#btnObservedPettyCashExpenses').on('click', function () {
        loadObservedExpenses()
            .done(() => $('#observedPettyCashExpensesModal').modal('show'))
            .fail(xhr => notify('error', errorMessage(xhr)));
    });
    $(document).on('click', '.correctObservedPettyCashExpense, #btnCorrectObservedExpense', function () {
        const expense = findObservedExpense($(this).data('id'));
        if (!expense) return;
        $('#pettyCashObservationDetailModal,#observedPettyCashExpensesModal').modal('hide');
        loadBox(expense.petty_cash_box_id).done(box => {
            currentBox = box;
            $('<button type="button" class="editPettyCashExpense d-none">')
                .data('id', expense.id)
                .appendTo(document.body)
                .trigger('click')
                .remove();
        }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    $('#btnPendingPettyCashExpenses').on('click', function () {
        loadPendingExpenses().done(() => $('#pendingPettyCashExpensesModal').modal('show')).fail(xhr => notify('error', errorMessage(xhr)));
    });
    $('#btnApproveExpensesFromDetail').on('click', function () {
        $('#btnPendingPettyCashExpenses').trigger('click');
    });
    $(document).on('click', '.approvePettyCashExpense, .rejectPettyCashExpense, .observePettyCashExpense', function () {
        const expense = currentBox?.expenses?.find(item => Number(item.id) === Number($(this).data('id')));
        if (expense) {
            const action = $(this).hasClass('observePettyCashExpense')
                ? 'observe'
                : ($(this).hasClass('rejectPettyCashExpense') ? 'reject' : 'approve');
            openApprovalModal(expense, action);
        }
    });
    $(document).on('click', '.approvePendingPettyCashExpense, .rejectPendingPettyCashExpense, .observePendingPettyCashExpense', function () {
        const expenses = $('#pendingPettyCashExpensesModal').data('expenses') || [];
        const expense = expenses.find(item => Number(item.id) === Number($(this).data('id')));
        if (expense) {
            const action = $(this).hasClass('observePendingPettyCashExpense')
                ? 'observe'
                : ($(this).hasClass('rejectPendingPettyCashExpense') ? 'reject' : 'approve');
            openApprovalModal(expense, action);
        }
    });
    $('#pettyCashExpenseApprovalForm').on('submit', function (event) {
        event.preventDefault();
        const action = $('#pca_action').val();
        const expenseId = $('#pca_expense_id').val();
        const url = expenseActionUrl(action, expenseId);
        const observation = $('#pca_observation').val().trim();
        if (!url) return notify('error', 'La acción administrativa seleccionada no es válida.');
        if (action === 'reject' && !observation) return notify('warning', 'Ingresa el motivo del rechazo.');
        if (action === 'observe' && observation.length < 10) return notify('warning', 'Ingresa una observación clara de al menos 10 caracteres.');
        loading($(this), true);
        api({
            url,
            method: 'POST',
            data: action === 'observe' ? { observation } : { approval_observation: observation }
        })
            .done(response => {
                $('#pettyCashExpenseApprovalModal').modal('hide');
                if (response.counts) {
                    updateAttentionCounter('#btnPendingPettyCashExpenses', '#pcPendingExpensesBadge', response.counts.pending, 'Gastos por aprobar');
                    updateAttentionCounter('#btnObservedPettyCashExpenses', '#pcObservedExpensesBadge', response.counts.observed, 'Gastos observados');
                }
                table.ajax.reload(null, false);
                loadPendingExpenses();
                loadObservedExpenses();
                if (currentBox) loadBox(currentBox.id).done(renderDetail);
                notify('success', response.message);
            }).fail(xhr => notify('error', errorMessage(xhr))).always(() => loading($(this), false));
    });

    $(document).on('click', '.addPettyCashExpense, .btn-create-petty-cash-expense', function (event) {
        event.preventDefault();
        event.stopPropagation();
        $('#pce_observed_notice').remove();
        $('#pce_correction_section').addClass('d-none');
        $('#pce_correction_comment').prop('required', false).val('');
        $('#pettyCashExpenseForm')[0].reset(); $('#pc_expense_id').val(''); $('#pc_expense_box_id').val($(this).data('id'));
        resetExpenseDocuments();
        $('#pcExpenseTitle').text('Registrar gasto'); $('#pettyCashExpenseModal').modal('show');
    });

    $(document).on('click', '.editPettyCashExpense', function () {
        const expense = currentBox?.expenses?.find(item => Number(item.id) === Number($(this).data('id')));
        if (!expense) return;
        $('#pce_observed_notice').remove();
        $('#pce_correction_section').toggleClass('d-none', expense.approval_status !== 'observado');
        $('#pce_correction_comment').prop('required', expense.approval_status === 'observado').val('');
        if (expense.approval_status === 'observado') {
            const observation = expense.current_observation;
            $('#pettyCashExpenseForm .modal-body').prepend(
                `<div id="pce_observed_notice" class="alert alert-warning py-2 px-3"><strong>Este gasto fue observado por el administrador.</strong><br><small>Corrige la información solicitada y vuelve a enviarlo para aprobación.${observation ? `<br><b>Observación:</b> ${escapeHtml(observation.observation)}<br><b>Observado por:</b> ${escapeHtml(userName(observation.observer))} · ${dateTime(observation.observed_at)}` : ''}</small></div>`
            );
        }
        $('#pettyCashExpenseForm')[0].reset(); $('#pc_expense_id').val(expense.id); $('#pc_expense_box_id').val(currentBox.id);
        resetExpenseDocuments(expense.documents || []);
        ['expense_date','document_type','document_series','document_correlative','supplier_ruc','supplier_name','concept','amount','observation'].forEach(field => {
            let value = expense[field] ?? '';
            if (field === 'document_correlative' && !value && !expense.document_series) value = expense.document_number || '';
            $(`#pce_${field}`).val(String(value).slice(0, field === 'expense_date' ? 10 : undefined));
        });
        $('#pcExpenseTitle').text(expense.approval_status === 'observado' ? 'Corregir gasto observado' : 'Editar gasto'); $('#pettyCashExpenseModal').modal('show');
    });

    $('#pce_documents').on('change', function () {
        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        Array.from(this.files).forEach(file => {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(extension)) {
                notify('error', `Formato no permitido: ${file.name}`);
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                notify('error', `El archivo supera el tamaño permitido: ${file.name}`);
                return;
            }
            pendingExpenseFiles.push(file);
        });
        syncExpenseFileInput();
        renderExpenseDocuments();
    });

    $(document).on('click', '.removePendingExpenseDocument', function () {
        pendingExpenseFiles.splice(Number($(this).data('index')), 1);
        syncExpenseFileInput();
        renderExpenseDocuments();
    });

    $(document).on('click', '.removeExistingExpenseDocument', function () {
        const documentId = Number($(this).data('id'));
        const expenseId = Number($('#pc_expense_id').val());
        const remove = () => api({
            url: `${base}/expenses/${expenseId}/documents/${documentId}`,
            method: 'DELETE'
        }).done(response => {
            existingExpenseDocuments = existingExpenseDocuments.filter(document => Number(document.id) !== documentId);
            const expense = currentBox?.expenses?.find(item => Number(item.id) === expenseId);
            if (expense) expense.documents = existingExpenseDocuments;
            renderExpenseDocuments();
            if (currentBox) renderDetail(currentBox);
            notify('success', response.message);
        }).fail(xhr => notify('error', errorMessage(xhr)));

        Swal.fire({
            icon: 'warning',
            title: '¿Eliminar este comprobante?',
            text: 'El archivo se eliminará de forma permanente.',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545'
        }).then(result => result.isConfirmed && remove());
    });

    $('#pettyCashExpenseModal').on('hidden.bs.modal', function () {
        expensePreviewUrls.forEach(url => URL.revokeObjectURL(url));
        expensePreviewUrls = [];
    });

    let supplierRucRequest = null;
    $('#pce_supplier_ruc').on('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
        $('#pce_supplier_ruc_status').removeClass('text-success text-danger text-muted').text('');
    }).on('blur', function () {
        const ruc = this.value.trim();
        const status = $('#pce_supplier_ruc_status');
        if (!ruc) return;
        if (ruc.length !== 11) {
            status.addClass('text-danger').text('Ingrese un RUC válido de 11 dígitos.');
            return;
        }

        if (supplierRucRequest) supplierRucRequest.abort();
        $('#pce_supplier_ruc_loading').removeClass('d-none');
        status.removeClass('text-success text-danger').addClass('text-muted').text('Consultando RUC...');
        supplierRucRequest = api({ url: `${app.data('ruc-url')}/${ruc}`, method: 'GET' })
            .done(response => {
                const data = response.data || response;
                const businessName = response.razon_social || data.razonSocial || data.razon_social || data.nombre || data.nombre_o_razon_social || data.name || '';
                if (businessName) {
                    $('#pce_supplier_name').val(businessName).trigger('change');
                    status.removeClass('text-muted text-danger').addClass('text-success').text('Razón social encontrada.');
                }
            })
            .fail(xhr => {
                if (xhr.statusText === 'abort') return;
                status.removeClass('text-muted text-success').addClass('text-danger').text(
                    xhr.responseJSON?.message || 'No se pudo consultar el RUC. Verifique la configuración del servicio o complete manualmente.'
                );
                $('#pce_supplier_name').prop('readonly', false);
            })
            .always(() => {
                supplierRucRequest = null;
                $('#pce_supplier_ruc_loading').addClass('d-none');
            });
    });

    $('#pettyCashExpenseForm').on('submit', function (event) {
        event.preventDefault();
        const id = $('#pc_expense_id').val(), boxId = $('#pc_expense_box_id').val(), data = new FormData(this);
        if (id) data.append('_method', 'PUT');
        loading($(this), true);
        api({ url: id ? `${base}/expenses/${id}` : `${base}/${boxId}/expenses`, method: 'POST', data, processData: false, contentType: false })
            .done(response => {
                const detailIsOpen = $('#viewPettyCashModal').hasClass('show');
                $('#pettyCashExpenseModal').modal('hide');
                table.ajax.reload(null, false);
                loadObservedExpenses();

                if (detailIsOpen) {
                    loadBox(boxId)
                        .done(renderDetail)
                        .fail(xhr => notify('error', errorMessage(xhr)));
                }

                notify('success', response.message);
            })
            .fail(xhr => notify('error', errorMessage(xhr))).always(() => loading($(this), false));
    });

    $(document).on('click', '.deletePettyCashExpense', function () {
        const id = $(this).data('id');
        const run = () => api({ url: `${base}/expenses/${id}`, method: 'DELETE' }).done(response => { $('#viewPettyCashModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); }).fail(xhr => notify('error', errorMessage(xhr)));
        Swal.fire({ icon: 'warning', title: '¿Eliminar este gasto?', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' }).then(result => result.isConfirmed && run());
    });

    $(document).on('click', '.closePettyCash, .btn-close-petty-cash', function (event) {
        event.preventDefault();
        event.stopPropagation();
        loadBox($(this).data('id')).done(box => {
            const symbol = box.currency?.symbol || '';
            const summary = box.financial_summary || {};
            const pending = Number(summary.pending_replenishment ?? box.reimbursement_amount) || 0;
            const pendingExpenses = Number(box.pending_expenses_count || 0);
            const observedExpensesCount = Number(box.observed_expenses_count || 0);
            const unresolvedExpenses = pendingExpenses + observedExpensesCount;
            $('#pcc_box_id').val(box.id);
            $('#pcc_close_observation').val('');
            $('#pcc_summary').html([
                ['Monto aprobado', money(summary.approved_amount, symbol)],
                ['Fondo inicial', money(summary.initial_fund, symbol)],
                ['Total gastado', money(summary.total_expenses, symbol)],
                ['Total repuesto', money(summary.total_replenished, symbol)],
                ['Saldo disponible', money(summary.current_balance, symbol)],
                ['Pendiente de reposición', money(pending, symbol)]
            ].map(item => `<div class="petty-summary-item"><small>${item[0]}</small><strong>${item[1]}</strong></div>`).join(''));
            $('#pcc_pending_warning').toggleClass('d-none', pending <= 0).html(
                `<i class="fas fa-exclamation-triangle mr-1"></i> Existe un pendiente de reposición de <strong>${money(pending, symbol)}</strong>. El cierre será registrado por decisión de gerencia.`
            );
            $('#pcc_pending_expenses_warning').toggleClass('d-none', unresolvedExpenses === 0).html(
                '<i class="fas fa-ban mr-1"></i> No se puede cerrar la caja chica porque existen gastos pendientes de aprobación. Apruebe o rechace los gastos antes de cerrar.'
            );
            $('#btnConfirmClosePettyCash').prop('disabled', unresolvedExpenses > 0)
                .attr('title', pendingExpenses > 0 ? 'Tiene gastos pendientes de aprobación.' : '');
            $('#pettyCashCloseModal').modal('show');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    $('#btnConfirmClosePettyCash').on('click', function () {
        if ($(this).prop('disabled')) return;
        const button = $(this).prop('disabled', true);
        api({ url: `${base}/${$('#pcc_box_id').val()}/close`, method: 'POST', data: { close_observation: $('#pcc_close_observation').val() } })
            .done(response => { $('#pettyCashCloseModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); })
            .fail(xhr => notify('error', errorMessage(xhr))).always(() => button.prop('disabled', false));
    });

    $(document).on('click', '.deletePettyCash, .btn-cancel-petty-cash', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({ icon: 'warning', title: '¿Anular la caja chica?', text: 'Esta acción retirará la caja de la operación activa.', showCancelButton: true, confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' })
            .then(result => result.isConfirmed && api({ url: `${base}/${id}`, method: 'DELETE' }).done(response => { table.ajax.reload(null, false); notify('success', response.message); }).fail(xhr => notify('error', errorMessage(xhr))));
    });

    $(document).off('click', '.btn-replenish-petty-cash').on('click', '.btn-replenish-petty-cash', function (event) {
        event.preventDefault();
        event.stopPropagation();
        loadBox($(this).data('id')).done(box => {
            $('#pettyCashReplenishmentForm')[0].reset(); $('#pcr_box_id').val(box.id);
            resetSourceReceipts('replenishment');
            $('#pcr_fund_source_bank_account_id').prop('disabled', true).html('<option value="">Seleccione primero una empresa</option>');
            $('#pcr_fund_source_account_help').text('');
            const symbol = box.currency?.symbol || '';
            const summary = box.financial_summary || {};
            const pending = Math.max(0, Number(summary.pending_replenishment ?? box.reimbursement_amount));
            $('#pcr_code').text(box.code);
            $('#pcr_company').text(box.company?.trade_name || box.company?.business_name || '-');
            $('#pcr_summary').html([
                ['Monto aprobado', money(summary.approved_amount, symbol), 'fa-hand-holding-usd', 'is-approved'],
                ['Fondo inicial', money(summary.initial_fund, symbol), 'fa-coins', 'is-opening'],
                ['Total gastado', money(summary.total_expenses, symbol), 'fa-receipt', 'is-spent'],
                ['Total repuesto', money(summary.total_replenished, symbol), 'fa-sync-alt', 'is-replenished'],
                ['Saldo disponible', money(summary.current_balance, symbol), 'fa-wallet', 'is-balance'],
                ['Pendiente', money(pending, symbol), 'fa-hourglass-half', 'is-pending']
            ].map(item => `<div class="petty-replenishment-kpi ${item[3]}"><i class="fas ${item[2]}"></i><small>${item[0]}</small><strong>${item[1]}</strong></div>`).join(''));
            const hasPending = pending > 0;
            $('#pcr_no_pending').toggleClass('d-none', hasPending);
            $('#pcr_pending_status').toggleClass('d-none', !hasPending).html(
                `<i class="fas fa-exclamation-circle"></i><span>Pendiente de reposición:<strong>${money(pending, symbol)}</strong></span>`
            );
            $('#pcr_amount').removeAttr('max').data('pending', pending).val(pending.toFixed(2)).prop('disabled', !hasPending);
            $('#pcr_excess_warning').addClass('d-none');
            $('#btnSavePettyCashReplenishment').prop('disabled', !hasPending);
            $('#pcr_date').val(new Date().toISOString().slice(0, 10));
            $('#pettyCashReplenishmentModal').modal('show');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    });

    $('#pcr_amount').on('input', function () {
        $('#pcr_excess_warning').toggleClass('d-none', Number(this.value || 0) <= Number($(this).data('pending') || 0));
    });

    $('#pettyCashReplenishmentForm').on('submit', function (event, confirmed = false) {
        event.preventDefault();
        const amount = Number($('#pcr_amount').val() || 0);
        const pending = Number($('#pcr_amount').data('pending') || 0);
        if (!confirmed && amount > pending) {
            Swal.fire({
                icon: 'warning',
                title: 'La reposición supera el pendiente',
                text: 'Revise si corresponde registrar este monto por decisión de gerencia.',
                showCancelButton: true,
                confirmButtonText: 'Registrar de todos modos',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#b7791f'
            }).then(result => result.isConfirmed && $(this).trigger('submit', [true]));
            return;
        }
        const data = new FormData(this); loading($(this), true);
        api({ url: `${base}/${$('#pcr_box_id').val()}/replenishments`, method: 'POST', data, processData: false, contentType: false })
            .done(response => { $('#pettyCashReplenishmentModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); })
            .fail(xhr => notify('error', errorMessage(xhr))).always(() => loading($(this), false));
    });

    const openReceiptExchange = box => {
        currentBox = box;
        const boxId = box.id;
        api({ url: `${base}/${boxId}/receipt-exchanges/pending`, method: 'GET' }).done(response => {
            pendingExchangeReceipts = response.data || [];
            if (!pendingExchangeReceipts.length) {
                notify('info', 'No hay recibos aprobados pendientes de canje para esta caja.');
                return;
            }
            receiptExchangeFiles = [];
            $('#pettyCashReceiptExchangeForm')[0].reset();
            renderReceiptExchangeFiles();
            $('#pcre_box_id').val(boxId);
            $('#pcre_date').val(new Date().toISOString().slice(0, 10));
            $('#pcre_receipts').html(pendingExchangeReceipts.length ? pendingExchangeReceipts.map(receipt => {
                const number = [receipt.document_series, receipt.document_correlative].filter(Boolean).join('-') || receipt.document_number || '-';
                return `<tr><td><input type="checkbox" class="pcre-receipt" name="expense_ids[]" value="${receipt.id}"></td><td>${date(receipt.expense_date)}</td><td><strong>RECIBO ${escapeHtml(number)}</strong></td><td>${escapeHtml(receipt.supplier_name)}</td><td>${escapeHtml(receipt.concept)}</td><td class="text-right petty-amount-cell">${money(receipt.amount, currentBox?.currency?.symbol || '')}</td><td><span class="petty-approval-badge is-approved">Aprobado</span></td></tr>`;
            }).join('') : '<tr><td colspan="7" class="petty-empty-state"><strong>No hay recibos aprobados pendientes de canje.</strong></td></tr>');
            updateReceiptExchangeSelection();
            $('#pettyCashReceiptExchangeModal').modal('show');
        }).fail(xhr => notify('error', errorMessage(xhr)));
    };
    $(document).on('click', '.exchangePettyCashReceipts, .btn-exchange-petty-cash-receipts', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const boxId = $(this).data('id');
        if (currentBox && Number(currentBox.id) === Number(boxId)) openReceiptExchange(currentBox);
        else loadBox(boxId).done(openReceiptExchange).fail(xhr => notify('error', errorMessage(xhr)));
    });

    $(document).on('change', '.pcre-receipt', updateReceiptExchangeSelection);
    $('#pcre_documents').on('change', function () {
        Array.from(this.files).forEach(file => {
            const extension = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'jpg', 'jpeg', 'png'].includes(extension)) notify('error', `Formato no permitido: ${file.name}`);
            else if (file.size > 10 * 1024 * 1024) notify('error', `El archivo supera el tamaño permitido: ${file.name}`);
            else receiptExchangeFiles.push(file);
        });
        renderReceiptExchangeFiles();
    });
    $(document).on('click', '.removeReceiptExchangeFile', function () {
        receiptExchangeFiles.splice(Number($(this).data('index')), 1);
        renderReceiptExchangeFiles();
    });

    $('#pettyCashReceiptExchangeForm').on('submit', function (event) {
        event.preventDefault();
        if (!$('.pcre-receipt:checked').length) {
            notify('warning', 'Seleccione al menos un recibo para canjear.');
            return;
        }
        const form = $(this);
        const data = new FormData(this);
        loading(form, true);
        api({ url: `${base}/${$('#pcre_box_id').val()}/receipt-exchanges`, method: 'POST', data, processData: false, contentType: false })
            .done(response => {
                $('#pettyCashReceiptExchangeModal').modal('hide');
                table.ajax.reload(null, false);
                if (currentBox) loadBox(currentBox.id).done(box => {
                    renderDetail(box);
                    $('#viewPettyCashModal .nav-link[href="#pcv_tab_exchanges"]').tab('show');
                });
                notify('success', response.message);
            })
            .fail(xhr => notify('error', errorMessage(xhr)))
            .always(() => loading(form, false));
    });
});
