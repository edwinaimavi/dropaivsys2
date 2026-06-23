<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteItem extends Model
{
    protected $fillable = [

        'quote_id',
        'market_study_item_id',

        'article_id',
        'article_code',
        'billing_name_snapshot',

        'note',

        'unit_id',
        'presentation_id',
        'brand_id',

        'origin',

        'expiration_date',

        'cost_type',
        'cost_price',

        'quantity',

        'unit_price',

        'discount_percentage',
        'discount_amount',

        'line_total',

        'is_winner'
    ];

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function marketStudyItem()
    {
        return $this->belongsTo(MarketStudyItem::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }
}
