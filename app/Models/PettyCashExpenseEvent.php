<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashExpenseEvent extends Model
{
    protected $fillable = ['petty_cash_expense_id', 'document_id', 'event', 'description', 'metadata', 'created_by'];

    protected $casts = ['metadata' => 'array'];

    public function expense() { return $this->belongsTo(PettyCashExpense::class, 'petty_cash_expense_id'); }
    public function document() { return $this->belongsTo(Document::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
