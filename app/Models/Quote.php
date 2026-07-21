<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_AWARDED = 'awarded';

    public const EXPIRABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
    ];

    protected $fillable = [

        'quote_number',
        'market_study_id',
        'customer_id',
        'customer_branch_id',
      
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
        'additional_observations',

        'subtotal_exonerated',
        'subtotal_taxed',
        'igv',
        'grand_total',

        'status',

        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'affect_igv' => 'boolean',
        'validity_date' => 'date',
    ];

    public static function dismissExpiredQuotes(): int
    {
        return static::query()
            ->whereNotNull('validity_date')
            ->whereDate('validity_date', '<', today())
            ->whereIn('status', self::EXPIRABLE_STATUSES)
            ->whereDoesntHave('customerPurchaseOrders')
            ->update([
                'status' => self::STATUS_EXPIRED,
            ]);
    }

    public function marketStudy()
    {
        return $this->belongsTo(MarketStudy::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerBranch()
    {
        return $this->belongsTo(CustomerBranch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function customerPurchaseOrders()
    {
        return $this->hasMany(CustomerPurchaseOrder::class);
    }
}
