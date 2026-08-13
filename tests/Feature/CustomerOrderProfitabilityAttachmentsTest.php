<?php

use App\Http\Controllers\Admin\CustomerOrderProfitabilityController;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDocument;
use App\Models\WarehouseEntryItem;
use App\Services\CustomerOrderProfitabilityService;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('public');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = User::factory()->create();
    Permission::findOrCreate('admin.customer-order-profitability.show', 'web');
    $this->user->givePermissionTo('admin.customer-order-profitability.show');
    $this->actingAs($this->user);

    $this->company = Company::create([
        'business_name' => 'DROPAIV S.A.C.',
        'trade_name' => 'DROPAIV',
        'ruc' => '20123456789',
        'status' => true,
    ]);
    $this->currency = Currency::create([
        'code' => 'PEN',
        'description' => 'Soles',
        'symbol' => 'S/',
        'status' => 'ACTIVE',
    ]);
    $this->customer = Customer::create([
        'business_name' => 'CLIENTE DE PRUEBA S.A.C.',
        'document_type' => 'RUC',
        'document_number' => '20600000001',
        'status' => true,
    ]);
    $this->supplier = Supplier::create([
        'ruc' => '20600000002',
        'business_name' => 'PROVEEDOR DE PRUEBA S.A.C.',
        'short_name' => 'PROVEEDOR',
        'supplier_type' => 'SERVICIOS',
        'payment_condition' => 'CONTADO',
        'status' => 'ACTIVE',
    ]);
    $this->order = CustomerPurchaseOrder::create([
        'code' => 'OCC-TEST-001',
        'company_id' => $this->company->id,
        'customer_id' => $this->customer->id,
        'order_type' => 'local',
        'purchase_order_number' => 'OC-001',
        'currency_id' => $this->currency->id,
        'affect_igv' => true,
        'status' => 'registered',
        'created_by' => $this->user->id,
    ]);
    $this->entry = WarehouseEntry::create([
        'entry_number' => 'ING-ATT-001',
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'customer_id' => $this->customer->id,
        'currency_id' => $this->currency->id,
        'status' => 'registered',
    ]);
});

function profitabilityAttachmentCost(array $attributes = []): WarehouseEntryExpense
{
    return WarehouseEntryExpense::create(array_merge([
        'warehouse_entry_id' => test()->entry->id,
        'expense_category' => 'other_expense',
        'cost_origin' => 'third_party',
        'expense_type' => 'other',
        'provider_name' => 'RESPONSABLE',
        'document_type' => 'SIN_COMPROBANTE',
        'currency_id' => test()->currency->id,
        'amount' => 50,
        'affects_igv' => false,
        'igv_rate' => 0,
        'taxable_amount' => 50,
        'igv_amount' => 0,
        'total_amount' => 50,
        'affects_inventory_cost' => false,
        'description' => 'COSTO DE PRUEBA',
        'status' => 'ACTIVE',
    ], $attributes));
}

it('incluye la base corregida en la respuesta AJAX del modal', function () {
    $response = $this->getJson(route('admin.customer-order-profitability.show', $this->order));

    $response->assertOk()
        ->assertJsonStructure([
            'html',
            'metrics' => ['net_profit', 'profitability_base', 'profitability_percentage'],
            'warnings',
        ])
        ->assertJsonPath('metrics.profitability_base', 0)
        ->assertJsonPath('metrics.profitability_percentage', 0);
});

it('prepara acciones para comprobante, pago, recibo interno, ausencia y archivo perdido', function () {
    Storage::disk('public')->put('costos/factura.pdf', '%PDF-1.4');
    Storage::disk('public')->put('costos/pago.png', 'imagen');
    Storage::disk('public')->put('costos/recibo.webp', 'imagen');

    $official = profitabilityAttachmentCost([
        'expense_category' => 'freight_transport',
        'expense_type' => 'agency_freight',
        'document_type' => 'FACTURA',
    ]);
    $official->documents()->create([
        'document_type' => 'invoice',
        'file_path' => 'costos/factura.pdf',
        'original_name' => 'factura.pdf',
        'mime_type' => 'application/pdf',
        'status' => 'ACTIVE',
    ]);
    $official->documents()->create([
        'document_type' => 'payment_proof',
        'file_path' => 'costos/pago.png',
        'original_name' => 'pago.png',
        'mime_type' => 'image/png',
        'status' => 'ACTIVE',
    ]);
    $internal = profitabilityAttachmentCost(['document_type' => 'RECIBO_INTERNO']);
    $internal->documents()->create([
        'document_type' => 'invoice',
        'file_path' => 'costos/recibo.webp',
        'original_name' => 'recibo.webp',
        'mime_type' => 'image/webp',
        'status' => 'ACTIVE',
    ]);
    $withoutFiles = profitabilityAttachmentCost();
    $missing = profitabilityAttachmentCost(['official_document_path' => 'costos/no-existe.pdf']);
    $costs = collect([$official, $internal, $withoutFiles, $missing]);

    $controller = app(CustomerOrderProfitabilityController::class);
    (new ReflectionMethod($controller, 'appendLinkedExpenseAttachments'))
        ->invoke($controller, $this->order, $costs);

    expect(collect($official->profitability_attachments)->pluck('label')->all())
        ->toBe(['Ver comprobante', 'Ver pago'])
        ->and(collect($official->profitability_attachments)->pluck('status')->unique()->all())
        ->toBe(['available'])
        ->and(collect($official->profitability_attachments)->firstWhere('label', 'Ver pago')['is_image'])
        ->toBeTrue()
        ->and($internal->profitability_attachments[0]['label'])->toBe('Ver recibo interno')
        ->and($internal->profitability_attachments[0]['is_image'])->toBeTrue()
        ->and($withoutFiles->profitability_attachments)->toBe([])
        ->and($missing->profitability_attachments[0]['status'])->toBe('missing')
        ->and($missing->profitability_attachments[0]['view_url'])->toBeNull();

    $this->order->setRelation('customer', $this->customer);
    $this->order->setRelation('company', $this->company);
    $this->order->setRelation('currency', $this->currency);
    $this->order->setRelation('items', collect());
    $html = view('admin.customer-order-profitability.partials.detail', [
        'mode' => 'without_igv',
        'usesIgvStructure' => true,
        'order' => $this->order,
        'supplierItems' => collect(),
        'supplierOrderIds' => collect(),
        'costs' => $costs,
        'operationalTransportCosts' => collect([$official]),
        'otherOrUnsupportedCosts' => collect([$internal, $withoutFiles, $missing]),
        'saleTotal' => 0,
        'saleBase' => 0,
        'saleIgv' => 0,
        'saleValue' => 0,
        'saleProfitValue' => 0,
        'purchaseValue' => 0,
        'purchaseTotal' => 0,
        'purchaseBase' => 0,
        'purchaseIgv' => 0,
        'purchaseProfitValue' => 0,
        'freightTotal' => 50,
        'freightBase' => 50,
        'freightIgv' => 0,
        'freightValue' => 50,
        'otherTotal' => 150,
        'linkedTotal' => 200,
        'linkedGrossTotal' => 200,
        'linkedBase' => 200,
        'linkedIgv' => 0,
        'linkedProfitValue' => 200,
        'gross' => 0,
        'operating' => -50,
        'incomeTax' => 0,
        'net' => -200,
        'profitabilityBase' => 200,
        'percentage' => -100,
        'purchasedByItem' => collect(),
        'enteredByCustomerItem' => collect(),
        'warnings' => [],
        'igvRate' => 18,
        'incomeTaxRate' => 29.5,
        'igvSales' => 0,
        'igvPurchases' => 0,
        'igvLinkedCosts' => 0,
        'igvDifference' => 0,
        'igvOfficialCosts' => 0,
        'igvPayable' => 0,
        'igvCreditBalance' => 0,
        'totalTaxes' => 0,
        'orderDocuments' => collect(),
    ])->render();

    expect($html)->toContain('Ver comprobante')
        ->toContain('Ver pago')
        ->toContain('Ver recibo interno')
        ->toContain('Venta considerada')
        ->toContain('Sin adjuntos')
        ->toContain('Archivo no encontrado')
        ->not->toContain('Venta sin IGV')
        ->not->toContain('Comprobante: Sí');
});

it('sirve el adjunto vinculado en línea desde un endpoint autorizado', function () {
    Storage::disk('public')->put('costos/factura.pdf', '%PDF-1.4 contenido');
    $cost = profitabilityAttachmentCost(['document_type' => 'FACTURA']);
    $document = $cost->documents()->create([
        'document_type' => 'invoice',
        'file_path' => 'costos/factura.pdf',
        'original_name' => 'factura-prueba.pdf',
        'mime_type' => 'application/pdf',
        'status' => 'ACTIVE',
    ]);
    $service = Mockery::mock(CustomerOrderProfitabilityService::class);
    $service->shouldReceive('calculate')->once()->andReturn(['costs' => collect([$cost])]);
    $this->app->instance(CustomerOrderProfitabilityService::class, $service);

    $response = $this->get(route('admin.customer-order-profitability.expense-documents.view', [
        $this->order,
        $document,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('inline')
        ->toContain('factura-prueba.pdf');
});

it('sirve la ruta histórica de constancia de pago sin exponer su ubicación física', function () {
    Storage::disk('public')->put('costos/pago-historico.jpg', 'imagen');
    $cost = profitabilityAttachmentCost([
        'document_type' => 'RECIBO_INTERNO',
        'payment_proof_path' => 'costos/pago-historico.jpg',
    ]);
    $service = Mockery::mock(CustomerOrderProfitabilityService::class);
    $service->shouldReceive('calculate')->once()->andReturn(['costs' => collect([$cost])]);
    $this->app->instance(CustomerOrderProfitabilityService::class, $service);

    $response = $this->get(route('admin.customer-order-profitability.expense-files.view', [
        $this->order,
        $cost,
        WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF,
    ]));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('inline')
        ->toContain('pago-historico.jpg')
        ->not->toContain('costos/');
});

it('muestra completos los costos vinculados de la OC 4505460426', function () {
    $firstFreight = profitabilityAttachmentCost([
        'expense_category' => 'freight_transport',
        'expense_type' => 'agency_freight',
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'igv_rate' => 18,
        'amount' => 128,
        'taxable_amount' => 108.47,
        'igv_amount' => 19.53,
        'total_amount' => 128,
    ]);
    $secondFreight = profitabilityAttachmentCost([
        'expense_category' => 'freight_transport',
        'expense_type' => 'agency_freight',
        'document_type' => 'FACTURA',
        'affects_igv' => true,
        'igv_rate' => 18,
        'amount' => 25,
        'taxable_amount' => 21.19,
        'igv_amount' => 3.81,
        'total_amount' => 25,
    ]);
    $other = profitabilityAttachmentCost([
        'amount' => 1150,
        'taxable_amount' => 1150,
        'total_amount' => 1150,
    ]);
    $costs = collect([$firstFreight, $secondFreight, $other]);
    $costs->each(fn (WarehouseEntryExpense $cost) => $cost->setAttribute('profitability_attachments', []));
    $this->order->setRelation('customer', $this->customer);
    $this->order->setRelation('company', $this->company);
    $this->order->setRelation('currency', $this->currency);
    $this->order->setRelation('items', collect());

    $html = view('admin.customer-order-profitability.partials.detail', [
        'mode' => 'without_igv',
        'usesIgvStructure' => true,
        'order' => $this->order,
        'supplierItems' => collect(),
        'supplierOrderIds' => collect(),
        'costs' => $costs,
        'operationalTransportCosts' => collect([$firstFreight, $secondFreight]),
        'otherOrUnsupportedCosts' => collect([$other]),
        'saleTotal' => 10000,
        'saleBase' => 10000,
        'saleIgv' => 0,
        'saleValue' => 10000,
        'saleProfitValue' => 10000,
        'purchaseValue' => 5000,
        'purchaseTotal' => 5000,
        'purchaseBase' => 5000,
        'purchaseIgv' => 0,
        'purchaseProfitValue' => 5000,
        'freightTotal' => 153,
        'freightBase' => 129.66,
        'freightIgv' => 23.34,
        'freightValue' => 153,
        'otherTotal' => 1150,
        'linkedTotal' => 1303,
        'linkedGrossTotal' => 1303,
        'linkedBase' => 1279.66,
        'linkedIgv' => 23.34,
        'linkedProfitValue' => 1303,
        'gross' => 5000,
        'operating' => 4847,
        'incomeTax' => 1429.87,
        'net' => 2267.13,
        'profitabilityBase' => 7732.87,
        'percentage' => 29.32,
        'purchasedByItem' => collect(),
        'enteredByCustomerItem' => collect(),
        'warnings' => [],
        'igvRate' => 18,
        'incomeTaxRate' => 29.5,
        'igvSales' => 0,
        'igvPurchases' => 0,
        'igvLinkedCosts' => 23.34,
        'igvDifference' => -23.34,
        'igvOfficialCosts' => 23.34,
        'igvPayable' => -23.34,
        'igvCreditBalance' => 23.34,
        'totalTaxes' => 1429.87,
        'orderDocuments' => collect(),
    ])->render();

    expect($html)->toContain('Usado en cálculo <strong>153.00</strong>')
        ->toContain('<td class="text-right font-weight-bold">128.00</td>')
        ->toContain('<td class="text-right font-weight-bold">25.00</td>')
        ->toContain('Usado en cálculo <strong>1,150.00</strong>')
        ->toContain('4,847.00')
        ->toContain('1,429.87')
        ->toContain('2,267.13')
        ->toContain('29.32%')
        ->toContain('Base total para rentabilidad')
        ->toContain('7,732.87')
        ->not->toContain('Usado en cálculo <strong>129.66</strong>');
});

it('calcula el modal con bases sin IGV y conserva visibles los totales registrados', function () {
    $category = Category::create([
        'description' => 'CATEGORÍA DE PRUEBA',
        'code' => 'CAT-RENT',
        'type' => 'PRODUCTO COMERCIAL',
        'status' => 'ACTIVE',
    ]);
    $unit = Unit::create([
        'abbreviation' => 'UND-RENT',
        'description' => 'UNIDAD DE PRUEBA',
        'status' => 'ACTIVE',
    ]);
    $article = Article::create([
        'code' => 'ART-RENT',
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'legal_name' => 'ARTÍCULO RENTABILIDAD',
        'billing_name' => 'ARTÍCULO RENTABILIDAD',
        'status' => 'ACTIVE',
    ]);
    $this->order->update([
        'affect_igv' => true,
        'subtotal_taxed' => 15752.54,
        'igv' => 2835.46,
        'grand_total' => 18588.00,
    ]);
    $customerItem = $this->order->items()->create([
        'article_id' => $article->id,
        'billing_name_snapshot' => $article->billing_name,
        'quantity' => 1,
        'unit_price' => 18588.00,
        'subtotal' => 15752.54,
        'tax_amount' => 2835.46,
        'line_total' => 18588.00,
        'status' => 'active',
    ]);
    $supplierOrder = SupplierPurchaseOrder::create([
        'code' => 'OCP-RENT-001',
        'company_id' => $this->company->id,
        'supplier_id' => $this->supplier->id,
        'currency_id' => $this->currency->id,
        'payment_currency_id' => $this->currency->id,
        'customer_purchase_order_id' => $this->order->id,
        'order_type' => 'local',
        'affect_igv' => true,
        'subtotal' => 12671.16,
        'igv' => 2280.81,
        'grand_total' => 14951.97,
        'total_purchase_currency' => 14951.97,
        'total_payment_currency' => 14951.97,
        'total_pen' => 14951.97,
        'status' => 'registered',
    ]);
    $supplierItem = SupplierPurchaseOrderItem::create([
        'supplier_purchase_order_id' => $supplierOrder->id,
        'article_id' => $article->id,
        'customer_purchase_order_item_id' => $customerItem->id,
        'billing_name_snapshot' => $article->billing_name,
        'quantity' => 1,
        'unit_price' => 14951.97,
        'subtotal' => 12671.16,
        'tax_amount' => 2280.81,
        'line_total' => 14951.97,
        'total_with_igv' => 14951.97,
        'taxable_base' => 12671.16,
        'igv_amount' => 2280.81,
        'status' => 'active',
    ]);
    $this->entry->update([
        'supplier_purchase_order_id' => $supplierOrder->id,
        'affect_igv' => true,
        'subtotal' => 12671.16,
        'igv' => 2280.81,
        'grand_total' => 14951.97,
    ]);
    WarehouseEntryItem::create([
        'warehouse_entry_id' => $this->entry->id,
        'supplier_purchase_order_item_id' => $supplierItem->id,
        'article_id' => $article->id,
        'billing_name_snapshot' => $article->billing_name,
        'quantity' => 1,
        'unit_price' => 14951.97,
        'subtotal' => 12671.16,
        'tax_amount' => 2280.81,
        'line_total' => 14951.97,
        'status' => 'active',
    ]);
    profitabilityAttachmentCost([
        'expense_category' => 'freight_transport',
        'expense_type' => 'agency_freight',
        'document_type' => 'FACTURA',
        'amount' => 27.50,
        'affects_igv' => true,
        'igv_rate' => 18,
        'taxable_amount' => 23.31,
        'igv_amount' => 4.19,
        'total_amount' => 27.50,
    ]);
    profitabilityAttachmentCost([
        'expense_category' => 'other_expense',
        'expense_type' => 'other',
        'document_type' => 'RECIBO_INTERNO',
        'amount' => 400.00,
        'affects_igv' => false,
        'igv_rate' => 0,
        'taxable_amount' => 400.00,
        'igv_amount' => 0,
        'total_amount' => 400.00,
    ]);

    $data = app(CustomerOrderProfitabilityService::class)->calculate(
        $this->order->fresh(),
        CustomerOrderProfitabilityService::MODE_WITHOUT_IGV
    );

    expect($data['saleValue'])->toBe(18588.00)
        ->and($data['mode'])->toBe(CustomerOrderProfitabilityService::MODE_WITH_IGV)
        ->and($data['usesIgvStructure'])->toBeTrue()
        ->and($data['purchaseValue'])->toBe(14951.97)
        ->and(round($data['saleProfitValue'], 2))->toBe(15752.54)
        ->and(round($data['purchaseProfitValue'], 2))->toBe(12671.16)
        ->and($data['gross'])->toBe(3081.38)
        ->and(round($data['freightValue'], 2))->toBe(23.31)
        ->and($data['operating'])->toBe(3058.08)
        ->and($data['incomeTax'])->toBe(902.13)
        ->and($data['otherTotal'])->toBe(400.00)
        ->and($data['net'])->toBe(1755.94)
        ->and($data['profitabilityBase'])->toBe(13996.60)
        ->and($data['percentage'])->toBe(12.55)
        ->and($data['linkedTotal'])->toBe(427.50)
        ->and(round($data['linkedProfitValue'], 2))->toBe(423.31)
        ->and($data['igvPayable'])->toBe(550.46)
        ->and($data['totalTaxes'])->toBe(1452.59)
        ->and($data['gross'])->not->toBe(3636.03);

    $pdf = view('admin.customer-order-profitability.pdf', $data)->render();

    expect($pdf)->toContain('Costos oficiales')
        ->toContain('IGV por pagar')
        ->toContain('S/ 550.46')
        ->toContain('Total impuestos')
        ->toContain('S/ 1,452.59')
        ->toContain('Base total para rentabilidad')
        ->toContain('S/ 13,996.60')
        ->toContain('12.55%');

    $this->order->update(['affect_igv' => false]);
    $exonerated = app(CustomerOrderProfitabilityService::class)->calculate(
        $this->order->fresh(),
        CustomerOrderProfitabilityService::MODE_WITH_IGV
    );

    expect($exonerated['mode'])->toBe(CustomerOrderProfitabilityService::MODE_WITHOUT_IGV)
        ->and($exonerated['usesIgvStructure'])->toBeFalse()
        ->and($exonerated['saleProfitValue'])->toBe(18588.00)
        ->and($exonerated['purchaseProfitValue'])->toBe(14951.97)
        ->and($exonerated['gross'])->toBe(3636.03)
        ->and($exonerated['freightValue'])->toBe(27.50)
        ->and($exonerated['operating'])->toBe(3608.53)
        ->and($exonerated['incomeTax'])->toBe(1064.52)
        ->and($exonerated['otherTotal'])->toBe(400.00)
        ->and($exonerated['net'])->toBe(2144.01)
        ->and($exonerated['profitabilityBase'])->toBe(16443.99)
        ->and($exonerated['percentage'])->toBe(13.04)
        ->and($exonerated['igvPayable'])->toBe(0.0)
        ->and($exonerated['totalTaxes'])->toBe(1064.52);
});
