<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssistanceNote extends Model
{
    use SoftDeletes;

    public const PERMISSION_VIEW_ALL = 'agenda_notas.ver_todos';

    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITY_SHARED = 'shared';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DERIVED = 'derived';

    public const STATUS_COMPLETED = 'completed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'company_id',
        'created_by_user_id',
        'responsible_user_id',
        'title',
        'content',
        'visibility',
        'status',
        'priority',
        'follow_up_date',
        'due_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'created_by_user_id' => 'integer',
            'responsible_user_id' => 'integer',
            'follow_up_date' => 'date',
            'due_at' => 'datetime',
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
                ->where($visibilityQuery->qualifyColumn('created_by_user_id'), $user->getKey())
                ->orWhere($visibilityQuery->qualifyColumn('responsible_user_id'), $user->getKey())
                ->orWhereHas('shares', function (Builder $shareQuery) use ($user) {
                    $shareQuery
                        ->where('assistance_note_shares.user_id', $user->getKey());
                });
        });
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

    public function shares(): HasMany
    {
        return $this->hasMany(AssistanceNoteShare::class);
    }

    public function derivations(): HasMany
    {
        return $this->hasMany(AssistanceNoteDerivation::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AssistanceNoteComment::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
