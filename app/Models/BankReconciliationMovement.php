<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankReconciliationMovement extends Model
{
    protected $fillable = ['bank_reconciliation_id', 'bank_movement_id', 'status', 'observation'];

    public function reconciliation()
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function movement()
    {
        return $this->belongsTo(BankMovement::class, 'bank_movement_id');
    }
}
