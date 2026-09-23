<?php

use App\Models\WarehouseEntry;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseKardexRecalculation;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseKardexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

function warehouseKardexLegacyGuardFixture(string $suffix): array
{
    $now = now();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA GUARD PPM '.$suffix,
        'ruc' => '20'.str_pad((string) (crc32($suffix) % 1_000_000_000), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
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
        'business_name' => 'PROVEEDOR GUARD PPM '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('GW'.$suffix, 0, 20),
        'name' => 'ALMACÉN GUARD PPM '.$suffix,
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
        'description' => 'UNIDAD GUARD PPM '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTO GUARD PPM '.$suffix,
        'code' => substr('GC'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('GA'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO GUARD PPM '.$suffix,
        'billing_name' => 'ARTÍCULO GUARD PPM '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-GUARD-'.$suffix,
        'warehouse_id' => $warehouseId,
        'company_id' => $companyId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'status' => 'registered',
    ]);
    $item = $entry->items()->create([
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO GUARD PPM '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 10,
        'unit_price' => 20,
        'status' => 'active',
    ]);
    $stock = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|SIN_LOTE|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'current_quantity' => 10,
        'reserved_quantity' => 0,
        'average_unit_cost' => 20,
        'total_cost' => 200,
        'status' => 'ACTIVE',
    ]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'current_quantity' => 10,
        'average_unit_cost' => 20,
        'total_cost' => 200,
    ]);
    $movement = WarehouseKardexMovement::create([
        'movement_number' => 'KDX-GUARD-'.$suffix,
        'company_id' => $companyId,
        'warehouse_stock_id' => $stock->id,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'movement_date' => $now,
        'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry',
        'source_type' => WarehouseEntry::class,
        'source_id' => $entry->id,
        'source_item_type' => $item::class,
        'source_item_id' => $item->id,
        'source_key' => 'guard-entry-'.$suffix,
        'quantity_in' => 10,
        'quantity_out' => 0,
        'balance_quantity' => 10,
        'unit_cost' => 20,
        'total_cost_in' => 200,
        'total_cost_out' => 0,
        'average_unit_cost' => 20,
        'balance_total_cost' => 200,
        'currency_id' => $currencyId,
        'exchange_rate' => 1,
        'status' => 'registered',
    ]);

    return compact('stock', 'pool', 'movement');
}

function legacyGuardSnapshot(array $fixture): array
{
    return [
        'stock' => $fixture['stock']->fresh()->getAttributes(),
        'pool' => $fixture['pool']->fresh()->getAttributes(),
        'movement' => $fixture['movement']->fresh()->getAttributes(),
        'stock_count' => WarehouseStock::count(),
        'pool_count' => WarehouseValuationPool::count(),
        'movement_count' => WarehouseKardexMovement::count(),
        'recalculation_count' => WarehouseKardexRecalculation::count(),
    ];
}

it('bloquea recalculate antes de cualquier escritura', function () {
    $fixture = warehouseKardexLegacyGuardFixture('RECALC');
    $before = legacyGuardSnapshot($fixture);

    expect(fn () => app(WarehouseKardexService::class)->recalculate())
        ->toThrow(\RuntimeException::class, 'recálculo legacy de Kardex está deshabilitado');

    expect(legacyGuardSnapshot($fixture))->toBe($before);
});

it('bloquea kardex rebuild fresh antes de truncar datos', function () {
    $fixture = warehouseKardexLegacyGuardFixture('FRESH');
    $before = legacyGuardSnapshot($fixture);

    $exitCode = Artisan::call('warehouse:kardex-rebuild', ['--fresh' => true]);

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('modo --fresh está deshabilitado con el pool global PPM')
        ->and(legacyGuardSnapshot($fixture))->toBe($before);
});

it('bloquea kardex rebuild normal porque también puede escribir en el pool', function () {
    $fixture = warehouseKardexLegacyGuardFixture('NORMAL');
    $before = legacyGuardSnapshot($fixture);

    $exitCode = Artisan::call('warehouse:kardex-rebuild');

    expect($exitCode)->toBe(Command::FAILURE)
        ->and(Artisan::output())->toContain('rebuild legacy de Kardex está deshabilitado con el pool global PPM')
        ->and(legacyGuardSnapshot($fixture))->toBe($before);
});
