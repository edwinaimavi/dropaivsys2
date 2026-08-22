<?php

namespace App\Models;

use App\Services\PettyCashCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashBox extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'OPEN';
    public const STATUS_IN_REVIEW = 'IN_REVIEW';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_REIMBURSED = 'REIMBURSED';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const STATUSES = [
        self::STATUS_OPEN => 'ABIERTA',
        self::STATUS_IN_REVIEW => 'EN RENDICIÓN',
        self::STATUS_CLOSED => 'CERRADA',
        self::STATUS_REIMBURSED => 'REEMBOLSADA',
        self::STATUS_CANCELLED => 'ANULADA',
    ];

    protected $fillable = [
        'code', 'company_id', 'currency_id', 'approved_amount_id', 'approved_amount_snapshot', 'period_month', 'period_year',
        'periodicity', 'start_date', 'end_date', 'approved_fund', 'previous_balance',
        'fund_source_company_id', 'fund_source_bank_account_id', 'fund_source_exchange_rate',
        'previous_petty_cash_id', 'opening_amount',
        'total_expenses', 'cash_balance', 'reimbursement_amount',
        'responsible_name', 'responsible_dni', 'supervisor_name', 'supervisor_dni',
        'observations', 'status', 'opened_by', 'closed_by', 'reimbursed_by',
        'closed_at', 'close_observation', 'reimbursed_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_fund' => 'decimal:2',
        'approved_amount_snapshot' => 'decimal:2',
        'previous_balance' => 'decimal:2',
        'opening_amount' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'cash_balance' => 'decimal:2',
        'reimbursement_amount' => 'decimal:2',
        'fund_source_exchange_rate' => 'decimal:6',
        'closed_at' => 'datetime',
        'reimbursed_at' => 'datetime',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function approvedAmount() { return $this->belongsTo(PettyCashApprovedAmount::class, 'approved_amount_id'); }
    public function expenses() { return $this->hasMany(PettyCashExpense::class); }
    public function replenishments() { return $this->hasMany(PettyCashReplenishment::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }
    public function sourceCompany() { return $this->belongsTo(Company::class, 'fund_source_company_id'); }
    public function sourceBankAccount() { return $this->belongsTo(CompanyBankAccount::class, 'fund_source_bank_account_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function closer() { return $this->belongsTo(User::class, 'closed_by'); }
    public function previousPettyCash() { return $this->belongsTo(self::class, 'previous_petty_cash_id'); }
    public function carriedForwardTo() { return $this->hasOne(self::class, 'previous_petty_cash_id'); }
    public function expenseExchanges() { return $this->hasMany(PettyCashExpenseExchange::class); }
    public function receiptReturns() { return $this->hasMany(PettyCashExpenseExchangeReturn::class); }

    public function canManageExpenses(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_REVIEW], true);
    }

    public static function calculateBalances(float $fund, float $spent, float $replenished, float $previousBalance = 0): array
    {
        $openingAmount = round(max(0, $fund + $previousBalance), 2);
        $totals = app(PettyCashCalculator::class)->calculateValues(
            $openingAmount,
            $openingAmount,
            $spent,
            $replenished
        );

        return [
            'opening_amount' => $totals['initial_fund'],
            'current_balance' => $totals['current_balance'],
            'pending_replenishment' => $totals['pending_replenishment'],
        ];
    }
}
