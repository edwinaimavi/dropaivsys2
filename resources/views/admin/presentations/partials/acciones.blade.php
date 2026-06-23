<div class="btn-group shadow-sm" role="group" aria-label="Actions">

    {{-- VIEW --}}
    <button type="button" class="btn btn-outline-info btn-sm viewPresentation" data-toggle="tooltip"
        title="Ver Presentación" data-id="{{ $presentation->id }}" data-description="{{ $presentation->description }}"
        data-quantity="{{ $presentation->quantity }}" data-unit="{{ $presentation->unit->description ?? '—' }}"
        data-observation="{{ $presentation->observation }}" data-status="{{ $presentation->status }}"
        data-created_at="{{ $presentation->created_at ? $presentation->created_at->format('d/m/Y H:i') : '—' }}"
        data-updated_at="{{ $presentation->updated_at ? $presentation->updated_at->format('d/m/Y H:i') : '—' }}"
        data-created_by="{{ $presentation->creator->name ?? 'No registrado' }}"
        data-updated_by="{{ $presentation->editor->name ?? 'No registrado' }}"
        data-quantity="{{ $presentation->quantity }}" data-unit="{{ $presentation->unit }}">

        <i class="fas fa-eye"></i>

    </button>

    {{-- EDIT --}}
    <button type="button" class="btn btn-outline-warning btn-sm editPresentation" data-toggle="tooltip"
        title="Editar Presentación" data-id="{{ $presentation->id }}"
        data-description="{{ $presentation->description }}" data-quantity="{{ $presentation->quantity }}"
        data-unit_id="{{ $presentation->unit_id }}" data-observation="{{ $presentation->observation }}"
        data-status="{{ $presentation->status }}">

        <i class="fas fa-pen"></i>

    </button>

    {{-- DELETE --}}
    <button type="button" class="btn btn-outline-danger btn-sm deletePresentation" data-id="{{ $presentation->id }}"
        data-toggle="tooltip" title="Eliminar Presentación">

        <i class="fas fa-trash"></i>

    </button>

</div>
