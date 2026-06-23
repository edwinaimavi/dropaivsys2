<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'documentable_type',
        'documentable_id',

        'document_type_id',

        'original_name',
        'stored_name',

        'file_path',

        'mime_type',
        'extension',

        'file_size',

        'issue_date',
        'expiration_date',

        'observation',

        'status',

        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [

        'issue_date'      => 'date',
        'expiration_date' => 'date',
        'file_size'       => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | POLYMORPHIC RELATION
    |--------------------------------------------------------------------------
    */

    public function documentable()
    {
        return $this->morphTo();
    }

    /*
    |--------------------------------------------------------------------------
    | DOCUMENT TYPE
    |--------------------------------------------------------------------------
    */

    public function documentType()
    {
        return $this->belongsTo(DocumentType::class);
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT
    |--------------------------------------------------------------------------
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
}
