<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Database\UniqueConstraintViolationException;

class ArticleCodeGenerator
{
    private const PREFIX = 'ART';

    private const PADDING = 6;

    private const MAX_CREATE_ATTEMPTS = 10;

    public function next(): string
    {
        $highestNumber = Article::withTrashed()
            ->where('code', 'like', self::PREFIX.'%')
            ->pluck('code')
            ->reduce(function (int $highest, ?string $code): int {
                if (! preg_match('/^'.self::PREFIX.'(\d+)$/i', (string) $code, $matches)) {
                    return $highest;
                }

                return max($highest, (int) $matches[1]);
            }, 0);

        return $this->format($highestNumber + 1);
    }

    /**
     * Creates an article with a server-confirmed automatic code.
     *
     * The unique database index remains the final concurrency guard. If
     * another request takes the candidate first, the next number is tried.
     */
    public function create(array $attributes): Article
    {
        $code = $this->next();

        for ($attempt = 1; $attempt <= self::MAX_CREATE_ATTEMPTS; $attempt++) {
            try {
                return Article::create([
                    ...$attributes,
                    'code' => $code,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isArticleCodeCollision($exception) || $attempt === self::MAX_CREATE_ATTEMPTS) {
                    throw $exception;
                }

                $code = $this->format($this->numberFrom($code) + 1);
            }
        }

        throw new \RuntimeException('No se pudo confirmar un código automático para el artículo.');
    }

    private function format(int $number): string
    {
        return self::PREFIX.str_pad((string) $number, self::PADDING, '0', STR_PAD_LEFT);
    }

    private function numberFrom(string $code): int
    {
        return (int) substr($code, strlen(self::PREFIX));
    }

    private function isArticleCodeCollision(UniqueConstraintViolationException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        return str_contains($message, 'articles_code_unique')
            || str_contains($message, 'articles.code');
    }
}
