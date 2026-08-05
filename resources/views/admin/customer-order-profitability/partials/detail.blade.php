@php
    $money = fn ($value) => number_format((float) $value, 2);
    $customerName = $order->customer?->business_name ?: $order->customer?->full_name;
    $companyName = $order->company?->trade_name ?: $order->company?->business_name;
    $profitLevel = $percentage < 0 ? 'negative' : ($percentage <= 5 ? 'low' : ($percentage <= 15 ? 'medium' : 'high'));
    $profitLabel = ['negative' => 'Negativa', 'low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta'][$profitLevel];
    $freightCosts = $operationalTransportCosts;
    $otherCosts = $otherOrUnsupportedCosts;
    $costTypeLabel = function ($cost) {
        $type = strtolower((string) ($cost->expense_type ?? ''));
        if (in_array($type, ['agency_freight', 'transport_agency', 'courier', 'shipping'])) return 'Flete de agencia';
        if (in_array($type, ['pickup_transfer', 'agency_pickup_to_warehouse', 'agency_direct_to_warehouse', 'supplier_warehouse_pickup', 'transfer_to_agency', 'truck', 'mobility', 'delivery', 'transfer', 'flete', 'transporte', 'movilidad'])) return 'Recojo / traslado';
        return 'Otros gastos';
    };
@endphp

<div class="cop-tabs">
    <ul class="nav nav-pills cop-modal-nav" role="tablist">
        @foreach([
            'summary' => ['Resumen', 'fa-th-large'],
            'sale' => ['Venta al cliente', 'fa-file-invoice-dollar'],
            'purchases' => ['Compras a proveedor', 'fa-truck-loading'],
            'costs' => ['Costos vinculados', 'fa-wallet'],
            'taxes' => ['IGV e impuestos', 'fa-calculator'],
            'profit' => ['Rentabilidad', 'fa-chart-line'],
        ] as $key => [$label, $icon])
            <li class="nav-item"><a class="nav-link {{$loop->first ? 'active' : ''}}" data-toggle="pill" href="#cop_{{$key}}"><i class="fas {{$icon}}"></i><span>{{$label}}</span></a></li>
        @endforeach
    </ul>
</div>

<div class="tab-content cop-tab-content">
    <div id="cop_summary" class="tab-pane fade show active">
        <div class="cop-section-heading"><div><span class="cop-eyebrow">Vista ejecutiva</span><h6>Resumen financiero de la orden</h6></div><span class="cop-order-chip"><i class="fas fa-file-signature"></i>{{$order->purchase_order_number ?: $order->code}}</span></div>
        <div class="row cop-metrics-grid">
            @foreach([
                ['Total venta', $saleTotal, 'sale', 'fa-coins', 'sale'],
                ['Total compra', $purchaseTotal, 'purchase', 'fa-shopping-cart', 'purchases'],
                ['Utilidad bruta', $gross, 'gross', 'fa-chart-bar', 'profit'],
                ['Flete / recojo / traslado con comprobante', $freightBase, 'freight', 'fa-truck', 'costs'],
                ['Otros gastos / sin comprobante', $otherTotal, 'other', 'fa-receipt', 'costs'],
                ['Utilidad operativa', $operating, 'operating', 'fa-wave-square', 'profit'],
                ['Impuesto renta', $incomeTax, 'tax', 'fa-percentage', 'taxes'],
                ['Utilidad neta', $net, $net < 0 ? 'negative' : 'net', 'fa-hand-holding-usd', 'profit'],
            ] as [$label, $value, $tone, $icon, $tabTarget])
                <div class="col-6 col-xl-3 mb-3"><div class="cop-metric cop-metric-{{$tone}} cop-summary-tab-link" data-tab-target="{{$tabTarget}}" role="button" tabindex="0" title="Ver detalle: {{$label}}" aria-label="Ver detalle de {{$label}}"><span class="cop-metric-icon"><i class="fas {{$icon}}"></i></span><div><small>{{$label}}</small><strong>{{$order->currency?->symbol ?: 'S/'}} {{$money($value)}}</strong></div><span class="cop-summary-link-cue"><i class="fas fa-arrow-right"></i></span></div></div>
            @endforeach
        </div>

        <div class="row justify-content-center"><div class="col-xl-6 mb-4"><div class="cop-profit-hero cop-profit-{{$profitLevel}} cop-summary-tab-link" data-tab-target="profit" role="button" tabindex="0" title="Ver detalle de rentabilidad" aria-label="Ver detalle de rentabilidad de la orden"><div><span>Rentabilidad de la orden</span><strong>{{$money($percentage)}}%</strong></div><span class="cop-profit-level"><i class="fas fa-signal"></i>{{$profitLabel}}</span><span class="cop-summary-link-cue"><i class="fas fa-arrow-right"></i></span></div></div></div>

        <div class="cop-formula-panel">
            <div class="cop-formula-row"><span class="formula-value"><small>Utilidad bruta</small><strong>{{$money($gross)}}</strong></span><i class="fas fa-minus formula-operator"></i><span class="formula-value"><small>Transporte con comprobante</small><strong>{{$money($freightBase)}}</strong></span><i class="fas fa-equals formula-operator"></i><span class="formula-result"><small>Utilidad operativa</small><strong>{{$money($operating)}}</strong></span></div>
            <div class="cop-formula-divider"></div>
            <div class="cop-formula-row"><span class="formula-value"><small>Utilidad operativa</small><strong>{{$money($operating)}}</strong></span><i class="fas fa-minus formula-operator"></i><span class="formula-value"><small>Renta + otros / sin comprobante</small><strong>{{$money($incomeTax + $otherTotal)}}</strong></span><i class="fas fa-equals formula-operator"></i><span class="formula-result formula-net"><small>Utilidad neta</small><strong>{{$money($net)}}</strong></span></div>
        </div>
        <div class="cop-classification-note"><i class="fas fa-info-circle"></i>Los costos sin factura o boleta se consideran otros gastos para el cálculo de rentabilidad.</div>

        @foreach($warnings as $warning)<div class="alert alert-warning cop-alert"><i class="fas fa-exclamation-triangle mr-2"></i>{{$warning}}</div>@endforeach
        <div class="cop-calculation-meta"><span><i class="fas fa-sliders-h"></i>Modo: <strong>{{$mode === 'with_igv' ? 'Con IGV' : 'Sin IGV'}}</strong></span><span><i class="fas fa-percent"></i>IGV: <strong>{{$igvRate}}%</strong></span><span><i class="fas fa-landmark"></i>Renta: <strong>{{$incomeTaxRate}}%</strong></span><span><i class="far fa-clock"></i>Calculado: <strong>{{now()->format('d/m/Y H:i')}}</strong></span></div>
    </div>

    <div id="cop_sale" class="tab-pane fade">
        <div class="cop-section-heading"><div><span class="cop-eyebrow">Venta al cliente</span><h6>{{$order->purchase_order_number ?: $order->code}} · {{$customerName}}</h6><small>{{$companyName}}</small></div><div class="d-flex align-items-center"><button type="button" class="btn btn-outline-info btn-sm mr-2 cop-view-order-documents" data-documents="{{e($orderDocuments->toJson())}}"><i class="fas fa-folder-open mr-1"></i>Ver documentos ({{$orderDocuments->count()}})</button><div class="cop-section-total"><small>Total venta</small><strong>{{$money($saleTotal)}}</strong></div></div></div>
        <div class="row cop-mini-summary"><div class="col-md-4"><span>Base / subtotal<strong>{{$money($saleBase)}}</strong></span></div><div class="col-md-4"><span>IGV venta<strong>{{$money($saleIgv)}}</strong></span></div><div class="col-md-4"><span>Total venta<strong>{{$money($saleTotal)}}</strong></span></div></div>
        <div class="table-responsive cop-inner-table"><table class="table table-hover"><thead><tr><th>Artículo</th><th class="text-right">Cantidad</th><th class="text-right">Precio venta</th><th class="text-right">Total</th><th class="text-right">En compra</th><th class="text-right">Ingresada</th><th class="text-right">Pendiente</th></tr></thead><tbody>@foreach($order->items->where('status', '!=', 'deleted') as $item)@php($p=(float)($purchasedByItem[$item->id]??0))@php($e=(float)($enteredByCustomerItem[$item->id]??0))<tr><td><strong>{{$item->billing_name_snapshot}}</strong></td><td class="text-right">{{$money($item->quantity)}}</td><td class="text-right">{{$money($item->unit_price)}}</td><td class="text-right font-weight-bold">{{$money($item->line_total)}}</td><td class="text-right">{{$money($p)}}</td><td class="text-right">{{$money($e)}}</td><td class="text-right">{{$money(max((float)$item->quantity-$e,0))}}</td></tr>@endforeach</tbody></table></div>
    </div>

    <div id="cop_purchases" class="tab-pane fade">
        <div class="cop-section-heading"><div><span class="cop-eyebrow">Abastecimiento</span><h6>Compras vinculadas a la orden</h6><small>{{count($supplierOrderIds)}} orden(es) de compra a proveedor</small></div><div class="cop-section-total"><small>Total compra</small><strong>{{$money($purchaseTotal)}}</strong></div></div>
        <div class="table-responsive cop-inner-table"><table class="table table-hover"><thead><tr><th>OC proveedor</th><th>Proveedor</th><th>Artículo</th><th class="text-right">Cantidad</th><th class="text-right">Precio</th><th class="text-right">Total</th><th>Estado</th></tr></thead><tbody>@forelse($supplierItems as $item)@php($supplierStatus=\App\Models\CustomerPurchaseOrder::statusPresentation($item->order_status))<tr><td><strong>{{$item->order_code}}</strong></td><td>{{$item->supplier_name}}</td><td>{{$item->billing_name_snapshot}}</td><td class="text-right">{{$money($item->quantity)}}</td><td class="text-right">{{$money($item->unit_price)}}</td><td class="text-right font-weight-bold">{{$money($item->line_total)}}</td><td><span class="cop-status-pill {{$supplierStatus['class']}}"><i class="fas {{$supplierStatus['icon']}}"></i>{{$supplierStatus['label']}}</span></td></tr>@empty<tr><td colspan="7" class="cop-empty"><i class="fas fa-shopping-cart"></i>Sin compras vinculadas.</td></tr>@endforelse</tbody></table></div>
    </div>

    <div id="cop_costs" class="tab-pane fade">
        <div class="cop-section-heading"><div><span class="cop-eyebrow">Costos vinculados</span><h6>Detalle de transporte y otros gastos</h6><small>Los costos de transporte afectan la utilidad operativa; otros gastos se descuentan de la utilidad neta.</small></div><div class="cop-section-total"><small>Total vinculado</small><strong>{{$money($linkedTotal)}}</strong></div></div>
        @foreach([['Flete / recojo / traslado con comprobante', $freightCosts, $freightTotal, 'fa-truck', 'operating'], ['Otros gastos / sin comprobante', $otherCosts, $otherTotal, 'fa-receipt', 'net']] as [$title, $rows, $subtotal, $icon, $impact])
            <div class="cop-cost-block"><div class="cop-cost-block-header"><div><span class="cop-cost-icon"><i class="fas {{$icon}}"></i></span><div><h6>{{$title}}</h6><small>{{$impact === 'operating' ? 'Se descuenta antes del impuesto a la renta.' : 'Se descuenta después del impuesto a la renta.'}}</small></div></div><span class="cop-cost-subtotal">Subtotal <strong>{{$money($subtotal)}}</strong></span></div>
            <div class="table-responsive cop-inner-table"><table class="table table-hover mb-0"><thead><tr><th>Tipo</th><th>Responsable</th><th>Documento</th><th>Clasificación para rentabilidad</th><th>Fecha</th><th class="text-right">Importe</th><th>Adjunto</th><th>Observación</th></tr></thead><tbody>@forelse($rows as $cost)<tr><td><strong>{{$costTypeLabel($cost)}}</strong></td><td>{{$cost->provider_name ?: '-'}}</td><td>{{collect([$cost->document_type,$cost->document_series,$cost->document_number])->filter()->join(' ') ?: 'Sin comprobante'}}</td><td><span class="cop-classification-badge {{$impact === 'operating' ? 'is-operational' : 'is-other'}}"><i class="fas {{$impact === 'operating' ? 'fa-check-circle' : 'fa-exclamation-circle'}}"></i>{{$impact === 'operating' ? 'Operativo con comprobante' : 'Otros gastos / sin comprobante'}}</span></td><td>{{$cost->expense_date?->format('d/m/Y') ?: '-'}}</td><td class="text-right font-weight-bold">{{$money($cost->amount)}}</td><td>{{$cost->documents->isNotEmpty() ? 'Sí' : 'No'}}</td><td>{{$cost->description ?: '-'}}</td></tr>@empty<tr><td colspan="8" class="cop-empty"><i class="fas {{$icon}}"></i>Sin registros en esta categoría.</td></tr>@endforelse</tbody></table></div></div>
        @endforeach
    </div>

    <div id="cop_taxes" class="tab-pane fade">
        <div class="cop-section-heading"><div><span class="cop-eyebrow">Tributos</span><h6>IGV e impuesto a la renta</h6><small>Resumen tributario estimado de la operación.</small></div><span class="cop-order-chip"><i class="fas fa-calculator"></i>{{$mode === 'with_igv' ? 'Con IGV' : 'Sin IGV'}}</span></div>
        @if($mode !== 'with_igv')<div class="cop-mode-notice"><i class="fas fa-info-circle"></i><div><strong>Análisis sin IGV</strong><span>Cambia el modo de cálculo a “Con IGV” para visualizar débito y crédito fiscal.</span></div></div>@else<div class="row cop-tax-grid"><div class="col-md-4"><div><small>Base de venta</small><strong>{{$money($saleBase)}}</strong></div></div><div class="col-md-4"><div><small>IGV venta</small><strong>{{$money($saleIgv)}}</strong></div></div><div class="col-md-4"><div><small>Débito fiscal</small><strong>{{$money($igvSales)}}</strong></div></div><div class="col-md-4"><div><small>Base de compra</small><strong>{{$money($purchaseBase)}}</strong></div></div><div class="col-md-4"><div><small>IGV compra + fletes</small><strong>{{$money($igvPurchases)}}</strong></div></div><div class="col-md-4"><div class="cop-tax-highlight"><small>IGV estimado por pagar</small><strong>{{$money($igvDifference)}}</strong></div></div></div>@endif
        <div class="cop-income-tax"><span><i class="fas fa-landmark"></i>Impuesto a la renta estimado</span><strong>{{$money($incomeTax)}}</strong><small>{{$incomeTaxRate}}% de la utilidad operativa positiva</small></div>
    </div>

    <div id="cop_profit" class="tab-pane fade">
        <div class="cop-section-heading"><div><span class="cop-eyebrow">Resultado final</span><h6>Indicadores ejecutivos de rentabilidad</h6><small>Lectura consolidada del rendimiento económico de la orden.</small></div><span class="cop-profit-level cop-profit-{{$profitLevel}}"><i class="fas fa-signal"></i>Rentabilidad {{$profitLabel}}</span></div>
        <div class="row cop-executive-results"><div class="col-md-4"><div><small>Utilidad bruta</small><strong>{{$money($gross)}}</strong><span>Venta menos compra</span></div></div><div class="col-md-4"><div><small>Utilidad operativa</small><strong>{{$money($operating)}}</strong><span>Después de transporte con comprobante</span></div></div><div class="col-md-4"><div class="cop-executive-net"><small>Utilidad neta</small><strong>{{$money($net)}}</strong><span>Después de renta y otros / sin comprobante</span></div></div></div>
        <div class="cop-profit-gauge"><div class="cop-profit-gauge-head"><span>Indicador de rentabilidad</span><strong>{{$money($percentage)}}%</strong></div><div class="progress"><div class="progress-bar cop-gauge-{{$profitLevel}}" style="width: {{max(2,min(abs($percentage),100))}}%"></div></div><div class="cop-gauge-scale"><span>Baja</span><span>Media</span><span>Alta</span></div></div>
        <div class="cop-statement"><div><span>{{$mode==='with_igv'?'Base venta':'Total venta'}}</span><strong>{{$money($saleBase)}}</strong></div><div><span>- {{$mode==='with_igv'?'Base compra':'Total compra'}}</span><strong>{{$money($purchaseBase)}}</strong></div><div class="total"><span>= Utilidad bruta</span><strong>{{$money($gross)}}</strong></div><div><span>- Flete / recojo / traslado con comprobante</span><strong>{{$money($freightBase)}}</strong></div><div class="total"><span>= Utilidad operativa</span><strong>{{$money($operating)}}</strong></div><div><span>- Impuesto renta estimado</span><strong>{{$money($incomeTax)}}</strong></div><div><span>- Otros gastos / sin comprobante</span><strong>{{$money($otherTotal)}}</strong></div><div class="total cop-final-total"><span>= Utilidad neta</span><strong>{{$money($net)}}</strong></div></div>
    </div>
</div>
