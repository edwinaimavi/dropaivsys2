<x-table-actions-dropdown label="Acciones de la empresa">
    <x-slot name="main">
        @can('admin.companies.show')
            <button type="button" class="btn btn-sm btn-success dp-action-main btn-view-company"
                data-id="{{ $company->id }}" title="Ver detalle">
                <i class="fas fa-eye mr-1"></i> Ver
            </button>
        @endcan
    </x-slot>
    <x-slot name="menu">
        @canany(['admin.companies.update', 'admin.company-bank-accounts.index'])
            <h6 class="dropdown-header">Acciones operativas</h6>
            @can('admin.companies.update')
                <button type="button" class="dropdown-item btn-edit-company" data-id="{{ $company->id }}">
                    <i class="fas fa-edit text-primary"></i> Editar empresa
                </button>
            @endcan
            @can('admin.company-bank-accounts.index')
                <button type="button" class="dropdown-item btn-company-bank-accounts"
                    data-id="{{ $company->id }}" data-name="{{ $company->business_name }}" data-ruc="{{ $company->ruc }}"
                    data-status="{{ $company->status ? 'ACTIVO' : 'INACTIVO' }}">
                    <i class="fas fa-university text-success"></i> Cuentas bancarias
                </button>
            @endcan
        @endcanany
        @can('admin.companies.destroy')
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger btn-delete-company"
                data-id="{{ $company->id }}" data-name="{{ $company->business_name }}">
                <i class="fas fa-trash-alt"></i> Eliminar empresa
            </button>
        @endcan
    </x-slot>
</x-table-actions-dropdown>
