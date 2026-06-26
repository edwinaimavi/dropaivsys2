<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'company_id',
        'supplier_id',
        'supplier_account_id',
        'currency_id',
        'customer_purchase_order_id',
        'quote_id',
        'market_study_id',
        'order_type',
        'payment_condition',
        'delivery_type',
        'transport_type',
        'shipping_address',
        'destination_ubigeo_id',
        'destination_text',
        'payment_method',
        'document_type',
        'affect_igv',
        'observations',
        'subtotal',
        'igv',
        'grand_total',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'affect_igv' => 'boolean',
        'subtotal' => 'decimal:2',
        'igv' => 'decimal:2',
        'grand_total' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supplierAccount()
    {
        return $this->belongsTo(SupplierAccount::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function customerPurchaseOrder()
    {
        return $this->belongsTo(CustomerPurchaseOrder::class);
    }

    public function customerPurchaseOrders()
    {
        return $this->belongsToMany(
            CustomerPurchaseOrder::class,
            'supplier_purchase_order_customer_purchase_order'
        )->withTimestamps();
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function marketStudy()
    {
        return $this->belongsTo(MarketStudy::class);
    }

    public function destinationUbigeo()
    {
        return $this->belongsTo(Ubigeo::class, 'destination_ubigeo_id');
    }

    public function items()
    {
        return $this->hasMany(SupplierPurchaseOrderItem::class);
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
