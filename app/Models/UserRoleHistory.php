<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class UserRoleHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_id',
        'previous_role_id',
        'action',
        'description',
        'performed_by',
        'performed_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function previousRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'previous_role_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
