<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\SupplierPurchaseOrder;
use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use App\Services\CustomerOrderProfitabilityService;
use App\Services\CustomerPurchaseOrderStatusService;
use App\Services\WarehouseEntryAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $now = now();
    $this->companyId = DB::table('companies')->insertGetId([
        'business_name' => 'DROPAIV ALLOCATION TEST', 'trade_name' => 'DROPAIV',
        'ruc' => '20123456789', 'status' => true, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica', 'business_name' => 'CLIENTE ASIGNACIÓN',
        'document_type' => 'RUC', 'document_number' => '20987654321', 'ruc' => '20987654321',
        'status' => true, 'created_by' => $this->user->id, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20444555666', 'business_name' => 'PROVEEDOR ASIGNACIÓN',
        'short_name' => 'PROVEEDOR TEST', 'supplier_type' => 'DISTRIBUIDOR',
        'payment_condition' => 'CRÉDITO', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->currencyId = DB::table('currencies')->insertGetId([
        'code' => 'PEN', 'description' => 'SOLES', 'symbol' => 'S/',
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'UND', 'description' => 'UNIDAD', 'status' => 'ACTIVE',
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $presentationId = DB::table('presentations')->insertGetId([
        'description' => 'UNIDAD', 'unit_id' => $unitId, 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $brandId = DB::table('brands')->insertGetId([
        'code' => 'ALLOC-BRAND', 'description' => 'MARCA', 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA', 'code' => 'ALLOC-CAT', 'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $this->articleId = DB::table('articles')->insertGetId([
        'code' => 'ALLOC-ART', 'category_id' => $categoryId, 'presentation_id' => $presentationId,
        'unit_id' => $unitId, 'brand_id' => $brandId, 'legal_name' => 'ARTÍCULO ASIGNABLE',
        'billing_name' => 'ARTÍCULO ASIGNABLE', 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
});

it('divide una línea entre varias OC cliente sin duplicar el movimiento físico', function () {
    [$firstOrder, $firstItemId] = allocationCustomerOrder($this, 'OC-CLIENTE-A', 4);
    [$secondOrder, $secondItemId] = allocationCustomerOrder($this, 'OC-CLIENTE-B', 6);
    [$entry, $entryItem] = allocationWarehouseEntry($this, 10, 10);

    $beforeKardex = DB::table('warehouse_kardex_movements')->count();
    app(WarehouseEntryAllocationService::class)->sync($entryItem, [
        ['allocation_type' => 'customer_order', 'customer_purchase_order_item_id' => $firstItemId, 'quantity_allocated' => 4],
        ['allocation_type' => 'customer_order', 'customer_purchase_order_item_id' => $secondItemId, 'quantity_allocated' => 6],
    ], $this->user->id);

    expect($entryItem->allocations()->count())->toBe(2)
        ->and((float) $entryItem->allocations()->sum('quantity_allocated'))->toBe(10.0)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe($beforeKardex);

    app(CustomerPurchaseOrderStatusService::class)->syncMany([$firstOrder->id, $secondOrder->id]);
    expect($firstOrder->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED)
        ->and($secondOrder->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);

    $firstProfitability = app(CustomerOrderProfitabilityService::class)->calculate($firstOrder->fresh());
    $secondProfitability = app(CustomerOrderProfitabilityService::class)->calculate($secondOrder->fresh());
    expect($firstProfitability['purchaseTotal'])->toBe(40.0)
        ->and($secondProfitability['purchaseTotal'])->toBe(60.0)
        ->and($firstProfitability['entryIds']->contains($entry->id))->toBeTrue();
});

it('impide asignar por encima del saldo pendiente de la OC cliente', function () {
    [, $customerItemId] = allocationCustomerOrder($this, 'OC-LÍMITE', 5);
    [, $firstEntryItem] = allocationWarehouseEntry($this, 4, 8);
    app(WarehouseEntryAllocationService::class)->sync($firstEntryItem, [[
        'allocation_type' => 'customer_order',
        'customer_purchase_order_item_id' => $customerItemId,
        'quantity_allocated' => 4,
    ]], $this->user->id);
    [, $secondEntryItem] = allocationWarehouseEntry($this, 2, 8);

    expect(fn () => app(WarehouseEntryAllocationService::class)->sync($secondEntryItem, [[
        'allocation_type' => 'customer_order',
        'customer_purchase_order_item_id' => $customerItemId,
        'quantity_allocated' => 2,
    ]], $this->user->id))->toThrow(ValidationException::class);
});

it('permite abastecer una OC cliente en moneda distinta a la factura del proveedor', function () {
    [$customerOrder, $customerItemId] = allocationCustomerOrder($this, 'OC-MONEDA-DISTINTA', 3);
    $dollarCurrencyId = DB::table('currencies')->insertGetId([
        'code' => 'USD', 'description' => 'DÓLARES', 'symbol' => '$',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $customerOrder->update(['currency_id' => $dollarCurrencyId]);
    [, $entryItem] = allocationWarehouseEntry($this, 3, 12);

    app(WarehouseEntryAllocationService::class)->sync($entryItem, [[
        'allocation_type' => 'customer_order',
        'customer_purchase_order_item_id' => $customerItemId,
        'quantity_allocated' => 3,
    ]], $this->user->id);

    expect($entryItem->allocations()->first()->customer_purchase_order_id)->toBe($customerOrder->id);
});

it('convierte a PEN el costo asignado de una factura en moneda extranjera', function () {
    [$customerOrder, $customerItemId] = allocationCustomerOrder($this, 'OC-COSTO-USD', 3);
    $dollarCurrencyId = DB::table('currencies')->insertGetId([
        'code' => 'USD', 'description' => 'DÓLARES', 'symbol' => '$',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    [$entry, $entryItem] = allocationWarehouseEntry($this, 3, 12);
    $entry->update([
        'currency_id' => $dollarCurrencyId,
        'bank_payment_exchange_rate' => 3.75,
    ]);

    app(WarehouseEntryAllocationService::class)->sync($entryItem, [[
        'allocation_type' => 'customer_order',
        'customer_purchase_order_item_id' => $customerItemId,
        'quantity_allocated' => 3,
    ]], $this->user->id);

    $profitability = app(CustomerOrderProfitabilityService::class)->calculate($customerOrder->fresh());
    expect($profitability['purchaseTotal'])->toBe(135.0);
});

it('recalcula una OC proveedor desde una factura sin vínculo obligatorio en cabecera', function () {
    $supplierOrderId = DB::table('supplier_purchase_orders')->insertGetId([
        'code' => 'OCP-ALLOC', 'company_id' => $this->companyId, 'supplier_id' => $this->supplierId,
        'currency_id' => $this->currencyId, 'order_type' => 'articles', 'status' => 'registered',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $supplierItemId = DB::table('supplier_purchase_order_items')->insertGetId([
        'supplier_purchase_order_id' => $supplierOrderId, 'article_id' => $this->articleId,
        'billing_name_snapshot' => 'ARTÍCULO ASIGNABLE', 'quantity' => 10,
        'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);
    [, $entryItem] = allocationWarehouseEntry($this, 10, 5);
    app(WarehouseEntryAllocationService::class)->sync($entryItem, [[
        'allocation_type' => 'supplier_order',
        'supplier_purchase_order_item_id' => $supplierItemId,
        'quantity_allocated' => 10,
    ]], $this->user->id);

    $supplierOrder = SupplierPurchaseOrder::query()->findOrFail($supplierOrderId);
    $supplierOrder->refreshEntryStatus();
    expect($supplierOrder->fresh()->status)->toBe('entered')
        ->and($entryItem->warehouseEntry->supplier_purchase_order_id)->toBeNull();
});

function allocationCustomerOrder(object $test, string $number, float $quantity): array
{
    $id = DB::table('customer_purchase_orders')->insertGetId([
        'code' => $number, 'company_id' => $test->companyId, 'customer_id' => $test->customerId,
        'currency_id' => $test->currencyId, 'order_type' => 'articles',
        'purchase_order_number' => $number, 'status' => 'registered',
        'created_by' => $test->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $itemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $id, 'article_id' => $test->articleId,
        'billing_name_snapshot' => 'ARTÍCULO ASIGNABLE', 'quantity' => $quantity,
        'unit_price' => 20, 'line_total' => $quantity * 20, 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return [CustomerPurchaseOrder::query()->findOrFail($id), $itemId];
}

function allocationWarehouseEntry(object $test, float $quantity, float $unitCost): array
{
    static $sequence = 0;
    $sequence++;
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-ALLOC-'.$sequence, 'entry_mode' => 'supplier_invoice',
        'company_id' => $test->companyId, 'supplier_id' => $test->supplierId,
        'currency_id' => $test->currencyId, 'document_type' => 'FACTURA',
        'document_series' => 'F001', 'document_number' => (string) $sequence,
        'document_date' => today(), 'affect_igv' => false,
        'subtotal' => 0, 'igv' => 0, 'grand_total' => $quantity * $unitCost,
        'status' => 'registered', 'created_by' => $test->user->id, 'updated_by' => $test->user->id,
    ]);
    $item = WarehouseEntryItem::create([
        'warehouse_entry_id' => $entry->id, 'article_id' => $test->articleId,
        'billing_name_snapshot' => 'ARTÍCULO ASIGNABLE', 'quantity' => $quantity,
        'unit_price' => $unitCost, 'line_total' => $quantity * $unitCost,
        'status' => 'active',
    ]);

    return [$entry, $item];
}
