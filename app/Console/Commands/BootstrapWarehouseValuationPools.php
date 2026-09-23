<?php

namespace App\Console\Commands;

use App\Models\WarehouseValuationPool;
use App\Services\WarehouseValuationPoolAuditService;
use Illuminate\Console\Command;

class BootstrapWarehouseValuationPools extends Command
{
    protected $signature = 'inventory:bootstrap-valuation-pools
                            {--apply-safe : Inserta únicamente pools seguros inexistentes}';

    protected $description = 'Prepara o inserta de forma segura los pools iniciales de valorización PPM';

    public function handle(WarehouseValuationPoolAuditService $auditService): int
    {
        $audit = $auditService->audit();
        $existingPools = WarehouseValuationPool::query()
            ->get([
                'company_id',
                'warehouse_id',
                'article_id',
                'current_quantity',
                'average_unit_cost',
                'total_cost',
            ])
            ->keyBy(fn (WarehouseValuationPool $pool) => $this->key(
                $pool->company_id,
                $pool->warehouse_id,
                $pool->article_id
            ));

        $summary = [
            'audited' => count($audit['pools']),
            'safe' => 0,
            'candidates' => 0,
            'existing_correct' => 0,
            'conflicts' => 0,
            'manual_review' => 0,
        ];
        $candidates = [];

        foreach ($audit['pools'] as $pool) {
            $key = $this->key($pool['company_id'], $pool['warehouse_id'], $pool['article_id']);
            $existing = $existingPools->get($key);

            if ($pool['status'] !== 'SEGURO') {
                if ($existing !== null && $this->hasExistingPoolConflict($pool)) {
                    $summary['conflicts']++;
                } else {
                    $summary['manual_review']++;
                }

                continue;
            }

            $summary['safe']++;

            if ($existing !== null) {
                if ($this->matches($existing, $pool)) {
                    $summary['existing_correct']++;
                } else {
                    $summary['conflicts']++;
                }

                continue;
            }

            $summary['candidates']++;
            $candidates[] = $pool;
        }

        $inserted = 0;

        if ($this->option('apply-safe')) {
            foreach ($candidates as $candidate) {
                $pool = WarehouseValuationPool::query()->firstOrCreate(
                    [
                        'company_id' => $candidate['company_id'],
                        'warehouse_id' => $candidate['warehouse_id'],
                        'article_id' => $candidate['article_id'],
                    ],
                    [
                        'current_quantity' => $candidate['current_quantity'],
                        'average_unit_cost' => $candidate['average_unit_cost_candidate'],
                        'total_cost' => $candidate['total_cost'],
                        'created_by' => null,
                        'updated_by' => null,
                    ]
                );

                if ($pool->wasRecentlyCreated) {
                    $inserted++;
                }
            }
        }

        $this->line('Pools auditados: '.$summary['audited']);
        $this->line('Pools seguros: '.$summary['safe']);
        $this->line('Nuevos pools candidatos: '.$summary['candidates']);
        $this->line('Pools existentes correctos: '.$summary['existing_correct']);
        $this->line('Pools con conflicto: '.$summary['conflicts']);
        $this->line('Pools omitidos por revisión manual: '.$summary['manual_review']);
        $this->line(
            'Escrituras realizadas: '.($this->option('apply-safe') ? 'SÍ ('.$inserted.')' : 'NO')
        );

        if ($candidates !== []) {
            $this->newLine();
            $this->table(
                [
                    'company_id',
                    'warehouse_id',
                    'article_id',
                    'current_quantity',
                    'average_unit_cost',
                    'total_cost',
                ],
                array_map(fn (array $candidate) => [
                    $candidate['company_id'],
                    $candidate['warehouse_id'],
                    $candidate['article_id'],
                    number_format($candidate['current_quantity'], 4, '.', ''),
                    number_format($candidate['average_unit_cost_candidate'], 6, '.', ''),
                    number_format($candidate['total_cost'], 2, '.', ''),
                ], $candidates)
            );
        }

        return self::SUCCESS;
    }

    private function key(mixed $companyId, mixed $warehouseId, mixed $articleId): string
    {
        return implode(':', [
            $companyId === null ? 'NULL' : (string) $companyId,
            $warehouseId === null ? 'NULL' : (string) $warehouseId,
            $articleId === null ? 'NULL' : (string) $articleId,
        ]);
    }

    private function matches(WarehouseValuationPool $existing, array $candidate): bool
    {
        return abs(((float) $existing->current_quantity) - $candidate['current_quantity']) <= 0.0001
            && abs(((float) $existing->average_unit_cost) - $candidate['average_unit_cost_candidate']) <= 0.000001
            && abs(((float) $existing->total_cost) - $candidate['total_cost']) <= 0.01;
    }

    private function hasExistingPoolConflict(array $pool): bool
    {
        foreach ($pool['observations'] as $observation) {
            if ($observation === 'El pool existente no coincide con el estado agregado calculado.') {
                return true;
            }
        }

        return false;
    }
}
