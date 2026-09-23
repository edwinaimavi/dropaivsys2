document.addEventListener('DOMContentLoaded', () => {
    const config = window.sunatCatalogConfig;
    if (!config) return;

    const state = { catalogs: [], selected: null, table: null, pendingStatus: null };
    let searchTimer = null;

    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });

    const route = (template, catalogId, itemId = null) => template
        .replace('__CATALOG__', catalogId)
        .replace('__ITEM__', itemId ?? '__ITEM__');

    const escapeHtml = (value) => $('<div>').text(value ?? '').html();

    function notify(icon, message) {
        if (window.Swal) {
            Swal.fire({ icon, text: message, confirmButtonText: 'Aceptar' });
            return;
        }
        console[icon === 'error' ? 'error' : 'log'](message);
    }

    function clearValidation(form) {
        $(form).find('.is-invalid').removeClass('is-invalid');
        $(form).find('.invalid-feedback').text('');
    }

    function showValidation(form, xhr) {
        clearValidation(form);
        const errors = xhr.responseJSON?.errors || {};
        Object.entries(errors).forEach(([field, messages]) => {
            const input = $(form).find(`[name="${field}"]`);
            input.addClass('is-invalid');
            input.siblings('.invalid-feedback').text(messages[0]);
        });
        notify('error', xhr.responseJSON?.message || 'Revisa los datos ingresados.');
    }

    function setBusy(button, busy) {
        const $button = $(button);
        if (busy) {
            $button.data('original-html', $button.html()).prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm mr-1"></span> Guardando...');
        } else {
            $button.prop('disabled', false).html($button.data('original-html'));
        }
    }

    function loadCatalogs(search = '', preferredId = null) {
        $('#catalogList').html('<div class="text-center text-muted py-5"><i class="fas fa-spinner fa-spin mr-1"></i> Cargando...</div>');
        $.get(config.routes.catalogs, { search })
            .done(({ data }) => {
                state.catalogs = data;
                renderCatalogs();
                const selectedId = preferredId || state.selected?.id;
                const next = data.find((catalog) => catalog.id === selectedId);
                if (next) selectCatalog(next);
                else if (state.selected && !search) clearSelection();
            })
            .fail(() => notify('error', 'No se pudieron cargar los catálogos SUNAT.'));
    }

    function renderCatalogs() {
        if (!state.catalogs.length) {
            $('#catalogList').html('<div class="text-center text-muted py-5"><i class="fas fa-inbox fa-2x mb-2 d-block"></i>No hay catálogos para mostrar.</div>');
            return;
        }

        $('#catalogList').html(state.catalogs.map((catalog) => `
            <button type="button" class="sunat-catalog-card ${state.selected?.id === catalog.id ? 'active' : ''}" data-catalog-id="${catalog.id}">
                <div class="d-flex align-items-start">
                    <span class="sunat-catalog-code mr-2">${escapeHtml(catalog.code)}</span>
                    <span class="flex-grow-1 min-width-0">
                        <span class="d-block font-weight-bold">${escapeHtml(catalog.name)}</span>
                        <span class="sunat-catalog-meta">${catalog.active_items_count} activos de ${catalog.items_count} códigos</span>
                    </span>
                    <i class="fas fa-chevron-right mt-2 ml-2"></i>
                </div>
            </button>
        `).join(''));
    }

    function selectCatalog(catalog) {
        state.selected = catalog;
        renderCatalogs();
        $('#emptyCatalogState').addClass('d-none');
        $('#catalogDetail').removeClass('d-none');
        $('#selectedCatalogCode').text(catalog.code);
        $('#selectedCatalogName').text(catalog.name);
        $('#selectedCatalogDescription').text(catalog.description || 'Sin descripción adicional.');
        $('#selectedCatalogStatus')
            .toggleClass('badge-success', catalog.is_active)
            .toggleClass('badge-secondary', !catalog.is_active)
            .text(catalog.is_active ? 'Activo' : 'Inactivo');
        $('#btnCatalogStatus').attr('title', catalog.is_active ? 'Inactivar catálogo' : 'Activar catálogo');
        loadItemsTable();
    }

    function clearSelection() {
        state.selected = null;
        state.table?.destroy();
        state.table = null;
        $('#tableSunatItems tbody').empty();
        $('#catalogDetail').addClass('d-none');
        $('#emptyCatalogState').removeClass('d-none');
    }

    function loadItemsTable() {
        if (state.table) state.table.destroy();
        const canAct = config.permissions.edit || config.permissions.status;
        state.table = $('#tableSunatItems').DataTable({
            processing: true,
            serverSide: true,
            ajax: route(config.routes.items, state.selected.id),
            columns: [
                { data: 'item_code', name: 'item_code', render: (value) => `<span class="badge badge-light border px-2 py-1">${escapeHtml(value)}</span>` },
                { data: 'description', name: 'description', className: 'sunat-item-description', render: (value) => escapeHtml(value) },
                { data: 'source_label', name: 'source', render: (value, type, row) => `<span class="badge ${row.is_official ? 'badge-primary' : 'badge-info'}">${escapeHtml(value)}</span>` },
                { data: 'is_active', name: 'status', render: (active) => `<span class="badge ${active ? 'badge-success' : 'badge-secondary'}">${active ? 'Activo' : 'Inactivo'}</span>` },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    visible: canAct,
                    render: (value, type, row) => `${config.permissions.edit ? `<button type="button" class="btn btn-outline-primary btn-sm btn-edit-item mr-1" data-id="${row.id}" title="Editar"><i class="fas fa-pen"></i></button>` : ''}${config.permissions.status ? `<button type="button" class="btn btn-outline-secondary btn-sm btn-status-item" data-id="${row.id}" title="${row.is_active ? 'Inactivar' : 'Activar'}"><i class="fas fa-power-off"></i></button>` : ''}`,
                },
            ],
            responsive: true,
            autoWidth: false,
            pageLength: 25,
            order: [[0, 'asc']],
            language: { url: '/vendor/datatables/js/i18n/es-ES.json' },
        });
    }

    function openCatalogModal(catalog = null) {
        clearValidation('#catalogForm');
        $('#catalogForm')[0].reset();
        $('#catalogId').val(catalog?.id || '');
        $('#catalogCode').val(catalog?.code || '');
        $('#catalogName').val(catalog?.name || '');
        $('#catalogDescription').val(catalog?.description || '');
        $('#catalogModalTitle').text(catalog ? 'Editar catálogo SUNAT' : 'Nuevo catálogo SUNAT');
        $('#catalogModal').modal('show');
    }

    function openItemModal(item = null) {
        clearValidation('#itemForm');
        $('#itemForm')[0].reset();
        $('#itemId').val(item?.id || '');
        $('#itemCode').val(item?.item_code || '');
        $('#itemDescription').val(item?.description || '');
        $('#itemShortName').val(item?.short_name || '');
        $('#itemExtraData').val(item?.extra_data ? JSON.stringify(item.extra_data, null, 2) : '');
        $('#itemModalTitle').text(item ? 'Editar código SUNAT' : 'Agregar código SUNAT');
        $('#itemCatalogLabel').text(`Tabla ${state.selected.code} — ${state.selected.name}`);
        $('#itemModal').modal('show');
    }

    function openStatusModal(type, entity) {
        const active = type === 'catalog' ? entity.is_active : entity.is_active;
        state.pendingStatus = { type, entity, next: !active };
        const noun = type === 'catalog' ? 'catálogo' : 'código';
        $('#statusModalTitle').text(`${active ? 'Inactivar' : 'Activar'} ${noun}`);
        $('#statusModalText').text(active
            ? `El ${noun} seguirá disponible para consultas históricas, pero no estará activo.`
            : `El ${noun} volverá a estar disponible para uso administrativo.`);
        $('#btnConfirmStatus').toggleClass('btn-danger', active).toggleClass('btn-primary', !active)
            .text(active ? 'Sí, inactivar' : 'Sí, activar');
        $('#statusModal').modal('show');
    }

    $('#catalogList').on('click', '[data-catalog-id]', function () {
        const id = Number($(this).data('catalog-id'));
        const catalog = state.catalogs.find((entry) => entry.id === id);
        if (catalog) selectCatalog(catalog);
    });

    $('#catalogSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => loadCatalogs($(this).val().trim()), 250);
    });

    $('#btnNewCatalog').on('click', () => openCatalogModal());
    $('#btnEditCatalog').on('click', () => openCatalogModal(state.selected));
    $('#btnNewItem').on('click', () => openItemModal());
    $('#btnCatalogStatus').on('click', () => openStatusModal('catalog', state.selected));

    $('#catalogForm').on('submit', function (event) {
        event.preventDefault();
        const id = $('#catalogId').val();
        const request = id
            ? $.ajax({ url: route(config.routes.catalogUpdate, id), method: 'PUT', data: $(this).serialize() })
            : $.post(config.routes.catalogStore, $(this).serialize());
        setBusy('#btnSaveCatalog', true);
        request.done(({ message, data }) => {
            $('#catalogModal').modal('hide');
            notify('success', message);
            loadCatalogs($('#catalogSearch').val().trim(), data.id);
        }).fail((xhr) => showValidation(this, xhr)).always(() => setBusy('#btnSaveCatalog', false));
    });

    $('#itemForm').on('submit', function (event) {
        event.preventDefault();
        clearValidation(this);
        let extraData = null;
        const rawExtraData = $('#itemExtraData').val().trim();
        if (rawExtraData) {
            try {
                extraData = JSON.parse(rawExtraData);
                if (!extraData || Array.isArray(extraData) || typeof extraData !== 'object') throw new Error();
            } catch {
                $('#itemExtraData').addClass('is-invalid');
                $('#itemExtraDataError').text('Ingresa un objeto JSON válido.');
                return;
            }
        }

        const id = $('#itemId').val();
        const data = {
            item_code: $('#itemCode').val(),
            description: $('#itemDescription').val(),
            short_name: $('#itemShortName').val(),
            extra_data: extraData,
        };
        const request = id
            ? $.ajax({ url: route(config.routes.itemUpdate, state.selected.id, id), method: 'PUT', data })
            : $.post(route(config.routes.itemStore, state.selected.id), data);
        setBusy('#btnSaveItem', true);
        request.done(({ message }) => {
            $('#itemModal').modal('hide');
            notify('success', message);
            state.table.ajax.reload(null, false);
            loadCatalogs($('#catalogSearch').val().trim(), state.selected.id);
        }).fail((xhr) => showValidation(this, xhr)).always(() => setBusy('#btnSaveItem', false));
    });

    $('#tableSunatItems').on('click', '.btn-edit-item', function () {
        openItemModal(state.table.row($(this).closest('tr')).data());
    }).on('click', '.btn-status-item', function () {
        openStatusModal('item', state.table.row($(this).closest('tr')).data());
    });

    $('#btnConfirmStatus').on('click', function () {
        if (!state.pendingStatus) return;
        const { type, entity, next } = state.pendingStatus;
        const url = type === 'catalog'
            ? route(config.routes.catalogStatus, entity.id)
            : route(config.routes.itemStatus, state.selected.id, entity.id);
        $(this).prop('disabled', true);
        $.ajax({ url, method: 'PATCH', data: { is_active: next ? 1 : 0 } })
            .done(({ message }) => {
                $('#statusModal').modal('hide');
                notify('success', message);
                if (type === 'catalog') loadCatalogs($('#catalogSearch').val().trim(), entity.id);
                else {
                    state.table.ajax.reload(null, false);
                    loadCatalogs($('#catalogSearch').val().trim(), state.selected.id);
                }
            })
            .fail((xhr) => notify('error', xhr.responseJSON?.message || 'No se pudo cambiar el estado.'))
            .always(() => $(this).prop('disabled', false));
    });

    loadCatalogs();
});
