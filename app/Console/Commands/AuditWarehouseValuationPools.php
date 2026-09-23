<?php

namespace App\Console\Commands;

use App\Services\WarehouseValuationPoolAuditService;
use Illuminate\Console\Command;

class AuditWarehouseValuationPools extends Command
{
    protected $signature = 'inventory:audit-valuation-pools';

    protected $description = 'Audita en modo lectura los pools candidatos de valorización PPM';

    public function handle(WarehouseValuationPoolAuditService $auditService): int
    {
        $result = $auditService->audit();
        $summary = $result['summary'];

        $this->line('Pools analizados: '.$summary['pools_analyzed']);
        $this->line('Seguros: '.$summary['safe']);
        $this->line('Revisión manual: '.$summary['manual_review']);
        $this->line('PPM fragmentados: '.$summary['fragmented_ppm']);
        $this->line('Stocks negativos: '.$summary['negative_stocks']);
        $this->line('Residuos valorizados: '.$summary['valued_residues']);
        $this->line('Pools existentes inconsistentes: '.$summary['inconsistent_existing_pools']);
        $this->line('Pools huérfanos: '.$summary['orphan_pools']);

        if ($result['details'] === []) {
            $this->newLine();
            $this->info('No se encontraron observaciones.');

            return self::SUCCESS;
        }

        $limit = 50;
        $details = array_slice($result['details'], 0, $limit);

        $this->newLine();
        $this->table(
            [
                'Empresa ID',
                'Warehouse ID',
                'Article ID',
                'Filas stock',
                'Cantidad agregada',
                'Valor agregado',
                'PPM candidato',
                'Estado',
                'Observaciones',
            ],
            array_map(fn (array $detail) => [
                $detail['company_id'] ?? 'NULL',
                $detail['warehouse_id'] ?? 'NULL',
                $detail['article_id'] ?? 'NULL',
                $detail['stock_rows'],
                number_format($detail['quantity'], 4, '.', ''),
                number_format($detail['total_cost'], 2, '.', ''),
                $detail['candidate_ppm'] === null
                    ? 'N/D'
                    : number_format($detail['candidate_ppm'], 6, '.', ''),
                $detail['status'],
                implode(' ', $detail['observations']),
            ], $details)
        );

        if (count($result['details']) > $limit) {
            $this->warn(
                'Se omitieron '.(count($result['details']) - $limit).' pool(es) con observaciones adicionales.'
            );
        }

        return self::SUCCESS;
    }
}
