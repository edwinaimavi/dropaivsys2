<x-table-actions-dropdown label="Acciones de la orden al proveedor">
    <x-slot name="main">
        @can('admin.supplier-purchase-orders.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewSupplierPurchaseOrder"
                data-id="{{ $order->id }}" title="Ver detalle">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @canany(['admin.supplier-purchase-orders.trackings.index', 'admin.supplier-purchase-orders.update'])
            <h6 class="dropdown-header">Acciones operativas</h6>
            @can('admin.supplier-purchase-orders.trackings.index')
                <button type="button" class="dropdown-item trackingSupplierPurchaseOrder" data-id="{{ $order->id }}">
                    <i class="fas fa-route text-success"></i> Seguimiento logístico
                </button>
            @endcan
            @can('admin.supplier-purchase-orders.update')
                <button type="button" class="dropdown-item editSupplierPurchaseOrder" data-id="{{ $order->id }}">
                    <i class="fas fa-pen text-primary"></i> Editar orden
                </button>
            @endcan
        @endcanany
        @can('admin.supplier-purchase-orders.pdf')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Documentos</h6>
            @if (!empty($pdfUrl))
                <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="dropdown-item">
                    <i class="fas fa-file-pdf text-danger"></i> Ver PDF
                </a>
            @else
                <button type="button" class="dropdown-item" disabled>
                    <i class="fas fa-file-pdf text-muted"></i> PDF no generado
                </button>
            @endif
        @endcan
        @can('admin.supplier-purchase-orders.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteSupplierPurchaseOrder"
                data-id="{{ $order->id }}">
                <i class="fas fa-trash"></i> Eliminar orden
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
