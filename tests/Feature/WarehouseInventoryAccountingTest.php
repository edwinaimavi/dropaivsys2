<?php

use App\Models\AccountingAccount;
use App\Models\AccountingJournalEntry;
use App\Models\User;
use App\Models\WarehouseInventoryPeriodClosure;
use App\Models\WarehouseKardexMovement;
use App\Services\InventoryAccountingService;
use App\Services\WarehousePleReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function inventoryAccountingFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA CONTABLE '.$suffix,
        'ruc' => '20'.str_pad((string) (abs(crc32('ACC-'.$suffix)) % 1000000000), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);

    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('ACC-'.$suffix, 0, 30),
        'name' => 'ALMACÉN CONTABLE '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyId,
        'warehouse_id' => $warehouseId,
        'sunat_establishment_code' => '0001',
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('CA'.$suffix, 0, 20),
        'description' => 'UNIDAD CONTABLE '.$suffix,
        'decimal_quantity' => true,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA CONTABLE '.$suffix,
        'code' => substr('CCA'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('AACC'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO CONTABLE '.$suffix,
        'billing_name' => 'ARTÍCULO CONTABLE '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => false,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return compact('user', 'companyId', 'warehouseId', 'unitId', 'articleId');
}

function inventoryAccountingMovement(
    array $fixture,
    CarbonImmutable $period,
    string $operation = 'warehouse_entry',
    string $totalIn = '10.00',
    string $totalOut = '0.00'
): WarehouseKardexMovement {
    $isEntry = (float) $totalIn > 0;

    return WarehouseKardexMovement::create([
        'movement_number' => 'KDX-ACC-'.uniqid(),
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'sunat_establishment_code_snapshot' => '0001',
        'article_code_snapshot' => 'SKU-ACC',
        'article_description_snapshot' => 'ARTÍCULO CONTABLE SNAPSHOT',
        'sunat_existence_type_code_snapshot' => '01',
        'existence_catalog_code_snapshot' => '9',
        'existence_code_snapshot' => 'SKU-ACC',
        'sunat_unit_code_snapshot' => 'NIU',
        'unit_description_snapshot' => 'UNIDAD',
        'valuation_method_code_snapshot' => '1',
        'valuation_method_description_snapshot' => 'PROMEDIO PONDERADO',
        'movement_date' => $period->addDays(5),
        'movement_type' => $isEntry ? 'entry' : 'exit',
        'operation_type' => $operation,
        'sunat_operation_type_code_snapshot' => $operation === 'warehouse_entry' ? '02' : '99',
        'document_type' => 'FACTURA',
        'document_date_snapshot' => $period->addDays(5)->toDateString(),
        'sunat_document_type_code_snapshot' => '01',
        'document_series' => 'F001',
        'document_number' => '00000001',
        'quantity_in' => $isEntry ? '1.0000' : '0.0000',
        'quantity_out' => $isEntry ? '0.0000' : '1.0000',
        'balance_quantity' => $isEntry ? '1.0000' : '0.0000',
        'unit_cost' => '10.000000',
        'total_cost_in' => $totalIn,
        'total_cost_out' => $totalOut,
        'average_unit_cost' => '10.000000',
        'balance_total_cost' => $isEntry ? $totalIn : '0.00',
        'status' => 'registered',
        'created_by' => $fixture['user']->id,
        'updated_by' => $fixture['user']->id,
    ]);
}

function closeInventoryAccountingPeriod(array $fixture, CarbonImmutable $period): void
{
    WarehouseInventoryPeriodClosure::create([
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'year' => $period->year,
        'month' => $period->month,
        'action' => WarehouseInventoryPeriodClosure::ACTION_CLOSE,
        'reason' => 'Cierre para prueba contable',
        'summary' => [],
        'created_by' => $fixture['user']->id,
    ]);
}

function configureInventoryAccounting(array $fixture): array
{
    $inventory = AccountingAccount::create([
        'company_id' => $fixture['companyId'],
        'code' => 'INV-'.uniqid(),
        'name' => 'Existencias prueba',
        'status' => true,
    ]);
    $receipt = AccountingAccount::create([
        'company_id' => $fixture['companyId'],
        'code' => 'REC-'.uniqid(),
        'name' => 'Contrapartida ingresos prueba',
        'status' => true,
    ]);

    app(InventoryAccountingService::class)->saveSettings($fixture['companyId'], [
        'inventory_account_id' => $inventory->id,
        'receipt_offset_account_id' => $receipt->id,
    ], $fixture['user']->id);

    return compact('inventory', 'receipt');
}

it('bloquea contabilización cuando faltan las cuentas configuradas', function () {
    $fixture = inventoryAccountingFixture('MISS'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    inventoryAccountingMovement($fixture, $period);
    closeInventoryAccountingPeriod($fixture, $period);

    $audit = app(InventoryAccountingService::class)->auditPeriod(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month
    );

    expect($audit['can_post'])->toBeFalse()
        ->and(collect($audit['blockers'])->pluck('code')->all())
        ->toContain('accounting_settings_missing');
});

it('genera asiento balanceado y conserva CUO y correlativo en el Kardex', function () {
    $fixture = inventoryAccountingFixture('POST'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    $movement = inventoryAccountingMovement($fixture, $period);
    $accounts = configureInventoryAccounting($fixture);
    closeInventoryAccountingPeriod($fixture, $period);

    $result = app(InventoryAccountingService::class)->postPeriod(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month,
        $fixture['user']->id
    );

    $movement->refresh();
    $entry = AccountingJournalEntry::query()
        ->with('lines')
        ->where('warehouse_kardex_movement_id', $movement->id)
        ->sole();

    expect($result['created_count'])->toBe(1)
        ->and($entry->total_debit)->toBe('10.00')
        ->and($entry->total_credit)->toBe('10.00')
        ->and($entry->lines)->toHaveCount(2)
        ->and((float) $entry->lines->sum('debit'))->toBe(10.0)
        ->and((float) $entry->lines->sum('credit'))->toBe(10.0)
        ->and($entry->lines->pluck('account_id')->all())->toContain($accounts['inventory']->id, $accounts['receipt']->id)
        ->and($movement->accounting_cuo_snapshot)->toBe($entry->cuo)
        ->and($movement->accounting_entry_correlative_snapshot)->toBe($entry->correlative)
        ->and($movement->accounting_posted_at)->not->toBeNull();

    $ple = app(WarehousePleReadinessService::class)->audit(
        $fixture['companyId'],
        $period->year,
        $period->month,
        $fixture['warehouseId']
    );

    expect(collect($ple['blockers'])->pluck('code')->all())
        ->not->toContain('accounting_reference_missing');
});

it('es idempotente y no duplica un asiento ya generado', function () {
    $fixture = inventoryAccountingFixture('IDEM'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    $movement = inventoryAccountingMovement($fixture, $period);
    configureInventoryAccounting($fixture);
    closeInventoryAccountingPeriod($fixture, $period);

    $service = app(InventoryAccountingService::class);
    $service->postPeriod($fixture['companyId'], $fixture['warehouseId'], $period->year, $period->month, $fixture['user']->id);
    $second = $service->postPeriod($fixture['companyId'], $fixture['warehouseId'], $period->year, $period->month, $fixture['user']->id);

    expect(AccountingJournalEntry::where('warehouse_kardex_movement_id', $movement->id)->count())->toBe(1)
        ->and($second['created_count'])->toBe(0)
        ->and($second['existing_count'])->toBe(1);
});

it('bloquea operaciones sin regla contable en vez de inventar una contrapartida', function () {
    $fixture = inventoryAccountingFixture('UNKNOWN'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    inventoryAccountingMovement($fixture, $period, 'mystery_operation');
    configureInventoryAccounting($fixture);
    closeInventoryAccountingPeriod($fixture, $period);

    $audit = app(InventoryAccountingService::class)->auditPeriod(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month
    );

    expect($audit['can_post'])->toBeFalse()
        ->and(collect($audit['blockers'])->pluck('code')->all())
        ->toContain('unsupported_operation');

    expect(fn () => app(InventoryAccountingService::class)->postPeriod(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month,
        $fixture['user']->id
    ))->toThrow(ValidationException::class);
});
