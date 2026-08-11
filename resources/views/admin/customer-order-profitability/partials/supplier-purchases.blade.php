<div class="cop-section-heading">
    <div>
        <span class="cop-eyebrow">Abastecimiento</span>
        <h6>Compras vinculadas a la orden</h6>
        <small>{{count($supplierOrderIds)}} orden(es) de compra a proveedor · importes normalizados a soles</small>
    </div>
    <div class="cop-section-total"><small>Compra considerada</small><strong>S/ {{$money($purchaseValue)}}</strong></div>
</div>
<div class="cop-purchase-mode-note">
    <i class="fas fa-info-circle"></i>
    La compra considerada respeta la condición IGV de cada OC proveedor y prioriza los importes registrados en Almacén.
</div>
<div class="table-responsive cop-inner-table">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>OC proveedor</th>
                <th>Proveedor / artículo</th>
                <th>Moneda compra</th>
                <th class="text-right">Subtotal</th>
                <th class="text-right">IGV</th>
                <th class="text-right">Total compra</th>
                <th class="text-right">Compra considerada</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($supplierItems as $item)
                @php($supplierStatus=\App\Models\CustomerPurchaseOrder::statusPresentation($item->order_status))
                <tr>
                    <td><strong>{{$item->order_code}}</strong></td>
                    <td><strong>{{$item->supplier_name}}</strong><small class="d-block text-muted">{{$item->billing_name_snapshot}} · Cant. {{$money($item->quantity)}}</small></td>
                    <td>
                        {{$item->purchase_currency_code ?: '-'}}
                        <small class="d-block text-muted">Total origen: {{$item->purchase_currency_symbol}} {{$money($item->line_total)}}</small>
                        @if($item->purchase_currency_code !== 'PEN' && (float)$item->pen_conversion_factor > 0)
                            <small class="d-block text-muted">TC: {{number_format((float)$item->pen_conversion_factor, 4)}}</small>
                        @endif
                    </td>
                    <td class="text-right text-nowrap">S/ {{$money($item->purchase_subtotal_pen)}}</td>
                    <td class="text-right text-nowrap">S/ {{$money($item->purchase_igv_pen)}}</td>
                    <td class="text-right text-nowrap">S/ {{$money($item->purchase_total_pen)}}</td>
                    <td class="text-right"><strong class="cop-purchase-considered">S/ {{$money($item->considered_purchase_amount)}}</strong></td>
                    <td>
                        <span class="cop-status-pill {{$supplierStatus['class']}}"><i class="fas {{$supplierStatus['icon']}}"></i>{{$supplierStatus['label']}}</span>
                        <small class="cop-purchase-source">{{$item->purchase_amount_source === 'warehouse_entry' ? 'Según Almacén' : 'Según OC proveedor'}}</small>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="cop-empty"><i class="fas fa-shopping-cart"></i>Sin compras vinculadas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
