<div class="btn-group shadow-sm" role="group" aria-label="Acciones">
    <button type="button" class="btn btn-outline-info btn-sm viewCustomerPurchaseOrder"
        data-id="{{ $order->id }}" data-toggle="tooltip" title="Ver orden">
        <i class="fas fa-eye"></i>
    </button>

    <button type="button" class="btn btn-outline-primary btn-sm editCustomerPurchaseOrder"
        data-id="{{ $order->id }}" data-toggle="tooltip" title="Editar orden">
        <i class="fas fa-pen"></i>
    </button>

    <button type="button" class="btn btn-outline-danger btn-sm deleteCustomerPurchaseOrder"
        data-id="{{ $order->id }}" data-toggle="tooltip" title="Eliminar orden">
        <i class="fas fa-trash"></i>
    </button>
</div>
