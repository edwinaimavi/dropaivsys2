<div class="btn-group shadow-sm" role="group" aria-label="Actions">

    {{-- VIEW --}}
    <button type="button" class="btn btn-outline-info btn-sm viewCustomer" data-toggle="tooltip" title="Ver Cliente"
        data-id="{{ $customer->id }}" data-person_type="{{ $customer->person_type }}"
        data-first_name="{{ $customer->first_name }}" data-last_name="{{ $customer->last_name }}"
        data-business_name="{{ $customer->business_name }}" data-document_type="{{ $customer->document_type }}"
        data-document_number="{{ $customer->document_number }}" data-phone="{{ $customer->phone }}"
        data-email="{{ $customer->email }}" data-address="{{ $customer->address }}"
        data-status="{{ $customer->status }}"
        data-created_at="{{ $customer->created_at ? $customer->created_at->format('d/m/Y H:i') : '—' }}"
        data-updated_at="{{ $customer->updated_at ? $customer->updated_at->format('d/m/Y H:i') : '—' }}"
        data-created_by="{{ $customer->creator->name ?? 'No registrado' }}"
        data-updated_by="{{ $customer->updater->name ?? 'No registrado' }}">

        <i class="fas fa-eye"></i>

    </button>

    {{-- EDIT --}}
    <button type="button" class="btn btn-outline-primary btn-sm editCustomer" data-toggle="tooltip"
        title="Editar Cliente" data-id="{{ $customer->id }}" data-person_type="{{ $customer->person_type }}"
        data-first_name="{{ $customer->first_name }}" data-last_name="{{ $customer->last_name }}"
        data-business_name="{{ $customer->business_name }}" data-document_type="{{ $customer->document_type }}"
        data-document_number="{{ $customer->document_number }}" data-phone="{{ $customer->phone }}"
        data-email="{{ $customer->email }}" data-address="{{ $customer->address }}"
        data-status="{{ $customer->status }}">

        <i class="fas fa-pen"></i>

    </button>

    {{-- SEDES --}}
    <button type="button" class="btn btn-outline-success btn-sm branchCustomer" data-toggle="tooltip"
        title="Gestionar Sedes" data-id="{{ $customer->id }}"
        data-name="{{ $customer->business_name ?: trim($customer->first_name . ' ' . $customer->last_name) }}">

        <i class="fas fa-building"></i>

    </button>

    {{-- DELETE --}}
    <button type="button" class="btn btn-outline-danger btn-sm deleteCustomer" data-id="{{ $customer->id }}"
        data-toggle="tooltip" title="Eliminar Cliente">

        <i class="fas fa-trash"></i>

    </button>

</div>
