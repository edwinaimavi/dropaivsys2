<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MarketStudyItemWinner extends Model
{
    use HasFactory;

    protected $fillable = [
        'market_study_item_id',
        'market_study_quote_item_id',
    ];

    /**
     * Ítem del estudio de mercado.
     */
    public function studyItem()
    {
        return $this->belongsTo(
            MarketStudyItem::class,
            'market_study_item_id'
        );
    }

    /**
     * Ítem de la cotización ganadora.
     */
    public function quoteItem()
    {
        return $this->belongsTo(
            MarketStudyQuoteItem::class,
            'market_study_quote_item_id'
        );
    }

    
}
