<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'entry_number',
        'supplier_purchase_order_id',
        'warehouse_id',
        'company_id',
        'supplier_id',
        'customer_id',
        'currency_id',
        'purchase_order_number',
        'document_type',
        'document_series',
        'document_number',
        'document_date',
        'payment_method',
        'payment_condition',
        'generate_account_payable',
        'payable_amount',
        'expected_payment_date',
        'seller_name',
        'affect_igv',
        'guide_series',
        'guide_number',
        'guide_ruc',
        'observations',
        'subtotal',
        'igv',
        'grand_total',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'expected_payment_date' => 'date',
        'generate_account_payable' => 'boolean',
        'affect_igv' => 'boolean',
        'payable_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function supplierPurchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items()
    {
        return $this->hasMany(WarehouseEntryItem::class);
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
