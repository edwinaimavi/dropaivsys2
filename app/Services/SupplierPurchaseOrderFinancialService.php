<?php

namespace App\Services;

use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderAdvancePayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SupplierPurchaseOrderFinancialService
{
    private const MONEY_EPSILON = 0.0001;

    public function calculate(
        float $purchaseTotal,
        string $purchaseCurrency,
        string $paymentCurrency,
        bool $applyExchangeRate,
        ?float $exchangeRate,
        bool $applyAdvance,
        ?string $advanceType,
        ?float $advancePercentage,
        ?float $fixedAdvanceAmount,
        float $paidAmount,
        float $paidAmountPen,
        ?string $paymentCondition
    ): array {
        $purchaseCurrency = strtoupper(trim($purchaseCurrency));
        $paymentCurrency = strtoupper(trim($paymentCurrency));
        $exchangeRate = $exchangeRate !== null ? round($exchangeRate, 6) : null;
        $needsExchangeRate = $purchaseCurrency !== $paymentCurrency
            || $purchaseCurrency !== 'PEN'
            || $paymentCurrency !== 'PEN';

        if ($purchaseCurrency !== $paymentCurrency
            && $purchaseCurrency !== 'PEN'
            && $paymentCurrency !== 'PEN') {
            throw new InvalidArgumentException('Una de las monedas debe ser PEN para aplicar el tipo de cambio registrado.');
        }
        if ($needsExchangeRate && ! $applyExchangeRate) {
            throw new InvalidArgumentException('Active el tipo de cambio para convertir la operación y normalizarla en soles.');
        }
        if ($applyExchangeRate && (! $exchangeRate || $exchangeRate <= 0)) {
            throw new InvalidArgumentException('Ingrese un tipo de cambio mayor a cero.');
        }

        $totalPayment = match (true) {
            $purchaseCurrency === $paymentCurrency => $purchaseTotal,
            $paymentCurrency === 'PEN' => $purchaseTotal * $exchangeRate,
            $purchaseCurrency === 'PEN' => $purchaseTotal / $exchangeRate,
            default => $purchaseTotal,
        };
        $totalPen = $paymentCurrency === 'PEN'
            ? $totalPayment
            : $totalPayment * ($exchangeRate ?: 1);

        if (! $applyAdvance) {
            $advanceAmount = 0.0;
            $advancePercentage = null;
            $advanceType = null;
            $advanceStatus = SupplierPurchaseOrder::ADVANCE_NOT_REQUIRED;
        } else {
            if (! in_array($advanceType, ['fixed_amount', 'percentage'], true)) {
                throw new InvalidArgumentException('Seleccione el tipo de anticipo.');
            }
            if ($advanceType === 'percentage') {
                if (! $advancePercentage || $advancePercentage <= 0 || $advancePercentage > 100) {
                    throw new InvalidArgumentException('El porcentaje del anticipo debe ser mayor a 0 y menor o igual a 100.');
                }
                $advanceAmount = $totalPayment * ($advancePercentage / 100);
            } else {
                if (! $fixedAdvanceAmount || $fixedAdvanceAmount <= 0) {
                    throw new InvalidArgumentException('Ingrese un monto de anticipo mayor a cero.');
                }
                $advanceAmount = $fixedAdvanceAmount;
                $advancePercentage = null;
            }

            if ($advanceAmount > $totalPayment + 0.0001) {
                throw new InvalidArgumentException('El anticipo no puede ser mayor al total en la moneda de pago.');
            }
            if ($paidAmount > $advanceAmount + 0.0001) {
                throw new InvalidArgumentException('El monto pagado no puede ser mayor al anticipo requerido.');
            }

            $advanceStatus = match (true) {
                $paidAmount <= 0 => SupplierPurchaseOrder::ADVANCE_PENDING,
                $paidAmount + 0.0001 < $advanceAmount => SupplierPurchaseOrder::ADVANCE_PARTIAL,
                default => SupplierPurchaseOrder::ADVANCE_PAID,
            };
        }

        $advanceAmountPen = $paymentCurrency === 'PEN'
            ? $advanceAmount
            : $advanceAmount * ($exchangeRate ?: 1);
        $isCredit = str_starts_with(strtolower((string) $paymentCondition), 'credito');
        $paymentStatus = match (true) {
            $isCredit => 'credit',
            $paidAmount <= 0 => 'pending',
            $paidAmount + 0.0001 < $totalPayment => 'partial',
            default => 'paid',
        };

        return [
            'apply_exchange_rate' => $applyExchangeRate,
            'exchange_rate' => $applyExchangeRate ? $exchangeRate : null,
            'total_purchase_currency' => round($purchaseTotal, 4),
            'total_payment_currency' => round($totalPayment, 4),
            'total_pen' => round($totalPen, 4),
            'apply_advance' => $applyAdvance,
            'advance_type' => $advanceType,
            'advance_percentage' => $advancePercentage !== null ? round($advancePercentage, 4) : null,
            'advance_amount' => round($advanceAmount, 4),
            'advance_amount_pen' => round($advanceAmountPen, 4),
            'advance_paid_amount' => round($paidAmount, 4),
            'advance_paid_amount_pen' => round($paidAmountPen, 4),
            'advance_status' => $advanceStatus,
            'payment_status' => $paymentStatus,
        ];
    }

    public function amountInPen(float $amount, string $currencyCode, ?float $exchangeRate): float
    {
        if (strtoupper($currencyCode) === 'PEN') {
            return round($amount, 4);
        }
        if (! $exchangeRate || $exchangeRate <= 0) {
            throw new InvalidArgumentException('El pago en moneda extranjera requiere un tipo de cambio mayor a cero.');
        }

        return round($amount * $exchangeRate, 4);
    }

    public function paymentSummary(SupplierPurchaseOrder $order): array
    {
        $order->loadMissing([
            'currency:id,code',
            'paymentCurrency:id,code',
            'advancePayments.currency:id,code',
        ]);

        $purchaseCurrency = strtoupper((string) $order->currency?->code);
        $paymentCurrency = strtoupper((string) ($order->paymentCurrency?->code ?: $purchaseCurrency));
        $exchangeRate = (float) $order->exchange_rate > 0 ? (float) $order->exchange_rate : null;
        $purchaseTotal = (float) $order->total_purchase_currency > 0
            ? (float) $order->total_purchase_currency
            : (float) $order->grand_total;
        $paymentTotal = $this->orderTotalInCurrency(
            $order,
            $purchaseTotal,
            $purchaseCurrency,
            $paymentCurrency,
            $exchangeRate
        );

        $payments = $order->advancePayments
            ->filter(fn (SupplierPurchaseOrderAdvancePayment $payment) =>
                strtoupper((string) $payment->status) === 'ACTIVE' && $payment->deleted_at === null
            )
            ->values();
        $paidInPaymentCurrency = $this->paymentsTotalInCurrency(
            $payments,
            $paymentCurrency,
            $exchangeRate
        );

        // Las órdenes guardadas por el flujo financiero siempre tienen este total calculable.
        // Si un registro histórico carece de TC, se usa la moneda de compra sin inventar conversión.
        $summaryCurrency = $paymentTotal !== null && $paidInPaymentCurrency !== null
            ? $paymentCurrency
            : $purchaseCurrency;
        $orderTotal = $summaryCurrency === $paymentCurrency
            ? $paymentTotal
            : $purchaseTotal;
        $paidTotal = $summaryCurrency === $paymentCurrency
            ? $paidInPaymentCurrency
            : $this->paymentsTotalInCurrency($payments, $purchaseCurrency, $exchangeRate);
        $balance = $orderTotal !== null && $paidTotal !== null
            ? max(round($orderTotal - $paidTotal, 4), 0)
            : null;

        $advanceRequired = $this->requiredAdvanceAmount($order, (float) ($paymentTotal ?? $orderTotal ?? 0));
        $paidForAdvance = $paidInPaymentCurrency ?? 0.0;
        $requiredAdvanceBalance = max(round($advanceRequired - $paidForAdvance, 4), 0);
        $fullyPaid = $balance !== null && $balance <= self::MONEY_EPSILON;
        $storedAdvancePending = ! in_array($order->advance_status, [
            SupplierPurchaseOrder::ADVANCE_PAID,
            SupplierPurchaseOrder::ADVANCE_NOT_REQUIRED,
        ], true);
        $hasPendingAdvance = (bool) $order->apply_advance
            && ! $fullyPaid
            && ($requiredAdvanceBalance > self::MONEY_EPSILON || $storedAdvancePending);
        $isCredit = str_starts_with(
            strtolower(Str::ascii(trim((string) $order->payment_condition))),
            'credito'
        );

        $breakdown = [[
            'currency' => $summaryCurrency,
            'order_total' => $orderTotal !== null ? round($orderTotal, 4) : null,
            'paid_total' => $paidTotal !== null ? round($paidTotal, 4) : null,
            'balance' => $balance,
            'label' => $summaryCurrency === $paymentCurrency ? 'Moneda de pago' : 'Moneda de la orden',
        ]];

        if ($purchaseCurrency !== $summaryCurrency) {
            $paidInPurchaseCurrency = $this->paymentsTotalInCurrency(
                $payments,
                $purchaseCurrency,
                $exchangeRate
            );

            if ($paidInPurchaseCurrency !== null) {
                $breakdown[] = [
                    'currency' => $purchaseCurrency,
                    'order_total' => round($purchaseTotal, 4),
                    'paid_total' => round($paidInPurchaseCurrency, 4),
                    'balance' => max(round($purchaseTotal - $paidInPurchaseCurrency, 4), 0),
                    'label' => 'Moneda de la orden',
                ];
            }
        }

        $paymentsByCurrency = $payments
            ->groupBy(fn (SupplierPurchaseOrderAdvancePayment $payment) =>
                strtoupper((string) ($payment->currency?->code ?: $paymentCurrency))
            )
            ->map(fn (Collection $currencyPayments, string $currency) => [
                'currency' => $currency,
                'amount' => round((float) $currencyPayments->sum('amount'), 4),
            ])
            ->values()
            ->all();

        return [
            'currency' => $summaryCurrency,
            'order_total' => $orderTotal !== null ? round($orderTotal, 4) : null,
            'paid_total' => $paidTotal !== null ? round($paidTotal, 4) : null,
            'balance' => $balance,
            'advance_required' => round($advanceRequired, 4),
            'required_advance_balance' => round($requiredAdvanceBalance, 4),
            'breakdown' => $breakdown,
            'payments_by_currency' => $paymentsByCurrency,
            'has_pending_advance' => $hasPendingAdvance,
            'financial_blocked' => $hasPendingAdvance && ! $isCredit,
            'financial_credit_warning' => $hasPendingAdvance && $isCredit,
            'fully_paid' => $fullyPaid,
        ];
    }

    private function requiredAdvanceAmount(SupplierPurchaseOrder $order, float $paymentTotal): float
    {
        if (! $order->apply_advance) {
            return 0.0;
        }

        if ($order->advance_type === 'percentage' && (float) $order->advance_percentage > 0) {
            return round($paymentTotal * ((float) $order->advance_percentage / 100), 4);
        }

        return max(round((float) $order->advance_amount, 4), 0);
    }

    private function orderTotalInCurrency(
        SupplierPurchaseOrder $order,
        float $purchaseTotal,
        string $purchaseCurrency,
        string $targetCurrency,
        ?float $exchangeRate
    ): ?float {
        if ($targetCurrency === strtoupper((string) ($order->paymentCurrency?->code ?: $purchaseCurrency))
            && (float) $order->total_payment_currency > 0) {
            return round((float) $order->total_payment_currency, 4);
        }

        if ($purchaseCurrency === $targetCurrency) {
            return round($purchaseTotal, 4);
        }

        if (! $exchangeRate) {
            return null;
        }

        return match (true) {
            $targetCurrency === 'PEN' => round($purchaseTotal * $exchangeRate, 4),
            $purchaseCurrency === 'PEN' => round($purchaseTotal / $exchangeRate, 4),
            default => null,
        };
    }

    private function paymentsTotalInCurrency(
        Collection $payments,
        string $targetCurrency,
        ?float $orderExchangeRate
    ): ?float {
        $total = 0.0;

        foreach ($payments as $payment) {
            $converted = $this->paymentAmountInCurrency($payment, $targetCurrency, $orderExchangeRate);

            if ($converted === null) {
                return null;
            }

            $total += $converted;
        }

        return round($total, 4);
    }

    private function paymentAmountInCurrency(
        SupplierPurchaseOrderAdvancePayment $payment,
        string $targetCurrency,
        ?float $orderExchangeRate
    ): ?float {
        $sourceCurrency = strtoupper((string) ($payment->currency?->code ?: ''));
        $amount = (float) $payment->amount;
        $amountPen = (float) $payment->amount_pen;
        $exchangeRate = (float) $payment->exchange_rate > 0
            ? (float) $payment->exchange_rate
            : $orderExchangeRate;

        if ($sourceCurrency === $targetCurrency) {
            return round($amount, 4);
        }

        if ($targetCurrency === 'PEN') {
            if ($amountPen > 0) {
                return round($amountPen, 4);
            }

            return $exchangeRate ? round($amount * $exchangeRate, 4) : null;
        }

        if ($sourceCurrency === 'PEN') {
            return $exchangeRate ? round($amount / $exchangeRate, 4) : null;
        }

        if ($amountPen > 0 && $orderExchangeRate) {
            return round($amountPen / $orderExchangeRate, 4);
        }

        return null;
    }
}
