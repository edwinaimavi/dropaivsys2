<div class="btn-group" role="group">
    @can('admin.electronic-invoices.show')
    <button type="button" class="btn btn-sm btn-outline-info viewElectronicInvoice" data-id="{{ $invoice->id }}"
        title="Ver detalle">
        <i class="fas fa-eye"></i>
    </button>
    @endcan
    @can('admin.electronic-invoices.update')
    @if ($invoice->status === 'draft')
    <button type="button" class="btn btn-sm btn-outline-success editElectronicInvoice" data-id="{{ $invoice->id }}"
        title="Completar y generar internamente">
        <i class="fas fa-check-circle"></i>
    </button>
    @endif
    @if ($invoice->status !== 'cancelled')
    <button type="button" class="btn btn-sm btn-outline-primary editElectronicInvoice" data-id="{{ $invoice->id }}"
        title="Editar">
        <i class="fas fa-edit"></i>
    </button>
    @endif
    @endcan
    @can('admin.electronic-invoices.pdf')
    @if ($invoice->status === 'generated')
    <a href="{{ route('admin.electronic-invoices.pdf', $invoice) }}" target="_blank" rel="noopener"
        class="btn btn-sm btn-outline-success" title="PDF local preliminar">
        <i class="fas fa-file-pdf"></i>
    </a>
    @endif
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
    @can('admin.electronic-invoices.send')
    @if ($invoice->status === 'generated')
    <button type="button"
        class="btn btn-sm btn-outline-info {{ $apiReady ? 'sendElectronicInvoiceToApi' : 'apiNotConfiguredElectronicInvoice' }}"
        data-id="{{ $invoice->id }}"
        title="{{ $apiReady ? 'Preparar envío a SUNAT' : 'Configura APIs Perú antes de enviar a SUNAT' }}">
        <i class="fas fa-paper-plane"></i>
    </button>
    @endif
    @endcan
    @can('admin.electronic-invoices.destroy')
    @if ($invoice->status !== 'cancelled')
    <button type="button" class="btn btn-sm btn-outline-danger deleteElectronicInvoice" data-id="{{ $invoice->id }}"
        title="Cancelar">
        <i class="fas fa-trash-alt"></i>
    </button>
    @endif
    @endcan
</div>
