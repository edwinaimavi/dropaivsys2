<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserRoleHistory;
use App\Services\UserSecurityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    public function __construct(private readonly UserSecurityService $security)
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
        $principalUserId = $this->security->principalUserId();
        $currentUserId = Auth::id();
        $users = User::with('roles')
            ->where('status', '!=', -1)
            ->orderBy('id', 'desc')
            ->get();

        return DataTables::of($users)
            ->addIndexColumn()
            ->addColumn('is_principal', fn (User $user) => $user->id === $principalUserId)
            ->editColumn('status', function (User $user) {
                return $user->status == 1
                    ? '<span class="users-status-badge users-status-active"><i class="fas fa-check-circle"></i>ACTIVO</span>'
                    : '<span class="users-status-badge users-status-inactive"><i class="fas fa-ban"></i>INACTIVO</span>';
            })
            ->addColumn('roles_display', function (User $user) use ($principalUserId) {
                $roles = $user->roles->pluck('name');
                $display = $roles->isEmpty()
                    ? '<span class="users-role-chip users-role-chip-empty"><i class="fas fa-user-slash"></i>Sin rol</span>'
                    : $roles->map(fn (string $role) => '<span class="users-role-chip"><i class="fas fa-user-tag"></i>'.e($role).'</span>')->implode(' ');

                if ($user->id === $principalUserId) {
                    $display .= ' <span class="users-role-chip users-principal-chip"><i class="fas fa-shield-alt"></i>Protegido</span>';
                }

                return $display;
            })
            ->addColumn('acciones', function (User $user) use ($principalUserId, $currentUserId) {
                $statusOriginal = $user->status;
                $rutaFoto = $user->photo
                    ? asset('storage/'.$user->photo)
                    : 'https://www.shutterstock.com/image-vector/default-avatar-profile-icon-social-600nw-1906669723.jpg';
                $hasPhoto = (bool) $user->photo;
                $rol = $user->roles->first()?->id ?? '';
                $isPrincipal = $user->id === $principalUserId;
                $canEditPrincipal = ! $isPrincipal || $currentUserId === $user->id;

                return view('admin.users.partials.acciones', compact(
                    'user',
                    'statusOriginal',
                    'rutaFoto',
                    'hasPhoto',
                    'rol',
                    'isPrincipal',
                    'canEditPrincipal'
                ))->render();
            })
            ->rawColumns(['status', 'roles_display', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'dni' => 'required|digits:8|unique:users,dni',
            'name' => 'required|min:3|max:50',
            'lastname' => 'required|min:3|max:50',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6|max:20',
            'password_confirmation' => 'required|same:password',
            'phone' => 'nullable|min:9|max:15',
            'address' => 'nullable|min:3|max:150',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'required|integer|in:0,1',
            'role' => 'required|exists:roles,id',
        ]);

        if ($request->hasFile('image')) {
            $data['photo'] = $request->file('image')->store('users', 'public');
        }

        $data['password'] = Hash::make($data['password']);
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();
        $roleId = (int) $data['role'];
        unset($data['password_confirmation'], $data['role'], $data['image']);

        try {
            DB::transaction(function () use ($data, $roleId, $request) {
                $user = User::create($data);
                $role = Role::findById($roleId);
                $user->assignRole($role);
                $this->recordRoleHistory($user, null, $roleId, 'assigned', $request);
            });
        } catch (\Throwable $exception) {
            if (! empty($data['photo'])) {
                Storage::disk('public')->delete($data['photo']);
            }

            throw $exception;
        }

        return response()->json(['message' => 'Usuario registrado correctamente']);
    }

    public function show(User $user)
    {
        $user->load([
            'creator:id,name,lastname',
            'updater:id,name,lastname',
            'roles:id,name',
            'latestRoleHistory.performer:id,name,lastname',
        ]);

        $historical = 'No registrado / histórico';
        $lastRoleHistory = $user->latestRoleHistory;

        return response()->json([
            'data' => [
                'created_by' => $this->userDisplayName($user->creator) ?? $historical,
                'created_at' => $user->created_at?->format('d/m/Y H:i') ?? $historical,
                'updated_by' => $this->userDisplayName($user->updater) ?? $historical,
                'updated_at' => $user->updated_at?->format('d/m/Y H:i') ?? $historical,
                'current_role' => $user->roles->first()?->name ?? 'Sin rol',
                'last_role_changed_by' => $this->userDisplayName($lastRoleHistory?->performer) ?? $historical,
                'last_role_changed_at' => $lastRoleHistory?->performed_at?->format('d/m/Y H:i') ?? $historical,
                'is_principal' => $this->security->isPrincipal($user),
            ],
        ]);
    }

    public function edit(User $user)
    {
        // La edición se realiza en el modal del listado.
    }

    public function update(Request $request, User $user)
    {
        $user->loadMissing('roles');
        $actor = $request->user();
        $this->security->ensureCanEdit($user, $actor);

        if ($this->security->isPrincipal($user)) {
            return $this->updatePrincipalProfile($request, $user);
        }

        $data = $request->validate([
            'dni' => 'required|digits:8|unique:users,dni,'.$user->id,
            'name' => 'required|min:3|max:50',
            'lastname' => 'required|min:3|max:50',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|min:9|max:15',
            'address' => 'nullable|min:3|max:150',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'required|integer|in:0,1',
            'role' => 'sometimes|nullable|exists:roles,id',
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => 'required|min:6|max:20',
                'password_confirmation' => 'required|same:password',
            ]);
            $data['password'] = Hash::make($request->password);
        }

        $previousRoleId = $user->roles->first()?->id;
        $roleId = array_key_exists('role', $data)
            ? ($data['role'] !== null ? (int) $data['role'] : null)
            : $previousRoleId;
        $this->security->ensureAccessChangeIsSafe($user, $actor, (int) $data['status'], $roleId);
        unset($data['role'], $data['image']);
        $data['updated_by'] = Auth::id();

        $newPhoto = $request->hasFile('image')
            ? $request->file('image')->store('users', 'public')
            : null;
        $oldPhoto = $user->photo;
        if ($newPhoto) {
            $data['photo'] = $newPhoto;
        }

        try {
            DB::transaction(function () use ($user, $data, $roleId, $previousRoleId, $request) {
                $user->update($data);
                if ($roleId === null) {
                    $user->syncRoles([]);
                } else {
                    $role = Role::findById($roleId);
                    $user->syncRoles([$role]);
                }

                if ($previousRoleId !== $roleId) {
                    $action = $roleId === null ? 'removed' : 'changed';
                    $this->recordRoleHistory($user, $previousRoleId, $roleId, $action, $request);
                }
            });
        } catch (\Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }

            throw $exception;
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return response()->json(['message' => 'Usuario actualizado correctamente']);
    }

    public function destroy(Request $request, User $user)
    {
        $user->loadMissing('roles');
        $this->security->ensureCanDelete($user, $request->user());
        $user->update([
            'status' => -1,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'El usuario ha sido eliminado correctamente',
        ]);
    }

    private function updatePrincipalProfile(Request $request, User $user)
    {
        $accessChange = ($request->has('status') && (int) $request->status !== (int) $user->status)
            || ($request->has('role') && (int) $request->role !== (int) $user->roles->first()?->id);

        if ($accessChange) {
            throw new AuthorizationException('No puedes desactivar ni cambiar el rol del usuario principal del sistema.');
        }

        $data = $request->validate([
            'dni' => 'required|digits:8|unique:users,dni,'.$user->id,
            'name' => 'required|min:3|max:50',
            'lastname' => 'required|min:3|max:50',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|min:9|max:15',
            'address' => 'nullable|min:3|max:150',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'sometimes|integer|in:0,1',
            'role' => 'sometimes|exists:roles,id',
        ]);

        if ($request->filled('password')) {
            $request->validate([
                'password' => 'required|min:6|max:20',
                'password_confirmation' => 'required|same:password',
            ]);
            $data['password'] = Hash::make($request->password);
        }

        $newPhoto = $request->hasFile('image')
            ? $request->file('image')->store('users', 'public')
            : null;
        $oldPhoto = $user->photo;
        unset($data['image'], $data['status'], $data['role']);
        $data['updated_by'] = Auth::id();
        if ($newPhoto) {
            $data['photo'] = $newPhoto;
        }

        try {
            $user->update($data);
        } catch (\Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }

            throw $exception;
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return response()->json(['message' => 'Usuario principal actualizado correctamente']);
    }

    private function recordRoleHistory(
        User $user,
        ?int $previousRoleId,
        ?int $roleId,
        string $action,
        Request $request
    ): void {
        $descriptions = [
            'assigned' => 'Rol asignado al crear el usuario.',
            'changed' => 'Rol del usuario actualizado.',
            'removed' => 'Rol del usuario retirado.',
        ];

        UserRoleHistory::create([
            'user_id' => $user->id,
            'role_id' => $roleId,
            'previous_role_id' => $previousRoleId,
            'action' => $action,
            'description' => $descriptions[$action] ?? null,
            'performed_by' => Auth::id(),
            'performed_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    private function userDisplayName(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        return trim($user->name.' '.$user->lastname);
    }
}
