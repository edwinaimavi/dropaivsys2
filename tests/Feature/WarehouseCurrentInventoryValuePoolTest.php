<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KardexController;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use Illuminate\Support\Facades\DB;

function currentInventoryValueScope(string $suffix, string $ruc): array
{
    $now = now();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA VALOR ACTUAL '.$suffix,
        'ruc' => $ruc,
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'IVA-'.$suffix,
        'name' => 'ALMACEN VALOR ACTUAL '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'IV'.$suffix,
        'description' => 'UNIDAD VALOR ACTUAL '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORIA VALOR ACTUAL '.$suffix,
        'code' => 'IVC-'.$suffix,
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleId = currentInventoryValueArticle($suffix.'-A', $unitId, $categoryId);

    return compact('companyId', 'warehouseId', 'unitId', 'categoryId', 'articleId');
}

function currentInventoryValueArticle(string $suffix, int $unitId, int $categoryId): int
{
    $code = 'IVA-'.$suffix;

    return DB::table('articles')->insertGetId([
        'code' => $code,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTICULO VALOR ACTUAL '.$suffix,
        'billing_name' => 'ARTICULO VALOR ACTUAL '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($code),
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function currentInventoryValueLegacyStock(
    array $scope,
    string $lot,
    float $quantity,
    float $totalCost,
    ?int $articleId = null
): WarehouseStock {
    $articleId ??= $scope['articleId'];

    return WarehouseStock::create([
        'stock_key' => implode('|', [
            $scope['companyId'],
            $scope['warehouseId'],
            $articleId,
            $lot,
            'SIN_FECHA',
        ]),
        'company_id' => $scope['companyId'],
        'warehouse_id' => $scope['warehouseId'],
        'article_id' => $articleId,
        'unit_id' => $scope['unitId'],
        'lot_number' => $lot,
        'current_quantity' => $quantity,
        'reserved_quantity' => 0,
        'average_unit_cost' => $quantity > 0 ? $totalCost / $quantity : 0,
        'total_cost' => $totalCost,
        'status' => 'ACTIVE',
    ]);
}

function currentInventoryValuePool(
    array $scope,
    float $quantity,
    float $totalCost,
    ?int $articleId = null
): WarehouseValuationPool {
    return WarehouseValuationPool::create([
        'company_id' => $scope['companyId'],
        'warehouse_id' => $scope['warehouseId'],
        'article_id' => $articleId ?? $scope['articleId'],
        'current_quantity' => $quantity,
        'average_unit_cost' => $quantity > 0 ? $totalCost / $quantity : 0,
        'total_cost' => $totalCost,
    ]);
}

it('usa el valuation pool sobre costos legacy y alimenta Dashboard y Kardex', function () {
    $scope = currentInventoryValueScope('MAIN', '20111111111');
    currentInventoryValueLegacyStock($scope, 'LOTE-A', 10, 200);
    currentInventoryValueLegacyStock($scope, 'LOTE-B', 10, 300);
    currentInventoryValuePool($scope, 20, 480);

    $dashboard = app(DashboardController::class)->index()->getData();
    $kardex = app(KardexController::class)->index()->getData();

    expect((float) WarehouseStock::query()->sum('total_cost'))->toBe(500.0)
        ->and(WarehouseStock::currentInventoryValue())->toBe(480.0)
        ->and($dashboard['metrics']['inventoryValue'])->toBe(480.0)
        ->and($kardex['stats']['inventory_value'])->toBe(480.0);
});

it('suma varios pools del mismo alcance', function () {
    $scope = currentInventoryValueScope('MULTI', '20222222222');
    $secondArticleId = currentInventoryValueArticle('MULTI-B', $scope['unitId'], $scope['categoryId']);
    currentInventoryValuePool($scope, 20, 480);
    currentInventoryValuePool($scope, 5, 120, $secondArticleId);

    expect(WarehouseStock::currentInventoryValue())->toBe(600.0);
});

it('permite consultar una empresa sin sumar pools de otra empresa', function () {
    $companyA = currentInventoryValueScope('COMPA', '20333333333');
    $companyB = currentInventoryValueScope('COMPB', '20444444444');
    currentInventoryValuePool($companyA, 10, 500);
    currentInventoryValuePool($companyB, 10, 900);

    expect(WarehouseStock::currentInventoryValue($companyA['companyId']))->toBe(500.0)
        ->and(WarehouseStock::currentInventoryValue($companyB['companyId']))->toBe(900.0);
});

it('devuelve cero sin pools y no usa WarehouseStock como fallback', function () {
    $scope = currentInventoryValueScope('EMPTY', '20555555555');
    currentInventoryValueLegacyStock($scope, 'LOTE-LEGACY', 10, 500);

    expect((float) WarehouseStock::query()->sum('total_cost'))->toBe(500.0)
        ->and(WarehouseStock::currentInventoryValue())->toBe(0.0)
        ->and(WarehouseStock::currentInventoryValue($scope['companyId']))->toBe(0.0);
});
