<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'imageable_type',
        'imageable_id',

        'original_name',
        'stored_name',

        'file_path',

        'mime_type',
        'extension',

        'file_size',

        'title',
        'description',

        'is_primary',
        'sort_order',

        'status',

        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [

        'is_primary' => 'boolean',

        'sort_order' => 'integer',

        'file_size'  => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | POLYMORPHIC
    |--------------------------------------------------------------------------
    */

    public function imageable()
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT
    |--------------------------------------------------------------------------
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
}
