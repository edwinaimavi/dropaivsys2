<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'description',

        'short_name',

        'status',

        'created_by',

        'updated_by',

        'deleted_by'
    ];

    /**
     * =========================================================
     * RELATIONS
     * =========================================================
     */

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function editor()
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function deleter()
    {
        return $this->belongsTo(
            User::class,
            'deleted_by'
        );
    }

    public function supplierAccounts()
    {
        return $this->hasMany(SupplierAccount::class);
    }
}
