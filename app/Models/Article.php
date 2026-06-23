<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'code',
        'code_type',

        'institutional_code',
        'category_id',
        'subcategory_id',
        'presentation_id',
        'unit_id',
        'brand_id',

        'legal_name',
        'commercial_name',
        'billing_name',

        'is_taxable',

        'minimum_stock',

        'has_batch',
        'has_expiration',

        'observation',

        'status',

        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [

        'is_taxable'     => 'boolean',
        'has_batch'      => 'boolean',
        'has_expiration' => 'boolean',
        'minimum_stock'  => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
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

    public function documents()
    {
        return $this->morphMany(
            Document::class,
            'documentable'
        );
    }

    public function images()
    {
        return $this->morphMany(
            Image::class,
            'imageable'
        );
    }

    public function marketStudyItems()
    {
        return $this->hasMany(
            MarketStudyItem::class,
            'article_id'
        );
    }
}
