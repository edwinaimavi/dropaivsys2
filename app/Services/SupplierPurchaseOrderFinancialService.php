<?php

namespace App\Services;

use App\Models\SupplierPurchaseOrder;
use InvalidArgumentException;

class SupplierPurchaseOrderFinancialService
{
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
}
