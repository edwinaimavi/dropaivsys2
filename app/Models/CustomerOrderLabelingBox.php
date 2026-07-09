<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOrderLabelingBox extends Model
{
    protected $fillable = [
        'customer_order_labeling_id',
        'box_number',
        'observation',
        'box_label',
        'observations',
    ];

    public function labeling()
    {
        return $this->belongsTo(CustomerOrderLabeling::class, 'customer_order_labeling_id');
    }

    public function items()
    {
        return $this->hasMany(CustomerOrderLabelingBoxItem::class);
    }
}
