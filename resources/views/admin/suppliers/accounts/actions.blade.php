 <div class="btn-group btn-group-sm" role="group" aria-label="Actions">

     <button type="button" class="btn btn-outline-primary editSupplierAccount" data-id="{{ $item->id }}"
         data-bank_id="{{ $item->bank_id }}" data-currency_id="{{ $item->currency_id }}"
         data-account_holder="{{ $item->account_holder }}" data-account_number="{{ $item->account_number }}"
         data-cci="{{ $item->cci }}" data-is_detraction="{{ $item->is_detraction }}"
         data-status="{{ $item->status }}" data-observation="{{ $item->observation }}">
         <i class="fas fa-pen"></i>
     </button>

     <button type="button" class="btn btn-outline-danger deleteSupplierAccount" data-id="{{ $item->id }}">
         <i class="fas fa-trash"></i>
     </button>

 </div>
