<!doctype html>
<html lang="es">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="utf-8">
    <title>{{ $labeling->code }}</title>
    <style>
        @page {
            margin: 9mm 10mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            background: #fff;
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7px;
        }

        .sheet {
            page-break-after: always;
            width: 100%;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .label-grid {
            border-collapse: separate;
            border-spacing: 2.6mm;
            margin: 0 auto;
            table-layout: fixed;
            width: 97%;
        }

        .label-cell {
            padding: 0;
            vertical-align: top;
            width: 50%;
        }

        .label {
            border: .75px solid #6b7280;
            border-radius: 3px;
            overflow: hidden;
            padding: 5.5px;
            page-break-inside: avoid;
            width: 100%;
        }

        .labels-4 .label {
            min-height: 94mm;
        }

        .labels-6 .label {
            min-height: 78mm;
            padding: 4.5px;
        }

        .top {
            border-bottom: .7px solid #cbd5e1;
            padding-bottom: 4px;
        }

        .brand {
            float: left;
            width: 31%;
        }

        .rotulado-logo,
        .logo {
            height: auto;
            max-height: 36px;
            max-width: 104px;
        }

        .company-info {
            color: #333;
            font-family: Arial, Helvetica, sans-serif;
            margin-top: 3px;
            overflow-wrap: anywhere;
            text-transform: uppercase;
            word-break: break-word;
        }

        .company-info span {
            display: block;
        }

        .rotulado-company-name,
        .rotulado-company-ruc {
            color: #333;
            font-size: 7.8px;
            font-weight: 600;
            line-height: 1.12;
        }

        .rotulado-code,
        .code {
            color: #4b5563;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.5px;
            font-weight: 700;
            line-height: 1.05;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .destination {
            float: right;
            text-align: right;
            width: 68%;
        }

        .eyebrow {
            color: #6b7280;
            display: block;
            font-size: 5.8px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .highlight-font {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 800;
            letter-spacing: .3px;
        }

        .rotulado-destino-label {
            color: #555;
            display: block;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 800;
            letter-spacing: 1px;
            line-height: 1;
            text-transform: uppercase;
        }

        .rotulado-destino-value,
        .destination-value {
            color: #111827;
            display: block;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 25px;
            font-weight: 900;
            letter-spacing: .5px;
            line-height: 1.1;
            overflow-wrap: anywhere;
            text-transform: uppercase;
            word-break: break-word;
            word-wrap: break-word;
        }

        .destination-value.is-long {
            font-size: 20px;
        }

        .destination-value.is-very-long {
            font-size: 16px;
            line-height: 1.05;
        }

        .clear {
            clear: both;
        }

        .observation-row {
            margin: 4px 0 2px;
            min-height: 8px;
        }

        .observation-tag {
            background: #b91c1c;
            border: .7px solid #991b1b;
            color: #fff;
            display: inline-block;
            font-size: 8px;
            font-weight: 900;
            line-height: 1.08;
            max-width: 100%;
            padding: 3px 7px;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .customer {
            margin-top: 2px;
            text-align: center;
        }

        .customer-name {
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13.8px;
            font-weight: 900;
            letter-spacing: .3px;
            line-height: 1.05;
            overflow-wrap: anywhere;
            text-transform: uppercase;
            word-break: break-word;
            word-wrap: break-word;
        }

        .labels-6 .customer-name {
            font-size: 12px;
        }

        .oc {
            background: #f8fafc;
            border: .8px solid #6b7280;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14.5px;
            font-weight: 900;
            letter-spacing: .3px;
            line-height: 1.05;
            margin: 4px 0;
            padding: 4px;
            text-align: center;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .labels-6 .oc {
            font-size: 12.5px;
            padding: 3px;
        }

        .docs {
            border-collapse: collapse;
            margin-bottom: 4px;
            table-layout: fixed;
            width: 100%;
        }

        .docs td {
            border: .7px solid #6b7280;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8.2px;
            font-weight: 900;
            letter-spacing: .2px;
            line-height: 1.05;
            padding: 3px;
            text-align: center;
            text-transform: uppercase;
            width: 50%;
            word-wrap: break-word;
        }

        .labels-6 .docs td {
            font-size: 7.4px;
            padding: 2.5px;
        }

        .items {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .items th,
        .items td {
            border: .65px solid #9ca3af;
            padding: 2px 3px;
            vertical-align: top;
        }

        .items th {
            background: #eef2f7;
            color: #111827;
            font-size: 6.1px;
            font-weight: 900;
            line-height: 1.05;
            text-align: center;
            text-transform: uppercase;
        }

        .description {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 6.2px;
            font-weight: 800;
            letter-spacing: .2px;
            line-height: 1.12;
            overflow-wrap: anywhere;
            word-break: break-word;
            word-wrap: break-word;
        }

        .labels-6 .description {
            font-size: 5.7px;
        }

        .unit {
            font-size: 6px;
            line-height: 1.05;
            text-align: center;
            word-wrap: break-word;
        }

        .qty {
            font-size: 7.2px;
            font-weight: 900;
            text-align: right;
            white-space: nowrap;
        }

        .footer {
            border-top: .7px solid #cbd5e1;
            margin-top: 5px;
            padding-top: 4px;
        }

        .box-number {
            float: left;
            color: #111827;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 29px;
            font-weight: 900;
            letter-spacing: .3px;
            line-height: .9;
            text-transform: uppercase;
            width: 60%;
        }

        .labels-6 .box-number {
            font-size: 25px;
        }

        .box-number span {
            color: #6b7280;
            display: block;
            font-size: 6.2px;
            font-weight: 900;
            line-height: 1;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .qr {
            float: right;
            text-align: right;
            width: 36%;
        }

        .qr img {
            border: .65px solid #cbd5e1;
            height: 38px;
            padding: 2px;
            width: 38px;
        }

        .labels-6 .qr img {
            height: 32px;
            width: 32px;
        }

        .qr small {
            color: #6b7280;
            display: block;
            font-size: 5.4px;
            font-weight: 800;
            margin-top: 1px;
            text-transform: uppercase;
        }
    </style>
</head>

<body>
    @php
        $customerName = $labeling->customer?->business_name
            ?? $labeling->customer?->full_name
            ?? trim(($labeling->customer?->first_name ?? '') . ' ' . ($labeling->customer?->last_name ?? ''))
            ?: '-';
        $orderNumber = $labeling->customerPurchaseOrder?->purchase_order_number
            ?? $labeling->customerPurchaseOrder?->code
            ?? '-';
        $destination = $labeling->destination ?: '-';
        $companyName = $labeling->company?->business_name ?? $labeling->company?->trade_name ?? null;
        $companyRuc = $labeling->company?->ruc;
        $labelsPerPage = (int) ($labelsPerPage ?? 4);
        $rowsPerPage = $labelsPerPage === 6 ? 3 : 2;
        $destinationLength = mb_strlen((string) $destination, 'UTF-8');
        $destinationClass = $destinationLength > 55 ? 'is-very-long' : ($destinationLength > 34 ? 'is-long' : '');
    @endphp

    @foreach ($labeling->boxes->chunk($labelsPerPage) as $pageBoxes)
        <div class="sheet labels-{{ $labelsPerPage }}">
            <table class="label-grid">
                @for ($row = 0; $row < $rowsPerPage; $row++)
                    <tr>
                        @for ($col = 0; $col < 2; $col++)
                            @php
                                $slot = ($row * 2) + $col;
                                $box = $pageBoxes->get($slot);
                            @endphp

                            <td class="label-cell">
                                @if ($box)
                                    @php
                                        $boxObservation = trim((string) ($box->observation ?? $box->observations ?? ''));
                                    @endphp

                                    <div class="label">
                                        <div class="top">
                                            <div class="brand">
                                                @if (!empty($logoDataUri))
                                                    <img src="{{ $logoDataUri }}" class="rotulado-logo logo" alt="Dropaiv">
                                                @else
                                                    <strong>DROPAIV</strong>
                                                @endif
                                                @if ($companyName || $companyRuc)
                                                    <div class="company-info">
                                                        @if ($companyName)
                                                            <span class="rotulado-company-name">{{ $companyName }}</span>
                                                        @endif
                                                        @if ($companyRuc)
                                                            <span class="rotulado-company-ruc">RUC: {{ $companyRuc }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="rotulado-code code">{{ $labeling->code }}</div>
                                            </div>

                                            <div class="destination">
                                                <span class="rotulado-destino-label">Destino</span>
                                                <span class="rotulado-destino-value destination-value {{ $destinationClass }}">{{ $destination }}</span>
                                            </div>
                                            <div class="clear"></div>
                                        </div>

                                        <div class="observation-row">
                                            @if ($boxObservation !== '')
                                                <span class="observation-tag">{{ $boxObservation }}</span>
                                            @endif
                                        </div>

                                        <div class="customer">
                                            <span class="eyebrow highlight-font">Cliente</span>
                                            <div class="customer-name">{{ $customerName }}</div>
                                        </div>

                                        <div class="oc">OC N° {{ $orderNumber }}</div>

                                        <table class="docs">
                                            <tr>
                                                <td>Factura: {{ $labeling->invoice_number ?? '-' }}</td>
                                                <td>Guía: {{ $labeling->guide_number ?? '-' }}</td>
                                            </tr>
                                        </table>

                                        <table class="items">
                                            <thead>
                                                <tr>
                                                    <th>Descripción detallada</th>
                                                    <th style="width: 21%;">Unidad</th>
                                                    <th style="width: 15%;">Cantidad</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($box->items as $item)
                                                    <tr>
                                                        <td class="description">{{ $item->description }}</td>
                                                        <td class="unit">{{ $item->unit_name ?? '-' }}</td>
                                                        <td class="qty">{{ number_format((float) $item->quantity, 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="3" style="text-align:center;">Sin artículos registrados</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>

                                        <div class="footer">
                                            <div class="box-number">
                                                <span>Caja</span>
                                                {{ $box->box_label }}
                                            </div>
                                            <div class="qr">
                                                @if (!empty($qrCodes[$box->id] ?? null))
                                                    <img src="{{ $qrCodes[$box->id] }}" alt="QR {{ $box->box_label }}">
                                                @endif
                                                <small>Verificación</small>
                                            </div>
                                            <div class="clear"></div>
                                        </div>
                                    </div>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endfor
            </table>
        </div>
    @endforeach
</body>

</html>
