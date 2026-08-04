<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerOrderProfitabilityAnalysis extends Model
{
    protected $guarded = [];

    protected $casts = [
        'warnings' => 'array',
        'calculated_at' => 'datetime',
        'igv_rate' => 'decimal:2',
        'income_tax_rate' => 'decimal:2',
    ];

    public function customerPurchaseOrder() { return $this->belongsTo(CustomerPurchaseOrder::class); }
    public function currency() { return $this->belongsTo(Currency::class); }
    public function calculator() { return $this->belongsTo(User::class, 'calculated_by'); }
}
