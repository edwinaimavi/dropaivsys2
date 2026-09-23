<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    public function entries()
    {
        return $this->hasMany(WarehouseEntry::class);
    }

    public function dispatches()
    {
        return $this->hasMany(WarehouseDispatch::class);
    }

    public function companyWarehouses()
    {
        return $this->hasMany(CompanyWarehouse::class);
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_warehouses')
            ->withPivot([
                'sunat_establishment_code',
                'is_active',
                'created_by_user_id',
                'updated_by_user_id',
            ])
            ->withTimestamps();
    }

    public function stocks()
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function kardexMovements()
    {
        return $this->hasMany(WarehouseKardexMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
