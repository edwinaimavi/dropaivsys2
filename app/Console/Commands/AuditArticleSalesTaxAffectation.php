<?php

namespace App\Console\Commands;

use App\Services\ArticleSalesTaxAffectationAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class AuditArticleSalesTaxAffectation extends Command
{
    protected $signature = 'articles:audit-sales-tax-affectation
                            {--apply : Aplica únicamente is_taxable=true → 10 GRAVADO}';

    protected $description = 'Audita la afectación tributaria de venta 10/20/30 de los artículos';

    public function handle(ArticleSalesTaxAffectationAuditService $service): int
    {
        $apply = (bool) $this->option('apply');
        $protectedBefore = $this->protectedFingerprint();
        $result = $service->analyze();

        if (! hash_equals($protectedBefore, $this->protectedFingerprint())) {
            $this->error('La auditoría detectó cambios inesperados en datos protegidos.');
            return self::FAILURE;
        }

        $this->info('Auditoría de afectación tributaria de venta - Modo '.($apply ? 'APPLY SEGURO' : 'DRY-RUN'));
        $this->line($apply
            ? 'Solo se aplicará is_taxable=true → 10 — GRAVADO. Los false permanecen ambiguos.'
            : 'Este comando no modifica artículos.');

        if (! $result['migration_applied']) {
            $this->warn('La migración de Fase 2.6 todavía no está aplicada.');
            if ($apply) {
                $this->error('No se puede aplicar hasta que exista sales_tax_affectation_code.');
                return self::FAILURE;
            }
        }

        $this->render($result);

        if (! $apply) {
            $this->info('Integridad verificada: artículos, stock, Kardex, cotizaciones, órdenes, facturas, cantidades, costos, PPM e IGV histórico permanecen sin cambios.');
            return self::SUCCESS;
        }

        $before = $this->taxSnapshot();
        try {
            $application = DB::transaction(function () use ($service, $protectedBefore, $before) {
                $application = $service->applySafeCandidates();
                if (! hash_equals($protectedBefore, $this->protectedFingerprint())) {
                    throw new RuntimeException('Se detectó un cambio fuera de sales_tax_affectation_code.');
                }
                $this->assertOnlySafeChanges($before, $this->taxSnapshot(), $application['applied']);

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
        $this->info('Integridad verificada: solo se completaron candidatos seguros 10 — GRAVADO.');

        return self::SUCCESS;
    }

    private function render(array $result): void
    {
        $this->table(['Clasificación', 'Total'], [
            ['Artículos', $result['total']],
            ['Configurados 10/20/30', count($result['configured'])],
            ['Candidatos seguros 10', count($result['safe_candidates'])],
            ['Ambiguos 20/30', count($result['ambiguous'])],
            ['Conflictos', count($result['conflicts'])],
        ]);

        if ($result['safe_candidates']) {
            $this->table(['ID', 'Código', 'Artículo', 'Sugerencia', 'Criterio'], array_map(
                fn (array $row) => [$row['id'], $row['article_code'], $row['billing_name'], '10 — GRAVADO', $row['reason']],
                $result['safe_candidates']
            ));
        }

        if ($result['ambiguous']) {
            $this->table(['ID', 'Código', 'Artículo', 'Motivo'], array_map(
                fn (array $row) => [$row['id'], $row['article_code'], $row['billing_name'], $row['reason']],
                $result['ambiguous']
            ));
        }

        if ($result['conflicts']) {
            $this->table(['ID', 'Código', 'Artículo', 'Conflicto'], array_map(
                fn (array $row) => [$row['id'], $row['article_code'], $row['billing_name'], $row['reason']],
                $result['conflicts']
            ));
        }
    }

    private function protectedFingerprint(): string
    {
        $tables = [
            'articles', 'warehouse_stocks', 'warehouse_kardex_movements',
            'quotes', 'quote_items', 'customer_purchase_orders', 'customer_purchase_order_items',
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
                $columns = array_values(array_diff($columns, ['sales_tax_affectation_code']));
            }
            $snapshot[$table] = DB::table($table)->orderBy('id')->get($columns)->all();
        }

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }

    private function taxSnapshot(): array
    {
        if (! Schema::hasColumn('articles', 'sales_tax_affectation_code')) {
            return [];
        }

        return DB::table('articles')->orderBy('id')->pluck('sales_tax_affectation_code', 'id')->all();
    }

    private function assertOnlySafeChanges(array $before, array $after, array $applied): void
    {
        $expected = collect($applied)->keyBy('id');
        foreach ($after as $articleId => $code) {
            if (($before[$articleId] ?? null) === $code) {
                continue;
            }
            $row = $expected->get((int) $articleId);
            if (! $row || ($before[$articleId] ?? null) !== null || $code !== '10') {
                throw new RuntimeException("Cambio no autorizado en artículo {$articleId}.");
            }
        }
    }
}
