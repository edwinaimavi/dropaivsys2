<?php

use App\Http\Controllers\Admin\SupplierPurchaseOrderController;
use App\Http\Controllers\Admin\WarehouseEntryController;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\Unit;
use App\Services\WarehouseEntryTaxTotalsService;

function supplierTransactionalTaxFixture(): array
{
    $company = Company::create([
        'business_name' => 'EMPRESA OC TRIBUTARIA', 'ruc' => '20424242421', 'status' => true,
    ]);
    $supplier = Supplier::create([
        'ruc' => '20624242421', 'business_name' => 'PROVEEDOR OC TRIBUTARIA',
        'supplier_type' => 'BIENES', 'payment_condition' => 'CREDITO', 'status' => 'ACTIVE',
    ]);
    $currency = Currency::create([
        'code' => 'PEN', 'description' => 'Soles OC', 'symbol' => 'S/', 'status' => 'ACTIVE',
    ]);
    $unit = Unit::create([
        'abbreviation' => 'UND-O', 'description' => 'UNIDAD OC', 'status' => 'ACTIVE',
    ]);
    $category = Category::create([
        'code' => 'CAT-OC-TAX', 'description' => 'CATEGORÍA OC TRIBUTARIA',
        'type' => 'PRODUCTO COMERCIAL', 'status' => 'ACTIVE',
    ]);
    $article = Article::create([
        'code' => 'ART-OC-TAX', 'category_id' => $category->id, 'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO OC TRIBUTARIA', 'billing_name' => 'ARTÍCULO OC TRIBUTARIA',
        'item_kind' => Article::KIND_PRODUCT, 'is_inventory_item' => true,
        'sales_tax_affectation_code' => '10', 'is_taxable' => true, 'status' => 'ACTIVE',
    ]);
    $order = SupplierPurchaseOrder::create([
        'code' => 'OCPT-TAX-001', 'company_id' => $company->id, 'supplier_id' => $supplier->id,
        'currency_id' => $currency->id, 'payment_currency_id' => $currency->id,
        'order_type' => 'articles', 'affect_igv' => true,
        'subtotal' => 170, 'igv' => 18, 'grand_total' => 188,
        'total_purchase_currency' => 188, 'total_payment_currency' => 188,
        'status' => 'registered',
    ]);

    return compact('company', 'supplier', 'currency', 'unit', 'category', 'article', 'order');
}

function supplierTransactionalLine(array $fixture, string $code, float $price, array $overrides = []): array
{
    return array_merge([
        'article_id' => $fixture['article']->id,
        'billing_name_snapshot' => 'ARTÍCULO '.$code,
        'unit_id' => $fixture['unit']->id,
        'quantity' => 1,
        'unit_price' => $price,
        'tax_affectation_code' => $code,
        'tax_rate' => $code === '10' ? 18 : 0,
        'discount_amount' => 0,
        'is_free' => false,
        'igv_recoverable' => $code === '10',
        'status' => 'active',
    ], $overrides);
}

it('calcula y persiste una OC mixta con snapshots tributarios por línea', function () {
    $fixture = supplierTransactionalTaxFixture();
    $method = new ReflectionMethod(SupplierPurchaseOrderController::class, 'prepareItems');
    $prepared = $method->invoke(new SupplierPurchaseOrderController, [
        supplierTransactionalLine($fixture, '10', 118),
        supplierTransactionalLine($fixture, '20', 50, ['discount_amount' => 10]),
        supplierTransactionalLine($fixture, '30', 30),
    ], true);

    foreach ($prepared as $item) {
        $itemId = $fixture['order']->items()->create($item)->id;
        expect($itemId)->toBeGreaterThan(0);
    }
    $fixture['order']->update(['subtotal' => 170, 'igv' => 18, 'grand_total' => 188]);
    $items = $fixture['order']->items()->orderBy('id')->get();

    expect($items->pluck('tax_affectation_code')->all())->toBe(['10', '20', '30'])
        ->and($items[0]->taxable_base)->toBe('100.000000')
        ->and($items[0]->tax_amount)->toBe('18.000000')
        ->and($items[0]->igv_recoverable)->toBeTrue()
        ->and($items[1]->taxable_base)->toBe('40.000000')
        ->and($items[1]->tax_amount)->toBe('0.000000')
        ->and($items[1]->discount_amount)->toBe('10.00')
        ->and($items[2]->line_total)->toBe('30.000000');

    $fixture['article']->update(['sales_tax_affectation_code' => '30', 'is_taxable' => false]);
    expect($items[0]->fresh()->tax_affectation_code)->toBe('10')
        ->and($items[0]->tax_rate)->toBe('18.00')
        ->and($items[0]->taxable_base)->toBe('100.000000');
});

it('transporta 10 20 30 gratuidad y recuperabilidad desde OC hacia el desglose del ingreso', function () {
    $fixture = supplierTransactionalTaxFixture();
    $sourceItems = collect([
        supplierTransactionalLine($fixture, '10', 118),
        supplierTransactionalLine($fixture, '20', 40),
        supplierTransactionalLine($fixture, '30', 30, ['is_free' => true]),
    ])->map(fn (array $data) => $fixture['order']->items()->create([
        ...$data,
        'subtotal' => $data['tax_affectation_code'] === '10' ? 100 : ($data['is_free'] ? 0 : $data['unit_price']),
        'tax_amount' => $data['tax_affectation_code'] === '10' ? 18 : 0,
        'line_total' => $data['is_free'] ? 0 : $data['unit_price'],
        'total_with_igv' => $data['is_free'] ? 0 : $data['unit_price'],
        'taxable_base' => $data['tax_affectation_code'] === '10' ? 100 : $data['unit_price'],
        'igv_percent' => $data['tax_rate'],
        'igv_amount' => $data['tax_affectation_code'] === '10' ? 18 : 0,
    ]));
    $fixture['order']->update(['subtotal' => 140, 'igv' => 18, 'grand_total' => 158]);

    $received = $sourceItems->map(fn (SupplierPurchaseOrderItem $item) => [
        'supplier_purchase_order_item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => $item->unit_price,
    ])->all();
    $calculation = app(WarehouseEntryTaxTotalsService::class)->calculate(
        $fixture['order']->fresh('items'),
        $received
    );

    expect($calculation['totals'])->toBe([
        'subtotal' => '140.00', 'igv' => '18.00', 'grand_total' => '158.00',
    ])
        ->and(array_column($calculation['items'], 'tax_affectation_code'))->toBe(['10', '20', '30'])
        ->and($calculation['items'][0]['igv_recoverable'])->toBeTrue()
        ->and($calculation['items'][2]['is_free'])->toBeTrue()
        ->and($calculation['items'][2]['line_total'])->toBe('0.00');

    $preparedEntryItems = array_map(fn ($item) => [
        'subtotal' => 0, 'tax_amount' => 0, 'line_total' => 0,
        'taxable_base' => null, 'tax_affectation_code' => null, 'tax_rate' => null,
        'discount_amount' => 0, 'is_free' => false, 'igv_recoverable' => null,
        '_transactional_tax_legacy' => true, '_taxable_base_explicit' => false,
    ], $calculation['items']);
    $transported = (new ReflectionMethod(WarehouseEntryController::class, 'applySupplierOrderTaxBreakdown'))
        ->invoke(new WarehouseEntryController, $preparedEntryItems, $calculation['items']);

    expect(array_column($transported, 'tax_affectation_code'))->toBe(['10', '20', '30'])
        ->and($transported[0]['taxable_base'])->toBe('100.00')
        ->and($transported[0]['tax_amount'])->toBe('18.00')
        ->and($transported[1]['line_total'])->toBe('40.00')
        ->and($transported[2]['is_free'])->toBeTrue();
});

it('mantiene prorrateo parcial y cierre exacto del residuo tributario', function () {
    $fixture = supplierTransactionalTaxFixture();
    $item = $fixture['order']->items()->create([
        ...supplierTransactionalLine($fixture, '10', 10, ['quantity' => 10]),
        'subtotal' => 84.75, 'tax_amount' => 15.25, 'line_total' => 100,
        'total_with_igv' => 100, 'taxable_base' => 84.75,
        'igv_percent' => 18, 'igv_amount' => 15.25,
    ]);
    $fixture['order']->update(['subtotal' => 84.75, 'igv' => 15.25, 'grand_total' => 100]);
    $service = app(WarehouseEntryTaxTotalsService::class);

    $partial = $service->calculate($fixture['order']->fresh('items'), [[
        'supplier_purchase_order_item_id' => $item->id, 'quantity' => 3, 'unit_price' => 10,
    ]]);
    $final = $service->calculate($fixture['order']->fresh('items'), [[
        'supplier_purchase_order_item_id' => $item->id, 'quantity' => 7, 'unit_price' => 10,
    ]], [$item->id => 3], $partial['totals']);

    expect($partial['totals'])->toBe(['subtotal' => '25.43', 'igv' => '4.57', 'grand_total' => '30.00'])
        ->and($final['totals'])->toBe(['subtotal' => '59.32', 'igv' => '10.68', 'grand_total' => '70.00'])
        ->and((float) $partial['totals']['subtotal'] + (float) $final['totals']['subtotal'])->toBe(84.75)
        ->and((float) $partial['totals']['igv'] + (float) $final['totals']['igv'])->toBe(15.25);
});
