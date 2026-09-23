<?php

namespace App\Services;

use App\Models\WarehouseStock;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStockValuationNormalizationService
{
    public const ZERO_QUANTITY_TOLERANCE = 0.0001;

    public const MUTATION_DISABLED_MESSAGE = 'La normalización legacy de warehouse_stocks está deshabilitada porque el inventario utiliza el pool global de valorización PPM. Esta operación podría desincronizar stock, Kardex y warehouse_valuation_pools.';

    public function audit(): array
    {
        return $this->report($this->candidates()->get());
    }

    public function normalize(): array
    {
        throw new \LogicException(self::MUTATION_DISABLED_MESSAGE);
    }

    public function normalizeIfMatches(int $expectedCount, float $expectedTotal, array $expectedIds): ?array
    {
        throw new \LogicException(self::MUTATION_DISABLED_MESSAGE);
    }

    private function candidates(): Builder
    {
        return WarehouseStock::query()
            ->with('article:id,code')
            ->where('status', 'ACTIVE')
            ->whereBetween('current_quantity', [-self::ZERO_QUANTITY_TOLERANCE, 0])
            ->where(function (Builder $query) {
                $query->where('current_quantity', '!=', 0)
                    ->orWhere('average_unit_cost', '!=', 0)
                    ->orWhere('total_cost', '!=', 0);
            })
            ->orderBy('id');
    }

    private function report($stocks): array
    {
        return [
            'count' => $stocks->count(),
            'residual_total' => round((float) $stocks->sum('total_cost'), 2),
            'stocks' => $stocks->map(fn (WarehouseStock $stock) => [
                'id' => $stock->id,
                'article_code' => $stock->article?->code,
                'lot_number' => $stock->lot_number,
                'current_quantity' => (float) $stock->current_quantity,
                'reserved_quantity' => (float) $stock->reserved_quantity,
                'average_unit_cost' => (float) $stock->average_unit_cost,
                'total_cost' => (float) $stock->total_cost,
            ])->values()->all(),
        ];
    }
}
