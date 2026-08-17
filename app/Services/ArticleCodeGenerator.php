<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArticleCodeGenerator
{
    private const PREFIX = 'ART';

    private const PADDING = 5;

    private const MAX_CREATE_ATTEMPTS = 3;

    public function next(bool $lockForUpdate = false): string
    {
        $query = Article::withTrashed()
            ->where('code', 'like', self::PREFIX.'%');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $highestNumber = $query
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
        for ($attempt = 1; $attempt <= self::MAX_CREATE_ATTEMPTS; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes): Article {
                    $code = $this->next(lockForUpdate: true);

                    return Article::create([
                        ...$attributes,
                        'code' => $code,
                    ]);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isArticleCodeCollision($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'code' => 'El código automático estaba desactualizado. Intente nuevamente o recargue el código.',
        ]);
    }

    private function format(int $number): string
    {
        return self::PREFIX.str_pad((string) $number, self::PADDING, '0', STR_PAD_LEFT);
    }

    private function isArticleCodeCollision(UniqueConstraintViolationException $exception): bool
    {
        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        return str_contains($message, 'articles_code_unique')
            || str_contains($message, 'articles.code');
    }
}
