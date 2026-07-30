<x-table-actions-dropdown label="Acciones de la marca">
    <x-slot name="main">
        @can('admin.brands.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewBrand" title="Ver marca"
                data-id="{{ $brand->id }}" data-code="{{ $brand->code }}" data-description="{{ $brand->description }}"
                data-observation="{{ $brand->observation }}" data-status="{{ $brand->status }}"
                data-created_at="{{ $brand->created_at ? $brand->created_at->format('d/m/Y H:i') : '-' }}"
                data-updated_at="{{ $brand->updated_at ? $brand->updated_at->format('d/m/Y H:i') : '-' }}"
                data-created_by="{{ $brand->creator->name ?? 'No registrado' }}"
                data-updated_by="{{ $brand->editor->name ?? 'No registrado' }}">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @can('admin.brands.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editBrand" data-id="{{ $brand->id }}"
                data-code="{{ $brand->code }}" data-description="{{ $brand->description }}"
                data-observation="{{ $brand->observation }}" data-status="{{ $brand->status }}">
                <i class="fas fa-pen text-primary"></i> Editar marca
            </button>
        @endcan
        @can('admin.brands.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteBrand" data-id="{{ $brand->id }}">
                <i class="fas fa-trash"></i> Eliminar marca
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
