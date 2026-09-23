<?php

use App\Models\User;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function warehouseAdjustmentPoolFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA AJUSTE PPM '.$suffix,
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('AW'.$suffix, 0, 20),
        'name' => 'ALMACÉN AJUSTE PPM '.$suffix,
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
        'description' => 'UNIDAD AJUSTE PPM '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTO AJUSTE PPM '.$suffix,
        'code' => substr('AC'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('AA'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO AJUSTE PPM '.$suffix,
        'billing_name' => 'ARTÍCULO AJUSTE PPM '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => true,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $stockA = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-A|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-A',
        'current_quantity' => 10,
        'average_unit_cost' => 20,
        'total_cost' => 200,
        'status' => 'ACTIVE',
    ]);
    $stockB = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-B|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-B',
        'current_quantity' => 10,
        'average_unit_cost' => 30,
        'total_cost' => 300,
        'status' => 'ACTIVE',
    ]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'current_quantity' => 20,
        'average_unit_cost' => 25,
        'total_cost' => 500,
    ]);

    return compact('companyId', 'stockA', 'stockB', 'pool');
}

it('aplica adjustment_in al lote y al pool usando el costo explícito', function () {
    $fixture = warehouseAdjustmentPoolFixture('IN');
    $fixture['stockB']->update([
        'current_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
    ]);
    $fixture['pool']->update([
        'current_quantity' => 10,
        'average_unit_cost' => 20,
        'total_cost' => 200,
    ]);

    $movement = app(WarehouseKardexService::class)->registerAdjustment(
        $fixture['stockA'],
        $fixture['companyId'],
        'adjustment_in',
        5,
        30,
        'Ajuste positivo focal',
        'adjustment-pool-in'
    );
    $pool = $fixture['pool']->fresh();

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(15.0)
        ->and((float) $fixture['stockB']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $pool->current_quantity)->toBe(15.0)
        ->and((float) $pool->total_cost)->toBe(350.0)
        ->and((float) $pool->average_unit_cost)->toBe(23.333333)
        ->and((float) $movement->unit_cost)->toBe(30.0)
        ->and((float) $movement->total_cost_in)->toBe(150.0);
});

it('valoriza adjustment_out al PPM global y no al costo legacy del lote', function () {
    $fixture = warehouseAdjustmentPoolFixture('OUT');

    $movement = app(WarehouseKardexService::class)->registerAdjustment(
        $fixture['stockA'],
        $fixture['companyId'],
        'adjustment_out',
        4,
        999,
        'Ajuste negativo focal',
        'adjustment-pool-out'
    );
    $pool = $fixture['pool']->fresh();

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(6.0)
        ->and((float) $fixture['stockB']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $pool->current_quantity)->toBe(16.0)
        ->and((float) $pool->total_cost)->toBe(400.0)
        ->and((float) $pool->average_unit_cost)->toBe(25.0)
        ->and((float) $movement->unit_cost)->toBe(25.0)
        ->and((float) $movement->total_cost_out)->toBe(100.0)
        ->and((float) $movement->total_cost_out)->not->toBe(80.0)
        ->and((float) $fixture['stockA']->fresh()->total_cost)->toBe(120.0);
});

it('mantiene un solo pool para ajustes sobre lotes diferentes', function () {
    $fixture = warehouseAdjustmentPoolFixture('LOTS');
    $service = app(WarehouseKardexService::class);

    $service->registerAdjustment(
        $fixture['stockA'],
        $fixture['companyId'],
        'adjustment_out',
        2,
        null,
        'Salida lote A',
        'adjustment-lot-a'
    );
    $service->registerAdjustment(
        $fixture['stockB'],
        $fixture['companyId'],
        'adjustment_out',
        3,
        null,
        'Salida lote B',
        'adjustment-lot-b'
    );

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(8.0)
        ->and((float) $fixture['stockB']->fresh()->current_quantity)->toBe(7.0)
        ->and(WarehouseValuationPool::count())->toBe(1)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(15.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(375.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'manual_adjustment')->count())->toBe(2);
});

it('bloquea sobre-salida del lote y del pool sin cambios parciales', function () {
    $physical = warehouseAdjustmentPoolFixture('OVERLOT');
    $service = app(WarehouseKardexService::class);

    expect(fn () => $service->registerAdjustment(
        $physical['stockA'],
        $physical['companyId'],
        'adjustment_out',
        11,
        null,
        'Sobre-salida física'
    ))->toThrow(ValidationException::class);

    $poolLimited = warehouseAdjustmentPoolFixture('OVERPOOL');
    $poolLimited['pool']->update([
        'current_quantity' => 3,
        'average_unit_cost' => 25,
        'total_cost' => 75,
    ]);

    expect(fn () => $service->registerAdjustment(
        $poolLimited['stockA'],
        $poolLimited['companyId'],
        'adjustment_out',
        4,
        null,
        'Sobre-salida contable'
    ))->toThrow(ValidationException::class);

    expect((float) $physical['stockA']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $physical['pool']->fresh()->current_quantity)->toBe(20.0)
        ->and((float) $poolLimited['stockA']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $poolLimited['pool']->fresh()->current_quantity)->toBe(3.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'manual_adjustment')->count())->toBe(0);
});

it('hace rollback de stock pool y Kardex ante una excepción', function () {
    $fixture = warehouseAdjustmentPoolFixture('ROLL');
    $failure = (object) ['enabled' => true];
    WarehouseKardexMovement::creating(function (WarehouseKardexMovement $movement) use ($failure) {
        if ($failure->enabled && $movement->operation_type === 'manual_adjustment') {
            throw new RuntimeException('Fallo focal posterior a stock y pool.');
        }
    });

    expect(fn () => app(WarehouseKardexService::class)->registerAdjustment(
        $fixture['stockA'],
        $fixture['companyId'],
        'adjustment_out',
        4,
        null,
        'Rollback focal'
    ))->toThrow(RuntimeException::class);
    $failure->enabled = false;

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['stockA']->fresh()->total_cost)->toBe(200.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(20.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(500.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'manual_adjustment')->count())->toBe(0);
});

it('bloquea pool faltante cuando ya existe inventario físico o valorizado', function () {
    $fixture = warehouseAdjustmentPoolFixture('NOPOOL');
    $fixture['pool']->delete();

    expect(fn () => app(WarehouseKardexService::class)->registerAdjustment(
        $fixture['stockA'],
        $fixture['companyId'],
        'adjustment_in',
        1,
        30,
        'No debe crear pool cero'
    ))->toThrow(ValidationException::class);

    expect(WarehouseValuationPool::count())->toBe(0)
        ->and((float) $fixture['stockA']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['stockA']->fresh()->total_cost)->toBe(200.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'manual_adjustment')->count())->toBe(0);
});
