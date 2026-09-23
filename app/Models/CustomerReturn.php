<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReturn extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';

    public const REASONS = [
        'customer_rejection' => 'Rechazo del cliente',
        'delivery_error' => 'Error en entrega',
        'nonconforming_product' => 'Producto no conforme',
        'damaged_product' => 'Producto dañado',
        'quantity_difference' => 'Diferencia de cantidad',
        'expiration_or_lot' => 'Vencimiento / lote',
        'customer_cancellation' => 'Cancelación del cliente',
        'other' => 'Otro',
    ];

    public const QUARANTINE_REASONS = ['damaged_product', 'expiration_or_lot'];

    protected $fillable = [
        'return_number', 'idempotency_key', 'company_id', 'customer_purchase_order_id',
        'warehouse_dispatch_id', 'warehouse_id', 'return_date', 'status', 'reason',
        'reason_description', 'observation', 'received_by_user_id', 'created_by_user_id',
        'updated_by_user_id', 'confirmed_by_user_id', 'confirmed_at',
        'cancelled_by_user_id', 'cancelled_at', 'cancellation_reason',
        'reversed_by_user_id', 'reversed_at', 'reversal_reason',
    ];

    protected $casts = [
        'return_date' => 'datetime', 'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime', 'reversed_at' => 'datetime',
    ];

    protected $appends = [
        'return_date_local',
        'return_date_display',
    ];

    public function getReturnDateLocalAttribute(): ?string
    {
        return $this->return_date
            ?->copy()
            ?->timezone(config('app.timezone'))
            ->format('Y-m-d\TH:i');
    }

    public function getReturnDateDisplayAttribute(): ?string
    {
        return $this->return_date
            ?->copy()
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function customerPurchaseOrder() { return $this->belongsTo(CustomerPurchaseOrder::class); }
    public function warehouseDispatch() { return $this->belongsTo(WarehouseDispatch::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function items() { return $this->hasMany(CustomerReturnItem::class); }
    public function documents() { return $this->morphMany(Document::class, 'documentable'); }
    public function receivedBy() { return $this->belongsTo(User::class, 'received_by_user_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by_user_id'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by_user_id'); }
    public function confirmedBy() { return $this->belongsTo(User::class, 'confirmed_by_user_id'); }
    public function cancelledBy() { return $this->belongsTo(User::class, 'cancelled_by_user_id'); }
    public function reversedBy() { return $this->belongsTo(User::class, 'reversed_by_user_id'); }

    public function isDraft(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isConfirmed(): bool { return $this->status === self::STATUS_CONFIRMED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
    public function isReversed(): bool { return $this->status === self::STATUS_REVERSED; }
}
