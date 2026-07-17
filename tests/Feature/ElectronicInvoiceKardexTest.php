<?php

use App\Models\ElectronicInvoice;
use App\Models\ElectronicInvoiceSetting;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Services\WarehouseKardexService;
use App\Services\ElectronicBilling\ApiPeruBillingService;
use App\Http\Controllers\Admin\ElectronicInvoiceSettingController;
use App\Http\Controllers\Admin\ElectronicInvoiceSeriesController;
use App\Models\ElectronicInvoiceSeries;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

function electronicInvoiceStockFixture(float $invoiceQuantity = 4, string $status = 'generated'): array
{
    $now = now();
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'DROPAIV TEST', 'ruc' => '20123456789', 'status' => true,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'PEN', 'description' => 'SOLES', 'symbol' => 'S/', 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20999999991', 'business_name' => 'PROVEEDOR TEST',
        'supplier_type' => 'LOCAL', 'payment_condition' => 'CONTADO', 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId([
        'code' => 'ALM-TEST', 'name' => 'ALMACÉN TEST', 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $warehouseEntryId = DB::table('warehouse_entries')->insertGetId([
        'entry_number' => 'ING-TEST-001', 'warehouse_id' => $warehouseId,
        'company_id' => $companyId, 'supplier_id' => $supplierId, 'currency_id' => $currencyId,
        'status' => 'registered', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'UND', 'description' => 'UNIDAD', 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => 'CATEGORÍA TEST', 'code' => 'CAT-TEST', 'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => 'ART-TEST', 'category_id' => $categoryId, 'unit_id' => $unitId,
        'legal_name' => 'ARTÍCULO TEST', 'billing_name' => 'ARTÍCULO TEST', 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $stock = WarehouseStock::create([
        'stock_key' => "{$warehouseId}|{$articleId}|LOTE-01|2027-12-31",
        'warehouse_id' => $warehouseId, 'article_id' => $articleId, 'unit_id' => $unitId,
        'lot_number' => 'LOTE-01', 'expiration_date' => '2027-12-31',
        'current_quantity' => 10, 'reserved_quantity' => 0, 'average_unit_cost' => 10,
        'total_cost' => 100, 'status' => 'ACTIVE',
    ]);
    $invoice = ElectronicInvoice::create([
        'warehouse_entry_id' => $warehouseEntryId, 'warehouse_id' => $warehouseId, 'currency_id' => $currencyId,
        'document_type' => '01', 'serie' => 'F001', 'correlativo' => '00000001',
        'full_number' => 'F001-00000001', 'issue_date' => now()->toDateString(),
        'client_name' => 'CLIENTE TEST', 'status' => $status,
    ]);
    $item = $invoice->items()->create([
        'article_id' => $articleId, 'item_number' => 1, 'product_code' => 'ART-TEST',
        'description' => 'ARTÍCULO TEST', 'unit_code' => 'NIU', 'quantity' => $invoiceQuantity,
        'unit_value' => 10, 'unit_price' => 11.8, 'lot_number' => 'LOTE-01',
        'expiration_date' => '2027-12-31', 'status' => 'ACTIVE',
    ]);

    return compact('invoice', 'item', 'stock');
}

test('generated electronic invoice creates one stock exit and its cancellation reverses it', function () {
    ['invoice' => $invoice, 'stock' => $stock] = electronicInvoiceStockFixture();
    $service = app(WarehouseKardexService::class);

    $service->registerExitFromElectronicInvoice($invoice);
    $service->registerExitFromElectronicInvoice($invoice->fresh());

    expect((float) $stock->fresh()->current_quantity)->toBe(6.0)
        ->and(WarehouseKardexMovement::where('movement_type', 'exit')->count())->toBe(1)
        ->and($invoice->fresh()->stock_moved_at)->not->toBeNull();

    $service->reverseElectronicInvoiceExit($invoice->fresh(), 'Factura anulada');
    $service->reverseElectronicInvoiceExit($invoice->fresh(), 'Segundo intento');

    expect((float) $stock->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::where('movement_type', 'exit_reversal')->count())->toBe(1)
        ->and(WarehouseKardexMovement::where('movement_type', 'exit')->value('status'))->toBe('reversed')
        ->and($invoice->fresh()->stock_reversed_at)->not->toBeNull();
});

test('draft electronic invoice does not move stock', function () {
    ['invoice' => $invoice, 'stock' => $stock] = electronicInvoiceStockFixture(4, 'draft');

    app(WarehouseKardexService::class)->registerExitFromElectronicInvoice($invoice);

    expect((float) $stock->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

test('electronic invoice is blocked when stock is insufficient', function () {
    ['invoice' => $invoice, 'stock' => $stock] = electronicInvoiceStockFixture(11);

    expect(fn () => app(WarehouseKardexService::class)->registerExitFromElectronicInvoice($invoice))
        ->toThrow(ValidationException::class, 'No hay stock suficiente para el artículo ARTÍCULO TEST');

    expect((float) $stock->fresh()->current_quantity)->toBe(10.0)
        ->and(WarehouseKardexMovement::count())->toBe(0);
});

test('internal invoice can exist without api configuration', function () {
    ['invoice' => $invoice] = electronicInvoiceStockFixture();
    $service = app(ApiPeruBillingService::class);

    expect($service->canSendToApi($invoice))->toBeFalse()
        ->and($service->externalStatus($invoice))->toBe('not_configured')
        ->and($service->send($invoice)['message'])->toBe('API de facturación aún no configurada.')
        ->and($service->buildPayload($invoice))->toHaveKeys(['tipoDoc', 'serie', 'correlativo', 'details']);
});

test('electronic billing credentials are encrypted and never returned by show endpoint', function () {
    $setting = ElectronicInvoiceSetting::create([
        'provider' => 'apisperu', 'environment' => 'beta', 'api_base_url' => 'https://api.invalid',
        'api_token' => 'TOKEN-SECRETO', 'user_token' => 'USUARIO-SECRETO',
        'sol_user' => 'USUARIO-SOL', 'sol_password' => 'CLAVE-SECRETA', 'is_active' => true,
    ]);

    $rawToken = DB::table('electronic_invoice_settings')->where('id', $setting->id)->value('api_token');
    $rawSolUser = DB::table('electronic_invoice_settings')->where('id', $setting->id)->value('sol_user');
    $response = app(ElectronicInvoiceSettingController::class)->show($setting)->getData(true);

    expect($rawToken)->not->toBe('TOKEN-SECRETO')
        ->and($response['data'])->not->toHaveKeys(['api_token', 'user_token', 'sol_password'])
        ->and($rawSolUser)->not->toBe('USUARIO-SOL')
        ->and($response['data'])->not->toHaveKey('sol_user')
        ->and($response['data']['has_api_token'])->toBeTrue()
        ->and($response['data']['has_sol_password'])->toBeTrue();
});

test('internal electronic billing setting needs no credentials and prevents an active duplicate', function () {
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMISOR INTERNO', 'ruc' => '20111111111', 'status' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $payload = [
        'company_id' => $companyId,
        'provider' => 'internal',
        'environment' => 'internal',
        'ruc' => '20111111111',
        'business_name' => 'EMISOR INTERNO',
        'is_active' => 1,
    ];
    $controller = app(ElectronicInvoiceSettingController::class);

    $response = $controller->store(Request::create('/', 'POST', $payload));

    expect($response->getStatusCode())->toBe(201)
        ->and(ElectronicInvoiceSetting::where('provider', 'internal')->value('api_token'))->toBeNull();

    expect(fn () => $controller->store(Request::create('/', 'POST', $payload)))
        ->toThrow(ValidationException::class, 'Ya existe una configuración activa');
});

test('internal electronic series support invoice and receipt with unique defaults and correlatives', function () {
    $companyId = DB::table('companies')->insertGetId([
        'business_name' => 'EMISOR SERIES', 'ruc' => '20222222222', 'status' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $controller = app(ElectronicInvoiceSeriesController::class);
    $payload = fn (string $type, string $serie) => [
        'company_id' => $companyId, 'document_type' => $type, 'serie' => $serie,
        'current_number' => 0, 'next_number' => 1, 'environment' => 'internal',
        'is_default' => 1, 'status' => 'ACTIVE',
    ];

    expect($controller->store(Request::create('/', 'POST', $payload('01', 'F001')))->getStatusCode())->toBe(201)
        ->and($controller->store(Request::create('/', 'POST', $payload('03', 'B001')))->getStatusCode())->toBe(201)
        ->and(ElectronicInvoiceSeries::where('serie', 'F001')->value('next_number'))->toBe(1)
        ->and(ElectronicInvoiceSeries::where('serie', 'B001')->value('is_default'))->toBeTrue();

    $controller->store(Request::create('/', 'POST', $payload('01', 'F002')));
    expect(ElectronicInvoiceSeries::where('serie', 'F001')->value('is_default'))->toBeFalse()
        ->and(ElectronicInvoiceSeries::where('serie', 'F002')->value('is_default'))->toBeTrue();

    expect(fn () => $controller->store(Request::create('/', 'POST', $payload('01', 'F001'))))
        ->toThrow(ValidationException::class, 'Ya existe una serie');
});
