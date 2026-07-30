<x-table-actions-dropdown label="Acciones de la unidad">
    <x-slot name="main">
        @can('admin.units.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewUnit" title="Ver unidad"
                data-id="{{ $unit->id }}" data-abbreviation="{{ $unit->abbreviation }}"
                data-description="{{ $unit->description }}" data-decimal_quantity="{{ $unit->decimal_quantity }}"
                data-observation="{{ $unit->observation }}" data-status="{{ $unit->status }}"
                data-created_at="{{ $unit->created_at ? $unit->created_at->format('d/m/Y H:i') : '-' }}"
                data-updated_at="{{ $unit->updated_at ? $unit->updated_at->format('d/m/Y H:i') : '-' }}"
                data-created_by="{{ $unit->creator->name ?? 'No registrado' }}"
                data-updated_by="{{ $unit->editor->name ?? 'No registrado' }}">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @can('admin.units.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editUnit" data-id="{{ $unit->id }}"
                data-abbreviation="{{ $unit->abbreviation }}" data-description="{{ $unit->description }}"
                data-decimal_quantity="{{ $unit->decimal_quantity }}" data-observation="{{ $unit->observation }}"
                data-status="{{ $unit->status }}">
                <i class="fas fa-pen text-primary"></i> Editar unidad
            </button>
        @endcan
        @can('admin.units.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteUnit" data-id="{{ $unit->id }}">
                <i class="fas fa-trash"></i> Eliminar unidad
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
