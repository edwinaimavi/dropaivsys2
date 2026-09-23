<?php

namespace App\Services;

use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryItem;
use Illuminate\Validation\ValidationException;

class WarehouseEntryAcquisitionCostService
{
    private const SCALE = 12;

    public function exchangeRate(WarehouseEntry $entry): string
    {
        $entry->loadMissing('currency:id,code');
        if (strtoupper((string) $entry->currency?->code) === 'PEN') {
            return '1';
        }

        $rate = $this->decimal($entry->exchange_rate);
        if (bccomp($rate, '0', self::SCALE) <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Ingrese un tipo de cambio mayor a cero para convertir el costo a moneda base.',
            ]);
        }

        return $rate;
    }

    public function itemCostInDocumentCurrency(WarehouseEntryItem|array $item): string
    {
        $value = fn (string $field, mixed $default = null) => is_array($item)
            ? ($item[$field] ?? $default)
            : ($item->getAttribute($field) ?? $default);

        if (filter_var($value('is_free', false), FILTER_VALIDATE_BOOLEAN)) {
            return '0';
        }

        $code = $value('tax_affectation_code');
        if ($code === null || $code === '') {
            return bcmul(
                $this->decimal($value('quantity', 0)),
                $this->decimal($value('unit_price', 0)),
                self::SCALE
            );
        }

        $base = $this->decimal($value('taxable_base', $value('subtotal', 0)));
        if ((string) $code === WarehouseEntryItem::TAX_AFFECTATION_TAXED) {
            $recoverable = $value('igv_recoverable');
            if ($recoverable === null) {
                throw ValidationException::withMessages([
                    'igv_recoverable' => 'Indique si el IGV de cada línea gravada es recuperable.',
                ]);
            }

            return filter_var($recoverable, FILTER_VALIDATE_BOOLEAN)
                ? $base
                : bcadd($base, $this->decimal($value('tax_amount', 0)), self::SCALE);
        }

        return $this->decimal($value('line_total', $base));
    }

    /** @return array{total: float, unit: float} */
    public function itemCosts(WarehouseEntry $entry, WarehouseEntryItem|array $item): array
    {
        $quantity = $this->decimal(is_array($item) ? ($item['quantity'] ?? 0) : $item->quantity);
        $documentCost = $this->itemCostInDocumentCurrency($item);
        $taxCode = is_array($item)
            ? ($item['tax_affectation_code'] ?? null)
            : $item->tax_affectation_code;
        $legacyWithoutExchangeSnapshot = blank($taxCode) && $entry->exchange_rate === null;
        $baseCost = bcmul(
            $documentCost,
            $legacyWithoutExchangeSnapshot ? '1' : $this->exchangeRate($entry),
            self::SCALE
        );
        $unitCost = bccomp($quantity, '0', self::SCALE) > 0
            ? bcdiv($baseCost, $quantity, self::SCALE)
            : '0';

        return [
            'total' => $this->money($baseCost),
            'unit' => $this->unitMoney($unitCost),
        ];
    }

    public function capitalizableExpenseAmount(WarehouseEntry $entry, WarehouseEntryExpense $expense): float
    {
        if (! $expense->affects_inventory_cost) {
            return 0.0;
        }

        $amount = $expense->affects_igv && $expense->igv_recoverable === true
            ? $expense->taxable_amount
            : ($expense->total_amount ?? $expense->amount);
        $rate = $this->expenseExchangeRate($entry, $expense);

        return $this->money(bcmul($this->decimal($amount), $rate, self::SCALE));
    }

    private function expenseExchangeRate(WarehouseEntry $entry, WarehouseEntryExpense $expense): string
    {
        $expense->loadMissing('currency:id,code');
        $entry->loadMissing('currency:id,code');
        $expenseCode = strtoupper((string) ($expense->currency?->code ?: $entry->currency?->code));
        if ($expenseCode === 'PEN') {
            return '1';
        }

        $rate = $this->decimal($expense->exchange_rate);
        if (bccomp($rate, '0', self::SCALE) <= 0
            && (int) $expense->currency_id === (int) $entry->currency_id) {
            $rate = $this->exchangeRate($entry);
        }
        if (bccomp($rate, '0', self::SCALE) <= 0) {
            throw ValidationException::withMessages([
                'expenses' => 'Un gasto en moneda extranjera requiere tipo de cambio mayor a cero.',
            ]);
        }

        return $rate;
    }

    private function decimal(mixed $value): string
    {
        return bcadd((string) ($value ?? 0), '0', self::SCALE);
    }

    private function money(mixed $value): float
    {
        return (float) bcadd($this->decimal($value), '0.005', 2);
    }

    private function unitMoney(mixed $value): float
    {
        return (float) bcadd($this->decimal($value), '0.0000005', 6);
    }
}
