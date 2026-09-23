<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Formato 13.1</title>
<style>
@page { margin: 6mm; } body { font-family: DejaVu Sans, Arial, sans-serif; color:#111; font-size:7px; } .sheet{page-break-after:always}.sheet:last-child{page-break-after:auto}.title{text-align:center;font-size:11px;font-weight:bold;margin-bottom:8px}table{border-collapse:collapse;width:100%;margin-bottom:8px}th,td{border:1px solid #555;padding:2px 3px;vertical-align:middle}th{background:#e8ecef;text-align:center}.meta th{text-align:left;width:18%}.number{text-align:right;white-space:nowrap;font-family:DejaVu Sans Mono,monospace}.warning{border:1px solid #b8860b;background:#fff7d6;padding:5px;margin-bottom:7px}.error{border:1px solid #a00;background:#ffecec;padding:5px;margin-bottom:7px}.empty{padding:12px;text-align:center;border:1px solid #999}
</style>
</head><body>
@if ($report['incomplete_snapshot_count'] > 0)<div class="warning">INFORMACIÓN HISTÓRICA INCOMPLETA: {{ $report['incomplete_snapshot_count'] }} movimiento(s) requieren regularización de snapshots.</div>@endif
@if ($report['valuation_inconsistency_count'] > 0)<div class="error">INCONSISTENCIAS DE VALORIZACIÓN: {{ $report['valuation_inconsistency_count'] }} existencia(s) presentan cantidad cero con valor residual.</div>@endif
@forelse ($report['registers'] as $register)
<section class="sheet">
<div class="title">FORMATO 13.1<br>REGISTRO DEL INVENTARIO PERMANENTE VALORIZADO<br>DETALLE DEL INVENTARIO VALORIZADO</div>
<table class="meta">
<tr><th>PERÍODO:</th><td>{{ $report['period'] }}</td><th>RUC:</th><td>{{ $report['company']->ruc }}</td></tr>
<tr><th>RAZÓN SOCIAL:</th><td colspan="3">{{ $report['company']->business_name }}</td></tr>
<tr><th>ESTABLECIMIENTO:</th><td>{{ $register['establishment_code'] ?: '—' }}</td><th>CÓDIGO DE LA EXISTENCIA:</th><td>{{ $register['existence_code'] ?: '—' }}</td></tr>
<tr><th>TIPO (TABLA 5):</th><td>{{ $register['existence_type_code'] ?: '—' }}</td><th>DESCRIPCIÓN:</th><td>{{ $register['description'] ?: '—' }}</td></tr>
<tr><th>UNIDAD (TABLA 6):</th><td>{{ $register['unit_code'] ?: '—' }}</td><th>MÉTODO DE VALUACIÓN (TABLA 14):</th><td>{{ collect([$register['valuation_method_code'], $register['valuation_method_description']])->filter()->implode(' — ') ?: '—' }}</td></tr>
</table>
<table><thead><tr><th colspan="4">DOCUMENTO</th><th rowspan="2">TIPO OPERACIÓN<br>TABLA 12</th><th colspan="3">ENTRADAS</th><th colspan="3">SALIDAS</th><th colspan="3">SALDO FINAL</th></tr><tr><th>FECHA</th><th>TIPO<br>TABLA 10</th><th>SERIE</th><th>NÚMERO</th><th>CANTIDAD</th><th>COSTO UNITARIO</th><th>COSTO TOTAL</th><th>CANTIDAD</th><th>COSTO UNITARIO</th><th>COSTO TOTAL</th><th>CANTIDAD</th><th>COSTO UNITARIO</th><th>COSTO TOTAL</th></tr></thead><tbody>
@if ($register['initial_quantity'] !== '0.0000' || $register['initial_total_cost'] !== '0.00')
<tr><td>{{ $report['period_start']->format('d/m/Y') }}</td><td></td><td></td><td>SALDO INICIAL</td><td>SALDO INICIAL</td><td class="number">0.0000</td><td></td><td class="number">0.00</td><td class="number">0.0000</td><td></td><td class="number">0.00</td><td class="number">{{ $register['initial_quantity'] }}</td><td class="number">{{ $register['initial_average_unit_cost'] }}</td><td class="number">{{ $register['initial_total_cost'] }}</td></tr>
@endif
@foreach ($register['rows'] as $row)
<tr><td>{{ $row['document_date']?->format('d/m/Y') }}</td><td>{{ $row['document_type_code'] }}</td><td>{{ $row['document_series'] }}</td><td>{{ $row['document_number'] ?: 'Mov. '.$row['movement_number'] }}</td><td>{{ $row['operation_type_code'] }}</td><td class="number">{{ $row['quantity_in'] }}</td><td class="number">{{ $row['entry_unit_cost'] ?? '' }}</td><td class="number">{{ $row['total_cost_in'] }}</td><td class="number">{{ $row['quantity_out'] }}</td><td class="number">{{ $row['exit_unit_cost'] ?? '' }}</td><td class="number">{{ $row['total_cost_out'] }}</td><td class="number">{{ $row['balance_quantity'] }}</td><td class="number">{{ $row['balance_average_unit_cost'] }}</td><td class="number">{{ $row['balance_total_cost'] }}</td></tr>
@endforeach
</tbody><tfoot><tr><th colspan="5" style="text-align:right">TOTALES</th><th class="number">{{ $register['total_quantity_in'] }}</th><th></th><th class="number">{{ $register['total_cost_in'] }}</th><th class="number">{{ $register['total_quantity_out'] }}</th><th></th><th class="number">{{ $register['total_cost_out'] }}</th><th class="number">{{ $register['final_quantity'] }}</th><th class="number">{{ $register['final_average_unit_cost'] }}</th><th class="number">{{ $register['final_total_cost'] }}</th></tr></tfoot></table>
</section>
@empty
<div class="empty">No existen movimientos Kardex para los filtros seleccionados.</div>
@endforelse
</body></html>
