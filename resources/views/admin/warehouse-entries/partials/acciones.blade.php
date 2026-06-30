<div class="btn-group" role="group">
    <button type="button" class="btn btn-sm btn-outline-info viewWarehouseEntry" data-id="{{ $entry->id }}"
        title="Ver detalle">
        <i class="fas fa-eye"></i>
    </button>
    <button type="button" class="btn btn-sm btn-outline-primary editWarehouseEntry" data-id="{{ $entry->id }}"
        title="Editar">
        <i class="fas fa-edit"></i>
    </button>
    <button type="button" class="btn btn-sm btn-outline-danger deleteWarehouseEntry" data-id="{{ $entry->id }}"
        title="Eliminar">
        <i class="fas fa-trash-alt"></i>
    </button>
</div>
