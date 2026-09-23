<?php

namespace App\Console\Commands;

use App\Services\ArticleSunatExistenceTypeAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditArticleSunatExistenceType extends Command
{
    protected $signature = 'articles:audit-sunat-existence-type';

    protected $description = 'Audita en modo lectura la clasificación SUNAT 05 de productos inventariables';

    public function handle(ArticleSunatExistenceTypeAuditService $service): int
    {
        $inventoryFingerprintBefore = $this->inventoryFingerprint();
        $result = $service->analyze();
        $inventoryFingerprintAfter = $this->inventoryFingerprint();

        if (! hash_equals($inventoryFingerprintBefore, $inventoryFingerprintAfter)) {
            $this->error('La auditoría detectó una variación inesperada en los datos físicos.');

            return self::FAILURE;
        }

        $this->info('Auditoría de Tipo de Existencia SUNAT - Modo DRY-RUN');
        $this->line('Este comando no escribe ni aplica clasificaciones.');

        if (! $result['migration_applied']) {
            $this->warn('La migración de Fase 2.3 aún no está aplicada; los inventariables se reportan como pendientes.');
        }

        $this->table(['Clasificación', 'Total'], [
            ['Artículos', $result['total_articles']],
            ['Inventariables', $result['inventory_articles']],
            ['Configurados Tabla 05', count($result['configured'])],
            ['Inventariables pendientes', count($result['pending'])],
            ['Candidatos seguros', count($result['safe_candidates'])],
            ['Ambiguos', count($result['ambiguous'])],
            ['Conflictos', count($result['conflicts'])],
        ]);

        $this->newLine();
        $this->comment('Criterio conservador: no se propone 01 - MERCADERÍAS sin evidencia de compra a terceros, almacenamiento y venta sin transformación, y sin uso como materia prima, activo fijo o suministro interno.');
        $this->info('Integridad verificada: cantidades, costos, Kardex, company_id y clasificación operativa permanecen sin cambios.');

        return self::SUCCESS;
    }

    private function inventoryFingerprint(): string
    {
        $definitions = [
            'articles' => ['id', 'item_kind', 'is_inventory_item', 'sunat_existence_type_item_id'],
            'warehouse_stocks' => ['id', 'company_id', 'current_quantity', 'reserved_quantity', 'average_unit_cost', 'total_cost'],
            'warehouse_kardex_movements' => [
                'id', 'company_id', 'quantity_in', 'quantity_out', 'balance_quantity',
                'unit_cost', 'total_cost_in', 'total_cost_out', 'average_unit_cost', 'balance_total_cost',
            ],
        ];
        $snapshot = [];

        foreach ($definitions as $table => $columns) {
            if (! Schema::hasTable($table)) {
                $snapshot[$table] = [];
                continue;
            }

            $availableColumns = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn($table, $column)
            ));
            $snapshot[$table] = DB::table($table)
                ->orderBy('id')
                ->get($availableColumns)
                ->all();
        }

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
