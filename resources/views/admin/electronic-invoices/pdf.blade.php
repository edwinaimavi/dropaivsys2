@php
    $formatMoney = fn ($value) => trim(($invoice->currency?->symbol ?? '') . ' ' . number_format((float) $value, 3));
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
    $title = $invoice->document_type === '03' ? 'BOLETA DE VENTA ELECTRONICA' : 'FACTURA ELECTRONICA';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->full_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 10px; margin: 0; }
        .header { border-bottom: 2px solid #0f766e; padding-bottom: 10px; margin-bottom: 12px; }
        .brand { width: 58%; display: inline-block; vertical-align: top; text-align: center; }
        .logo img { width: 210px; max-height: 70px; object-fit: contain; }
        .doc-box { width: 40%; display: inline-block; vertical-align: top; text-align: right; }
        .doc-number { display: inline-block; border: 1px solid #99f6e4; background: #f0fdfa; border-radius: 8px; padding: 10px 14px; min-width: 230px; text-align: center; }
        .doc-number .title { color: #0f766e; font-size: 14px; font-weight: 800; }
        .doc-number .number { color: #134e4a; font-size: 18px; font-weight: 900; margin-top: 4px; }
        .muted { color: #64748b; }
        .section-title { color: #0f766e; font-size: 10px; font-weight: 800; text-transform: uppercase; margin: 10px 0 5px; }
        table { width: 100%; border-collapse: collapse; }
        .info td { border: 1px solid #ccfbf1; padding: 5px 6px; vertical-align: top; }
        .label { color: #64748b; display: block; font-size: 8px; text-transform: uppercase; }
        .value { font-weight: 700; }
        .items th { background: #0f766e; color: #fff; border: 1px solid #0f766e; padding: 5px 3px; font-size: 8px; }
        .items td { border: 1px solid #ccfbf1; padding: 5px 3px; font-size: 8px; vertical-align: top; }
        .items tbody tr:nth-child(even) td { background: #f6fffd; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .totals { width: 38%; float: right; margin-top: 10px; }
        .totals td { border: 1px solid #ccfbf1; padding: 6px; }
        .totals .grand td { background: #ccfbf1; color: #134e4a; font-size: 13px; font-weight: 900; }
        .notes { width: 58%; display: inline-block; border: 1px solid #ccfbf1; min-height: 56px; padding: 8px; margin-top: 10px; }
        .footer { position: fixed; bottom: 0; left: 0; right: 0; border-top: 1px solid #e5e7eb; color: #64748b; font-size: 8px; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">
            @if (!empty($logoUrl))
                <div class="logo"><img src="{{ $logoUrl }}" alt="Logo"></div>
            @endif
            <div class="value">{{ $invoice->company_business_name }}</div>
            <div class="muted">RUC {{ $invoice->company_ruc }} | {{ $invoice->company_address }}</div>
        </div>
        <div class="doc-box">
            <div class="doc-number">
                <div class="title">{{ $title }}</div>
                <div class="number">{{ $invoice->full_number }}</div>
                <div class="muted">{{ $invoice->status === 'draft' ? 'BORRADOR - SIN VALIDEZ INTERNA' : 'GENERADO INTERNAMENTE' }}</div>
            </div>
        </div>
    </div>

    <div class="section-title">Cliente</div>
    <table class="info">
        <tr>
            <td width="42%"><span class="label">Raz&oacute;n social</span><span class="value">{{ $invoice->client_name }}</span></td>
            <td width="18%"><span class="label">Documento</span><span class="value">{{ $invoice->client_document_number }}</span></td>
            <td width="20%"><span class="label">Fecha emisi&oacute;n</span><span class="value">{{ $formatDate($invoice->issue_date) }}</span></td>
            <td width="20%"><span class="label">Forma pago</span><span class="value">{{ $invoice->payment_type }}</span></td>
        </tr>
        <tr>
            <td colspan="2"><span class="label">Direcci&oacute;n</span><span class="value">{{ $invoice->client_address ?: '-' }}</span></td>
            <td><span class="label">Moneda</span><span class="value">{{ $invoice->currency_code }}</span></td>
            <td><span class="label">Orden compra</span><span class="value">{{ $invoice->purchase_order_number ?: '-' }}</span></td>
        </tr>
    </table>

    <div class="section-title">Detalle</div>
    <table class="items">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="10%">Codigo</th>
                <th width="31%">Descripcion</th>
                <th width="9%">Lote</th>
                <th width="9%">F. venc.</th>
                <th width="9%">Marca</th>
                <th width="8%">Proced.</th>
                <th width="7%">Cant.</th>
                <th width="7%">Precio c/ IGV</th>
                <th width="6%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $item->product_code ?: '-' }}</td>
                    <td><strong>{{ $item->description }}</strong><br><span class="muted">{{ $item->presentation_name }}</span></td>
                    <td>{{ $item->lot_number ?: '-' }}</td>
                    <td class="text-center">{{ $formatDate($item->expiration_date) }}</td>
                    <td>{{ $item->brand_name ?: '-' }}</td>
                    <td>{{ $item->origin ?: '-' }}</td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 3) }}</td>
                    <td class="text-right">{{ $formatMoney($item->unit_price) }}</td>
                    <td class="text-right">{{ $formatMoney($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="notes">
        <span class="label">Monto en letras</span>
        <div class="value">{{ $invoice->total_text }}</div>
        <br>
        <span class="label">Observaci&oacute;n</span>
        <div>{{ $invoice->observations ?: '-' }}</div>
    </div>
    <table class="totals">
        <tr><td>Gravada</td><td class="text-right">{{ $formatMoney($invoice->taxable_amount) }}</td></tr>
        <tr><td>Exonerada</td><td class="text-right">{{ $formatMoney($invoice->exonerated_amount) }}</td></tr>
        <tr><td>Inafecta</td><td class="text-right">{{ $formatMoney($invoice->unaffected_amount) }}</td></tr>
        <tr><td>IGV</td><td class="text-right">{{ $formatMoney($invoice->igv_amount) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="text-right">{{ $formatMoney($invoice->total_amount) }}</td></tr>
    </table>

    <div class="footer">
        @if ($invoice->status === 'draft')
            Borrador interno generado por DropaivSys. No mueve stock y no ha sido enviado a SUNAT.
        @elseif ($invoice->sunat_status === 'not_configured')
            Comprobante interno no enviado a SUNAT. API no configurada.
        @else
            Comprobante interno no enviado a SUNAT. Pendiente de env&iacute;o.
        @endif
    </div>
</body>
</html>
