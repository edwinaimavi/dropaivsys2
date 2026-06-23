<div class="btn-group shadow-sm" role="group" aria-label="Actions">

    {{-- VIEW --}}
    <button type="button" class="btn btn-outline-info btn-sm viewCategory" data-toggle="tooltip" title="Ver Categoría"
        data-id="{{ $category->id }}" data-description="{{ $category->description }}" data-code="{{ $category->code }}"
        data-type="{{ $category->type }}" data-observation="{{ $category->observation }}"
        data-status="{{ $category->status }}"
        data-created_at="{{ $category->created_at ? $category->created_at->format('d/m/Y H:i') : '—' }}"
        data-updated_at="{{ $category->updated_at ? $category->updated_at->format('d/m/Y H:i') : '—' }}"
        data-created_by="{{ $category->creator->name ?? 'No registrado' }}"
        data-updated_by="{{ $category->editor->name ?? 'No registrado' }}"
        data-subcategories='@json($category->subcategories)'>

        <i class="fas fa-eye"></i>

    </button>

    {{-- EDIT --}}
    <button type="button" class="btn btn-outline-primary btn-sm editCategory" data-toggle="tooltip"
        title="Editar Categoría" data-id="{{ $category->id }}" data-description="{{ $category->description }}"
        data-code="{{ $category->code }}" data-type="{{ $category->type }}"
        data-observation="{{ $category->observation }}" data-status="{{ $category->status }}">

        <i class="fas fa-pen"></i>

    </button>

    {{-- SUBCATEGORÍAS --}}
    <button type="button" class="btn btn-outline-success btn-sm subcategoryCategory" data-toggle="tooltip"
        title="Subcategorías" data-id="{{ $category->id }}" data-description="{{ $category->description }}">

        <i class="fas fa-layer-group"></i>

    </button>

    {{-- DELETE --}}
    <button type="button" class="btn btn-outline-danger btn-sm deleteCategory" data-id="{{ $category->id }}"
        data-toggle="tooltip" title="Eliminar Categoría">

        <i class="fas fa-trash"></i>

    </button>

</div>
