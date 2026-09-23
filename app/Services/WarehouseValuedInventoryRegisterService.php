<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\WarehouseKardexMovement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class WarehouseValuedInventoryRegisterService
{
    private const QUANTITY_SCALE = 4;

    private const UNIT_COST_SCALE = 6;

    private const TOTAL_COST_SCALE = 2;

    /**
     * Construye el Formato 13.1 exclusivamente desde movimientos históricos del Kardex.
     */
    public function generate(
        int $companyId,
        int $year,
        int $month,
        int $warehouseId,
        ?int $articleId = null
    ): array {
        $periodStart = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $periodEndExclusive = $periodStart->addMonth();

        $company = Company::query()->findOrFail($companyId);
        $warehouse = Warehouse::query()->findOrFail($warehouseId);

        $movements = $this->effectiveHistoryQuery($companyId, $warehouseId, $articleId)
            ->where('movement_date', '<', $periodEndExclusive)
            ->orderBy('movement_date')
            ->orderBy('id')
            ->get();

        $incomplete = [];
        $inconsistencies = [];
        $registers = [];

        foreach ($movements->groupBy('article_id') as $groupMovements) {
            $openingMovements = $groupMovements->filter(
                fn (WarehouseKardexMovement $movement) => $movement->movement_date->lt($periodStart)
            );
            $periodMovements = $groupMovements->filter(
                fn (WarehouseKardexMovement $movement) => $movement->movement_date->gte($periodStart)
                    && $movement->movement_date->lt($periodEndExclusive)
            )->values();

            [$openingQuantityUnits, $openingCostUnits] = $this->movementBalances($openingMovements);
            if ($openingQuantityUnits === 0 && $openingCostUnits === 0 && $periodMovements->isEmpty()) {
                continue;
            }

            $headerMovement = $periodMovements->last() ?? $openingMovements->last();
            $quantityBalanceUnits = $openingQuantityUnits;
            $costBalanceUnits = $openingCostUnits;
            $totalQuantityInUnits = 0;
            $totalQuantityOutUnits = 0;
            $totalCostInUnits = 0;
            $totalCostOutUnits = 0;
            $rows = [];

            foreach ($periodMovements as $movement) {
                $quantityInUnits = $this->decimalToUnits($movement->quantity_in, self::QUANTITY_SCALE);
                $quantityOutUnits = $this->decimalToUnits($movement->quantity_out, self::QUANTITY_SCALE);
                $costInUnits = $this->decimalToUnits($movement->total_cost_in, self::TOTAL_COST_SCALE);
                $costOutUnits = $this->decimalToUnits($movement->total_cost_out, self::TOTAL_COST_SCALE);

                $totalQuantityInUnits += $quantityInUnits;
                $totalQuantityOutUnits += $quantityOutUnits;
                $totalCostInUnits += $costInUnits;
                $totalCostOutUnits += $costOutUnits;
                $quantityBalanceUnits += $quantityInUnits - $quantityOutUnits;
                $costBalanceUnits += $costInUnits - $costOutUnits;

                $rows[] = [
                    'id' => $movement->id,
                    'movement_number' => $movement->movement_number,
                    'movement_date' => $movement->movement_date,
                    'document_date' => $movement->document_date_snapshot,
                    'document_type_code' => $movement->sunat_document_type_code_snapshot,
                    'document_series' => $movement->document_series,
                    'document_number' => $movement->document_number,
                    'operation_type_code' => $movement->sunat_operation_type_code_snapshot,
                    'quantity_in' => $this->unitsToDecimal($quantityInUnits, self::QUANTITY_SCALE),
                    'entry_unit_cost' => $quantityInUnits > 0
                        ? $this->normalizeDecimal($movement->unit_cost, self::UNIT_COST_SCALE)
                        : null,
                    'total_cost_in' => $this->unitsToDecimal($costInUnits, self::TOTAL_COST_SCALE),
                    'quantity_out' => $this->unitsToDecimal($quantityOutUnits, self::QUANTITY_SCALE),
                    'exit_unit_cost' => $quantityOutUnits > 0
                        ? $this->normalizeDecimal($movement->unit_cost, self::UNIT_COST_SCALE)
                        : null,
                    'total_cost_out' => $this->unitsToDecimal($costOutUnits, self::TOTAL_COST_SCALE),
                    'balance_quantity' => $this->unitsToDecimal($quantityBalanceUnits, self::QUANTITY_SCALE),
                    'balance_average_unit_cost' => $this->averageUnitCost($quantityBalanceUnits, $costBalanceUnits),
                    'balance_total_cost' => $this->unitsToDecimal($costBalanceUnits, self::TOTAL_COST_SCALE),
                ];
            }

            foreach ($groupMovements as $movement) {
                if ($this->hasIncompleteSnapshots($movement)) {
                    $incomplete[$movement->id] = [
                        'id' => $movement->id,
                        'movement_number' => $movement->movement_number,
                    ];
                }
            }

            $finalQuantityUnits = $openingQuantityUnits + $totalQuantityInUnits - $totalQuantityOutUnits;
            $finalCostUnits = $openingCostUnits + $totalCostInUnits - $totalCostOutUnits;
            $hasValuationInconsistency = $finalQuantityUnits === 0 && $finalCostUnits !== 0;

            if ($hasValuationInconsistency) {
                $inconsistencies[] = [
                    'article_id' => (int) $headerMovement->article_id,
                    'article_code' => $headerMovement->article_code_snapshot,
                    'movement_number' => $periodMovements->last()?->movement_number
                        ?? $openingMovements->last()?->movement_number,
                    'residual_total_cost' => $this->unitsToDecimal($finalCostUnits, self::TOTAL_COST_SCALE),
                ];
            }

            $registers[] = [
                'article_id' => (int) $headerMovement->article_id,
                'establishment_code' => $headerMovement->sunat_establishment_code_snapshot,
                'article_code' => $headerMovement->article_code_snapshot,
                'existence_catalog_code' => $headerMovement->existence_catalog_code_snapshot,
                'existence_code' => $headerMovement->existence_code_snapshot,
                'existence_type_code' => $headerMovement->sunat_existence_type_code_snapshot,
                'description' => $headerMovement->article_description_snapshot,
                'unit_code' => $headerMovement->sunat_unit_code_snapshot,
                'unit_description' => $headerMovement->unit_description_snapshot,
                'valuation_method_code' => $headerMovement->valuation_method_code_snapshot,
                'valuation_method_description' => $headerMovement->valuation_method_description_snapshot,
                'initial_quantity' => $this->unitsToDecimal($openingQuantityUnits, self::QUANTITY_SCALE),
                'initial_average_unit_cost' => $this->averageUnitCost($openingQuantityUnits, $openingCostUnits),
                'initial_total_cost' => $this->unitsToDecimal($openingCostUnits, self::TOTAL_COST_SCALE),
                'rows' => $rows,
                'total_quantity_in' => $this->unitsToDecimal($totalQuantityInUnits, self::QUANTITY_SCALE),
                'total_cost_in' => $this->unitsToDecimal($totalCostInUnits, self::TOTAL_COST_SCALE),
                'total_quantity_out' => $this->unitsToDecimal($totalQuantityOutUnits, self::QUANTITY_SCALE),
                'total_cost_out' => $this->unitsToDecimal($totalCostOutUnits, self::TOTAL_COST_SCALE),
                'final_quantity' => $this->unitsToDecimal($finalQuantityUnits, self::QUANTITY_SCALE),
                'final_average_unit_cost' => $this->averageUnitCost($finalQuantityUnits, $finalCostUnits),
                'final_total_cost' => $this->unitsToDecimal($finalCostUnits, self::TOTAL_COST_SCALE),
                'has_valuation_inconsistency' => $hasValuationInconsistency,
            ];
        }

        return [
            'company' => $company,
            'warehouse' => $warehouse,
            'period_start' => $periodStart,
            'period_end' => $periodEndExclusive->subDay(),
            'period' => sprintf('%02d/%04d', $month, $year),
            'registers' => $registers,
            'incomplete_snapshot_count' => count($incomplete),
            'incomplete_movements' => array_values($incomplete),
            'valuation_inconsistency_count' => count($inconsistencies),
            'valuation_inconsistencies' => $inconsistencies,
        ];
    }

    /**
     * Los anulados no forman historial; los originales revertidos y sus inversos sí.
     */
    private function effectiveHistoryQuery(int $companyId, int $warehouseId, ?int $articleId): Builder
    {
        return WarehouseKardexMovement::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', '!=', 'cancelled')
            ->when($articleId, fn (Builder $query, int $id) => $query->where('article_id', $id));
    }

    private function movementBalances(iterable $movements): array
    {
        $quantityUnits = 0;
        $costUnits = 0;

        foreach ($movements as $movement) {
            $quantityUnits += $this->decimalToUnits($movement->quantity_in, self::QUANTITY_SCALE)
                - $this->decimalToUnits($movement->quantity_out, self::QUANTITY_SCALE);
            $costUnits += $this->decimalToUnits($movement->total_cost_in, self::TOTAL_COST_SCALE)
                - $this->decimalToUnits($movement->total_cost_out, self::TOTAL_COST_SCALE);
        }

        return [$quantityUnits, $costUnits];
    }

    private function hasIncompleteSnapshots(WarehouseKardexMovement $movement): bool
    {
        foreach ([
            $movement->sunat_establishment_code_snapshot,
            $movement->article_code_snapshot,
            $movement->article_description_snapshot,
            $movement->sunat_existence_type_code_snapshot,
            $movement->existence_catalog_code_snapshot,
            $movement->existence_code_snapshot,
            $movement->sunat_unit_code_snapshot,
            $movement->valuation_method_code_snapshot,
            $movement->valuation_method_description_snapshot,
            $movement->sunat_operation_type_code_snapshot,
        ] as $snapshot) {
            if (blank($snapshot)) {
                return true;
            }
        }

        $hasExternalDocument = filled($movement->document_type)
            && ! in_array($movement->document_type, ['SIN_COMPROBANTE', 'RECIBO_INTERNO'], true);

        return $hasExternalDocument && (
            blank($movement->document_date_snapshot)
            || blank($movement->sunat_document_type_code_snapshot)
            || blank($movement->document_number)
        );
    }

    private function averageUnitCost(int $quantityUnits, int $totalCostUnits): string
    {
        if ($quantityUnits <= 0) {
            return $this->unitsToDecimal(0, self::UNIT_COST_SCALE);
        }

        $quantity = $quantityUnits / (10 ** self::QUANTITY_SCALE);
        $totalCost = $totalCostUnits / (10 ** self::TOTAL_COST_SCALE);
        $average = round($totalCost / $quantity, self::UNIT_COST_SCALE, PHP_ROUND_HALF_UP);

        return number_format($average, self::UNIT_COST_SCALE, '.', '');
    }

    private function normalizeDecimal(mixed $value, int $scale): string
    {
        return $this->unitsToDecimal($this->decimalToUnits($value, $scale), $scale);
    }

    private function decimalToUnits(mixed $value, int $scale): int
    {
        $decimal = trim((string) ($value ?? '0'));
        $negative = str_starts_with($decimal, '-');
        $decimal = ltrim($decimal, '+-');
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $fraction = substr(str_pad($fraction, $scale, '0'), 0, $scale);
        $units = ((int) ($whole === '' ? '0' : $whole) * (10 ** $scale)) + (int) $fraction;

        return $negative ? -$units : $units;
    }

    private function unitsToDecimal(int $units, int $scale): string
    {
        $factor = 10 ** $scale;
        $sign = $units < 0 ? '-' : '';
        $absolute = abs($units);

        return $sign.intdiv($absolute, $factor).'.'.str_pad(
            (string) ($absolute % $factor),
            $scale,
            '0',
            STR_PAD_LEFT
        );
    }
}
