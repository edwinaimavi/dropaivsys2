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
        'admin.warehouse-entries.show',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo([
        'admin.warehouse-entries.index',
        'admin.warehouse-entries.load-items',
        'admin.warehouse-entries.store',
        'admin.warehouse-entries.show',
    ]);
    $this->actingAs($this->user);

    $this->company = Company::create([
        'business_name' => 'DROPAIV TEST S.A.C.',
        'trade_name' => 'DROPAIV TEST',
        'ruc' => '20123456789',
        'status' => true,
    ]);
    $this->user->companies()->attach($this->company->id);
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
        ->assertViewHas('warehouseEntryDeepLink', fn (array $deepLink) => $deepLink['action'] === 'create'
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
        ->assertViewHas('warehouseEntryDeepLink', fn (array $deepLink) => $deepLink['action'] === 'edit'
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

it('hidrata y persiste la cabecera tributaria canonica de una OC completa de 100', function () {
    $this->supplierOrder->update([
        'payment_condition' => 'credito_7_dias',
        'subtotal' => '84.75',
        'igv' => '15.25',
        'grand_total' => '100.00',
    ]);
    $firstArticle = warehouseEntryDeepLinkArticle();
    $secondArticle = Article::create([
        'code' => 'ART-DEEP-002',
        'category_id' => $firstArticle->category_id,
        'unit_id' => $firstArticle->unit_id,
        'legal_name' => 'SEGUNDO ARTICULO DE PRUEBA',
        'billing_name' => 'SEGUNDO ARTICULO DE PRUEBA',
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields('ART-DEEP-002'),
        'status' => 'ACTIVE',
    ]);
    $orderItems = collect([
        [$firstArticle, '10.00', '5.000000'],
        [$secondArticle, '20.00', '2.500000'],
    ])->map(fn (array $row) => SupplierPurchaseOrderItem::create([
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'article_id' => $row[0]->id,
        'article_code' => $row[0]->code,
        'billing_name_snapshot' => $row[0]->billing_name,
        'unit_id' => $row[0]->unit_id,
        'quantity' => $row[1],
        'unit_price' => $row[2],
        'subtotal' => '42.372881',
        'tax_amount' => '7.627119',
        'line_total' => '50.000000',
        'total_with_igv' => '50.000000',
        'taxable_base' => '42.372881',
        'igv_percent' => '18.00',
        'igv_amount' => '7.627119',
        'status' => 'active',
    ]));

    $this->postJson(route('admin.warehouse-entries.loadSupplierOrderItems'), [
        'supplier_purchase_order_id' => $this->supplierOrder->id,
    ])->assertOk()
        ->assertJsonPath('tax_totals.subtotal', '84.75')
        ->assertJsonPath('tax_totals.igv', '15.25')
        ->assertJsonPath('tax_totals.grand_total', '100.00');

    $warehouse = Warehouse::create([
        'code' => 'ALM-TAX-TEST',
        'name' => 'ALMACEN TRIBUTOS',
        'status' => 'ACTIVE',
    ]);
    $this->company->warehouses()->attach($warehouse->id, ['is_active' => true]);
    $response = $this->postJson(route('admin.warehouse-entries.store'), [
        'supplier_purchase_order_id' => $this->supplierOrder->id,
        'warehouse_id' => $warehouse->id,
        'document_type' => 'FACTURA',
        'generate_account_payable' => 1,
        'expected_payment_date' => today()->addDays(7)->toDateString(),
        'items' => $orderItems->values()->map(fn (SupplierPurchaseOrderItem $item) => [
            'supplier_purchase_order_item_id' => $item->id,
            'article_id' => $item->article_id,
            'billing_name_snapshot' => $item->billing_name_snapshot,
            'unit_id' => $item->unit_id,
            'ordered_quantity' => $item->quantity,
            'quantity' => $item->quantity,
            'unit_price' => $item->unit_price,
        ])->all(),
    ])->assertCreated();

    $entry = WarehouseEntry::query()->findOrFail($response->json('data.id'));
    expect($entry->subtotal)->toBe('84.75')
        ->and($entry->igv)->toBe('15.25')
        ->and($entry->grand_total)->toBe('100.00')
        ->and($entry->items()->pluck('quantity')->all())->toBe(['10.00', '20.00'])
        ->and($entry->items()->pluck('unit_price')->all())->toBe(['5.000000', '2.500000']);
});

it('al editar muestra la condición vigente de la OC proveedor y recalcula su vencimiento', function () {
    $this->supplierOrder->update(['payment_condition' => 'credito_30_dias']);
    $entry = warehouseEntryDeepLinkExistingEntry();
    $entry->update([
        'document_date' => '2026-07-21',
        'payment_condition' => 'contado',
        'generate_account_payable' => false,
        'expected_payment_date' => null,
    ]);

    $this->getJson(route('admin.warehouse-entries.show', $entry))
        ->assertOk()
        ->assertJsonPath('data.payment_condition', 'credito_30_dias')
        ->assertJsonPath('data.payment_condition_label', 'Crédito 30 días')
        ->assertJsonPath('data.generate_account_payable', true)
        ->assertJsonPath('data.credit_due_date', '2026-08-20')
        ->assertJsonPath('data.credit_days', 30);
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
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
    ]);

    return Article::create([
        'code' => 'ART-DEEP-001',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO DE PRUEBA',
        'billing_name' => 'ARTÍCULO DE PRUEBA',
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields('ART-DEEP-001'),
        'status' => 'ACTIVE',
    ]);
}
