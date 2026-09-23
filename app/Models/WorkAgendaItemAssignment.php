<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WorkAgendaItemAssignment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DERIVED = 'derived';

    public const STATUS_CANCELLED = 'cancelled';

    public const ACTIVE_STATUSES = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_IN_PROGRESS => 'En proceso',
        self::STATUS_COMPLETED => 'Concluida',
        self::STATUS_DERIVED => 'Derivada',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    protected $fillable = [
        'work_agenda_item_id', 'user_id', 'assigned_by_user_id', 'cancelled_by_user_id',
        'status', 'assigned_at', 'started_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(WorkAgendaItem::class, 'work_agenda_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function derivation(): HasOne
    {
        return $this->hasOne(WorkAgendaItemDerivation::class, 'from_assignment_id');
    }
}
