let tableElectronicInvoiceSeries;

document.addEventListener('DOMContentLoaded', function () {
    $.fn.dataTable.ext.errMode = 'none';

    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    tableElectronicInvoiceSeries = $('#tableElectronicInvoiceSeries').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: window.routes.electronicInvoiceSeriesList,
            error: function () {
                toast('error', 'No se pudo cargar la información. Revise la conexión o contacte al administrador.');
            }
        },
        columns: [
            { data: 'company', name: 'companies.business_name' },
            { data: 'document_type_label', name: 'electronic_invoice_series.document_type' },
            { data: 'serie', name: 'electronic_invoice_series.serie' },
            { data: 'current_number', name: 'electronic_invoice_series.current_number' },
            { data: 'next_number', name: 'electronic_invoice_series.next_number' },
            { data: 'environment', name: 'electronic_invoice_series.environment', render: renderEnvironment },
            { data: 'status', name: 'electronic_invoice_series.status' },
            { data: 'acciones', name: 'acciones', orderable: false, searchable: false }
        ],
        responsive: true,
        autoWidth: false,
        language: {
            url: '/vendor/datatables/js/i18n/es-ES.json'
        }
    });

    $('#electronicInvoiceSeriesForm').on('submit', function (event) {
        event.preventDefault();
        clearSeriesErrors();

        const id = $('#series_id').val();
        const url = id
            ? `${window.routes.electronicInvoiceSeriesBase}/${id}`
            : window.routes.electronicInvoiceSeriesStore;
        const data = $(this).serializeArray();

        if (id) {
            data.push({ name: '_method', value: 'PUT' });
        }

        $.ajax({
            url,
            type: 'POST',
            data: $.param(data),
            success: function (response) {
                $('#electronicInvoiceSeriesModal').modal('hide');
                tableElectronicInvoiceSeries.ajax.reload(null, false);
                toast('success', response.message || 'Serie guardada correctamente.');
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    showSeriesErrors(xhr.responseJSON.errors || {});
                    return;
                }

                toast('error', xhr.responseJSON?.message || 'No se pudo guardar la serie.');
            }
        });
    });

    $('#electronicInvoiceSeriesModal').on('hidden.bs.modal', resetSeriesForm);

    $(document).on('click', '.editElectronicInvoiceSeries', function () {
        loadSeries($(this).data('id'), true);
    });

    $(document).on('click', '.viewElectronicInvoiceSeries', function () {
        loadSeries($(this).data('id'), false);
    });

    $(document).on('click', '.deleteElectronicInvoiceSeries', function () {
        const id = $(this).data('id');

        Swal.fire({
            title: '¿Está seguro?',
            text: 'La serie será eliminada.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) {
                return;
            }

            $.ajax({
                url: `${window.routes.electronicInvoiceSeriesBase}/${id}`,
                type: 'POST',
                data: { _method: 'DELETE' },
                success: function (response) {
                    tableElectronicInvoiceSeries.ajax.reload(null, false);
                    toast('success', response.message || 'Serie eliminada correctamente.');
                },
                error: function (xhr) {
                    toast('error', xhr.responseJSON?.message || 'No se pudo eliminar la serie.');
                }
            });
        });
    });
});

function loadSeries(id, editable) {
    $.get(`${window.routes.electronicInvoiceSeriesBase}/${id}`, function (response) {
        const item = response.data;

        if (editable) {
            $('#series_id').val(item.id);
            $('#series_company_id').val(item.company_id);
            $('#series_document_type').val(item.document_type);
            $('#series_environment').val(item.environment);
            $('#series_serie').val(item.serie);
            $('#series_current_number').val(item.current_number);
            $('#series_next_number').val(item.next_number);
            $('#series_description').val(item.description);
            $('#series_status').val(item.status);
            $('#series_is_default').prop('checked', Boolean(item.is_default));
            $('#electronicInvoiceSeriesModalTitle').text('Editar Serie');
            $('#electronicInvoiceSeriesModal').modal('show');
            return;
        }

        $('#view_series_company').text(item.company?.trade_name || item.company?.business_name || '-');
        $('#view_series_document_type').text(documentTypeLabel(item.document_type));
        $('#view_series_serie').text(item.serie || '-');
        $('#view_series_current_number').text(item.current_number ?? '-');
        $('#view_series_next_number').text(item.next_number ?? '-');
        $('#view_series_environment').text(renderEnvironment(item.environment, 'display'));
        $('#view_series_status').text(item.status === 'ACTIVE' ? 'Activo' : 'Inactivo');
        $('#electronicInvoiceSeriesViewModal').modal('show');
    });
}

function resetSeriesForm() {
    const form = $('#electronicInvoiceSeriesForm')[0];
    if (form) {
        form.reset();
    }

    $('#series_id').val('');
    $('#series_current_number').val(0);
    $('#series_next_number').val(1);
    $('#electronicInvoiceSeriesModalTitle').text('Nueva Serie');
    clearSeriesErrors();
}

function clearSeriesErrors() {
    $('#electronicInvoiceSeriesForm .is-invalid').removeClass('is-invalid');
    $('[id^="series_"][id$="-error"]').text('');
    $('#series_general_errors').addClass('d-none').empty();
}

function showSeriesErrors(errors) {
    const general = [];

    $.each(errors, function (key, messages) {
        const message = messages[0] || 'Revise el dato ingresado.';
        const inputId = `#series_${key}`;
        const errorId = `#series_${key}-error`;

        if ($(inputId).length && $(errorId).length) {
            $(inputId).addClass('is-invalid');
            $(errorId).text(message);
            return;
        }

        general.push(message);
    });

    if (general.length) {
        $('#series_general_errors').removeClass('d-none').html(general.join('<br>'));
    }
}

function renderEnvironment(data, type) {
    const label = data === 'production' ? 'Producción' : 'Beta';

    if (type !== 'display') {
        return label;
    }

    return `<span class="badge badge-light border rounded-pill px-3">${label}</span>`;
}

function documentTypeLabel(type) {
    return {
        '01': 'Factura',
        '03': 'Boleta',
        '07': 'Nota de Crédito',
        '08': 'Nota de Débito'
    }[type] || type || '-';
}

function toast(icon, title) {
    Swal.fire({
        icon,
        title,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
}
