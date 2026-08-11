<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyBankAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'bank_id',
        'currency_id',
        'account_holder',
        'account_number',
        'cci',
        'is_detraction',
        'status',
        'observation',
        'opening_balance',
        'current_balance',
        'opening_balance_date',
        'opening_balance_observation',
        'opening_balance_set_by',
        'opening_balance_set_at',
        'last_movement_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:4',
        'current_balance' => 'decimal:4',
        'opening_balance_date' => 'date',
        'opening_balance_set_at' => 'datetime',
        'last_movement_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function openingBalanceSetter()
    {
        return $this->belongsTo(User::class, 'opening_balance_set_by');
    }

    public function movements()
    {
        return $this->hasMany(BankMovement::class);
    }

    public function outgoingTransfers()
    {
        return $this->hasMany(BankTransfer::class, 'from_company_bank_account_id');
    }

    public function incomingTransfers()
    {
        return $this->hasMany(BankTransfer::class, 'to_company_bank_account_id');
    }

    public function reconciliations()
    {
        return $this->hasMany(BankReconciliation::class);
    }
}
