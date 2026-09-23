<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SunatCatalogItem;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SunatUnitPolicy
{
    public const CATALOG_CODE = '06';

    public const INVENTORY_MESSAGE = 'La unidad del artículo no tiene configurado su código SUNAT.';

    public const BILLING_MESSAGE = 'La unidad del artículo no tiene configurado un código de unidad SUNAT.';

    private const PHYSICAL_TABLES = [
        'warehouse_stocks',
        'warehouse_kardex_movements',
        'warehouse_entry_items',
        'warehouse_dispatch_items',
        'customer_return_items',
    ];

    public function activeSunatUnits(): Collection
    {
        return $this->activeSunatUnitsQuery()
            ->orderBy('item_code')
            ->get(['id', 'item_code', 'description']);
    }

    public function validateSunatUnit(mixed $itemId): ?int
    {
        if ($itemId === null || $itemId === '') {
            return null;
        }

        if (! filter_var($itemId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw ValidationException::withMessages([
                'sunat_unit_item_id' => 'La unidad SUNAT seleccionada no es válida.',
            ]);
        }

        $itemId = (int) $itemId;
        if (! $this->activeSunatUnitsQuery()->whereKey($itemId)->exists()) {
            throw ValidationException::withMessages([
                'sunat_unit_item_id' => 'Seleccione una unidad activa perteneciente al catálogo SUNAT 06.',
            ]);
        }

        return $itemId;
    }

    public function assertChangeAllowed(Unit $unit, ?int $targetItemId): void
    {
        $currentItemId = $unit->sunat_unit_item_id === null ? null : (int) $unit->sunat_unit_item_id;

        if ($currentItemId === null || $currentItemId === $targetItemId) {
            return;
        }

        if ($this->hasOperationalHistory($unit)) {
            throw ValidationException::withMessages([
                'sunat_unit_item_id' => 'La unidad SUNAT no puede modificarse porque esta unidad ya tiene historial operativo.',
            ]);
        }
    }

    public function codeForArticle(Article $article, string $field = 'items', bool $inventoryContext = false): string
    {
        $article->loadMissing('unit.sunatUnit.catalog');
        $item = $article->unit?->sunatUnit;
        $valid = $item
            && $item->catalog_code === self::CATALOG_CODE
            && $item->status === 'ACTIVE'
            && $item->catalog
            && $item->catalog->code === self::CATALOG_CODE
            && $item->catalog->is_active;

        if (! $valid) {
            throw ValidationException::withMessages([
                $field => $inventoryContext ? self::INVENTORY_MESSAGE : self::BILLING_MESSAGE,
            ]);
        }

        return $item->item_code;
    }

    public function hasOperationalHistory(Unit $unit): bool
    {
        $articleIds = Article::query()->where('unit_id', $unit->id)->pluck('id');
        if ($articleIds->isEmpty()) {
            return false;
        }

        foreach (self::PHYSICAL_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->whereIn('article_id', $articleIds)->exists()) {
                return true;
            }
        }

        return Schema::hasTable('electronic_invoice_items')
            && DB::table('electronic_invoice_items')->whereIn('article_id', $articleIds)->exists();
    }

    private function activeSunatUnitsQuery(): Builder
    {
        return SunatCatalogItem::query()
            ->where('catalog_code', self::CATALOG_CODE)
            ->where('status', 'ACTIVE')
            ->whereHas('catalog', fn (Builder $catalog) => $catalog
                ->where('code', self::CATALOG_CODE)
                ->where('is_active', true));
    }
}
