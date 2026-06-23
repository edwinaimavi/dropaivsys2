<!-- Modal -->
<div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header bg-dark">
          <h5 class="modal-title" id="exampleModalLabel"><i class="fas fa-plus"></i> Nuevo Usuario</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body">
          
            <div class="card card-warning">
               
                <!-- /.card-header -->
                <div class="card-body">
                  <span class="text-danger">* Campos Obligatorios</span>
                  <form id="userForm" enctype="multipart/form-data">
                    <div id="error-messages" class="alert alert-danger d-none"></div> 
                    @csrf
                    <!-- input states -->                      
                    <div class="row">
                      <div class="col-sm-3">
                        <!-- select -->
                        <div class="form-group">
                          <label class="col-form-label small" for="dni"><i class="fas fa-asterisk" style="color: red;font-size: 8px"></i> Num. DNI</label>
                          <input type="text" class="form-control form-control-sm" id="dni" name="dni" placeholder="Num DNI" required>
                             
                        </div>  
                      </div> 
                      <div class="col-sm-4">
                        <!-- select -->
                        <div class="form-group">
                          <label class="col-form-label small" for="name"><i class="fas fa-asterisk" style="color: red;font-size: 8px"></i> Nombres y Apellidos</label>
                          <input type="text" class="form-control form-control-sm" id="name" name="name" placeholder="Nombre y Apellidos" required>
                            
                        </div>  
                      </div> 
                      <div class="col-sm-5">
                        <!-- select -->
                        <div class="form-group">
                          <label class="col-form-label small" for="lastname"><i class="fas fa-asterisk" style="color: red;font-size: 8px"></i> Apellidos</label>
                          <input type="text" class="form-control form-control-sm" id="lastname" name="lastname" placeholder="Apellidos" required>
                            
                        </div>  
                      </div> 

                      
                    </div> 

                    <div class="row">
                      <div class="col-sm-6">
                        <!-- select -->
                        <div class="form-group">
                          <label class="col-form-label small" for="email"><i class="fas fa-asterisk" style="color: red;font-size: 8px"></i> Email</label>
                          <input type="text" class="form-control form-control-sm" id="email" name="email" placeholder="Email" required>
                             
                        </div>  
                      </div> 
                      <div class="col-sm-3">
                        <!-- select -->
                        <div class="form-group">
                          <label class="col-form-label small" for="password"><i class="fas fa-asterisk" style="color: red;font-size: 8px"></i> Password</label>
                          <input type="password" class="form-control form-control-sm" id="password" name="password" >
                            
                        </div>  
                      </div> 
                      <div class="col-sm-3">
                        <!-- select -->
                        <div class="form-group">
                          <label class="col-form-label small" for="password_confirmation"><i class="fas fa-asterisk" style="color: red;font-size: 8px"></i> Repetir Password</label>
                          <input type="password" class="form-control form-control-sm" id="password_confirmation" name="password_confirmation" >
                            
                        </div>  
                      </div> 

                      
                    </div> 
                    

                    <div class="row">
                      <div class="col-sm-3">
                        <!-- select -->
                        <div class="form-group">
                          <label class="col-form-label small" for="phone"> Celular</label>
                          <input type="text" class="form-control form-control-sm" id="phone" name="phone" placeholder="Numero de Celular" required>
                           
                        </div>  
                      </div> 
                      <div class="col-sm-9">
                        <!-- select -->
                        <div class="form-group">
                          <label class="col-form-label small" for="address"> Dirección del Usuario </label>
                          <input type="text" class="form-control form-control-sm" id="address" name="address" placeholder="Dirección del Usuario" required>
                            
                        </div>  
                      </div> 

               
                    </div> 
                    
                 
                    <div class="row">
                  
                      <div class="col-sm-4">
                        <!-- select -->
                        <div class="form-group">
                          <label> <i class="fas fa-asterisk" style="color: red;font-size: 8px"></i> Estado</label>
                          <select class="form-control form-control-sm" name="status">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                            
                          </select>
                        </div> 
                      </div> 
                       <div class="col-sm-4">
                        <!-- select -->
                        <div class="form-group">
                          <label> <i class="fas fa-asterisk" style="color: red;font-size: 8px"></i> Asignar Rol</label>
                          <select class="form-control form-control-sm" name="role" id="role" required>
                            <option value="">Seleccione un Rol</option>
                            @foreach ($roles as $role)
                              <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                          </select>
                        </div> 
                      </div>
                      <div class="col-md-4">
                        <div class="position-relative mb-2" style="aspect-ratio: 1/1;">
                          <img id="imgPreview" src="https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg"
                               class="w-100 h-100 object-fit-cover rounded"
                               style="position: absolute; inset: 0;" alt="image">
                      
                          <label class="position-absolute top-0 start-0 m-2 bg-white px-3 py-1 rounded shadow-sm"
                                 style="cursor: pointer;">
                                 Foto <i class="fas fa-upload"></i>
                            <input type="file" class="d-none" name="image" id="image" accept="image/*" onchange="previewImage(event, '#imgPreview')">
                          </label>
                        </div>
                      </div>
                      
                      
             
                    </div>     

                      <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                      <button type="submit" class="btn btn-primary">Guardar</button>
                    
                  </form>
                </div>
                <!-- /.card-body -->
              </div>
        
          
        </div>
        </div>
      </div>
    </div>
        