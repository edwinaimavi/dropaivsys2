<?php

use App\Models\Article;
use App\Models\BankMovement;
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
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('public');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.update', 'web');

    $this->user = User::factory()->create();
    $this->user->givePermissionTo('admin.warehouse-entries.update');
    $this->actingAs($this->user);

    $this->company = Company::create([
        'business_name' => 'EMPRESA UPDATE ADMINISTRATIVO S.A.C.',
        'ruc' => '20545454541',
        'status' => true,
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->supplier = Supplier::create([
        'ruc' => '20656565651',
        'business_name' => 'PROVEEDOR UPDATE ADMINISTRATIVO S.A.C.',
        'supplier_type' => 'BIENES',
        'payment_condition' => 'CRÉDITO',
        'status' => 'ACTIVE',
    ]);
    $category = Category::create([
        'code' => 'CAT-UPD-ADMIN',
        'description' => 'PRODUCTOS UPDATE ADMINISTRATIVO',
        'type' => 'PRODUCTO',
        'status' => 'ACTIVE',
    ]);
    $this->unit = Unit::create([
        'abbreviation' => 'UND',
        'description' => 'UNIDAD',
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'decimal_quantity' => false,
        'status' => 'ACTIVE',
    ]);
    $this->warehouse = Warehouse::create([
        'code' => 'ALM-UPD-ADMIN',
        'name' => 'ALMACÉN UPDATE ADMINISTRATIVO',
        'status' => 'ACTIVE',
    ]);
    $this->user->companies()->attach($this->company->id);
    $this->company->warehouses()->attach($this->warehouse->id, ['is_active' => true]);
    $this->article = Article::create([
        'code' => 'ART-UPD-ADMIN',
        'category_id' => $category->id,
        'unit_id' => $this->unit->id,
        'legal_name' => 'ARTÍCULO UPDATE ADMINISTRATIVO',
        'billing_name' => 'ARTÍCULO UPDATE ADMINISTRATIVO',
        'has_batch' => false,
        'has_expiration' => false,
        'status' => 'ACTIVE',
    ]);
    $this->entry = WarehouseEntry::create([
        'entry_number' => 'ING-UPD-ADMIN-001',
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $this->warehouse->id,
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_number' => 'ADMIN-001',
        'document_date' => '2026-08-20',
        'payment_method' => 'transferencia',
        'payment_condition' => 'credito',
        'credit_days' => 30,
        'generate_account_payable' => true,
        'payable_amount' => 1000,
        'expected_payment_date' => '2026-09-19',
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

it('actualiza observaciones con stock parcialmente consumido sin tocar inventario ni Kardex', function () {
    registerAdministrativeUpdateKardex($this);
    $stock = WarehouseStock::query()->sole();
    $stock->update(['current_quantity' => 60, 'total_cost' => 600, 'average_unit_cost' => 10]);
    $stockSnapshot = $stock->fresh()->getAttributes();
    $movement = entryAdministrativeKardexMovements($this->entry)->sole();
    $movementSnapshot = $movement->getAttributes();
    $bankMovementCount = BankMovement::count();

    $payload = warehouseEntryAdministrativePayload($this);
    $payload['observations'] = 'OBSERVACIÓN ADMINISTRATIVA ACTUALIZADA';

    $this->putJson(route('admin.warehouse-entries.update', $this->entry), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect((float) $stock->fresh()->current_quantity)->toBe(60.0)
        ->and($stock->fresh()->getAttributes())->toBe($stockSnapshot)
        ->and($movement->fresh()->getAttributes())->toBe($movementSnapshot)
        ->and(entryAdministrativeKardexMovements($this->entry)->count())->toBe(1)
        ->and((float) $this->item->fresh()->quantity)->toBe(100.0)
        ->and($this->entry->fresh()->observations)->toBe('OBSERVACIÓN ADMINISTRATIVA ACTUALIZADA')
        ->and(BankMovement::count())->toBe($bankMovementCount);
});

it('mantiene el bloqueo cuando cambia una cantidad y el stock ya fue consumido', function () {
    registerAdministrativeUpdateKardex($this);
    $stock = WarehouseStock::query()->sole();
    $stock->update(['current_quantity' => 60, 'total_cost' => 600, 'average_unit_cost' => 10]);
    $movement = entryAdministrativeKardexMovements($this->entry)->sole();
    $movementSnapshot = $movement->getAttributes();
    $payload = warehouseEntryAdministrativePayload($this);
    $payload['items'][0]['quantity'] = 90;

    $this->putJson(route('admin.warehouse-entries.update', $this->entry), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kardex')
        ->assertJsonPath('errors.kardex.0', 'No se puede revertir el ingreso porque el stock disponible es insuficiente.');

    expect((float) $stock->fresh()->current_quantity)->toBe(60.0)
        ->and((float) $this->item->fresh()->quantity)->toBe(100.0)
        ->and($movement->fresh()->getAttributes())->toBe($movementSnapshot)
        ->and(entryAdministrativeKardexMovements($this->entry)->count())->toBe(1);
});

it('reconstruye Kardex una sola vez cuando cambia una cantidad y existe stock completo', function () {
    registerAdministrativeUpdateKardex($this);
    $stock = WarehouseStock::query()->sole();
    $originalMovement = entryAdministrativeKardexMovements($this->entry)->sole();
    $payload = warehouseEntryAdministrativePayload($this);
    $payload['items'][0]['quantity'] = 90;

    $this->putJson(route('admin.warehouse-entries.update', $this->entry), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $movements = entryAdministrativeKardexMovements($this->entry);
    expect((float) $stock->fresh()->current_quantity)->toBe(90.0)
        ->and((float) $this->item->fresh()->quantity)->toBe(90.0)
        ->and($originalMovement->fresh()->status)->toBe('reversed')
        ->and($movements->where('operation_type', 'warehouse_entry_cancel')->count())->toBe(1)
        ->and($movements->where('operation_type', 'warehouse_entry')->where('status', 'registered')->count())->toBe(1)
        ->and($movements->count())->toBe(3);
});

it('trata Actualizar sin cambios como no-op de inventario y pagos', function () {
    registerAdministrativeUpdateKardex($this);
    $stock = WarehouseStock::query()->sole();
    $stockSnapshot = $stock->getAttributes();
    $movement = entryAdministrativeKardexMovements($this->entry)->sole();
    $movementSnapshot = $movement->getAttributes();

    $this->putJson(
        route('admin.warehouse-entries.update', $this->entry),
        warehouseEntryAdministrativePayload($this)
    )->assertOk()->assertJsonPath('status', 'success');

    expect($stock->fresh()->getAttributes())->toBe($stockSnapshot)
        ->and($movement->fresh()->getAttributes())->toBe($movementSnapshot)
        ->and(entryAdministrativeKardexMovements($this->entry)->count())->toBe(1)
        ->and(BankMovement::count())->toBe(0);
});

it('normaliza el orden de lotes sin provocar una reconstrucción falsa', function () {
    $this->article->update(['has_batch' => true, 'has_expiration' => true]);
    $firstLot = $this->item->lots()->create([
        'lot_code' => 'LOTE-A',
        'quantity' => 40,
        'expiration_date' => '2027-01-31',
        'manufacturing_date' => '2026-01-15',
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    $secondLot = $this->item->lots()->create([
        'lot_code' => 'LOTE-B',
        'quantity' => 60,
        'expiration_date' => '2027-02-28',
        'manufacturing_date' => '2026-02-15',
        'status' => 'active',
        'created_by' => $this->user->id,
    ]);
    registerAdministrativeUpdateKardex($this);
    $movementSnapshots = entryAdministrativeKardexMovements($this->entry)
        ->map(fn ($movement) => $movement->getAttributes())
        ->all();
    $stockSnapshots = WarehouseStock::query()->orderBy('id')->get()
        ->map(fn ($stock) => $stock->getAttributes())
        ->all();
    $payload = warehouseEntryAdministrativePayload($this);
    $payload['observations'] = 'SOLO CAMBIA OBSERVACIÓN';
    $payload['items'][0]['lots'] = collect([$secondLot, $firstLot])->map(fn ($lot) => [
        'id' => (string) $lot->id,
        'client_key' => 'id:'.$lot->id,
        'lot_code' => strtolower($lot->lot_code),
        'quantity' => number_format((float) $lot->quantity, 4, '.', ''),
        'expiration_date' => $lot->expiration_date->format('Y-m-d'),
        'manufacturing_date' => $lot->manufacturing_date->format('Y-m-d'),
    ])->all();

    $this->putJson(route('admin.warehouse-entries.update', $this->entry), $payload)
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(entryAdministrativeKardexMovements($this->entry)
        ->map(fn ($movement) => $movement->getAttributes())->all())->toBe($movementSnapshots)
        ->and(WarehouseStock::query()->orderBy('id')->get()
            ->map(fn ($stock) => $stock->getAttributes())->all())->toBe($stockSnapshots)
        ->and($this->item->lots()->count())->toBe(2);
});

function registerAdministrativeUpdateKardex(object $test): void
{
    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry(
        $test->entry->fresh(['supplier', 'currency', 'items.article', 'items.unit', 'items.lots'])
    );
}

function entryAdministrativeKardexMovements(WarehouseEntry $entry)
{
    return WarehouseKardexMovement::query()
        ->where('source_type', WarehouseEntry::class)
        ->where('source_id', $entry->id)
        ->orderBy('id')
        ->get();
}

function warehouseEntryAdministrativePayload(object $test): array
{
    return [
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $test->warehouse->id,
        'company_id' => $test->company->id,
        'supplier_id' => $test->supplier->id,
        'currency_id' => $test->currency->id,
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_number' => 'ADMIN-001',
        'document_date' => '2026-08-20',
        'payment_method' => 'transferencia',
        'payment_condition' => 'credito',
        'credit_days' => 30,
        'generate_account_payable' => 1,
        'affect_igv' => 0,
        'observations' => $test->entry->observations,
        'items' => [[
            'id' => (string) $test->item->id,
            'article_id' => (string) $test->article->id,
            'article_code' => strtolower($test->article->code),
            'billing_name_snapshot' => $test->article->billing_name,
            'unit_id' => (string) $test->unit->id,
            'quantity' => '100.00',
            'unit_price' => '10.000000',
            'lots' => $test->item->lots()->orderBy('id')->get()->map(fn ($lot) => [
                'id' => (string) $lot->id,
                'client_key' => 'id:'.$lot->id,
                'lot_code' => $lot->lot_code,
                'quantity' => (string) $lot->quantity,
                'expiration_date' => $lot->expiration_date?->format('Y-m-d'),
                'manufacturing_date' => $lot->manufacturing_date?->format('Y-m-d'),
            ])->all(),
        ]],
    ];
}
