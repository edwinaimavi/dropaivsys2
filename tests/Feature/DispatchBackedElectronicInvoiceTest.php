<?php

use App\Http\Controllers\Admin\ElectronicInvoiceController;
use App\Models\BankMovement;
use App\Models\Company;
use App\Models\Currency;
use App\Models\CustomerPurchaseOrder;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItemDispatchAllocation;
use App\Models\ElectronicInvoiceSeries;
use App\Models\ElectronicInvoiceSetting;
use App\Models\InvoiceCollection;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseEntryItemAllocation;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\InvoiceFromCustomerOrderService;
use App\Services\ElectronicInvoiceFormDataService;
use App\Services\WarehouseDispatchService;
use App\Services\WarehouseKardexService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function dispatchBackedInvoiceFixture(
    array $lines = [],
    bool $withConfiguration = true,
    bool $withSeries = true
): array {
    $lines = $lines ?: [
        [
            'ordered' => 10,
            'dispatched' => 10,
            'unit_price' => 10,
            'lot' => '14545454',
            'expiration' => '2030-02-05',
            'requested_expiration' => '2027-01-14',
            'origin' => 'NACIONAL',
        ],
        [
            'ordered' => 20,
            'dispatched' => 20,
            'unit_price' => 5,
            'lot' => '5445445',
            'expiration' => '2030-06-20',
            'requested_expiration' => '2028-09-29',
            'origin' => 'JAPON',
        ],
    ];
    Storage::fake('public');
    $user = User::factory()->create();
    Auth::login($user);
    $company = Company::create([
        'business_name' => 'DROGUERIA DROPAIV TEST',
        'trade_name' => 'DROPAIV TEST',
        'ruc' => '20601010101',
        'address' => 'LIMA',
        'status' => true,
    ]);
    $user->companies()->attach($company->id);
    $currency = Currency::create([
        'code' => 'PEN',
        'description' => 'SOLES',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'first_name' => '',
        'last_name' => '',
        'business_name' => 'SEGURO SOCIAL DE SALUD TEST',
        'document_type' => 'RUC',
        'document_number' => '20501010101',
        'ruc' => '20501010101',
        'address' => 'TARAPOTO',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'ALM-FAC',
        'name' => 'ALMACEN FACTURACION',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $company->id,
        'warehouse_id' => $warehouseId,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $catalog06Id = DB::table('sunat_catalogs')->insertGetId([
        'code' => '06', 'name' => 'UNIDAD DE MEDIDA', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $sunatUnitId = DB::table('sunat_catalog_items')->insertGetId([
        'sunat_catalog_id' => $catalog06Id, 'catalog_code' => '06', 'item_code' => 'NIU',
        'description' => 'UNIDAD (BIENES)', 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('units')->where('id', $unitId)->update(['sunat_unit_item_id' => $sunatUnitId]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTOS FACTURABLES',
        'code' => 'FAC',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $grandTotal = collect($lines)->sum(fn ($line) => $line['ordered'] * $line['unit_price']);
    $order = CustomerPurchaseOrder::create([
        'code' => 'P-FAC-001',
        'company_id' => $company->id,
        'customer_id' => $customerId,
        'order_type' => 'articles',
        'purchase_order_number' => 'OC-FAC-001',
        'currency_id' => $currency->id,
        'affect_igv' => true,
        'grand_total' => $grandTotal,
        'status' => CustomerPurchaseOrder::STATUS_ATTENDED,
        'created_by' => $user->id,
    ]);
    $orderItems = collect();
    $stocks = collect();
    foreach ($lines as $index => $line) {
        $articleId = DB::table('articles')->insertGetId([
            'code' => 'ART-FAC-'.($index + 1),
            'category_id' => $categoryId,
            'unit_id' => $unitId,
            'legal_name' => 'ARTICULO FACTURABLE '.($index + 1),
            'billing_name' => 'ARTICULO FACTURABLE '.($index + 1),
            'item_kind' => 'product',
            'is_inventory_item' => true,
            ...testSunatInventoryArticleFields('ART-FAC-'.($index + 1)),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $orderItem = $order->items()->create([
            'article_id' => $articleId,
            'article_code' => 'ART-FAC-'.($index + 1),
            'billing_name_snapshot' => 'ARTICULO FACTURABLE '.($index + 1),
            'unit_id' => $unitId,
            'origin' => 'SOLICITADO',
            'expiration_date' => $line['requested_expiration'],
            'quantity' => $line['ordered'],
            'unit_price' => $line['unit_price'],
            'line_total' => $line['ordered'] * $line['unit_price'],
            'status' => 'active',
        ]);
        $stock = WarehouseStock::create([
            'company_id' => $company->id,
            'stock_key' => "{$company->id}|{$warehouseId}|{$articleId}|{$line['lot']}|{$line['expiration']}",
            'warehouse_id' => $warehouseId,
            'article_id' => $articleId,
            'unit_id' => $unitId,
            'lot_number' => $line['lot'],
            'expiration_date' => $line['expiration'],
            'origin' => $line['origin'],
            'current_quantity' => 0,
            'reserved_quantity' => 0,
            'average_unit_cost' => 0,
            'total_cost' => 0,
            'status' => 'ACTIVE',
        ]);
        $orderItems->push($orderItem);
        $stocks->push($stock);
    }
    $dispatch = WarehouseDispatch::create([
        'company_id' => $company->id,
        'dispatch_number' => 'SAL-FAC-001',
        'idempotency_key' => 'dispatch-backed-fixture-1',
        'customer_purchase_order_id' => $order->id,
        'warehouse_id' => $warehouseId,
        'dispatch_date' => now(),
        'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER,
        'status' => WarehouseDispatch::STATUS_CONFIRMED,
        'confirmed_at' => now(),
        'confirmed_by' => $user->id,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);
    $dispatchItems = collect();
    foreach ($lines as $index => $line) {
        $orderItem = $orderItems[$index];
        $stock = $stocks[$index];
        $dispatchItems->push(WarehouseDispatchItem::create([
            'warehouse_dispatch_id' => $dispatch->id,
            'customer_purchase_order_item_id' => $orderItem->id,
            'warehouse_stock_id' => $stock->id,
            'article_id' => $orderItem->article_id,
            'unit_id' => $unitId,
            'lot_number' => $line['lot'],
            'expiration_date' => $line['expiration'],
            'quantity' => $line['dispatched'],
            'unit_cost' => 2,
            'total_cost' => $line['dispatched'] * 2,
            'status' => WarehouseDispatchItem::STATUS_CONFIRMED,
        ]));
    }
    $configuration = $withConfiguration ? ElectronicInvoiceSetting::create([
        'company_id' => $company->id,
        'provider' => 'internal',
        'environment' => 'internal',
        'ruc' => $company->ruc,
        'business_name' => $company->business_name,
        'is_active' => true,
    ]) : null;
    $series = $withSeries ? ElectronicInvoiceSeries::create([
        'company_id' => $company->id,
        'document_type' => '01',
        'serie' => 'F001',
        'current_number' => 0,
        'next_number' => 1,
        'environment' => 'internal',
        'is_default' => true,
        'status' => 'ACTIVE',
    ]) : null;

    return compact(
        'user', 'company', 'currency', 'customerId', 'warehouseId', 'unitId',
        'order', 'orderItems', 'stocks', 'dispatch', 'dispatchItems', 'configuration', 'series'
    );
}

function dispatchBackedInvoicePayload(array $fixture, ?array $items = null): array
{
    $prepared = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh());
    $items ??= $prepared['items'];

    return [
        'company_id' => $fixture['company']->id,
        'customer_id' => $fixture['customerId'],
        'customer_purchase_order_id' => $fixture['order']->id,
        'currency_id' => $fixture['currency']->id,
        'serie_id' => $fixture['series']?->id,
        'document_type' => '01',
        'requested_status' => 'generated',
        'issue_date' => today()->toDateString(),
        'payment_type' => 'Contado',
        'purchase_order_number' => $fixture['order']->purchase_order_number,
        'items' => array_map(fn (array $item) => [
            'warehouse_dispatch_item_id' => $item['warehouse_dispatch_item_id'] ?? null,
            'customer_purchase_order_item_id' => $item['customer_purchase_order_item_id'],
            'article_id' => $item['article_id'],
            'product_code' => $item['product_code'],
            'description' => $item['description'],
            'unit_code' => $item['unit_code'],
            'brand_name' => $item['brand_name'] ?? null,
            'presentation_name' => $item['presentation_name'] ?? null,
            'lot_number' => $item['lot_number'] ?? null,
            'expiration_date' => $item['expiration_date'] ?? null,
            'origin' => $item['origin'] ?? null,
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'tax_affectation_code' => $item['tax_affectation_code'],
        ], $items),
    ];
}

function generateDispatchBackedInvoice(array $fixture, ?array $items = null): ElectronicInvoice
{
    $response = app(ElectronicInvoiceController::class)->store(
        Request::create('/electronic-invoices', 'POST', dispatchBackedInvoicePayload($fixture, $items))
    );
    expect($response->getStatusCode())->toBe(201);

    return ElectronicInvoice::query()->latest('id')->firstOrFail();
}

function cancelDispatchBackedInvoice(ElectronicInvoice $invoice, string $reason = 'Corrección comercial de prueba')
{
    return app(ElectronicInvoiceController::class)->destroy(
        Request::create('/electronic-invoices/'.$invoice->id, 'DELETE', ['reason' => $reason]),
        $invoice->fresh(),
        app(WarehouseKardexService::class),
        app(InvoiceFromCustomerOrderService::class)
    );
}

it('prepara por GET el modal desde OC con cantidades y lotes reales del despacho', function () {
    $fixture = dispatchBackedInvoiceFixture();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.customer-purchase-orders.invoice', 'web');
    $fixture['user']->givePermissionTo('admin.customer-purchase-orders.invoice');

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.electronic-invoices.customer-purchase-order', $fixture['order']))
        ->assertOk()
        ->assertJsonPath('data.id', $fixture['order']->id)
        ->assertJsonPath('data.dispatch_backed', true)
        ->assertJsonPath('data.warehouse_context.mode', 'single')
        ->assertJsonPath('data.warehouse_context.warehouse_id', $fixture['warehouseId'])
        ->assertJsonPath('data.warehouse_context.label', 'ALM-FAC | ALMACEN FACTURACION')
        ->assertJsonPath('data.items.0.quantity', 10)
        ->assertJsonPath('data.items.0.unit_code', 'NIU')
        ->assertJsonPath('data.items.0.tax_affectation_code', '10')
        ->assertJsonPath('data.items.0.warehouse_id', $fixture['warehouseId'])
        ->assertJsonPath('data.items.0.lot_number', '14545454')
        ->assertJsonPath('data.items.0.expiration_date', '2030-02-05')
        ->assertJsonPath('data.items.1.quantity', 20)
        ->assertJsonPath('data.items.1.tax_affectation_code', '10')
        ->assertJsonPath('data.items.1.warehouse_id', $fixture['warehouseId'])
        ->assertJsonPath('data.items.1.lot_number', '5445445')
        ->assertJsonPath('data.items.1.expiration_date', '2030-06-20');
});

it('ignora el código recibido y guarda el snapshot SUNAT configurado en la unidad del artículo', function () {
    $fixture = dispatchBackedInvoiceFixture();
    $items = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'];
    $items[0]['unit_code'] = 'KGM';

    $invoice = generateDispatchBackedInvoice($fixture, $items);

    expect($invoice->items()->orderBy('item_number')->value('unit_code'))->toBe('NIU')
        ->and($invoice->items()->orderBy('item_number')->value('unit_code'))->not->toBe('UND');
});

it('bloquea una factura nueva sin equivalencia SUNAT y no crea comprobante ni Kardex', function () {
    $fixture = dispatchBackedInvoiceFixture();
    DB::table('units')->where('id', $fixture['unitId'])->update(['sunat_unit_item_id' => null]);
    $items = [[
        'warehouse_dispatch_item_id' => $fixture['dispatchItems']->first()->id,
        'customer_purchase_order_item_id' => $fixture['orderItems']->first()->id,
        'article_id' => $fixture['orderItems']->first()->article_id,
        'product_code' => 'ART-FAC-1', 'description' => 'ARTICULO FACTURABLE 1',
        'unit_code' => 'NIU', 'quantity' => 1, 'unit_price' => 10, 'tax_affectation_code' => '10',
    ]];

    expect(fn () => app(ElectronicInvoiceController::class)->store(
        Request::create('/electronic-invoices', 'POST', dispatchBackedInvoicePayload($fixture, $items))
    ))->toThrow(ValidationException::class, 'La unidad del artículo no tiene configurado un código de unidad SUNAT.');

    expect(ElectronicInvoice::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('combina productos despachados con servicios comerciales en una factura mixta', function () {
    $fixture = dispatchBackedInvoiceFixture();
    $catalog06Id = DB::table('sunat_catalogs')->where('code', '06')->value('id');
    $zzId = DB::table('sunat_catalog_items')->insertGetId([
        'sunat_catalog_id' => $catalog06Id, 'catalog_code' => '06', 'item_code' => 'ZZ',
        'description' => 'UNIDAD (SERVICIOS)', 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $serviceUnitId = DB::table('units')->insertGetId([
        'abbreviation' => 'SERV', 'description' => 'SERVICIO', 'sunat_unit_item_id' => $zzId,
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $serviceArticleId = DB::table('articles')->insertGetId([
        'code' => 'SERV-FAC-MIX', 'category_id' => $fixture['orderItems']->first()->article->category_id,
        'unit_id' => $serviceUnitId, 'legal_name' => 'SERVICIO FACTURABLE',
        'billing_name' => 'SERVICIO FACTURABLE', 'item_kind' => 'service',
        'is_inventory_item' => false, 'sales_tax_affectation_code' => '10', 'is_taxable' => true, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $serviceItem = $fixture['order']->items()->create([
        'article_id' => $serviceArticleId, 'article_code' => 'SERV-FAC-MIX',
        'billing_name_snapshot' => 'SERVICIO FACTURABLE', 'unit_id' => $serviceUnitId,
        'quantity' => 1, 'unit_price' => 50, 'line_total' => 50, 'status' => 'active',
    ]);
    $invoiceService = app(InvoiceFromCustomerOrderService::class);
    $prepared = $invoiceService->prepare($fixture['order']->fresh());
    $serviceRow = collect($prepared['items'])->firstWhere('customer_purchase_order_item_id', $serviceItem->id);

    expect($prepared['dispatch_backed'])->toBeTrue()
        ->and($prepared['items'])->toHaveCount(3)
        ->and($serviceRow['warehouse_dispatch_item_id'])->toBeNull()
        ->and($serviceRow['unit_code'])->toBe('ZZ')
        ->and($serviceRow['quantity'])->toBe(1.0);

    $invoiceService->validateGeneratedInvoice($fixture['order'], $prepared['items']);
    expect(ElectronicInvoiceItemDispatchAllocation::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe(0);

    $fixture['order']->update(['grand_total' => 250]);
    $invoice = generateDispatchBackedInvoice($fixture, $prepared['items']);
    expect($invoice->items()->where('article_id', $serviceArticleId)->value('unit_code'))->toBe('ZZ')
        ->and(ElectronicInvoiceItemDispatchAllocation::count())->toBe(2)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('factura un producto no inventariable con el mapping explícito de su unidad', function () {
    $fixture = dispatchBackedInvoiceFixture();
    $articleId = DB::table('articles')->insertGetId([
        'code' => 'PROD-NOINV', 'category_id' => $fixture['orderItems']->first()->article->category_id,
        'unit_id' => $fixture['unitId'], 'legal_name' => 'PRODUCTO NO INVENTARIABLE',
        'billing_name' => 'PRODUCTO NO INVENTARIABLE', 'item_kind' => 'product',
        'is_inventory_item' => false, 'sales_tax_affectation_code' => '10', 'is_taxable' => true, 'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $fixture['order']->items()->create([
        'article_id' => $articleId, 'article_code' => 'PROD-NOINV',
        'billing_name_snapshot' => 'PRODUCTO NO INVENTARIABLE', 'unit_id' => $fixture['unitId'],
        'quantity' => 1, 'unit_price' => 25, 'line_total' => 25, 'status' => 'active',
    ]);
    $fixture['order']->update(['grand_total' => 225]);
    $items = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'];
    $invoice = generateDispatchBackedInvoice($fixture, $items);

    expect($invoice->items()->where('article_id', $articleId)->value('unit_code'))->toBe('NIU')
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('genera la factura P00004 simulada con stock cero sin duplicar la salida física', function () {
    $fixture = dispatchBackedInvoiceFixture();
    $stockBefore = $fixture['stocks']->map(fn ($stock) => $stock->fresh()->getAttributes())->all();
    $dispatchBefore = $fixture['dispatch']->fresh()->getAttributes();
    $dispatchItemsBefore = $fixture['dispatchItems']->map(fn ($item) => $item->fresh()->getAttributes())->all();
    $kardexCount = WarehouseKardexMovement::count();
    $dispatchCount = WarehouseDispatch::count();
    $warehouseAllocations = WarehouseEntryItemAllocation::count();

    $invoice = generateDispatchBackedInvoice($fixture);

    expect((float) $invoice->total_amount)->toBe(200.0)
        ->and((float) $invoice->taxable_amount)->toBe(169.4915254236)
        ->and((float) $invoice->igv_amount)->toBe(30.5084745764)
        ->and($invoice->items()->orderBy('item_number')->pluck('tax_affectation_code')->all())->toBe(['10', '10'])
        ->and((float) $invoice->pending_amount)->toBe(200.0)
        ->and($invoice->payment_status)->toBe('pending')
        ->and($invoice->warehouse_id)->toBeNull()
        ->and($invoice->stock_moved_at)->toBeNull()
        ->and($fixture['stocks']->map(fn ($stock) => $stock->fresh()->getAttributes())->all())->toBe($stockBefore)
        ->and(WarehouseKardexMovement::count())->toBe($kardexCount)
        ->and(WarehouseDispatch::count())->toBe($dispatchCount)
        ->and($fixture['dispatch']->fresh()->getAttributes())->toBe($dispatchBefore)
        ->and($fixture['dispatchItems']->map(fn ($item) => $item->fresh()->getAttributes())->all())->toBe($dispatchItemsBefore)
        ->and(WarehouseEntryItemAllocation::count())->toBe($warehouseAllocations)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED)
        ->and(ElectronicInvoiceItemDispatchAllocation::count())->toBe(2)
        ->and(ElectronicInvoiceItemDispatchAllocation::orderBy('id')->pluck('quantity')->map(fn ($value) => (float) $value)->all())->toBe([10.0, 20.0])
        ->and($invoice->items()->orderBy('item_number')->pluck('lot_number')->all())->toBe(['14545454', '5445445'])
        ->and($invoice->items()->orderBy('item_number')->pluck('expiration_date')->map(fn ($date) => substr((string) $date, 0, 10))->all())->toBe(['2030-02-05', '2030-06-20'])
        ->and(InvoiceCollection::count())->toBe(0)
        ->and(BankMovement::count())->toBe(0);
});

it('factura un despacho confirmado por el servicio sin generar otra salida Kardex', function () {
    $fixture = dispatchBackedInvoiceFixture([[
        'ordered' => 10,
        'dispatched' => 10,
        'unit_price' => 10,
        'lot' => 'LOTE-KDX-SAL',
        'expiration' => '2030-02-05',
        'requested_expiration' => '2030-02-05',
        'origin' => 'PERU',
    ]]);
    $fixture['dispatch']->items()->delete();
    $fixture['dispatch']->delete();
    $fixture['order']->update(['status' => CustomerPurchaseOrder::STATUS_ENTERED]);
    $stock = $fixture['stocks'][0];
    $stock->update([
        'current_quantity' => 10,
        'average_unit_cost' => 5,
        'total_cost' => 50,
    ]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $fixture['company']->id,
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['orderItems'][0]->article_id,
        'current_quantity' => 10,
        'average_unit_cost' => 5,
        'total_cost' => 50,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20999999992',
        'business_name' => 'PROVEEDOR KARDEX DESPACHO',
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-KDX-SAL',
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $fixture['warehouseId'],
        'company_id' => $fixture['company']->id,
        'supplier_id' => $supplierId,
        'currency_id' => $fixture['currency']->id,
        'status' => 'registered',
        'created_by' => $fixture['user']->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $entryItemId = DB::table('warehouse_entry_items')->insertGetId([
        'warehouse_entry_id' => $entryId,
        'article_id' => $fixture['orderItems'][0]->article_id,
        'billing_name_snapshot' => $fixture['orderItems'][0]->billing_name_snapshot,
        'unit_id' => $fixture['unitId'],
        'quantity' => 10,
        'unit_price' => 5,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('warehouse_entry_item_allocations')->insert([
        'warehouse_entry_id' => $entryId,
        'warehouse_entry_item_id' => $entryItemId,
        'customer_purchase_order_id' => $fixture['order']->id,
        'customer_purchase_order_item_id' => $fixture['orderItems'][0]->id,
        'article_id' => $fixture['orderItems'][0]->article_id,
        'quantity_allocated' => 10,
        'unit_cost' => 5,
        'total_cost' => 50,
        'allocation_type' => 'customer_order',
        'status' => 'active',
        'created_by' => $fixture['user']->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $dispatchService = app(WarehouseDispatchService::class);
    $dispatch = $dispatchService->createDraft($fixture['order']->fresh(), [
        'warehouse_id' => $fixture['warehouseId'],
        'dispatch_date' => '2026-09-01 10:00:00',
        'responsible_user_id' => $fixture['user']->id,
        'document_type' => 'GUÍA',
        'document_number' => 'GR-KDX-SAL',
        'items' => [[
            'customer_purchase_order_item_id' => $fixture['orderItems'][0]->id,
            'warehouse_stock_id' => $stock->id,
            'quantity' => 10,
        ]],
    ]);

    expect(WarehouseKardexMovement::count())->toBe(0);

    $dispatch = $dispatchService->confirm($dispatch);
    $dispatchItem = $dispatch->items()->firstOrFail();
    $dispatchMovement = $dispatchItem->kardexMovement()->firstOrFail();
    $kardexAfterDispatch = WarehouseKardexMovement::count();
    $stockAfterDispatch = $stock->fresh()->only([
        'current_quantity', 'average_unit_cost', 'total_cost',
    ]);
    $poolAfterDispatch = $pool->fresh()->only([
        'current_quantity', 'average_unit_cost', 'total_cost',
    ]);

    expect($kardexAfterDispatch)->toBe(1)
        ->and($dispatchMovement->source_type)->toBe(WarehouseDispatch::class)
        ->and($dispatchMovement->source_id)->toBe($dispatch->id)
        ->and($dispatchMovement->source_item_id)->toBe($dispatchItem->id)
        ->and((float) $stock->fresh()->current_quantity)->toBe(0.0);

    $invoice = generateDispatchBackedInvoice($fixture);

    expect($invoice->stock_moved_at)->toBeNull()
        ->and(WarehouseKardexMovement::count())->toBe($kardexAfterDispatch)
        ->and(WarehouseKardexMovement::where('source_type', ElectronicInvoice::class)
            ->where('source_id', $invoice->id)->count())->toBe(0)
        ->and($stock->fresh()->only([
            'current_quantity', 'average_unit_cost', 'total_cost',
        ]))->toBe($stockAfterDispatch)
        ->and($pool->fresh()->only([
            'current_quantity', 'average_unit_cost', 'total_cost',
        ]))->toBe($poolAfterDispatch)
        ->and(ElectronicInvoiceItemDispatchAllocation::where(
            'warehouse_dispatch_item_id',
            $dispatchItem->id
        )->count())->toBe(1);
});

it('permite editar draft y consume el correlativo solo al convertirlo en generated', function () {
    $fixture = dispatchBackedInvoiceFixture();
    $draftPayload = dispatchBackedInvoicePayload($fixture);
    $draftPayload['requested_status'] = 'draft';

    $created = app(ElectronicInvoiceController::class)->store(
        Request::create('/electronic-invoices', 'POST', $draftPayload)
    );
    $draft = ElectronicInvoice::query()->latest('id')->firstOrFail();

    expect($created->getStatusCode())->toBe(201)
        ->and($draft->status)->toBe('draft')
        ->and($draft->isEditable())->toBeTrue()
        ->and($fixture['series']->fresh()->current_number)->toBe(0)
        ->and($fixture['series']->fresh()->next_number)->toBe(1)
        ->and(ElectronicInvoiceItemDispatchAllocation::count())->toBe(0);

    $draftPayload['observations'] = 'BORRADOR EDITADO';
    $updated = app(ElectronicInvoiceController::class)->update(
        Request::create('/electronic-invoices/'.$draft->id, 'PUT', $draftPayload),
        $draft->fresh()
    );

    expect($updated->getStatusCode())->toBe(200)
        ->and($draft->fresh()->status)->toBe('draft')
        ->and($draft->fresh()->observations)->toBe('BORRADOR EDITADO')
        ->and($fixture['series']->fresh()->current_number)->toBe(0)
        ->and($fixture['series']->fresh()->next_number)->toBe(1);

    $generatedPayload = dispatchBackedInvoicePayload($fixture);
    $generated = app(ElectronicInvoiceController::class)->update(
        Request::create('/electronic-invoices/'.$draft->id, 'PUT', $generatedPayload),
        $draft->fresh()
    );

    expect($generated->getStatusCode())->toBe(200)
        ->and($draft->fresh()->status)->toBe('generated')
        ->and($draft->fresh()->isEditable())->toBeFalse()
        ->and($draft->fresh()->full_number)->toBe('F001-00000001')
        ->and($fixture['series']->fresh()->current_number)->toBe(1)
        ->and($fixture['series']->fresh()->next_number)->toBe(2)
        ->and(ElectronicInvoiceItemDispatchAllocation::count())->toBe(2);
});

it('muestra edición únicamente para draft en el menú de acciones', function () {
    $fixture = dispatchBackedInvoiceFixture();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.electronic-invoices.update', 'web');
    $fixture['user']->givePermissionTo('admin.electronic-invoices.update');
    $invoice = generateDispatchBackedInvoice($fixture);

    $invoice->update(['status' => 'draft']);
    $draftActions = view('admin.electronic-invoices.partials.acciones', [
        'invoice' => $invoice->fresh(),
        'apiReady' => false,
    ])->render();
    $invoice->update(['status' => 'generated']);
    $generatedActions = view('admin.electronic-invoices.partials.acciones', [
        'invoice' => $invoice->fresh(),
        'apiReady' => false,
    ])->render();
    $invoice->update(['status' => 'cancelled']);
    $cancelledActions = view('admin.electronic-invoices.partials.acciones', [
        'invoice' => $invoice->fresh(),
        'apiReady' => false,
    ])->render();

    expect($draftActions)->toContain('Editar borrador')
        ->and(substr_count($draftActions, 'editElectronicInvoice'))->toBe(1)
        ->and($generatedActions)->not->toContain('editElectronicInvoice')
        ->not->toContain('Editar comprobante')
        ->and($cancelledActions)->not->toContain('editElectronicInvoice')
        ->not->toContain('Editar comprobante');
});

it('rechaza por HTTP editar o actualizar comprobantes generated y cancelled', function () {
    $fixture = dispatchBackedInvoiceFixture();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.electronic-invoices.update', 'web');
    $fixture['user']->givePermissionTo('admin.electronic-invoices.update');
    $invoice = generateDispatchBackedInvoice($fixture);
    $payload = dispatchBackedInvoicePayload($fixture);

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.electronic-invoices.edit', $invoice))
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
    $this->actingAs($fixture['user'])
        ->putJson(route('admin.electronic-invoices.update', $invoice), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('status')
        ->assertJsonPath('errors.status.0', 'El comprobante generado ya no puede editarse. Si necesita corregirlo, debe cancelarlo y emitir un nuevo comprobante.');

    cancelDispatchBackedInvoice($invoice);

    $this->actingAs($fixture['user'])
        ->putJson(route('admin.electronic-invoices.update', $invoice), $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('status');
});

it('calcula facturación parcial y acumula un segundo despacho sin reutilizar cantidades', function () {
    $fixture = dispatchBackedInvoiceFixture([[
        'ordered' => 100,
        'dispatched' => 40,
        'unit_price' => 1,
        'lot' => 'LOTE-A',
        'expiration' => '2030-01-01',
        'requested_expiration' => '2028-01-01',
        'origin' => 'PERU',
    ]]);
    $firstItems = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'];
    $firstItems[0]['quantity'] = 25;
    generateDispatchBackedInvoice($fixture, $firstItems);

    $afterFirst = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh());
    expect((float) $afterFirst['items'][0]['pending_quantity'])->toBe(15.0);

    $secondDispatch = WarehouseDispatch::create([
        'company_id' => $fixture['company']->id,
        'dispatch_number' => 'SAL-FAC-002',
        'idempotency_key' => 'dispatch-backed-fixture-2',
        'customer_purchase_order_id' => $fixture['order']->id,
        'warehouse_id' => $fixture['warehouseId'],
        'dispatch_date' => now()->addMinute(),
        'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER,
        'status' => WarehouseDispatch::STATUS_CONFIRMED,
        'confirmed_at' => now(),
        'confirmed_by' => $fixture['user']->id,
        'created_by' => $fixture['user']->id,
        'updated_by' => $fixture['user']->id,
    ]);
    $secondStock = WarehouseStock::create([
        'company_id' => $fixture['company']->id,
        'stock_key' => "{$fixture['company']->id}|{$fixture['warehouseId']}|{$fixture['orderItems'][0]->article_id}|LOTE-B|2031-02-02",
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['orderItems'][0]->article_id,
        'unit_id' => $fixture['unitId'],
        'lot_number' => 'LOTE-B',
        'expiration_date' => '2031-02-02',
        'origin' => 'PERU',
        'current_quantity' => 0,
        'reserved_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
        'status' => 'ACTIVE',
    ]);
    WarehouseDispatchItem::create([
        'warehouse_dispatch_id' => $secondDispatch->id,
        'customer_purchase_order_item_id' => $fixture['orderItems'][0]->id,
        'warehouse_stock_id' => $secondStock->id,
        'article_id' => $fixture['orderItems'][0]->article_id,
        'unit_id' => $fixture['unitId'],
        'lot_number' => 'LOTE-B',
        'expiration_date' => '2031-02-02',
        'quantity' => 30,
        'unit_cost' => 2,
        'total_cost' => 60,
        'status' => WarehouseDispatchItem::STATUS_CONFIRMED,
    ]);

    $afterSecond = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh());
    expect(collect($afterSecond['items'])->sum('pending_quantity'))->toBe(45.0)
        ->and($afterSecond['warehouse_context']['mode'])->toBe('single')
        ->and($afterSecond['warehouse_context']['warehouse_id'])->toBe($fixture['warehouseId'])
        ->and(collect($afterSecond['items'])->pluck('pending_quantity')->map(fn ($value) => (float) $value)->all())->toBe([15.0, 30.0])
        ->and(collect($afterSecond['items'])->pluck('lot_number')->all())->toBe(['LOTE-A', 'LOTE-B']);

    generateDispatchBackedInvoice($fixture, $afterSecond['items']);

    expect(app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'])->toBe([])
        ->and((float) ElectronicInvoiceItemDispatchAllocation::query()->sum('quantity'))->toBe(70.0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('representa varios almacenes sin inventar una cabecera y conserva la trazabilidad por despacho', function () {
    $fixture = dispatchBackedInvoiceFixture();
    $otherWarehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'ALM-FAC-2',
        'name' => 'ALMACEN FACTURACION 2',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $fixture['company']->id,
        'warehouse_id' => $otherWarehouseId,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherDispatch = WarehouseDispatch::create([
        'company_id' => $fixture['company']->id,
        'dispatch_number' => 'SAL-FAC-002',
        'idempotency_key' => 'dispatch-backed-other-warehouse',
        'customer_purchase_order_id' => $fixture['order']->id,
        'warehouse_id' => $otherWarehouseId,
        'dispatch_date' => now()->addMinute(),
        'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER,
        'status' => WarehouseDispatch::STATUS_CONFIRMED,
        'confirmed_at' => now(),
        'confirmed_by' => $fixture['user']->id,
        'created_by' => $fixture['user']->id,
        'updated_by' => $fixture['user']->id,
    ]);
    $secondOrderItem = $fixture['orderItems'][1];
    $otherStock = WarehouseStock::create([
        'company_id' => $fixture['company']->id,
        'stock_key' => "{$fixture['company']->id}|{$otherWarehouseId}|{$secondOrderItem->article_id}|5445445|2030-06-20",
        'warehouse_id' => $otherWarehouseId,
        'article_id' => $secondOrderItem->article_id,
        'unit_id' => $fixture['unitId'],
        'lot_number' => '5445445',
        'expiration_date' => '2030-06-20',
        'origin' => 'JAPON',
        'current_quantity' => 0,
        'reserved_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
        'status' => 'ACTIVE',
    ]);
    $fixture['dispatchItems'][1]->update([
        'warehouse_dispatch_id' => $otherDispatch->id,
        'warehouse_stock_id' => $otherStock->id,
    ]);

    $prepared = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh());
    expect($prepared['warehouse_context'])->toMatchArray([
        'mode' => 'multiple',
        'warehouse_id' => null,
        'label' => 'Múltiples almacenes — según despachos',
    ])->and(collect($prepared['items'])->pluck('warehouse_id')->sort()->values()->all())
        ->toBe([$fixture['warehouseId'], $otherWarehouseId]);

    $kardexCount = WarehouseKardexMovement::count();
    $dispatchCount = WarehouseDispatch::count();
    $invoice = generateDispatchBackedInvoice($fixture, $prepared['items']);
    $allocatedWarehouseIds = ElectronicInvoiceItemDispatchAllocation::query()
        ->join('warehouse_dispatch_items', 'warehouse_dispatch_items.id', '=', 'electronic_invoice_item_dispatch_allocations.warehouse_dispatch_item_id')
        ->join('warehouse_dispatches', 'warehouse_dispatches.id', '=', 'warehouse_dispatch_items.warehouse_dispatch_id')
        ->distinct()
        ->orderBy('warehouse_dispatches.warehouse_id')
        ->pluck('warehouse_dispatches.warehouse_id')
        ->map(fn ($id) => (int) $id)
        ->all();

    expect($invoice->warehouse_id)->toBeNull()
        ->and($allocatedWarehouseIds)->toBe([$fixture['warehouseId'], $otherWarehouseId])
        ->and(WarehouseDispatch::count())->toBe($dispatchCount)
        ->and(WarehouseKardexMovement::count())->toBe($kardexCount);
});

it('bloquea sobrefacturar o consumir dos veces un detalle de despacho', function () {
    $fixture = dispatchBackedInvoiceFixture([[
        'ordered' => 40,
        'dispatched' => 40,
        'unit_price' => 1,
        'lot' => 'LOTE-40',
        'expiration' => '2030-01-01',
        'requested_expiration' => '2028-01-01',
        'origin' => 'PERU',
    ]]);
    $items = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'];
    $items[0]['quantity'] = 25;
    generateDispatchBackedInvoice($fixture, $items);
    $remaining = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'];
    $remaining[0]['quantity'] = 16;

    expect(fn () => generateDispatchBackedInvoice($fixture, $remaining))
        ->toThrow(ValidationException::class, 'supera el saldo físicamente despachado (15)');

    expect(ElectronicInvoice::count())->toBe(1)
        ->and((float) ElectronicInvoiceItemDispatchAllocation::sum('quantity'))->toBe(25.0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('mantiene generated inmutable y su cancelación libera solo asignaciones comerciales', function () {
    $fixture = dispatchBackedInvoiceFixture([[
        'ordered' => 40,
        'dispatched' => 40,
        'unit_price' => 1,
        'lot' => 'LOTE-EDIT',
        'expiration' => '2030-01-01',
        'requested_expiration' => '2028-01-01',
        'origin' => 'PERU',
    ]]);
    $items = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'];
    $items[0]['quantity'] = 40;
    $invoice = generateDispatchBackedInvoice($fixture, $items);
    $stockBefore = $fixture['stocks'][0]->fresh()->getAttributes();
    $dispatchBefore = $fixture['dispatch']->fresh()->getAttributes();
    $kardexCount = WarehouseKardexMovement::count();
    $seriesAfterGeneration = $fixture['series']->fresh()->only(['current_number', 'next_number']);
    $invoiceBefore = $invoice->fresh()->getAttributes();
    $generatedView = app(ElectronicInvoiceController::class)->show(
        $invoice->fresh(),
        app(InvoiceFromCustomerOrderService::class)
    )->getData(true)['data'];
    $items[0]['quantity'] = 20;

    expect($generatedView['full_number'])->toBe('F001-00000001')
        ->and($generatedView['dispatch_context']['dispatch_label'])->toBe('SAL-FAC-001')
        ->and($generatedView['dispatch_context']['warehouse_label'])->toBe('ALM-FAC | ALMACEN FACTURACION')
        ->and($generatedView['items'][0]['lot_number'])->toBe('LOTE-EDIT')
        ->and(substr((string) $generatedView['items'][0]['expiration_date'], 0, 10))->toBe('2030-01-01')
        ->and($fixture['series']->fresh()->only(['current_number', 'next_number']))->toBe($seriesAfterGeneration)
        ->and($invoice->sunat_status)->toBe('not_configured')
        ->and(fn () => app(ElectronicInvoiceController::class)->update(
        Request::create('/electronic-invoices/'.$invoice->id, 'PUT', dispatchBackedInvoicePayload($fixture, $items)),
        $invoice->fresh()
    ))->toThrow(ValidationException::class, 'El comprobante generado ya no puede editarse')
        ->and(fn () => app(ElectronicInvoiceController::class)->edit($invoice->fresh()))
        ->toThrow(ValidationException::class, 'El comprobante generado ya no puede editarse')
        ->and($invoice->fresh()->getAttributes())->toBe($invoiceBefore)
        ->and((float) ElectronicInvoiceItemDispatchAllocation::sum('quantity'))->toBe(40.0)
        ->and($fixture['series']->fresh()->only(['current_number', 'next_number']))->toBe($seriesAfterGeneration)
        ->and($fixture['stocks'][0]->fresh()->getAttributes())->toBe($stockBefore)
        ->and(WarehouseKardexMovement::count())->toBe($kardexCount);

    expect(fn () => app(ElectronicInvoiceController::class)->destroy(
        Request::create('/electronic-invoices/'.$invoice->id, 'DELETE'),
        $invoice->fresh(),
        app(WarehouseKardexService::class),
        app(InvoiceFromCustomerOrderService::class)
    ))->toThrow(ValidationException::class, 'Debe ingresar el motivo de cancelación');

    $cancelledResponse = cancelDispatchBackedInvoice($invoice);
    $cancelled = ElectronicInvoice::query()->findOrFail($invoice->id);
    $cancellationHistory = $cancelled->statusHistories()->latest('id')->firstOrFail();

    expect($cancelledResponse->getStatusCode())->toBe(200)
        ->and($cancelled->status)->toBe('cancelled')
        ->and($cancelled->full_number)->toBe('F001-00000001')
        ->and($cancelled->voided_reason)->toBe('Corrección comercial de prueba')
        ->and($cancelled->voided_at)->not->toBeNull()
        ->and($cancelled->trashed())->toBeFalse()
        ->and($cancelled->items()->count())->toBe(1)
        ->and($cancelled->pdf_path)->not->toBeNull()
        ->and($cancellationHistory->changed_by)->toBe($fixture['user']->id)
        ->and($cancellationHistory->description)->toContain('Corrección comercial de prueba')
        ->and(ElectronicInvoiceItemDispatchAllocation::count())->toBe(0)
        ->and(ElectronicInvoiceItemDispatchAllocation::withTrashed()->count())->toBe(1)
        ->and(app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'][0]['pending_quantity'])->toBe(40.0)
        ->and($fixture['series']->fresh()->only(['current_number', 'next_number']))->toBe($seriesAfterGeneration)
        ->and($fixture['stocks'][0]->fresh()->getAttributes())->toBe($stockBefore)
        ->and(WarehouseKardexMovement::count())->toBe($kardexCount)
        ->and($fixture['dispatch']->fresh()->getAttributes())->toBe($dispatchBefore);

    expect(fn () => app(ElectronicInvoiceController::class)->update(
        Request::create('/electronic-invoices/'.$cancelled->id, 'PUT', dispatchBackedInvoicePayload($fixture, $items)),
        $cancelled->fresh()
    ))->toThrow(ValidationException::class, 'El comprobante generado ya no puede editarse');

    $viewResponse = app(ElectronicInvoiceController::class)->show(
        $cancelled->fresh(),
        app(InvoiceFromCustomerOrderService::class)
    );
    $viewData = $viewResponse->getData(true)['data'];
    expect($viewData['full_number'])->toBe('F001-00000001')
        ->and($viewData['dispatch_context']['dispatch_backed'])->toBeTrue()
        ->and($viewData['dispatch_context']['active_allocations'])->toBe(0)
        ->and($viewData['dispatch_context']['historical_allocations'])->toBe(1)
        ->and($viewData['dispatch_context']['dispatch_label'])->toBe('SAL-FAC-001')
        ->and($viewData['dispatch_context']['warehouse_label'])->toBe('ALM-FAC | ALMACEN FACTURACION');

    $replacementItems = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh())['items'];
    $replacement = generateDispatchBackedInvoice($fixture, $replacementItems);

    expect($replacement->full_number)->toBe('F001-00000002')
        ->and($fixture['series']->fresh()->current_number)->toBe(2)
        ->and($fixture['series']->fresh()->next_number)->toBe(3)
        ->and(ElectronicInvoice::query()->count())->toBe(2)
        ->and(ElectronicInvoice::query()->findOrFail($invoice->id)->status)->toBe('cancelled')
        ->and(ElectronicInvoiceItemDispatchAllocation::count())->toBe(1)
        ->and(ElectronicInvoiceItemDispatchAllocation::withTrashed()->count())->toBe(2)
        ->and($fixture['stocks'][0]->fresh()->getAttributes())->toBe($stockBefore)
        ->and(WarehouseKardexMovement::count())->toBe($kardexCount)
        ->and($fixture['dispatch']->fresh()->getAttributes())->toBe($dispatchBefore);
});

it('exige configuración local internal con un mensaje específico', function () {
    $fixture = dispatchBackedInvoiceFixture([], false, false);

    expect(fn () => app(ElectronicInvoiceController::class)->store(
        Request::create('/electronic-invoices', 'POST', dispatchBackedInvoicePayload($fixture))
    ))->toThrow(ValidationException::class, 'Configuración local requerida');

    expect(ElectronicInvoice::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('exige una serie internal con un mensaje local específico', function () {
    $fixture = dispatchBackedInvoiceFixture([], true, false);

    expect(fn () => app(ElectronicInvoiceController::class)->store(
        Request::create('/electronic-invoices', 'POST', dispatchBackedInvoicePayload($fixture))
    ))->toThrow(ValidationException::class, 'Serie local requerida');

    expect(ElectronicInvoice::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('mantiene la salida legacy para una factura sin despacho confirmado', function () {
    $fixture = dispatchBackedInvoiceFixture([[
        'ordered' => 10,
        'dispatched' => 10,
        'unit_price' => 1,
        'lot' => 'LOTE-LEGACY',
        'expiration' => '2030-01-01',
        'requested_expiration' => '2030-01-01',
        'origin' => 'PERU',
    ]]);
    $fixture['dispatch']->items()->delete();
    $fixture['dispatch']->delete();
    $fixture['stocks'][0]->update([
        'current_quantity' => 10,
        'average_unit_cost' => 2,
        'total_cost' => 20,
    ]);
    WarehouseValuationPool::create([
        'company_id' => $fixture['company']->id,
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['orderItems'][0]->article_id,
        'current_quantity' => 10,
        'average_unit_cost' => 2,
        'total_cost' => 20,
    ]);
    $prepared = app(InvoiceFromCustomerOrderService::class)->prepare($fixture['order']->fresh());
    $availableWarehouses = app(ElectronicInvoiceFormDataService::class)->get()['warehouses'];
    expect($prepared['dispatch_backed'])->toBeFalse()
        ->and($prepared['warehouse_context'])->toBeNull()
        ->and($availableWarehouses->pluck('id'))->toContain($fixture['warehouseId']);
    $payload = dispatchBackedInvoicePayload($fixture);
    $payload['warehouse_id'] = $fixture['warehouseId'];

    $response = app(ElectronicInvoiceController::class)->store(
        Request::create('/electronic-invoices', 'POST', $payload)
    );

    $invoice = ElectronicInvoice::query()->latest('id')->firstOrFail();
    expect($response->getStatusCode())->toBe(201)
        ->and((float) $fixture['stocks'][0]->fresh()->current_quantity)->toBe(0.0)
        ->and(WarehouseKardexMovement::where('source_type', ElectronicInvoice::class)->count())->toBe(1)
        ->and($invoice->stock_moved_at)->not->toBeNull()
        ->and(ElectronicInvoiceItemDispatchAllocation::count())->toBe(0);
});
