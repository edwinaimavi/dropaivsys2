<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use SoftDeletes;

    protected $fillable = [

        'quote_number',
        'market_study_id',
        'customer_id',
      
        'company_id',
        'currency_id',
        'payment_condition',

        'delivery_address',

        'show_code_type',
        'orientation',
        'billing_type',

        'affect_igv',

        'validity_date',
        'delivery_days',
        'delivery_time',

        'observations',

        'subtotal_exonerated',
        'subtotal_taxed',
        'igv',
        'grand_total',

        'status',

        'created_by',
        'updated_by'
    ];

    public function marketStudy()
    {
        return $this->belongsTo(MarketStudy::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function items()
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
