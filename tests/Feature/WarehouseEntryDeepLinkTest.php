<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderAdvancePayment;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    foreach ([
        'admin.warehouse-entries.index',
        'admin.warehouse-entries.load-items',
        'admin.warehouse-entries.store',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo([
        'admin.warehouse-entries.index',
        'admin.warehouse-entries.load-items',
        'admin.warehouse-entries.store',
    ]);
    $this->actingAs($this->user);

    $this->company = Company::create([
        'business_name' => 'DROPAIV TEST S.A.C.',
        'trade_name' => 'DROPAIV TEST',
        'ruc' => '20123456789',
        'status' => true,
    ]);
    $this->supplier = Supplier::create([
        'ruc' => '20987654321',
        'business_name' => 'PROVEEDOR DE PRUEBA S.A.C.',
        'short_name' => 'PROVEEDOR TEST',
        'supplier_type' => 'DISTRIBUIDOR',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->supplierOrder = SupplierPurchaseOrder::create([
        'code' => 'OCP-DEEP-001',
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'order_type' => 'DIRECTA',
        'payment_method' => 'deposito_cuenta',
        'payment_condition' => 'contado',
        'document_type' => 'factura',
        'affect_igv' => true,
        'grand_total' => 118.00,
        'status' => 'registered',
    ]);
});

it('abre el flujo de creación precargada cuando la OC proveedor no tiene ingreso', function () {
    $response = $this->get(route('admin.warehouse-entries.index', [
        'from_supplier_purchase_order' => $this->supplierOrder->id,
        'auto_open' => 1,
    ]));

    $response->assertOk()
        ->assertViewHas('warehouseEntryDeepLink', fn (array $deepLink) =>
            $deepLink['action'] === 'create'
            && $deepLink['supplier_purchase_order_id'] === $this->supplierOrder->id
            && $deepLink['warehouse_entry_id'] === null
        );
});

it('abre la edición cuando la OC proveedor ya tiene un ingreso asociado', function () {
    $entry = warehouseEntryDeepLinkExistingEntry();

    $response = $this->get(route('admin.warehouse-entries.index', [
        'from_supplier_purchase_order' => $this->supplierOrder->id,
        'auto_open' => 1,
    ]));

    $response->assertOk()
        ->assertViewHas('warehouseEntryDeepLink', fn (array $deepLink) =>
            $deepLink['action'] === 'edit'
            && $deepLink['supplier_purchase_order_id'] === $this->supplierOrder->id
            && $deepLink['warehouse_entry_id'] === $entry->id
        );
});

it('reutiliza la carga de la OC proveedor para precargar cabecera e items', function () {
    $article = warehouseEntryDeepLinkArticle();
    $orderItem = SupplierPurchaseOrderItem::create([
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'article_id' => $article->id,
        'article_code' => $article->code,
        'billing_name_snapshot' => $article->billing_name,
        'unit_id' => $article->unit_id,
        'quantity' => 1,
        'unit_price' => 118,
        'subtotal' => 100,
        'tax_amount' => 18,
        'line_total' => 118,
        'status' => 'active',
    ]);

    $response = $this->postJson(route('admin.warehouse-entries.loadSupplierOrderItems'), [
        'supplier_purchase_order_id' => $this->supplierOrder->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('supplier_purchase_order_id', $this->supplierOrder->id)
        ->assertJsonPath('company_id', $this->company->id)
        ->assertJsonPath('supplier_id', $this->supplier->id)
        ->assertJsonPath('supplier_ruc', $this->supplier->ruc)
        ->assertJsonPath('currency_id', $this->currency->id)
        ->assertJsonPath('purchase_order_number', $this->supplierOrder->code)
        ->assertJsonPath('order_total', '118.00')
        ->assertJsonPath('payment_method', 'deposito_cuenta')
        ->assertJsonPath('payment_condition', 'contado')
        ->assertJsonPath('document_type', 'FACTURA')
        ->assertJsonPath('affect_igv', true)
        ->assertJsonPath('items.0.supplier_purchase_order_item_id', $orderItem->id);
});

it('devuelve el saldo real del anticipo desde los pagos activos', function () {
    $this->supplierOrder->update([
        'payment_currency_id' => $this->currency->id,
        'grand_total' => 10000,
        'total_purchase_currency' => 10000,
        'total_payment_currency' => 10000,
        'total_pen' => 10000,
        'apply_advance' => true,
        'advance_type' => 'percentage',
        'advance_percentage' => 50,
        'advance_amount' => 0,
        'advance_paid_amount' => 0,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PENDING,
        'payment_status' => 'partial',
    ]);
    SupplierPurchaseOrderAdvancePayment::create([
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'currency_id' => $this->currency->id,
        'payment_date' => now()->toDateString(),
        'amount' => 4000,
        'amount_pen' => 4000,
        'payment_method' => 'transferencia',
        'status' => 'ACTIVE',
    ]);

    $response = $this->getJson(route(
        'admin.warehouse-entries.supplier-order-logistics-status',
        $this->supplierOrder
    ));

    $response->assertOk()
        ->assertJsonPath('financial_blocked', true)
        ->assertJsonPath('payment_currency', 'PEN')
        ->assertJsonPath('order_total', 10000)
        ->assertJsonPath('advance_paid', 4000)
        ->assertJsonPath('advance_balance', 6000)
        ->assertJsonPath('required_advance_balance', 1000)
        ->assertJsonPath('payment_summary.breakdown.0.currency', 'PEN')
        ->assertJsonPath('payment_summary.breakdown.0.order_total', 10000)
        ->assertJsonPath('payment_summary.breakdown.0.paid_total', 4000)
        ->assertJsonPath('payment_summary.breakdown.0.balance', 6000);
});

it('no bloquea la carga de artículos cuando el saldo real ya está cancelado', function () {
    $this->supplierOrder->update([
        'payment_currency_id' => $this->currency->id,
        'grand_total' => 118,
        'total_purchase_currency' => 118,
        'total_payment_currency' => 118,
        'total_pen' => 118,
        'apply_advance' => true,
        'advance_type' => 'percentage',
        'advance_percentage' => 50,
        'advance_amount' => 0,
        'advance_paid_amount' => 0,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PENDING,
        'payment_status' => 'pending',
    ]);
    SupplierPurchaseOrderAdvancePayment::create([
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'currency_id' => $this->currency->id,
        'payment_date' => now()->toDateString(),
        'amount' => 118,
        'amount_pen' => 118,
        'payment_method' => 'transferencia',
        'status' => 'ACTIVE',
    ]);
    $article = warehouseEntryDeepLinkArticle();
    SupplierPurchaseOrderItem::create([
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'article_id' => $article->id,
        'article_code' => $article->code,
        'billing_name_snapshot' => $article->billing_name,
        'unit_id' => $article->unit_id,
        'quantity' => 1,
        'unit_price' => 118,
        'subtotal' => 100,
        'tax_amount' => 18,
        'line_total' => 118,
        'status' => 'active',
    ]);

    $this->postJson(route('admin.warehouse-entries.loadSupplierOrderItems'), [
        'supplier_purchase_order_id' => $this->supplierOrder->id,
    ])
        ->assertOk()
        ->assertJsonPath('supplier_purchase_order_id', $this->supplierOrder->id)
        ->assertJsonCount(1, 'items');
});

it('bloquea también el registro directo mientras el anticipo obligatorio siga pendiente', function () {
    $this->supplierOrder->update([
        'payment_currency_id' => $this->currency->id,
        'grand_total' => 10000,
        'total_purchase_currency' => 10000,
        'total_payment_currency' => 10000,
        'total_pen' => 10000,
        'apply_advance' => true,
        'advance_type' => 'percentage',
        'advance_percentage' => 50,
        'advance_status' => SupplierPurchaseOrder::ADVANCE_PARTIAL,
        'payment_status' => 'partial',
    ]);
    SupplierPurchaseOrderAdvancePayment::create([
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'currency_id' => $this->currency->id,
        'payment_date' => now()->toDateString(),
        'amount' => 4000,
        'amount_pen' => 4000,
        'payment_method' => 'transferencia',
        'status' => 'ACTIVE',
    ]);
    $warehouse = Warehouse::create([
        'code' => 'ALM-ADVANCE',
        'name' => 'ALMACÉN ANTICIPO',
        'status' => 'ACTIVE',
    ]);
    $article = warehouseEntryDeepLinkArticle();

    $this->postJson(route('admin.warehouse-entries.store'), [
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'warehouse_id' => $warehouse->id,
        'document_type' => 'FACTURA',
        'generate_account_payable' => true,
        'expected_payment_date' => now()->addDays(7)->toDateString(),
        'items' => [[
            'article_id' => $article->id,
            'billing_name_snapshot' => $article->billing_name,
            'quantity' => 1,
            'unit_price' => 10000,
        ]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('supplier_purchase_order_id');

    expect(WarehouseEntry::query()
        ->where('supplier_purchase_order_id', $this->supplierOrder->id)
        ->doesntExist())->toBeTrue();
});

it('evita crear otro ingreso backend para una OC proveedor que ya tiene uno', function () {
    $entry = warehouseEntryDeepLinkExistingEntry();
    $warehouse = Warehouse::create([
        'code' => 'ALM-TEST',
        'name' => 'ALMACÉN DE PRUEBA',
        'status' => 'ACTIVE',
    ]);
    $article = warehouseEntryDeepLinkArticle();

    $response = $this->postJson(route('admin.warehouse-entries.store'), [
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'warehouse_id' => $warehouse->id,
        'document_type' => 'FACTURA',
        'items' => [[
            'article_id' => $article->id,
            'billing_name_snapshot' => $article->billing_name,
            'quantity' => 1,
            'unit_price' => 118,
        ]],
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('status', 'existing')
        ->assertJsonPath('existing_entry_id', $entry->id);
    expect(WarehouseEntry::query()
        ->where('supplier_purchase_order_id', $this->supplierOrder->id)
        ->count())->toBe(1);
});

function warehouseEntryDeepLinkExistingEntry(): WarehouseEntry
{
    return WarehouseEntry::create([
        'entry_number' => 'ING-DEEP-001',
        'supplier_purchase_order_id' => test()->supplierOrder->id,
        'company_id' => test()->company->id,
        'supplier_id' => test()->supplier->id,
        'currency_id' => test()->currency->id,
        'purchase_order_number' => test()->supplierOrder->code,
        'document_type' => 'FACTURA',
        'affect_igv' => true,
        'status' => 'registered',
    ]);
}

function warehouseEntryDeepLinkArticle(): Article
{
    $category = Category::create([
        'code' => 'CAT-TEST',
        'description' => 'CATEGORÍA DE PRUEBA',
        'type' => 'PRODUCTO',
        'status' => 'ACTIVE',
    ]);
    $unit = Unit::create([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'decimal_quantity' => false,
        'status' => 'ACTIVE',
    ]);

    return Article::create([
        'code' => 'ART-DEEP-001',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO DE PRUEBA',
        'billing_name' => 'ARTÍCULO DE PRUEBA',
        'status' => 'ACTIVE',
    ]);
}
