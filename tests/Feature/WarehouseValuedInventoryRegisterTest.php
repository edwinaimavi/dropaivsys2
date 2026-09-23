<?php

use App\Models\User;
use App\Models\WarehouseKardexMovement;
use App\Services\WarehouseValuedInventoryRegisterService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function valuedRegisterFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA F131 '.$suffix,
        'ruc' => sprintf('20%09d', abs(crc32('F131-'.$suffix)) % 1_000_000_000),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('WV'.$suffix, 0, 30),
        'name' => 'ALMACÉN F131 '.$suffix,
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
        'abbreviation' => substr('V'.$suffix, 0, 20),
        'description' => 'UNIDAD ACTUAL '.$suffix,
        'decimal_quantity' => true,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA F131 '.$suffix,
        'code' => substr('CV'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('AV'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO ACTUAL '.$suffix,
        'billing_name' => 'ARTÍCULO ACTUAL '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => true,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return compact('user', 'companyId', 'warehouseId', 'unitId', 'articleId', 'articleCode');
}

function valuedMovement(array $fixture, string $number, array $overrides = []): WarehouseKardexMovement
{
    return WarehouseKardexMovement::create(array_merge([
        'movement_number' => $number,
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'sunat_establishment_code_snapshot' => '0001',
        'article_code_snapshot' => 'COD-VAL-SNAPSHOT',
        'article_description_snapshot' => 'DESCRIPCIÓN VALORIZADA SNAPSHOT',
        'sunat_existence_type_code_snapshot' => '01',
        'existence_catalog_code_snapshot' => '9',
        'existence_code_snapshot' => 'EXIST-VAL-SNAPSHOT',
        'sunat_unit_code_snapshot' => 'NIU',
        'unit_description_snapshot' => 'UNIDAD SNAPSHOT',
        'valuation_method_code_snapshot' => '1',
        'valuation_method_description_snapshot' => 'PROMEDIO PONDERADO',
        'movement_date' => '2026-09-10 10:00:00',
        'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry',
        'sunat_operation_type_code_snapshot' => '02',
        'quantity_in' => '0.0000',
        'quantity_out' => '0.0000',
        'unit_cost' => '0.000000',
        'total_cost_in' => '0.00',
        'total_cost_out' => '0.00',
        'balance_quantity' => '0.0000',
        'average_unit_cost' => '0.000000',
        'balance_total_cost' => '0.00',
        'status' => 'registered',
    ], $overrides));
}

function valuedReport(array $fixture, ?int $articleId = null): array
{
    return app(WarehouseValuedInventoryRegisterService::class)->generate(
        $fixture['companyId'],
        2026,
        9,
        $fixture['warehouseId'],
        $articleId
    );
}

function expectValuedEquations(array $register): void
{
    $quantityUnits = static fn (string $value): int => (int) str_replace('.', '', $value);
    $costUnits = static fn (string $value): int => (int) str_replace('.', '', $value);

    expect($quantityUnits($register['final_quantity']))->toBe(
        $quantityUnits($register['initial_quantity'])
        + $quantityUnits($register['total_quantity_in'])
        - $quantityUnits($register['total_quantity_out'])
    )->and($costUnits($register['final_total_cost']))->toBe(
        $costUnits($register['initial_total_cost'])
        + $costUnits($register['total_cost_in'])
        - $costUnits($register['total_cost_out'])
    );
}

it('calcula saldo inicial valorizado y PPM global movimiento por movimiento', function () {
    $fixture = valuedRegisterFixture('OPENING');
    valuedMovement($fixture, 'F131-OPEN-1', [
        'movement_date' => '2026-08-01', 'quantity_in' => '10.0000',
        'unit_cost' => '20.000000', 'total_cost_in' => '200.00',
    ]);
    valuedMovement($fixture, 'F131-OPEN-2', [
        'movement_date' => '2026-08-02', 'movement_type' => 'exit',
        'quantity_out' => '2.0000', 'unit_cost' => '20.000000', 'total_cost_out' => '40.00',
    ]);
    valuedMovement($fixture, 'F131-OPEN-3', [
        'movement_date' => '2026-09-01', 'quantity_in' => '2.0000',
        'unit_cost' => '30.000000', 'total_cost_in' => '60.00',
    ]);
    valuedMovement($fixture, 'F131-OPEN-4', [
        'movement_date' => '2026-09-02', 'movement_type' => 'exit',
        'quantity_out' => '5.0000', 'unit_cost' => '22.000000', 'total_cost_out' => '110.00',
    ]);

    $register = valuedReport($fixture)['registers'][0];

    expect($register['initial_quantity'])->toBe('8.0000')
        ->and($register['initial_total_cost'])->toBe('160.00')
        ->and($register['initial_average_unit_cost'])->toBe('20.000000')
        ->and($register['rows'][0]['balance_quantity'])->toBe('10.0000')
        ->and($register['rows'][0]['balance_total_cost'])->toBe('220.00')
        ->and($register['rows'][0]['balance_average_unit_cost'])->toBe('22.000000')
        ->and($register['final_quantity'])->toBe('5.0000')
        ->and($register['final_total_cost'])->toBe('110.00')
        ->and($register['final_average_unit_cost'])->toBe('22.000000');
    expectValuedEquations($register);
});

it('consolida lotes en un pool global y usa el costo histórico de salida', function () {
    $fixture = valuedRegisterFixture('LOTS');
    valuedMovement($fixture, 'F131-LOT-1', [
        'lot_number' => 'LOTE-A', 'quantity_in' => '10.0000',
        'unit_cost' => '20.000000', 'total_cost_in' => '200.00',
    ]);
    valuedMovement($fixture, 'F131-LOT-2', [
        'lot_number' => 'LOTE-B', 'quantity_in' => '10.0000',
        'unit_cost' => '30.000000', 'total_cost_in' => '300.00',
    ]);
    valuedMovement($fixture, 'F131-LOT-3', [
        'lot_number' => 'LOTE-A', 'movement_type' => 'exit', 'quantity_out' => '4.0000',
        'unit_cost' => '25.000000', 'total_cost_out' => '100.00',
    ]);

    $report = valuedReport($fixture);
    $register = $report['registers'][0];

    expect($report['registers'])->toHaveCount(1)
        ->and($register['rows'][2]['exit_unit_cost'])->toBe('25.000000')
        ->and($register['final_quantity'])->toBe('16.0000')
        ->and($register['final_total_cost'])->toBe('400.00')
        ->and($register['final_average_unit_cost'])->toBe('25.000000');
    expectValuedEquations($register);
});

it('aplica costo vinculado al valor sin modificar unidades', function () {
    $fixture = valuedRegisterFixture('LINKED');
    valuedMovement($fixture, 'F131-LINK-1', [
        'quantity_in' => '10.0000', 'unit_cost' => '20.000000', 'total_cost_in' => '200.00',
    ]);
    valuedMovement($fixture, 'F131-LINK-2', [
        'movement_date' => '2026-09-11', 'movement_type' => 'linked_cost',
        'operation_type' => 'warehouse_entry_linked_cost', 'sunat_operation_type_code_snapshot' => '99',
        'quantity_in' => '0.0000', 'quantity_out' => '0.0000', 'total_cost_in' => '50.00',
    ]);

    $register = valuedReport($fixture)['registers'][0];
    $linkedRow = $register['rows'][1];

    expect($linkedRow['quantity_in'])->toBe('0.0000')
        ->and($linkedRow['entry_unit_cost'])->toBeNull()
        ->and($linkedRow['total_cost_in'])->toBe('50.00')
        ->and($register['final_quantity'])->toBe('10.0000')
        ->and($register['final_total_cost'])->toBe('250.00')
        ->and($register['final_average_unit_cost'])->toBe('25.000000');
    expectValuedEquations($register);
});

it('revierte un costo vinculado sin modificar unidades', function () {
    $fixture = valuedRegisterFixture('COST-REV');
    valuedMovement($fixture, 'F131-CREV-1', [
        'quantity_in' => '10.0000', 'unit_cost' => '20.000000', 'total_cost_in' => '200.00',
    ]);
    valuedMovement($fixture, 'F131-CREV-2', [
        'movement_date' => '2026-09-11', 'movement_type' => 'linked_cost',
        'operation_type' => 'warehouse_entry_linked_cost', 'sunat_operation_type_code_snapshot' => '99',
        'total_cost_in' => '50.00',
    ]);
    valuedMovement($fixture, 'F131-CREV-3', [
        'movement_date' => '2026-09-12', 'movement_type' => 'cost_reversal',
        'operation_type' => 'warehouse_entry_linked_cost_cancel', 'sunat_operation_type_code_snapshot' => '99',
        'total_cost_out' => '50.00',
    ]);

    $register = valuedReport($fixture)['registers'][0];

    expect($register['final_quantity'])->toBe('10.0000')
        ->and($register['final_total_cost'])->toBe('200.00')
        ->and($register['final_average_unit_cost'])->toBe('20.000000');
    expectValuedEquations($register);
});

it('valoriza devolución con el costo histórico almacenado y no con el PPM actual', function () {
    $fixture = valuedRegisterFixture('RETURN');
    valuedMovement($fixture, 'F131-RETURN-1', [
        'movement_date' => '2026-08-10', 'quantity_in' => '10.0000',
        'unit_cost' => '30.000000', 'total_cost_in' => '300.00',
    ]);
    valuedMovement($fixture, 'F131-RETURN-2', [
        'movement_type' => 'entry', 'operation_type' => 'customer_return',
        'sunat_operation_type_code_snapshot' => '24', 'quantity_in' => '2.0000',
        'unit_cost' => '25.000000', 'total_cost_in' => '50.00',
    ]);

    $register = valuedReport($fixture)['registers'][0];

    expect($register['rows'][0]['entry_unit_cost'])->toBe('25.000000')
        ->and($register['rows'][0]['total_cost_in'])->toBe('50.00')
        ->and($register['final_quantity'])->toBe('12.0000')
        ->and($register['final_total_cost'])->toBe('350.00')
        ->and($register['final_average_unit_cost'])->toBe('29.166667');
    expectValuedEquations($register);
});

it('conserva original revertido y movimiento inverso hasta saldo cero', function () {
    $fixture = valuedRegisterFixture('REVERSAL');
    valuedMovement($fixture, 'F131-REV-1', [
        'quantity_in' => '10.0000', 'unit_cost' => '30.000000',
        'total_cost_in' => '300.00', 'status' => 'reversed',
    ]);
    valuedMovement($fixture, 'F131-REV-2', [
        'movement_date' => '2026-09-11', 'movement_type' => 'reversal',
        'operation_type' => 'warehouse_entry_cancel', 'sunat_operation_type_code_snapshot' => '99',
        'quantity_out' => '10.0000', 'unit_cost' => '30.000000', 'total_cost_out' => '300.00',
    ]);

    $register = valuedReport($fixture)['registers'][0];

    expect(array_column($register['rows'], 'movement_number'))->toBe(['F131-REV-1', 'F131-REV-2'])
        ->and($register['final_quantity'])->toBe('0.0000')
        ->and($register['final_total_cost'])->toBe('0.00')
        ->and($register['final_average_unit_cost'])->toBe('0.000000');
    expectValuedEquations($register);
});

it('mantiene snapshots de Tablas 5 6 10 12 y 14 tras cambios de maestros', function () {
    $fixture = valuedRegisterFixture('SNAPSHOT');
    valuedMovement($fixture, 'F131-SNAPSHOT-1', [
        'document_type' => 'FACTURA', 'document_date_snapshot' => '2026-09-05',
        'sunat_document_type_code_snapshot' => '01', 'document_series' => 'F001',
        'document_number' => '00000001', 'quantity_in' => '4.0000',
        'unit_cost' => '12.500000', 'total_cost_in' => '50.00',
    ]);
    DB::table('articles')->where('id', $fixture['articleId'])->update(['billing_name' => 'NOMBRE MAESTRO NUEVO']);
    DB::table('units')->where('id', $fixture['unitId'])->update(['description' => 'UNIDAD MAESTRA NUEVA']);
    DB::table('companies')->where('id', $fixture['companyId'])->update([
        'business_name' => 'EMPRESA CON MÉTODO MODIFICADO',
        'inventory_valuation_method_item_id' => testSunatUnitItemId(),
    ]);
    DB::table('company_warehouses')->where('company_id', $fixture['companyId'])->update(['sunat_establishment_code' => '9999']);

    $register = valuedReport($fixture)['registers'][0];
    $row = $register['rows'][0];

    expect($register['description'])->toBe('DESCRIPCIÓN VALORIZADA SNAPSHOT')
        ->and($register['existence_type_code'])->toBe('01')
        ->and($register['unit_code'])->toBe('NIU')
        ->and($register['establishment_code'])->toBe('0001')
        ->and($register['valuation_method_code'])->toBe('1')
        ->and($register['valuation_method_description'])->toBe('PROMEDIO PONDERADO')
        ->and($row['document_type_code'])->toBe('01')
        ->and($row['operation_type_code'])->toBe('02');
});

it('advierte histórico incompleto sin inventar snapshots', function () {
    $fixture = valuedRegisterFixture('INCOMPLETE');
    valuedMovement($fixture, 'F131-INCOMPLETE-1', [
        'sunat_establishment_code_snapshot' => null,
        'article_code_snapshot' => null,
        'article_description_snapshot' => null,
        'sunat_existence_type_code_snapshot' => null,
        'existence_catalog_code_snapshot' => null,
        'existence_code_snapshot' => null,
        'sunat_unit_code_snapshot' => null,
        'valuation_method_code_snapshot' => null,
        'valuation_method_description_snapshot' => null,
        'sunat_operation_type_code_snapshot' => null,
        'quantity_in' => '3.0000', 'unit_cost' => '10.000000', 'total_cost_in' => '30.00',
    ]);

    $report = valuedReport($fixture);
    $register = $report['registers'][0];

    expect($report['incomplete_snapshot_count'])->toBeGreaterThan(0)
        ->and($register['description'])->toBeNull()
        ->and($register['valuation_method_code'])->toBeNull()
        ->and($register['rows'][0]['operation_type_code'])->toBeNull()
        ->and($register['final_total_cost'])->toBe('30.00');
});

it('segrega simultáneamente empresa y almacén', function () {
    $companyA = valuedRegisterFixture('SEG-A');
    $companyB = valuedRegisterFixture('SEG-B');
    valuedMovement($companyA, 'F131-SEG-A-1', [
        'quantity_in' => '5.0000', 'unit_cost' => '20.000000', 'total_cost_in' => '100.00',
    ]);
    valuedMovement($companyB, 'F131-SEG-B-1', [
        'quantity_in' => '9.0000', 'unit_cost' => '30.000000', 'total_cost_in' => '270.00',
    ]);
    $otherWarehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WVSEGA2', 'name' => 'ALMACÉN SEGUNDO', 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $companyA['companyId'], 'warehouse_id' => $otherWarehouseId,
        'sunat_establishment_code' => '0002', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    valuedMovement($companyA, 'F131-SEG-A-2', [
        'warehouse_id' => $otherWarehouseId, 'quantity_in' => '7.0000',
        'unit_cost' => '40.000000', 'total_cost_in' => '280.00',
    ]);

    $report = valuedReport($companyA);

    expect($report['registers'])->toHaveCount(1)
        ->and($report['registers'][0]['final_quantity'])->toBe('5.0000')
        ->and($report['registers'][0]['final_total_cost'])->toBe('100.00')
        ->and(array_column($report['registers'][0]['rows'], 'movement_number'))->toBe(['F131-SEG-A-1']);
});

it('reporta cantidad cero con valor residual sin corregir el histórico', function () {
    $fixture = valuedRegisterFixture('INCONSISTENCY');
    valuedMovement($fixture, 'F131-INC-1', [
        'quantity_in' => '1.0000', 'unit_cost' => '10.000000', 'total_cost_in' => '10.00',
    ]);
    valuedMovement($fixture, 'F131-INC-2', [
        'movement_date' => '2026-09-11', 'movement_type' => 'exit',
        'quantity_out' => '1.0000', 'unit_cost' => '9.000000', 'total_cost_out' => '9.00',
    ]);

    $report = valuedReport($fixture);
    $register = $report['registers'][0];

    expect($report['valuation_inconsistency_count'])->toBe(1)
        ->and($register['has_valuation_inconsistency'])->toBeTrue()
        ->and($register['final_quantity'])->toBe('0.0000')
        ->and($register['final_total_cost'])->toBe('1.00')
        ->and($register['final_average_unit_cost'])->toBe('0.000000');
});

it('muestra el endpoint autorizado con columnas completas del Formato 13.1', function () {
    $fixture = valuedRegisterFixture('HTTP');
    valuedMovement($fixture, 'F131-HTTP-1', [
        'quantity_in' => '2.0000', 'unit_cost' => '15.000000', 'total_cost_in' => '30.00',
    ]);
    Permission::findOrCreate('admin.kardex.index', 'web');
    $fixture['user']->givePermissionTo('admin.kardex.index');

    $response = $this->actingAs($fixture['user'])->get(route('admin.kardex.formato-13-1', [
        'consult' => 1,
        'company_id' => $fixture['companyId'],
        'year' => 2026,
        'month' => 9,
        'warehouse_id' => $fixture['warehouseId'],
    ]));

    $response->assertOk()
        ->assertSee('FORMATO 13.1')
        ->assertSee('ENTRADAS')
        ->assertSee('SALIDAS')
        ->assertSee('SALDO FINAL')
        ->assertSee('COSTO UNITARIO')
        ->assertSee('COSTO TOTAL');
});


it('exporta el Formato 13.1 a Excel y PDF con los mismos filtros del registro', function () {
    $fixture = valuedRegisterFixture('EXPORT');
    valuedMovement($fixture, 'F131-EXPORT-1', [
        'document_type' => 'FACTURA',
        'document_date_snapshot' => '2026-09-05',
        'sunat_document_type_code_snapshot' => '01',
        'document_series' => 'F001',
        'document_number' => '00000199',
        'quantity_in' => '2.0000',
        'unit_cost' => '15.000000',
        'total_cost_in' => '30.00',
    ]);
    Permission::findOrCreate('admin.kardex.export', 'web');
    $fixture['user']->givePermissionTo('admin.kardex.export');

    $filters = [
        'company_id' => $fixture['companyId'],
        'year' => 2026,
        'month' => 9,
        'warehouse_id' => $fixture['warehouseId'],
    ];

    $excel = $this->actingAs($fixture['user'])->get(route('admin.kardex.formato-13-1.export', ['format' => 'excel', ...$filters]));
    $excel->assertOk()->assertSee('FORMATO 13.1')->assertSee('00000199');
    expect($excel->headers->get('content-type'))->toContain('application/vnd.ms-excel')
        ->and($excel->headers->get('content-disposition'))->toContain('.xls');

    $pdf = $this->actingAs($fixture['user'])->get(route('admin.kardex.formato-13-1.export', ['format' => 'pdf', ...$filters]));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toContain('.pdf');
});
