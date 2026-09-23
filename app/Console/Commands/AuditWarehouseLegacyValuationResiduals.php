<?php

namespace App\Console\Commands;

use App\Services\WarehouseLegacyValuationResidualService;
use Illuminate\Console\Command;

class AuditWarehouseLegacyValuationResiduals extends Command
{
    protected $signature = 'inventory:audit-legacy-valuation-residuals
                            {--apply-safe : Crea únicamente compensaciones de valor clasificadas SAFE}';

    protected $description = 'Audita y compensa residuos valorizados legacy explicados por costos vinculados sin existencia';

    public function handle(WarehouseLegacyValuationResidualService $service): int
    {
        $apply = (bool) $this->option('apply-safe');
        $result = $service->audit($apply);
        $summary = $result['summary'];

        $this->info('MODO: '.($apply ? 'APPLY SAFE' : 'DRY-RUN'));
        $this->line('Grupos analizados: '.$summary['groups_analyzed']);
        $this->line('SAFE: '.$summary['safe']);
        $this->line('MANUAL_REVIEW: '.$summary['manual_review']);
        $this->line('Ya corregidos: '.$summary['already_corrected']);
        $this->line('CONFLICT: '.$summary['conflict']);
        $this->line('Correcciones creadas: '.$summary['corrections_created']);

        foreach ($result['groups'] as $group) {
            $this->newLine();
            $this->line(sprintf(
                '[%s] company=%d warehouse=%d article=%d | residuo Kardex=%s | valor pool=%s | corrección propuesta=%s',
                $group['classification'],
                $group['company_id'],
                $group['warehouse_id'],
                $group['article_id'],
                $group['kardex_residual'],
                $group['pool_value'] ?? 'NO EXISTE',
                $group['proposed_correction']
            ));

            if ($group['linked_costs'] !== []) {
                $this->table(
                    ['ID', 'Movimiento', 'Fecha', 'Importe'],
                    array_map(fn (array $movement): array => [
                        $movement['id'],
                        $movement['movement_number'],
                        $movement['movement_date'],
                        $movement['amount'],
                    ], $group['linked_costs'])
                );
            }

            foreach ($group['reasons'] as $reason) {
                $this->warn('- '.$reason);
            }
        }

        return self::SUCCESS;
    }
}
