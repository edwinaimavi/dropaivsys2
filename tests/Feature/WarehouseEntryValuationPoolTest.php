<?php

use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function valuationPoolEntryFixture(string $suffix, bool $inventory = true): array
{
    $now = now();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA POOL '.$suffix,
        'ruc' => '20'.str_pad((string) crc32($suffix), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'VP'.$suffix,
        'name' => 'ALMACÉN POOL '.$suffix,
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
        'abbreviation' => 'U'.$suffix,
        'description' => 'UNIDAD POOL '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => ($inventory ? 'PRODUCTO ' : 'SERVICIO ').$suffix,
        'code' => 'C'.$suffix,
        'type' => $inventory ? 'PRODUCTO COMERCIAL' : 'SERVICIO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = 'A'.$suffix;
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO POOL '.$suffix,
        'billing_name' => 'ARTÍCULO POOL '.$suffix,
        'item_kind' => $inventory ? 'product' : 'service',
        'is_inventory_item' => $inventory,
        ...($inventory ? testSunatInventoryArticleFields($articleCode) : []),
        'has_batch' => $inventory,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '10'.str_pad((string) crc32('SUP'.$suffix), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR POOL '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'P'.$suffix,
        'description' => 'SOLES POOL '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-POOL-'.$suffix,
        'entry_mode' => 'supplier_invoice',
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'document_type' => 'FACTURA',
        'status' => 'registered',
    ]);

    return compact('companyId', 'warehouseId', 'unitId', 'articleId', 'entry');
}

function valuationPoolEntryItem(
    array $fixture,
    float $quantity,
    float $unitPrice,
    ?string $lotNumber = null
): WarehouseEntryItem {
    return WarehouseEntryItem::create([
        'warehouse_entry_id' => $fixture['entry']->id,
        'article_id' => $fixture['articleId'],
        'billing_name_snapshot' => 'ARTÍCULO POOL',
        'unit_id' => $fixture['unitId'],
        'lot_number' => $lotNumber,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'line_total' => round($quantity * $unitPrice, 2),
        'status' => 'active',
    ]);
}

it('actualiza el stock físico y el pool con promedio ponderado una sola vez', function () {
    $fixture = valuationPoolEntryFixture('ONE');
    WarehouseStock::create([
        'stock_key' => implode('|', [
            $fixture['companyId'],
            $fixture['warehouseId'],
            $fixture['articleId'],
            'SIN_LOTE',
            'SIN_FECHA',
        ]),
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'current_quantity' => 10,
        'average_unit_cost' => 20,
        'total_cost' => 200,
        'status' => 'ACTIVE',
    ]);
    WarehouseValuationPool::create([
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'current_quantity' => 10,
        'average_unit_cost' => 20,
        'total_cost' => 200,
    ]);
    valuationPoolEntryItem($fixture, 10, 30);

    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($fixture['entry']);
    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($fixture['entry']);

    $stock = WarehouseStock::sole();
    $pool = WarehouseValuationPool::sole();

    expect((float) $stock->current_quantity)->toBe(20.0)
        ->and((float) $stock->total_cost)->toBe(500.0)
        ->and((float) $pool->current_quantity)->toBe(20.0)
        ->and((float) $pool->total_cost)->toBe(500.0)
        ->and((float) $pool->average_unit_cost)->toBe(25.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'warehouse_entry')->count())->toBe(1);
});

it('mantiene dos lotes físicos y consolida un solo pool PPM global', function () {
    $fixture = valuationPoolEntryFixture('LOTS');
    WarehouseValuationPool::create([
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'current_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
    ]);
    valuationPoolEntryItem($fixture, 5, 20, 'LOTE-A');
    valuationPoolEntryItem($fixture, 5, 30, 'LOTE-B');

    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($fixture['entry']);

    $stocks = WarehouseStock::query()->orderBy('lot_number')->get();
    $pool = WarehouseValuationPool::sole();

    expect($stocks)->toHaveCount(2)
        ->and($stocks->pluck('lot_number')->all())->toBe(['LOTE-A', 'LOTE-B'])
        ->and($stocks->map(fn (WarehouseStock $stock) => (float) $stock->current_quantity)->all())
        ->toBe([5.0, 5.0])
        ->and(WarehouseValuationPool::count())->toBe(1)
        ->and((float) $pool->current_quantity)->toBe(10.0)
        ->and((float) $pool->total_cost)->toBe(250.0)
        ->and((float) $pool->average_unit_cost)->toBe(25.0);
});

it('no modifica el pool para servicios no inventariables', function () {
    $fixture = valuationPoolEntryFixture('SERV', false);
    valuationPoolEntryItem($fixture, 2, 10);

    expect(fn () => app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($fixture['entry']))
        ->toThrow(ValidationException::class);

    expect(WarehouseValuationPool::count())->toBe(0)
        ->and(WarehouseStock::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

it('revierte conjuntamente pool stock y Kardex cuando falla la entrada', function () {
    $fixture = valuationPoolEntryFixture('ROLL');
    WarehouseValuationPool::create([
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'current_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
    ]);
    $item = valuationPoolEntryItem($fixture, 5, 20);
    $item->lots()->create([
        'lot_code' => 'LOTE-OK',
        'quantity' => 5,
        'status' => 'active',
    ]);
    $item->lots()->create([
        'lot_code' => 'LOTE-ERROR',
        'quantity' => 0,
        'status' => 'active',
    ]);

    expect(fn () => app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($fixture['entry']))
        ->toThrow(ValidationException::class);

    $pool = WarehouseValuationPool::sole();
    expect((float) $pool->current_quantity)->toBe(0.0)
        ->and((float) $pool->total_cost)->toBe(0.0)
        ->and(WarehouseStock::count())->toBe(0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});
