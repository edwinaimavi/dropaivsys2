<?php

use App\Models\ElectronicInvoice;
use App\Models\User;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use App\Services\WarehouseKardexService;
use App\Services\WarehouseValuationPoolService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

function electronicInvoiceValuationPoolFixture(string $suffix): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMPRESA FACTURA PPM '.$suffix,
        'ruc' => '20'.str_pad((string) (crc32($suffix) % 1_000_000_000), 9, '0', STR_PAD_LEFT),
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $user->companies()->attach($companyId);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'PEN',
        'description' => 'SOLES',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20'.str_pad((string) ((crc32($suffix) + 1) % 1_000_000_000), 9, '0', STR_PAD_LEFT),
        'business_name' => 'PROVEEDOR FACTURA PPM '.$suffix,
        'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => substr('FW'.$suffix, 0, 20),
        'name' => 'ALMACÉN FACTURA PPM '.$suffix,
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
        'description' => 'UNIDAD FACTURA PPM '.$suffix,
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'PRODUCTO FACTURA PPM '.$suffix,
        'code' => substr('FC'.$suffix, 0, 20),
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $articleCode = substr('FA'.$suffix, 0, 20);
    $articleId = DB::table('articles')->insertGetId([
        'code' => $articleCode,
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO FACTURA PPM '.$suffix,
        'billing_name' => 'ARTÍCULO FACTURA PPM '.$suffix,
        'item_kind' => 'product',
        'is_inventory_item' => true,
        ...testSunatInventoryArticleFields($articleCode),
        'has_batch' => true,
        'has_expiration' => false,
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $entryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-FAC-PPM-'.$suffix,
        'warehouse_id' => $warehouseId,
        'company_id' => $companyId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'status' => 'registered',
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
        'reserved_quantity' => 0,
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
        'reserved_quantity' => 0,
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
    $invoice = ElectronicInvoice::create([
        'company_id' => $companyId,
        'warehouse_entry_id' => $entryId,
        'warehouse_id' => $warehouseId,
        'currency_id' => $currencyId,
        'document_type' => '01',
        'serie' => 'F001',
        'correlativo' => '00000001',
        'full_number' => 'F001-00000001',
        'issue_date' => $now->toDateString(),
        'client_name' => 'CLIENTE FACTURA PPM',
        'status' => 'generated',
    ]);
    $item = $invoice->items()->create([
        'article_id' => $articleId,
        'item_number' => 1,
        'product_code' => $articleCode,
        'description' => 'ARTÍCULO FACTURA PPM '.$suffix,
        'unit_code' => 'NIU',
        'quantity' => 4,
        'unit_value' => 20,
        'unit_price' => 23.6,
        'lot_number' => 'LOTE-A',
        'status' => 'ACTIVE',
    ]);

    return compact(
        'companyId', 'warehouseId', 'articleId', 'stockA', 'stockB', 'pool', 'invoice', 'item'
    );
}

it('valoriza la salida de factura directa al PPM global sin duplicarla', function () {
    $fixture = electronicInvoiceValuationPoolFixture('OUT');
    $service = app(WarehouseKardexService::class);

    $service->registerExitFromElectronicInvoice($fixture['invoice']);
    $service->registerExitFromElectronicInvoice($fixture['invoice']->fresh());

    $movement = WarehouseKardexMovement::query()
        ->where('operation_type', 'electronic_invoice')
        ->firstOrFail();
    $pool = $fixture['pool']->fresh();

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(6.0)
        ->and((float) $fixture['stockB']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $pool->current_quantity)->toBe(16.0)
        ->and((float) $pool->total_cost)->toBe(400.0)
        ->and((float) $pool->average_unit_cost)->toBe(25.0)
        ->and((float) $movement->unit_cost)->toBe(25.0)
        ->and((float) $movement->total_cost_out)->toBe(100.0)
        ->and((float) $movement->total_cost_out)->not->toBe(80.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'electronic_invoice')->count())->toBe(1);
});

it('revierte la factura directa con el costo histórico de la salida sin duplicarla', function () {
    $fixture = electronicInvoiceValuationPoolFixture('REV');
    $kardexService = app(WarehouseKardexService::class);
    $poolService = app(WarehouseValuationPoolService::class);
    $kardexService->registerExitFromElectronicInvoice($fixture['invoice']);

    $poolService->addQuantityAtCost($fixture['pool']->fresh(), 10, 400);
    $fixture['stockB']->update([
        'current_quantity' => 20,
        'average_unit_cost' => 35,
        'total_cost' => 700,
    ]);
    $poolBeforeReversal = $fixture['pool']->fresh();

    $kardexService->reverseElectronicInvoiceExit($fixture['invoice']->fresh(), 'Reversa focal');
    $kardexService->reverseElectronicInvoiceExit($fixture['invoice']->fresh(), 'Segundo intento');

    $pool = $fixture['pool']->fresh();
    $reversal = WarehouseKardexMovement::query()
        ->where('operation_type', 'electronic_invoice_cancel')
        ->firstOrFail();

    expect((float) $poolBeforeReversal->current_quantity)->toBe(26.0)
        ->and((float) $poolBeforeReversal->total_cost)->toBe(800.0)
        ->and((float) $fixture['stockA']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $pool->current_quantity)->toBe(30.0)
        ->and((float) $pool->total_cost)->toBe(900.0)
        ->and((float) $pool->average_unit_cost)->toBe(30.0)
        ->and((float) $reversal->total_cost_in)->toBe(100.0)
        ->and(WarehouseKardexMovement::where('operation_type', 'electronic_invoice_cancel')->count())->toBe(1)
        ->and(WarehouseKardexMovement::where('operation_type', 'electronic_invoice')->value('status'))->toBe('reversed');
});

it('revierte stock pool y Kardex si falla la salida de factura directa', function () {
    $fixture = electronicInvoiceValuationPoolFixture('ROLL');
    $failure = (object) ['enabled' => true];
    WarehouseKardexMovement::creating(function () use ($failure) {
        if ($failure->enabled) {
            throw new \RuntimeException('Falla focal de Kardex');
        }
    });

    expect(fn () => app(WarehouseKardexService::class)
        ->registerExitFromElectronicInvoice($fixture['invoice']))
        ->toThrow(\RuntimeException::class, 'Falla focal de Kardex');
    $failure->enabled = false;

    expect((float) $fixture['stockA']->fresh()->current_quantity)->toBe(10.0)
        ->and((float) $fixture['stockA']->fresh()->total_cost)->toBe(200.0)
        ->and((float) $fixture['pool']->fresh()->current_quantity)->toBe(20.0)
        ->and((float) $fixture['pool']->fresh()->total_cost)->toBe(500.0)
        ->and(WarehouseKardexMovement::count())->toBe(0)
        ->and($fixture['invoice']->fresh()->stock_moved_at)->toBeNull();
});
