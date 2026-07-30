<x-table-actions-dropdown label="Acciones del etiquetado">
    <x-slot name="main">
        @can('admin.labelings.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewLabeling"
                data-id="{{ $labeling->id }}" title="Ver detalle">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @can('admin.labelings.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editLabeling" data-id="{{ $labeling->id }}">
                <i class="fas fa-edit text-primary"></i> Editar etiquetado
            </button>
        @endcan
        @can('admin.labelings.pdf')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Documentos</h6>
            <a href="{{ route('admin.labelings.pdf', $labeling->id) }}" target="_blank" rel="noopener"
                class="dropdown-item">
                <i class="fas fa-file-pdf text-danger"></i> Ver PDF
            </a>
        @endcan
        @can('admin.labelings.destroy')
            @if ($labeling->status !== 'CANCELLED')
                <div class="dropdown-divider"></div>
                <h6 class="dropdown-header">Cierre / anulación</h6>
                <button type="button" class="dropdown-item text-danger deleteLabeling" data-id="{{ $labeling->id }}">
                    <i class="fas fa-ban"></i> Anular etiquetado
                </button>
            @endif
        @endcan
    </x-slot>
</x-table-actions-dropdown>
