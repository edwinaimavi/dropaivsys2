<?php

use App\Models\Company;
use App\Models\CompanyWarehouse;
use App\Models\CustomerPurchaseOrder;
use App\Models\CustomerReturn;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDistribution;
use App\Models\WarehouseEntryItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Services\CustomerReturnService;
use App\Services\ElectronicInvoiceFormDataService;
use App\Services\WarehouseCompanyOwnershipAuditService;
use App\Services\WarehouseDispatchService;
use App\Services\WarehouseKardexService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function companyOwnershipBase(string $suffix = 'BASE'): array
{
    $now = now();
    $user = User::factory()->create();
    Auth::login($user);
    $companyA = Company::create([
        'business_name' => "EMPRESA A {$suffix}",
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
    $companyB = Company::create([
        'business_name' => "EMPRESA B {$suffix}",
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
    $user->companies()->attach([$companyA->id, $companyB->id]);
    $warehouse = Warehouse::create([
        'code' => "ALM-{$suffix}",
        'name' => "ALMACÉN {$suffix}",
        'status' => 'ACTIVE',
    ]);
    CompanyWarehouse::create(['company_id' => $companyA->id, 'warehouse_id' => $warehouse->id]);
    CompanyWarehouse::create(['company_id' => $companyB->id, 'warehouse_id' => $warehouse->id]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'P'.substr($suffix, 0, 2), 'description' => "SOLES {$suffix}", 'symbol' => 'S/',
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'U'.substr($suffix, 0, 5), 'description' => "UNIDAD {$suffix}",
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $categoryId = DB::table('categories')->insertGetId([
        'description' => "CATEGORÍA {$suffix}", 'code' => 'C'.substr($suffix, 0, 8),
        'type' => 'PRODUCTO COMERCIAL', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);
    $articleId = DB::table('articles')->insertGetId([
        'code' => 'A'.substr($suffix, 0, 10), 'category_id' => $categoryId, 'unit_id' => $unitId,
        'legal_name' => "ARTÍCULO {$suffix}", 'billing_name' => "ARTÍCULO {$suffix}",
        'item_kind' => 'product', 'is_inventory_item' => true,
        ...testSunatInventoryArticleFields('A'.substr($suffix, 0, 10)),
        'has_batch' => false, 'has_expiration' => false, 'status' => 'ACTIVE',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $supplierId = DB::table('suppliers')->insertGetId([
        'ruc' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'business_name' => "PROVEEDOR {$suffix}", 'supplier_type' => 'LOCAL',
        'payment_condition' => 'CONTADO', 'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
    ]);

    return compact('user', 'companyA', 'companyB', 'warehouse', 'currencyId', 'unitId', 'articleId', 'supplierId');
}

function companyOwnershipEntry(array $fixture, Company $company, string $suffix, float $quantity = 5): array
{
    $entry = WarehouseEntry::create([
        'entry_number' => "ING-OWN-{$suffix}", 'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $fixture['warehouse']->id, 'company_id' => $company->id,
        'supplier_id' => $fixture['supplierId'], 'currency_id' => $fixture['currencyId'],
        'document_type' => 'FACTURA', 'status' => 'registered',
    ]);
    $item = WarehouseEntryItem::create([
        'warehouse_entry_id' => $entry->id, 'article_id' => $fixture['articleId'],
        'billing_name_snapshot' => "ARTÍCULO {$suffix}", 'unit_id' => $fixture['unitId'],
        'quantity' => $quantity, 'unit_price' => 10, 'line_total' => $quantity * 10, 'status' => 'active',
    ]);
    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($entry);
    $stock = WarehouseStock::query()->where('company_id', $company->id)->where('article_id', $fixture['articleId'])->sole();

    return compact('entry', 'item', 'stock');
}

function companyOwnershipOrder(array $fixture, Company $company, WarehouseEntry $entry, WarehouseEntryItem $entryItem, float $quantity = 5): CustomerPurchaseOrder
{
    $now = now();
    $customerId = DB::table('customers')->insertGetId([
        'person_type' => 'juridica', 'business_name' => 'CLIENTE OWN', 'document_type' => 'RUC',
        'document_number' => '20'.str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT),
        'status' => true, 'created_by' => $fixture['user']->id, 'created_at' => $now, 'updated_at' => $now,
    ]);
    $orderId = DB::table('customer_purchase_orders')->insertGetId([
        'code' => 'OC-OWN-'.Str::upper(Str::random(5)), 'company_id' => $company->id,
        'customer_id' => $customerId, 'order_type' => 'articles', 'currency_id' => $fixture['currencyId'],
        'status' => CustomerPurchaseOrder::STATUS_ENTERED, 'created_by' => $fixture['user']->id,
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $orderItemId = DB::table('customer_purchase_order_items')->insertGetId([
        'customer_purchase_order_id' => $orderId, 'article_id' => $fixture['articleId'],
        'billing_name_snapshot' => 'ARTÍCULO OWN', 'unit_id' => $fixture['unitId'],
        'quantity' => $quantity, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('warehouse_entry_item_allocations')->insert([
        'warehouse_entry_id' => $entry->id, 'warehouse_entry_item_id' => $entryItem->id,
        'customer_purchase_order_id' => $orderId, 'customer_purchase_order_item_id' => $orderItemId,
        'article_id' => $fixture['articleId'], 'quantity_allocated' => $quantity,
        'unit_cost' => 10, 'total_cost' => $quantity * 10, 'allocation_type' => 'customer_order',
        'status' => 'active', 'created_by' => $fixture['user']->id, 'created_at' => $now, 'updated_at' => $now,
    ]);

    return CustomerPurchaseOrder::findOrFail($orderId);
}

it('permite el mismo almacén para empresas diferentes y conserva códigos SUNAT como texto nullable', function () {
    $fixture = companyOwnershipBase('REL');
    $first = CompanyWarehouse::where('company_id', $fixture['companyA']->id)->sole();
    $second = CompanyWarehouse::where('company_id', $fixture['companyB']->id)->sole();
    $first->update(['sunat_establishment_code' => '0012']);

    expect($first->fresh()->sunat_establishment_code)->toBe('0012')
        ->and($second->sunat_establishment_code)->toBeNull()
        ->and($first->warehouse_id)->toBe($second->warehouse_id)
        ->and(fn () => CompanyWarehouse::create([
            'company_id' => $fixture['companyA']->id,
            'warehouse_id' => $fixture['warehouse']->id,
        ]))->toThrow(QueryException::class);
});

it('separa stocks y movimientos por empresa para el mismo artículo almacén lote y vencimiento', function () {
    $fixture = companyOwnershipBase('SEP');
    $entryA = companyOwnershipEntry($fixture, $fixture['companyA'], 'A');
    $entryB = companyOwnershipEntry($fixture, $fixture['companyB'], 'B');

    expect(WarehouseStock::count())->toBe(2)
        ->and($entryA['stock']->company_id)->toBe($fixture['companyA']->id)
        ->and($entryB['stock']->company_id)->toBe($fixture['companyB']->id)
        ->and($entryA['stock']->stock_key)->not->toBe($entryB['stock']->stock_key)
        ->and((float) $entryA['stock']->current_quantity)->toBe(5.0)
        ->and((float) $entryB['stock']->current_quantity)->toBe(5.0)
        ->and(WarehouseKardexMovement::where('source_id', $entryA['entry']->id)->sole()->company_id)->toBe($fixture['companyA']->id)
        ->and(WarehouseKardexMovement::where('source_id', $entryB['entry']->id)->sole()->company_id)->toBe($fixture['companyB']->id);
});

it('impide que un despacho consuma stock de otra empresa y conserva empresa en salida y reversa', function () {
    $fixture = companyOwnershipBase('DSP');
    $entryA = companyOwnershipEntry($fixture, $fixture['companyA'], 'A');
    $entryB = companyOwnershipEntry($fixture, $fixture['companyB'], 'B');
    $order = companyOwnershipOrder($fixture, $fixture['companyA'], $entryA['entry'], $entryA['item']);
    $payload = [
        'warehouse_id' => $fixture['warehouse']->id, 'dispatch_date' => now(),
        'responsible_user_id' => $fixture['user']->id, 'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'customer_purchase_order_item_id' => $order->items()->value('id'),
            'warehouse_stock_id' => $entryB['stock']->id, 'quantity' => 1,
        ]],
    ];
    expect(fn () => app(WarehouseDispatchService::class)->createDraft($order, $payload))
        ->toThrow(ValidationException::class);

    $payload['idempotency_key'] = (string) Str::uuid();
    $payload['items'][0]['warehouse_stock_id'] = $entryA['stock']->id;
    $dispatch = app(WarehouseDispatchService::class)->confirm(
        app(WarehouseDispatchService::class)->createDraft($order, $payload)
    );
    $movement = $dispatch->items()->firstOrFail()->kardexMovement;
    $reversed = app(WarehouseDispatchService::class)->reverse($dispatch, 'Reversa de prueba empresarial');
    $reversal = WarehouseKardexMovement::where('operation_type', 'customer_order_dispatch_cancel')->sole();

    expect($movement->company_id)->toBe($fixture['companyA']->id)
        ->and($reversal->company_id)->toBe($fixture['companyA']->id)
        ->and($reversed->company_id)->toBe($fixture['companyA']->id)
        ->and((float) $entryB['stock']->fresh()->current_quantity)->toBe(5.0);
});

it('conserva la empresa de la SAL en devolución y reversa', function () {
    $fixture = companyOwnershipBase('RET');
    $entry = companyOwnershipEntry($fixture, $fixture['companyA'], 'A');
    $order = companyOwnershipOrder($fixture, $fixture['companyA'], $entry['entry'], $entry['item']);
    $dispatch = app(WarehouseDispatchService::class)->createDraft($order, [
        'warehouse_id' => $fixture['warehouse']->id, 'dispatch_date' => now(),
        'responsible_user_id' => $fixture['user']->id, 'idempotency_key' => (string) Str::uuid(),
        'items' => [['customer_purchase_order_item_id' => $order->items()->value('id'),
            'warehouse_stock_id' => $entry['stock']->id, 'quantity' => 2]],
    ]);
    $dispatch = app(WarehouseDispatchService::class)->confirm($dispatch);
    $return = app(CustomerReturnService::class)->createDraft($dispatch, [
        'return_date' => now(), 'reason' => 'customer_rejection',
        'items' => [['warehouse_dispatch_item_id' => $dispatch->items()->value('id'), 'quantity' => 1]],
    ]);
    $return = app(CustomerReturnService::class)->confirm($return);
    $entryMovement = $return->items()->firstOrFail()->kardexMovement;
    $return = app(CustomerReturnService::class)->reverse($return, 'Reversa de devolución empresarial');
    $exitMovement = $return->items()->firstOrFail()->reversalKardexMovement;

    expect($return->company_id)->toBe($dispatch->company_id)
        ->and($entryMovement->company_id)->toBe($dispatch->company_id)
        ->and($exitMovement->company_id)->toBe($dispatch->company_id)
        ->and($entry['stock']->fresh()->company_id)->toBe($dispatch->company_id);
});

it('hereda empresa en costos vinculados y exige empresa explícita en ajustes', function () {
    $fixture = companyOwnershipBase('CST');
    $context = companyOwnershipEntry($fixture, $fixture['companyA'], 'A');
    $expense = WarehouseEntryExpense::create([
        'warehouse_entry_id' => $context['entry']->id, 'source_type' => WarehouseEntryExpense::SOURCE_MANUAL,
        'expense_category' => 'freight_transport', 'cost_origin' => 'third_party', 'expense_type' => 'agency_freight',
        'provider_name' => 'TRANSPORTE', 'document_type' => 'SIN_COMPROBANTE', 'currency_id' => $fixture['currencyId'],
        'amount' => 5, 'affects_igv' => false, 'taxable_amount' => 5, 'igv_amount' => 0, 'total_amount' => 5,
        'affects_inventory_cost' => true, 'distribution_method' => 'amount', 'description' => 'FLETE',
        'status' => 'ACTIVE', 'approval_status' => WarehouseEntryExpense::APPROVAL_APPROVED,
    ]);
    WarehouseEntryExpenseDistribution::create([
        'warehouse_entry_expense_id' => $expense->id,
        'warehouse_entry_item_id' => $context['item']->id,
        'distributed_amount' => 5,
    ]);
    app(WarehouseKardexService::class)->syncLinkedCosts($context['entry']->fresh());
    $linked = WarehouseKardexMovement::where('operation_type', 'warehouse_entry_linked_cost')->sole();
    $expense->update(['approval_status' => WarehouseEntryExpense::APPROVAL_PENDING]);
    app(WarehouseKardexService::class)->syncLinkedCosts($context['entry']->fresh());
    $linkedReversal = WarehouseKardexMovement::where('operation_type', 'warehouse_entry_linked_cost_cancel')->sole();
    $unauthorizedCompany = Company::create([
        'business_name' => 'EMPRESA SIN AUTORIZACIÓN CST', 'ruc' => '20900000002', 'status' => true,
    ]);
    CompanyWarehouse::create([
        'company_id' => $unauthorizedCompany->id,
        'warehouse_id' => $fixture['warehouse']->id,
    ]);

    expect($linked->company_id)->toBe($fixture['companyA']->id)
        ->and($linkedReversal->company_id)->toBe($fixture['companyA']->id)
        ->and(fn () => app(WarehouseKardexService::class)->registerAdjustment(
            $context['stock'], $unauthorizedCompany->id, 'adjustment_in', 1, 10, 'Ajuste no autorizado'
        ))->toThrow(ValidationException::class, 'No está autorizado')
        ->and(fn () => app(WarehouseKardexService::class)->registerAdjustment(
            $context['stock'], $fixture['companyB']->id, 'adjustment_in', 1, 10, 'Ajuste inválido'
        ))->toThrow(ValidationException::class);

    $adjustment = app(WarehouseKardexService::class)->registerAdjustment(
        $context['stock'], $fixture['companyA']->id, 'adjustment_in', 1, 10, 'Ajuste válido'
    );
    expect($adjustment->company_id)->toBe($fixture['companyA']->id);
});

it('no reutiliza un stock legacy sin empresa para una entrada nueva', function () {
    $fixture = companyOwnershipBase('LEG');
    WarehouseStock::create([
        'stock_key' => $fixture['warehouse']->id.'|'.$fixture['articleId'].'|SIN_LOTE|SIN_FECHA',
        'warehouse_id' => $fixture['warehouse']->id, 'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'], 'current_quantity' => 7, 'average_unit_cost' => 4,
        'total_cost' => 28, 'status' => 'ACTIVE',
    ]);
    $new = companyOwnershipEntry($fixture, $fixture['companyA'], 'NEW', 2);

    expect(WarehouseStock::count())->toBe(2)
        ->and(WarehouseStock::whereNull('company_id')->sole()->current_quantity)->toBe('7.0000')
        ->and((float) $new['stock']->current_quantity)->toBe(2.0);
});

it('audita en dry-run y aplica solo empresas inequívocas sin cambiar cantidades costos ni movimientos', function () {
    $fixture = companyOwnershipBase('AUD');
    $resolved = companyOwnershipEntry($fixture, $fixture['companyA'], 'SRC');
    $resolved['stock']->update(['company_id' => null]);
    $safeMovement = WarehouseKardexMovement::where('source_id', $resolved['entry']->id)->sole();
    $safeMovement->update(['company_id' => null]);

    $conflictStock = WarehouseStock::create([
        'stock_key' => 'LEGACY-CONFLICT', 'warehouse_id' => $fixture['warehouse']->id,
        'article_id' => $fixture['articleId'], 'unit_id' => $fixture['unitId'],
        'current_quantity' => 3, 'average_unit_cost' => 7, 'total_cost' => 21, 'status' => 'ACTIVE',
    ]);
    $sourceIds = [];
    foreach ([$fixture['companyA'], $fixture['companyB']] as $index => $company) {
        $source = WarehouseEntry::create([
            'entry_number' => 'ING-AUD-'.$index, 'entry_mode' => 'supplier_invoice',
            'warehouse_id' => $fixture['warehouse']->id, 'company_id' => $company->id,
            'supplier_id' => $fixture['supplierId'], 'currency_id' => $fixture['currencyId'], 'status' => 'registered',
        ]);
        $sourceIds[] = $source->id;
        WarehouseKardexMovement::create([
            'movement_number' => 'KDX-AUD-'.$index, 'warehouse_stock_id' => $conflictStock->id,
            'warehouse_id' => $fixture['warehouse']->id, 'article_id' => $fixture['articleId'],
            'unit_id' => $fixture['unitId'], 'movement_date' => now(), 'movement_type' => 'entry',
            'operation_type' => 'warehouse_entry', 'source_type' => WarehouseEntry::class, 'source_id' => $source->id,
            'quantity_in' => 1, 'balance_quantity' => $index + 1, 'unit_cost' => 7,
            'total_cost_in' => 7, 'average_unit_cost' => 7, 'balance_total_cost' => 7 * ($index + 1),
            'status' => 'registered',
        ]);
    }
    $orphan = WarehouseStock::create([
        'stock_key' => 'LEGACY-ORPHAN', 'warehouse_id' => $fixture['warehouse']->id,
        'article_id' => $fixture['articleId'], 'unit_id' => $fixture['unitId'],
        'current_quantity' => 9, 'average_unit_cost' => 2, 'total_cost' => 18, 'status' => 'ACTIVE',
    ]);
    $ambiguousMovement = WarehouseKardexMovement::create([
        'movement_number' => 'KDX-AUD-AMB', 'warehouse_stock_id' => $orphan->id,
        'warehouse_id' => $fixture['warehouse']->id, 'article_id' => $fixture['articleId'],
        'unit_id' => $fixture['unitId'], 'movement_date' => now(), 'movement_type' => 'entry',
        'operation_type' => 'warehouse_entry', 'source_type' => WarehouseEntry::class, 'source_id' => $sourceIds[0],
        'source_item_type' => WarehouseEntry::class, 'source_item_id' => $sourceIds[1],
        'quantity_in' => 1, 'balance_quantity' => 1, 'unit_cost' => 2,
        'total_cost_in' => 2, 'average_unit_cost' => 2, 'balance_total_cost' => 2,
        'status' => 'registered',
    ]);
    CompanyWarehouse::query()
        ->where('company_id', $fixture['companyB']->id)
        ->where('warehouse_id', $fixture['warehouse']->id)
        ->delete();
    $before = WarehouseStock::withTrashed()->get()->mapWithKeys(fn ($stock) => [$stock->id => [
        'quantity' => $stock->current_quantity, 'reserved' => $stock->reserved_quantity,
        'average' => $stock->average_unit_cost, 'total' => $stock->total_cost,
    ]])->all();
    $movementCount = WarehouseKardexMovement::count();
    $externalCounts = [DB::table('electronic_invoices')->count(), DB::table('invoice_collections')->count(), DB::table('bank_movements')->count()];

    $service = app(WarehouseCompanyOwnershipAuditService::class);
    $dryRun = $service->analyze();
    expect($safeMovement->fresh()->company_id)->toBeNull()
        ->and($dryRun['movements']['analyzed'])->toBe(4)
        ->and($dryRun['movements']['resolvable'])->toBe(3)
        ->and($dryRun['movements']['ambiguous'])->toBe(1)
        ->and($dryRun['stocks']['analyzed'])->toBe(3)
        ->and($dryRun['stocks']['resolvable'])->toBe(1)
        ->and($dryRun['stocks']['conflicts'])->toBe(1)
        ->and($dryRun['stocks']['without_source'])->toBe(1)
        ->and($dryRun['company_warehouses']['creatable'])->toBe(1);

    $service->apply();
    $after = WarehouseStock::withTrashed()->get()->mapWithKeys(fn ($stock) => [$stock->id => [
        'quantity' => $stock->current_quantity, 'reserved' => $stock->reserved_quantity,
        'average' => $stock->average_unit_cost, 'total' => $stock->total_cost,
    ]])->all();
    expect($safeMovement->fresh()->company_id)->toBe($fixture['companyA']->id)
        ->and($resolved['stock']->fresh()->company_id)->toBe($fixture['companyA']->id)
        ->and($conflictStock->fresh()->company_id)->toBeNull()
        ->and($orphan->fresh()->company_id)->toBeNull()
        ->and($ambiguousMovement->fresh()->company_id)->toBeNull()
        ->and(CompanyWarehouse::query()
            ->where('company_id', $fixture['companyB']->id)
            ->where('warehouse_id', $fixture['warehouse']->id)
            ->count())->toBe(1)
        ->and(CompanyWarehouse::query()
            ->where('company_id', $fixture['companyB']->id)
            ->where('warehouse_id', $fixture['warehouse']->id)
            ->value('sunat_establishment_code'))->toBeNull()
        ->and($after)->toBe($before)
        ->and(WarehouseKardexMovement::count())->toBe($movementCount)
        ->and([DB::table('electronic_invoices')->count(), DB::table('invoice_collections')->count(), DB::table('bank_movements')->count()])->toBe($externalCounts);
});

it('el comando es dry-run por defecto', function () {
    $fixture = companyOwnershipBase('CMD');
    $legacy = WarehouseStock::create([
        'stock_key' => 'LEGACY-CMD', 'warehouse_id' => $fixture['warehouse']->id,
        'article_id' => $fixture['articleId'], 'unit_id' => $fixture['unitId'],
        'current_quantity' => 1, 'average_unit_cost' => 3, 'total_cost' => 3, 'status' => 'ACTIVE',
    ]);

    $this->artisan('warehouse:audit-company-ownership')
        ->expectsOutputToContain('DRY-RUN')
        ->assertExitCode(0);

    expect($legacy->fresh()->company_id)->toBeNull()
        ->and($legacy->fresh()->sunat_establishment_code ?? null)->toBeNull();
});

it('administra la relación desde UI sin inventar ni hacer único el código SUNAT', function () {
    $fixture = companyOwnershipBase('UI');
    $warehouse = Warehouse::create(['code' => 'ALM-UI2', 'name' => 'ALMACÉN UI 2', 'status' => 'ACTIVE']);
    foreach (['admin.warehouse-entries.index', 'admin.warehouse-entries.update'] as $permission) {
        $fixture['user']->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $response = $this->actingAs($fixture['user'])->postJson(route('admin.company-warehouses.store'), [
        'company_id' => $fixture['companyA']->id, 'warehouse_id' => $warehouse->id,
        'sunat_establishment_code' => '0007', 'is_active' => true,
    ])->assertCreated();
    $relationId = $response->json('data.id');
    $this->actingAs($fixture['user'])->postJson(route('admin.company-warehouses.store'), [
        'company_id' => $fixture['companyB']->id, 'warehouse_id' => $warehouse->id,
        'sunat_establishment_code' => '0007', 'is_active' => true,
    ])->assertCreated();
    $this->actingAs($fixture['user'])->postJson(route('admin.company-warehouses.store'), [
        'company_id' => $fixture['companyA']->id, 'warehouse_id' => $warehouse->id,
        'sunat_establishment_code' => null, 'is_active' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');
    $this->actingAs($fixture['user'])->putJson(route('admin.company-warehouses.update', $relationId), [
        'sunat_establishment_code' => '0007', 'is_active' => false,
    ])->assertOk();
    $this->actingAs($fixture['user'])
        ->getJson(route('admin.warehouse-entries.company-warehouses', $fixture['companyA']))
        ->assertOk()
        ->assertJsonMissing(['id' => $warehouse->id]);
    $this->actingAs($fixture['user'])
        ->getJson(route('admin.warehouse-entries.company-warehouses', $fixture['companyB']))
        ->assertOk()
        ->assertJsonFragment(['id' => $warehouse->id]);
    $unauthorizedCompany = Company::create([
        'business_name' => 'EMPRESA NO AUTORIZADA UI', 'ruc' => '20900000001', 'status' => true,
    ]);
    $unauthorizedWarehouse = Warehouse::create([
        'code' => 'ALM-UI-NO-AUTH', 'name' => 'ALMACÉN NO AUTORIZADO', 'status' => 'ACTIVE',
    ]);
    CompanyWarehouse::create([
        'company_id' => $unauthorizedCompany->id,
        'warehouse_id' => $unauthorizedWarehouse->id,
    ]);
    $invoiceWarehouseIds = app(ElectronicInvoiceFormDataService::class)
        ->get()['warehouses']->pluck('id');

    expect(CompanyWarehouse::findOrFail($relationId)->sunat_establishment_code)->toBe('0007')
        ->and(CompanyWarehouse::findOrFail($relationId)->is_active)->toBeFalse()
        ->and(CompanyWarehouse::where('warehouse_id', $warehouse->id)->count())->toBe(2)
        ->and($invoiceWarehouseIds)->toContain($fixture['warehouse']->id, $warehouse->id)
        ->not->toContain($unauthorizedWarehouse->id);
});
