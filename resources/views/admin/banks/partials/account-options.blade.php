<option value="">Seleccione cuenta</option>
@foreach($accounts as $treasuryAccount)
<option value="{{$treasuryAccount->id}}" data-company="{{$treasuryAccount->company_id}}" data-currency="{{$treasuryAccount->currency?->code}}" data-symbol="{{$treasuryAccount->currency?->symbol}}">
    {{($treasuryAccount->company?->trade_name ?: $treasuryAccount->company?->business_name).' · '.($treasuryAccount->bank?->short_name ?: $treasuryAccount->bank?->description).' · '.$treasuryAccount->currency?->code.' · '.$treasuryAccount->account_number}}
</option>
@endforeach
