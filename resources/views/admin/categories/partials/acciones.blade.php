<x-table-actions-dropdown label="Acciones de la categoría">
    <x-slot name="main">
        @can('admin.categories.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewCategory" title="Ver categoría"
                data-id="{{ $category->id }}" data-description="{{ $category->description }}"
                data-code="{{ $category->code }}" data-type="{{ $category->type }}"
                data-observation="{{ $category->observation }}" data-status="{{ $category->status }}"
                data-created_at="{{ $category->created_at ? $category->created_at->format('d/m/Y H:i') : '-' }}"
                data-updated_at="{{ $category->updated_at ? $category->updated_at->format('d/m/Y H:i') : '-' }}"
                data-created_by="{{ $category->creator->name ?? 'No registrado' }}"
                data-updated_by="{{ $category->editor->name ?? 'No registrado' }}"
                data-subcategories='@json($category->subcategories)'>
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @canany(['admin.categories.update', 'admin.subcategories.index'])
            <h6 class="dropdown-header">Acciones operativas</h6>
            @can('admin.categories.update')
                <button type="button" class="dropdown-item editCategory" data-id="{{ $category->id }}"
                    data-description="{{ $category->description }}" data-code="{{ $category->code }}"
                    data-type="{{ $category->type }}" data-observation="{{ $category->observation }}"
                    data-status="{{ $category->status }}">
                    <i class="fas fa-pen text-primary"></i> Editar categoría
                </button>
            @endcan
            @can('admin.subcategories.index')
                <button type="button" class="dropdown-item subcategoryCategory" data-id="{{ $category->id }}"
                    data-description="{{ $category->description }}">
                    <i class="fas fa-layer-group text-success"></i> Gestionar subcategorías
                </button>
            @endcan
        @endcanany
        @can('admin.categories.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteCategory" data-id="{{ $category->id }}">
                <i class="fas fa-trash"></i> Eliminar categoría
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
