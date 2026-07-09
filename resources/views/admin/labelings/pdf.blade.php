<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>{{ $labeling->code }}</title>
    <style>
        @page {
            margin: 12mm;
        }

        body {
            color: #111;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            margin: 0;
            text-transform: uppercase;
        }

        .label {
            border: 1px solid #222;
            height: 126mm;
            padding: 7px;
            position: relative;
        }

        .cell {
            display: inline-block;
            vertical-align: top;
            width: 49.2%;
            margin-bottom: 6px;
        }

        .cell:nth-child(odd) {
            margin-right: 1%;
        }

        .page-break {
            page-break-after: always;
        }

        .header {
            border-bottom: 1px solid #333;
            min-height: 34px;
            padding-bottom: 5px;
        }

        .logo {
            float: left;
            max-height: 28px;
            max-width: 92px;
        }

        .code {
            float: right;
            font-size: 10px;
            font-weight: bold;
            text-align: right;
        }

        .clearfix {
            clear: both;
        }

        .title {
            font-size: 11px;
            font-weight: bold;
            margin: 5px 0;
            text-align: center;
        }

        .meta {
            line-height: 1.35;
            margin-bottom: 5px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #333;
            padding: 3px;
        }

        th {
            background: #eee;
            font-size: 8px;
            text-align: center;
        }

        td.qty {
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }

        .box-number {
            bottom: 8px;
            font-size: 34px;
            font-weight: bold;
            left: 0;
            position: absolute;
            right: 0;
            text-align: center;
        }
    </style>
</head>

<body>
    @foreach ($labeling->boxes->chunk(4) as $chunk)
        <div>
            @foreach ($chunk as $box)
                <div class="cell">
                    <div class="label">
                        <div class="header">
                            @if (!empty($logoPath) && file_exists($logoPath))
                                <img src="{{ $logoPath }}" class="logo">
                            @endif
                            <div class="code">
                                {{ $labeling->code }}<br>
                                OC N°: {{ $labeling->customerPurchaseOrder?->purchase_order_number ?? $labeling->customerPurchaseOrder?->code }}
                            </div>
                            <div class="clearfix"></div>
                        </div>

                        <div class="title">RÓTULO DE CAJA</div>

                        <div class="meta">
                            <strong>FACTURA:</strong> {{ $labeling->invoice_number ?? '-' }}
                            &nbsp;&nbsp;
                            <strong>GUÍA:</strong> {{ $labeling->guide_number ?? '-' }}<br>
                            <strong>CLIENTE:</strong> {{ $labeling->customer?->business_name ?? $labeling->customer?->full_name ?? '-' }}<br>
                            <strong>EMPRESA:</strong> {{ $labeling->company?->trade_name ?? $labeling->company?->business_name ?? '-' }}
                        </div>

                        <table>
                            <thead>
                                <tr>
                                    <th>Descripción detallada</th>
                                    <th style="width: 22%;">Unidad</th>
                                    <th style="width: 18%;">Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($box->items as $item)
                                    <tr>
                                        <td>{{ $item->description }}</td>
                                        <td>{{ $item->unit_name ?? '-' }}</td>
                                        <td class="qty">{{ number_format((float) $item->quantity, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="box-number">{{ $box->box_label }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>

</html>
