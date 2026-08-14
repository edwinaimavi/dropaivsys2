<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class UserSecurityService
{
    public const ADMIN_ROLE = 'Administrador';

    public function principalUserId(): ?int
    {
        return User::query()->min('id');
    }

    public function isPrincipal(User $user): bool
    {
        return $user->id === $this->principalUserId();
    }

    public function ensureCanEdit(User $target, ?User $actor): void
    {
        if ($this->isPrincipal($target) && $actor?->id !== $target->id) {
            throw new AuthorizationException('No puedes modificar el usuario principal del sistema.');
        }
    }

    public function ensureAccessChangeIsSafe(User $target, ?User $actor, int $status, ?int $roleId): void
    {
        $currentRoleId = $target->roles->first()?->id;
        $isCurrentAdmin = $target->hasRole(self::ADMIN_ROLE);
        $newRoleIsAdmin = $roleId !== null && $roleId === $this->administratorRoleId();
        $deactivatesUser = $status !== 1;
        $removesAdminRole = $isCurrentAdmin && ! $newRoleIsAdmin;

        if ($this->isPrincipal($target) && ($deactivatesUser || $currentRoleId !== $roleId)) {
            throw new AuthorizationException('No puedes desactivar ni cambiar el rol del usuario principal del sistema.');
        }

        if ($actor?->id === $target->id && $isCurrentAdmin && ($deactivatesUser || $removesAdminRole)) {
            throw new AuthorizationException('No puedes quitarte el rol de administrador ni desactivar tu propio acceso.');
        }

        if ($target->status === 1
            && $isCurrentAdmin
            && ($deactivatesUser || $removesAdminRole)
            && $this->activeAdministratorCount() <= 1) {
            throw new AuthorizationException('No puedes dejar el sistema sin un usuario administrador activo.');
        }
    }

    public function ensureCanDelete(User $target, ?User $actor): void
    {
        if ($this->isPrincipal($target)) {
            throw new AuthorizationException('No puedes eliminar el usuario principal del sistema.');
        }

        if ($actor?->id === $target->id && $target->hasRole(self::ADMIN_ROLE)) {
            throw new AuthorizationException('No puedes eliminar tu propio usuario administrador.');
        }

        if ($target->status === 1
            && $target->hasRole(self::ADMIN_ROLE)
            && $this->activeAdministratorCount() <= 1) {
            throw new AuthorizationException('No puedes dejar el sistema sin un usuario administrador activo.');
        }
    }

    public function activeAdministratorCount(): int
    {
        return User::query()
            ->where('status', 1)
            ->whereHas('roles', fn ($query) => $query->where('name', self::ADMIN_ROLE))
            ->count();
    }

    private function administratorRoleId(): int
    {
        return (int) \Spatie\Permission\Models\Role::query()
            ->where('name', self::ADMIN_ROLE)
            ->value('id');
    }
}
