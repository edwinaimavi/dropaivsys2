<div class="btn-group shadow-sm" role="group" aria-label="Acciones">
    @can('admin.brands.show')
        <button type="button" class="btn btn-outline-info btn-sm viewBrand" data-toggle="tooltip" title="Ver marca"
            data-id="{{ $brand->id }}" data-code="{{ $brand->code }}" data-description="{{ $brand->description }}"
            data-observation="{{ $brand->observation }}" data-status="{{ $brand->status }}"
            data-created_at="{{ $brand->created_at ? $brand->created_at->format('d/m/Y H:i') : '-' }}"
            data-updated_at="{{ $brand->updated_at ? $brand->updated_at->format('d/m/Y H:i') : '-' }}"
            data-created_by="{{ $brand->creator->name ?? 'No registrado' }}"
            data-updated_by="{{ $brand->editor->name ?? 'No registrado' }}">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.brands.update')
        <button type="button" class="btn btn-outline-secondary btn-sm editBrand" data-toggle="tooltip" title="Editar marca"
            data-id="{{ $brand->id }}" data-code="{{ $brand->code }}" data-description="{{ $brand->description }}"
            data-observation="{{ $brand->observation }}" data-status="{{ $brand->status }}">
            <i class="fas fa-pen"></i>
        </button>
    @endcan

    @can('admin.brands.destroy')
        <button type="button" class="btn btn-outline-danger btn-sm deleteBrand" data-id="{{ $brand->id }}"
            data-toggle="tooltip" title="Eliminar marca">
            <i class="fas fa-trash"></i>
        </button>
    @endcan
</div>
