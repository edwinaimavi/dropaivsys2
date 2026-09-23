<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WorkAgendaItem;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach ([
        'agenda_trabajo.ver',
        'agenda_trabajo.crear',
        'agenda_trabajo.editar',
        'agenda_trabajo.eliminar',
        'agenda_trabajo.cambiar_estado',
        WorkAgendaItem::PERMISSION_VIEW_ALL,
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

function workCrudCompany(string $suffix): Company
{
    return Company::create([
        'business_name' => "Empresa Trabajo {$suffix}",
        'ruc' => str_pad((string) fake()->unique()->numberBetween(1, 99999999999), 11, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
}

function workCrudUser(array $permissions = []): User
{
    $user = User::factory()->create();
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function workCrudPayload(Company $company, User $responsible, array $overrides = []): array
{
    return array_merge([
        'company_id' => $company->id,
        'responsible_user_id' => $responsible->id,
        'title' => 'Reunión de seguimiento',
        'description' => 'Revisar los pendientes del abastecimiento.',
        'activity_date' => '2026-09-10',
        'is_all_day' => false,
        'starts_at' => '09:00',
        'ends_at' => '10:00',
        'activity_type' => 'Reunión',
        'priority' => WorkAgendaItem::PRIORITY_NORMAL,
        'status' => WorkAgendaItem::STATUS_PENDING,
        'location' => 'Oficina principal',
        'reminder_at' => '2026-09-10T08:30',
    ], $overrides);
}

function workCrudItem(Company $company, User $creator, User $responsible, array $overrides = []): WorkAgendaItem
{
    return WorkAgendaItem::create(array_merge([
        'company_id' => $company->id,
        'created_by_user_id' => $creator->id,
        'responsible_user_id' => $responsible->id,
        'title' => 'Actividad de prueba',
        'activity_date' => '2026-09-10',
        'starts_at' => '2026-09-10 09:00:00',
        'ends_at' => '2026-09-10 10:00:00',
        'priority' => WorkAgendaItem::PRIORITY_NORMAL,
        'status' => WorkAgendaItem::STATUS_PENDING,
    ], $overrides));
}

test('un usuario normal ve su actividad pero no una actividad ajena', function () {
    $company = workCrudCompany('Visibilidad');
    $viewer = workCrudUser(['agenda_trabajo.ver']);
    $colleague = workCrudUser();
    $viewer->companies()->attach($company);
    $colleague->companies()->attach($company);

    $own = workCrudItem($company, $colleague, $viewer, ['title' => 'Actividad propia']);
    workCrudItem($company, $colleague, $colleague, ['title' => 'Actividad ajena']);

    $this->actingAs($viewer)
        ->getJson(route('admin.work-agenda.data'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.activity', fn (string $html) => str_contains($html, $own->title));
});

test('un usuario normal no puede crear para otro responsable', function () {
    $company = workCrudCompany('Responsable normal');
    $user = workCrudUser(['agenda_trabajo.crear']);
    $other = workCrudUser();
    $user->companies()->attach($company);
    $other->companies()->attach($company);

    $this->actingAs($user)
        ->postJson(route('admin.work-agenda.store'), workCrudPayload($company, $other))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('responsible_user_id');

    $this->assertDatabaseCount('work_agenda_items', 0);
});

test('un usuario normal no puede modificar eliminar ni cambiar el estado de una actividad ajena', function () {
    $company = workCrudCompany('Protección');
    $user = workCrudUser([
        'agenda_trabajo.editar',
        'agenda_trabajo.eliminar',
        'agenda_trabajo.cambiar_estado',
    ]);
    $owner = workCrudUser();
    $user->companies()->attach($company);
    $owner->companies()->attach($company);
    $item = workCrudItem($company, $owner, $owner);

    $this->actingAs($user)
        ->putJson(route('admin.work-agenda.update', $item), workCrudPayload($company, $user))
        ->assertNotFound();

    $this->patchJson(route('admin.work-agenda.status.update', $item), ['status' => WorkAgendaItem::STATUS_COMPLETED])
        ->assertNotFound();

    $this->deleteJson(route('admin.work-agenda.destroy', $item))->assertNotFound();

    expect($item->fresh())->not->toBeNull();
});

test('un usuario normal no puede forzar una empresa no autorizada', function () {
    $authorized = workCrudCompany('Autorizada');
    $unauthorized = workCrudCompany('No autorizada');
    $user = workCrudUser(['agenda_trabajo.crear']);
    $user->companies()->attach($authorized);

    $this->actingAs($user)
        ->postJson(route('admin.work-agenda.store'), workCrudPayload($unauthorized, $user))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('company_id');
});

test('ver todos lista responsables de empresas autorizadas y no atraviesa el pivot', function () {
    $companyA = workCrudCompany('A');
    $companyB = workCrudCompany('B');
    $viewer = workCrudUser(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_VIEW_ALL]);
    $memberA = workCrudUser();
    $memberB = workCrudUser();
    $viewer->companies()->attach($companyA);
    $memberA->companies()->attach($companyA);
    $memberB->companies()->attach($companyB);

    $this->actingAs($viewer)
        ->getJson(route('admin.work-agenda.responsibles', $companyA))
        ->assertOk()
        ->assertJsonFragment(['id' => $memberA->id])
        ->assertJsonMissing(['id' => $memberB->id]);

    $this->getJson(route('admin.work-agenda.responsibles', $companyB))->assertNotFound();

    $foreignItem = workCrudItem($companyB, $memberB, $memberB);
    $this->getJson(route('admin.work-agenda.show', $foreignItem))->assertNotFound();
});

test('un responsable debe pertenecer a la empresa seleccionada', function () {
    $companyA = workCrudCompany('Asignación A');
    $companyB = workCrudCompany('Asignación B');
    $manager = workCrudUser(['agenda_trabajo.crear', WorkAgendaItem::PERMISSION_VIEW_ALL]);
    $responsible = workCrudUser();
    $manager->companies()->attach($companyA);
    $responsible->companies()->attach($companyB);

    $this->actingAs($manager)
        ->postJson(route('admin.work-agenda.store'), workCrudPayload($companyA, $responsible))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('responsible_user_id');
});

test('concluir establece completed at y reabrir lo limpia', function () {
    $company = workCrudCompany('Estados');
    $user = workCrudUser(['agenda_trabajo.cambiar_estado']);
    $user->companies()->attach($company);
    $item = workCrudItem($company, $user, $user);

    $this->actingAs($user)
        ->patchJson(route('admin.work-agenda.status.update', $item), ['status' => WorkAgendaItem::STATUS_COMPLETED])
        ->assertOk();

    expect($item->fresh()->completed_at)->not->toBeNull();

    $this->patchJson(route('admin.work-agenda.status.update', $item), ['status' => WorkAgendaItem::STATUS_IN_PROGRESS])
        ->assertOk();

    expect($item->fresh()->completed_at)->toBeNull();
});

test('una actividad de todo el dia permite horas nulas', function () {
    $company = workCrudCompany('Todo el día');
    $user = workCrudUser(['agenda_trabajo.crear']);
    $user->companies()->attach($company);

    $this->actingAs($user)
        ->postJson(route('admin.work-agenda.store'), workCrudPayload($company, $user, [
            'is_all_day' => true,
            'starts_at' => null,
            'ends_at' => null,
        ]))
        ->assertCreated();

    $item = WorkAgendaItem::sole();
    expect($item->is_all_day)->toBeTrue()
        ->and($item->starts_at)->toBeNull()
        ->and($item->ends_at)->toBeNull();
});

test('la hora de fin debe ser posterior a la hora de inicio', function () {
    $company = workCrudCompany('Horario');
    $user = workCrudUser(['agenda_trabajo.crear']);
    $user->companies()->attach($company);

    $this->actingAs($user)
        ->postJson(route('admin.work-agenda.store'), workCrudPayload($company, $user, [
            'starts_at' => '10:00',
            'ends_at' => '09:00',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ends_at');
});

test('un usuario sin empresas obtiene cero resultados', function () {
    $company = workCrudCompany('Sin acceso');
    $viewer = workCrudUser(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_VIEW_ALL]);
    $colleague = workCrudUser();
    $colleague->companies()->attach($company);
    workCrudItem($company, $colleague, $colleague);

    $this->actingAs($viewer)
        ->getJson(route('admin.work-agenda.data'))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
