<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use SoftDeletes;

    public const KIND_PRODUCT = 'product';

    public const KIND_SERVICE = 'service';

    public const SALES_TAX_TAXABLE = '10';
    public const SALES_TAX_EXONERATED = '20';
    public const SALES_TAX_UNAFFECTED = '30';

    protected $fillable = [

        'code',
        'code_type',

        'institutional_code',
        'category_id',
        'subcategory_id',
        'presentation_id',
        'unit_id',
        'brand_id',

        'legal_name',
        'commercial_name',
        'billing_name',

        'item_kind',
        'is_inventory_item',
        'sunat_existence_type_item_id',
        'sunat_inventory_catalog_item_id',
        'sunat_inventory_catalog_code',
        'sunat_standard_catalog_item_id',
        'sunat_standard_code',

        'is_taxable',
        'sales_tax_affectation_code',

        'minimum_stock',

        'has_batch',
        'has_expiration',

        'observation',

        'status',

        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [

        'is_taxable'     => 'boolean',
        'has_batch'      => 'boolean',
        'has_expiration' => 'boolean',
        'minimum_stock'  => 'decimal:2',
        'is_inventory_item' => 'boolean',
    ];

    public function hasPendingInventoryClassification(): bool
    {
        return $this->item_kind === null && $this->is_inventory_item === null;
    }

    public function isProduct(): bool
    {
        return $this->item_kind === self::KIND_PRODUCT;
    }

    public function isService(): bool
    {
        return $this->item_kind === self::KIND_SERVICE;
    }

    public function salesTaxAffectationLabel(): string
    {
        return match ($this->sales_tax_affectation_code) {
            self::SALES_TAX_TAXABLE => '10 — GRAVADO',
            self::SALES_TAX_EXONERATED => '20 — EXONERADO',
            self::SALES_TAX_UNAFFECTED => '30 — INAFECTO',
            default => 'PENDIENTE',
        };
    }

    public function isInventoryItem(): bool
    {
        return $this->isProduct() && $this->is_inventory_item === true;
    }

    public function isExplicitInventoryItem(): bool
    {
        return $this->isInventoryItem();
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function presentation()
    {
        return $this->belongsTo(Presentation::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function sunatExistenceType(): BelongsTo
    {
        return $this->belongsTo(SunatCatalogItem::class, 'sunat_existence_type_item_id');
    }

    public function sunatInventoryCatalogItem(): BelongsTo
    {
        return $this->belongsTo(SunatCatalogItem::class, 'sunat_inventory_catalog_item_id');
    }

    public function sunatStandardCatalogItem(): BelongsTo
    {
        return $this->belongsTo(SunatCatalogItem::class, 'sunat_standard_catalog_item_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function documents()
    {
        return $this->morphMany(
            Document::class,
            'documentable'
        );
    }

    public function images()
    {
        return $this->morphMany(
            Image::class,
            'imageable'
        );
    }

    public function marketStudyItems()
    {
        return $this->hasMany(
            MarketStudyItem::class,
            'article_id'
        );
    }
}
