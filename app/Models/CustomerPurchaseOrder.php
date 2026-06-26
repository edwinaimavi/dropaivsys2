<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}
