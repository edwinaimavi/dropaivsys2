<?php

use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use Database\Seeders\SunatCatalogSeeder;
use Illuminate\Support\Facades\DB;

it('documenta las partes 1 a 3 de países sin incorporarlas todavía al seeder', function () {
    $part01File = database_path('data/sunat/staging/35/part-01.json');
    $part02File = database_path('data/sunat/staging/35/part-02.json');
    $part03File = database_path('data/sunat/staging/35/part-03.json');

    expect(file_exists($part01File))->toBeTrue()
        ->and(file_exists($part02File))->toBeTrue()
        ->and(file_exists($part03File))->toBeTrue();

    $part01 = json_decode(file_get_contents($part01File), true, 512, JSON_THROW_ON_ERROR);
    $part02 = json_decode(file_get_contents($part02File), true, 512, JSON_THROW_ON_ERROR);
    $part03 = json_decode(file_get_contents($part03File), true, 512, JSON_THROW_ON_ERROR);
    $part01Items = collect($part01['items']);
    $part02Items = collect($part02['items']);
    $part03Items = collect($part03['items']);
    $stagingItems = $part01Items->concat($part02Items)->concat($part03Items);
    $part01Countries = $part01Items->keyBy('code');
    $part02Countries = $part02Items->keyBy('code');
    $part03Countries = $part03Items->keyBy('code');
    $seedableFiles = glob(database_path('data/sunat/catalogs/*.json')) ?: [];
    $seedableItems = collect($seedableFiles)->sum(function (string $file): int {
        $catalog = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return count($catalog['items']);
    });

    expect($part01['catalog_code'])->toBe('35')
        ->and($part01['catalog_name'])->toBe('PAISES')
        ->and($part01['part'])->toBe(1)
        ->and($part01['source'])->toBe('sunat_pdf')
        ->and($part02['catalog_code'])->toBe('35')
        ->and($part02['catalog_name'])->toBe('PAISES')
        ->and($part02['part'])->toBe(2)
        ->and($part02['source'])->toBe('sunat_pdf')
        ->and($part03['catalog_code'])->toBe('35')
        ->and($part03['catalog_name'])->toBe('PAISES')
        ->and($part03['part'])->toBe(3)
        ->and($part03['source'])->toBe('sunat_pdf')
        ->and($part01Items)->toHaveCount(66)
        ->and($part02Items)->toHaveCount(69)
        ->and($part03Items)->toHaveCount(69)
        ->and($stagingItems)->toHaveCount(204)
        ->and($part03Items->pluck('code')->unique())->toHaveCount(69)
        ->and($part02Items->pluck('code')->unique())->toHaveCount(69)
        ->and($stagingItems->pluck('code')->unique())->toHaveCount(204)
        ->and($stagingItems->every(fn (array $item) => is_string($item['code'])))->toBeTrue()
        ->and($stagingItems->every(fn (array $item) => is_string($item['description'])
            && $item['source'] === 'sunat_pdf'
            && $item['is_official'] === true))->toBeTrue()
        ->and($part01Items->first()['code'])->toBe('9001')
        ->and($part01Items->last()['code'])->toBe('9198')
        ->and($part01Countries['9001']['description'])->toBe('BOUVET ISLAND')
        ->and($part01Countries['9028']['description'])->toBe('ASCENCION')
        ->and($part01Countries['9053']['description'])->toBe('ARABIA SAUDITA')
        ->and($part01Countries['9119']['description'])->toBe('BUTAN')
        ->and($part01Countries['9147']['description'])->toBe("CAMPIONE D'ITALIA")
        ->and($part01Countries['9169']['description'])->toBe('COLOMBIA')
        ->and($part01Countries['9187']['description'])->toBe('COREA (NORTE), REPUBLICA POPULAR DEMOCRATICA DE')
        ->and($part01Countries['9190']['description'])->toBe('COREA (SUR), REPUBLICA DE')
        ->and($part01Countries['9198']['description'])->toBe('CROACIA')
        ->and($part02Items->first()['code'])->toBe('9199')
        ->and($part02Items->last()['code'])->toBe('9411')
        ->and($part02Countries['9199']['description'])->toBe('CUBA')
        ->and($part02Countries['9207']['description'])->toBe('CHECOSLOVAQUIA')
        ->and($part02Countries['9218']['description'])->toBe('TAIWAN (FORMOSA)')
        ->and($part02Countries['9245']['description'])->toBe('ESPAÑA')
        ->and($part02Countries['9348']['description'])->toBe('HONDURAS BRITANICAS')
        ->and($part02Countries['9377']['description'])->toBe('ISLA AZORES')
        ->and($part02Countries['9381']['description'])->toBe('ISLAS DE CHRISTMAS')
        ->and($part02Countries['9382']['description'])->toBe('ISLAS QESHM')
        ->and($part02Countries['9395']['description'])->toBe('JONSTON, ISLAS')
        ->and($part02Countries['9411']['description'])->toBe('KIRIBATI')
        ->and($part03Items->first()['code'])->toBe('9412')
        ->and($part03Items->last()['code'])->toBe('9644')
        ->and($part03Countries['9412']['description'])->toBe('KIRGUIZISTAN')
        ->and($part03Countries['9418']['description'])->toBe('LABUN')
        ->and($part03Countries['9420']['description'])->toBe('LAOS, REPUBLICA POPULAR DEMOCRATICA DE')
        ->and($part03Countries['9440']['description'])->toBe('LIECHTENSTEIN')
        ->and($part03Countries['9447']['description'])->toBe('MACAO')
        ->and($part03Countries['9469']['description'])->toBe('MARIANAS DEL NORTE, ISLAS')
        ->and($part03Countries['9494']['description'])->toBe('MICRONESIA, ESTADOS FEDERADOS DE')
        ->and($part03Countries['9495']['description'])->toBe('MIDWAY ISLAS')
        ->and($part03Countries['9511']['description'])->toBe('NAVIDAD (CHRISTMAS), ISLA')
        ->and($part03Countries['9545']['description'])->toBe('PAPUASIA NUEVA GUINEA')
        ->and($part03Countries['9551']['description'])->toBe('VANUATU')
        ->and($part03Countries['9566']['description'])->toBe('PACIFICO, ISLAS DEL')
        ->and($part03Countries['9573']['description'])->toBe('PAISES BAJOS')
        ->and($part03Countries['9579']['description'])->toBe('TERRITORIO AUTONOMO DE PALESTINA')
        ->and($part03Countries['9589']['description'])->toBe('PERU')
        ->and($part03Countries['9599']['description'])->toBe('POLINESIA FRANCESA')
        ->and($part03Countries['9611']['description'])->toBe('PUERTO RICO')
        ->and($part03Countries['9618']['description'])->toBe('QATAR')
        ->and($part03Countries['9628']['description'])->toBe('REINO UNIDO')
        ->and($part03Countries['9629']['description'])->toBe('ESCOCIA')
        ->and($part03Countries['9633']['description'])->toBe('REPUBLICA ARABE UNIDA')
        ->and($part03Countries['9644']['description'])->toBe('REPUBLICA CHECA')
        ->and($seedableFiles)->toHaveCount(26)
        ->and($seedableItems)->toBe(644);

    $seriesBefore = DB::table('electronic_invoice_series')->count();
    $kardexBefore = DB::table('warehouse_kardex_movements')->count();
    $invoicesBefore = DB::table('electronic_invoices')->count();
    $currenciesBefore = DB::table('currencies')->count();
    $unitsBefore = DB::table('units')->count();
    $banksBefore = DB::table('banks')->count();

    app(SunatCatalogSeeder::class)->run();

    expect(SunatCatalog::where('code', '35')->exists())->toBeFalse()
        ->and(SunatCatalog::count())->toBe(26)
        ->and(SunatCatalogItem::count())->toBe(644)
        ->and(DB::table('electronic_invoice_series')->count())->toBe($seriesBefore)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe($kardexBefore)
        ->and(DB::table('electronic_invoices')->count())->toBe($invoicesBefore)
        ->and(DB::table('currencies')->count())->toBe($currenciesBefore)
        ->and(DB::table('units')->count())->toBe($unitsBefore)
        ->and(DB::table('banks')->count())->toBe($banksBefore);
});
