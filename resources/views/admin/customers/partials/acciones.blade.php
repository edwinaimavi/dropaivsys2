<x-table-actions-dropdown label="Acciones del cliente">
    <x-slot name="main">
        <button type="button" class="btn btn-sm btn-success dp-action-main viewCustomer" title="Ver cliente"
            data-id="{{ $customer->id }}" data-person_type="{{ $customer->person_type }}"
            data-first_name="{{ $customer->first_name }}" data-last_name="{{ $customer->last_name }}"
            data-business_name="{{ $customer->business_name }}" data-document_type="{{ $customer->document_type }}"
            data-document_number="{{ $customer->document_number }}" data-phone="{{ $customer->phone }}"
            data-email="{{ $customer->email }}" data-address="{{ $customer->address }}"
            data-channel="{{ $customer->channel }}" data-subchannel="{{ $customer->subchannel }}"
            data-withholding_agent="{{ $customer->withholding_agent }}"
            data-status="{{ $customer->status }}"
            data-created_at="{{ $customer->created_at ? $customer->created_at->format('d/m/Y H:i') : '—' }}"
            data-updated_at="{{ $customer->updated_at ? $customer->updated_at->format('d/m/Y H:i') : '—' }}"
            data-created_by="{{ $customer->creator->name ?? 'No registrado' }}"
            data-updated_by="{{ $customer->updater->name ?? 'No registrado' }}">
            <i class="fas fa-eye mr-1"></i> Ver
        </button>
    </x-slot>
    <x-slot name="menu">
        <h6 class="dropdown-header">Acciones operativas</h6>
        <button type="button" class="dropdown-item editCustomer" title="Editar cliente"
            data-id="{{ $customer->id }}" data-person_type="{{ $customer->person_type }}"
            data-first_name="{{ $customer->first_name }}" data-last_name="{{ $customer->last_name }}"
            data-business_name="{{ $customer->business_name }}" data-full_name="{{ $customer->full_name }}"
            data-document_type="{{ $customer->document_type }}"
            data-document_number="{{ $customer->document_number }}" data-phone="{{ $customer->phone }}"
            data-email="{{ $customer->email }}" data-address="{{ $customer->address }}"
            data-channel="{{ $customer->channel }}" data-subchannel="{{ $customer->subchannel }}"
            data-withholding_agent="{{ $customer->withholding_agent }}"
            data-status="{{ $customer->status }}">
            <i class="fas fa-pen text-primary"></i> Editar cliente
        </button>
        <button type="button" class="dropdown-item branchCustomer" title="Gestionar sedes"
            data-id="{{ $customer->id }}" data-person_type="{{ $customer->person_type }}"
            data-business_name="{{ $customer->business_name }}" data-first_name="{{ $customer->first_name }}"
            data-last_name="{{ $customer->last_name }}" data-document_number="{{ $customer->document_number }}"
            data-name="{{ $customer->business_name ?: trim($customer->first_name . ' ' . $customer->last_name) }}">
            <i class="fas fa-building text-success"></i> Gestionar sedes
        </button>
        <div class="dropdown-divider"></div>
        <h6 class="dropdown-header">Cierre / anulación</h6>
        <button type="button" class="dropdown-item text-danger deleteCustomer" data-id="{{ $customer->id }}">
            <i class="fas fa-trash"></i> Eliminar cliente
        </button>
    </x-slot>
</x-table-actions-dropdown>
