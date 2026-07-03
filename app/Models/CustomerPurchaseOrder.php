<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CustomerPurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'company_id',
        'quote_id',
        'customer_id',
        'customer_branch_id',
        'order_type',
        'purchase_order_number',
        'currency_id',
        'notification_date',
        'delivery_start_date',
        'delivery_end_date',
        'siaf_file_number',
        'acquisition_chart_number',
        'process_type',
        'billing_type',
        'affect_igv',
        'observations',
        'subtotal_exonerated',
        'subtotal_taxed',
        'igv',
        'grand_total',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'notification_date' => 'date',
        'delivery_start_date' => 'date',
        'delivery_end_date' => 'date',
        'affect_igv' => 'boolean',
        'subtotal_exonerated' => 'decimal:2',
        'subtotal_taxed' => 'decimal:2',
        'igv' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerBranch()
    {
        return $this->belongsTo(CustomerBranch::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function items()
    {
        return $this->hasMany(CustomerPurchaseOrderItem::class);
    }

    public function supplierPurchaseOrders()
    {
        return $this->belongsToMany(
            SupplierPurchaseOrder::class,
            'supplier_purchase_order_customer_purchase_order'
        )->withTimestamps();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function refreshSupplyStatus(): void
    {
        if (in_array($this->status, ['cancelled', 'delivered', 'invoiced'], true)) {
            return;
        }

        $requestedByItem = $this->items()
            ->where('status', '!=', 'deleted')
            ->select('id', 'quantity')
            ->get()
            ->mapWithKeys(fn (CustomerPurchaseOrderItem $item) => [
                $item->id => round((float) $item->quantity, 2),
            ]);

        if ($requestedByItem->isEmpty()) {
            $this->forceFill(['status' => 'registered'])->save();
            return;
        }

        $itemIds = $requestedByItem->keys()->all();

        $hasSupplierPurchase = SupplierPurchaseOrderItem::query()
            ->join('supplier_purchase_orders as orders', 'orders.id', '=', 'supplier_purchase_order_items.supplier_purchase_order_id')
            ->whereIn('supplier_purchase_order_items.customer_purchase_order_item_id', $itemIds)
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', 'cancelled')
            ->where('supplier_purchase_order_items.status', '!=', 'deleted')
            ->exists();

        if (! $hasSupplierPurchase) {
            $this->forceFill(['status' => 'registered'])->save();
            return;
        }

        $enteredByItem = DB::table('warehouse_entry_items as items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'items.warehouse_entry_id')
            ->join('supplier_purchase_order_items as supplier_items', 'supplier_items.id', '=', 'items.supplier_purchase_order_item_id')
            ->join('supplier_purchase_orders as supplier_orders', 'supplier_orders.id', '=', 'supplier_items.supplier_purchase_order_id')
            ->whereIn('supplier_items.customer_purchase_order_item_id', $itemIds)
            ->whereNull('entries.deleted_at')
            ->whereNull('supplier_orders.deleted_at')
            ->where('entries.status', 'registered')
            ->where('supplier_orders.status', '!=', 'cancelled')
            ->where('items.status', '!=', 'deleted')
            ->where('supplier_items.status', '!=', 'deleted')
            ->groupBy('supplier_items.customer_purchase_order_item_id')
            ->selectRaw('supplier_items.customer_purchase_order_item_id, SUM(items.quantity) as entered_quantity')
            ->pluck('entered_quantity', 'customer_purchase_order_item_id')
            ->map(fn ($quantity) => round((float) $quantity, 2));

        $totalEntered = round((float) $enteredByItem->sum(), 2);
        $isComplete = $requestedByItem->every(function (float $requested, $itemId) use ($enteredByItem) {
            return round((float) ($enteredByItem[$itemId] ?? 0), 2) >= $requested;
        });

        $status = match (true) {
            $totalEntered <= 0 => 'in_purchase',
            $isComplete => 'entered',
            default => 'partial_entered',
        };

        if ($this->status !== $status) {
            $this->forceFill(['status' => $status])->save();
        }
    }
}
