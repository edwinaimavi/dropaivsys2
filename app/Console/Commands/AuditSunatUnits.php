<?php

namespace App\Console\Commands;

use App\Services\UnitSunatAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AuditSunatUnits extends Command
{
    protected $signature = 'units:audit-sunat-unit
                            {--apply : Aplica únicamente candidatos seguros con confianza alta}';

    protected $description = 'Audita y, con --apply, aplica equivalencias seguras de unidades con SUNAT Tabla 06';

    public function handle(UnitSunatAuditService $service): int
    {
        $apply = (bool) $this->option('apply');
        $before = $this->fingerprint(true);
        $result = $service->analyze();

        if (! hash_equals($before, $this->fingerprint(true))) {
            $this->error('La auditoría detectó una variación inesperada en los datos operativos.');
            return self::FAILURE;
        }

        $this->info('Auditoría SUNAT Tabla 06 - Modo '.($apply ? 'APPLY SEGURO' : 'DRY-RUN'));
        $this->line($apply
            ? 'Solo se actualizará units.sunat_unit_item_id para candidatos seguros aún no configurados.'
            : 'Este comando no escribe ni aplica equivalencias.');

        if (! $result['migration_applied']) {
            $this->warn('La migración de Fase 2.4 aún no está aplicada; todas las unidades se evalúan como no configuradas.');
            if ($apply) {
                $this->error('No es posible aplicar mappings hasta que exista units.sunat_unit_item_id.');
                return self::FAILURE;
            }
        }

        $this->renderAudit($result, $apply);

        if (! $apply) {
            $this->info('Integridad verificada: unidades, artículos, stocks, Kardex, comprobantes, cantidades, costos y company_id permanecen sin cambios.');
            return self::SUCCESS;
        }

        $protectedBefore = $this->fingerprint(false);
        $mappingBefore = $this->mappingSnapshot();
        try {
            $application = DB::transaction(function () use ($service, $protectedBefore, $mappingBefore) {
                $application = $service->applySafeCandidates();
                if (! hash_equals($protectedBefore, $this->fingerprint(false))) {
                    throw new RuntimeException('Se detectó una modificación fuera de units.sunat_unit_item_id.');
                }
                $this->assertOnlyAppliedMappingsChanged($mappingBefore, $this->mappingSnapshot(), $application['applied']);

                return $application;
            });
        } catch (Throwable $exception) {
            $this->error('Aplicación cancelada y revertida: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->table(['Resultado', 'Total'], [
            ['APLICADA', count($application['applied'])],
            ['YA CONFIGURADA', count($result['configured']) + count($application['already_configured'])],
            ['OMITIDA', count($application['skipped'])],
        ]);
        if ($application['applied']) {
            $this->table(['ID', 'Unidad interna', 'Código SUNAT', 'Descripción SUNAT', 'Estado'], array_map(
                fn ($row) => [$row['id'], $row['abbreviation'], $row['suggested_code'], $row['sunat_description'], $row['status']],
                $application['applied']
            ));
        }
        $this->info('Integridad verificada: solo cambiaron mappings seguros en units.sunat_unit_item_id; datos operativos, cantidades y costos permanecen intactos.');

        return self::SUCCESS;
    }

    private function renderAudit(array $result, bool $apply): void
    {
        $this->table(['Clasificación', 'Total'], [
            ['Unidades internas', $result['total_units']],
            ['Configuradas Tabla 06', count($result['configured'])],
            ['Usadas por artículos', $result['used_by_articles']],
            ['Usadas por inventariables', $result['used_by_inventory']],
            ['Candidatos seguros', count($result['safe_candidates'])],
            ['Ambiguas', count($result['ambiguous'])],
            ['Conflictos', count($result['conflicts'])],
            ['Sin uso', count($result['unused'])],
        ]);
        if ($result['safe_candidates']) {
            $this->newLine();
            $this->comment($apply ? 'Candidatos seguros a aplicar' : 'Sugerencias conservadoras (no aplicadas)');
            $this->table(['ID', 'Abrev.', 'Descripción', 'Código sugerido', 'Descripción SUNAT', 'Criterio'], array_map(
                fn ($row) => [$row['id'], $row['abbreviation'], $row['description'], $row['suggested_code'], $row['sunat_description'], $row['reason']],
                $result['safe_candidates']
            ));
        }
        if ($result['configured']) {
            $this->newLine();
            $this->comment('Mappings existentes preservados');
            $this->table(['ID', 'Abrev.', 'Descripción', 'Código SUNAT', 'Estado'], array_map(
                fn ($row) => [$row['id'], $row['abbreviation'], $row['description'], $row['suggested_code'], 'YA CONFIGURADA'],
                $result['configured']
            ));
        }
        if ($result['ambiguous']) {
            $this->newLine();
            $this->comment('Unidades que requieren validación manual');
            $this->table(['ID', 'Abrev.', 'Descripción', 'Motivo'], array_map(
                fn ($row) => [$row['id'], $row['abbreviation'], $row['description'], $row['reason']],
                $result['ambiguous']
            ));
        }
        if ($result['conflicts']) {
            $this->newLine();
            $this->error('Conflictos detectados');
            $this->table(['ID', 'Abrev.', 'Descripción', 'Motivo'], array_map(
                fn ($row) => [$row['id'], $row['abbreviation'], $row['description'], $row['reason']],
                $result['conflicts']
            ));
        }
    }

    private function fingerprint(bool $includeMapping): string
    {
        $definitions = [
            'units' => array_values(array_filter(
                ['id', 'abbreviation', 'description', 'decimal_quantity', 'observation', 'status', 'created_by', 'updated_by', 'deleted_by', 'created_at', 'updated_at', 'deleted_at', 'sunat_unit_item_id'],
                fn ($column) => $includeMapping || $column !== 'sunat_unit_item_id'
            )),
            'articles' => null,
            'warehouse_stocks' => null,
            'warehouse_kardex_movements' => null,
            'electronic_invoice_items' => null,
        ];
        $snapshot = [];
        foreach ($definitions as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $snapshot[$table] = [];
                continue;
            }
            $available = $columns === null
                ? Schema::getColumnListing($table)
                : array_values(array_filter($columns, fn ($column) => Schema::hasColumn($table, $column)));
            $snapshot[$table] = DB::table($table)->orderBy('id')->get($available)->all();
        }

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function mappingSnapshot(): array
    {
        if (! Schema::hasTable('units') || ! Schema::hasColumn('units', 'sunat_unit_item_id')) {
            return [];
        }

        return DB::table('units')->orderBy('id')->pluck('sunat_unit_item_id', 'id')->all();
    }

    private function assertOnlyAppliedMappingsChanged(array $before, array $after, array $applied): void
    {
        $expected = collect($applied)->keyBy('id');
        foreach ($after as $unitId => $itemId) {
            $previous = $before[$unitId] ?? null;
            if ((string) $previous === (string) $itemId) {
                continue;
            }
            $row = $expected->get((int) $unitId);
            if (! $row || $previous !== null || (int) $itemId !== (int) $row['sunat_unit_item_id']) {
                throw new RuntimeException("Cambio no autorizado detectado en la unidad {$unitId}.");
            }
        }
    }
}
