<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Kardex valorizado</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #263238; font-size: 9px; }
        h1 { margin: 0 0 4px; font-size: 17px; color: #0f766e; }
        .meta { margin-bottom: 12px; color: #607d8b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cfd8dc; padding: 4px; }
        th { background: #e7f5f3; color: #174f4a; text-align: center; }
        .group { background: #0f766e; color: #fff; }
        .number { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    @if ($format === 'print')
        <button class="no-print" onclick="window.print()">Imprimir</button>
    @endif
    <h1>Kardex valorizado por promedio ponderado móvil</h1>
    <div class="meta">Generado: {{ now()->format('d/m/Y H:i') }} · Movimientos: {{ number_format($movements->count()) }}</div>
    <table>
        <thead>
            <tr>
                <th rowspan="2">Fecha</th>
                <th rowspan="2">Movimiento</th>
                <th rowspan="2">Almacén / artículo</th>
                <th rowspan="2">Tipo / documento</th>
                <th colspan="3" class="group">Entradas</th>
                <th colspan="3" class="group">Salidas</th>
                <th colspan="3" class="group">Saldos</th>
            </tr>
            <tr>
                <th>Cantidad</th><th>C. unitario</th><th>C. total</th>
                <th>Cantidad</th><th>C. unitario</th><th>C. total</th>
                <th>Cantidad</th><th>C. promedio</th><th>Valor</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($movements as $movement)
                @php($symbol = $movement->currency?->symbol ?? $movement->currency?->code ?? 'S/')
                <tr>
                    <td class="center">{{ $movement->movement_date?->format('d/m/Y H:i') }}</td>
                    <td>{{ $movement->movement_number }}</td>
                    <td>{{ $movement->warehouse?->name }}<br>{{ $movement->article?->code }} · {{ $movement->article?->billing_name }}</td>
                    <td>{{ $movement->movement_type }}<br>{{ collect([$movement->document_type, $movement->document_series, $movement->document_number])->filter()->implode(' ') }}</td>
                    <td class="number">{{ number_format((float) $movement->quantity_in, 4) }}</td>
                    <td class="number">{{ (float) $movement->quantity_in > 0 ? $symbol.' '.number_format((float) $movement->unit_cost, 6) : '-' }}</td>
                    <td class="number">{{ $symbol }} {{ number_format((float) $movement->total_cost_in, 2) }}</td>
                    <td class="number">{{ number_format((float) $movement->quantity_out, 4) }}</td>
                    <td class="number">{{ (float) $movement->quantity_out > 0 ? $symbol.' '.number_format((float) $movement->unit_cost, 6) : '-' }}</td>
                    <td class="number">{{ $symbol }} {{ number_format((float) $movement->total_cost_out, 2) }}</td>
                    <td class="number">{{ number_format((float) $movement->balance_quantity, 4) }}</td>
                    <td class="number">{{ $symbol }} {{ number_format((float) $movement->average_unit_cost, 6) }}</td>
                    <td class="number">{{ $symbol }} {{ number_format((float) $movement->balance_total_cost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @if ($format === 'print')
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
