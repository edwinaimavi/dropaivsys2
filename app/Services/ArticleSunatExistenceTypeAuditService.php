<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Schema;

class ArticleSunatExistenceTypeAuditService
{
    public function analyze(): array
    {
        $hasClassification = Schema::hasColumn('articles', 'item_kind')
            && Schema::hasColumn('articles', 'is_inventory_item');
        $hasExistenceType = Schema::hasColumn('articles', 'sunat_existence_type_item_id');

        $columns = ['id', 'code'];
        if ($hasClassification) {
            array_push($columns, 'item_kind', 'is_inventory_item');
        }
        if ($hasExistenceType) {
            $columns[] = 'sunat_existence_type_item_id';
        }

        $query = Article::query()->select($columns)->orderBy('id');
        if ($hasExistenceType) {
            $query->with('sunatExistenceType.catalog');
        }

        $result = [
            'migration_applied' => $hasExistenceType,
            'total_articles' => 0,
            'inventory_articles' => 0,
            'configured' => [],
            'pending' => [],
            'safe_candidates' => [],
            'ambiguous' => [],
            'conflicts' => [],
        ];

        foreach ($query->get() as $article) {
            $result['total_articles']++;
            $isInventory = $hasClassification
                && $article->item_kind === Article::KIND_PRODUCT
                && $article->is_inventory_item === true;
            $itemId = $hasExistenceType ? $article->sunat_existence_type_item_id : null;

            if (! $isInventory) {
                if ($itemId !== null) {
                    $result['conflicts'][] = $this->reference($article, 'Tabla 05 asignada a un artículo no inventariable o pendiente de clasificación');
                }

                continue;
            }

            $result['inventory_articles']++;

            if ($itemId === null) {
                $reference = $this->reference(
                    $article,
                    'No existe evidencia funcional suficiente para probar compra, almacenamiento y venta sin transformación'
                );
                $result['pending'][] = $reference;
                $result['ambiguous'][] = $reference;
                continue;
            }

            $item = $article->sunatExistenceType;
            $isValid = $item
                && $item->catalog_code === ArticleInventoryPolicy::SUNAT_EXISTENCE_TYPE_CATALOG
                && $item->status === 'ACTIVE'
                && $item->catalog
                && $item->catalog->code === ArticleInventoryPolicy::SUNAT_EXISTENCE_TYPE_CATALOG
                && $item->catalog->is_active;

            if ($isValid) {
                $result['configured'][] = $this->reference($article);
            } else {
                $result['conflicts'][] = $this->reference($article, 'El item asignado no es un item activo del catálogo SUNAT 05 activo');
            }
        }

        return $result;
    }

    private function reference(Article $article, ?string $reason = null): array
    {
        return array_filter([
            'id' => $article->id,
            'code' => $article->code,
            'reason' => $reason,
        ], fn ($value) => $value !== null);
    }
}
