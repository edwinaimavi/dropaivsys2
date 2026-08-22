<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PettyCashExpenseExchange extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_CANCELLED = 'CANCELLED';

    public const SETTLEMENT_PENDING = 'PENDING';
    public const SETTLEMENT_PARTIAL = 'PARTIAL';
    public const SETTLEMENT_SETTLED = 'SETTLED';
    public const SETTLEMENT_OBSERVED = 'OBSERVED';

    protected $fillable = [
        'petty_cash_box_id', 'document_issuer_id', 'exchange_date', 'document_type', 'document_series',
        'document_correlative', 'issuer_ruc', 'issuer_business_name', 'total_amount', 'observation', 'status',
        'original_amount', 'supported_amount', 'returned_amount', 'pending_amount',
        'settlement_status', 'settled_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'exchange_date' => 'date',
        'total_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'supported_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
        'pending_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    protected $appends = ['document_full_number'];

    public function getDocumentFullNumberAttribute(): string
    {
        return implode('-', array_filter([$this->document_series, $this->document_correlative]));
    }

    public function pettyCash() { return $this->belongsTo(PettyCashBox::class, 'petty_cash_box_id'); }
    public function documentIssuer() { return $this->belongsTo(DocumentIssuer::class); }
    public function items() { return $this->hasMany(PettyCashExpenseExchangeItem::class, 'exchange_id'); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }
    public function settlementDocuments()
    {
        return $this->hasMany(PettyCashExpenseExchangeDocument::class, 'exchange_id')
            ->where('status', PettyCashExpenseExchangeDocument::STATUS_ACTIVE);
    }
    public function returns()
    {
        return $this->hasMany(PettyCashExpenseExchangeReturn::class, 'exchange_id')
            ->where('status', PettyCashExpenseExchangeReturn::STATUS_ACTIVE);
    }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function editor() { return $this->belongsTo(User::class, 'updated_by'); }
}
