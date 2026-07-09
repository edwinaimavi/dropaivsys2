<!doctype html>
<html lang="es">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta charset="utf-8">
    <title>{{ $labeling->code }}</title>
    <style>
        @page {
            margin: 9mm;
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 9px;
            margin: 0;
        }

        .sheet {
            page-break-after: always;
            width: 100%;
        }

        .sheet:last-child {
            page-break-after: auto;
        }

        .label-stack {
            border-collapse: separate;
            border-spacing: 0 5mm;
            table-layout: fixed;
            width: 100%;
        }

        .label-cell {
            height: 132mm;
            padding: 0;
            vertical-align: top;
        }

        .label {
            border: 1.5px solid #111827;
            height: 132mm;
            overflow: hidden;
            padding: 8px 9px;
            page-break-inside: avoid;
            position: relative;
        }

        .header {
            border-bottom: 1px solid #111827;
            height: 38px;
            padding-bottom: 5px;
        }

        .logo {
            float: left;
            max-height: 32px;
            max-width: 120px;
        }

        .code {
            float: right;
            font-size: 9px;
            font-weight: bold;
            line-height: 1.25;
            text-align: right;
            width: 55%;
        }

        .clear {
            clear: both;
        }

        .title-row {
            margin: 7px 0 6px;
            min-height: 22px;
        }

        .title {
            float: left;
            font-size: 13px;
            font-weight: bold;
            line-height: 1.2;
            text-transform: uppercase;
            width: 62%;
        }

        .box-label {
            background: #111827;
            color: #fff;
            float: right;
            font-size: 13px;
            font-weight: bold;
            line-height: 1.15;
            padding: 5px 8px;
            text-align: center;
            width: 31%;
        }

        .meta {
            border: 1px solid #d1d5db;
            margin-bottom: 7px;
            padding: 5px 6px;
        }

        .meta-row {
            line-height: 1.35;
            margin-bottom: 1px;
        }

        .meta-label {
            display: inline-block;
            font-weight: bold;
            width: 55px;
        }

        .items {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        .items th,
        .items td {
            border: 1px solid #111827;
            padding: 4px;
            vertical-align: top;
        }

        .items th {
            background: #f3f4f6;
            font-size: 8px;
            font-weight: bold;
            line-height: 1.15;
            text-align: center;
            text-transform: uppercase;
        }

        .description {
            font-size: 8px;
            line-height: 1.22;
            word-wrap: break-word;
        }

        .unit {
            font-size: 7.8px;
            text-align: center;
            word-wrap: break-word;
        }

        .qty {
            font-size: 9px;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }

        .box-observation {
            background: #fff8db;
            border: 1px solid #e0b84f;
            margin-top: 6px;
            padding: 5px 6px;
        }

        .box-observation-title {
            color: #7a5600;
            display: block;
            font-size: 7.5px;
            font-weight: bold;
            margin-bottom: 2px;
            text-transform: uppercase;
        }

        .box-observation-text {
            font-size: 8px;
            line-height: 1.25;
            word-wrap: break-word;
        }

        .footer {
            bottom: 6px;
            color: #4b5563;
            font-size: 7px;
            left: 9px;
            position: absolute;
            right: 9px;
            text-align: center;
        }

        .empty-label {
            height: 132mm;
        }
    </style>
</head>

<body>
    @foreach ($labeling->boxes->chunk(2) as $pageBoxes)
        <div class="sheet">
            <table class="label-stack">
                @for ($slot = 0; $slot < 2; $slot++)
                    @php
                        $box = $pageBoxes->get($slot);
                    @endphp

                    <tr>
                        <td class="label-cell">
                            @if ($box)
                                @php
                                    $boxObservation = trim((string) ($box->observation ?? $box->observations ?? ''));
                                @endphp

                                <div class="label">
                                    <div class="header">
                                        @if (!empty($logoDataUri))
                                            <img src="{{ $logoDataUri }}" class="logo" alt="Dropaiv">
                                        @endif

                                        <div class="code">
                                            {{ $labeling->code }}<br>
                                            OC N°:
                                            {{ $labeling->customerPurchaseOrder?->purchase_order_number ?? $labeling->customerPurchaseOrder?->code ?? '-' }}
                                        </div>
                                        <div class="clear"></div>
                                    </div>

                                    <div class="title-row">
                                        <div class="title">Rótulo de caja</div>
                                        <div class="box-label">Caja {{ $box->box_label }}</div>
                                        <div class="clear"></div>
                                    </div>

                                    <div class="meta">
                                        <div class="meta-row">
                                            <span class="meta-label">Factura:</span>
                                            {{ $labeling->invoice_number ?? '-' }}
                                        </div>
                                        <div class="meta-row">
                                            <span class="meta-label">Guía:</span>
                                            {{ $labeling->guide_number ?? '-' }}
                                        </div>
                                        <div class="meta-row">
                                            <span class="meta-label">Cliente:</span>
                                            {{ $labeling->customer?->business_name ?? $labeling->customer?->full_name ?? '-' }}
                                        </div>
                                        <div class="meta-row">
                                            <span class="meta-label">Empresa:</span>
                                            {{ $labeling->company?->trade_name ?? $labeling->company?->business_name ?? 'DROPAIV S.A.C.' }}
                                        </div>
                                    </div>

                                    <table class="items">
                                        <thead>
                                            <tr>
                                                <th>Descripción detallada</th>
                                                <th style="width: 21%;">Unidad de medida</th>
                                                <th style="width: 14%;">Cantidad</th>
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
                                                    <td colspan="3" style="text-align: center;">Sin artículos registrados</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>

                                    @if ($boxObservation !== '')
                                        <div class="box-observation">
                                            <span class="box-observation-title">Observación de caja</span>
                                            <div class="box-observation-text">{{ $boxObservation }}</div>
                                        </div>
                                    @endif

                                    <div class="footer">Documento generado por DropaivSys2</div>
                                </div>
                            @else
                                <div class="empty-label"></div>
                            @endif
                        </td>
                    </tr>
                @endfor
            </table>
        </div>
    @endforeach
</body>

</html>
