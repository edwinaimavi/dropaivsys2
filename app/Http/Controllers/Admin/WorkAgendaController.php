<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Agenda\DeriveWorkAgendaAssignmentRequest;
use App\Http\Requests\Admin\Agenda\StoreWorkAgendaItemRequest;
use App\Http\Requests\Admin\Agenda\UpdateWorkAgendaItemRequest;
use App\Http\Requests\Admin\Agenda\UpdateWorkAgendaStatusRequest;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkAgendaItem;
use App\Models\WorkAgendaItemAssignment;
use App\Services\WorkAgendaAlertService;
use App\Services\WorkAgendaAssignmentService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class WorkAgendaController extends Controller
{
    public function __construct(
        private readonly WorkAgendaAssignmentService $assignmentService,
        private readonly WorkAgendaAlertService $alertService,
    ) {
        $this->middleware('can:agenda_trabajo.ver')->only(['index', 'data', 'calendar', 'show', 'responsibles']);
        $this->middleware('can:agenda_trabajo.crear')->only('store');
        $this->middleware('can:agenda_trabajo.editar')->only('update');
        $this->middleware('can:agenda_trabajo.eliminar')->only('destroy');
        $this->middleware('can:agenda_trabajo.cambiar_estado')->only(['updateStatus', 'updateAssignmentStatus']);
        $this->middleware('can:'.WorkAgendaItem::PERMISSION_DERIVE)->only('deriveAssignment');
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $companies = $user->companies()->where('companies.status', true)
            ->orderBy('companies.business_name')->get(['companies.id', 'companies.business_name', 'companies.trade_name']);

        return view('admin.work-agenda.index', [
            'companies' => $companies,
            'defaultCompanyId' => $companies->count() === 1 ? $companies->first()->id : null,
            'canViewAll' => $user->can(WorkAgendaItem::PERMISSION_VIEW_ALL),
            'canAssign' => $user->can(WorkAgendaItem::PERMISSION_ASSIGN),
            'statuses' => WorkAgendaItem::STATUSES,
            'priorities' => WorkAgendaItem::PRIORITIES,
            'activityTypes' => WorkAgendaItem::ACTIVITY_TYPES,
        ]);
    }

    public function data(Request $request)
    {
        $user = $request->user();
        $request->validate([
            'company_id' => ['nullable', 'integer'], 'responsible_user_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(WorkAgendaItem::STATUSES))],
            'priority' => ['nullable', 'string', 'in:'.implode(',', array_keys(WorkAgendaItem::PRIORITIES))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search_term' => ['nullable', 'string', 'max:180'],
        ]);

        $query = WorkAgendaItem::query()->visibleTo($user)->with($this->relations());
        if ($request->filled('company_id')) {
            $query->where('work_agenda_items.company_id', $request->integer('company_id'));
        }
        if ($user->can(WorkAgendaItem::PERMISSION_VIEW_ALL) && $request->filled('responsible_user_id')) {
            $id = $request->integer('responsible_user_id');
            $query->where(function (Builder $q) use ($id) {
                $q->whereHas('activeAssignments', fn (Builder $a) => $a->where('user_id', $id))
                    ->orWhere(fn (Builder $legacy) => $legacy->whereDoesntHave('assignments')->where('responsible_user_id', $id));
            });
        }
        if ($request->filled('status')) {
            $query->where('work_agenda_items.status', $request->string('status'));
        }
        if ($request->filled('priority')) {
            $query->where('work_agenda_items.priority', $request->string('priority'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('activity_date', '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('activity_date', '<=', $request->date('date_to'));
        }

        $search = trim((string) $request->input('search_term'));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%")->orWhere('activity_type', 'like', "%{$search}%")
                    ->orWhereHas('company', fn (Builder $c) => $c->where('business_name', 'like', "%{$search}%")->orWhere('trade_name', 'like', "%{$search}%"))
                    ->orWhereHas('assignments.user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%")->orWhere('lastname', 'like', "%{$search}%"));
            });
        }

        $canEdit = $user->can('agenda_trabajo.editar');
        $canDelete = $user->can('agenda_trabajo.eliminar');
        $canChangeStatus = $user->can('agenda_trabajo.cambiar_estado');

        return DataTables::eloquent($query)->addIndexColumn()
            ->addColumn('schedule', fn (WorkAgendaItem $i) => $this->scheduleHtml($i))
            ->addColumn('activity', fn (WorkAgendaItem $i) => $this->activityHtml($i))
            ->addColumn('company_name', fn (WorkAgendaItem $i) => e($this->companyName($i->company)))
            ->addColumn('responsible_name', fn (WorkAgendaItem $i) => e($this->responsibleSummary($i)))
            ->addColumn('type_label', fn (WorkAgendaItem $i) => e($i->activity_type ?: 'Sin tipo'))
            ->addColumn('priority_badge', fn (WorkAgendaItem $i) => $this->badge('priority', $i->priority, WorkAgendaItem::PRIORITIES[$i->priority] ?? $i->priority))
            ->addColumn('status_badge', fn (WorkAgendaItem $i) => $this->badge('status', $i->status, WorkAgendaItem::STATUSES[$i->status] ?? $i->status))
            ->addColumn('actions', function (WorkAgendaItem $item) use ($user, $canEdit, $canDelete, $canChangeStatus) {
                $primaryAssignment = $item->activeAssignments->firstWhere('user_id', $user->id);

                return view('admin.work-agenda.partials.actions', compact('item', 'primaryAssignment', 'canEdit', 'canDelete', 'canChangeStatus'))->render();
            })
            ->setRowClass(fn (WorkAgendaItem $i) => $this->isOverdue($i) ? 'work-agenda-row is-overdue' : ($i->activity_date?->isToday() ? 'work-agenda-row is-today' : 'work-agenda-row'))
            ->rawColumns(['schedule', 'activity', 'priority_badge', 'status_badge', 'actions'])
            ->with('summary', $this->summaryFor($user))->toJson();
    }

    public function calendar(Request $request): JsonResponse
    {
        $v = $request->validate([
            'start' => ['required', 'date'], 'end' => ['required', 'date', 'after:start'],
            'company_id' => ['nullable', 'integer'], 'responsible_user_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(WorkAgendaItem::STATUSES))],
            'priority' => ['nullable', 'string', 'in:'.implode(',', array_keys(WorkAgendaItem::PRIORITIES))],
        ]);
        $user = $request->user();
        $query = WorkAgendaItem::query()->visibleTo($user)->with($this->relations())
            ->whereDate('activity_date', '>=', $this->calendarDate($v['start']))
            ->whereDate('activity_date', '<', $this->calendarDate($v['end']));
        if (! empty($v['company_id'])) {
            $query->where('company_id', (int) $v['company_id']);
        }
        if ($user->can(WorkAgendaItem::PERMISSION_VIEW_ALL) && ! empty($v['responsible_user_id'])) {
            $id = (int) $v['responsible_user_id'];
            $query->whereHas('activeAssignments', fn (Builder $q) => $q->where('user_id', $id));
        }
        if (! empty($v['status'])) {
            $query->where('status', $v['status']);
        }
        if (! empty($v['priority'])) {
            $query->where('priority', $v['priority']);
        }

        return response()->json($query->orderBy('activity_date')->orderBy('starts_at')->get()
            ->map(fn (WorkAgendaItem $i) => $this->calendarItem($i))->values());
    }

    public function responsibles(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can(WorkAgendaItem::PERMISSION_ASSIGN) || $user->can(WorkAgendaItem::PERMISSION_VIEW_ALL) || $user->can(WorkAgendaItem::PERMISSION_DERIVE), 403);
        abort_unless($user->belongsToCompany($company->id), 404);
        $users = $company->users()->where('users.status', 1)->orderBy('users.name')->orderBy('users.lastname')
            ->get(['users.id', 'users.name', 'users.lastname'])
            ->map(fn (User $u) => ['id' => $u->id, 'text' => $this->userName($u)]);

        return response()->json(['data' => $users]);
    }

    public function show(Request $request, WorkAgendaItem $workAgendaItem): JsonResponse
    {
        return response()->json(['data' => $this->serializeItem($this->visibleItem($request->user(), $workAgendaItem->id), $request->user())]);
    }

    public function store(StoreWorkAgendaItemRequest $request): JsonResponse
    {
        $user = $request->user();
        $v = $request->validated();
        $ids = $user->can(WorkAgendaItem::PERMISSION_ASSIGN) ? array_values(array_unique(array_map('intval', $v['responsible_user_ids']))) : [$user->id];
        $this->ensureMembership($ids, (int) $v['company_id']);
        $status = $user->can('agenda_trabajo.cambiar_estado') ? $v['status'] : WorkAgendaItem::STATUS_PENDING;
        $data = $this->payload($v) + ['created_by_user_id' => $user->id, 'responsible_user_id' => $ids[0]];
        $data['status'] = $status;
        $data['completed_at'] = $status === WorkAgendaItem::STATUS_COMPLETED ? now() : null;

        $item = DB::transaction(function () use ($data, $ids, $user, $status) {
            $item = WorkAgendaItem::create($data);
            $this->assignmentService->createAssignments($item, $ids, $user, $status);

            return $item;
        });

        return response()->json(['message' => 'Actividad registrada correctamente.', 'data' => $this->serializeItem($this->visibleItem($user, $item->id), $user)], 201);
    }

    public function update(UpdateWorkAgendaItemRequest $request, WorkAgendaItem $workAgendaItem): JsonResponse
    {
        $user = $request->user();
        $item = $this->visibleItem($user, $workAgendaItem->id);
        $v = $request->validated();
        if ($item->status === WorkAgendaItem::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['status' => ['Una actividad cancelada no puede editarse.']]);
        }
        if ((int) $v['company_id'] !== (int) $item->company_id) {
            throw ValidationException::withMessages(['company_id' => ['La empresa no puede cambiarse porque la actividad ya tiene historial.']]);
        }
        $ids = $user->can(WorkAgendaItem::PERMISSION_ASSIGN) ? array_values(array_unique(array_map('intval', $v['responsible_user_ids']))) : [];
        if ($ids) {
            $this->ensureMembership($ids, $item->company_id);
        }
        $data = $this->payload($v);
        unset($data['status']);
        DB::transaction(function () use ($item, $data, $ids, $user) {
            $item->update($data);
            if ($user->can(WorkAgendaItem::PERMISSION_ASSIGN)) {
                $this->assignmentService->synchronizeAssignees($item, $ids, $user);
            }
        });

        return response()->json(['message' => 'Actividad actualizada correctamente.', 'data' => $this->serializeItem($this->visibleItem($user, $item->id), $user)]);
    }

    public function updateAssignmentStatus(UpdateWorkAgendaStatusRequest $request, WorkAgendaItem $workAgendaItem, WorkAgendaItemAssignment $assignment): JsonResponse
    {
        $item = $this->visibleItem($request->user(), $workAgendaItem->id);
        $updated = $this->assignmentService->updateStatus($item, $assignment, $request->validated('status'), $request->user());

        return response()->json(['message' => 'Estado de la asignación actualizado correctamente.', 'data' => $this->serializeAssignment($updated, $request->user())]);
    }

    /** Compatibilidad B1: opera únicamente sobre la asignación propia o sobre la única asignación. */
    public function updateStatus(UpdateWorkAgendaStatusRequest $request, WorkAgendaItem $workAgendaItem): JsonResponse
    {
        $user = $request->user();
        $item = $this->visibleItem($user, $workAgendaItem->id);
        $status = $request->validated('status');
        if ($status === WorkAgendaItem::STATUS_CANCELLED) {
            abort_unless($user->can(WorkAgendaItem::PERMISSION_VIEW_ALL), 403);
            $this->assignmentService->cancelActivity($item, $user);
        } else {
            $candidates = $item->activeAssignments;
            if ($candidates->isEmpty()) {
                $candidates = collect([$this->assignmentService->ensureLegacyAssignment($item)]);
            }
            $assignment = $candidates->firstWhere('user_id', $user->id)
                ?: ($user->can(WorkAgendaItem::PERMISSION_VIEW_ALL) && $candidates->count() === 1 ? $candidates->first() : null);
            if (! $assignment) {
                throw ValidationException::withMessages(['assignment' => ['Seleccione una asignación individual.']]);
            }
            $this->assignmentService->updateStatus($item, $assignment, $status, $user);
        }

        return response()->json(['message' => 'Estado actualizado correctamente.', 'data' => $this->serializeItem($this->visibleItem($user, $item->id), $user)]);
    }

    public function deriveAssignment(DeriveWorkAgendaAssignmentRequest $request, WorkAgendaItem $workAgendaItem, WorkAgendaItemAssignment $assignment): JsonResponse
    {
        $item = $this->visibleItem($request->user(), $workAgendaItem->id);
        $destination = User::query()->findOrFail($request->integer('to_user_id'));
        $this->assignmentService->derive($item, $assignment, $destination, $request->user(), $request->string('reason'));

        return response()->json(['message' => 'La responsabilidad fue derivada correctamente.', 'data' => $this->serializeItem($this->visibleItem($request->user(), $item->id), $request->user())]);
    }

    public function myAlerts(Request $request): JsonResponse
    {
        return response()->json($this->alertService->forUser($request->user()));
    }

    public function destroy(Request $request, WorkAgendaItem $workAgendaItem): JsonResponse
    {
        DB::transaction(fn () => $this->visibleItem($request->user(), $workAgendaItem->id)->delete());

        return response()->json(['message' => 'Actividad eliminada correctamente.']);
    }

    private function visibleItem(User $user, int $id): WorkAgendaItem
    {
        return WorkAgendaItem::query()->visibleTo($user)->with($this->relations(true))->findOrFail($id);
    }

    private function relations(bool $history = false): array
    {
        $relations = ['company:id,business_name,trade_name', 'creator:id,name,lastname', 'responsible:id,name,lastname',
            'assignments.user:id,name,lastname', 'assignments.assignedBy:id,name,lastname', 'assignments.cancelledBy:id,name,lastname',
            'activeAssignments.user:id,name,lastname'];
        if ($history) {
            array_push($relations, 'derivations.fromUser:id,name,lastname', 'derivations.toUser:id,name,lastname', 'derivations.derivedBy:id,name,lastname');
        }

        return $relations;
    }

    private function payload(array $v): array
    {
        $allDay = (bool) $v['is_all_day'];
        $type = ($v['activity_type'] ?? null) === 'Otro' ? (trim((string) ($v['activity_type_other'] ?? '')) ?: 'Otro') : ($v['activity_type'] ?? null);

        return [
            'company_id' => (int) $v['company_id'], 'title' => trim($v['title']), 'description' => $this->trimOrNull($v['description'] ?? null),
            'activity_date' => $v['activity_date'], 'starts_at' => $allDay ? null : $this->localDateTime($v['activity_date'], $v['starts_at']),
            'ends_at' => $allDay || empty($v['ends_at']) ? null : $this->localDateTime($v['activity_date'], $v['ends_at']),
            'is_all_day' => $allDay, 'activity_type' => $this->trimOrNull($type), 'priority' => $v['priority'], 'status' => $v['status'],
            'location' => $this->trimOrNull($v['location'] ?? null),
            'reminder_at' => empty($v['reminder_at']) ? null : Carbon::createFromFormat('Y-m-d\TH:i', $v['reminder_at'], config('app.timezone'))->format('Y-m-d H:i:s'),
        ];
    }

    private function ensureMembership(array $ids, int $companyId): void
    {
        $count = User::query()->whereIn('id', $ids)->where('status', 1)
            ->whereHas('companies', fn (Builder $q) => $q->whereKey($companyId))->count();
        if ($count !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['responsible_user_ids' => ['Todos los responsables deben pertenecer a la empresa seleccionada.']]);
        }
    }

    private function serializeItem(WorkAgendaItem $item, User $user): array
    {
        $ids = $item->activeAssignments->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();

        return [
            'id' => $item->id, 'company_id' => $item->company_id, 'company' => $this->companyName($item->company),
            'responsible_user_id' => $item->responsible_user_id, 'responsible_user_ids' => $ids ?: [(int) $item->responsible_user_id],
            'responsible' => $this->responsibleSummary($item),
            'responsibles' => $item->assignments->sortBy('id')->map(fn ($a) => $this->serializeAssignment($a, $user))->values()->all(),
            'created_by' => $this->userName($item->creator), 'title' => $item->title, 'description' => $item->description,
            'activity_date' => $item->activity_date?->format('Y-m-d'), 'activity_date_display' => $item->activity_date?->format('d/m/Y'),
            'starts_at' => $item->starts_at?->format('H:i'), 'ends_at' => $item->ends_at?->format('H:i'), 'schedule' => $this->scheduleText($item),
            'is_all_day' => $item->is_all_day, 'activity_type' => $item->activity_type, 'priority' => $item->priority,
            'priority_label' => WorkAgendaItem::PRIORITIES[$item->priority] ?? $item->priority, 'status' => $item->status,
            'status_label' => WorkAgendaItem::STATUSES[$item->status] ?? $item->status, 'location' => $item->location,
            'reminder_at' => $item->reminder_at?->format('Y-m-d\TH:i'), 'reminder_display' => $item->reminder_at?->format('d/m/Y H:i'),
            'created_at' => $this->dateTime($item->created_at), 'completed_at' => $this->dateTime($item->completed_at),
            'is_today' => $item->activity_date?->isToday() ?? false, 'is_overdue' => $this->isOverdue($item),
            'can_edit' => $user->can('agenda_trabajo.editar'), 'can_delete' => $user->can('agenda_trabajo.eliminar'),
            'can_assign' => $user->can(WorkAgendaItem::PERMISSION_ASSIGN), 'timeline' => $this->timeline($item),
        ];
    }

    private function serializeAssignment(WorkAgendaItemAssignment $a, User $user): array
    {
        $ownsAssignment = (int) $a->user_id === (int) $user->id;
        $manageOwnStatus = $user->can('agenda_trabajo.cambiar_estado') && $ownsAssignment;
        $canCancel = $user->can('agenda_trabajo.cambiar_estado')
            && $user->can(WorkAgendaItem::PERMISSION_VIEW_ALL)
            && in_array($a->status, ['pending', 'in_progress'], true);
        $derive = $user->can(WorkAgendaItem::PERMISSION_DERIVE)
            && in_array($a->status, [WorkAgendaItemAssignment::STATUS_PENDING, WorkAgendaItemAssignment::STATUS_IN_PROGRESS], true)
            && ($a->user_id === $user->id || $user->can(WorkAgendaItem::PERMISSION_VIEW_ALL));
        $transitions = match ($a->status) {
            'pending' => ['in_progress', 'completed'], 'in_progress' => ['completed'], 'completed' => ['in_progress'], default => [],
        };
        if (! $manageOwnStatus) {
            $transitions = [];
        }
        if ($canCancel) {
            $transitions[] = 'cancelled';
        }

        return ['id' => $a->id, 'user_id' => $a->user_id, 'user' => $this->userName($a->user), 'status' => $a->status,
            'status_label' => WorkAgendaItemAssignment::STATUSES[$a->status] ?? $a->status, 'assigned_by' => $this->userName($a->assignedBy),
            'assigned_at' => $this->dateTime($a->assigned_at), 'started_at' => $this->dateTime($a->started_at),
            'completed_at' => $this->dateTime($a->completed_at), 'cancelled_at' => $this->dateTime($a->cancelled_at),
            'can_manage' => $manageOwnStatus || $canCancel, 'can_derive' => $derive, 'available_transitions' => $transitions];
    }

    private function timeline(WorkAgendaItem $item): array
    {
        $events = collect([['at' => $item->created_at, 'type' => 'created', 'text' => 'Actividad creada por '.$this->userName($item->creator).'.', 'reason' => null]]);
        foreach ($item->assignments as $a) {
            $events->push(['at' => $a->assigned_at, 'type' => 'assigned', 'text' => 'Asignada a '.$this->userName($a->user).' por '.$this->userName($a->assignedBy).'.', 'reason' => null]);
            if ($a->started_at) {
                $events->push(['at' => $a->started_at, 'type' => 'started', 'text' => $this->userName($a->user).' inició su tarea.', 'reason' => null]);
            }
            if ($a->completed_at) {
                $events->push(['at' => $a->completed_at, 'type' => 'completed', 'text' => $this->userName($a->user).' concluyó su tarea.', 'reason' => null]);
            }
            if ($a->cancelled_at) {
                $events->push(['at' => $a->cancelled_at, 'type' => 'cancelled', 'text' => 'Asignación de '.$this->userName($a->user).' cancelada.', 'reason' => null]);
            }
        }
        foreach ($item->derivations as $d) {
            $events->push(['at' => $d->derived_at, 'type' => 'derived',
                'text' => $this->userName($d->derivedBy).' derivó la responsabilidad de '.$this->userName($d->fromUser).' a '.$this->userName($d->toUser).'.', 'reason' => $d->reason]);
        }

        return $events->filter(fn ($e) => $e['at'])->sortBy(fn ($e) => $e['at']->getTimestamp())
            ->map(fn ($e) => ['at' => $this->dateTime($e['at']), 'type' => $e['type'], 'text' => $e['text'], 'reason' => $e['reason']])->values()->all();
    }

    private function summaryFor(User $user): array
    {
        $s = WorkAgendaItem::query()->visibleTo($user)
            ->selectRaw('SUM(CASE WHEN activity_date = ? THEN 1 ELSE 0 END) today_count', [today(config('app.timezone'))->toDateString()])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) pending_count', ['pending'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) progress_count', ['in_progress'])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) completed_count', ['completed'])->first();

        return ['today' => (int) ($s?->today_count ?? 0), 'pending' => (int) ($s?->pending_count ?? 0),
            'in_progress' => (int) ($s?->progress_count ?? 0), 'completed' => (int) ($s?->completed_count ?? 0)];
    }

    private function calendarItem(WorkAgendaItem $i): array
    {
        $date = $i->activity_date?->format('Y-m-d');

        return ['id' => (string) $i->id, 'title' => $i->title,
            'start' => $i->is_all_day ? $date : $i->starts_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i:s'),
            'end' => $i->is_all_day ? null : $i->ends_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i:s'),
            'allDay' => $i->is_all_day, 'status' => $i->status, 'priority' => $i->priority,
            'responsible' => $this->responsibleSummary($i), 'company' => $this->companyName($i->company)];
    }

    private function responsibleSummary(WorkAgendaItem $item): string
    {
        $active = $item->activeAssignments->sortBy('id');
        if ($active->isEmpty()) {
            return $this->userName($item->responsible);
        }

        return $this->userName($active->first()->user).($active->count() > 1 ? ' +'.($active->count() - 1) : '');
    }

    private function localDateTime(string $date, string $time): string
    {
        return Carbon::createFromFormat('Y-m-d H:i', "$date $time", config('app.timezone'))->format('Y-m-d H:i:s');
    }

    private function calendarDate(string $value): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}/', $value, $m) === 1 ? $m[0] : Carbon::parse($value, config('app.timezone'))->toDateString();
    }

    private function trimOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function companyName(?Company $company): string
    {
        return $company?->trade_name ?: ($company?->business_name ?: 'Empresa no disponible');
    }

    private function userName(?User $user): string
    {
        return $user ? (trim($user->name.' '.$user->lastname) ?: 'Usuario') : 'Usuario no disponible';
    }

    private function dateTime($value): ?string
    {
        return $value?->timezone(config('app.timezone'))->format('d/m/Y H:i');
    }

    private function scheduleText(WorkAgendaItem $i): string
    {
        return $i->is_all_day ? 'Todo el día' : collect([$i->starts_at?->format('H:i'), $i->ends_at?->format('H:i')])->filter()->implode(' - ');
    }

    private function badge(string $kind, string $value, string $label): string
    {
        return '<span class="work-agenda-badge '.$kind.'-'.e($value).'">'.e($label).'</span>';
    }

    private function scheduleHtml(WorkAgendaItem $i): string
    {
        $flag = $i->activity_date?->isToday() ? '<span class="work-agenda-date-flag is-today">Hoy</span>' : ($this->isOverdue($i) ? '<span class="work-agenda-date-flag is-overdue">Atrasada</span>' : '');

        return '<div class="work-agenda-schedule"><strong>'.e($i->activity_date?->format('d/m/Y')).'</strong><small><i class="far fa-clock"></i> '.e($this->scheduleText($i)).'</small>'.$flag.'</div>';
    }

    private function activityHtml(WorkAgendaItem $i): string
    {
        $description = $i->description ? '<small>'.e(str($i->description)->limit(82)).'</small>' : '<small class="text-muted">Sin descripción</small>';

        return '<div class="work-agenda-activity"><strong>'.e($i->title).'</strong>'.$description.'</div>';
    }

    private function isOverdue(WorkAgendaItem $i): bool
    {
        $due = $i->ends_at ?? $i->starts_at ?? $i->activity_date?->copy()->endOfDay();

        return ($due?->isPast() ?? false) && ! in_array($i->status, ['completed', 'cancelled'], true);
    }
}
