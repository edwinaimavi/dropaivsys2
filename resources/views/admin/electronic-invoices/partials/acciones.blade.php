<x-table-actions-dropdown label="Acciones del comprobante electrónico">
    <x-slot name="main">
        @can('admin.electronic-invoices.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewElectronicInvoice"
                data-id="{{ $invoice->id }}" title="Ver detalle">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @canany(['admin.electronic-invoices.update', 'admin.electronic-invoices.payload', 'admin.electronic-invoices.send'])
            <h6 class="dropdown-header">Acciones operativas</h6>
            @can('admin.electronic-invoices.update')
                @if ($invoice->status === 'draft')
                    <button type="button" class="dropdown-item editElectronicInvoice" data-id="{{ $invoice->id }}">
                        <i class="fas fa-check-circle text-success"></i> Completar y generar
                    </button>
                @endif
                @if ($invoice->status !== 'cancelled')
                    <button type="button" class="dropdown-item editElectronicInvoice" data-id="{{ $invoice->id }}">
                        <i class="fas fa-edit text-primary"></i> Editar comprobante
                    </button>
                @endif
            @endcan
            @can('admin.electronic-invoices.payload')
                <button type="button" class="dropdown-item previewElectronicInvoicePayload" data-id="{{ $invoice->id }}">
                    <i class="fas fa-code text-dark"></i> Ver payload JSON
                </button>
            @endcan
            @can('admin.electronic-invoices.send')
                @if ($invoice->status === 'generated')
                    <button type="button"
                        class="dropdown-item {{ $apiReady ? 'sendElectronicInvoiceToApi' : 'apiNotConfiguredElectronicInvoice' }}"
                        data-id="{{ $invoice->id }}">
                        <i class="fas fa-paper-plane text-info"></i>
                        {{ $apiReady ? 'Preparar envío a SUNAT' : 'API no configurada' }}
                    </button>
                @endif
            @endcan
        @endcanany

        @canany(['admin.electronic-invoices.pdf', 'admin.electronic-invoices.xml', 'admin.electronic-invoices.cdr'])
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Documentos</h6>
            @can('admin.electronic-invoices.pdf')
                @if ($invoice->status === 'generated')
                    <a href="{{ route('admin.electronic-invoices.pdf', $invoice) }}" target="_blank" rel="noopener"
                        class="dropdown-item">
                        <i class="fas fa-file-pdf text-danger"></i> Ver PDF preliminar
                    </a>
                @endif
            @endcan
            @can('admin.electronic-invoices.xml')
                <button type="button" class="dropdown-item disabledElectronicInvoiceApiAction">
                    <i class="fas fa-file-code text-secondary"></i> XML
                </button>
            @endcan
            @can('admin.electronic-invoices.cdr')
                <button type="button" class="dropdown-item disabledElectronicInvoiceApiAction">
                    <i class="fas fa-file-archive text-secondary"></i> CDR
                </button>
            @endcan
        @endcanany

        @can('admin.electronic-invoices.destroy')
            @if ($invoice->status !== 'cancelled')
                <div class="dropdown-divider"></div>
                <h6 class="dropdown-header">Cierre / anulación</h6>
                <button type="button" class="dropdown-item text-danger deleteElectronicInvoice"
                    data-id="{{ $invoice->id }}">
                    <i class="fas fa-trash-alt"></i> Cancelar comprobante
                </button>
            @endif
        @endcan
    </x-slot>
</x-table-actions-dropdown>
