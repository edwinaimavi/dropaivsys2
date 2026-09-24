<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('public');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['admin.warehouse-entries.show', 'admin.warehouse-entries.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->user = User::factory()->create();
    $this->user->givePermissionTo([
        'admin.warehouse-entries.show',
        'admin.warehouse-entries.update',
    ]);
    $this->actingAs($this->user);

    $this->company = Company::create([
        'business_name' => 'EMPRESA FECHA MOVIMIENTO S.A.C.',
        'ruc' => '20515151518',
        'status' => true,
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->supplier = Supplier::create([
        'ruc' => '20626262628',
        'business_name' => 'PROVEEDOR FECHA MOVIMIENTO S.A.C.',
        'supplier_type' => 'BIENES',
        'payment_condition' => 'CRÉDITO',
        'status' => 'ACTIVE',
    ]);
    $category = Category::create([
        'code' => 'CAT-FECHA-MOV',
        'description' => 'PRODUCTOS FECHA MOVIMIENTO',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
    ]);
    $this->unit = Unit::create([
        'abbreviation' => 'UND-FM',
        'description' => 'UNIDAD FECHA MOVIMIENTO',
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'decimal_quantity' => false,
        'status' => 'ACTIVE',
    ]);
    $this->warehouse = Warehouse::create([
        'code' => 'ALM-FECHA-MOV',
        'name' => 'ALMACÉN FECHA MOVIMIENTO',
        'status' => 'ACTIVE',
    ]);
    $this->user->companies()->attach($this->company->id);
    $this->company->warehouses()->attach($this->warehouse->id, [
        'sunat_establishment_code' => '0001',
        'is_active' => true,
    ]);
    $this->article = Article::create([
        'code' => 'ART-FECHA-MOV',
        'category_id' => $category->id,
        'unit_id' => $this->unit->id,
        'legal_name' => 'ARTÍCULO FECHA MOVIMIENTO',
        'billing_name' => 'ARTÍCULO FECHA MOVIMIENTO',
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        'has_batch' => false,
        'has_expiration' => false,
        ...testSunatInventoryArticleFields('ART-FECHA-MOV'),
        'status' => 'ACTIVE',
    ]);
    $this->entry = WarehouseEntry::create([
        'entry_number' => 'ING-FECHA-MOV-001',
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $this->warehouse->id,
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'exchange_rate' => 1,
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_number' => 'FM-001',
        'document_date' => '2026-09-03',
        'movement_date' => '2026-09-03 16:20:00',
        'payment_method' => 'transferencia',
        'payment_condition' => 'credito',
        'credit_days' => 30,
        'generate_account_payable' => true,
        'payable_amount' => 1000,
        'expected_payment_date' => '2026-10-03',
        'affect_igv' => false,
        'subtotal' => 0,
        'igv' => 0,
        'grand_total' => 1000,
        'status' => 'registered',
        'created_by' => $this->user->id,
    ]);
    $this->item = $this->entry->items()->create([
        'article_id' => $this->article->id,
        'article_code' => $this->article->code,
        'billing_name_snapshot' => $this->article->billing_name,
        'unit_id' => $this->unit->id,
        'quantity' => 100,
        'unit_price' => 10,
        'subtotal' => 0,
        'tax_amount' => 0,
        'line_total' => 1000,
        'status' => 'active',
    ]);
});

it('devuelve la fecha propia del ingreso sin conversión de zona horaria', function () {
    $movementDate = $this->getJson(route('admin.warehouse-entries.show', $this->entry))
        ->assertOk()
        ->json('data.movement_date');

    expect($movementDate)->toBe('2026-09-03 16:20:00')
        ->and($movementDate)->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

it('recupera del Kardex la fecha de un ingreso histórico con movement date nulo', function () {
    registerMovementDateEntryKardex($this);
    DB::table('warehouse_entries')->where('id', $this->entry->id)->update(['movement_date' => null]);

    $this->getJson(route('admin.warehouse-entries.show', $this->entry))
        ->assertOk()
        ->assertJsonPath('data.movement_date', '2026-09-03 16:20:00');
});

it('regulariza la fecha recuperada sin reconstruir Kardex stock ni pool', function () {
    registerMovementDateEntryKardex($this);
    DB::table('warehouse_entries')->where('id', $this->entry->id)->update(['movement_date' => null]);
    $movementSnapshots = WarehouseKardexMovement::query()->orderBy('id')->get()
        ->map(fn (WarehouseKardexMovement $movement) => $movement->getAttributes())->all();
    $stockSnapshots = WarehouseStock::query()->orderBy('id')->get()
        ->map(fn (WarehouseStock $stock) => $stock->getAttributes())->all();
    $poolSnapshots = WarehouseValuationPool::query()->orderBy('id')->get()
        ->map(fn (WarehouseValuationPool $pool) => $pool->getAttributes())->all();

    $payload = movementDateEntryUpdatePayload($this, '2026-09-03T16:20');
    $this->putJson(route('admin.warehouse-entries.update', $this->entry), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect($this->entry->fresh()->movement_date?->format('Y-m-d H:i:s'))->toBe('2026-09-03 16:20:00')
        ->and(WarehouseKardexMovement::query()->orderBy('id')->get()
            ->map(fn (WarehouseKardexMovement $movement) => $movement->getAttributes())->all())->toBe($movementSnapshots)
        ->and(WarehouseStock::query()->orderBy('id')->get()
            ->map(fn (WarehouseStock $stock) => $stock->getAttributes())->all())->toBe($stockSnapshots)
        ->and(WarehouseValuationPool::query()->orderBy('id')->get()
            ->map(fn (WarehouseValuationPool $pool) => $pool->getAttributes())->all())->toBe($poolSnapshots)
        ->and(WarehouseKardexMovement::where('operation_type', 'warehouse_entry_cancel')->count())->toBe(0);
});

it('no inventa fecha cuando el ingreso y el Kardex no tienen movement date', function () {
    DB::table('warehouse_entries')->where('id', $this->entry->id)->update(['movement_date' => null]);

    $this->getJson(route('admin.warehouse-entries.show', $this->entry))
        ->assertOk()
        ->assertJsonPath('data.movement_date', null);
});

it('conserva el comportamiento de actualización cuando el ingreso ya tiene fecha', function () {
    registerMovementDateEntryKardex($this);
    $movementSnapshots = WarehouseKardexMovement::query()->orderBy('id')->get()
        ->map(fn (WarehouseKardexMovement $movement) => $movement->getAttributes())->all();

    $this->putJson(
        route('admin.warehouse-entries.update', $this->entry),
        movementDateEntryUpdatePayload($this, '2026-09-03T16:20')
    )->assertOk()->assertJsonPath('status', 'success');

    expect($this->entry->fresh()->movement_date?->format('Y-m-d H:i:s'))->toBe('2026-09-03 16:20:00')
        ->and(WarehouseKardexMovement::query()->orderBy('id')->get()
            ->map(fn (WarehouseKardexMovement $movement) => $movement->getAttributes())->all())->toBe($movementSnapshots);
});

function registerMovementDateEntryKardex(object $test): void
{
    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry(
        $test->entry->fresh(['supplier', 'currency', 'items.article', 'items.unit', 'items.lots'])
    );
}

function movementDateEntryUpdatePayload(object $test, string $movementDate): array
{
    return [
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $test->warehouse->id,
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'currency_id' => $test->currency->id,
        'exchange_rate' => 1,
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_number' => 'FM-001',
        'document_date' => '2026-09-03',
        'movement_date' => $movementDate,
        'payment_method' => 'transferencia',
        'payment_condition' => 'credito',
        'credit_days' => 30,
        'generate_account_payable' => 1,
        'affect_igv' => 0,
        'items' => [[
            'id' => (string) $test->item->id,
            'article_id' => (string) $test->article->id,
            'article_code' => $test->article->code,
            'billing_name_snapshot' => $test->article->billing_name,
            'unit_id' => (string) $test->unit->id,
            'quantity' => '100.00',
            'unit_price' => '10.000000',
            'lots' => [],
        ]],
    ];
}
