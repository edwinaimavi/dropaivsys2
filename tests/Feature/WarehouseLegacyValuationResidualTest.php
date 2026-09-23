<?php

use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDistribution;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseLegacyValuationResidualService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

function legacyValuationResidualFixture(string $suffix): array
{
    $now = now()->startOfSecond();
    $user = User::factory()->create();
    Auth::login($user);

    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA LEGACY RESIDUAL '.$suffix,
        'ruc' => '20'.str_pad((string) (abs(crc32('LVR-'.$suffix)) % 1_000_000_000), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('LVRW'.$suffix, 0, 20),
        'name' => 'ALMACÉN LEGACY RESIDUAL '.$suffix,
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
        'description' => 'SOLES LEGACY RESIDUAL '.$suffix,
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('U'.$suffix, 0, 10),
        'description' => 'UNIDAD LEGACY RESIDUAL '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA LEGACY RESIDUAL '.$suffix,
        'code' => substr('LVRC'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => substr('LVRA'.$suffix, 0, 20),
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO LEGACY RESIDUAL '.$suffix,
        'billing_name' => 'ARTÍCULO LEGACY RESIDUAL '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '15'.str_pad((string) (abs(crc32('LVRS-'.$suffix)) % 1_000_000_000), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR LEGACY RESIDUAL '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-LVR-'.$suffix,
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
        'article_code' => substr('LVRA'.$suffix, 0, 20),
        'billing_name_snapshot' => 'ARTÍCULO HISTÓRICO '.$suffix,
        'unit_id' => $unitId,
        'quantity' => 18,
        'unit_price' => 30,
        'line_total' => 540,
        'status' => 'active',
    ]);

    $stock = WarehouseStock::create([
        'stock_key' => "{$companyId}|{$warehouseId}|{$articleId}|LEGACY|SIN_FECHA",
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LEGACY',
        'current_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
        'status' => 'ACTIVE',
    ]);
    $pool = WarehouseValuationPool::create([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'current_quantity' => 0,
        'average_unit_cost' => 0,
        'total_cost' => 0,
    ]);

    $expense = WarehouseEntryExpense::create([
        'warehouse_entry_id' => $entry->id,
        'source_type' => WarehouseEntryExpense::SOURCE_MANUAL,
        'expense_category' => 'freight_transport',
        'cost_origin' => 'third_party',
        'expense_type' => 'agency_freight',
        'provider_name' => 'TRANSPORTE LEGACY '.$suffix,
        'document_type' => 'SIN_COMPROBANTE',
        'currency_id' => $currencyId,
        'amount' => 36.52,
        'affects_igv' => false,
        'taxable_amount' => 36.52,
        'igv_amount' => 0,
        'total_amount' => 36.52,
        'affects_inventory_cost' => true,
        'distribution_method' => 'amount',
        'description' => 'COSTO LEGACY '.$suffix,
        'status' => 'ACTIVE',
        'approval_status' => WarehouseEntryExpense::APPROVAL_APPROVED,
    ]);
    $distributionA = WarehouseEntryExpenseDistribution::create([
        'warehouse_entry_expense_id' => $expense->id,
        'warehouse_entry_item_id' => $item->id,
        'distributed_amount' => 20.87,
    ]);
    $distributionB = WarehouseEntryExpenseDistribution::create([
        'warehouse_entry_expense_id' => $expense->id,
        'warehouse_entry_item_id' => $item->id,
        'distributed_amount' => 15.65,
    ]);

    $base = [
        'company_id' => $companyId,
        'warehouse_stock_id' => $stock->id,
        'warehouse_id' => $warehouseId,
        'article_id' => $articleId,
        'unit_id' => $unitId,
        'lot_number' => 'LEGACY',
        'currency_id' => $currencyId,
        'exchange_rate' => 1,
        'status' => 'registered',
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ];

    $entryMovement = WarehouseKardexMovement::create([...$base,
        'movement_number' => 'KDX-LVR-'.$suffix.'-01',
        'movement_date' => $now->copy()->subMinutes(40),
        'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry',
        'source_type' => WarehouseEntry::class,
        'source_id' => $entry->id,
        'source_item_type' => WarehouseEntryItem::class,
        'source_item_id' => $item->id,
        'source_key' => 'lvr-entry-'.$suffix,
        'quantity_in' => 18,
        'quantity_out' => 0,
        'unit_cost' => 30,
        'total_cost_in' => 540,
        'total_cost_out' => 0,
        'balance_quantity' => 18,
        'average_unit_cost' => 30,
        'balance_total_cost' => 540,
    ]);
    $exitMovement = WarehouseKardexMovement::create([...$base,
        'movement_number' => 'KDX-LVR-'.$suffix.'-02',
        'movement_date' => $now->copy()->subMinutes(30),
        'movement_type' => 'exit',
        'operation_type' => 'customer_order_dispatch',
        'source_key' => 'lvr-exit-'.$suffix,
        'quantity_in' => 0,
        'quantity_out' => 18,
        'unit_cost' => 30,
        'total_cost_in' => 0,
        'total_cost_out' => 540,
        'balance_quantity' => 0,
        'average_unit_cost' => 0,
        'balance_total_cost' => 0,
    ]);

    $snapshotBase = [
        'sunat_establishment_code_snapshot' => '0001',
        'article_code_snapshot' => 'HIST-'.$suffix,
        'article_description_snapshot' => 'ARTÍCULO HISTÓRICO '.$suffix,
        'sunat_existence_type_code_snapshot' => '01',
        'existence_catalog_code_snapshot' => '9',
        'existence_code_snapshot' => 'HIST-'.$suffix,
        'sunat_unit_code_snapshot' => 'NIU',
        'unit_description_snapshot' => 'UNIDAD HISTÓRICA',
        'valuation_method_code_snapshot' => '1',
        'valuation_method_description_snapshot' => 'PROMEDIO PONDERADO',
        'document_date_snapshot' => $now->toDateString(),
        'sunat_document_type_code_snapshot' => null,
        'document_type' => 'SIN_COMPROBANTE',
    ];

    $linkedA = WarehouseKardexMovement::create([...$base, ...$snapshotBase,
        'movement_number' => 'KDX-LVR-'.$suffix.'-03',
        'movement_date' => $now->copy()->subMinutes(20),
        'movement_type' => 'linked_cost',
        'operation_type' => 'warehouse_entry_linked_cost',
        'sunat_operation_type_code_snapshot' => '99',
        'source_type' => WarehouseEntry::class,
        'source_id' => $entry->id,
        'source_item_type' => WarehouseEntryExpenseDistribution::class,
        'source_item_id' => $distributionA->id,
        'source_key' => 'lvr-linked-'.$suffix.'-A',
        'quantity_in' => 0,
        'quantity_out' => 0,
        'unit_cost' => 0,
        'total_cost_in' => 20.87,
        'total_cost_out' => 0,
        'balance_quantity' => 0,
        'average_unit_cost' => 0,
        'balance_total_cost' => 20.87,
    ]);
    $linkedB = WarehouseKardexMovement::create([...$base, ...$snapshotBase,
        'movement_number' => 'KDX-LVR-'.$suffix.'-04',
        'movement_date' => $now->copy()->subMinutes(10),
        'movement_type' => 'linked_cost',
        'operation_type' => 'warehouse_entry_linked_cost',
        'sunat_operation_type_code_snapshot' => '99',
        'source_type' => WarehouseEntry::class,
        'source_id' => $entry->id,
        'source_item_type' => WarehouseEntryExpenseDistribution::class,
        'source_item_id' => $distributionB->id,
        'source_key' => 'lvr-linked-'.$suffix.'-B',
        'quantity_in' => 0,
        'quantity_out' => 0,
        'unit_cost' => 0,
        'total_cost_in' => 15.65,
        'total_cost_out' => 0,
        'balance_quantity' => 0,
        'average_unit_cost' => 0,
        'balance_total_cost' => 36.52,
    ]);

    return compact(
        'companyId', 'warehouseId', 'articleId', 'currencyId', 'entry', 'item', 'stock', 'pool',
        'distributionA', 'distributionB', 'entryMovement', 'exitMovement', 'linkedA', 'linkedB'
    );
}

function legacyValuationResidualGroup(array $result, array $fixture): array
{
    return collect($result['groups'])->first(fn (array $group): bool =>
        $group['company_id'] === $fixture['companyId']
        && $group['warehouse_id'] === $fixture['warehouseId']
        && $group['article_id'] === $fixture['articleId']
    );
}

function legacyValuationKardexTotals(array $fixture): array
{
    $movements = WarehouseKardexMovement::query()
        ->where('company_id', $fixture['companyId'])
        ->where('warehouse_id', $fixture['warehouseId'])
        ->where('article_id', $fixture['articleId'])
        ->where('status', '!=', 'cancelled')
        ->get();

    return [
        'quantity' => round((float) $movements->sum(fn ($movement) => (float) $movement->quantity_in - (float) $movement->quantity_out), 4),
        'value' => round((float) $movements->sum(fn ($movement) => (float) $movement->total_cost_in - (float) $movement->total_cost_out), 2),
    ];
}

it('detecta como SAFE el residuo legacy exacto de 20.87 + 15.65', function () {
    $fixture = legacyValuationResidualFixture('SAFE');

    $result = app(WarehouseLegacyValuationResidualService::class)->audit();
    $group = legacyValuationResidualGroup($result, $fixture);

    expect($group['classification'])->toBe('SAFE')
        ->and($group['kardex_residual'])->toBe('36.52')
        ->and($group['proposed_correction'])->toBe('36.52')
        ->and(collect($group['linked_costs'])->pluck('amount')->all())->toBe(['20.87', '15.65'])
        ->and(WarehouseKardexMovement::where('source_key', 'like', 'legacy-valuation-correction:%')->count())->toBe(0);
});

it('apply-safe crea compensaciones solo de valor sin tocar stock pool ni originales', function () {
    $fixture = legacyValuationResidualFixture('APPLY');
    $originalA = $fixture['linkedA']->only(['quantity_in', 'quantity_out', 'total_cost_in', 'total_cost_out', 'source_key']);
    $originalB = $fixture['linkedB']->only(['quantity_in', 'quantity_out', 'total_cost_in', 'total_cost_out', 'source_key']);

    $result = app(WarehouseLegacyValuationResidualService::class)->audit(true);
    $totals = legacyValuationKardexTotals($fixture);
    $corrections = WarehouseKardexMovement::query()
        ->where('company_id', $fixture['companyId'])
        ->where('warehouse_id', $fixture['warehouseId'])
        ->where('article_id', $fixture['articleId'])
        ->where('operation_type', 'warehouse_entry_linked_cost_cancel')
        ->orderBy('id')
        ->get();

    expect($result['summary']['corrections_created'])->toBe(2)
        ->and($corrections)->toHaveCount(2)
        ->and($corrections->pluck('movement_type')->unique()->values()->all())->toBe(['cost_reversal'])
        ->and($corrections->map(fn ($movement) => (float) $movement->quantity_in)->all())->toBe([0.0, 0.0])
        ->and($corrections->map(fn ($movement) => (float) $movement->quantity_out)->all())->toBe([0.0, 0.0])
        ->and($corrections->map(fn ($movement) => (float) $movement->total_cost_out)->all())->toBe([20.87, 15.65])
        ->and($totals['quantity'])->toBe(0.0)
        ->and($totals['value'])->toBe(0.0)
        ->and((float) $fixture['stock']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(0.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(0.0)
        ->and($fixture['linkedA']->fresh()->only(array_keys($originalA)))->toBe($originalA)
        ->and($fixture['linkedB']->fresh()->only(array_keys($originalB)))->toBe($originalB);
});

it('es idempotente y no crea una segunda compensación', function () {
    $fixture = legacyValuationResidualFixture('IDEMP');
    $service = app(WarehouseLegacyValuationResidualService::class);

    $first = $service->audit(true);
    $second = $service->audit(true);
    $dryRun = $service->audit();
    $group = legacyValuationResidualGroup($dryRun, $fixture);

    expect($first['summary']['corrections_created'])->toBe(2)
        ->and($second['summary']['corrections_created'])->toBe(0)
        ->and(WarehouseKardexMovement::where('source_key', 'like', 'legacy-valuation-correction:%')->count())->toBe(2)
        ->and($group['classification'])->toBe('ALREADY_CORRECTED')
        ->and(legacyValuationKardexTotals($fixture)['value'])->toBe(0.0);
});

it('no considera SAFE costos vinculados registrados cuando todavía había existencia', function () {
    $fixture = legacyValuationResidualFixture('WITHQTY');
    $fixture['exitMovement']->update(['movement_date' => now()->addMinute()]);

    $group = legacyValuationResidualGroup(app(WarehouseLegacyValuationResidualService::class)->audit(), $fixture);

    expect($group['classification'])->toBe('MANUAL_REVIEW')
        ->and($group['linked_costs'])->toBe([])
        ->and(WarehouseKardexMovement::where('source_key', 'like', 'legacy-valuation-correction:%')->count())->toBe(0);
});

it('deja en revisión manual un residuo que los linked costs no explican al cien por ciento', function () {
    $fixture = legacyValuationResidualFixture('PARTIAL');
    WarehouseKardexMovement::create([
        'movement_number' => 'KDX-LVR-PARTIAL-X',
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['linkedA']->unit_id,
        'movement_date' => now(),
        'movement_type' => 'adjustment_in',
        'operation_type' => 'manual_adjustment',
        'source_key' => 'lvr-unexplained-PARTIAL',
        'quantity_in' => 0,
        'quantity_out' => 0,
        'unit_cost' => 0,
        'total_cost_in' => 3.48,
        'total_cost_out' => 0,
        'balance_quantity' => 0,
        'average_unit_cost' => 0,
        'balance_total_cost' => 40,
        'status' => 'registered',
    ]);

    $group = legacyValuationResidualGroup(app(WarehouseLegacyValuationResidualService::class)->audit(), $fixture);

    expect($group['classification'])->toBe('MANUAL_REVIEW')
        ->and($group['kardex_residual'])->toBe('40.00')
        ->and($group['proposed_correction'])->toBe('36.52');
});

it('no corrige un candidato cuyo origen histórico ya no existe', function () {
    $fixture = legacyValuationResidualFixture('MISSING');
    $fixture['linkedA']->update(['source_item_id' => 999999999]);

    $result = app(WarehouseLegacyValuationResidualService::class)->audit(true);
    $group = legacyValuationResidualGroup($result, $fixture);

    expect($group['classification'])->toBe('MANUAL_REVIEW')
        ->and($result['summary']['corrections_created'])->toBe(0)
        ->and(WarehouseKardexMovement::where('source_key', 'like', 'legacy-valuation-correction:%')->count())->toBe(0);
});

it('no aplica esta reparación si el valuation pool conserva valor material', function () {
    $fixture = legacyValuationResidualFixture('POOL');
    $fixture['pool']->update(['total_cost' => 1]);

    $group = legacyValuationResidualGroup(app(WarehouseLegacyValuationResidualService::class)->audit(), $fixture);

    expect($group['classification'])->toBe('MANUAL_REVIEW')
        ->and($group['pool_value'])->toBe('1.00');
});

it('no aplica esta reparación si el stock físico actual no está en cero', function () {
    $fixture = legacyValuationResidualFixture('STOCK');
    $fixture['stock']->update(['current_quantity' => 1]);

    $group = legacyValuationResidualGroup(app(WarehouseLegacyValuationResidualService::class)->audit(), $fixture);

    expect($group['classification'])->toBe('MANUAL_REVIEW')
        ->and($group['stock_quantity'])->toBe('1.0000');
});

it('la corrección copia snapshots del movimiento original y no consulta maestros actuales', function () {
    $fixture = legacyValuationResidualFixture('SNAP');
    DB::table('articles')->where('id', $fixture['articleId'])->update([
        'code' => 'ACTUAL-CAMBIADO',
        'billing_name' => 'NOMBRE ACTUAL CAMBIADO',
    ]);
    DB::table('units')->where('id', $fixture['linkedA']->unit_id)->update([
        'description' => 'UNIDAD ACTUAL CAMBIADA',
    ]);

    app(WarehouseLegacyValuationResidualService::class)->audit(true);
    $correction = WarehouseKardexMovement::query()
        ->where('source_key', 'legacy-valuation-correction:'.$fixture['linkedA']->id)
        ->firstOrFail();

    expect($correction->article_code_snapshot)->toBe($fixture['linkedA']->article_code_snapshot)
        ->and($correction->article_description_snapshot)->toBe($fixture['linkedA']->article_description_snapshot)
        ->and($correction->sunat_unit_code_snapshot)->toBe($fixture['linkedA']->sunat_unit_code_snapshot)
        ->and($correction->unit_description_snapshot)->toBe($fixture['linkedA']->unit_description_snapshot)
        ->and($correction->valuation_method_code_snapshot)->toBe($fixture['linkedA']->valuation_method_code_snapshot)
        ->and($correction->source_type)->toBe($fixture['linkedA']->source_type)
        ->and($correction->source_id)->toBe($fixture['linkedA']->source_id)
        ->and($correction->source_item_id)->toBe($fixture['linkedA']->source_item_id);
});

it('el comando es DRY-RUN por defecto y no crea movimientos', function () {
    legacyValuationResidualFixture('COMMAND');

    $this->artisan('inventory:audit-legacy-valuation-residuals')
        ->expectsOutputToContain('MODO: DRY-RUN')
        ->assertSuccessful();

    expect(WarehouseKardexMovement::where('source_key', 'like', 'legacy-valuation-correction:%')->count())->toBe(0);
});
