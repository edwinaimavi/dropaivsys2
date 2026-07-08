<div class="btn-group shadow-sm" role="group" aria-label="Acciones">
    @can('admin.shipping-agencies.show')
        <button type="button" class="btn btn-outline-info btn-sm viewShippingAgency"
            data-id="{{ $agency->id }}" data-toggle="tooltip" title="Ver detalle">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.shipping-agencies.update')
        <button type="button" class="btn btn-outline-warning btn-sm editShippingAgency"
            data-id="{{ $agency->id }}" data-toggle="tooltip" title="Editar agencia">
            <i class="fas fa-pen"></i>
        </button>
    @endcan

    @can('admin.shipping-agencies.destroy')
        <button type="button" class="btn btn-outline-danger btn-sm deleteShippingAgency"
            data-id="{{ $agency->id }}" data-toggle="tooltip" title="Eliminar agencia">
            <i class="fas fa-trash"></i>
        </button>
    @endcan
</div>
