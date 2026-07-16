<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPurchaseOrderItem extends Model
{
    protected $fillable = [
        'supplier_purchase_order_id',
        'article_id',
        'market_study_item_id',
        'quote_item_id',
        'customer_purchase_order_item_id',
        'article_code',
        'billing_name_snapshot',
        'note',
        'unit_id',
        'presentation_id',
        'brand_id',
        'origin',
        'expiration_date',
        'cost_type',
        'reference_purchase_price',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_amount',
        'line_total',
        'total_with_igv',
        'taxable_base',
        'igv_percent',
        'igv_amount',
        'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'reference_purchase_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'total_with_igv' => 'decimal:2',
        'taxable_base' => 'decimal:2',
        'igv_percent' => 'decimal:2',
        'igv_amount' => 'decimal:2',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(
            SupplierPurchaseOrder::class,
            'supplier_purchase_order_id'
        );
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function marketStudyItem()
    {
        return $this->belongsTo(MarketStudyItem::class);
    }

    public function quoteItem()
    {
        return $this->belongsTo(QuoteItem::class);
    }

    public function customerPurchaseOrderItem()
    {
        return $this->belongsTo(CustomerPurchaseOrderItem::class);
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
