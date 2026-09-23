<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\WarehouseKardexMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class WarehousePleReadinessService
{
    public const BOOK_PHYSICAL = '120100';

    public const BOOK_VALUED = '130100';

    public function __construct(
        private readonly WarehousePhysicalInventoryRegisterService $physicalRegisterService,
        private readonly WarehouseValuedInventoryRegisterService $valuedRegisterService,
        private readonly WarehouseInventoryPeriodClosureService $periodClosureService
    ) {}

    /**
     * Audita si el período tiene información suficiente para construir los TXT PLE
     * 12.1 y 13.1 sin inventar referencias contables.
     */
    public function audit(int $companyId, int $year, int $month, int $warehouseId): array
    {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEndExclusive = $periodStart->addMonth();
        $company = Company::query()->findOrFail($companyId);
        $warehouse = Warehouse::query()->findOrFail($warehouseId);

        $physical = $this->physicalRegisterService->generate($companyId, $year, $month, $warehouseId);
        $valued = $this->valuedRegisterService->generate($companyId, $year, $month, $warehouseId);

        $movementQuery = WarehouseKardexMovement::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', '!=', 'cancelled')
            ->where('movement_date', '>=', $periodStart)
            ->where('movement_date', '<', $periodEndExclusive);

        $movementCount = (clone $movementQuery)->count();
        $articleIds = (clone $movementQuery)->pluck('article_id')->filter()->unique()->values();
        $hasContent = $movementCount > 0
            || collect($physical['registers'] ?? [])->contains(
                fn (array $register) => $this->nonZero($register['opening_balance'] ?? '0')
            );

        $blockers = [];
        $warnings = [];

        if (! preg_match('/^\d{11}$/', (string) $company->ruc)) {
            $this->push($blockers, 'invalid_ruc', 'El RUC de la empresa debe contener exactamente 11 dígitos para el nombre oficial del archivo PLE.');
        }

        if (! $this->periodClosureService->isClosed($companyId, $warehouseId, $periodStart)) {
            $this->push($blockers, 'period_not_closed', 'El período debe estar cerrado antes de preparar un archivo electrónico definitivo.');
        }

        if ((int) ($physical['incomplete_snapshot_count'] ?? 0) > 0) {
            $this->push(
                $blockers,
                'physical_snapshots_incomplete',
                'El Formato 12.1 contiene movimientos con snapshots SUNAT/documentales incompletos.',
                ['count' => (int) $physical['incomplete_snapshot_count']]
            );
        }

        if ((int) ($valued['incomplete_snapshot_count'] ?? 0) > 0) {
            $this->push(
                $blockers,
                'valued_snapshots_incomplete',
                'El Formato 13.1 contiene movimientos con snapshots SUNAT/documentales incompletos.',
                ['count' => (int) $valued['incomplete_snapshot_count']]
            );
        }

        if ((int) ($valued['valuation_inconsistency_count'] ?? 0) > 0) {
            $this->push(
                $blockers,
                'valuation_inconsistency',
                'El Formato 13.1 tiene inconsistencias de valorización que deben resolverse antes de exportar.',
                ['count' => (int) $valued['valuation_inconsistency_count']]
            );
        }

        $physicalPrecisionIssues = $this->physicalPrecisionIssues($physical['registers'] ?? []);
        if ($physicalPrecisionIssues > 0) {
            $this->push(
                $blockers,
                'physical_quantity_precision',
                'El PLE 12.1 admite hasta 2 decimales en cantidades y existen valores históricos con mayor precisión no nula. No se redondearán silenciosamente.',
                ['count' => $physicalPrecisionIssues]
            );
        }

        if ($hasContent) {
            $this->auditAccountingReferences($movementQuery, $blockers);
            $this->auditOpeningBalances($physical['registers'] ?? [], $valued['registers'] ?? [], $blockers);
        }

        if ($articleIds->isNotEmpty()) {
            $standardCodeCount = Schema::hasColumn('articles', 'sunat_standard_code')
                ? \App\Models\Article::query()
                    ->whereIn('id', $articleIds)
                    ->whereNotNull('sunat_standard_code')
                    ->where('sunat_standard_code', '!=', '')
                    ->count()
                : 0;

            if ($standardCodeCount > 0) {
                $this->push(
                    $warnings,
                    'secondary_catalog_not_snapshotted',
                    'Existen artículos con código estándar SUNAT/UNSPSC/GTIN actual, pero el Kardex no conserva todavía ese dato como snapshot histórico. La obligatoriedad condicional del campo 8/9 debe resolverse sin tomar el maestro actual como verdad histórica.',
                    ['articles' => $standardCodeCount]
                );
            }
        }

        if ($hasContent) {
            $this->push(
                $warnings,
                'ple_correction_state_not_tracked',
                'El sistema todavía no registra constancias/envíos PLE; por tanto no puede determinar automáticamente si una rectificación debe usar estado 8 o 9. La preparación actual corresponde al flujo inicial del período.'
            );
        }

        $periodCode = sprintf('%04d%02d00', $year, $month);
        $contentIndicator = $hasContent ? '1' : '0';

        return [
            'company' => $company,
            'warehouse' => $warehouse,
            'year' => $year,
            'month' => $month,
            'period_code' => $periodCode,
            'period_closed' => $this->periodClosureService->isClosed($companyId, $warehouseId, $periodStart),
            'movement_count' => $movementCount,
            'has_content' => $hasContent,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'blocker_count' => count($blockers),
            'warning_count' => count($warnings),
            'can_generate_official_txt' => $blockers === [],
            'physical' => [
                'book_code' => self::BOOK_PHYSICAL,
                'register_count' => count($physical['registers'] ?? []),
                'filename' => $this->officialFilename((string) $company->ruc, $year, $month, self::BOOK_PHYSICAL, $contentIndicator),
                'incomplete_snapshot_count' => (int) ($physical['incomplete_snapshot_count'] ?? 0),
                'precision_issue_count' => $physicalPrecisionIssues,
            ],
            'valued' => [
                'book_code' => self::BOOK_VALUED,
                'register_count' => count($valued['registers'] ?? []),
                'filename' => $this->officialFilename((string) $company->ruc, $year, $month, self::BOOK_VALUED, $contentIndicator),
                'incomplete_snapshot_count' => (int) ($valued['incomplete_snapshot_count'] ?? 0),
                'valuation_inconsistency_count' => (int) ($valued['valuation_inconsistency_count'] ?? 0),
            ],
        ];
    }

    /**
     * Nombre PLE: LE + RUC + AAAA + MM + 00 + identificador libro + 00 + O + I + M + 1.
     * O=1 empresa operativa, I=0/1 contenido, M=1 soles, último 1 generado para PLE.
     */
    public function officialFilename(
        string $ruc,
        int $year,
        int $month,
        string $bookCode,
        string $contentIndicator = '1'
    ): string {
        return sprintf(
            'LE%s%04d%02d00%s001%s11.TXT',
            preg_replace('/\D+/', '', $ruc),
            $year,
            $month,
            $bookCode,
            $contentIndicator
        );
    }

    private function auditAccountingReferences($movementQuery, array &$blockers): void
    {
        $cuoColumn = 'accounting_cuo_snapshot';
        $correlativeColumn = 'accounting_entry_correlative_snapshot';

        if (! Schema::hasColumn('warehouse_kardex_movements', $cuoColumn)
            || ! Schema::hasColumn('warehouse_kardex_movements', $correlativeColumn)) {
            $this->push(
                $blockers,
                'accounting_reference_structure_missing',
                'SUNAT exige CUO y correlativo del asiento contable en los campos 2 y 3 de los registros 12.1 y 13.1. DROPAIVSYS2 todavía no tiene una base contable que genere y preserve esas referencias; no se crearán CUO ficticios.'
            );

            return;
        }

        $missing = (clone $movementQuery)
            ->where(function ($query) use ($cuoColumn, $correlativeColumn) {
                $query->whereNull($cuoColumn)
                    ->orWhere($cuoColumn, '')
                    ->orWhereNull($correlativeColumn)
                    ->orWhere($correlativeColumn, '');
            })
            ->count();

        if ($missing > 0) {
            $this->push(
                $blockers,
                'accounting_reference_missing',
                'Existen movimientos sin CUO o correlativo de asiento contable. Deben provenir de la integración contable real antes de generar el TXT oficial.',
                ['count' => $missing]
            );
        }
    }

    private function auditOpeningBalances(array $physicalRegisters, array $valuedRegisters, array &$blockers): void
    {
        $physicalOpenings = collect($physicalRegisters)->filter(
            fn (array $register) => $this->nonZero($register['opening_balance'] ?? '0')
        )->count();
        $valuedOpenings = collect($valuedRegisters)->filter(
            fn (array $register) => $this->nonZero($register['initial_quantity'] ?? '0')
                || $this->nonZero($register['initial_total_cost'] ?? '0')
        )->count();

        if ($physicalOpenings > 0 || $valuedOpenings > 0) {
            $this->push(
                $blockers,
                'opening_balance_accounting_reference_missing',
                'SUNAT indica que la primera tupla corresponde al saldo inicial y también exige CUO/correlativo. El sistema aún no dispone de una referencia contable específica para el saldo inicial del período; debe provenir del Libro Diario/integración contable.',
                ['physical_registers' => $physicalOpenings, 'valued_registers' => $valuedOpenings]
            );
        }
    }

    private function physicalPrecisionIssues(array $registers): int
    {
        $issues = 0;

        foreach ($registers as $register) {
            if ($this->hasMoreThanScale($register['opening_balance'] ?? '0', 2)) {
                $issues++;
            }

            foreach ($register['rows'] ?? [] as $row) {
                if ($this->hasMoreThanScale($row['quantity_in'] ?? '0', 2)
                    || $this->hasMoreThanScale($row['quantity_out'] ?? '0', 2)) {
                    $issues++;
                }
            }
        }

        return $issues;
    }

    private function hasMoreThanScale(mixed $value, int $scale): bool
    {
        $decimal = ltrim(trim((string) $value), '+-');
        [, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return trim(substr($fraction, $scale), '0') !== '';
    }

    private function nonZero(mixed $value): bool
    {
        return abs((float) $value) > 0.0000001;
    }

    private function push(array &$target, string $code, string $message, array $meta = []): void
    {
        $target[] = [
            'code' => $code,
            'message' => $message,
            'meta' => $meta,
        ];
    }
}
