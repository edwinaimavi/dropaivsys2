<?php

namespace App\Console\Commands;

use App\Services\CompanyInventoryValuationAuditService;
use Illuminate\Console\Command;

class AuditCompanyInventoryValuationMethod extends Command
{
    protected $signature = 'companies:audit-inventory-valuation-method {--apply : Aplica únicamente candidatos seguros al método 1 — PROMEDIO PONDERADO}';

    protected $description = 'Audita la configuración SUNAT Tabla 14 de método de valuación por empresa';

    public function handle(CompanyInventoryValuationAuditService $service): int
    {
        $apply = (bool) $this->option('apply');
        $this->info('Auditoría SUNAT Tabla 14 - '.($apply ? 'Modo APPLY SEGURO' : 'Modo DRY-RUN'));

        if (! $apply) {
            $this->line('Este comando no modifica empresas, stocks ni Kardex.');
        } else {
            $this->line('Solo se configurará 1 — PROMEDIO PONDERADO en empresas con historial de inventario y sin método asignado.');
        }

        $report = $apply ? $service->apply() : $service->audit();

        $this->table(
            ['Clasificación', 'Total'],
            [
                ['Empresas', $report['total_companies']],
                ['Con historial de inventario', $report['inventory_companies']],
                ['Configuradas Tabla 14', $report['configured']],
                ['Candidatas seguras a 1', $report['safe_candidates']],
                ['Conflictos', $report['conflicts']],
                ['Sin inventario', $report['without_inventory']],
                ['Aplicadas', $report['applied']],
            ]
        );

        if ($report['rows'] !== []) {
            $this->table(
                ['ID', 'RUC', 'Empresa', 'Historial', 'Actual', 'Sugerido', 'Estado'],
                collect($report['rows'])->map(fn (array $row) => [
                    $row['id'],
                    $row['ruc'],
                    $row['company'],
                    $row['has_inventory_history'] ? 'SÍ' : 'NO',
                    $row['current_code'] ?: '—',
                    $row['suggested_code'] ?: '—',
                    $row['status'],
                ])->all()
            );
        }

        $this->newLine();
        $this->line('Motor actual detectado en código: promedio ponderado móvil.');
        $this->line('Importante: la valorización operativa sigue almacenándose por warehouse_stock; la clave actual incluye lote y vencimiento.');
        $this->line('Pools potencialmente fragmentados (misma empresa + almacén + artículo con más de un stock): '.$report['fragmented_valuation_pools'].'.');
        $this->warn('Esta fase formaliza la política Tabla 14. NO fusiona lotes, NO recalcula PPM y NO revaloriza el Kardex histórico.');

        if ($report['conflicts'] > 0) {
            $this->error('Se detectaron conflictos. No continúe con un apply adicional hasta revisarlos.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
