<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\Document;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItemDispatchAllocation;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseDispatchService;
use App\Services\InvoiceFromCustomerOrderService;
use App\Services\WarehouseKardexService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function warehouseDispatchFixture(float $quantity = 10, string $suffix = 'A'): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'DROPAIV ' . $suffix,
        'ruc' => '20' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE ' . $suffix,
        'document_type' => 'RUC',
        'document_number' => '20' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'P' . $suffix,
        'description' => 'SOLES ' . $suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $catalog06Id = DB::table('sunat_catalogs')->where('code', '06')->value('id');
    if (! $catalog06Id) {
        $catalog06Id = DB::table('sunat_catalogs')->insertGetId([
            'code' => '06', 'name' => 'UNIDAD DE MEDIDA', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $sunatUnitId = DB::table('sunat_catalog_items')->where('catalog_code', '06')->where('item_code', 'NIU')->value('id');
    if (! $sunatUnitId) {
        $sunatUnitId = DB::table('sunat_catalog_items')->insertGetId([
            'sunat_catalog_id' => $catalog06Id, 'catalog_code' => '06', 'item_code' => 'NIU',
            'description' => 'UNIDAD (BIENES)', 'status' => 'ACTIVE',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'U' . $suffix,
        'description' => 'UNIDAD ' . $suffix,
        'sunat_unit_item_id' => $sunatUnitId,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA ' . $suffix,
        'code' => 'CD' . $suffix,
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => 'AD' . $suffix,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO ' . $suffix,
        'billing_name' => 'ARTÍCULO ' . $suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields('AD' . $suffix),
        'has_batch' => false,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WD' . $suffix,
        'name' => 'ALMACÉN ' . $suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'sunat_establishment_code' => null,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR ' . $suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'PD-' . $suffix,
        'company_id' => $companyId,
        'customer_id' => $customerId,
        'order_type' => 'articles',
        'currency_id' => $currencyId,
        'status' => CustomerPurchaseOrder::STATUS_ENTERED,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $itemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $orderId,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO ' . $suffix,
        'unit_id' => $unitId,
        'quantity' => $quantity,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-D-' . $suffix,
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $warehouseId,
        'company_id' => $companyId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'status' => 'registered',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryItemId = DB::table('warehouse_entry_items')->insertGetId([
        'warehouse_entry_id' => $entryId,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO ' . $suffix,
        'unit_id' => $unitId,
        'quantity' => $quantity,
        'unit_price' => 10,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('warehouse_entry_item_allocations')->insert([
        'warehouse_entry_id' => $entryId,
        'warehouse_entry_item_id' => $entryItemId,
        'customer_purchase_order_id' => $orderId,
        'customer_purchase_order_item_id' => $itemId,
        'article_id' => $articleId,
        'quantity_allocated' => $quantity,
        'unit_cost' => 10,
        'total_cost' => $quantity * 10,
        'allocation_type' => 'customer_order',
        'status' => 'active',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stock = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|SIN_LOTE|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'current_quantity' => $quantity,
        'average_unit_cost' => 10,
        'total_cost' => $quantity * 10,
        'status' => 'ACTIVE',
    ]);

    $fixture = [
        'user' => $user,
        'order' => CustomerPurchaseOrder::findOrFail($orderId),
        'item_id' => $itemId,
        'stock' => $stock,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'company_id' => $companyId,
        'customer_id' => $customerId,
    ];
    syncWarehouseDispatchSeedValuationPools($fixture);

    return $fixture;
}

function syncWarehouseDispatchSeedValuationPools(array $fixture): void
{
    $stocksByArticle = WarehouseStock::query()
        ->where('company_id', $fixture['company_id'])
        ->where('warehouse_id', $fixture['warehouse_id'])
        ->get()
        ->groupBy('article_id');

    foreach ($stocksByArticle as $articleId => $stocks) {
        $hasKardex = WarehouseKardexMovement::query()
            ->where('company_id', $fixture['company_id'])
            ->where('warehouse_id', $fixture['warehouse_id'])
            ->where('article_id', $articleId)
            ->exists();

        if ($hasKardex) {
            continue;
        }

        $quantity = round($stocks->sum(fn (WarehouseStock $stock) => (float) $stock->current_quantity), 4);
        $totalCost = round($stocks->sum(fn (WarehouseStock $stock) => (float) $stock->total_cost), 2);
        $averageUnitCost = $quantity > 0 ? round($totalCost / $quantity, 6) : 0;

        WarehouseValuationPool::query()->updateOrCreate(
            [
                'company_id' => $fixture['company_id'],
                'warehouse_id' => $fixture['warehouse_id'],
                'article_id' => (int) $articleId,
            ],
            [
                'current_quantity' => $quantity,
                'average_unit_cost' => $averageUnitCost,
                'total_cost' => $totalCost,
            ]
        );
    }
}

function dispatchPayload(array $fixture, float $quantity): array
{
    syncWarehouseDispatchSeedValuationPools($fixture);
    return [
        'warehouse_id' => $fixture['warehouse_id'],
        'dispatch_date' => now(),
        'responsible_user_id' => $fixture['user']->id,
        'idempotency_key' => (string) Str::uuid(),
        'observation' => 'Despacho de prueba',
        'items' => [[
            'customer_purchase_order_item_id' => $fixture['item_id'],
            'warehouse_stock_id' => $fixture['stock']->id,
            'quantity' => $quantity,
        ]],
    ];
}

function additionalDispatchStock(array $fixture, float $quantity, string $suffix, ?string $lot = null): WarehouseStock
{
    $stock = WarehouseStock::create([
        'stock_key' => implode('|', [
            $fixture['company_id'],
            $fixture['warehouse_id'],
            $fixture['article_id'],
            $lot ?: 'SIN_LOTE_' . $suffix,
            $lot ? '2027-12-31' : 'SIN_FECHA_' . $suffix,
        ]),
        'company_id' => $fixture['company_id'],
        'warehouse_id' => $fixture['warehouse_id'],
        'article_id' => $fixture['article_id'],
        'unit_id' => $fixture['unit_id'],
        'lot_number' => $lot,
        'expiration_date' => $lot ? '2027-12-31' : null,
        'current_quantity' => $quantity,
        'average_unit_cost' => 10,
        'total_cost' => $quantity * 10,
        'status' => 'ACTIVE',
    ]);
    syncWarehouseDispatchSeedValuationPools($fixture);

    return $stock;
}

it('bloquea la confirmación de una salida sin Tabla 13 antes de modificar stock o Kardex', function () {
    $fixture = warehouseDispatchFixture(5, 'S13BLOCK');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchPayload($fixture, 2));
    DB::table('articles')->where('id', $fixture['article_id'])->update([
        'sunat_inventory_catalog_item_id' => null,
        'sunat_inventory_catalog_code' => null,
    ]);
    $stockBefore = $fixture['stock']->fresh()->getAttributes();
    $movementsBefore = WarehouseKardexMovement::count();

    expect(fn () => $service->confirm($draft))
        ->toThrow(ValidationException::class, 'El artículo no tiene configurado su catálogo y código de existencia SUNAT.');
    expect($fixture['stock']->fresh()->getAttributes())->toBe($stockBefore)
        ->and(WarehouseKardexMovement::count())->toBe($movementsBefore)
        ->and($draft->fresh()->isDraft())->toBeTrue();
});

test('rechaza servicio o producto no inventariable antes de crear una salida física', function (string $kind) {
    $fixture = warehouseDispatchFixture(5, $kind === 'service' ? 'NS' : 'NP');
    DB::table('articles')->where('id', $fixture['article_id'])->update([
        'item_kind' => $kind,
        'is_inventory_item' => false,
    ]);

    expect(fn () => app(WarehouseDispatchService::class)->createDraft(
        $fixture['order'],
        dispatchPayload($fixture, 1)
    ))->toThrow(ValidationException::class, 'no está clasificado como producto inventariable');

    expect(WarehouseDispatch::count())->toBe(0)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(5.0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
})->with([
    'servicio' => ['service'],
    'producto no inventariable' => ['product'],
]);

function directWarehouseDispatchDraft(array $fixture, array $items): WarehouseDispatch
{
    $dispatch = WarehouseDispatch::create([
        'company_id' => $fixture['company_id'],
        'dispatch_number' => 'SAL-T-' . Str::upper(Str::random(8)),
        'idempotency_key' => (string) Str::uuid(),
        'customer_purchase_order_id' => $fixture['order']->id,
        'warehouse_id' => $fixture['warehouse_id'],
        'dispatch_date' => now(),
        'responsible_user_id' => $fixture['user']->id,
        'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER,
        'status' => WarehouseDispatch::STATUS_DRAFT,
        'created_by' => $fixture['user']->id,
        'updated_by' => $fixture['user']->id,
    ]);

    foreach ($items as $item) {
        $stock = WarehouseStock::findOrFail($item['warehouse_stock_id']);
        WarehouseDispatchItem::create([
            'warehouse_dispatch_id' => $dispatch->id,
            'customer_purchase_order_item_id' => $item['customer_purchase_order_item_id'],
            'warehouse_stock_id' => $stock->id,
            'article_id' => $stock->article_id,
            'unit_id' => $stock->unit_id,
            'presentation_id' => $stock->presentation_id,
            'brand_id' => $stock->brand_id,
            'lot_number' => $stock->lot_number,
            'expiration_date' => $stock->expiration_date,
            'quantity' => $item['quantity'],
            'unit_cost' => $stock->average_unit_cost,
            'total_cost' => round((float) $item['quantity'] * (float) $stock->average_unit_cost, 2),
            'kardex_movement_id' => null,
            'status' => WarehouseDispatchItem::STATUS_DRAFT,
        ]);
    }

    return $dispatch->fresh('items');
}

function linkGeneratedInvoiceToWarehouseDispatch(
    array $fixture,
    WarehouseDispatch $dispatch,
    string $paymentStatus = 'pending'
): ElectronicInvoice {
    $number = Str::upper(Str::random(8));
    $paid = $paymentStatus === 'paid' ? 100 : 0;
    $invoice = ElectronicInvoice::create([
        'company_id' => $fixture['company_id'],
        'customer_id' => $fixture['customer_id'],
        'customer_purchase_order_id' => $fixture['order']->id,
        'currency_id' => $fixture['order']->currency_id,
        'document_type' => '01',
        'serie' => 'FREV',
        'correlativo' => $number,
        'full_number' => 'FREV-' . $number,
        'issue_date' => today(),
        'client_name' => 'CLIENTE REVERSA',
        'total_amount' => 100,
        'paid_amount' => $paid,
        'pending_amount' => 100 - $paid,
        'payment_status' => $paymentStatus,
        'status' => ElectronicInvoice::STATUS_GENERATED,
        'is_voided' => false,
        'created_by' => $fixture['user']->id,
        'updated_by' => $fixture['user']->id,
    ]);

    foreach ($dispatch->items()->orderBy('id')->get() as $index => $dispatchItem) {
        $invoiceItem = $invoice->items()->create([
            'customer_purchase_order_item_id' => $dispatchItem->customer_purchase_order_item_id,
            'article_id' => $dispatchItem->article_id,
            'item_number' => $index + 1,
            'product_code' => 'REV-' . $dispatchItem->article_id,
            'description' => 'ARTÍCULO REVERSA',
            'unit_code' => 'NIU',
            'quantity' => $dispatchItem->quantity,
            'unit_value' => 10,
            'unit_price' => 11.8,
            'status' => 'ACTIVE',
        ]);
        ElectronicInvoiceItemDispatchAllocation::create([
            'electronic_invoice_item_id' => $invoiceItem->id,
            'warehouse_dispatch_item_id' => $dispatchItem->id,
            'quantity' => $dispatchItem->quantity,
        ]);
    }

    return $invoice;
}

function grantWarehouseDispatchPermission(User $user): void
{
    Permission::findOrCreate('admin.customer-purchase-orders.dispatch', 'web');
    $user->givePermissionTo('admin.customer-purchase-orders.dispatch');
}

function createConfirmedWarehouseDispatch(
    WarehouseDispatchService $service,
    CustomerPurchaseOrder $order,
    array $payload
): WarehouseDispatch {
    return $service->confirm($service->createDraft($order, $payload));
}

it('genera Kardex de salida, descuenta stock y marca despacho parcial', function () {
    $fixture = warehouseDispatchFixture(10, 'P1');
    $dispatch = createConfirmedWarehouseDispatch(app(WarehouseDispatchService::class), $fixture['order'], dispatchPayload($fixture, 4));

    expect($dispatch->status)->toBe(WarehouseDispatch::STATUS_CONFIRMED)
        ->and($dispatch->company_id)->toBe($fixture['company_id'])
        ->and($dispatch->dispatch_type)->toBe(WarehouseDispatch::TYPE_CUSTOMER_ORDER)
        ->and($dispatch->confirmed_at)->not->toBeNull()
        ->and($dispatch->confirmed_by)->toBe($fixture['user']->id)
        ->and($dispatch->responsible_user_id)->toBe($fixture['user']->id)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_DISPATCHED)
        ->and(WarehouseKardexMovement::where('source_type', WarehouseDispatch::class)->where('movement_type', 'exit')->count())->toBe(1);
});

it('registra trazabilidad documental fecha y costo del stock en una salida parcial', function () {
    $fixture = warehouseDispatchFixture(10, 'KDX1');
    DB::table('customer_purchase_order_items')->where('id', $fixture['item_id'])->update([
        'unit_price' => 25,
        'subtotal' => 250,
        'line_total' => 250,
    ]);
    WarehouseStock::query()->whereKey($fixture['stock']->id)->update([
        'average_unit_cost' => 5,
        'total_cost' => 50,
    ]);
    $payload = dispatchPayload($fixture, 4);
    $payload['dispatch_date'] = '2026-09-01 17:33:00';
    $payload['document_type'] = 'GUÍA';
    $payload['document_number'] = 'GR-KDX-001';

    $dispatch = createConfirmedWarehouseDispatch(
        app(WarehouseDispatchService::class),
        $fixture['order']->fresh(),
        $payload
    );
    $detail = $dispatch->items()->firstOrFail();
    $movement = $detail->kardexMovement()->firstOrFail();
    $stock = $fixture['stock']->fresh();

    expect($movement->movement_type)->toBe('exit')
        ->and($movement->operation_type)->toBe('customer_order_dispatch')
        ->and($movement->source_type)->toBe(WarehouseDispatch::class)
        ->and($movement->source_id)->toBe($dispatch->id)
        ->and($movement->source_item_type)->toBe(WarehouseDispatchItem::class)
        ->and($movement->source_item_id)->toBe($detail->id)
        ->and($movement->warehouse_stock_id)->toBe($stock->id)
        ->and($movement->source_key)->toBe("customer-dispatch:{$dispatch->id}:{$detail->id}:{$stock->id}")
        ->and($movement->document_type)->toBe('GUÍA')
        ->and($movement->document_number)->toBe('GR-KDX-001')
        ->and($movement->document_date_snapshot?->format('Y-m-d'))->toBe('2026-09-01')
        ->and($movement->sunat_document_type_code_snapshot)->toBe('09')
        ->and($movement->movement_date->format('Y-m-d H:i:s'))->toBe('2026-09-01 17:33:00')
        ->and((float) $movement->quantity_in)->toBe(0.0)
        ->and((float) $movement->quantity_out)->toBe(4.0)
        ->and((float) $movement->unit_cost)->toBe(5.0)
        ->and((float) $movement->total_cost_out)->toBe(20.0)
        ->and((float) $movement->balance_quantity)->toBe(6.0)
        ->and((float) $movement->average_unit_cost)->toBe(5.0)
        ->and((float) $movement->balance_total_cost)->toBe(30.0)
        ->and((float) $detail->unit_cost)->toBe(5.0)
        ->and((float) $detail->total_cost)->toBe(20.0)
        ->and($detail->kardex_movement_id)->toBe($movement->id)
        ->and((float) $stock->current_quantity)->toBe(6.0)
        ->and((float) $stock->average_unit_cost)->toBe(5.0)
        ->and((float) $stock->total_cost)->toBe(30.0);
});

it('lleva cantidad costo promedio y valor a cero exacto en una salida total', function () {
    $fixture = warehouseDispatchFixture(10, 'KDX2');
    WarehouseStock::query()->whereKey($fixture['stock']->id)->update([
        'average_unit_cost' => 5,
        'total_cost' => 50,
    ]);

    $dispatch = createConfirmedWarehouseDispatch(
        app(WarehouseDispatchService::class),
        $fixture['order'],
        dispatchPayload($fixture, 10)
    );
    $movement = $dispatch->items()->firstOrFail()->kardexMovement()->firstOrFail();
    $stock = $fixture['stock']->fresh();

    expect((float) $movement->quantity_out)->toBe(10.0)
        ->and((float) $movement->unit_cost)->toBe(5.0)
        ->and((float) $movement->total_cost_out)->toBe(50.0)
        ->and((float) $movement->balance_quantity)->toBe(0.0)
        ->and((float) $movement->average_unit_cost)->toBe(0.0)
        ->and((float) $movement->balance_total_cost)->toBe(0.0)
        ->and((float) $stock->current_quantity)->toBe(0.0)
        ->and((float) $stock->average_unit_cost)->toBe(0.0)
        ->and((float) $stock->total_cost)->toBe(0.0);
});

it('marca atendida solo cuando toda la cantidad fue despachada', function () {
    $fixture = warehouseDispatchFixture(10, 'P2');
    $service = app(WarehouseDispatchService::class);
    createConfirmedWarehouseDispatch($service, $fixture['order'], dispatchPayload($fixture, 4));
    createConfirmedWarehouseDispatch($service, $fixture['order']->fresh(), dispatchPayload($fixture, 6));

    expect($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0);
});

it('rechaza cantidades mayores al pendiente de la OC o al stock disponible', function () {
    $fixture = warehouseDispatchFixture(10, 'P3');
    $service = app(WarehouseDispatchService::class);

    expect(fn() => $service->createDraft($fixture['order'], dispatchPayload($fixture, 11)))
        ->toThrow(ValidationException::class, 'supera el pendiente de la OC Cliente');

    $fixture['stock']->update(['current_quantity' => 3, 'total_cost' => 30]);
    expect(fn() => $service->createDraft($fixture['order']->fresh(), dispatchPayload($fixture, 4)))
        ->toThrow(ValidationException::class, 'supera el stock disponible del saldo seleccionado');

    expect(WarehouseDispatch::count())->toBe(0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0);
});

it('anula mediante reversa, restaura stock y recalcula la OC Cliente', function () {
    $fixture = warehouseDispatchFixture(10, 'P4');
    $service = app(WarehouseDispatchService::class);
    $dispatch = createConfirmedWarehouseDispatch($service, $fixture['order'], dispatchPayload($fixture, 10));
    $detail = $dispatch->items()->firstOrFail();
    $originalMovement = $detail->kardexMovement()->firstOrFail();
    $originalAttributes = $originalMovement->getAttributes();
    $document = $dispatch->documents()->create([
        'original_name' => 'sustento-salida.pdf',
        'stored_name' => 'sustento-salida.pdf',
        'file_path' => 'warehouse-dispatches/sustento-salida.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'file_size' => 100,
        'status' => 'ACTIVE',
        'created_by' => $fixture['user']->id,
    ]);
    $documentAttributes = $document->fresh()->getAttributes();
    $reversed = $service->reverse($dispatch, 'Error operativo de despacho');
    $reversal = WarehouseKardexMovement::where('movement_type', 'exit_reversal')->firstOrFail();

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(100.0)
        ->and($reversed->status)->toBe(WarehouseDispatch::STATUS_REVERSED)
        ->and($reversed->cancelled_at)->not->toBeNull()
        ->and($reversed->cancelled_by)->toBe($fixture['user']->id)
        ->and($reversed->cancellation_reason)->toBe('Error operativo de despacho')
        ->and($reversed->items->every(fn($item) => $item->status === WarehouseDispatchItem::STATUS_REVERSED))->toBeTrue()
        ->and($reversed->items->first()->kardex_movement_id)->toBe($originalMovement->id)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED)
        ->and(Document::findOrFail($document->id)->getAttributes())->toBe($documentAttributes)
        ->and(WarehouseKardexMovement::where('movement_type', 'exit_reversal')->count())->toBe(1)
        ->and($originalMovement->fresh()->getAttributes())->toBe($originalAttributes)
        ->and($reversal->operation_type)->toBe('customer_order_dispatch_cancel')
        ->and($reversal->source_type)->toBe(WarehouseDispatch::class)
        ->and($reversal->source_id)->toBe($dispatch->id)
        ->and($reversal->source_item_type)->toBe(WarehouseDispatchItem::class)
        ->and($reversal->source_item_id)->toBe($detail->id)
        ->and($reversal->source_key)->toBe("customer-dispatch-reversal:{$originalMovement->id}")
        ->and((float) $reversal->quantity_in)->toBe(10.0)
        ->and((float) $reversal->unit_cost)->toBe(10.0)
        ->and((float) $reversal->total_cost_in)->toBe(100.0)
        ->and((float) $reversal->balance_quantity)->toBe(10.0)
        ->and((float) $reversal->balance_total_cost)->toBe(100.0)
        ->and($reversal->observations)->toBe('Error operativo de despacho')
        ->and($reversal->created_by)->toBe($fixture['user']->id);
});

it('solo revierte confirmed exige motivo en el servicio y no duplica una segunda reversa', function () {
    $fixture = warehouseDispatchFixture(10, 'REV1');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchPayload($fixture, 4));

    expect(fn() => $service->reverse($draft, 'Motivo operativo valido'))
        ->toThrow(ValidationException::class, 'Solo una salida confirmada puede revertirse.');

    $confirmed = $service->confirm($draft);
    expect(fn() => $service->reverse($confirmed, '   '))
        ->toThrow(ValidationException::class, 'Ingrese un motivo de reversa de 5 a 2000 caracteres.');

    $service->reverse($confirmed, 'Motivo operativo valido');
    expect(fn() => $service->reverse($confirmed, 'Segundo intento de reversa'))
        ->toThrow(ValidationException::class, 'Solo una salida confirmada puede revertirse.');

    expect($confirmed->fresh()->status)->toBe(WarehouseDispatch::STATUS_REVERSED)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::where('movement_type', 'exit_reversal')->count())->toBe(1)
        ->and($service->canReverse($confirmed->fresh()))->toBeFalse();
});

it('restaura cada lote con el costo historico y promedia contra el valor nuevo del mismo stock', function () {
    $fixture = warehouseDispatchFixture(10, 'REV2');
    $fixture['stock']->update([
        'average_unit_cost' => 5,
        'total_cost' => 50,
    ]);
    $secondStock = additionalDispatchStock($fixture, 4, 'REV2B', 'LOTE-REV-B');
    $secondStock->update([
        'average_unit_cost' => 6,
        'total_cost' => 24,
    ]);
    $payload = dispatchPayload($fixture, 6);
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 4,
    ];
    $service = app(WarehouseDispatchService::class);
    $dispatch = createConfirmedWarehouseDispatch($service, $fixture['order'], $payload);
    $details = $dispatch->items()->with('kardexMovement')->orderBy('warehouse_stock_id')->get();
    $originalMovementIds = $details->pluck('kardex_movement_id')->all();
    $originalMovements = $details->mapWithKeys(
        fn(WarehouseDispatchItem $detail) => [$detail->id => $detail->kardexMovement->getAttributes()]
    );
    $firstHistoricalCost = (float) $details[0]->kardexMovement->total_cost_out;
    $secondHistoricalCost = (float) $details[1]->kardexMovement->total_cost_out;

    $fixture['stock']->update([
        'current_quantity' => 2,
        'average_unit_cost' => 10,
        'total_cost' => 20,
    ]);
    $secondStock->update([
        'current_quantity' => 3,
        'average_unit_cost' => 12,
        'total_cost' => 36,
    ]);

    $service->reverse($dispatch, 'Reposicion valorizada por lotes');
    $firstRestored = $fixture['stock']->fresh();
    $secondRestored = $secondStock->fresh();
    $reversals = WarehouseKardexMovement::query()
        ->where('source_id', $dispatch->id)
        ->where('movement_type', 'exit_reversal')
        ->orderBy('warehouse_stock_id')
        ->get();

    expect($dispatch->fresh()->status)->toBe(WarehouseDispatch::STATUS_REVERSED)
        ->and($dispatch->fresh()->items()->orderBy('id')->pluck('kardex_movement_id')->all())->toBe($originalMovementIds)
        ->and($reversals)->toHaveCount(2)
        ->and($reversals->pluck('warehouse_stock_id')->all())->toBe([$firstRestored->id, $secondRestored->id])
        ->and((float) $firstRestored->current_quantity)->toBe(8.0)
        ->and((float) $firstRestored->total_cost)->toBe(round(20 + $firstHistoricalCost, 2))
        ->and((float) $firstRestored->average_unit_cost)->toBe(round((20 + $firstHistoricalCost) / 8, 6))
        ->and((float) $secondRestored->current_quantity)->toBe(7.0)
        ->and((float) $secondRestored->total_cost)->toBe(round(36 + $secondHistoricalCost, 2))
        ->and((float) $secondRestored->average_unit_cost)->toBe(round((36 + $secondHistoricalCost) / 7, 6));

    $details->each(function (WarehouseDispatchItem $detail) use ($originalMovements) {
        expect($detail->kardexMovement()->firstOrFail()->getAttributes())
            ->toBe($originalMovements->get($detail->id));
    });
});

it('hace rollback completo si falla la reversa de uno de varios detalles', function () {
    $fixture = warehouseDispatchFixture(10, 'REV3');
    $secondStock = additionalDispatchStock($fixture, 5, 'REV3B', 'LOTE-REV-ROLLBACK');
    $payload = dispatchPayload($fixture, 5);
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 5,
    ];
    $service = app(WarehouseDispatchService::class);
    $dispatch = createConfirmedWarehouseDispatch($service, $fixture['order'], $payload);
    $details = $dispatch->items()->with('kardexMovement')->orderBy('warehouse_stock_id')->get();
    $details->last()->kardexMovement->update([
        'source_key' => 'customer-dispatch-reversal:' . $details->last()->kardexMovement->id,
    ]);

    expect(fn() => $service->reverse($dispatch, 'Falla controlada del segundo detalle'))
        ->toThrow(UniqueConstraintViolationException::class);

    expect($dispatch->fresh()->status)->toBe(WarehouseDispatch::STATUS_CONFIRMED)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(5.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(0.0)
        ->and(WarehouseKardexMovement::where('movement_type', 'exit_reversal')->count())->toBe(0)
        ->and($dispatch->fresh()->items()->where('status', '!=', WarehouseDispatchItem::STATUS_CONFIRMED)->count())->toBe(0);
});

it('bloquea factura generated paid y permite reversa tras cancelar sus allocations sin otra factura activa', function () {
    $fixture = warehouseDispatchFixture(10, 'REV4');
    grantWarehouseDispatchPermission($fixture['user']);
    $service = app(WarehouseDispatchService::class);
    $dispatch = createConfirmedWarehouseDispatch($service, $fixture['order'], dispatchPayload($fixture, 4));
    $paidInvoice = linkGeneratedInvoiceToWarehouseDispatch($fixture, $dispatch, 'paid');
    $paidInvoiceBefore = $paidInvoice->fresh()->getAttributes();

    expect($service->canReverse($dispatch))->toBeFalse();
    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-purchase-orders.dispatch-data', $fixture['order']))
        ->assertOk()
        ->assertJsonPath('data.dispatches.0.can_reverse', false);
    expect(fn() => $service->reverse($dispatch, 'No debe afectar la factura activa'))
        ->toThrow(ValidationException::class, 'vinculado a un comprobante generado');
    expect($paidInvoice->fresh()->getAttributes())->toBe($paidInvoiceBefore)
        ->and($dispatch->fresh()->status)->toBe(WarehouseDispatch::STATUS_CONFIRMED)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0)
        ->and(WarehouseKardexMovement::where('movement_type', 'exit_reversal')->count())->toBe(0);

    $paidInvoice->update(['status' => ElectronicInvoice::STATUS_CANCELLED]);
    ElectronicInvoiceItemDispatchAllocation::query()
        ->whereIn('electronic_invoice_item_id', $paidInvoice->items()->pluck('id'))
        ->delete();
    $otherActiveInvoice = linkGeneratedInvoiceToWarehouseDispatch($fixture, $dispatch);
    expect($service->canReverse($dispatch->fresh()))->toBeFalse();

    $otherActiveInvoice->update(['status' => ElectronicInvoice::STATUS_CANCELLED]);
    ElectronicInvoiceItemDispatchAllocation::query()
        ->whereIn('electronic_invoice_item_id', $otherActiveInvoice->items()->pluck('id'))
        ->delete();
    $cancelledInvoicesBefore = ElectronicInvoice::query()
        ->whereIn('id', [$paidInvoice->id, $otherActiveInvoice->id])
        ->orderBy('id')
        ->get()
        ->map->getAttributes()
        ->all();

    expect($service->canReverse($dispatch->fresh()))->toBeTrue();
    $service->reverse($dispatch, 'Facturas canceladas y allocations inactivas');

    expect($dispatch->fresh()->status)->toBe(WarehouseDispatch::STATUS_REVERSED)
        ->and(ElectronicInvoice::query()
            ->whereIn('id', [$paidInvoice->id, $otherActiveInvoice->id])
            ->orderBy('id')
            ->get()
            ->map->getAttributes()
            ->all())->toBe($cancelledInvoicesBefore)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0);
});

it('atiende una OC de varios proveedores solo al despachar todos sus artículos', function () {
    $fixture = warehouseDispatchFixture(5, 'P5');
    $now = now();
    $order = $fixture['order'];
    $firstItem = $order->items()->firstOrFail();
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA SEGUNDA',
        'code' => 'CDP5B',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => 'ADP5B',
        'category_id' => $categoryId,
        'unit_id' => $firstItem->unit_id,
        'legal_name' => 'ARTÍCULO SEGUNDO',
        'billing_name' => 'ARTÍCULO SEGUNDO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $itemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $order->id,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO SEGUNDO',
        'unit_id' => $firstItem->unit_id,
        'quantity' => 3,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20987654329',
        'business_name' => 'PROVEEDOR SEGUNDO',
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-D-P5B',
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $fixture['warehouse_id'],
        'company_id' => $order->company_id,
        'supplier_id' => $supplierId,
        'currency_id' => $order->currency_id,
        'status' => 'registered',
        'created_by' => $fixture['user']->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryItemId = DB::table('warehouse_entry_items')->insertGetId([
        'warehouse_entry_id' => $entryId,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO SEGUNDO',
        'unit_id' => $firstItem->unit_id,
        'quantity' => 3,
        'unit_price' => 8,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('warehouse_entry_item_allocations')->insert([
        'warehouse_entry_id' => $entryId,
        'warehouse_entry_item_id' => $entryItemId,
        'customer_purchase_order_id' => $order->id,
        'customer_purchase_order_item_id' => $itemId,
        'article_id' => $articleId,
        'quantity_allocated' => 3,
        'unit_cost' => 8,
        'total_cost' => 24,
        'allocation_type' => 'customer_order',
        'status' => 'active',
        'created_by' => $fixture['user']->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $secondStock = WarehouseStock::create([
        'stock_key' => "{$fixture['company_id']}|{$fixture['warehouse_id']}|{$articleId}|SIN_LOTE|SIN_FECHA",
        'company_id' => $fixture['company_id'],
        'warehouse_id' => $fixture['warehouse_id'],
        'article_id' => $articleId,
        'unit_id' => $firstItem->unit_id,
        'current_quantity' => 3,
        'average_unit_cost' => 8,
        'total_cost' => 24,
        'status' => 'ACTIVE',
    ]);
    $service = app(WarehouseDispatchService::class);
    createConfirmedWarehouseDispatch($service, $order->fresh(), dispatchPayload($fixture, 5));

    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_DISPATCHED);

    createConfirmedWarehouseDispatch($service, $order->fresh(), [
        'warehouse_id' => $fixture['warehouse_id'],
        'dispatch_date' => now(),
        'responsible_user_id' => $fixture['user']->id,
        'items' => [[
            'customer_purchase_order_item_id' => $itemId,
            'warehouse_stock_id' => $secondStock->id,
            'quantity' => 3,
        ]],
    ]);

    expect($order->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(0.0);
});

it('rechaza por backend una salida sin almacén con el mensaje operativo', function () {
    $fixture = warehouseDispatchFixture(10, 'P6');
    grantWarehouseDispatchPermission($fixture['user']);
    $payload = dispatchPayload($fixture, 2);
    unset($payload['warehouse_id']);

    $this->actingAs($fixture['user'])
        ->postJson(route('admin.customer-purchase-orders.dispatches.store', $fixture['order']), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['warehouse_id'])
        ->assertJsonPath('errors.warehouse_id.0', 'Seleccione un almacén para registrar la salida.');

    expect(WarehouseDispatch::count())->toBe(0);
});

it('el servicio también rechaza una salida sin almacén', function () {
    $fixture = warehouseDispatchFixture(10, 'P7');
    $payload = dispatchPayload($fixture, 2);
    unset($payload['warehouse_id']);

    expect(fn() => app(WarehouseDispatchService::class)->createDraft($fixture['order'], $payload))
        ->toThrow(ValidationException::class, 'Seleccione un almacén para registrar la salida.');
});

it('carga únicamente saldos del almacén seleccionado y no precarga saldos globales', function () {
    $fixture = warehouseDispatchFixture(10, 'P8');
    grantWarehouseDispatchPermission($fixture['user']);
    $now = now();
    $otherWarehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WDP8B',
        'name' => 'ALMACÉN P8 B',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $fixture['company_id'],
        'warehouse_id' => $otherWarehouseId,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $otherStock = WarehouseStock::create([
        'stock_key' => "{$fixture['company_id']}|{$otherWarehouseId}|{$fixture['article_id']}|SIN_LOTE|SIN_FECHA",
        'company_id' => $fixture['company_id'],
        'warehouse_id' => $otherWarehouseId,
        'article_id' => $fixture['article_id'],
        'unit_id' => $fixture['unit_id'],
        'current_quantity' => 5,
        'average_unit_cost' => 10,
        'total_cost' => 50,
        'status' => 'ACTIVE',
    ]);

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-purchase-orders.dispatch-data', $fixture['order']))
        ->assertOk()
        ->assertJsonCount(0, 'data.items.0.stocks');

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-purchase-orders.dispatch-stocks', [
            'customerPurchaseOrder' => $fixture['order'],
            'warehouse_id' => $fixture['warehouse_id'],
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.items.0.stocks')
        ->assertJsonPath('data.items.0.stocks.0.id', $fixture['stock']->id)
        ->assertJsonMissing(['id' => $otherStock->id]);

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-purchase-orders.dispatch-stocks', [
            'customerPurchaseOrder' => $fixture['order'],
            'warehouse_id' => $otherWarehouseId,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data.items.0.stocks')
        ->assertJsonPath('data.items.0.stocks.0.id', $otherStock->id)
        ->assertJsonMissing(['id' => $fixture['stock']->id]);
});

it('rechaza un saldo que pertenece a otro almacén', function () {
    $fixture = warehouseDispatchFixture(10, 'P9');
    $otherWarehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WDP9B',
        'name' => 'ALMACÉN P9 B',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $fixture['company_id'],
        'warehouse_id' => $otherWarehouseId,
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $payload = dispatchPayload($fixture, 2);
    $payload['warehouse_id'] = $otherWarehouseId;

    expect(fn() => app(WarehouseDispatchService::class)->createDraft($fixture['order'], $payload))
        ->toThrow(ValidationException::class, 'El saldo seleccionado no pertenece al almacén indicado.');

    expect(WarehouseDispatch::count())->toBe(0)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0);
});

it('rechaza un saldo sin stock disponible', function () {
    $fixture = warehouseDispatchFixture(10, 'P10');
    $fixture['stock']->update(['current_quantity' => 0, 'total_cost' => 0]);

    expect(fn() => app(WarehouseDispatchService::class)->createDraft($fixture['order'], dispatchPayload($fixture, 1)))
        ->toThrow(ValidationException::class, 'No hay stock disponible en el almacén seleccionado.');

    expect(WarehouseDispatch::count())->toBe(0);
});

it('rechaza un saldo que no pertenece al artículo pendiente', function () {
    $fixture = warehouseDispatchFixture(10, 'P12');
    $otherFixture = warehouseDispatchFixture(3, 'P12B');
    $otherFixture['stock']->update([
        'company_id' => $fixture['company_id'],
        'stock_key' => "{$fixture['company_id']}|{$fixture['warehouse_id']}|{$otherFixture['article_id']}|SIN_LOTE|SIN_FECHA",
        'warehouse_id' => $fixture['warehouse_id'],
    ]);
    $payload = dispatchPayload($fixture, 2);
    $payload['items'][0]['warehouse_stock_id'] = $otherFixture['stock']->id;

    expect(fn() => app(WarehouseDispatchService::class)->createDraft($fixture['order'], $payload))
        ->toThrow(ValidationException::class, 'El saldo seleccionado no pertenece al artículo indicado.');

    expect(WarehouseDispatch::count())->toBe(0)
        ->and((float) $otherFixture['stock']->fresh()->current_quantity)->toBe(3.0);
});

it('mantiene el despacho parcial en el historial del modal', function () {
    $fixture = warehouseDispatchFixture(10, 'P11');
    grantWarehouseDispatchPermission($fixture['user']);
    $dispatch = createConfirmedWarehouseDispatch(app(WarehouseDispatchService::class), $fixture['order'], dispatchPayload($fixture, 4));

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-purchase-orders.dispatch-data', $fixture['order']))
        ->assertOk()
        ->assertJsonPath('data.dispatches.0.id', $dispatch->id)
        ->assertJsonPath('data.dispatches.0.status', WarehouseDispatch::STATUS_CONFIRMED)
        ->assertJsonPath('data.dispatches.0.items.0.quantity', '4.0000');
});

it('distribuye una linea entre dos lotes con detalle costo y Kardex independientes', function () {
    $fixture = warehouseDispatchFixture(20, 'D1');
    DB::table('articles')->where('id', $fixture['article_id'])->update([
        'has_batch' => true,
        'has_expiration' => true,
    ]);
    $fixture['stock']->update([
        'stock_key' => "{$fixture['warehouse_id']}|{$fixture['article_id']}|LOTE-A|2027-06-30",
        'lot_number' => 'LOTE-A',
        'expiration_date' => '2027-06-30',
        'current_quantity' => 8,
        'total_cost' => 80,
    ]);
    $secondStock = additionalDispatchStock($fixture, 15, 'D1B', 'LOTE-B');
    $payload = dispatchPayload($fixture, 8);
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 12,
    ];

    $dispatch = createConfirmedWarehouseDispatch(app(WarehouseDispatchService::class), $fixture['order'], $payload);
    $details = $dispatch->items()->orderBy('warehouse_stock_id')->get();

    expect($details)->toHaveCount(2)
        ->and($details->pluck('customer_purchase_order_item_id')->unique()->all())->toBe([$fixture['item_id']])
        ->and($details->pluck('warehouse_stock_id')->all())->toBe([$fixture['stock']->id, $secondStock->id])
        ->and($details->pluck('lot_number')->all())->toBe(['LOTE-A', 'LOTE-B'])
        ->and($details->pluck('kardex_movement_id')->filter()->unique())->toHaveCount(2)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(3.0)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED)
        ->and(WarehouseKardexMovement::whereIn('source_item_id', $details->pluck('id'))->where('movement_type', 'exit')->count())->toBe(2);

    $details->each(function ($detail) {
        expect($detail->customerPurchaseOrderItem->id)->toBe($detail->customer_purchase_order_item_id)
            ->and($detail->stock->id)->toBe($detail->warehouse_stock_id)
            ->and($detail->kardexMovement)->not->toBeNull()
            ->and($detail->kardexMovement->warehouse_stock_id)->toBe($detail->warehouse_stock_id)
            ->and((float) $detail->kardexMovement->quantity_out)->toBe((float) $detail->quantity)
            ->and((float) $detail->unit_cost)->toBe(10.0)
            ->and((float) $detail->total_cost)->toBe((float) $detail->quantity * 10);
    });
});

it('conserva por lote cantidades y saldos físicos usando un único PPM de salida', function () {
    $fixture = warehouseDispatchFixture(10, 'KDX3');
    DB::table('articles')->where('id', $fixture['article_id'])->update([
        'has_batch' => true,
        'has_expiration' => true,
    ]);
    WarehouseStock::query()->whereKey($fixture['stock']->id)->update([
        'stock_key' => "{$fixture['warehouse_id']}|{$fixture['article_id']}|LOTE-A|2030-02-05",
        'lot_number' => 'LOTE-A',
        'expiration_date' => '2030-02-05',
        'current_quantity' => 6,
        'average_unit_cost' => 5,
        'total_cost' => 30,
    ]);
    $secondStock = additionalDispatchStock($fixture, 4, 'KDX3B', 'LOTE-B');
    $secondStock->update([
        'average_unit_cost' => 6,
        'total_cost' => 24,
    ]);
    $payload = dispatchPayload($fixture, 6);
    $expectedPpm = (float) WarehouseValuationPool::query()
        ->where('company_id', $fixture['company_id'])
        ->where('warehouse_id', $fixture['warehouse_id'])
        ->where('article_id', $fixture['article_id'])
        ->value('average_unit_cost');
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 4,
    ];

    $dispatch = createConfirmedWarehouseDispatch(
        app(WarehouseDispatchService::class),
        $fixture['order']->fresh(),
        $payload
    );
    $details = $dispatch->items()->with('kardexMovement')->orderBy('warehouse_stock_id')->get();
    $firstMovement = $details[0]->kardexMovement;
    $secondMovement = $details[1]->kardexMovement;

    expect($details)->toHaveCount(2)
        ->and($details->pluck('kardex_movement_id')->filter()->unique())->toHaveCount(2)
        ->and(WarehouseKardexMovement::where('source_type', WarehouseDispatch::class)
            ->where('source_id', $dispatch->id)->count())->toBe(2)
        ->and($firstMovement->lot_number)->toBe('LOTE-A')
        ->and((float) $firstMovement->quantity_out)->toBe(6.0)
        ->and((float) $firstMovement->unit_cost)->toBe($expectedPpm)
        ->and((float) $firstMovement->total_cost_out)->toBe(round(6 * $expectedPpm, 2))
        ->and((float) $firstMovement->balance_quantity)->toBe(0.0)
        ->and((float) $firstMovement->balance_total_cost)->toBe(0.0)
        ->and($secondMovement->lot_number)->toBe('LOTE-B')
        ->and((float) $secondMovement->quantity_out)->toBe(4.0)
        ->and((float) $secondMovement->unit_cost)->toBe($expectedPpm)
        ->and((float) $secondMovement->total_cost_out)->toBe(round(4 * $expectedPpm, 2))
        ->and((float) $secondMovement->balance_quantity)->toBe(0.0)
        ->and((float) $secondMovement->balance_total_cost)->toBe(0.0)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(0.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $secondStock->fresh()->total_cost)->toBe(0.0);
});

it('rechaza repetir el mismo stock para el mismo item dentro del despacho', function () {
    $fixture = warehouseDispatchFixture(10, 'D2');
    $payload = dispatchPayload($fixture, 5);
    $payload['items'][] = $payload['items'][0] + ['quantity' => 3];
    $payload['items'][1]['quantity'] = 3;

    expect(fn() => app(WarehouseDispatchService::class)->createDraft($fixture['order'], $payload))
        ->toThrow(ValidationException::class, 'El lote/stock seleccionado está repetido para este artículo.');

    expect(WarehouseDispatch::count())->toBe(0)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0);
});

it('rechaza cuando la suma de lotes supera el pendiente de la OC', function () {
    $fixture = warehouseDispatchFixture(10, 'D3');
    $secondStock = additionalDispatchStock($fixture, 10, 'D3B');
    $payload = dispatchPayload($fixture, 6);
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 5,
    ];

    expect(fn() => app(WarehouseDispatchService::class)->createDraft($fixture['order'], $payload))
        ->toThrow(ValidationException::class, 'supera el pendiente de la OC Cliente');

    expect(WarehouseDispatch::count())->toBe(0)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(10.0);
});

it('revierte toda la transaccion si uno de varios stocks es insuficiente', function () {
    $fixture = warehouseDispatchFixture(10, 'D4');
    $secondStock = additionalDispatchStock($fixture, 2, 'D4B');
    $payload = dispatchPayload($fixture, 5);
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 5,
    ];

    expect(fn() => app(WarehouseDispatchService::class)->createDraft($fixture['order'], $payload))
        ->toThrow(ValidationException::class, 'supera el stock disponible del saldo seleccionado');

    expect(WarehouseDispatch::count())->toBe(0)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(2.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0);
});

it('rechaza cantidades cero negativas o con mas de cuatro decimales', function ($quantity) {
    $fixture = warehouseDispatchFixture(10, 'D5' . str_replace(['-', '.'], '', (string) $quantity));

    expect(fn() => app(WarehouseDispatchService::class)->createDraft($fixture['order'], dispatchPayload($fixture, $quantity)))
        ->toThrow(ValidationException::class, 'Cada cantidad a despachar debe ser mayor a 0 y tener como máximo 4 decimales.');

    expect(WarehouseDispatch::count())->toBe(0);
})->with([0, -1, 1.00001]);

it('no cuenta un despacho cancelado y si cuenta uno confirmado para calcular pendiente', function () {
    $fixture = warehouseDispatchFixture(10, 'D6');
    $service = app(WarehouseDispatchService::class);
    $cancelled = createConfirmedWarehouseDispatch($service, $fixture['order'], dispatchPayload($fixture, 4));
    $service->reverse($cancelled, 'Se anula para comprobar el pendiente');
    $confirmed = createConfirmedWarehouseDispatch($service, $fixture['order']->fresh(), dispatchPayload($fixture, 4));

    expect((float) $service->dispatchedQuantities($fixture['order']->fresh())->get($fixture['item_id']))->toBe(4.0);

    $payload = dispatchPayload($fixture, 7);
    expect(fn() => $service->createDraft($fixture['order']->fresh(), $payload))
        ->toThrow(ValidationException::class, 'supera el pendiente de la OC Cliente');

    expect($cancelled->fresh()->status)->toBe(WarehouseDispatch::STATUS_REVERSED)
        ->and($confirmed->fresh()->status)->toBe(WarehouseDispatch::STATUS_CONFIRMED)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0);
});

it('el indice unique impide duplicar item y stock dentro del mismo despacho', function () {
    $fixture = warehouseDispatchFixture(10, 'D7');
    createConfirmedWarehouseDispatch(app(WarehouseDispatchService::class), $fixture['order'], dispatchPayload($fixture, 2));
    $detail = (array) DB::table('warehouse_dispatch_items')->first();
    unset($detail['id']);

    expect(fn() => DB::table('warehouse_dispatch_items')->insert($detail))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('mantiene los estados y tipos canonicos de la cabecera', function () {
    expect(WarehouseDispatch::STATUSES)->toBe([
        WarehouseDispatch::STATUS_DRAFT,
        WarehouseDispatch::STATUS_CONFIRMED,
        WarehouseDispatch::STATUS_CANCELLED,
        WarehouseDispatch::STATUS_REVERSED,
    ])->and(WarehouseDispatch::DISPATCH_TYPES)->toBe([
        WarehouseDispatch::TYPE_CUSTOMER_ORDER,
    ]);
});

it('toma empresa y tipo desde la OC sin aceptar manipulacion del request', function () {
    $fixture = warehouseDispatchFixture(10, 'H1');
    grantWarehouseDispatchPermission($fixture['user']);
    $otherCompanyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA AJENA',
        'ruc' => '20999999991',
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $payload = dispatchPayload($fixture, 2);
    $payload += [
        'company_id' => $otherCompanyId,
        'dispatch_type' => 'other',
        'status' => WarehouseDispatch::STATUS_DRAFT,
        'confirmed_by' => User::factory()->create()->id,
        'destination' => 'Sede operativa del cliente',
    ];

    $this->actingAs($fixture['user'])
        ->postJson(route('admin.customer-purchase-orders.dispatches.store', $fixture['order']), $payload)
        ->assertCreated()
        ->assertJsonPath('data.company_id', $fixture['company_id'])
        ->assertJsonPath('data.dispatch_type', WarehouseDispatch::TYPE_CUSTOMER_ORDER)
        ->assertJsonPath('data.status', WarehouseDispatch::STATUS_DRAFT)
        ->assertJsonPath('data.confirmed_by', null)
        ->assertJsonPath('data.destination', 'Sede operativa del cliente');

    expect(Schema::hasColumn('warehouse_dispatches', 'customer_id'))->toBeFalse();
});

it('reutiliza una cabecera ante el mismo idempotency key sin repetir el efecto fisico', function () {
    $fixture = warehouseDispatchFixture(10, 'H2');
    $payload = dispatchPayload($fixture, 4);
    $payload['idempotency_key'] = (string) Str::uuid();
    $service = app(WarehouseDispatchService::class);

    $first = $service->createDraft($fixture['order'], $payload);
    $second = $service->createDraft($fixture['order']->fresh(), $payload);

    expect($second->id)->toBe($first->id)
        ->and(WarehouseDispatch::count())->toBe(1)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0);
});

it('impide reutilizar una idempotency key en dos cabeceras', function () {
    $fixture = warehouseDispatchFixture(10, 'H3');
    $dispatch = app(WarehouseDispatchService::class)->createDraft($fixture['order'], dispatchPayload($fixture, 2));

    expect(fn() => DB::table('warehouse_dispatches')->insert([
        'company_id' => $fixture['company_id'],
        'dispatch_number' => 'SAL-999999',
        'idempotency_key' => $dispatch->idempotency_key,
        'customer_purchase_order_id' => $fixture['order']->id,
        'warehouse_id' => $fixture['warehouse_id'],
        'dispatch_date' => now(),
        'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER,
        'status' => WarehouseDispatch::STATUS_DRAFT,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('genera numeros SAL consecutivos y distintos', function () {
    $firstFixture = warehouseDispatchFixture(2, 'H4A');
    $first = app(WarehouseDispatchService::class)->createDraft(
        $firstFixture['order'],
        dispatchPayload($firstFixture, 1)
    );
    $secondFixture = warehouseDispatchFixture(2, 'H4B');
    $second = app(WarehouseDispatchService::class)->createDraft(
        $secondFixture['order'],
        dispatchPayload($secondFixture, 1)
    );

    expect($first->dispatch_number)->toMatch('/^SAL-\d{6,}$/')
        ->and($second->dispatch_number)->toMatch('/^SAL-\d{6,}$/')
        ->and($second->dispatch_number)->not->toBe($first->dispatch_number);
});

it('normaliza historicos registered sin alterar stock ni Kardex y conserva cancelled', function () {
    $fixture = warehouseDispatchFixture(10, 'H5');
    $dispatch = createConfirmedWarehouseDispatch(app(WarehouseDispatchService::class), $fixture['order'], dispatchPayload($fixture, 2));
    $cancelledId = DB::table('warehouse_dispatches')->insertGetId([
        'company_id' => $fixture['company_id'],
        'dispatch_number' => 'SAL-900001',
        'idempotency_key' => (string) Str::uuid(),
        'customer_purchase_order_id' => $fixture['order']->id,
        'warehouse_id' => $fixture['warehouse_id'],
        'dispatch_date' => now(),
        'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER,
        'status' => WarehouseDispatch::STATUS_CANCELLED,
        'created_by' => $fixture['user']->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $stockBefore = $fixture['stock']->fresh()->only(['current_quantity', 'average_unit_cost', 'total_cost']);
    $kardexBefore = WarehouseKardexMovement::count();
    $migration = require database_path('migrations/2026_08_31_000001_enhance_warehouse_dispatches_header.php');

    $migration->down();
    expect(DB::table('warehouse_dispatches')->where('id', $dispatch->id)->value('status'))->toBe('registered')
        ->and(DB::table('warehouse_dispatches')->where('id', $cancelledId)->value('status'))->toBe(WarehouseDispatch::STATUS_CANCELLED);

    $migration->up();
    $legacy = DB::table('warehouse_dispatches')->where('id', $dispatch->id)->first();

    expect($legacy->status)->toBe(WarehouseDispatch::STATUS_CONFIRMED)
        ->and((int) $legacy->company_id)->toBe($fixture['company_id'])
        ->and($legacy->dispatch_type)->toBe(WarehouseDispatch::TYPE_CUSTOMER_ORDER)
        ->and($legacy->confirmed_at)->not->toBeNull()
        ->and((int) $legacy->confirmed_by)->toBe($fixture['user']->id)
        ->and(DB::table('warehouse_dispatches')->where('id', $cancelledId)->value('status'))->toBe(WarehouseDispatch::STATUS_CANCELLED)
        ->and($fixture['stock']->fresh()->only(['current_quantity', 'average_unit_cost', 'total_cost']))->toBe($stockBefore)
        ->and(WarehouseKardexMovement::count())->toBe($kardexBefore);
});

it('crea y edita un borrador multilote sin producir efectos fisicos', function () {
    $fixture = warehouseDispatchFixture(10, 'LIFE1');
    $secondStock = additionalDispatchStock($fixture, 8, 'LIFE1B');
    $service = app(WarehouseDispatchService::class);
    $payload = dispatchPayload($fixture, 4);
    $payload['destination'] = 'Destino inicial';
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 3,
    ];

    $draft = $service->createDraft($fixture['order'], $payload);

    expect($draft->status)->toBe(WarehouseDispatch::STATUS_DRAFT)
        ->and($draft->confirmed_at)->toBeNull()
        ->and($draft->confirmed_by)->toBeNull()
        ->and($draft->items)->toHaveCount(2)
        ->and($draft->items->every(fn($item) => $item->status === WarehouseDispatchItem::STATUS_DRAFT))->toBeTrue()
        ->and($draft->items->pluck('kardex_movement_id')->filter())->toBeEmpty()
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(8.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);

    $payload['destination'] = 'Destino actualizado';
    $payload['items'][0]['quantity'] = 2;
    $payload['items'][1]['quantity'] = 5;
    $updated = $service->updateDraft($draft, $payload);

    expect($updated->destination)->toBe('Destino actualizado')
        ->and($updated->items->pluck('quantity')->map(fn($quantity) => (float) $quantity)->sort()->values()->all())->toBe([2.0, 5.0])
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(8.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0);
});

it('revalida el stock bloqueado al confirmar cuando baja despues de crear el borrador', function () {
    $fixture = warehouseDispatchFixture(10, 'LOCK1');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchPayload($fixture, 10));
    $orderStatusBefore = $fixture['order']->fresh()->status;
    $kardexBefore = WarehouseKardexMovement::count();

    $fixture['stock']->update(['current_quantity' => 7, 'total_cost' => 70]);

    expect(fn() => $service->confirm($draft))
        ->toThrow(ValidationException::class, 'El stock disponible cambió desde que se creó el borrador.');

    $draft->refresh()->load('items');

    expect($draft->status)->toBe(WarehouseDispatch::STATUS_DRAFT)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(7.0)
        ->and($draft->items->every(fn($item) => $item->status === WarehouseDispatchItem::STATUS_DRAFT))->toBeTrue()
        ->and($draft->items->pluck('kardex_movement_id')->filter())->toBeEmpty()
        ->and(WarehouseKardexMovement::count())->toBe($kardexBefore)
        ->and($fixture['order']->fresh()->status)->toBe($orderStatusBefore);
});

it('conserva la fecha local del borrador durante dos ciclos de guardado y edicion', function () {
    $fixture = warehouseDispatchFixture(10, 'TZ1');
    grantWarehouseDispatchPermission($fixture['user']);
    $payload = dispatchPayload($fixture, 4);
    $payload['dispatch_date'] = '2026-09-01T17:33';

    $created = $this->actingAs($fixture['user'])
        ->postJson(route('admin.customer-purchase-orders.dispatches.store', $fixture['order']), $payload)
        ->assertCreated()
        ->assertJsonPath('data.dispatch_date_local', '2026-09-01T17:33')
        ->json('data');

    expect(DB::table('warehouse_dispatches')->where('id', $created['id'])->value('dispatch_date'))
        ->toBe('2026-09-01 17:33:00');

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-purchase-orders.dispatch-data', [
            'customerPurchaseOrder' => $fixture['order'],
            'dispatch_id' => $created['id'],
        ]))
        ->assertOk()
        ->assertJsonPath('data.editing_dispatch.dispatch_date_local', '2026-09-01T17:33');

    unset($payload['idempotency_key']);
    $this->actingAs($fixture['user'])
        ->putJson(route('admin.customer-purchase-orders.dispatches.update', [
            $fixture['order'],
            $created['id'],
        ]), $payload)
        ->assertOk()
        ->assertJsonPath('data.dispatch_date_local', '2026-09-01T17:33');

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-purchase-orders.dispatch-data', [
            'customerPurchaseOrder' => $fixture['order'],
            'dispatch_id' => $created['id'],
        ]))
        ->assertOk()
        ->assertJsonPath('data.editing_dispatch.dispatch_date_local', '2026-09-01T17:33');

    expect(DB::table('warehouse_dispatches')->where('id', $created['id'])->value('dispatch_date'))
        ->toBe('2026-09-01 17:33:00')
        ->and(WarehouseDispatch::findOrFail($created['id'])->status)->toBe(WarehouseDispatch::STATUS_DRAFT)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0);
});

it('confirma un borrador multilote y rechaza editarlo despues del efecto fisico', function () {
    $fixture = warehouseDispatchFixture(10, 'LIFE2');
    $secondStock = additionalDispatchStock($fixture, 8, 'LIFE2B');
    $service = app(WarehouseDispatchService::class);
    $payload = dispatchPayload($fixture, 4);
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 3,
    ];
    $draft = $service->createDraft($fixture['order'], $payload);
    $confirmed = $service->confirm($draft);

    expect($confirmed->status)->toBe(WarehouseDispatch::STATUS_CONFIRMED)
        ->and($confirmed->confirmed_at)->not->toBeNull()
        ->and($confirmed->confirmed_by)->toBe($fixture['user']->id)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(5.0)
        ->and($confirmed->items->every(fn($item) => $item->status === WarehouseDispatchItem::STATUS_CONFIRMED))->toBeTrue()
        ->and($confirmed->items->pluck('kardex_movement_id')->filter()->unique())->toHaveCount(2)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(2)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_PARTIAL_DISPATCHED);

    expect(fn() => $service->updateDraft($confirmed, $payload))
        ->toThrow(ValidationException::class, 'Solo los despachos en borrador pueden modificarse.');
});

it('revalida todos los stocks al confirmar y revierte completamente ante un lote insuficiente', function () {
    $fixture = warehouseDispatchFixture(10, 'LIFE3');
    $fixture['stock']->update(['current_quantity' => 6, 'total_cost' => 60]);
    $secondStock = additionalDispatchStock($fixture, 4, 'LIFE3B');
    $service = app(WarehouseDispatchService::class);
    $payload = dispatchPayload($fixture, 6);
    $payload['items'][] = [
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $secondStock->id,
        'quantity' => 4,
    ];
    $draft = $service->createDraft($fixture['order'], $payload);
    $secondStock->update(['current_quantity' => 2, 'total_cost' => 20]);
    $kardexBefore = WarehouseKardexMovement::count();

    expect(fn() => $service->confirm($draft))
        ->toThrow(ValidationException::class, 'El stock disponible cambió desde que se creó el borrador.');

    expect($draft->fresh()->status)->toBe(WarehouseDispatch::STATUS_DRAFT)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0)
        ->and((float) $secondStock->fresh()->current_quantity)->toBe(2.0)
        ->and(WarehouseKardexMovement::count())->toBe($kardexBefore)
        ->and($draft->items()->whereNotNull('kardex_movement_id')->count())->toBe(0)
        ->and($draft->items()->where('status', '!=', WarehouseDispatchItem::STATUS_DRAFT)->count())->toBe(0)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('permite dos borradores competidores pero solo confirma el primero', function () {
    $fixture = warehouseDispatchFixture(10, 'LIFE4');
    $service = app(WarehouseDispatchService::class);
    $firstPayload = dispatchPayload($fixture, 10);
    $secondPayload = dispatchPayload($fixture, 10);
    $firstDraft = $service->createDraft($fixture['order'], $firstPayload);
    $oldDraft = $service->createDraft($fixture['order'], $secondPayload);
    $service->confirm($firstDraft);
    WarehouseStock::query()->whereKey($fixture['stock']->id)->update([
        'current_quantity' => 10,
        'total_cost' => 100,
    ]);

    expect(fn() => $service->confirm($oldDraft))
        ->toThrow(ValidationException::class, 'La cantidad pendiente de la orden cambió.');

    expect($oldDraft->fresh()->status)->toBe(WarehouseDispatch::STATUS_DRAFT)
        ->and($firstDraft->fresh()->status)->toBe(WarehouseDispatch::STATUS_CONFIRMED)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(1)
        ->and($oldDraft->items()->whereNotNull('kardex_movement_id')->count())->toBe(0)
        ->and($oldDraft->items()->where('status', '!=', WarehouseDispatchItem::STATUS_DRAFT)->count())->toBe(0)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED);
});

it('suma todos los detalles que consumen el mismo stock antes de aplicar efectos fisicos', function () {
    $fixture = warehouseDispatchFixture(6, 'LOCK2');
    $fixture['stock']->update(['current_quantity' => 10, 'total_cost' => 100]);
    $now = now();
    $secondOrderItemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $fixture['order']->id,
        'article_id' => $fixture['article_id'],
        'billing_name_snapshot' => 'ARTÍCULO LOCK2-B',
        'unit_id' => $fixture['unit_id'],
        'quantity' => 6,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryId = DB::table('warehouse_entries')
        ->where('company_id', $fixture['company_id'])
        ->value('id');
    $entryItemId = DB::table('warehouse_entry_items')->insertGetId([
        'warehouse_entry_id' => $entryId,
        'article_id' => $fixture['article_id'],
        'billing_name_snapshot' => 'ARTÍCULO LOCK2-B',
        'unit_id' => $fixture['unit_id'],
        'quantity' => 6,
        'unit_price' => 10,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('warehouse_entry_item_allocations')->insert([
        'warehouse_entry_id' => $entryId,
        'warehouse_entry_item_id' => $entryItemId,
        'customer_purchase_order_id' => $fixture['order']->id,
        'customer_purchase_order_item_id' => $secondOrderItemId,
        'article_id' => $fixture['article_id'],
        'quantity_allocated' => 6,
        'unit_cost' => 10,
        'total_cost' => 60,
        'allocation_type' => 'customer_order',
        'status' => 'active',
        'created_by' => $fixture['user']->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $draft = directWarehouseDispatchDraft($fixture, [
        [
            'customer_purchase_order_item_id' => $fixture['item_id'],
            'warehouse_stock_id' => $fixture['stock']->id,
            'quantity' => 6,
        ],
        [
            'customer_purchase_order_item_id' => $secondOrderItemId,
            'warehouse_stock_id' => $fixture['stock']->id,
            'quantity' => 6,
        ],
    ]);

    expect(fn() => app(WarehouseDispatchService::class)->confirm($draft))
        ->toThrow(ValidationException::class, 'El stock disponible cambió desde que se creó el borrador.');

    $draft->refresh()->load('items');

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and($draft->status)->toBe(WarehouseDispatch::STATUS_DRAFT)
        ->and($draft->items->every(fn($item) => $item->status === WarehouseDispatchItem::STATUS_DRAFT))->toBeTrue()
        ->and($draft->items->pluck('kardex_movement_id')->filter())->toBeEmpty()
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('confirma con la verdad bloqueada cuando el stock aumenta despues de crear el borrador', function () {
    $fixture = warehouseDispatchFixture(10, 'LOCK3');
    $fixture['stock']->update(['current_quantity' => 5, 'total_cost' => 50]);
    $draft = directWarehouseDispatchDraft($fixture, [[
        'customer_purchase_order_item_id' => $fixture['item_id'],
        'warehouse_stock_id' => $fixture['stock']->id,
        'quantity' => 10,
    ]]);

    $fixture['stock']->update(['current_quantity' => 10, 'total_cost' => 100]);
    $confirmed = app(WarehouseDispatchService::class)->confirm($draft);

    expect($confirmed->status)->toBe(WarehouseDispatch::STATUS_CONFIRMED)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and($confirmed->items->every(fn($item) => $item->status === WarehouseDispatchItem::STATUS_CONFIRMED))->toBeTrue()
        ->and($confirmed->items->pluck('kardex_movement_id')->filter()->unique())->toHaveCount(1)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(1)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED);
});

it('no tolera un deficit de una diezmilésima al revalidar el stock', function () {
    $fixture = warehouseDispatchFixture(1, 'LOCK4');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchPayload($fixture, 1));
    $fixture['stock']->update(['current_quantity' => '0.9999', 'total_cost' => 10]);

    expect(fn() => $service->confirm($draft))
        ->toThrow(ValidationException::class, 'El stock disponible cambió desde que se creó el borrador.');

    expect($draft->fresh()->status)->toBe(WarehouseDispatch::STATUS_DRAFT)
        ->and($fixture['stock']->fresh()->current_quantity)->toBe('0.9999')
        ->and($draft->items()->whereNotNull('kardex_movement_id')->count())->toBe(0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('hace idempotente la confirmacion secuencial y por doble request HTTP', function () {
    $fixture = warehouseDispatchFixture(10, 'LIFE5');
    grantWarehouseDispatchPermission($fixture['user']);
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchPayload($fixture, 4));

    $first = $service->confirm($draft);
    $movementId = $first->items()->firstOrFail()->kardex_movement_id;
    $second = $service->confirm($draft);
    expect($second->id)->toBe($first->id)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(1)
        ->and($second->items()->firstOrFail()->kardex_movement_id)->toBe($movementId);

    $url = route('admin.customer-purchase-orders.dispatches.confirm', [$fixture['order'], $draft]);
    $this->actingAs($fixture['user'])->postJson($url)->assertOk()->assertJsonPath('data.status', WarehouseDispatch::STATUS_CONFIRMED);
    $this->actingAs($fixture['user'])->postJson($url)->assertOk()->assertJsonPath('data.status', WarehouseDispatch::STATUS_CONFIRMED);

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(6.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(1)
        ->and($draft->fresh()->items()->firstOrFail()->kardex_movement_id)->toBe($movementId);
});

it('cancela un borrador conservando auditoria y sin reversa fisica', function () {
    $fixture = warehouseDispatchFixture(10, 'LIFE6');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchPayload($fixture, 4));
    $cancelled = $service->cancelDraft($draft, 'Preparación descartada por el operador');

    expect($cancelled->status)->toBe(WarehouseDispatch::STATUS_CANCELLED)
        ->and($cancelled->cancelled_at)->not->toBeNull()
        ->and($cancelled->cancelled_by)->toBe($fixture['user']->id)
        ->and($cancelled->cancellation_reason)->toBe('Preparación descartada por el operador')
        ->and($cancelled->items->every(fn($item) => $item->status === WarehouseDispatchItem::STATUS_CANCELLED))->toBeTrue()
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0)
        ->and(WarehouseKardexMovement::where('movement_type', 'exit_reversal')->count())->toBe(0)
        ->and($fixture['order']->fresh()->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('no reprocesa un confirmed historico con detalle registered', function () {
    $fixture = warehouseDispatchFixture(10, 'LIFE7');
    $service = app(WarehouseDispatchService::class);
    $confirmed = createConfirmedWarehouseDispatch($service, $fixture['order'], dispatchPayload($fixture, 3));
    $confirmed->items()->update(['status' => WarehouseDispatchItem::STATUS_LEGACY_CONFIRMED]);
    $beforeStock = $fixture['stock']->fresh()->current_quantity;
    $beforeKardex = WarehouseKardexMovement::count();

    $result = $service->confirm($confirmed);

    expect($result->id)->toBe($confirmed->id)
        ->and($fixture['stock']->fresh()->current_quantity)->toBe($beforeStock)
        ->and(WarehouseKardexMovement::count())->toBe($beforeKardex)
        ->and((float) $service->dispatchedQuantities($fixture['order'])->get($fixture['item_id']))->toBe(3.0);
});

it('protege crear editar confirmar y cancelar borradores con el permiso operativo', function () {
    $fixture = warehouseDispatchFixture(10, 'LIFE8');
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], dispatchPayload($fixture, 2));
    $unauthorized = User::factory()->create();

    $this->actingAs($unauthorized)
        ->postJson(route('admin.customer-purchase-orders.dispatches.store', $fixture['order']), dispatchPayload($fixture, 1))
        ->assertForbidden();
    $this->actingAs($unauthorized)
        ->putJson(route('admin.customer-purchase-orders.dispatches.update', [$fixture['order'], $draft]), dispatchPayload($fixture, 1))
        ->assertForbidden();
    $this->actingAs($unauthorized)
        ->postJson(route('admin.customer-purchase-orders.dispatches.confirm', [$fixture['order'], $draft]))
        ->assertForbidden();
    $this->actingAs($unauthorized)
        ->postJson(route('admin.customer-purchase-orders.dispatches.cancel-draft', [$fixture['order'], $draft]), ['reason' => 'Sin permiso operativo'])
        ->assertForbidden();

    expect($draft->fresh()->status)->toBe(WarehouseDispatch::STATUS_DRAFT)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(0);
});
