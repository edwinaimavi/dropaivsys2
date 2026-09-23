<?php

namespace App\Services;

use App\Models\WarehouseEntry;
use App\Models\WarehouseKardexMovement;
use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseLegacyValuationResidualService
{
    private const QUANTITY_TOLERANCE_UNITS = 1;

    private const VALUE_TOLERANCE_UNITS = 1;

    private const ACCOUNTING_SNAPSHOTS = [
        'sunat_establishment_code_snapshot',
        'article_code_snapshot',
        'article_description_snapshot',
        'sunat_existence_type_code_snapshot',
        'existence_catalog_code_snapshot',
        'existence_code_snapshot',
        'sunat_unit_code_snapshot',
        'unit_description_snapshot',
        'valuation_method_code_snapshot',
        'valuation_method_description_snapshot',
    ];

    private const DOCUMENT_SNAPSHOTS = [
        'document_date_snapshot',
        'sunat_document_type_code_snapshot',
    ];

    public function __construct(private readonly WarehouseKardexService $kardexService) {}

    public function audit(bool $applySafe = false): array
    {
        $result = [
            'mode' => $applySafe ? 'APPLY SAFE' : 'DRY-RUN',
            'summary' => [
                'groups_analyzed' => 0,
                'safe' => 0,
                'manual_review' => 0,
                'already_corrected' => 0,
                'conflict' => 0,
                'corrections_created' => 0,
                'no_residual' => 0,
            ],
            'groups' => [],
        ];

        $groups = WarehouseKardexMovement::query()
            ->where('status', '!=', 'cancelled')
            ->select(['company_id', 'warehouse_id', 'article_id'])
            ->distinct()
            ->orderBy('company_id')
            ->orderBy('warehouse_id')
            ->orderBy('article_id')
            ->get();

        foreach ($groups as $identity) {
            $group = $this->classifyGroup(
                (int) $identity->company_id,
                (int) $identity->warehouse_id,
                (int) $identity->article_id
            );
            $result['summary']['groups_analyzed']++;
            $result['summary'][match ($group['classification']) {
                'SAFE' => 'safe',
                'MANUAL_REVIEW' => 'manual_review',
                'ALREADY_CORRECTED' => 'already_corrected',
                default => 'no_residual',
            }]++;

            if ($group['classification'] !== 'NO_RESIDUAL') {
                $result['groups'][] = $group;
            }
        }

        if ($applySafe) {
            $this->applySafe($result);
        }

        return $result;
    }

    private function classifyGroup(int $companyId, int $warehouseId, int $articleId): array
    {
        $movements = $this->groupMovements($companyId, $warehouseId, $articleId)->get();
        $stockQuantityUnits = $this->quantityUnits(WarehouseStock::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('article_id', $articleId)
            ->sum('current_quantity'));
        $pool = WarehouseValuationPool::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('article_id', $articleId)
            ->first();
        $poolQuantityUnits = $pool ? $this->quantityUnits($pool->current_quantity) : null;
        $poolValueUnits = $pool ? $this->moneyUnits($pool->total_cost) : null;
        $kardexQuantityUnits = 0;
        $kardexValueUnits = 0;
        $eligible = [];
        $sourceMissing = false;
        $hasLegacyCorrections = false;

        foreach ($movements as $movement) {
            $quantityIn = $this->quantityUnits($movement->quantity_in);
            $quantityOut = $this->quantityUnits($movement->quantity_out);
            $costIn = $this->moneyUnits($movement->total_cost_in);
            $costOut = $this->moneyUnits($movement->total_cost_out);
            $quantityBefore = $kardexQuantityUnits;
            $kardexQuantityUnits += $quantityIn - $quantityOut;
            $kardexValueUnits += $costIn - $costOut;

            if (str_starts_with((string) $movement->source_key, 'legacy-valuation-correction:')) {
                $hasLegacyCorrections = true;
            }

            if ($movement->movement_type !== 'linked_cost'
                || $movement->operation_type !== 'warehouse_entry_linked_cost'
                || $quantityIn !== 0
                || $quantityOut !== 0
                || $costIn <= 0
                || $costOut !== 0
                || abs($quantityBefore) > self::QUANTITY_TOLERANCE_UNITS
                || abs($kardexQuantityUnits) > self::QUANTITY_TOLERANCE_UNITS
                || $this->hasEquivalentCorrection($movements, $movement)) {
                continue;
            }

            if (! $this->sourceExists($movement)) {
                $sourceMissing = true;

                continue;
            }

            $eligible[] = [
                'id' => (int) $movement->id,
                'movement_number' => $movement->movement_number,
                'amount' => $this->money($costIn),
                'amount_units' => $costIn,
                'movement_date' => $movement->movement_date?->format('Y-m-d H:i:s'),
                'fingerprint' => $this->movementFingerprint($movement),
            ];
        }

        $base = [
            'company_id' => $companyId,
            'warehouse_id' => $warehouseId,
            'article_id' => $articleId,
            'stock_quantity' => $this->quantity($stockQuantityUnits),
            'kardex_quantity' => $this->quantity($kardexQuantityUnits),
            'pool_quantity' => $poolQuantityUnits === null ? null : $this->quantity($poolQuantityUnits),
            'kardex_residual' => $this->money($kardexValueUnits),
            'pool_value' => $poolValueUnits === null ? null : $this->money($poolValueUnits),
            'linked_costs' => $eligible,
            'proposed_correction' => $this->money(array_sum(array_column($eligible, 'amount_units'))),
            'fingerprint' => $this->groupFingerprint($movements, $stockQuantityUnits, $poolQuantityUnits, $poolValueUnits),
            'reasons' => [],
        ];

        if (abs($kardexValueUnits) <= self::VALUE_TOLERANCE_UNITS) {
            return [...$base, 'classification' => $hasLegacyCorrections ? 'ALREADY_CORRECTED' : 'NO_RESIDUAL'];
        }

        $reasons = [];
        if (abs($stockQuantityUnits) > self::QUANTITY_TOLERANCE_UNITS) {
            $reasons[] = 'El stock físico consolidado no es cero.';
        }
        if (abs($kardexQuantityUnits) > self::QUANTITY_TOLERANCE_UNITS) {
            $reasons[] = 'El saldo físico acumulado del Kardex no es cero.';
        }
        if ($poolQuantityUnits === null || abs($poolQuantityUnits) > self::QUANTITY_TOLERANCE_UNITS) {
            $reasons[] = 'El pool no existe o su cantidad no es cero.';
        }
        if ($poolValueUnits === null || abs($poolValueUnits) > self::VALUE_TOLERANCE_UNITS) {
            $reasons[] = 'El pool no existe o su valor no es cero.';
        }
        if ($kardexValueUnits <= self::VALUE_TOLERANCE_UNITS) {
            $reasons[] = 'El residuo Kardex no es positivo.';
        }
        if ($sourceMissing) {
            $reasons[] = 'Uno o más costos vinculados candidatos no conservan un origen verificable.';
        }

        $explainedUnits = array_sum(array_column($eligible, 'amount_units'));
        if ($eligible === [] || abs($explainedUnits - $kardexValueUnits) > self::VALUE_TOLERANCE_UNITS) {
            $reasons[] = 'Los costos vinculados legacy seguros no explican íntegramente el residuo.';
        }

        return [
            ...$base,
            'classification' => $reasons === [] ? 'SAFE' : 'MANUAL_REVIEW',
            'reasons' => $reasons,
        ];
    }

    private function applySafe(array &$result): void
    {
        foreach (collect($result['groups'])->where('classification', 'SAFE') as $candidate) {
            DB::transaction(function () use ($candidate, &$result): void {
                $this->groupMovements(
                    $candidate['company_id'],
                    $candidate['warehouse_id'],
                    $candidate['article_id']
                )->lockForUpdate()->get();
                WarehouseStock::query()
                    ->where('company_id', $candidate['company_id'])
                    ->where('warehouse_id', $candidate['warehouse_id'])
                    ->where('article_id', $candidate['article_id'])
                    ->lockForUpdate()
                    ->get();
                WarehouseValuationPool::query()
                    ->where('company_id', $candidate['company_id'])
                    ->where('warehouse_id', $candidate['warehouse_id'])
                    ->where('article_id', $candidate['article_id'])
                    ->lockForUpdate()
                    ->get();

                $fresh = $this->classifyGroup(
                    $candidate['company_id'],
                    $candidate['warehouse_id'],
                    $candidate['article_id']
                );

                if ($fresh['classification'] !== 'SAFE' || $fresh['fingerprint'] !== $candidate['fingerprint']) {
                    $result['summary']['conflict']++;

                    return;
                }

                $originals = [];
                foreach ($fresh['linked_costs'] as $linkedCost) {
                    $original = WarehouseKardexMovement::query()->lockForUpdate()->find($linkedCost['id']);
                    if ($original === null
                        || $this->movementFingerprint($original) !== $linkedCost['fingerprint']
                        || WarehouseKardexMovement::query()->where('source_key', $this->correctionKey((int) $linkedCost['id']))->exists()) {
                        $result['summary']['conflict']++;

                        return;
                    }

                    $originals[] = $original;
                }

                // Valida todo el grupo antes de crear la primera compensación. Así un
                // conflicto en el segundo/tercer costo no puede dejar una reparación parcial.
                foreach ($originals as $original) {
                    $this->createCorrection($original);
                    $result['summary']['corrections_created']++;
                }
            });
        }
    }

    private function createCorrection(WarehouseKardexMovement $original): WarehouseKardexMovement
    {
        $balance = $this->balanceAtCorrectionPosition($original);
        $amountUnits = $this->moneyUnits($original->total_cost_in);
        $balanceValueUnits = $balance['value_units'] - $amountUnits;
        $average = $balance['quantity_units'] === 0
            ? '0.000000'
            : number_format(
                ($balanceValueUnits / 100) / ($balance['quantity_units'] / 10000),
                6,
                '.',
                ''
            );
        $snapshots = [];
        foreach ([...self::ACCOUNTING_SNAPSHOTS, ...self::DOCUMENT_SNAPSHOTS] as $field) {
            $snapshots[$field] = $original->getAttribute($field);
        }

        return WarehouseKardexMovement::create([
            'movement_number' => $this->kardexService->generateMovementNumber(),
            'company_id' => $original->company_id,
            'warehouse_stock_id' => $original->warehouse_stock_id,
            'warehouse_id' => $original->warehouse_id,
            'article_id' => $original->article_id,
            'unit_id' => $original->unit_id,
            'presentation_id' => $original->presentation_id,
            'brand_id' => $original->brand_id,
            'lot_number' => $original->lot_number,
            'expiration_date' => $original->expiration_date,
            'origin' => $original->origin,
            'cost_type' => $original->cost_type,
            ...$snapshots,
            'movement_date' => $original->movement_date,
            'movement_type' => 'cost_reversal',
            'operation_type' => 'warehouse_entry_linked_cost_cancel',
            'sunat_operation_type_code_snapshot' => $this->kardexService->resolveSunatOperationTypeCode(
                'cost_reversal',
                'warehouse_entry_linked_cost_cancel'
            ),
            'source_type' => $original->source_type,
            'source_id' => $original->source_id,
            'source_item_type' => $original->source_item_type,
            'source_item_id' => $original->source_item_id,
            'source_key' => $this->correctionKey($original->id),
            'document_type' => $original->document_type,
            'document_series' => $original->document_series,
            'document_number' => $original->document_number,
            'related_party_type' => $original->related_party_type,
            'related_party_id' => $original->related_party_id,
            'related_party_name' => $original->related_party_name,
            'quantity_in' => 0,
            'quantity_out' => 0,
            'unit_cost' => 0,
            'total_cost_in' => 0,
            'total_cost_out' => $this->money($amountUnits),
            'balance_quantity' => $this->quantity($balance['quantity_units']),
            'average_unit_cost' => $average,
            'balance_total_cost' => $this->money($balanceValueUnits),
            'currency_id' => $original->currency_id,
            'exchange_rate' => $original->exchange_rate,
            'observations' => 'Corrección histórica controlada de valorización legacy: costo vinculado registrado sin existencia física disponible. Movimiento original '.$original->movement_number.'.',
            'status' => 'registered',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);
    }

    private function balanceAtCorrectionPosition(WarehouseKardexMovement $original): array
    {
        $movements = $this->groupMovements(
            (int) $original->company_id,
            (int) $original->warehouse_id,
            (int) $original->article_id
        )->where('movement_date', '<=', $original->getRawOriginal('movement_date'))->get();

        return [
            'quantity_units' => $movements->sum(fn ($movement) =>
                $this->quantityUnits($movement->quantity_in) - $this->quantityUnits($movement->quantity_out)),
            'value_units' => $movements->sum(fn ($movement) =>
                $this->moneyUnits($movement->total_cost_in) - $this->moneyUnits($movement->total_cost_out)),
        ];
    }

    private function groupMovements(int $companyId, int $warehouseId, int $articleId)
    {
        return WarehouseKardexMovement::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('article_id', $articleId)
            ->where('status', '!=', 'cancelled')
            ->orderBy('movement_date')
            ->orderBy('id');
    }

    private function hasEquivalentCorrection(Collection $movements, WarehouseKardexMovement $original): bool
    {
        $amount = $this->moneyUnits($original->total_cost_in);

        return $movements->contains(function ($movement) use ($original, $amount): bool {
            if ($movement->operation_type !== 'warehouse_entry_linked_cost_cancel'
                || $this->quantityUnits($movement->quantity_in) !== 0
                || $this->quantityUnits($movement->quantity_out) !== 0
                || $this->moneyUnits($movement->total_cost_out) !== $amount) {
                return false;
            }

            return in_array($movement->source_key, [
                $this->correctionKey($original->id),
                'reversal:'.$original->id,
            ], true);
        });
    }

    private function sourceExists(WarehouseKardexMovement $movement): bool
    {
        if ($movement->source_type !== WarehouseEntry::class || ! $movement->source_id) {
            return false;
        }
        if (! WarehouseEntry::withTrashed()->whereKey($movement->source_id)->exists()) {
            return false;
        }

        return $movement->source_item_type === 'App\\Models\\WarehouseEntryExpenseDistribution'
            && $movement->source_item_id
            && DB::table('warehouse_entry_expense_distributions')->where('id', $movement->source_item_id)->exists();
    }

    private function groupFingerprint(Collection $movements, int $stock, ?int $poolQuantity, ?int $poolValue): string
    {
        return hash('sha256', json_encode([
            'movements' => $movements->map(fn ($movement) => $this->movementFingerprint($movement))->all(),
            'stock' => $stock,
            'pool_quantity' => $poolQuantity,
            'pool_value' => $poolValue,
        ]));
    }

    private function movementFingerprint(WarehouseKardexMovement $movement): string
    {
        return hash('sha256', json_encode([
            $movement->id,
            $movement->getRawOriginal('movement_date'),
            $movement->movement_type,
            $movement->operation_type,
            $movement->status,
            $movement->quantity_in,
            $movement->quantity_out,
            $movement->total_cost_in,
            $movement->total_cost_out,
            $movement->source_type,
            $movement->source_id,
            $movement->source_item_type,
            $movement->source_item_id,
            $movement->source_key,
            $movement->getRawOriginal('updated_at'),
        ]));
    }

    private function correctionKey(int $movementId): string
    {
        return 'legacy-valuation-correction:'.$movementId;
    }

    private function quantityUnits(mixed $value): int
    {
        return (int) round((float) $value * 10000);
    }

    private function moneyUnits(mixed $value): int
    {
        return (int) round((float) $value * 100);
    }

    private function quantity(int $units): string
    {
        return number_format($units / 10000, 4, '.', '');
    }

    private function money(int $units): string
    {
        return number_format($units / 100, 2, '.', '');
    }
}
