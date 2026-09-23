<?php

namespace App\Services;

use App\Models\Company;
use App\Models\SunatCatalogItem;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CompanyInventoryValuationAuditService
{
    public function __construct(private readonly CompanyInventoryValuationPolicy $policy) {}

    public function audit(): array
    {
        return $this->analyze(false);
    }

    public function apply(): array
    {
        return DB::transaction(function () {
            $before = $this->operationalFingerprint();
            $report = $this->analyze(true);
            $after = $this->operationalFingerprint();

            if ($before !== $after) {
                throw new RuntimeException('La auditoría detectó cambios fuera de companies.inventory_valuation_method_item_id. Se revirtió la operación.');
            }

            return $report;
        });
    }

    private function analyze(bool $apply): array
    {
        $weightedAverage = $this->policy->availableMethods()->first();
        $companies = Company::query()
            ->with('inventoryValuationMethod.catalog')
            ->orderBy('id')
            ->get();

        $rows = [];
        $configured = 0;
        $inventoryCompanies = 0;
        $safeCandidates = 0;
        $conflicts = 0;
        $applied = 0;
        $withoutInventory = 0;

        foreach ($companies as $company) {
            $hasHistory = $this->policy->hasInventoryHistory($company);
            $method = $company->inventoryValuationMethod;
            $status = 'SIN INVENTARIO';
            $suggestedCode = null;

            if ($hasHistory) {
                $inventoryCompanies++;
            } else {
                $withoutInventory++;
            }

            if ($method) {
                $valid = $method->catalog_code === CompanyInventoryValuationPolicy::CATALOG_CODE
                    && $method->item_code === CompanyInventoryValuationPolicy::WEIGHTED_AVERAGE_CODE
                    && $method->status === 'ACTIVE'
                    && $method->catalog?->code === CompanyInventoryValuationPolicy::CATALOG_CODE
                    && (bool) $method->catalog?->is_active;

                if ($valid) {
                    $configured++;
                    $status = 'CONFIGURADA';
                } else {
                    $conflicts++;
                    $status = 'CONFLICTO';
                }
            } elseif ($hasHistory && $weightedAverage) {
                $safeCandidates++;
                $suggestedCode = CompanyInventoryValuationPolicy::WEIGHTED_AVERAGE_CODE;
                $status = 'CANDIDATA SEGURA';

                if ($apply) {
                    $updated = Company::query()
                        ->whereKey($company->id)
                        ->whereNull('inventory_valuation_method_item_id')
                        ->update(['inventory_valuation_method_item_id' => $weightedAverage->id]);

                    if ($updated === 1) {
                        $applied++;
                        $configured++;
                        $safeCandidates--;
                        $status = 'APLICADA';
                    }
                }
            } elseif ($hasHistory && ! $weightedAverage) {
                $conflicts++;
                $status = 'CONFLICTO: TABLA 14/1 NO DISPONIBLE';
            }

            $rows[] = [
                'id' => (int) $company->id,
                'ruc' => $company->ruc,
                'company' => $company->business_name,
                'has_inventory_history' => $hasHistory,
                'current_code' => $method?->item_code,
                'current_description' => $method?->description,
                'suggested_code' => $suggestedCode,
                'status' => $status,
            ];
        }

        return [
            'total_companies' => $companies->count(),
            'inventory_companies' => $inventoryCompanies,
            'configured' => $configured,
            'safe_candidates' => $safeCandidates,
            'conflicts' => $conflicts,
            'without_inventory' => $withoutInventory,
            'applied' => $applied,
            'fragmented_valuation_pools' => $this->fragmentedValuationPoolsCount(),
            'rows' => $rows,
        ];
    }

    private function fragmentedValuationPoolsCount(): int
    {
        if (! Schema::hasTable('warehouse_stocks')) {
            return 0;
        }

        return DB::query()
            ->fromSub(
                WarehouseStock::query()
                    ->selectRaw('company_id, warehouse_id, article_id, COUNT(*) as stocks_count')
                    ->whereNotNull('company_id')
                    ->where('status', 'ACTIVE')
                    ->groupBy('company_id', 'warehouse_id', 'article_id')
                    ->havingRaw('COUNT(*) > 1'),
                'valuation_pools'
            )
            ->count();
    }

    private function operationalFingerprint(): string
    {
        $stocks = WarehouseStock::query()
            ->orderBy('id')
            ->get([
                'id', 'company_id', 'warehouse_id', 'article_id', 'stock_key', 'lot_number', 'expiration_date',
                'current_quantity', 'reserved_quantity', 'average_unit_cost', 'total_cost', 'status',
            ])
            ->map(fn ($row) => $row->toArray())
            ->all();

        $movements = WarehouseKardexMovement::query()
            ->orderBy('id')
            ->get([
                'id', 'company_id', 'warehouse_stock_id', 'warehouse_id', 'article_id', 'movement_type',
                'operation_type', 'quantity_in', 'quantity_out', 'balance_quantity', 'unit_cost',
                'total_cost_in', 'total_cost_out', 'average_unit_cost', 'balance_total_cost', 'status',
            ])
            ->map(fn ($row) => $row->toArray())
            ->all();

        return hash('sha256', json_encode([$stocks, $movements], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }
}
