<div class="btn-group btn-group-sm" role="group">
    <button type="button" class="btn btn-outline-info btn-bank-view" data-id="{{$account->id}}" title="Ver cuenta">
        <i class="fas fa-eye"></i>
    </button>
    @canany(['admin.banks.edit', 'admin.banks.movements.create', 'admin.banks.transfers.create', 'admin.banks.reconciliations.create'])
        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown" title="Más acciones"></button>
        <div class="dropdown-menu dropdown-menu-right bank-actions-menu">
            @can('admin.banks.edit')
                <button class="dropdown-item btn-bank-opening" data-id="{{$account->id}}"><i class="fas fa-sliders-h text-primary"></i> Configurar saldo inicial</button>
            @endcan
            @can('admin.banks.movements.create')
                <button class="dropdown-item btn-bank-income" data-id="{{$account->id}}"><i class="fas fa-arrow-down text-success"></i> Registrar ingreso</button>
                <button class="dropdown-item btn-bank-expense" data-id="{{$account->id}}"><i class="fas fa-arrow-up text-danger"></i> Registrar egreso</button>
            @endcan
            @can('admin.banks.transfers.create')
                <button class="dropdown-item btn-bank-transfer" data-id="{{$account->id}}"><i class="fas fa-exchange-alt text-info"></i> Transferir</button>
            @endcan
            @can('admin.banks.reconciliations.create')
                <button class="dropdown-item btn-bank-reconcile" data-id="{{$account->id}}"><i class="fas fa-check-double text-warning"></i> Conciliar</button>
            @endcan
        </div>
    @endcanany
</div>
