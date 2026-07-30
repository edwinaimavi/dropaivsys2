<x-table-actions-dropdown label="Acciones de la agencia">
    <x-slot name="main">
        @can('admin.shipping-agencies.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewShippingAgency"
                data-id="{{ $agency->id }}" title="Ver detalle">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @can('admin.shipping-agencies.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editShippingAgency" data-id="{{ $agency->id }}">
                <i class="fas fa-pen text-primary"></i> Editar agencia
            </button>
        @endcan
        @can('admin.shipping-agencies.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteShippingAgency" data-id="{{ $agency->id }}">
                <i class="fas fa-trash"></i> Eliminar agencia
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
