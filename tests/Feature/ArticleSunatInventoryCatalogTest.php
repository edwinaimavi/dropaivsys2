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
use App\Services\ArticleSunatInventoryCatalogAuditService;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware();

    $this->category13 = Category::create([
        'code' => 'CAT-S13', 'description' => 'CATEGORÍA SUNAT 13', 'type' => 'ARTICLE', 'status' => 'ACTIVE',
    ]);
    $this->unit13 = Unit::create([
        'abbreviation' => 'UND', 'description' => 'UNIDAD', 'decimal_quantity' => false, 'status' => 'ACTIVE',
    ]);
    $this->subcategory13 = Subcategory::create([
        'category_id' => $this->category13->id, 'description' => 'SUBCATEGORÍA SUNAT 13', 'status' => 'ACTIVE',
    ]);
    $this->presentation13 = Presentation::create([
        'description' => 'PRESENTACIÓN SUNAT 13', 'quantity' => 1,
        'unit_id' => $this->unit13->id, 'status' => 'ACTIVE',
    ]);

    $catalog05 = SunatCatalog::create(['code' => '05', 'name' => 'TIPO DE EXISTENCIA', 'is_active' => true]);
    $this->type05 = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog05->id, 'catalog_code' => '05', 'item_code' => '01',
        'description' => 'MERCADERÍAS', 'status' => 'ACTIVE',
    ]);
    $catalog06 = SunatCatalog::create(['code' => '06', 'name' => 'UNIDAD DE MEDIDA', 'is_active' => true]);
    $this->unit06 = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog06->id, 'catalog_code' => '06', 'item_code' => 'NIU',
        'description' => 'UNIDAD (BIENES)', 'status' => 'ACTIVE',
    ]);
    $this->unit13->update(['sunat_unit_item_id' => $this->unit06->id]);

    $this->catalog13 = SunatCatalog::create(['code' => '13', 'name' => 'CATÁLOGO DE EXISTENCIAS', 'is_active' => true]);
    $this->un13 = sunat13Item($this->catalog13, '1', 'NACIONES UNIDAS');
    $this->gs113 = sunat13Item($this->catalog13, '3', 'GS1 (EAN-UCC)');
    $this->other13 = sunat13Item($this->catalog13, '9', 'OTROS');
});

function sunat13Item(SunatCatalog $catalog, string $code, string $description, string $status = 'ACTIVE'): SunatCatalogItem
{
    return SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog->id,
        'catalog_code' => '13',
        'item_code' => $code,
        'description' => $description,
        'status' => $status,
    ]);
}

function sunat13Payload(string $code, array $overrides = []): array
{
    return array_merge([
        'code' => $code,
        'code_mode' => 'manual',
        'code_type' => 'SIGA/SISMED',
        'category_id' => test()->category13->id,
        'subcategory_id' => test()->subcategory13->id,
        'presentation_id' => test()->presentation13->id,
        'unit_id' => test()->unit13->id,
        'legal_name' => $code,
        'commercial_name' => $code,
        'billing_name' => $code,
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => 1,
        'sunat_existence_type_item_id' => test()->type05->id,
        'sunat_inventory_catalog_item_id' => test()->other13->id,
        'sunat_inventory_catalog_code' => $code,
        'sunat_standard_catalog_item_id' => null,
        'sunat_standard_code' => null,
        'minimum_stock' => 0,
        'is_taxable' => 1,
        'sales_tax_affectation_code' => '10',
        'has_batch' => 0,
        'has_expiration' => 0,
        'status' => 'ACTIVE',
        'documents_data' => '[]',
    ], $overrides);
}

function sunat13Article(string $code, array $overrides = []): Article
{
    return Article::create(array_merge([
        'code' => $code,
        'category_id' => test()->category13->id,
        'unit_id' => test()->unit13->id,
        'legal_name' => $code,
        'billing_name' => $code,
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        'sunat_existence_type_item_id' => test()->type05->id,
        'sunat_inventory_catalog_item_id' => test()->other13->id,
        'sunat_inventory_catalog_code' => $code,
        'status' => 'ACTIVE',
    ], $overrides));
}

function sunat13History(Article $article, string $suffix): array
{
    $company = Company::create([
        'business_name' => 'EMPRESA '.$suffix,
        'ruc' => '23'.str_pad((string) $article->id, 9, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
    $warehouse = Warehouse::create(['code' => 'W-'.$suffix, 'name' => 'ALMACÉN '.$suffix, 'status' => 'ACTIVE']);
    $stockId = DB::table('warehouse_stocks')->insertGetId([
        'stock_key' => 'S13-'.$suffix,
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'article_id' => $article->id,
        'unit_id' => test()->unit13->id,
        'current_quantity' => 5,
        'reserved_quantity' => 0,
        'average_unit_cost' => 7.5,
        'total_cost' => 37.5,
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$company, $warehouse, $stockId];
}

function sunat13Entry(Article $article, string $suffix): WarehouseEntry
{
    $company = Company::create([
        'business_name' => 'EMPRESA INGRESO '.$suffix,
        'ruc' => '24'.str_pad((string) $article->id, 9, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
    $warehouse = Warehouse::create(['code' => 'WE-'.$suffix, 'name' => 'ALMACÉN '.$suffix, 'status' => 'ACTIVE']);
    DB::table('company_warehouses')->insert([
        'company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '25'.str_pad((string) $article->id, 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR '.$suffix, 'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO', 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'X'.$article->id, 'description' => 'MONEDA '.$suffix, 'symbol' => 'S/',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-S13-'.$suffix,
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
        'unit_id' => test()->unit13->id,
        'quantity' => 2,
        'unit_price' => 10,
        'subtotal' => 20,
        'tax_amount' => 3.6,
        'line_total' => 23.6,
        'status' => 'active',
    ]);

    return $entry;
}

it('agrega cuatro campos y guarda Tabla 13 = 9 con una copia explícita del código interno', function () {
    expect(Schema::hasColumns('articles', [
        'sunat_inventory_catalog_item_id', 'sunat_inventory_catalog_code',
        'sunat_standard_catalog_item_id', 'sunat_standard_code',
    ]))->toBeTrue();

    $this->postJson(route('admin.articles.store'), sunat13Payload('ART-S13-001'))->assertCreated();
    $article = Article::where('code', 'ART-S13-001')->firstOrFail();
    expect($article->sunatInventoryCatalogItem->item_code)->toBe('9')
        ->and($article->sunat_inventory_catalog_code)->toBe('ART-S13-001')
        ->and($article->sunat_standard_catalog_item_id)->toBeNull();

    $article->update(['code' => 'ART-S13-NUEVO']);
    expect($article->fresh()->sunat_inventory_catalog_code)->toBe('ART-S13-001');
});

it('rechaza inventariables sin catálogo principal o sin código principal', function (array $overrides, string $field) {
    $code = $field === 'sunat_inventory_catalog_item_id' ? 'S13-SIN-CAT' : 'S13-SIN-COD';
    $this->postJson(route('admin.articles.store'), sunat13Payload($code, $overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'sin catálogo' => [['sunat_inventory_catalog_item_id' => null], 'sunat_inventory_catalog_item_id'],
    'sin código' => [['sunat_inventory_catalog_code' => '  '], 'sunat_inventory_catalog_code'],
]);

it('rechaza items ajenos, inactivos o pertenecientes a un catálogo 13 inactivo', function (Closure $item, string $code) {
    $this->postJson(route('admin.articles.store'), sunat13Payload($code, [
        'sunat_inventory_catalog_item_id' => $item()->id,
    ]))->assertUnprocessable()->assertJsonValidationErrors('sunat_inventory_catalog_item_id');
})->with([
    'Tabla 05' => [fn () => test()->type05, 'S13-WRONG-05'],
    'item inactivo' => [fn () => sunat13Item(test()->catalog13, '7', 'INACTIVO', 'INACTIVE'), 'S13-INACTIVE'],
    'catálogo inactivo' => [function () {
        test()->catalog13->update(['is_active' => false]);
        return test()->un13;
    }, 'S13-CAT-OFF'],
]);

it('no exige identificación principal a servicios ni productos no inventariables', function (string $kind, int $inventory) {
    $payload = sunat13Payload('S13-NO-'.$kind.'-'.$inventory, [
        'item_kind' => $kind,
        'is_inventory_item' => $inventory,
        'sunat_existence_type_item_id' => null,
        'sunat_inventory_catalog_item_id' => null,
        'sunat_inventory_catalog_code' => null,
    ]);
    $this->postJson(route('admin.articles.store'), $payload)->assertCreated();
})->with([
    'servicio' => [Article::KIND_SERVICE, 0],
    'producto no inventariable' => [Article::KIND_PRODUCT, 0],
]);

it('permite primera asignación legacy con historial y bloquea después cambiar catálogo o código', function () {
    $article = sunat13Article('S13-LEGACY', [
        'sunat_inventory_catalog_item_id' => null,
        'sunat_inventory_catalog_code' => null,
    ]);
    sunat13History($article, 'LEGACY');

    $this->putJson(route('admin.articles.update', $article), sunat13Payload($article->code))->assertOk();
    expect($article->fresh()->sunat_inventory_catalog_code)->toBe($article->code);

    foreach ([
        ['sunat_inventory_catalog_item_id' => $this->un13->id],
        ['sunat_inventory_catalog_code' => 'OTRO-CODIGO'],
    ] as $change) {
        $this->putJson(route('admin.articles.update', $article), sunat13Payload($article->code, $change))
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.sunat_inventory_catalog_item_id.0',
                'La identificación SUNAT de la existencia no puede modificarse porque el artículo ya tiene historial de inventario.'
            );
    }
});

it('permite cambiar catálogo y código principal cuando no existe historial físico', function () {
    $article = sunat13Article('S13-SIN-HIST');
    $this->putJson(route('admin.articles.update', $article), sunat13Payload($article->code, [
        'sunat_inventory_catalog_item_id' => $this->un13->id,
        'sunat_inventory_catalog_code' => '51100000',
    ]))->assertOk();

    expect((int) $article->fresh()->sunat_inventory_catalog_item_id)->toBe($this->un13->id)
        ->and($article->fresh()->sunat_inventory_catalog_code)->toBe('51100000');
});

it('valida el código internacional como pareja opcional y solo permite Naciones Unidas o GS1', function (mixed $overrides, ?string $error, string $code) {
    $overrides = is_callable($overrides) ? $overrides() : $overrides;
    $payload = sunat13Payload($code, $overrides);
    $response = $this->postJson(route('admin.articles.store'), $payload);
    if ($error) {
        $response->assertUnprocessable()->assertJsonValidationErrors($error);
    } else {
        $response->assertCreated();
    }
})->with([
    'Naciones Unidas válido' => [fn () => ['sunat_standard_catalog_item_id' => test()->un13->id, 'sunat_standard_code' => '51100000'], null, 'S13-STD-001'],
    'GS1 válido' => [fn () => ['sunat_standard_catalog_item_id' => test()->gs113->id, 'sunat_standard_code' => '7751234567890'], null, 'S13-STD-002'],
    '9 rechazado' => [fn () => ['sunat_standard_catalog_item_id' => test()->other13->id, 'sunat_standard_code' => 'PROPIO'], 'sunat_standard_catalog_item_id', 'S13-STD-003'],
    'catálogo sin código' => [fn () => ['sunat_standard_catalog_item_id' => test()->un13->id, 'sunat_standard_code' => null], 'sunat_standard_code', 'S13-STD-004'],
    'código sin catálogo' => [fn () => ['sunat_standard_catalog_item_id' => null, 'sunat_standard_code' => '51100000'], 'sunat_standard_catalog_item_id', 'S13-STD-005'],
]);

it('bloquea Warehouse Entry sin Tabla 13 antes de tocar stock o Kardex', function () {
    $article = sunat13Article('S13-ENTRY-BLOCK', [
        'sunat_inventory_catalog_item_id' => null,
        'sunat_inventory_catalog_code' => null,
    ]);
    $entry = sunat13Entry($article, 'BLOCK');
    $entryItemBefore = DB::table('warehouse_entry_items')->where('warehouse_entry_id', $entry->id)->first();

    expect(fn () => app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($entry))
        ->toThrow(ValidationException::class, 'El artículo no tiene configurado su catálogo y código de existencia SUNAT.');
    expect(DB::table('warehouse_stocks')->count())->toBe(0)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe(0)
        ->and(DB::table('warehouse_entry_items')->where('warehouse_entry_id', $entry->id)->first())->toEqual($entryItemBefore);
});

it('mantiene operativo Warehouse Entry configurado sin alterar cantidades, costos, IGV ni company_id', function () {
    $article = sunat13Article('S13-ENTRY-OK');
    $entry = sunat13Entry($article, 'OK');
    $taxBefore = DB::table('warehouse_entry_items')->where('warehouse_entry_id', $entry->id)->value('tax_amount');

    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($entry);

    $stock = DB::table('warehouse_stocks')->first();
    $movement = DB::table('warehouse_kardex_movements')->first();
    expect((float) $stock->current_quantity)->toBe(2.0)
        ->and((float) $stock->average_unit_cost)->toBe(10.0)
        ->and((float) $stock->total_cost)->toBe(20.0)
        ->and((int) $stock->company_id)->toBe((int) $entry->company_id)
        ->and((float) $movement->quantity_in)->toBe(2.0)
        ->and((float) $movement->unit_cost)->toBe(10.0)
        ->and((float) DB::table('warehouse_entry_items')->where('warehouse_entry_id', $entry->id)->value('tax_amount'))->toBe((float) $taxBefore);
});

it('audita en dry-run candidatos seguros, detecciones y conflictos sin escribir', function () {
    $safe = sunat13Article('S13-AUD-SAFE', [
        'sunat_inventory_catalog_item_id' => null, 'sunat_inventory_catalog_code' => null,
    ]);
    sunat13Article('CODIGO CON ESPACIO', [
        'sunat_inventory_catalog_item_id' => null, 'sunat_inventory_catalog_code' => null,
    ]);
    sunat13Article('S13-AUD-UNSPSC', [
        'sunat_inventory_catalog_item_id' => null, 'sunat_inventory_catalog_code' => null,
        'sunat_standard_catalog_item_id' => $this->un13->id, 'sunat_standard_code' => '51100000',
    ]);
    sunat13Article('S13-AUD-CONFLICT', ['sunat_inventory_catalog_code' => null]);
    $before = DB::table('articles')->orderBy('id')->get()->all();

    $result = app(ArticleSunatInventoryCatalogAuditService::class)->analyze();
    $exit = Artisan::call('articles:audit-sunat-inventory-catalog');

    expect($result['total_articles'])->toBe(4)
        ->and($result['inventory_articles'])->toBe(4)
        ->and(count($result['configured']))->toBe(0)
        ->and(count($result['pending']))->toBe(3)
        ->and(count($result['safe_candidates']))->toBe(1)
        ->and($result['safe_candidates'][0]['id'])->toBe($safe->id)
        ->and(count($result['unspsc_detected']))->toBe(1)
        ->and(count($result['gtin_detected']))->toBe(0)
        ->and(count($result['ambiguous']))->toBe(2)
        ->and(count($result['conflicts']))->toBe(1)
        ->and($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Modo DRY-RUN')
        ->and(DB::table('articles')->orderBy('id')->get()->all())->toEqual($before);
});

it('--apply de testing solo configura candidatos 9 seguros y la segunda ejecución es idempotente', function () {
    $safe = sunat13Article('S13-APPLY-SAFE', [
        'sunat_inventory_catalog_item_id' => null, 'sunat_inventory_catalog_code' => null,
    ]);
    $ambiguous = sunat13Article('CODIGO AMBIGUO', [
        'sunat_inventory_catalog_item_id' => null, 'sunat_inventory_catalog_code' => null,
    ]);
    $configured = sunat13Article('S13-CONFIGURADO');
    [$company, , $stockId] = sunat13History($safe, 'APPLY');
    $stockBefore = DB::table('warehouse_stocks')->where('id', $stockId)->first();
    $protectedBefore = [
        'type05' => $safe->sunat_existence_type_item_id,
        'unit06' => $this->unit13->sunat_unit_item_id,
        'company_id' => $company->id,
    ];

    expect(Artisan::call('articles:audit-sunat-inventory-catalog', ['--apply' => true]))->toBe(0);
    $afterFirst = $safe->fresh();
    expect((int) $afterFirst->sunat_inventory_catalog_item_id)->toBe($this->other13->id)
        ->and($afterFirst->sunat_inventory_catalog_code)->toBe('S13-APPLY-SAFE')
        ->and($ambiguous->fresh()->sunat_inventory_catalog_item_id)->toBeNull()
        ->and((int) $configured->fresh()->sunat_inventory_catalog_item_id)->toBe($this->other13->id)
        ->and(DB::table('warehouse_stocks')->where('id', $stockId)->first())->toEqual($stockBefore)
        ->and($afterFirst->sunat_existence_type_item_id)->toBe($protectedBefore['type05'])
        ->and($this->unit13->fresh()->sunat_unit_item_id)->toBe($protectedBefore['unit06'])
        ->and((int) DB::table('warehouse_stocks')->where('id', $stockId)->value('company_id'))->toBe($protectedBefore['company_id']);

    $snapshot = DB::table('articles')->orderBy('id')->get()->all();
    expect(Artisan::call('articles:audit-sunat-inventory-catalog', ['--apply' => true]))->toBe(0)
        ->and(DB::table('articles')->orderBy('id')->get()->all())->toEqual($snapshot);
});

it('expone catálogos 13 separados y presenta los controles principal e internacional', function () {
    $this->getJson(route('admin.articles.sunat-inventory-catalogs'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonCount(2, 'standard')
        ->assertJsonPath('data.2.code', '9');

    expect(file_get_contents(resource_path('views/admin/articles/partials/modal.blade.php')))
        ->toContain('Identificación SUNAT de existencia')
        ->toContain('id="useInternalArticleCode"')
        ->toContain('Usar código')
        ->toContain('Código internacional')
        ->and(file_get_contents(resource_path('js/pages/article.js')))
        ->toContain("$('#sunat_inventory_catalog_code').val($.trim($('#code').val()))");
});

it('mantiene la identificación Tabla 13 en los dos flujos quick-create de Article', function () {
    $payload = sunat13Payload('S13-QUICK-001');
    unset($payload['documents_data']);

    $this->postJson(route('admin.articles.quick-store'), $payload)->assertCreated();
    $this->postJson(route('admin.quotes.articles.quick-store'), array_merge($payload, [
        'code' => 'S13-QUICK-002',
        'sunat_inventory_catalog_code' => 'S13-QUICK-002',
        'legal_name' => 'S13-QUICK-002',
        'commercial_name' => 'S13-QUICK-002',
        'billing_name' => 'S13-QUICK-002',
    ]))->assertCreated();

    expect(Article::where('code', 'S13-QUICK-001')->value('sunat_inventory_catalog_code'))->toBe('S13-QUICK-001')
        ->and(Article::where('code', 'S13-QUICK-002')->value('sunat_inventory_catalog_code'))->toBe('S13-QUICK-002');
});
