<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryExpense extends Model
{
    protected $fillable = ['warehouse_entry_id', 'supplier_purchase_order_id', 'expense_category', 'cost_origin', 'expense_type', 'shipping_agency_id', 'provider_id', 'provider_ruc', 'provider_name', 'document_type', 'document_series', 'document_number', 'document_date', 'currency_id', 'amount', 'affects_inventory_cost', 'distribution_method', 'description', 'status', 'created_by', 'updated_by'];
    protected $casts = ['document_date' => 'date', 'amount' => 'decimal:2', 'affects_inventory_cost' => 'boolean'];

    public function warehouseEntry() { return $this->belongsTo(WarehouseEntry::class); }
    public function provider() { return $this->belongsTo(Supplier::class, 'provider_id'); }
    public function shippingAgency() { return $this->belongsTo(ShippingAgency::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function distributions() { return $this->hasMany(WarehouseEntryExpenseDistribution::class); }
    public function documents() { return $this->hasMany(WarehouseEntryExpenseDocument::class)->where('status', 'ACTIVE'); }
}
