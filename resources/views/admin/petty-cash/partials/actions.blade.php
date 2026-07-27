<div class="petty-main-actions">
    @can('admin.petty-cash.show')
        <button type="button" class="btn btn-sm btn-info viewPettyCash" data-id="{{ $box->id }}" title="Ver detalle">
            <i class="fas fa-eye mr-1"></i> Ver
        </button>
    @endcan
    <div class="dropdown">
        <button class="btn btn-sm btn-light border dropdown-toggle petty-actions-trigger" type="button"
            data-toggle="dropdown" data-boundary="viewport" aria-haspopup="true" aria-expanded="false">
            <i class="fas fa-ellipsis-v mr-1"></i> Acciones
        </button>
        <div class="dropdown-menu dropdown-menu-right petty-actions-menu">
            @if(in_array($box->status, ['OPEN', 'IN_REVIEW'], true))
                <h6 class="dropdown-header">Acciones operativas</h6>
                @can('admin.petty-cash.update')
                    <button type="button" class="dropdown-item editPettyCash btn-edit-petty-cash" data-id="{{ $box->id }}" data-code="{{ $box->code }}"><i class="fas fa-edit text-warning"></i> Editar apertura</button>
                @endcan
                @can('admin.petty-cash.expenses.store')
                    <button type="button" class="dropdown-item addPettyCashExpense btn-create-petty-cash-expense" data-id="{{ $box->id }}" data-code="{{ $box->code }}"><i class="fas fa-receipt text-success"></i> Registrar gasto</button>
                @endcan
                @can('admin.petty-cash.replenishments.store')
                    @if($box->status === 'OPEN' && (float) $box->reimbursement_amount > 0)
                        <button type="button" class="dropdown-item btn-replenish-petty-cash" data-id="{{ $box->id }}" data-code="{{ $box->code }}"><i class="fas fa-sync-alt text-info"></i> Reponer caja</button>
                    @endif
                @endcan
                @can('admin.petty-cash.receipt-exchanges.store')
                    @if($box->pending_exchange_receipts_count > 0)
                        <button type="button" class="dropdown-item exchangePettyCashReceipts btn-exchange-petty-cash-receipts" data-id="{{ $box->id }}" data-code="{{ $box->code }}"><i class="fas fa-exchange-alt text-success"></i> Canjear recibos</button>
                    @endif
                @endcan
                <div class="dropdown-divider"></div>
                <h6 class="dropdown-header">Cierre</h6>
                @can('admin.petty-cash.close')
                    <button type="button" class="dropdown-item closePettyCash btn-close-petty-cash" data-id="{{ $box->id }}" data-code="{{ $box->code }}" @disabled($box->pending_expenses_count > 0)
                        title="{{ $box->pending_expenses_count > 0 ? 'Tiene gastos pendientes de aprobación.' : 'Cerrar caja' }}">
                        <i class="fas fa-lock text-secondary"></i> Cerrar caja
                    </button>
                @endcan
                @can('admin.petty-cash.destroy')
                    <button type="button" class="dropdown-item deletePettyCash btn-cancel-petty-cash text-danger" data-id="{{ $box->id }}" data-code="{{ $box->code }}"><i class="fas fa-ban"></i> Anular caja</button>
                @endcan
                <div class="dropdown-divider"></div>
            @endif
            <h6 class="dropdown-header">Exportación</h6>
            @can('admin.petty-cash.pdf')
                <a class="dropdown-item" href="{{ route('admin.petty-cash.pdf', $box) }}" target="_blank"><i class="fas fa-file-pdf text-danger"></i> Descargar PDF</a>
            @endcan
            @can('admin.petty-cash.excel')
                <a class="dropdown-item" href="{{ route('admin.petty-cash.excel', $box) }}"><i class="fas fa-file-excel text-success"></i> Exportar Excel</a>
            @endcan
        </div>
    </div>
</div>
