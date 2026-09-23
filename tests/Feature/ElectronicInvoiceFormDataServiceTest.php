<?php

use App\Http\Controllers\Admin\ElectronicInvoiceSeriesController;
use App\Models\ElectronicInvoiceSeries;
use App\Models\SunatCatalogItem;
use App\Services\ElectronicInvoiceFormDataService;
use Illuminate\Support\Facades\DB;

function invoiceFormCompany(string $name): int
{
    $ruc = '20'.str_pad((string) (DB::table('companies')->count() + 1), 9, '0', STR_PAD_LEFT);

    return DB::table('companies')->insertGetId([
        'business_name' => $name,
        'trade_name' => $name,
        'ruc' => $ruc,
        'status' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function invoiceFormSeries(int $companyId, array $attributes): ElectronicInvoiceSeries
{
    return ElectronicInvoiceSeries::create(array_merge([
        'company_id' => $companyId,
        'document_type' => '01',
        'serie' => 'F001',
        'current_number' => 0,
        'next_number' => 1,
        'environment' => 'internal',
        'is_default' => false,
        'status' => 'ACTIVE',
    ], $attributes));
}

it('entrega las afectaciones SUNAT canónicas al formulario aunque el catálogo aún no esté sembrado', function () {
    expect(SunatCatalogItem::query()->where('catalog_code', 'tax_affectation')->count())->toBe(0);

    $data = app(ElectronicInvoiceFormDataService::class)->get();
    $affectations = $data['taxAffectations']->keyBy('item_code');
    $formHtml = view('admin.electronic-invoices.partials.modal', $data)->render();

    expect($data['taxAffectations']->pluck('item_code')->all())->toBe(['10', '20', '30'])
        ->and($affectations['10']->description)->toBe('GRAVADO - OPERACION ONEROSA')
        ->and($affectations['10']->exists)->toBeFalse()
        ->and($formHtml)->toContain('<option value="10">10 | GRAVADO - OPERACION ONEROSA</option>')
        ->toContain('<option value="20">20 | EXONERADO - OPERACION ONEROSA</option>')
        ->toContain('<option value="30">30 | INAFECTO - OPERACION ONEROSA</option>');
});

it('entrega F001 aunque no sea default y excluye series inactivas o no INTERNAL', function () {
    $companyId = invoiceFormCompany('DROPAIV TEST');
    DB::table('electronic_invoice_settings')->insert([
        'company_id' => $companyId,
        'provider' => 'internal',
        'environment' => 'internal',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $f001 = invoiceFormSeries($companyId, ['serie' => 'F001', 'is_default' => false]);
    $inactive = invoiceFormSeries($companyId, ['serie' => 'F002', 'status' => 'INACTIVE']);
    $beta = invoiceFormSeries($companyId, ['serie' => 'F003', 'environment' => 'beta']);

    $data = app(ElectronicInvoiceFormDataService::class)->get();
    $ids = $data['series']->pluck('id');
    $formHtml = view('admin.electronic-invoices.partials.modal', $data)->render();

    expect($ids)
        ->toContain($f001->id)
        ->not->toContain($inactive->id)
        ->not->toContain($beta->id)
        ->and($data['companyEnvironments']->get($companyId))->toBe('internal')
        ->and($f001->is_default)->toBeFalse()
        ->and($formHtml)->toContain('F001 | internal')
        ->toContain('data-is-default="0"');
});

it('conserva metadatos para filtrar empresa y tipo documental en el navegador', function () {
    $companyOne = invoiceFormCompany('EMPRESA UNO');
    $companyTwo = invoiceFormCompany('EMPRESA DOS');
    $invoice = invoiceFormSeries($companyOne, ['serie' => 'F001', 'document_type' => '01']);
    $receipt = invoiceFormSeries($companyOne, ['serie' => 'B001', 'document_type' => '03']);
    $otherCompany = invoiceFormSeries($companyTwo, ['serie' => 'F002', 'document_type' => '01']);

    $series = app(ElectronicInvoiceFormDataService::class)->get()['series']->keyBy('id');

    expect($series[$invoice->id]->company_id)->toBe($companyOne)
        ->and($series[$invoice->id]->document_type)->toBe('01')
        ->and($series[$receipt->id]->document_type)->toBe('03')
        ->and($series[$otherCompany->id]->company_id)->toBe($companyTwo);
});

it('devuelve una etiqueta plana y segura para el ambiente del detalle de serie', function () {
    $companyId = invoiceFormCompany('EMPRESA DETALLE');
    $series = invoiceFormSeries($companyId, ['serie' => 'F001']);

    $response = app(ElectronicInvoiceSeriesController::class)->show($series);
    $payload = $response->getData(true);

    expect($payload['data']['environment'])->toBe('internal')
        ->and($payload['data']['environment_label'])->toBe('Interno')
        ->and(json_encode($payload))->not->toContain('<span');
});
