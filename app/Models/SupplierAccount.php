<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [

        'supplier_id',

        'bank_id',

        'currency_id',

        'account_holder',

        'account_number',

        'cci',

        'is_detraction',

        'status',

        'observation',

        'created_by',

        'updated_by',

        'deleted_by'
    ];

    /**
     * =========================================================
     * RELACIONES
     * =========================================================
     */

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

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

   
}
