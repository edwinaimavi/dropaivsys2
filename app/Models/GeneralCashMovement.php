<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralCashMovement extends Model
{
    public const DIRECTION_IN = 'IN';

    public const DIRECTION_OUT = 'OUT';

    public const STATUS_REGISTERED = 'REGISTERED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const TYPE_INCOME = 'INCOME';

    public const TYPE_EXPENSE = 'EXPENSE';

    public const TYPE_REVERSAL = 'REVERSAL';

    public const SOURCE_BANK_FUNDING = 'GENERAL_CASH_FUNDING';

    public const SOURCE_EXPENSE = 'GENERAL_CASH_EXPENSE';

    public const SOURCE_REVERSAL = 'GENERAL_CASH_REVERSAL';

    protected $fillable = [
        'code', 'general_cash_box_id', 'company_id', 'company_bank_account_id', 'bank_movement_id',
        'movement_date', 'direction', 'movement_type', 'source_type', 'source_id', 'amount',
        'previous_balance', 'new_balance', 'operation_number', 'responsible_user_id', 'responsible_name',
        'description', 'observation', 'status', 'reversal_of_id', 'idempotency_key', 'created_by',
        'updated_by', 'cancelled_by', 'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'movement_date' => 'datetime', 'amount' => 'decimal:4', 'previous_balance' => 'decimal:4',
        'new_balance' => 'decimal:4', 'cancelled_at' => 'datetime',
    ];

    public function box()
    {
        return $this->belongsTo(GeneralCashBox::class, 'general_cash_box_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(CompanyBankAccount::class, 'company_bank_account_id');
    }

    public function bankMovement()
    {
        return $this->belongsTo(BankMovement::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function reversalOf()
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal()
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public static function sourceLabel(?string $source): string
    {
        return match ($source) {
            self::SOURCE_BANK_FUNDING => 'Ingreso desde banco',
            self::SOURCE_EXPENSE => 'Gasto general',
            self::SOURCE_REVERSAL => 'Reversa',
            default => (string) $source,
        };
    }
}
