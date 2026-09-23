<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Presentation;
use App\Models\Subcategory;
use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Services\ArticleSunatExistenceTypeAuditService;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware();

    $this->category = Category::create([
        'code' => 'CAT-S05', 'description' => 'CATEGORÍA SUNAT 05', 'type' => 'ARTICLE', 'status' => 'ACTIVE',
    ]);
    $this->unit = Unit::create([
        'abbreviation' => 'UND', 'description' => 'UNIDAD', 'decimal_quantity' => false, 'status' => 'ACTIVE',
    ]);
    $this->subcategory = Subcategory::create([
        'category_id' => $this->category->id, 'description' => 'SUBCATEGORÍA SUNAT 05', 'status' => 'ACTIVE',
    ]);
    $this->presentation = Presentation::create([
        'description' => 'PRESENTACIÓN SUNAT 05', 'quantity' => 1,
        'unit_id' => $this->unit->id, 'status' => 'ACTIVE',
    ]);

    $catalog05 = SunatCatalog::create([
        'code' => '05', 'name' => 'TIPO DE EXISTENCIA', 'is_active' => true,
    ]);
    $this->merchandiseType = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog05->id, 'catalog_code' => '05', 'item_code' => '01',
        'description' => 'MERCADERÍAS', 'status' => 'ACTIVE',
    ]);
    $this->finishedProductType = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog05->id, 'catalog_code' => '05', 'item_code' => '02',
        'description' => 'PRODUCTOS TERMINADOS', 'status' => 'ACTIVE',
    ]);
    $this->inactiveType = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog05->id, 'catalog_code' => '05', 'item_code' => '03',
        'description' => 'MATERIAS PRIMAS', 'status' => 'INACTIVE',
    ]);

    $catalog06 = SunatCatalog::create([
        'code' => '06', 'name' => 'UNIDAD DE MEDIDA', 'is_active' => true,
    ]);
    $this->catalog06Item = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog06->id, 'catalog_code' => '06', 'item_code' => 'NIU',
        'description' => 'UNIDAD', 'status' => 'ACTIVE',
    ]);
    $this->unit->update(['sunat_unit_item_id' => $this->catalog06Item->id]);
    $catalog13 = SunatCatalog::create([
        'code' => '13', 'name' => 'CATÁLOGO DE EXISTENCIAS', 'is_active' => true,
    ]);
    $this->other13 = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog13->id, 'catalog_code' => '13', 'item_code' => '9',
        'description' => 'OTROS', 'status' => 'ACTIVE',
    ]);
});

function sunat05ArticlePayload(string $code, array $overrides = []): array
{
    return array_merge([
        'code' => $code,
        'code_mode' => 'manual',
        'code_type' => 'SIGA/SISMED',
        'category_id' => test()->category->id,
        'subcategory_id' => test()->subcategory->id,
        'presentation_id' => test()->presentation->id,
        'unit_id' => test()->unit->id,
        'legal_name' => $code,
        'commercial_name' => $code,
        'billing_name' => $code,
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => 1,
        'sunat_existence_type_item_id' => test()->merchandiseType->id,
        'sunat_inventory_catalog_item_id' => test()->other13->id,
        'sunat_inventory_catalog_code' => $code,
        'minimum_stock' => 0,
        'is_taxable' => 1,
        'sales_tax_affectation_code' => '10',
        'has_batch' => 0,
        'has_expiration' => 0,
        'status' => 'ACTIVE',
        'documents_data' => '[]',
    ], $overrides);
}

function sunat05Article(string $code, array $overrides = []): Article
{
    return Article::create(array_merge([
        'code' => $code,
        'category_id' => test()->category->id,
        'unit_id' => test()->unit->id,
        'legal_name' => $code,
        'billing_name' => $code,
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        'sunat_existence_type_item_id' => test()->merchandiseType->id,
        'sunat_inventory_catalog_item_id' => test()->other13->id,
        'sunat_inventory_catalog_code' => $code,
        'status' => 'ACTIVE',
    ], $overrides));
}

function sunat05PhysicalHistory(Article $article, string $suffix): void
{
    $company = Company::create([
        'business_name' => 'EMPRESA '.$suffix,
        'ruc' => '20'.str_pad((string) $article->id, 9, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
    $warehouse = Warehouse::create([
        'code' => 'ALM-'.$suffix, 'name' => 'ALMACÉN '.$suffix, 'status' => 'ACTIVE',
    ]);
    DB::table('warehouse_stocks')->insert([
        'stock_key' => 'S05-'.$suffix,
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'article_id' => $article->id,
        'current_quantity' => 1,
        'reserved_quantity' => 0,
        'average_unit_cost' => 10,
        'total_cost' => 10,
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function sunat05WarehouseEntry(Article $article, string $suffix): WarehouseEntry
{
    $company = Company::create([
        'business_name' => 'EMPRESA INGRESO '.$suffix,
        'ruc' => '21'.str_pad((string) $article->id, 9, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
    $warehouse = Warehouse::create([
        'code' => 'WE-'.$suffix, 'name' => 'ALMACÉN INGRESO '.$suffix, 'status' => 'ACTIVE',
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '22'.str_pad((string) $article->id, 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR '.$suffix, 'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO', 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'M'.$article->id, 'description' => 'MONEDA '.$suffix, 'symbol' => 'S/',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-'.$suffix,
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'status' => 'registered',
    ]);
    $entry->items()->create([
        'article_id' => $article->id,
        'article_code' => $article->code,
        'billing_name_snapshot' => $article->billing_name,
        'unit_id' => test()->unit->id,
        'quantity' => 2,
        'unit_price' => 10,
        'subtotal' => 20,
        'tax_amount' => 0,
        'line_total' => 20,
        'status' => 'active',
    ]);

    return $entry;
}

it('agrega la FK nullable y crea un producto inventariable con un Tipo 05 válido', function () {
    expect(Schema::hasColumn('articles', 'sunat_existence_type_item_id'))->toBeTrue();

    $this->postJson(route('admin.articles.store'), sunat05ArticlePayload('S05-VALIDO'))
        ->assertCreated();

    $article = Article::where('code', 'S05-VALIDO')->firstOrFail();
    expect($article->sunatExistenceType->item_code)->toBe('01');
});

it('expone únicamente los items activos del catálogo SUNAT 05 con código y descripción', function () {
    $this->getJson(route('admin.articles.sunat-existence-types'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.text', '01 — MERCADERÍAS')
        ->assertJsonPath('data.1.text', '02 — PRODUCTOS TERMINADOS');
});

it('rechaza un inventariable sin Tipo 05, inexistente, inactivo o de otro catálogo', function ($value) {
    $payload = sunat05ArticlePayload('S05-INVALIDO', [
        'sunat_existence_type_item_id' => is_callable($value) ? $value() : $value,
    ]);

    $this->postJson(route('admin.articles.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sunat_existence_type_item_id');
})->with([
    'ausente' => [null],
    'inexistente' => [999999],
    'inactivo' => [fn () => test()->inactiveType->id],
    'catálogo 06' => [fn () => test()->catalog06Item->id],
]);

it('servicios y productos no inventariables no requieren Tipo 05 y rechazan enviarlo', function ($kind, $inventory) {
    $code = 'S05-NO-'.strtoupper($kind).'-'.$inventory;
    $payload = sunat05ArticlePayload($code, [
        'item_kind' => $kind,
        'is_inventory_item' => $inventory,
        'sunat_existence_type_item_id' => null,
        'sunat_inventory_catalog_item_id' => null,
        'sunat_inventory_catalog_code' => null,
    ]);

    $this->postJson(route('admin.articles.store'), $payload)->assertCreated();
    expect(Article::where('code', $code)->value('sunat_existence_type_item_id'))->toBeNull();

    $payload['code'] .= '-BAD';
    $payload['sunat_existence_type_item_id'] = $this->merchandiseType->id;
    $this->postJson(route('admin.articles.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sunat_existence_type_item_id');
})->with([
    'servicio' => [Article::KIND_SERVICE, 0],
    'producto no inventariable' => [Article::KIND_PRODUCT, 0],
]);

it('muestra PENDIENTE para inventariable legacy sin Tabla 05', function () {
    $article = sunat05Article('S05-PENDIENTE', ['sunat_existence_type_item_id' => null]);

    $this->getJson(route('admin.articles.showData', $article))
        ->assertOk()
        ->assertJsonPath('data.sunat_existence_type_item_id', null)
        ->assertJsonPath('data.sunat_existence_type', null);

    expect(file_get_contents(resource_path('js/pages/article.js')))
        ->toContain('? `${article.sunat_existence_type.item_code} — ${article.sunat_existence_type.description}`')
        ->toContain(": 'PENDIENTE'");
});

it('permite la asignación inicial aun con historial y bloquea cambiarla después', function () {
    $article = sunat05Article('S05-LEGACY', ['sunat_existence_type_item_id' => null]);
    sunat05PhysicalHistory($article, 'LEGACY');

    $this->putJson(route('admin.articles.update', $article), sunat05ArticlePayload($article->code))
        ->assertOk();
    expect((int) $article->fresh()->sunat_existence_type_item_id)->toBe($this->merchandiseType->id);

    $this->putJson(route('admin.articles.update', $article), sunat05ArticlePayload($article->code, [
        'sunat_existence_type_item_id' => $this->finishedProductType->id,
    ]))->assertUnprocessable()
        ->assertJsonPath(
            'errors.sunat_existence_type_item_id.0',
            'El tipo de existencia SUNAT no puede modificarse porque el artículo ya tiene historial de inventario.'
        );

    expect((int) $article->fresh()->sunat_existence_type_item_id)->toBe($this->merchandiseType->id);
});

it('permite cambiar el Tipo 05 cuando no existe historial físico', function () {
    $article = sunat05Article('S05-SIN-HISTORIAL');

    $this->putJson(route('admin.articles.update', $article), sunat05ArticlePayload($article->code, [
        'sunat_existence_type_item_id' => $this->finishedProductType->id,
    ]))->assertOk();

    expect((int) $article->fresh()->sunat_existence_type_item_id)->toBe($this->finishedProductType->id);
});

it('bloquea un ingreso nuevo sin Tipo 05 sin alterar cantidades, costos ni Kardex', function () {
    $article = sunat05Article('S05-ENTRY-BLOCK', ['sunat_existence_type_item_id' => null]);
    $entry = sunat05WarehouseEntry($article, 'BLOCK');

    expect(fn () => app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($entry))
        ->toThrow(ValidationException::class, 'El artículo no tiene configurado su Tipo de Existencia SUNAT.');

    expect(DB::table('warehouse_stocks')->count())->toBe(0)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe(0);
});

it('bloquea un ingreso nuevo sin unidad SUNAT sin alterar cantidades, costos ni Kardex', function () {
    $article = sunat05Article('S06-ENTRY-BLOCK');
    $this->unit->update(['sunat_unit_item_id' => null]);
    $entry = sunat05WarehouseEntry($article, 'UNIT-BLOCK');

    expect(fn () => app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($entry))
        ->toThrow(ValidationException::class, 'La unidad del artículo no tiene configurado su código SUNAT.');

    expect(DB::table('warehouse_stocks')->count())->toBe(0)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe(0);
});

it('mantiene operativo el ingreso configurado sin cambiar clasificación ni company_id', function () {
    $article = sunat05Article('S05-ENTRY-OK');
    $entry = sunat05WarehouseEntry($article, 'OK');
    $companyId = $entry->company_id;

    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($entry);

    $stock = DB::table('warehouse_stocks')->first();
    $movement = DB::table('warehouse_kardex_movements')->first();
    expect((float) $stock->current_quantity)->toBe(2.0)
        ->and((float) $stock->average_unit_cost)->toBe(10.0)
        ->and((float) $stock->total_cost)->toBe(20.0)
        ->and((int) $stock->company_id)->toBe((int) $companyId)
        ->and((float) $movement->quantity_in)->toBe(2.0)
        ->and((float) $movement->unit_cost)->toBe(10.0)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe(1)
        ->and($article->fresh()->item_kind)->toBe(Article::KIND_PRODUCT)
        ->and($article->fresh()->is_inventory_item)->toBeTrue();
});

it('audita en dry-run sin asignar candidatos ni escribir datos', function () {
    $configured = sunat05Article('S05-AUD-OK');
    $pending = sunat05Article('S05-AUD-PEND', ['sunat_existence_type_item_id' => null]);
    sunat05Article('S05-AUD-SERVICE', [
        'item_kind' => Article::KIND_SERVICE,
        'is_inventory_item' => false,
        'sunat_existence_type_item_id' => null,
    ]);

    $before = Article::query()->pluck('sunat_existence_type_item_id', 'id')->all();
    $result = app(ArticleSunatExistenceTypeAuditService::class)->analyze();
    $exitCode = Artisan::call('articles:audit-sunat-existence-type');
    $output = Artisan::output();

    expect($result['total_articles'])->toBe(3)
        ->and($result['inventory_articles'])->toBe(2)
        ->and(count($result['configured']))->toBe(1)
        ->and(count($result['pending']))->toBe(1)
        ->and(count($result['safe_candidates']))->toBe(0)
        ->and(count($result['ambiguous']))->toBe(1)
        ->and(count($result['conflicts']))->toBe(0)
        ->and($exitCode)->toBe(0)
        ->and($output)->toContain('Modo DRY-RUN')
        ->and($output)->toContain('Integridad verificada')
        ->and(Article::query()->pluck('sunat_existence_type_item_id', 'id')->all())->toBe($before)
        ->and($configured->fresh()->sunat_existence_type_item_id)->not->toBeNull()
        ->and($pending->fresh()->sunat_existence_type_item_id)->toBeNull();
});
