<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkAgendaItem;
use App\Models\WorkAgendaItemAssignment;
use Carbon\CarbonInterface;

class WorkAgendaAlertService
{
    public function forUser(User $user, int $limit = 10): array
    {
        $now = now(config('app.timezone'));
        $assignments = WorkAgendaItemAssignment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                WorkAgendaItemAssignment::STATUS_PENDING,
                WorkAgendaItemAssignment::STATUS_IN_PROGRESS,
            ])
            ->whereHas('item', function ($query) use ($user) {
                $query->whereNull('deleted_at')
                    ->where('status', '!=', WorkAgendaItem::STATUS_CANCELLED)
                    ->whereIn('company_id', function ($companyQuery) use ($user) {
                        $companyQuery->select('company_id')->from('company_user')->where('user_id', $user->id);
                    });
            })
            ->with(['item.company:id,business_name,trade_name'])
            ->get();

        $alerts = $assignments
            ->map(fn (WorkAgendaItemAssignment $assignment) => $this->makeAlert($assignment, $now))
            ->filter()
            ->sortBy(fn (array $alert) => sprintf('%02d-%s', $alert['rank'], $alert['sort_at']))
            ->values();

        return [
            'count' => $alerts->count(),
            'alerts' => $alerts->take($limit)->values()->all(),
            'banner' => $alerts->first(fn (array $alert) => $alert['banner_eligible']),
        ];
    }

    private function makeAlert(WorkAgendaItemAssignment $assignment, CarbonInterface $now): ?array
    {
        $item = $assignment->item;
        $scheduledAt = $item->starts_at ?? $item->activity_date?->copy()->endOfDay();
        $dueAt = $item->ends_at ?? $scheduledAt;
        $overdue = $dueAt?->lt($now) ?? false;
        $reminderTriggered = $item->reminder_at?->lte($now) ?? false;
        $urgentToday = $item->priority === WorkAgendaItem::PRIORITY_URGENT
            && $item->activity_date?->isSameDay($now);
        $upcoming = $scheduledAt?->betweenIncluded($now, $now->copy()->addHours(24)) ?? false;

        if (! $overdue && ! $reminderTriggered && ! $urgentToday && ! $upcoming) {
            return null;
        }

        [$type, $rank] = match (true) {
            $overdue => ['overdue', 1],
            $urgentToday => ['urgent', 2],
            $reminderTriggered => ['reminder', 3],
            default => ['upcoming', 4],
        };
        $displayAt = $scheduledAt ?? $item->activity_date;

        return [
            'assignment_id' => $assignment->id,
            'item_id' => $item->id,
            'title' => $item->title,
            'company' => $item->company?->trade_name ?: $item->company?->business_name,
            'type' => $type,
            'label' => ['overdue' => 'VENCIDA', 'urgent' => 'URGENTE', 'reminder' => 'RECORDATORIO', 'upcoming' => 'PRÓXIMA'][$type],
            'when' => $this->whenText($displayAt, $now, (bool) $item->is_all_day),
            'url' => route('admin.work-agenda.index', ['activity' => $item->id]),
            'banner_eligible' => ($overdue && $item->priority === WorkAgendaItem::PRIORITY_URGENT)
                || ($reminderTriggered && $item->priority === WorkAgendaItem::PRIORITY_URGENT),
            'rank' => $rank,
            'sort_at' => $displayAt?->format('Y-m-d H:i:s') ?? '9999-12-31 23:59:59',
        ];
    }

    private function whenText(?CarbonInterface $date, CarbonInterface $now, bool $allDay): string
    {
        if (! $date) {
            return 'Sin horario';
        }

        $day = match (true) {
            $date->isSameDay($now) => 'Hoy',
            $date->isSameDay($now->copy()->subDay()) => 'Ayer',
            $date->isSameDay($now->copy()->addDay()) => 'Mañana',
            default => $date->format('d/m/Y'),
        };

        return $allDay ? $day.' · Todo el día' : $day.' · '.$date->format('H:i');
    }
}
