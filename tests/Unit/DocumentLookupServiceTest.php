<?php

use App\Services\DocumentLookupService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config()->set('services.apisperu', [
        'token' => 'token-de-prueba',
        'base_url' => 'https://documentos.test/api/v1',
        'timeout' => 5,
    ]);
});

it('consulta y normaliza un RUC con token como query parameter', function () {
    Http::fake([
        'https://documentos.test/api/v1/ruc/20123456789*' => Http::response([
            'ruc' => '20123456789',
            'razonSocial' => 'PROVEEDOR DE PRUEBA S.A.C.',
            'nombreComercial' => 'PROVEEDOR',
            'direccion' => 'AV. PRUEBA 123',
        ]),
    ]);

    $result = app(DocumentLookupService::class)->searchRuc('20123456789');

    expect($result)
        ->success->toBeTrue()
        ->business_name->toBe('PROVEEDOR DE PRUEBA S.A.C.')
        ->razon_social->toBe('PROVEEDOR DE PRUEBA S.A.C.')
        ->commercial_name->toBe('PROVEEDOR')
        ->address->toBe('AV. PRUEBA 123');

    Http::assertSent(fn ($request) => $request->url() === 'https://documentos.test/api/v1/ruc/20123456789?token=token-de-prueba'
        && !$request->hasHeader('Authorization'));
});

it('consulta y normaliza un DNI', function () {
    Http::fake([
        'https://documentos.test/api/v1/dni/70587639*' => Http::response([
            'success' => true,
            'dni' => '70587639',
            'nombres' => 'JUAN CARLOS',
            'apellidoPaterno' => 'PEREZ',
            'apellidoMaterno' => 'LOPEZ',
        ]),
    ]);

    $result = app(DocumentLookupService::class)->searchDni('70587639');

    expect($result)
        ->success->toBeTrue()
        ->names->toBe('JUAN CARLOS')
        ->paternal_lastname->toBe('PEREZ')
        ->maternal_lastname->toBe('LOPEZ')
        ->full_name->toBe('JUAN CARLOS PEREZ LOPEZ');
});

it('valida documentos antes de llamar a la API', function () {
    Http::fake();

    expect(app(DocumentLookupService::class)->searchRuc('123')['code'])->toBe('INVALID_RUC')
        ->and(app(DocumentLookupService::class)->searchDni('123')['code'])->toBe('INVALID_DNI');

    Http::assertNothingSent();
});

it('distingue errores remotos y respuestas incompletas', function (int $status, string $code) {
    Http::fake(['*' => Http::response(['message' => 'detalle remoto'], $status)]);

    expect(app(DocumentLookupService::class)->searchRuc('20123456789')['code'])->toBe($code);
})->with([
    [401, 'AUTH_ERROR'],
    [403, 'AUTH_ERROR'],
    [404, 'RUC_NOT_FOUND'],
    [422, 'INVALID_RUC'],
    [500, 'API_ERROR'],
]);

it('permite completar manualmente cuando falta configuración', function () {
    config()->set('services.apisperu.token', null);

    $result = app(DocumentLookupService::class)->searchDni('70587639');

    expect($result)
        ->success->toBeFalse()
        ->code->toBe('SERVICE_NOT_CONFIGURED');
});
