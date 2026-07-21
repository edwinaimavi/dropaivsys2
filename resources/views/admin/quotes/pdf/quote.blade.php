@php
    $currencySymbol = $quote->currency?->symbol ?? '';
    $currencyCode = $quote->currency?->code ?? '';
    $customerName = $quote->customer?->business_name
        ?? $quote->customer?->full_name
        ?? trim(($quote->customer?->first_name ?? '') . ' ' . ($quote->customer?->last_name ?? ''));
    $customerRuc = $quote->customer?->ruc ?? $quote->customer?->document_number ?? null;
    $companyName = $quote->company?->trade_name ?? $quote->company?->business_name ?? '-';
    $branchName = $quote->customerBranch?->branch_name ?? '-';
    $registeredBy = trim(($quote->creator?->name ?? '') . ' ' . ($quote->creator?->lastname ?? '')) ?: '-';

    $formatMoney = fn ($value) => trim($currencySymbol . ' ' . number_format((float) $value, 2));
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
    $observationItems = collect(preg_split('/\R/u', (string) $quote->observations))
        ->map(fn ($line) => trim(preg_replace('/^[\-\*\x{2022}\s]+/u', '', $line)))
        ->filter()
        ->values();
    $additionalObservations = trim((string) $quote->additional_observations);
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
            border-bottom: 2px solid {{ $brandColor }};
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .brand {
            width: 62%;
            display: inline-block;
            vertical-align: top;
            text-align: left;
        }

        .logo {
            text-align: left;
            margin: 0 0 5px;
        }

        .logo img {
            width: 145px;
            max-width: 100%;
            max-height: 58px;
            object-fit: contain;
        }

        .brand-info {
            width: 100%;
            text-align: left;
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
            border: 1px solid {{ $brandBorderColor }};
            background: {{ $brandLightColor }};
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
            color: {{ $brandColor }};
            font-size: 17px;
            font-weight: 700;
        }

        .section-title {
            color: {{ $brandColor }};
            font-size: 9.5px;
            font-weight: 700;
            margin: 6px 0 3px;
            text-transform: uppercase;
        }

        .info-layout {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 6px;
            table-layout: fixed;
            border: 1px solid {{ $brandBorderColor }};
        }

        .info-layout th,
        .info-layout td {
            border: 1px solid {{ $brandBorderColor }};
            vertical-align: top;
            word-wrap: break-word;
        }

        .info-title {
            color: {{ $brandColor }};
            font-size: 9px;
            font-weight: 800;
            padding: 3px 5px;
            background: {{ $brandLightColor }};
            text-align: center;
            text-transform: uppercase;
            line-height: 1.05;
        }

        .info-cell {
            padding: 3px 5px;
            font-size: 8px;
            line-height: 1.05;
        }

        .contact-start {
            border-left: 2px solid {{ $brandColor }} !important;
        }

        .info-cell .label {
            color: #64748b;
            font-size: 6.8px;
            text-transform: uppercase;
            display: block;
            margin: 0 0 1px;
            line-height: 1;
        }

        .info-cell .value {
            display: block;
            color: #111827;
            font-weight: 700;
            word-wrap: break-word;
            font-size: 8px;
            line-height: 1.08;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
        }

        .items th {
            background: {{ $brandColor }};
            color: #ffffff;
            border: 1px solid {{ $brandColor }};
            padding: 4px 3px;
            text-transform: uppercase;
            font-size: 8px;
            font-weight: 700;
        }

        .items td {
            border: 1px solid {{ $brandBorderColor }};
            padding: 4px 3px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 8.5px;
        }

        .items tbody tr:nth-child(even) td {
            background: {{ $brandLightColor }};
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

        .bottom-layout {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 6px -6px 0;
            table-layout: fixed;
            page-break-inside: avoid;
        }

        .bottom-left,
        .bottom-right {
            vertical-align: top;
        }

        .notes {
            border: 1px solid {{ $brandBorderColor }};
            border-radius: 6px;
            padding: 5px 7px;
            font-size: 8px;
            line-height: 1.15;
            word-wrap: break-word;
        }

        .notes-title {
            display: block;
            margin-bottom: 3px;
            font-size: 8.5px;
            font-weight: 800;
        }

        .notes-list {
            margin: 0;
            padding-left: 13px;
        }

        .notes-list li {
            margin: 0 0 2px;
            padding-left: 1px;
            line-height: 1.15;
        }

        .totals {
            width: 100%;
            border-collapse: collapse;
        }

        .totals td {
            border: 1px solid {{ $brandBorderColor }};
            padding: 5px 6px;
        }

        .totals .grand td {
            background: {{ $brandLightColor }};
            color: {{ $brandColor }};
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

        .signature {
            width: 72%;
            margin: 12px 0 2px;
            text-align: center;
            font-size: 8px;
            line-height: 1.15;
            page-break-inside: avoid;
        }

        .signature-line {
            border-top: 1px solid #475569;
            padding-top: 5px;
            font-weight: 800;
        }
    </style>
</head>

<body>
    <div class="header">
        <div class="brand">
            @if (!empty($logoPath))
                <div class="logo">
                    <img src="{{ $logoPath }}" alt="Logo">
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

    <table class="info-layout">
        <colgroup>
            <col width="25%">
            <col width="25%">
            <col width="25%">
            <col width="25%">
        </colgroup>
        <thead>
            <tr>
                <th class="info-title" colspan="2">Datos del cliente</th>
                <th class="info-title contact-start" colspan="2">Datos de contacto</th>
            </tr>
        </thead>
        <tbody>
        <tr>
            <td class="info-cell">
                <span class="label">Razón social</span>
                <span class="value">{{ $customerName ?: '-' }}</span>
            </td>
            <td class="info-cell">
                <span class="label">Sucursal / tienda</span>
                <span class="value">{{ $branchName }}</span>
            </td>
            <td class="info-cell contact-start">
                <span class="label">Registrado por</span>
                <span class="value">{{ $registeredBy }}</span>
            </td>
            <td class="info-cell">
                <span class="label">Fecha de emisión</span>
                <span class="value">{{ $formatDate($quote->created_at) }}</span>
            </td>
        </tr>
        <tr>
            <td class="info-cell">
                <span class="label">RUC / DNI</span>
                <span class="value">{{ $customerRuc ?: '-' }}</span>
            </td>
            <td class="info-cell">
                <span class="label">Dirección de entrega</span>
                <span class="value">{{ $quote->delivery_address ?? '-' }}</span>
            </td>
            <td class="info-cell contact-start">
                <span class="label">Fecha de validez</span>
                <span class="value">{{ $formatDate($quote->validity_date) }}</span>
            </td>
            <td class="info-cell">
                <span class="label">Condición de pago</span>
                <span class="value">{{ $quote->payment_condition ?? '-' }}</span>
            </td>
        </tr>
        <tr>
            <td class="info-cell">&nbsp;</td>
            <td class="info-cell">&nbsp;</td>
            <td class="info-cell contact-start">
                <span class="label">Moneda</span>
                <span class="value">{{ trim($currencyCode . ' ' . $currencySymbol) ?: '-' }}</span>
            </td>
            <td class="info-cell">
                <span class="label">Correo empresa</span>
                <span class="value">{{ $quote->company?->email ?? '-' }}</span>
            </td>
        </tr>
        </tbody>
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

    <table class="bottom-layout">
        <tr>
        <td class="bottom-left" width="60%">
          <div class="notes">
            <span class="notes-title">TÉRMINOS Y CONDICIONES</span>

            @if ($observationItems->isNotEmpty())
                <ul class="notes-list">
                    @foreach ($observationItems as $observation)
                        <li>{{ $observation }}</li>
                    @endforeach
                </ul>
            @else
                <span class="muted">Sin términos y condiciones.</span>
            @endif

            @if ($additionalObservations !== '')
                <div class="section-title">Observaciones</div>
                <div>{{ $additionalObservations }}</div>
            @endif
          </div>

            <div class="signature">
                <div class="muted">Autorizado por</div>
                <div class="signature-line">{{ $authorizedName }}</div>
                <div>{{ $authorizedPosition }}</div>
            </div>
        </td>

        <td class="bottom-right" width="40%">
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
        </td>
        </tr>
    </table>

    <div class="footer">
        Documento generado automaticamente por DroPaivSys. Archivo:
        cotizacion_{{ $quote->quote_number }}.pdf
    </div>
</body>

</html>
