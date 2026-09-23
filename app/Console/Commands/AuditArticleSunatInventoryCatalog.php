<?php

namespace App\Console\Commands;

use App\Services\ArticleSunatInventoryCatalogAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AuditArticleSunatInventoryCatalog extends Command
{
    protected $signature = 'articles:audit-sunat-inventory-catalog
                            {--apply : Aplica únicamente candidatos seguros como 9 - OTROS + copia de Article.code}';

    protected $description = 'Audita y aplica de forma opcional la identificación de existencias del catálogo SUNAT 13';

    public function handle(ArticleSunatInventoryCatalogAuditService $service): int
    {
        $apply = (bool) $this->option('apply');
        $protectedBefore = $this->protectedFingerprint();
        $result = $service->analyze();

        if (! hash_equals($protectedBefore, $this->protectedFingerprint())) {
            $this->error('La auditoría detectó una variación inesperada en los datos protegidos.');
            return self::FAILURE;
        }

        $this->info('Auditoría SUNAT Tabla 13 - Modo '.($apply ? 'APPLY SEGURO' : 'DRY-RUN'));
        $this->line($apply
            ? 'Solo se copiará Article.code para candidatos inequívocos usando 9 - OTROS.'
            : 'Este comando no escribe ni aplica identificaciones.');

        if (! $result['migration_applied']) {
            $this->warn('La migración de Fase 2.5 aún no está aplicada; los inventariables se evalúan como pendientes.');
            if ($apply) {
                $this->error('No es posible aplicar candidatos hasta que existan los campos de Fase 2.5.');
                return self::FAILURE;
            }
        }

        $this->renderAudit($result);
        if (! $apply) {
            $this->info('Integridad verificada: artículos, cantidades, costos, Kardex, PPM, IGV, company_id y Tablas 05/06 permanecen sin cambios.');
            return self::SUCCESS;
        }

        $identificationBefore = $this->identificationSnapshot();
        try {
            $application = DB::transaction(function () use ($service, $protectedBefore, $identificationBefore) {
                $application = $service->applySafeCandidates();
                if (! hash_equals($protectedBefore, $this->protectedFingerprint())) {
                    throw new RuntimeException('Se detectó una modificación fuera de la identificación principal Tabla 13.');
                }
                $this->assertOnlyAppliedIdentificationsChanged(
                    $identificationBefore,
                    $this->identificationSnapshot(),
                    $application['applied']
                );

                return $application;
            });
        } catch (Throwable $exception) {
            $this->error('Aplicación cancelada y revertida: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->table(['Resultado', 'Total'], [
            ['APLICADO', count($application['applied'])],
            ['YA CONFIGURADO', count($result['configured']) + count($application['already_configured'])],
            ['OMITIDO', count($application['skipped'])],
        ]);
        $this->info('Integridad verificada: solo cambiaron catálogo principal y copia de Article.code en candidatos seguros.');

        return self::SUCCESS;
    }

    private function renderAudit(array $result): void
    {
        $this->table(['Clasificación', 'Total'], [
            ['Artículos', $result['total_articles']],
            ['Inventariables', $result['inventory_articles']],
            ['Configurados Tabla 13', count($result['configured'])],
            ['Pendientes', count($result['pending'])],
            ['Candidatos seguros 9 - OTROS', count($result['safe_candidates'])],
            ['UNSPSC detectados', count($result['unspsc_detected'])],
            ['GS1/GTIN detectados', count($result['gtin_detected'])],
            ['Ambiguos', count($result['ambiguous'])],
            ['Conflictos', count($result['conflicts'])],
        ]);
        if ($result['safe_candidates']) {
            $this->table(['ID', 'Article.code', 'Longitud', 'Catálogo sugerido', 'Código sugerido', 'Criterio'], array_map(
                fn (array $row) => [$row['id'], $row['article_code'], $row['length'], '9 - OTROS', $row['suggested_code'], $row['reason']],
                $result['safe_candidates']
            ));
        }
        if ($result['ambiguous']) {
            $this->table(['ID', 'Article.code', 'Motivo'], array_map(
                fn (array $row) => [$row['id'], $row['article_code'], $row['reason']],
                $result['ambiguous']
            ));
        }
        if ($result['conflicts']) {
            $this->table(['ID', 'Article.code', 'Conflicto'], array_map(
                fn (array $row) => [$row['id'], $row['article_code'], $row['reason']],
                $result['conflicts']
            ));
        }
    }

    private function protectedFingerprint(): string
    {
        $tables = [
            'articles', 'units', 'warehouse_stocks', 'warehouse_kardex_movements',
            'warehouse_entries', 'warehouse_entry_items', 'warehouse_dispatches',
            'warehouse_dispatch_items', 'customer_returns', 'customer_return_items',
            'electronic_invoices', 'electronic_invoice_items',
        ];
        $snapshot = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $snapshot[$table] = [];
                continue;
            }
            $columns = Schema::getColumnListing($table);
            if ($table === 'articles') {
                $columns = array_values(array_diff($columns, [
                    'sunat_inventory_catalog_item_id', 'sunat_inventory_catalog_code',
                ]));
            }
            $snapshot[$table] = DB::table($table)->orderBy('id')->get($columns)->all();
        }

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function identificationSnapshot(): array
    {
        if (! Schema::hasColumn('articles', 'sunat_inventory_catalog_item_id')) {
            return [];
        }

        return DB::table('articles')->orderBy('id')->get([
            'id', 'sunat_inventory_catalog_item_id', 'sunat_inventory_catalog_code',
        ])->mapWithKeys(fn ($row) => [(int) $row->id => [
            'item_id' => $row->sunat_inventory_catalog_item_id,
            'code' => $row->sunat_inventory_catalog_code,
        ]])->all();
    }

    private function assertOnlyAppliedIdentificationsChanged(array $before, array $after, array $applied): void
    {
        $expected = collect($applied)->keyBy('id');
        foreach ($after as $articleId => $values) {
            if (($before[$articleId] ?? null) === $values) {
                continue;
            }
            $row = $expected->get((int) $articleId);
            if (! $row
                || ($before[$articleId]['item_id'] ?? null) !== null
                || ($before[$articleId]['code'] ?? null) !== null
                || (int) $values['item_id'] !== (int) $row['sunat_inventory_catalog_item_id']
                || $values['code'] !== $row['suggested_code']) {
                throw new RuntimeException("Cambio no autorizado detectado en el artículo {$articleId}.");
            }
        }
    }
}
