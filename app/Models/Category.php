<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'categories';

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'description',
        'code',
        'type',
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

    // Usuario que actualizó
    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Usuario que eliminó
    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // Subcategorías
    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
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
}
