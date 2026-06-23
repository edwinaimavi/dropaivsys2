<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerBranch extends Model
{
    protected $fillable = [

        'customer_id',

        'branch_name',
        'branch_type',

        'phone',
        'email',

        'ubigeo_id',

        'address',
        'reference',

        'voucher_type',
        'generate_guide',
        'payment_condition',

        'is_main',
        'status',

        'created_by',
        'updated_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
    public function ubigeo()
    {
        return $this->belongsTo(Ubigeo::class);
    }

    public function contacts()
    {
        return $this->hasMany(
            CustomerBranchContact::class,
            'customer_branch_id'
        );
    }
}
