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
use App\Models\WarehouseEntryItem;
use App\Services\ArticleInventoryPolicy;
use App\Services\WarehouseEntryTransactionalTaxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function centralTaxLine(string $code = '10', float $rate = 18, array $overrides = []): array
{
    return array_merge([
        'quantity' => 1,
        'unit_price' => 118,
        'discount_amount' => 0,
        'tax_affectation_code' => $code,
        'tax_rate' => $rate,
        'is_free' => false,
        '_transactional_tax_legacy' => false,
    ], $overrides);
}

function centralTaxDirectEntryFixture(): array
{
    $valuationCatalogId = DB::table('sunat_catalogs')->insertGetId([
        'code' => '14',
        'name' => 'MÉTODO DE VALORIZACIÓN',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $valuationMethodId = DB::table('sunat_catalog_items')->insertGetId([
        'sunat_catalog_id' => $valuationCatalogId,
        'catalog_code' => '14',
        'item_code' => '1',
        'description' => 'PROMEDIO PONDERADO',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::factory()->create();
    $company = Company::create([
        'business_name' => 'EMPRESA CÁLCULO CENTRAL',
        'ruc' => '20111111119',
        'inventory_valuation_method_item_id' => $valuationMethodId,
        'status' => true,
    ]);
    $user->companies()->attach($company->id);
    $warehouse = Warehouse::create([
        'code' => 'ALM-CENTRAL',
        'name' => 'ALMACÉN CÁLCULO CENTRAL',
        'status' => 'ACTIVE',
    ]);
    $company->warehouses()->attach($warehouse->id, [
        'sunat_establishment_code' => '0001',
        'is_active' => true,
    ]);
    $unit = Unit::create([
        'abbreviation' => 'UND-C',
        'description' => 'UNIDAD CENTRAL',
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
    ]);
    $category = Category::create([
        'code' => 'CAT-CENTRAL',
        'description' => 'CATEGORÍA CENTRAL',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
    ]);
    $article = Article::create([
        'code' => 'ART-CENTRAL',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO CENTRAL',
        'billing_name' => 'ARTÍCULO CENTRAL',
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        'has_batch' => false,
        'has_expiration' => false,
        ...testSunatInventoryArticleFields('ART-CENTRAL'),
        'status' => 'ACTIVE',
    ]);
    $supplier = Supplier::create([
        'ruc' => '20666666668',
        'business_name' => 'PROVEEDOR CÁLCULO CENTRAL',
        'supplier_type' => 'BIENES',
        'payment_condition' => 'CREDITO',
        'status' => 'ACTIVE',
    ]);
    $currency = Currency::create([
        'code' => 'PEN-C',
        'description' => 'SOLES CENTRAL',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);

    return compact('user', 'company', 'warehouse', 'unit', 'article', 'supplier', 'currency');
}

it('calcula una línea gravada con precio unitario incluido IGV', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine(centralTaxLine());

    expect($line['gross_amount'])->toBe(118.0)
        ->and($line['taxable_base'])->toBe(100.0)
        ->and($line['tax_amount'])->toBe(18.0)
        ->and($line['line_total'])->toBe(118.0);
});

it('respeta una tasa gravada transaccional distinta de 18 por ciento', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine('10', 10, ['unit_price' => 110])
    );

    expect($line['taxable_base'])->toBe(100.0)
        ->and($line['tax_amount'])->toBe(10.0)
        ->and($line['line_total'])->toBe(110.0);
});

it('aplica el descuento antes de separar base e impuesto', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine('10', 18, ['quantity' => 2, 'discount_amount' => 23.60])
    );

    expect($line['gross_amount'])->toBe(236.0)
        ->and($line['net_amount'])->toBe(212.4)
        ->and($line['taxable_base'])->toBe(180.0)
        ->and($line['tax_amount'])->toBe(32.4)
        ->and($line['line_total'])->toBe(212.4);
});

it('calcula una línea exonerada sin impuesto', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine('20', 0, ['quantity' => 2, 'unit_price' => 50, 'discount_amount' => 10])
    );

    expect($line['taxable_base'])->toBe(90.0)
        ->and($line['subtotal'])->toBe(90.0)
        ->and($line['tax_amount'])->toBe(0.0)
        ->and($line['line_total'])->toBe(90.0);
});

it('calcula una línea inafecta sin impuesto', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine('30', 0, ['quantity' => 3, 'unit_price' => 20])
    );

    expect($line['taxable_base'])->toBe(60.0)
        ->and($line['tax_amount'])->toBe(0.0)
        ->and($line['line_total'])->toBe(60.0);
});

it('aplica el descuento a una línea inafecta', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine('30', 0, ['quantity' => 3, 'unit_price' => 20, 'discount_amount' => 7.50])
    );

    expect($line['gross_amount'])->toBe(60.0)
        ->and($line['discount_amount'])->toBe(7.5)
        ->and($line['taxable_base'])->toBe(52.5)
        ->and($line['tax_amount'])->toBe(0.0)
        ->and($line['line_total'])->toBe(52.5);
});

it('consolida líneas gravadas exoneradas e inafectas por su afectación transaccional', function () {
    $result = app(WarehouseEntryTransactionalTaxService::class)->calculate([
        centralTaxLine('10', 18),
        centralTaxLine('20', 0, ['unit_price' => 40]),
        centralTaxLine('30', 0, ['unit_price' => 30]),
    ]);

    expect($result['totals']['gross_total'])->toBe(188.0)
        ->and($result['totals']['taxable_total'])->toBe(100.0)
        ->and($result['totals']['exempt_total'])->toBe(40.0)
        ->and($result['totals']['unaffected_total'])->toBe(30.0)
        ->and($result['totals']['tax_total'])->toBe(18.0)
        ->and($result['totals']['grand_total'])->toBe(188.0);
});

it('separa el valor referencial de una línea gratuita de los importes pagables', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine('10', 18, ['quantity' => 2, 'unit_price' => 59, 'is_free' => true])
    );

    expect($line['free_reference_amount'])->toBe(118.0)
        ->and($line['taxable_base'])->toBe(100.0)
        ->and($line['subtotal'])->toBe(0.0)
        ->and($line['tax_amount'])->toBe(0.0)
        ->and($line['line_total'])->toBe(0.0);
});

it('no suma líneas gratuitas a las categorías ni al total del documento', function () {
    $result = app(WarehouseEntryTransactionalTaxService::class)->calculate([
        centralTaxLine('10', 18),
        centralTaxLine('20', 0, ['unit_price' => 50, 'is_free' => true]),
    ]);

    expect($result['totals']['gross_total'])->toBe(118.0)
        ->and($result['totals']['free_reference_total'])->toBe(50.0)
        ->and($result['totals']['exempt_total'])->toBe(0.0)
        ->and($result['totals']['tax_total'])->toBe(18.0)
        ->and($result['totals']['grand_total'])->toBe(118.0);
});

it('redondea una sola vez al importe monetario persistible', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine('10', 18, ['quantity' => 3.3333, 'unit_price' => 35.401234])
    );

    expect($line['gross_amount'])->toBe(118.0)
        ->and($line['taxable_base'])->toBe(100.0)
        ->and($line['tax_amount'])->toBe(18.0)
        ->and($line['line_total'])->toBe(118.0);
});

it('consolida varias líneas gravadas usando los importes finales de cada línea', function () {
    $result = app(WarehouseEntryTransactionalTaxService::class)->calculate([
        centralTaxLine('10', 18, ['unit_price' => 0.10]),
        centralTaxLine('10', 18, ['unit_price' => 0.10]),
        centralTaxLine('10', 18, ['unit_price' => 0.10]),
    ]);

    expect($result['totals']['taxable_total'])->toBe(0.24)
        ->and($result['totals']['tax_total'])->toBe(0.06)
        ->and($result['totals']['grand_total'])->toBe(0.30);
});

it('no permite que affect igv legacy gobierne líneas nuevas 20 o 30', function () {
    $result = app(WarehouseEntryTransactionalTaxService::class)->calculate([
        centralTaxLine('20', 0, ['unit_price' => 40]),
        centralTaxLine('30', 0, ['unit_price' => 30]),
    ], true);

    expect($result['totals']['exempt_total'])->toBe(40.0)
        ->and($result['totals']['unaffected_total'])->toBe(30.0)
        ->and($result['totals']['tax_total'])->toBe(0.0)
        ->and($result['totals']['grand_total'])->toBe(70.0);
});

it('rechaza una línea gravada sin tasa positiva', function () {
    expect(fn () => app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine('10', 0)
    ))->toThrow(ValidationException::class, 'Una línea gravada debe tener una tasa de impuesto mayor a cero.');
});

it('rechaza una tasa positiva para afectaciones no gravadas', function (string $code) {
    expect(fn () => app(WarehouseEntryTransactionalTaxService::class)->calculateLine(
        centralTaxLine($code, 18)
    ))->toThrow(ValidationException::class, 'Una línea exonerada o inafecta debe tener tasa cero.');
})->with(['20', '30']);

it('conserva el cálculo histórico cuando la línea no tiene evidencia tributaria', function () {
    $line = app(WarehouseEntryTransactionalTaxService::class)->calculateLine([
        'quantity' => 1,
        'unit_price' => 118,
        '_transactional_tax_legacy' => true,
    ], true);

    expect($line['tax_affectation_code'] ?? null)->toBeNull()
        ->and($line['taxable_base'])->toBeNull()
        ->and($line['subtotal'])->toBe(100.0)
        ->and($line['tax_amount'])->toBe(18.0)
        ->and($line['line_total'])->toBe(118.0);
});

it('prepara líneas directas sin consultar ni copiar la afectación del artículo', function () {
    $prepared = app(WarehouseEntryTransactionalTaxService::class)->prepare([
        ['quantity' => 1, 'unit_price' => 50],
    ], false);

    expect($prepared[0]['tax_affectation_code'])->toBe('30')
        ->and($prepared[0]['tax_rate'])->toBe(0)
        ->and($prepared[0]['discount_amount'])->toBe(0)
        ->and($prepared[0]['is_free'])->toBeFalse();
});

it('no inventa afectación 10 20 o 30 para líneas procedentes de una orden de proveedor', function () {
    $prepared = app(WarehouseEntryTransactionalTaxService::class)->prepare([
        ['quantity' => 1, 'unit_price' => 118],
    ], true, null, false);

    expect($prepared[0]['tax_affectation_code'] ?? null)->toBeNull()
        ->and($prepared[0]['tax_rate'] ?? null)->toBeNull()
        ->and($prepared[0]['_transactional_tax_legacy'])->toBeTrue();
});

it('mantiene servicios y artículos no inventariables fuera del ingreso físico', function () {
    $service = new Article([
        'item_kind' => Article::KIND_SERVICE,
        'is_inventory_item' => false,
    ]);

    expect(fn () => app(ArticleInventoryPolicy::class)->assertCanParticipateInInventory($service, 'items'))
        ->toThrow(ValidationException::class, 'no está clasificado como producto inventariable');
});

it('persiste en un ingreso directo los totales de cabecera y snapshots tributarios de línea', function () {
    Storage::fake('public');
    $fixture = centralTaxDirectEntryFixture();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.store', 'web');
    $fixture['user']->givePermissionTo('admin.warehouse-entries.store');

    $response = $this->actingAs($fixture['user'])->postJson(route('admin.warehouse-entries.store'), [
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $fixture['warehouse']->id,
        'company_id' => $fixture['company']->id,
        'supplier_id' => $fixture['supplier']->id,
        'currency_id' => $fixture['currency']->id,
        'document_type' => 'FACTURA',
        'document_series' => 'F043',
        'document_number' => '000001',
        'document_date' => '2026-09-22',
        'movement_date' => '2026-09-22 10:30:00',
        'exchange_rate' => 1,
        'payment_method' => 'transferencia',
        'payment_condition' => 'credito',
        'credit_days' => 15,
        'generate_account_payable' => 1,
        'affect_igv' => 0,
        'items' => [[
            'article_id' => $fixture['article']->id,
            'billing_name_snapshot' => $fixture['article']->billing_name,
            'unit_id' => $fixture['unit']->id,
            'quantity' => 2,
            'unit_price' => 59,
            'discount_amount' => 11.80,
            'tax_affectation_code' => '10',
            'tax_rate' => 18,
            'is_free' => false,
            'igv_recoverable' => true,
        ]],
    ])->assertCreated()->assertJsonPath('status', 'success');

    $entry = WarehouseEntry::query()->findOrFail($response->json('data.id'));
    $item = $entry->items()->firstOrFail();

    expect($entry->affect_igv)->toBeTrue()
        ->and($entry->subtotal)->toBe('90.00')
        ->and($entry->igv)->toBe('16.20')
        ->and($entry->grand_total)->toBe('106.20')
        ->and($entry->payable_amount)->toBe('106.20')
        ->and($item->tax_affectation_code)->toBe('10')
        ->and($item->tax_rate)->toBe('18.00')
        ->and($item->discount_amount)->toBe('11.80')
        ->and($item->taxable_base)->toBe('90.00')
        ->and($item->subtotal)->toBe('90.00')
        ->and($item->tax_amount)->toBe('16.20')
        ->and($item->line_total)->toBe('106.20');

    $fixture['article']->update([
        'sales_tax_affectation_code' => Article::SALES_TAX_UNAFFECTED,
        'is_taxable' => false,
    ]);

    expect($item->fresh()->tax_affectation_code)->toBe('10')
        ->and($item->tax_rate)->toBe('18.00')
        ->and($item->taxable_base)->toBe('90.00')
        ->and($item->tax_amount)->toBe('16.20')
        ->and($item->line_total)->toBe('106.20');
});
