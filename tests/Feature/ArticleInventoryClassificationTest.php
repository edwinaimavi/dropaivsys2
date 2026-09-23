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
use App\Services\ArticleInventoryClassificationAuditService;
use App\Services\ArticleInventoryPolicy;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware();
    $this->category = Category::create([
        'code' => 'CAT-INV', 'description' => 'CLASIFICACIÓN INVENTARIO', 'type' => 'ARTICLE', 'status' => 'ACTIVE',
    ]);
    $this->unit = Unit::create([
        'abbreviation' => 'UND', 'description' => 'UNIDAD', 'decimal_quantity' => false, 'status' => 'ACTIVE',
    ]);
    $this->subcategory = Subcategory::create([
        'category_id' => $this->category->id, 'description' => 'SUBCATEGORÍA INVENTARIO', 'status' => 'ACTIVE',
    ]);
    $this->presentation = Presentation::create([
        'description' => 'PRESENTACIÓN INVENTARIO', 'quantity' => 1,
        'unit_id' => $this->unit->id, 'status' => 'ACTIVE',
    ]);
    $catalog05 = SunatCatalog::create([
        'code' => '05', 'name' => 'TIPO DE EXISTENCIA', 'is_active' => true,
    ]);
    $this->sunatExistenceType = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog05->id,
        'catalog_code' => '05',
        'item_code' => '01',
        'description' => 'MERCADERÍAS',
        'status' => 'ACTIVE',
    ]);
    $catalog13 = SunatCatalog::create([
        'code' => '13', 'name' => 'CATÁLOGO DE EXISTENCIAS', 'is_active' => true,
    ]);
    $this->sunatInventoryCatalog = SunatCatalogItem::create([
        'sunat_catalog_id' => $catalog13->id, 'catalog_code' => '13', 'item_code' => '9',
        'description' => 'OTROS', 'status' => 'ACTIVE',
    ]);
});

function inventoryClassificationArticle(array $classification, string $code): Article
{
    return Article::create(array_merge([
        'code' => $code,
        'category_id' => test()->category->id,
        'unit_id' => test()->unit->id,
        'legal_name' => $code,
        'billing_name' => $code,
        'status' => 'ACTIVE',
    ], $classification));
}

function inventoryClassificationPayload(string $code, array $classification): array
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
        'minimum_stock' => 0,
        'is_taxable' => 1,
        'sales_tax_affectation_code' => '10',
        'has_batch' => 0,
        'has_expiration' => 0,
        'status' => 'ACTIVE',
        'documents_data' => '[]',
    ], $classification);
}

it('agrega ambos campos de clasificación como columnas anulables', function () {
    expect(Schema::hasColumns('articles', ['item_kind', 'is_inventory_item']))->toBeTrue();
    $legacy = inventoryClassificationArticle([], 'LEGACY-NULL');
    expect($legacy->item_kind)->toBeNull()->and($legacy->is_inventory_item)->toBeNull();
});

it('crea producto inventariable, producto no inventariable y servicio no inventariable', function ($kind, $inventory) {
    $code = 'COMBO-'.strtoupper($kind).'-'.(int) $inventory;
    $this->postJson(route('admin.articles.store'), inventoryClassificationPayload($code, [
        'item_kind' => $kind,
        'is_inventory_item' => $inventory,
        'sunat_existence_type_item_id' => $kind === Article::KIND_PRODUCT && $inventory
            ? $this->sunatExistenceType->id
            : null,
        'sunat_inventory_catalog_item_id' => $kind === Article::KIND_PRODUCT && $inventory
            ? $this->sunatInventoryCatalog->id
            : null,
        'sunat_inventory_catalog_code' => $kind === Article::KIND_PRODUCT && $inventory ? $code : null,
    ]))->assertCreated();

    $this->assertDatabaseHas('articles', [
        'code' => $code,
        'item_kind' => $kind,
        'is_inventory_item' => $inventory,
    ]);
})->with([
    'producto inventariable' => [Article::KIND_PRODUCT, 1],
    'producto no inventariable' => [Article::KIND_PRODUCT, 0],
    'servicio' => [Article::KIND_SERVICE, 0],
]);

it('rechaza clasificación ausente y servicio inventariable', function () {
    $this->postJson(route('admin.articles.store'), inventoryClassificationPayload('SIN-CLASE', []))
        ->assertUnprocessable()->assertJsonValidationErrors('item_kind');

    $this->postJson(route('admin.articles.store'), inventoryClassificationPayload('PROD-SIN-INV', [
        'item_kind' => Article::KIND_PRODUCT,
    ]))->assertUnprocessable()->assertJsonValidationErrors('is_inventory_item');

    $this->postJson(route('admin.articles.store'), inventoryClassificationPayload('SERV-INV', [
        'item_kind' => Article::KIND_SERVICE,
        'is_inventory_item' => 1,
    ]))->assertUnprocessable()->assertJsonValidationErrors('is_inventory_item');
});

it('normaliza un servicio sin bandera explícita como no inventariable', function () {
    $this->postJson(route('admin.articles.store'), inventoryClassificationPayload('SERV-FORZADO', [
        'item_kind' => Article::KIND_SERVICE,
    ]))->assertCreated();

    $this->assertDatabaseHas('articles', [
        'code' => 'SERV-FORZADO', 'item_kind' => Article::KIND_SERVICE, 'is_inventory_item' => 0,
    ]);
});

it('exige clasificación en alta rápida y guarda un servicio comercial sin inventario', function () {
    $payload = [
        'code' => 'QUICK-SERV', 'code_mode' => 'manual', 'legal_name' => 'SERVICIO RÁPIDO',
        'commercial_name' => 'SERVICIO RÁPIDO', 'billing_name' => 'SERVICIO RÁPIDO',
        'sales_tax_affectation_code' => '10',
    ];

    $this->postJson(route('admin.articles.quick-store'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('item_kind');

    $this->postJson(route('admin.articles.quick-store'), $payload + [
        'item_kind' => Article::KIND_SERVICE,
    ])->assertCreated()->assertJsonPath('data.item_kind', Article::KIND_SERVICE)
        ->assertJsonPath('data.is_inventory_item', false);
});

it('exige clasificación también en el alta rápida de cotización', function () {
    $payload = [
        'code' => 'QUOTE-QUICK', 'code_mode' => 'manual', 'legal_name' => 'PRODUCTO COTIZACIÓN',
        'commercial_name' => 'PRODUCTO COTIZACIÓN', 'billing_name' => 'PRODUCTO COTIZACIÓN',
        'sales_tax_affectation_code' => '10',
    ];

    $this->postJson(route('admin.quotes.articles.quick-store'), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('item_kind');

    $this->postJson(route('admin.quotes.articles.quick-store'), $payload + [
        'item_kind' => Article::KIND_PRODUCT, 'is_inventory_item' => 0,
    ])->assertCreated();

    $this->assertDatabaseHas('articles', [
        'code' => 'QUOTE-QUICK', 'item_kind' => Article::KIND_PRODUCT, 'is_inventory_item' => 0,
    ]);
});

it('permite inventario sólo al producto inventariable explícito', function () {
    $policy = app(ArticleInventoryPolicy::class);
    $inventory = inventoryClassificationArticle(['item_kind' => 'product', 'is_inventory_item' => true], 'INV-SI');
    $commercial = inventoryClassificationArticle(['item_kind' => 'product', 'is_inventory_item' => false], 'INV-NO');
    $service = inventoryClassificationArticle(['item_kind' => 'service', 'is_inventory_item' => false], 'SERVICIO');

    expect($policy->canParticipateInInventory($inventory))->toBeTrue()
        ->and($policy->canParticipateInInventory($commercial))->toBeFalse()
        ->and($policy->canParticipateInInventory($service))->toBeFalse();
});

it('mantiene legacy ambiguo fuera de inventario y admite legacy con evidencia física', function () {
    $policy = app(ArticleInventoryPolicy::class);
    $ambiguous = inventoryClassificationArticle([], 'LEGACY-AMB');
    $physical = inventoryClassificationArticle([], 'LEGACY-FIS');
    $company = Company::create(['business_name' => 'EMPRESA TEST', 'ruc' => '20111111111', 'status' => true]);
    $warehouse = Warehouse::create(['code' => 'ALM-INV', 'name' => 'ALMACÉN INVENTARIO', 'status' => 'ACTIVE']);
    DB::table('warehouse_stocks')->insert([
        'stock_key' => 'LEGACY-EVIDENCE',
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'article_id' => $physical->id,
        'current_quantity' => 0,
        'reserved_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($policy->canParticipateInInventory($ambiguous))->toBeFalse()
        ->and($policy->canParticipateInInventory($physical))->toBeTrue();
});

it('bloquea reclasificar cualquier artículo con evidencia física fuera de inventario', function () {
    $article = inventoryClassificationArticle(['item_kind' => 'product', 'is_inventory_item' => true], 'RECLASS');
    $company = Company::create(['business_name' => 'EMPRESA HISTORIAL', 'ruc' => '20222222222', 'status' => true]);
    $warehouse = Warehouse::create(['code' => 'ALM-HIS', 'name' => 'ALMACÉN HISTORIAL', 'status' => 'ACTIVE']);
    DB::table('warehouse_stocks')->insert([
        'stock_key' => 'RECLASS-EVIDENCE', 'company_id' => $company->id, 'warehouse_id' => $warehouse->id,
        'article_id' => $article->id, 'current_quantity' => 1, 'reserved_quantity' => 0,
        'average_unit_cost' => 10, 'total_cost' => 10, 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(fn () => app(ArticleInventoryPolicy::class)
        ->assertClassificationChangeAllowed($article, Article::KIND_SERVICE, false))
        ->toThrow(ValidationException::class);

    $this->putJson(route('admin.articles.update', $article), inventoryClassificationPayload('RECLASS', [
        'item_kind' => Article::KIND_SERVICE,
        'is_inventory_item' => 0,
    ]))->assertUnprocessable()->assertJsonValidationErrors('item_kind');

    $stock = DB::table('warehouse_stocks')->where('stock_key', 'RECLASS-EVIDENCE')->first();
    expect((int) $stock->company_id)->toBe($company->id)
        ->and((float) $stock->current_quantity)->toBe(1.0)
        ->and((float) $stock->average_unit_cost)->toBe(10.0)
        ->and((float) $stock->total_cost)->toBe(10.0)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe(0);
});

it('permite reclasificar un producto sin historial físico', function () {
    $article = inventoryClassificationArticle(['item_kind' => 'product', 'is_inventory_item' => true], 'RECLASS-SAFE');

    $this->putJson(route('admin.articles.update', $article), inventoryClassificationPayload('RECLASS-SAFE', [
        'item_kind' => Article::KIND_SERVICE,
        'is_inventory_item' => 0,
    ]))->assertOk();

    expect($article->fresh()->item_kind)->toBe(Article::KIND_SERVICE)
        ->and($article->fresh()->is_inventory_item)->toBeFalse();
});

it('el selector de inventario incluye explícitos y legacy con evidencia, no servicios ni ambiguos', function () {
    $eligible = inventoryClassificationArticle(['item_kind' => 'product', 'is_inventory_item' => true], 'SEL-SI');
    inventoryClassificationArticle(['item_kind' => 'product', 'is_inventory_item' => false], 'SEL-NO');
    inventoryClassificationArticle(['item_kind' => 'service', 'is_inventory_item' => false], 'SEL-SERV');
    $legacy = inventoryClassificationArticle([], 'SEL-LEG');
    $company = Company::create(['business_name' => 'EMPRESA SELECTOR', 'ruc' => '20333333333', 'status' => true]);
    $warehouse = Warehouse::create(['code' => 'ALM-SEL', 'name' => 'ALMACÉN SELECTOR', 'status' => 'ACTIVE']);
    DB::table('warehouse_stocks')->insert([
        'stock_key' => 'SELECTOR-EVIDENCE', 'company_id' => $company->id, 'warehouse_id' => $warehouse->id,
        'article_id' => $legacy->id, 'current_quantity' => 0, 'reserved_quantity' => 0,
        'average_unit_cost' => 0, 'total_cost' => 0, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $query = Article::query();
    app(ArticleInventoryPolicy::class)->scopeEligible($query);
    expect($query->pluck('id')->all())->toEqualCanonicalizing([$eligible->id, $legacy->id]);
});

it('el backend de ingreso rechaza servicio y producto no inventariable sin crear stock ni Kardex', function (string $kind) {
    $article = inventoryClassificationArticle(['item_kind' => $kind, 'is_inventory_item' => false], 'ENTRY-'.strtoupper($kind));
    $company = Company::create(['business_name' => 'EMPRESA INGRESO '.$kind, 'ruc' => $kind === 'service' ? '20555555555' : '20666666666', 'status' => true]);
    $warehouse = Warehouse::create(['code' => 'ALM-'.strtoupper(substr($kind, 0, 3)), 'name' => 'ALMACÉN '.$kind, 'status' => 'ACTIVE']);
    DB::table('company_warehouses')->insert([
        'company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => $kind === 'service' ? 'PEN' : 'USD', 'description' => 'MONEDA '.$kind,
        'symbol' => '$', 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => $kind === 'service' ? '20777777777' : '20888888888', 'business_name' => 'PROVEEDOR '.$kind,
        'supplier_type' => 'LOCAL', 'payment_condition' => 'CONTADO', 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-'.strtoupper($kind), 'company_id' => $company->id,
        'warehouse_id' => $warehouse->id, 'supplier_id' => $supplierId,
        'currency_id' => $currencyId, 'status' => 'registered',
    ]);
    $entry->items()->create([
        'article_id' => $article->id, 'article_code' => $article->code,
        'billing_name_snapshot' => $article->billing_name, 'unit_id' => $this->unit->id,
        'quantity' => 2, 'unit_price' => 10, 'subtotal' => 20, 'tax_amount' => 0,
        'line_total' => 20, 'status' => 'active',
    ]);

    expect(fn () => app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($entry))
        ->toThrow(ValidationException::class, 'no está clasificado como producto inventariable');
    expect(DB::table('warehouse_stocks')->count())->toBe(0)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe(0);
})->with([
    'servicio' => ['service'],
    'producto no inventariable' => ['product'],
]);

it('audita sin escribir y apply sólo completa candidatos con evidencia', function () {
    $candidate = inventoryClassificationArticle([], 'AUD-CAND');
    $ambiguous = inventoryClassificationArticle([], 'AUD-AMB');
    $already = inventoryClassificationArticle(['item_kind' => 'service', 'is_inventory_item' => false], 'AUD-OK');
    $company = Company::create(['business_name' => 'EMPRESA AUDITORÍA', 'ruc' => '20444444444', 'status' => true]);
    $warehouse = Warehouse::create(['code' => 'ALM-AUD', 'name' => 'ALMACÉN AUDITORÍA', 'status' => 'ACTIVE']);
    DB::table('warehouse_stocks')->insert([
        'stock_key' => 'AUDIT-EVIDENCE', 'company_id' => $company->id, 'warehouse_id' => $warehouse->id,
        'article_id' => $candidate->id, 'current_quantity' => 0, 'reserved_quantity' => 0,
        'average_unit_cost' => 0, 'total_cost' => 0, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $service = app(ArticleInventoryClassificationAuditService::class);
    $dryRun = $service->analyze();
    expect($dryRun['inventory_candidates'])->toContain($candidate->id)
        ->and($dryRun['ambiguous_without_evidence'])->toContain($ambiguous->id)
        ->and($dryRun['already_classified'])->toContain($already->id)
        ->and($candidate->fresh()->item_kind)->toBeNull();

    $applied = $service->analyze(true);
    expect($applied['applied'])->toBe(1)
        ->and($candidate->fresh()->isExplicitInventoryItem())->toBeTrue()
        ->and($ambiguous->fresh()->item_kind)->toBeNull();
});

it('el comando usa dry-run por defecto', function () {
    inventoryClassificationArticle([], 'CMD-AMB');
    expect(Artisan::call('articles:audit-inventory-classification'))->toBe(0)
        ->and(Artisan::output())->toContain('Modo DRY-RUN');
    $this->assertDatabaseHas('articles', ['code' => 'CMD-AMB', 'item_kind' => null]);
});
