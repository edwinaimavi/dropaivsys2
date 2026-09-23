<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyWarehouse extends Model
{
    protected $fillable = [
        'company_id',
        'warehouse_id',
        'sunat_establishment_code',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
