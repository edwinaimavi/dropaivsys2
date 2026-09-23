<?php

use App\Models\BankMovement;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Document;
use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceItemDispatchAllocation;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseDispatchItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Services\CustomerReturnService;
use App\Services\InvoiceFromCustomerOrderService;
use App\Services\WarehouseDispatchService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function customerReturnFixture(float $quantity = 5, string $suffix = 'A', ?string $dispatchDate = null): array
{
    $user = User::factory()->create();
    Auth::login($user);
    $now = now();
    $companyId = DB::table('companies')->insertGetId(['business_name' => "EMPRESA DEV $suffix", 'ruc' => '20'.random_int(100000000, 999999999), 'status' => true, 'created_at' => $now, 'updated_at' => $now]);
    $user->companies()->attach($companyId);
    $customerId = DB::table('customers')->insertGetId(['person_type' => 'juridica', 'business_name' => "CLIENTE DEV $suffix", 'document_type' => 'RUC', 'document_number' => '20'.random_int(100000000, 999999999), 'status' => true, 'created_at' => $now, 'updated_at' => $now]);
    $currencyId = DB::table('currencies')->insertGetId(['code' => substr("P$suffix", 0, 3), 'description' => "SOLES $suffix", 'symbol' => 'S/', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    $catalog06Id = DB::table('sunat_catalogs')->where('code', '06')->value('id');
    if (! $catalog06Id) {
        $catalog06Id = DB::table('sunat_catalogs')->insertGetId(['code' => '06', 'name' => 'UNIDAD DE MEDIDA', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
    }
    $sunatUnitId = DB::table('sunat_catalog_items')->where('catalog_code', '06')->where('item_code', 'NIU')->value('id');
    if (! $sunatUnitId) {
        $sunatUnitId = DB::table('sunat_catalog_items')->insertGetId(['sunat_catalog_id' => $catalog06Id, 'catalog_code' => '06', 'item_code' => 'NIU', 'description' => 'UNIDAD (BIENES)', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    }
    $unitId = DB::table('units')->insertGetId(['abbreviation' => substr("U$suffix", 0, 10), 'description' => "UNIDAD $suffix", 'sunat_unit_item_id' => $sunatUnitId, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    $categoryId = DB::table('categories')->insertGetId(['description' => "CATEGORIA DEV $suffix", 'code' => substr("CR$suffix", 0, 20), 'type' => 'PRODUCTO COMERCIAL', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    $articleCode = substr("AR$suffix", 0, 20);
    $articleId = DB::table('articles')->insertGetId(['code' => $articleCode, 'category_id' => $categoryId, 'unit_id' => $unitId, 'legal_name' => "ARTICULO DEV $suffix", 'billing_name' => "ARTICULO DEV $suffix", 'item_kind' => 'product', 'is_inventory_item' => true, ...testSunatInventoryArticleFields($articleCode), 'has_batch' => true, 'has_expiration' => true, 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    $warehouseId = DB::table('warehouses')->insertGetId(['code' => substr("WD$suffix", 0, 20), 'name' => "ALMACEN DEV $suffix", 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('company_warehouses')->insert(['company_id' => $companyId, 'warehouse_id' => $warehouseId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
    $supplierId = DB::table('suppliers')->insertGetId(['ruc' => '20'.random_int(100000000, 999999999), 'business_name' => "PROVEEDOR DEV $suffix", 'supplier_type' => 'LOCAL', 'payment_condition' => 'CONTADO', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId(['code' => "P-DEV-$suffix", 'purchase_order_number' => "OC-DEV-$suffix", 'company_id' => $companyId, 'customer_id' => $customerId, 'order_type' => 'articles', 'currency_id' => $currencyId, 'status' => CustomerPurchaseOrder::STATUS_ATTENDED, 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
    $orderItemId = DB::table('customer_purchase_order_items')->insertGetId(['customer_purchase_order_id' => $orderId, 'article_id' => $articleId, 'billing_name_snapshot' => "ARTICULO DEV $suffix", 'unit_id' => $unitId, 'quantity' => $quantity, 'unit_price' => 50, 'line_total' => $quantity * 50, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    $entryId = DB::table('warehouse_entries')->insertGetId(['entry_number' => "ING-DEV-$suffix", 'entry_mode' => 'supplier_invoice', 'warehouse_id' => $warehouseId, 'company_id' => $companyId, 'supplier_id' => $supplierId, 'currency_id' => $currencyId, 'status' => 'registered', 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
    $entryItemId = DB::table('warehouse_entry_items')->insertGetId(['warehouse_entry_id' => $entryId, 'article_id' => $articleId, 'billing_name_snapshot' => "ARTICULO DEV $suffix", 'unit_id' => $unitId, 'quantity' => $quantity, 'unit_price' => 5, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('warehouse_entry_item_allocations')->insert(['warehouse_entry_id' => $entryId, 'warehouse_entry_item_id' => $entryItemId, 'customer_purchase_order_id' => $orderId, 'customer_purchase_order_item_id' => $orderItemId, 'article_id' => $articleId, 'quantity_allocated' => $quantity, 'unit_cost' => 5, 'total_cost' => $quantity * 5, 'allocation_type' => 'customer_order', 'status' => 'active', 'created_by' => $user->id, 'created_at' => $now, 'updated_at' => $now]);
    $stock = WarehouseStock::create(['stock_key' => "$companyId|$warehouseId|$articleId|LOTE-$suffix|2030-12-31", 'company_id' => $companyId, 'warehouse_id' => $warehouseId, 'article_id' => $articleId, 'unit_id' => $unitId, 'lot_number' => "LOTE-$suffix", 'expiration_date' => '2030-12-31', 'current_quantity' => 0, 'average_unit_cost' => 0, 'total_cost' => 0, 'status' => 'ACTIVE']);
    $dispatch = WarehouseDispatch::create(['company_id' => $companyId, 'dispatch_number' => "SAL-DEV-$suffix", 'idempotency_key' => (string) Str::uuid(), 'customer_purchase_order_id' => $orderId, 'warehouse_id' => $warehouseId, 'dispatch_date' => $dispatchDate ?? $now, 'dispatch_type' => WarehouseDispatch::TYPE_CUSTOMER_ORDER, 'status' => WarehouseDispatch::STATUS_CONFIRMED, 'confirmed_at' => $now, 'confirmed_by' => $user->id, 'created_by' => $user->id, 'updated_by' => $user->id]);
    $dispatchItem = WarehouseDispatchItem::create(['warehouse_dispatch_id' => $dispatch->id, 'customer_purchase_order_item_id' => $orderItemId, 'warehouse_stock_id' => $stock->id, 'article_id' => $articleId, 'unit_id' => $unitId, 'lot_number' => "LOTE-$suffix", 'expiration_date' => '2030-12-31', 'quantity' => $quantity, 'unit_cost' => 5, 'total_cost' => $quantity * 5, 'status' => WarehouseDispatchItem::STATUS_CONFIRMED]);
    $movement = WarehouseKardexMovement::create(['movement_number' => 'KDX-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT), 'company_id' => $companyId, 'warehouse_stock_id' => $stock->id, 'warehouse_id' => $warehouseId, 'article_id' => $articleId, 'unit_id' => $unitId, 'lot_number' => "LOTE-$suffix", 'expiration_date' => '2030-12-31', 'movement_date' => $now, 'movement_type' => 'exit', 'operation_type' => 'customer_order_dispatch', 'source_type' => WarehouseDispatch::class, 'source_id' => $dispatch->id, 'source_item_type' => WarehouseDispatchItem::class, 'source_item_id' => $dispatchItem->id, 'source_key' => "customer-dispatch:{$dispatch->id}:{$dispatchItem->id}:{$stock->id}", 'quantity_in' => 0, 'quantity_out' => $quantity, 'balance_quantity' => 0, 'unit_cost' => 5, 'total_cost_in' => 0, 'total_cost_out' => $quantity * 5, 'average_unit_cost' => 0, 'balance_total_cost' => 0, 'currency_id' => $currencyId, 'status' => 'registered', 'created_by' => $user->id, 'updated_by' => $user->id]);
    $dispatchItem->update(['kardex_movement_id' => $movement->id]);

    return compact('user', 'companyId', 'customerId', 'currencyId', 'orderId', 'orderItemId', 'warehouseId', 'articleId', 'stock', 'dispatch', 'dispatchItem', 'movement');
}

function customerReturnDraft(array $fixture, float $quantity, array $extra = []): CustomerReturn
{
    return app(CustomerReturnService::class)->createDraft($fixture['dispatch']->fresh(), array_merge([
        'idempotency_key' => (string) Str::uuid(), 'return_date' => now(),
        'reason' => 'customer_rejection', 'received_by_user_id' => $fixture['user']->id,
        'observation' => 'Producto apto para stock',
        'items' => [['warehouse_dispatch_item_id' => $fixture['dispatchItem']->id, 'quantity' => $quantity]],
    ], $extra));
}

function customerReturnInvoice(array $fixture, float $quantity = 5): ElectronicInvoice
{
    $invoice = ElectronicInvoice::create(['company_id' => $fixture['companyId'], 'customer_id' => $fixture['customerId'], 'customer_purchase_order_id' => $fixture['orderId'], 'currency_id' => $fixture['currencyId'], 'document_type' => '01', 'serie' => 'F001', 'correlativo' => random_int(1,9999), 'full_number' => 'F001-'.random_int(1000,9999), 'issue_date' => today(), 'client_name' => 'CLIENTE DEV', 'total_amount' => 250, 'paid_amount' => 100, 'pending_amount' => 150, 'payment_status' => 'partial', 'status' => ElectronicInvoice::STATUS_GENERATED, 'is_voided' => false, 'created_by' => $fixture['user']->id]);
    $item = $invoice->items()->create(['customer_purchase_order_item_id' => $fixture['orderItemId'], 'article_id' => $fixture['articleId'], 'item_number' => 1, 'product_code' => 'ART-DEV', 'description' => 'ARTICULO DEV', 'unit_code' => 'NIU', 'quantity' => $quantity, 'unit_value' => 50, 'unit_price' => 59, 'status' => 'ACTIVE']);
    ElectronicInvoiceItemDispatchAllocation::create(['electronic_invoice_item_id' => $item->id, 'warehouse_dispatch_item_id' => $fixture['dispatchItem']->id, 'quantity' => $quantity]);
    return $invoice;
}

it('bloquea una devolución nueva sin Tabla 13 sin dejar stock o Kardex parcial', function () {
    $fixture = customerReturnFixture(5, 'S13BLOCK');
    $return = customerReturnDraft($fixture, 2);
    DB::table('articles')->where('id', $fixture['articleId'])->update([
        'sunat_inventory_catalog_item_id' => null,
        'sunat_inventory_catalog_code' => null,
    ]);
    $stockBefore = $fixture['stock']->fresh()->getAttributes();
    $movementsBefore = WarehouseKardexMovement::count();

    expect(fn () => app(CustomerReturnService::class)->confirm($return))
        ->toThrow(ValidationException::class, 'El artículo no tiene configurado su catálogo y código de existencia SUNAT.');
    expect($fixture['stock']->fresh()->getAttributes())->toBe($stockBefore)
        ->and(WarehouseKardexMovement::count())->toBe($movementsBefore)
        ->and($return->fresh()->isDraft())->toBeTrue();
});

function grantCustomerReturnPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
}

it('A crea borrador numerado sin modificar stock Kardex ni OC', function () {
    $f = customerReturnFixture(5, 'A');
    $beforeMovement = WarehouseKardexMovement::count();
    $draft = customerReturnDraft($f, 5);
    expect($draft->status)->toBe('draft')->and($draft->return_number)->toMatch('/^DEV-\d{6}$/')
        ->and((float) $f['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and(WarehouseKardexMovement::count())->toBe($beforeMovement)
        ->and(CustomerPurchaseOrder::find($f['orderId'])->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED);
});

it('rechaza devolución si el artículo original no está clasificado como inventariable', function () {
    $f = customerReturnFixture(5, 'NOINV');
    DB::table('articles')->where('id', $f['articleId'])->update([
        'item_kind' => 'service',
        'is_inventory_item' => false,
    ]);
    $beforeMovements = WarehouseKardexMovement::count();

    expect(fn () => customerReturnDraft($f, 1))
        ->toThrow(ValidationException::class, 'no está clasificado como producto inventariable');
    expect(CustomerReturn::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe($beforeMovements)
        ->and((float) $f['stock']->fresh()->current_quantity)->toBe(0.0);
});

it('B C D E I J confirma total una sola vez al costo original y conserva la SAL', function () {
    $f = customerReturnFixture(5, 'BCDEIJ');$service = app(CustomerReturnService::class);$original = $f['movement']->fresh()->getAttributes();
    $return = $service->confirm(customerReturnDraft($f, 5));$firstCount = WarehouseKardexMovement::count();$service->confirm($return);
    $item = $return->items()->firstOrFail();$entry = $item->kardexMovement;
    expect((float) $f['stock']->fresh()->current_quantity)->toBe(5.0)->and((float) $f['stock']->fresh()->total_cost)->toBe(25.0)
        ->and((float) $f['stock']->fresh()->average_unit_cost)->toBe(5.0)->and((float) $item->unit_cost_snapshot)->toBe(5.0)
        ->and((float) $item->total_cost)->toBe(25.0)->and($entry->movement_type)->toBe('entry')
        ->and($entry->operation_type)->toBe('customer_return')->and(WarehouseKardexMovement::count())->toBe($firstCount)
        ->and($f['movement']->fresh()->getAttributes())->toBe($original)->and($f['dispatch']->fresh()->status)->toBe('confirmed')
        ->and(CustomerPurchaseOrder::find($f['orderId'])->status)->toBe(CustomerPurchaseOrder::STATUS_ENTERED);
});

it('F G H permite parciales exactas y rechaza sobredevolución', function () {
    $f = customerReturnFixture(10, 'FGH');$service=app(CustomerReturnService::class);
    $service->confirm(customerReturnDraft($f, 3));
    expect((float) $service->returnableQuantities($f['dispatch'])->get($f['dispatchItem']->id))->toBe(7.0);
    $service->confirm(customerReturnDraft($f, 7));
    expect((float) $service->returnableQuantities($f['dispatch'])->get($f['dispatchItem']->id))->toBe(0.0)
        ->and(fn()=>customerReturnDraft($f, 0.0001))->toThrow(ValidationException::class, 'supera el disponible');
});

it('bloquea confirmación de producto dañado o con incidencia de vencimiento', function (string $reason) {
    $f=customerReturnFixture(5, 'SAFE'.substr($reason,0,2));$draft=customerReturnDraft($f, 1, ['reason'=>$reason]);
    expect(fn()=>app(CustomerReturnService::class)->confirm($draft))->toThrow(ValidationException::class, 'cuarentena');
})->with(['damaged_product','expiration_or_lot']);

it('K bloquea reversa SAL con devolución confirmada', function () {
    $f=customerReturnFixture(5,'K');app(CustomerReturnService::class)->confirm(customerReturnDraft($f,2));
    expect(fn()=>app(WarehouseDispatchService::class)->reverse($f['dispatch'],'Reversa técnica inválida'))
        ->toThrow(ValidationException::class,'posee devoluciones de cliente confirmadas');
});

it('L borradores y canceladas no bloquean reversa SAL', function (bool $cancel) {
    $f=customerReturnFixture(5,$cancel?'LC':'LD');$return=customerReturnDraft($f,2);
    if($cancel) app(CustomerReturnService::class)->cancelDraft($return,'Borrador descartado');
    $reversed=app(WarehouseDispatchService::class)->reverse($f['dispatch'],'Corrección técnica de salida');
    expect($reversed->status)->toBe(WarehouseDispatch::STATUS_REVERSED)->and((float)$f['stock']->fresh()->current_quantity)->toBe(5.0);
})->with([false,true]);

it('M N permite devolución facturada sin tocar comprobante cobranza ni banco', function () {
    $f=customerReturnFixture(5,'MN');$invoice=customerReturnInvoice($f,5);$invoiceBefore=$invoice->fresh()->getAttributes();$bankCount=BankMovement::count();
    app(CustomerReturnService::class)->confirm(customerReturnDraft($f,2));
    expect($invoice->fresh()->getAttributes())->toBe($invoiceBefore)->and(BankMovement::count())->toBe($bankCount)
        ->and((float)$f['stock']->fresh()->current_quantity)->toBe(2.0);
});

it('O resta devoluciones confirmadas del máximo facturable', function () {
    $f=customerReturnFixture(10,'O');app(CustomerReturnService::class)->confirm(customerReturnDraft($f,4));
    $prepared=app(InvoiceFromCustomerOrderService::class)->prepare(CustomerPurchaseOrder::findOrFail($f['orderId']));
    expect((float)$prepared['items'][0]['dispatched_quantity'])->toBe(10.0)
        ->and((float)$prepared['items'][0]['returned_quantity'])->toBe(4.0)
        ->and((float)$prepared['items'][0]['pending_quantity'])->toBe(6.0);
});

it('P reversa DEV crea salida contraria y no borra la entrada original', function () {
    $f=customerReturnFixture(5,'P');$service=app(CustomerReturnService::class);$return=$service->confirm(customerReturnDraft($f,5));
    $original=$return->items()->firstOrFail()->kardexMovement;$attributes=$original->getAttributes();$reversed=$service->reverse($return,'Devolución registrada por error');
    $item=$reversed->items()->firstOrFail();
    expect($reversed->status)->toBe('reversed')->and((float)$f['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and($item->reversalKardexMovement->operation_type)->toBe('customer_return_reversal')
        ->and($item->reversalKardexMovement->movement_type)->toBe('exit')
        ->and($original->fresh()->getAttributes())->toBe($attributes)
        ->and(CustomerPurchaseOrder::find($f['orderId'])->status)->toBe(CustomerPurchaseOrder::STATUS_ATTENDED);
});

it('Q bloquea reversa DEV si el lote ya no tiene stock suficiente', function () {
    $f=customerReturnFixture(5,'Q');$service=app(CustomerReturnService::class);$return=$service->confirm(customerReturnDraft($f,5));
    $f['stock']->update(['current_quantity'=>2,'total_cost'=>10]);
    expect(fn()=>$service->reverse($return,'Intento sin saldo suficiente'))->toThrow(ValidationException::class,'stock suficiente');
});

it('R bloquea acceso cross-company con 404', function () {
    $f=customerReturnFixture(5,'R');$return=customerReturnDraft($f,1);$outsider=User::factory()->create();
    grantCustomerReturnPermissions($outsider,['devoluciones_clientes.ver']);
    $this->actingAs($outsider)->getJson(route('admin.customer-returns.show',$return))->assertNotFound();
});

it('S conserva documentos polimórficos de la devolución', function () {
    Storage::fake('public');$f=customerReturnFixture(5,'S');$return=customerReturnDraft($f,1);
    grantCustomerReturnPermissions($f['user'],['devoluciones_clientes.documentos']);
    $this->actingAs($f['user'])->post(route('admin.customer-returns.documents.store',$return),[
        'documents'=>[['type'=>'return_guide','description'=>'Guía del cliente','file'=>UploadedFile::fake()->create('guia.pdf',100,'application/pdf')]],
    ])->assertCreated();
    $document=Document::firstOrFail();
    expect($document->documentable_type)->toBe(CustomerReturn::class)->and($document->documentable_id)->toBe($return->id)
        ->and($document->status)->toBe('ACTIVE')->and(Storage::disk('public')->exists($document->file_path))->toBeTrue();
});

it('renderiza el módulo y entrega datos contextuales solo de empresas autorizadas', function () {
    $f=customerReturnFixture(5,'UI');$return=customerReturnDraft($f,1);
    grantCustomerReturnPermissions($f['user'],['devoluciones_clientes.ver','devoluciones_clientes.crear']);
    $this->actingAs($f['user'])->get(route('admin.customer-returns.index'))->assertOk()->assertSeeText('Devoluciones de clientes');
    $this->actingAs($f['user'])->getJson(route('admin.customer-returns.list'))->assertOk()->assertJsonFragment(['return_number'=>$return->return_number]);
    $this->actingAs($f['user'])->getJson(route('admin.customer-returns.dispatch-data',$f['dispatch']))
        ->assertOk()->assertJsonPath('data.items.0.returnable_quantity',5)->assertJsonPath('data.dispatch.warehouse_id',$f['warehouseId']);
});

it('presenta fecha y hora local histórica de SAL y DEV sin conversión UTC', function () {
    expect(config('app.timezone'))->toBe('America/Lima');
    $f=customerReturnFixture(5,'TZ','2026-09-09 10:18:00');
    grantCustomerReturnPermissions($f['user'],['devoluciones_clientes.ver','devoluciones_clientes.crear']);

    $this->actingAs($f['user'])
        ->getJson(route('admin.customer-returns.dispatch-data',$f['dispatch']))
        ->assertOk()
        ->assertJsonPath('data.dispatch.dispatch_date_local','2026-09-09T10:18')
        ->assertJsonPath('data.dispatch.dispatch_date_display','09/09/2026 10:18');

    $return=customerReturnDraft($f,1,['return_date'=>'2026-09-09 10:18:00']);
    $this->actingAs($f['user'])
        ->getJson(route('admin.customer-returns.show',$return))
        ->assertOk()
        ->assertJsonPath('data.return.return_date_local','2026-09-09T10:18')
        ->assertJsonPath('data.return.return_date_display','09/09/2026 10:18');

    $script=file_get_contents(resource_path('js/pages/customer-return.js'));
    expect($script)->toContain('dispatch.dispatch_date_display || crLocalDisplay(dispatch.dispatch_date_local)')
        ->and($script)->toContain("editing?.return_date_local || data.current_local_datetime")
        ->and($script)->not->toContain('toISOString()');
});

it('presenta el borrador relacionado con sus acciones contextuales en Despachos', function () {
    $fixture = customerReturnFixture(5, 'UIDRAFT');
    $return = customerReturnDraft($fixture, 5);
    grantCustomerReturnPermissions($fixture['user'], [
        'admin.customer-purchase-orders.show',
        'devoluciones_clientes.ver',
        'devoluciones_clientes.editar',
        'devoluciones_clientes.confirmar',
        'devoluciones_clientes.cancelar',
        'devoluciones_clientes.documentos',
    ]);

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-purchase-orders.show', $fixture['orderId']))
        ->assertOk()
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.id', $return->id)
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.status', CustomerReturn::STATUS_DRAFT)
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.total_quantity', 5)
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.can_view', true)
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.can_edit', true)
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.can_confirm', true)
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.can_cancel', true)
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.can_documents', true)
        ->assertJsonPath('data.warehouse_dispatches.0.customer_returns.0.can_reverse', false);
});

it('carga la DEV existente para edición con quantity 5 sin crear otra devolución', function () {
    $fixture = customerReturnFixture(5, 'UIEDIT');
    $return = customerReturnDraft($fixture, 5, [
        'reason' => 'customer_rejection',
        'reason_description' => 'Rechazo del cliente',
    ]);
    grantCustomerReturnPermissions($fixture['user'], [
        'devoluciones_clientes.ver',
        'devoluciones_clientes.editar',
    ]);
    $before = CustomerReturn::count();

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-returns.show', $return))
        ->assertOk()
        ->assertJsonPath('data.return.id', $return->id)
        ->assertJsonPath('data.return.reason', 'customer_rejection')
        ->assertJsonPath('data.return.items.0.quantity', '5.0000')
        ->assertJsonPath('data.actions.edit', true);

    $this->actingAs($fixture['user'])
        ->getJson(route('admin.customer-returns.dispatch-data', $fixture['dispatch']))
        ->assertOk()
        ->assertJsonPath('data.items.0.returnable_quantity', 5);

    expect(CustomerReturn::count())->toBe($before);
});

it('limita acciones de confirmed cancelled y reversed aunque el usuario posea todos los permisos', function () {
    $fixture = customerReturnFixture(5, 'UISTATE');
    $service = app(CustomerReturnService::class);
    $confirmed = $service->confirm(customerReturnDraft($fixture, 1));
    $cancelled = $service->cancelDraft(customerReturnDraft($fixture, 1), 'Borrador cancelado para prueba');
    $reversed = $service->reverse(
        $service->confirm(customerReturnDraft($fixture, 1)),
        'Devolución revertida para prueba'
    );
    grantCustomerReturnPermissions($fixture['user'], [
        'devoluciones_clientes.ver',
        'devoluciones_clientes.editar',
        'devoluciones_clientes.confirmar',
        'devoluciones_clientes.cancelar',
        'devoluciones_clientes.reversar',
        'devoluciones_clientes.documentos',
    ]);

    $this->actingAs($fixture['user'])->getJson(route('admin.customer-returns.show', $confirmed))
        ->assertOk()->assertJsonPath('data.actions.edit', false)->assertJsonPath('data.actions.confirm', false)
        ->assertJsonPath('data.actions.cancel', false)->assertJsonPath('data.actions.reverse', true);
    $this->actingAs($fixture['user'])->getJson(route('admin.customer-returns.show', $cancelled))
        ->assertOk()->assertJsonPath('data.actions.edit', false)->assertJsonPath('data.actions.confirm', false)
        ->assertJsonPath('data.actions.cancel', false)->assertJsonPath('data.actions.reverse', false);
    $this->actingAs($fixture['user'])->getJson(route('admin.customer-returns.show', $reversed))
        ->assertOk()->assertJsonPath('data.actions.edit', false)->assertJsonPath('data.actions.confirm', false)
        ->assertJsonPath('data.actions.cancel', false)->assertJsonPath('data.actions.reverse', false);
});

it('recalcula devuelto y entregado neto al confirmar la DEV existente', function () {
    $fixture = customerReturnFixture(5, 'UIQTY');
    $return = customerReturnDraft($fixture, 5);
    grantCustomerReturnPermissions($fixture['user'], [
        'admin.customer-purchase-orders.show',
        'devoluciones_clientes.confirmar',
    ]);

    $before = $this->actingAs($fixture['user'])->getJson(route('admin.customer-purchase-orders.show', $fixture['orderId']))->assertOk();
    expect((float) $before->json('data.warehouse_dispatches.0.returned_quantity'))->toBe(0.0)
        ->and((float) $before->json('data.warehouse_dispatches.0.net_delivered_quantity'))->toBe(5.0);

    $this->actingAs($fixture['user'])->postJson(route('admin.customer-returns.confirm', $return))->assertOk();

    $after = $this->actingAs($fixture['user'])->getJson(route('admin.customer-purchase-orders.show', $fixture['orderId']))->assertOk();
    expect((float) $after->json('data.warehouse_dispatches.0.returned_quantity'))->toBe(5.0)
        ->and((float) $after->json('data.warehouse_dispatches.0.net_delivered_quantity'))->toBe(0.0);
});

it('mantiene en frontend el CTA de continuación y el refresco namespaced sin crear otra DEV', function () {
    $purchaseOrderScript = file_get_contents(resource_path('js/pages/customer-purchase-order.js'));
    $returnScript = file_get_contents(resource_path('js/pages/customer-return.js'));

    expect($purchaseOrderScript)->toContain('Continuar devolución')
        ->and($purchaseOrderScript)->toContain("draftReturns.length === 1")
        ->and($purchaseOrderScript)->toContain("customer-return:changed.customerPurchaseOrder")
        ->and($returnScript)->toContain("if (id) form.append('_method', 'PUT')")
        ->and($returnScript)->toContain('Confirmar devolución ${ret.return_number}')
        ->and($returnScript)->toContain(".off('click.customerReturn'");
});

it('rechaza por backend la reversa sin motivo', function () {
    $fixture = customerReturnFixture(5, 'REVNOMOTIVE');
    $return = app(CustomerReturnService::class)->confirm(customerReturnDraft($fixture, 5));
    grantCustomerReturnPermissions($fixture['user'], ['devoluciones_clientes.reversar']);

    $this->actingAs($fixture['user'])
        ->postJson(route('admin.customer-returns.reverse', $return), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect($return->fresh()->status)->toBe(CustomerReturn::STATUS_CONFIRMED)
        ->and($return->fresh()->reversal_reason)->toBeNull();
});

it('rechaza por backend la reversa con un motivo formado solo por espacios', function () {
    $fixture = customerReturnFixture(5, 'REVSPACES');
    $return = app(CustomerReturnService::class)->confirm(customerReturnDraft($fixture, 5));
    grantCustomerReturnPermissions($fixture['user'], ['devoluciones_clientes.reversar']);

    $this->actingAs($fixture['user'])
        ->postJson(route('admin.customer-returns.reverse', $return), ['reason' => '     '])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('reason');

    expect($return->fresh()->status)->toBe(CustomerReturn::STATUS_CONFIRMED)
        ->and($return->fresh()->reversal_reason)->toBeNull();
});

it('audita una reversa válida y evita una segunda salida Kardex o cambios bancarios', function () {
    $fixture = customerReturnFixture(5, 'REVAUDIT');
    $service = app(CustomerReturnService::class);
    $return = $service->confirm(customerReturnDraft($fixture, 5));
    $originalMovement = $return->items()->firstOrFail()->kardexMovement;
    $originalAttributes = $originalMovement->getAttributes();
    $bankMovementsBefore = BankMovement::count();
    $reversalMovementsBefore = WarehouseKardexMovement::query()
        ->where('operation_type', 'customer_return_reversal')
        ->count();
    $reason = 'PRUEBA INTEGRAL - REVERSA DE DEVOLUCIÓN '.$return->return_number;
    grantCustomerReturnPermissions($fixture['user'], ['devoluciones_clientes.reversar']);

    $this->actingAs($fixture['user'])
        ->postJson(route('admin.customer-returns.reverse', $return), ['reason' => "  {$reason}  "])
        ->assertOk();

    $reversed = $return->fresh(['reversedBy', 'items.reversalKardexMovement']);
    $reversalMovement = $reversed->items->firstOrFail()->reversalKardexMovement;
    expect($reversed->status)->toBe(CustomerReturn::STATUS_REVERSED)
        ->and($reversed->reversal_reason)->toBe($reason)
        ->and($reversed->reversed_by_user_id)->toBe($fixture['user']->id)
        ->and($reversed->reversed_at)->not->toBeNull()
        ->and($reversalMovement)->not->toBeNull()
        ->and($reversalMovement->operation_type)->toBe('customer_return_reversal')
        ->and($reversalMovement->movement_type)->toBe('exit')
        ->and((float) $reversalMovement->quantity_out)->toBe(5.0)
        ->and((float) $reversalMovement->unit_cost)->toBe(5.0)
        ->and((float) $reversalMovement->total_cost_out)->toBe(25.0)
        ->and(WarehouseKardexMovement::query()->where('operation_type', 'customer_return_reversal')->count())->toBe($reversalMovementsBefore + 1)
        ->and(BankMovement::count())->toBe($bankMovementsBefore)
        ->and($originalMovement->fresh()->getAttributes())->toBe($originalAttributes);

    $movementCountAfterFirstReverse = WarehouseKardexMovement::count();
    $this->actingAs($fixture['user'])
        ->postJson(route('admin.customer-returns.reverse', $return), ['reason' => 'Segundo intento de reversa'])
        ->assertUnprocessable();

    expect(WarehouseKardexMovement::count())->toBe($movementCountAfterFirstReverse)
        ->and($return->fresh()->reversal_reason)->toBe($reason);
});

it('aclara en la presentación que la reversa deja sin efecto una devolución confirmada', function () {
    $script = file_get_contents(resource_path('js/pages/customer-return.js'));
    $purchaseOrderScript = file_get_contents(resource_path('js/pages/customer-purchase-order.js'));
    $kardexScript = file_get_contents(resource_path('js/pages/kardex.js'));

    expect($script)->toContain("target: alertTarget")
        ->and($script)->toContain("input: 'textarea'")
        ->and($script)->toContain("inputLabel: 'MOTIVO DE LA REVERSA *'")
        ->and($script)->toContain("inputPlaceholder: 'Indique por qué se está dejando sin efecto esta devolución'")
        ->and($script)->toContain("inputAttributes: { maxlength: 2000, rows: 5, 'aria-required': 'true' }")
        ->and($script)->not->toContain("inputAttributes: { disabled")
        ->and($script)->not->toContain("inputAttributes: { readonly")
        ->and($script)->toContain("String(value || '').trim()")
        ->and($script)->toContain('Debe indicar el motivo de la reversa.')
        ->and($script)->toContain('showLoaderOnConfirm: true')
        ->and($script)->toContain("title: 'Revertir devolución confirmada'")
        ->and($script)->toContain('Use esta opción únicamente para dejar sin efecto una devolución que ya fue confirmada e ingresó nuevamente al almacén.')
        ->and($script)->toContain('Esta opción NO se utiliza para registrar la devolución de un cliente.')
        ->and($script)->toContain("confirmButtonText: 'Revertir devolución'")
        ->and($script)->toContain("cancelButtonText: 'Cancelar'")
        ->and($script)->toContain('Anular / Revertir devolución')
        ->and($script)->toContain("reversed: ['REVERTIDA', 'dark']")
        ->and($script)->toContain('Devolución dejada sin efecto')
        ->and($purchaseOrderScript)->toContain('Anular / Revertir devolución')
        ->and($purchaseOrderScript)->not->toContain('>Reversar</button>')
        ->and($purchaseOrderScript)->toContain('Devolución dejada sin efecto')
        ->and($kardexScript)->toContain("customer_return_reversal: 'Anulación de devolución de cliente'");
});
