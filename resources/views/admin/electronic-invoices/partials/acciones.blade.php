<div class="btn-group" role="group">
    @can('admin.electronic-invoices.show')
    <button type="button" class="btn btn-sm btn-outline-info viewElectronicInvoice" data-id="{{ $invoice->id }}"
        title="Ver detalle">
        <i class="fas fa-eye"></i>
    </button>
    @endcan
    @can('admin.electronic-invoices.update')
    <button type="button" class="btn btn-sm btn-outline-primary editElectronicInvoice" data-id="{{ $invoice->id }}"
        title="Editar">
        <i class="fas fa-edit"></i>
    </button>
    @endcan
    @can('admin.electronic-invoices.pdf')
    <a href="{{ route('admin.electronic-invoices.pdf', $invoice) }}" target="_blank" rel="noopener"
        class="btn btn-sm btn-outline-success" title="PDF local preliminar">
        <i class="fas fa-file-pdf"></i>
    </a>
    @endcan
    @can('admin.electronic-invoices.payload')
    <button type="button" class="btn btn-sm btn-outline-dark previewElectronicInvoicePayload"
        data-id="{{ $invoice->id }}" title="Payload JSON">
        <i class="fas fa-code"></i>
    </button>
    @endcan
    @can('admin.electronic-invoices.xml')
    <button type="button" class="btn btn-sm btn-outline-secondary disabledElectronicInvoiceApiAction"
        title="XML">
        <i class="fas fa-file-code"></i>
    </button>
    @endcan
    @can('admin.electronic-invoices.cdr')
    <button type="button" class="btn btn-sm btn-outline-secondary disabledElectronicInvoiceApiAction"
        title="CDR">
        <i class="fas fa-file-archive"></i>
    </button>
    @endcan
    @can('admin.electronic-invoices.destroy')
    <button type="button" class="btn btn-sm btn-outline-danger deleteElectronicInvoice" data-id="{{ $invoice->id }}"
        title="Cancelar">
        <i class="fas fa-trash-alt"></i>
    </button>
    @endcan
</div>
