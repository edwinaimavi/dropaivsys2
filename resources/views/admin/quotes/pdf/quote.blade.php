@php
    $currencySymbol = $quote->currency?->symbol ?? '';
    $customerName = $quote->customer?->business_name
        ?? $quote->customer?->full_name
        ?? trim(($quote->customer?->first_name ?? '') . ' ' . ($quote->customer?->last_name ?? ''));
    $customerRuc = $quote->customer?->ruc ?? $quote->customer?->document_number ?? null;
    $companyName = $quote->company?->trade_name ?? $quote->company?->business_name ?? '-';
    $branchName = $quote->customerBranch?->branch_name ?? '-';
    $upper = fn ($value) => \Illuminate\Support\Str::upper(trim((string) ($value ?: '-')));

    $formatMoney = fn ($value) => trim($currencySymbol . ' ' . number_format((float) $value, 2));
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d/m/Y') : '-';
    $formatExpirationMonth = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('m/Y') : '-';
    $amountSizeClass = fn ($value) => mb_strlen($formatMoney($value), 'UTF-8') > 12 ? 'amount-small' : '';
    $quantitySizeClass = fn ($value) => strlen(number_format((float) $value, 2)) > 10 ? 'amount-small' : '';
    $observationItems = collect(preg_split('/\R/u', (string) $quote->observations))
        ->map(fn ($line) => trim(preg_replace('/^[\-\*\x{2022}\s]+/u', '', $line)))
        ->filter()
        ->values();
    $additionalObservationItems = collect(preg_split('/\R/u', (string) $quote->additional_observations))
        ->map(fn ($line) => trim(preg_replace('/^[\-\*\x{2022}\s]+/u', '', $line)))
        ->filter()
        ->map(fn ($line) => \Illuminate\Support\Str::upper($line))
        ->values();
    $companyEmail = \Illuminate\Support\Str::lower(trim((string) $quote->company?->email)) ?: '-';
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

        .info-container {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0 0 6px;
            table-layout: fixed;
        }

        .info-container-cell {
            vertical-align: top;
        }

        .info-container-spacer {
            width: 10px;
        }

        .info-box {
            border: 1px solid {{ $brandBorderColor }};
            border-radius: 7px;
            padding: 0;
        }

        .info-box-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .info-box-table td {
            border-top: 1px solid {{ $brandBorderColor }};
            border-right: 1px solid {{ $brandBorderColor }};
            vertical-align: top;
            word-wrap: break-word;
        }

        .info-box-table td:last-child {
            border-right: 0;
        }

        .info-title {
            color: {{ $brandColor }};
            font-size: 9px;
            font-weight: 800;
            padding: 2px 4px;
            background: {{ $brandLightColor }};
            text-align: center;
            text-transform: uppercase;
            line-height: 1.05;
        }

        .info-cell {
            padding: 2px 4px;
            font-size: 7.7px;
            line-height: 1.02;
        }

        .info-cell .label {
            color: #64748b;
            font-size: 6.4px;
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
            font-size: 7.7px;
            line-height: 1.04;
        }

        .info-cell .email-value {
            color: {{ $brandColor }};
            font-size: 9px;
            font-weight: 800;
            text-decoration: none;
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
            padding: 3px 2px;
            text-transform: uppercase;
            font-size: 7.4px;
            font-weight: 700;
        }

        .items td {
            border: 1px solid {{ $brandBorderColor }};
            padding: 3px 2px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 7.9px;
            line-height: 1.08;
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

        .item-nowrap {
            white-space: nowrap;
            overflow: hidden;
        }

        .item-number {
            padding-left: 1px !important;
            padding-right: 1px !important;
            font-size: 7px;
        }

        .amount-small {
            font-size: 6.2px !important;
            letter-spacing: -0.1px;
        }

        .item-compact-text {
            font-size: 7.5px;
            line-height: 1.04;
        }

        .muted {
            color: #64748b;
        }

        .bottom-layout {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 6px -10px 0;
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

        .totals-box {
            border: 1px solid {{ $brandBorderColor }};
            border-radius: 7px;
            padding: 0;
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

        .observations-box {
            margin-top: 6px;
            padding: 5px 5px 2px;
            border-top: 1px solid {{ $brandBorderColor }};
            background: {{ $brandLightColor }};
        }

        .observations-title {
            display: block;
            color: {{ $brandColor }};
            font-size: 8px;
            font-weight: 800;
            margin-bottom: 3px;
        }

        .observations-list {
            margin: 0;
            padding-left: 13px;
        }

        .observations-list li {
            margin: 0 0 2px;
            line-height: 1.15;
        }

        .totals {
            width: 100%;
            border-collapse: collapse;
        }

        .totals td {
            border: 0;
            border-bottom: 1px solid {{ $brandBorderColor }};
            padding: 5px 6px;
        }

        .totals tr:last-child td {
            border-bottom: 0;
        }

        .totals .grand td {
            background: {{ $brandLightColor }};
            color: {{ $brandColor }};
            font-size: 12px;
            font-weight: 800;
        }

        .signature {
            width: 72%;
            margin: 12px 0 2px;
            text-align: center;
            font-size: 8px;
            line-height: 1.15;
            page-break-inside: avoid;
        }

        .signature-label {
            color: #64748b;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .signature-image {
            display: block;
            width: 175px;
            max-width: 100%;
            max-height: 70px;
            margin: 0 auto;
        }

        .amount-words {
            margin-top: 5px;
            border: 1px solid {{ $brandBorderColor }};
            border-radius: 6px;
            background: {{ $brandLightColor }};
            padding: 5px 6px;
            color: #111827;
            font-size: 7.5px;
            line-height: 1.2;
            word-wrap: break-word;
        }

        .amount-words-label {
            display: block;
            color: {{ $brandColor }};
            font-weight: 800;
            margin-bottom: 2px;
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

    <table class="info-container">
        <tr>
            <td class="info-container-cell" width="49%">
                <div class="info-box">
                    <div class="info-title">Datos del cliente</div>
                    <table class="info-box-table">
                        <tr>
                            <td class="info-cell" width="50%">
                                <span class="label">Razón social</span>
                                <span class="value">{{ $upper($customerName) }}</span>
                            </td>
                            <td class="info-cell" width="50%">
                                <span class="label">Sucursal / tienda</span>
                                <span class="value">{{ $upper($branchName) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="info-cell">
                                <span class="label">RUC / DNI</span>
                                <span class="value">{{ $upper($customerRuc) }}</span>
                            </td>
                            <td class="info-cell">
                                <span class="label">Dirección</span>
                                <span class="value">{{ $upper($quote->delivery_address) }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
            <td class="info-container-spacer"></td>
            <td class="info-container-cell" width="49%">
                <div class="info-box">
                    <div class="info-title">Datos de contacto</div>
                    <table class="info-box-table">
                        <tr>
                            <td class="info-cell" width="50%">
                                <span class="label">Registrado por</span>
                                <span class="value">{{ $upper($registeredBy) }}</span>
                            </td>
                            <td class="info-cell" width="50%">
                                <span class="label">Fecha de emisión</span>
                                <span class="value">{{ $formatDate($quote->created_at) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="info-cell">
                                <span class="label">Cargo / departamento</span>
                                <span class="value">{{ $upper($quote->issuer_department) }}</span>
                            </td>
                            <td class="info-cell">
                                <span class="label">Condición de pago</span>
                                <span class="value">{{ $upper($quote->payment_condition) }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="info-cell">
                                <span class="label">Número de contacto</span>
                                <span class="value">{{ $upper($quote->contact_number) }}</span>
                            </td>
                            <td class="info-cell">
                                <span class="label">Correo empresa</span>
                                <span class="value email-value">{{ $companyEmail }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">Detalle de productos o servicios</div>
    <table class="items">
        <thead>
            <tr>
                <th width="3%">#</th>
                <th width="8%">Codigo</th>
                <th width="24%">Descripcion</th>
                <th class="item-nowrap" width="7%">Cant.</th>
                <th class="item-nowrap" width="5%">Und.</th>
                <th width="12%">Marca</th>
                <th width="11%">Present.</th>
                <th class="item-nowrap" width="7%">F. Venc.</th>
                <th width="7%">Proced.</th>
                <th class="item-nowrap" width="7%">P. Unit.</th>
                <th class="item-nowrap" width="9%">Subtotal</th>
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
                    <td class="text-right item-nowrap item-number {{ $quantitySizeClass($item->quantity) }}">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="item-nowrap">{{ $item->unit?->abbreviation ?? $item->unit?->description ?? '-' }}</td>
                    <td class="item-compact-text">{{ $item->brand?->description ?? '-' }}</td>
                    <td class="item-compact-text">{{ $item->presentation?->description ?? '-' }}</td>
                    <td class="text-center item-nowrap item-number">{{ $formatExpirationMonth($item->expiration_date) }}</td>
                    <td>{{ $item->origin ?? '-' }}</td>
                    <td class="text-right item-nowrap item-number {{ $amountSizeClass($item->unit_price) }}">{{ $formatMoney($item->unit_price) }}</td>
                    <td class="text-right item-nowrap item-number {{ $amountSizeClass($item->line_total) }}">{{ $formatMoney($item->line_total) }}</td>
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

            @if ($additionalObservationItems->isNotEmpty())
                <div class="observations-box">
                    <span class="observations-title">OBSERVACIONES</span>
                    <ul class="observations-list">
                        @foreach ($additionalObservationItems as $observation)
                            <li>{{ $observation }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
          </div>

            <div class="signature">
                <div class="signature-label">Autorizado por</div>
                @if (!empty($signaturePath))
                    <img class="signature-image" src="{{ $signaturePath }}" alt="Firma autorizada">
                @endif
            </div>
        </td>

        <td class="bottom-right" width="40%">
          <div class="totals-box">
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
          <div class="amount-words">
              <span class="amount-words-label">MONTO EN LETRAS</span>
              {{ $amountInWords }}
          </div>
        </td>
        </tr>
    </table>

</body>

</html>
