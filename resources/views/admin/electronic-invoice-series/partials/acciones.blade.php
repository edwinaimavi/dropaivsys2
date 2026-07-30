<x-table-actions-dropdown label="Acciones de la serie electrónica">
    <x-slot name="main">
        @can('admin.electronic-invoice-series.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewElectronicInvoiceSeries"
                data-id="{{ $series->id }}" title="Ver serie">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @can('admin.electronic-invoice-series.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editElectronicInvoiceSeries" data-id="{{ $series->id }}">
                <i class="fas fa-edit text-primary"></i> Editar serie
            </button>
        @endcan
        @can('admin.electronic-invoice-series.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteElectronicInvoiceSeries"
                data-id="{{ $series->id }}">
                <i class="fas fa-trash"></i> Eliminar serie
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
