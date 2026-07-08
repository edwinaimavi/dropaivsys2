<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingAgencyBranch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shipping_agency_id',
        'code',
        'branch_name',
        'address',
        'ubigeo_id',
        'department',
        'province',
        'district',
        'reference',
        'is_main',
        'phone',
        'email',
        'status',
        'observations',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function agency()
    {
        return $this->belongsTo(ShippingAgency::class, 'shipping_agency_id');
    }

    public function contacts()
    {
        return $this->hasMany(ShippingAgencyContact::class, 'shipping_agency_branch_id');
    }

    public function ubigeo()
    {
        return $this->belongsTo(Ubigeo::class, 'ubigeo_id');
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
