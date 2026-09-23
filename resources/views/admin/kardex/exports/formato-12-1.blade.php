<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Formato 12.1</title>
<style>
@page { margin: 7mm; } body { font-family: DejaVu Sans, Arial, sans-serif; color:#111; font-size:9px; } .sheet{page-break-after:always}.sheet:last-child{page-break-after:auto}.title{text-align:center;font-size:12px;font-weight:bold;margin-bottom:10px} table{border-collapse:collapse;width:100%;margin-bottom:10px}th,td{border:1px solid #555;padding:3px 4px;vertical-align:middle}th{background:#e8ecef;text-align:center}.meta th{text-align:left;width:18%}.number{text-align:right;white-space:nowrap;font-family:DejaVu Sans Mono,monospace}.warning{border:1px solid #b8860b;background:#fff7d6;padding:6px;margin-bottom:8px}.empty{padding:12px;text-align:center;border:1px solid #999}
</style>
</head><body>
@if ($report['incomplete_snapshot_count'] > 0)
<div class="warning">INFORMACIÓN HISTÓRICA INCOMPLETA: {{ $report['incomplete_snapshot_count'] }} movimiento(s) requieren regularización de snapshots.</div>
@endif
@forelse ($report['registers'] as $register)
<section class="sheet">
<div class="title">FORMATO 12.1<br>REGISTRO DEL INVENTARIO PERMANENTE EN UNIDADES FÍSICAS</div>
<table class="meta">
<tr><th>PERÍODO:</th><td>{{ $report['period'] }}</td><th>RUC:</th><td>{{ $report['company']->ruc }}</td></tr>
<tr><th>RAZÓN SOCIAL:</th><td colspan="3">{{ $report['company']->business_name }}</td></tr>
<tr><th>ESTABLECIMIENTO:</th><td>{{ $register['establishment_code'] ?: '—' }}</td><th>CÓDIGO DE LA EXISTENCIA:</th><td>{{ $register['existence_code'] ?: '—' }}</td></tr>
<tr><th>TIPO (TABLA 5):</th><td>{{ $register['existence_type_code'] ?: '—' }}</td><th>DESCRIPCIÓN:</th><td>{{ $register['description'] ?: '—' }}</td></tr>
<tr><th>UNIDAD (TABLA 6):</th><td colspan="3">{{ $register['unit_code'] ?: '—' }}</td></tr>
</table>
<table><thead><tr><th colspan="4">DOCUMENTO DE TRASLADO / COMPROBANTE / DOCUMENTO INTERNO</th><th rowspan="2">TIPO OPERACIÓN<br>TABLA 12</th><th rowspan="2">ENTRADAS</th><th rowspan="2">SALIDAS</th><th rowspan="2">SALDO FINAL</th></tr><tr><th>FECHA</th><th>TIPO TABLA 10</th><th>SERIE</th><th>NÚMERO</th></tr></thead><tbody>
@if ($register['opening_balance'] !== '0.0000')
<tr><td>{{ $report['period_start']->format('d/m/Y') }}</td><td></td><td></td><td>SALDO INICIAL</td><td>SALDO INICIAL</td><td class="number">0.0000</td><td class="number">0.0000</td><td class="number">{{ $register['opening_balance'] }}</td></tr>
@endif
@foreach ($register['rows'] as $row)
<tr><td>{{ $row['document_date']?->format('d/m/Y') }}</td><td>{{ $row['document_type_code'] }}</td><td>{{ $row['document_series'] }}</td><td>{{ $row['document_number'] ?: 'Mov. '.$row['movement_number'] }}</td><td>{{ $row['operation_type_code'] }}</td><td class="number">{{ $row['quantity_in'] }}</td><td class="number">{{ $row['quantity_out'] }}</td><td class="number">{{ $row['balance'] }}</td></tr>
@endforeach
</tbody><tfoot><tr><th colspan="5" style="text-align:right">TOTALES</th><th class="number">{{ $register['total_entries'] }}</th><th class="number">{{ $register['total_exits'] }}</th><th class="number">{{ $register['final_balance'] }}</th></tr></tfoot></table>
</section>
@empty
<div class="empty">No existen movimientos Kardex para los filtros seleccionados.</div>
@endforelse
</body></html>
