let tableKardex;
let kardexGridNavigation;
let format12ModalState;

document.addEventListener('DOMContentLoaded', function () {
    initKardexSelect2();
    initKardexTable();
    initFormat12Modal();

    $(document).on('click', '#btnFilterKardex', function () {
        tableKardex.ajax.reload();
    });

    $(document).on('click', '#btnClearKardexFilters', function () {
        $('#kardex_filter_warehouse_id, #kardex_filter_article_id, #kardex_filter_movement_type').val('').trigger('change.select2');
        $('#kardex_filter_date_from, #kardex_filter_date_to, #kardex_filter_lot_number, #kardex_filter_document, #kardex_filter_related_party').val('');
        tableKardex.ajax.reload();
    });

    $(document).on('click', '.viewKardexMovement', function () {
        loadKardexMovementDetail($(this).data('id'));
    });

    $(document).on('click', '#btnKardexStockAtDate', loadKardexStockAtDate);
    $(document).on('click', '#btnRecalculateKardex', recalculateKardex);
});

function initKardexSelect2() {
    if (!$.fn.select2) {
        return;
    }

    $('.js-kardex-filter').select2({
        width: '100%'
    });
}

function initKardexTable() {
    tableKardex = $('#tableKardex').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.kardexList,
            data: function (data) {
                data.warehouse_id = $('#kardex_filter_warehouse_id').val();
                data.article_id = $('#kardex_filter_article_id').val();
                data.date_from = $('#kardex_filter_date_from').val();
                data.date_to = $('#kardex_filter_date_to').val();
                data.movement_type = $('#kardex_filter_movement_type').val();
                data.lot_number = $('#kardex_filter_lot_number').val();
                data.document = $('#kardex_filter_document').val();
                data.related_party = $('#kardex_filter_related_party').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'movement_date', name: 'movement_date', className: 'text-nowrap' },
            { data: 'movement_number', name: 'movement_number', render: renderKardexMovementNumber },
            { data: 'warehouse', name: 'warehouse.name', orderable: false, render: renderKardexClampedText },
            { data: 'article', name: 'article.billing_name', orderable: false, render: renderKardexArticleCell },
            { data: 'lot_number', name: 'lot_number', defaultContent: '-', render: renderKardexEllipsisText },
            { data: 'expiration_date', name: 'expiration_date', className: 'text-nowrap' },
            { data: 'movement_type', name: 'movement_type' },
            { data: 'document', name: 'document', orderable: false, searchable: false, render: renderKardexDocumentPill },
            { data: 'quantity_in', name: 'quantity_in', className: 'text-right kardex-cell-number', render: renderKardexEntryNumber },
            { data: 'entry_unit_cost', name: 'unit_cost', className: 'text-right kardex-cell-number', render: renderKardexMoneyCell },
            { data: 'entry_total_cost', name: 'total_cost_in', className: 'text-right kardex-cell-number', render: renderKardexMoneyCell },
            { data: 'quantity_out', name: 'quantity_out', className: 'text-right kardex-cell-number', render: renderKardexExitNumber },
            { data: 'exit_unit_cost', name: 'unit_cost', className: 'text-right kardex-cell-number', render: renderKardexMoneyCell },
            { data: 'exit_total_cost', name: 'total_cost_out', className: 'text-right kardex-cell-number', render: renderKardexMoneyCell },
            { data: 'balance_quantity', name: 'balance_quantity', className: 'text-right kardex-cell-number', render: renderKardexBalanceNumber },
            { data: 'average_unit_cost_display', name: 'average_unit_cost', className: 'text-right kardex-cell-number', render: renderKardexMoneyCell },
            { data: 'balance_total_cost', name: 'balance_total_cost', className: 'text-right kardex-cell-number', render: renderKardexMoneyCell },
            { data: 'created_by_label', name: 'created_by_label', orderable: false, searchable: false, render: renderKardexEllipsisText },
            { data: 'updated_by_label', name: 'updated_by_label', orderable: false, searchable: false, render: renderKardexEllipsisText },
            { data: 'status', name: 'status' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: false,
        autoWidth: false,
        scrollX: true,
        scrollY: '52vh',
        scrollCollapse: true,
        pageLength: 10,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        },
        dom: `
            <'kardex-dt-toolbar row align-items-center'
                <'col-12 col-md-4 col-xl-3'l>
                <'col-12 col-md-8 col-xl-5'f>
                <'col-12 col-xl-4'B>
            >
            <'row m-0'<'col-12 p-0'tr>>
            <'kardex-dt-footer row align-items-center'
                <'col-sm-12 col-md-5'i>
                <'col-sm-12 col-md-7 d-flex justify-content-center justify-content-md-end'p>
            >
        `,
        buttons: (window.routes.kardexCanExport ? ['excel', 'pdf', 'print'] : []).map(function (format) {
            const presentation = {
                excel: ['fa-file-excel', 'Excel', 'btn-success'],
                pdf: ['fa-file-pdf', 'PDF', 'btn-danger'],
                print: ['fa-print', 'Imprimir', 'btn-secondary']
            }[format];
            return {
                text: `<i class="fas ${presentation[0]}"></i> ${presentation[1]}`,
                className: `btn ${presentation[2]} btn-sm`,
                action: function () { window.open(`${window.routes.kardexExport}/${format}?${kardexFilterQuery()}`, '_blank'); }
            };
        }),
        initComplete: function () {
            const api = this.api();
            const $wrapper = $('#tableKardex_wrapper');
            $wrapper.find('.dataTables_filter input')
                .attr('placeholder', 'Buscar en movimientos...')
                .attr('aria-label', 'Buscar en movimientos Kardex');
            $wrapper.find('.dataTables_scrollBody')
                .attr('tabindex', '0')
                .attr('role', 'region')
                .attr('aria-label', 'Movimientos Kardex con desplazamiento horizontal y vertical');
            api.columns.adjust();
            initKardexGridNavigation(api);
        }
    });
}

function initKardexGridNavigation(api) {
    const wrapper = document.getElementById('tableKardex_wrapper');
    const navigation = document.querySelector('.kardex-grid-navigation');
    const scrollBody = wrapper?.querySelector('.dataTables_scrollBody');
    const scrollFrame = wrapper?.querySelector('.dataTables_scroll');
    const proxy = navigation?.querySelector('.kardex-scroll-proxy');
    const proxyTrack = navigation?.querySelector('.kardex-scroll-proxy-track');

    if (!wrapper || !navigation || !scrollBody || !scrollFrame || !proxy || !proxyTrack) {
        return;
    }

    let syncingFromBody = false;
    let syncingFromProxy = false;
    let resizeFrame;

    kardexGridNavigation = { api, wrapper, navigation, scrollBody, scrollFrame, proxy, proxyTrack };

    scrollBody.addEventListener('scroll', function () {
        if (!syncingFromProxy) {
            syncingFromBody = true;
            setKardexScrollRatio(proxy, getKardexScrollRatio(scrollBody));
            syncingFromBody = false;
        }

        updateKardexGridState();
    }, { passive: true });

    proxy.addEventListener('scroll', function () {
        if (!syncingFromBody) {
            syncingFromProxy = true;
            setKardexScrollRatio(scrollBody, getKardexScrollRatio(proxy));
            syncingFromProxy = false;
        }

        updateKardexGridState();
    }, { passive: true });

    navigation.querySelectorAll('.kardex-grid-zone').forEach(function (button) {
        button.addEventListener('click', function () {
            scrollKardexToColumn(Number(button.dataset.kardexColumn));
        });
    });

    $(api.table().node()).on('draw.dt.kardexGrid column-sizing.dt.kardexGrid', function () {
        window.requestAnimationFrame(refreshKardexGridNavigation);
    });

    window.addEventListener('resize', function () {
        window.cancelAnimationFrame(resizeFrame);
        resizeFrame = window.requestAnimationFrame(refreshKardexGridNavigation);
    }, { passive: true });

    if (window.ResizeObserver) {
        kardexGridNavigation.resizeObserver = new ResizeObserver(function () {
            window.cancelAnimationFrame(resizeFrame);
            resizeFrame = window.requestAnimationFrame(refreshKardexGridNavigation);
        });
        kardexGridNavigation.resizeObserver.observe(scrollBody);
        kardexGridNavigation.resizeObserver.observe(scrollBody.querySelector('table'));
    }

    refreshKardexGridNavigation();
}

function refreshKardexGridNavigation() {
    if (!kardexGridNavigation) {
        return;
    }

    const { navigation, scrollBody, proxy, proxyTrack } = kardexGridNavigation;
    const hasOverflow = scrollBody.scrollWidth > scrollBody.clientWidth + 1;

    navigation.classList.toggle('is-visible', hasOverflow);
    proxyTrack.style.width = `${scrollBody.scrollWidth}px`;
    setKardexScrollRatio(proxy, getKardexScrollRatio(scrollBody));

    applyKardexStickyColumns();
    updateKardexGridState();
}

function getKardexStickyColumnIndexes() {
    const availableWidth = kardexGridNavigation?.scrollBody.clientWidth || window.innerWidth;

    if (availableWidth >= 980) {
        return [0, 1, 2, 3, 4];
    }

    if (availableWidth >= 680) {
        return [2, 3, 4];
    }

    if (availableWidth >= 480) {
        return [2, 4];
    }

    return [4];
}

function applyKardexStickyColumns() {
    const { wrapper, scrollBody } = kardexGridNavigation;
    const stickyIndexes = getKardexStickyColumnIndexes();
    const headCells = getKardexLogicalHeaderCells(wrapper);
    const bodyRows = scrollBody.querySelectorAll('tbody tr');
    let left = 0;

    wrapper.querySelectorAll('.kardex-sticky-left, .kardex-sticky-right, .kardex-sticky-left-edge').forEach(function (cell) {
        cell.classList.remove('kardex-sticky-left', 'kardex-sticky-right', 'kardex-sticky-left-edge', 'kardex-shadow-visible');
        cell.style.removeProperty('left');
    });

    stickyIndexes.forEach(function (columnIndex, position) {
        const cells = [];
        const headCell = headCells.get(columnIndex);

        if (headCell) {
            cells.push(headCell);
        }

        bodyRows.forEach(function (row) {
            if (row.children[columnIndex]) {
                cells.push(row.children[columnIndex]);
            }
        });

        cells.forEach(function (cell) {
            cell.classList.add('kardex-sticky-left');
            cell.style.left = `${left}px`;
            cell.classList.toggle('kardex-sticky-left-edge', position === stickyIndexes.length - 1);
        });

        left += getKardexColumnWidth(columnIndex);
    });

    const actionsHeader = headCells.get(21);
    if (actionsHeader) {
        actionsHeader.classList.add('kardex-sticky-right');
    }
    bodyRows.forEach(function (row) {
        row.children[21]?.classList.add('kardex-sticky-right');
    });

    kardexGridNavigation.stickyWidth = left;
}

function getKardexLogicalHeaderCells(wrapper) {
    const cells = new Map();
    let logicalIndex = 0;

    wrapper.querySelectorAll('.dataTables_scrollHead thead tr:first-child th').forEach(function (cell) {
        const span = Number(cell.getAttribute('colspan')) || 1;
        if (span === 1) {
            cells.set(logicalIndex, cell);
        }
        logicalIndex += span;
    });

    return cells;
}

function getKardexColumnWidth(columnIndex) {
    const { scrollBody } = kardexGridNavigation;
    const dataCell = scrollBody.querySelector(`tbody tr td:nth-child(${columnIndex + 1})`);
    const column = scrollBody.querySelector(`colgroup col:nth-child(${columnIndex + 1})`);

    return dataCell?.getBoundingClientRect().width || column?.getBoundingClientRect().width || 0;
}

function getKardexColumnLeft(columnIndex) {
    let left = 0;

    for (let index = 0; index < columnIndex; index += 1) {
        left += getKardexColumnWidth(index);
    }

    return left;
}

function getKardexScrollRatio(element) {
    const maxScroll = Math.max(0, element.scrollWidth - element.clientWidth);

    return maxScroll ? element.scrollLeft / maxScroll : 0;
}

function setKardexScrollRatio(element, ratio) {
    const maxScroll = Math.max(0, element.scrollWidth - element.clientWidth);
    element.scrollLeft = maxScroll * ratio;
}

function scrollKardexToColumn(columnIndex) {
    if (!kardexGridNavigation) {
        return;
    }

    const { scrollBody } = kardexGridNavigation;
    const maxScroll = Math.max(0, scrollBody.scrollWidth - scrollBody.clientWidth);
    const target = columnIndex === 0
        ? 0
        : getKardexColumnLeft(columnIndex) - (kardexGridNavigation.stickyWidth || 0);

    scrollBody.scrollTo({ left: Math.min(maxScroll, Math.max(0, target)), behavior: 'smooth' });
}

function updateKardexGridState() {
    if (!kardexGridNavigation) {
        return;
    }

    const { navigation, scrollBody, scrollFrame } = kardexGridNavigation;
    const maxScroll = Math.max(0, scrollBody.scrollWidth - scrollBody.clientWidth);
    const hasLeftOverflow = scrollBody.scrollLeft > 1;
    const hasRightOverflow = scrollBody.scrollLeft < maxScroll - 1;
    const visibleStart = scrollBody.scrollLeft + (kardexGridNavigation.stickyWidth || 0) + 12;
    let activeColumn = 0;

    navigation.querySelectorAll('.kardex-grid-zone').forEach(function (button) {
        const columnIndex = Number(button.dataset.kardexColumn);
        if (getKardexColumnLeft(columnIndex) <= visibleStart) {
            activeColumn = columnIndex;
        }
    });

    navigation.querySelectorAll('.kardex-grid-zone').forEach(function (button) {
        const active = Number(button.dataset.kardexColumn) === activeColumn;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-pressed', String(active));
    });

    scrollFrame.classList.toggle('kardex-has-left-overflow', hasLeftOverflow);
    scrollFrame.classList.toggle('kardex-has-right-overflow', hasRightOverflow);
    kardexGridNavigation.proxy.setAttribute('aria-valuemin', '0');
    kardexGridNavigation.proxy.setAttribute('aria-valuemax', String(Math.round(maxScroll)));
    kardexGridNavigation.proxy.setAttribute('aria-valuenow', String(Math.round(scrollBody.scrollLeft)));
    scrollFrame.querySelectorAll('.kardex-sticky-left-edge').forEach(function (cell) {
        cell.classList.toggle('kardex-shadow-visible', hasLeftOverflow);
    });
    scrollFrame.querySelectorAll('.kardex-sticky-right').forEach(function (cell) {
        cell.classList.toggle('kardex-shadow-visible', hasRightOverflow);
    });
}

function initFormat12Modal() {
    const trigger = document.getElementById('btnOpenFormat12Modal');
    const modal = document.getElementById('format12Modal');
    const content = modal?.querySelector('[data-format12-modal-content]');

    if (!trigger || !modal || !content) {
        return;
    }

    format12ModalState = {
        baseUrl: trigger.dataset.url,
        content,
        loaded: false,
        requestController: null,
        articleController: null,
    };

    trigger.addEventListener('click', function () {
        $(modal).modal('show');

        if (!format12ModalState.loaded) {
            loadFormat12Modal(format12ModalState.baseUrl, 'Preparando el registro...');
        }
    });

    $(modal)
        .off('hidden.bs.modal.format12')
        .on('hidden.bs.modal.format12', function () {
            format12ModalState.requestController?.abort();
            format12ModalState.articleController?.abort();
            if ($.fn.select2) {
                $(modal).find('select.select2-hidden-accessible').select2('close');
            }
            document.body.classList.remove('format12-modal-printing');
        });
}

async function loadFormat12Modal(url, loadingMessage = 'Consultando registro...') {
    if (!format12ModalState) {
        return;
    }

    format12ModalState.requestController?.abort();
    format12ModalState.articleController?.abort();

    const controller = new AbortController();
    const requestUrl = new URL(url, window.location.origin);
    requestUrl.searchParams.set('modal', '1');
    format12ModalState.requestController = controller;
    format12ModalState.loaded = false;

    if ($.fn.select2) {
        $(format12ModalState.content).find('select.select2-hidden-accessible').select2('destroy');
    }

    format12ModalState.content.innerHTML = format12LoadingMarkup(loadingMessage);

    try {
        const response = await fetch(requestUrl.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: controller.signal,
        });

        if (!response.ok) {
            let message = 'No se pudo cargar el Formato 12.1.';
            try {
                const payload = await response.json();
                message = Object.values(payload.errors || {})[0]?.[0] || payload.message || message;
            } catch (error) {
                // La respuesta no contiene JSON de validación.
            }
            throw new Error(message);
        }

        format12ModalState.content.innerHTML = await response.text();
        format12ModalState.loaded = true;
        initFormat12ModalContent();
        $('#format12Modal').modal('handleUpdate');
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        format12ModalState.content.innerHTML = `
            <div class="format12-empty-state">
                <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                <h6>No se pudo abrir el registro</h6>
                <p>${escapeKardexHtml(error.message)}</p>
                <button type="button" class="btn btn-outline-info btn-sm mt-3" data-format12-retry>Reintentar</button>
            </div>
        `;
        format12ModalState.content.querySelector('[data-format12-retry]')?.addEventListener('click', function () {
            loadFormat12Modal(format12ModalState.baseUrl, 'Preparando el registro...');
        });
    } finally {
        if (format12ModalState.requestController === controller) {
            format12ModalState.requestController = null;
        }
    }
}

function initFormat12ModalContent() {
    const root = format12ModalState?.content.querySelector('[data-format12-root]');
    const form = root?.querySelector('[data-format12-form]');

    if (!root || !form) {
        return;
    }

    const validation = root.querySelector('[data-format12-validation]');
    const controls = {
        company: form.querySelector('#company_id'),
        year: form.querySelector('#year'),
        month: form.querySelector('#month'),
        warehouse: form.querySelector('#warehouse_id'),
        article: form.querySelector('#article_id'),
    };

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!validateFormat12Filters(controls, validation)) {
            return;
        }

        const params = new URLSearchParams(new FormData(form));
        params.set('consult', '1');
        params.set('modal', '1');
        loadFormat12Modal(`${format12ModalState.baseUrl}?${params.toString()}`);
    });

    root.querySelector('[data-format12-clear]')?.addEventListener('click', function () {
        loadFormat12Modal(format12ModalState.baseUrl, 'Restableciendo filtros...');
    });

    root.querySelector('[data-format12-print]')?.addEventListener('click', function () {
        document.body.classList.add('format12-modal-printing');
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('format12-modal-printing');
        }, { once: true });
        window.print();
    });

    const articleDependencies = [controls.company, controls.year, controls.month, controls.warehouse].filter(Boolean);
    $(articleDependencies)
        .off('change.format12ModalArticles')
        .on('change.format12ModalArticles', function () {
            loadFormat12Articles(root, controls);
        });

    if ($.fn.select2) {
        $(root).find('select').each(function () {
            const $select = $(this);
            if (!$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    width: '100%',
                    dropdownParent: $('#format12Modal'),
                });
            }
        });
    }
}

function validateFormat12Filters(controls, validation) {
    const missing = [];

    if (!controls.company?.value) missing.push('empresa');
    if (!controls.year?.value) missing.push('año');
    if (!controls.month?.value) missing.push('mes');
    if (!controls.warehouse?.value) missing.push('almacén');

    if (!missing.length) {
        validation?.classList.add('d-none');
        return true;
    }

    if (validation) {
        validation.textContent = `Completa los filtros obligatorios: ${missing.join(', ')}.`;
        validation.classList.remove('d-none');
    }

    return false;
}

async function loadFormat12Articles(root, controls) {
    const { company, year, month, warehouse, article } = controls;

    if (!article) {
        return;
    }

    const resetArticles = function (message = null) {
        article.innerHTML = '';
        article.add(new Option('Todos', '', false, true));

        if (message) {
            const option = new Option(message, '', false, false);
            option.disabled = true;
            article.add(option);
        }

        $(article).trigger('change.select2');
    };

    if (!company?.value || !warehouse?.value || !year?.value || !month?.value) {
        resetArticles('Seleccione empresa y almacén');
        article.disabled = false;
        return;
    }

    format12ModalState.articleController?.abort();
    const controller = new AbortController();
    format12ModalState.articleController = controller;
    resetArticles('Cargando artículos...');
    article.disabled = true;

    const params = new URLSearchParams({
        company_id: company.value,
        warehouse_id: warehouse.value,
        year: year.value,
        month: month.value,
    });

    try {
        const response = await fetch(`${root.dataset.articlesUrl}?${params.toString()}`, {
            headers: { 'Accept': 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();
        const articles = Array.isArray(payload.articles) ? payload.articles : [];
        resetArticles(articles.length ? null : 'Sin artículos con movimientos o saldo inicial en el período');
        articles.forEach(function (item) {
            article.add(new Option(item.label, String(item.id)));
        });
        $(article).trigger('change.select2');
    } catch (error) {
        if (error.name !== 'AbortError') {
            resetArticles('No se pudieron cargar los artículos');
        }
    } finally {
        if (format12ModalState.articleController === controller) {
            format12ModalState.articleController = null;
            article.disabled = false;
        }
    }
}

function format12LoadingMarkup(message) {
    return `
        <div class="format12-modal-loading">
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>${escapeKardexHtml(message)}</span>
        </div>
    `;
}

function loadKardexMovementDetail(id) {
    $.get(`${window.routes.kardexShow}/${id}`)
        .done(function (response) {
            renderKardexMovementDetail(response);
            $('#kardexViewModal').modal('show');
        })
        .fail(function () {
            Swal.fire('Error', 'No se pudo cargar el movimiento Kardex.', 'error');
        });
}

function renderKardexMovementDetail(response) {
    const movement = response.data;
    const symbol = movement.currency?.symbol || movement.currency?.code || '';

    $('#vk_movement_number').text(movement.movement_number || '-');
    $('#vk_status').html(kardexStatusBadge(movement.status));
    $('#vk_movement_type').html(kardexMovementBadge(movement.movement_type));
    $('#vk_warehouse').text(movement.warehouse?.name || '-');
    $('#vk_article').text([movement.article?.code, movement.article?.billing_name].filter(Boolean).join(' | ') || '-');
    $('#vk_balance_quantity').text(formatKardexNumber(movement.balance_quantity));
    $('#vk_movement_date').text(formatKardexDisplayDateTime(movement.movement_date));
    $('#vk_operation_type').text(formatKardexOperation(movement.operation_type));
    $('#vk_document').text([movement.document_type, movement.document_series, movement.document_number].filter(Boolean).join(' ') || '-');
    $('#vk_related_party').text(movement.related_party_name || '-');
    $('#vk_lot_number').text(movement.lot_number || '-');
    $('#vk_expiration_date').text(formatKardexDisplayDate(movement.expiration_date));
    $('#vk_unit').text(movement.unit?.description || '-');
    $('#vk_presentation').text(movement.presentation?.description || '-');
    $('#vk_brand').text(movement.brand?.description || '-');
    $('#vk_origin').text(movement.origin || '-');
    $('#vk_cost_type').text(movement.cost_type || '-');
    $('#vk_quantity_in').text(formatKardexNumber(movement.quantity_in));
    $('#vk_quantity_out').text(formatKardexNumber(movement.quantity_out));
    $('#vk_unit_cost').text(formatKardexMoney(movement.unit_cost, symbol));
    $('#vk_average_unit_cost').text(formatKardexMoney(movement.average_unit_cost, symbol));
    $('#vk_total_cost_in').text(formatKardexMoney(movement.total_cost_in, symbol));
    $('#vk_total_cost_out').text(formatKardexMoney(movement.total_cost_out, symbol));
    $('#vk_balance_total_cost').text(formatKardexMoney(movement.balance_total_cost, symbol));
    $('#vk_observations').text(movement.observations || '-');
    $('#vk_source_type').text(response.source_label || '-');
    $('#vk_source_id').text(movement.source_id || '-');
    $('#vk_source_item_type').text(response.source_item_label || '-');
    $('#vk_source_item_id').text(movement.source_item_id || '-');
    $('#vk_source_url')
        .toggleClass('d-none', !response.source_url)
        .attr('href', response.source_url || '#');
}

function kardexMovementBadge(type) {
    const map = {
        entry: ['Entrada', 'kardex-badge-entry', 'fa-sign-in-alt'],
        exit: ['Salida', 'kardex-badge-exit', 'fa-sign-out-alt'],
        adjustment_in: ['Ajuste Entrada', 'kardex-badge-adjustment-in', 'fa-plus-circle'],
        adjustment_out: ['Ajuste Salida', 'kardex-badge-adjustment-out', 'fa-minus-circle'],
        transfer_in: ['Transferencia Entrada', 'kardex-badge-transfer-in', 'fa-exchange-alt'],
        transfer_out: ['Transferencia Salida', 'kardex-badge-transfer-out', 'fa-exchange-alt'],
        reversal: ['Reversa', 'kardex-badge-reversal', 'fa-undo-alt'],
        exit_reversal: ['Reversa de salida', 'kardex-badge-reversal', 'fa-undo-alt'],
        linked_cost: ['Costo vinculado', 'kardex-badge-transfer-in', 'fa-coins'],
        cost_reversal: ['Reversa de costo', 'kardex-badge-reversal', 'fa-undo-alt']
    };
    const item = map[type] || [type || '-', 'kardex-badge-reversal', 'fa-circle'];

    return `<span class="kardex-badge ${item[1]}"><i class="fas ${item[2]}"></i>${escapeKardexHtml(item[0])}</span>`;
}

function kardexStatusBadge(status) {
    const map = {
        registered: ['Registrado', 'kardex-badge-registered', 'fa-check-circle'],
        cancelled: ['Anulado', 'kardex-badge-cancelled', 'fa-ban'],
        reversed: ['Revertido', 'kardex-badge-reversed', 'fa-history']
    };
    const item = map[status] || [status || '-', 'kardex-badge-reversed', 'fa-circle'];

    return `<span class="kardex-badge ${item[1]}"><i class="fas ${item[2]}"></i>${escapeKardexHtml(item[0])}</span>`;
}

function renderKardexMovementNumber(data, type) {
    if (type !== 'display') {
        return data;
    }

    const value = escapeKardexHtml(data || '-');

    return `<span class="kardex-movement-pill">${value}</span>`;
}

function renderKardexArticleCell(data, type) {
    if (type !== 'display') {
        return data;
    }

    const value = String(data || '-');
    const parts = value.split('|');
    const code = escapeKardexHtml((parts[0] || '').trim());
    const name = escapeKardexHtml((parts.slice(1).join('|') || parts[0] || '-').trim());

    if (!code || code === name) {
        return `<div class="kardex-article-cell"><span class="kardex-article-name" title="${name}">${name}</span></div>`;
    }

    return `
        <div class="kardex-article-cell">
            <span class="kardex-article-code">${code}</span>
            <span class="kardex-article-name" title="${name}">${name}</span>
        </div>
    `;
}

function renderKardexDocumentPill(data, type) {
    if (type !== 'display') {
        return data;
    }

    const value = escapeKardexHtml(data || '-');

    return `<span class="kardex-document-pill" title="${value}"><i class="fas fa-file-invoice mr-1"></i><span class="kardex-document-text">${value}</span></span>`;
}

function renderKardexClampedText(data, type) {
    if (type !== 'display') {
        return data;
    }

    const value = escapeKardexHtml(data || '-');

    return `<span class="kardex-text-clamp" title="${value}">${value}</span>`;
}

function renderKardexEllipsisText(data, type) {
    if (type !== 'display') {
        return data;
    }

    const value = escapeKardexHtml(data || '-');

    return `<span class="kardex-text-ellipsis" title="${value}">${value}</span>`;
}

function renderKardexEntryNumber(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="kardex-num-in">${escapeKardexHtml(data || '0.00')}</span>`;
}

function renderKardexExitNumber(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="kardex-num-out">${escapeKardexHtml(data || '0.00')}</span>`;
}

function renderKardexBalanceNumber(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="kardex-num-balance">${escapeKardexHtml(data || '0.00')}</span>`;
}

function renderKardexMoneyCell(data, type) {
    if (type !== 'display') {
        return data;
    }

    return `<span class="kardex-money">${escapeKardexHtml(data || '0.00')}</span>`;
}

function formatKardexOperation(value) {
    const map = {
        warehouse_entry: 'Ingreso de Almacen',
        warehouse_entry_cancel: 'Anulacion de Ingreso',
        manual_adjustment: 'Ajuste Manual',
        sale_exit: 'Salida por Venta',
        transfer: 'Transferencia',
        warehouse_entry_linked_cost: 'Costo vinculado al ingreso',
        warehouse_entry_linked_cost_cancel: 'Reversa de costo vinculado',
        customer_order_dispatch: 'Despacho de OC Cliente',
        customer_order_dispatch_cancel: 'Reversa de despacho de OC Cliente',
        customer_return: 'Devolución cliente',
        customer_return_reversal: 'Anulación de devolución de cliente'
    };

    return map[value] || value || '-';
}

function formatKardexNumber(value) {
    return (parseFloat(value) || 0).toFixed(2);
}

function formatKardexMoney(value, symbol = '') {
    return `${symbol ? `${symbol} ` : ''}${formatKardexNumber(value)}`.trim();
}

function formatKardexDisplayDate(value) {
    if (!value) {
        return '-';
    }

    const date = String(value).substring(0, 10);
    const parts = date.split('-');

    return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : date;
}

function formatKardexDisplayDateTime(value) {
    if (!value) {
        return '-';
    }

    const raw = String(value);
    const date = formatKardexDisplayDate(raw.substring(0, 10));
    const time = raw.substring(11, 16);

    return time ? `${date} ${time}` : date;
}

function escapeKardexHtml(value) {
    return $('<div>').text(value ?? '').html();
}

function kardexFilterData() {
    return {
        warehouse_id: $('#kardex_filter_warehouse_id').val() || '',
        article_id: $('#kardex_filter_article_id').val() || '',
        date_from: $('#kardex_filter_date_from').val() || '',
        date_to: $('#kardex_filter_date_to').val() || '',
        movement_type: $('#kardex_filter_movement_type').val() || '',
        lot_number: $('#kardex_filter_lot_number').val() || '',
        document: $('#kardex_filter_document').val() || '',
        related_party: $('#kardex_filter_related_party').val() || ''
    };
}

function kardexFilterQuery() {
    return $.param(kardexFilterData());
}

function loadKardexStockAtDate() {
    const date = $('#kardex_stock_date').val();
    if (!date) {
        Swal.fire('Validación', 'Seleccione la fecha de consulta.', 'warning');
        return;
    }

    $.get(window.routes.kardexStockAtDate, {
        date,
        warehouse_id: $('#kardex_filter_warehouse_id').val(),
        article_id: $('#kardex_filter_article_id').val()
    }).done(function (response) {
        const rows = response.items.map(item => `
            <tr>
                <td>${escapeKardexHtml(item.warehouse || '-')}</td>
                <td>${escapeKardexHtml(item.article || '-')}</td>
                <td>${escapeKardexHtml(item.lot_number || '-')}</td>
                <td class="text-right">${formatKardexNumber(item.quantity)}</td>
                <td class="text-right">${formatKardexMoney(item.average_cost, 'S/')}</td>
                <td class="text-right">${formatKardexMoney(item.total_value, 'S/')}</td>
            </tr>`).join('');
        Swal.fire({
            title: `Stock al ${formatKardexDisplayDate(date)}`,
            width: 1000,
            html: `<div class="text-left mb-2"><strong>Cantidad:</strong> ${formatKardexNumber(response.total_quantity)} · <strong>Valor:</strong> ${formatKardexMoney(response.total_value, 'S/')}</div>
                <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Almacén</th><th>Artículo</th><th>Lote</th><th>Cantidad</th><th>C. promedio</th><th>Valor</th></tr></thead><tbody>${rows || '<tr><td colspan="6">Sin stock a esa fecha.</td></tr>'}</tbody></table></div>`,
            confirmButtonText: 'Cerrar'
        });
    }).fail(function () {
        Swal.fire('Error', 'No se pudo consultar el stock histórico.', 'error');
    });
}

function recalculateKardex() {
    Swal.fire({
        title: '¿Recalcular Kardex?',
        text: 'Se reconstruirán costos promedio y saldos de los artículos alcanzados por los filtros.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, recalcular',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.isConfirmed) return;
        const data = kardexFilterData();
        data._token = $('meta[name="csrf-token"]').attr('content');
        $.post(window.routes.kardexRecalculate, data)
            .done(function (response) {
                Swal.fire('Completado', response.message, 'success');
                tableKardex.ajax.reload(null, false);
            })
            .fail(function (xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo recalcular el Kardex.', 'error');
            });
    });
}
