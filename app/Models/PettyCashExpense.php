<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class PettyCashExpense extends Model
{
    use SoftDeletes;

    public const APPROVAL_PENDING = 'pendiente_aprobacion';

    public const APPROVAL_OBSERVED = 'observado';

    public const APPROVAL_APPROVED = 'aprobado';

    public const APPROVAL_REJECTED = 'rechazado';

    public const APPROVAL_CANCELLED = 'anulado';

    public const EXCHANGE_NOT_APPLICABLE = 'NO_APLICA';

    public const EXCHANGE_PENDING = 'PENDIENTE_CANJE';

    public const EXCHANGE_PARTIAL = 'PARCIALMENTE_RENDIDO';

    public const EXCHANGE_OBSERVED = 'OBSERVADO';

    public const EXCHANGE_COMPLETED = 'CANJEADO';

    public const WAREHOUSE_LINKABLE_DOCUMENT_TYPES = [
        'FACTURA',
        'BOLETA',
        'RECIBO_HONORARIOS',
        'RECIBO_POR_HONORARIOS',
        'RECIBO_HONORARIO',
        'RECIBO',
        'RECIBO_INTERNO',
        'SIN_COMPROBANTE',
        'TICKET',
        'OTRO',
    ];

    protected $fillable = [
        'petty_cash_box_id', 'item_number', 'expense_date', 'document_type',
        'document_series', 'document_correlative', 'document_number',
        'supplier_id', 'supplier_ruc', 'supplier_name',
        'concept', 'amount', 'observation', 'status', 'approval_status',
        'exchange_status', 'exchanged_at', 'exchange_id',
        'approved_at', 'approved_by_user_id', 'rejected_at', 'rejected_by_user_id',
        'approval_observation', 'created_by', 'updated_by',
    ];

    protected $appends = ['document_full_number'];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'exchanged_at' => 'datetime',
    ];

    public function getDocumentFullNumberAttribute(): ?string
    {
        $parts = array_filter([
            trim((string) $this->document_series),
            trim((string) $this->document_correlative),
        ], fn (string $value) => $value !== '');

        return $parts ? implode('-', $parts) : ($this->document_number ?: null);
    }

    public function pettyCashBox()
    {
        return $this->belongsTo(PettyCashBox::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    public function exchange()
    {
        return $this->belongsTo(PettyCashExpenseExchange::class, 'exchange_id');
    }

    public function exchangeItems()
    {
        return $this->hasMany(PettyCashExpenseExchangeItem::class, 'petty_cash_expense_id');
    }

    public function warehouseEntryExpenses()
    {
        if (! Schema::hasTable('warehouse_entry_expenses')
            || ! Schema::hasColumn('warehouse_entry_expenses', 'petty_cash_expense_id')) {
            return $this->hasMany(WarehouseEntryExpense::class, 'id', 'id')->whereRaw('1 = 0');
        }

        return $this->hasMany(WarehouseEntryExpense::class);
    }

    public function warehouseEntryExpense()
    {
        if (! Schema::hasTable('warehouse_entry_expenses')
            || ! Schema::hasColumn('warehouse_entry_expenses', 'petty_cash_expense_id')) {
            return $this->hasOne(WarehouseEntryExpense::class, 'id', 'id')->whereRaw('1 = 0');
        }

        return $this->hasOne(WarehouseEntryExpense::class)->latestOfMany();
    }

    public function observations()
    {
        return $this->hasMany(PettyCashExpenseObservation::class)->latest('observed_at');
    }

    public function events()
    {
        return $this->hasMany(PettyCashExpenseEvent::class)->latest();
    }

    public function currentObservation()
    {
        return $this->hasOne(PettyCashExpenseObservation::class)
            ->where('status', PettyCashExpenseObservation::STATUS_OPEN)
            ->latestOfMany('observed_at');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', self::APPROVAL_APPROVED);
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('approval_status', self::APPROVAL_PENDING);
    }

    public function scopeAwaitingResolution(Builder $query): Builder
    {
        return $query->whereIn('approval_status', [
            self::APPROVAL_PENDING,
            self::APPROVAL_OBSERVED,
        ]);
    }

    public function scopePendingExchange(Builder $query): Builder
    {
        return $query->whereIn('exchange_status', [
            self::EXCHANGE_PENDING,
            self::EXCHANGE_PARTIAL,
            self::EXCHANGE_OBSERVED,
        ]);
    }

    public function scopeAvailableForWarehouseLink(Builder $query): Builder
    {
        return $query
            ->where('status', 'ACTIVE')
            ->whereIn('document_type', self::WAREHOUSE_LINKABLE_DOCUMENT_TYPES)
            ->approved()
            ->whereDoesntHave('warehouseEntryExpenses', fn (Builder $warehouseQuery) => $warehouseQuery
                ->where('status', 'ACTIVE'))
            ->whereHas('pettyCashBox', fn (Builder $boxQuery) => $boxQuery
                ->where('status', '!=', PettyCashBox::STATUS_CANCELLED));
    }

    public function warehouseDocumentType(): ?string
    {
        $documentType = strtoupper(trim((string) $this->document_type));
        $documentType = str_replace([' ', '-'], '_', $documentType);

        return match ($documentType) {
            'FACTURA', 'BOLETA' => $documentType,
            'RECIBO_HONORARIOS', 'RECIBO_POR_HONORARIOS', 'RECIBO_HONORARIO' => 'RECIBO_HONORARIOS',
            'RECIBO', 'RECIBO_INTERNO' => 'RECIBO_INTERNO',
            'SIN_COMPROBANTE', 'TICKET', 'OTRO' => 'SIN_COMPROBANTE',
            default => null,
        };
    }

    public function hasWarehouseLinkableDocument(): bool
    {
        return $this->warehouseDocumentType() !== null;
    }
}
