<div class="btn-group" role="group">
    @can('admin.companies.show')
        <button type="button" class="btn btn-sm btn-outline-info btn-view-company mr-2" data-id="{{ $company->id }}"
            data-toggle="tooltip" title="Ver detalle">
            <i class="fas fa-eye"></i>
        </button>
    @endcan

    @can('admin.companies.update')
        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-company mr-2" data-id="{{ $company->id }}"
            data-toggle="tooltip" title="Editar">
            <i class="fas fa-edit"></i>
        </button>
    @endcan

    @can('admin.company-bank-accounts.index')
        <button type="button" class="btn btn-sm btn-outline-success btn-company-bank-accounts mr-2"
            data-id="{{ $company->id }}" data-name="{{ $company->business_name }}" data-ruc="{{ $company->ruc }}"
            data-status="{{ $company->status ? 'ACTIVO' : 'INACTIVO' }}" data-toggle="tooltip"
            title="Cuentas bancarias">
            <i class="fas fa-university"></i>
        </button>
    @endcan

    @can('admin.companies.destroy')
        <button type="button" class="btn btn-sm btn-outline-danger btn-delete-company" data-id="{{ $company->id }}"
            data-name="{{ $company->business_name }}" data-toggle="tooltip" title="Eliminar">
            <i class="fas fa-trash-alt"></i>
        </button>
    @endcan
</div>
