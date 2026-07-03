<div class="btn-group" role="group">
    @can('admin.warehouse-entries.show')
    <button type="button" class="btn btn-sm btn-outline-info viewWarehouseEntry mr-2" data-id="{{ $entry->id }}"
        title="Ver detalle">
        <i class="fas fa-eye"></i>
    </button>
    @endcan
    @can('admin.warehouse-entries.pdf')
    <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success mr-2"
        title="Ver PDF">
        <i class="fas fa-file-pdf"></i>
    </a>
    @endcan
    @can('admin.warehouse-entries.update')
    <button type="button" class="btn btn-sm btn-outline-primary editWarehouseEntry mr-2" data-id="{{ $entry->id }}"
        title="Editar">
        <i class="fas fa-edit"></i>
    </button>
    @endcan
    @can('admin.warehouse-entries.destroy')
    <button type="button" class="btn btn-sm btn-outline-danger deleteWarehouseEntry mr-2" data-id="{{ $entry->id }}"
        title="Eliminar">
        <i class="fas fa-trash-alt"></i>
    </button>
    @endcan
</div>
