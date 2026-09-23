<?php

use App\Models\SunatCatalog;
use App\Models\SunatCatalogItem;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

function sunatUserWithPermissions(array $permissions): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user->givePermissionTo($permissions);

    return $user;
}

it('crea un catálogo global conservando los ceros a la izquierda y la trazabilidad', function () {
    $user = sunatUserWithPermissions(['catalogos_sunat.crear']);

    $response = $this->actingAs($user)->postJson(route('admin.sunat-catalogs.catalogs.store'), [
        'code' => '01',
        'name' => 'Tipo de medio de pago',
        'description' => 'Tabla de prueba',
    ]);

    $response->assertCreated()->assertJsonPath('data.code', '01');
    $catalog = SunatCatalog::firstOrFail();

    expect($catalog->code)->toBe('01')
        ->and($catalog->name)->toBe('TIPO DE MEDIO DE PAGO')
        ->and($catalog->source)->toBe('manual')
        ->and($catalog->created_by_user_id)->toBe($user->id)
        ->and(Schema::hasColumn('sunat_catalogs', 'company_id'))->toBeFalse();
});

it('crea códigos numéricos o alfanuméricos y los relaciona con su catálogo', function () {
    $user = sunatUserWithPermissions(['catalogos_sunat.crear']);
    $catalog = SunatCatalog::create(['code' => '06', 'name' => 'UNIDADES', 'source' => 'sunat_pdf']);

    $this->actingAs($user)->postJson(route('admin.sunat-catalogs.items.store', $catalog), [
        'item_code' => '001',
        'description' => 'Unidad con cero',
    ])->assertCreated()->assertJsonPath('data.item_code', '001');

    $this->actingAs($user)->postJson(route('admin.sunat-catalogs.items.store', $catalog), [
        'item_code' => 'NIU',
        'description' => 'Unidad bienes',
        'extra_data' => ['referencia' => 'prueba'],
    ])->assertCreated();

    $item = SunatCatalogItem::where('item_code', 'NIU')->firstOrFail();
    expect($item->catalog->is($catalog))->toBeTrue()
        ->and($item->catalog_code)->toBe('06')
        ->and($item->is_official)->toBeFalse()
        ->and($item->source)->toBe('manual')
        ->and($item->extra_data)->toBe(['referencia' => 'prueba']);
});

it('permite el mismo item_code en catálogos distintos pero no lo duplica dentro del mismo', function () {
    $user = sunatUserWithPermissions(['catalogos_sunat.crear']);
    $first = SunatCatalog::create(['code' => '01', 'name' => 'PRIMERO']);
    $second = SunatCatalog::create(['code' => '02', 'name' => 'SEGUNDO']);
    $payload = ['item_code' => '01', 'description' => 'CÓDIGO COMPARTIDO'];

    $this->actingAs($user)->postJson(route('admin.sunat-catalogs.items.store', $first), $payload)->assertCreated();
    $this->actingAs($user)->postJson(route('admin.sunat-catalogs.items.store', $second), $payload)->assertCreated();
    $this->actingAs($user)->postJson(route('admin.sunat-catalogs.items.store', $first), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('item_code');

    expect(SunatCatalogItem::where('item_code', '01')->count())->toBe(2);
});

it('edita y cambia estado sin eliminar físicamente catálogos ni códigos', function () {
    $user = sunatUserWithPermissions(['catalogos_sunat.editar', 'catalogos_sunat.cambiar_estado']);
    $catalog = SunatCatalog::create(['code' => '12', 'name' => 'OPERACIONES']);
    $item = $catalog->items()->create([
        'catalog_code' => '12', 'item_code' => '24', 'description' => 'ORIGINAL', 'status' => 'ACTIVE',
    ]);

    $this->actingAs($user)->putJson(route('admin.sunat-catalogs.catalogs.update', $catalog), [
        'code' => '012', 'name' => 'Operaciones actualizadas',
    ])->assertOk();
    $this->actingAs($user)->putJson(route('admin.sunat-catalogs.items.update', [$catalog, $item]), [
        'item_code' => '024', 'description' => 'Entrada actualizada',
    ])->assertOk();
    $this->actingAs($user)->patchJson(route('admin.sunat-catalogs.catalogs.status', $catalog), ['is_active' => false])->assertOk();
    $this->actingAs($user)->patchJson(route('admin.sunat-catalogs.items.status', [$catalog, $item]), ['is_active' => false])->assertOk();

    expect($catalog->fresh()->code)->toBe('012')
        ->and($catalog->fresh()->is_active)->toBeFalse()
        ->and($item->fresh()->catalog_code)->toBe('012')
        ->and($item->fresh()->item_code)->toBe('024')
        ->and($item->fresh()->status)->toBe('INACTIVE')
        ->and(SunatCatalog::count())->toBe(1)
        ->and(SunatCatalogItem::count())->toBe(1)
        ->and(Route::has('admin.sunat-catalogs.destroy'))->toBeFalse()
        ->and(Route::has('admin.sunat-catalogs.items.destroy'))->toBeFalse();
});

it('protege cada endpoint con el permiso específico y rechaza items de otro catálogo', function () {
    $catalog = SunatCatalog::create(['code' => '01', 'name' => 'PRIMERO']);
    $other = SunatCatalog::create(['code' => '02', 'name' => 'SEGUNDO']);
    $item = $other->items()->create([
        'catalog_code' => '02', 'item_code' => '01', 'description' => 'AJENO', 'status' => 'ACTIVE',
    ]);
    $withoutPermissions = User::factory()->create();

    $this->actingAs($withoutPermissions)->getJson(route('admin.sunat-catalogs.catalogs'))->assertForbidden();
    $this->actingAs($withoutPermissions)->postJson(route('admin.sunat-catalogs.catalogs.store'), [])->assertForbidden();
    $this->actingAs($withoutPermissions)->putJson(route('admin.sunat-catalogs.catalogs.update', $catalog), [])->assertForbidden();
    $this->actingAs($withoutPermissions)->patchJson(route('admin.sunat-catalogs.catalogs.status', $catalog), [])->assertForbidden();

    $editor = sunatUserWithPermissions(['catalogos_sunat.editar']);
    $this->actingAs($editor)->putJson(route('admin.sunat-catalogs.items.update', [$catalog, $item]), [
        'item_code' => '01', 'description' => 'INTENTO',
    ])->assertNotFound();
});

it('lista únicamente una página de códigos mediante procesamiento en servidor', function () {
    $user = sunatUserWithPermissions(['catalogos_sunat.ver']);
    $catalog = SunatCatalog::create(['code' => '35', 'name' => 'PAÍSES']);
    foreach (range(1, 60) as $number) {
        $catalog->items()->create([
            'catalog_code' => '35',
            'item_code' => str_pad((string) $number, 3, '0', STR_PAD_LEFT),
            'description' => "PAÍS {$number}",
            'status' => 'ACTIVE',
        ]);
    }

    $this->actingAs($user)->get(route('admin.sunat-catalogs.index'))
        ->assertOk()
        ->assertDontSee('PAÍS 60');

    $response = $this->actingAs($user)->getJson(route('admin.sunat-catalogs.items', [
        'sunatCatalog' => $catalog,
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'columns' => [
            ['data' => 'item_code', 'name' => 'item_code', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
        ],
        'order' => [['column' => 0, 'dir' => 'asc']],
        'search' => ['value' => '', 'regex' => 'false'],
    ]));

    $response->assertOk()->assertJsonPath('recordsTotal', 60);
    expect($response->json('data'))->toHaveCount(10);
});
