<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Services\WarehouseEntryAcquisitionCostService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

function inventoryCostEligibilityFixture(): array
{
    $catalogId = DB::table('sunat_catalogs')->insertGetId([
        'code' => '14',
        'name' => 'MÉTODO DE VALORIZACIÓN',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $valuationMethodId = DB::table('sunat_catalog_items')->insertGetId([
        'sunat_catalog_id' => $catalogId,
        'catalog_code' => '14',
        'item_code' => '1',
        'description' => 'PROMEDIO PONDERADO',
        'status' => 'ACTIVE',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::factory()->create();
    $company = Company::create([
        'business_name' => 'EMPRESA ELEGIBILIDAD COSTO',
        'ruc' => '20123456786',
        'inventory_valuation_method_item_id' => $valuationMethodId,
        'status' => true,
    ]);
    $user->companies()->attach($company->id);
    $warehouse = Warehouse::create([
        'code' => 'ALM-COSTO',
        'name' => 'ALMACÉN ELEGIBILIDAD COSTO',
        'status' => 'ACTIVE',
    ]);
    $company->warehouses()->attach($warehouse->id, [
        'sunat_establishment_code' => '0001',
        'is_active' => true,
    ]);
    $unit = Unit::create([
        'abbreviation' => 'UND-EC',
        'description' => 'UNIDAD ELEGIBILIDAD COSTO',
        'sunat_unit_item_id' => testSunatUnitItemId(),
        'status' => 'ACTIVE',
    ]);
    $category = Category::create([
        'code' => 'CAT-COSTO',
        'description' => 'CATEGORÍA ELEGIBILIDAD COSTO',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
    ]);
    $article = Article::create([
        'code' => 'ART-COSTO',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO ELEGIBILIDAD COSTO',
        'billing_name' => 'ARTÍCULO ELEGIBILIDAD COSTO',
        'item_kind' => Article::KIND_PRODUCT,
        'is_inventory_item' => true,
        'has_batch' => false,
        'has_expiration' => false,
        ...testSunatInventoryArticleFields('ART-COSTO'),
        'status' => 'ACTIVE',
    ]);
    $supplier = Supplier::create([
        'ruc' => '20654321017',
        'business_name' => 'PROVEEDOR ELEGIBILIDAD COSTO',
        'supplier_type' => 'BIENES',
        'payment_condition' => 'CREDITO',
        'status' => 'ACTIVE',
    ]);
    $currency = Currency::create([
        'code' => 'PEN',
        'description' => 'SOLES',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);

    return compact('user', 'company', 'warehouse', 'unit', 'article', 'supplier', 'currency');
}

function storeInventoryCostEligibilityExpense(
    TestCase $testCase,
    string $documentType,
    ?bool $requested,
    array $expenseOverrides = []
): array {
    Storage::fake('public');
    $fixture = inventoryCostEligibilityFixture();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('admin.warehouse-entries.store', 'web');
    Permission::findOrCreate('admin.warehouse-entries.expenses.store', 'web');
    $fixture['user']->givePermissionTo([
        'admin.warehouse-entries.store',
        'admin.warehouse-entries.expenses.store',
    ]);

    $expense = array_merge([
        'source_type' => WarehouseEntryExpense::SOURCE_MANUAL,
        'expense_category' => 'other_expense',
        'cost_origin' => 'third_party',
        'expense_type' => 'other',
        'provider_name' => 'TRANSPORTISTA DE PRUEBA',
        'document_type' => $documentType,
        'document_series' => 'EC01',
        'document_number' => '000001',
        'document_date' => '2026-09-23',
        'currency_id' => $fixture['currency']->id,
        'exchange_rate' => 1,
        'amount' => 118,
        'affects_igv' => false,
        'igv_recoverable' => false,
        'distribution_method' => 'quantity',
        'description' => 'Costo vinculado usado para probar elegibilidad de inventario.',
        'distributions' => [[
            'item_index' => 0,
            'distributed_amount' => 118,
        ]],
    ], $expenseOverrides);
    if ($requested !== null) {
        $expense['affects_inventory_cost'] = $requested;
    }

    $response = $testCase->actingAs($fixture['user'])->postJson(route('admin.warehouse-entries.store'), [
        'entry_mode' => 'supplier_invoice',
        'warehouse_id' => $fixture['warehouse']->id,
        'company_id' => $fixture['company']->id,
        'supplier_id' => $fixture['supplier']->id,
        'currency_id' => $fixture['currency']->id,
        'document_type' => 'FACTURA',
        'document_series' => 'FEC1',
        'document_number' => '000001',
        'document_date' => '2026-09-23',
        'movement_date' => '2026-09-23 10:30:00',
        'exchange_rate' => 1,
        'payment_method' => 'transferencia',
        'payment_condition' => 'credito',
        'credit_days' => 15,
        'generate_account_payable' => 1,
        'affect_igv' => 0,
        'expense_management' => 1,
        'items' => [[
            'article_id' => $fixture['article']->id,
            'billing_name_snapshot' => $fixture['article']->billing_name,
            'unit_id' => $fixture['unit']->id,
            'quantity' => 2,
            'unit_price' => 59,
            'discount_amount' => 0,
            'tax_affectation_code' => '10',
            'tax_rate' => 18,
            'is_free' => false,
            'igv_recoverable' => true,
        ]],
        'expenses' => [$expense],
    ])->assertCreated()->assertJsonPath('status', 'success');

    $entry = WarehouseEntry::query()->findOrFail($response->json('data.id'));

    return [$entry, $entry->expenses()->with('distributions')->sole()];
}

it('persiste la elegibilidad solicitada y limpia la distribución de documentos no elegibles', function (
    string $documentType,
    bool $requested,
    bool $expected
) {
    [, $expense] = storeInventoryCostEligibilityExpense($this, $documentType, $requested);

    expect($expense->affects_inventory_cost)->toBe($expected)
        ->and($expense->distribution_method)->toBe($expected ? 'quantity' : null)
        ->and($expense->distributions)->toHaveCount($expected ? 1 : 0);
})->with([
    'factura marcada' => ['FACTURA', true, true],
    'factura no marcada' => ['FACTURA', false, false],
    'boleta marcada' => ['BOLETA', true, true],
    'boleta no marcada' => ['BOLETA', false, false],
    'recibo por honorarios marcado' => ['RECIBO_HONORARIOS', true, true],
    'recibo por honorarios no marcado' => ['RECIBO_HONORARIOS', false, false],
    'sin comprobante manipulado' => ['SIN_COMPROBANTE', true, false],
    'recibo interno manipulado' => ['RECIBO_INTERNO', true, false],
]);

it('persiste un costo nuevo sin capitalizar cuando el request omite la selección', function () {
    [, $expense] = storeInventoryCostEligibilityExpense($this, 'FACTURA', null);

    expect($expense->affects_inventory_cost)->toBeFalse()
        ->and($expense->distribution_method)->toBeNull()
        ->and($expense->distributions)->toHaveCount(0);
});

it('mantiene el tratamiento del IGV sobre un gasto persistido por request', function (
    bool $recoverable,
    float $expectedCapitalizable
) {
    [$entry, $expense] = storeInventoryCostEligibilityExpense($this, 'FACTURA', true, [
        'affects_igv' => true,
        'igv_recoverable' => $recoverable,
    ]);

    expect($expense->taxable_amount)->toBe('100.00')
        ->and($expense->igv_amount)->toBe('18.00')
        ->and($expense->total_amount)->toBe('118.00')
        ->and(app(WarehouseEntryAcquisitionCostService::class)->capitalizableExpenseAmount($entry, $expense))
        ->toBe($expectedCapitalizable);
})->with([
    'IGV recuperable' => [true, 100.0],
    'IGV no recuperable' => [false, 118.0],
]);
