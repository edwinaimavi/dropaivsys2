<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOrderLabelingBoxItem extends Model
{
    protected $fillable = [
        'customer_order_labeling_box_id',
        'customer_purchase_order_item_id',
        'article_id',
        'article_code',
        'description',
        'unit_name',
        'quantity',
        'lot',
        'expiration_date',
        'brand_name',
        'origin',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'expiration_date' => 'date',
    ];

    public function box()
    {
        return $this->belongsTo(CustomerOrderLabelingBox::class, 'customer_order_labeling_box_id');
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function customerPurchaseOrderItem()
    {
        return $this->belongsTo(CustomerPurchaseOrderItem::class);
    }
}
