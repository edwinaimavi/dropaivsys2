<!-- Modal -->
<div class="modal fade" id="roleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header bg-dark">
          <h5 class="modal-title" id="exampleModalLabel">Nuevo Rol</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          
            <div class="card card-warning">
               
                <!-- /.card-header -->
                <div class="card-body">
                  <form id="roleForm">
                    @csrf
                    <!-- input states -->
                    <div class="form-group">
                      <label class="col-form-label" for="name"><i class="fas fa-check"></i> Nombre del Rol</label>
                      <input type="text" class="form-control" id="name" name="name" placeholder="Nombre del Rol" required>
                      <div id="error-messages" class="alert alert-danger d-none"></div>

                    </div>  
                    
                    <h2 class="h3"> Lista de Permisos</h2>

                    @foreach ($permissions as $permission)
                        <div class="form-group">
                          <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" value="{{ $permission->name }}" id="permission_{{ $permission->id }}" name="permissions[]">
                            <label class="custom-control-label" for="permission_{{ $permission->id }}">{{ $permission->description }} <small class="text-muted">({{ $permission->guard_name }})</small></label>
                          </div>
                        </div>

                      {{--   <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="{{ $permission->name }}" id="permission_{{ $permission->id }}" name="permissions[]">
                            <label class="form-check-label" for="permission_{{ $permission->id }}">
                                {{ $permission->description }} <small class="text-muted">({{ $permission->guard_name }})</small>
                            </label>
                        </div> --}}
                    @endforeach
                                   

                 
                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                      <button type="submit" class="btn btn-primary">Guardar</button>
                    
                  </form>
                </div>
                <!-- /.card-body -->
              </div>
        
          
        </div>
        </div>
      </div>
    </div>
        