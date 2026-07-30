<x-table-actions-dropdown label="Acciones de la cotización">
    <x-slot name="main">
        @can('admin.quotes.pdf')
            @if ($pdfUrl)
                <a href="{{ $pdfUrl }}" target="_blank" rel="noopener"
                    class="btn btn-sm btn-success dp-action-main" title="Ver PDF">
                    <i class="fas fa-eye mr-1"></i> Ver PDF
                </a>
            @else
                <button type="button" class="btn btn-sm btn-secondary dp-action-main" disabled title="PDF no generado">
                    <i class="fas fa-eye mr-1"></i> Ver PDF
                </button>
            @endif
        @endcan
    </x-slot>
    <x-slot name="menu">
        @can('admin.quotes.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editQuote" data-id="{{ $quote->id }}">
                <i class="fas fa-pen text-primary"></i> Editar cotización
            </button>
        @endcan
        @can('admin.quotes.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteQuote" data-id="{{ $quote->id }}">
                <i class="fas fa-trash"></i> Eliminar cotización
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
