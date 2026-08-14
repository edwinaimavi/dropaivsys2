<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return response()->json([
            'data' => $this->profileData($request->user()),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'dni' => [
                'nullable',
                'digits:8',
                Rule::unique('users', 'dni')->ignore($user->id),
            ],
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'lastname' => ['nullable', 'string', 'min:3', 'max:50'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'min:9', 'max:15'],
            'address' => ['nullable', 'string', 'min:3', 'max:150'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'min:8', 'max:64', 'confirmed'],
        ], [
            'current_password.required_with' => 'La contraseña actual es obligatoria para cambiar la clave.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
        ], [
            'current_password' => 'contraseña actual',
            'image' => 'foto de perfil',
        ]);

        $newPhoto = $request->hasFile('image')
            ? $request->file('image')->store('users', 'public')
            : null;
        $oldPhoto = $user->photo;

        $profile = collect($validated)->only([
            'dni',
            'name',
            'lastname',
            'email',
            'phone',
            'address',
        ])->all();
        $profile['updated_by'] = $user->id;

        if (! empty($validated['password'])) {
            $profile['password'] = Hash::make($validated['password']);
        }

        if ($newPhoto) {
            $profile['photo'] = $newPhoto;
        }

        try {
            $user->update($profile);
        } catch (\Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }

            throw $exception;
        }

        if ($newPhoto && $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return response()->json([
            'message' => 'Tu perfil fue actualizado correctamente.',
            'data' => $this->profileData($user->fresh()),
        ]);
    }

    private function profileData(User $user): array
    {
        $user->loadMissing([
            'creator:id,name,lastname',
            'updater:id,name,lastname',
            'roles:id,name',
            'latestRoleHistory.performer:id,name,lastname',
        ]);

        $historical = 'No registrado / histórico';
        $roleHistory = $user->latestRoleHistory;
        $fullName = trim($user->name.' '.$user->lastname);
        $initials = collect(preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY))
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');

        return [
            'id' => $user->id,
            'dni' => $user->dni,
            'name' => $user->name,
            'lastname' => $user->lastname,
            'full_name' => $fullName ?: 'Usuario',
            'initials' => $initials ?: 'U',
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'photo_url' => $user->photo ? Storage::url($user->photo) : null,
            'role' => $user->roles->first()?->name ?? 'Sin rol',
            'status' => (int) $user->status,
            'status_label' => (int) $user->status === 1 ? 'Activo' : 'Inactivo',
            'created_at' => $user->created_at?->format('d/m/Y H:i') ?? $historical,
            'created_by' => $this->userName($user->creator) ?? $historical,
            'updated_at' => $user->updated_at?->format('d/m/Y H:i') ?? $historical,
            'updated_by' => $this->userName($user->updater) ?? $historical,
            'last_role_changed_by' => $this->userName($roleHistory?->performer) ?? $historical,
            'last_role_changed_at' => $roleHistory?->performed_at?->format('d/m/Y H:i') ?? $historical,
        ];
    }

    private function userName(?User $user): ?string
    {
        return $user ? trim($user->name.' '.$user->lastname) : null;
    }
}
