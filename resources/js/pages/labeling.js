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
        } else {
            currentLabelingOrder = null;
            renderLabelingOrderItems([]);
            renderLabelingBoxes();
        }
    });

    $(document).on('input change', '#labeling_boxes_count', renderLabelingBoxes);
    $(document).on('input', '.labeling-item-distribute', updateLabelingAvailableSummary);

    $(document).on('click', '.btnAddLabelingBoxItem', function () {
        const box = $(this).closest('.labeling-box-card');
        addLabelingBoxItemRow(box.data('box-number'));
    });

    $(document).on('click', '.btnRemoveLabelingBoxItem', function () {
        $(this).closest('.labeling-box-item-row').remove();
    });

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
    $('#labeling_customer_purchase_order_id').prop('disabled', false).html('<option value="">Seleccione orden abastecida</option>');
    $('#labeling_customer_name, #labeling_company_name, #labeling_order_number').val('');
    $('#labeling_boxes_count').val(1);
    currentLabelingOrder = null;
    clearLabelingErrors();
    renderLabelingOrderItems([]);
    renderLabelingBoxes();
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
}

function renderLabelingOrderItems(items) {
    const tbody = $('#labelingOrderItemsTable tbody');

    if (!items.length) {
        tbody.html('<tr><td colspan="10" class="text-center text-muted py-3">Seleccione una orden abastecida.</td></tr>');
        return;
    }

    tbody.html(items.map(function (item) {
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
                <td class="text-right labeling-available-cell">${formatLabelingNumber(item.available_quantity)}</td>
                <td>
                    <input type="number" class="form-control form-control-sm text-right labeling-item-distribute"
                        data-item-id="${item.id}" min="0" step="0.01" max="${item.available_quantity}"
                        value="${item.available_quantity > 0 ? item.available_quantity : 0}">
                </td>
            </tr>
        `;
    }).join(''));
}

function renderLabelingBoxes() {
    const count = parseInt($('#labeling_boxes_count').val(), 10) || 0;
    const container = $('#labelingBoxesContainer');

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
                        <button type="button" class="btn btn-outline-primary btn-xs btnAddLabelingBoxItem">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="card-body p-2">
                        <div class="labeling-box-items"></div>
                    </div>
                </div>
            </div>
        `;
    }

    container.html(html);
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
            <button type="button" class="btn btn-outline-danger btn-sm btnRemoveLabelingBoxItem">
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

    renderLabelingBoxes();

    $('.labeling-item-distribute').each(function () {
        const input = $(this);
        const itemId = input.data('item-id');
        const total = roundLabeling(parseFloat(input.val()) || 0);

        if (total <= 0) {
            return;
        }

        const base = Math.floor((total / count) * 100) / 100;
        let accumulated = 0;

        for (let box = 1; box <= count; box++) {
            let quantity = box === count
                ? roundLabeling(total - accumulated)
                : base;
            accumulated = roundLabeling(accumulated + quantity);

            if (quantity > 0) {
                addLabelingBoxItemRow(box, itemId, quantity);
            }
        }
    });
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

            if (response.data?.pdf_url) {
                window.open(response.data.pdf_url, '_blank');
            }
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
    formData.append('boxes_count', $('#labeling_boxes_count').val() || '');
    formData.append('observations', $('#labeling_observations').val() || '');

    $('.labeling-box-card').each(function (boxIndex) {
        const box = $(this);
        formData.append(`boxes[${boxIndex}][box_number]`, box.data('box-number'));

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

    const totals = {};
    let hasItems = false;
    let hasNegative = false;

    $('.labeling-box-item-row').each(function () {
        const itemId = $(this).find('.labeling-box-item-select').val();
        const quantity = parseFloat($(this).find('.labeling-box-item-quantity').val()) || 0;

        if (quantity < 0) {
            hasNegative = true;
            return;
        }

        if (!itemId && quantity > 0) {
            return;
        }

        if (itemId && quantity > 0) {
            hasItems = true;
            totals[itemId] = roundLabeling((totals[itemId] || 0) + quantity);
        }

    });

    if (hasNegative) {
        return { valid: false, message: 'No se permiten cantidades negativas.' };
    }

    if (!hasItems) {
        return { valid: false, message: 'Debe distribuir al menos un artículo en una caja.' };
    }

    for (const item of currentLabelingOrder.items || []) {
        if ((totals[item.id] || 0) > parseFloat(item.available_quantity)) {
            return {
                valid: false,
                message: `La cantidad distribuida supera lo disponible para ${item.article_name}.`
            };
        }
    }

    return { valid: true };
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
            renderLabelingBoxes();

            (data.boxes || []).forEach(function (box) {
                (box.items || []).forEach(function (item) {
                    addLabelingBoxItemRow(box.box_number, item.customer_purchase_order_item_id, item.quantity);
                });
            });

            $('#labelingModal').modal('show');
        })
        .fail(function (xhr) {
            Swal.fire('Error', xhr.responseJSON?.message || 'No se pudo cargar la rotulación.', 'error');
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
            return `<li>${escapeLabelingHtml(orderItem?.article_name || 'ARTÍCULO')}: <strong>${formatLabelingNumber(item.quantity)}</strong></li>`;
        }).join('');

        return `<div class="col-md-6 mb-2"><div class="border rounded p-2"><strong>Caja ${box.box_label}</strong><ul class="mb-0 pl-3">${rows}</ul></div></div>`;
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

function updateLabelingAvailableSummary() {
    return true;
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
