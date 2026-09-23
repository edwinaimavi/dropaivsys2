<?php

use App\Models\User;
use App\Models\WarehouseInventoryPeriodClosure;
use App\Models\WarehouseKardexMovement;
use App\Services\WarehouseInventoryPeriodClosureService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function periodClosureFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);

    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA CIERRE '.$suffix,
        'ruc' => '20'.str_pad((string) (abs(crc32('CIERRE-'.$suffix)) % 1000000000), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);

    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('CL-'.$suffix, 0, 30),
        'name' => 'ALMACÉN CIERRE '.$suffix,
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

    return compact('user', 'companyId', 'warehouseId');
}

function periodClosureArticle(array $fixture, string $suffix): int
{
    $now = now();
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => substr('U-'.$suffix, 0, 20),
        'description' => 'UNIDAD '.$suffix,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA '.$suffix,
        'code' => substr('C-'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return DB::table('articles')->insertGetId([
        'code' => substr('A-'.$suffix, 0, 20),
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO LEGAL '.$suffix,
        'billing_name' => 'ARTÍCULO '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        'is_taxable' => true,
        'minimum_stock' => 0,
        'has_batch' => false,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

it('cierra un período finalizado y conserva actor y resumen de control', function () {
    $fixture = periodClosureFixture('A'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

    $event = app(WarehouseInventoryPeriodClosureService::class)->close(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month,
        'Cierre mensual validado',
        $fixture['user']->id
    );

    expect($event->action)->toBe(WarehouseInventoryPeriodClosure::ACTION_CLOSE)
        ->and($event->created_by)->toBe($fixture['user']->id)
        ->and($event->summary['reconciliation']['error_groups'] ?? null)->toBe(0)
        ->and(app(WarehouseInventoryPeriodClosureService::class)->isClosed(
            $fixture['companyId'],
            $fixture['warehouseId'],
            $period
        ))->toBeTrue();
});

it('bloquea nuevos movimientos y cambios contables dentro de un período cerrado', function () {
    $fixture = periodClosureFixture('B'.uniqid());
    $articleId = periodClosureArticle($fixture, 'B'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

    $movement = WarehouseKardexMovement::create([
        'movement_number' => 'KDX-CLOSE-'.uniqid(),
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $articleId,
        'movement_date' => $period->addDays(2),
        'movement_type' => 'adjustment_in',
        'operation_type' => 'manual_adjustment',
        'quantity_in' => 1,
        'quantity_out' => 0,
        'balance_quantity' => 1,
        'unit_cost' => 10,
        'total_cost_in' => 10,
        'total_cost_out' => 0,
        'average_unit_cost' => 10,
        'balance_total_cost' => 10,
        'status' => 'registered',
        'created_by' => $fixture['user']->id,
        'updated_by' => $fixture['user']->id,
    ]);

    // El cierre exige que la situación física y valorizada sea coherente.
    // Este movimiento de prueba representa un ajuste de entrada real y por eso
    // se acompaña con el stock físico y el pool PPM que le corresponden.
    DB::table('warehouse_stocks')->insert([
        'stock_key' => 'CLOSE-OK-'.uniqid(),
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $articleId,
        'current_quantity' => 1,
        'reserved_quantity' => 0,
        'average_unit_cost' => 10,
        'total_cost' => 10,
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('warehouse_valuation_pools')->insert([
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $articleId,
        'current_quantity' => 1,
        'average_unit_cost' => 10,
        'total_cost' => 10,
        'created_by' => $fixture['user']->id,
        'updated_by' => $fixture['user']->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(WarehouseInventoryPeriodClosureService::class)->close(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month,
        null,
        $fixture['user']->id
    );

    expect(fn () => WarehouseKardexMovement::create([
        'movement_number' => 'KDX-BLOCK-'.uniqid(),
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $articleId,
        'movement_date' => $period->addDays(5),
        'movement_type' => 'adjustment_in',
        'operation_type' => 'manual_adjustment',
    ]))->toThrow(ValidationException::class, 'está cerrado');

    expect(fn () => $movement->update(['document_number' => 'CORREGIDO-001']))
        ->toThrow(ValidationException::class, 'está cerrado');

    // El update fallido deja el atributo rechazado sucio en esta misma instancia.
    // Refrescamos desde BD para simular una nueva operación y validar que un cambio
    // operativo de estado posterior no reescriba la información contable cerrada.
    $movement->refresh();

    $movement->update([
        'status' => 'reversed',
        'updated_by' => $fixture['user']->id,
    ]);

    expect($movement->fresh()->status)->toBe('reversed');
});

it('reabre el período sin borrar el evento de cierre y vuelve a permitir movimientos', function () {
    $fixture = periodClosureFixture('C'.uniqid());
    $articleId = periodClosureArticle($fixture, 'C'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
    $service = app(WarehouseInventoryPeriodClosureService::class);

    $close = $service->close(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month,
        'Cierre mensual',
        $fixture['user']->id
    );
    $reopen = $service->reopen(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month,
        'Corrección autorizada',
        $fixture['user']->id
    );

    expect($close->action)->toBe(WarehouseInventoryPeriodClosure::ACTION_CLOSE)
        ->and($reopen->action)->toBe(WarehouseInventoryPeriodClosure::ACTION_REOPEN)
        ->and(WarehouseInventoryPeriodClosure::query()
            ->where('company_id', $fixture['companyId'])
            ->where('warehouse_id', $fixture['warehouseId'])
            ->where('year', $period->year)
            ->where('month', $period->month)
            ->count())->toBe(2)
        ->and($service->isClosed($fixture['companyId'], $fixture['warehouseId'], $period))->toBeFalse();

    $movement = WarehouseKardexMovement::create([
        'movement_number' => 'KDX-REOPEN-'.uniqid(),
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $articleId,
        'movement_date' => $period->addDays(10),
        'movement_type' => 'adjustment_in',
        'operation_type' => 'manual_adjustment',
    ]);

    expect($movement->exists)->toBeTrue();
});

it('rechaza cerrar un período cuando la conciliación contiene errores', function () {
    $fixture = periodClosureFixture('D'.uniqid());
    $articleId = periodClosureArticle($fixture, 'D'.uniqid());
    $period = CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();

    DB::table('warehouse_stocks')->insert([
        'stock_key' => 'CLOSE-ERR-'.uniqid(),
        'company_id' => $fixture['companyId'],
        'warehouse_id' => $fixture['warehouseId'],
        'article_id' => $articleId,
        'current_quantity' => 5,
        'reserved_quantity' => 0,
        'average_unit_cost' => 10,
        'total_cost' => 50,
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => app(WarehouseInventoryPeriodClosureService::class)->close(
        $fixture['companyId'],
        $fixture['warehouseId'],
        $period->year,
        $period->month,
        null,
        $fixture['user']->id
    ))->toThrow(ValidationException::class, 'No se puede cerrar');
});
