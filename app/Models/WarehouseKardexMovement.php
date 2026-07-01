<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseKardexMovement extends Model
{
    protected $fillable = [
        'movement_number',
        'warehouse_stock_id',
        'warehouse_id',
        'article_id',
        'unit_id',
        'presentation_id',
        'brand_id',
        'lot_number',
        'expiration_date',
        'origin',
        'cost_type',
        'movement_date',
        'movement_type',
        'operation_type',
        'source_type',
        'source_id',
        'source_item_type',
        'source_item_id',
        'document_type',
        'document_series',
        'document_number',
        'related_party_type',
        'related_party_id',
        'related_party_name',
        'quantity_in',
        'quantity_out',
        'balance_quantity',
        'unit_cost',
        'total_cost_in',
        'total_cost_out',
        'average_unit_cost',
        'balance_total_cost',
        'currency_id',
        'exchange_rate',
        'observations',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'movement_date' => 'datetime',
        'quantity_in' => 'decimal:4',
        'quantity_out' => 'decimal:4',
        'balance_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:6',
        'total_cost_in' => 'decimal:2',
        'total_cost_out' => 'decimal:2',
        'average_unit_cost' => 'decimal:6',
        'balance_total_cost' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stock()
    {
        return $this->belongsTo(WarehouseStock::class, 'warehouse_stock_id');
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

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function sourceItem()
    {
        return $this->morphTo(__FUNCTION__, 'source_item_type', 'source_item_id');
    }
}
