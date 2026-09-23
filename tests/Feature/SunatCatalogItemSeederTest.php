<?php

use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use App\Models\User;
use Database\Seeders\SunatCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

function officialSunatCatalogFiles(): array
{
    $files = glob(database_path('data/sunat/catalogs/*.json')) ?: [];
    sort($files);

    return $files;
}

it('documenta B1 a B4B con veintiséis catálogos, 644 items y códigos string', function () {
    $expectedCounts = [
        '01' => 22,
        '02' => 6,
        '03' => 38,
        '04' => 178,
        '05' => 19,
        '06' => 62,
        '10' => 61,
        '11' => 30,
        '12' => 47,
        '13' => 3,
        '14' => 6,
        '15' => 11,
        '16' => 4,
        '17' => 8,
        '18' => 2,
        '19' => 3,
        '20' => 3,
        '21' => 5,
        '22' => 9,
        '25' => 10,
        '27' => 13,
        '28' => 46,
        '30' => 5,
        '31' => 44,
        '32' => 3,
        '33' => 6,
    ];
    $files = officialSunatCatalogFiles();
    $payloads = collect($files)->map(fn (string $file) => json_decode(
        file_get_contents($file),
        true,
        512,
        JSON_THROW_ON_ERROR
    ));
    $fileNames = collect($files)->map(fn (string $file) => basename($file));

    expect($files)->toHaveCount(26)
        ->and($fileNames)->toContain('25.json', '27.json', '28.json', '30.json', '31.json', '32.json', '33.json')
        ->and($payloads->sum(fn (array $payload) => count($payload['items'])))->toBe(644)
        ->and($payloads->mapWithKeys(fn (array $payload) => [
            $payload['catalog_code'] => count($payload['items']),
        ])->all())->toBe($expectedCounts)
        ->and($payloads->every(fn (array $payload) => is_string($payload['catalog_code'])))->toBeTrue()
        ->and($payloads->every(fn (array $payload) => is_string($payload['catalog_name'])
            && (is_null($payload['description']) || is_string($payload['description']))
            && is_string($payload['source'])
            && is_bool($payload['is_active'])
            && is_array($payload['items'])))->toBeTrue()
        ->and($payloads->every(fn (array $payload) => $payload['source'] === 'sunat_pdf'))->toBeTrue()
        ->and($payloads->every(fn (array $payload) => count($payload['items']) === collect($payload['items'])->pluck('code')->unique()->count()))->toBeTrue()
        ->and($payloads->every(fn (array $payload) => collect($payload['items'])->every(
            fn (array $item) => is_string($item['code'])
                && is_string($item['description'])
                && mb_strlen($item['description']) <= 5000
                && $item['source'] === 'sunat_pdf'
                && $item['is_official'] === true
                && $item['is_active'] === true
                && $item['short_name'] === null
                && ($item['extra_data'] === null || is_array($item['extra_data']))
        )))->toBeTrue();

    $catalog01 = $payloads->firstWhere('catalog_code', '01');
    $catalog02 = $payloads->firstWhere('catalog_code', '02');
    $catalog04 = $payloads->firstWhere('catalog_code', '04');
    $catalog06 = $payloads->firstWhere('catalog_code', '06');
    $catalog10 = $payloads->firstWhere('catalog_code', '10');
    $catalog15 = $payloads->firstWhere('catalog_code', '15');
    $catalog16 = $payloads->firstWhere('catalog_code', '16');
    $catalog17 = $payloads->firstWhere('catalog_code', '17');
    $catalog18 = $payloads->firstWhere('catalog_code', '18');
    $catalog19 = $payloads->firstWhere('catalog_code', '19');
    $catalog20 = $payloads->firstWhere('catalog_code', '20');
    $catalog21 = $payloads->firstWhere('catalog_code', '21');
    $catalog22 = $payloads->firstWhere('catalog_code', '22');
    $catalog25 = $payloads->firstWhere('catalog_code', '25');
    $catalog27 = $payloads->firstWhere('catalog_code', '27');
    $catalog28 = $payloads->firstWhere('catalog_code', '28');
    $catalog30 = $payloads->firstWhere('catalog_code', '30');
    $catalog31 = $payloads->firstWhere('catalog_code', '31');
    $catalog32 = $payloads->firstWhere('catalog_code', '32');
    $catalog33 = $payloads->firstWhere('catalog_code', '33');
    $currencies = collect($catalog04['items'])->keyBy('code');
    $units = collect($catalog06['items'])->keyBy('code');
    $documents = collect($catalog10['items'])->keyBy('code');
    $titles = collect($catalog15['items'])->keyBy('code');
    $shares = collect($catalog16['items'])->keyBy('code');
    $accountPlans = collect($catalog17['items'])->keyBy('code');
    $fixedAssetTypes = collect($catalog18['items'])->keyBy('code');
    $fixedAssetStatuses = collect($catalog19['items'])->keyBy('code');
    $depreciationMethods = collect($catalog20['items'])->keyBy('code');
    $productionGroups = collect($catalog21['items'])->keyBy('code');
    $financialStatements = collect($catalog22['items'])->keyBy('code');
    $doubleTaxationAgreements = collect($catalog25['items'])->keyBy('code');
    $economicRelationships = collect($catalog27['items'])->keyBy('code');
    $netWorth = collect($catalog28['items'])->keyBy('code');
    $acquiredGoodsAndServices = collect($catalog30['items'])->keyBy('code');
    $incomeTypes = collect($catalog31['items'])->keyBy('code');
    $nonDomiciledServiceModalities = collect($catalog32['items'])->keyBy('code');
    $nonDomiciledExemptions = collect($catalog33['items'])->keyBy('code');
    $schema = json_decode(file_get_contents(database_path('data/sunat/schema.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($catalog01['catalog_code'])->toBe('01')
        ->and($catalog01['items'][0]['code'])->toBe('001')
        ->and(collect($catalog02['items'])->pluck('code'))->toContain('A')
        ->and($catalog04['catalog_code'])->toBe('04')
        ->and($catalog04['items'])->toHaveCount(178)
        ->and(collect($catalog04['items'])->pluck('code')->unique())->toHaveCount(178)
        ->and($currencies['PEN']['description'])->toBe('Nuevo Sol o Sol')
        ->and($currencies['PEN']['extra_data']['country_or_reference_zone'])->toBe('Perú')
        ->and($currencies['USD']['description'])->toBe('US Dollar')
        ->and($currencies['USD']['extra_data']['country_or_reference_zone'])->toBe('Estados Unidos (EEUU)')
        ->and($currencies['EUR']['description'])->toBe('Euro')
        ->and($currencies['EUR']['extra_data']['country_or_reference_zone'])->toBe('Unión Europea')
        ->and($currencies['JPY']['description'])->toBe('Yen')
        ->and($currencies['XAU']['description'])->toBe('Gold')
        ->and($currencies['XAU']['extra_data']['country_or_reference_zone'])->toBe('-')
        ->and($currencies['BMD']['description'])->toBe('Bermudian Dollar (customarily known as Bermuda Dollar)')
        ->and($currencies['MXV']['description'])->toBe('Mexican Unidad de Inversion (UDI)')
        ->and($currencies)->toHaveKeys(['USN', 'USS', 'BYR', 'EEK', 'LTL', 'LVL', 'MRO', 'STD', 'VEF', 'ZMK'])
        ->and($currencies['USN']['description'])->not->toBe($currencies['USS']['description'])
        ->and($catalog06['items'])->toHaveCount(62)
        ->and($catalog10['items'])->toHaveCount(61)
        ->and($units['4A']['code'])->toBe('4A')
        ->and($units['NIU']['description'])->toBe('UNIDAD (BIENES)')
        ->and($units['ZZ']['description'])->toBe('UNIDAD (SERVICIOS)')
        ->and($units['C62']['description'])->toBe('PIEZAS')
        ->and($documents['01']['description'])->toBe('Factura')
        ->and($documents['03']['description'])->toBe('Boleta de Venta')
        ->and($documents['07']['description'])->toBe('Nota de crédito')
        ->and($documents['08']['description'])->toBe('Nota de débito')
        ->and($documents['09']['description'])->toBe('Guía de remisión - Remitente')
        ->and($documents['31']['description'])->toBe('Guía de Remisión - Transportista')
        ->and($documents['87']['description'])->toBe('Nota de Crédito Especial')
        ->and($documents['98']['description'])->toBe('Nota de Débito - No Domiciliado')
        ->and(mb_strlen($documents['26']['description']))->toBeGreaterThan(255)
        ->and($schema['properties']['items']['items']['properties']['description']['maxLength'])->toBe(5000)
        ->and(mb_strlen($documents['26']['description']))->toBeLessThanOrEqual(2000);

    expect(collect([$catalog15, $catalog16, $catalog17, $catalog18, $catalog19, $catalog20, $catalog21, $catalog22])
        ->sum(fn (array $catalog) => count($catalog['items'])))->toBe(45)
        ->and($titles['01']['description'])->toBe('VALORES EMITIDOS O GARANTIZADOS POR EL ESTADO')
        ->and($shares['01']['description'])->toBe('ACCIONES CON DERECHO A VOTO')
        ->and($accountPlans['01']['description'])->toBe('PLAN CONTABLE GENERAL EMPRESARIAL')
        ->and($fixedAssetTypes['1']['description'])->toBe('NO REVALUADO O REVALUADO SIN EFECTO TRIBUTARIO')
        ->and($fixedAssetStatuses['2']['description'])->toBe('ACTIVOS OBSOLETOS')
        ->and($depreciationMethods['1']['description'])->toBe('LINEA RECTA')
        ->and($productionGroups['3']['description'])->toBe('PRODUCTO')
        ->and($financialStatements['04']['description'])->toBe('SUPERINTENDENCIA DEL MERCADO DE VALORES - ADMINISTRADORAS DE FONDOS DE PENSIONES (AFP)')
        ->and($financialStatements['08']['description'])->toBe('SUPERINTENDENCIA DEL MERCADO DE VALORES - ICLV');

    expect(collect([$catalog25, $catalog28, $catalog30, $catalog32, $catalog33])
        ->sum(fn (array $catalog) => count($catalog['items'])))->toBe(70)
        ->and($doubleTaxationAgreements['00']['description'])->toBe('NINGUNO')
        ->and($doubleTaxationAgreements['03']['description'])->toBe('COMUNIDAD ANDINA DE NACIONES (CAN)')
        ->and($netWorth['5011']['description'])->toBe('Acciones')
        ->and($netWorth['564']['description'])->toBe('Ganancia o pérdida en activos o pasivos financieros disponibles para la venta - Compra o venta convencional fecha de liquidación')
        ->and($netWorth['585']['description'])->toBe('Facultativas')
        ->and($netWorth['5922']['description'])->toBe('Gastos de años anteriores')
        ->and($acquiredGoodsAndServices['1']['description'])->toBe('MERCADERIA, MATERIA PRIMA, SUMINISTRO, ENVASES Y EMBALAJES')
        ->and($acquiredGoodsAndServices['4']['description'])->toBe('GASTOS DE EDUCACIÓN, RECREACIÓN, SALUD, CULTURALES. REPRESENTACIÓN, CAPACITACIÓN, DE VIAJE, MANTENIMIENTO DE VEHÍCULO Y DE PREMIOS')
        ->and($nonDomiciledServiceModalities['2']['description'])->toBe('SERVICIO PRESTADO PARTE EN EL PERÚ Y PARTE EN EL EXTRANJERO')
        ->and($nonDomiciledExemptions['1']['description'])->toBe('Los intereses provenientes de créditos de fomento otorgados directamente o mediante proveedores o intermediarios financieros por organismos internacionales o instituciones gubernamentales extranjeras.')
        ->and($nonDomiciledExemptions['6']['description'])->toBe('Los ingresos extranjeros por los espectáculos en vivo de teatro, zarzuela, conciertos de música clásica, ópera, opereta, ballet y folclor, calificados como espectáculos públicos culturales por el Instituto Nacional de Cultura, realizados en el país. brutos que perciben las representaciones de países.');

    expect(collect([$catalog27, $catalog31])
        ->sum(fn (array $catalog) => count($catalog['items'])))->toBe(57)
        ->and($economicRelationships['00']['description'])->toBe('Sin vinculación')
        ->and($economicRelationships['00']['extra_data'])->toBeNull()
        ->and($economicRelationships['01']['extra_data']['legal_reference'])->toBe('Artículo 24° numeral 1')
        ->and($economicRelationships['05']['description'])->toContain('una o más directores')
        ->and(mb_strlen($economicRelationships['12']['description']))->toBeGreaterThan(2000)
        ->and(mb_strlen($economicRelationships['12']['description']))->toBeLessThanOrEqual(5000)
        ->and($economicRelationships['12']['extra_data']['legal_reference'])->toBe('Artículo 24° numeral 12')
        ->and($incomeTypes['00']['extra_data'])->toBeNull()
        ->and($incomeTypes['01']['extra_data']['oecd_income_code'])->toBe('06')
        ->and($incomeTypes['03']['extra_data']['oecd_income_code'])->toBe('12')
        ->and($incomeTypes['18']['extra_data']['oecd_income_code'])->toBe('21')
        ->and($incomeTypes['30']['extra_data']['oecd_income_code'])->toBe('07 - 21')
        ->and($incomeTypes['30']['extra_data']['legal_reference'])->toBe('Art. 12 y 48° a)')
        ->and($incomeTypes['43']['extra_data']['oecd_income_code'])->toBe('15')
        ->and($incomeTypes['04']['description'])->toContain('por los que pagan son utilizados país')
        ->and($incomeTypes['11']['description'])->toContain('sujeto domiciliada')
        ->and($incomeTypes['28']['description'])->toContain('enajenación de de empresas')
        ->and($incomeTypes['33']['description'])->toContain('1% por el ingresos')
        ->and($incomeTypes->except('00')->every(fn (array $item) => is_string($item['extra_data']['oecd_income_code'])))->toBeTrue();
});

it('carga el primer lote real con los valores documentales de control', function () {
    app(SunatCatalogSeeder::class)->run();

    $catalog10Payload = json_decode(
        file_get_contents(database_path('data/sunat/catalogs/10.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $officialCode26 = collect($catalog10Payload['items'])->firstWhere('code', '26')['description'];
    $catalog27Payload = json_decode(
        file_get_contents(database_path('data/sunat/catalogs/27.json')),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    $officialEconomicRelationship12 = collect($catalog27Payload['items'])->firstWhere('code', '12')['description'];
    $expectedCode26 = 'Recibo por el Pago de la Tarifa por Uso de Agua Superficial con fines agrarios y por el pago de la Cuota para la ejecución de una determinada obra o actividad acordada por la Asamblea General de la Comisión de Regantes o Resolución expedida por el Jefe de la Unidad de Aguas y de Riego (Decreto Supremo N° 003-90-AG, Arts. 28 y 48)';

    expect(SunatCatalog::count())->toBe(26)
        ->and(SunatCatalogItem::count())->toBe(644)
        ->and($officialCode26)->toBe($expectedCode26)
        ->and(SunatCatalog::where('code', '01')->value('code'))->toBe('01')
        ->and(SunatCatalogItem::where('catalog_code', '01')->where('item_code', '001')->value('item_code'))->toBe('001')
        ->and(SunatCatalogItem::where('catalog_code', '02')->where('item_code', 'A')->exists())->toBeTrue()
        ->and(SunatCatalogItem::where('catalog_code', '12')->where('item_code', '24')->value('description'))
        ->toBe('ENTRADA POR DEVOLUCION DEL CLIENTE')
        ->and(SunatCatalogItem::where('catalog_code', '11')->where('item_code', '271')->value('description'))
        ->toBe('TARAPOTO')
        ->and(SunatCatalogItem::where('catalog_code', '14')->where('item_code', '1')->value('description'))
        ->toBe('PROMEDIO PONDERADO')
        ->and(SunatCatalogItem::where('catalog_code', '10')->where('item_code', '26')->value('description'))
        ->toBe($officialCode26)
        ->and(SunatCatalogItem::where('catalog_code', '27')->where('item_code', '12')->value('description'))
        ->toBe($officialEconomicRelationship12)
        ->and(mb_strlen($officialEconomicRelationship12))->toBeGreaterThan(2000)
        ->and(SunatCatalog::where('source', 'sunat_pdf')->where('is_active', true)->count())->toBe(26)
        ->and(SunatCatalogItem::where('source', 'sunat_pdf')->where('is_official', true)->where('status', 'ACTIVE')->count())->toBe(644)
        ->and(SunatCatalog::whereNotNull('created_by_user_id')->count())->toBe(0)
        ->and(SunatCatalogItem::whereNotNull('created_by_user_id')->count())->toBe(0)
        ->and(DB::table('electronic_invoice_series')->count())->toBe(0);
});

it('es idempotente, no elimina datos manuales ni toca monedas, unidades, series o Kardex', function () {
    $timestamp = now();
    $manualCatalog = SunatCatalog::create([
        'code' => 'MANUAL',
        'name' => 'CATÁLOGO ADMINISTRATIVO',
        'source' => 'manual',
        'is_active' => true,
    ]);
    $manualItem = $manualCatalog->items()->create([
        'catalog_code' => 'MANUAL',
        'item_code' => 'X01',
        'description' => 'ELEMENTO MANUAL',
        'source' => 'manual',
        'is_official' => false,
        'status' => 'ACTIVE',
    ]);
    $currencyId = DB::table('currencies')->insertGetId([
        'code' => 'TST',
        'description' => 'MONEDA DE CONTROL',
        'symbol' => 'T',
        'status' => 'ACTIVE',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $unitId = DB::table('units')->insertGetId([
        'abbreviation' => 'TST',
        'description' => 'UNIDAD DE CONTROL',
        'decimal_quantity' => false,
        'status' => 'ACTIVE',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $currencyBefore = (array) DB::table('currencies')->find($currencyId);
    $unitBefore = (array) DB::table('units')->find($unitId);
    $seriesBefore = DB::table('electronic_invoice_series')->count();
    $kardexBefore = DB::table('warehouse_kardex_movements')->count();
    $invoicesBefore = DB::table('electronic_invoices')->count();
    $banksBefore = DB::table('banks')->count();

    $seeder = app(SunatCatalogSeeder::class);
    $seeder->run();
    $firstCatalogIds = SunatCatalog::where('source', 'sunat_pdf')->orderBy('code')->pluck('id', 'code')->all();
    $firstItemIds = SunatCatalogItem::where('source', 'sunat_pdf')
        ->orderBy('catalog_code')
        ->orderBy('item_code')
        ->get(['id', 'catalog_code', 'item_code'])
        ->mapWithKeys(fn (SunatCatalogItem $item) => ["{$item->catalog_code}:{$item->item_code}" => $item->id])
        ->all();

    $seeder->run();

    expect(SunatCatalog::count())->toBe(27)
        ->and(SunatCatalogItem::count())->toBe(645)
        ->and(SunatCatalog::where('source', 'sunat_pdf')->orderBy('code')->pluck('id', 'code')->all())->toBe($firstCatalogIds)
        ->and(SunatCatalogItem::where('source', 'sunat_pdf')
            ->orderBy('catalog_code')
            ->orderBy('item_code')
            ->get(['id', 'catalog_code', 'item_code'])
            ->mapWithKeys(fn (SunatCatalogItem $item) => ["{$item->catalog_code}:{$item->item_code}" => $item->id])
            ->all())->toBe($firstItemIds)
        ->and(SunatCatalog::whereKey($manualCatalog->id)->exists())->toBeTrue()
        ->and(SunatCatalogItem::whereKey($manualItem->id)->exists())->toBeTrue()
        ->and((array) DB::table('currencies')->find($currencyId))->toBe($currencyBefore)
        ->and((array) DB::table('units')->find($unitId))->toBe($unitBefore)
        ->and(DB::table('electronic_invoice_series')->count())->toBe($seriesBefore)
        ->and(DB::table('warehouse_kardex_movements')->count())->toBe($kardexBefore)
        ->and(DB::table('electronic_invoices')->count())->toBe($invoicesBefore)
        ->and(DB::table('banks')->count())->toBe($banksBefore);
});

it('persiste más de 2000 caracteres y el CRUD admite hasta 5000 sin truncar', function () {
    $user = User::factory()->create();
    foreach (['catalogos_sunat.crear', 'catalogos_sunat.editar'] as $permissionName) {
        Permission::findOrCreate($permissionName, 'web');
    }
    $user->givePermissionTo(['catalogos_sunat.crear', 'catalogos_sunat.editar']);
    $catalog = SunatCatalog::create(['code' => 'LARGO', 'name' => 'PRUEBA DE TEXTO']);
    $createDescription = str_repeat('Descripción documental extensa. ', 80);
    $updateDescription = str_repeat('Texto oficial actualizado sin recorte. ', 70);
    $tooLongDescription = str_repeat('X', 5001);

    expect(mb_strlen($createDescription))->toBeGreaterThan(2000)
        ->and(mb_strlen($createDescription))->toBeLessThanOrEqual(5000)
        ->and(mb_strlen($updateDescription))->toBeGreaterThan(2000)
        ->and(mb_strlen($updateDescription))->toBeLessThanOrEqual(5000)
        ->and(Schema::getColumnType('sunat_catalog_items', 'description'))->toBe('text')
        ->and(file_get_contents(resource_path('views/admin/sunat-catalogs/index.blade.php')))
        ->toContain('id="itemDescription" name="description" rows="4" maxlength="5000"');

    $response = $this->actingAs($user)->postJson(route('admin.sunat-catalogs.items.store', $catalog), [
        'item_code' => 'TXT',
        'description' => $createDescription,
    ]);
    $response->assertCreated();
    $item = SunatCatalogItem::where('catalog_code', 'LARGO')->where('item_code', 'TXT')->firstOrFail();

    expect($item->description)->toBe(mb_strtoupper(trim($createDescription), 'UTF-8'));

    $this->actingAs($user)->putJson(route('admin.sunat-catalogs.items.update', [$catalog, $item]), [
        'item_code' => 'TXT',
        'description' => $updateDescription,
    ])->assertOk();

    expect($item->fresh()->description)->toBe(mb_strtoupper(trim($updateDescription), 'UTF-8'));

    $this->actingAs($user)->postJson(route('admin.sunat-catalogs.items.store', $catalog), [
        'item_code' => 'EXCESO',
        'description' => $tooLongDescription,
    ])->assertUnprocessable()->assertJsonValidationErrors('description');
});
