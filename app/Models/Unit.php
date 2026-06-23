<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'units';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'abbreviation',
        'description',
        'decimal_quantity',
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

        'decimal_quantity' => 'boolean',

        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    // Usuario que creó
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Usuario que editó
    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Usuario que eliminó
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    // Solo activos
    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    public function presentations()
    {
        return $this->hasMany(Presentation::class);
    }
}
