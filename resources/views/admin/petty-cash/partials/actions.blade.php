<div class="btn-group shadow-sm" role="group">
    @can('admin.petty-cash.show')
        <button class="btn btn-info viewPettyCash" data-id="{{ $box->id }}" title="Ver detalle"><i class="fas fa-eye"></i></button>
    @endcan
    @can('admin.petty-cash.update')
        @if(in_array($box->status, ['OPEN', 'IN_REVIEW'], true))
            <button class="btn btn-warning editPettyCash" data-id="{{ $box->id }}" title="Editar"><i class="fas fa-edit"></i></button>
        @endif
    @endcan
    @can('admin.petty-cash.expenses.store')
        @if(in_array($box->status, ['OPEN', 'IN_REVIEW'], true))
            <button class="btn btn-success addPettyCashExpense" data-id="{{ $box->id }}" title="Registrar gasto"><i class="fas fa-receipt"></i></button>
        @endif
    @endcan
    @can('admin.petty-cash.close')
        @if(in_array($box->status, ['OPEN', 'IN_REVIEW'], true))
            <button class="btn btn-secondary closePettyCash"
                data-id="{{ $box->id }}"
                @disabled($box->pending_expenses_count > 0)
                data-toggle="tooltip"
                title="{{ $box->pending_expenses_count > 0 ? 'Tiene gastos pendientes de aprobación.' : 'Cerrar caja' }}"
                aria-label="{{ $box->pending_expenses_count > 0 ? 'No se puede cerrar: tiene gastos pendientes de aprobación' : 'Cerrar caja' }}">
                <i class="fas fa-lock"></i>
            </button>
        @endif
    @endcan
    @can('admin.petty-cash.replenishments.store')
        @if($box->status === 'OPEN')
            <button class="btn btn-success btn-replenish-petty-cash" data-id="{{ $box->id }}"
                data-toggle="tooltip" title="Reponer caja" aria-label="Reponer caja">
                <i class="fas fa-sync-alt"></i>
            </button>
        @endif
    @endcan
    @can('admin.petty-cash.pdf')
        <a class="btn btn-danger" href="{{ route('admin.petty-cash.pdf', $box) }}" target="_blank" title="PDF"><i class="fas fa-file-pdf"></i></a>
    @endcan
    @can('admin.petty-cash.excel')
        <a class="btn btn-success" href="{{ route('admin.petty-cash.excel', $box) }}" title="Excel"><i class="fas fa-file-excel"></i></a>
    @endcan
    @can('admin.petty-cash.destroy')
        @if(!in_array($box->status, ['CANCELLED', 'REIMBURSED'], true))
            <button class="btn btn-outline-danger deletePettyCash" data-id="{{ $box->id }}" title="Anular"><i class="fas fa-trash"></i></button>
        @endif
    @endcan
</div>
