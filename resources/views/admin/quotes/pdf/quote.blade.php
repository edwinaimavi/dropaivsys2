@php
    $currencySymbol = $quote->currency?->symbol ?? '';
    $currencyCode = $quote->currency?->code ?? '';
    $customerName = $quote->customer?->business_name
        ?? $quote->customer?->full_name
        ?? trim(($quote->customer?->first_name ?? '') . ' ' . ($quote->customer?->last_name ?? ''));
    $customerRuc = $quote->customer?->ruc ?? $quote->customer?->document_number ?? null;
    $companyName = $quote->company?->trade_name ?? $quote->company?->business_name ?? '-';

    $formatMoney = fn ($value) => trim($currencySymbol . ' ' . number_format((float) $value, 2));
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
    $observationItems = collect(preg_split('/\R/u', (string) $quote->observations))
        ->map(fn ($line) => trim(preg_replace('/^[\-\*\x{2022}\s]+/u', '', $line)))
        ->filter()
        ->values();
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Cotizacion {{ $quote->quote_number }}</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            font-size: 10px;
            line-height: 1.3;
            margin: 0;
        }

        .header {
            border-bottom: 2px solid #16a34a;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .brand {
            width: 62%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }

        .logo {
            text-align: center;
            margin: 0 0 5px;
        }

        .logo img {
            width: 260px;
            max-width: 100%;
            max-height: 92px;
            object-fit: contain;
        }

        .brand-info {
            width: 100%;
            text-align: center;
        }

        .brand h1 {
            color: #0f172a;
            font-size: 21px;
            margin: 0 0 4px;
            letter-spacing: 0;
        }

        .brand p {
            margin: 1px 0;
            color: #475569;
            word-wrap: break-word;
        }

        .brand .company-name {
            color: #334155;
            font-size: 12px;
            font-weight: 700;
        }

        .quote-box {
            width: 36%;
            display: inline-block;
            vertical-align: top;
            text-align: right;
        }

        .quote-number {
            display: inline-block;
            border: 1px solid #86efac;
            background: #f0fdf4;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
            min-width: 160px;
        }

        .quote-number .label {
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
        }

        .quote-number .value {
            color: #15803d;
            font-size: 17px;
            font-weight: 700;
        }

        .section-title {
            color: #15803d;
            font-size: 11px;
            font-weight: 700;
            margin: 9px 0 5px;
            text-transform: uppercase;
        }

        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
            table-layout: fixed;
        }

        .info-grid td {
            border: 1px solid #d9eadf;
            padding: 5px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .info-grid .label {
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }

        .info-grid .value {
            color: #111827;
            font-weight: 700;
            word-wrap: break-word;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
        }

        .items th {
            background: #15803d;
            color: #ffffff;
            border: 1px solid #15803d;
            padding: 4px 3px;
            text-transform: uppercase;
            font-size: 8px;
            font-weight: 700;
        }

        .items td {
            border: 1px solid #d9eadf;
            padding: 4px 3px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 8.5px;
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
            word-wrap: break-word;
        }

        .muted {
            color: #64748b;
        }

        .totals-wrapper {
            width: 100%;
            margin-top: 8px;
        }

        .notes {
            width: 58%;
            display: inline-block;
            vertical-align: top;
            border: 1px solid #d9eadf;
            border-radius: 6px;
            padding: 6px;
            min-height: 58px;
            word-wrap: break-word;
        }

        .notes-title {
            display: block;
            margin-bottom: 4px;
            font-weight: 800;
        }

        .notes-list {
            margin: 0;
            padding-left: 13px;
        }

        .notes-list li {
            margin: 0 0 3px;
            padding-left: 2px;
            line-height: 1.25;
        }

        .totals {
            width: 40%;
            margin-left: 1%;
            float: right;
            vertical-align: top;
            border-collapse: collapse;
        }

        .totals td {
            border: 1px solid #d9eadf;
            padding: 5px 6px;
        }

        .totals .grand td {
            background: #dcfce7;
            color: #14532d;
            font-size: 12px;
            font-weight: 800;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: 0;
            border-top: 1px solid #e5e7eb;
            color: #64748b;
            font-size: 9px;
            padding-top: 6px;
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
                <h1>{{ $companyName }}</h1>
            @endif

            <div class="brand-info">
                <p class="company-name">{{ $quote->company?->business_name ?? $companyName }}</p>
                <p>{{ $quote->company?->address ?? '' }}</p>
                <p>
                    {{ $quote->company?->email ?? '' }}
                    {{ $quote->company?->phone ? ' | ' . $quote->company->phone : '' }}
                </p>
            </div>
        </div>

        <div class="quote-box">
            <div class="quote-number">
                <div class="label">Cotizacion</div>
                <div class="value">{{ $quote->quote_number }}</div>
                <div class="muted">{{ $quote->created_at?->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    <div class="section-title">Datos de cabecera</div>
    <table class="info-grid">
        <tr>
            <td width="32%">
                <span class="label">Cliente</span>
                <span class="value">{{ $customerName ?: '-' }}</span>
            </td>
            <td width="16%">
                <span class="label">RUC</span>
                <span class="value">{{ $customerRuc ?: '-' }}</span>
            </td>
            <td width="16%">
                <span class="label">Moneda</span>
                <span class="value">{{ trim($currencyCode . ' ' . $currencySymbol) ?: '-' }}</span>
            </td>
            <td width="20%">
                <span class="label">Condicion de pago</span>
                <span class="value">{{ $quote->payment_condition ?? '-' }}</span>
            </td>
            <td width="16%">
                <span class="label">Total</span>
                <span class="value">{{ $formatMoney($quote->grand_total) }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="label">Direccion de entrega</span>
                <span class="value">{{ $quote->delivery_address ?? '-' }}</span>
            </td>
            <td>
                <span class="label">Fecha de validez</span>
                <span class="value">{{ $formatDate($quote->validity_date) }}</span>
            </td>
            <td>
                <span class="label">Dias de entrega</span>
                <span class="value">{{ $quote->delivery_days ?? '-' }}</span>
            </td>
        </tr>
    </table>

    <div class="section-title">Detalle de productos o servicios</div>
    <table class="items">
        <thead>
            <tr>
                <th width="4%">#</th>
                <th width="8%">Codigo</th>
                <th width="24%">Descripcion</th>
                <th width="6%">Cant.</th>
                <th width="7%">Unidad</th>
                <th width="8%">Marca</th>
                <th width="8%">Present.</th>
                <th width="8%">F. Venc.</th>
                <th width="8%">Proced.</th>
                <th width="8%">P. Unit.</th>
                <th width="6%">Desc.</th>
                <th width="9%">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $item->article_code ?? '-' }}</td>
                    <td>
                        <div class="description">{{ $item->billing_name_snapshot }}</div>
                        @if ($item->note)
                            <div class="muted">{{ $item->note }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td>{{ $item->unit?->abbreviation ?? $item->unit?->description ?? '-' }}</td>
                    <td>{{ $item->brand?->description ?? '-' }}</td>
                    <td>{{ $item->presentation?->description ?? '-' }}</td>
                    <td class="text-center">{{ $formatDate($item->expiration_date) }}</td>
                    <td>{{ $item->origin ?? '-' }}</td>
                    <td class="text-right">{{ $formatMoney($item->unit_price) }}</td>
                    <td class="text-right">
                        @if ((float) $item->discount_percentage > 0)
                            {{ number_format((float) $item->discount_percentage, 2) }}%
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">{{ $formatMoney($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-wrapper">
        <div class="notes">
            <span class="notes-title">Observaciones</span>

            @if ($observationItems->isNotEmpty())
                <ul class="notes-list">
                    @foreach ($observationItems as $observation)
                        <li>{{ $observation }}</li>
                    @endforeach
                </ul>
            @else
                <span class="muted">Sin observaciones.</span>
            @endif
        </div>

        <table class="totals">
            <tr>
                <td>Venta exonerada</td>
                <td class="text-right">{{ $formatMoney($quote->subtotal_exonerated) }}</td>
            </tr>
            <tr>
                <td>Venta gravada</td>
                <td class="text-right">{{ $formatMoney($quote->subtotal_taxed) }}</td>
            </tr>
            <tr>
                <td>IGV</td>
                <td class="text-right">{{ $formatMoney($quote->igv) }}</td>
            </tr>
            <tr class="grand">
                <td>Total</td>
                <td class="text-right">{{ $formatMoney($quote->grand_total) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Documento generado automaticamente por DroPaivSys. Archivo:
        cotizacion_{{ $quote->quote_number }}.pdf
    </div>
</body>

</html>
