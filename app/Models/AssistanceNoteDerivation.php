<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AssistanceNoteDerivation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'assistance_note_id',
        'from_user_id',
        'to_user_id',
        'derived_by_user_id',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'assistance_note_id' => 'integer',
            'from_user_id' => 'integer',
            'to_user_id' => 'integer',
            'derived_by_user_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('El historial de derivaciones es inmutable.');
        });

        static::deleting(function () {
            throw new LogicException('El historial de derivaciones es inmutable.');
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(AssistanceNote::class, 'assistance_note_id');
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
