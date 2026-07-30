<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashExpenseObservation extends Model
{
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_RESOLVED = 'RESOLVED';

    protected $fillable = [
        'petty_cash_expense_id',
        'observation',
        'status',
        'observed_by',
        'observed_at',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'observed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function expense()
    {
        return $this->belongsTo(PettyCashExpense::class, 'petty_cash_expense_id');
    }

    public function observer()
    {
        return $this->belongsTo(User::class, 'observed_by');
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
