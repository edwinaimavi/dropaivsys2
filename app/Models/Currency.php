<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [

        'code',

        'description',

        'symbol',

        'status',

        'created_by',

        'updated_by',

        'deleted_by'
    ];

    /**
     * =========================================================
     * RELACIONES
     * =========================================================
     */

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function supplierAccounts()
    {
        return $this->hasMany(SupplierAccount::class);
    }
}
