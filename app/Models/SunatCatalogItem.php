<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class SunatCatalogItem extends Model
{
    public const TAX_AFFECTATION_CATALOG = [
        ['item_code' => '10', 'description' => 'GRAVADO - OPERACION ONEROSA', 'short_name' => 'Gravado'],
        ['item_code' => '20', 'description' => 'EXONERADO - OPERACION ONEROSA', 'short_name' => 'Exonerado'],
        ['item_code' => '30', 'description' => 'INAFECTO - OPERACION ONEROSA', 'short_name' => 'Inafecto'],
    ];

    protected $fillable = [
        'sunat_catalog_id',
        'catalog_code',
        'item_code',
        'description',
        'short_name',
        'extra_data',
        'source',
        'is_official',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'extra_data' => 'array',
        'is_official' => 'boolean',
    ];

    public function catalog(): BelongsTo
    {
        return $this->belongsTo(SunatCatalog::class, 'sunat_catalog_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public static function activeTaxAffectations(): Collection
    {
        $items = static::query()
            ->where('catalog_code', 'tax_affectation')
            ->where('status', 'ACTIVE')
            ->orderBy('item_code')
            ->get();

        if ($items->isNotEmpty()) {
            return $items;
        }

        return collect(static::TAX_AFFECTATION_CATALOG)->map(fn (array $item) => new static(array_merge($item, [
            'catalog_code' => 'tax_affectation',
            'status' => 'ACTIVE',
        ])));
    }
}
