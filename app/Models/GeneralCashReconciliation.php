<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralCashReconciliation extends Model
{
    public const STATUS_CLOSED = 'CLOSED';

    protected $fillable = [
        'code', 'general_cash_box_id', 'company_id', 'reconciliation_date', 'system_balance',
        'physical_balance', 'difference', 'responsible_user_id', 'responsible_name', 'observation',
        'status', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'reconciliation_date' => 'datetime', 'system_balance' => 'decimal:4',
        'physical_balance' => 'decimal:4', 'difference' => 'decimal:4',
    ];

    public function box()
    {
        return $this->belongsTo(GeneralCashBox::class, 'general_cash_box_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
