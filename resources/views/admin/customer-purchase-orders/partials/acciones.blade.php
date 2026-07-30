<div class="customer-order-main-actions" role="group" aria-label="Acciones de la orden">
    @can('admin.customer-purchase-orders.show')
        <button type="button" class="btn btn-sm btn-success viewCustomerPurchaseOrder"
            data-id="{{ $order->id }}" title="Ver detalle de la orden">
            <i class="fas fa-eye mr-1" aria-hidden="true"></i> Ver
        </button>
    @endcan

    @canany(['admin.customer-purchase-orders.show', 'admin.customer-purchase-orders.update', 'admin.customer-purchase-orders.destroy'])
    <div class="dropdown customer-order-actions-dropdown">
        <button type="button"
            class="btn btn-sm btn-light border dropdown-toggle customer-order-actions-trigger"
            data-toggle="dropdown" data-boundary="window" data-display="static"
            aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-ellipsis-v mr-1" aria-hidden="true"></i> Acciones
        </button>

        <div class="dropdown-menu dropdown-menu-right customer-order-actions-menu">
            @can('admin.customer-purchase-orders.update')
                <h6 class="dropdown-header">Acciones operativas</h6>
                <button type="button" class="dropdown-item editCustomerPurchaseOrder"
                    data-id="{{ $order->id }}">
                    <i class="fas fa-pen text-primary" aria-hidden="true"></i> Editar orden
                </button>
            @endcan

            @can('admin.customer-purchase-orders.show')
                @can('admin.customer-purchase-orders.update')
                    <div class="dropdown-divider"></div>
                @endcan
                <h6 class="dropdown-header">Documentos</h6>
                <a href="{{ route('admin.customer-purchase-orders.pdf', $order) }}"
                    target="_blank" rel="noopener" class="dropdown-item">
                    <i class="fas fa-file-pdf text-danger" aria-hidden="true"></i> Ver PDF
                </a>
            @endcan

            @canany(['admin.customer-purchase-orders.update', 'admin.customer-purchase-orders.destroy'])
                <div class="dropdown-divider"></div>
                <h6 class="dropdown-header">Cierre / anulación</h6>

                @can('admin.customer-purchase-orders.update')
                    @if ($order->status === \App\Models\CustomerPurchaseOrder::STATUS_ENTERED)
                        <button type="button" class="dropdown-item closeCustomerPurchaseOrderAttention"
                            data-id="{{ $order->id }}" data-code="{{ $order->code }}">
                            <i class="fas fa-clipboard-check text-success" aria-hidden="true"></i>
                            Cerrar atención
                        </button>
                    @endif
                @endcan

                @can('admin.customer-purchase-orders.destroy')
                    <button type="button" class="dropdown-item text-danger deleteCustomerPurchaseOrder"
                        data-id="{{ $order->id }}">
                        <i class="fas fa-trash" aria-hidden="true"></i> Eliminar orden
                    </button>
                @endcan
            @endcanany
        </div>
    </div>
    @endcanany
</div>
