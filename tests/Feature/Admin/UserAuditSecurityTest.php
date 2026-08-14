<?php

use App\Models\User;
use App\Models\UserRoleHistory;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'admin.users.index',
        'admin.users.store',
        'admin.users.update',
        'admin.users.destroy',
        'admin.users.show',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->administratorRole = Role::findOrCreate('Administrador', 'web');
    $this->administratorRole->syncPermissions(Permission::all());
    $this->vendorRole = Role::findOrCreate('Vendedor', 'web');
});

it('audita al creador y la asignación inicial de rol', function () {
    $actor = createAuditUser(['dni' => '10000001']);
    $actor->assignRole($this->administratorRole);

    $response = $this->actingAs($actor)->postJson(route('admin.users.store'), [
        'dni' => '10000002',
        'name' => 'Usuario',
        'lastname' => 'Auditado',
        'email' => 'auditado@example.com',
        'password' => 'secret12',
        'password_confirmation' => 'secret12',
        'phone' => '999999999',
        'address' => 'Dirección de prueba',
        'status' => 1,
        'role' => $this->vendorRole->id,
    ]);

    $response->assertOk();

    $created = User::where('email', 'auditado@example.com')->firstOrFail();
    expect($created->created_by)->toBe($actor->id)
        ->and($created->updated_by)->toBe($actor->id);

    $this->assertDatabaseHas('user_role_histories', [
        'user_id' => $created->id,
        'role_id' => $this->vendorRole->id,
        'previous_role_id' => null,
        'action' => 'assigned',
        'performed_by' => $actor->id,
    ]);
});

it('audita al editor y el cambio de rol', function () {
    $actor = createAuditUser(['dni' => '10000011']);
    $actor->assignRole($this->administratorRole);
    $user = createAuditUser([
        'dni' => '10000012',
        'email' => 'cambio@example.com',
    ]);
    $user->assignRole($this->vendorRole);
    $newRole = Role::findOrCreate('Contabilidad', 'web');

    $response = $this->actingAs($actor)->putJson(route('admin.users.update', $user), [
        'dni' => $user->dni,
        'name' => 'Nombre Editado',
        'lastname' => $user->lastname,
        'email' => $user->email,
        'phone' => '988888888',
        'address' => 'Nueva dirección',
        'status' => 1,
        'role' => $newRole->id,
    ]);

    $response->assertOk();
    expect($user->fresh()->updated_by)->toBe($actor->id);

    $history = UserRoleHistory::where('user_id', $user->id)->latest('id')->firstOrFail();
    expect($history->action)->toBe('changed')
        ->and($history->previous_role_id)->toBe($this->vendorRole->id)
        ->and($history->role_id)->toBe($newRole->id)
        ->and($history->performed_by)->toBe($actor->id);

    $this->actingAs($actor)
        ->getJson(route('admin.users.show', $user))
        ->assertOk()
        ->assertJsonPath('data.updated_by', trim($actor->name.' '.$actor->lastname))
        ->assertJsonPath('data.last_role_changed_by', trim($actor->name.' '.$actor->lastname))
        ->assertJsonPath('data.current_role', 'Contabilidad');
});

it('audita la eliminación de un rol', function () {
    $actor = createAuditUser(['dni' => '10000013']);
    $actor->assignRole($this->administratorRole);
    $user = createAuditUser([
        'dni' => '10000014',
        'email' => 'sinrol@example.com',
    ]);
    $user->assignRole($this->vendorRole);

    $this->actingAs($actor)->putJson(route('admin.users.update', $user), [
        'dni' => $user->dni,
        'name' => $user->name,
        'lastname' => $user->lastname,
        'email' => $user->email,
        'phone' => $user->phone,
        'address' => $user->address,
        'status' => 1,
        'role' => null,
    ])->assertOk();

    expect($user->fresh()->roles)->toBeEmpty();
    $this->assertDatabaseHas('user_role_histories', [
        'user_id' => $user->id,
        'previous_role_id' => $this->vendorRole->id,
        'role_id' => null,
        'action' => 'removed',
        'performed_by' => $actor->id,
    ]);
});

it('muestra valores históricos cuando no existen responsables de auditoría', function () {
    $principal = createAuditUser(['dni' => '10000021']);
    $principal->assignRole($this->administratorRole);

    $response = $this->actingAs($principal)->getJson(route('admin.users.show', $principal));

    $response->assertOk()
        ->assertJsonPath('data.created_by', 'No registrado / histórico')
        ->assertJsonPath('data.updated_by', 'No registrado / histórico')
        ->assertJsonPath('data.last_role_changed_by', 'No registrado / histórico')
        ->assertJsonPath('data.is_principal', true);
});

it('impide que otro administrador modifique o elimine al usuario principal', function () {
    $principal = createAuditUser(['dni' => '10000031']);
    $principal->assignRole($this->administratorRole);
    $attacker = createAuditUser(['dni' => '10000032']);
    $attacker->assignRole($this->administratorRole);

    $payload = [
        'dni' => $principal->dni,
        'name' => 'Nombre Alterado',
        'lastname' => $principal->lastname,
        'email' => $principal->email,
        'phone' => $principal->phone,
        'address' => $principal->address,
        'status' => 1,
        'role' => $this->administratorRole->id,
    ];

    $this->actingAs($attacker)
        ->putJson(route('admin.users.update', $principal), $payload)
        ->assertForbidden()
        ->assertJsonPath('message', 'No puedes modificar el usuario principal del sistema.');

    $this->actingAs($attacker)
        ->deleteJson(route('admin.users.destroy', $principal))
        ->assertForbidden();

    $listResponse = $this->actingAs($attacker)->getJson(route('admin.users.list', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]))->assertOk();
    $principalRow = collect($listResponse->json('data'))->firstWhere('id', $principal->id);

    expect($principal->fresh()->name)->not->toBe('Nombre Alterado')
        ->and($principal->fresh()->status)->toBe(1)
        ->and($principalRow['roles_display'])->toContain('Protegido')
        ->and($principalRow['acciones'])->not->toContain('editUser')
        ->and($principalRow['acciones'])->not->toContain('deleteUser');
});

it('permite al principal editar solo sus datos personales', function () {
    $principal = createAuditUser(['dni' => '10000041']);
    $principal->assignRole($this->administratorRole);

    $this->actingAs($principal)->putJson(route('admin.users.update', $principal), [
        'dni' => $principal->dni,
        'name' => 'Nombre Permitido',
        'lastname' => 'Principal',
        'email' => 'principal.actualizado@example.com',
        'phone' => '977777777',
        'address' => 'Dirección permitida',
    ])->assertOk();

    expect($principal->fresh()->name)->toBe('Nombre Permitido')
        ->and($principal->fresh()->email)->toBe('principal.actualizado@example.com')
        ->and($principal->fresh()->updated_by)->toBe($principal->id);

    $this->actingAs($principal)->putJson(route('admin.users.update', $principal), [
        'dni' => $principal->dni,
        'name' => 'Nombre Permitido',
        'lastname' => 'Principal',
        'email' => 'principal.actualizado@example.com',
        'status' => 0,
        'role' => $this->vendorRole->id,
    ])->assertForbidden();
});

it('impide desactivar al único administrador activo', function () {
    $principal = createAuditUser(['dni' => '10000051']);
    $principal->assignRole($this->vendorRole);
    $onlyAdministrator = createAuditUser(['dni' => '10000052']);
    $onlyAdministrator->assignRole($this->administratorRole);
    $operator = createAuditUser(['dni' => '10000053']);
    $operator->givePermissionTo('admin.users.update');

    $response = $this->actingAs($operator)->putJson(route('admin.users.update', $onlyAdministrator), [
        'dni' => $onlyAdministrator->dni,
        'name' => $onlyAdministrator->name,
        'lastname' => $onlyAdministrator->lastname,
        'email' => $onlyAdministrator->email,
        'phone' => $onlyAdministrator->phone,
        'address' => $onlyAdministrator->address,
        'status' => 0,
        'role' => $this->administratorRole->id,
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'No puedes dejar el sistema sin un usuario administrador activo.');
    expect($onlyAdministrator->fresh()->status)->toBe(1);
});

it('impide que un administrador se quite a sí mismo el acceso', function () {
    $principal = createAuditUser(['dni' => '10000061']);
    $principal->assignRole($this->administratorRole);
    $administrator = createAuditUser(['dni' => '10000062']);
    $administrator->assignRole($this->administratorRole);

    $response = $this->actingAs($administrator)->putJson(route('admin.users.update', $administrator), [
        'dni' => $administrator->dni,
        'name' => $administrator->name,
        'lastname' => $administrator->lastname,
        'email' => $administrator->email,
        'phone' => $administrator->phone,
        'address' => $administrator->address,
        'status' => 1,
        'role' => $this->vendorRole->id,
    ]);

    $response->assertForbidden()
        ->assertJsonPath('message', 'No puedes quitarte el rol de administrador ni desactivar tu propio acceso.');
    expect($administrator->fresh()->hasRole('Administrador'))->toBeTrue();
});

function createAuditUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'dni' => fake()->unique()->numerify('########'),
        'name' => 'Usuario',
        'lastname' => 'Prueba',
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'phone' => '999999999',
        'address' => 'Dirección de prueba',
        'status' => 1,
    ], $attributes));
}
