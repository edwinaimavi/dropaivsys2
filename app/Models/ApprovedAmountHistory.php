<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovedAmountHistory extends Model
{
    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'previous_amount',
        'approved_amount',
        'currency',
        'approved_by_user_id',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'previous_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function approvable()
    {
        return $this->morphTo();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
