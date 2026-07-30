<x-table-actions-dropdown label="Acciones del proveedor">
    <x-slot name="main">
        <button type="button" class="btn btn-sm btn-success dp-action-main viewSupplier" title="Ver proveedor"
            data-id="{{ $supplier->id }}" data-ruc="{{ $supplier->ruc }}"
            data-business_name="{{ $supplier->business_name }}" data-short_name="{{ $supplier->short_name }}"
            data-address="{{ $supplier->address }}" data-ubigeo="{{ $supplier->ubigeo->full_name ?? '—' }}"
            data-supplier_type="{{ $supplier->supplier_type }}" data-payment_condition="{{ $supplier->payment_condition }}"
            data-contact_name="{{ $supplier->contact_name }}" data-email="{{ $supplier->email }}"
            data-phone="{{ $supplier->phone }}" data-igv_percentage="{{ $supplier->igv_percentage }}"
            data-observation="{{ $supplier->observation }}" data-status="{{ $supplier->status }}"
            data-created_by="{{ $supplier->creator->name ?? '—' }}" data-updated_by="{{ $supplier->editor->name ?? '—' }}"
            data-created_at="{{ $supplier->created_at ? $supplier->created_at->format('d/m/Y H:i') : '—' }}"
            data-updated_at="{{ $supplier->updated_at ? $supplier->updated_at->format('d/m/Y H:i') : '—' }}">
            <i class="fas fa-eye mr-1"></i> Ver
        </button>
    </x-slot>
    <x-slot name="menu">
        <h6 class="dropdown-header">Acciones operativas</h6>
        <button type="button" class="dropdown-item bankAccountsSupplier" title="Cuentas bancarias"
            data-id="{{ $supplier->id }}" data-business_name="{{ $supplier->business_name }}"
            data-ruc="{{ $supplier->ruc }}">
            <i class="fas fa-university text-success"></i> Cuentas bancarias
        </button>
        <button type="button" class="dropdown-item editSupplier" title="Editar proveedor"
            data-id="{{ $supplier->id }}" data-ruc="{{ $supplier->ruc }}"
            data-business_name="{{ $supplier->business_name }}" data-short_name="{{ $supplier->short_name }}"
            data-address="{{ $supplier->address }}" data-ubigeo_id="{{ $supplier->ubigeo_id }}"
            data-ubigeo_text="{{ $supplier->ubigeo->full_name ?? '' }}"
            data-supplier_type="{{ $supplier->supplier_type }}" data-payment_condition="{{ $supplier->payment_condition }}"
            data-contact_name="{{ $supplier->contact_name }}" data-email="{{ $supplier->email }}"
            data-phone="{{ $supplier->phone }}" data-igv_percentage="{{ $supplier->igv_percentage }}"
            data-observation="{{ $supplier->observation }}" data-status="{{ $supplier->status }}">
            <i class="fas fa-pen text-primary"></i> Editar proveedor
        </button>
        <div class="dropdown-divider"></div>
        <h6 class="dropdown-header">Cierre / anulación</h6>
        <button type="button" class="dropdown-item text-danger deleteSupplier" data-id="{{ $supplier->id }}">
            <i class="fas fa-trash"></i> Eliminar proveedor
        </button>
    </x-slot>
</x-table-actions-dropdown>
