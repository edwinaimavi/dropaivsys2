<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingAgency extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'ruc',
        'business_name',
        'trade_name',
        'agency_type',
        'phone',
        'email',
        'website',
        'status',
        'observations',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function branches()
    {
        return $this->hasMany(ShippingAgencyBranch::class);
    }

    public function contacts()
    {
        return $this->hasMany(ShippingAgencyContact::class);
    }

    public function mainBranch()
    {
        return $this->hasOne(ShippingAgencyBranch::class)
            ->where('is_main', true)
            ->where('status', 'ACTIVE');
    }

    public function activeBranches()
    {
        return $this->hasMany(ShippingAgencyBranch::class)->where('status', 'ACTIVE');
    }

    public function activeContacts()
    {
        return $this->hasMany(ShippingAgencyContact::class)->where('status', 'ACTIVE');
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
