<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ArticleInventoryClassificationAuditService
{
    public function __construct(private readonly ArticleInventoryPolicy $policy) {}

    public function analyze(bool $apply = false): array
    {
        $hasColumns = Schema::hasColumn('articles', 'item_kind')
            && Schema::hasColumn('articles', 'is_inventory_item');

        if ($apply && ! $hasColumns) {
            throw new RuntimeException('Primero debe aplicarse la migración de clasificación de artículos.');
        }

        $articles = Article::query()->orderBy('id')->get($hasColumns
            ? ['id', 'code', 'item_kind', 'is_inventory_item']
            : ['id', 'code']);
        $result = [
            'total' => $articles->count(),
            'already_classified' => [],
            'inventory_candidates' => [],
            'ambiguous_without_evidence' => [],
            'conflicts' => [],
            'applied' => 0,
        ];

        foreach ($articles as $article) {
            $kind = $hasColumns ? $article->item_kind : null;
            $inventory = $hasColumns ? $article->is_inventory_item : null;
            $valid = ($kind === Article::KIND_PRODUCT && $inventory !== null)
                || ($kind === Article::KIND_SERVICE && $inventory === false);

            if ($valid) {
                $result['already_classified'][] = $article->id;
                continue;
            }

            if ($kind !== null || $inventory !== null) {
                $result['conflicts'][] = $article->id;
                continue;
            }

            if ($this->policy->hasPhysicalEvidence($article)) {
                $result['inventory_candidates'][] = $article->id;
            } else {
                $result['ambiguous_without_evidence'][] = $article->id;
            }
        }

        if ($apply && $result['inventory_candidates']) {
            $result['applied'] = DB::table('articles')
                ->whereIn('id', $result['inventory_candidates'])
                ->whereNull('item_kind')
                ->whereNull('is_inventory_item')
                ->update([
                    'item_kind' => Article::KIND_PRODUCT,
                    'is_inventory_item' => true,
                    'updated_at' => now(),
                ]);
        }

        return $result;
    }
}
