<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketStudyQuote extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'market_study_id',

        'quote_number',

        'supplier_id',

        'currency_id',

        'exchange_rate',

        'payment_condition',

        'shipping_cost',

        'other_costs',

        'delivery_date',

        'commercial_conditions',

        'status',

        'created_by',
        'updated_by',

        'gravada',
        'exonerada',
        'inafecta',
        'igv',
        'grand_total',
    ];

    protected $casts = [

        'exchange_rate' => 'decimal:4',

        'shipping_cost' => 'decimal:2',

        'other_costs' => 'decimal:2',

        'delivery_date' => 'date',

        'status' => 'boolean',
        'gravada'     => 'decimal:3',
        'exonerada'   => 'decimal:3',
        'inafecta'    => 'decimal:3',
        'igv'         => 'decimal:3',
        'grand_total' => 'decimal:3',
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

    public function supplier()
    {
        return $this->belongsTo(
            Supplier::class,
            'supplier_id'
        );
    }

    public function currency()
    {
        return $this->belongsTo(
            Currency::class,
            'currency_id'
        );
    }

    public function items()
    {
        return $this->hasMany(
            MarketStudyQuoteItem::class,
            'market_study_quote_id'
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

    
}
