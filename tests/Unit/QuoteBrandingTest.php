<?php

use App\Http\Controllers\Admin\QuoteController;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

uses(Tests\TestCase::class);

function quoteBrandingForTest(Quote $quote): array
{
    $method = new ReflectionMethod(QuoteController::class, 'quoteBranding');

    return $method->invoke(new QuoteController(), $quote);
}

function quoteAmountInWordsForTest(float $amount, string $currency): string
{
    $method = new ReflectionMethod(QuoteController::class, 'numeroALetras');

    return $method->invoke(new QuoteController(), $amount, $currency);
}

function quoteCurrencyNameForTest(string $code, ?string $description = null): string
{
    $quote = new Quote();
    $quote->setRelation('currency', (new Currency())->forceFill([
        'code' => $code,
        'description' => $description,
    ]));
    $method = new ReflectionMethod(QuoteController::class, 'quoteCurrencyName');

    return $method->invoke(new QuoteController(), $quote);
}

it('convierte el total 1800 a letras respetando la moneda', function (
    string $currency,
    string $expected
) {
    expect(quoteAmountInWordsForTest(1800, $currency))->toBe($expected);
})->with([
    'PEN' => ['SOLES', 'SON MIL OCHOCIENTOS CON 00/100 SOLES'],
    'CHF' => ['FRANCOS SUIZOS', 'SON MIL OCHOCIENTOS CON 00/100 FRANCOS SUIZOS'],
]);

it('resuelve el nombre de la moneda de la cotizacion', function (
    string $code,
    ?string $description,
    string $expected
) {
    expect(quoteCurrencyNameForTest($code, $description))->toBe($expected);
})->with([
    'PEN' => ['PEN', 'Sol peruano', 'SOLES'],
    'USD' => ['USD', 'Dólar americano', 'DÓLARES'],
    'CHF' => ['CHF', 'Franco suizo', 'FRANCOS SUIZOS'],
    'fallback' => ['EUR', 'Euros', 'EUROS'],
]);

it('muestra a Anabel Cudeñas como registrada por para Praga', function () {
    $quote = new Quote();
    $quote->setRelation('company', (new Company())->forceFill([
        'business_name' => 'PRAGA INVERSIONES S.A.C.',
        'trade_name' => 'PRAGA',
    ]));
    $quote->setRelation('creator', (new User())->forceFill([
        'name' => 'USUARIO',
        'lastname' => 'REAL',
    ]));

    expect(quoteBrandingForTest($quote))->toMatchArray([
        'brandColor' => '#1d4ed8',
        'signaturePath' => public_path('vendor/adminlte/dist/img/firmapraga.jpeg'),
        'registeredBy' => 'ANABEL CUDEÑAS',
    ]);
});

it('muestra al usuario creador como registrado por para Dropaiv', function () {
    $quote = new Quote();
    $quote->setRelation('company', (new Company())->forceFill([
        'business_name' => 'DROPAIV SERVICIOS GENERALES S.A.C.',
        'trade_name' => 'DROPAIV',
    ]));
    $quote->setRelation('creator', (new User())->forceFill([
        'name' => 'EDWIN',
        'lastname' => 'CIGÜEÑAS MAYA',
    ]));

    expect(quoteBrandingForTest($quote))->toMatchArray([
        'brandColor' => '#15803d',
        'signaturePath' => public_path('vendor/adminlte/dist/img/firmadropaiv.jpeg'),
        'registeredBy' => 'EDWIN CIGÜEÑAS MAYA',
    ]);
});

it('renderiza el PDF con su firma empresarial y sin columna de descuento', function (
    string $companyName,
    string $tradeName,
    string $expectedRegisteredBy,
    string $signatureFile
) {
    $quote = (new Quote())->forceFill([
        'quote_number' => 'COT-TEST',
        'orientation' => 'vertical',
        'delivery_address' => 'JR. LIMA 260',
        'issuer_department' => 'COMPRAS',
        'contact_number' => '954241544',
        'payment_condition' => 'CONTADO',
        'subtotal_exonerated' => 0,
        'subtotal_taxed' => 100,
        'igv' => 18,
        'grand_total' => 118,
        'created_at' => Carbon::parse('2026-07-22'),
    ]);
    $quote->setRelation('company', (new Company())->forceFill([
        'business_name' => $companyName,
        'trade_name' => $tradeName,
        'email' => 'praga@gmail.com',
    ]));
    $quote->setRelation('customer', (new Customer())->forceFill([
        'business_name' => 'MINISTERIO DE SALUD',
        'ruc' => '20123456789',
    ]));
    $quote->setRelation('customerBranch', null);
    $quote->setRelation('currency', (new Currency())->forceFill([
        'symbol' => 'S/',
        'code' => 'PEN',
    ]));
    $quote->setRelation('creator', (new User())->forceFill([
        'name' => 'USUARIO',
        'lastname' => 'REAL',
    ]));
    $quote->setRelation('items', collect());

    $branding = quoteBrandingForTest($quote);
    $html = view('admin.quotes.pdf.quote', [
        'quote' => $quote,
        'orientation' => 'vertical',
        'amountInWords' => quoteAmountInWordsForTest(118, 'SOLES'),
        ...$branding,
    ])->render();
    $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();

    expect(substr_count($html, 'class="info-box"'))->toBe(2)
        ->and(substr_count($html, 'class="notes"'))->toBe(1)
        ->and(substr_count($html, 'class="totals-box"'))->toBe(1)
        ->and($html)->toContain($expectedRegisteredBy, 'COMPRAS', '954241544', $signatureFile)
        ->and($html)->toContain('MONTO EN LETRAS', 'SON CIENTO DIECIOCHO CON 00/100 SOLES')
        ->and($html)->not->toContain('Documento generado automaticamente por DroPaivSys')
        ->and($html)->not->toContain('<th width="6%">Desc.</th>', 'authorizedName', 'authorizedPosition')
        ->and(str_starts_with($pdf, '%PDF'))->toBeTrue();
})->with([
    'PRAGA' => [
        'PRAGA MEDICAL IMPORT S.A.C.',
        'PRAGA',
        'ANABEL CUDEÑAS',
        'firmapraga.jpeg',
    ],
    'DROPAIV' => [
        'DROGUERIA DROPAIV S.A.C.',
        'DROPAIV',
        'USUARIO REAL',
        'firmadropaiv.jpeg',
    ],
]);
