<?php

namespace App\Models;

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
        'code', 'company_id', 'currency_id', 'period_month', 'period_year',
        'periodicity', 'start_date', 'end_date', 'approved_fund', 'opening_amount',
        'total_expenses', 'cash_balance', 'reimbursement_amount',
        'responsible_name', 'responsible_dni', 'supervisor_name', 'supervisor_dni',
        'observations', 'status', 'opened_by', 'closed_by', 'reimbursed_by',
        'closed_at', 'reimbursed_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_fund' => 'decimal:2',
        'opening_amount' => 'decimal:2',
        'total_expenses' => 'decimal:2',
        'cash_balance' => 'decimal:2',
        'reimbursement_amount' => 'decimal:2',
        'closed_at' => 'datetime',
        'reimbursed_at' => 'datetime',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function expenses() { return $this->hasMany(PettyCashExpense::class); }
    public function replenishments() { return $this->hasMany(PettyCashReplenishment::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function canManageExpenses(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_REVIEW], true);
    }

    public static function calculateBalances(float $fund, float $spent, float $replenished): array
    {
        $fund = round($fund, 2);
        $spent = round($spent, 2);
        $replenished = round($replenished, 2);

        return [
            'current_balance' => (float) min($fund, max(0, round($fund - $spent + $replenished, 2))),
            'pending_replenishment' => (float) max(0, round($spent - $replenished, 2)),
        ];
    }
}
