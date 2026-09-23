<?php

namespace App\Services;

use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;

class WarehouseValuationPoolAuditService
{
    private const MONEY_TOLERANCE = 0.01;

    private const QUANTITY_TOLERANCE = 0.0001;

    private const UNIT_COST_TOLERANCE = 0.000001;

    public function audit(): array
    {
        $groups = [];

        foreach (WarehouseStock::query()
            ->select([
                'id',
                'company_id',
                'warehouse_id',
                'article_id',
                'current_quantity',
                'average_unit_cost',
                'total_cost',
            ])
            ->cursor() as $stock) {
            $key = $this->key($stock->company_id, $stock->warehouse_id, $stock->article_id);

            if (! isset($groups[$key])) {
                $groups[$key] = $this->emptyGroup(
                    $stock->company_id,
                    $stock->warehouse_id,
                    $stock->article_id
                );
            }

            $quantity = (float) $stock->current_quantity;
            $averageUnitCost = (float) $stock->average_unit_cost;
            $totalCost = (float) $stock->total_cost;

            $groups[$key]['stock_rows']++;
            $groups[$key]['quantity'] += $quantity;
            $groups[$key]['total_cost'] += $totalCost;
            $groups[$key]['unit_costs'][number_format($averageUnitCost, 6, '.', '')] = true;

            if ($stock->company_id === null) {
                $groups[$key]['missing_company']++;
            }

            if ($stock->warehouse_id === null) {
                $groups[$key]['missing_warehouse']++;
            }

            if ($stock->article_id === null) {
                $groups[$key]['missing_article']++;
            }

            if ($quantity < 0) {
                $groups[$key]['negative_quantity']++;
            }

            if ($averageUnitCost < 0) {
                $groups[$key]['negative_average_cost']++;
            }

            if ($totalCost < 0) {
                $groups[$key]['negative_total_cost']++;
            }

            if (abs($quantity) <= self::QUANTITY_TOLERANCE
                && abs($totalCost) > self::MONEY_TOLERANCE) {
                $groups[$key]['valued_residue']++;
            }

            if (abs(($quantity * $averageUnitCost) - $totalCost) > self::MONEY_TOLERANCE) {
                $groups[$key]['row_value_difference']++;
            }
        }

        $existingPools = [];

        foreach (WarehouseValuationPool::query()
            ->select([
                'company_id',
                'warehouse_id',
                'article_id',
                'current_quantity',
                'average_unit_cost',
                'total_cost',
            ])
            ->cursor() as $pool) {
            $existingPools[$this->key($pool->company_id, $pool->warehouse_id, $pool->article_id)] = $pool;
        }

        $details = [];
        $pools = [];
        $summary = [
            'pools_analyzed' => 0,
            'safe' => 0,
            'manual_review' => 0,
            'fragmented_ppm' => 0,
            'negative_stocks' => 0,
            'valued_residues' => 0,
            'inconsistent_existing_pools' => 0,
            'orphan_pools' => 0,
        ];

        foreach ($groups as $key => $group) {
            $observations = [];
            $requiresManualReview = false;
            $quantity = $group['quantity'];
            $totalCost = $group['total_cost'];
            $candidatePpm = null;

            if ($quantity > self::QUANTITY_TOLERANCE) {
                $candidatePpm = $totalCost / $quantity;
            } elseif (abs($quantity) <= self::QUANTITY_TOLERANCE) {
                $candidatePpm = 0.0;

                if (abs($totalCost) > self::MONEY_TOLERANCE) {
                    $observations[] = 'Cantidad agregada cero con valor residual.';
                    $requiresManualReview = true;
                }
            } else {
                $observations[] = 'Cantidad agregada negativa; no existe un PPM candidato seguro.';
                $requiresManualReview = true;
            }

            $this->appendCountObservation(
                $observations,
                $group['missing_company'],
                'fila(s) con company_id faltante'
            );
            $this->appendCountObservation(
                $observations,
                $group['missing_warehouse'],
                'fila(s) con warehouse_id faltante'
            );
            $this->appendCountObservation(
                $observations,
                $group['missing_article'],
                'fila(s) con article_id faltante'
            );
            $this->appendCountObservation(
                $observations,
                $group['negative_quantity'],
                'stock(s) con cantidad negativa'
            );
            $this->appendCountObservation(
                $observations,
                $group['negative_average_cost'],
                'stock(s) con average_unit_cost negativo'
            );
            $this->appendCountObservation(
                $observations,
                $group['negative_total_cost'],
                'stock(s) con total_cost negativo'
            );
            $this->appendCountObservation(
                $observations,
                $group['valued_residue'],
                'stock(s) con cantidad cero y valor residual'
            );
            $this->appendCountObservation(
                $observations,
                $group['row_value_difference'],
                'stock(s) con diferencia mayor a S/ 0.01 entre cantidad por costo y total_cost'
            );

            if ($group['missing_company'] > 0
                || $group['missing_warehouse'] > 0
                || $group['missing_article'] > 0
                || $group['negative_quantity'] > 0
                || $group['negative_average_cost'] > 0
                || $group['negative_total_cost'] > 0
                || $group['valued_residue'] > 0
                || $group['row_value_difference'] > 0) {
                $requiresManualReview = true;
            }

            if (count($group['unit_costs']) > 1) {
                $observations[] = 'PPM fragmentado: existen varios average_unit_cost en el pool.';
                $summary['fragmented_ppm']++;
            }

            $summary['negative_stocks'] += $group['negative_quantity'];
            $summary['valued_residues'] += $group['valued_residue'];

            if (isset($existingPools[$key])) {
                $existing = $existingPools[$key];
                unset($existingPools[$key]);

                if ($this->existingPoolDiffers($existing, $quantity, $candidatePpm, $totalCost)) {
                    $observations[] = 'El pool existente no coincide con el estado agregado calculado.';
                    $requiresManualReview = true;
                    $summary['inconsistent_existing_pools']++;
                }
            }

            $summary['pools_analyzed']++;
            $summary[$requiresManualReview ? 'manual_review' : 'safe']++;

            $poolResult = $this->detail($group, $candidatePpm, $requiresManualReview, $observations);
            $pools[] = $poolResult;

            if ($observations !== []) {
                $details[] = $poolResult;
            }
        }

        foreach ($existingPools as $pool) {
            $summary['pools_analyzed']++;
            $summary['manual_review']++;
            $summary['orphan_pools']++;

            $poolResult = [
                'company_id' => $pool->company_id,
                'warehouse_id' => $pool->warehouse_id,
                'article_id' => $pool->article_id,
                'stock_rows' => 0,
                'quantity' => 0.0,
                'current_quantity' => 0.0,
                'total_cost' => 0.0,
                'candidate_ppm' => 0.0,
                'average_unit_cost_candidate' => 0.0,
                'status' => 'REVISION_MANUAL',
                'observations' => ['Pool existente sin warehouse_stocks correspondientes.'],
            ];

            $pools[] = $poolResult;
            $details[] = $poolResult;
        }

        return [
            'summary' => $summary,
            'details' => $details,
            'pools' => $pools,
        ];
    }

    private function emptyGroup(mixed $companyId, mixed $warehouseId, mixed $articleId): array
    {
        return [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'article_id' => $articleId,
            'stock_rows' => 0,
            'quantity' => 0.0,
            'total_cost' => 0.0,
            'unit_costs' => [],
            'missing_company' => 0,
            'missing_warehouse' => 0,
            'missing_article' => 0,
            'negative_quantity' => 0,
            'negative_average_cost' => 0,
            'negative_total_cost' => 0,
            'valued_residue' => 0,
            'row_value_difference' => 0,
        ];
    }

    private function key(mixed $companyId, mixed $warehouseId, mixed $articleId): string
    {
        return implode(':', [
            $companyId === null ? 'NULL' : (string) $companyId,
            $warehouseId === null ? 'NULL' : (string) $warehouseId,
            $articleId === null ? 'NULL' : (string) $articleId,
        ]);
    }

    private function appendCountObservation(array &$observations, int $count, string $message): void
    {
        if ($count > 0) {
            $observations[] = $count.' '.$message.'.';
        }
    }

    private function existingPoolDiffers(
        WarehouseValuationPool $pool,
        float $quantity,
        ?float $candidatePpm,
        float $totalCost
    ): bool {
        if (abs(((float) $pool->current_quantity) - $quantity) > self::QUANTITY_TOLERANCE
            || abs(((float) $pool->total_cost) - $totalCost) > self::MONEY_TOLERANCE) {
            return true;
        }

        return $candidatePpm === null
            || abs(((float) $pool->average_unit_cost) - $candidatePpm) > self::UNIT_COST_TOLERANCE;
    }

    private function detail(
        array $group,
        ?float $candidatePpm,
        bool $requiresManualReview,
        array $observations
    ): array {
        return [
            'company_id' => $group['company_id'],
            'warehouse_id' => $group['warehouse_id'],
            'article_id' => $group['article_id'],
            'stock_rows' => $group['stock_rows'],
            'quantity' => $group['quantity'],
            'current_quantity' => $group['quantity'],
            'total_cost' => $group['total_cost'],
            'candidate_ppm' => $candidatePpm,
            'average_unit_cost_candidate' => $candidatePpm,
            'status' => $requiresManualReview ? 'REVISION_MANUAL' : 'SEGURO',
            'observations' => $observations,
        ];
    }
}
