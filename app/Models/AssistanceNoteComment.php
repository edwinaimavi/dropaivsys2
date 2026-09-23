<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssistanceNoteComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'assistance_note_id',
        'user_id',
        'comment',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'assistance_note_id' => 'integer',
            'user_id' => 'integer',
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
}
