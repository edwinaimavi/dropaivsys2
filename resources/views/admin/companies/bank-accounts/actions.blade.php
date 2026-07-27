<div class="btn-group btn-group-sm" role="group" aria-label="Acciones">
    @can('admin.company-bank-accounts.update')
        <button type="button" class="btn btn-outline-primary btn-edit-company-bank-account"
            data-account="{{ json_encode([
                "id" => $account->id,
                "bank_id" => $account->bank_id,
                "currency_id" => $account->currency_id,
                "account_holder" => $account->account_holder,
                "account_number" => $account->account_number,
                "cci" => $account->cci,
                "is_detraction" => $account->is_detraction,
                "status" => $account->status,
                "observation" => $account->observation,
            ]) }}"
            title="Editar">
            <i class="fas fa-pen"></i>
        </button>
    @endcan

    @can('admin.company-bank-accounts.destroy')
        <button type="button" class="btn btn-outline-danger btn-delete-company-bank-account"
            data-id="{{ $account->id }}" title="Eliminar">
            <i class="fas fa-trash"></i>
        </button>
    @endcan
</div>
