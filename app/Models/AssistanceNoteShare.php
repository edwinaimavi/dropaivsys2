<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistanceNoteShare extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'company_id',
        'assistance_note_id',
        'user_id',
        'shared_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'assistance_note_id' => 'integer',
            'user_id' => 'integer',
            'shared_by_user_id' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(AssistanceNote::class, 'assistance_note_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by_user_id');
    }
}
