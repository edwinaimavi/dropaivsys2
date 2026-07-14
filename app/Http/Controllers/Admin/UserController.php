<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guarantor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;



class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.users.index')->only('index', 'list');
        $this->middleware('can:admin.users.store')->only('store');
        $this->middleware('can:admin.users.update')->only('update');
        $this->middleware('can:admin.users.destroy')->only('destroy');
        $this->middleware('can:admin.users.show')->only('show');
    }
    public function index()
    {
        $roles = Role::all();

        return view('admin.users.index', compact('roles'));
    }

    public function list()
    {
        $users = User::with('roles')
            ->where('status', '!=', -1)
            ->orderBy('id', 'desc')
            ->get();

        ////si te sale un erro ejecunta en la rais del proyecto esto   composer require yajra/laravel-datatables-oracle 
        return DataTables::of($users)
            ->addIndexColumn()
            ->editColumn('status', function ($user) {
                return $user->status == 1
                    ? '<span class="users-status-badge users-status-active"><i class="fas fa-check-circle"></i>ACTIVO</span>'
                    : '<span class="users-status-badge users-status-inactive"><i class="fas fa-ban"></i>INACTIVO</span>';
            })
            ->addColumn('roles_display', function ($user) {
                $roles = $user->roles->pluck('name');

                if ($roles->isEmpty()) {
                    return '<span class="users-role-chip users-role-chip-empty"><i class="fas fa-user-slash"></i>Sin rol</span>';
                }

                return $roles->map(function ($role) {
                    return '<span class="users-role-chip"><i class="fas fa-user-tag"></i>' . e($role) . '</span>';
                })->implode(' ');
            })
            ->addColumn('acciones', function ($user) {
                $statusOriginal = $user->status;
                $rutaFoto = $user->photo
                    ? asset('storage/' . $user->photo)
                    : 'https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg';
                $rol = $user->roles->first()?->id ?? '';
                return view('admin.users.partials.acciones', compact('user', 'statusOriginal', 'rutaFoto', 'rol'))->render();
            })
            ->rawColumns(['status', 'roles_display', 'acciones'])
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
            'dni' => 'required|min:8|max:8|unique:users,dni',
            'name' => 'required|min:3|max:50',
            'lastname' => 'required|min:3|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|max:20',
            'password_confirmation' => 'required|same:password',
            'phone' => 'nullable|min:9|max:15',
            'address' => 'nullable|min:3|max:150',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'required',
            'role' => 'required|exists:roles,id',

        ]);

        if ($request->hasFile('image')) {
            $data['photo'] = $request->file('image')->store('users', 'public');
        }
        $data['password'] = Hash::make($data['password']);
        unset($data['password_confirmation']);

        $roleId = $data['role'];
        unset($data['role']);

        $user = User::create($data);

        $user->roles()->sync([$roleId]);


        return response()->json(['message' => 'Usuario registrado correctamente']);
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
    public function update(Request $request, User $user)
    {
        //
        $data = $request->validate([
            'dni' => 'required|min:8|max:8|unique:users,dni,' . $user->id,
            'name' => 'required|min:3|max:50',
            'lastname' => 'required|min:3|max:50',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|min:9|max:15',
            'address' => 'nullable|min:3|max:150',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'required',
            'role' => 'required|exists:roles,id',

        ]);
        //VALIDAMOS LA CONTRASEÑA
        if ($request->filled('password')) {
            $request->validate([
                'password' => 'required|min:6|max:20',
                'password_confirmation' => 'required|same:password',
            ]);
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('image')) {
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }

            $data['photo'] = $request->file('image')->store('users', 'public');
        }

        $roleId = $data['role'];
        unset($data['role']);

        $user->update($data);
        $role = Role::findById($roleId);
        $user->syncRoles([$role->name]);

        return response()->json(['message' => 'Usuario actualizado correctamente']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->update(['status' => -1]);

        return response()->json([
            'message' => 'El usuario ha sido eliminado correctamente'
        ]);
    }
}
