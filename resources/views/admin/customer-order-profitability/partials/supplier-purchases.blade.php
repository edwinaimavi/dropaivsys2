@php
    $withIgv = $mode === \App\Services\CustomerOrderProfitabilityService::MODE_WITH_IGV;
    $modeLabel = $withIgv ? 'Con IGV' : 'Sin IGV';
    $consideredColumn = $withIgv ? 'Total con IGV en soles' : 'Total sin IGV en soles';
@endphp

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
    Los importes mostrados respetan el modo de cálculo seleccionado: <strong>{{$modeLabel}}</strong>.
</div>
<div class="table-responsive cop-inner-table">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>OC proveedor</th>
                <th>Proveedor / artículo</th>
                <th>Moneda compra</th>
                <th>Moneda pago</th>
                <th class="text-right">TC</th>
                <th class="text-right">{{$consideredColumn}}</th>
                <th>Desglose en soles</th>
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
                    </td>
                    <td>{{$item->payment_currency_code ?: $item->purchase_currency_code ?: '-'}}</td>
                    <td class="text-right">{{(float)$item->pen_conversion_factor > 0 && $item->purchase_currency_code !== 'PEN' ? number_format((float)$item->pen_conversion_factor, 4) : '-'}}</td>
                    <td class="text-right"><strong class="cop-purchase-considered">S/ {{$money($item->considered_purchase_amount)}}</strong></td>
                    <td>
                        <span class="cop-purchase-breakdown"><small>Subtotal</small><strong>S/ {{$money($item->purchase_subtotal_pen)}}</strong></span>
                        <span class="cop-purchase-breakdown"><small>IGV</small><strong>S/ {{$money($item->purchase_igv_pen)}}</strong></span>
                        <span class="cop-purchase-breakdown"><small>Total</small><strong>S/ {{$money($item->purchase_total_pen)}}</strong></span>
                    </td>
                    <td><span class="cop-status-pill {{$supplierStatus['class']}}"><i class="fas {{$supplierStatus['icon']}}"></i>{{$supplierStatus['label']}}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="cop-empty"><i class="fas fa-shopping-cart"></i>Sin compras vinculadas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
