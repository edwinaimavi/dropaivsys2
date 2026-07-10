<!doctype html>
<html lang="es">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="utf-8">
    <title>{{ $labeling->code }}</title>
    <style>
        @page {
            margin: 6mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 7.4px;
            margin: 0;
        }

        .sheet {
            page-break-after: always;
            width: 100%;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .label-grid {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .label-cell {
            height: 139mm;
            padding: 2mm;
            vertical-align: top;
            width: 50%;
        }

        .label {
            border: 1.2px solid #111827;
            height: 135mm;
            overflow: hidden;
            padding: 5px 6px;
            page-break-inside: avoid;
            position: relative;
        }

        .top {
            border-bottom: 1px solid #111827;
            min-height: 30px;
            padding-bottom: 3px;
        }

        .logo {
            float: left;
            max-height: 23px;
            max-width: 82px;
        }

        .destination {
            float: right;
            line-height: 1.05;
            text-align: right;
            width: 62%;
        }

        .destination-label {
            display: block;
            font-size: 6.5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .destination-value {
            display: block;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .tag-row {
            height: 16px;
            margin-top: 4px;
        }

        .observation-tag {
            background: #dc2626;
            border: 1px solid #991b1b;
            color: #fff;
            display: inline-block;
            font-size: 8px;
            font-weight: bold;
            max-width: 100%;
            padding: 2px 7px;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .main {
            text-align: center;
        }

        .customer-label {
            font-size: 6.5px;
            font-weight: bold;
            margin-top: 2px;
            text-transform: uppercase;
        }

        .customer-name {
            font-size: 12px;
            font-weight: bold;
            line-height: 1.08;
            margin-top: 1px;
            min-height: 25px;
            text-transform: uppercase;
            word-wrap: break-word;
        }

        .oc {
            border-bottom: 1px solid #111827;
            border-top: 1px solid #111827;
            font-size: 14px;
            font-weight: bold;
            line-height: 1.1;
            margin: 4px 0 3px;
            padding: 3px 0;
            text-transform: uppercase;
        }

        .docs {
            border-collapse: collapse;
            margin-bottom: 4px;
            table-layout: fixed;
            width: 100%;
        }

        .docs td {
            border: 1px solid #111827;
            font-size: 7.8px;
            font-weight: bold;
            padding: 2px 3px;
            text-align: center;
            text-transform: uppercase;
            width: 50%;
            word-wrap: break-word;
        }

        .items {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .items th,
        .items td {
            border: 1px solid #111827;
            padding: 2px 3px;
            vertical-align: top;
        }

        .items th {
            background: #f3f4f6;
            font-size: 6.4px;
            font-weight: bold;
            line-height: 1.05;
            text-align: center;
            text-transform: uppercase;
        }

        .description {
            font-size: 6.5px;
            line-height: 1.1;
            word-wrap: break-word;
        }

        .unit {
            font-size: 6.3px;
            line-height: 1.05;
            text-align: center;
            word-wrap: break-word;
        }

        .qty {
            font-size: 7.2px;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }

        .box-number {
            bottom: 5px;
            font-size: 23px;
            font-weight: bold;
            left: 0;
            line-height: 1;
            position: absolute;
            right: 0;
            text-align: center;
        }

        .empty-label {
            height: 135mm;
        }

        .clear {
            clear: both;
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
    @endphp

    @foreach ($labeling->boxes->chunk(4) as $pageBoxes)
        <div class="sheet">
            <table class="label-grid">
                @for ($row = 0; $row < 2; $row++)
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
                                            @if (!empty($logoDataUri))
                                                <img src="{{ $logoDataUri }}" class="logo" alt="Dropaiv">
                                            @endif

                                            <div class="destination">
                                                <span class="destination-label">Destino</span>
                                                <span class="destination-value">{{ $destination }}</span>
                                            </div>
                                            <div class="clear"></div>
                                        </div>

                                        <div class="tag-row">
                                            @if ($boxObservation !== '')
                                                <span class="observation-tag">{{ $boxObservation }}</span>
                                            @endif
                                        </div>

                                        <div class="main">
                                            <div class="customer-label">Cliente</div>
                                            <div class="customer-name">{{ $customerName }}</div>
                                            <div class="oc">OC N&deg; {{ $orderNumber }}</div>
                                        </div>

                                        <table class="docs">
                                            <tr>
                                                <td>Factura: {{ $labeling->invoice_number ?? '-' }}</td>
                                                <td>Guia: {{ $labeling->guide_number ?? '-' }}</td>
                                            </tr>
                                        </table>

                                        <table class="items">
                                            <thead>
                                                <tr>
                                                    <th>Descripcion detallada</th>
                                                    <th style="width: 23%;">Unidad de medida</th>
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
                                                        <td colspan="3" style="text-align:center;">Sin articulos registrados</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>

                                        <div class="box-number">{{ $box->box_label }}</div>
                                    </div>
                                @else
                                    <div class="empty-label"></div>
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
