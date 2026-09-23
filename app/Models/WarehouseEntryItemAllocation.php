<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseEntryItemAllocation extends Model
{
    use SoftDeletes;

    public const TYPE_CUSTOMER_ORDER = 'customer_order';
    public const TYPE_SUPPLIER_ORDER = 'supplier_order';
    public const TYPE_FREE_STOCK = 'free_stock';

    protected $fillable = [
        'warehouse_entry_id', 'warehouse_entry_item_id',
        'customer_purchase_order_id', 'customer_purchase_order_item_id',
        'supplier_purchase_order_id', 'supplier_purchase_order_item_id',
        'article_id', 'quantity_allocated', 'unit_cost', 'total_cost',
        'allocation_type', 'status', 'created_by', 'updated_by', 'deleted_by',
    ];

    protected $casts = [
        'quantity_allocated' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:2',
    ];

    public function warehouseEntry() { return $this->belongsTo(WarehouseEntry::class); }
    public function warehouseEntryItem() { return $this->belongsTo(WarehouseEntryItem::class); }
    public function customerPurchaseOrder() { return $this->belongsTo(CustomerPurchaseOrder::class); }
    public function customerPurchaseOrderItem() { return $this->belongsTo(CustomerPurchaseOrderItem::class); }
    public function supplierPurchaseOrder() { return $this->belongsTo(SupplierPurchaseOrder::class); }
    public function supplierPurchaseOrderItem() { return $this->belongsTo(SupplierPurchaseOrderItem::class); }
    public function article() { return $this->belongsTo(Article::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }
    public function deleter() { return $this->belongsTo(User::class, 'deleted_by'); }
}
