<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseDispatch extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REVERSED = 'reversed';

    public const TYPE_CUSTOMER_ORDER = 'customer_order';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONFIRMED,
        self::STATUS_CANCELLED,
        self::STATUS_REVERSED,
    ];

    public const DISPATCH_TYPES = [
        self::TYPE_CUSTOMER_ORDER,
    ];

    protected $fillable = [
        'company_id',
        'dispatch_number',
        'idempotency_key',
        'customer_purchase_order_id',
        'warehouse_id',
        'dispatch_date',
        'responsible_user_id',
        'destination',
        'dispatch_type',
        'observation',
        'document_type',
        'document_number',
        'document_path',
        'document_name',
        'document_mime',
        'status',
        'confirmed_at',
        'confirmed_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dispatch_date' => 'datetime',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected $appends = [
        'dispatch_date_local',
    ];

    public function getDispatchDateLocalAttribute(): ?string
    {
        return $this->dispatch_date
            ?->copy()
            ?->timezone(config('app.timezone'))
            ->format('Y-m-d\TH:i');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customerPurchaseOrder()
    {
        return $this->belongsTo(CustomerPurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(WarehouseDispatchItem::class);
    }

    public function invoiceItemAllocations()
    {
        return $this->hasManyThrough(
            ElectronicInvoiceItemDispatchAllocation::class,
            WarehouseDispatchItem::class,
            'warehouse_dispatch_id',
            'warehouse_dispatch_item_id'
        );
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function customerReturns()
    {
        return $this->hasMany(CustomerReturn::class);
    }

    public function responsibleUser()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function confirmedBy()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }
}
