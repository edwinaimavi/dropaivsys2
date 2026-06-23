<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'person_type',
        'first_name',
        'last_name',
        'business_name',
        'full_name',
        'document_type',
        'document_number',
        'ruc',

        'channel',
        'subchannel',
        'withholding_agent',
        'phone',
        'email',
        'address',
        'status',
        'created_by',
        'updated_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    // 🔗 RELACIÓN CON VENTAS (para después)
    /*    public function sales()
    {
        return $this->hasMany(Sale::class);
    } */
    public function branches()
    {
        return $this->hasMany(CustomerBranch::class);
    }
}
