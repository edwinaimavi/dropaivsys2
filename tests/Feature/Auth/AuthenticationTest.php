<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('required login errors use the styled Spanish field messages', function () {
    $this->from('/login')
        ->post('/login', [])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email', 'password']);

    $response = $this->get('/login');

    $response->assertOk()
        ->assertSeeText('El correo electrónico es obligatorio.')
        ->assertSeeText('La contraseña es obligatoria.')
        ->assertSee('auth-field-error', false)
        ->assertSee('auth-input-error', false)
        ->assertSee('aria-describedby="email-error"', false)
        ->assertSee('aria-describedby="password-error"', false);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login');

    $response->assertHasErrors('email');

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $response->assertRedirect('/');

    $this->assertGuest();
});
