<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseValuationPool extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'article_id',
        'current_quantity',
        'average_unit_cost',
        'total_cost',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'current_quantity' => 'decimal:4',
        'average_unit_cost' => 'decimal:6',
        'total_cost' => 'decimal:2',
    ];

    public static function currentInventoryValue(?int $companyId = null): float
    {
        return round((float) static::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->sum('total_cost'), 2);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
