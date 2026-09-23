<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SunatCatalogItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ArticleInventoryPolicy
{
    public const SUNAT_EXISTENCE_TYPE_CATALOG = '05';

    private const EVIDENCE_TABLES = [
        'warehouse_stocks',
        'warehouse_kardex_movements',
        'warehouse_entry_items',
        'warehouse_dispatch_items',
        'customer_return_items',
    ];

    public function normalize(string $kind, mixed $inventory): array
    {
        return [
            'item_kind' => $kind,
            'is_inventory_item' => $kind === Article::KIND_SERVICE ? false : filter_var($inventory, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public function activeSunatExistenceTypes(): Collection
    {
        return $this->activeSunatExistenceTypesQuery()
            ->orderBy('item_code')
            ->get(['id', 'item_code', 'description']);
    }

    public function validateSunatExistenceType(
        string $kind,
        bool $inventory,
        mixed $sunatExistenceTypeItemId
    ): ?int {
        $hasValue = $sunatExistenceTypeItemId !== null && $sunatExistenceTypeItemId !== '';
        $requiresType = $kind === Article::KIND_PRODUCT && $inventory;

        if (! $requiresType) {
            if ($hasValue) {
                throw ValidationException::withMessages([
                    'sunat_existence_type_item_id' => 'El Tipo de Existencia SUNAT solo aplica a productos inventariables.',
                ]);
            }

            return null;
        }

        if (! $hasValue) {
            throw ValidationException::withMessages([
                'sunat_existence_type_item_id' => 'El Tipo de Existencia SUNAT es obligatorio para un producto inventariable.',
            ]);
        }

        if (! filter_var($sunatExistenceTypeItemId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])) {
            throw ValidationException::withMessages([
                'sunat_existence_type_item_id' => 'El Tipo de Existencia SUNAT seleccionado no es válido.',
            ]);
        }

        $itemId = (int) $sunatExistenceTypeItemId;
        if (! $this->activeSunatExistenceTypesQuery()->whereKey($itemId)->exists()) {
            throw ValidationException::withMessages([
                'sunat_existence_type_item_id' => 'Seleccione un Tipo de Existencia activo perteneciente al catálogo SUNAT 05.',
            ]);
        }

        return $itemId;
    }

    public function assertSunatExistenceTypeChangeAllowed(Article $article, ?int $targetItemId): void
    {
        $currentItemId = $article->sunat_existence_type_item_id === null
            ? null
            : (int) $article->sunat_existence_type_item_id;

        if ($currentItemId === null || $currentItemId === $targetItemId) {
            return;
        }

        if ($this->hasPhysicalEvidence($article)) {
            throw ValidationException::withMessages([
                'sunat_existence_type_item_id' => 'El tipo de existencia SUNAT no puede modificarse porque el artículo ya tiene historial de inventario.',
            ]);
        }
    }

    public function assertHasSunatExistenceType(Article $article, string $field = 'article_id'): void
    {
        if (! $article->isInventoryItem()) {
            return;
        }

        $article->loadMissing('sunatExistenceType.catalog');
        $item = $article->sunatExistenceType;
        $isValid = $item
            && $item->catalog_code === self::SUNAT_EXISTENCE_TYPE_CATALOG
            && $item->status === 'ACTIVE'
            && $item->catalog
            && $item->catalog->code === self::SUNAT_EXISTENCE_TYPE_CATALOG
            && $item->catalog->is_active;

        if (! $isValid) {
            throw ValidationException::withMessages([
                $field => 'El artículo no tiene configurado su Tipo de Existencia SUNAT.',
            ]);
        }
    }

    public function hasPhysicalEvidence(int|Article $article): bool
    {
        $articleId = $article instanceof Article ? $article->getKey() : $article;

        foreach (self::EVIDENCE_TABLES as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('article_id', $articleId)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function canParticipateInInventory(Article $article): bool
    {
        if ($article->isExplicitInventoryItem()) {
            return true;
        }

        if ($article->item_kind !== null || $article->is_inventory_item !== null) {
            return false;
        }

        return $this->hasPhysicalEvidence($article);
    }

    public function assertCanParticipateInInventory(Article $article, string $field = 'article_id'): void
    {
        if (! $this->canParticipateInInventory($article)) {
            throw ValidationException::withMessages([
                $field => 'El artículo seleccionado no está clasificado como producto inventariable.',
            ]);
        }
    }

    public function assertClassificationChangeAllowed(Article $article, string $kind, bool $inventory): void
    {
        $targetIsInventory = $kind === Article::KIND_PRODUCT && $inventory;

        if (! $targetIsInventory && $this->hasPhysicalEvidence($article)) {
            throw ValidationException::withMessages([
                'item_kind' => 'No se puede reclasificar como servicio o no inventariable porque el artículo tiene historial físico.',
            ]);
        }
    }

    public function scopeEligible(Builder $query, string $qualifiedId = 'articles.id'): Builder
    {
        $hasColumns = Schema::hasColumn('articles', 'item_kind')
            && Schema::hasColumn('articles', 'is_inventory_item');

        return $query->where(function (Builder $eligible) use ($qualifiedId, $hasColumns) {
            if ($hasColumns) {
                $eligible->where(function (Builder $explicit) {
                    $explicit->where('articles.item_kind', Article::KIND_PRODUCT)
                        ->where('articles.is_inventory_item', true);
                })->orWhere(function (Builder $legacy) use ($qualifiedId) {
                    $legacy->whereNull('articles.item_kind')
                        ->whereNull('articles.is_inventory_item')
                        ->where(fn (Builder $evidence) => $this->addEvidenceClauses($evidence, $qualifiedId));
                });

                return;
            }

            $eligible->where(fn (Builder $evidence) => $this->addEvidenceClauses($evidence, $qualifiedId));
        });
    }

    private function addEvidenceClauses(Builder $query, string $qualifiedId): void
    {
        $first = true;
        foreach (self::EVIDENCE_TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $method = $first ? 'whereExists' : 'orWhereExists';
            $query->{$method}(fn ($evidence) => $evidence
                ->selectRaw('1')
                ->from($table)
                ->whereColumn("{$table}.article_id", $qualifiedId));
            $first = false;
        }

        if ($first) {
            $query->whereRaw('1 = 0');
        }
    }

    private function activeSunatExistenceTypesQuery(): Builder
    {
        return SunatCatalogItem::query()
            ->where('catalog_code', self::SUNAT_EXISTENCE_TYPE_CATALOG)
            ->where('status', 'ACTIVE')
            ->whereHas('catalog', function (Builder $catalog) {
                $catalog->where('code', self::SUNAT_EXISTENCE_TYPE_CATALOG)
                    ->where('is_active', true);
            });
    }
}
