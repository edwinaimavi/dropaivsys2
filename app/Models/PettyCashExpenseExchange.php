<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashExpenseExchange extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'petty_cash_box_id', 'exchange_date', 'document_type', 'document_series',
        'document_correlative', 'total_amount', 'observation', 'status',
        'created_by', 'updated_by',
    ];

    protected $casts = ['exchange_date' => 'date', 'total_amount' => 'decimal:2'];

    protected $appends = ['document_full_number'];

    public function getDocumentFullNumberAttribute(): string
    {
        return implode('-', array_filter([$this->document_series, $this->document_correlative]));
    }

    public function pettyCash() { return $this->belongsTo(PettyCashBox::class, 'petty_cash_box_id'); }
    public function items() { return $this->hasMany(PettyCashExpenseExchangeItem::class, 'exchange_id'); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function editor() { return $this->belongsTo(User::class, 'updated_by'); }
}
