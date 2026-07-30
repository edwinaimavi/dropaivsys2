<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.apisperu', [
        'token' => 'token-de-prueba',
        'base_url' => 'https://documentos.test/api/v1',
        'timeout' => 5,
    ]);
});

it('protege las consultas internas con autenticación', function () {
    $this->getJson(route('admin.document-lookup.ruc', '20123456789'))
        ->assertUnauthorized();
});

it('devuelve RUC normalizado desde la ruta interna', function () {
    Http::fake([
        'https://documentos.test/api/v1/ruc/20123456789*' => Http::response([
            'ruc' => '20123456789',
            'razonSocial' => 'EMPRESA DE PRUEBA S.A.C.',
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.document-lookup.ruc', '20123456789'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('business_name', 'EMPRESA DE PRUEBA S.A.C.');
});

it('devuelve DNI normalizado desde la ruta interna', function () {
    Http::fake([
        'https://documentos.test/api/v1/dni/70587639*' => Http::response([
            'success' => true,
            'dni' => '70587639',
            'nombres' => 'ANA',
            'apellidoPaterno' => 'PEREZ',
            'apellidoMaterno' => 'LOPEZ',
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.document-lookup.dni', '70587639'))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('full_name', 'ANA PEREZ LOPEZ');
});

it('responde con errores controlados y nunca con 500', function () {
    Http::fake(['*' => Http::response([], 404)]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('admin.document-lookup.ruc', '20123456789'))
        ->assertNotFound()
        ->assertJsonPath('code', 'RUC_NOT_FOUND')
        ->assertJsonPath('success', false);
});
