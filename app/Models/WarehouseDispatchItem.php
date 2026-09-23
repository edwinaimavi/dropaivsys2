<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseDispatchItem extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_LEGACY_CONFIRMED = 'registered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'warehouse_dispatch_id',
        'customer_purchase_order_item_id',
        'warehouse_stock_id',
        'article_id',
        'unit_id',
        'presentation_id',
        'brand_id',
        'lot_number',
        'expiration_date',
        'quantity',
        'unit_cost',
        'total_cost',
        'kardex_movement_id',
        'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:2',
    ];

    public function dispatch()
    {
        return $this->belongsTo(WarehouseDispatch::class, 'warehouse_dispatch_id');
    }

    public function customerPurchaseOrderItem()
    {
        return $this->belongsTo(CustomerPurchaseOrderItem::class);
    }

    public function stock()
    {
        return $this->belongsTo(WarehouseStock::class, 'warehouse_stock_id');
    }

    public function kardexMovement()
    {
        return $this->belongsTo(WarehouseKardexMovement::class);
    }

    public function invoiceAllocations()
    {
        return $this->hasMany(ElectronicInvoiceItemDispatchAllocation::class);
    }

    public function customerReturnItems()
    {
        return $this->hasMany(CustomerReturnItem::class);
    }

    public function invoiceItems()
    {
        return $this->belongsToMany(
            ElectronicInvoiceItem::class,
            'electronic_invoice_item_dispatch_allocations'
        )->withPivot('quantity')->wherePivotNull('deleted_at')->withTimestamps();
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
