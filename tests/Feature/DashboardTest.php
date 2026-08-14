<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertStatus(200);
});

test('dashboard displays registered users with role status and short name', function () {
    $viewer = User::factory()->create([
        'name' => 'Zulema',
        'lastname' => 'Visor',
        'status' => 1,
    ]);

    $activeUser = User::factory()->create([
        'name' => 'Edwin Alcides',
        'lastname' => 'Cigüeñas Piña',
        'status' => 1,
    ]);
    $activeUser->assignRole(Role::findOrCreate('Logística', 'web'));

    $inactiveUser = User::factory()->create([
        'name' => 'Marta Elena',
        'lastname' => null,
        'status' => 0,
    ]);

    $deletedUser = User::factory()->create([
        'name' => 'Usuario Eliminado',
        'status' => -1,
    ]);

    $response = $this->actingAs($viewer)->get('/home');

    $response->assertOk()
        ->assertSee('Equipo registrado')
        ->assertSee('Usuarios con acceso al sistema')
        ->assertSee('Edwin')
        ->assertDontSee('Edwin Alcides')
        ->assertSee('<span>EC</span>', false)
        ->assertSee('Logística')
        ->assertSee('Activo')
        ->assertSee('Marta')
        ->assertSee('Sin rol')
        ->assertSee('Inactivo')
        ->assertDontSee($deletedUser->name);

    $dashboardUsers = $response->viewData('dashboardUsers');
    $dashboardHtml = $response->getContent();

    expect($dashboardUsers->pluck('id')->all())
        ->toContain($activeUser->id, $inactiveUser->id)
        ->not->toContain($deletedUser->id)
        ->and($dashboardUsers->every->relationLoaded('roles'))->toBeTrue()
        ->and($dashboardUsers->firstWhere('id', $activeUser->id)->getAttributes())->not->toHaveKey('email')
        ->and($dashboardUsers->search(fn (User $user) => $user->id === $activeUser->id))
        ->toBeLessThan($dashboardUsers->search(fn (User $user) => $user->id === $inactiveUser->id))
        ->and(strpos($dashboardHtml, 'Centro de comando operativo'))->toBeLessThan(strpos($dashboardHtml, 'Equipo registrado'))
        ->and(strpos($dashboardHtml, 'Equipo registrado'))->toBeLessThan(strpos($dashboardHtml, 'Indicadores clave'))
        ->and(strpos($dashboardHtml, 'Indicadores clave'))->toBeLessThan(strpos($dashboardHtml, 'Flujo operativo'));
});

test('dashboard limits the registered team preview to eight users', function () {
    $viewer = User::factory()->create(['status' => 1]);
    User::factory()->count(10)->create(['status' => 1]);

    $response = $this->actingAs($viewer)->get('/dashboard');

    $response->assertOk()
        ->assertViewHas('dashboardUsers', fn ($users) => $users->count() === 8)
        ->assertViewHas('dashboardUsersTotal', 11)
        ->assertSee('+ 3 m&aacute;s', false);
});

test('dashboard team hover enlarges the avatar only on precise pointers', function () {
    $dashboardCss = file_get_contents(resource_path('css/dashboard.css'));

    expect($dashboardCss)
        ->toContain('@media(hover:hover) and (pointer:fine)')
        ->toContain('.team-member:hover .team-member__avatar')
        ->toContain('transform:scale(1.4)');
});
