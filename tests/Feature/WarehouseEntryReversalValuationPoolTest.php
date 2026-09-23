<?php

use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function warehouseEntryReversalPoolFixture(
    string $suffix,
    float $linkedCost = 0,
    bool $withMainMovement = true
): array {
    $now = now();
    $movementDate = $now->copy()->subHours(2);
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA REVERSA PPM '.$suffix,
        'ruc' => '20'.str_pad((string) (crc32($suffix) % 1_000_000_000), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'PEN',
        'description' => 'SOLES',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20'.str_pad((string) ((crc32($suffix) + 1) % 1_000_000_000), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR REVERSA PPM '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('RW'.$suffix, 0, 20),
        'name' => 'ALMACÉN REVERSA PPM '.$suffix,
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
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('U'.$suffix, 0, 10),
        'description' => 'UNIDAD REVERSA PPM '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTO REVERSA PPM '.$suffix,
        'code' => substr('RC'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('RA'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO REVERSA PPM '.$suffix,
        'billing_name' => 'ARTÍCULO REVERSA PPM '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => true,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-REV-PPM-'.$suffix,
        'warehouse_id' => $warehouseId,
        'company_id' => $companyId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'status' => 'registered',
        'created_by' => $user->id,
    ]);
    $item = $entry->items()->create([
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO REVERSA PPM '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 10,
        'unit_price' => 30,
        'lot_number' => 'LOTE-A',
        'status' => 'active',
    ]);
    $quantity = $withMainMovement ? 20 : 10;
    $baseCost = $withMainMovement ? 500 : 200;
    $totalCost = $baseCost + $linkedCost;
    $stock = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-A|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-A',
        'current_quantity' => $quantity,
        'reserved_quantity' => 0,
        'average_unit_cost' => $totalCost / $quantity,
        'total_cost' => $totalCost,
        'status' => 'ACTIVE',
    ]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'current_quantity' => $quantity,
        'average_unit_cost' => $totalCost / $quantity,
        'total_cost' => $totalCost,
    ]);
    $mainMovement = $withMainMovement ? WarehouseKardexMovement::create([
        'movement_number' => 'KDX-'.$suffix.'-MAIN',
        'company_id' => $companyId,
        'warehouse_stock_id' => $stock->id,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-A',
        'movement_date' => $movementDate,
        'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry',
        'source_type' => WarehouseEntry::class,
        'source_id' => $entry->id,
        'source_item_type' => WarehouseEntryItem::class,
        'source_item_id' => $item->id,
        'source_key' => 'entry-reversal-'.$suffix,
        'quantity_in' => 10,
        'quantity_out' => 0,
        'balance_quantity' => 20,
        'unit_cost' => 30,
        'total_cost_in' => 300,
        'total_cost_out' => 0,
        'average_unit_cost' => 25,
        'balance_total_cost' => 500,
        'currency_id' => $currencyId,
        'exchange_rate' => 1,
        'status' => 'registered',
    ]) : null;
    $linkedMovement = $linkedCost > 0 ? WarehouseKardexMovement::create([
        'movement_number' => 'KDX-'.$suffix.'-COST',
        'company_id' => $companyId,
        'warehouse_stock_id' => $stock->id,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-A',
        'movement_date' => $movementDate->copy()->addMinute(),
        'movement_type' => 'linked_cost',
        'operation_type' => 'warehouse_entry_linked_cost',
        'source_type' => WarehouseEntry::class,
        'source_id' => $entry->id,
        'source_item_type' => WarehouseEntryItem::class,
        'source_item_id' => $item->id,
        'source_key' => 'entry-linked-cost-'.$suffix,
        'quantity_in' => 0,
        'quantity_out' => 0,
        'balance_quantity' => $quantity,
        'unit_cost' => 0,
        'total_cost_in' => $linkedCost,
        'total_cost_out' => 0,
        'average_unit_cost' => $totalCost / $quantity,
        'balance_total_cost' => $totalCost,
        'currency_id' => $currencyId,
        'exchange_rate' => 1,
        'status' => 'registered',
    ]) : null;

    return compact(
        'companyId', 'warehouseId', 'articleId', 'unitId', 'currencyId', 'entry', 'item',
        'stock', 'pool', 'mainMovement', 'linkedMovement', 'movementDate'
    );
}

function addLaterPoolMovement(
    array $fixture,
    WarehouseStock $stock,
    string $suffix,
    float $quantityIn,
    float $quantityOut,
    float $costIn,
    float $costOut
): WarehouseKardexMovement {
    return WarehouseKardexMovement::create([
        'movement_number' => 'KDX-EXT-'.$suffix,
        'company_id' => $fixture['companyId'],
        'warehouse_stock_id' => $stock->id,
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'lot_number' => $stock->lot_number,
        'movement_date' => $fixture['movementDate']->copy()->addHour(),
        'movement_type' => $quantityOut > 0 ? 'exit' : 'entry',
        'operation_type' => 'external_'.$suffix,
        'source_type' => WarehouseStock::class,
        'source_id' => $stock->id,
        'source_key' => 'external-'.$suffix,
        'quantity_in' => $quantityIn,
        'quantity_out' => $quantityOut,
        'balance_quantity' => $stock->current_quantity,
        'unit_cost' => $quantityIn > 0 ? $costIn / $quantityIn : $costOut / $quantityOut,
        'total_cost_in' => $costIn,
        'total_cost_out' => $costOut,
        'average_unit_cost' => $stock->average_unit_cost,
        'balance_total_cost' => $stock->total_cost,
        'currency_id' => $fixture['currencyId'],
        'exchange_rate' => 1,
        'status' => 'registered',
    ]);
}

it('revierte una entrada segura con su cantidad y costo históricos', function () {
    $fixture = warehouseEntryReversalPoolFixture('SAFE');

    app(WarehouseKardexService::class)->reverseWarehouseEntry($fixture['entry']);

    $pool = $fixture['pool']->fresh();
    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $pool->current_quantity)->toBe(10.0)
        ->and((float) $pool->total_cost)->toBe(200.0)
        ->and((float) $pool->average_unit_cost)->toBe(20.0)
        ->and((float) WarehouseKardexMovement::where('operation_type', 'warehouse_entry_cancel')->value('quantity_out'))->toBe(10.0)
        ->and((float) WarehouseKardexMovement::where('operation_type', 'warehouse_entry_cancel')->value('total_cost_out'))->toBe(300.0)
        ->and($fixture['mainMovement']->fresh()->status)->toBe('reversed');
});

it('bloquea la reversa cuando existe una salida posterior', function () {
    $fixture = warehouseEntryReversalPoolFixture('EXIT');
    $fixture['stock']->update(['current_quantity' => 15, 'average_unit_cost' => 25, 'total_cost' => 375]);
    $fixture['pool']->update(['current_quantity' => 15, 'average_unit_cost' => 25, 'total_cost' => 375]);
    addLaterPoolMovement($fixture, $fixture['stock']->fresh(), 'EXIT', 0, 5, 0, 125);
    $movementCount = WarehouseKardexMovement::count();

    expect(fn () => app(WarehouseKardexService::class)->reverseWarehouseEntry($fixture['entry']))
        ->toThrow(ValidationException::class, 'existen movimientos posteriores');

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(15.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(15.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(375.0)
        ->and($fixture['mainMovement']->fresh()->status)->toBe('registered')
        ->and(WarehouseKardexMovement::count())->toBe($movementCount);
});

it('bloquea la reversa aunque movimientos posteriores recuperen la cantidad', function () {
    $fixture = warehouseEntryReversalPoolFixture('RECOVER');
    $fixture['stock']->update(['current_quantity' => 20, 'average_unit_cost' => 26.25, 'total_cost' => 525]);
    $fixture['pool']->update(['current_quantity' => 20, 'average_unit_cost' => 26.25, 'total_cost' => 525]);
    addLaterPoolMovement($fixture, $fixture['stock']->fresh(), 'RECOVER-OUT', 0, 5, 0, 125);
    $laterEntry = addLaterPoolMovement($fixture, $fixture['stock']->fresh(), 'RECOVER-IN', 5, 0, 150, 0);
    $laterEntry->update(['movement_date' => $fixture['movementDate']->copy()->addMinutes(90)]);

    expect(fn () => app(WarehouseKardexService::class)->reverseWarehouseEntry($fixture['entry']))
        ->toThrow(ValidationException::class, 'existen movimientos posteriores');

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(20.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(20.0)
        ->and($fixture['mainMovement']->fresh()->status)->toBe('registered');
});

it('bloquea por un movimiento posterior de otro lote del mismo pool', function () {
    $fixture = warehouseEntryReversalPoolFixture('LOT');
    $stockB = WarehouseStock::create([
        'stock_key' => "{$fixture['companyId']}|{$fixture['warehouseId']}|{$fixture['articleId']}|LOTE-B|SIN_FECHA",
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'lot_number' => 'LOTE-B',
        'current_quantity' => 2,
        'average_unit_cost' => 40,
        'total_cost' => 80,
        'status' => 'ACTIVE',
    ]);
    $fixture['pool']->update(['current_quantity' => 22, 'average_unit_cost' => 26.363636, 'total_cost' => 580]);
    addLaterPoolMovement($fixture, $stockB, 'OTHER-LOT', 2, 0, 80, 0);

    expect(fn () => app(WarehouseKardexService::class)->reverseWarehouseEntry($fixture['entry']))
        ->toThrow(ValidationException::class, 'existen movimientos posteriores');

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(20.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(22.0)
        ->and($fixture['mainMovement']->fresh()->status)->toBe('registered');
});

it('revierte un costo vinculado sin modificar cantidad', function () {
    $fixture = warehouseEntryReversalPoolFixture('COST', 50, false);

    app(WarehouseKardexService::class)->syncLinkedCosts($fixture['entry']);

    $pool = $fixture['pool']->fresh();
    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $pool->current_quantity)->toBe(10.0)
        ->and((float) $pool->total_cost)->toBe(200.0)
        ->and((float) $pool->average_unit_cost)->toBe(20.0)
        ->and((float) WarehouseKardexMovement::where('operation_type', 'warehouse_entry_linked_cost_cancel')->value('quantity_out'))->toBe(0.0)
        ->and((float) WarehouseKardexMovement::where('operation_type', 'warehouse_entry_linked_cost_cancel')->value('total_cost_out'))->toBe(50.0);
});

it('revierte costos vinculados antes del movimiento principal', function () {
    $fixture = warehouseEntryReversalPoolFixture('ORDER', 50);

    app(WarehouseKardexService::class)->reverseWarehouseEntry($fixture['entry']);

    $reversalTypes = WarehouseKardexMovement::query()
        ->whereIn('operation_type', ['warehouse_entry_linked_cost_cancel', 'warehouse_entry_cancel'])
        ->orderBy('id')
        ->pluck('operation_type')
        ->all();
    $pool = $fixture['pool']->fresh();

    expect($reversalTypes)->toBe(['warehouse_entry_linked_cost_cancel', 'warehouse_entry_cancel'])
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $pool->current_quantity)->toBe(10.0)
        ->and((float) $pool->total_cost)->toBe(200.0)
        ->and((float) $pool->average_unit_cost)->toBe(20.0);
});

it('bloquea una edición cuando existe un movimiento posterior externo', function () {
    $fixture = warehouseEntryReversalPoolFixture('EDIT');
    addLaterPoolMovement($fixture, $fixture['stock'], 'EDIT', 0, 1, 0, 25);
    $movementCount = WarehouseKardexMovement::count();

    expect(fn () => app(WarehouseKardexService::class)->rebuildEntryMovements($fixture['entry']))
        ->toThrow(ValidationException::class, 'existen movimientos posteriores');

    expect($fixture['mainMovement']->fresh()->status)->toBe('registered')
        ->and(WarehouseKardexMovement::count())->toBe($movementCount)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(20.0);
});

it('revierte completamente stock pool y Kardex cuando falla una reversa', function () {
    $fixture = warehouseEntryReversalPoolFixture('ROLLBACK', 50);
    $failure = (object) ['enabled' => true];
    WarehouseKardexMovement::creating(function () use ($failure) {
        if ($failure->enabled) {
            throw new \RuntimeException('Falla focal de reversa');
        }
    });

    expect(fn () => app(WarehouseKardexService::class)->reverseWarehouseEntry($fixture['entry']))
        ->toThrow(\RuntimeException::class, 'Falla focal de reversa');
    $failure->enabled = false;

    expect((float) $fixture['stock']->fresh()->current_quantity)->toBe(20.0)
        ->and((float) $fixture['stock']->fresh()->total_cost)->toBe(550.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(20.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(550.0)
        ->and($fixture['mainMovement']->fresh()->status)->toBe('registered')
        ->and($fixture['linkedMovement']->fresh()->status)->toBe('registered')
        ->and(WarehouseKardexMovement::whereIn('operation_type', [
            'warehouse_entry_cancel', 'warehouse_entry_linked_cost_cancel',
        ])->count())->toBe(0);
});
