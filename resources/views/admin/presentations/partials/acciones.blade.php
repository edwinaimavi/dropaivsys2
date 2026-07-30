<x-table-actions-dropdown label="Acciones de la presentación">
    <x-slot name="main">
        @can('admin.presentations.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewPresentation"
                data-id="{{ $presentation->id }}" data-description="{{ $presentation->description }}"
                data-quantity="{{ $presentation->quantity }}" data-unit="{{ $presentation->unit->description ?? '-' }}"
                data-observation="{{ $presentation->observation }}" data-status="{{ $presentation->status }}"
                data-created_at="{{ $presentation->created_at ? $presentation->created_at->format('d/m/Y H:i') : '-' }}"
                data-updated_at="{{ $presentation->updated_at ? $presentation->updated_at->format('d/m/Y H:i') : '-' }}"
                data-created_by="{{ $presentation->creator->name ?? 'No registrado' }}"
                data-updated_by="{{ $presentation->editor->name ?? 'No registrado' }}" title="Ver presentación">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @can('admin.presentations.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editPresentation" data-id="{{ $presentation->id }}"
                data-description="{{ $presentation->description }}" data-quantity="{{ $presentation->quantity }}"
                data-unit_id="{{ $presentation->unit_id }}" data-observation="{{ $presentation->observation }}"
                data-status="{{ $presentation->status }}">
                <i class="fas fa-pen text-primary"></i> Editar presentación
            </button>
        @endcan
        @can('admin.presentations.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deletePresentation" data-id="{{ $presentation->id }}">
                <i class="fas fa-trash"></i> Eliminar presentación
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
