<?php

use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('considera completo el seguimiento cuando existe la recepción en almacén', function () {
    $summary = SupplierPurchaseOrderTracking::logisticsSummary([
        'registered',
        'in_transit',
        'received_warehouse',
    ]);

    expect($summary['is_complete'])->toBeTrue()
        ->and($summary['current_status'])->toBe('Recibida en almacén')
        ->and($summary['missing_steps'])->toBeEmpty();
});

it('informa el estado actual y las etapas pendientes sin bloquear', function () {
    $summary = SupplierPurchaseOrderTracking::logisticsSummary([
        'registered',
        'sent_to_supplier',
        'supplier_confirmed',
        'preparing',
        'delivered_to_carrier',
        'in_transit',
    ]);

    expect($summary['is_complete'])->toBeFalse()
        ->and($summary['current_status'])->toBe('En tránsito')
        ->and($summary['missing_steps'])->toBe([
            'Llegó a destino',
            'Recibida en oficina/agencia',
            'Recibida en almacén',
        ]);
});

it('mantiene las etapas logísticas pendientes aunque el evento actual sea una observación', function () {
    $summary = SupplierPurchaseOrderTracking::logisticsSummary([
        'registered',
        'preparing',
        'observed',
    ]);

    expect($summary['current_status'])->toBe('Observada')
        ->and($summary['missing_step_codes'][0])->toBe('delivered_to_carrier')
        ->and($summary['is_complete'])->toBeFalse();
});

it('registra una sola vez la recepción automática del ingreso de almacén', function () {
    $now = now();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'Empresa de prueba',
        'ruc' => '20123456789',
        'status' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20987654321',
        'business_name' => 'Proveedor de prueba',
        'supplier_type' => 'DISTRIBUIDOR',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $orderId = DB::table('supplier_purchase_orders')->insertGetId([
        'code' => '00008-2026-TEST',
        'company_id' => $companyId,
        'supplier_id' => $supplierId,
        'currency_id' => $currencyId,
        'order_type' => 'DIRECTA',
        'status' => 'registered',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $order = SupplierPurchaseOrder::query()->findOrFail($orderId);

    $first = $order->registerWarehouseReceiptTracking('ING-00001', null);
    $second = $order->registerWarehouseReceiptTracking('ING-00002', null);

    expect($second->id)->toBe($first->id)
        ->and($order->trackings()->where('status', 'received_warehouse')->count())->toBe(1)
        ->and($first->description)->toContain('ING-00001');
});
