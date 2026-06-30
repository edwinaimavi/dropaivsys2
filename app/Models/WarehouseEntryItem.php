<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryItem extends Model
{
    protected $fillable = [
        'warehouse_entry_id',
        'supplier_purchase_order_item_id',
        'article_id',
        'article_code',
        'billing_name_snapshot',
        'note',
        'unit_id',
        'presentation_id',
        'brand_id',
        'origin',
        'cost_type',
        'expiration_date',
        'lot_number',
        'ordered_quantity',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_amount',
        'line_total',
        'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'ordered_quantity' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function warehouseEntry()
    {
        return $this->belongsTo(WarehouseEntry::class);
    }

    public function supplierPurchaseOrderItem()
    {
        return $this->belongsTo(SupplierPurchaseOrderItem::class);
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
