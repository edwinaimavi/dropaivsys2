<div class="btn-group shadow-sm" role="group" aria-label="Actions">

    {{-- VIEW --}}
    <button type="button" class="btn btn-outline-info btn-sm viewSupplier" data-toggle="tooltip" title="Ver Proveedor"
        data-id="{{ $supplier->id }}" data-ruc="{{ $supplier->ruc }}" data-business_name="{{ $supplier->business_name }}"
        data-short_name="{{ $supplier->short_name }}" data-address="{{ $supplier->address }}"
        data-ubigeo="{{ $supplier->ubigeo->full_name ?? '—' }}" data-supplier_type="{{ $supplier->supplier_type }}"
        data-payment_condition="{{ $supplier->payment_condition }}" data-contact_name="{{ $supplier->contact_name }}"
        data-email="{{ $supplier->email }}" data-phone="{{ $supplier->phone }}"
        data-igv_percentage="{{ $supplier->igv_percentage }}" data-observation="{{ $supplier->observation }}"
        data-status="{{ $supplier->status }}" data-created_by="{{ $supplier->creator->name ?? '—' }}"
        data-updated_by="{{ $supplier->editor->name ?? '—' }}"
        data-created_at="{{ $supplier->created_at ? $supplier->created_at->format('d/m/Y H:i') : '—' }}"
        data-updated_at="{{ $supplier->updated_at ? $supplier->updated_at->format('d/m/Y H:i') : '—' }}" >

        <i class="fas fa-eye"></i>

    </button>

    {{-- CUENTAS BANCARIAS --}}
    <button type="button" class="btn btn-outline-success btn-sm bankAccountsSupplier" data-toggle="tooltip"
        title="Cuentas Bancarias" data-id="{{ $supplier->id }}" data-business_name="{{ $supplier->business_name }}"
        data-ruc="{{ $supplier->ruc }}">

        <i class="fas fa-university"></i>

    </button>

    {{-- EDIT --}}
    <button type="button" class="btn btn-outline-warning btn-sm editSupplier" data-toggle="tooltip"
        title="Editar Proveedor" data-id="{{ $supplier->id }}" data-ruc="{{ $supplier->ruc }}"
        data-business_name="{{ $supplier->business_name }}" data-short_name="{{ $supplier->short_name }}"
        data-address="{{ $supplier->address }}" data-ubigeo_id="{{ $supplier->ubigeo_id }}"
        data-ubigeo_text="{{ $supplier->ubigeo->full_name ?? '' }}"
        data-supplier_type="{{ $supplier->supplier_type }}"
        data-payment_condition="{{ $supplier->payment_condition }}" data-contact_name="{{ $supplier->contact_name }}"
        data-email="{{ $supplier->email }}" data-phone="{{ $supplier->phone }}"
        data-igv_percentage="{{ $supplier->igv_percentage }}" data-observation="{{ $supplier->observation }}"
        data-status="{{ $supplier->status }}">

        <i class="fas fa-pen"></i>

    </button>

    {{-- DELETE --}}
    <button type="button" class="btn btn-outline-danger btn-sm deleteSupplier" data-id="{{ $supplier->id }}"
        data-toggle="tooltip" title="Eliminar Proveedor">

        <i class="fas fa-trash"></i>

    </button>


   

</div>
