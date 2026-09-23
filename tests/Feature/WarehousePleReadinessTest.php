<?php

use App\Models\User;
use App\Models\WarehouseInventoryPeriodClosure;
use App\Models\WarehouseKardexMovement;
use App\Services\WarehousePleReadinessService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function pleFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA PLE '.$suffix,
        'ruc' => '20'.str_pad((string) (abs(crc32('PLE-'.$suffix)) % 1000000000), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);

    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('PLE-'.$suffix, 0, 30),
        'name' => 'ALMACÉN PLE '.$suffix,
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
        'abbreviation' => substr('U'.$suffix, 0, 20),
        'description' => 'UNIDAD '.$suffix,
        'decimal_quantity' => true,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA '.$suffix,
        'code' => substr('CP'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('AP'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO PLE '.$suffix,
        'billing_name' => 'ARTÍCULO PLE '.$suffix,
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

function pleMovement(array $fixture, CarbonImmutable $period, string $quantity = '1.0000'): WarehouseKardexMovement
{
    return WarehouseKardexMovement::create([
        'movement_number' => 'KDX-PLE-'.uniqid(),
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'sunat_establishment_code_snapshot' => '0001',
        'article_code_snapshot' => 'SKU-PLE',
        'article_description_snapshot' => 'ARTÍCULO SNAPSHOT PLE',
        'sunat_existence_type_code_snapshot' => '01',
        'existence_catalog_code_snapshot' => '9',
        'existence_code_snapshot' => 'SKU-PLE',
        'sunat_unit_code_snapshot' => 'NIU',
        'unit_description_snapshot' => 'UNIDAD',
        'valuation_method_code_snapshot' => '1',
        'valuation_method_description_snapshot' => 'PROMEDIO PONDERADO',
        'movement_date' => $period->addDays(5),
        'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry',
        'sunat_operation_type_code_snapshot' => '02',
        'document_type' => 'FACTURA',
        'document_date_snapshot' => $period->addDays(5)->toDateString(),
        'sunat_document_type_code_snapshot' => '01',
        'document_series' => 'F001',
        'document_number' => '00000001',
        'quantity_in' => $quantity,
        'quantity_out' => '0.0000',
        'balance_quantity' => $quantity,
        'unit_cost' => '10.000000',
        'total_cost_in' => '10.00',
        'total_cost_out' => '0.00',
        'average_unit_cost' => '10.000000',
        'balance_total_cost' => '10.00',
        'status' => 'registered',
        'created_by' => $fixture['user']->id,
        'updated_by' => $fixture['user']->id,
    ]);
}

function closePlePeriod(array $fixture, CarbonImmutable $period): void
{
    WarehouseInventoryPeriodClosure::create([
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'year' => $period->year,
        'month' => $period->month,
        'action' => WarehouseInventoryPeriodClosure::ACTION_CLOSE,
        'reason' => 'Cierre para prueba PLE',
        'summary' => [],
        'created_by' => $fixture['user']->id,
    ]);
}

it('construye los nombres oficiales esperados para 12.1 y 13.1', function () {
    $service = app(WarehousePleReadinessService::class);

    expect($service->officialFilename('20123456789', 2026, 9, '120100', '1'))
        ->toBe('LE2012345678920260900120100001111.TXT')
        ->and($service->officialFilename('20123456789', 2026, 9, '130100', '0'))
        ->toBe('LE2012345678920260900130100001011.TXT');
});

it('bloquea un PLE con contenido cuando no existe la referencia contable CUO y correlativo', function () {
    $fixture = pleFixture('ACC'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    pleMovement($fixture, $period);
    closePlePeriod($fixture, $period);

    $report = app(WarehousePleReadinessService::class)->audit(
        $fixture['companyId'],
        $period->year,
        $period->month,
        $fixture['warehouseId']
    );

    expect($report['period_closed'])->toBeTrue()
        ->and($report['has_content'])->toBeTrue()
        ->and($report['can_generate_official_txt'])->toBeFalse()
        ->and(collect($report['blockers'])->pluck('code')->all())
        ->toContain('accounting_reference_missing')
        ->and($report['physical']['filename'])->toContain('120100')
        ->and($report['valued']['filename'])->toContain('130100');
});

it('detecta cantidades con más de dos decimales para el PLE 12.1 sin redondearlas silenciosamente', function () {
    $fixture = pleFixture('DEC'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    pleMovement($fixture, $period, '1.1234');
    closePlePeriod($fixture, $period);

    $report = app(WarehousePleReadinessService::class)->audit(
        $fixture['companyId'],
        $period->year,
        $period->month,
        $fixture['warehouseId']
    );

    expect($report['physical']['precision_issue_count'])->toBeGreaterThan(0)
        ->and(collect($report['blockers'])->pluck('code')->all())
        ->toContain('physical_quantity_precision');
});
