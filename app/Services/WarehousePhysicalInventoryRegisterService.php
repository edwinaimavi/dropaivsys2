<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Warehouse;
use App\Models\WarehouseKardexMovement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class WarehousePhysicalInventoryRegisterService
{
    private const QUANTITY_SCALE = 4;

    /**
     * Construye el Formato 12.1 exclusivamente desde el historial del Kardex.
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
        $registers = [];

        foreach ($movements->groupBy('article_id') as $groupMovements) {
            $openingMovements = $groupMovements->filter(
                fn (WarehouseKardexMovement $movement) => $movement->movement_date->lt($periodStart)
            );
            $periodMovements = $groupMovements->filter(
                fn (WarehouseKardexMovement $movement) => $movement->movement_date->gte($periodStart)
                    && $movement->movement_date->lt($periodEndExclusive)
            )->values();

            $openingUnits = $this->movementDeltaUnits($openingMovements);
            if ($openingUnits === 0 && $periodMovements->isEmpty()) {
                continue;
            }

            $headerMovement = $periodMovements->last() ?? $openingMovements->last();
            $balanceUnits = $openingUnits;
            $totalInUnits = 0;
            $totalOutUnits = 0;
            $rows = [];

            foreach ($periodMovements as $movement) {
                $quantityInUnits = $this->decimalToUnits($movement->quantity_in);
                $quantityOutUnits = $this->decimalToUnits($movement->quantity_out);
                $totalInUnits += $quantityInUnits;
                $totalOutUnits += $quantityOutUnits;
                $balanceUnits += $quantityInUnits - $quantityOutUnits;

                $rows[] = [
                    'id' => $movement->id,
                    'movement_number' => $movement->movement_number,
                    'movement_date' => $movement->movement_date,
                    'document_date' => $movement->document_date_snapshot,
                    'document_type_code' => $movement->sunat_document_type_code_snapshot,
                    'document_series' => $movement->document_series,
                    'document_number' => $movement->document_number,
                    'operation_type_code' => $movement->sunat_operation_type_code_snapshot,
                    'quantity_in' => $this->unitsToDecimal($quantityInUnits),
                    'quantity_out' => $this->unitsToDecimal($quantityOutUnits),
                    'balance' => $this->unitsToDecimal($balanceUnits),
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
                'opening_balance' => $this->unitsToDecimal($openingUnits),
                'rows' => $rows,
                'total_entries' => $this->unitsToDecimal($totalInUnits),
                'total_exits' => $this->unitsToDecimal($totalOutUnits),
                'final_balance' => $this->unitsToDecimal($openingUnits + $totalInUnits - $totalOutUnits),
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

    private function movementDeltaUnits(iterable $movements): int
    {
        $balance = 0;

        foreach ($movements as $movement) {
            $balance += $this->decimalToUnits($movement->quantity_in)
                - $this->decimalToUnits($movement->quantity_out);
        }

        return $balance;
    }

    private function hasIncompleteSnapshots(WarehouseKardexMovement $movement): bool
    {
        if ($this->hasIncompleteHeaderSnapshots($movement)
            || blank($movement->sunat_operation_type_code_snapshot)) {
            return true;
        }

        $hasExternalDocument = filled($movement->document_type)
            && ! in_array($movement->document_type, ['SIN_COMPROBANTE', 'RECIBO_INTERNO'], true);

        return $hasExternalDocument && (
            blank($movement->document_date_snapshot)
            || blank($movement->sunat_document_type_code_snapshot)
            || blank($movement->document_number)
        );
    }

    private function hasIncompleteHeaderSnapshots(WarehouseKardexMovement $movement): bool
    {
        foreach ([
            $movement->sunat_establishment_code_snapshot,
            $movement->article_code_snapshot,
            $movement->article_description_snapshot,
            $movement->sunat_existence_type_code_snapshot,
            $movement->existence_catalog_code_snapshot,
            $movement->existence_code_snapshot,
            $movement->sunat_unit_code_snapshot,
        ] as $snapshot) {
            if (blank($snapshot)) {
                return true;
            }
        }

        return false;
    }

    private function decimalToUnits(mixed $value): int
    {
        $decimal = trim((string) ($value ?? '0'));
        $negative = str_starts_with($decimal, '-');
        $decimal = ltrim($decimal, '+-');
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $fraction = substr(str_pad($fraction, self::QUANTITY_SCALE, '0'), 0, self::QUANTITY_SCALE);
        $units = ((int) ($whole === '' ? '0' : $whole) * (10 ** self::QUANTITY_SCALE)) + (int) $fraction;

        return $negative ? -$units : $units;
    }

    private function unitsToDecimal(int $units): string
    {
        $factor = 10 ** self::QUANTITY_SCALE;
        $sign = $units < 0 ? '-' : '';
        $absolute = abs($units);

        return $sign.intdiv($absolute, $factor).'.'.str_pad(
            (string) ($absolute % $factor),
            self::QUANTITY_SCALE,
            '0',
            STR_PAD_LEFT
        );
    }
}
