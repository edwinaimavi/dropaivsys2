<x-table-actions-dropdown label="Acciones del ingreso de almacén">
    <x-slot name="main">
        @can('admin.warehouse-entries.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewWarehouseEntry"
                data-id="{{ $entry->id }}" title="Ver detalle">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @can('admin.warehouse-entries.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editWarehouseEntry" data-id="{{ $entry->id }}">
                <i class="fas fa-edit text-primary"></i> Editar ingreso
            </button>
        @endcan
        @can('admin.warehouse-entries.pdf')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Documentos</h6>
            <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="dropdown-item">
                <i class="fas fa-file-pdf text-danger"></i> Ver PDF
            </a>
        @endcan
        @can('admin.warehouse-entries.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteWarehouseEntry" data-id="{{ $entry->id }}">
                <i class="fas fa-trash-alt"></i> Eliminar ingreso
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
