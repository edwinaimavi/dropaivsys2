@php
    $currencySymbol = $order->currency?->symbol ?? '';
    $currencyCode = $order->currency?->code ?? '';
    $companyName = $order->company?->trade_name ?? $order->company?->business_name ?? '-';
    $supplierName = $order->supplier?->business_name ?? $order->supplier?->short_name ?? '-';
    $supplierRuc = $order->supplier?->ruc ?? '-';
    $account = $order->supplierAccount
        ? trim(($order->supplierAccount->bank?->description ?? 'Banco') . ' - ' . $order->supplierAccount->account_number . ' - ' . ($order->supplierAccount->currency?->code ?? ''))
        : '-';
    $destination = collect([
        $order->destinationUbigeo
            ? $order->destinationUbigeo->department . '/' . $order->destinationUbigeo->province . '/' . $order->destinationUbigeo->district
            : null,
        $order->destination_text,
    ])->filter()->join(' | ') ?: '-';
    $formatMoney = fn ($value) => trim($currencySymbol . ' ' . number_format((float) $value, 2));
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
    $labels = [
        'terrestre' => 'Terrestre',
        'aereo' => 'Aereo',
        'contado' => 'Contado',
        'credito_20_dias' => 'Credito 20 dias',
        'credito_30_dias' => 'Credito 30 dias',
        'credito_45_dias' => 'Credito 45 dias',
        'credito_60_dias' => 'Credito 60 dias',
        'agencia' => 'Agencia',
        'recojo_almacen' => 'Recojo de almacen',
        'transportista_proveedor' => 'Transportista del proveedor',
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'deposito_cuenta' => 'Deposito en cuenta',
        'factura' => 'Factura',
        'boleta' => 'Boleta',
        'nota_pedido' => 'Nota de pedido',
        'guia_remision' => 'Guia de remision',
    ];
    $optionLabel = fn ($value) => $labels[$value] ?? ($value ?: '-');
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Orden de compra {{ $order->code }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            font-size: 9px;
            line-height: 1.25;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #15803d;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .brand {
            width: 62%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }

        .logo img {
            width: 230px;
            max-height: 78px;
            object-fit: contain;
        }

        .company-name {
            color: #334155;
            font-size: 11px;
            font-weight: 700;
            margin-top: 3px;
        }

        .order-box {
            width: 36%;
            display: inline-block;
            vertical-align: top;
            text-align: right;
        }

        .order-number {
            display: inline-block;
            border: 1px solid #86efac;
            background: #f0fdf4;
            border-radius: 6px;
            padding: 8px 12px;
            min-width: 190px;
            text-align: center;
        }

        .order-number .label {
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
        }

        .order-number .value {
            color: #15803d;
            font-size: 16px;
            font-weight: 800;
        }

        .section-title {
            color: #15803d;
            font-size: 10px;
            font-weight: 800;
            margin: 7px 0 4px;
            text-transform: uppercase;
        }

        .info-grid,
        .items,
        .totals {
            width: 100%;
            border-collapse: collapse;
        }

        .info-grid {
            table-layout: fixed;
            margin-bottom: 6px;
        }

        .info-grid td {
            border: 1px solid #d9eadf;
            padding: 4px 5px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .label {
            color: #64748b;
            display: block;
            font-size: 8px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .value {
            color: #111827;
            font-weight: 700;
        }

        .related {
            margin: 0;
            padding-left: 11px;
        }

        .related li {
            margin-bottom: 2px;
        }

        .items {
            table-layout: fixed;
            margin-top: 5px;
        }

        .items th {
            background: #15803d;
            color: #fff;
            border: 1px solid #15803d;
            font-size: 7px;
            font-weight: 800;
            padding: 3px 2px;
            text-transform: uppercase;
        }

        .items td {
            border: 1px solid #d9eadf;
            font-size: 7.6px;
            padding: 3px 2px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .items tbody tr:nth-child(even) td {
            background: #f7fdf9;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .description {
            font-weight: 700;
        }

        .muted {
            color: #64748b;
        }

        .totals-wrapper {
            width: 100%;
            margin-top: 7px;
        }

        .notes {
            width: 58%;
            display: inline-block;
            vertical-align: top;
            border: 1px solid #d9eadf;
            border-radius: 6px;
            min-height: 48px;
            padding: 6px;
            word-wrap: break-word;
        }

        .totals {
            width: 38%;
            float: right;
        }

        .totals td {
            border: 1px solid #d9eadf;
            padding: 5px 6px;
        }

        .totals .grand td {
            background: #dcfce7;
            color: #14532d;
            font-size: 11px;
            font-weight: 800;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 8px;
            padding-top: 5px;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="brand">
            @if (!empty($logoUrl))
                <div class="logo">
                    <img src="{{ $logoUrl }}" alt="Logo">
                </div>
            @else
                <div class="company-name">{{ $companyName }}</div>
            @endif
            <div class="company-name">{{ $order->company?->business_name ?? $companyName }}</div>
            <div class="muted">{{ $order->company?->address ?? '' }}</div>
            <div class="muted">
                {{ $order->company?->email ?? '' }}
                {{ $order->company?->phone ? ' | ' . $order->company->phone : '' }}
            </div>
        </div>

        <div class="order-box">
            <div class="order-number">
                <div class="label">Orden de compra a proveedor</div>
                <div class="value">{{ $order->code }}</div>
                <div class="muted">{{ $order->created_at?->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    <div class="section-title">Datos principales</div>
    <table class="info-grid">
        <tr>
            <td width="25%">
                <span class="label">Empresa compradora</span>
                <span class="value">{{ $companyName }}</span>
            </td>
            <td width="25%">
                <span class="label">Proveedor</span>
                <span class="value">{{ $supplierName }}</span>
            </td>
            <td width="12%">
                <span class="label">RUC proveedor</span>
                <span class="value">{{ $supplierRuc }}</span>
            </td>
            <td width="12%">
                <span class="label">Moneda</span>
                <span class="value">{{ trim($currencyCode . ' ' . $currencySymbol) ?: '-' }}</span>
            </td>
            <td width="13%">
                <span class="label">Afecto IGV</span>
                <span class="value">{{ $order->affect_igv ? 'SI' : 'NO' }}</span>
            </td>
            <td width="13%">
                <span class="label">Total</span>
                <span class="value">{{ $formatMoney($order->grand_total) }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Cuenta banco proveedor</span>
                <span class="value">{{ $account }}</span>
            </td>
            <td>
                <span class="label">Condicion pago</span>
                <span class="value">{{ $optionLabel($order->payment_condition) }}</span>
            </td>
            <td>
                <span class="label">Transporte</span>
                <span class="value">{{ $optionLabel($order->transport_type) }}</span>
            </td>
            <td>
                <span class="label">Entrega</span>
                <span class="value">{{ $optionLabel($order->delivery_type) }}</span>
            </td>
            <td>
                <span class="label">Documento</span>
                <span class="value">{{ $optionLabel($order->document_type) }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Direccion de envio</span>
                <span class="value">{{ $order->shipping_address ?: '-' }}</span>
            </td>
            <td colspan="2">
                <span class="label">Ubigeo / destino</span>
                <span class="value">{{ $destination }}</span>
            </td>
            <td>
                <span class="label">Forma pago</span>
                <span class="value">{{ $optionLabel($order->payment_method) }}</span>
            </td>
            <td>
                <span class="label">Emision</span>
                <span class="value">{{ $order->created_at?->format('d/m/Y') }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Ordenes de compra de cliente relacionadas</div>
    <table class="info-grid">
        <tr>
            <td>
                @if ($order->customerPurchaseOrders->isNotEmpty())
                    <ul class="related">
                        @foreach ($order->customerPurchaseOrders as $customerOrder)
                            @php
                                $customerName = $customerOrder->customer?->business_name
                                    ?? $customerOrder->customer?->full_name
                                    ?? trim(($customerOrder->customer?->first_name ?? '') . ' ' . ($customerOrder->customer?->last_name ?? ''))
                                    ?: 'Sin cliente';
                            @endphp
                            <li>
                                <strong>{{ $customerOrder->code }}</strong>
                                | {{ $customerName }}
                                | {{ $formatMoney($customerOrder->grand_total) }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <span class="value">-</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="section-title">Detalle de articulos</div>
    <table class="items">
        <thead>
            <tr>
                <th width="2%">#</th>
                <th width="6%">Codigo</th>
                <th width="19%">Articulo / descripcion</th>
                <th width="8%">Nota</th>
                <th width="5%">U.M.</th>
                <th width="7%">Present.</th>
                <th width="7%">Marca</th>
                <th width="7%">Proced.</th>
                <th width="6%">F. Venc.</th>
                <th width="6%">C. Costeo</th>
                <th width="7%">P. Ref.</th>
                <th width="6%">Cant.</th>
                <th width="7%">Precio</th>
                <th width="7%">P. Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($order->items as $item)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $item->article_code ?: '-' }}</td>
                    <td class="description">{{ $item->billing_name_snapshot }}</td>
                    <td>{{ $item->note ?: '-' }}</td>
                    <td>{{ $item->unit?->abbreviation ?? $item->unit?->description ?? '-' }}</td>
                    <td>{{ $item->presentation?->description ?? '-' }}</td>
                    <td>{{ $item->brand?->description ?? '-' }}</td>
                    <td>{{ $item->origin ?: '-' }}</td>
                    <td class="text-center">{{ $formatDate($item->expiration_date) }}</td>
                    <td>{{ $item->cost_type ?: '-' }}</td>
                    <td class="text-right">{{ $formatMoney($item->reference_purchase_price) }}</td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="text-right">{{ $formatMoney($item->unit_price) }}</td>
                    <td class="text-right">{{ $formatMoney($item->line_total) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="14" class="text-center muted">Sin articulos registrados</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals-wrapper">
        <div class="notes">
            <span class="label">Observacion</span>
            <span class="value">{{ $order->observations ?: 'Sin observaciones' }}</span>
        </div>

        <table class="totals">
            <tr>
                <td>Subtotal compra</td>
                <td class="text-right">{{ $formatMoney($order->subtotal) }}</td>
            </tr>
            <tr>
                <td>Total IGV</td>
                <td class="text-right">{{ $formatMoney($order->igv) }}</td>
            </tr>
            <tr class="grand">
                <td>Total compra</td>
                <td class="text-right">{{ $formatMoney($order->grand_total) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Documento generado automaticamente por DropaivSys2 |
        {{ now()->format('d/m/Y H:i') }} |
        orden_compra_proveedor_{{ $order->code }}.pdf
    </div>
</body>

</html>
