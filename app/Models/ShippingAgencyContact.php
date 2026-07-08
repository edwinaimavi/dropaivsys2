<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShippingAgencyContact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shipping_agency_id',
        'shipping_agency_branch_id',
        'contact_name',
        'position',
        'phone',
        'whatsapp',
        'email',
        'is_primary',
        'status',
        'observations',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function agency()
    {
        return $this->belongsTo(ShippingAgency::class, 'shipping_agency_id');
    }

    public function branch()
    {
        return $this->belongsTo(ShippingAgencyBranch::class, 'shipping_agency_branch_id');
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
