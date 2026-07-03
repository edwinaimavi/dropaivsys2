@php
    $currencySymbol = $entry->currency?->symbol ?? '';
    $currencyCode = $entry->currency?->code ?? '';
    $companyName = $entry->company?->trade_name ?? $entry->company?->business_name ?? '-';
    $supplierName = $entry->supplier?->business_name ?? $entry->supplier?->short_name ?? '-';
    $supplierRuc = $entry->supplier?->ruc ?? '-';
    $warehouseName = $entry->warehouse?->name ?? 'SIN ALMACEN';
    $formatMoney = fn ($value) => trim($currencySymbol . ' ' . number_format((float) $value, 2));
    $formatQty = fn ($value) => number_format((float) $value, 2);
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
    $formatDateTime = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y H:i') : '-';
    $labels = [
        'registered' => 'Registrado',
        'cancelled' => 'Anulado',
        'contado' => 'Contado',
        'credito_20_dias' => 'Credito 20 dias',
        'credito_30_dias' => 'Credito 30 dias',
        'credito_45_dias' => 'Credito 45 dias',
        'credito_60_dias' => 'Credito 60 dias',
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'deposito_cuenta' => 'Deposito en cuenta',
    ];
    $optionLabel = fn ($value) => $labels[$value] ?? ($value ?: '-');
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Ingreso de almacen {{ $entry->entry_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            font-size: 8.4px;
            line-height: 1.25;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 7px;
            margin-bottom: 7px;
        }

        .brand {
            width: 62%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }

        .logo img {
            width: 220px;
            max-height: 72px;
            object-fit: contain;
        }

        .company-name {
            color: #334155;
            font-size: 10px;
            font-weight: 700;
            margin-top: 2px;
        }

        .entry-box {
            width: 36%;
            display: inline-block;
            vertical-align: top;
            text-align: right;
        }

        .entry-number {
            display: inline-block;
            border: 1px solid #99f6e4;
            background: #f0fdfa;
            border-radius: 6px;
            padding: 7px 11px;
            min-width: 185px;
            text-align: center;
        }

        .entry-number .label {
            color: #64748b;
            font-size: 8px;
            text-transform: uppercase;
        }

        .entry-number .value {
            color: #0f766e;
            font-size: 16px;
            font-weight: 800;
        }

        .section-title {
            color: #0f766e;
            font-size: 9.2px;
            font-weight: 800;
            margin: 6px 0 4px;
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
            margin-bottom: 5px;
        }

        .info-grid td {
            border: 1px solid #ccfbf1;
            padding: 4px 5px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .label {
            color: #64748b;
            display: block;
            font-size: 7.4px;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .value {
            color: #111827;
            font-weight: 700;
        }

        .items {
            table-layout: fixed;
            margin-top: 4px;
        }

        .items th {
            background: #0f766e;
            color: #fff;
            border: 1px solid #0f766e;
            font-size: 6.5px;
            font-weight: 800;
            padding: 3px 2px;
            text-transform: uppercase;
        }

        .items td {
            border: 1px solid #ccfbf1;
            font-size: 6.9px;
            padding: 3px 2px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .items tbody tr:nth-child(even) td {
            background: #f6fffd;
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
            border: 1px solid #ccfbf1;
            border-radius: 6px;
            min-height: 42px;
            padding: 6px;
            word-wrap: break-word;
        }

        .totals {
            width: 38%;
            float: right;
        }

        .totals td {
            border: 1px solid #ccfbf1;
            padding: 5px 6px;
        }

        .totals .grand td {
            background: #ccfbf1;
            color: #134e4a;
            font-size: 10.5px;
            font-weight: 800;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 7.4px;
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
            <div class="company-name">{{ $entry->company?->business_name ?? $companyName }}</div>
            <div class="muted">{{ $entry->company?->address ?? '' }}</div>
            <div class="muted">
                {{ $entry->company?->email ?? '' }}
                {{ $entry->company?->phone ? ' | ' . $entry->company->phone : '' }}
            </div>
        </div>

        <div class="entry-box">
            <div class="entry-number">
                <div class="label">Ingreso de almacen</div>
                <div class="value">{{ $entry->entry_number }}</div>
                <div class="muted">{{ $formatDateTime($entry->created_at) }}</div>
                <div class="muted">{{ $optionLabel($entry->status) }}</div>
            </div>
        </div>
    </div>

    <div class="section-title">Datos principales</div>
    <table class="info-grid">
        <tr>
            <td width="17%">
                <span class="label">Orden compra proveedor</span>
                <span class="value">{{ $entry->supplierPurchaseOrder?->code ?? $entry->purchase_order_number ?? '-' }}</span>
            </td>
            <td width="20%">
                <span class="label">Empresa</span>
                <span class="value">{{ $companyName }}</span>
            </td>
            <td width="22%">
                <span class="label">Proveedor</span>
                <span class="value">{{ $supplierName }}</span>
            </td>
            <td width="11%">
                <span class="label">RUC proveedor</span>
                <span class="value">{{ $supplierRuc }}</span>
            </td>
            <td width="18%">
                <span class="label">Almacen</span>
                <span class="value">{{ $warehouseName }}</span>
            </td>
            <td width="12%">
                <span class="label">Moneda</span>
                <span class="value">{{ trim($currencyCode . ' ' . $currencySymbol) ?: '-' }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Datos documentales</div>
    <table class="info-grid">
        <tr>
            <td width="12%">
                <span class="label">Tipo documento</span>
                <span class="value">{{ $entry->document_type ?: '-' }}</span>
            </td>
            <td width="10%">
                <span class="label">Serie</span>
                <span class="value">{{ $entry->document_series ?: '-' }}</span>
            </td>
            <td width="12%">
                <span class="label">Nro comprobante</span>
                <span class="value">{{ $entry->document_number ?: '-' }}</span>
            </td>
            <td width="12%">
                <span class="label">Fecha documento</span>
                <span class="value">{{ $formatDate($entry->document_date) }}</span>
            </td>
            <td width="13%">
                <span class="label">Forma pago</span>
                <span class="value">{{ $optionLabel($entry->payment_method) }}</span>
            </td>
            <td width="13%">
                <span class="label">Condicion pago</span>
                <span class="value">{{ $optionLabel($entry->payment_condition) }}</span>
            </td>
            <td width="10%">
                <span class="label">Cta. por pagar</span>
                <span class="value">{{ $entry->generate_account_payable ? 'SI' : 'NO' }}</span>
            </td>
            <td width="10%">
                <span class="label">Monto</span>
                <span class="value">{{ $formatMoney($entry->payable_amount) }}</span>
            </td>
            <td width="8%">
                <span class="label">F. pago</span>
                <span class="value">{{ $formatDate($entry->expected_payment_date) }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Vendedor</span>
                <span class="value">{{ $entry->seller_name ?: '-' }}</span>
            </td>
            <td>
                <span class="label">Serie guia</span>
                <span class="value">{{ $entry->guide_series ?: '-' }}</span>
            </td>
            <td>
                <span class="label">Nro guia</span>
                <span class="value">{{ $entry->guide_number ?: '-' }}</span>
            </td>
            <td>
                <span class="label">RUC guia</span>
                <span class="value">{{ $entry->guide_ruc ?: '-' }}</span>
            </td>
            <td colspan="4">
                <span class="label">Registro</span>
                <span class="value">Generado por {{ $entry->creator?->name ?? 'el sistema' }} el {{ $formatDateTime($entry->created_at) }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Articulos ingresados</div>
    <table class="items">
        <thead>
            <tr>
                <th width="2%">#</th>
                <th width="6%">Codigo</th>
                <th width="19%">Articulo</th>
                <th width="8%">Nota</th>
                <th width="5%">U.M.</th>
                <th width="7%">Present.</th>
                <th width="7%">Marca</th>
                <th width="7%">Proced.</th>
                <th width="6%">Lote</th>
                <th width="6%">F. Venc.</th>
                <th width="7%">Cant. ordenada</th>
                <th width="7%">Cant. ingresada</th>
                <th width="6%">Precio</th>
                <th width="7%">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entry->items as $item)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $item->article_code ?: '-' }}</td>
                    <td class="description">{{ $item->billing_name_snapshot }}</td>
                    <td>{{ $item->note ?: '-' }}</td>
                    <td>{{ $item->unit?->abbreviation ?? $item->unit?->description ?? '-' }}</td>
                    <td>{{ $item->presentation?->description ?? '-' }}</td>
                    <td>{{ $item->brand?->description ?? '-' }}</td>
                    <td>{{ $item->origin ?: '-' }}</td>
                    <td>{{ $item->lot_number ?: '-' }}</td>
                    <td class="text-center">{{ $formatDate($item->expiration_date) }}</td>
                    <td class="text-right">{{ $formatQty($item->ordered_quantity) }}</td>
                    <td class="text-right">{{ $formatQty($item->quantity) }}</td>
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
            <span class="label">Observaciones</span>
            <span class="value">{{ $entry->observations ?: 'Sin observaciones' }}</span>
        </div>

        <table class="totals">
            <tr>
                <td>Subtotal</td>
                <td class="text-right">{{ $formatMoney($entry->subtotal) }}</td>
            </tr>
            <tr>
                <td>IGV</td>
                <td class="text-right">{{ $formatMoney($entry->igv) }}</td>
            </tr>
            <tr class="grand">
                <td>Total ingreso</td>
                <td class="text-right">{{ $formatMoney($entry->grand_total) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Generado por DropaivSys el {{ now()->format('d/m/Y H:i') }}.
        Documento interno de trazabilidad de ingresos de almacen.
    </div>
</body>

</html>
