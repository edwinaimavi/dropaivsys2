<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashExpenseExchangeReturn extends Model
{
    public const STATUS_ACTIVE = 'ACTIVE';

    protected $fillable = [
        'exchange_id', 'petty_cash_box_id', 'amount', 'return_date',
        'responsible_user_id', 'responsible_name', 'observation', 'file_path',
        'original_name', 'mime_type', 'file_size', 'status', 'created_by',
    ];

    protected $casts = [
        'return_date' => 'date',
        'amount' => 'decimal:2',
        'file_size' => 'integer',
    ];

    protected $appends = ['view_url', 'movement_code'];

    public function getMovementCodeAttribute(): string
    {
        return 'DEV-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function getViewUrlAttribute(): ?string
    {
        return $this->file_path ? route('admin.petty-cash.receipt-exchanges.returns.view', [$this->exchange_id, $this->id]) : null;
    }

    public function exchange() { return $this->belongsTo(PettyCashExpenseExchange::class, 'exchange_id'); }
    public function pettyCashBox() { return $this->belongsTo(PettyCashBox::class); }
    public function responsibleUser() { return $this->belongsTo(User::class, 'responsible_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
