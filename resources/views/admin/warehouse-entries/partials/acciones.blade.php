<div class="btn-group" role="group">
    @can('admin.warehouse-entries.show')
    <button type="button" class="btn btn-sm btn-outline-info viewWarehouseEntry" data-id="{{ $entry->id }}"
        title="Ver detalle">
        <i class="fas fa-eye"></i>
    </button>
    @endcan
    @can('admin.warehouse-entries.update')
    <button type="button" class="btn btn-sm btn-outline-primary editWarehouseEntry" data-id="{{ $entry->id }}"
        title="Editar">
        <i class="fas fa-edit"></i>
    </button>
    @endcan
    @can('admin.warehouse-entries.destroy')
    <button type="button" class="btn btn-sm btn-outline-danger deleteWarehouseEntry" data-id="{{ $entry->id }}"
        title="Eliminar">
        <i class="fas fa-trash-alt"></i>
    </button>
    @endcan
</div>
