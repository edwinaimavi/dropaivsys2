<div class="btn-group shadow-sm" role="group" aria-label="Acciones">
    @can('admin.presentations.show')
        <button type="button" class="btn btn-outline-info btn-sm viewPresentation" data-toggle="tooltip"
            title="Ver presentacion" data-id="{{ $presentation->id }}" data-description="{{ $presentation->description }}"
            data-quantity="{{ $presentation->quantity }}" data-unit="{{ $presentation->unit->description ?? '-' }}"
            data-observation="{{ $presentation->observation }}" data-status="{{ $presentation->status }}"
            data-created_at="{{ $presentation->created_at ? $presentation->created_at->format('d/m/Y H:i') : '-' }}"
            data-updated_at="{{ $presentation->updated_at ? $presentation->updated_at->format('d/m/Y H:i') : '-' }}"
            data-created_by="{{ $presentation->creator->name ?? 'No registrado' }}"
            data-updated_by="{{ $presentation->editor->name ?? 'No registrado' }}">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.presentations.update')
        <button type="button" class="btn btn-outline-warning btn-sm editPresentation" data-toggle="tooltip"
            title="Editar presentacion" data-id="{{ $presentation->id }}"
            data-description="{{ $presentation->description }}" data-quantity="{{ $presentation->quantity }}"
            data-unit_id="{{ $presentation->unit_id }}" data-observation="{{ $presentation->observation }}"
            data-status="{{ $presentation->status }}">
            <i class="fas fa-pen"></i>
        </button>
    @endcan

    @can('admin.presentations.destroy')
        <button type="button" class="btn btn-outline-danger btn-sm deletePresentation" data-id="{{ $presentation->id }}"
            data-toggle="tooltip" title="Eliminar presentacion">
            <i class="fas fa-trash"></i>
        </button>
    @endcan
</div>
