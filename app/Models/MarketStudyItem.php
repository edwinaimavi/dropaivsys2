<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketStudyItem extends Model
{
    protected $table = 'market_study_items';

    protected $fillable = [

        'market_study_id',
        'article_id',

        'article_code_snapshot',
        'billing_name_snapshot',

        'category_snapshot',
        'subcategory_snapshot',

        'presentation_snapshot',

        'weight_snapshot',

        'cost_condition_snapshot',

        'status',

        'created_by',
        'updated_by',
    ];

    protected $casts = [

        'status' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function marketStudy()
    {
        return $this->belongsTo(
            MarketStudy::class,
            'market_study_id'
        );
    }

    public function article()
    {
        return $this->belongsTo(
            Article::class,
            'article_id'
        );
    }

    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updater()
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }



    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function winner()
    {
        return $this->hasOne(
            MarketStudyItemWinner::class,
            'market_study_item_id'
        );
    }
}
