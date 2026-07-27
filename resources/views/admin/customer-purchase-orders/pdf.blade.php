<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de compra {{ $order->code }}</title>
    <style>
        body{font-family:DejaVu Sans,sans-serif;color:#263238;font-size:10px}
        h1{margin:0;color:#174f8a;font-size:18px}.muted{color:#718096}
        .header{padding-bottom:12px;border-bottom:2px solid #2474bd}
        .grid{width:100%;margin-top:12px;border-collapse:collapse}.grid td{width:33%;padding:6px;border:1px solid #e2e8f0;vertical-align:top}
        small{display:block;color:#718096;font-size:8px;text-transform:uppercase}strong{display:block;margin-top:2px}
        table.items{width:100%;margin-top:14px;border-collapse:collapse}.items th,.items td{padding:6px;border:1px solid #d9e2ec}.items th{background:#edf5fb;text-align:left}
        .right{text-align:right}.manager{margin-top:10px;padding:8px;border-left:3px solid #2474bd;background:#f4f8fc}
    </style>
</head>
<body>
    <div class="header">
        <h1>Orden de Compra del Cliente</h1>
        <span class="muted">{{ $order->code }} · {{ $order->purchase_order_number }}</span>
    </div>
    <table class="grid">
        <tr>
            <td><small>Cliente</small><strong>{{ $order->customer?->business_name ?: $order->customer?->full_name }}</strong></td>
            <td><small>Empresa</small><strong>{{ $order->company?->trade_name ?: $order->company?->business_name }}</strong></td>
            <td><small>Registrado por</small><strong>{{ trim(($order->creator?->name ?? '').' '.($order->creator?->lastname ?? '')) }}</strong></td>
        </tr>
    </table>
    @if($order->seller_dni || $order->seller_full_name)
        <div class="manager">
            <small>Gestionado por</small>
            <strong>{{ $order->seller_dni ? 'DNI '.$order->seller_dni.' - ' : '' }}{{ $order->seller_full_name }}</strong>
            <span class="muted">{{ $order->seller_type === 'USER' ? 'Usuario del sistema' : 'Externo' }}</span>
        </div>
    @endif
    <table class="items">
        <thead><tr><th>Artículo / servicio</th><th class="right">Cantidad</th><th class="right">Precio unitario</th><th class="right">Total</th></tr></thead>
        <tbody>
        @foreach($order->items as $item)
            <tr>
                <td>{{ $item->billing_name_snapshot }}</td>
                <td class="right">{{ number_format((float)$item->quantity, 2) }}</td>
                <td class="right">{{ number_format((float)$item->unit_price, 2) }}</td>
                <td class="right">{{ number_format((float)$item->line_total, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="right"><strong>Total: {{ $order->currency?->symbol }} {{ number_format((float)$order->grand_total, 2) }}</strong></p>
</body>
</html>
