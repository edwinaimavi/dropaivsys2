<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashExpense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'petty_cash_box_id', 'item_number', 'expense_date', 'document_type',
        'document_number', 'supplier_id', 'supplier_ruc', 'supplier_name',
        'concept', 'amount', 'observation', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = ['expense_date' => 'date', 'amount' => 'decimal:2'];

    public function pettyCashBox() { return $this->belongsTo(PettyCashBox::class); }
    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }
}
