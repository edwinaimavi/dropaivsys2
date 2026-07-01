<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WarehouseStock extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'stock_key',
        'warehouse_id',
        'article_id',
        'unit_id',
        'presentation_id',
        'brand_id',
        'lot_number',
        'expiration_date',
        'origin',
        'cost_type',
        'current_quantity',
        'reserved_quantity',
        'average_unit_cost',
        'total_cost',
        'min_stock',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'current_quantity' => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
        'average_unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:2',
        'min_stock' => 'decimal:4',
    ];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function movements()
    {
        return $this->hasMany(WarehouseKardexMovement::class, 'warehouse_stock_id');
    }
}
