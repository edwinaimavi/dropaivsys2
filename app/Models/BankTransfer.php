<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankTransfer extends Model
{
    public const STATUS_REGISTERED = 'REGISTRADO';

    public const STATUS_CANCELLED = 'ANULADO';

    protected $fillable = [
        'code', 'company_id', 'from_company_bank_account_id', 'to_company_bank_account_id',
        'transfer_date', 'amount', 'currency_id', 'destination_amount', 'destination_currency_id',
        'exchange_rate', 'amount_pen', 'operation_number', 'description', 'file_path',
        'file_original_name', 'file_mime_type', 'file_size', 'status', 'created_by', 'updated_by',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'transfer_date' => 'datetime',
        'amount' => 'decimal:4',
        'destination_amount' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
        'amount_pen' => 'decimal:4',
        'file_size' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function fromAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'from_company_bank_account_id');
    }

    public function toAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'to_company_bank_account_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function destinationCurrency()
    {
        return $this->belongsTo(Currency::class, 'destination_currency_id');
    }

    public function movements()
    {
        return $this->hasMany(BankMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
