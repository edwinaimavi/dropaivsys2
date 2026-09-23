<?php

use App\Models\User;
use App\Models\WarehouseKardexMovement;
use App\Services\WarehousePhysicalInventoryRegisterService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function physicalRegisterFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA F121 '.$suffix,
        'ruc' => sprintf('20%09d', abs(crc32('F121-'.$suffix)) % 1_000_000_000),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('WF'.$suffix, 0, 30),
        'name' => 'ALMACÉN F121 '.$suffix,
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
        'description' => 'UNIDAD ACTUAL '.$suffix,
        'decimal_quantity' => true,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA F121 '.$suffix,
        'code' => substr('CF'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('AF'.$suffix, 0, 20);
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

function physicalMovement(array $fixture, string $number, array $overrides = []): WarehouseKardexMovement
{
    return WarehouseKardexMovement::create(array_merge([
        'movement_number' => $number,
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'],
        'sunat_establishment_code_snapshot' => '0001',
        'article_code_snapshot' => 'COD-SNAPSHOT',
        'article_description_snapshot' => 'DESCRIPCIÓN SNAPSHOT',
        'sunat_existence_type_code_snapshot' => '01',
        'existence_catalog_code_snapshot' => '9',
        'existence_code_snapshot' => 'EXIST-SNAPSHOT',
        'sunat_unit_code_snapshot' => 'NIU',
        'unit_description_snapshot' => 'UNIDAD SNAPSHOT',
        'movement_date' => '2026-09-10 10:00:00',
        'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry',
        'sunat_operation_type_code_snapshot' => '02',
        'quantity_in' => '0.0000',
        'quantity_out' => '0.0000',
        'balance_quantity' => '0.0000',
        'status' => 'registered',
    ], $overrides));
}

function physicalReport(array $fixture, ?int $articleId = null): array
{
    return app(WarehousePhysicalInventoryRegisterService::class)->generate(
        $fixture['companyId'],
        2026,
        9,
        $fixture['warehouseId'],
        $articleId
    );
}

function expectPhysicalEquation(array $register): void
{
    $toUnits = static fn (string $value): int => (int) str_replace('.', '', $value);

    expect($toUnits($register['final_balance']))->toBe(
        $toUnits($register['opening_balance'])
        + $toUnits($register['total_entries'])
        - $toUnits($register['total_exits'])
    );
}

it('calcula saldo inicial y movimientos del período exclusivamente desde Kardex', function () {
    $fixture = physicalRegisterFixture('OPENING');
    physicalMovement($fixture, 'F121-OPEN-1', ['movement_date' => '2026-08-01', 'quantity_in' => '10.0000']);
    physicalMovement($fixture, 'F121-OPEN-2', ['movement_date' => '2026-08-02', 'movement_type' => 'exit', 'quantity_out' => '3.0000']);
    physicalMovement($fixture, 'F121-OPEN-3', ['movement_date' => '2026-09-01', 'quantity_in' => '5.0000']);
    physicalMovement($fixture, 'F121-OPEN-4', ['movement_date' => '2026-09-02', 'movement_type' => 'exit', 'quantity_out' => '2.0000']);

    $register = physicalReport($fixture)['registers'][0];

    expect($register['opening_balance'])->toBe('7.0000')
        ->and($register['total_entries'])->toBe('5.0000')
        ->and($register['total_exits'])->toBe('2.0000')
        ->and($register['final_balance'])->toBe('10.0000');
    expectPhysicalEquation($register);
});

it('consolida dos lotes del mismo artículo en un único saldo global', function () {
    $fixture = physicalRegisterFixture('LOTS');
    physicalMovement($fixture, 'F121-LOT-1', ['lot_number' => 'LOTE-A', 'quantity_in' => '10.0000']);
    physicalMovement($fixture, 'F121-LOT-2', ['lot_number' => 'LOTE-B', 'quantity_in' => '20.0000']);
    physicalMovement($fixture, 'F121-LOT-3', ['lot_number' => 'LOTE-A', 'movement_type' => 'exit', 'quantity_out' => '4.0000']);

    $report = physicalReport($fixture);
    $register = $report['registers'][0];

    expect($report['registers'])->toHaveCount(1)
        ->and($register['final_balance'])->toBe('26.0000');
    expectPhysicalEquation($register);
});

it('mantiene el saldo físico ante un movimiento de costo vinculado sin cantidad', function () {
    $fixture = physicalRegisterFixture('COST');
    physicalMovement($fixture, 'F121-COST-1', ['quantity_in' => '8.5000']);
    physicalMovement($fixture, 'F121-COST-2', [
        'movement_type' => 'linked_cost',
        'operation_type' => 'warehouse_entry_linked_cost',
        'sunat_operation_type_code_snapshot' => '99',
        'quantity_in' => '0.0000',
        'quantity_out' => '0.0000',
    ]);

    $register = physicalReport($fixture)['registers'][0];

    expect($register['rows'])->toHaveCount(2)
        ->and($register['final_balance'])->toBe('8.5000');
    expectPhysicalEquation($register);
});

it('incluye el original revertido y su movimiento inverso en orden histórico', function () {
    $fixture = physicalRegisterFixture('REVERSAL');
    physicalMovement($fixture, 'F121-REV-1', ['quantity_in' => '10.0000', 'status' => 'reversed']);
    physicalMovement($fixture, 'F121-REV-2', [
        'movement_date' => '2026-09-11 10:00:00',
        'movement_type' => 'reversal',
        'operation_type' => 'warehouse_entry_cancel',
        'sunat_operation_type_code_snapshot' => '99',
        'quantity_out' => '10.0000',
    ]);

    $register = physicalReport($fixture)['registers'][0];

    expect(array_column($register['rows'], 'movement_number'))->toBe(['F121-REV-1', 'F121-REV-2'])
        ->and($register['final_balance'])->toBe('0.0000');
    expectPhysicalEquation($register);
});

it('ordena movimientos con la misma fecha por id ascendente', function () {
    $fixture = physicalRegisterFixture('ORDER');
    physicalMovement($fixture, 'F121-ORDER-1', ['quantity_in' => '1.0000']);
    physicalMovement($fixture, 'F121-ORDER-2', ['quantity_in' => '2.0000']);

    $rows = physicalReport($fixture)['registers'][0]['rows'];

    expect(array_column($rows, 'movement_number'))->toBe(['F121-ORDER-1', 'F121-ORDER-2'])
        ->and(array_column($rows, 'balance'))->toBe(['1.0000', '3.0000']);
});

it('usa snapshots SUNAT y documentales aunque cambien los maestros', function () {
    $fixture = physicalRegisterFixture('SNAPSHOT');
    physicalMovement($fixture, 'F121-SNAPSHOT-1', [
        'document_type' => 'FACTURA',
        'document_date_snapshot' => '2026-09-05',
        'sunat_document_type_code_snapshot' => '01',
        'document_series' => 'F001',
        'document_number' => '00000001',
        'sunat_operation_type_code_snapshot' => '02',
        'quantity_in' => '4.0000',
    ]);
    DB::table('articles')->where('id', $fixture['articleId'])->update(['billing_name' => 'NOMBRE MAESTRO NUEVO']);
    DB::table('units')->where('id', $fixture['unitId'])->update(['description' => 'UNIDAD MAESTRA NUEVA']);
    DB::table('company_warehouses')->where('company_id', $fixture['companyId'])->update(['sunat_establishment_code' => '9999']);

    $register = physicalReport($fixture)['registers'][0];
    $row = $register['rows'][0];

    expect($register['description'])->toBe('DESCRIPCIÓN SNAPSHOT')
        ->and($register['existence_type_code'])->toBe('01')
        ->and($register['unit_code'])->toBe('NIU')
        ->and($register['establishment_code'])->toBe('0001')
        ->and($row['document_type_code'])->toBe('01')
        ->and($row['operation_type_code'])->toBe('02')
        ->and($row['document_series'])->toBe('F001')
        ->and($row['document_number'])->toBe('00000001');
});

it('reporta histórico sin snapshots como incompleto sin inventar valores', function () {
    $fixture = physicalRegisterFixture('INCOMPLETE');
    physicalMovement($fixture, 'F121-INCOMPLETE-1', [
        'sunat_establishment_code_snapshot' => null,
        'article_code_snapshot' => null,
        'article_description_snapshot' => null,
        'sunat_existence_type_code_snapshot' => null,
        'existence_code_snapshot' => null,
        'sunat_unit_code_snapshot' => null,
        'sunat_operation_type_code_snapshot' => null,
        'quantity_in' => '3.0000',
    ]);

    $report = physicalReport($fixture);
    $register = $report['registers'][0];

    expect($report['incomplete_snapshot_count'])->toBeGreaterThan(0)
        ->and($register['description'])->toBeNull()
        ->and($register['existence_type_code'])->toBeNull()
        ->and($register['unit_code'])->toBeNull()
        ->and($register['rows'][0]['operation_type_code'])->toBeNull()
        ->and($register['final_balance'])->toBe('3.0000');
});

it('segrega estrictamente los movimientos por empresa', function () {
    $companyA = physicalRegisterFixture('COMPANY-A');
    $companyB = physicalRegisterFixture('COMPANY-B');
    physicalMovement($companyA, 'F121-COMPANY-A-1', ['quantity_in' => '5.0000']);
    physicalMovement($companyB, 'F121-COMPANY-B-1', ['quantity_in' => '99.0000']);

    $report = physicalReport($companyA);

    expect($report['registers'])->toHaveCount(1)
        ->and($report['registers'][0]['final_balance'])->toBe('5.0000')
        ->and(array_column($report['registers'][0]['rows'], 'movement_number'))->not->toContain('F121-COMPANY-B-1');
});

it('segrega saldos y movimientos por almacén', function () {
    $fixture = physicalRegisterFixture('WAREHOUSE');
    physicalMovement($fixture, 'F121-WAREHOUSE-1', ['quantity_in' => '6.0000']);
    $otherWarehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'WFWAREHOUSE2', 'name' => 'ALMACÉN DOS', 'status' => 'ACTIVE',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('company_warehouses')->insert([
        'company_id' => $fixture['companyId'], 'warehouse_id' => $otherWarehouseId,
        'sunat_establishment_code' => '0002', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    physicalMovement($fixture, 'F121-WAREHOUSE-2', ['warehouse_id' => $otherWarehouseId, 'quantity_in' => '40.0000']);

    $report = physicalReport($fixture);

    expect($report['registers'][0]['final_balance'])->toBe('6.0000')
        ->and(array_column($report['registers'][0]['rows'], 'movement_number'))->toBe(['F121-WAREHOUSE-1']);
});

it('muestra el endpoint autorizado con encabezados físicos y sin columnas de costos', function () {
    $fixture = physicalRegisterFixture('HTTP');
    physicalMovement($fixture, 'F121-HTTP-1', ['quantity_in' => '2.0000']);
    Permission::findOrCreate('admin.kardex.index', 'web');
    $fixture['user']->givePermissionTo('admin.kardex.index');

    $response = $this->actingAs($fixture['user'])->get(route('admin.kardex.formato-12-1', [
        'consult' => 1,
        'company_id' => $fixture['companyId'],
        'year' => 2026,
        'month' => 9,
        'warehouse_id' => $fixture['warehouseId'],
    ]));

    $response->assertOk()
        ->assertSee('FORMATO 12.1')
        ->assertSee('ENTRADAS')
        ->assertSee('SALIDAS')
        ->assertSee('SALDO FINAL')
        ->assertDontSee('COSTO UNITARIO')
        ->assertDontSee('VALORACIÓN');
});


it('exporta el Formato 12.1 a Excel y PDF con los mismos filtros del registro', function () {
    $fixture = physicalRegisterFixture('EXPORT');
    physicalMovement($fixture, 'F121-EXPORT-1', [
        'document_type' => 'FACTURA',
        'document_date_snapshot' => '2026-09-05',
        'sunat_document_type_code_snapshot' => '01',
        'document_series' => 'F001',
        'document_number' => '00000099',
        'quantity_in' => '2.0000',
    ]);
    Permission::findOrCreate('admin.kardex.export', 'web');
    $fixture['user']->givePermissionTo('admin.kardex.export');

    $filters = [
        'company_id' => $fixture['companyId'],
        'year' => 2026,
        'month' => 9,
        'warehouse_id' => $fixture['warehouseId'],
    ];

    $excel = $this->actingAs($fixture['user'])->get(route('admin.kardex.formato-12-1.export', ['format' => 'excel', ...$filters]));
    $excel->assertOk()->assertSee('FORMATO 12.1')->assertSee('00000099');
    expect($excel->headers->get('content-type'))->toContain('application/vnd.ms-excel')
        ->and($excel->headers->get('content-disposition'))->toContain('.xls');

    $pdf = $this->actingAs($fixture['user'])->get(route('admin.kardex.formato-12-1.export', ['format' => 'pdf', ...$filters]));
    $pdf->assertOk();
    expect($pdf->headers->get('content-type'))->toContain('application/pdf')
        ->and($pdf->headers->get('content-disposition'))->toContain('.pdf');
});
