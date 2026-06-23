 @can('admin.users.update')
<div class="btn-group btn-group-sm" role="group">
   <button class="btn btn-primary btn-sm me-2 editUser"
    data-id="{{ $user->id }}" 
    data-dni="{{ $user->dni }}" 
    data-name="{{ $user->name }}" 
    data-lastname="{{ $user->lastname }}"
    data-email="{{ $user->email }}" 
    data-phone="{{ $user->phone }}" 
    data-address="{{ $user->address }}" 
    data-status="{{ $statusOriginal }}"
    data-role="{{ $rol }}"
    data-photo="{{ $rutaFoto }}"
   ><i class="fas fa-pen"></i></button>-
@endcan
@can('admin.users.destroy')
    <button class="btn btn-danger btn-sm deleteUser" data-id="{{ $user->id }}">
        <i class="fa fa-trash"></i>
    </button>
@endcan
</div>