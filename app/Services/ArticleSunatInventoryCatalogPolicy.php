<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SunatCatalogItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ArticleSunatInventoryCatalogPolicy
{
    public const CATALOG_CODE = '13';

    public const INVENTORY_MESSAGE = 'El artículo no tiene configurado su catálogo y código de existencia SUNAT.';

    private const MAIN_ITEM_CODES = ['1', '3', '9'];

    private const STANDARD_ITEM_CODES = ['1', '3'];

    public function __construct(private readonly ArticleInventoryPolicy $inventoryPolicy) {}

    public function activeInventoryCatalogItems(): Collection
    {
        return $this->activeItemsQuery(self::MAIN_ITEM_CODES)
            ->orderBy('item_code')
            ->get(['id', 'item_code', 'description']);
    }

    public function activeStandardCatalogItems(): Collection
    {
        return $this->activeItemsQuery(self::STANDARD_ITEM_CODES)
            ->orderBy('item_code')
            ->get(['id', 'item_code', 'description']);
    }

    public function validate(
        string $kind,
        bool $inventory,
        mixed $inventoryCatalogItemId,
        mixed $inventoryCatalogCode,
        mixed $standardCatalogItemId,
        mixed $standardCode
    ): array {
        $requiresInventoryIdentification = $kind === Article::KIND_PRODUCT && $inventory;
        $mainItemId = $this->positiveIntegerOrNull($inventoryCatalogItemId, 'sunat_inventory_catalog_item_id');
        $mainCode = $this->trimmedOrNull($inventoryCatalogCode);

        if ($requiresInventoryIdentification) {
            if ($mainItemId === null) {
                throw ValidationException::withMessages([
                    'sunat_inventory_catalog_item_id' => 'El catálogo de existencia SUNAT es obligatorio para un producto inventariable.',
                ]);
            }
            if ($mainCode === null) {
                throw ValidationException::withMessages([
                    'sunat_inventory_catalog_code' => 'El código de existencia SUNAT es obligatorio para un producto inventariable.',
                ]);
            }
            $this->assertActiveItem($mainItemId, self::MAIN_ITEM_CODES, 'sunat_inventory_catalog_item_id');
            $this->assertCodeLength($mainCode, 24, 'sunat_inventory_catalog_code', 'El código de existencia SUNAT no debe superar 24 caracteres.');
        } else {
            if ($mainItemId !== null || $mainCode !== null) {
                throw ValidationException::withMessages([
                    'sunat_inventory_catalog_item_id' => 'La identificación SUNAT de existencia solo aplica a productos inventariables.',
                ]);
            }
            $mainItemId = null;
            $mainCode = null;
        }

        $standardItemId = $this->positiveIntegerOrNull($standardCatalogItemId, 'sunat_standard_catalog_item_id');
        $normalizedStandardCode = $this->trimmedOrNull($standardCode);
        if (($standardItemId === null) !== ($normalizedStandardCode === null)) {
            $field = $standardItemId === null ? 'sunat_standard_catalog_item_id' : 'sunat_standard_code';
            throw ValidationException::withMessages([
                $field => 'El catálogo y el código internacional SUNAT deben registrarse conjuntamente.',
            ]);
        }
        if ($standardItemId !== null) {
            $this->assertActiveItem($standardItemId, self::STANDARD_ITEM_CODES, 'sunat_standard_catalog_item_id');
            $this->assertCodeLength($normalizedStandardCode, 128, 'sunat_standard_code', 'El código internacional no debe superar 128 caracteres.');
        }

        return [
            'sunat_inventory_catalog_item_id' => $mainItemId,
            'sunat_inventory_catalog_code' => $mainCode,
            'sunat_standard_catalog_item_id' => $standardItemId,
            'sunat_standard_code' => $normalizedStandardCode,
        ];
    }

    public function assertPrimaryChangeAllowed(Article $article, ?int $targetItemId, ?string $targetCode): void
    {
        $currentItemId = $article->sunat_inventory_catalog_item_id === null
            ? null
            : (int) $article->sunat_inventory_catalog_item_id;
        $currentCode = $this->trimmedOrNull($article->sunat_inventory_catalog_code);

        if ($currentItemId === null && $currentCode === null) {
            return;
        }
        if ($currentItemId === $targetItemId && $currentCode === $this->trimmedOrNull($targetCode)) {
            return;
        }
        if ($this->inventoryPolicy->hasPhysicalEvidence($article)) {
            throw ValidationException::withMessages([
                'sunat_inventory_catalog_item_id' => 'La identificación SUNAT de la existencia no puede modificarse porque el artículo ya tiene historial de inventario.',
            ]);
        }
    }

    public function assertHasInventoryIdentification(Article $article, string $field = 'article_id'): void
    {
        if (! $article->isInventoryItem()) {
            return;
        }

        $article->loadMissing('sunatInventoryCatalogItem.catalog');
        $item = $article->sunatInventoryCatalogItem;
        $code = $this->trimmedOrNull($article->sunat_inventory_catalog_code);
        $valid = $item
            && in_array((string) $item->item_code, self::MAIN_ITEM_CODES, true)
            && $item->catalog_code === self::CATALOG_CODE
            && $item->status === 'ACTIVE'
            && $item->catalog
            && $item->catalog->code === self::CATALOG_CODE
            && $item->catalog->is_active
            && $code !== null
            && mb_strlen($code) <= 24;

        if (! $valid) {
            throw ValidationException::withMessages([$field => self::INVENTORY_MESSAGE]);
        }
    }

    private function activeItemsQuery(array $itemCodes): Builder
    {
        return SunatCatalogItem::query()
            ->where('catalog_code', self::CATALOG_CODE)
            ->whereIn('item_code', $itemCodes)
            ->where('status', 'ACTIVE')
            ->whereHas('catalog', fn (Builder $catalog) => $catalog
                ->where('code', self::CATALOG_CODE)
                ->where('is_active', true));
    }

    private function assertActiveItem(int $itemId, array $allowedItemCodes, string $field): void
    {
        if (! $this->activeItemsQuery($allowedItemCodes)->whereKey($itemId)->exists()) {
            $message = $field === 'sunat_standard_catalog_item_id'
                ? 'Seleccione Naciones Unidas o GS1, activos y pertenecientes al catálogo SUNAT 13.'
                : 'Seleccione un item activo perteneciente al catálogo SUNAT 13.';
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function positiveIntegerOrNull(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw ValidationException::withMessages([
                $field => 'El catálogo SUNAT seleccionado no es válido.',
            ]);
        }

        return (int) $value;
    }

    private function trimmedOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function assertCodeLength(?string $code, int $max, string $field, string $message): void
    {
        if ($code === null || mb_strlen($code) > $max) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
