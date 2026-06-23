<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'suppliers';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'ruc',
        'business_name',
        'short_name',
        'address',

        'ubigeo_id',

        'supplier_type',
        'payment_condition',

        'contact_name',
        'email',
        'phone',

        'igv_percentage',

        'observation',

        'status',

        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'igv_percentage' => 'decimal:2',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    // Usuario creador
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Usuario editor
    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Usuario eliminador
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Ubigeo
    public function ubigeo()
    {
        return $this->belongsTo(Ubigeo::class);
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }
    public function accounts()
    {
        return $this->hasMany(SupplierAccount::class);
    }
}
