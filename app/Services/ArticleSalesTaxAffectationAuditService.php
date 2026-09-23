<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ArticleSalesTaxAffectationAuditService
{
    public function analyze(): array
    {
        $migrationApplied = Schema::hasColumn('articles', 'sales_tax_affectation_code');
        $columns = ['id', 'code', 'billing_name', 'is_taxable'];
        if ($migrationApplied) {
            $columns[] = 'sales_tax_affectation_code';
        }

        $articles = Article::query()->select($columns)->orderBy('id')->get();
        $result = [
            'migration_applied' => $migrationApplied,
            'total' => $articles->count(),
            'configured' => [],
            'safe_candidates' => [],
            'ambiguous' => [],
            'conflicts' => [],
        ];

        foreach ($articles as $article) {
            $code = $migrationApplied ? trim((string) $article->sales_tax_affectation_code) : '';

            if ($code !== '') {
                if (! in_array($code, ArticleSalesTaxPolicy::ALLOWED, true)) {
                    $result['conflicts'][] = $this->row($article, 'Código 10/20/30 no válido');
                    continue;
                }

                $expectedTaxable = $code === ArticleSalesTaxPolicy::TAXABLE;
                if ((bool) $article->is_taxable !== $expectedTaxable) {
                    $result['conflicts'][] = $this->row($article, 'El código tributario no coincide con el booleano legacy is_taxable');
                    continue;
                }

                $result['configured'][] = $this->row($article, 'Configurado', $code);
                continue;
            }

            if ((bool) $article->is_taxable) {
                $result['safe_candidates'][] = $this->row(
                    $article,
                    'is_taxable=true permite migración inequívoca a 10 — GRAVADO',
                    ArticleSalesTaxPolicy::TAXABLE
                );
            } else {
                $result['ambiguous'][] = $this->row(
                    $article,
                    'is_taxable=false no permite distinguir 20 — EXONERADO de 30 — INAFECTO'
                );
            }
        }

        return $result;
    }

    public function applySafeCandidates(): array
    {
        if (! Schema::hasColumn('articles', 'sales_tax_affectation_code')) {
            return ['migration_applied' => false, 'applied' => [], 'already_configured' => [], 'skipped' => []];
        }

        return DB::transaction(function () {
            $result = ['migration_applied' => true, 'applied' => [], 'already_configured' => [], 'skipped' => []];
            $candidateIds = collect($this->analyze()['safe_candidates'])->pluck('id');

            foreach ($candidateIds as $articleId) {
                $article = Article::query()->whereKey($articleId)->lockForUpdate()->first();
                if (! $article) {
                    $result['skipped'][] = ['id' => $articleId, 'reason' => 'NO ENCONTRADO'];
                    continue;
                }

                if (trim((string) $article->sales_tax_affectation_code) !== '') {
                    $result['already_configured'][] = $this->row($article, 'YA CONFIGURADO', $article->sales_tax_affectation_code);
                    continue;
                }

                if (! (bool) $article->is_taxable) {
                    $result['skipped'][] = $this->row($article, 'YA NO ES CANDIDATO SEGURO');
                    continue;
                }

                $updated = DB::table('articles')
                    ->where('id', $article->id)
                    ->whereNull('sales_tax_affectation_code')
                    ->where('is_taxable', true)
                    ->update(['sales_tax_affectation_code' => ArticleSalesTaxPolicy::TAXABLE]);

                if ($updated !== 1) {
                    $result['already_configured'][] = $this->row($article, 'YA CONFIGURADO');
                    continue;
                }

                $result['applied'][] = $this->row($article, 'APLICADO', ArticleSalesTaxPolicy::TAXABLE);
            }

            return $result;
        });
    }

    private function row(Article $article, string $reason, ?string $suggested = null): array
    {
        return array_filter([
            'id' => (int) $article->id,
            'article_code' => $article->code,
            'billing_name' => $article->billing_name,
            'suggested_code' => $suggested,
            'reason' => $reason,
        ], fn ($value) => $value !== null);
    }
}
