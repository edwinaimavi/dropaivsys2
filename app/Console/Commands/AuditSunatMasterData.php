<?php

namespace App\Console\Commands;

use App\Services\SunatMasterDataAuditService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class AuditSunatMasterData extends Command
{
    protected $signature = 'sunat:audit-master-data
                            {--apply-safe : Aplica solo backfills ya considerados inequívocos por las auditorías especializadas}
                            {--details : Muestra pendientes manuales y conflictos relevantes}';

    protected $description = 'Auditor integral Fase 2.8 para maestros SUNAT e inventario histórico';

    public function handle(SunatMasterDataAuditService $service): int
    {
        $apply = (bool) $this->option('apply-safe');

        $this->info('FASE 2.8 — Auditoría integral de maestros SUNAT / inventario');
        $this->line($apply
            ? 'Modo APPLY-SAFE: solo se ejecutan correcciones inequívocas ya protegidas por las fases 2.1–2.7.'
            : 'Modo DRY-RUN: no se modifica ningún dato.');
        $this->warn('La afectación 10/20/30 NO se regulariza en el maestro de Artículos: desde 2.6 es un dato transaccional por línea comercial.');

        try {
            if ($apply) {
                $result = $service->applySafe();
                $this->renderApplications($result['applications']);
                $audit = $result['after'];
            } else {
                $audit = $service->audit();
            }
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('La auditoría no pudo completarse: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->renderAudit($audit);

        if ($audit['totals']['inconsistent'] > 0) {
            $this->error('Hay inconsistencias que requieren revisión antes de cualquier aplicación adicional.');
        } elseif ($audit['totals']['manual'] > 0) {
            $this->warn('Quedan registros para revisión manual. No deben completarse por inferencia.');
        } else {
            $this->info('No quedan inconsistencias ni pendientes manuales en los controles auditados.');
        }

        $this->newLine();
        $this->comment('Los totales son hallazgos por control; no representan registros únicos del sistema.');
        $this->info('La auditoría no fusiona lotes, no recalcula PPM, no revaloriza Kardex y no altera importes históricos.');

        return self::SUCCESS;
    }

    private function renderAudit(array $audit): void
    {
        $this->newLine();
        $this->table(
            ['Control', 'Correctos', 'Autocorregibles', 'Revisión manual', 'Inconsistentes', 'Nota'],
            array_map(fn (array $row) => [
                $row['name'],
                $row['correct'],
                $row['auto_correctable'],
                $row['manual'],
                $row['inconsistent'],
                $row['note'],
            ], $audit['controls'])
        );

        $this->table(['Resumen', 'Total'], [
            ['Correctos', $audit['totals']['correct']],
            ['Autocorregibles', $audit['totals']['auto_correctable']],
            ['Revisión manual', $audit['totals']['manual']],
            ['Inconsistentes', $audit['totals']['inconsistent']],
        ]);

        if (! $this->option('details')) {
            return;
        }

        $this->renderEstablishmentDetails($audit['establishments']);
        $this->renderConflictDetails($audit);
    }

    private function renderApplications(array $applications): void
    {
        $ownership = $applications['ownership'];
        $classification = $applications['classification'];
        $units = $applications['units'];
        $catalog13 = $applications['catalog13'];
        $valuation = $applications['valuation'];

        $this->newLine();
        $this->comment('Cambios APPLY-SAFE realizados');
        $this->table(['Área', 'Aplicados'], [
            ['Empresa/almacén - movimientos', $ownership['movements']['resolvable'] ?? 0],
            ['Empresa/almacén - stocks', $ownership['stocks']['resolvable'] ?? 0],
            ['Relaciones empresa-almacén', $ownership['company_warehouses']['creatable'] ?? 0],
            ['Clasificación inventariable', $classification['applied'] ?? 0],
            ['Tabla 06 - unidades', count($units['applied'] ?? [])],
            ['Tabla 13 - identificación', count($catalog13['applied'] ?? [])],
            ['Tabla 14 - valorización', $valuation['applied'] ?? 0],
        ]);
        $this->line('Tabla 05 y códigos de establecimiento SUNAT permanecen sin autoaplicar.');
    }

    private function renderEstablishmentDetails(array $report): void
    {
        $pending = array_values(array_filter(
            $report['rows'] ?? [],
            fn (array $row) => $row['code'] === null
        ));

        if ($pending === []) {
            return;
        }

        $this->newLine();
        $this->comment('Establecimientos que requieren código SUNAT oficial');
        $this->table(
            ['Empresa', 'RUC', 'Almacén', 'Estado'],
            array_map(fn (array $row) => [
                $row['company'] ?: '#'.$row['company_id'],
                $row['ruc'] ?: '—',
                $row['warehouse'] ?: '#'.$row['warehouse_id'],
                $row['status'],
            ], $pending)
        );
    }

    private function renderConflictDetails(array $audit): void
    {
        $groups = [
            'Clasificación de artículos' => $audit['classification']['conflicts'] ?? [],
            'SUNAT Tabla 05' => $audit['existence']['conflicts'] ?? [],
            'SUNAT Tabla 06' => $audit['units']['conflicts'] ?? [],
            'SUNAT Tabla 13' => $audit['catalog13']['conflicts'] ?? [],
        ];

        foreach ($groups as $label => $rows) {
            if ($rows === []) {
                continue;
            }

            $this->newLine();
            $this->error($label.' — conflictos');
            $this->table(
                ['ID', 'Referencia', 'Motivo'],
                array_map(function (array $row): array {
                    $reference = $row['article_code']
                        ?? $row['abbreviation']
                        ?? $row['code']
                        ?? '—';
                    $reason = $row['reason'] ?? $row['status'] ?? 'Revisión requerida';

                    return [$row['id'] ?? '—', $reference, $reason];
                }, $rows)
            );
        }

        $ownershipDetails = array_merge(
            $audit['ownership']['movements']['details'] ?? [],
            $audit['ownership']['stocks']['details'] ?? []
        );
        $ownershipConflicts = array_values(array_filter(
            $ownershipDetails,
            fn (array $row) => ($row['status'] ?? '') === 'CONFLICT'
        ));
        if ($ownershipConflicts !== []) {
            $this->newLine();
            $this->error('Empresa / almacén — conflictos');
            $this->table(
                ['ID', 'Referencia', 'Empresa actual', 'Candidatas'],
                array_map(fn (array $row) => [
                    $row['id'] ?? '—',
                    $row['movement_number'] ?? $row['stock_key'] ?? '—',
                    $row['current_company_id'] ?? 'NULL',
                    implode(',', $row['candidate_company_ids'] ?? []) ?: 'ninguna',
                ], $ownershipConflicts)
            );
        }
    }
}
