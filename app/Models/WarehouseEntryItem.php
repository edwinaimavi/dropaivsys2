<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseEntryItem extends Model
{
    public const TAX_AFFECTATION_TAXED = '10';

    public const TAX_AFFECTATION_EXEMPT = '20';

    public const TAX_AFFECTATION_UNAFFECTED = '30';

    public const SUPPORTED_TAX_AFFECTATION_CODES = [
        self::TAX_AFFECTATION_TAXED,
        self::TAX_AFFECTATION_EXEMPT,
        self::TAX_AFFECTATION_UNAFFECTED,
    ];

    public const TRANSACTIONAL_TAX_FIELDS = [
        'tax_affectation_code',
        'tax_rate',
        'discount_amount',
        'taxable_base',
        'is_free',
        'igv_recoverable',
    ];

    protected $fillable = [
        'warehouse_entry_id',
        'supplier_purchase_order_item_id',
        'article_id',
        'article_code',
        'billing_name_snapshot',
        'note',
        'unit_id',
        'presentation_id',
        'brand_id',
        'origin',
        'cost_type',
        'expiration_date',
        'lot_number',
        'ordered_quantity',
        'quantity',
        'unit_price',
        'subtotal',
        'tax_amount',
        'line_total',
        'tax_affectation_code',
        'tax_rate',
        'discount_amount',
        'taxable_base',
        'is_free',
        'igv_recoverable',
        'acquisition_cost_base',
        'acquisition_unit_cost_base',
        'additional_cost',
        'real_unit_cost',
        'status',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'ordered_quantity' => 'decimal:2',
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'taxable_base' => 'decimal:2',
        'is_free' => 'boolean',
        'igv_recoverable' => 'boolean',
        'acquisition_cost_base' => 'decimal:2',
        'acquisition_unit_cost_base' => 'decimal:6',
        'additional_cost' => 'decimal:2',
        'real_unit_cost' => 'decimal:6',
    ];

    public function isTaxed(): bool
    {
        return $this->tax_affectation_code === self::TAX_AFFECTATION_TAXED;
    }

    public function isExempt(): bool
    {
        return $this->tax_affectation_code === self::TAX_AFFECTATION_EXEMPT;
    }

    public function isUnaffected(): bool
    {
        return $this->tax_affectation_code === self::TAX_AFFECTATION_UNAFFECTED;
    }

    public function isFree(): bool
    {
        return $this->is_free === true;
    }

    public function warehouseEntry()
    {
        return $this->belongsTo(WarehouseEntry::class);
    }

    public function supplierPurchaseOrderItem()
    {
        return $this->belongsTo(SupplierPurchaseOrderItem::class);
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

    public function lots()
    {
        return $this->hasMany(WarehouseEntryItemLot::class)->where('status', 'active');
    }

    public function allocations()
    {
        return $this->hasMany(WarehouseEntryItemAllocation::class)
            ->where('status', 'active');
    }

    public function lotDocuments()
    {
        return $this->hasMany(WarehouseEntryItemLotDocument::class);
    }

    public function expenseDistributions()
    {
        return $this->hasMany(WarehouseEntryExpenseDistribution::class);
    }
}
