<?php

use App\Models\CustomerPurchaseOrder;
use App\Models\Document;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Services\WarehouseDispatchService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function warehouseDispatchDocumentFixture(string $suffix = 'DOC', float $quantity = 10): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'DROPAIV '.$suffix,
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica',
        'business_name' => 'CLIENTE '.$suffix,
        'document_type' => 'RUC',
        'document_number' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_by' => $user->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'D'.substr($suffix, 0, 4),
        'description' => 'SOLES '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'U'.substr($suffix, 0, 4),
        'description' => 'UNIDAD '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA '.$suffix,
        'code' => 'C'.substr($suffix, 0, 5),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = 'A'.substr($suffix, 0, 5);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO '.$suffix,
        'billing_name' => 'ARTÍCULO '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => false,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'W'.substr($suffix, 0, 5),
        'name' => 'ALMACÉN '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'PO-'.$suffix,
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
        'billing_name_snapshot' => 'ARTÍCULO '.$suffix,
        'unit_id' => $unitId,
        'quantity' => $quantity,
        'status' => 'active',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-'.$suffix,
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
        'billing_name_snapshot' => 'ARTÍCULO '.$suffix,
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

    return [
        'user' => $user,
        'order' => CustomerPurchaseOrder::findOrFail($orderId),
        'item_id' => $itemId,
        'stock' => $stock,
        'warehouse_id' => $warehouseId,
    ];
}

function warehouseDispatchDocumentPayload(array $fixture, float $quantity = 1): array
{
    return [
        'warehouse_id' => $fixture['warehouse_id'],
        'dispatch_date' => now()->format('Y-m-d H:i:s'),
        'responsible_user_id' => $fixture['user']->id,
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'customer_purchase_order_item_id' => $fixture['item_id'],
            'warehouse_stock_id' => $fixture['stock']->id,
            'quantity' => $quantity,
        ]],
    ];
}

function createConfirmedWarehouseDispatchWithDocuments(
    WarehouseDispatchService $service,
    CustomerPurchaseOrder $order,
    array $payload
): WarehouseDispatch {
    return $service->confirm($service->createDraft($order, $payload));
}

function grantWarehouseDispatchDocumentPermissions(User $user, bool $dispatch = true): void
{
    foreach ([
        'admin.customer-purchase-orders.dispatch.documents.view',
        'admin.customer-purchase-orders.dispatch.documents.manage',
        ...($dispatch ? ['admin.customer-purchase-orders.dispatch'] : []),
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }
}

beforeEach(function () {
    Storage::fake('public');
});

it('permite crear un despacho sin documentos', function () {
    $fixture = warehouseDispatchDocumentFixture('ZERO');
    Permission::findOrCreate('admin.customer-purchase-orders.dispatch', 'web');
    $fixture['user']->givePermissionTo('admin.customer-purchase-orders.dispatch');

    $this->postJson(
        route('admin.customer-purchase-orders.dispatches.store', $fixture['order']),
        warehouseDispatchDocumentPayload($fixture)
    )->assertCreated();

    expect(WarehouseDispatch::count())->toBe(1)
        ->and(Document::where('documentable_type', WarehouseDispatch::class)->count())->toBe(0);
});

it('adjunta un PDF al crear y lo relaciona exactamente con el despacho', function () {
    $fixture = warehouseDispatchDocumentFixture('ONE');
    grantWarehouseDispatchDocumentPermissions($fixture['user']);
    $payload = warehouseDispatchDocumentPayload($fixture);
    $payload['documents'] = [[
        'type' => 'dispatch_guide',
        'description' => 'Guía de prueba',
        'file' => UploadedFile::fake()->create('guia.pdf', 120, 'application/pdf'),
    ]];

    $this->withHeader('Accept', 'application/json')
        ->post(route('admin.customer-purchase-orders.dispatches.store', $fixture['order']), $payload)
        ->assertCreated();

    $dispatch = WarehouseDispatch::firstOrFail();
    $document = Document::firstOrFail();
    expect($dispatch->documents()->count())->toBe(1)
        ->and($document->documentable_type)->toBe(WarehouseDispatch::class)
        ->and($document->documentable_id)->toBe($dispatch->id)
        ->and($document->original_name)->toBe('guia.pdf')
        ->and($document->creator?->is($fixture['user']))->toBeTrue();
    Storage::disk('public')->assertExists($document->file_path);
});

it('no duplica documentos ni efectos físicos al repetir la misma clave idempotente', function () {
    $fixture = warehouseDispatchDocumentFixture('IDEMP');
    grantWarehouseDispatchDocumentPermissions($fixture['user']);
    $payload = warehouseDispatchDocumentPayload($fixture, 2);
    $payload['documents'] = [[
        'type' => 'dispatch_guide',
        'file' => UploadedFile::fake()->create('primera.pdf', 40, 'application/pdf'),
    ]];
    $url = route('admin.customer-purchase-orders.dispatches.store', $fixture['order']);

    $this->withHeader('Accept', 'application/json')->post($url, $payload)->assertCreated();
    $stockAfterFirst = $fixture['stock']->fresh()->current_quantity;
    $kardexAfterFirst = WarehouseKardexMovement::count();
    $payload['documents'][0]['file'] = UploadedFile::fake()->create('reintento.pdf', 40, 'application/pdf');
    $this->withHeader('Accept', 'application/json')->post($url, $payload)->assertCreated();

    expect(WarehouseDispatch::count())->toBe(1)
        ->and(Document::count())->toBe(1)
        ->and($fixture['stock']->fresh()->current_quantity)->toBe($stockAfterFirst)
        ->and(WarehouseKardexMovement::count())->toBe($kardexAfterFirst);
});

it('acepta múltiples documentos PDF e imagen al crear', function () {
    $fixture = warehouseDispatchDocumentFixture('MULTI');
    grantWarehouseDispatchDocumentPermissions($fixture['user']);
    $payload = warehouseDispatchDocumentPayload($fixture);
    $payload['documents'] = [
        ['type' => 'delivery_receipt', 'file' => UploadedFile::fake()->create('constancia.pdf', 80, 'application/pdf')],
        ['type' => 'photo_evidence', 'file' => UploadedFile::fake()->image('entrega.webp')],
    ];

    $this->withHeader('Accept', 'application/json')
        ->post(route('admin.customer-purchase-orders.dispatches.store', $fixture['order']), $payload)
        ->assertCreated();

    expect(WarehouseDispatch::firstOrFail()->documents()->count())->toBe(2)
        ->and(Document::pluck('mime_type')->contains(fn ($mime) => str_starts_with($mime, 'image/')))->toBeTrue();
});

it('rechaza extensiones no permitidas y archivos mayores a 10 MB', function () {
    $fixture = warehouseDispatchDocumentFixture('INVALID');
    grantWarehouseDispatchDocumentPermissions($fixture['user']);

    foreach ([
        UploadedFile::fake()->create('programa.exe', 10, 'application/octet-stream'),
        UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
    ] as $file) {
        $payload = warehouseDispatchDocumentPayload($fixture);
        $payload['documents'] = [['type' => 'other', 'file' => $file]];
        $this->withHeader('Accept', 'application/json')
            ->post(route('admin.customer-purchase-orders.dispatches.store', $fixture['order']), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documents.0.file');
    }

    expect(WarehouseDispatch::count())->toBe(0)
        ->and(Document::count())->toBe(0);
});

it('agrega documentos después de confirmar sin tocar stock Kardex estado OC ni detalle', function () {
    $fixture = warehouseDispatchDocumentFixture('AFTER');
    $dispatch = createConfirmedWarehouseDispatchWithDocuments(app(WarehouseDispatchService::class),
        $fixture['order'],
        warehouseDispatchDocumentPayload($fixture, 4)
    );
    grantWarehouseDispatchDocumentPermissions($fixture['user'], false);
    $before = [
        'stock' => $fixture['stock']->fresh()->only(['current_quantity', 'reserved_quantity', 'average_unit_cost', 'total_cost']),
        'kardex' => WarehouseKardexMovement::count(),
        'order_status' => $fixture['order']->fresh()->status,
        'items' => $dispatch->items()->count(),
        'dispatch_status' => $dispatch->status,
    ];

    $this->withHeader('Accept', 'application/json')->post(
        route('admin.customer-purchase-orders.dispatches.documents.store', [$fixture['order'], $dispatch]),
        ['documents' => [[
            'type' => 'signed_receipt',
            'description' => 'Cargo firmado por el cliente',
            'file' => UploadedFile::fake()->create('cargo.pdf', 90, 'application/pdf'),
        ]]]
    )->assertCreated()->assertJsonPath('data.count', 1);

    expect($fixture['stock']->fresh()->only(array_keys($before['stock'])))->toBe($before['stock'])
        ->and(WarehouseKardexMovement::count())->toBe($before['kardex'])
        ->and($fixture['order']->fresh()->status)->toBe($before['order_status'])
        ->and($dispatch->fresh()->status)->toBe($before['dispatch_status'])
        ->and($dispatch->items()->count())->toBe($before['items']);
});

it('permite documentos en draft sin confirmar el despacho', function () {
    $fixture = warehouseDispatchDocumentFixture('DRAFT');
    $dispatch = app(WarehouseDispatchService::class)->createDraft(
        $fixture['order'],
        warehouseDispatchDocumentPayload($fixture, 1)
    );
    grantWarehouseDispatchDocumentPermissions($fixture['user'], false);

    $this->withHeader('Accept', 'application/json')->post(
        route('admin.customer-purchase-orders.dispatches.documents.store', [$fixture['order'], $dispatch]),
        ['documents' => [[
            'type' => 'other',
            'file' => UploadedFile::fake()->create('borrador.pdf', 50, 'application/pdf'),
        ]]]
    )->assertCreated();

    expect($dispatch->fresh()->status)->toBe(WarehouseDispatch::STATUS_DRAFT);
});

it('aísla los documentos por despacho y protege la ruta nested', function () {
    $first = warehouseDispatchDocumentFixture('ISO1');
    $firstDispatch = app(WarehouseDispatchService::class)->createDraft($first['order'], warehouseDispatchDocumentPayload($first));
    grantWarehouseDispatchDocumentPermissions($first['user'], false);
    $this->withHeader('Accept', 'application/json')->post(
        route('admin.customer-purchase-orders.dispatches.documents.store', [$first['order'], $firstDispatch]),
        ['documents' => [['type' => 'other', 'file' => UploadedFile::fake()->create('uno.pdf', 20, 'application/pdf')]]]
    )->assertCreated();

    $second = warehouseDispatchDocumentFixture('ISO2');
    $secondDispatch = app(WarehouseDispatchService::class)->createDraft($second['order'], warehouseDispatchDocumentPayload($second));
    grantWarehouseDispatchDocumentPermissions($second['user'], false);

    $this->getJson(route('admin.customer-purchase-orders.dispatches.documents.index', [$second['order'], $secondDispatch]))
        ->assertOk()
        ->assertJsonCount(0, 'data.documents');
    $this->getJson(route('admin.customer-purchase-orders.dispatches.documents.index', [$first['order'], $secondDispatch]))
        ->assertNotFound();
    $foreignDocument = Document::firstOrFail();
    $this->get(route('admin.customer-purchase-orders.dispatches.documents.show', [
        $second['order'], $secondDispatch, $foreignDocument,
    ]))->assertNotFound();
});

it('responde 403 a un usuario sin permiso documental', function () {
    $fixture = warehouseDispatchDocumentFixture('DENY');
    $dispatch = app(WarehouseDispatchService::class)->createDraft($fixture['order'], warehouseDispatchDocumentPayload($fixture));

    $this->getJson(route('admin.customer-purchase-orders.dispatches.documents.index', [$fixture['order'], $dispatch]))
        ->assertForbidden();
});

it('retira lógicamente un documento con auditoría sin borrar el archivo ni efectos físicos', function () {
    $fixture = warehouseDispatchDocumentFixture('DELETE');
    $dispatch = app(WarehouseDispatchService::class)->createDraft($fixture['order'], warehouseDispatchDocumentPayload($fixture, 2));
    grantWarehouseDispatchDocumentPermissions($fixture['user'], false);
    $this->withHeader('Accept', 'application/json')->post(
        route('admin.customer-purchase-orders.dispatches.documents.store', [$fixture['order'], $dispatch]),
        ['documents' => [['type' => 'other', 'file' => UploadedFile::fake()->create('retiro.pdf', 20, 'application/pdf')]]]
    )->assertCreated();
    $document = Document::firstOrFail();
    $beforeStock = $fixture['stock']->fresh()->only(['current_quantity', 'reserved_quantity', 'total_cost']);
    $beforeKardex = WarehouseKardexMovement::count();
    $beforeStatus = $fixture['order']->fresh()->status;

    $this->deleteJson(route('admin.customer-purchase-orders.dispatches.documents.destroy', [
        $fixture['order'], $dispatch, $document,
    ]))->assertOk()->assertJsonPath('data.count', 0);

    $deleted = Document::withTrashed()->findOrFail($document->id);
    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->status)->toBe('INACTIVE')
        ->and($deleted->deleted_by)->toBe($fixture['user']->id)
        ->and($fixture['stock']->fresh()->only(array_keys($beforeStock)))->toBe($beforeStock)
        ->and(WarehouseKardexMovement::count())->toBe($beforeKardex)
        ->and($fixture['order']->fresh()->status)->toBe($beforeStatus);
    Storage::disk('public')->assertExists($deleted->file_path);
});

it('mantiene visible el documento legacy sin crear duplicados al consultarlo', function () {
    $fixture = warehouseDispatchDocumentFixture('LEGACY');
    $dispatch = app(WarehouseDispatchService::class)->createDraft($fixture['order'], warehouseDispatchDocumentPayload($fixture));
    $legacyPath = 'warehouse-dispatches/documents/legacy.pdf';
    Storage::disk('public')->put($legacyPath, '%PDF legacy');
    $dispatch->update([
        'document_path' => $legacyPath,
        'document_name' => 'guia-legacy.pdf',
        'document_mime' => 'application/pdf',
    ]);
    grantWarehouseDispatchDocumentPermissions($fixture['user'], false);

    $url = route('admin.customer-purchase-orders.dispatches.documents.index', [$fixture['order'], $dispatch]);
    $this->getJson($url)->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.legacy_document.original_name', 'guia-legacy.pdf');
    $this->getJson($url)->assertOk()->assertJsonPath('data.count', 1);
    $this->get(route('admin.customer-purchase-orders.dispatches.document', [$fixture['order'], $dispatch]))->assertOk();

    expect(Document::where('documentable_type', WarehouseDispatch::class)->count())->toBe(0);
});
