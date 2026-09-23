<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkAgendaItem;
use App\Models\WorkAgendaItemAssignment;
use App\Models\WorkAgendaItemDerivation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkAgendaAssignmentService
{
    public function createAssignments(
        WorkAgendaItem $item,
        array $userIds,
        User $actor,
        string $status = WorkAgendaItemAssignment::STATUS_PENDING
    ): void {
        $now = now();

        foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
            WorkAgendaItemAssignment::create([
                'work_agenda_item_id' => $item->id,
                'user_id' => $userId,
                'assigned_by_user_id' => $actor->id,
                'status' => $status,
                'assigned_at' => $now,
                'started_at' => in_array($status, [WorkAgendaItemAssignment::STATUS_IN_PROGRESS, WorkAgendaItemAssignment::STATUS_COMPLETED], true) ? $now : null,
                'completed_at' => $status === WorkAgendaItemAssignment::STATUS_COMPLETED ? $now : null,
                'cancelled_at' => $status === WorkAgendaItemAssignment::STATUS_CANCELLED ? $now : null,
                'cancelled_by_user_id' => $status === WorkAgendaItemAssignment::STATUS_CANCELLED ? $actor->id : null,
            ]);
        }

        $this->synchronizeItem($item);
    }

    public function synchronizeAssignees(WorkAgendaItem $item, array $userIds, User $actor): void
    {
        DB::transaction(function () use ($item, $userIds, $actor) {
            $lockedItem = WorkAgendaItem::query()->lockForUpdate()->findOrFail($item->id);
            $desiredIds = collect($userIds)->map(fn ($id) => (int) $id)->unique()->values();
            $assignments = WorkAgendaItemAssignment::query()
                ->where('work_agenda_item_id', $lockedItem->id)
                ->lockForUpdate()
                ->get();

            $assignments
                ->whereIn('status', [WorkAgendaItemAssignment::STATUS_PENDING, WorkAgendaItemAssignment::STATUS_IN_PROGRESS])
                ->whereNotIn('user_id', $desiredIds->all())
                ->each(function (WorkAgendaItemAssignment $assignment) use ($actor) {
                    $assignment->update([
                        'status' => WorkAgendaItemAssignment::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                        'cancelled_by_user_id' => $actor->id,
                    ]);
                });

            $activeUserIds = $assignments
                ->whereIn('status', WorkAgendaItemAssignment::ACTIVE_STATUSES)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id);

            foreach ($desiredIds->diff($activeUserIds) as $userId) {
                WorkAgendaItemAssignment::create([
                    'work_agenda_item_id' => $lockedItem->id,
                    'user_id' => $userId,
                    'assigned_by_user_id' => $actor->id,
                    'status' => WorkAgendaItemAssignment::STATUS_PENDING,
                    'assigned_at' => now(),
                ]);
            }

            $this->synchronizeItem($lockedItem);
        });
    }

    public function updateStatus(
        WorkAgendaItem $item,
        WorkAgendaItemAssignment $assignment,
        string $status,
        User $actor
    ): WorkAgendaItemAssignment {
        return DB::transaction(function () use ($item, $assignment, $status, $actor) {
            $lockedItem = WorkAgendaItem::query()->lockForUpdate()->findOrFail($item->id);
            $locked = WorkAgendaItemAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $locked->work_agenda_item_id !== (int) $lockedItem->id) {
                throw ValidationException::withMessages(['assignment' => ['La asignación no pertenece a esta actividad.']]);
            }

            if ($lockedItem->status === WorkAgendaItem::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['status' => ['La actividad está cancelada.']]);
            }

            if (! in_array($locked->status, WorkAgendaItemAssignment::ACTIVE_STATUSES, true)) {
                throw ValidationException::withMessages(['status' => ['La asignación ya no está activa.']]);
            }

            $allowed = match ($locked->status) {
                WorkAgendaItemAssignment::STATUS_PENDING => [
                    WorkAgendaItemAssignment::STATUS_IN_PROGRESS,
                    WorkAgendaItemAssignment::STATUS_COMPLETED,
                    WorkAgendaItemAssignment::STATUS_CANCELLED,
                ],
                WorkAgendaItemAssignment::STATUS_IN_PROGRESS => [
                    WorkAgendaItemAssignment::STATUS_COMPLETED,
                    WorkAgendaItemAssignment::STATUS_CANCELLED,
                ],
                WorkAgendaItemAssignment::STATUS_COMPLETED => [WorkAgendaItemAssignment::STATUS_IN_PROGRESS],
                default => [],
            };

            if ($status !== $locked->status && ! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages(['status' => ['El cambio de estado solicitado no está permitido.']]);
            }

            if ($status !== WorkAgendaItemAssignment::STATUS_CANCELLED
                && (int) $locked->user_id !== (int) $actor->id) {
                abort(403);
            }

            if ($status === WorkAgendaItemAssignment::STATUS_CANCELLED && ! $actor->can(WorkAgendaItem::PERMISSION_VIEW_ALL)) {
                abort(403);
            }

            $changes = ['status' => $status];
            if ($status === WorkAgendaItemAssignment::STATUS_IN_PROGRESS) {
                $changes['started_at'] = $locked->started_at ?? now();
                $changes['completed_at'] = null;
            } elseif ($status === WorkAgendaItemAssignment::STATUS_COMPLETED) {
                $changes['started_at'] = $locked->started_at ?? now();
                $changes['completed_at'] = $locked->completed_at ?? now();
            } elseif ($status === WorkAgendaItemAssignment::STATUS_CANCELLED) {
                $changes['cancelled_at'] = now();
                $changes['cancelled_by_user_id'] = $actor->id;
            }

            $locked->update($changes);
            $this->synchronizeItem($lockedItem);

            return $locked->fresh(['user', 'assignedBy', 'cancelledBy']);
        });
    }

    public function derive(
        WorkAgendaItem $item,
        WorkAgendaItemAssignment $assignment,
        User $destination,
        User $actor,
        string $reason
    ): WorkAgendaItemDerivation {
        return DB::transaction(function () use ($item, $assignment, $destination, $actor, $reason) {
            $lockedItem = WorkAgendaItem::query()->lockForUpdate()->findOrFail($item->id);
            $source = WorkAgendaItemAssignment::query()->lockForUpdate()->findOrFail($assignment->id);

            if ((int) $source->work_agenda_item_id !== (int) $lockedItem->id) {
                throw ValidationException::withMessages(['assignment' => ['La asignación no pertenece a esta actividad.']]);
            }

            if (! in_array($source->status, [WorkAgendaItemAssignment::STATUS_PENDING, WorkAgendaItemAssignment::STATUS_IN_PROGRESS], true)) {
                throw ValidationException::withMessages(['assignment' => ['La asignación ya no puede derivarse.']]);
            }

            if ((int) $source->user_id !== (int) $actor->id && ! $actor->can(WorkAgendaItem::PERMISSION_VIEW_ALL)) {
                abort(403);
            }

            if ((int) $source->user_id === (int) $destination->id) {
                throw ValidationException::withMessages(['to_user_id' => ['Seleccione un usuario diferente al responsable de origen.']]);
            }

            if (! $destination->belongsToCompany((int) $lockedItem->company_id) || ! $destination->status) {
                throw ValidationException::withMessages(['to_user_id' => ['El usuario destino no pertenece a la empresa de la actividad.']]);
            }

            $duplicate = WorkAgendaItemAssignment::query()
                ->where('work_agenda_item_id', $lockedItem->id)
                ->where('user_id', $destination->id)
                ->whereIn('status', WorkAgendaItemAssignment::ACTIVE_STATUSES)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages(['to_user_id' => ['El usuario destino ya tiene una asignación activa.']]);
            }

            $target = WorkAgendaItemAssignment::create([
                'work_agenda_item_id' => $lockedItem->id,
                'user_id' => $destination->id,
                'assigned_by_user_id' => $actor->id,
                'status' => WorkAgendaItemAssignment::STATUS_PENDING,
                'assigned_at' => now(),
            ]);

            $source->update(['status' => WorkAgendaItemAssignment::STATUS_DERIVED]);

            $derivation = WorkAgendaItemDerivation::create([
                'work_agenda_item_id' => $lockedItem->id,
                'from_assignment_id' => $source->id,
                'to_assignment_id' => $target->id,
                'from_user_id' => $source->user_id,
                'to_user_id' => $target->user_id,
                'derived_by_user_id' => $actor->id,
                'reason' => trim($reason),
                'derived_at' => now(),
            ]);

            $this->synchronizeItem($lockedItem);

            return $derivation->fresh(['fromUser', 'toUser', 'derivedBy']);
        });
    }

    public function cancelActivity(WorkAgendaItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor) {
            $lockedItem = WorkAgendaItem::query()->lockForUpdate()->findOrFail($item->id);
            WorkAgendaItemAssignment::query()
                ->where('work_agenda_item_id', $lockedItem->id)
                ->whereIn('status', [WorkAgendaItemAssignment::STATUS_PENDING, WorkAgendaItemAssignment::STATUS_IN_PROGRESS])
                ->update([
                    'status' => WorkAgendaItemAssignment::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancelled_by_user_id' => $actor->id,
                    'updated_at' => now(),
                ]);
            $lockedItem->update(['status' => WorkAgendaItem::STATUS_CANCELLED, 'completed_at' => null]);
            $this->synchronizeCompatibilityResponsible($lockedItem);
        });
    }

    public function ensureLegacyAssignment(WorkAgendaItem $item): WorkAgendaItemAssignment
    {
        return DB::transaction(function () use ($item) {
            $lockedItem = WorkAgendaItem::query()->lockForUpdate()->findOrFail($item->id);
            $existing = WorkAgendaItemAssignment::query()
                ->where('work_agenda_item_id', $lockedItem->id)
                ->oldest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return WorkAgendaItemAssignment::create([
                'work_agenda_item_id' => $lockedItem->id,
                'user_id' => $lockedItem->responsible_user_id,
                'assigned_by_user_id' => $lockedItem->created_by_user_id,
                'status' => $lockedItem->status,
                'assigned_at' => $lockedItem->created_at ?? now(),
                'started_at' => in_array($lockedItem->status, [WorkAgendaItem::STATUS_IN_PROGRESS, WorkAgendaItem::STATUS_COMPLETED], true) ? ($lockedItem->updated_at ?? now()) : null,
                'completed_at' => $lockedItem->completed_at,
                'cancelled_at' => $lockedItem->status === WorkAgendaItem::STATUS_CANCELLED ? ($lockedItem->updated_at ?? now()) : null,
            ]);
        });
    }

    public function synchronizeItem(WorkAgendaItem $item): void
    {
        $active = WorkAgendaItemAssignment::query()
            ->where('work_agenda_item_id', $item->id)
            ->whereIn('status', WorkAgendaItemAssignment::ACTIVE_STATUSES)
            ->get();

        $this->synchronizeCompatibilityResponsible($item, $active);

        if ($item->status === WorkAgendaItem::STATUS_CANCELLED) {
            return;
        }

        if ($active->isEmpty()) {
            $item->update(['status' => WorkAgendaItem::STATUS_CANCELLED, 'completed_at' => null]);

            return;
        }

        if ($active->every(fn (WorkAgendaItemAssignment $assignment) => $assignment->status === WorkAgendaItemAssignment::STATUS_COMPLETED)) {
            $completedAt = $active->max(fn (WorkAgendaItemAssignment $assignment) => $assignment->completed_at?->getTimestamp());
            $item->update([
                'status' => WorkAgendaItem::STATUS_COMPLETED,
                'completed_at' => $completedAt ? now()->setTimestamp($completedAt) : now(),
            ]);

            return;
        }

        $inProgress = $active->contains(fn (WorkAgendaItemAssignment $assignment) => in_array($assignment->status, [WorkAgendaItemAssignment::STATUS_IN_PROGRESS, WorkAgendaItemAssignment::STATUS_COMPLETED], true)
        );

        $item->update([
            'status' => $inProgress ? WorkAgendaItem::STATUS_IN_PROGRESS : WorkAgendaItem::STATUS_PENDING,
            'completed_at' => null,
        ]);
    }

    private function synchronizeCompatibilityResponsible(WorkAgendaItem $item, ?Collection $active = null): void
    {
        $active ??= WorkAgendaItemAssignment::query()
            ->where('work_agenda_item_id', $item->id)
            ->whereIn('status', WorkAgendaItemAssignment::ACTIVE_STATUSES)
            ->orderBy('id')
            ->get();

        $first = $active->sortBy('id')->first();
        if ($first && (int) $item->responsible_user_id !== (int) $first->user_id) {
            $item->update(['responsible_user_id' => $first->user_id]);
        }
    }
}
