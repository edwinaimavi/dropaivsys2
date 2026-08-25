<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashExpenseExchangeDocument extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    protected $fillable = [
        'exchange_id', 'issuer_id', 'issuer_ruc', 'issuer_name', 'document_type',
        'series', 'number', 'issue_date', 'concept', 'observation', 'amount', 'file_path',
        'original_name', 'mime_type', 'file_size', 'status', 'created_by', 'updated_by',
        'edited_by', 'edited_at', 'reversed_by', 'reversed_at', 'reversal_reason',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'amount' => 'decimal:2',
        'file_size' => 'integer',
        'edited_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected $appends = ['document_full_number', 'view_url', 'update_url', 'reverse_url'];

    public function getDocumentFullNumberAttribute(): string
    {
        return implode('-', array_filter([$this->series, $this->number]));
    }

    public function getViewUrlAttribute(): ?string
    {
        return $this->file_path ? route('admin.petty-cash.receipt-exchanges.documents.view', [$this->exchange_id, $this->id]) : null;
    }

    public function getUpdateUrlAttribute(): string
    {
        return route('admin.petty-cash.receipt-exchanges.documents.update', [$this->exchange_id, $this->id]);
    }

    public function getReverseUrlAttribute(): string
    {
        return route('admin.petty-cash.receipt-exchanges.documents.reverse', [$this->exchange_id, $this->id]);
    }

    public function exchange() { return $this->belongsTo(PettyCashExpenseExchange::class, 'exchange_id'); }
    public function issuer() { return $this->belongsTo(DocumentIssuer::class, 'issuer_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function editor() { return $this->belongsTo(User::class, 'updated_by'); }
    public function explicitEditor() { return $this->belongsTo(User::class, 'edited_by'); }
    public function reverser() { return $this->belongsTo(User::class, 'reversed_by'); }
}
