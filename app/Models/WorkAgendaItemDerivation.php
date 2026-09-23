<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkAgendaItemDerivation extends Model
{
    protected $fillable = [
        'work_agenda_item_id', 'from_assignment_id', 'to_assignment_id', 'from_user_id',
        'to_user_id', 'derived_by_user_id', 'reason', 'derived_at',
    ];

    protected function casts(): array
    {
        return ['derived_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(WorkAgendaItem::class, 'work_agenda_item_id');
    }

    public function fromAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkAgendaItemAssignment::class, 'from_assignment_id');
    }

    public function toAssignment(): BelongsTo
    {
        return $this->belongsTo(WorkAgendaItemAssignment::class, 'to_assignment_id');
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function derivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'derived_by_user_id');
    }
}
