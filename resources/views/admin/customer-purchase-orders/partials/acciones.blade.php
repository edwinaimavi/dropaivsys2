<x-table-actions-dropdown label="Acciones de la orden">
    @php
        $billingSummary = $order->billing_summary ?? [];
        $billingStatus = $billingSummary['billing_status'] ?? 'unbilled';
        $billingInvoices = collect($billingSummary['invoices'] ?? []);
        $collectibleInvoice = $billingInvoices->first(fn ($invoice) => (float) $invoice->pending_amount > 0);
        $canInvoiceOrder = in_array($order->status, ['partial_entered', 'entered', 'attended', 'delivered'], true)
            && $billingStatus !== 'fully_invoiced';
    @endphp
    <x-slot name="main">
        @can('admin.customer-purchase-orders.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main viewCustomerPurchaseOrder"
                data-id="{{ $order->id }}" title="Ver detalle de la orden">
                <i class="fas fa-eye mr-1" aria-hidden="true"></i> Ver
            </button>
        @endcan
    </x-slot>

    <x-slot name="menu">
        @canany(['admin.customer-purchase-orders.invoice', 'admin.customer-purchase-orders.invoices.index', 'admin.electronic-invoices.collect'])
            <h6 class="dropdown-header">Facturaci&oacute;n y cobros</h6>
            @can('admin.customer-purchase-orders.invoice')
                @if ($canInvoiceOrder)
                    <a href="{{ route('admin.electronic-invoices.index', ['customer_purchase_order_id' => $order->id]) }}"
                        class="dropdown-item">
                        <i class="fas fa-file-invoice-dollar text-success" aria-hidden="true"></i>
                        {{ $billingStatus === 'partially_invoiced' ? 'Facturar saldo' : 'Facturar' }}
                    </a>
                @endif
            @endcan
            @can('admin.customer-purchase-orders.invoices.index')
                @if ($billingInvoices->isNotEmpty())
                    <a href="{{ route('admin.electronic-invoices.index', ['invoice_order_id' => $order->id]) }}"
                        class="dropdown-item">
                        <i class="fas fa-list-alt text-primary" aria-hidden="true"></i> Ver facturas
                    </a>
                @endif
            @endcan
            @if (auth()->user()->can('admin.electronic-invoices.collect') && auth()->user()->can('admin.invoice-collections.store'))
                @if ($collectibleInvoice)
                    <a href="{{ route('admin.electronic-invoices.index', ['collect_invoice_id' => $collectibleInvoice->id]) }}"
                        class="dropdown-item">
                        <i class="fas fa-university text-info" aria-hidden="true"></i>
                        {{ (float) $collectibleInvoice->paid_amount > 0 ? 'Registrar otro cobro' : 'Confirmar ingreso' }}
                    </a>
                @endif
            @endif
            <div class="dropdown-divider"></div>
        @endcanany

        @can('admin.customer-purchase-orders.update')
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editCustomerPurchaseOrder" data-id="{{ $order->id }}">
                <i class="fas fa-pen text-primary" aria-hidden="true"></i> Editar orden
            </button>
        @endcan

        @can('admin.customer-purchase-orders.show')
            @can('admin.customer-purchase-orders.update')
                <div class="dropdown-divider"></div>
            @endcan
            <h6 class="dropdown-header">Documentos</h6>
            <a href="{{ route('admin.customer-purchase-orders.pdf', $order) }}" target="_blank" rel="noopener"
                class="dropdown-item">
                <i class="fas fa-file-pdf text-danger" aria-hidden="true"></i> Ver PDF
            </a>
        @endcan

        @canany(['admin.customer-purchase-orders.update', 'admin.customer-purchase-orders.destroy'])
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            @can('admin.customer-purchase-orders.update')
                @if ($order->status === \App\Models\CustomerPurchaseOrder::STATUS_ENTERED && !$order->attention_closed_at)
                    <button type="button" class="dropdown-item closeCustomerPurchaseOrderAttention"
                        data-id="{{ $order->id }}" data-code="{{ $order->code }}">
                        <i class="fas fa-clipboard-check text-success" aria-hidden="true"></i> Cerrar atención
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
    </x-slot>
</x-table-actions-dropdown>
