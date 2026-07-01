<div class="btn-group shadow-sm" role="group" aria-label="Acciones">
    @can('admin.units.show')
        <button type="button" class="btn btn-outline-info btn-sm viewUnit" data-toggle="tooltip" title="Ver unidad"
            data-id="{{ $unit->id }}" data-abbreviation="{{ $unit->abbreviation }}"
            data-description="{{ $unit->description }}" data-decimal_quantity="{{ $unit->decimal_quantity }}"
            data-observation="{{ $unit->observation }}" data-status="{{ $unit->status }}"
            data-created_at="{{ $unit->created_at ? $unit->created_at->format('d/m/Y H:i') : '-' }}"
            data-updated_at="{{ $unit->updated_at ? $unit->updated_at->format('d/m/Y H:i') : '-' }}"
            data-created_by="{{ $unit->creator->name ?? 'No registrado' }}"
            data-updated_by="{{ $unit->editor->name ?? 'No registrado' }}">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.units.update')
        <button type="button" class="btn btn-outline-primary btn-sm editUnit" data-toggle="tooltip" title="Editar unidad"
            data-id="{{ $unit->id }}" data-abbreviation="{{ $unit->abbreviation }}"
            data-description="{{ $unit->description }}" data-decimal_quantity="{{ $unit->decimal_quantity }}"
            data-observation="{{ $unit->observation }}" data-status="{{ $unit->status }}">
            <i class="fas fa-pen"></i>
        </button>
    @endcan

    @can('admin.units.destroy')
        <button type="button" class="btn btn-outline-danger btn-sm deleteUnit" data-id="{{ $unit->id }}"
            data-toggle="tooltip" title="Eliminar unidad">
            <i class="fas fa-trash"></i>
        </button>
    @endcan
</div>
