<?php

namespace App\Console\Commands;

use App\Services\WarehouseStockValuationNormalizationService;
use Illuminate\Console\Command;

class NormalizeWarehouseStockValuation extends Command
{
    protected $signature = 'warehouse:normalize-stock-valuation
        {--dry-run : Audita los stocks que serían normalizados sin modificarlos}
        {--apply : Aplica la normalización del estado actual}
        {--expect-count= : Cantidad esperada de stocks para la ejecución}
        {--expect-total= : Valor residual total esperado para la ejecución}
        {--expect-id=* : IDs esperados; puede repetirse}';

    protected $description = 'Audita stocks agotados; la normalización legacy está deshabilitada por el pool global PPM.';

    public function handle(WarehouseStockValuationNormalizationService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');

        if ($dryRun === $apply) {
            $this->error('Indique exactamente una opción: --dry-run o --apply.');

            return self::INVALID;
        }

        if ($apply) {
            $this->error(WarehouseStockValuationNormalizationService::MUTATION_DISABLED_MESSAGE);

            return self::FAILURE;
        }

        $audit = $service->audit();
        $this->renderReport($audit, 'DRY-RUN');

        return self::SUCCESS;
    }

    private function renderReport(array $report, string $mode): void
    {
        $this->info($mode.' — Normalización de valoración actual');
        $this->table(
            ['ID', 'Artículo', 'Lote', 'Cantidad', 'Reservada', 'Costo promedio', 'Valor actual', 'Nuevo valor'],
            collect($report['stocks'])->map(fn (array $stock) => [
                $stock['id'],
                $stock['article_code'] ?: '-',
                $stock['lot_number'] ?: 'SIN LOTE',
                number_format($stock['current_quantity'], 4, '.', ''),
                number_format($stock['reserved_quantity'], 4, '.', ''),
                number_format($stock['average_unit_cost'], 6, '.', ''),
                number_format($stock['total_cost'], 2, '.', ''),
                '0.00',
            ])->all()
        );
        $this->line("Registros detectados: {$report['count']}");
        $this->line('Valor residual detectado: S/ '.number_format($report['residual_total'], 2));
    }
}
