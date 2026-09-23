<?php

namespace App\Services;

use App\Models\Article;
use App\Models\SunatCatalogItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArticleSunatInventoryCatalogAuditService
{
    private const POSSIBLE_UNSPSC_COLUMNS = ['unspsc', 'unspsc_code', 'sunat_product_code'];

    private const POSSIBLE_GTIN_COLUMNS = ['barcode', 'bar_code', 'ean', 'ean_code', 'gtin', 'gtin_code'];

    public function analyze(): array
    {
        $hasColumns = $this->hasIdentificationColumns();
        $evidenceColumns = $this->evidenceColumns();
        $columns = array_values(array_unique(array_merge(
            ['id', 'code', 'item_kind', 'is_inventory_item'],
            $evidenceColumns,
            $hasColumns ? [
                'sunat_inventory_catalog_item_id', 'sunat_inventory_catalog_code',
                'sunat_standard_catalog_item_id', 'sunat_standard_code',
            ] : []
        )));

        $query = Article::query()->select($columns)->orderBy('id');
        if ($hasColumns) {
            $query->with(['sunatInventoryCatalogItem.catalog', 'sunatStandardCatalogItem.catalog']);
        }
        $articles = $query->get();
        $inventoryArticles = $articles->filter(fn (Article $article) =>
            $article->item_kind === Article::KIND_PRODUCT && $article->is_inventory_item === true
        );
        $codeCounts = $inventoryArticles
            ->map(fn (Article $article) => mb_strtoupper(trim((string) $article->code), 'UTF-8'))
            ->filter()
            ->countBy();

        $result = [
            'migration_applied' => $hasColumns,
            'total_articles' => $articles->count(),
            'inventory_articles' => $inventoryArticles->count(),
            'configured' => [],
            'pending' => [],
            'safe_candidates' => [],
            'unspsc_detected' => [],
            'gtin_detected' => [],
            'ambiguous' => [],
            'conflicts' => [],
        ];

        foreach ($articles as $article) {
            $isInventory = $article->item_kind === Article::KIND_PRODUCT && $article->is_inventory_item === true;
            $mainId = $hasColumns ? $article->sunat_inventory_catalog_item_id : null;
            $mainCode = $hasColumns ? trim((string) $article->sunat_inventory_catalog_code) : '';
            $standardEvidence = $this->standardEvidence($article, $evidenceColumns, $hasColumns);
            if ($standardEvidence['unspsc']) {
                $result['unspsc_detected'][] = $this->reference($article, null, $standardEvidence['unspsc']);
            }
            if ($standardEvidence['gtin']) {
                $result['gtin_detected'][] = $this->reference($article, null, $standardEvidence['gtin']);
            }

            if (! $isInventory) {
                if ($mainId !== null || $mainCode !== '') {
                    $result['conflicts'][] = $this->reference($article, 'Identificación principal asignada a un artículo no inventariable');
                }
                continue;
            }

            if ($mainId !== null || $mainCode !== '') {
                if ($mainId !== null && $mainCode !== '' && $this->validMainAssignment($article)) {
                    $result['configured'][] = $this->reference(
                        $article,
                        'Configuración válida',
                        $mainCode,
                        (string) $article->sunatInventoryCatalogItem->item_code
                    );
                } else {
                    $result['conflicts'][] = $this->reference($article, 'La asignación principal no es una pareja válida y activa del catálogo SUNAT 13');
                }
                continue;
            }

            $result['pending'][] = $this->reference($article, 'Sin catálogo y código principal de existencia');
            $reason = $this->candidateRejectionReason($article, $codeCounts, $standardEvidence);
            if ($reason === null) {
                $result['safe_candidates'][] = $this->reference(
                    $article,
                    'Código interno único, válido y sin evidencia GS1/UNSPSC; confianza alta',
                    trim((string) $article->code),
                    '9'
                );
            } else {
                $result['ambiguous'][] = $this->reference($article, $reason);
            }
        }

        return $result;
    }

    public function applySafeCandidates(): array
    {
        if (! $this->hasIdentificationColumns()) {
            return ['migration_applied' => false, 'applied' => [], 'already_configured' => [], 'skipped' => []];
        }

        return DB::transaction(function () {
            $candidateIds = collect($this->analyze()['safe_candidates'])->pluck('id');
            $item = SunatCatalogItem::query()
                ->where('catalog_code', ArticleSunatInventoryCatalogPolicy::CATALOG_CODE)
                ->where('item_code', '9')
                ->where('status', 'ACTIVE')
                ->whereHas('catalog', fn ($catalog) => $catalog
                    ->where('code', ArticleSunatInventoryCatalogPolicy::CATALOG_CODE)
                    ->where('is_active', true))
                ->first();
            $result = ['migration_applied' => true, 'applied' => [], 'already_configured' => [], 'skipped' => []];

            if (! $item) {
                foreach ($candidateIds as $articleId) {
                    $result['skipped'][] = ['id' => $articleId, 'status' => 'ITEM 13/9 NO DISPONIBLE'];
                }
                return $result;
            }

            foreach ($candidateIds as $articleId) {
                $article = Article::query()->whereKey($articleId)->lockForUpdate()->first();
                if (! $article) {
                    $result['skipped'][] = ['id' => $articleId, 'status' => 'NO ENCONTRADO'];
                    continue;
                }
                if ($article->sunat_inventory_catalog_item_id !== null
                    || trim((string) $article->sunat_inventory_catalog_code) !== '') {
                    $result['already_configured'][] = $this->reference($article, 'YA CONFIGURADO');
                    continue;
                }

                $freshAudit = $this->analyze();
                $candidate = collect($freshAudit['safe_candidates'])->firstWhere('id', $article->id);
                if (! $candidate) {
                    $result['skipped'][] = $this->reference($article, 'YA NO ES CANDIDATO SEGURO');
                    continue;
                }

                $updated = DB::table('articles')
                    ->where('id', $article->id)
                    ->whereNull('sunat_inventory_catalog_item_id')
                    ->whereNull('sunat_inventory_catalog_code')
                    ->update([
                        'sunat_inventory_catalog_item_id' => $item->id,
                        'sunat_inventory_catalog_code' => trim((string) $article->code),
                    ]);
                if ($updated !== 1) {
                    $result['already_configured'][] = $this->reference($article, 'YA CONFIGURADO');
                    continue;
                }

                $result['applied'][] = array_merge($candidate, [
                    'sunat_inventory_catalog_item_id' => $item->id,
                    'status' => 'APLICADO',
                ]);
            }

            return $result;
        });
    }

    private function hasIdentificationColumns(): bool
    {
        return Schema::hasColumn('articles', 'sunat_inventory_catalog_item_id')
            && Schema::hasColumn('articles', 'sunat_inventory_catalog_code')
            && Schema::hasColumn('articles', 'sunat_standard_catalog_item_id')
            && Schema::hasColumn('articles', 'sunat_standard_code');
    }

    private function evidenceColumns(): array
    {
        return collect(array_merge(self::POSSIBLE_UNSPSC_COLUMNS, self::POSSIBLE_GTIN_COLUMNS))
            ->filter(fn (string $column) => Schema::hasColumn('articles', $column))
            ->values()
            ->all();
    }

    private function standardEvidence(Article $article, array $columns, bool $hasColumns): array
    {
        $unspsc = null;
        $gtin = null;
        foreach ($columns as $column) {
            $value = trim((string) $article->getAttribute($column));
            if ($value === '') {
                continue;
            }
            if (in_array($column, self::POSSIBLE_UNSPSC_COLUMNS, true)) {
                $unspsc = "{$column}: {$value}";
            }
            if (in_array($column, self::POSSIBLE_GTIN_COLUMNS, true)) {
                $gtin = "{$column}: {$value}";
            }
        }
        if ($hasColumns && $article->sunatStandardCatalogItem && trim((string) $article->sunat_standard_code) !== '') {
            $itemCode = (string) $article->sunatStandardCatalogItem->item_code;
            if ($itemCode === '1') {
                $unspsc = 'Tabla 13/1: '.$article->sunat_standard_code;
            } elseif ($itemCode === '3') {
                $gtin = 'Tabla 13/3: '.$article->sunat_standard_code;
            }
        }

        return ['unspsc' => $unspsc, 'gtin' => $gtin];
    }

    private function candidateRejectionReason(Article $article, $codeCounts, array $evidence): ?string
    {
        $code = trim((string) $article->code);
        $normalized = mb_strtoupper($code, 'UTF-8');
        if ($code === '') {
            return 'Article.code está vacío';
        }
        if (mb_strlen($code) > 24) {
            return 'Article.code supera 24 caracteres';
        }
        if (! preg_match('/^[\pL\pN][\pL\pN._\/-]*$/u', $code)) {
            return 'Article.code no cumple el formato alfanumérico seguro';
        }
        if (($codeCounts[$normalized] ?? 0) !== 1) {
            return 'Article.code no es único entre productos inventariables';
        }
        if ($evidence['unspsc'] || $evidence['gtin']) {
            return 'Existe evidencia de identificación UNSPSC o GS1/GTIN que requiere revisión humana';
        }

        return null;
    }

    private function validMainAssignment(Article $article): bool
    {
        $item = $article->sunatInventoryCatalogItem;
        $code = trim((string) $article->sunat_inventory_catalog_code);

        return $item
            && in_array((string) $item->item_code, ['1', '3', '9'], true)
            && $item->catalog_code === ArticleSunatInventoryCatalogPolicy::CATALOG_CODE
            && $item->status === 'ACTIVE'
            && $item->catalog?->code === ArticleSunatInventoryCatalogPolicy::CATALOG_CODE
            && $item->catalog->is_active
            && $code !== ''
            && mb_strlen($code) <= 24;
    }

    private function reference(Article $article, ?string $reason = null, ?string $suggestedCode = null, ?string $catalog = null): array
    {
        return array_filter([
            'id' => (int) $article->id,
            'article_code' => $article->code,
            'catalog' => $catalog,
            'suggested_code' => $suggestedCode,
            'length' => mb_strlen(trim((string) $article->code)),
            'reason' => $reason,
        ], fn ($value) => $value !== null);
    }
}
