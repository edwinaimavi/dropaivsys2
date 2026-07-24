<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Presentation;
use App\Models\Subcategory;
use App\Models\Unit;

beforeEach(function () {
    $this->withoutMiddleware();

    $this->category = Category::create([
        'code' => 'CAT-TEST',
        'description' => 'CATEGORÍA DE PRUEBA',
        'type' => 'ARTICLE',
        'status' => 'ACTIVE',
    ]);
    $this->subcategory = Subcategory::create([
        'category_id' => $this->category->id,
        'description' => 'SUBCATEGORÍA DE PRUEBA',
        'status' => 'ACTIVE',
    ]);
    $this->unit = Unit::create([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'decimal_quantity' => false,
        'status' => 'ACTIVE',
    ]);
    $this->presentation = Presentation::create([
        'description' => 'PRESENTACIÓN',
        'quantity' => 1,
        'unit_id' => $this->unit->id,
        'status' => 'ACTIVE',
    ]);
});

function articlePayloadForValidationTest(string $code, ?string $institutionalCode, string $legalName): array
{
    return [
        'code' => $code,
        'code_type' => 'SIGA/SISMED',
        'institutional_code' => $institutionalCode,
        'category_id' => test()->category->id,
        'subcategory_id' => test()->subcategory->id,
        'presentation_id' => test()->presentation->id,
        'unit_id' => test()->unit->id,
        'legal_name' => $legalName,
        'commercial_name' => "COMERCIAL $code",
        'billing_name' => "FACTURACIÓN $code",
        'minimum_stock' => 0,
        'is_taxable' => 1,
        'has_batch' => 0,
        'has_expiration' => 0,
        'status' => 'ACTIVE',
        'documents_data' => '[]',
    ];
}

it('permite repetir nombre legal y bloquea el codigo institucional duplicado', function () {
    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART00001', '13216464', 'NOMBRE RARO')
    )->assertCreated();

    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART00002', '99999999', 'NOMBRE RARO')
    )->assertCreated();

    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART00003', '13216464', 'OTRO ARTÍCULO')
    )->assertUnprocessable()
        ->assertJsonPath(
            'errors.institutional_code.0',
            'El código institucional ya está registrado en otro artículo.'
        );

    expect(Article::where('legal_name', 'NOMBRE RARO')->count())->toBe(2);
});

it('ignora el articulo actual al validar codigo institucional durante la edicion', function () {
    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART00001', '13216464', 'ARTÍCULO A')
    )->assertCreated();
    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART00002', '99999999', 'ARTÍCULO B')
    )->assertCreated();

    $first = Article::where('code', 'ART00001')->firstOrFail();

    $this->putJson(
        route('admin.articles.update', $first),
        articlePayloadForValidationTest('ART00001', '13216464', 'ARTÍCULO A')
    )->assertOk();

    $this->putJson(
        route('admin.articles.update', $first),
        articlePayloadForValidationTest('ART00001', '99999999', 'ARTÍCULO A')
    )->assertUnprocessable()
        ->assertJsonPath(
            'errors.institutional_code.0',
            'El código institucional ya está registrado en otro artículo.'
        );
});

it('permite varios articulos sin codigo institucional', function () {
    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART00001', null, 'ARTÍCULO SIN CÓDIGO A')
    )->assertCreated();

    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART00002', null, 'ARTÍCULO SIN CÓDIGO B')
    )->assertCreated();

    expect(Article::whereNull('institutional_code')->count())->toBe(2);
});
