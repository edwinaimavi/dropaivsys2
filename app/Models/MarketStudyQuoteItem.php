<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\Presentation;

class MarketStudyQuoteItem extends Model
{
    use SoftDeletes;
    protected $fillable = [

        'market_study_quote_id',

        'market_study_item_id',

        'article_id',

        'brand_id',

        'unit_id',

        'presentation_id',

        'manufacture_date',

        'expiration_date',

        'origin',

        'sanitary_registration',

        'tax_type',

        'quantity',

        'unit_price',

        'subtotal',

        'tax_amount',

        'total',

        'observation',

        'status',

        'created_by',

        'updated_by',

        'deleted_by',
    ];

    protected $casts = [

        'manufacture_date' => 'date',

        'expiration_date' => 'date',

        'quantity' => 'decimal:2',

        'unit_price' => 'decimal:4',

        'subtotal' => 'decimal:2',

        'tax_amount' => 'decimal:2',

        'total' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function quote()
    {
        return $this->belongsTo(
            MarketStudyQuote::class,
            'market_study_quote_id'
        );
    }

    public function marketStudyItem()
    {
        return $this->belongsTo(
            MarketStudyItem::class,
            'market_study_item_id'
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

    public function editor()
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function brand()
    {
        return $this->belongsTo(
            Brand::class,
            'brand_id'
        );
    }

    public function unit()
    {
        return $this->belongsTo(
            Unit::class,
            'unit_id'
        );
    }

    public function presentation()
    {
        return $this->belongsTo(
            Presentation::class,
            'presentation_id'
        );
    }

    public function winnerSelection()
    {
        return $this->hasOne(
            MarketStudyItemWinner::class,
            'market_study_quote_item_id'
        );
    }
}
