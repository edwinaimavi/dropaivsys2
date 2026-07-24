<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashReplenishment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'petty_cash_box_id', 'code', 'replenishment_date', 'amount',
        'payment_method', 'bank_id', 'bank_account', 'reference_number',
        'observation', 'status', 'created_by', 'updated_by',
    ];

    protected $casts = ['replenishment_date' => 'date', 'amount' => 'decimal:2'];

    public function pettyCashBox() { return $this->belongsTo(PettyCashBox::class); }
    public function bank() { return $this->belongsTo(Bank::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }
}
