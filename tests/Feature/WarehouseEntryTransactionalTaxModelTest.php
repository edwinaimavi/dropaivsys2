<?php

use App\Http\Controllers\Admin\WarehouseEntryController;
use App\Models\Article;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use App\Services\ArticleInventoryPolicy;
use App\Services\WarehouseEntryTransactionalTaxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function transactionalTaxFixture(): array
{
    $now = now();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA TRIBUTARIA',
        'ruc' => '20123456789',
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'TAX-WH',
        'name' => 'ALMACÉN TRIBUTARIO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'TAX',
        'description' => 'UNIDAD TRIBUTARIA',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTOS TRIBUTARIOS',
        'code' => 'TAX-CAT',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => 'TAX-ARTICLE',
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO TRIBUTARIO',
        'billing_name' => 'ARTÍCULO TRIBUTARIO',
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        'sales_tax_affectation_code' => Article::SALES_TAX_TAXABLE,
        'is_taxable' => true,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20987654321',
        'business_name' => 'PROVEEDOR TRIBUTARIO',
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'TAX-PEN',
        'description' => 'SOLES TRIBUTARIOS',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-TAX-000001',
        'entry_mode' => 'supplier_invoice',
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'document_type' => 'FACTURA',
        'affect_igv' => true,
        'status' => 'registered',
    ]);

    return compact('entry', 'articleId', 'unitId');
}

function transactionalTaxLine(string $code, float $rate, array $overrides = []): array
{
    return array_merge([
        'article_id' => 1,
        'billing_name_snapshot' => 'ARTÍCULO TRIBUTARIO',
        'quantity' => 2,
        'unit_price' => 59,
        'subtotal' => 100,
        'tax_amount' => 18,
        'line_total' => 118,
        'tax_affectation_code' => $code,
        'tax_rate' => $rate,
        'discount_amount' => 0,
        'taxable_base' => 100,
        'is_free' => false,
        '_transactional_tax_legacy' => false,
    ], $overrides);
}

it('acepta las afectaciones 10, 20 y 30 con sus tasas válidas', function (string $code, float $rate) {
    app(WarehouseEntryTransactionalTaxService::class)->validate([
        transactionalTaxLine($code, $rate, [
            'taxable_base' => $code === WarehouseEntryItem::TAX_AFFECTATION_TAXED ? 100 : 118,
        ]),
    ]);

    expect(true)->toBeTrue();
})->with([
    'gravado' => [WarehouseEntryItem::TAX_AFFECTATION_TAXED, 18],
    'exonerado' => [WarehouseEntryItem::TAX_AFFECTATION_EXEMPT, 0],
    'inafecto' => [WarehouseEntryItem::TAX_AFFECTATION_UNAFFECTED, 0],
]);

it('rechaza códigos tributarios no soportados', function () {
    expect(fn () => app(WarehouseEntryTransactionalTaxService::class)->validate([
        transactionalTaxLine('40', 0),
    ]))->toThrow(ValidationException::class, 'La afectación tributaria debe ser 10, 20 o 30.');
});

it('rechaza tasa positiva para líneas exoneradas o inafectas', function (string $code) {
    expect(fn () => app(WarehouseEntryTransactionalTaxService::class)->validate([
        transactionalTaxLine($code, 18),
    ]))->toThrow(ValidationException::class, 'Una línea exonerada o inafecta debe tener tasa cero.');
})->with([
    WarehouseEntryItem::TAX_AFFECTATION_EXEMPT,
    WarehouseEntryItem::TAX_AFFECTATION_UNAFFECTED,
]);

it('rechaza descuentos negativos', function () {
    expect(fn () => app(WarehouseEntryTransactionalTaxService::class)->validate([
        transactionalTaxLine('10', 18, ['discount_amount' => -0.01]),
    ]))->toThrow(ValidationException::class, 'El descuento no puede ser negativo.');
});

it('rechaza descuentos mayores al importe bruto', function () {
    expect(fn () => app(WarehouseEntryTransactionalTaxService::class)->validate([
        transactionalTaxLine('10', 18, ['discount_amount' => 118.01]),
    ]))->toThrow(ValidationException::class, 'El descuento no puede superar el importe bruto de la línea.');
});

it('mantiene legible una línea histórica sin evidencia tributaria', function () {
    $fixture = transactionalTaxFixture();
    $item = WarehouseEntryItem::create([
        'warehouse_entry_id' => $fixture['entry']->id,
        'article_id' => $fixture['articleId'],
        'billing_name_snapshot' => 'ARTÍCULO LEGACY',
        'unit_id' => $fixture['unitId'],
        'quantity' => 1,
        'unit_price' => 25,
        'subtotal' => 25,
        'tax_amount' => 0,
        'line_total' => 25,
        'status' => 'active',
    ])->fresh();

    expect($item->tax_affectation_code)->toBeNull()
        ->and($item->tax_rate)->toBeNull()
        ->and($item->taxable_base)->toBeNull()
        ->and((float) $item->discount_amount)->toBe(0.0)
        ->and($item->is_free)->toBeFalse();
});

it('valida una línea legacy cuando recibe nueva información tributaria', function () {
    $fixture = transactionalTaxFixture();
    $item = WarehouseEntryItem::create([
        'warehouse_entry_id' => $fixture['entry']->id,
        'article_id' => $fixture['articleId'],
        'billing_name_snapshot' => 'ARTÍCULO LEGACY EDITADO',
        'unit_id' => $fixture['unitId'],
        'quantity' => 1,
        'unit_price' => 100,
        'line_total' => 100,
        'status' => 'active',
    ]);
    $prepared = app(WarehouseEntryTransactionalTaxService::class)->prepare([[
        'id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'tax_affectation_code' => '20',
        'tax_rate' => 18,
        'taxable_base' => 100,
    ]], true, $fixture['entry']);

    expect(fn () => app(WarehouseEntryTransactionalTaxService::class)->validate($prepared))
        ->toThrow(ValidationException::class, 'Una línea exonerada o inafecta debe tener tasa cero.');
});

it('persiste el snapshot tributario y no cambia al modificar Article', function () {
    $fixture = transactionalTaxFixture();
    $raw = app(WarehouseEntryTransactionalTaxService::class)->prepare([[
        'article_id' => $fixture['articleId'],
        'billing_name_snapshot' => 'ARTÍCULO EXONERADO',
        'unit_id' => $fixture['unitId'],
        'quantity' => 2,
        'unit_price' => 50,
        'tax_affectation_code' => '20',
        'tax_rate' => 0,
        'discount_amount' => 10,
        'taxable_base' => 90,
        'is_free' => true,
    ]], true, $fixture['entry']);
    $prepared = (new ReflectionMethod(WarehouseEntryController::class, 'prepareItems'))
        ->invoke(new WarehouseEntryController, $raw, true);
    app(WarehouseEntryTransactionalTaxService::class)->validate($prepared);
    $item = $fixture['entry']->items()->create($prepared[0]);

    Article::whereKey($fixture['articleId'])->update([
        'sales_tax_affectation_code' => Article::SALES_TAX_UNAFFECTED,
    ]);
    $item->refresh();

    expect($item->tax_affectation_code)->toBe('20')
        ->and((float) $item->tax_rate)->toBe(0.0)
        ->and((float) $item->discount_amount)->toBe(10.0)
        ->and((float) $item->taxable_base)->toBe(90.0)
        ->and($item->is_free)->toBeTrue()
        ->and($item->isExempt())->toBeTrue()
        ->and($item->isTaxed())->toBeFalse()
        ->and($item->isUnaffected())->toBeFalse();
});

it('mantiene servicios y artículos no inventariables fuera del ingreso físico', function () {
    $service = new Article([
        'item_kind' => Article::KIND_SERVICE,
        'is_inventory_item' => false,
    ]);

    expect(fn () => app(ArticleInventoryPolicy::class)->assertCanParticipateInInventory($service, 'items'))
        ->toThrow(ValidationException::class, 'no está clasificado como producto inventariable');
});
