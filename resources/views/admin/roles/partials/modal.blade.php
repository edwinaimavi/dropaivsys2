@php
    $moduleLabels = [
        'role' => 'Roles',
        'rol' => 'Roles',
        'user' => 'Usuarios',
        'usuario' => 'Usuarios',
        'customer' => 'Clientes',
        'cliente' => 'Clientes',
        'supplier' => 'Proveedores',
        'proveedor' => 'Proveedores',
        'category' => 'Categor&iacute;as',
        'categoria' => 'Categor&iacute;as',
        'unit' => 'Unidades',
        'unidad' => 'Unidades',
        'presentation' => 'Presentaciones',
        'presentacion' => 'Presentaciones',
        'brand' => 'Marcas',
        'marca' => 'Marcas',
        'article' => 'Art&iacute;culos',
        'articulo' => 'Art&iacute;culos',
        'market' => 'Estudios de Mercado',
        'study' => 'Estudios de Mercado',
        'quote' => 'Cotizaciones',
        'cotizacion' => 'Cotizaciones',
        'purchase' => '&Oacute;rdenes de Compra',
        'order' => '&Oacute;rdenes de Compra',
        'warehouse' => 'Almac&eacute;n',
        'almacen' => 'Almac&eacute;n',
        'kardex' => 'Kardex',
    ];

    $permissionGroups = $permissions->groupBy(function ($permission) use ($moduleLabels) {
        $text = mb_strtolower(($permission->name ?? '') . ' ' . ($permission->description ?? ''));

        foreach ($moduleLabels as $needle => $label) {
            if (str_contains($text, $needle)) {
                return $label;
            }
        }

        $parts = preg_split('/[\s\.\-_]+/', $permission->name ?? '');
        $fallback = $parts ? end($parts) : 'otros';

        return ucfirst(str_replace(['_', '-'], ' ', $fallback ?: 'Otros'));
    })->sortKeys();
@endphp

<div class="modal fade roles-modal" id="roleModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered role-modal-dialog" role="document">
        <div class="modal-content border-0 shadow-lg role-modal-content">
            <form id="roleForm" class="role-modal-form">
                @csrf
                <div class="modal-header border-0">
                    <div class="roles-modal-title">
                        <div class="roles-modal-title-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0" id="exampleModalLabel">Nuevo Rol</h5>
                            <small>Asigna un nombre y selecciona los permisos del rol</small>
                        </div>
                    </div>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body role-modal-body">
                    <div class="row">
                        <div class="col-lg-3 mb-3 mb-lg-0">
                            <aside class="roles-side-panel">
                                <div class="roles-side-icon mb-3">
                                    <i class="fas fa-users-cog"></i>
                                </div>
                                <h6 class="font-weight-bold mb-1">Roles</h6>
                                <p class="text-muted small mb-3">Control de accesos y permisos por perfil de usuario.</p>

                                <div class="roles-summary-box mb-2">
                                    <span>Total permisos</span>
                                    <strong id="roleTotalPermissions">{{ $permissions->count() }}</strong>
                                </div>
                                <div class="roles-summary-box">
                                    <span>Permisos seleccionados</span>
                                    <strong>
                                        <span id="roleSelectedPermissions">0</span>
                                        <small class="text-muted">de {{ $permissions->count() }}</small>
                                    </strong>
                                </div>
                            </aside>
                        </div>

                        <div class="col-lg-9">
                            <div class="card roles-modal-card mb-3">
                                <div class="card-header border-0">
                                    <h6 class="mb-1 font-weight-bold">
                                        <i class="fas fa-id-badge text-success mr-1"></i>
                                        Datos del rol
                                    </h6>
                                    <small class="text-muted">Define el nombre visible del rol dentro del sistema</small>
                                </div>
                                <div class="card-body">
                                    <div class="form-group mb-0">
                                        <label for="name">Nombre del rol</label>
                                        <input type="text" class="form-control" id="name" name="name"
                                            placeholder="Ejemplo: Administrador, Vendedor, Supervisor" required>
                                        <div id="error-messages" class="alert alert-danger d-none mt-2 mb-0"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="card roles-permission-card">
                                <div class="card-header border-0">
                                    <div class="roles-toolbar">
                                        <div>
                                            <h6 class="mb-1 font-weight-bold">
                                                <i class="fas fa-key text-success mr-1"></i>
                                                Permisos del rol
                                            </h6>
                                            <small class="text-muted">Busca, selecciona o quita permisos sin cambiar sus nombres reales</small>
                                        </div>
                                        <div class="roles-search-wrap">
                                            <i class="fas fa-search"></i>
                                            <input type="text" id="rolePermissionSearch" class="form-control form-control-sm"
                                                placeholder="Buscar permiso...">
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                                        <small class="text-muted font-weight-bold">Permisos agrupados por m&oacute;dulo</small>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-success" id="btnSelectAllPermissions">
                                                <i class="fas fa-check-double mr-1"></i>
                                                Seleccionar todos
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="btnClearAllPermissions">
                                                <i class="fas fa-eraser mr-1"></i>
                                                Quitar todos
                                            </button>
                                        </div>
                                    </div>

                                    <div class="roles-permission-groups" id="rolePermissionGroups">
                                        @foreach ($permissionGroups as $groupName => $groupPermissions)
                                            <section class="roles-permission-group" data-permission-group>
                                                <div class="roles-permission-group-header">
                                                    <div>
                                                        <div class="roles-permission-group-title">{!! $groupName !!}</div>
                                                        <div class="roles-permission-group-count">{{ $groupPermissions->count() }} permisos</div>
                                                    </div>
                                                    <button type="button" class="btn btn-outline-success btn-sm btnSelectPermissionGroup">
                                                        <i class="fas fa-check mr-1"></i>
                                                        Seleccionar grupo
                                                    </button>
                                                </div>
                                                <div class="roles-permission-grid">
                                                    @foreach ($groupPermissions as $permission)
                                                        <div class="roles-permission-item"
                                                            data-permission-item
                                                            data-permission-text="{{ mb_strtolower(($permission->description ?: $permission->name) . ' ' . $permission->name . ' ' . $permission->guard_name) }}">
                                                            <div class="custom-control custom-switch">
                                                                <input type="checkbox" class="custom-control-input"
                                                                    value="{{ $permission->name }}"
                                                                    id="permission_{{ $permission->id }}"
                                                                    name="permissions[]">
                                                                <label class="custom-control-label" for="permission_{{ $permission->id }}">
                                                                    {{ $permission->description ?: $permission->name }}
                                                                    <small>{{ $permission->guard_name }} | {{ $permission->name }}</small>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </section>
                                        @endforeach
                                    </div>

                                    <div class="roles-permission-empty" id="rolePermissionEmpty">
                                        <i class="fas fa-search mr-1"></i>
                                        No se encontraron permisos con ese texto.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 role-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i>
                        Cerrar
                    </button>
                    <button type="submit" class="btn btn-success" id="btnSaveRole">
                        <i class="fas fa-save mr-1"></i>
                        Guardar Rol
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
