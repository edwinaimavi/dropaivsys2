import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';

$(function () {
    const app = $('#pettyCashApp');
    if (!app.length) return;

    const base = app.data('base-url');
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const expiredSessionMessage = 'Tu sesión ha vencido o el formulario expiró. Recarga la página e intenta nuevamente.';

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json'
        }
    });
    const canEditExpenseDocument = Boolean(app.data('can-edit-expense-document'));
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
    let imageEditorCropper = null;
    let imageEditorTarget = null;
    let imageEditorObjectUrl = null;
    let pendingExchangeReceipts = [];
    let receiptIssuerLookup = null;
    let loadedReceiptIssuerRuc = '';
    let availableWarehouseExpenses = [];
    let selectedWarehouseExpenses = new Map();
    let warehouseExpensePage = 1;
    let warehouseExpenseLastPage = 1;
    let warehouseExpenseCurrencySymbol = '';
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
    const isExpiredSession = xhr => [401, 419].includes(Number(xhr?.status));
    const errorMessage = xhr => isExpiredSession(xhr)
        ? expiredSessionMessage
        : xhr.responseJSON?.message || Object.values(xhr.responseJSON?.errors || {}).flat()[0] || 'No fue posible completar la operación.';
    const notifyRequestError = xhr => {
        if (!isExpiredSession(xhr)) return notify('error', errorMessage(xhr));

        if (!window.Swal) {
            alert(expiredSessionMessage);
            return;
        }

        Swal.fire({
            icon: 'warning',
            title: 'Sesión vencida',
            text: expiredSessionMessage,
            confirmButtonText: 'Recargar página',
            showCancelButton: true,
            cancelButtonText: 'Cerrar',
            confirmButtonColor: '#20765c'
        }).then(result => {
            if (result.isConfirmed) window.location.reload();
        });
    };
    const loading = (form, active) => form.find('[type="submit"]').prop('disabled', active).find('i').toggleClass('fa-spin', active);
    const userName = user => user ? [user.name, user.lastname].filter(Boolean).join(' ') : '-';
    const resolvedObservations = expense => (expense?.observations || [])
        .filter(observation => observation.status === 'RESOLVED' && observation.resolved_at);
    const latestLiftedObservation = expense => resolvedObservations(expense)
        .sort((first, second) =>
            (new Date(second.resolved_at) - new Date(first.resolved_at))
            || (Number(second.id) - Number(first.id))
        )[0] || null;
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

    const api = (options) => {
        const token = csrfToken();
        const headers = {
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        if (token) headers['X-CSRF-TOKEN'] = token;
        if (options.data instanceof FormData && token && !options.data.has('_token')) {
            options.data.append('_token', token);
        }

        return $.ajax({ ...options, headers });
    };
    const loadBox = id => api({ url: `${base}/${id}`, method: 'GET' }).then(response => response.data);
    const stackedModalSelector = '.petty-detail-modal, .petty-expense-modal, .petty-approved-modal, .petty-replenishment-modal, .petty-receipt-exchange-modal, .petty-approval-modal, .petty-observation-detail-modal, .petty-expense-detail-modal, .petty-image-editor-modal, .petty-warehouse-expense-modal';
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
    const setReceiptIssuerSource = (source = '') => {
        const labels = { cache: 'Historial', api: 'SUNAT/API', manual: 'Manual' };
        $('#pcre_issuer_source').text(labels[source] || '').toggleClass('d-none', !labels[source]);
    };
    const resetReceiptIssuer = () => {
        receiptIssuerLookup = null;
        loadedReceiptIssuerRuc = '';
        $('#pcre_document_issuer_id').val('');
        $('#pcre_issuer_business_name').val('').prop('readonly', true).removeClass('is-valid is-invalid');
        $('#pcre_issuer_status').text('');
        setReceiptIssuerSource();
    };
    const searchReceiptIssuer = () => {
        const ruc = String($('#pcre_issuer_ruc').val() || '').replace(/\D/g, '').slice(0, 11);
        $('#pcre_issuer_ruc').val(ruc);
        if (ruc.length !== 11) {
            notify('warning', 'Ingrese un RUC válido de 11 dígitos.');
            return;
        }
        if (receiptIssuerLookup || (ruc === loadedReceiptIssuerRuc && $('#pcre_issuer_business_name').val())) return;

        const button = $('#pcre_search_issuer');
        const searchUrl = window.pettyCashRoutes?.documentIssuerSearch
            || app.attr('data-document-issuer-search-url');
        if (!searchUrl) {
            $('#pcre_issuer_business_name').prop('readonly', false);
            setReceiptIssuerSource('manual');
            notify('error', 'No se encontró la ruta para consultar el RUC. Puede ingresar la razón social manualmente.');
            return;
        }
        receiptIssuerLookup = api({ url: searchUrl, method: 'GET', data: { ruc }, dataType: 'json' });
        button.prop('disabled', true).find('span').text('Buscando...');
        button.find('i').removeClass('fa-search').addClass('fa-spinner fa-spin');
        $('#pcre_issuer_ruc').prop('readonly', true);
        $('#pcre_issuer_status').removeClass('text-success text-danger').addClass('text-muted').text('Consultando información fiscal...');
        receiptIssuerLookup.done(response => {
            const issuer = response.data || {};
            loadedReceiptIssuerRuc = ruc;
            $('#pcre_document_issuer_id').val(issuer.id || '');
            $('#pcre_issuer_business_name').val(issuer.business_name || '').prop('readonly', true).addClass('is-valid').removeClass('is-invalid');
            setReceiptIssuerSource(response.source);
            const origin = response.source === 'cache' ? 'Datos cargados desde el historial del sistema.' : 'Datos obtenidos desde SUNAT/API y guardados en el historial.';
            const fiscal = [issuer.status, issuer.condition].filter(Boolean).join(' · ');
            $('#pcre_issuer_status').removeClass('text-muted text-danger').addClass('text-success').text([origin, fiscal].filter(Boolean).join(' '));
        }).fail(xhr => {
            loadedReceiptIssuerRuc = ruc;
            $('#pcre_document_issuer_id').val('');
            $('#pcre_issuer_business_name').val('').prop('readonly', false).addClass('is-invalid').removeClass('is-valid').trigger('focus');
            setReceiptIssuerSource('manual');
            $('#pcre_issuer_status').removeClass('text-muted text-success').addClass('text-danger').text(errorMessage(xhr));
        }).always(() => {
            receiptIssuerLookup = null;
            button.prop('disabled', false).find('span').text('Buscar');
            button.find('i').removeClass('fa-spinner fa-spin').addClass('fa-search');
            $('#pcre_issuer_ruc').prop('readonly', false);
        });
    };
    // Delegados para que funcionen aunque Bootstrap reconstruya o inserte el modal después.
    $(document).on('click.pettyCashIssuer', '#pcre_search_issuer', function (event) {
        event.preventDefault();
        searchReceiptIssuer();
    });
    $(document).on('keydown.pettyCashIssuer', '#pcre_issuer_ruc', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            searchReceiptIssuer();
        }
    });

    const initializeDetailTooltips = () => {
        const tooltips = $('#viewPettyCashModal [data-toggle="tooltip"]');
        tooltips.tooltip('dispose').tooltip({
            container: 'body',
            boundary: 'window',
            trigger: 'hover focus',
            template: detailTooltipTemplate
        });
    };

    const expenseDetailStatus = expense => {
        const states = {
            pendiente_aprobacion: ['Pendiente de aprobación', 'is-pending'],
            observado: ['Observado', 'is-observed'],
            aprobado: ['Aprobado', 'is-approved'],
            rechazado: ['Rechazado', 'is-rejected'],
            anulado: ['Anulado', 'is-cancelled']
        };
        return states[expense.approval_status] || [expense.approval_status || 'Pendiente', 'is-pending'];
    };

    const expenseTimelineItem = event => `
        <article class="petty-expense-timeline-item ${event.className}">
            <span class="petty-expense-timeline-icon"><i class="fas ${event.icon}"></i></span>
            <div>
                <header><strong>${escapeHtml(event.title)}</strong><time>${dateTime(event.at)}</time></header>
                <small>Por: ${escapeHtml(userName(event.user))}</small>
                ${event.label ? `<p><b>${escapeHtml(event.label)}:</b> ${escapeHtml(event.message || '-')}</p>` : ''}
            </div>
        </article>`;

    const expenseWarehouseTrace = expense => {
        const linkedExpense = expense?.warehouse_entry_expense;
        const entry = linkedExpense?.warehouse_entry;
        if (!linkedExpense || !entry) {
            return {
                html: '<span class="badge badge-light border">Pendiente de vincular</span>',
                text: 'Pendiente de vincular'
            };
        }
        const supplierOrder = entry.supplier_purchase_order;
        const customerOrders = supplierOrder?.customer_purchase_orders?.length
            ? supplierOrder.customer_purchase_orders
            : (supplierOrder?.customer_purchase_order ? [supplierOrder.customer_purchase_order] : []);
        const supplierOrderLabel = supplierOrder?.code || supplierOrder?.purchase_order_number;
        const customerOrderLabels = customerOrders
            .map(order => order.code || order.purchase_order_number)
            .filter(Boolean);
        const parts = [
            `Ingreso ${entry.entry_number || `#${entry.id}`}`,
            supplierOrderLabel ? `OC proveedor ${supplierOrderLabel}` : '',
            customerOrderLabels.length ? `OC cliente ${customerOrderLabels.join(', ')}` : ''
        ].filter(Boolean);
        const activeLink = linkedExpense.status === 'ACTIVE';
        return {
            html: `<span class="badge badge-${activeLink ? 'info' : 'secondary'}"><i class="fas fa-warehouse mr-1"></i>${activeLink ? 'Vinculado' : 'Vínculo anulado'}</span><small class="petty-approval-trace">${parts.map(escapeHtml).join('<br>')}</small>`,
            text: `${activeLink ? 'Vinculado' : 'Vínculo anulado'} · ${parts.join(' · ')}`
        };
    };

    const renderExpenseDetail = expense => {
        const symbol = expense.petty_cash_box?.currency?.symbol || currentBox?.currency?.symbol || '';
        const number = expense.document_full_number || expense.document_number || '-';
        const status = expenseDetailStatus(expense);
        $('#pced_status').html(`<span class="petty-approval-badge ${status[1]}">${escapeHtml(status[0])}</span>`);
        const warehouse = expenseWarehouseTrace(expense);
        $('#pced_data').html([
            ['Fecha', date(expense.expense_date)],
            ['Tipo de comprobante', expense.document_type || '-'],
            ['Serie', expense.document_series || '-'],
            ['Correlativo', expense.document_correlative || '-'],
            ['Comprobante completo', [expense.document_type, number].filter(Boolean).join(' ')],
            ['RUC proveedor', expense.supplier_ruc || '-'],
            ['Proveedor', expense.supplier_name || '-'],
            ['Concepto', expense.concept || '-'],
            ['Importe', money(expense.amount, symbol)],
            ['Estado actual', status[0]],
            ['Vínculo con almacén', warehouse.text],
            ['Registrado por', userName(expense.creator)],
            ['Fecha de registro', dateTime(expense.created_at)]
        ].map(item => `<div><small>${escapeHtml(item[0])}</small><strong>${escapeHtml(item[1])}</strong></div>`).join(''));
        $('#pced_observation').text(String(expense.observation || '').trim() || 'Sin observación registrada.');

        const documents = expense.documents || [];
        $('#pced_documents_count').text(documents.length);
        $('#pced_documents').html(documents.length ? documents.map(document => {
            const isImage = String(document.mime_type || '').startsWith('image/');
            const visual = isImage
                ? `<a class="petty-expense-document-preview" href="${document.view_url}" target="_blank" title="Ver imagen"><img src="${document.view_url}" alt="${escapeHtml(document.original_name || 'Comprobante')}"></a>`
                : '<span class="petty-expense-document-pdf"><i class="fas fa-file-pdf"></i></span>';
            return `<article>${visual}<div><strong>${escapeHtml(document.original_name || 'Comprobante')}</strong><small>${escapeHtml(String(document.extension || document.mime_type || 'Archivo').toUpperCase())} · ${fileSize(document.file_size)}</small></div><div class="petty-expense-document-actions"><a href="${document.view_url}" target="_blank" title="Abrir documento"><i class="fas fa-external-link-alt"></i><span>Abrir</span></a><a href="${document.view_url}" download="${escapeHtml(document.original_name || '')}" title="Descargar documento"><i class="fas fa-download"></i><span>Descargar</span></a></div></article>`;
        }).join('') : '<div class="petty-expense-detail-empty"><i class="far fa-folder-open"></i> Sin comprobantes adjuntos.</div>');

        const events = [{
            title: 'Registrado',
            at: expense.created_at,
            user: expense.creator,
            icon: 'fa-plus',
            className: 'is-registered'
        }];
        (expense.observations || []).forEach(observation => {
            events.push({
                title: 'Observado',
                at: observation.observed_at,
                user: observation.observer,
                label: 'Observación del administrador',
                message: observation.observation,
                icon: 'fa-comment-alt',
                className: 'is-observed'
            });
            if (observation.status === 'RESOLVED' && observation.resolved_at) {
                events.push({
                    title: 'Corregido',
                    at: observation.resolved_at,
                    user: observation.resolver,
                    label: 'Levantamiento o corrección',
                    message: observation.correction_comment || 'Corrección registrada sin comentario.',
                    icon: 'fa-edit',
                    className: 'is-corrected'
                });
            }
        });
        (expense.events || []).forEach(event => events.push({
            title: event.event === 'comprobante_actualizado' ? 'Comprobante actualizado' : 'Actividad administrativa',
            at: event.created_at,
            user: event.creator,
            label: 'Detalle',
            message: event.description,
            icon: 'fa-crop-alt',
            className: 'is-corrected'
        }));
        if (expense.approved_at) events.push({
            title: 'Aprobado',
            at: expense.approved_at,
            user: expense.approved_by,
            label: 'Observación de aprobación',
            message: expense.approval_observation || 'Sin observación de aprobación.',
            icon: 'fa-check',
            className: 'is-approved'
        });
        if (expense.rejected_at) events.push({
            title: 'Rechazado',
            at: expense.rejected_at,
            user: expense.rejected_by,
            label: 'Motivo de rechazo',
            message: expense.approval_observation,
            icon: 'fa-times',
            className: 'is-rejected'
        });
        events.sort((first, second) => new Date(first.at || 0) - new Date(second.at || 0));
        $('#pced_history_count').text(events.length);
        $('#pced_admin_empty').toggleClass('d-none', events.length > 1);
        $('#pced_timeline').html(events.map(expenseTimelineItem).join(''));

        const lastObservation = [...(expense.observations || [])]
            .sort((first, second) => Number(second.id) - Number(first.id))[0] || null;
        const lastLifted = latestLiftedObservation(expense);
        const approvalDetails = expense.approval_status === 'aprobado'
            ? [
                ['Estado de aprobación', 'Aprobado'],
                ['Aprobado por', userName(expense.approved_by)],
                ['Fecha de aprobación', dateTime(expense.approved_at)],
                ['Observación de aprobación', expense.approval_observation || 'Sin observación de aprobación.']
            ]
            : expense.approval_status === 'rechazado'
                ? [
                    ['Estado de aprobación', 'Rechazado'],
                    ['Rechazado por', userName(expense.rejected_by)],
                    ['Fecha de rechazo', dateTime(expense.rejected_at)],
                    ['Motivo de rechazo', expense.approval_observation || '-']
                ]
                : [
                    ['Estado de aprobación', status[0]],
                    ['Situación actual', lastLifted && expense.approval_status === 'pendiente_aprobacion'
                        ? 'Enviado nuevamente para revisión después del levantamiento.'
                        : 'Pendiente de decisión administrativa.']
                ];
        const observationDetails = [
            ['Última observación administrativa', lastObservation?.observation || 'Sin observaciones administrativas.'],
            ['Observado por', lastObservation ? userName(lastObservation.observer) : '-'],
            ['Último levantamiento del usuario', lastLifted?.correction_comment || 'Sin levantamientos registrados.'],
            ['Levantado por', lastLifted ? userName(lastLifted.resolver) : '-'],
            ['Fecha del levantamiento', lastLifted ? dateTime(lastLifted.resolved_at) : '-']
        ];
        const exchange = expense.exchange;
        let exchangeDetails;
        if (expense.exchange_status === 'CANJEADO' && exchange) {
            const linkedReceipts = (exchange.items || []).map(item =>
                `${item.receipt_type || 'RECIBO'} ${[item.receipt_series, item.receipt_correlative].filter(Boolean).join('-')}`
            ).join(', ');
            exchangeDetails = [
                ['Estado de canje', 'Canjeado'],
                ['Comprobante real', `${exchange.document_type || ''} ${exchange.document_full_number || '-'}`.trim()],
                ['Fecha de canje', date(exchange.exchange_date || expense.exchanged_at)],
                ['Canje realizado por', userName(exchange.creator)],
                ['Recibos vinculados', linkedReceipts || 'Sin detalle de recibos vinculados.']
            ];
        } else if (expense.exchange_status === 'PENDIENTE_CANJE') {
            exchangeDetails = [
                ['Estado de canje', 'Pendiente de canje'],
                ['Detalle', 'El recibo está aprobado y pendiente de ser reemplazado por el comprobante definitivo.']
            ];
        } else {
            exchangeDetails = [['Estado de canje', 'No aplica para este gasto.']];
        }
        const detailCard = (title, icon, modifier, items) => `
            <section class="petty-expense-admin-card ${modifier}">
                <header><span><i class="fas ${icon}"></i></span><div><small>${escapeHtml(title.toUpperCase())}</small><h6>${escapeHtml(title)}</h6></div></header>
                <div>${items.map(item => `<article><small>${escapeHtml(item[0])}</small><strong>${escapeHtml(item[1] || '-')}</strong></article>`).join('')}</div>
            </section>`;
        $('#pced_approval_exchange').html([
            detailCard('Aprobación', 'fa-user-check', `is-${expense.approval_status || 'pending'}`, approvalDetails),
            detailCard('Observaciones y levantamientos', 'fa-comments', 'is-observation', observationDetails),
            detailCard('Canje', 'fa-exchange-alt', 'is-exchange', exchangeDetails)
        ].join(''));
    };

    const openExpenseDetailModal = expenseId => {
        $('#pced_summary_tab').tab('show');
        $('#pced_content').addClass('d-none');
        $('#pced_loading').removeClass('d-none');
        $('#pettyCashExpenseDetailModal').modal('show');
        api({
            url: `${app.data('expense-detail-base-url')}/${expenseId}/detail`,
            method: 'GET'
        }).done(response => {
            renderExpenseDetail(response.data);
            $('#pced_loading').addClass('d-none');
            $('#pced_content').removeClass('d-none');
        }).fail(() => {
            $('#pettyCashExpenseDetailModal').modal('hide');
            notify('error', 'No se pudo cargar el detalle del gasto.');
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
        const isImage = mime.startsWith('image/') || ['jpg', 'jpeg', 'png', 'webp'].includes(extension);
        let preview;

        if (isImage) {
            const url = isExisting ? source.view_url : URL.createObjectURL(source);
            if (!isExisting) expensePreviewUrls.push(url);
            preview = `<a class="petty-receipt-image-preview" href="${url}" target="_blank" title="Ver comprobante"><img src="${url}" alt=""></a>`;
        } else {
            preview = '<span class="petty-receipt-pdf"><i class="fas fa-file-pdf"></i></span>';
        }

        const name = escapeHtml(source.original_name || source.name || 'Comprobante');
        const size = fileSize(source.file_size ?? source.size);
        const actions = isExisting
            ? `<a href="${source.view_url}" target="_blank" class="petty-receipt-action is-view" title="Abrir comprobante"><i class="fas fa-external-link-alt"></i></a>${isImage && canEditExpenseDocument ? `<button type="button" class="petty-receipt-action is-edit editStoredReceiptImage" data-id="${source.id}" title="Editar imagen"><i class="fas fa-crop-alt"></i></button>` : ''}<button type="button" class="petty-receipt-action is-remove removeExistingExpenseDocument" data-id="${source.id}" title="Eliminar comprobante"><i class="fas fa-trash"></i></button>`
            : `${isImage && canEditExpenseDocument ? `<button type="button" class="petty-receipt-action is-edit editPendingReceiptImage" data-collection="expense" data-index="${index}" title="Editar imagen"><i class="fas fa-crop-alt"></i></button>` : (!isImage ? '<span class="petty-receipt-pdf-help" title="Los PDF no requieren edición visual"><i class="fas fa-lock"></i></span>' : '')}<button type="button" class="petty-receipt-action is-remove removePendingExpenseDocument" data-index="${index}" title="Quitar archivo"><i class="fas fa-times"></i></button>`;

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
            let editAction = '';
            if (file.type.startsWith('image/')) {
                const url = URL.createObjectURL(file);
                state.urls.push(url);
                visual = `<a class="petty-source-image-preview" href="${url}" target="_blank" title="Ver comprobante"><img src="${url}" alt=""></a>`;
                editAction = canEditExpenseDocument
                    ? `<button type="button" class="editPendingReceiptImage" data-collection="${key}" data-index="${index}" title="Editar imagen"><i class="fas fa-crop-alt"></i></button>`
                    : '';
            }
            return `<article class="petty-source-file">${visual}<div><strong>${escapeHtml(file.name)}</strong><small>Nuevo · ${fileSize(file.size)}${file.type === 'application/pdf' ? ' · PDF sin edición visual' : ''}</small></div>${editAction}<button type="button" class="removeSourceReceipt" data-key="${key}" data-index="${index}" title="Quitar archivo"><i class="fas fa-times"></i></button></article>`;
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
            if (!['pdf', 'jpg', 'jpeg', 'png', 'webp'].includes(extension)) notify('error', `Formato no permitido: ${file.name}`);
            else if (file.size > 10 * 1024 * 1024) notify('error', `El archivo supera el tamaño permitido: ${file.name}`);
            else sourceReceipts[key].files.push(file);
        });
        syncSourceReceipts(key);
        renderSourceReceipts(key);
    };
    const imageEditorCollection = collection => collection === 'expense'
        ? pendingExpenseFiles
        : sourceReceipts[collection]?.files;
    const refreshEditedImageCollection = collection => {
        if (collection === 'expense') {
            syncExpenseFileInput();
            renderExpenseDocuments();
            return;
        }
        syncSourceReceipts(collection);
        renderSourceReceipts(collection);
    };
    const closeImageEditor = () => {
        imageEditorCropper?.destroy();
        imageEditorCropper = null;
        imageEditorTarget = null;
        if (imageEditorObjectUrl) URL.revokeObjectURL(imageEditorObjectUrl);
        imageEditorObjectUrl = null;
        $('#pcie_image').attr('src', '');
    };
    const openImageEditor = (collection, index) => {
        if (!canEditExpenseDocument) {
            return notify('error', 'No tienes permiso para editar comprobantes de caja chica.');
        }
        const file = imageEditorCollection(collection)?.[index];
        if (!file || !String(file.type).startsWith('image/')) return;
        closeImageEditor();
        imageEditorTarget = { collection, index };
        imageEditorObjectUrl = URL.createObjectURL(file);
        $('#pcie_image').attr('src', imageEditorObjectUrl);
        $('#pettyCashImageEditorModal').modal('show');
        $('#pettyCashImageEditorModal').one('shown.bs.modal', () => {
            imageEditorCropper = new Cropper(document.getElementById('pcie_image'), {
                viewMode: 1,
                dragMode: 'crop',
                aspectRatio: NaN,
                autoCropArea: 0.92,
                responsive: true,
                background: false,
                checkOrientation: true
            });
        });
    };
    const openStoredImageEditor = documentId => {
        if (!canEditExpenseDocument) return notify('error', 'No tienes permiso para editar comprobantes de caja chica.');
        const storedDocument = existingExpenseDocuments.find(item => Number(item.id) === Number(documentId));
        if (!storedDocument || !String(storedDocument.mime_type || '').startsWith('image/')) return;
        closeImageEditor();
        const sourceUrl = `${storedDocument.view_url}${String(storedDocument.view_url).includes('?') ? '&' : '?'}v=${Date.now()}`;
        fetch(sourceUrl, { credentials: 'same-origin', headers: { Accept: 'image/*' } })
            .then(response => {
                if (!response.ok || !String(response.headers.get('content-type') || '').startsWith('image/')) {
                    throw new Error('No se pudo cargar la imagen guardada.');
                }
                return response.blob();
            })
            .then(blob => {
                imageEditorTarget = { type: 'stored', documentId: Number(documentId), expenseId: Number($('#pc_expense_id').val()), document: storedDocument };
                imageEditorObjectUrl = URL.createObjectURL(blob);
                $('#pcie_image').attr('src', imageEditorObjectUrl);
                $('#pettyCashImageEditorModal').modal('show').one('shown.bs.modal', () => {
                    imageEditorCropper = new Cropper(document.getElementById('pcie_image'), {
                        viewMode: 1, dragMode: 'crop', aspectRatio: NaN, autoCropArea: 0.92,
                        responsive: true, background: false, checkOrientation: true
                    });
                });
            })
            .catch(error => notify('error', error.message || 'No se pudo cargar la imagen guardada.'));
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
                select.html('<option value="">Seleccione cuenta bancaria</option>' + accounts.map(account => `<option value="${account.id}" data-currency="${escapeHtml(account.currency_code || '')}">${escapeHtml(account.label)}</option>`).join(''))
                    .prop('disabled', !accounts.length).val(String(selectedId || ''));
                $(helpSelector).text(accounts.length ? '' : 'Esta empresa no tiene cuentas bancarias registradas.');
                select.trigger('change.bankExchangeRate');
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
    $(document).on('click', '.editPendingReceiptImage', function () {
        openImageEditor(String($(this).data('collection')), Number($(this).data('index')));
    });
    $(document).on('click', '.editStoredReceiptImage', function () {
        openStoredImageEditor(Number($(this).data('id')));
    });
    $('#pcie_rotate_left').on('click', () => imageEditorCropper?.rotate(-90));
    $('#pcie_rotate_right').on('click', () => imageEditorCropper?.rotate(90));
    $('#pcie_crop').on('click', () => imageEditorCropper?.crop().setDragMode('crop'));
    $('#pcie_reset').on('click', () => imageEditorCropper?.reset());
    $('#pcie_apply').on('click', function () {
        if (!imageEditorCropper || !imageEditorTarget) return;
        const target = { ...imageEditorTarget };
        const original = target.type === 'stored' ? target.document : imageEditorCollection(target.collection)?.[target.index];
        if (!original) return;
        const originalType = original.type || original.mime_type;
        const outputType = originalType === 'image/png' ? 'image/png' : (originalType === 'image/webp' ? 'image/webp' : 'image/jpeg');
        const extension = outputType === 'image/png' ? 'png' : (outputType === 'image/webp' ? 'webp' : 'jpg');
        const safeBaseName = String(original.name || original.original_name || 'comprobante')
            .replace(/\.[^.]+$/, '')
            .replace(/[^\w.-]+/g, '-');
        const button = $(this).prop('disabled', true);
        const canvas = imageEditorCropper.getCroppedCanvas({
            maxWidth: 3000,
            maxHeight: 3000,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high',
            fillColor: outputType === 'image/jpeg' ? '#fff' : 'transparent'
        });
        if (!canvas) {
            button.prop('disabled', false);
            return notify('error', 'No se pudo generar la imagen editada.');
        }
        canvas.toBlob(blob => {
            button.prop('disabled', false);
            if (!blob) return notify('error', 'No se pudo generar la imagen editada.');
            if (blob.size > 10 * 1024 * 1024) {
                return notify('error', 'La imagen editada supera el tamaño máximo permitido.');
            }
            const editedFile = new File([blob], `${safeBaseName}-editado.${extension}`, {
                type: outputType,
                lastModified: Date.now()
            });
            if (target.type === 'stored') {
                const formData = new FormData();
                formData.append('image', editedFile);
                button.prop('disabled', true);
                api({
                    url: `${base}/expenses/${target.expenseId}/documents/${target.documentId}/replace-image`,
                    method: 'POST', data: formData, processData: false, contentType: false
                }).done(response => {
                    const updated = response.document;
                    updated.view_url = `${updated.view_url}${String(updated.view_url).includes('?') ? '&' : '?'}v=${Date.now()}`;
                    const index = existingExpenseDocuments.findIndex(item => Number(item.id) === target.documentId);
                    if (index !== -1) existingExpenseDocuments[index] = updated;
                    const expense = currentBox?.expenses?.find(item => Number(item.id) === target.expenseId);
                    if (expense) expense.documents = [...existingExpenseDocuments];
                    renderExpenseDocuments();
                    if (currentBox) renderDetail(currentBox);
                    $('#pettyCashImageEditorModal').modal('hide');
                    notify('success', response.message);
                }).fail(notifyRequestError).always(() => button.prop('disabled', false));
                return;
            }
            const collection = imageEditorCollection(target.collection);
            if (!collection?.[target.index]) return;
            collection[target.index] = editedFile;
            refreshEditedImageCollection(target.collection);
            $('#pettyCashImageEditorModal').modal('hide');
            notify('success', 'La imagen fue editada y se guardará con el comprobante.');
        }, outputType, outputType === 'image/jpeg' ? 0.88 : undefined);
    });
    $('#pettyCashImageEditorModal').on('hidden.bs.modal', closeImageEditor);
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
    $(document).on('change.bankExchangeRate', '#pc_fund_source_bank_account_id,#pcr_fund_source_bank_account_id', function () {
        const opening = this.id === 'pc_fund_source_bank_account_id';
        const prefix = opening ? '#pc' : '#pcr';
        const foreign = String($(this).find('option:selected').data('currency') || '').toUpperCase() !== 'PEN' && Boolean(this.value);
        $(`${prefix}_fund_source_exchange_rate_group`).toggleClass('d-none', !foreign);
        $(`${prefix}_fund_source_exchange_rate`).prop('required', foreign);
        if (!foreign) $(`${prefix}_fund_source_exchange_rate`).val('');
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
        }).fail(notifyRequestError);
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
        }).fail(notifyRequestError)
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
            .fail(notifyRequestError)
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
            $('#pc_fund_source_exchange_rate').val(box.fund_source_exchange_rate || '');
            if (box.fund_source_company_id) {
                loadSourceAccounts(box.fund_source_company_id, '#pc_fund_source_bank_account_id', '#pc_fund_source_account_help', box.fund_source_bank_account_id);
            }
            $('#pc_side_expenses').text(money(box.total_expenses)); $('#pc_side_balance').text(money(box.cash_balance));
            $('#pettyCashModalLabel').text('Editar caja chica'); $('#btnSavePettyCash span').text('Actualizar Caja'); $('#pettyCashModal').modal('show');
        }).fail(notifyRequestError);
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
            const actions = `<span class="petty-row-actions"><button class="btn viewPettyCashExpenseDetail" data-id="${expense.id}" data-toggle="tooltip" title="Ver detalle del gasto" aria-label="Ver detalle del gasto"><i class="fas fa-eye"></i></button>${box.can_manage_expenses ? `${isPending && app.data('can-expense-approve') ? `<button class="btn approvePettyCashExpense" data-id="${expense.id}" title="Aprobar"><i class="fas fa-check"></i></button><button class="btn rejectPettyCashExpense" data-id="${expense.id}" title="Rechazar"><i class="fas fa-times"></i></button>` : ''}${isPending && app.data('can-expense-observe') ? `<button class="btn petty-observe-btn observePettyCashExpense" data-id="${expense.id}" title="Observar gasto"><i class="fas fa-comment-alt"></i></button>` : ''}${((isPending && app.data('can-expense-update')) || canCorrectObserved) ? `<button class="btn editPettyCashExpense" data-id="${expense.id}" title="${isObserved ? 'Corregir gasto observado' : 'Editar gasto'}"><i class="fas fa-edit"></i></button>` : ''}${app.data('can-expense-delete') ? `<button class="btn deletePettyCashExpense" data-id="${expense.id}" title="Eliminar gasto"><i class="fas fa-trash"></i></button>` : ''}` : ''}</span>`;
            const number = expense.document_full_number || expense.document_number || '';
            const voucher = number ? [expense.document_type, number].filter(Boolean).join(' ') : '-';
            const approval = approvalStatusHtml(expense);
            let exchange = '<span class="petty-no-document">No aplica</span>';
            if (expense.exchange_status === 'PENDIENTE_CANJE') exchange = '<span class="petty-exchange-badge is-pending">Pendiente de canje</span>';
            if (expense.exchange_status === 'CANJEADO') {
                const realDocument = expense.exchange ? `${expense.exchange.document_type} ${expense.exchange.document_series}-${expense.exchange.document_correlative}` : 'Canjeado';
                exchange = `<span class="petty-exchange-badge is-completed">Canjeado</span><small class="petty-approval-trace">${escapeHtml(realDocument)}</small>`;
            }
            const warehouse = expenseWarehouseTrace(expense);
            return `<tr><td><span class="petty-row-number">${expense.item_number}</span></td><td class="petty-date-cell">${date(expense.expense_date)}</td><td>${escapeHtml(voucher)}</td><td class="petty-supplier-cell">${escapeHtml(expense.supplier_name)}</td><td class="petty-concept-cell">${escapeHtml(expense.concept)}</td><td class="text-right petty-amount-cell">${money(expense.amount, symbol)}</td><td>${approval}</td><td>${exchange}</td><td>${warehouse.html}</td><td class="text-center">${docs}</td><td class="text-center">${actions}</td></tr>`;
        }).join('') : '<tr><td colspan="11" class="petty-empty-state"><i class="fas fa-receipt"></i><strong>No hay gastos registrados para esta caja.</strong><small>Los nuevos gastos aparecerán en esta sección.</small></td></tr>');
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
            const issuer = exchange.issuer_ruc ? `<small>RUC ${escapeHtml(exchange.issuer_ruc)}</small><strong>${escapeHtml(exchange.issuer_business_name || '-')}</strong>` : '<small>Emisor no registrado</small>';
            return `<article class="petty-exchange-history-item"><div><small>${date(exchange.exchange_date)}</small><strong>${escapeHtml(exchange.document_type)} ${escapeHtml(exchange.document_full_number)}</strong>${issuer}<span>${money(exchange.total_amount, symbol)}</span></div><ul>${receipts}</ul><div>${exchangeDocs}<small>${escapeHtml(userName(exchange.creator))}</small></div></article>`;
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

    $(document).on('click', '.viewPettyCashExpenseDetail', function () {
        $(this).tooltip('hide');
        openExpenseDetailModal($(this).data('id'));
    });

    $(document).on('click', '.viewPettyCash', function () {
        loadBox($(this).data('id')).done(box => {
            renderDetail(box);
            $('#viewPettyCashModal .nav-link[href="#pcv_tab_summary"]').tab('show');
            $('#viewPettyCashModal').modal('show');
        }).fail(notifyRequestError);
    });

    const selectReviewAction = action => {
        const reject = action === 'reject';
        const observe = action === 'observe';
        $('#pca_action').val(action);
        $('#pca_decision_field').removeClass('d-none');
        $('#pca_review_observation').val('').prop('required', reject || observe).attr({
            maxlength: observe ? 2000 : 1000,
            placeholder: observe ? 'Describe claramente qué debe corregir el usuario.' : (reject ? 'Indica el motivo del rechazo.' : 'Nota administrativa opcional.')
        }).trigger('focus');
        $('#pca_observation_label').text(observe ? 'Observación del administrador *' : (reject ? 'Motivo de rechazo *' : 'Observación administrativa (opcional)'));
        $('#pca_observation_help').text(observe ? 'Se enviará al usuario para que corrija el gasto.' : (reject ? 'El motivo quedará registrado en el historial administrativo.' : 'Puedes dejar una nota antes de aprobar.'));
        $('#btnConfirmExpenseApproval').removeClass('d-none btn-success btn-danger btn-warning')
            .addClass(observe ? 'btn-warning' : (reject ? 'btn-danger' : 'btn-success'))
            .find('span').text(observe ? 'Confirmar observación' : (reject ? 'Confirmar rechazo' : 'Confirmar aprobación'));
        $('.selectExpenseReviewAction').removeClass('active').filter(`[data-action="${action}"]`).addClass('active');
    };

    const openApprovalModal = expense => {
        approvalExpense = expense;
        const box = expense.petty_cash_box || currentBox;
        const symbol = box?.currency?.symbol || currentBox?.currency?.symbol || '';
        const number = expense.document_full_number || expense.document_number || '-';
        $('#pca_expense_id').val(expense.id);
        $('#pca_action').val('');
        $('#pca_decision_field,#btnConfirmExpenseApproval').addClass('d-none');
        $('.selectExpenseReviewAction').removeClass('active');
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
        $('#pca_expense_observation').text(
            String(expense.observation || '').trim() || 'Sin observación registrada.'
        );
        const lifted = latestLiftedObservation(expense);
        $('#pca_lifted_observation').toggleClass('d-none', !lifted);
        $('#pca_lifted_observation_message').text(lifted?.correction_comment || '');
        $('#pca_lifted_observation_user').text(userName(lifted?.resolver));
        $('#pca_lifted_observation_date').text(dateTime(lifted?.resolved_at));
        $('#btnViewApprovalObservationHistory').data('id', expense.id);
        const history = [];
        (expense.observations || []).forEach(item => history.push({ at: item.observed_at, icon: 'fa-comment-alt', title: 'Gasto observado', user: item.observer, detail: item.observation }));
        (expense.events || []).forEach(item => history.push({ at: item.created_at, icon: item.event === 'gasto_aprobado' ? 'fa-check' : (item.event === 'gasto_rechazado' ? 'fa-times' : 'fa-clipboard-check'), title: item.description, user: item.creator, detail: item.metadata?.observation || item.metadata?.reason || '' }));
        history.sort((a, b) => new Date(b.at || 0) - new Date(a.at || 0));
        $('#pca_history').html(history.length ? history.map(item => `<article><i class="fas ${item.icon}"></i><div><strong>${escapeHtml(item.title)}</strong><small>${escapeHtml(userName(item.user))} · ${dateTime(item.at)}</small>${item.detail ? `<p>${escapeHtml(item.detail)}</p>` : ''}</div></article>`).join('') : '<span class="text-muted d-block mt-2">Sin decisiones administrativas previas.</span>');
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
            const actions = `<button class="btn btn-sm btn-outline-success reviewPendingPettyCashExpense" data-id="${expense.id}" title="Revisar gasto"><i class="fas fa-clipboard-check mr-1"></i>Revisar</button>`;
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
    $(document).on('click', '#btnViewApprovalObservationHistory', function () {
        const expense = findObservedExpense($(this).data('id')) || approvalExpense;
        if (!expense) return;
        $('#pettyCashExpenseApprovalModal').modal('hide');
        openObservationDetail(expense);
    });
    $('#btnObservedPettyCashExpenses').on('click', function () {
        loadObservedExpenses()
            .done(() => $('#observedPettyCashExpensesModal').modal('show'))
            .fail(notifyRequestError);
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
        }).fail(notifyRequestError);
    });

    $('#btnPendingPettyCashExpenses').on('click', function () {
        loadPendingExpenses().done(() => $('#pendingPettyCashExpensesModal').modal('show')).fail(notifyRequestError);
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
    $(document).on('click', '.reviewPendingPettyCashExpense', function () {
        const expenses = $('#pendingPettyCashExpensesModal').data('expenses') || [];
        const expense = expenses.find(item => Number(item.id) === Number($(this).data('id')));
        if (expense) openApprovalModal(expense);
    });
    $(document).on('click', '.selectExpenseReviewAction', function () { selectReviewAction($(this).data('action')); });
    $('#pettyCashExpenseApprovalForm').on('submit', function (event) {
        event.preventDefault();
        const action = $('#pca_action').val();
        const expenseId = $('#pca_expense_id').val();
        const url = expenseActionUrl(action, expenseId);
        const observation = $('#pca_review_observation').val().trim();
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
            }).fail(notifyRequestError).always(() => loading($(this), false));
    });

    const warehouseExpenseCustomerOrders = expense => {
        const supplierOrder = expense.warehouse_entry?.supplier_purchase_order;
        if (supplierOrder?.customer_purchase_orders?.length) return supplierOrder.customer_purchase_orders;
        return supplierOrder?.customer_purchase_order ? [supplierOrder.customer_purchase_order] : [];
    };

    const warehouseExpenseCustomerNames = expense => {
        const entry = expense.warehouse_entry;
        const names = [entry?.customer, ...warehouseExpenseCustomerOrders(expense).map(order => order.customer)]
            .filter(Boolean)
            .map(customer => customer.business_name || customer.full_name || [customer.first_name, customer.last_name].filter(Boolean).join(' '))
            .filter(Boolean);
        return [...new Set(names)];
    };

    const updateWarehouseExpenseSelection = () => {
        $('.pc-warehouse-expense-check').each(function () {
            $(this).prop('checked', selectedWarehouseExpenses.has(Number(this.value)));
        });
        const pageIds = availableWarehouseExpenses.map(expense => Number(expense.id));
        $('#pcWarehouseExpenseSelectAll').prop(
            'checked',
            pageIds.length > 0 && pageIds.every(id => selectedWarehouseExpenses.has(id))
        );
        const total = [...selectedWarehouseExpenses.values()]
            .reduce((sum, expense) => sum + Number(expense.total_amount || expense.amount || 0), 0);
        $('#pcWarehouseExpenseSelection').text(`${selectedWarehouseExpenses.size} costo(s) seleccionado(s)`);
        $('#pcWarehouseExpenseTotal').text(`Total: ${money(total, warehouseExpenseCurrencySymbol)}`);
    };

    const renderWarehouseExpenses = () => {
        $('#pcWarehouseExpenseRows').html(availableWarehouseExpenses.length
            ? availableWarehouseExpenses.map(expense => {
                const entry = expense.warehouse_entry;
                const supplierOrder = entry?.supplier_purchase_order;
                const customerOrders = warehouseExpenseCustomerOrders(expense);
                const customerOrderCodes = customerOrders.map(order => order.code || order.purchase_order_number).filter(Boolean);
                const customerNames = warehouseExpenseCustomerNames(expense);
                const documentNumber = [expense.document_series, expense.document_number].filter(Boolean).join('-');
                const document = [expense.document_label, documentNumber].filter(Boolean).join(' ');
                const orders = [
                    supplierOrder?.code ? `OC Prov. ${supplierOrder.code}` : '',
                    customerOrderCodes.length ? `OC Cli. ${customerOrderCodes.join(', ')}` : ''
                ].filter(Boolean).join(' · ');
                return `<tr><td class="text-center"><input type="checkbox" class="pc-warehouse-expense-check" value="${expense.id}"></td><td>${date(expense.document_date || expense.created_at)}</td><td><strong>${escapeHtml(entry?.entry_number || `#${entry?.id || '-'}`)}</strong><small>${escapeHtml(orders || 'Sin órdenes relacionadas')}</small></td><td>${escapeHtml(customerNames.join(', ') || '-')}</td><td><strong>${escapeHtml(expense.provider_name || '-')}</strong><small>${escapeHtml(expense.provider_ruc || '')}</small></td><td><span class="badge badge-light border">${escapeHtml(expense.expense_type_label || 'Otros gastos')}</span></td><td>${escapeHtml(document || 'Sin comprobante')}<small><span class="badge badge-warning">No oficial</span></small></td><td class="text-right font-weight-bold">${money(expense.total_amount || expense.amount, warehouseExpenseCurrencySymbol)}</td><td>${escapeHtml(expense.description || '-')}<small><i class="fas fa-warehouse mr-1"></i>Origen: Almacén</small></td></tr>`;
            }).join('')
            : '<tr><td colspan="9" class="text-center text-muted py-4"><i class="fas fa-check-circle mr-1"></i>No hay costos de almacén pendientes para esta caja.</td></tr>');
        $('#pcWarehouseExpensePage').text(`Página ${warehouseExpensePage} de ${warehouseExpenseLastPage}`);
        $('#btnWarehouseExpensePrevious').prop('disabled', warehouseExpensePage <= 1);
        $('#btnWarehouseExpenseNext').prop('disabled', warehouseExpensePage >= warehouseExpenseLastPage);
        updateWarehouseExpenseSelection();
    };

    const loadWarehouseExpenses = (page = 1) => {
        const boxId = $('#pc_expense_box_id').val();
        if (!boxId) return notify('warning', 'Debe aperturar una caja chica activa para registrar estos gastos.');
        $('#pcWarehouseExpenseRows').html('<tr><td colspan="9" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm mr-1"></span>Consultando costos...</td></tr>');
        return api({
            url: `${base}/${boxId}/warehouse-expenses/available`,
            method: 'GET',
            data: {
                search: $('#pcWarehouseExpenseSearch').val(),
                date_from: $('#pcWarehouseExpenseDateFrom').val(),
                date_to: $('#pcWarehouseExpenseDateTo').val(),
                page,
                per_page: 20
            }
        }).done(response => {
            availableWarehouseExpenses = response.data || [];
            warehouseExpensePage = Number(response.meta?.current_page || 1);
            warehouseExpenseLastPage = Number(response.meta?.last_page || 1);
            warehouseExpenseCurrencySymbol = response.meta?.currency_symbol || '';
            renderWarehouseExpenses();
        }).fail(xhr => {
            availableWarehouseExpenses = [];
            $('#pcWarehouseExpenseRows').html(`<tr><td colspan="9" class="text-center text-danger py-4">${escapeHtml(errorMessage(xhr))}</td></tr>`);
        });
    };

    $(document).on('click', '#btnPullWarehouseExpenses', function () {
        if (!$('#pc_expense_box_id').val()) return notify('warning', 'Debe aperturar una caja chica activa para registrar estos gastos.');
        selectedWarehouseExpenses = new Map();
        warehouseExpensePage = 1;
        $('#pcWarehouseExpenseSearch,#pcWarehouseExpenseDateFrom,#pcWarehouseExpenseDateTo').val('');
        $('#pettyCashWarehouseExpenseModal').modal('show');
        loadWarehouseExpenses(1);
    });
    $(document).on('click', '#btnSearchWarehouseExpenses', () => loadWarehouseExpenses(1));
    $(document).on('click', '#btnClearWarehouseExpenseFilters', function () {
        $('#pcWarehouseExpenseSearch,#pcWarehouseExpenseDateFrom,#pcWarehouseExpenseDateTo').val('');
        loadWarehouseExpenses(1);
    });
    $(document).on('click', '#btnWarehouseExpensePrevious', () => loadWarehouseExpenses(warehouseExpensePage - 1));
    $(document).on('click', '#btnWarehouseExpenseNext', () => loadWarehouseExpenses(warehouseExpensePage + 1));
    $(document).on('change', '.pc-warehouse-expense-check', function () {
        const id = Number(this.value);
        const expense = availableWarehouseExpenses.find(item => Number(item.id) === id);
        if (this.checked && expense) selectedWarehouseExpenses.set(id, expense);
        else selectedWarehouseExpenses.delete(id);
        updateWarehouseExpenseSelection();
    });
    $(document).on('change', '#pcWarehouseExpenseSelectAll', function () {
        availableWarehouseExpenses.forEach(expense => {
            if (this.checked) selectedWarehouseExpenses.set(Number(expense.id), expense);
            else selectedWarehouseExpenses.delete(Number(expense.id));
        });
        updateWarehouseExpenseSelection();
    });
    $(document).on('click', '#btnConfirmWarehouseExpenses', function () {
        if (!selectedWarehouseExpenses.size) return notify('warning', 'Seleccione al menos un costo de almacén.');
        const button = $(this).prop('disabled', true);
        button.find('i').addClass('fa-spin fa-spinner').removeClass('fa-link');
        api({
            url: `${base}/${$('#pc_expense_box_id').val()}/warehouse-expenses/pull`,
            method: 'POST',
            data: { warehouse_entry_expense_ids: [...selectedWarehouseExpenses.keys()] }
        }).done(response => {
            $('#pettyCashWarehouseExpenseModal,#pettyCashExpenseModal').modal('hide');
            if (response.counts) {
                updateAttentionCounter('#btnPendingPettyCashExpenses', '#pcPendingExpensesBadge', response.counts.pending, 'Gastos por aprobar');
                updateAttentionCounter('#btnObservedPettyCashExpenses', '#pcObservedExpensesBadge', response.counts.observed, 'Gastos observados');
            }
            table.ajax.reload(null, false);
            loadPendingExpenses();
            if (currentBox) loadBox(currentBox.id).done(box => { currentBox = box; renderDetail(box); });
            notify('success', response.message);
        }).fail(notifyRequestError).always(() => {
            button.prop('disabled', false).find('i').removeClass('fa-spin fa-spinner').addClass('fa-link');
        });
    });

    $(document).on('click', '.addPettyCashExpense, .btn-create-petty-cash-expense', function (event) {
        event.preventDefault();
        event.stopPropagation();
        $('#pce_observed_notice').remove();
        $('#pce_correction_section').addClass('d-none');
        $('#pce_correction_comment').prop('required', false).val('');
        $('#pettyCashExpenseForm')[0].reset(); $('#pc_expense_id').val(''); $('#pc_expense_box_id').val($(this).data('id'));
        resetExpenseDocumentDuplicateState();
        resetExpenseDocuments();
        $('#btnPullWarehouseExpenses').removeClass('d-none');
        $('#pcExpenseTitle').text('Registrar gasto'); $('#pettyCashExpenseModal').modal('show');
    });

    $(document).on('click', '.editPettyCashExpense', function () {
        const expense = currentBox?.expenses?.find(item => Number(item.id) === Number($(this).data('id')));
        if (!expense) return;
        $('#pce_observed_notice').remove();
        $('#btnPullWarehouseExpenses').addClass('d-none');
        $('#pce_correction_section').toggleClass('d-none', expense.approval_status !== 'observado');
        $('#pce_correction_comment').prop('required', expense.approval_status === 'observado').val('');
        if (expense.approval_status === 'observado') {
            const observation = expense.current_observation;
            $('#pettyCashExpenseForm .modal-body').prepend(
                `<div id="pce_observed_notice" class="alert alert-warning py-2 px-3"><strong>Este gasto fue observado por el administrador.</strong><br><small>Corrige la información solicitada y vuelve a enviarlo para aprobación.${observation ? `<br><b>Observación:</b> ${escapeHtml(observation.observation)}<br><b>Observado por:</b> ${escapeHtml(userName(observation.observer))} · ${dateTime(observation.observed_at)}` : ''}</small></div>`
            );
        }
        $('#pettyCashExpenseForm')[0].reset(); $('#pc_expense_id').val(expense.id); $('#pc_expense_box_id').val(currentBox.id);
        resetExpenseDocumentDuplicateState();
        resetExpenseDocuments(expense.documents || []);
        ['expense_date','document_type','document_series','document_correlative','supplier_ruc','supplier_name','concept','amount','observation'].forEach(field => {
            let value = expense[field] ?? '';
            if (field === 'document_correlative' && !value && !expense.document_series) value = expense.document_number || '';
            $(`#pce_${field}`).val(String(value).slice(0, field === 'expense_date' ? 10 : undefined));
        });
        $('#pcExpenseTitle').text(expense.approval_status === 'observado' ? 'Corregir gasto observado' : 'Editar gasto'); $('#pettyCashExpenseModal').modal('show');
    });

    $('#pce_documents').on('change', function () {
        const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
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
        }).fail(notifyRequestError);

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
    let expenseDocumentCheckRequest = null;
    let expenseDocumentCheckTimer = null;
    let expenseDocumentIsDuplicate = false;

    const resetExpenseDocumentDuplicateState = () => {
        expenseDocumentIsDuplicate = false;
        $('#pce_document_duplicate_alert').addClass('d-none').find('span')
            .text('Este comprobante ya fue registrado en caja chica.');
        $('#btnSavePettyCashExpense').prop('disabled', false);
    };

    const checkExpenseDocumentDuplicate = () => {
        const payload = {
            document_type: String($('#pce_document_type').val() || '').trim().toUpperCase(),
            document_series: String($('#pce_document_series').val() || '').trim().toUpperCase(),
            document_correlative: String($('#pce_document_correlative').val() || '').trim(),
            supplier_ruc: String($('#pce_supplier_ruc').val() || '').trim(),
            expense_id: $('#pc_expense_id').val() || null
        };

        resetExpenseDocumentDuplicateState();
        if (!payload.document_type || !payload.document_series || !payload.document_correlative
            || !/^\d{11}$/.test(payload.supplier_ruc)) return;

        if (expenseDocumentCheckRequest) expenseDocumentCheckRequest.abort();
        expenseDocumentCheckRequest = api({
            url: app.data('expense-document-check-url'),
            method: 'GET',
            data: payload
        }).done(response => {
            expenseDocumentIsDuplicate = Boolean(response.exists);
            $('#pce_document_duplicate_alert')
                .toggleClass('d-none', !expenseDocumentIsDuplicate)
                .find('span').text(response.message || 'Este comprobante ya fue registrado en caja chica.');
            $('#btnSavePettyCashExpense').prop('disabled', expenseDocumentIsDuplicate);
        }).fail(xhr => {
            if (xhr.statusText !== 'abort') resetExpenseDocumentDuplicateState();
        }).always(() => {
            expenseDocumentCheckRequest = null;
        });
    };

    $('#pce_document_type, #pce_document_series, #pce_document_correlative, #pce_supplier_ruc')
        .on('input change blur', function () {
            clearTimeout(expenseDocumentCheckTimer);
            resetExpenseDocumentDuplicateState();
            expenseDocumentCheckTimer = setTimeout(checkExpenseDocumentDuplicate, 350);
        });

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
        if (expenseDocumentIsDuplicate) {
            notify('error', $('#pce_document_duplicate_alert span').text());
            return;
        }
        const id = $('#pc_expense_id').val(), boxId = $('#pc_expense_box_id').val(), data = new FormData(this);
        const wasObservedCorrection = Boolean(id) && !$('#pce_correction_section').hasClass('d-none');
        if (id) data.append('_method', 'PUT');
        loading($(this), true);
        api({ url: id ? `${base}/expenses/${id}` : `${base}/${boxId}/expenses`, method: 'POST', data, processData: false, contentType: false })
            .done(response => {
                $('#pettyCashExpenseModal').modal('hide');
                table.ajax.reload(null, false);
                if (response.counts) {
                    updateAttentionCounter('#btnPendingPettyCashExpenses', '#pcPendingExpensesBadge', response.counts.pending, 'Gastos por aprobar');
                    updateAttentionCounter('#btnObservedPettyCashExpenses', '#pcObservedExpensesBadge', response.counts.observed, 'Gastos observados');
                }
                if (response.expense) {
                    const updatedExpense = response.expense;
                    pendingExpenses = pendingExpenses
                        .filter(item => Number(item.id) !== Number(updatedExpense.id));
                    if (updatedExpense.approval_status === 'pendiente_aprobacion') pendingExpenses.unshift(updatedExpense);
                    observedExpenses = observedExpenses
                        .filter(item => Number(item.id) !== Number(updatedExpense.id));
                    $('#pendingPettyCashExpensesModal').data('expenses', pendingExpenses);
                    if (currentBox && Number(currentBox.id) === Number(boxId)) {
                        currentBox.expenses = (currentBox.expenses || []).map(item =>
                            Number(item.id) === Number(updatedExpense.id) ? updatedExpense : item
                        );
                        renderDetail(currentBox);
                    }
                    if ($('#pettyCashObservationDetailModal').hasClass('show')) openObservationDetail(updatedExpense);
                }
                loadPendingExpenses();
                loadObservedExpenses();
                loadBox(boxId).done(box => {
                    currentBox = box;
                    renderDetail(box);
                }).fail(notifyRequestError);
                if (wasObservedCorrection) {
                    $('#pce_correction_comment').val('');
                    $('#pce_correction_section').addClass('d-none');
                }
                notify('success', response.message);
            })
            .fail(notifyRequestError).always(() => loading($(this), false));
    });

    $(document).on('click', '.deletePettyCashExpense', function () {
        const id = $(this).data('id');
        const run = () => api({ url: `${base}/expenses/${id}`, method: 'DELETE' }).done(response => { $('#viewPettyCashModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); }).fail(notifyRequestError);
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
            const pendingExchangeReceipts = Number(box.pending_exchange_receipts_count || 0);
            const pendingWarehouseLinks = Number(box.pending_warehouse_link_expenses_count || 0);
            const hasPendingOperationalLinks = pendingExchangeReceipts > 0 || pendingWarehouseLinks > 0;
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
            $('#pcc_pending_link_warning').toggleClass('d-none', !hasPendingOperationalLinks).html(
                '<i class="fas fa-exclamation-triangle mr-1"></i> Esta caja tiene gastos pendientes de canje o vinculación. Si la cierra, ya no podrán jalarse desde almacén. ¿Desea continuar?'
            );
            $('#btnConfirmClosePettyCash').prop('disabled', unresolvedExpenses > 0)
                .attr('title', pendingExpenses > 0 ? 'Tiene gastos pendientes de aprobación.' : '');
            $('#pettyCashCloseModal').modal('show');
        }).fail(notifyRequestError);
    });

    $('#btnConfirmClosePettyCash').on('click', function () {
        if ($(this).prop('disabled')) return;
        const button = $(this).prop('disabled', true);
        api({ url: `${base}/${$('#pcc_box_id').val()}/close`, method: 'POST', data: { close_observation: $('#pcc_close_observation').val() } })
            .done(response => { $('#pettyCashCloseModal').modal('hide'); table.ajax.reload(null, false); notify('success', response.message); })
            .fail(notifyRequestError).always(() => button.prop('disabled', false));
    });

    $(document).on('click', '.deletePettyCash, .btn-cancel-petty-cash', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const id = $(this).data('id');
        Swal.fire({ icon: 'warning', title: '¿Anular la caja chica?', text: 'Esta acción retirará la caja de la operación activa.', showCancelButton: true, confirmButtonText: 'Sí, anular', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' })
            .then(result => result.isConfirmed && api({ url: `${base}/${id}`, method: 'DELETE' }).done(response => { table.ajax.reload(null, false); notify('success', response.message); }).fail(notifyRequestError));
    });

    $(document).off('click', '.btn-replenish-petty-cash').on('click', '.btn-replenish-petty-cash', function (event) {
        event.preventDefault();
        event.stopPropagation();
        loadBox($(this).data('id')).done(box => {
            $('#pettyCashReplenishmentForm')[0].reset(); $('#pcr_box_id').val(box.id);
            resetSourceReceipts('replenishment');
            $('#pcr_fund_source_bank_account_id').prop('disabled', true).html('<option value="">Seleccione primero una empresa</option>');
            $('#pcr_fund_source_exchange_rate').val('').prop('required', false);
            $('#pcr_fund_source_exchange_rate_group').addClass('d-none');
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
        }).fail(notifyRequestError);
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
            .fail(xhr => {
                if (window.console && xhr.responseJSON?.message) console.error('Error al registrar reposición:', xhr.responseJSON.message);
                notify('error', 'No se pudo registrar la reposición. Revise los datos e inténtelo nuevamente.');
            }).always(() => loading($(this), false));
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
            resetReceiptIssuer();
            renderReceiptExchangeFiles();
            $('#pcre_box_id').val(boxId);
            $('#pcre_date').val(new Date().toISOString().slice(0, 10));
            $('#pcre_receipts').html(pendingExchangeReceipts.length ? pendingExchangeReceipts.map(receipt => {
                const number = [receipt.document_series, receipt.document_correlative].filter(Boolean).join('-') || receipt.document_number || '-';
                return `<tr><td><input type="checkbox" class="pcre-receipt" name="expense_ids[]" value="${receipt.id}"></td><td>${date(receipt.expense_date)}</td><td><strong>RECIBO ${escapeHtml(number)}</strong></td><td>${escapeHtml(receipt.supplier_name)}</td><td>${escapeHtml(receipt.concept)}</td><td class="text-right petty-amount-cell">${money(receipt.amount, currentBox?.currency?.symbol || '')}</td><td><span class="petty-approval-badge is-approved">Aprobado</span></td></tr>`;
            }).join('') : '<tr><td colspan="7" class="petty-empty-state"><strong>No hay recibos aprobados pendientes de canje.</strong></td></tr>');
            updateReceiptExchangeSelection();
            $('#pettyCashReceiptExchangeModal').modal('show');
        }).fail(notifyRequestError);
    };
    $(document).on('click', '.exchangePettyCashReceipts, .btn-exchange-petty-cash-receipts', function (event) {
        event.preventDefault();
        event.stopPropagation();
        const boxId = $(this).data('id');
        if (currentBox && Number(currentBox.id) === Number(boxId)) openReceiptExchange(currentBox);
        else loadBox(boxId).done(openReceiptExchange).fail(notifyRequestError);
    });

    $(document).on('change', '.pcre-receipt', updateReceiptExchangeSelection);
    $('#pcre_issuer_ruc').on('input', function () {
        const clean = String(this.value || '').replace(/\D/g, '').slice(0, 11);
        if (this.value !== clean) this.value = clean;
        if (clean !== loadedReceiptIssuerRuc) resetReceiptIssuer();
    });
    $('#pcre_issuer_business_name').on('input', function () { if (!this.readOnly) setReceiptIssuerSource('manual'); });
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
            .fail(notifyRequestError)
            .always(() => loading(form, false));
    });
});
