<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

it('protege los endpoints del perfil con autenticación', function () {
    $this->getJson(route('admin.profile.show'))->assertUnauthorized();
    $this->putJson(route('admin.profile.update'), [])->assertUnauthorized();
});

it('integra el botón y el modal de perfil en el layout administrativo', function () {
    $user = createProfileUser(['dni' => '20000000']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('id="btnOpenUserProfile"', false)
        ->assertSee('id="modalUserProfile"', false)
        ->assertSee('id="profile-personal-tab"', false)
        ->assertSee('id="profile-security-tab"', false)
        ->assertSee('id="profile-trace-tab"', false)
        ->assertDontSee('name="role"', false)
        ->assertDontSee('name="status"', false);
});

it('muestra únicamente el perfil del usuario autenticado sin exigir permisos administrativos', function () {
    $user = createProfileUser(['dni' => '20000001']);
    $role = Role::findOrCreate('Vendedor', 'web');
    $user->assignRole($role);

    $response = $this->actingAs($user)->getJson(route('admin.profile.show'));

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'Vendedor')
        ->assertJsonPath('data.status_label', 'Activo')
        ->assertJsonPath('data.created_by', 'No registrado / histórico');
});

it('actualiza solo datos personales del usuario autenticado y conserva rol y estado', function () {
    $user = createProfileUser(['dni' => '20000011']);
    $otherUser = createProfileUser([
        'dni' => '20000012',
        'email' => 'otro.perfil@example.com',
    ]);
    $adminRole = Role::findOrCreate('Administrador', 'web');
    $vendorRole = Role::findOrCreate('Vendedor', 'web');
    $user->assignRole($adminRole);

    $response = $this->actingAs($user)->putJson(route('admin.profile.update'), [
        'user_id' => $otherUser->id,
        'dni' => '20000013',
        'name' => 'Perfil',
        'lastname' => 'Actualizado',
        'email' => 'perfil.actualizado@example.com',
        'phone' => '988888888',
        'address' => 'Dirección actualizada',
        'role' => $vendorRole->id,
        'status' => 0,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Perfil')
        ->assertJsonPath('data.role', 'Administrador')
        ->assertJsonPath('data.status', 1);

    $user->refresh();
    expect($user->dni)->toBe('20000013')
        ->and($user->email)->toBe('perfil.actualizado@example.com')
        ->and($user->updated_by)->toBe($user->id)
        ->and($user->status)->toBe(1)
        ->and($user->hasRole('Administrador'))->toBeTrue()
        ->and($otherUser->fresh()->email)->toBe('otro.perfil@example.com');
});

it('exige la contraseña actual antes de cambiar la clave', function () {
    $user = createProfileUser(['dni' => '20000021']);
    $payload = profilePayload($user, [
        'current_password' => 'incorrecta',
        'password' => 'nueva-clave-123',
        'password_confirmation' => 'nueva-clave-123',
    ]);

    $this->actingAs($user)
        ->putJson(route('admin.profile.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password')
        ->assertJsonPath('errors.current_password.0', 'La contraseña actual no es correcta.');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();

    $payload['current_password'] = 'password';
    $this->actingAs($user)
        ->putJson(route('admin.profile.update'), $payload)
        ->assertOk();

    expect(Hash::check('nueva-clave-123', $user->fresh()->password))->toBeTrue();
});

it('actualiza la foto de perfil y elimina de forma segura la anterior', function () {
    Storage::fake('public');
    Storage::disk('public')->put('users/anterior.png', 'old-photo');
    $user = createProfileUser([
        'dni' => '20000031',
        'photo' => 'users/anterior.png',
    ]);

    $response = $this->actingAs($user)
        ->withHeader('Accept', 'application/json')
        ->post(route('admin.profile.update'), array_merge(profilePayload($user), [
            '_method' => 'PUT',
            'image' => UploadedFile::fake()->image('perfil.png', 500, 500),
        ]));

    $response->assertOk();
    $photo = $user->fresh()->photo;

    expect($photo)->not->toBe('users/anterior.png')
        ->and($response->json('data.photo_url'))->toContain('/storage/users/');
    Storage::disk('public')->assertExists($photo);
    Storage::disk('public')->assertMissing('users/anterior.png');
});

function createProfileUser(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'dni' => fake()->unique()->numerify('########'),
        'name' => 'Usuario',
        'lastname' => 'Perfil',
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'phone' => '999999999',
        'address' => 'Dirección de prueba',
        'status' => 1,
    ], $attributes));
}

function profilePayload(User $user, array $attributes = []): array
{
    return array_merge([
        'dni' => $user->dni,
        'name' => $user->name,
        'lastname' => $user->lastname,
        'email' => $user->email,
        'phone' => $user->phone,
        'address' => $user->address,
    ], $attributes);
}
