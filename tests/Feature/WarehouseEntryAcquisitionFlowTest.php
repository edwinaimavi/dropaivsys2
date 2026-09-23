<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Services\ArticleInventoryPolicy;
use App\Services\WarehouseEntryAcquisitionCostService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function acquisitionFlowFixture(): array
{
    $catalogId = DB::table('sunat_catalogs')->insertGetId([
        'code' => '14', 'name' => 'MÉTODO DE VALORIZACIÓN', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $methodId = DB::table('sunat_catalog_items')->insertGetId([
        'sunat_catalog_id' => $catalogId, 'catalog_code' => '14', 'item_code' => '1',
        'description' => 'PROMEDIO PONDERADO', 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = User::factory()->create();
    $company = Company::create([
        'business_name' => 'EMPRESA ADQUISICIÓN', 'ruc' => '20131313131',
        'inventory_valuation_method_item_id' => $methodId, 'status' => true,
    ]);
    $user->companies()->attach($company->id);
    $warehouse = Warehouse::create(['code' => 'ALM-ADQ', 'name' => 'ALMACÉN ADQUISICIÓN', 'status' => 'ACTIVE']);
    $company->warehouses()->attach($warehouse->id, ['sunat_establishment_code' => '0001', 'is_active' => true]);
    $unit = Unit::create([
        'abbreviation' => 'UND-A', 'description' => 'UNIDAD ADQUISICIÓN',
        'sunat_unit_item_id' => testSunatUnitItemId(), 'status' => 'ACTIVE',
    ]);
    $category = Category::create([
        'code' => 'CAT-ADQ', 'description' => 'CATEGORÍA ADQUISICIÓN',
        'type' => 'PRODUCTO COMERCIAL', 'status' => 'ACTIVE',
    ]);
    $article = Article::create([
        'code' => 'ART-ADQ', 'category_id' => $category->id, 'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO ADQUISICIÓN', 'billing_name' => 'ARTÍCULO ADQUISICIÓN',
        'item_kind' => Article::KIND_PRODUCT, 'is_inventory_item' => true,
        'has_batch' => false, 'has_expiration' => false,
        ...testSunatInventoryArticleFields('ART-ADQ'), 'status' => 'ACTIVE',
    ]);
    $supplier = Supplier::create([
        'ruc' => '20613131311', 'business_name' => 'PROVEEDOR ADQUISICIÓN',
        'supplier_type' => 'BIENES', 'payment_condition' => 'CREDITO', 'status' => 'ACTIVE',
    ]);
    $otherSupplier = Supplier::create([
        'ruc' => '20613131312', 'business_name' => 'OTRO PROVEEDOR ADQUISICIÓN',
        'supplier_type' => 'BIENES', 'payment_condition' => 'CREDITO', 'status' => 'ACTIVE',
    ]);
    $pen = Currency::create(['code' => 'PEN', 'description' => 'Soles', 'symbol' => 'S/', 'status' => 'ACTIVE']);
    $usd = Currency::create(['code' => 'USD', 'description' => 'Dólares', 'symbol' => '$', 'status' => 'ACTIVE']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['admin.warehouse-entries.store', 'admin.warehouse-entries.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo(['admin.warehouse-entries.store', 'admin.warehouse-entries.update']);

    return compact('user', 'company', 'warehouse', 'unit', 'article', 'supplier', 'otherSupplier', 'pen', 'usd');
}

function acquisitionEntryPayload(array $fixture, array $overrides = []): array
{
    return array_replace_recursive([
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $fixture['warehouse']->id,
        'company_id' => $fixture['company']->id,
        'supplier_id' => $fixture['supplier']->id,
        'currency_id' => $fixture['pen']->id,
        'exchange_rate' => 1,
        'document_type' => 'FACTURA',
        'document_series' => ' f001 ',
        'document_number' => ' 000101 ',
        'document_date' => '2026-09-20',
        'movement_date' => '2026-09-22 14:35:00',
        'payment_method' => 'transferencia',
        'payment_condition' => 'credito',
        'credit_days' => 15,
        'generate_account_payable' => 1,
        'affect_igv' => 1,
        'items' => [[
            'article_id' => $fixture['article']->id,
            'billing_name_snapshot' => $fixture['article']->billing_name,
            'unit_id' => $fixture['unit']->id,
            'quantity' => 1,
            'unit_price' => 118,
            'tax_affectation_code' => '10',
            'tax_rate' => 18,
            'discount_amount' => 0,
            'is_free' => false,
            'igv_recoverable' => true,
        ]],
    ], $overrides);
}

it('persiste documento SUNAT fechas separadas y controla duplicidad activa por proveedor', function () {
    Storage::fake('public');
    $fixture = acquisitionFlowFixture();
    $payload = acquisitionEntryPayload($fixture);

    $response = $this->actingAs($fixture['user'])
        ->postJson(route('admin.warehouse-entries.store'), $payload)
        ->assertCreated();
    $entry = WarehouseEntry::query()->findOrFail($response->json('data.id'));

    expect($entry->sunat_document_type_code)->toBe('01')
        ->and($entry->document_series)->toBe('F001')
        ->and($entry->document_number)->toBe('000101')
        ->and($entry->document_date->toDateString())->toBe('2026-09-20')
        ->and($entry->movement_date->format('Y-m-d H:i:s'))->toBe('2026-09-22 14:35:00')
        ->and($entry->exchange_rate)->toBe('1.000000');

    $this->postJson(route('admin.warehouse-entries.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('document_number');

    $otherSupplierPayload = $payload;
    $otherSupplierPayload['supplier_id'] = $fixture['otherSupplier']->id;
    $this->postJson(route('admin.warehouse-entries.store'), $otherSupplierPayload)->assertCreated();

    $payload['items'][0]['id'] = $entry->items()->firstOrFail()->id;
    $this->putJson(route('admin.warehouse-entries.update', $entry), $payload)->assertOk();
});

it('exige documento completo movimiento y tipo de cambio extranjero', function () {
    $fixture = acquisitionFlowFixture();
    $payload = acquisitionEntryPayload($fixture, [
        'currency_id' => $fixture['usd']->id,
        'exchange_rate' => null,
        'document_series' => null,
        'document_number' => null,
        'document_date' => null,
        'movement_date' => null,
    ]);

    $this->actingAs($fixture['user'])->postJson(route('admin.warehouse-entries.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'exchange_rate', 'document_series', 'document_number', 'document_date', 'movement_date',
        ]);
});

it('convierte el costo sin IGV recuperable y usa movement date en Kardex', function () {
    Storage::fake('public');
    $fixture = acquisitionFlowFixture();
    $payload = acquisitionEntryPayload($fixture, [
        'currency_id' => $fixture['usd']->id,
        'exchange_rate' => 3.5,
        'document_number' => 'USD-001',
    ]);

    $response = $this->actingAs($fixture['user'])
        ->postJson(route('admin.warehouse-entries.store'), $payload)
        ->assertCreated();
    $entry = WarehouseEntry::query()->findOrFail($response->json('data.id'));
    $item = $entry->items()->firstOrFail();
    $movement = WarehouseKardexMovement::query()
        ->where('source_type', WarehouseEntry::class)
        ->where('source_id', $entry->id)
        ->where('operation_type', 'warehouse_entry')
        ->firstOrFail();

    expect($item->taxable_base)->toBe('100.00')
        ->and($item->tax_amount)->toBe('18.00')
        ->and($item->acquisition_cost_base)->toBe('350.00')
        ->and($item->acquisition_unit_cost_base)->toBe('350.000000')
        ->and((float) $movement->unit_cost)->toBe(350.0)
        ->and((float) $movement->total_cost_in)->toBe(350.0)
        ->and($movement->movement_date->format('Y-m-d H:i:s'))->toBe('2026-09-22 14:35:00')
        ->and((float) $movement->exchange_rate)->toBe(3.5);
});

it('calcula costo recuperable no recuperable exonerado inafecto descuentos y gratuidad', function () {
    $fixture = acquisitionFlowFixture();
    $entry = new WarehouseEntry(['currency_id' => $fixture['pen']->id, 'exchange_rate' => 1]);
    $entry->setRelation('currency', $fixture['pen']);
    $service = app(WarehouseEntryAcquisitionCostService::class);

    $recoverable = $service->itemCosts($entry, new WarehouseEntryItem([
        'quantity' => 2, 'unit_price' => 59, 'tax_affectation_code' => '10',
        'taxable_base' => 90, 'tax_amount' => 16.20, 'line_total' => 106.20,
        'discount_amount' => 11.80, 'igv_recoverable' => true, 'is_free' => false,
    ]));
    $nonRecoverable = $service->itemCosts($entry, new WarehouseEntryItem([
        'quantity' => 2, 'unit_price' => 59, 'tax_affectation_code' => '10',
        'taxable_base' => 90, 'tax_amount' => 16.20, 'line_total' => 106.20,
        'igv_recoverable' => false, 'is_free' => false,
    ]));
    $exempt = $service->itemCosts($entry, new WarehouseEntryItem([
        'quantity' => 2, 'unit_price' => 50, 'tax_affectation_code' => '20',
        'taxable_base' => 90, 'tax_amount' => 0, 'line_total' => 90,
        'is_free' => false,
    ]));
    $unaffected = $service->itemCosts($entry, new WarehouseEntryItem([
        'quantity' => 2, 'unit_price' => 40, 'tax_affectation_code' => '30',
        'taxable_base' => 75, 'tax_amount' => 0, 'line_total' => 75,
        'is_free' => false,
    ]));
    $free = $service->itemCosts($entry, new WarehouseEntryItem([
        'quantity' => 1, 'unit_price' => 118, 'tax_affectation_code' => '10',
        'taxable_base' => 100, 'tax_amount' => 0, 'line_total' => 0,
        'igv_recoverable' => true, 'is_free' => true,
    ]));

    expect($recoverable)->toBe(['total' => 90.0, 'unit' => 45.0])
        ->and($nonRecoverable)->toBe(['total' => 106.2, 'unit' => 53.1])
        ->and($exempt)->toBe(['total' => 90.0, 'unit' => 45.0])
        ->and($unaffected)->toBe(['total' => 75.0, 'unit' => 37.5])
        ->and($free)->toBe(['total' => 0.0, 'unit' => 0.0]);
});

it('capitaliza gastos netos de IGV recuperable y conserva el IGV no recuperable', function () {
    $fixture = acquisitionFlowFixture();
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-GASTO-4', 'entry_mode' => 'supplier_invoice',
        'company_id' => $fixture['company']->id, 'warehouse_id' => $fixture['warehouse']->id,
        'supplier_id' => $fixture['supplier']->id, 'currency_id' => $fixture['usd']->id,
        'exchange_rate' => 3.5, 'document_type' => 'FACTURA', 'status' => 'registered',
    ]);
    $recoverable = new WarehouseEntryExpense([
        'currency_id' => $fixture['usd']->id, 'exchange_rate' => 3.5,
        'amount' => 118, 'total_amount' => 118, 'taxable_amount' => 100,
        'igv_amount' => 18, 'affects_igv' => true, 'igv_recoverable' => true,
        'affects_inventory_cost' => true,
    ]);
    $recoverable->setRelation('currency', $fixture['usd']);
    $nonRecoverable = clone $recoverable;
    $nonRecoverable->igv_recoverable = false;

    $service = app(WarehouseEntryAcquisitionCostService::class);
    expect($service->capitalizableExpenseAmount($entry, $recoverable))->toBe(350.0)
        ->and($service->capitalizableExpenseAmount($entry, $nonRecoverable))->toBe(413.0);
});

it('mantiene servicios fuera del movimiento físico', function () {
    $serviceArticle = new Article(['item_kind' => Article::KIND_SERVICE, 'is_inventory_item' => false]);

    expect(fn () => app(ArticleInventoryPolicy::class)->assertCanParticipateInInventory($serviceArticle, 'items'))
        ->toThrow(ValidationException::class);
});
