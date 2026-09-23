<?php

namespace App\Services;

use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryItem;
use Illuminate\Validation\ValidationException;

class WarehouseEntryTransactionalTaxService
{
    private const SCALE = 12;

    private const MONEY_SCALE = 2;

    /**
     * Conserva los snapshots tributarios de líneas existentes y proporciona
     * valores transitorios a líneas nuevas sin consultar el maestro Article.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function prepare(
        array $items,
        bool $legacyAffectIgv,
        ?WarehouseEntry $entry = null,
        bool $defaultTransactionalTax = true
    ): array
    {
        $existingItems = $entry
            ? $entry->items()
                ->whereIn('id', collect($items)->pluck('id')->filter()->all())
                ->get()
                ->keyBy('id')
            : collect();

        return collect($items)->map(function (array $item) use (
            $existingItems,
            $legacyAffectIgv,
            $defaultTransactionalTax
        ) {
            $existing = ! empty($item['id'])
                ? $existingItems->get((int) $item['id'])
                : null;

            if ($existing) {
                $hasSubmittedTaxData = filled($item['tax_affectation_code'] ?? null)
                    || ($item['tax_rate'] ?? null) !== null
                    || ($item['taxable_base'] ?? null) !== null
                    || ($item['igv_recoverable'] ?? null) !== null;

                foreach (WarehouseEntryItem::TRANSACTIONAL_TAX_FIELDS as $field) {
                    if (! array_key_exists($field, $item)) {
                        $item[$field] = $existing->getAttribute($field);
                    }
                }

                $item['_transactional_tax_legacy'] = ! $hasSubmittedTaxData
                    && $existing->tax_affectation_code === null
                    && $existing->tax_rate === null
                    && $existing->taxable_base === null;

                return $item;
            }

            $item['discount_amount'] ??= 0;
            $item['is_free'] ??= false;

            if ($defaultTransactionalTax) {
                $item['tax_affectation_code'] ??= $legacyAffectIgv
                    ? WarehouseEntryItem::TAX_AFFECTATION_TAXED
                    : WarehouseEntryItem::TAX_AFFECTATION_UNAFFECTED;
                $item['tax_rate'] ??= $item['tax_affectation_code']
                    === WarehouseEntryItem::TAX_AFFECTATION_TAXED
                        ? 18
                        : 0;
            }

            $item['_transactional_tax_legacy'] = empty($item['tax_affectation_code'])
                && ($item['tax_rate'] ?? null) === null;

            return $item;
        })->all();
    }

    /**
     * Calcula una línea usando precio unitario con impuesto incluido.
     * Los importes se mantienen a escala interna alta y se redondean una sola
     * vez, al construir los valores monetarios persistibles de dos decimales.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public function calculateLine(array $item, ?bool $legacyAffectIgv = null): array
    {
        if ((bool) ($item['_transactional_tax_legacy'] ?? false)) {
            return $this->calculateLegacyLine($item, (bool) $legacyAffectIgv);
        }

        $this->validate([$item]);

        $gross = bcmul(
            $this->decimal($item['quantity'] ?? 0),
            $this->decimal($item['unit_price'] ?? 0),
            self::SCALE
        );
        $discount = $this->decimal($item['discount_amount'] ?? 0);
        $net = bcsub($gross, $discount, self::SCALE);
        $lineTotal = $this->roundMoney($net);
        $code = (string) $item['tax_affectation_code'];
        $rate = $this->decimal($item['tax_rate'] ?? 0);

        if ($code === WarehouseEntryItem::TAX_AFFECTATION_TAXED) {
            $factor = bcadd('1', bcdiv($rate, '100', self::SCALE), self::SCALE);
            $rawTaxableBase = bcdiv($net, $factor, self::SCALE);
            $taxableBase = $this->roundMoney($rawTaxableBase);
            $taxAmount = bcsub($lineTotal, $taxableBase, self::MONEY_SCALE);
        } else {
            $taxableBase = $lineTotal;
            $taxAmount = '0.00';
        }

        $isFree = filter_var($item['is_free'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payableSubtotal = $isFree ? '0.00' : $taxableBase;
        $payableTax = $isFree ? '0.00' : $taxAmount;
        $payableTotal = $isFree ? '0.00' : $lineTotal;

        return array_merge($item, [
            'gross_amount' => $this->moneyFloat($gross),
            'net_amount' => $isFree ? 0.0 : $this->moneyFloat($net),
            'free_reference_amount' => $isFree ? $this->moneyFloat($gross) : 0.0,
            'discount_amount' => $this->moneyFloat($discount),
            'taxable_base' => $this->moneyFloat($taxableBase),
            'subtotal' => $this->moneyFloat($payableSubtotal),
            'tax_amount' => $this->moneyFloat($payableTax),
            'line_total' => $this->moneyFloat($payableTotal),
            'is_free' => $isFree,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{items: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    public function calculate(array $items, ?bool $legacyAffectIgv = null): array
    {
        $calculatedItems = collect($items)
            ->map(fn (array $item) => $this->calculateLine($item, $legacyAffectIgv))
            ->all();

        $this->validate($calculatedItems);

        return [
            'items' => $calculatedItems,
            'totals' => $this->consolidate($calculatedItems),
        ];
    }

    /**
     * Consolida importes ya calculados; las sumas se realizan sobre los valores
     * persistibles de cada línea para mantener el cuadre exacto con la cabecera.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, float>
     */
    public function consolidate(array $items): array
    {
        $totals = [
            'gross_total' => '0.00',
            'discount_total' => '0.00',
            'taxable_total' => '0.00',
            'exempt_total' => '0.00',
            'unaffected_total' => '0.00',
            'free_reference_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '0.00',
            'subtotal' => '0.00',
        ];

        foreach ($items as $item) {
            $isFree = filter_var($item['is_free'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isFree) {
                $totals['free_reference_total'] = bcadd(
                    $totals['free_reference_total'],
                    (string) ($item['free_reference_amount'] ?? $item['gross_amount'] ?? 0),
                    self::MONEY_SCALE
                );

                continue;
            }

            $totals['gross_total'] = bcadd($totals['gross_total'], (string) ($item['gross_amount'] ?? 0), self::MONEY_SCALE);
            $totals['discount_total'] = bcadd($totals['discount_total'], (string) ($item['discount_amount'] ?? 0), self::MONEY_SCALE);
            $totals['tax_total'] = bcadd($totals['tax_total'], (string) ($item['tax_amount'] ?? 0), self::MONEY_SCALE);
            $totals['grand_total'] = bcadd($totals['grand_total'], (string) ($item['line_total'] ?? 0), self::MONEY_SCALE);
            $totals['subtotal'] = bcadd($totals['subtotal'], (string) ($item['subtotal'] ?? 0), self::MONEY_SCALE);

            $category = match ($item['tax_affectation_code'] ?? null) {
                WarehouseEntryItem::TAX_AFFECTATION_TAXED => 'taxable_total',
                WarehouseEntryItem::TAX_AFFECTATION_EXEMPT => 'exempt_total',
                WarehouseEntryItem::TAX_AFFECTATION_UNAFFECTED => 'unaffected_total',
                default => null,
            };
            if ($category) {
                $value = $category === 'taxable_total'
                    ? ($item['taxable_base'] ?? 0)
                    : ($item['line_total'] ?? 0);
                $totals[$category] = bcadd($totals[$category], (string) $value, self::MONEY_SCALE);
            }
        }

        return collect($totals)
            ->map(fn (string $value) => (float) $value)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function validate(array $items): void
    {
        $errors = [];

        foreach ($items as $index => $item) {
            if ((bool) ($item['_transactional_tax_legacy'] ?? false)) {
                continue;
            }

            $field = "items.{$index}";
            $code = (string) ($item['tax_affectation_code'] ?? '');
            $rate = $item['tax_rate'] ?? null;
            $discount = $this->decimal($item['discount_amount'] ?? 0);
            $gross = bcmul(
                $this->decimal($item['quantity'] ?? 0),
                $this->decimal($item['unit_price'] ?? 0),
                self::SCALE
            );

            if (! in_array($code, WarehouseEntryItem::SUPPORTED_TAX_AFFECTATION_CODES, true)) {
                $errors["{$field}.tax_affectation_code"] = 'La afectación tributaria debe ser 10, 20 o 30.';
            }

            if ($rate === null || ! is_numeric($rate)) {
                $errors["{$field}.tax_rate"] = 'Ingrese una tasa de impuesto válida.';
            } elseif ($code === WarehouseEntryItem::TAX_AFFECTATION_TAXED && (float) $rate <= 0) {
                $errors["{$field}.tax_rate"] = 'Una línea gravada debe tener una tasa de impuesto mayor a cero.';
            } elseif (in_array($code, [
                WarehouseEntryItem::TAX_AFFECTATION_EXEMPT,
                WarehouseEntryItem::TAX_AFFECTATION_UNAFFECTED,
            ], true) && abs((float) $rate) > 0.000001) {
                $errors["{$field}.tax_rate"] = 'Una línea exonerada o inafecta debe tener tasa cero.';
            }

            if (bccomp($discount, '0', self::SCALE) < 0) {
                $errors["{$field}.discount_amount"] = 'El descuento no puede ser negativo.';
            } elseif (bccomp($discount, $gross, self::SCALE) > 0) {
                $errors["{$field}.discount_amount"] = 'El descuento no puede superar el importe bruto de la línea.';
            }

            if (array_key_exists('taxable_base', $item)
                && $item['taxable_base'] !== null
                && (! is_numeric($item['taxable_base']) || (float) $item['taxable_base'] < 0)) {
                $errors["{$field}.taxable_base"] = 'La base tributaria debe ser un importe mayor o igual a cero.';
            }

            if (! array_key_exists('is_free', $item)
                || ! in_array($item['is_free'], [true, false, 0, 1, '0', '1'], true)) {
                $errors["{$field}.is_free"] = 'El indicador de gratuidad debe ser booleano.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $item */
    private function calculateLegacyLine(array $item, bool $affectIgv): array
    {
        $gross = bcmul(
            $this->decimal($item['quantity'] ?? 0),
            $this->decimal($item['unit_price'] ?? 0),
            self::SCALE
        );
        $lineTotal = $this->roundMoney($gross);
        $subtotal = $affectIgv
            ? $this->roundMoney(bcdiv($gross, '1.18', self::SCALE))
            : $lineTotal;
        $taxAmount = $affectIgv
            ? bcsub($lineTotal, $subtotal, self::MONEY_SCALE)
            : '0.00';

        return array_merge($item, [
            'gross_amount' => $this->moneyFloat($gross),
            'net_amount' => $this->moneyFloat($gross),
            'free_reference_amount' => 0.0,
            'discount_amount' => $this->moneyFloat($item['discount_amount'] ?? 0),
            'subtotal' => $this->moneyFloat($subtotal),
            'tax_amount' => $this->moneyFloat($taxAmount),
            'line_total' => $this->moneyFloat($lineTotal),
            'taxable_base' => null,
            'is_free' => false,
        ]);
    }

    private function decimal(mixed $value): string
    {
        return bcadd((string) ($value ?? 0), '0', self::SCALE);
    }

    private function roundMoney(mixed $value): string
    {
        $decimal = $this->decimal($value);
        $increment = bccomp($decimal, '0', self::SCALE) < 0 ? '-0.005' : '0.005';

        return bcadd($decimal, $increment, self::MONEY_SCALE);
    }

    private function moneyFloat(mixed $value): float
    {
        return (float) $this->roundMoney($value);
    }
}
