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
        float $paidAppliedAmount,
        float $paidAmountPen,
        ?string $paymentCondition
    ): array {
        $purchaseCurrency = strtoupper(trim($purchaseCurrency));
        $paymentCurrency = strtoupper(trim($paymentCurrency));
        $exchangeRate = $exchangeRate !== null && $exchangeRate > 0
            ? round($exchangeRate, 6)
            : null;

        if ($purchaseCurrency !== $paymentCurrency
            && $purchaseCurrency !== 'PEN'
            && $paymentCurrency !== 'PEN') {
            throw new InvalidArgumentException('Una de las monedas debe ser PEN para usar un tipo de cambio referencial.');
        }

        $totalPayment = $this->convertAmount(
            $purchaseTotal,
            $purchaseCurrency,
            $paymentCurrency,
            $exchangeRate
        ) ?? $purchaseTotal;
        $totalPen = $purchaseCurrency === 'PEN'
            ? $purchaseTotal
            : ($exchangeRate ? $purchaseTotal * $exchangeRate : 0.0);

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
                $advanceAmount = $purchaseTotal * ($advancePercentage / 100);
            } else {
                if (! $fixedAdvanceAmount || $fixedAdvanceAmount <= 0) {
                    throw new InvalidArgumentException('Ingrese un monto de anticipo mayor a cero.');
                }
                $advanceAmount = $fixedAdvanceAmount;
                $advancePercentage = null;
            }

            if ($advanceAmount > $purchaseTotal + self::MONEY_EPSILON) {
                throw new InvalidArgumentException('El anticipo no puede ser mayor al total de la compra.');
            }
            if ($paidAppliedAmount > $advanceAmount + self::MONEY_EPSILON) {
                throw new InvalidArgumentException('El monto aplicado no puede ser mayor al anticipo pendiente.');
            }

            $advanceStatus = match (true) {
                $paidAppliedAmount <= 0 => SupplierPurchaseOrder::ADVANCE_PENDING,
                $paidAppliedAmount + self::MONEY_EPSILON < $advanceAmount => SupplierPurchaseOrder::ADVANCE_PARTIAL,
                default => SupplierPurchaseOrder::ADVANCE_PAID,
            };
        }

        $advanceAmountPen = $purchaseCurrency === 'PEN'
            ? $advanceAmount
            : ($exchangeRate ? $advanceAmount * $exchangeRate : 0.0);
        $isCredit = str_starts_with(strtolower((string) $paymentCondition), 'credito');
        $paymentStatus = match (true) {
            $isCredit => 'credit',
            $paidAppliedAmount <= 0 => 'pending',
            $paidAppliedAmount + self::MONEY_EPSILON < $purchaseTotal => 'partial',
            default => 'paid',
        };

        return [
            'apply_exchange_rate' => $exchangeRate !== null,
            'exchange_rate' => $exchangeRate,
            'total_purchase_currency' => round($purchaseTotal, 4),
            'total_payment_currency' => round($totalPayment, 4),
            'total_pen' => round($totalPen, 4),
            'apply_advance' => $applyAdvance,
            'advance_type' => $advanceType,
            'advance_percentage' => $advancePercentage !== null ? round($advancePercentage, 4) : null,
            'advance_amount' => round($advanceAmount, 4),
            'advance_amount_pen' => round($advanceAmountPen, 4),
            'advance_paid_amount' => round($paidAppliedAmount, 4),
            'advance_paid_amount_pen' => round($paidAmountPen, 4),
            'advance_status' => $advanceStatus,
            'payment_status' => $paymentStatus,
        ];
    }

    public function convertAppliedToPaid(
        float $appliedAmount,
        string $purchaseCurrency,
        string $paymentCurrency,
        ?float $exchangeRate
    ): float {
        $converted = $this->convertAmount(
            $appliedAmount,
            strtoupper(trim($purchaseCurrency)),
            strtoupper(trim($paymentCurrency)),
            $exchangeRate
        );

        if ($converted === null) {
            throw new InvalidArgumentException('Ingrese el tipo de cambio de este pago.');
        }

        return round($converted, 4);
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

    public function effectiveAppliedAmount(
        SupplierPurchaseOrderAdvancePayment $payment,
        SupplierPurchaseOrder $order
    ): ?float {
        if ($payment->applied_amount !== null) {
            return round((float) $payment->applied_amount, 4);
        }

        $purchaseCurrency = strtoupper((string) (
            $payment->purchaseCurrency?->code ?: $order->currency?->code
        ));
        $paymentCurrency = strtoupper((string) (
            $payment->currency?->code ?: $order->paymentCurrency?->code ?: $purchaseCurrency
        ));
        $rate = (float) $payment->exchange_rate > 0
            ? (float) $payment->exchange_rate
            : ((float) $order->exchange_rate > 0 ? (float) $order->exchange_rate : null);

        return $this->convertAmount(
            (float) $payment->amount,
            $paymentCurrency,
            $purchaseCurrency,
            $rate
        );
    }

    public function paymentSummary(SupplierPurchaseOrder $order): array
    {
        $order->loadMissing([
            'currency:id,code',
            'paymentCurrency:id,code',
            'advancePayments.currency:id,code',
            'advancePayments.purchaseCurrency:id,code',
        ]);

        $purchaseCurrency = strtoupper((string) $order->currency?->code);
        $purchaseTotal = (float) $order->total_purchase_currency > 0
            ? (float) $order->total_purchase_currency
            : (float) $order->grand_total;
        $payments = $order->advancePayments
            ->filter(fn (SupplierPurchaseOrderAdvancePayment $payment) =>
                strtoupper((string) $payment->status) === 'ACTIVE' && $payment->deleted_at === null
            )
            ->values();

        $appliedAmounts = $payments->map(
            fn (SupplierPurchaseOrderAdvancePayment $payment) => $this->effectiveAppliedAmount($payment, $order)
        );
        $paidApplied = $appliedAmounts->contains(null)
            ? null
            : round((float) $appliedAmounts->sum(), 4);
        $balance = $paidApplied !== null
            ? max(round($purchaseTotal - $paidApplied, 4), 0)
            : null;
        $advanceRequired = $this->requiredAdvanceAmount($order, $purchaseTotal);
        $requiredAdvanceBalance = $paidApplied !== null
            ? max(round($advanceRequired - $paidApplied, 4), 0)
            : $advanceRequired;
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
        $paymentsByCurrency = $payments
            ->groupBy(fn (SupplierPurchaseOrderAdvancePayment $payment) =>
                strtoupper((string) ($payment->currency?->code ?: $purchaseCurrency))
            )
            ->map(fn (Collection $currencyPayments, string $currency) => [
                'currency' => $currency,
                'amount' => round((float) $currencyPayments->sum('amount'), 4),
            ])
            ->values()
            ->all();

        return [
            'currency' => $purchaseCurrency,
            'order_total' => round($purchaseTotal, 4),
            'paid_total' => $paidApplied,
            'balance' => $balance,
            'advance_required' => round($advanceRequired, 4),
            'required_advance_balance' => round($requiredAdvanceBalance, 4),
            'breakdown' => [[
                'currency' => $purchaseCurrency,
                'order_total' => round($purchaseTotal, 4),
                'paid_total' => $paidApplied,
                'balance' => $balance,
                'label' => 'Moneda de la compra',
            ]],
            'payments_by_currency' => $paymentsByCurrency,
            'has_pending_advance' => $hasPendingAdvance,
            'financial_blocked' => $hasPendingAdvance && ! $isCredit,
            'financial_credit_warning' => $hasPendingAdvance && $isCredit,
            'fully_paid' => $fullyPaid,
        ];
    }

    private function requiredAdvanceAmount(SupplierPurchaseOrder $order, float $purchaseTotal): float
    {
        if (! $order->apply_advance) {
            return 0.0;
        }

        if ($order->advance_type === 'percentage' && (float) $order->advance_percentage > 0) {
            return round($purchaseTotal * ((float) $order->advance_percentage / 100), 4);
        }

        return max(round((float) $order->advance_amount, 4), 0);
    }

    private function convertAmount(
        float $amount,
        string $sourceCurrency,
        string $targetCurrency,
        ?float $exchangeRate
    ): ?float {
        $sourceCurrency = strtoupper(trim($sourceCurrency));
        $targetCurrency = strtoupper(trim($targetCurrency));

        if ($sourceCurrency === $targetCurrency) {
            return round($amount, 4);
        }
        if ($sourceCurrency !== 'PEN' && $targetCurrency !== 'PEN') {
            return null;
        }
        if (! $exchangeRate || $exchangeRate <= 0) {
            return null;
        }

        return $targetCurrency === 'PEN'
            ? round($amount * $exchangeRate, 4)
            : round($amount / $exchangeRate, 4);
    }
}
