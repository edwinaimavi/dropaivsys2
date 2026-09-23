<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function testSunatUnitItemId(): int
{
    $db = \Illuminate\Support\Facades\DB::class;
    $catalogId = $db::table('sunat_catalogs')->where('code', '06')->value('id');
    if (! $catalogId) {
        $catalogId = $db::table('sunat_catalogs')->insertGetId([
            'code' => '06', 'name' => 'UNIDAD DE MEDIDA', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $itemId = $db::table('sunat_catalog_items')->where('catalog_code', '06')->where('item_code', 'NIU')->value('id');
    if (! $itemId) {
        $itemId = $db::table('sunat_catalog_items')->insertGetId([
            'sunat_catalog_id' => $catalogId, 'catalog_code' => '06', 'item_code' => 'NIU',
            'description' => 'UNIDAD (BIENES)', 'status' => 'ACTIVE',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return (int) $itemId;
}

function testSunatInventoryMasterIds(): array
{
    $db = \Illuminate\Support\Facades\DB::class;
    $now = now();

    $catalog05Id = $db::table('sunat_catalogs')->where('code', '05')->value('id');
    if (! $catalog05Id) {
        $catalog05Id = $db::table('sunat_catalogs')->insertGetId([
            'code' => '05', 'name' => 'TIPO DE EXISTENCIA', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $type05Id = $db::table('sunat_catalog_items')->where('catalog_code', '05')->where('item_code', '01')->value('id');
    if (! $type05Id) {
        $type05Id = $db::table('sunat_catalog_items')->insertGetId([
            'sunat_catalog_id' => $catalog05Id, 'catalog_code' => '05', 'item_code' => '01',
            'description' => 'MERCADERÍAS', 'status' => 'ACTIVE',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    $catalog13Id = $db::table('sunat_catalogs')->where('code', '13')->value('id');
    if (! $catalog13Id) {
        $catalog13Id = $db::table('sunat_catalogs')->insertGetId([
            'code' => '13', 'name' => 'CATÁLOGO DE EXISTENCIAS', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $other13Id = $db::table('sunat_catalog_items')->where('catalog_code', '13')->where('item_code', '9')->value('id');
    if (! $other13Id) {
        $other13Id = $db::table('sunat_catalog_items')->insertGetId([
            'sunat_catalog_id' => $catalog13Id, 'catalog_code' => '13', 'item_code' => '9',
            'description' => 'OTROS', 'status' => 'ACTIVE',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    return [
        'sunat_existence_type_item_id' => (int) $type05Id,
        'sunat_inventory_catalog_item_id' => (int) $other13Id,
    ];
}

function testSunatInventoryArticleFields(string $code): array
{
    return array_merge(testSunatInventoryMasterIds(), [
        'sunat_inventory_catalog_code' => $code,
        'sales_tax_affectation_code' => '10',
        'is_taxable' => true,
    ]);
}
