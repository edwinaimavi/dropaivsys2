<div class="btn-group shadow-sm" role="group" aria-label="Acciones">

    <button type="button" class="btn btn-outline-info btn-sm viewUser" data-toggle="tooltip" title="Ver Usuario"
        data-id="{{ $user->id }}" data-dni="{{ $user->dni }}" data-name="{{ $user->name }}"
        data-lastname="{{ $user->lastname }}" data-email="{{ $user->email }}" data-phone="{{ $user->phone }}"
        data-address="{{ $user->address }}" data-status="{{ $statusOriginal }}" data-role="{{ $rol }}"
        data-role-name="{{ $user->roles->first()?->name ?? 'Sin rol' }}" data-photo="{{ $rutaFoto }}"
        data-created-at="{{ optional($user->created_at)->format('d/m/Y H:i') }}"
        data-updated-at="{{ optional($user->updated_at)->format('d/m/Y H:i') }}">

        <i class="fas fa-eye"></i>

    </button>

    @can('admin.users.update')
        <button type="button" class="btn btn-outline-primary btn-sm editUser" data-toggle="tooltip" title="Editar Usuario"
            data-id="{{ $user->id }}" data-dni="{{ $user->dni }}" data-name="{{ $user->name }}"
            data-lastname="{{ $user->lastname }}" data-email="{{ $user->email }}" data-phone="{{ $user->phone }}"
            data-address="{{ $user->address }}" data-status="{{ $statusOriginal }}" data-role="{{ $rol }}"
            data-photo="{{ $rutaFoto }}">

            <i class="fas fa-pen"></i>

        </button>
    @endcan

    @can('admin.users.destroy')
        <button type="button" class="btn btn-outline-danger btn-sm deleteUser" data-id="{{ $user->id }}"
            data-toggle="tooltip" title="Eliminar Usuario">

            <i class="fas fa-trash"></i>

        </button>
    @endcan

</div>
