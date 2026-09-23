<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerReturnItem extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'customer_return_id', 'warehouse_dispatch_item_id', 'warehouse_stock_id',
        'article_id', 'unit_id', 'presentation_id', 'brand_id', 'lot_number_snapshot',
        'expiration_date_snapshot', 'quantity', 'unit_cost_snapshot', 'total_cost',
        'kardex_movement_id', 'reversal_kardex_movement_id', 'status',
    ];

    protected $casts = [
        'expiration_date_snapshot' => 'date', 'quantity' => 'decimal:4',
        'unit_cost_snapshot' => 'decimal:6', 'total_cost' => 'decimal:2',
    ];

    public function customerReturn() { return $this->belongsTo(CustomerReturn::class); }
    public function warehouseDispatchItem() { return $this->belongsTo(WarehouseDispatchItem::class); }
    public function stock() { return $this->belongsTo(WarehouseStock::class, 'warehouse_stock_id'); }
    public function article() { return $this->belongsTo(Article::class); }
    public function unit() { return $this->belongsTo(Unit::class); }
    public function presentation() { return $this->belongsTo(Presentation::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function kardexMovement() { return $this->belongsTo(WarehouseKardexMovement::class); }
    public function reversalKardexMovement() { return $this->belongsTo(WarehouseKardexMovement::class, 'reversal_kardex_movement_id'); }
}
