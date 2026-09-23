<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\ElectronicInvoice;
use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\CustomerReturnService;
use App\Services\WarehouseDispatchService;
use App\Services\WarehouseKardexService;
use Database\Seeders\SunatCatalogSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function sunatDocumentOperationFixture(string $suffix, ?string $documentType = 'FACTURA'): array
{
    app(SunatCatalogSeeder::class)->seedFiles([
        database_path('data/sunat/catalogs/10.json'),
        database_path('data/sunat/catalogs/12.json'),
    ]);

    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA DOC SUNAT '.$suffix,
        'ruc' => '20'.str_pad((string) crc32('DOC'.$suffix), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE DOC SUNAT '.$suffix,
        'document_type' => 'RUC',
        'document_number' => '10'.str_pad((string) crc32('CLI-DOC'.$suffix), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => substr('D'.$suffix, 0, 10),
        'description' => 'SOLES DOC SUNAT '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('WD'.$suffix, 0, 20),
        'name' => 'ALMACÉN DOC SUNAT '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'sunat_establishment_code' => '0001',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('U'.$suffix, 0, 10),
        'description' => 'UNIDAD DOC SUNAT '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA DOC SUNAT '.$suffix,
        'code' => substr('CD'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('AD'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO DOC SUNAT '.$suffix,
        'billing_name' => 'ARTÍCULO DOC SUNAT '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => true,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '15'.str_pad((string) crc32('SUP-DOC'.$suffix), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR DOC SUNAT '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'OC-DOC-'.$suffix,
        'company_id' => $companyId,
        'customer_id' => $customerId,
        'order_type' => 'articles',
        'currency_id' => $currencyId,
        'status' => CustomerPurchaseOrder::STATUS_ENTERED,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderItemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $orderId,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO DOC SUNAT '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 10,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-DOC-'.$suffix,
        'entry_mode' => 'supplier_invoice',
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'document_type' => $documentType,
        'document_series' => 'F100',
        'document_number' => '00000001',
        'document_date' => '2026-09-01',
        'status' => 'registered',
    ]);
    $entryItem = WarehouseEntryItem::create([
        'warehouse_entry_id' => $entry->id,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO DOC SUNAT '.$suffix,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-'.$suffix,
        'quantity' => 10,
        'unit_price' => 20,
        'line_total' => 200,
        'status' => 'active',
    ]);
    DB::table('warehouse_entry_item_allocations')->insert([
        'warehouse_entry_id' => $entry->id,
        'warehouse_entry_item_id' => $entryItem->id,
        'customer_purchase_order_id' => $orderId,
        'customer_purchase_order_item_id' => $orderItemId,
        'article_id' => $articleId,
        'quantity_allocated' => 10,
        'unit_cost' => 20,
        'total_cost' => 200,
        'allocation_type' => 'customer_order',
        'status' => 'active',
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return compact(
        'user', 'companyId', 'customerId', 'currencyId', 'warehouseId', 'unitId',
        'articleId', 'articleCode', 'orderItemId', 'entry'
    ) + ['order' => CustomerPurchaseOrder::findOrFail($orderId)];
}

function registerSunatDocumentEntry(array $fixture): WarehouseKardexMovement
{
    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($fixture['entry']);

    return WarehouseKardexMovement::query()->where('operation_type', 'warehouse_entry')->sole();
}

function confirmSunatDocumentDispatch(array $fixture)
{
    registerSunatDocumentEntry($fixture);
    $stock = WarehouseStock::query()->sole();
    $service = app(WarehouseDispatchService::class);
    $draft = $service->createDraft($fixture['order'], [
        'warehouse_id' => $fixture['warehouseId'],
        'dispatch_date' => '2026-09-02 10:00:00',
        'responsible_user_id' => $fixture['user']->id,
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'customer_purchase_order_item_id' => $fixture['orderItemId'],
            'warehouse_stock_id' => $stock->id,
            'quantity' => 4,
        ]],
    ]);

    return $service->confirm($draft);
}

function sunatDocumentSnapshotState(WarehouseKardexMovement $movement): array
{
    return [
        'document_date_snapshot' => $movement->document_date_snapshot?->toDateString(),
        'sunat_document_type_code_snapshot' => $movement->sunat_document_type_code_snapshot,
        'document_series' => $movement->document_series,
        'document_number' => $movement->document_number,
        'sunat_operation_type_code_snapshot' => $movement->sunat_operation_type_code_snapshot,
    ];
}


it('normaliza el alias acentuado de guia al codigo 09 de Tabla 10', function () {
    sunatDocumentOperationFixture('GUIDEALIAS');
    $service = app(WarehouseKardexService::class);

    expect($service->resolveSunatDocumentTypeCode('GUÍA'))->toBe('09')
        ->and($service->resolveSunatDocumentTypeCode('Guía de remisión'))->toBe('09')
        ->and($service->resolveSunatDocumentTypeCode('GUIA_DE_REMISION_REMITENTE'))->toBe('09');
});

it('registra fecha documento Tabla 10 y Tabla 12 en factura directa sin alterar PPM', function () {
    $fixture = sunatDocumentOperationFixture('INVOICE');
    registerSunatDocumentEntry($fixture);
    $invoice = ElectronicInvoice::create([
        'company_id' => $fixture['companyId'],
        'customer_id' => $fixture['customerId'],
        'warehouse_id' => $fixture['warehouseId'],
        'currency_id' => $fixture['currencyId'],
        'document_type' => '01',
        'serie' => 'F001',
        'correlativo' => '00000025',
        'full_number' => 'F001-00000025',
        'issue_date' => '2026-09-05',
        'client_name' => 'CLIENTE DOC SUNAT',
        'status' => 'generated',
    ]);
    $invoice->items()->create([
        'article_id' => $fixture['articleId'],
        'item_number' => 1,
        'product_code' => $fixture['articleCode'],
        'description' => 'ARTÍCULO DOC SUNAT',
        'unit_code' => 'NIU',
        'quantity' => 4,
        'unit_value' => 20,
        'unit_price' => 23.6,
        'lot_number' => 'LOTE-INVOICE',
        'status' => 'ACTIVE',
    ]);

    app(WarehouseKardexService::class)->registerExitFromElectronicInvoice($invoice);
    $movement = WarehouseKardexMovement::query()->where('operation_type', 'electronic_invoice')->sole();
    $pool = WarehouseValuationPool::query()->sole();

    expect($movement->document_date_snapshot->toDateString())->toBe('2026-09-05')
        ->and($movement->sunat_document_type_code_snapshot)->toBe('01')
        ->and($movement->document_series)->toBe('F001')
        ->and($movement->document_number)->toBe('00000025')
        ->and($movement->sunat_operation_type_code_snapshot)->toBe('01')
        ->and((float) $pool->current_quantity)->toBe(6.0)
        ->and((float) $pool->total_cost)->toBe(120.0);
});

it('clasifica el despacho como venta nacional sin inventar documento externo', function () {
    $fixture = sunatDocumentOperationFixture('DISPATCH');
    $service = app(WarehouseDispatchService::class);
    $dispatch = confirmSunatDocumentDispatch($fixture);
    $service->confirm($dispatch);
    $movement = WarehouseKardexMovement::query()->where('operation_type', 'customer_order_dispatch')->sole();

    expect($movement->sunat_operation_type_code_snapshot)->toBe('01')
        ->and($movement->document_date_snapshot?->toDateString())->toBe('2026-09-02')
        ->and($movement->sunat_document_type_code_snapshot)->toBeNull()
        ->and(WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch')->count())->toBe(1);
});

it('registra documento de compra y operación de entrada', function () {
    $fixture = sunatDocumentOperationFixture('ENTRY');
    $movement = registerSunatDocumentEntry($fixture);

    expect(Schema::hasColumns('warehouse_kardex_movements', [
        'document_date_snapshot',
        'sunat_document_type_code_snapshot',
        'sunat_operation_type_code_snapshot',
    ]))->toBeTrue()
        ->and($movement->document_date_snapshot->toDateString())->toBe('2026-09-01')
        ->and($movement->sunat_document_type_code_snapshot)->toBe('01')
        ->and($movement->document_series)->toBe('F100')
        ->and($movement->document_number)->toBe('00000001')
        ->and($movement->sunat_operation_type_code_snapshot)->toBe('02');
});

it('clasifica devolución y su reversa preservando el documento histórico', function () {
    $fixture = sunatDocumentOperationFixture('RETURN');
    $dispatch = confirmSunatDocumentDispatch($fixture);
    $dispatchItem = $dispatch->items()->sole();
    $service = app(CustomerReturnService::class);
    $draft = $service->createDraft($dispatch, [
        'idempotency_key' => (string) Str::uuid(),
        'return_date' => '2026-09-03 10:00:00',
        'reason' => 'customer_rejection',
        'received_by_user_id' => $fixture['user']->id,
        'items' => [[
            'warehouse_dispatch_item_id' => $dispatchItem->id,
            'quantity' => 2,
        ]],
    ]);
    $return = $service->confirm($draft);
    $returnMovement = $return->items()->sole()->kardexMovement;
    $originalDocument = $returnMovement->only([
        'document_date_snapshot',
        'sunat_document_type_code_snapshot',
        'document_series',
        'document_number',
    ]);
    $reversed = $service->reverse($return, 'Reversa documental focal');
    $reversal = $reversed->items()->sole()->reversalKardexMovement;

    expect($returnMovement->sunat_operation_type_code_snapshot)->toBe('24')
        ->and($returnMovement->document_date_snapshot)->toBeNull()
        ->and($returnMovement->sunat_document_type_code_snapshot)->toBeNull()
        ->and($reversal->sunat_operation_type_code_snapshot)->toBe('99')
        ->and($reversal->only(array_keys($originalDocument)))->toBe($originalDocument);
});

it('clasifica un ajuste positivo con Tabla 12', function () {
    $fixture = sunatDocumentOperationFixture('ADJIN');
    $entry = registerSunatDocumentEntry($fixture);
    $movement = app(WarehouseKardexService::class)->registerAdjustment(
        $entry->stock,
        $fixture['companyId'],
        'adjustment_in',
        2,
        25,
        'Ajuste positivo SUNAT',
        'sunat-adjustment-in'
    );

    expect($movement->sunat_operation_type_code_snapshot)->toBe('28')
        ->and($movement->sunat_document_type_code_snapshot)->toBeNull();
});

it('clasifica un ajuste negativo con Tabla 12', function () {
    $fixture = sunatDocumentOperationFixture('ADJOUT');
    $entry = registerSunatDocumentEntry($fixture);
    $movement = app(WarehouseKardexService::class)->registerAdjustment(
        $entry->stock,
        $fixture['companyId'],
        'adjustment_out',
        2,
        null,
        'Ajuste negativo SUNAT',
        'sunat-adjustment-out'
    );

    expect($movement->sunat_operation_type_code_snapshot)->toBe('28')
        ->and((float) $movement->quantity_out)->toBe(2.0);
});

it('preserva documento original en reversa y asigna operación técnica', function () {
    $fixture = sunatDocumentOperationFixture('REVERSAL');
    $original = registerSunatDocumentEntry($fixture);
    $originalDocument = sunatDocumentSnapshotState($original);

    app(WarehouseKardexService::class)->reverseWarehouseEntry($fixture['entry'], 'Reversa documental focal');
    $reversal = WarehouseKardexMovement::query()->where('operation_type', 'warehouse_entry_cancel')->sole();

    expect(sunatDocumentSnapshotState($original->fresh()))->toBe($originalDocument)
        ->and($reversal->document_date_snapshot->toDateString())->toBe('2026-09-01')
        ->and($reversal->sunat_document_type_code_snapshot)->toBe('01')
        ->and($reversal->document_series)->toBe('F100')
        ->and($reversal->document_number)->toBe('00000001')
        ->and($reversal->sunat_operation_type_code_snapshot)->toBe('99');
});

it('cambios posteriores de la fuente no alteran snapshots documentales', function () {
    $fixture = sunatDocumentOperationFixture('FROZEN');
    $movement = registerSunatDocumentEntry($fixture);
    $before = sunatDocumentSnapshotState($movement);

    $fixture['entry']->update([
        'document_type' => 'BOLETA',
        'document_series' => 'B999',
        'document_number' => '99999999',
        'document_date' => '2026-12-31',
    ]);
    DB::table('sunat_catalog_items')->where('catalog_code', '10')->where('item_code', '01')->update([
        'description' => 'DESCRIPCIÓN MODIFICADA',
    ]);

    expect(sunatDocumentSnapshotState($movement->fresh()))->toBe($before);
});

it('rechaza código documental inexistente y revierte stock pool y Kardex', function () {
    $fixture = sunatDocumentOperationFixture('INVALID', 'CODIGO_INEXISTENTE');

    expect(fn () => app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($fixture['entry']))
        ->toThrow(ValidationException::class, 'no tiene un código válido en la Tabla 10 SUNAT');

    expect(WarehouseStock::count())->toBe(0)
        ->and(WarehouseValuationPool::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('permite histórico anterior con nuevos snapshots nulos sin backfill', function () {
    $fixture = sunatDocumentOperationFixture('HISTORICAL');
    $movement = WarehouseKardexMovement::create([
        'movement_number' => 'KDX-HISTORICAL-001',
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'movement_date' => '2026-08-01 10:00:00',
        'movement_type' => 'entry',
        'operation_type' => 'legacy_import',
        'quantity_in' => 1,
        'quantity_out' => 0,
        'balance_quantity' => 1,
        'unit_cost' => 10,
        'total_cost_in' => 10,
        'total_cost_out' => 0,
        'average_unit_cost' => 10,
        'balance_total_cost' => 10,
        'status' => 'registered',
    ]);

    expect($movement->document_date_snapshot)->toBeNull()
        ->and($movement->sunat_document_type_code_snapshot)->toBeNull()
        ->and($movement->sunat_operation_type_code_snapshot)->toBeNull();
});

it('mantiene una matriz explícita de Tabla 12 para operaciones actuales y reservadas', function () {
    app(SunatCatalogSeeder::class)->seedFiles([
        database_path('data/sunat/catalogs/12.json'),
    ]);

    $service = app(WarehouseKardexService::class);
    $matrix = [
        ['exit', 'customer_order_dispatch', '01'],
        ['exit', 'electronic_invoice', '01'],
        ['entry', 'warehouse_entry', '02'],
        ['exit', 'warehouse_transfer_out', '11'],
        ['entry', 'initial_balance', '16'],
        ['entry', 'warehouse_transfer_in', '21'],
        ['entry', 'customer_return', '24'],
        ['exit', 'supplier_return', '25'],
        ['adjustment_in', 'manual_adjustment', '28'],
        ['linked_cost', 'warehouse_entry_linked_cost', '99'],
        ['reversal', 'warehouse_entry_cancel', '99'],
        ['cost_reversal', 'warehouse_entry_linked_cost_cancel', '99'],
        ['exit_reversal', 'electronic_invoice_cancel', '99'],
        ['exit_reversal', 'customer_order_dispatch_cancel', '99'],
        ['exit', 'customer_return_reversal', '99'],
    ];

    foreach ($matrix as [$movementType, $operationType, $expectedCode]) {
        expect($service->resolveSunatOperationTypeCode($movementType, $operationType))
            ->toBe($expectedCode);
    }
});

it('rechaza operaciones sin mapeo y conserva el fallback legacy de ajustes', function () {
    app(SunatCatalogSeeder::class)->seedFiles([
        database_path('data/sunat/catalogs/12.json'),
    ]);

    $service = app(WarehouseKardexService::class);

    expect($service->resolveSunatOperationTypeCode('adjustment_out', 'legacy_adjustment'))
        ->toBe('28');

    expect(fn () => $service->resolveSunatOperationTypeCode('entry', 'operacion_no_definida'))
        ->toThrow(ValidationException::class, 'no tiene un código definido en la Tabla 12 SUNAT');
});

