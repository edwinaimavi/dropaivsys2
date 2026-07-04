<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
   public function __construct()
    {
        $this->middleware('can:admin.roles.index')->only('index', 'list');
        $this->middleware('can:admin.roles.store')->only('store');
        $this->middleware('can:admin.roles.update')->only('update');
        $this->middleware('can:admin.roles.destroy')->only('destroy');
        $this->middleware('can:admin.roles.show')->only(['show', 'getPermissions']);
    }
    public function index()
    {
        $permissions = Permission::all();
        return view('admin.roles.index', compact('permissions'));
    }

    public function getPermissions($id)
    {
        $role = Role::findOrFail($id);
        $permissions = $role->permissions->pluck('name'); // devuelve solo los nombres

        return response()->json($permissions);
    }

    public function list()
        {
            /* $permissions = Permission::all(); */
            
            $roles = Role::withCount('permissions')->orderBy('id', 'desc')->get();

            return DataTables::of($roles)
            ->addIndexcolumn()
            ->addColumn('acciones',function ($role){
               return view('admin.roles.partials.acciones',compact('role'))->render();

            })
            ->rawColumns(['acciones'])
            ->make(true);
        }

    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
            'permissions' => [
                'required',
                'array',
                'min:1',
            ],
            'permissions.*' => [
                Rule::exists('permissions', 'name')
                    ->where(fn ($query) => $query->where('guard_name', 'web')),
            ],
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.max' => 'El nombre del rol no debe superar 255 caracteres.',
            'name.unique' => 'El nombre del rol ya está registrado.',
            'permissions.required' => 'Debe seleccionar al menos un permiso.',
            'permissions.array' => 'Debe seleccionar al menos un permiso.',
            'permissions.min' => 'Debe seleccionar al menos un permiso.',
            'permissions.*.exists' => 'Uno de los permisos seleccionados no es válido.',
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

    

        if (!empty($data['permissions'])) {
            $permissions = Permission::where('guard_name', 'web')
                ->whereIn('name', $data['permissions'])
                ->pluck('id');
        
            $role->permissions()->sync($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Rol registrado correctamente.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Role $role)
    {
        $guardName = $role->guard_name ?? 'web';

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where(fn ($query) => $query->where('guard_name', $guardName))
                    ->ignore($role->id),
            ],
            'permissions' => [
                'required',
                'array',
                'min:1',
            ],
            'permissions.*' => [
                Rule::exists('permissions', 'name')
                    ->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
        ], [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.max' => 'El nombre del rol no debe superar 255 caracteres.',
            'name.unique' => 'El nombre del rol ya está registrado.',
            'permissions.required' => 'Debe seleccionar al menos un permiso.',
            'permissions.array' => 'Debe seleccionar al menos un permiso.',
            'permissions.min' => 'Debe seleccionar al menos un permiso.',
            'permissions.*.exists' => 'Uno de los permisos seleccionados no es válido.',
        ]);

            $role->update([
                'name' => $data['name'],
                'guard_name' => $guardName,
            ]);

            if (!empty($data['permissions'])) {
                $permissions = Permission::where('guard_name', $guardName)
                    ->whereIn('name', $data['permissions'])
                    ->pluck('id');
                $role->permissions()->sync($permissions);
            } else {
                $role->permissions()->detach(); // para quitar todos si viene vacío
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return response()->json(['message' => 'Rol actualizado correctamente.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Role::findOrFail($id);

        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar este rol porque esta asignado a uno o mas usuarios.',
            ], 409);
        }

        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return response()->json(['message' => 'Rol eliminado correctamente.']);
    }
}
