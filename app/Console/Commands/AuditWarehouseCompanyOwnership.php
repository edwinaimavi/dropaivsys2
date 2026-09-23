<?php

namespace App\Console\Commands;

use App\Services\WarehouseCompanyOwnershipAuditService;
use Illuminate\Console\Command;

class AuditWarehouseCompanyOwnership extends Command
{
    protected $signature = 'warehouse:audit-company-ownership {--apply : Aplica solamente resoluciones inequívocas}';

    protected $description = 'Audita la empresa propietaria de stocks, movimientos y relaciones empresa-almacén';

    public function handle(WarehouseCompanyOwnershipAuditService $service): int
    {
        $apply = (bool) $this->option('apply');
        $report = $apply ? $service->apply() : $service->analyze();

        $this->info($apply ? 'MODO APPLY EXPLÍCITO' : 'DRY-RUN: no se modificó ningún registro');
        $this->table(
            ['Entidad', 'Analizados', 'Resueltos', 'Ya asignados', 'Ambiguos', 'Sin fuente', 'Conflictos'],
            [
                $this->summaryRow('Movimientos', $report['movements']),
                $this->summaryRow('Stocks', $report['stocks']),
            ]
        );
        $this->line(sprintf(
            'Relaciones empresa-almacén: %d candidatas, %d existentes, %d por crear.',
            $report['company_warehouses']['candidates'],
            $report['company_warehouses']['existing'],
            $report['company_warehouses']['creatable']
        ));
        $this->line('Los códigos de establecimiento SUNAT permanecen NULL.');

        foreach (['movements' => 'Movimiento', 'stocks' => 'Stock'] as $key => $label) {
            foreach ($report[$key]['details'] as $detail) {
                $identifier = $detail['movement_number'] ?? $detail['stock_key'] ?? '#'.$detail['id'];
                $this->warn(sprintf(
                    '%s %s: %s; empresa actual=%s; candidatas=%s',
                    $label,
                    $identifier,
                    $detail['status'],
                    $detail['current_company_id'] ?? 'NULL',
                    implode(',', $detail['candidate_company_ids']) ?: 'ninguna'
                ));
            }
        }

        return self::SUCCESS;
    }

    private function summaryRow(string $label, array $summary): array
    {
        return [
            $label,
            $summary['analyzed'],
            $summary['resolvable'],
            $summary['already_owned'],
            $summary['ambiguous'],
            $summary['without_source'],
            $summary['conflicts'],
        ];
    }
}
