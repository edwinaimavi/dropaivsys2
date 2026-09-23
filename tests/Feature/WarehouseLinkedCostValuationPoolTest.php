<?php

use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDistribution;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseKardexService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function linkedCostValuationPoolFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA COSTO PPM '.$suffix,
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('LCW'.$suffix, 0, 20),
        'name' => 'ALMACÉN COSTO PPM '.$suffix,
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
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => substr('L'.$suffix, 0, 10),
        'description' => 'SOLES COSTO PPM '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('U'.$suffix, 0, 10),
        'description' => 'UNIDAD COSTO PPM '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTO COSTO PPM '.$suffix,
        'code' => substr('LCC'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => substr('LCA'.$suffix, 0, 20),
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO COSTO PPM '.$suffix,
        'billing_name' => 'ARTÍCULO COSTO PPM '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '15'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR COSTO PPM '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-LC-'.$suffix,
        'entry_mode' => 'supplier_invoice',
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'status' => 'registered',
        'created_by' => $user->id,
    ]);
    $item = WarehouseEntryItem::create([
        'warehouse_entry_id' => $entry->id,
        'article_id' => $articleId,
        'billing_name_snapshot' => 'ARTÍCULO COSTO PPM '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 10,
        'unit_price' => 20,
        'line_total' => 200,
        'status' => 'active',
    ]);
    $stockA = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-A|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-A',
        'current_quantity' => 5,
        'average_unit_cost' => 20,
        'total_cost' => 100,
        'status' => 'ACTIVE',
    ]);
    $stockB = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LOTE-B|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LOTE-B',
        'current_quantity' => 5,
        'average_unit_cost' => 20,
        'total_cost' => 100,
        'status' => 'ACTIVE',
    ]);

    foreach ([$stockA, $stockB] as $position => $stock) {
        WarehouseKardexMovement::create([
            'movement_number' => 'KDX-LC-'.$suffix.'-'.$position,
            'company_id' => $companyId,
            'warehouse_stock_id' => $stock->id,
            'warehouse_id' => $warehouseId,
            'article_id' => $articleId,
            'unit_id' => $unitId,
            'lot_number' => $stock->lot_number,
            'movement_date' => $now,
            'movement_type' => 'entry',
            'operation_type' => 'warehouse_entry',
            'source_type' => WarehouseEntry::class,
            'source_id' => $entry->id,
            'source_item_type' => WarehouseEntryItem::class,
            'source_item_id' => $item->id,
            'source_key' => 'warehouse-entry-linked-'.$suffix.'-'.$position,
            'quantity_in' => 5,
            'quantity_out' => 0,
            'balance_quantity' => 5,
            'unit_cost' => 20,
            'total_cost_in' => 100,
            'total_cost_out' => 0,
            'average_unit_cost' => 20,
            'balance_total_cost' => 100,
            'currency_id' => $currencyId,
            'status' => 'registered',
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }

    $expense = WarehouseEntryExpense::create([
        'warehouse_entry_id' => $entry->id,
        'source_type' => WarehouseEntryExpense::SOURCE_MANUAL,
        'expense_category' => 'freight_transport',
        'cost_origin' => 'third_party',
        'expense_type' => 'agency_freight',
        'provider_name' => 'TRANSPORTE '.$suffix,
        'document_type' => 'SIN_COMPROBANTE',
        'currency_id' => $currencyId,
        'amount' => 50,
        'affects_igv' => false,
        'taxable_amount' => 50,
        'igv_amount' => 0,
        'total_amount' => 50,
        'affects_inventory_cost' => true,
        'distribution_method' => 'amount',
        'description' => 'FLETE ATRIBUIBLE '.$suffix,
        'status' => 'ACTIVE',
        'approval_status' => WarehouseEntryExpense::APPROVAL_APPROVED,
    ]);
    $distribution = WarehouseEntryExpenseDistribution::create([
        'warehouse_entry_expense_id' => $expense->id,
        'warehouse_entry_item_id' => $item->id,
        'distributed_amount' => 50,
    ]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'current_quantity' => 10,
        'average_unit_cost' => 20,
        'total_cost' => 200,
    ]);

    return compact('entry', 'stockA', 'stockB', 'pool', 'distribution');
}

it('agrega el costo vinculado al pool sin aumentar cantidad física o contable', function () {
    $fixture = linkedCostValuationPoolFixture('VALUE');

    app(WarehouseKardexService::class)->syncLinkedCosts($fixture['entry']);
    $pool = $fixture['pool']->fresh();

    expect((float) $pool->current_quantity)->toBe(10.0)
        ->and((float) $pool->total_cost)->toBe(250.0)
        ->and((float) $pool->average_unit_cost)->toBe(25.0)
        ->and((float) $fixture['stockA']->fresh()->current_quantity)->toBe(5.0)
        ->and((float) $fixture['stockB']->fresh()->current_quantity)->toBe(5.0)
        ->and(WarehouseValuationPool::count())->toBe(1)
        ->and(WarehouseKardexMovement::where('operation_type', 'warehouse_entry_linked_cost')->count())->toBe(2);
});

it('mantiene un solo pool cuando distribuye el costo entre varios lotes', function () {
    $fixture = linkedCostValuationPoolFixture('LOTS');

    app(WarehouseKardexService::class)->syncLinkedCosts($fixture['entry']);
    $linkedCosts = WarehouseKardexMovement::query()
        ->where('operation_type', 'warehouse_entry_linked_cost')
        ->orderBy('warehouse_stock_id')
        ->get();

    expect($linkedCosts)->toHaveCount(2)
        ->and($linkedCosts->map(fn ($movement) => (float) $movement->total_cost_in)->all())
        ->toBe([25.0, 25.0])
        ->and((float) $fixture['stockA']->fresh()->total_cost)->toBe(125.0)
        ->and((float) $fixture['stockB']->fresh()->total_cost)->toBe(125.0)
        ->and(WarehouseValuationPool::count())->toBe(1)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(250.0);
});

it('rechaza costo positivo cuando el pool no tiene cantidad', function () {
    $fixture = linkedCostValuationPoolFixture('ZERO');
    $fixture['stockA']->update(['current_quantity' => 0, 'average_unit_cost' => 0, 'total_cost' => 0]);
    $fixture['stockB']->update(['current_quantity' => 0, 'average_unit_cost' => 0, 'total_cost' => 0]);
    $fixture['pool']->update(['current_quantity' => 0, 'average_unit_cost' => 0, 'total_cost' => 0]);

    expect(fn () => app(WarehouseKardexService::class)->syncLinkedCosts($fixture['entry']))
        ->toThrow(ValidationException::class);

    expect((float) $fixture['pool']->fresh()->total_cost)->toBe(0.0)
        ->and((float) $fixture['stockA']->fresh()->total_cost)->toBe(0.0)
        ->and((float) $fixture['stockB']->fresh()->total_cost)->toBe(0.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'warehouse_entry_linked_cost')->count())->toBe(0);
});

it('hace rollback de pool stock legacy y Kardex ante una excepción', function () {
    $fixture = linkedCostValuationPoolFixture('ROLL');
    $failure = (object) ['enabled' => true];
    WarehouseKardexMovement::creating(function (WarehouseKardexMovement $movement) use ($failure) {
        if ($failure->enabled && $movement->operation_type === 'warehouse_entry_linked_cost') {
            throw new RuntimeException('Fallo focal después de actualizar pool y stock.');
        }
    });

    expect(fn () => app(WarehouseKardexService::class)->syncLinkedCosts($fixture['entry']))
        ->toThrow(RuntimeException::class);
    $failure->enabled = false;

    expect((float) $fixture['pool']->fresh()->total_cost)->toBe(200.0)
        ->and((float) $fixture['stockA']->fresh()->total_cost)->toBe(100.0)
        ->and((float) $fixture['stockB']->fresh()->total_cost)->toBe(100.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'warehouse_entry_linked_cost')->count())->toBe(0);
});

it('no agrega dos veces el mismo costo vinculado', function () {
    $fixture = linkedCostValuationPoolFixture('ONCE');
    $service = app(WarehouseKardexService::class);

    $service->syncLinkedCosts($fixture['entry']);
    $service->syncLinkedCosts($fixture['entry']);

    expect((float) $fixture['pool']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(250.0)
        ->and((float) $fixture['pool']->fresh()->average_unit_cost)->toBe(25.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'warehouse_entry_linked_cost')->count())->toBe(2);
});
