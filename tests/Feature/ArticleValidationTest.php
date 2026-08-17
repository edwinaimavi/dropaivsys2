<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\DocumentType;
use App\Models\Presentation;
use App\Models\Subcategory;
use App\Models\Unit;
use App\Services\ArticleCodeGenerator;
use Illuminate\Support\Facades\Storage;

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

it('edita los datos de un documento existente sin exigir un archivo nuevo', function () {
    Storage::fake('public');
    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART-DOC', null, 'ARTÍCULO DOCUMENTADO')
    )->assertCreated();
    $article = Article::where('code', 'ART-DOC')->firstOrFail();
    $type = DocumentType::create(['code' => 'FICHA_TEST', 'description' => 'FICHA TÉCNICA', 'status' => 'ACTIVE']);
    Storage::disk('public')->put('articles/documento-original.pdf', 'contenido');
    $document = $article->documents()->create([
        'document_type_id' => $type->id,
        'original_name' => 'documento-original.pdf',
        'stored_name' => 'documento-original.pdf',
        'file_path' => 'articles/documento-original.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'file_size' => 9,
        'status' => 'ACTIVE',
    ]);
    $payload = articlePayloadForValidationTest('ART-DOC', null, 'ARTÍCULO DOCUMENTADO');
    $payload['documents_data'] = json_encode([[
        'id' => $document->id,
        'document_type_id' => $type->id,
        'issue_date' => '2026-08-04',
        'expiration_date' => '2027-08-04',
        'observation' => 'DOCUMENTO ACTUALIZADO',
    ]]);

    $this->putJson(route('admin.articles.update', $article), $payload)->assertOk();

    $document->refresh();
    expect($document->observation)->toBe('DOCUMENTO ACTUALIZADO')
        ->and($document->issue_date->format('Y-m-d'))->toBe('2026-08-04')
        ->and($document->original_name)->toBe('documento-original.pdf');
    Storage::disk('public')->assertExists('articles/documento-original.pdf');
});

it('calcula el siguiente codigo desde el mayor correlativo ART sin alterar codigos existentes', function () {
    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART00298', null, 'ARTÍCULO HISTÓRICO')
    )->assertCreated();

    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('LEGACY999999', null, 'ARTÍCULO LEGACY')
    )->assertCreated();

    $this->getJson(route('admin.articles.generateCode'))
        ->assertOk()
        ->assertJsonPath('code', 'ART000299');

    expect(Article::where('code', 'ART00298')->exists())->toBeTrue()
        ->and(Article::where('code', 'LEGACY999999')->exists())->toBeTrue();
});

it('confirma un codigo nuevo al guardar aunque la sugerencia automatica este vencida', function () {
    $suggestedCode = $this->getJson(route('admin.articles.generateCode'))
        ->assertOk()
        ->json('code');

    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest($suggestedCode, null, 'ARTÍCULO QUE OCUPÓ LA SUGERENCIA')
    )->assertCreated();

    $payload = articlePayloadForValidationTest($suggestedCode, null, 'ARTÍCULO AUTOMÁTICO');
    $payload['code_mode'] = 'automatic';

    $this->postJson(route('admin.articles.store'), $payload)
        ->assertCreated()
        ->assertJsonPath('data.code', 'ART000002');

    expect(Article::pluck('code')->all())->toContain('ART000001', 'ART000002');
});

it('genera codigos distintos en dos altas automaticas consecutivas', function () {
    $payloadA = articlePayloadForValidationTest('ART000001', null, 'ARTÍCULO AUTOMÁTICO A');
    $payloadA['code_mode'] = 'automatic';
    $payloadB = articlePayloadForValidationTest('ART000001', null, 'ARTÍCULO AUTOMÁTICO B');
    $payloadB['code_mode'] = 'automatic';

    $this->postJson(route('admin.articles.store'), $payloadA)
        ->assertCreated()
        ->assertJsonPath('data.code', 'ART000001');

    $this->postJson(route('admin.articles.store'), $payloadB)
        ->assertCreated()
        ->assertJsonPath('data.code', 'ART000002');

    expect(Article::distinct()->count('code'))->toBe(2);
});

it('muestra un error claro cuando un codigo manual esta duplicado', function () {
    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART-MANUAL', null, 'ARTÍCULO MANUAL A')
    )->assertCreated();

    $payload = articlePayloadForValidationTest('art-manual', null, 'ARTÍCULO MANUAL B');
    $payload['code_mode'] = 'manual';

    $this->postJson(route('admin.articles.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'El código del artículo ya está registrado.');
});

it('reintenta el correlativo cuando el indice unico detecta una colision', function () {
    $this->postJson(
        route('admin.articles.store'),
        articlePayloadForValidationTest('ART000001', null, 'ARTÍCULO EXISTENTE')
    )->assertCreated();

    $generator = Mockery::mock(ArticleCodeGenerator::class)->makePartial();
    $generator->shouldReceive('next')->once()->andReturn('ART000001');

    $article = $generator->create([
        'code_type' => 'SIGA/SISMED',
        'category_id' => $this->category->id,
        'subcategory_id' => $this->subcategory->id,
        'presentation_id' => $this->presentation->id,
        'unit_id' => $this->unit->id,
        'legal_name' => 'ARTÍCULO EN COLISIÓN',
        'commercial_name' => 'ARTÍCULO EN COLISIÓN',
        'billing_name' => 'ARTÍCULO EN COLISIÓN',
        'minimum_stock' => 0,
        'is_taxable' => 1,
        'has_batch' => 0,
        'has_expiration' => 0,
        'status' => 'ACTIVE',
    ]);

    expect($article->code)->toBe('ART000002')
        ->and(Article::distinct()->count('code'))->toBe(2);
});
