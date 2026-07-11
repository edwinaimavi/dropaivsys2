<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_name',
        'trade_name',
        'ruc',
        'email',
        'phone',
        'address',
        'logo',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function customerPurchaseOrders()
    {
        return $this->hasMany(CustomerPurchaseOrder::class);
    }

    public function supplierPurchaseOrders()
    {
        return $this->hasMany(SupplierPurchaseOrder::class);
    }

    public function warehouseEntries()
    {
        return $this->hasMany(WarehouseEntry::class);
    }

    public function customerOrderLabelings()
    {
        return $this->hasMany(CustomerOrderLabeling::class);
    }
}
