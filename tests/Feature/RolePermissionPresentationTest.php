<?php

use App\Models\User;
use App\Services\RolePermissionPresentationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function rolePermissionCatalog(): array
{
    $reflection = new ReflectionClass(RoleSeeder::class);
    $method = $reflection->getMethod('permissions');
    $method->setAccessible(true);

    return $method->invoke(new RoleSeeder);
}

function registerRolePermissionCatalog(): void
{
    foreach (rolePermissionCatalog() as $name => $description) {
        Permission::create(['name' => $name, 'guard_name' => 'web', 'description' => $description]);
    }
}

function flattenedPresentedPermissions(array $presentation): Collection
{
    return collect($presentation['modules'])
        ->flatMap(fn (array $module) => collect($module['subgroups'])->flatMap(fn (array $subgroup) => $subgroup['permissions']));
}

test('todos los permisos del catalogo aparecen exactamente una vez y ninguno cae en fallback', function () {
    registerRolePermissionCatalog();
    $permissions = Permission::query()->orderBy('id')->get();
    $presentation = app(RolePermissionPresentationService::class)->present($permissions);
    $presented = flattenedPresentedPermissions($presentation);

    expect($permissions)->toHaveCount(268)
        ->and($presented)->toHaveCount($permissions->count())
        ->and($presented->pluck('name')->unique())->toHaveCount($permissions->count())
        ->and($presented->pluck('name')->sort()->values()->all())->toBe($permissions->pluck('name')->sort()->values()->all())
        ->and($presentation['unclassified'])->toBe([]);
});

test('el editor renderiza exactamente un checkbox por permiso registrado', function () {
    registerRolePermissionCatalog();
    $user = User::factory()->create();
    $user->givePermissionTo('admin.roles.index');

    $html = $this->actingAs($user)->get(route('admin.roles.index'))->assertOk()->getContent();
    preg_match_all('/name="permissions\[\]"\s+value="([^"]+)"|value="([^"]+)"[^>]+name="permissions\[\]"/', $html, $matches);
    $names = collect($matches[1])->zip($matches[2])->map(fn ($pair) => $pair[0] ?: $pair[1]);

    expect($names)->toHaveCount(268)
        ->and($names->unique())->toHaveCount(268)
        ->and($names->sort()->values()->all())->toBe(Permission::pluck('name')->sort()->values()->all());
});

test('agenda de trabajo contiene todos sus permisos en el mismo submodulo', function () {
    registerRolePermissionCatalog();
    $presentation = app(RolePermissionPresentationService::class)->present(Permission::all());
    $agenda = collect($presentation['modules'])->firstWhere('key', 'agenda');
    $workAgenda = collect($agenda['subgroups'])->firstWhere('key', 'work_agenda');

    expect(collect($workAgenda['permissions'])->pluck('name')->sort()->values()->all())->toBe(collect([
        'agenda_trabajo.ver', 'agenda_trabajo.ver_todos', 'agenda_trabajo.crear', 'agenda_trabajo.editar',
        'agenda_trabajo.eliminar', 'agenda_trabajo.cambiar_estado', 'agenda_trabajo.asignar', 'agenda_trabajo.derivar',
    ])->sort()->values()->all());
});

test('ajustes bancarios queda bajo bancos y tesoreria', function () {
    registerRolePermissionCatalog();
    $presentation = app(RolePermissionPresentationService::class)->present(Permission::all());
    $banks = collect($presentation['modules'])->firstWhere('key', 'banks');
    $adjustments = collect($banks['subgroups'])->firstWhere('key', 'adjustments');

    expect($banks['label'])->toBe('Bancos / Tesorería')
        ->and($adjustments['label'])->toBe('Ajustes bancarios')
        ->and(collect($adjustments['permissions'])->pluck('name')->all())->toBe(['admin.banks.adjustments'])
        ->and($adjustments['permissions'][0]['label'])->toBe('Registrar ajustes bancarios');
});

test('un permiso desconocido aparece una vez en otros sin clasificar', function () {
    $permission = Permission::create(['name' => 'custom.unmapped.permission', 'guard_name' => 'web', 'description' => 'Permiso futuro']);
    $presentation = app(RolePermissionPresentationService::class)->present(collect([$permission]));
    $fallback = collect($presentation['modules'])->firstWhere('key', RolePermissionPresentationService::FALLBACK_MODULE);

    expect($presentation['unclassified'])->toBe(['custom.unmapped.permission'])
        ->and($fallback['label'])->toBe('Otros / Sin clasificar')
        ->and(flattenedPresentedPermissions($presentation)->pluck('name')->all())->toBe(['custom.unmapped.permission']);
});

test('un rol conserva exactamente sus permisos al cargar y guardar sin cambios', function () {
    registerRolePermissionCatalog();
    $user = User::factory()->create();
    $user->givePermissionTo(['admin.roles.show', 'admin.roles.update']);
    $role = Role::create(['name' => 'Supervisor de prueba', 'guard_name' => 'web']);
    $expected = ['admin.banks.adjustments', 'agenda_trabajo.derivar', 'admin.kardex.index'];
    $role->givePermissionTo($expected);

    $loaded = $this->actingAs($user)->getJson(route('admin.roles.permissions', $role))->assertOk()->json();
    expect($loaded)->toEqualCanonicalizing($expected);

    $this->putJson(route('admin.roles.update', $role), ['name' => $role->name, 'permissions' => $loaded])->assertOk();
    expect($role->fresh()->permissions->pluck('name')->all())->toEqualCanonicalizing($expected);
});
