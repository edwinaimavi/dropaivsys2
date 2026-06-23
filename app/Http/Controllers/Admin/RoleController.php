<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\models\Role;
use Yajra\DataTables\Facades\DataTables;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
   public function __construct()
    {
        $this->middleware('can:admin.roles.index')->only('index', 'list');
        $this->middleware('can:admin.roles.store')->only('store');
        $this->middleware('can:admin.roles.update')->only('update');
        $this->middleware('can:admin.roles.destroy')->only('destroy');
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
            
            $roles = Role::orderBy('id', 'desc')->get();

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
        'name' => 'required|string|max:255|unique:roles,name',
        'permissions' => 'array'
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);

    

        if (!empty($data['permissions'])) {
            $permissions = Permission::whereIn('name', $data['permissions'])->pluck('id');
        
            $role->permissions()->sync($permissions);
        }

        return response()->json(['message' => 'Rol creado Exitosamente']);
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
        $data = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
                'permissions' => 'array'
            ]);

            $role->update([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            if (!empty($data['permissions'])) {
                $permissions = Permission::whereIn('name', $data['permissions'])->pluck('id');
                $role->permissions()->sync($permissions);
            } else {
                $role->permissions()->detach(); // para quitar todos si viene vacío
            }

            return response()->json(['message' => 'Rol actualizado exitosamente.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
