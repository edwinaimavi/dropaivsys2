<html><head><meta charset="UTF-8"></head><body>
<table border="1"><tr><th colspan="8">RENDICIÓN DE CAJA CHICA {{ $box->code }}</th></tr>
<tr><td colspan="2"><b>Empresa</b></td><td colspan="6">{{ $box->company->business_name ?? $box->company->trade_name }}</td></tr>
<tr><td colspan="2"><b>Periodo</b></td><td>{{ str_pad($box->period_month,2,'0',STR_PAD_LEFT) }}/{{ $box->period_year }}</td><td><b>Fondo</b></td><td>{{ $box->approved_fund }}</td><td><b>Gastado</b></td><td colspan="2">{{ $box->total_expenses }}</td></tr>
<tr><th>Ítem</th><th>Fecha</th><th>Tipo</th><th>Número</th><th>RUC</th><th>Proveedor</th><th>Concepto</th><th>Importe</th></tr>
@foreach($box->expenses as $expense)<tr><td>{{ $expense->item_number }}</td><td>{{ $expense->expense_date->format('d/m/Y') }}</td><td>{{ $expense->document_type }}</td><td>{{ $expense->document_number }}</td><td>{{ $expense->supplier_ruc }}</td><td>{{ $expense->supplier_name }}</td><td>{{ $expense->concept }}</td><td>{{ $expense->amount }}</td></tr>@endforeach
<tr><td colspan="7"><b>TOTAL</b></td><td><b>{{ $box->total_expenses }}</b></td></tr></table>
</body></html>
