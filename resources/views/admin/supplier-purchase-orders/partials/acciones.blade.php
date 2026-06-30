<div class="btn-group" role="group">
    @if (!empty($pdfUrl))
        <a href="{{ $pdfUrl }}" target="_blank" rel="noopener"
            class="btn btn-outline-success btn-sm mr-2" title="Ver PDF" data-toggle="tooltip">
            <i class="fas fa-file-pdf"></i>
        </a>
    @else
        <button type="button" class="btn btn-outline-secondary btn-sm mr-2"" title="PDF no generado"
            data-toggle="tooltip" disabled>
            <i class="fas fa-file-pdf"></i>
        </button>
    @endif

    <button type="button" class="btn btn-outline-info btn-sm viewSupplierPurchaseOrder mr-2"
        data-id="{{ $order->id }}" title="Ver detalle" data-toggle="tooltip">
        <i class="fas fa-eye"></i>
    </button>
    <button type="button" class="btn btn-outline-primary btn-sm editSupplierPurchaseOrder mr-2"
        data-id="{{ $order->id }}" title="Editar" data-toggle="tooltip">
        <i class="fas fa-pen"></i>
    </button>
    <button type="button" class="btn btn-outline-danger btn-sm deleteSupplierPurchaseOrder mr-2"
        data-id="{{ $order->id }}" title="Eliminar" data-toggle="tooltip">
        <i class="fas fa-trash"></i>
    </button>
</div>
