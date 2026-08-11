<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankReconciliation extends Model
{
    public const STATUS_OPEN = 'ABIERTA';

    public const STATUS_CLOSED = 'CERRADA';

    public const STATUS_CANCELLED = 'ANULADA';

    protected $fillable = [
        'code', 'company_bank_account_id', 'company_id', 'period', 'start_date', 'end_date',
        'system_balance', 'bank_statement_balance', 'difference', 'status', 'observation',
        'file_path', 'file_original_name', 'file_mime_type', 'file_size', 'created_by',
        'updated_by', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'system_balance' => 'decimal:4',
        'bank_statement_balance' => 'decimal:4',
        'difference' => 'decimal:4',
        'file_size' => 'integer',
        'closed_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function details()
    {
        return $this->hasMany(BankReconciliationMovement::class);
    }

    public function movements()
    {
        return $this->belongsToMany(BankMovement::class, 'bank_reconciliation_movements')->withPivot(['status', 'observation'])->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
