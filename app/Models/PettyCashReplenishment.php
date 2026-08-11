<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashReplenishment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'petty_cash_box_id', 'code', 'replenishment_date', 'amount',
        'fund_source_company_id', 'fund_source_bank_account_id', 'fund_source_exchange_rate',
        'payment_method', 'bank_id', 'bank_account', 'reference_number',
        'observation', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = ['replenishment_date' => 'date', 'amount' => 'decimal:2', 'fund_source_exchange_rate' => 'decimal:6'];

    public function pettyCashBox() { return $this->belongsTo(PettyCashBox::class); }
    public function bank() { return $this->belongsTo(Bank::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }
    public function sourceCompany() { return $this->belongsTo(Company::class, 'fund_source_company_id'); }
    public function sourceBankAccount() { return $this->belongsTo(CompanyBankAccount::class, 'fund_source_bank_account_id'); }
}
