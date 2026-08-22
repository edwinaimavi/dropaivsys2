<?php

namespace App\Services;

use App\Models\PettyCashBox;
use App\Models\PettyCashExpenseExchangeReturn;
use Illuminate\Support\Facades\Schema;

class PettyCashCalculator
{
    public function calculate(PettyCashBox $cashBox): array
    {
        $approvedAmount = round((float) ($cashBox->approved_amount_snapshot ?? $cashBox->opening_amount), 2);
        $initialFund = round((float) $cashBox->opening_amount, 2);
        $totalExpenses = round((float) $cashBox->expenses()
            ->where('status', 'ACTIVE')
            ->approved()
            ->sum('amount'), 2);
        $pendingApprovalExpenses = round((float) $cashBox->expenses()
            ->where('status', 'ACTIVE')
            ->pendingApproval()
            ->sum('amount'), 2);
        $totalReplenished = round((float) $cashBox->replenishments()->where('status', 'ACTIVE')->sum('amount'), 2);
        $totalReturned = Schema::hasTable('petty_cash_expense_exchange_returns')
            ? round((float) PettyCashExpenseExchangeReturn::query()
                ->where('petty_cash_box_id', $cashBox->id)
                ->where('status', PettyCashExpenseExchangeReturn::STATUS_ACTIVE)
                ->sum('amount'), 2)
            : 0.0;

        return [
            ...$this->calculateValues($approvedAmount, $initialFund, $totalExpenses, $totalReplenished, $totalReturned),
            'total_returns' => $totalReturned,
            'pending_approval_expenses' => $pendingApprovalExpenses,
        ];
    }

    public function calculateValues(
        float $approvedAmount,
        float $initialFund,
        float $totalExpenses,
        float $totalReplenished,
        float $totalReturned = 0
    ): array {
        $approvedAmount = round(max(0, $approvedAmount), 2);
        $initialFund = round(max(0, $initialFund), 2);
        $totalExpenses = round(max(0, $totalExpenses), 2);
        $totalReplenished = round(max(0, $totalReplenished), 2);
        $totalReturned = round(max(0, $totalReturned), 2);

        return [
            'approved_amount' => (float) $approvedAmount,
            'initial_fund' => (float) $initialFund,
            'total_expenses' => (float) $totalExpenses,
            'total_replenished' => (float) $totalReplenished,
            'current_balance' => (float) max(0, round($initialFund + $totalReplenished + $totalReturned - $totalExpenses, 2)),
            'pending_replenishment' => (float) max(0, round($totalExpenses - $totalReplenished - $totalReturned, 2)),
        ];
    }

    public function opening(float $approvedAmount, ?float $availableBalance = null): array
    {
        $approvedAmount = round(max(0, $approvedAmount), 2);
        $hasPreviousBalance = $availableBalance !== null;
        $availableBalance = round(max(0, (float) $availableBalance), 2);
        $fundToReplenish = $hasPreviousBalance
            ? max(0, round($approvedAmount - $availableBalance, 2))
            : 0;

        return [
            'available_balance' => (float) $availableBalance,
            'fund_to_replenish' => (float) $fundToReplenish,
            'initial_fund' => (float) ($hasPreviousBalance
                ? round($availableBalance + $fundToReplenish, 2)
                : $approvedAmount),
        ];
    }
}
