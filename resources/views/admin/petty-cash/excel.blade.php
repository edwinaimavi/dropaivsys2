<html><head><meta charset="UTF-8"></head><body>
<table border="1"><tr><th colspan="8">RENDICIÓN DE CAJA CHICA {{ $box->code }}</th></tr>
<tr><td colspan="2"><b>Empresa</b></td><td colspan="6">{{ $box->company->business_name ?? $box->company->trade_name }}</td></tr>
<tr><td><b>Apertura</b></td><td>{{ $box->start_date->format('d/m/Y') }}</td><td><b>Cierre</b></td><td>{{ $box->closed_at?->format('d/m/Y H:i') ?? 'Pendiente por gerencia' }}</td><td><b>Monto aprobado</b></td><td>{{ $box->approved_amount_snapshot ?? $box->opening_amount }}</td><td><b>Fondo inicial entregado</b></td><td>{{ $box->opening_amount }}</td></tr>
<tr><th>Ítem</th><th>Fecha</th><th>Tipo</th><th>Número</th><th>RUC</th><th>Proveedor</th><th>Concepto</th><th>Importe</th></tr>
@foreach($box->expenses as $expense)<tr><td>{{ $expense->item_number }}</td><td>{{ $expense->expense_date->format('d/m/Y') }}</td><td>{{ $expense->document_type }}</td><td>{{ $expense->document_full_number }}</td><td>{{ $expense->supplier_ruc }}</td><td>{{ $expense->supplier_name }}</td><td>{{ $expense->concept }}</td><td>{{ $expense->amount }}</td></tr>@endforeach
<tr><td colspan="7"><b>TOTAL</b></td><td><b>{{ $box->total_expenses }}</b></td></tr></table>
</body></html>
