<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\Unit;
use App\Services\ArticleSalesTaxAffectationAuditService;

beforeEach(function () {
    $this->category = Category::create([
        'code' => 'CAT-TAX-SALE',
        'description' => 'AFECTACIÓN VENTA',
        'type' => 'ARTICLE',
        'status' => 'ACTIVE',
    ]);
    $this->unit = Unit::create([
        'abbreviation' => 'UND-TAX',
        'description' => 'UNIDAD TAX',
        'decimal_quantity' => false,
        'status' => 'ACTIVE',
    ]);
});

function salesTaxArticle(string $code, bool $legacyTaxable, ?string $newCode = null): Article
{
    return Article::create([
        'code' => $code,
        'category_id' => test()->category->id,
        'unit_id' => test()->unit->id,
        'legal_name' => $code,
        'billing_name' => $code,
        'is_taxable' => $legacyTaxable,
        'sales_tax_affectation_code' => $newCode,
        'status' => 'ACTIVE',
    ]);
}

it('audita true como candidato seguro 10 y false como ambiguo 20 30', function () {
    salesTaxArticle('TAX-SAFE', true);
    salesTaxArticle('TAX-AMB', false);

    $result = app(ArticleSalesTaxAffectationAuditService::class)->analyze();

    expect(collect($result['safe_candidates'])->pluck('article_code')->all())->toContain('TAX-SAFE')
        ->and(collect($result['ambiguous'])->pluck('article_code')->all())->toContain('TAX-AMB')
        ->and($result['conflicts'])->toBeEmpty();
});

it('apply seguro solo completa true a 10 y es idempotente', function () {
    $safe = salesTaxArticle('TAX-APPLY', true);
    $ambiguous = salesTaxArticle('TAX-NO-APPLY', false);
    $service = app(ArticleSalesTaxAffectationAuditService::class);

    $first = $service->applySafeCandidates();
    $second = $service->applySafeCandidates();

    expect($safe->fresh()->sales_tax_affectation_code)->toBe('10')
        ->and($ambiguous->fresh()->sales_tax_affectation_code)->toBeNull()
        ->and(collect($first['applied'])->pluck('id')->all())->toContain($safe->id)
        ->and($second['applied'])->toBeEmpty();
});

it('reporta conflicto si el nuevo código contradice is_taxable legacy', function () {
    salesTaxArticle('TAX-CONFLICT', true, '20');

    $result = app(ArticleSalesTaxAffectationAuditService::class)->analyze();

    expect(collect($result['conflicts'])->pluck('article_code')->all())->toContain('TAX-CONFLICT');
});
