<?php

use App\Http\Controllers\Admin\WarehouseEntryController;
use App\Models\Company;
use App\Models\Currency;
use App\Models\DocumentIssuer;
use App\Models\PettyCashBox;
use App\Models\PettyCashExpense;
use App\Models\PettyCashExpenseExchange;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->company = Company::create([
        'business_name' => 'DROPAIV S.A.C.', 'trade_name' => 'DROPAIV',
        'ruc' => '20123456789', 'status' => true,
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN', 'description' => 'Soles', 'symbol' => 'S/', 'status' => 'ACTIVE',
    ]);
    $this->user = User::factory()->create();
    foreach ([
        'admin.petty-cash.approved-amount.update', 'admin.petty-cash.show', 'admin.petty-cash.store',
        'admin.petty-cash.expenses.store', 'admin.petty-cash.expenses.approve',
        'admin.petty-cash.receipt-exchanges.index', 'admin.petty-cash.receipt-exchanges.store',
        'admin.petty-cash.receipt-exchanges.show',
        'admin.warehouse-entries.expenses.store', 'admin.warehouse-entries.expenses.update',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $this->user->givePermissionTo(Permission::all());
    $this->actingAs($this->user)->putJson(route('admin.petty-cash.approved-amount.update'), [
        'company_id' => $this->company->id,
        'currency_id' => $this->currency->id,
        'amount' => 2000,
        'active' => true,
    ])->assertOk();
    $this->boxId = $this->postJson(route('admin.petty-cash.store'), [
        'company_id' => $this->company->id,
        'currency_id' => $this->currency->id,
        'start_date' => '2026-07-01',
        'responsible_name' => 'RESPONSABLE',
        'responsible_dni' => '12345678',
        'supervisor_name' => 'SUPERVISOR',
        'supervisor_dni' => '87654321',
    ])->assertCreated()->json('data.id');
});

function createReceiptExpense(float $amount, string $correlative): PettyCashExpense
{
    test()->postJson(route('admin.petty-cash.expenses.store', test()->boxId), [
        'expense_date' => '2026-07-02',
        'document_type' => 'RECIBO',
        'document_series' => 'R',
        'document_correlative' => $correlative,
        'supplier_name' => 'PROVEEDOR '.$correlative,
        'concept' => 'SERVICIO '.$correlative,
        'amount' => $amount,
    ])->assertCreated();

    return PettyCashExpense::latest('id')->firstOrFail();
}

it('dispone de todas las columnas seguras para integrar caja chica con almacén', function () {
    foreach ([
        'source_type',
        'petty_cash_expense_id',
        'petty_cash_replenishment_id',
        'document_classification',
        'official_document_type',
        'internal_document_type',
        'exchanged_document_id',
        'exchanged_at',
        'payment_proof_path',
        'official_document_path',
    ] as $column) {
        expect(Schema::hasColumn('warehouse_entry_expenses', $column))->toBeTrue();
    }
});

it('canjea conjuntamente recibos de diferentes proveedores y conserva su trazabilidad', function () {
    Storage::fake('public');
    $first = createReceiptExpense(60, '000261');
    $second = createReceiptExpense(70, '000262');
    expect($first->exchange_status)->toBe(PettyCashExpense::EXCHANGE_PENDING)
        ->and($second->exchange_status)->toBe(PettyCashExpense::EXCHANGE_PENDING);

    $this->postJson(route('admin.petty-cash.expenses.approve', $first))->assertOk();
    $this->postJson(route('admin.petty-cash.expenses.approve', $second))->assertOk();
    $before = PettyCashBox::findOrFail($this->boxId)->only([
        'opening_amount', 'total_expenses', 'cash_balance', 'reimbursement_amount',
    ]);

    $this->post(route('admin.petty-cash.receipt-exchanges.store', $this->boxId), [
        'exchange_date' => '2026-07-30',
        'document_type' => 'FACTURA',
        'document_series' => 'F001',
        'document_correlative' => '000123',
        'issuer_ruc' => '20601234567',
        'issuer_business_name' => 'TRANSPORTES XYZ S.A.C.',
        'expense_ids' => [$first->id, $second->id],
        'documents' => [UploadedFile::fake()->create('factura.pdf', 120, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('data.total_amount', '130.00');

    $after = PettyCashBox::findOrFail($this->boxId)->only([
        'opening_amount', 'total_expenses', 'cash_balance', 'reimbursement_amount',
    ]);
    expect($after)->toBe($before)
        ->and($first->fresh()->exchange_status)->toBe(PettyCashExpense::EXCHANGE_COMPLETED)
        ->and($second->fresh()->exchange_status)->toBe(PettyCashExpense::EXCHANGE_COMPLETED)
        ->and($first->fresh()->exchange_id)->toBe($second->fresh()->exchange_id)
        ->and(PettyCashExpenseExchange::firstOrFail()->items)->toHaveCount(2)
        ->and(PettyCashExpenseExchange::firstOrFail()->documents)->toHaveCount(1);

    expect(DocumentIssuer::where('ruc', '20601234567')->value('source'))->toBe('manual')
        ->and(PettyCashExpenseExchange::firstOrFail()->issuer_business_name)->toBe('TRANSPORTES XYZ S.A.C.');

    $this->getJson(route('admin.petty-cash.receipt-exchanges.index', $this->boxId))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->getJson(route('admin.petty-cash.expenses.detail', $first))
        ->assertOk()
        ->assertJsonPath('data.exchange_status', PettyCashExpense::EXCHANGE_COMPLETED)
        ->assertJsonPath('data.exchange.document_type', 'FACTURA')
        ->assertJsonPath('data.exchange.document_full_number', 'F001-000123')
        ->assertJsonPath('data.exchange.creator.id', $this->user->id)
        ->assertJsonCount(2, 'data.exchange.items');

    $exchange = PettyCashExpenseExchange::firstOrFail();
    $this->getJson(route('admin.petty-cash.receipt-exchanges.show', $exchange))
        ->assertOk()
        ->assertJsonPath('data.total_amount', '130.00')
        ->assertJsonPath('data.issuer_ruc', '20601234567')
        ->assertJsonPath('data.issuer_business_name', 'TRANSPORTES XYZ S.A.C.')
        ->assertJsonPath('data.items.0.expense.supplier_name', 'PROVEEDOR 000261')
        ->assertJsonPath('data.items.1.expense.supplier_name', 'PROVEEDOR 000262')
        ->assertJsonPath('data.items.0.concept', 'SERVICIO 000261')
        ->assertJsonPath('data.items.1.concept', 'SERVICIO 000262');
});

it('vincula una sola vez el recibo a almacén y sincroniza el comprobante oficial sin duplicar el costo', function () {
    Storage::fake('public');
    $receipt = createReceiptExpense(85.50, '000280');
    $this->postJson(route('admin.petty-cash.expenses.approve', $receipt))->assertOk();

    $this->getJson(route('admin.warehouse-entries.petty-cash-expenses.available', [
        'company_id' => $this->company->id,
        'currency_id' => $this->currency->id,
    ]))->assertOk()->assertJsonPath('data.0.id', $receipt->id);

    $supplier = Supplier::create([
        'ruc' => '20601234001',
        'business_name' => 'PROVEEDOR DE ALMACÉN S.A.C.',
        'short_name' => 'PROVEEDOR ALMACÉN',
        'supplier_type' => 'BIENES',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-TEST-0001',
        'company_id' => $this->company->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $this->currency->id,
        'subtotal' => 0,
        'igv' => 0,
        'grand_total' => 0,
        'status' => 'registered',
    ]);
    $syncExpenses = new ReflectionMethod(WarehouseEntryController::class, 'syncEntryExpenses');
    $syncExpenses->invoke(app(WarehouseEntryController::class), $entry, [[
        'source_type' => WarehouseEntryExpense::SOURCE_MANUAL,
        'expense_type' => 'other',
        'expense_category' => 'other_expense',
        'cost_origin' => 'third_party',
        'provider_name' => 'RESPONSABLE MANUAL',
        'document_type' => 'SIN_COMPROBANTE',
        'document_date' => '2026-07-02',
        'amount' => 15,
        'affects_igv' => false,
        'affects_inventory_cost' => false,
        'description' => 'COSTO MANUAL DE PRUEBA',
    ], [
        'source_type' => WarehouseEntryExpense::SOURCE_PETTY_CASH,
        'petty_cash_expense_id' => $receipt->id,
        'expense_type' => 'pickup_transfer',
        'expense_category' => 'freight_transport',
        'cost_origin' => 'third_party',
        'document_type' => 'RECIBO_INTERNO',
        'document_date' => '2026-07-02',
        'amount' => 85.50,
        'affects_igv' => false,
        'affects_inventory_cost' => false,
        'description' => 'SERVICIO DE TRASLADO',
    ]], [], []);

    $manualCost = WarehouseEntryExpense::where('warehouse_entry_id', $entry->id)
        ->where('source_type', WarehouseEntryExpense::SOURCE_MANUAL)->firstOrFail();
    $linkedCost = WarehouseEntryExpense::where('petty_cash_expense_id', $receipt->id)->firstOrFail();
    expect($manualCost->document_classification)->toBe('non_official')
        ->and($manualCost->internal_document_type)->toBe('sin_comprobante');
    expect($linkedCost->document_classification)->toBe('non_official')
        ->and($linkedCost->internal_document_type)->toBe('recibo_interno')
        ->and($linkedCost->official_document_type)->toBeNull();

    $this->getJson(route('admin.warehouse-entries.petty-cash-expenses.available', [
        'company_id' => $this->company->id,
        'currency_id' => $this->currency->id,
    ]))->assertOk()->assertJsonCount(0, 'data');

    $linkedCost->update(['status' => 'INACTIVE']);
    $this->getJson(route('admin.warehouse-entries.petty-cash-expenses.available', [
        'company_id' => $this->company->id,
        'currency_id' => $this->currency->id,
    ]))->assertOk()->assertJsonCount(0, 'data');
    $linkedCost->update(['status' => 'ACTIVE']);

    $this->post(route('admin.petty-cash.receipt-exchanges.store', $this->boxId), [
        'exchange_date' => '2026-07-30',
        'document_type' => 'FACTURA',
        'document_series' => 'F002',
        'document_correlative' => '000280',
        'issuer_ruc' => '20601234001',
        'issuer_business_name' => 'PROVEEDOR DE ALMACÉN S.A.C.',
        'expense_ids' => [$receipt->id],
        'documents' => [UploadedFile::fake()->create('factura-canje.pdf', 80, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertCreated();

    $linkedCost->refresh();
    expect($linkedCost->id)->not->toBeNull()
        ->and($linkedCost->document_type)->toBe('FACTURA')
        ->and($linkedCost->document_classification)->toBe('official')
        ->and($linkedCost->official_document_type)->toBe('factura')
        ->and($linkedCost->internal_document_type)->toBeNull()
        ->and($linkedCost->exchanged_document_id)->not->toBeNull()
        ->and($linkedCost->exchanged_at)->not->toBeNull()
        ->and($linkedCost->official_document_path)->toContain('petty-cash/receipt-exchanges/')
        ->and((float) $linkedCost->amount)->toBe(85.5)
        ->and((float) $linkedCost->total_amount)->toBe(85.5)
        ->and(WarehouseEntryExpense::isOfficialDocument($linkedCost->document_type))->toBeTrue()
        ->and(WarehouseEntryExpense::where('petty_cash_expense_id', $receipt->id)->count())->toBe(1)
        ->and($linkedCost->documents()->where('source_context', 'petty_cash_exchange')->count())->toBe(1);

    $this->getJson(route('admin.petty-cash.show', $this->boxId))
        ->assertOk()
        ->assertJsonPath('data.expenses.0.warehouse_entry_expense.warehouse_entry.entry_number', 'ING-TEST-0001');
});

it('jala un costo no oficial de almacén a caja chica y lo reclasifica al canjearlo', function () {
    $supplier = Supplier::create([
        'ruc' => '20601234002',
        'business_name' => 'TRANSPORTES DE ORIGEN S.A.C.',
        'short_name' => 'TRANSPORTES ORIGEN',
        'supplier_type' => 'SERVICIOS',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
    ]);
    $entry = WarehouseEntry::create([
        'entry_number' => 'ING-TEST-0002',
        'company_id' => $this->company->id,
        'supplier_id' => $supplier->id,
        'currency_id' => $this->currency->id,
        'subtotal' => 0,
        'igv' => 0,
        'grand_total' => 0,
        'status' => 'registered',
    ]);
    $warehouseCost = WarehouseEntryExpense::create([
        'warehouse_entry_id' => $entry->id,
        'source_type' => WarehouseEntryExpense::SOURCE_MANUAL,
        'expense_category' => 'freight_transport',
        'cost_origin' => 'third_party',
        'expense_type' => 'pickup_transfer',
        'provider_id' => $supplier->id,
        'provider_ruc' => $supplier->ruc,
        'provider_name' => $supplier->business_name,
        'document_type' => 'RECIBO_INTERNO',
        ...WarehouseEntryExpense::documentMetadata('RECIBO_INTERNO'),
        'document_series' => 'G',
        'document_number' => '123',
        'document_date' => '2026-07-03',
        'currency_id' => $this->currency->id,
        'amount' => 45,
        'affects_igv' => false,
        'igv_rate' => 0,
        'taxable_amount' => 45,
        'igv_amount' => 0,
        'total_amount' => 45,
        'affects_inventory_cost' => false,
        'description' => 'RECOJO DE MERCADERÍA',
        'status' => 'ACTIVE',
    ]);

    $this->getJson(route('admin.petty-cash.warehouse-expenses.available', $this->boxId))
        ->assertOk()
        ->assertJsonPath('data.0.id', $warehouseCost->id)
        ->assertJsonPath('data.0.warehouse_entry.entry_number', 'ING-TEST-0002')
        ->assertJsonPath('data.0.document_label', 'Recibo interno');

    $response = $this->postJson(route('admin.petty-cash.warehouse-expenses.pull', $this->boxId), [
        'warehouse_entry_expense_ids' => [$warehouseCost->id],
    ])->assertCreated()->assertJsonPath('created_count', 1);

    $pettyExpense = PettyCashExpense::findOrFail($response->json('expense_ids.0'));
    $warehouseCost->refresh();
    expect($pettyExpense->document_type)->toBe('RECIBO')
        ->and($pettyExpense->approval_status)->toBe(PettyCashExpense::APPROVAL_PENDING)
        ->and($pettyExpense->exchange_status)->toBe(PettyCashExpense::EXCHANGE_PENDING)
        ->and($pettyExpense->observation)->toContain('INGRESO DE ALMACÉN ING-TEST-0002')
        ->and((float) $pettyExpense->amount)->toBe(45.0)
        ->and($warehouseCost->source_type)->toBe(WarehouseEntryExpense::SOURCE_PETTY_CASH)
        ->and($warehouseCost->petty_cash_expense_id)->toBe($pettyExpense->id)
        ->and($warehouseCost->document_classification)->toBe('non_official')
        ->and(WarehouseEntryExpense::whereKey($warehouseCost->id)->exists())->toBeTrue();

    $this->getJson(route('admin.petty-cash.warehouse-expenses.available', $this->boxId))
        ->assertOk()->assertJsonCount(0, 'data');
    $this->postJson(route('admin.petty-cash.warehouse-expenses.pull', $this->boxId), [
        'warehouse_entry_expense_ids' => [$warehouseCost->id],
    ])->assertUnprocessable()
        ->assertJsonPath('message', 'Este costo ya fue registrado en Caja Chica o no está disponible.');

    $this->postJson(route('admin.petty-cash.expenses.approve', $pettyExpense))->assertOk();
    $this->postJson(route('admin.petty-cash.receipt-exchanges.store', $this->boxId), [
        'exchange_date' => '2026-07-30',
        'document_type' => 'FACTURA',
        'document_series' => 'F003',
        'document_correlative' => '000045',
        'issuer_ruc' => $supplier->ruc,
        'issuer_business_name' => $supplier->business_name,
        'expense_ids' => [$pettyExpense->id],
    ])->assertCreated();

    $warehouseCost->refresh();
    expect($warehouseCost->document_type)->toBe('FACTURA')
        ->and($warehouseCost->document_classification)->toBe('official')
        ->and($warehouseCost->official_document_type)->toBe('factura')
        ->and($warehouseCost->internal_document_type)->toBeNull()
        ->and((float) $warehouseCost->amount)->toBe(45.0)
        ->and(WarehouseEntryExpense::where('petty_cash_expense_id', $pettyExpense->id)->count())->toBe(1);
});

it('no lista ni permite canjear recibos pendientes de aprobación', function () {
    $receipt = createReceiptExpense(60, '000300');

    $this->getJson(route('admin.petty-cash.receipt-exchanges.index', $this->boxId))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->postJson(route('admin.petty-cash.receipt-exchanges.store', $this->boxId), [
        'exchange_date' => '2026-07-30',
        'document_type' => 'BOLETA',
        'document_series' => 'B001',
        'document_correlative' => '000010',
        'issuer_ruc' => '20607654321',
        'issuer_business_name' => 'EMISOR DE PRUEBA S.A.C.',
        'expense_ids' => [$receipt->id],
    ])->assertStatus(422)
        ->assertJsonPath('message', 'No se puede canjear este recibo porque todavía no está aprobado.');

    expect(PettyCashExpenseExchange::count())->toBe(0);
});

it('consulta primero el historial local de emisores', function () {
    $issuer = DocumentIssuer::create([
        'ruc' => '20601112223',
        'business_name' => 'EMISOR HISTÓRICO S.A.C.',
        'source' => 'api',
    ]);

    $this->getJson(route('admin.petty-cash.document-issuer.search', ['ruc' => $issuer->ruc]))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('source', 'cache')
        ->assertJsonPath('data.id', $issuer->id)
        ->assertJsonPath('data.business_name', 'EMISOR HISTÓRICO S.A.C.');
});

it('consulta la API y guarda un emisor que no existe en el historial', function () {
    config([
        'services.apisperu.base_url' => 'https://consulta.test',
        'services.apisperu.token' => 'token-prueba',
    ]);
    Http::fake([
        'https://consulta.test/ruc/20609784050*' => Http::response([
            'ruc' => '20609784050',
            'razonSocial' => 'TRANSPORTES API S.A.C.',
            'nombreComercial' => 'TRANSPORTES API',
            'direccion' => 'LIMA',
            'estado' => 'ACTIVO',
            'condicion' => 'HABIDO',
        ]),
    ]);

    $this->getJson(route('admin.petty-cash.document-issuer.search', ['ruc' => '20609784050']))
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('source', 'api')
        ->assertJsonPath('data.business_name', 'TRANSPORTES API S.A.C.')
        ->assertJsonPath('data.status', 'ACTIVO')
        ->assertJsonPath('data.condition', 'HABIDO');

    $issuer = DocumentIssuer::where('ruc', '20609784050')->firstOrFail();
    expect($issuer->source)->toBe('api')
        ->and($issuer->business_name)->toBe('TRANSPORTES API S.A.C.')
        ->and($issuer->last_lookup_at)->not->toBeNull();
    Http::assertSentCount(1);
});
