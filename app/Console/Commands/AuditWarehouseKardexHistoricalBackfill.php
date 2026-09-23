<?php

namespace App\Console\Commands;

use App\Services\WarehouseKardexHistoricalBackfillService;
use Illuminate\Console\Command;

class AuditWarehouseKardexHistoricalBackfill extends Command
{
    protected $signature = 'inventory:audit-kardex-backfill
                            {--apply-safe : Completa únicamente snapshots NULL clasificados SAFE}';

    protected $description = 'Audita snapshots históricos de Kardex y aplica únicamente evidencia histórica segura';

    public function handle(WarehouseKardexHistoricalBackfillService $service): int
    {
        $apply = (bool) $this->option('apply-safe');
        $result = $service->audit($apply);
        $summary = $result['summary'];

        $this->newLine();
        $this->info('MODO: '.($apply ? 'APPLY SAFE' : 'DRY-RUN'));
        $this->line('Movimientos analizados: '.$summary['movements_analyzed']);
        $this->line('Completos: '.$summary['already_complete']);
        $this->line('Con faltantes: '.$summary['with_missing']);
        $this->line('SAFE: '.$summary['safe']);
        $this->line('MANUAL_REVIEW: '.$summary['manual_review']);
        $this->line('SOURCE_MISSING: '.$summary['source_missing']);
        $this->line('CONFLICT: '.$summary['conflict']);
        $this->line('Campos SAFE: '.$summary['safe_fields']);
        $this->line('Campos manuales: '.$summary['manual_fields']);
        $this->line('Campos con origen faltante: '.$summary['missing_source_fields']);
        $this->line('Campos aplicados: '.$summary['applied_fields']);

        $this->newLine();
        $this->table(
            ['Campo', 'safe', 'manual', 'missing'],
            collect($result['field_summary'])->map(
                fn (array $counts, string $field): array => [
                    $field,
                    $counts['safe'],
                    $counts['manual_review'],
                    $counts['source_missing'],
                ]
            )->values()->all()
        );

        if ($result['details'] !== []) {
            $limit = 100;
            $details = array_slice($result['details'], 0, $limit);
            $this->newLine();
            $this->table(
                ['ID', 'Movimiento', 'Empresa', 'Almacén', 'Artículo', 'Tipo', 'Operación', 'Origen', 'Origen ID', 'Campo', 'Clasificación', 'Propuesto', 'Fuente', 'Motivo'],
                array_map(fn (array $detail): array => [
                    $detail['movement_id'],
                    $detail['movement_number'],
                    $detail['company_id'] ?? 'NULL',
                    $detail['warehouse_id'] ?? 'NULL',
                    $detail['article_id'] ?? 'NULL',
                    $detail['movement_type'],
                    $detail['operation_type'],
                    $detail['source_type'] ?? 'NULL',
                    $detail['source_id'] ?? 'NULL',
                    $detail['field'],
                    $detail['classification'],
                    $detail['proposed_value'] ?? 'NULL',
                    $detail['source'],
                    $detail['reason'],
                ], $details)
            );

            if (count($result['details']) > $limit) {
                $this->warn('Se omitieron '.(count($result['details']) - $limit).' detalle(s) adicionales.');
            }
        }

        return self::SUCCESS;
    }
}
