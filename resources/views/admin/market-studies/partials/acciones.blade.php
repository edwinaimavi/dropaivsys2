<x-table-actions-dropdown label="Acciones del estudio de mercado">
    <x-slot name="main">
        <button type="button" class="btn btn-sm btn-success dp-action-main viewMarketStudy"
            data-id="{{ $marketStudy->id }}" title="Ver estudio">
            <i class="fas fa-eye mr-1"></i> Ver
        </button>
    </x-slot>
    <x-slot name="menu">
        <h6 class="dropdown-header">Acciones operativas</h6>
        <button type="button" class="dropdown-item editMarketStudy" data-id="{{ $marketStudy->id }}"
            data-code="{{ $marketStudy->code }}" data-description="{{ $marketStudy->description }}"
            data-reference_terms="{{ $marketStudy->reference_terms }}" data-status="{{ $marketStudy->status ? 1 : 0 }}">
            <i class="fas fa-pen text-primary"></i> Editar estudio
        </button>
        <button type="button" class="dropdown-item manageQuotes" data-id="{{ $marketStudy->id }}"
            data-code="{{ $marketStudy->code }}" data-description="{{ $marketStudy->description }}">
            <i class="fas fa-file-invoice-dollar text-warning"></i> Gestionar cotizaciones
        </button>
        <button type="button" class="dropdown-item compareQuotes" data-id="{{ $marketStudy->id }}"
            data-code="{{ $marketStudy->code }}" data-description="{{ $marketStudy->description }}">
            <i class="fas fa-balance-scale text-success"></i> Comparativo de cotizaciones
        </button>
        <div class="dropdown-divider"></div>
        <h6 class="dropdown-header">Cierre / anulación</h6>
        <button type="button" class="dropdown-item text-danger deleteMarketStudy" data-id="{{ $marketStudy->id }}">
            <i class="fas fa-trash"></i> Eliminar estudio
        </button>
    </x-slot>
</x-table-actions-dropdown>
