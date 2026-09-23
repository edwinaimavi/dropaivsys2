<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkAgendaItem extends Model
{
    use SoftDeletes;

    public const PERMISSION_VIEW_ALL = 'agenda_trabajo.ver_todos';

    public const PERMISSION_ASSIGN = 'agenda_trabajo.asignar';

    public const PERMISSION_DERIVE = 'agenda_trabajo.derivar';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_IN_PROGRESS => 'En proceso',
        self::STATUS_COMPLETED => 'Concluida',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Baja',
        self::PRIORITY_NORMAL => 'Normal',
        self::PRIORITY_HIGH => 'Alta',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    public const ACTIVITY_TYPES = [
        'Reunión',
        'Llamada',
        'Visita',
        'Seguimiento',
        'Entrega',
        'Revisión',
        'Trámite',
        'Otro',
    ];

    private const STATUS_TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        self::STATUS_COMPLETED => [self::STATUS_PENDING, self::STATUS_IN_PROGRESS],
        self::STATUS_CANCELLED => [],
    ];

    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'responsible_user_id',
        'title',
        'description',
        'activity_date',
        'starts_at',
        'ends_at',
        'is_all_day',
        'activity_type',
        'priority',
        'status',
        'location',
        'reminder_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'created_by_user_id' => 'integer',
            'responsible_user_id' => 'integer',
            'activity_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_all_day' => 'boolean',
            'reminder_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $query->whereIn($query->qualifyColumn('company_id'), function ($companyQuery) use ($user) {
            $companyQuery
                ->select('company_id')
                ->from('company_user')
                ->where('user_id', $user->getKey());
        });

        if ($user->can(self::PERMISSION_VIEW_ALL)) {
            return $query;
        }

        return $query->where(function (Builder $visibilityQuery) use ($user) {
            $visibilityQuery
                ->whereHas('assignments', fn (Builder $assignmentQuery) => $assignmentQuery
                    ->where('user_id', $user->getKey()))
                ->orWhere(function (Builder $legacyQuery) use ($user) {
                    $legacyQuery
                        ->whereDoesntHave('assignments')
                        ->where($legacyQuery->qualifyColumn('responsible_user_id'), $user->getKey());
                })
                ->orWhere($visibilityQuery->qualifyColumn('created_by_user_id'), $user->getKey());
        });
    }

    public function canTransitionTo(string $status): bool
    {
        return $status === $this->status
            || in_array($status, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function availableTransitions(): array
    {
        return self::STATUS_TRANSITIONS[$this->status] ?? [];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkAgendaItemAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->whereIn('status', WorkAgendaItemAssignment::ACTIVE_STATUSES);
    }

    public function derivations(): HasMany
    {
        return $this->hasMany(WorkAgendaItemDerivation::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
