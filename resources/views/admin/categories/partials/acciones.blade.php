<div class="btn-group shadow-sm" role="group" aria-label="Acciones">
    @can('admin.categories.show')
        <button type="button" class="btn btn-outline-info btn-sm viewCategory" data-toggle="tooltip" title="Ver categoria"
            data-id="{{ $category->id }}" data-description="{{ $category->description }}" data-code="{{ $category->code }}"
            data-type="{{ $category->type }}" data-observation="{{ $category->observation }}"
            data-status="{{ $category->status }}"
            data-created_at="{{ $category->created_at ? $category->created_at->format('d/m/Y H:i') : '-' }}"
            data-updated_at="{{ $category->updated_at ? $category->updated_at->format('d/m/Y H:i') : '-' }}"
            data-created_by="{{ $category->creator->name ?? 'No registrado' }}"
            data-updated_by="{{ $category->editor->name ?? 'No registrado' }}"
            data-subcategories='@json($category->subcategories)'>
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.categories.update')
        <button type="button" class="btn btn-outline-primary btn-sm editCategory" data-toggle="tooltip"
            title="Editar categoria" data-id="{{ $category->id }}" data-description="{{ $category->description }}"
            data-code="{{ $category->code }}" data-type="{{ $category->type }}"
            data-observation="{{ $category->observation }}" data-status="{{ $category->status }}">
            <i class="fas fa-pen"></i>
        </button>
    @endcan

    @can('admin.subcategories.index')
        <button type="button" class="btn btn-outline-success btn-sm subcategoryCategory" data-toggle="tooltip"
            title="Subcategorias" data-id="{{ $category->id }}" data-description="{{ $category->description }}">
            <i class="fas fa-layer-group"></i>
        </button>
    @endcan

    @can('admin.categories.destroy')
        <button type="button" class="btn btn-outline-danger btn-sm deleteCategory" data-id="{{ $category->id }}"
            data-toggle="tooltip" title="Eliminar categoria">
            <i class="fas fa-trash"></i>
        </button>
    @endcan
</div>
