let tableLabelings;
let currentLabelingOrder = null;

document.addEventListener('DOMContentLoaded', function () {
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    initLabelingsTable();

    $(document).on('click', '#btnCreateLabeling', function () {
        resetLabelingForm();
        $('#labelingModalLabel').text('Registrar Rotulación');
        loadAvailableLabelingOrders();
        $('#labelingModal').modal('show');
    });

    $(document).on('change', '#labeling_customer_purchase_order_id', function () {
        const orderId = $(this).val();

        if (orderId) {
            loadLabelingCustomerOrder(orderId);
            return;
        }

        currentLabelingOrder = null;
        resetLabelingSideSummary();
        renderLabelingOrderItems([]);
        renderLabelingBoxes();
    });

    $(document).on('input change', '#labeling_boxes_count', function () {
        renderLabelingBoxes(getCurrentBoxDistribution());
    });

    $(document).on('input', '.labeling-item-distribute', updateLabelingAvailableSummary);

    $(document).on('click', '.btnAddLabelingBoxItem', function () {
        addLabelingBoxItemRow($(this).closest('.labeling-box-card').data('box-number'));
    });

    $(document).on('click', '.btnRemoveLabelingBoxItem', function () {
        $(this).closest('.labeling-box-item-row').remove();
        updateLabelingAvailableSummary();
    });

    $(document).on('change input', '.labeling-box-item-select, .labeling-box-item-quantity', updateLabelingAvailableSummary);
    $(document).on('click', '#btnAutoDistributeLabeling', autoDistributeLabeling);

    $(document).on('submit', '#labelingForm', function (event) {
        event.preventDefault();
        saveLabeling();
    });

    $(document).on('click', '.viewLabeling', function () {
        viewLabeling($(this).data('id'));
    });

    $(document).on('click', '.editLabeling', function () {
        editLabeling($(this).data('id'));
    });

    $(document).on('click', '.deleteLabeling', function () {
        deleteLabeling($(this).data('id'));
    });
});

function initLabelingsTable() {
    tableLabelings = $('#tableLabelings').DataTable({
        processing: true,
        serverSide: true,
        ajax: window.routes.labelingsList,
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'code', name: 'code' },
            { data: 'order', name: 'customerPurchaseOrder.code', orderable: false },
            { data: 'customer', name: 'customer.business_name', orderable: false },
            { data: 'invoice_number', name: 'invoice_number', defaultContent: '-' },
            { data: 'guide_number', name: 'guide_number', defaultContent: '-' },
            { data: 'boxes_count', name: 'boxes_count', className: 'text-center' },
            { data: 'status', name: 'status', className: 'text-center' },
            { data: 'created_at', name: 'created_at' },
            { data: 'acciones', orderable: false, searchable: false, className: 'text-center' }
        ],
        responsive: true,
        autoWidth: false,
        order: [[1, 'desc']],
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        },
        dom: `
            <'row mb-3'
                <'col-sm-12 col-md-6'l>
                <'col-sm-12 col-md-6 text-md-end'f>
            >
            <'row'<'col-sm-12'tr>>
            <'row mt-3'
                <'col-sm-12 col-md-5'i>
                <'col-sm-12 col-md-7 d-flex justify-content-center justify-content-md-end'p>
            >
            <'row mt-3'<'col-sm-12 text-center'B>>
        `,
        buttons: [
            { extend: 'excel', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm' },
            { extend: 'pdf', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-danger btn-sm' },
            { extend: 'print', text: '<i class="fas fa-print"></i> Imprimir', className: 'btn btn-secondary btn-sm' }
        ],
        drawCallback: function () {
            $('[data-toggle="tooltip"]').tooltip();
        }
    });
}

function resetLabelingForm() {
    $('#labelingForm')[0].reset();
    $('#labeling_id').val('');
    $('#labeling_customer_purchase_order_id')
        .prop('disabled', false)
        .html('<option value="">Seleccione orden abastecida</option>');
    $('#labeling_customer_name, #labeling_company_name, #labeling_order_number').val('');
    $('#labeling_boxes_count').val(1);
    currentLabelingOrder = null;
    clearLabelingErrors();
    resetLabelingSideSummary();
    renderLabelingOrderItems([]);
    renderLabelingBoxes();
}

function resetLabelingSideSummary() {
    $('#labelingSideCustomer').text('Seleccione orden');
    $('#labelingSideCompany').text('-');
    $('#labelingSideBoxes').text($('#labeling_boxes_count').val() || '0');
}

function loadAvailableLabelingOrders(selectedId = '') {
    $.get(window.routes.labelingsCreateData)
        .done(function (response) {
            const select = $('#labeling_customer_purchase_order_id');
            select.html('<option value="">Seleccione orden abastecida</option>');

            (response.orders || []).forEach(function (order) {
                select.append(new Option(order.text, order.id, false, String(order.id) === String(selectedId)));
            });
        });
}

function loadLabelingCustomerOrder(orderId, callback = null) {
    const url = window.routes.labelingsCustomerOrder.replace(':id', orderId);

    $.get(url)
        .done(function (response) {
            currentLabelingOrder = response.data;
            fillLabelingOrderSummary(response.data);
            renderLabelingOrderItems(response.data.items || []);
            renderLabelingBoxes();

            if (typeof callback === 'function') {
                callback(response.data);
            }
        })
        .fail(function () {
            Swal.fire('Error', 'No se pudo cargar la orden seleccionada.', 'error');
        });
}

function fillLabelingOrderSummary(order) {
    $('#labeling_customer_name').val(order.customer_name || '');
    $('#labeling_company_name').val(order.company_name || '');
    $('#labeling_order_number').val(order.purchase_order_number || order.code || '');
    $('#labelingSideCustomer').text(order.customer_name || 'Seleccione orden');
    $('#labelingSideCompany').text(order.company_name || '-');
    $('#labelingSideBoxes').text($('#labeling_boxes_count').val() || '0');
}

function renderLabelingOrderItems(items) {
    const tbody = $('#labelingOrderItemsTable tbody');

    if (!items.length) {
        tbody.html('<tr><td colspan="11" class="text-center text-muted py-3">Seleccione una orden abastecida.</td></tr>');
        return;
    }

    tbody.html(items.map(function (item) {
        const available = parseFloat(item.available_quantity) || 0;

        return `
            <tr data-item-id="${item.id}">
                <td>${escapeLabelingHtml(item.article_code || '')}</td>
                <td>${escapeLabelingHtml(item.description || item.article_name || '')}</td>
                <td>${escapeLabelingHtml(item.unit_name || '')}</td>
                <td>${escapeLabelingHtml(item.brand_name || '')}</td>
                <td>${escapeLabelingHtml(item.presentation_name || '')}</td>
                <td>${escapeLabelingHtml(item.lot || '')}</td>
                <td>${escapeLabelingHtml(formatLabelingDate(item.expiration_date))}</td>
                <td class="text-right">${formatLabelingNumber(item.quantity)}</td>
                <td class="text-right">${formatLabelingNumber(item.labeled_quantity)}</td>
                <td class="text-right labeling-available-cell">${formatLabelingNumber(available)}</td>
                <td>
                    <input type="number" class="form-control form-control-sm text-right labeling-item-distribute"
                        data-item-id="${item.id}" min="0" step="0.01" max="${available}"
                        value="${available > 0 ? available : 0}">
                </td>
            </tr>
        `;
    }).join(''));
}

function renderLabelingBoxes(existingDistribution = {}) {
    const count = parseInt($('#labeling_boxes_count').val(), 10) || 0;
    const container = $('#labelingBoxesContainer');
    $('#labelingSideBoxes').text(count || '0');

    if (!count || count < 1) {
        container.html('<div class="col-12 text-center text-muted py-3">Ingrese la cantidad de cajas.</div>');
        return;
    }

    let html = '';
    for (let i = 1; i <= count; i++) {
        html += `
            <div class="col-lg-6 mb-2">
                <div class="card labeling-box-card" data-box-number="${i}">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <strong>Caja ${i}/${count}</strong>
                        <button type="button" class="btn btn-outline-primary btn-xs btnAddLabelingBoxItem" title="Agregar artículo">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <div class="labeling-box-items"></div>
                        <div class="form-group mb-0 mt-2">
                            <label class="small font-weight-bold text-muted mb-1">Observación de caja</label>
                            <textarea class="form-control form-control-sm labeling-box-observation" rows="2"
                                maxlength="1000" placeholder="Ingrese una observación para esta caja, si aplica"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    container.html(html);

    Object.entries(existingDistribution).forEach(function ([boxNumber, boxData]) {
        if (parseInt(boxNumber, 10) <= count) {
            const items = Array.isArray(boxData) ? boxData : (boxData.items || []);
            const observation = Array.isArray(boxData) ? '' : (boxData.observation || '');

            $(`.labeling-box-card[data-box-number="${boxNumber}"]`)
                .find('.labeling-box-observation')
                .val(observation);

            items.forEach(item => addLabelingBoxItemRow(boxNumber, item.itemId, item.quantity));
        }
    });

    updateLabelingAvailableSummary();
}

function addLabelingBoxItemRow(boxNumber, itemId = '', quantity = '') {
    const box = $(`.labeling-box-card[data-box-number="${boxNumber}"]`);
    const items = currentLabelingOrder?.items || [];
    const options = ['<option value="">Artículo</option>'].concat(items.map(function (item) {
        const selected = String(item.id) === String(itemId) ? 'selected' : '';
        return `<option value="${item.id}" ${selected}>${escapeLabelingHtml(item.article_name || item.description)}</option>`;
    })).join('');

    box.find('.labeling-box-items').append(`
        <div class="labeling-box-item-row">
            <select class="form-control form-control-sm labeling-box-item-select">${options}</select>
            <input type="number" class="form-control form-control-sm text-right labeling-box-item-quantity"
                min="0.01" step="0.01" value="${quantity}">
            <button type="button" class="btn btn-outline-danger btn-sm btnRemoveLabelingBoxItem" title="Quitar">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `);
}

function autoDistributeLabeling() {
    const count = parseInt($('#labeling_boxes_count').val(), 10) || 0;

    if (!currentLabelingOrder || count < 1) {
        Swal.fire('Atención', 'Seleccione una orden y registre la cantidad de cajas.', 'info');
        return;
    }

    const existingDistribution = getCurrentBoxDistribution();
    renderLabelingBoxes();

    $('.labeling-item-distribute').each(function () {
        const input = $(this);
        const itemId = input.data('item-id');
        const total = roundLabeling(parseFloat(input.val()) || 0);

        if (total <= 0) {
            return;
        }

        const cents = Math.round(total * 100);
        const baseCents = Math.floor(cents / count);
        const remainder = cents % count;

        for (let box = 1; box <= count; box++) {
            const quantity = (baseCents + (box <= remainder ? 1 : 0)) / 100;

            if (quantity > 0) {
                addLabelingBoxItemRow(box, itemId, quantity.toFixed(2));
            }
        }
    });

    Object.entries(existingDistribution).forEach(function ([boxNumber, boxData]) {
        $(`.labeling-box-card[data-box-number="${boxNumber}"]`)
            .find('.labeling-box-observation')
            .val(boxData.observation || '');
    });

    updateLabelingAvailableSummary();
}

function saveLabeling() {
    clearLabelingErrors();

    const validation = validateLabelingDistribution();
    if (!validation.valid) {
        Swal.fire('Validación', validation.message, 'warning');
        return;
    }

    const id = $('#labeling_id').val();
    const formData = buildLabelingFormData();
    let url = window.routes.labelingsStore;

    if (id) {
        url = window.routes.labelingsUpdate.replace(':id', id);
        formData.append('_method', 'PUT');
    }

    $('#btnSaveLabeling').prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...');

    $.ajax({
        url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
            $('#labelingModal').modal('hide');
            tableLabelings.ajax.reload(null, false);

            Swal.fire({
                icon: 'success',
                title: response.message || 'Rotulación guardada correctamente.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2500
            });
        },
        error: function (xhr) {
            handleLabelingError(xhr);
        },
        complete: function () {
            $('#btnSaveLabeling').prop('disabled', false).html('<i class="fas fa-save mr-1"></i> Guardar rotulación');
        }
    });
}

function buildLabelingFormData() {
    const formData = new FormData();
    formData.append('customer_purchase_order_id', $('#labeling_customer_purchase_order_id').val() || '');
    formData.append('invoice_number', $('#labeling_invoice_number').val() || '');
    formData.append('guide_number', $('#labeling_guide_number').val() || '');
    formData.append('destination', $('#labeling_destination').val() || '');
    formData.append('boxes_count', $('#labeling_boxes_count').val() || '');
    formData.append('observations', $('#labeling_observations').val() || '');

    getItemsToLabel().forEach(function (item, index) {
        formData.append(`items_to_label[${index}][customer_purchase_order_item_id]`, item.itemId);
        formData.append(`items_to_label[${index}][quantity]`, item.quantity);
    });

    $('.labeling-box-card').each(function (boxIndex) {
        const box = $(this);
        formData.append(`boxes[${boxIndex}][box_number]`, box.data('box-number'));
        formData.append(`boxes[${boxIndex}][observation]`, box.find('.labeling-box-observation').val() || '');

        box.find('.labeling-box-item-row').each(function (itemIndex) {
            const row = $(this);
            const itemId = row.find('.labeling-box-item-select').val();
            const quantity = parseFloat(row.find('.labeling-box-item-quantity').val()) || 0;

            if (itemId && quantity > 0) {
                formData.append(`boxes[${boxIndex}][items][${itemIndex}][customer_purchase_order_item_id]`, itemId);
                formData.append(`boxes[${boxIndex}][items][${itemIndex}][quantity]`, quantity);
            }
        });
    });

    return formData;
}

function validateLabelingDistribution() {
    if (!currentLabelingOrder) {
        return { valid: false, message: 'Seleccione una orden abastecida.' };
    }

    const count = parseInt($('#labeling_boxes_count').val(), 10) || 0;
    if (count < 1) {
        return { valid: false, message: 'La cantidad de cajas debe ser mayor a cero.' };
    }

    const selected = getItemsToLabel();
    if (!selected.length) {
        return { valid: false, message: 'Seleccione al menos un artículo con cantidad a rotular.' };
    }

    for (const item of selected) {
        const orderItem = findLabelingOrderItem(item.itemId);
        const available = parseFloat(orderItem?.available_quantity || 0);

        if (item.quantity > available) {
            return { valid: false, message: `La cantidad a rotular de ${orderItem?.article_name || 'un artículo'} supera lo disponible.` };
        }
    }

    const selectedTotals = Object.fromEntries(selected.map(item => [String(item.itemId), item.quantity]));
    const distributedTotals = {};

    for (let box = 1; box <= count; box++) {
        const card = $(`.labeling-box-card[data-box-number="${box}"]`);
        const rows = card.find('.labeling-box-item-row').filter(function () {
            const itemId = $(this).find('.labeling-box-item-select').val();
            const quantity = parseFloat($(this).find('.labeling-box-item-quantity').val()) || 0;
            return itemId && quantity > 0;
        });

        if (!rows.length) {
            return { valid: false, message: `La caja ${box}/${count} no tiene artículos.` };
        }

        let invalidRow = false;
        rows.each(function () {
            const itemId = String($(this).find('.labeling-box-item-select').val());
            const quantity = parseFloat($(this).find('.labeling-box-item-quantity').val()) || 0;

            if (!selectedTotals[itemId] || quantity <= 0) {
                invalidRow = true;
                return false;
            }

            distributedTotals[itemId] = roundLabeling((distributedTotals[itemId] || 0) + quantity);
        });

        if (invalidRow) {
            return { valid: false, message: `La caja ${box}/${count} tiene artículos sin cantidad válida o no seleccionados para rotular.` };
        }
    }

    for (const [itemId, selectedQuantity] of Object.entries(selectedTotals)) {
        const distributed = roundLabeling(distributedTotals[itemId] || 0);
        const orderItem = findLabelingOrderItem(itemId);

        if (distributed > selectedQuantity) {
            return {
                valid: false,
                message: `La cantidad distribuida del artículo ${orderItem?.article_name || itemId} supera la cantidad a rotular.`
            };
        }

        if (distributed !== selectedQuantity) {
            return {
                valid: false,
                message: `Hay artículos seleccionados que aún no fueron distribuidos completamente. Revise ${orderItem?.article_name || itemId}.`
            };
        }
    }

    return { valid: true };
}

function getItemsToLabel() {
    const items = [];

    $('.labeling-item-distribute').each(function () {
        const itemId = $(this).data('item-id');
        const quantity = roundLabeling(parseFloat($(this).val()) || 0);

        if (quantity > 0) {
            items.push({ itemId, quantity });
        }
    });

    return items;
}

function getCurrentBoxDistribution() {
    const distribution = {};

    $('.labeling-box-card').each(function () {
        const boxNumber = $(this).data('box-number');
        distribution[boxNumber] = {
            observation: $(this).find('.labeling-box-observation').val() || '',
            items: []
        };

        $(this).find('.labeling-box-item-row').each(function () {
            const itemId = $(this).find('.labeling-box-item-select').val();
            const quantity = $(this).find('.labeling-box-item-quantity').val();

            if (itemId || quantity) {
                distribution[boxNumber].items.push({ itemId, quantity });
            }
        });
    });

    return distribution;
}

function updateLabelingAvailableSummary() {
    const distributed = {};

    $('.labeling-box-item-row').each(function () {
        const itemId = $(this).find('.labeling-box-item-select').val();
        const quantity = parseFloat($(this).find('.labeling-box-item-quantity').val()) || 0;

        if (itemId && quantity > 0) {
            distributed[itemId] = roundLabeling((distributed[itemId] || 0) + quantity);
        }
    });

    $('.labeling-item-distribute').each(function () {
        const input = $(this);
        const itemId = String(input.data('item-id'));
        const toLabel = roundLabeling(parseFloat(input.val()) || 0);
        const assigned = roundLabeling(distributed[itemId] || 0);
        const row = input.closest('tr');
        const available = parseFloat(input.attr('max')) || 0;

        row.toggleClass('table-warning', assigned < toLabel);
        row.toggleClass('table-danger', assigned > toLabel || toLabel > available);
        row.find('.labeling-distributed-cell').remove();
        row.append(`<td class="d-none labeling-distributed-cell">${assigned}</td>`);
    });
}

function viewLabeling(id) {
    $.get(window.routes.labelingsShow.replace(':id', id))
        .done(function (response) {
            const data = response.data;
            $('#view_labeling_code').text(data.code);
            $('#viewLabelingBody').html(renderLabelingDetail(data));
            $('#viewLabelingModal').modal('show');
        });
}

function editLabeling(id) {
    resetLabelingForm();
    $('#labelingModalLabel').text('Editar Rotulación');

    $.get(window.routes.labelingsEdit.replace(':id', id))
        .done(function (response) {
            const data = response.data;
            $('#labeling_id').val(data.id);
            $('#labeling_invoice_number').val(data.invoice_number || '');
            $('#labeling_guide_number').val(data.guide_number || '');
            $('#labeling_destination').val(data.destination || '');
            $('#labeling_boxes_count').val(data.boxes_count || 1);
            $('#labeling_observations').val(data.observations || '');

            currentLabelingOrder = data.order;
            $('#labeling_customer_purchase_order_id')
                .html('')
                .append(new Option(`${data.order.code} | ${data.order.customer_name}`, data.customer_purchase_order_id, true, true))
                .val(data.customer_purchase_order_id)
                .prop('disabled', true);

            fillLabelingOrderSummary(data.order);
            renderLabelingOrderItems(data.order.items || []);
            applyLabelingItemsToLabel(data.boxes || []);
            renderLabelingBoxes(getSavedLabelingBoxDistribution(data.boxes || []));

            updateLabelingAvailableSummary();
            $('#labelingModal').modal('show');
        })
        .fail(function (xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo cargar la rotulación.', 'error');
        });
}

function getSavedLabelingBoxDistribution(boxes) {
    const distribution = {};

    boxes.forEach(function (box) {
        distribution[box.box_number] = {
            observation: box.observation || '',
            items: (box.items || []).map(function (item) {
                return {
                    itemId: item.customer_purchase_order_item_id,
                    quantity: item.quantity
                };
            })
        };
    });

    return distribution;
}

function applyLabelingItemsToLabel(boxes) {
    const totals = {};

    boxes.forEach(function (box) {
        (box.items || []).forEach(function (item) {
            const itemId = String(item.customer_purchase_order_item_id);
            totals[itemId] = roundLabeling((totals[itemId] || 0) + (parseFloat(item.quantity) || 0));
        });
    });

    $('.labeling-item-distribute').each(function () {
        const itemId = String($(this).data('item-id'));
        if (totals[itemId]) {
            $(this).val(totals[itemId]);
        }
    });
}

function deleteLabeling(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Anular rotulación',
        text: 'Esta acción anulará la rotulación seleccionada.',
        showCancelButton: true,
        confirmButtonText: 'Sí, anular',
        cancelButtonText: 'Cancelar'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }

        $.ajax({
            url: window.routes.labelingsDestroy.replace(':id', id),
            type: 'DELETE',
            success: function (response) {
                tableLabelings.ajax.reload(null, false);
                Swal.fire('Correcto', response.message || 'Rotulación anulada correctamente.', 'success');
            },
            error: function () {
                Swal.fire('Error', 'No se pudo anular la rotulación.', 'error');
            }
        });
    });
}

function renderLabelingDetail(data) {
    const boxes = (data.boxes || []).map(function (box) {
        const rows = (box.items || []).map(function (item) {
            const orderItem = (data.order.items || []).find(current => String(current.id) === String(item.customer_purchase_order_item_id));
            const description = item.description || orderItem?.description || orderItem?.article_name || 'ARTÍCULO';
            const unitName = item.unit_name ? ` <span class="text-muted">(${escapeLabelingHtml(item.unit_name)})</span>` : '';

            return `<li>${escapeLabelingHtml(description)}${unitName}: <strong>${formatLabelingNumber(item.quantity)}</strong></li>`;
        }).join('');
        const observation = box.observation
            ? `<div class="alert alert-warning py-1 px-2 mt-2 mb-0"><strong>Observación:</strong> ${escapeLabelingHtml(box.observation)}</div>`
            : '';

        return `<div class="col-md-6 mb-2"><div class="border rounded p-2"><strong>Caja ${box.box_label}</strong><ul class="mb-0 pl-3">${rows}</ul>${observation}</div></div>`;
    }).join('');

    return `
        <div class="card border-0 shadow-sm mb-2">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2"><small class="text-muted font-weight-bold">CLIENTE</small><div class="font-weight-bold">${escapeLabelingHtml(data.order.customer_name)}</div></div>
                    <div class="col-md-3 mb-2"><small class="text-muted font-weight-bold">FACTURA</small><div>${escapeLabelingHtml(data.invoice_number || '-')}</div></div>
                    <div class="col-md-3 mb-2"><small class="text-muted font-weight-bold">GUÍA</small><div>${escapeLabelingHtml(data.guide_number || '-')}</div></div>
                    <div class="col-md-6"><small class="text-muted font-weight-bold">ORDEN</small><div>${escapeLabelingHtml(data.order.purchase_order_number || data.order.code || '-')}</div></div>
                    <div class="col-md-3"><small class="text-muted font-weight-bold">CAJAS</small><div>${escapeLabelingHtml(data.boxes_count || '-')}</div></div>
                    <div class="col-md-3"><small class="text-muted font-weight-bold">ESTADO</small><div>${escapeLabelingHtml(data.status || '-')}</div></div>
                </div>
            </div>
        </div>
        <div class="row">
            ${boxes}
        </div>
    `;
}

function handleLabelingError(xhr) {
    if (xhr.status === 422) {
        const errors = xhr.responseJSON?.errors || {};
        const messages = [];

        Object.entries(errors).forEach(function ([field, fieldMessages]) {
            const input = $(`[name="${field}"]`);
            const message = fieldMessages[0];

            if (input.length) {
                input.addClass('is-invalid');
                input.closest('.form-group').find('.invalid-feedback').first().text(message);
            }

            messages.push(message);
        });

        $('#labelingErrors')
            .removeClass('d-none')
            .html(`<ul class="mb-0 pl-3">${messages.map(message => `<li>${escapeLabelingHtml(message)}</li>`).join('')}</ul>`);
        return;
    }

    Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo guardar la rotulación.', 'error');
}

function clearLabelingErrors() {
    $('#labelingForm').find('.is-invalid').removeClass('is-invalid');
    $('#labelingForm').find('.invalid-feedback').text('');
    $('#labelingErrors').addClass('d-none').empty();
}

function findLabelingOrderItem(itemId) {
    return (currentLabelingOrder?.items || []).find(item => String(item.id) === String(itemId));
}

function roundLabeling(value) {
    return Math.round((parseFloat(value) || 0) * 100) / 100;
}

function formatLabelingNumber(value) {
    return Number(value || 0).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function formatLabelingDate(value) {
    if (!value) {
        return '';
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('es-PE');
}

function escapeLabelingHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
