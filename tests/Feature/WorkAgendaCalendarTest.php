<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WorkAgendaItem;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_VIEW_ALL] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

function workCalendarCompany(string $suffix): Company
{
    return Company::create([
        'business_name' => "Empresa Calendario {$suffix}",
        'ruc' => str_pad((string) fake()->unique()->numberBetween(1, 99999999999), 11, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
}

function workCalendarUser(array $permissions = []): User
{
    $user = User::factory()->create();

    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function workCalendarItem(
    Company $company,
    User $creator,
    User $responsible,
    array $overrides = []
): WorkAgendaItem {
    return WorkAgendaItem::create(array_merge([
        'company_id' => $company->id,
        'created_by_user_id' => $creator->id,
        'responsible_user_id' => $responsible->id,
        'title' => 'Actividad del calendario',
        'activity_date' => '2026-09-10',
        'starts_at' => '2026-09-10 09:00:00',
        'ends_at' => '2026-09-10 10:00:00',
        'is_all_day' => false,
        'priority' => WorkAgendaItem::PRIORITY_NORMAL,
        'status' => WorkAgendaItem::STATUS_PENDING,
    ], $overrides));
}

function workCalendarUrl(array $parameters = []): string
{
    return route('admin.work-agenda.calendar', array_merge([
        'start' => '2026-09-01T00:00:00-05:00',
        'end' => '2026-10-01T00:00:00-05:00',
    ], $parameters));
}

test('el rango del calendario devuelve una actividad visible', function () {
    $company = workCalendarCompany('Visible');
    $user = workCalendarUser(['agenda_trabajo.ver']);
    $user->companies()->attach($company);
    $item = workCalendarItem($company, $user, $user, ['title' => 'Reunión visible']);

    $this->actingAs($user)
        ->getJson(workCalendarUrl())
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', (string) $item->id)
        ->assertJsonPath('0.title', 'Reunión visible');
});

test('el endpoint del calendario exige permiso de visualizacion', function () {
    $user = workCalendarUser();

    $this->actingAs($user)
        ->getJson(workCalendarUrl())
        ->assertForbidden();
});

test('una actividad fuera del rango no se devuelve', function () {
    $company = workCalendarCompany('Fuera de rango');
    $user = workCalendarUser(['agenda_trabajo.ver']);
    $user->companies()->attach($company);
    workCalendarItem($company, $user, $user, [
        'activity_date' => '2026-10-01',
        'starts_at' => '2026-10-01 09:00:00',
        'ends_at' => '2026-10-01 10:00:00',
    ]);

    $this->actingAs($user)
        ->getJson(workCalendarUrl())
        ->assertOk()
        ->assertJsonCount(0);
});

test('un usuario normal no recibe una actividad ajena', function () {
    $company = workCalendarCompany('Usuario normal');
    $viewer = workCalendarUser(['agenda_trabajo.ver']);
    $colleague = workCalendarUser();
    $viewer->companies()->attach($company);
    $colleague->companies()->attach($company);
    workCalendarItem($company, $colleague, $colleague);

    $this->actingAs($viewer)
        ->getJson(workCalendarUrl())
        ->assertOk()
        ->assertJsonCount(0);
});

test('ver todos recibe la actividad de otro usuario en una empresa autorizada', function () {
    $company = workCalendarCompany('Autorizada');
    $viewer = workCalendarUser(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_VIEW_ALL]);
    $colleague = workCalendarUser();
    $viewer->companies()->attach($company);
    $colleague->companies()->attach($company);
    $item = workCalendarItem($company, $colleague, $colleague);

    $this->actingAs($viewer)
        ->getJson(workCalendarUrl())
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', (string) $item->id);
});

test('ver todos no recibe actividades de una empresa no autorizada', function () {
    $authorized = workCalendarCompany('Propia');
    $foreign = workCalendarCompany('Ajena');
    $viewer = workCalendarUser(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_VIEW_ALL]);
    $colleague = workCalendarUser();
    $viewer->companies()->attach($authorized);
    $colleague->companies()->attach($foreign);
    workCalendarItem($foreign, $colleague, $colleague);

    $this->actingAs($viewer)
        ->getJson(workCalendarUrl())
        ->assertOk()
        ->assertJsonCount(0);
});

test('un filtro de empresa manipulado no atraviesa company user', function () {
    $authorized = workCalendarCompany('Filtro propio');
    $foreign = workCalendarCompany('Filtro ajeno');
    $viewer = workCalendarUser(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_VIEW_ALL]);
    $colleague = workCalendarUser();
    $viewer->companies()->attach($authorized);
    $colleague->companies()->attach($foreign);
    workCalendarItem($authorized, $viewer, $viewer);
    workCalendarItem($foreign, $colleague, $colleague);

    $this->actingAs($viewer)
        ->getJson(workCalendarUrl(['company_id' => $foreign->id]))
        ->assertOk()
        ->assertJsonCount(0);
});

test('una actividad de todo el dia se serializa sin horas artificiales', function () {
    $company = workCalendarCompany('Todo el día');
    $user = workCalendarUser(['agenda_trabajo.ver']);
    $user->companies()->attach($company);
    workCalendarItem($company, $user, $user, [
        'is_all_day' => true,
        'starts_at' => null,
        'ends_at' => null,
    ]);

    $this->actingAs($user)
        ->getJson(workCalendarUrl())
        ->assertOk()
        ->assertJsonPath('0.allDay', true)
        ->assertJsonPath('0.start', '2026-09-10')
        ->assertJsonPath('0.end', null);
});

test('las horas se conservan como fecha y hora local esperadas', function () {
    $company = workCalendarCompany('Horario local');
    $user = workCalendarUser(['agenda_trabajo.ver']);
    $user->companies()->attach($company);
    workCalendarItem($company, $user, $user, [
        'starts_at' => '2026-09-10 15:00:00',
        'ends_at' => '2026-09-10 16:30:00',
    ]);

    $this->actingAs($user)
        ->getJson(workCalendarUrl())
        ->assertOk()
        ->assertJsonPath('0.allDay', false)
        ->assertJsonPath('0.start', '2026-09-10T15:00:00')
        ->assertJsonPath('0.end', '2026-09-10T16:30:00');
});
