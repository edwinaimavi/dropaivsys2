<div class="btn-group btn-group-sm">
    @can('admin.general-cash.show')
        <button class="btn btn-outline-info btn-general-cash-view" data-id="{{ $box->id }}" title="Ver detalle"><i class="fas fa-eye"></i></button>
    @endcan
    @canany(['admin.general-cash.update','admin.general-cash.replenishments','admin.general-cash.expenses.store','admin.general-cash.close'])
        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" data-toggle="dropdown"><span class="sr-only">Acciones</span></button>
        <div class="dropdown-menu dropdown-menu-right general-cash-actions-menu">
            @can('admin.general-cash.update')<button class="dropdown-item btn-general-cash-edit" data-id="{{ $box->id }}"><i class="fas fa-edit text-info"></i> Editar caja</button>@endcan
            @if($box->status === \App\Models\GeneralCashBox::STATUS_ACTIVE)
                @can('admin.general-cash.replenishments')<button class="dropdown-item btn-general-cash-fund" data-id="{{ $box->id }}"><i class="fas fa-university text-primary"></i> Ingresar desde banco</button>@endcan
                @can('admin.general-cash.expenses.store')<button class="dropdown-item btn-general-cash-expense" data-id="{{ $box->id }}"><i class="fas fa-receipt text-danger"></i> Registrar gasto</button>@endcan
                @can('admin.general-cash.close')<button class="dropdown-item btn-general-cash-reconcile" data-id="{{ $box->id }}"><i class="fas fa-balance-scale text-warning"></i> Arqueo / cierre</button>@endcan
            @endif
        </div>
    @endcanany
</div>
