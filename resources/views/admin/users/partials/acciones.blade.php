<x-table-actions-dropdown label="Acciones del usuario">
    <x-slot name="main">
        <button type="button" class="btn btn-sm btn-success dp-action-main viewUser" title="Ver usuario"
            data-id="{{ $user->id }}" data-dni="{{ $user->dni }}" data-name="{{ $user->name }}"
            data-lastname="{{ $user->lastname }}" data-email="{{ $user->email }}" data-phone="{{ $user->phone }}"
            data-address="{{ $user->address }}" data-status="{{ $statusOriginal }}" data-role="{{ $rol }}"
            data-role-name="{{ $user->roles->first()?->name ?? 'Sin rol' }}" data-photo="{{ $rutaFoto }}"
            data-has-photo="{{ $hasPhoto ? 1 : 0 }}"
            data-is-principal="{{ $isPrincipal ? 1 : 0 }}"
            data-created-at="{{ optional($user->created_at)->format('d/m/Y H:i') }}"
            data-updated-at="{{ optional($user->updated_at)->format('d/m/Y H:i') }}">
            <i class="fas fa-eye mr-1"></i> Ver
        </button>
    </x-slot>
    <x-slot name="menu">
        @can('admin.users.update')
            @if ($canEditPrincipal)
            <h6 class="dropdown-header">Acciones operativas</h6>
            <button type="button" class="dropdown-item editUser" title="Editar usuario"
                data-id="{{ $user->id }}" data-dni="{{ $user->dni }}" data-name="{{ $user->name }}"
                data-lastname="{{ $user->lastname }}" data-email="{{ $user->email }}" data-phone="{{ $user->phone }}"
                data-address="{{ $user->address }}" data-status="{{ $statusOriginal }}" data-role="{{ $rol }}"
                data-photo="{{ $rutaFoto }}" data-is-principal="{{ $isPrincipal ? 1 : 0 }}">
                <i class="fas fa-pen text-primary"></i> Editar usuario
            </button>
            @endif
        @endcan
        @can('admin.users.destroy')
            @if (! $isPrincipal)
            <div class="dropdown-divider"></div>
            <h6 class="dropdown-header">Cierre / anulación</h6>
            <button type="button" class="dropdown-item text-danger deleteUser" data-id="{{ $user->id }}">
                <i class="fas fa-trash"></i> Eliminar usuario
            </button>
            @endif
        @endcan
    </x-slot>
</x-table-actions-dropdown>
