<?php

namespace App\Services;

use App\Models\WarehouseStock;
use App\Models\WarehouseValuationPool;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseValuationPoolService
{
    private const QUANTITY_SCALE = 4;

    private const UNIT_COST_SCALE = 6;

    private const TOTAL_COST_SCALE = 2;

    public function lockPool(
        int $companyId,
        int $warehouseId,
        int $articleId,
        ?int $userId = null
    ): WarehouseValuationPool {
        return $this->transaction(function () use ($companyId, $warehouseId, $articleId, $userId) {
            $pool = $this->findPoolForUpdate($companyId, $warehouseId, $articleId);

            if ($pool !== null) {
                return $pool;
            }

            $balance = $this->stockBalance($companyId, $warehouseId, $articleId);
            $quantity = $this->decimalToInt(
                $balance['quantity'],
                self::QUANTITY_SCALE,
                'current_quantity'
            );
            $totalCost = $this->decimalToInt(
                $balance['total_cost'],
                self::TOTAL_COST_SCALE,
                'total_cost'
            );

            if ($quantity !== 0 || $totalCost !== 0) {
                throw ValidationException::withMessages([
                    'pool' => 'El inventario tiene saldo físico o valorizado y requiere bootstrap/auditoría antes de operar.',
                ]);
            }

            $this->createZeroPool($companyId, $warehouseId, $articleId, $userId);

            return $this->findPoolForUpdate($companyId, $warehouseId, $articleId)
                ?? throw ValidationException::withMessages([
                    'pool' => 'No fue posible crear y bloquear el pool de valorización.',
                ]);
        });
    }

    public function addQuantityAtCost(
        WarehouseValuationPool $pool,
        float|string $quantity,
        float|string $totalCost,
        ?int $userId = null
    ): WarehouseValuationPool {
        $quantityUnits = $this->decimalToInt($quantity, self::QUANTITY_SCALE, 'quantity');
        $costCents = $this->decimalToInt($totalCost, self::TOTAL_COST_SCALE, 'total_cost');

        $this->validatePositive($quantityUnits, 'quantity', 'La cantidad debe ser mayor que cero.');
        $this->validateNonNegative($costCents, 'total_cost', 'El costo total no puede ser negativo.');

        return $this->withLockedPool($pool, function (WarehouseValuationPool $locked) use (
            $quantityUnits,
            $costCents,
            $userId
        ) {
            $newQuantity = $this->decimalToInt(
                $locked->current_quantity,
                self::QUANTITY_SCALE,
                'current_quantity'
            ) + $quantityUnits;
            $newTotalCost = $this->decimalToInt(
                $locked->total_cost,
                self::TOTAL_COST_SCALE,
                'total_cost'
            ) + $costCents;

            $this->setPoolAmounts(
                $locked,
                $newQuantity,
                $this->averageFrom($newTotalCost, $newQuantity),
                $newTotalCost,
                $userId
            );

            return $locked;
        });
    }

    public function removeQuantityAtAverage(
        WarehouseValuationPool $pool,
        float|string $quantity,
        ?int $userId = null
    ): array {
        $quantityUnits = $this->decimalToInt($quantity, self::QUANTITY_SCALE, 'quantity');
        $this->validatePositive($quantityUnits, 'quantity', 'La cantidad debe ser mayor que cero.');

        return $this->withLockedPool($pool, function (WarehouseValuationPool $locked) use (
            $quantityUnits,
            $userId
        ) {
            $currentQuantity = $this->decimalToInt(
                $locked->current_quantity,
                self::QUANTITY_SCALE,
                'current_quantity'
            );

            if ($quantityUnits > $currentQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'La cantidad de salida supera la cantidad disponible en el pool.',
                ]);
            }

            $unitCost = $this->decimalToInt(
                $locked->average_unit_cost,
                self::UNIT_COST_SCALE,
                'average_unit_cost'
            );
            $outputCost = $this->roundedDivide($quantityUnits * $unitCost, 100_000_000);
            $newQuantity = $currentQuantity - $quantityUnits;
            $newTotalCost = $this->decimalToInt(
                $locked->total_cost,
                self::TOTAL_COST_SCALE,
                'total_cost'
            ) - $outputCost;

            if ($newTotalCost < 0) {
                throw ValidationException::withMessages([
                    'total_cost' => 'El costo de salida supera el valor disponible en el pool.',
                ]);
            }

            if ($newQuantity === 0) {
                $newTotalCost = 0;
                $remainingUnitCost = 0;
            } else {
                $remainingUnitCost = $unitCost;
            }

            $this->setPoolAmounts(
                $locked,
                $newQuantity,
                $remainingUnitCost,
                $newTotalCost,
                $userId
            );

            return [
                'unit_cost' => $this->formatDecimal($unitCost, self::UNIT_COST_SCALE),
                'total_cost' => $this->formatDecimal($outputCost, self::TOTAL_COST_SCALE),
                'pool' => $locked,
            ];
        });
    }

    public function removeQuantityAtHistoricalCost(
        WarehouseValuationPool $pool,
        float|string $quantity,
        float|string $totalCost,
        ?int $userId = null
    ): WarehouseValuationPool {
        $quantityUnits = $this->decimalToInt($quantity, self::QUANTITY_SCALE, 'quantity');
        $costCents = $this->decimalToInt($totalCost, self::TOTAL_COST_SCALE, 'total_cost');

        $this->validatePositive($quantityUnits, 'quantity', 'La cantidad debe ser mayor que cero.');
        $this->validateNonNegative($costCents, 'total_cost', 'El costo total no puede ser negativo.');

        return $this->withLockedPool($pool, function (WarehouseValuationPool $locked) use (
            $quantityUnits,
            $costCents,
            $userId
        ) {
            $currentQuantity = $this->decimalToInt(
                $locked->current_quantity,
                self::QUANTITY_SCALE,
                'current_quantity'
            );
            $currentTotalCost = $this->decimalToInt(
                $locked->total_cost,
                self::TOTAL_COST_SCALE,
                'total_cost'
            );

            if ($quantityUnits > $currentQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'La cantidad de salida supera la cantidad disponible en el pool.',
                ]);
            }

            if ($costCents > $currentTotalCost) {
                throw ValidationException::withMessages([
                    'total_cost' => 'El costo histórico de salida supera el valor disponible en el pool.',
                ]);
            }

            $newQuantity = $currentQuantity - $quantityUnits;
            $newTotalCost = $currentTotalCost - $costCents;

            if ($newQuantity === 0) {
                $newTotalCost = 0;
                $newAverage = 0;
            } else {
                $newAverage = $this->averageFrom($newTotalCost, $newQuantity);
            }

            $this->setPoolAmounts($locked, $newQuantity, $newAverage, $newTotalCost, $userId);

            return $locked;
        });
    }

    public function addCostOnly(
        WarehouseValuationPool $pool,
        float|string $amount,
        ?int $userId = null
    ): WarehouseValuationPool {
        $amountCents = $this->decimalToInt($amount, self::TOTAL_COST_SCALE, 'amount');
        $this->validateNonNegative($amountCents, 'amount', 'El costo vinculado no puede ser negativo.');

        return $this->withLockedPool($pool, function (WarehouseValuationPool $locked) use (
            $amountCents,
            $userId
        ) {
            $currentQuantity = $this->decimalToInt(
                $locked->current_quantity,
                self::QUANTITY_SCALE,
                'current_quantity'
            );

            if ($amountCents > 0 && $currentQuantity <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'No se puede agregar valor de inventario a un pool sin cantidad.',
                ]);
            }

            $newTotalCost = $this->decimalToInt(
                $locked->total_cost,
                self::TOTAL_COST_SCALE,
                'total_cost'
            ) + $amountCents;
            $newAverage = $currentQuantity === 0
                ? 0
                : $this->averageFrom($newTotalCost, $currentQuantity);

            $this->setPoolAmounts($locked, $currentQuantity, $newAverage, $newTotalCost, $userId);

            return $locked;
        });
    }

    public function removeCostOnly(
        WarehouseValuationPool $pool,
        float|string $amount,
        ?int $userId = null
    ): WarehouseValuationPool {
        $amountCents = $this->decimalToInt($amount, self::TOTAL_COST_SCALE, 'amount');
        $this->validateNonNegative($amountCents, 'amount', 'El costo vinculado no puede ser negativo.');

        return $this->withLockedPool($pool, function (WarehouseValuationPool $locked) use (
            $amountCents,
            $userId
        ) {
            $currentQuantity = $this->decimalToInt(
                $locked->current_quantity,
                self::QUANTITY_SCALE,
                'current_quantity'
            );
            $currentTotalCost = $this->decimalToInt(
                $locked->total_cost,
                self::TOTAL_COST_SCALE,
                'total_cost'
            );
            $appliedAmount = $amountCents;

            if ($appliedAmount > $currentTotalCost) {
                if ($appliedAmount - $currentTotalCost <= 1) {
                    $appliedAmount = $currentTotalCost;
                } else {
                    throw ValidationException::withMessages([
                        'amount' => 'El costo vinculado a revertir supera el valor disponible en el pool.',
                    ]);
                }
            }

            $newTotalCost = $currentTotalCost - $appliedAmount;
            if ($currentQuantity === 0) {
                if ($newTotalCost > 1) {
                    throw ValidationException::withMessages([
                        'amount' => 'No puede quedar valor residual en un pool sin cantidad.',
                    ]);
                }

                $newTotalCost = 0;
                $newAverage = 0;
            } else {
                $newAverage = $this->averageFrom($newTotalCost, $currentQuantity);
            }

            $this->setPoolAmounts($locked, $currentQuantity, $newAverage, $newTotalCost, $userId);

            return $locked;
        });
    }

    protected function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    protected function findPoolForUpdate(
        int $companyId,
        int $warehouseId,
        int $articleId
    ): ?WarehouseValuationPool {
        return WarehouseValuationPool::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('article_id', $articleId)
            ->lockForUpdate()
            ->first();
    }

    protected function stockBalance(int $companyId, int $warehouseId, int $articleId): array
    {
        $balance = WarehouseStock::query()
            ->where('company_id', $companyId)
            ->where('warehouse_id', $warehouseId)
            ->where('article_id', $articleId)
            ->selectRaw(
                'COALESCE(SUM(current_quantity), 0) as quantity, COALESCE(SUM(total_cost), 0) as total_cost'
            )
            ->first();

        return [
            'quantity' => (string) ($balance?->quantity ?? '0'),
            'total_cost' => (string) ($balance?->total_cost ?? '0'),
        ];
    }

    protected function createZeroPool(
        int $companyId,
        int $warehouseId,
        int $articleId,
        ?int $userId
    ): void {
        WarehouseValuationPool::query()->firstOrCreate(
            [
                'company_id' => $companyId,
                'warehouse_id' => $warehouseId,
                'article_id' => $articleId,
            ],
            [
                'current_quantity' => '0.0000',
                'average_unit_cost' => '0.000000',
                'total_cost' => '0.00',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    protected function persistPool(WarehouseValuationPool $pool): void
    {
        $pool->save();
    }

    private function withLockedPool(WarehouseValuationPool $pool, Closure $callback): mixed
    {
        return $this->transaction(function () use ($pool, $callback) {
            $locked = $this->findPoolForUpdate(
                (int) $pool->company_id,
                (int) $pool->warehouse_id,
                (int) $pool->article_id
            );

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'pool' => 'El pool de valorización no existe.',
                ]);
            }

            $result = $callback($locked);
            $this->persistPool($locked);

            return $result;
        });
    }

    private function setPoolAmounts(
        WarehouseValuationPool $pool,
        int $quantity,
        int $averageUnitCost,
        int $totalCost,
        ?int $userId
    ): void {
        $pool->current_quantity = $this->formatDecimal($quantity, self::QUANTITY_SCALE);
        $pool->average_unit_cost = $this->formatDecimal($averageUnitCost, self::UNIT_COST_SCALE);
        $pool->total_cost = $this->formatDecimal($totalCost, self::TOTAL_COST_SCALE);
        $pool->updated_by = $userId;
    }

    private function averageFrom(int $totalCost, int $quantity): int
    {
        if ($quantity <= 0) {
            return 0;
        }

        return (int) round(
            ($totalCost * 100_000_000) / $quantity,
            0,
            PHP_ROUND_HALF_UP
        );
    }

    private function roundedDivide(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function decimalToInt(float|string $value, int $scale, string $field): int
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                throw ValidationException::withMessages([$field => 'El valor debe ser numérico.']);
            }

            $value = sprintf('%.12F', $value);
        }

        $value = trim($value);

        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
            throw ValidationException::withMessages([$field => 'El valor debe ser numérico.']);
        }

        $negative = $matches[1] === '-';
        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $matches[3] ?? '';
        $fractionPadded = str_pad($fraction, $scale + 1, '0');
        $keptFraction = substr($fractionPadded, 0, $scale);
        $scaledDigits = $whole.$keptFraction;

        if (strlen(ltrim($scaledDigits, '0')) > 18) {
            throw ValidationException::withMessages([$field => 'El valor excede la precisión permitida.']);
        }

        $scaled = (int) $scaledDigits;

        if ((int) $fractionPadded[$scale] >= 5) {
            $scaled++;
        }

        return $negative ? -$scaled : $scaled;
    }

    private function formatDecimal(int $value, int $scale): string
    {
        $negative = $value < 0;
        $digits = str_pad((string) abs($value), $scale + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$scale);
        $fraction = substr($digits, -$scale);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    private function validatePositive(int $value, string $field, string $message): void
    {
        if ($value <= 0) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    private function validateNonNegative(int $value, string $field, string $message): void
    {
        if ($value < 0) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
