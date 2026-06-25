<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPurchaseOrderItem extends Model
{
    protected $fillable = [
        'customer_purchase_order_id',
        'quote_item_id',
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
        'quoted_quantity',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_amount',
        'line_total',
        'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'quoted_quantity' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(
            CustomerPurchaseOrder::class,
            'customer_purchase_order_id'
        );
    }

    public function quoteItem()
    {
        return $this->belongsTo(QuoteItem::class);
    }

    public function marketStudyItem()
    {
        return $this->belongsTo(MarketStudyItem::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
