<div class="btn-group shadow-sm" role="group" aria-label="Actions">

    {{-- VIEW --}}
    <button type="button" class="btn btn-outline-info btn-sm viewMarketStudy" data-toggle="tooltip"
        title="Ver Estudio de Mercado" data-id="{{ $marketStudy->id }}">

        <i class="fas fa-eye"></i>

    </button>

    {{-- EDIT --}}
    <button type="button" class="btn btn-outline-primary btn-sm editMarketStudy" data-toggle="tooltip"
        title="Editar Estudio de Mercado" data-id="{{ $marketStudy->id }}" data-code="{{ $marketStudy->code }}"
        data-description="{{ $marketStudy->description }}" data-reference_terms="{{ $marketStudy->reference_terms }}"
        data-status="{{ $marketStudy->status }}">

        <i class="fas fa-pen"></i>

    </button>

    {{-- COTIZACIONES --}}
    <button type="button" class="btn btn-outline-warning btn-sm quoteMarketStudy" data-toggle="tooltip"
        title="Gestionar Cotizaciones" data-id="{{ $marketStudy->id }}" data-code="{{ $marketStudy->code }}"
        data-description="{{ $marketStudy->description }}">

        <i class="fas fa-file-invoice-dollar"></i>

    </button>

    {{-- DELETE --}}
    <button type="button" class="btn btn-outline-danger btn-sm deleteMarketStudy" data-id="{{ $marketStudy->id }}"
        data-toggle="tooltip" title="Eliminar Estudio de Mercado">

        <i class="fas fa-trash"></i>

    </button>

</div>
