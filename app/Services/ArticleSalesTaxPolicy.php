<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Validation\ValidationException;

class ArticleSalesTaxPolicy
{
    public const TAXABLE = '10';
    public const EXONERATED = '20';
    public const UNAFFECTED = '30';

    public const ALLOWED = [
        self::TAXABLE,
        self::EXONERATED,
        self::UNAFFECTED,
    ];

    public function validate(mixed $code, string $field = 'sales_tax_affectation_code'): string
    {
        $normalized = trim((string) $code);

        if (! in_array($normalized, self::ALLOWED, true)) {
            throw ValidationException::withMessages([
                $field => 'Seleccione una afectación tributaria de venta válida: 10 Gravado, 20 Exonerado o 30 Inafecto.',
            ]);
        }

        return $normalized;
    }

    public function assertConfigured(Article $article, string $field = 'article_id'): string
    {
        $code = trim((string) $article->sales_tax_affectation_code);

        if (! in_array($code, self::ALLOWED, true)) {
            throw ValidationException::withMessages([
                $field => 'El artículo no tiene configurada su afectación tributaria de venta (10/20/30).',
            ]);
        }

        return $code;
    }

    public function isTaxable(string $code): bool
    {
        return $code === self::TAXABLE;
    }

    public function label(?string $code): string
    {
        return match ($code) {
            self::TAXABLE => '10 — GRAVADO',
            self::EXONERATED => '20 — EXONERADO',
            self::UNAFFECTED => '30 — INAFECTO',
            default => 'PENDIENTE',
        };
    }
}
