<?php

namespace App\Services;

use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;

class WarehouseEntryTaxTotalsService
{
    private const SCALE = 12;

    private const MONEY_SCALE = 2;

    /**
     * Calcula los importes tributarios de una recepcion usando como fuente la OC proveedor.
     *
     * @param  array<int, array<string, mixed>>  $receivedItems
     * @param  array<int, mixed>  $receivedBeforeByItem
     * @param  array{subtotal?: mixed, igv?: mixed, grand_total?: mixed}  $previousEntryTotals
     * @return array{totals: array{subtotal: string, igv: string, grand_total: string}, items: array<int, array{subtotal: string, tax_amount: string}>}
     */
    public function calculate(
        SupplierPurchaseOrder $order,
        array $receivedItems,
        array $receivedBeforeByItem = [],
        array $previousEntryTotals = []
    ): array {
        $order->loadMissing('items');

        $orderItems = $order->items
            ->reject(fn (SupplierPurchaseOrderItem $item) => strtolower((string) $item->status) === 'deleted')
            ->keyBy('id');
        $incomingByItem = [];
        $rawSubtotal = '0';
        $rawIgv = '0';
        $rawGrandTotal = '0';

        foreach ($receivedItems as $item) {
            $sourceItemId = (int) ($item['supplier_purchase_order_item_id'] ?? 0);
            $sourceItem = $orderItems->get($sourceItemId);

            if (! $sourceItem) {
                continue;
            }

            $quantity = $this->decimal($item['quantity'] ?? 0);
            $incomingByItem[$sourceItemId] = bcadd(
                $incomingByItem[$sourceItemId] ?? '0',
                $quantity,
                self::SCALE
            );
            $prorated = $this->proratedItemAmounts($order, $sourceItem, $quantity);
            $rawSubtotal = bcadd($rawSubtotal, $prorated['subtotal'], self::SCALE);
            $rawIgv = bcadd($rawIgv, $prorated['igv'], self::SCALE);
            $rawGrandTotal = bcadd($rawGrandTotal, $prorated['grand_total'], self::SCALE);
        }

        $sourceTotals = $this->sourceHeaderTotals($order);
        $remainingTotals = [
            'subtotal' => $this->nonNegative(bcsub(
                $sourceTotals['subtotal'],
                $this->decimal($previousEntryTotals['subtotal'] ?? 0),
                self::MONEY_SCALE
            ), self::MONEY_SCALE),
            'igv' => $this->nonNegative(bcsub(
                $sourceTotals['igv'],
                $this->decimal($previousEntryTotals['igv'] ?? 0),
                self::MONEY_SCALE
            ), self::MONEY_SCALE),
            'grand_total' => $this->nonNegative(bcsub(
                $sourceTotals['grand_total'],
                $this->decimal($previousEntryTotals['grand_total'] ?? 0),
                self::MONEY_SCALE
            ), self::MONEY_SCALE),
        ];

        $completesOrder = $orderItems->isNotEmpty()
            && $orderItems->every(function (SupplierPurchaseOrderItem $item) use (
                $receivedBeforeByItem,
                $incomingByItem
            ) {
                $ordered = $this->decimal($item->quantity);
                $receivedBefore = $this->decimal($receivedBeforeByItem[$item->id] ?? 0);
                $pending = $this->nonNegative(bcsub($ordered, $receivedBefore, self::SCALE));

                return bccomp($incomingByItem[$item->id] ?? '0', $pending, self::SCALE) >= 0;
            });

        $totals = $completesOrder
            ? $remainingTotals
            : $this->partialTotals(
                $order,
                $rawSubtotal,
                $rawIgv,
                $rawGrandTotal,
                $remainingTotals
            );

        return [
            'totals' => $totals,
            'items' => $this->itemTaxBreakdown($order, $receivedItems, $orderItems->all(), $totals),
        ];
    }

    /**
     * @return array{subtotal: string, igv: string, grand_total: string}
     */
    public function canonicalItemAmounts(
        SupplierPurchaseOrder $order,
        SupplierPurchaseOrderItem $item
    ): array {
        if ($item->tax_affectation_code !== null) {
            return [
                'subtotal' => $this->nonNegative($this->decimal($item->subtotal)),
                'igv' => $this->nonNegative($this->decimal($item->tax_amount)),
                'grand_total' => $this->nonNegative($this->decimal($item->line_total)),
            ];
        }

        $total = $this->firstDecimal($item, ['total_with_igv', 'line_total']);
        if ($total === null) {
            $total = bcmul(
                $this->decimal($item->quantity),
                $this->decimal($item->unit_price),
                self::SCALE
            );
        }

        if (! $order->affect_igv) {
            return ['subtotal' => $total, 'igv' => '0', 'grand_total' => $total];
        }

        $subtotal = $this->firstDecimal($item, ['taxable_base', 'subtotal']);
        $igv = $this->firstDecimal($item, ['igv_amount', 'tax_amount']);

        if ($subtotal === null && $igv !== null) {
            $subtotal = bcsub($total, $igv, self::SCALE);
        } elseif ($subtotal !== null && $igv === null) {
            $igv = bcsub($total, $subtotal, self::SCALE);
        } elseif ($subtotal === null) {
            $header = $this->sourceHeaderTotals($order);
            $subtotal = bccomp($header['grand_total'], '0', self::SCALE) > 0
                ? bcdiv(bcmul($total, $header['subtotal'], self::SCALE), $header['grand_total'], self::SCALE)
                : '0';
            $igv = bcsub($total, $subtotal, self::SCALE);
        }

        return [
            'subtotal' => $this->nonNegative($subtotal),
            'igv' => $this->nonNegative($igv),
            'grand_total' => $this->nonNegative($total),
        ];
    }

    /**
     * @return array{subtotal: string, igv: string, grand_total: string}
     */
    public function proratedItemAmounts(
        SupplierPurchaseOrder $order,
        SupplierPurchaseOrderItem $item,
        string $receivedQuantity
    ): array {
        $orderedQuantity = $this->decimal($item->quantity);
        if (bccomp($orderedQuantity, '0', self::SCALE) <= 0) {
            return ['subtotal' => '0', 'igv' => '0', 'grand_total' => '0'];
        }

        $canonical = $this->canonicalItemAmounts($order, $item);

        return [
            'subtotal' => bcdiv(bcmul($canonical['subtotal'], $receivedQuantity, self::SCALE), $orderedQuantity, self::SCALE),
            'igv' => bcdiv(bcmul($canonical['igv'], $receivedQuantity, self::SCALE), $orderedQuantity, self::SCALE),
            'grand_total' => bcdiv(bcmul($canonical['grand_total'], $receivedQuantity, self::SCALE), $orderedQuantity, self::SCALE),
        ];
    }

    /**
     * @param  array{subtotal: string, igv: string, grand_total: string}  $remaining
     * @return array{subtotal: string, igv: string, grand_total: string}
     */
    private function partialTotals(
        SupplierPurchaseOrder $order,
        string $rawSubtotal,
        string $rawIgv,
        string $rawGrandTotal,
        array $remaining
    ): array {
        $grandTotal = $this->minimum($this->roundMoney($rawGrandTotal), $remaining['grand_total']);

        if (! $order->affect_igv) {
            return ['subtotal' => $grandTotal, 'igv' => '0.00', 'grand_total' => $grandTotal];
        }

        $subtotal = $this->minimum($this->roundMoney($rawSubtotal), $remaining['subtotal']);
        $igv = $this->minimum($this->roundMoney($rawIgv), $remaining['igv']);
        if (bccomp(bcadd($subtotal, $igv, self::MONEY_SCALE), $grandTotal, self::MONEY_SCALE) !== 0) {
            $igv = bcsub($grandTotal, $subtotal, self::MONEY_SCALE);
        }

        if (bccomp($igv, '0', self::MONEY_SCALE) < 0) {
            $subtotal = $grandTotal;
            $igv = '0.00';
        }

        if (bccomp($igv, $remaining['igv'], self::MONEY_SCALE) > 0) {
            $igv = $remaining['igv'];
            $subtotal = bcsub($grandTotal, $igv, self::MONEY_SCALE);
        }

        return [
            'subtotal' => $this->money($subtotal),
            'igv' => $this->money($igv),
            'grand_total' => $this->money($grandTotal),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $receivedItems
     * @param  array<int, SupplierPurchaseOrderItem>  $orderItems
     * @param  array{subtotal: string, igv: string, grand_total: string}  $totals
     * @return array<int, array{subtotal: string, tax_amount: string}>
     */
    private function itemTaxBreakdown(
        SupplierPurchaseOrder $order,
        array $receivedItems,
        array $orderItems,
        array $totals
    ): array {
        $breakdown = [];
        $lineGrandTotal = '0.00';
        $lineSubtotal = '0.00';

        foreach ($receivedItems as $index => $item) {
            $quantity = $this->decimal($item['quantity'] ?? 0);
            $sourceItem = $orderItems[(int) ($item['supplier_purchase_order_item_id'] ?? 0)] ?? null;
            $prorated = $sourceItem
                ? $this->proratedItemAmounts($order, $sourceItem, $quantity)
                : null;
            $lineTotal = $prorated
                ? $this->roundMoney($prorated['grand_total'])
                : $this->roundMoney(bcmul(
                    $quantity,
                    $this->decimal($item['unit_price'] ?? 0),
                    self::SCALE
                ));
            $subtotal = $sourceItem
                ? $this->roundMoney($prorated['subtotal'])
                : $lineTotal;
            $subtotal = $this->minimum($subtotal, $lineTotal);
            $orderedQuantity = $sourceItem ? $this->decimal($sourceItem->quantity) : '0';
            $discount = $sourceItem && bccomp($orderedQuantity, '0', self::SCALE) > 0
                ? $this->roundMoney(bcdiv(bcmul(
                    $this->decimal($sourceItem->discount_amount),
                    $quantity,
                    self::SCALE
                ), $orderedQuantity, self::SCALE))
                : '0.00';

            $breakdown[$index] = [
                'subtotal' => $subtotal,
                'tax_amount' => bcsub($lineTotal, $subtotal, self::MONEY_SCALE),
                'line_total' => $lineTotal,
                'taxable_base' => $subtotal,
                'tax_affectation_code' => $sourceItem?->tax_affectation_code,
                'tax_rate' => $sourceItem?->tax_rate,
                'discount_amount' => $discount,
                'is_free' => (bool) ($sourceItem?->is_free ?? false),
                'igv_recoverable' => $sourceItem?->igv_recoverable,
            ];
            $lineGrandTotal = bcadd($lineGrandTotal, $lineTotal, self::MONEY_SCALE);
            $lineSubtotal = bcadd($lineSubtotal, $subtotal, self::MONEY_SCALE);
        }

        if (bccomp($lineGrandTotal, $totals['grand_total'], self::MONEY_SCALE) !== 0) {
            return $breakdown;
        }

        $difference = bcsub($totals['subtotal'], $lineSubtotal, self::MONEY_SCALE);
        foreach (array_reverse(array_keys($breakdown)) as $index) {
            if (bccomp($difference, '0', self::MONEY_SCALE) === 0) {
                break;
            }

            $lineTotal = bcadd($breakdown[$index]['subtotal'], $breakdown[$index]['tax_amount'], self::MONEY_SCALE);
            if (bccomp($difference, '0', self::MONEY_SCALE) > 0) {
                $adjustment = $this->minimum($difference, bcsub($lineTotal, $breakdown[$index]['subtotal'], self::MONEY_SCALE));
            } else {
                $adjustment = '-'.$this->minimum(ltrim($difference, '-'), $breakdown[$index]['subtotal']);
            }

            $breakdown[$index]['subtotal'] = bcadd($breakdown[$index]['subtotal'], $adjustment, self::MONEY_SCALE);
            $breakdown[$index]['tax_amount'] = bcsub($lineTotal, $breakdown[$index]['subtotal'], self::MONEY_SCALE);
            $difference = bcsub($difference, $adjustment, self::MONEY_SCALE);
        }

        foreach ($breakdown as &$line) {
            $line['taxable_base'] = $line['subtotal'];
        }
        unset($line);

        return $breakdown;
    }

    /**
     * @return array{subtotal: string, igv: string, grand_total: string}
     */
    private function sourceHeaderTotals(SupplierPurchaseOrder $order): array
    {
        $grandTotal = $this->money($order->grand_total);

        if (! $order->affect_igv) {
            return ['subtotal' => $grandTotal, 'igv' => '0.00', 'grand_total' => $grandTotal];
        }

        return [
            'subtotal' => $this->money($order->subtotal),
            'igv' => $this->money($order->igv),
            'grand_total' => $grandTotal,
        ];
    }

    private function firstDecimal(SupplierPurchaseOrderItem $item, array $fields): ?string
    {
        foreach ($fields as $field) {
            $value = $item->getAttribute($field);
            if ($value !== null && $value !== '') {
                return $this->decimal($value);
            }
        }

        return null;
    }

    private function decimal(mixed $value): string
    {
        return bcadd((string) ($value ?? 0), '0', self::SCALE);
    }

    private function roundMoney(mixed $value): string
    {
        $value = $this->decimal($value);
        $increment = bccomp($value, '0', self::SCALE) < 0 ? '-0.005' : '0.005';

        return bcadd($value, $increment, self::MONEY_SCALE);
    }

    private function money(mixed $value): string
    {
        return bcadd((string) ($value ?? 0), '0', self::MONEY_SCALE);
    }

    private function nonNegative(string $value, int $scale = self::SCALE): string
    {
        return bccomp($value, '0', $scale) < 0 ? bcadd('0', '0', $scale) : $value;
    }

    private function minimum(string $left, string $right): string
    {
        return bccomp($left, $right, self::MONEY_SCALE) <= 0 ? $left : $right;
    }
}
