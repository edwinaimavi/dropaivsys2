<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SunatMasterDataAuditService
{
    public function __construct(
        private readonly WarehouseCompanyOwnershipAuditService $warehouseOwnership,
        private readonly ArticleInventoryClassificationAuditService $articleClassification,
        private readonly ArticleSunatExistenceTypeAuditService $existenceType,
        private readonly UnitSunatAuditService $unitMapping,
        private readonly ArticleSunatInventoryCatalogAuditService $inventoryCatalog,
        private readonly CompanyInventoryValuationAuditService $valuationMethod,
    ) {
    }

    public function audit(): array
    {
        $ownership = $this->warehouseOwnership->analyze();
        $classification = $this->articleClassification->analyze(false);
        $existence = $this->existenceType->analyze();
        $units = $this->unitMapping->analyze();
        $catalog13 = $this->inventoryCatalog->analyze();
        $valuation = $this->valuationMethod->audit();
        $establishments = $this->auditEstablishments();

        $controls = [
            $this->ownershipControl($ownership),
            $this->classificationControl($classification),
            $this->existenceControl($existence),
            $this->unitControl($units),
            $this->catalog13Control($catalog13),
            $this->valuationControl($valuation),
            $this->establishmentControl($establishments),
        ];

        return [
            'ownership' => $ownership,
            'classification' => $classification,
            'existence' => $existence,
            'units' => $units,
            'catalog13' => $catalog13,
            'valuation' => $valuation,
            'establishments' => $establishments,
            'controls' => $controls,
            'totals' => $this->controlTotals($controls),
        ];
    }

    public function applySafe(): array
    {
        $beforeAudit = $this->audit();
        if ($beforeAudit['totals']['inconsistent'] > 0) {
            throw new RuntimeException(
                'Se detectaron inconsistencias. Corríjalas o revíselas antes de ejecutar --apply-safe.'
            );
        }

        $protectedBefore = $this->protectedFingerprint();

        $applications = DB::transaction(function () use ($protectedBefore): array {
            $ownership = $this->warehouseOwnership->apply();
            $classification = $this->articleClassification->analyze(true);
            $units = $this->unitMapping->applySafeCandidates();
            $catalog13 = $this->inventoryCatalog->applySafeCandidates();
            $valuation = $this->valuationMethod->apply();

            if (! hash_equals($protectedBefore, $this->protectedFingerprint())) {
                throw new RuntimeException(
                    'La aplicación segura intentó modificar cantidades, costos o documentos protegidos. Todo el APPLY-SAFE fue revertido.'
                );
            }

            return [
                'ownership' => $ownership,
                'classification' => $classification,
                'units' => $units,
                'catalog13' => $catalog13,
                'valuation' => $valuation,
            ];
        });

        return [
            'before' => $beforeAudit,
            'applications' => $applications,
            'after' => $this->audit(),
        ];
    }

    private function ownershipControl(array $report): array
    {
        $movements = $report['movements'];
        $stocks = $report['stocks'];
        $relations = $report['company_warehouses'];

        return $this->control(
            'Empresa / almacén',
            (int) $movements['already_owned'] + (int) $stocks['already_owned'] + (int) $relations['existing'],
            (int) $movements['resolvable'] + (int) $stocks['resolvable'] + (int) $relations['creatable'],
            (int) $movements['ambiguous'] + (int) $stocks['ambiguous']
                + (int) $movements['without_source'] + (int) $stocks['without_source'],
            (int) $movements['conflicts'] + (int) $stocks['conflicts'],
            'Propiedad de stock/Kardex y relación empresa-almacén'
        );
    }

    private function classificationControl(array $report): array
    {
        return $this->control(
            'Artículo producto/servicio',
            count($report['already_classified']),
            count($report['inventory_candidates']),
            count($report['ambiguous_without_evidence']),
            count($report['conflicts']),
            'Clasificación operativa e inventariable'
        );
    }

    private function existenceControl(array $report): array
    {
        $classifiedPending = $this->uniqueIds(
            $report['safe_candidates'] ?? [],
            $report['ambiguous'] ?? [],
            $report['conflicts'] ?? []
        );
        $unclassifiedPending = max(0, count($report['pending'] ?? []) - count($classifiedPending));

        return $this->control(
            'SUNAT Tabla 05',
            count($report['configured']),
            0,
            count($report['safe_candidates']) + count($report['ambiguous']) + $unclassifiedPending,
            count($report['conflicts']),
            'El tipo de existencia no se autoasigna en 2.8'
        );
    }

    private function unitControl(array $report): array
    {
        return $this->control(
            'SUNAT Tabla 06',
            count($report['configured']),
            count($report['safe_candidates']),
            count($report['ambiguous']),
            count($report['conflicts']),
            'Equivalencia de unidad de medida'
        );
    }

    private function catalog13Control(array $report): array
    {
        $manual = $this->uniqueIds(
            $report['ambiguous'] ?? [],
            $report['unspsc_detected'] ?? [],
            $report['gtin_detected'] ?? []
        );

        return $this->control(
            'SUNAT Tabla 13',
            count($report['configured']),
            count($report['safe_candidates']),
            count($manual),
            count($report['conflicts']),
            'Identificación principal; UNSPSC/GTIN nunca se inventan'
        );
    }

    private function valuationControl(array $report): array
    {
        return $this->control(
            'SUNAT Tabla 14',
            (int) $report['configured'],
            (int) $report['safe_candidates'],
            0,
            (int) $report['conflicts'],
            'Método de valorización por empresa'
        );
    }

    private function establishmentControl(array $report): array
    {
        return $this->control(
            'Código establecimiento SUNAT',
            $report['configured'],
            0,
            $report['pending'],
            0,
            'Nunca se infiere automáticamente el código oficial del establecimiento'
        );
    }

    private function control(
        string $name,
        int $correct,
        int $autoCorrectable,
        int $manual,
        int $inconsistent,
        string $note
    ): array {
        return [
            'name' => $name,
            'correct' => $correct,
            'auto_correctable' => $autoCorrectable,
            'manual' => $manual,
            'inconsistent' => $inconsistent,
            'note' => $note,
        ];
    }

    private function controlTotals(array $controls): array
    {
        return [
            'correct' => array_sum(array_column($controls, 'correct')),
            'auto_correctable' => array_sum(array_column($controls, 'auto_correctable')),
            'manual' => array_sum(array_column($controls, 'manual')),
            'inconsistent' => array_sum(array_column($controls, 'inconsistent')),
        ];
    }

    private function uniqueIds(array ...$groups): array
    {
        $ids = [];
        foreach ($groups as $group) {
            foreach ($group as $row) {
                if (is_array($row) && array_key_exists('id', $row)) {
                    $ids[(string) $row['id']] = true;
                }
            }
        }

        return array_keys($ids);
    }

    private function auditEstablishments(): array
    {
        if (! Schema::hasTable('company_warehouses')) {
            return [
                'migration_applied' => false,
                'total' => 0,
                'configured' => 0,
                'pending' => 0,
                'rows' => [],
            ];
        }

        $rows = DB::table('company_warehouses as cw')
            ->leftJoin('companies as c', 'c.id', '=', 'cw.company_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'cw.warehouse_id')
            ->where('cw.is_active', true)
            ->orderBy('cw.company_id')
            ->orderBy('cw.warehouse_id')
            ->get([
                'cw.id',
                'cw.company_id',
                'cw.warehouse_id',
                'cw.sunat_establishment_code',
                'c.business_name as company_name',
                'c.ruc as company_ruc',
                'w.code as warehouse_code',
                'w.name as warehouse_name',
            ])
            ->map(function ($row): array {
                $code = trim((string) ($row->sunat_establishment_code ?? ''));

                return [
                    'id' => (int) $row->id,
                    'company_id' => (int) $row->company_id,
                    'company' => $row->company_name,
                    'ruc' => $row->company_ruc,
                    'warehouse_id' => (int) $row->warehouse_id,
                    'warehouse' => trim(($row->warehouse_code ? $row->warehouse_code.' — ' : '').($row->warehouse_name ?? '')),
                    'code' => $code !== '' ? $code : null,
                    'status' => $code !== '' ? 'CONFIGURADO' : 'REVISIÓN MANUAL',
                ];
            })
            ->all();

        return [
            'migration_applied' => true,
            'total' => count($rows),
            'configured' => count(array_filter($rows, fn (array $row) => $row['code'] !== null)),
            'pending' => count(array_filter($rows, fn (array $row) => $row['code'] === null)),
            'rows' => $rows,
        ];
    }

    private function protectedFingerprint(): string
    {
        $definitions = [
            'warehouse_stocks' => [
                'id', 'warehouse_id', 'article_id', 'unit_id', 'presentation_id', 'brand_id',
                'lot_number', 'expiration_date', 'origin', 'cost_type', 'current_quantity',
                'reserved_quantity', 'average_unit_cost', 'total_cost', 'min_stock', 'status', 'deleted_at',
            ],
            'warehouse_kardex_movements' => [
                'id', 'warehouse_stock_id', 'warehouse_id', 'article_id', 'unit_id', 'presentation_id', 'brand_id',
                'lot_number', 'expiration_date', 'origin', 'cost_type', 'movement_date', 'movement_type',
                'operation_type', 'source_type', 'source_id', 'source_item_type', 'source_item_id', 'source_key',
                'document_type', 'document_series', 'document_number', 'related_party_type', 'related_party_id',
                'related_party_name', 'quantity_in', 'quantity_out', 'balance_quantity', 'unit_cost',
                'total_cost_in', 'total_cost_out', 'average_unit_cost', 'balance_total_cost',
                'currency_id', 'exchange_rate', 'observations', 'status',
            ],
            'warehouse_entries' => null,
            'warehouse_entry_items' => null,
            'warehouse_dispatches' => null,
            'warehouse_dispatch_items' => null,
            'customer_returns' => null,
            'customer_return_items' => null,
            'quotes' => null,
            'quote_items' => null,
            'customer_purchase_orders' => null,
            'customer_purchase_order_items' => null,
            'electronic_invoices' => null,
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
                : array_values(array_filter($columns, fn (string $column) => Schema::hasColumn($table, $column)));

            $snapshot[$table] = DB::table($table)->orderBy('id')->get($available)->all();
        }

        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
    }
}
