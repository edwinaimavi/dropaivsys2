<?php

use App\Models\Company;
use App\Models\User;
use App\Models\WorkAgendaItem;
use App\Models\WorkAgendaItemAssignment;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['agenda_trabajo.ver', 'agenda_trabajo.crear', 'agenda_trabajo.editar', 'agenda_trabajo.cambiar_estado',
        WorkAgendaItem::PERMISSION_VIEW_ALL, WorkAgendaItem::PERMISSION_ASSIGN, WorkAgendaItem::PERMISSION_DERIVE] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

function b3Company(string $name): Company
{
    return Company::create(['business_name' => "Empresa B3 $name", 'ruc' => fake()->unique()->numerify('###########'), 'status' => true]);
}

function b3User(array $permissions = []): User
{
    $user = User::factory()->create();
    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function b3Payload(Company $company, array $responsibles, array $overrides = []): array
{
    return array_merge(['company_id' => $company->id, 'responsible_user_ids' => $responsibles, 'title' => 'Actividad B3',
        'activity_date' => '2026-09-08', 'is_all_day' => false, 'starts_at' => '10:00', 'ends_at' => '11:00',
        'priority' => 'normal', 'status' => 'pending'], $overrides);
}

function b3Item(Company $company, User $creator, array $users, array $overrides = []): WorkAgendaItem
{
    $item = WorkAgendaItem::create(array_merge(['company_id' => $company->id, 'created_by_user_id' => $creator->id,
        'responsible_user_id' => $users[0]->id, 'title' => 'Actividad B3 directa', 'activity_date' => '2026-09-08',
        'starts_at' => '2026-09-08 10:00:00', 'ends_at' => '2026-09-08 11:00:00', 'priority' => 'normal', 'status' => 'pending'], $overrides));
    foreach ($users as $user) {
        $item->assignments()->create(['user_id' => $user->id, 'assigned_by_user_id' => $creator->id, 'status' => 'pending', 'assigned_at' => now()]);
    }

    return $item;
}

test('administrador asigna dos usuarios de la empresa y la actividad no se duplica', function () {
    $company = b3Company('Múltiple');
    $manager = b3User(['agenda_trabajo.crear', WorkAgendaItem::PERMISSION_ASSIGN, WorkAgendaItem::PERMISSION_VIEW_ALL]);
    $a = b3User();
    $b = b3User();
    foreach ([$manager, $a, $b] as $user) {
        $user->companies()->attach($company);
    }

    $this->actingAs($manager)->postJson(route('admin.work-agenda.store'), b3Payload($company, [$a->id, $b->id]))->assertCreated();
    $this->assertDatabaseCount('work_agenda_items', 1)->assertDatabaseCount('work_agenda_item_assignments', 2);
    expect(WorkAgendaItem::sole()->responsible_user_id)->toBe($a->id);
});

test('usuario normal queda asignado a si mismo y no puede forzar otro responsable', function () {
    $company = b3Company('Propia');
    $user = b3User(['agenda_trabajo.crear']);
    $other = b3User();
    $user->companies()->attach($company);
    $other->companies()->attach($company);
    $this->actingAs($user)->postJson(route('admin.work-agenda.store'), b3Payload($company, [$other->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('responsible_user_ids.0');
    $this->postJson(route('admin.work-agenda.store'), b3Payload($company, [$user->id]))->assertCreated();
    expect(WorkAgendaItemAssignment::sole()->user_id)->toBe($user->id);
});

test('asignacion inicial entre empresas se bloquea', function () {
    $a = b3Company('A');
    $b = b3Company('B');
    $manager = b3User(['agenda_trabajo.crear', WorkAgendaItem::PERMISSION_ASSIGN]);
    $foreign = b3User();
    $manager->companies()->attach($a);
    $foreign->companies()->attach($b);
    $this->actingAs($manager)->postJson(route('admin.work-agenda.store'), b3Payload($a, [$foreign->id]))
        ->assertUnprocessable()->assertJsonValidationErrors('responsible_user_ids.0');
});

test('visibleTo reconoce asignacion y calendario devuelve un solo evento', function () {
    $company = b3Company('Visible');
    $creator = b3User();
    $viewer = b3User(['agenda_trabajo.ver']);
    $creator->companies()->attach($company);
    $viewer->companies()->attach($company);
    $item = b3Item($company, $creator, [$creator, $viewer]);
    expect(WorkAgendaItem::visibleTo($viewer)->pluck('id')->all())->toBe([$item->id]);
    $this->actingAs($viewer)->getJson(route('admin.work-agenda.calendar', ['start' => '2026-09-01', 'end' => '2026-10-01']))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.responsible', fn ($value) => str_contains($value, '+1'));
});

test('cada responsable cambia solo su asignacion y el item concluye cuando todos concluyen', function () {
    $company = b3Company('Estados');
    $a = b3User(['agenda_trabajo.ver', 'agenda_trabajo.cambiar_estado']);
    $b = b3User(['agenda_trabajo.ver', 'agenda_trabajo.cambiar_estado']);
    $a->companies()->attach($company);
    $b->companies()->attach($company);
    $item = b3Item($company, $a, [$a, $b]);
    $aa = $item->assignments()->where('user_id', $a->id)->first();
    $ab = $item->assignments()->where('user_id', $b->id)->first();
    $this->actingAs($a)->patchJson(route('admin.work-agenda.assignments.status.update', [$item, $aa]), ['status' => 'in_progress'])->assertOk();
    expect($aa->fresh()->status)->toBe('in_progress')->and($ab->fresh()->status)->toBe('pending')->and($item->fresh()->status)->toBe('in_progress');
    $this->patchJson(route('admin.work-agenda.assignments.status.update', [$item, $aa]), ['status' => 'completed'])->assertOk();
    expect($item->fresh()->status)->not->toBe('completed');
    $this->actingAs($b)->patchJson(route('admin.work-agenda.assignments.status.update', [$item, $ab]), ['status' => 'completed'])->assertOk();
    expect($item->fresh()->status)->toBe('completed')->and($item->fresh()->completed_at)->not->toBeNull();
});

test('derivacion propia exige permiso y motivo y conserva origen historico', function () {
    $company = b3Company('Derivar');
    $source = b3User(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_DERIVE]);
    $target = b3User();
    $source->companies()->attach($company);
    $target->companies()->attach($company);
    $item = b3Item($company, $source, [$source]);
    $assignment = $item->assignments()->sole();
    $url = route('admin.work-agenda.assignments.derive', [$item, $assignment]);
    $this->actingAs($source)->postJson($url, ['to_user_id' => $target->id])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->postJson($url, ['to_user_id' => $target->id, 'reason' => 'Visita técnica'])->assertOk();
    expect($assignment->fresh()->status)->toBe('derived')
        ->and($item->assignments()->where('user_id', $target->id)->where('status', 'pending')->exists())->toBeTrue();
    $this->assertDatabaseHas('work_agenda_item_derivations', ['from_user_id' => $source->id, 'to_user_id' => $target->id, 'reason' => 'Visita técnica']);
    $this->postJson($url, ['to_user_id' => $target->id, 'reason' => 'Repetir'])->assertUnprocessable();
});

test('derivar una asignacion no altera las otras y bloquea destino de otra empresa', function () {
    $company = b3Company('Equipo');
    $foreignCompany = b3Company('Ajena');
    $a = b3User(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_DERIVE]);
    $b = b3User();
    $target = b3User();
    $foreign = b3User();
    foreach ([$a, $b, $target] as $user) {
        $user->companies()->attach($company);
    } $foreign->companies()->attach($foreignCompany);
    $item = b3Item($company, $a, [$a, $b]);
    $assignment = $item->assignments()->where('user_id', $a->id)->first();
    $url = route('admin.work-agenda.assignments.derive', [$item, $assignment]);
    $this->actingAs($a)->postJson($url, ['to_user_id' => $foreign->id, 'reason' => 'No corresponde'])->assertUnprocessable();
    $this->postJson($url, ['to_user_id' => $target->id, 'reason' => 'Cambio válido'])->assertOk();
    expect($item->assignments()->where('user_id', $b->id)->sole()->status)->toBe('pending');
});

test('usuario sin permiso no deriva y supervisor autorizado gestiona asignacion ajena', function () {
    $company = b3Company('Supervisión');
    $owner = b3User(['agenda_trabajo.ver']);
    $supervisor = b3User(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_DERIVE, WorkAgendaItem::PERMISSION_VIEW_ALL]);
    $target = b3User();
    foreach ([$owner, $supervisor, $target] as $u) {
        $u->companies()->attach($company);
    }
    $item = b3Item($company, $owner, [$owner]);
    $assignment = $item->assignments()->sole();
    $url = route('admin.work-agenda.assignments.derive', [$item, $assignment]);
    $this->actingAs($owner)->postJson($url, ['to_user_id' => $target->id, 'reason' => 'Sin permiso'])->assertForbidden();
    $this->actingAs($supervisor)->postJson($url, ['to_user_id' => $target->id, 'reason' => 'Supervisión'])->assertOk();
});

test('compatibilidad B1 crea assignment focalizado sin perder actividad historica', function () {
    $company = b3Company('Histórica');
    $user = b3User(['agenda_trabajo.ver', 'agenda_trabajo.cambiar_estado']);
    $user->companies()->attach($company);
    $item = WorkAgendaItem::create(['company_id' => $company->id, 'created_by_user_id' => $user->id, 'responsible_user_id' => $user->id,
        'title' => 'Reunión de seguimiento ESSALUD', 'activity_date' => '2026-09-08']);
    $this->actingAs($user)->patchJson(route('admin.work-agenda.status.update', $item), ['status' => 'in_progress'])->assertOk();
    expect($item->fresh()->title)->toBe('Reunión de seguimiento ESSALUD')->and($item->assignments()->count())->toBe(1);
});

test('my alerts solo entrega assignments activos propios por reminder urgente vencida o proxima', function () {
    Carbon::setTestNow('2026-09-08 09:00:00');
    $company = b3Company('Alertas');
    $me = b3User();
    $other = b3User();
    $me->companies()->attach($company);
    $other->companies()->attach($company);
    b3Item($company, $me, [$me], ['title' => 'Recordatorio', 'reminder_at' => '2026-09-08 08:00:00']);
    b3Item($company, $me, [$me], ['title' => 'Urgente hoy', 'priority' => 'urgent']);
    b3Item($company, $me, [$me], ['title' => 'Vencida', 'activity_date' => '2026-09-07', 'starts_at' => '2026-09-07 10:00:00', 'ends_at' => '2026-09-07 11:00:00']);
    b3Item($company, $other, [$other], ['title' => 'De otro']);
    $completed = b3Item($company, $me, [$me], ['title' => 'Concluida']);
    $completed->assignments()->update(['status' => 'completed']);
    $derived = b3Item($company, $me, [$me], ['title' => 'Derivada']);
    $derived->assignments()->update(['status' => 'derived']);
    $cancelled = b3Item($company, $me, [$me], ['title' => 'Cancelada']);
    $cancelled->assignments()->update(['status' => 'cancelled']);
    $response = $this->actingAs($me)->getJson(route('admin.work-agenda.my-alerts'))->assertOk()->assertJsonPath('count', 3);
    $titles = collect($response->json('alerts'))->pluck('title');
    expect($titles)->toContain('Recordatorio', 'Urgente hoy', 'Vencida')->not->toContain('De otro', 'Concluida', 'Derivada', 'Cancelada');
    Carbon::setTestNow();
});

test('despues de derivar la alerta pasa del origen al destino', function () {
    Carbon::setTestNow('2026-09-08 09:00:00');
    $company = b3Company('Transferencia alerta');
    $from = b3User(['agenda_trabajo.ver', WorkAgendaItem::PERMISSION_DERIVE]);
    $to = b3User();
    $from->companies()->attach($company);
    $to->companies()->attach($company);
    $item = b3Item($company, $from, [$from], ['title' => 'Alerta transferida', 'priority' => 'urgent']);
    $assignment = $item->assignments()->sole();
    $this->actingAs($from)->postJson(route('admin.work-agenda.assignments.derive', [$item, $assignment]), ['to_user_id' => $to->id, 'reason' => 'Delegación'])->assertOk();
    $this->actingAs($from)->getJson(route('admin.work-agenda.my-alerts'))->assertJsonPath('count', 0);
    $this->actingAs($to)->getJson(route('admin.work-agenda.my-alerts'))->assertJsonPath('count', 1);
    Carbon::setTestNow();
});
