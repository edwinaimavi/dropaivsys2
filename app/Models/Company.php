<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'inventory_valuation_method_item_id',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }


    public function inventoryValuationMethod()
    {
        return $this->belongsTo(SunatCatalogItem::class, 'inventory_valuation_method_item_id');
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function workAgendaItems()
    {
        return $this->hasMany(WorkAgendaItem::class);
    }

    public function assistanceNotes()
    {
        return $this->hasMany(AssistanceNote::class);
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

    public function companyWarehouses()
    {
        return $this->hasMany(CompanyWarehouse::class);
    }

    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'company_warehouses')
            ->withPivot([
                'sunat_establishment_code',
                'is_active',
                'created_by_user_id',
                'updated_by_user_id',
            ])
            ->withTimestamps();
    }

    public function warehouseStocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function warehouseKardexMovements()
    {
        return $this->hasMany(WarehouseKardexMovement::class);
    }

    public function customerOrderLabelings()
    {
        return $this->hasMany(CustomerOrderLabeling::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(CompanyBankAccount::class);
    }

    public function pettyCashApprovedAmounts()
    {
        return $this->hasMany(PettyCashApprovedAmount::class);
    }
}
