<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashExpenseExchangeItem extends Model
{
    protected $fillable = [
        'exchange_id', 'petty_cash_expense_id', 'amount', 'concept',
        'receipt_type', 'receipt_series', 'receipt_correlative',
    ];

    protected $casts = ['amount' => 'decimal:2'];

    public function exchange() { return $this->belongsTo(PettyCashExpenseExchange::class, 'exchange_id'); }
    public function expense() { return $this->belongsTo(PettyCashExpense::class, 'petty_cash_expense_id'); }
}
