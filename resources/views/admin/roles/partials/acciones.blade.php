<div class="btn-group btn-group-sm" role="group">
    @can('admin.roles.update')
    <button
           class="btn btn-primary btn-sm me-2 editRole"
            data-id="{{$role->id}}"
            data-name="{{$role->name}}">
            <i class="fas fa-pen"></i>                               
    </button> -
    @endcan
    @can('admin.roles.destroy')
    <button
                class="btn btn-danger btn-sm deleteRole"
                data-id="{{$role->id}}">
                <i class="fa fa-trash"></i>
    </button>
    @endcan
                

</div>