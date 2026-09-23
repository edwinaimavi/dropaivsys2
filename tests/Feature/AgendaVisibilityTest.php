<?php

use App\Models\AssistanceNote;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkAgendaItem;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate(WorkAgendaItem::PERMISSION_VIEW_ALL, 'web');
    Permission::findOrCreate(AssistanceNote::PERMISSION_VIEW_ALL, 'web');
});

function agendaCompany(string $suffix): Company
{
    return Company::create([
        'business_name' => "Empresa Agenda {$suffix}",
        'ruc' => str_pad((string) fake()->unique()->numberBetween(1, 99999999999), 11, '0', STR_PAD_LEFT),
        'status' => true,
    ]);
}

function agendaUser(): User
{
    return User::factory()->create();
}

function authorizeAgendaCompanies(User $user, Company ...$companies): void
{
    $user->companies()->syncWithoutDetaching(
        collect($companies)->pluck('id')->all()
    );
}

function agendaWorkItem(Company $company, User $creator, User $responsible, string $title): WorkAgendaItem
{
    return WorkAgendaItem::create([
        'company_id' => $company->id,
        'created_by_user_id' => $creator->id,
        'responsible_user_id' => $responsible->id,
        'title' => $title,
        'activity_date' => '2026-09-07',
    ]);
}

function agendaNote(Company $company, User $creator, User $responsible, string $title): AssistanceNote
{
    return AssistanceNote::create([
        'company_id' => $company->id,
        'created_by_user_id' => $creator->id,
        'responsible_user_id' => $responsible->id,
        'title' => $title,
        'content' => "Contenido de {$title}",
    ]);
}

test('un usuario normal ve sus actividades en varias empresas autorizadas pero no las ajenas o no autorizadas', function () {
    $companyA = agendaCompany('A');
    $companyB = agendaCompany('B');
    $companyC = agendaCompany('C');
    $viewer = agendaUser();
    $colleague = agendaUser();

    authorizeAgendaCompanies($viewer, $companyA, $companyB);
    authorizeAgendaCompanies($colleague, $companyA, $companyB, $companyC);

    expect($viewer->belongsToCompany($companyA->id))->toBeTrue()
        ->and($viewer->belongsToCompany($companyB->id))->toBeTrue()
        ->and($viewer->belongsToCompany($companyC->id))->toBeFalse();

    $ownA = agendaWorkItem($companyA, $viewer, $viewer, 'Actividad propia A');
    $ownB = agendaWorkItem($companyB, $viewer, $colleague, 'Actividad propia B');
    $responsibleB = agendaWorkItem($companyB, $colleague, $viewer, 'Actividad asignada B');
    agendaWorkItem($companyA, $colleague, $colleague, 'Actividad ajena A');
    agendaWorkItem($companyC, $viewer, $viewer, 'Actividad propia C no autorizada');

    expect(WorkAgendaItem::visibleTo($viewer)->pluck('id')->all())
        ->toEqualCanonicalizing([$ownA->id, $ownB->id, $responsibleB->id]);
});

test('un usuario normal ve notas propias y compartidas pero no notas privadas ajenas', function () {
    $companyA = agendaCompany('Notas A');
    $companyB = agendaCompany('Notas B');
    $viewer = agendaUser();
    $colleague = agendaUser();

    authorizeAgendaCompanies($viewer, $companyA);
    authorizeAgendaCompanies($colleague, $companyA, $companyB);

    $ownNote = agendaNote($companyA, $viewer, $colleague, 'Nota propia');
    $sharedNote = agendaNote($companyA, $colleague, $colleague, 'Nota compartida');
    agendaNote($companyA, $colleague, $colleague, 'Nota privada ajena');
    agendaNote($companyB, $viewer, $viewer, 'Nota propia no autorizada');

    $sharedNote->shares()->create([
        'company_id' => $companyA->id,
        'user_id' => $viewer->id,
        'shared_by_user_id' => $colleague->id,
    ]);
    $sharedNote->update(['visibility' => AssistanceNote::VISIBILITY_SHARED]);

    expect(AssistanceNote::visibleTo($viewer)->pluck('id')->all())
        ->toEqualCanonicalizing([$ownNote->id, $sharedNote->id]);
});

test('ver todos respeta todas las empresas autorizadas y nunca una empresa fuera del pivot', function () {
    $companyA = agendaCompany('Total A');
    $companyB = agendaCompany('Total B');
    $companyC = agendaCompany('Total C');
    $viewer = agendaUser();
    $colleague = agendaUser();

    authorizeAgendaCompanies($viewer, $companyA, $companyB);
    authorizeAgendaCompanies($colleague, $companyA, $companyB, $companyC);
    $viewer->givePermissionTo([
        WorkAgendaItem::PERMISSION_VIEW_ALL,
        AssistanceNote::PERMISSION_VIEW_ALL,
    ]);

    $workA = agendaWorkItem($companyA, $colleague, $colleague, 'Actividad total A');
    $workB = agendaWorkItem($companyB, $colleague, $colleague, 'Actividad total B');
    agendaWorkItem($companyC, $colleague, $colleague, 'Actividad total C');
    $noteA = agendaNote($companyA, $colleague, $colleague, 'Nota total A');
    $noteB = agendaNote($companyB, $colleague, $colleague, 'Nota total B');
    agendaNote($companyC, $colleague, $colleague, 'Nota total C');

    expect(WorkAgendaItem::visibleTo($viewer)->pluck('id')->all())
        ->toEqualCanonicalizing([$workA->id, $workB->id])
        ->and(AssistanceNote::visibleTo($viewer)->pluck('id')->all())
        ->toEqualCanonicalizing([$noteA->id, $noteB->id]);
});

test('un usuario sin membresias no ve actividades ni notas', function () {
    $company = agendaCompany('Sin membresia');
    $viewer = agendaUser();
    $colleague = agendaUser();

    authorizeAgendaCompanies($colleague, $company);
    agendaWorkItem($company, $colleague, $colleague, 'Actividad invisible');
    agendaNote($company, $colleague, $colleague, 'Nota invisible');

    expect(WorkAgendaItem::visibleTo($viewer)->count())->toBe(0)
        ->and(AssistanceNote::visibleTo($viewer)->count())->toBe(0);
});

test('retirar una membresia revoca acceso sin eliminar historicos', function () {
    $company = agendaCompany('Historicos');
    $viewer = agendaUser();

    authorizeAgendaCompanies($viewer, $company);
    $workItem = agendaWorkItem($company, $viewer, $viewer, 'Actividad historica');
    $note = agendaNote($company, $viewer, $viewer, 'Nota historica');

    $viewer->companies()->detach($company->id);

    expect($workItem->fresh())->not->toBeNull()
        ->and($note->fresh())->not->toBeNull()
        ->and(WorkAgendaItem::visibleTo($viewer)->count())->toBe(0)
        ->and(AssistanceNote::visibleTo($viewer)->count())->toBe(0);
});
