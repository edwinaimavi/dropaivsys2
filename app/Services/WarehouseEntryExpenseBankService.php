<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\CompanyBankAccount;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseEntryExpenseBankService
{
    public function __construct(private BankMovementService $bankMovementService) {}

    public function markAutomaticallyApproved(
        WarehouseEntryExpense $expense,
        ?int $userId,
        bool $preserveAudit = false
    ): void {
        $expense->approval_status = WarehouseEntryExpense::APPROVAL_APPROVED;
        $expense->approved_by = $preserveAudit ? $expense->approved_by : $userId;
        $expense->approved_at = $preserveAudit ? $expense->approved_at : now();
        $expense->approval_observation = null;
    }

    public function approveAndSync(
        WarehouseEntryExpense $expense,
        ?int $userId,
        string $reason
    ): WarehouseEntryExpense {
        return DB::transaction(function () use ($expense, $userId, $reason) {
            $expense = WarehouseEntryExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($expense->status !== 'ACTIVE' || $expense->source_type !== WarehouseEntryExpense::SOURCE_BANK) {
                throw ValidationException::withMessages([
                    'source_type' => 'El gasto debe estar activo y tener Banco como fuente de pago.',
                ]);
            }

            $preserveAudit = $expense->approval_status === WarehouseEntryExpense::APPROVAL_APPROVED
                && $expense->approved_at !== null;
            $this->markAutomaticallyApproved($expense, $userId, $preserveAudit);
            $expense->updated_by = $userId;
            $expense->save();
            $this->syncLocked($expense, $reason, $userId);

            return $expense->fresh(['bankMovement']);
        });
    }

    public function sync(
        WarehouseEntryExpense $expense,
        string $correctionReason,
        ?int $userId,
        ?int $previousMovementId = null
    ): void {
        DB::transaction(fn () => $this->syncLocked(
            $expense,
            $correctionReason,
            $userId,
            $previousMovementId
        ));
    }

    public function cancel(
        WarehouseEntryExpense $expense,
        string $reason,
        ?int $userId,
        ?int $previousMovementId = null
    ): void {
        DB::transaction(fn () => $this->cancelLocked(
            $expense,
            $reason,
            $userId,
            $previousMovementId
        ));
    }

    private function syncLocked(
        WarehouseEntryExpense $expense,
        string $correctionReason,
        ?int $userId,
        ?int $previousMovementId = null
    ): void {
        $shouldHaveMovement = $expense->status === 'ACTIVE'
            && $expense->source_type === WarehouseEntryExpense::SOURCE_BANK
            && $expense->approval_status === WarehouseEntryExpense::APPROVAL_APPROVED;

        if (! $shouldHaveMovement) {
            $this->cancelLocked($expense, $correctionReason, $userId, $previousMovementId);

            return;
        }

        $entry = WarehouseEntry::query()->with('company:id,business_name')->findOrFail($expense->warehouse_entry_id);
        if (! $expense->company_bank_account_id) {
            throw ValidationException::withMessages([
                'company_bank_account_id' => 'El gasto no tiene una cuenta bancaria vinculada.',
            ]);
        }

        $account = CompanyBankAccount::query()
            ->with('currency:id,code')
            ->lockForUpdate()
            ->find($expense->company_bank_account_id);
        $companyName = $entry->company?->business_name ?? 'la empresa del ingreso';
        if (! $account || $account->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'company_bank_account_id' => "Seleccione una cuenta bancaria activa de la empresa {$companyName}.",
            ]);
        }
        if ((int) $account->company_id !== (int) $entry->company_id) {
            throw ValidationException::withMessages([
                'company_bank_account_id' => "La cuenta bancaria seleccionada no pertenece a la empresa {$companyName}.",
            ]);
        }
        if ((int) $account->currency_id !== (int) $expense->currency_id) {
            throw ValidationException::withMessages([
                'company_bank_account_id' => 'La moneda de la cuenta bancaria debe coincidir con la moneda del gasto.',
            ]);
        }

        $amount = round((float) ($expense->supplier_net_amount ?: $expense->total_amount ?: $expense->amount), 4);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'El monto a desembolsar desde banco debe ser mayor a cero.',
            ]);
        }

        $activeMovements = BankMovement::query()
            ->where('source_type', 'WAREHOUSE_ENTRY_EXPENSE')
            ->where('source_id', $expense->id)
            ->where('status', '!=', BankMovement::STATUS_CANCELLED)
            ->lockForUpdate()
            ->get();
        $matchingMovement = $activeMovements->first(fn (BankMovement $movement) => $movement->direction === BankMovement::DIRECTION_OUT
            && (int) $movement->company_bank_account_id === (int) $account->id
            && (int) $movement->currency_id === (int) $expense->currency_id
            && abs((float) $movement->amount - $amount) < 0.00005
        );

        $activeMovements
            ->reject(fn (BankMovement $movement) => $matchingMovement && $movement->is($matchingMovement))
            ->each(fn (BankMovement $movement) => $this->bankMovementService->cancelMovementForSourceCorrection(
                $movement,
                $correctionReason,
                $userId
            ));

        if ($matchingMovement) {
            if ((int) $expense->bank_movement_id !== (int) $matchingMovement->id) {
                $expense->update(['bank_movement_id' => $matchingMovement->id, 'updated_by' => $userId]);
            }

            return;
        }

        $revision = BankMovement::query()
            ->where('source_type', 'WAREHOUSE_ENTRY_EXPENSE')
            ->where('source_id', $expense->id)
            ->count() + 1;
        $movement = $this->bankMovementService->createMovement([
            'company_bank_account_id' => $account->id,
            'currency_id' => $expense->currency_id,
            'movement_date' => $expense->approved_at?->toDateString() ?? now()->toDateString(),
            'movement_type' => 'EGRESO',
            'amount' => $amount,
            'direction' => BankMovement::DIRECTION_OUT,
            'concept' => 'Gasto vinculado de ingreso de almacén',
            'description' => $expense->description,
            'source_type' => 'WAREHOUSE_ENTRY_EXPENSE',
            'source_id' => $expense->id,
            'source_code' => $entry->entry_number.'-GASTO-'.$expense->id,
            'source_description' => 'Gasto vinculado del ingreso '.$entry->entry_number,
            'idempotency_key' => "warehouse-entry-expense:{$expense->id}:v{$revision}",
        ], $userId);

        $expense->update(['bank_movement_id' => $movement->id, 'updated_by' => $userId]);
    }

    private function cancelLocked(
        WarehouseEntryExpense $expense,
        string $reason,
        ?int $userId,
        ?int $previousMovementId = null
    ): void {
        BankMovement::query()
            ->where(function ($query) use ($expense, $previousMovementId) {
                $query->where(function ($sourceQuery) use ($expense) {
                    $sourceQuery->where('source_type', 'WAREHOUSE_ENTRY_EXPENSE')
                        ->where('source_id', $expense->id);
                });
                if ($previousMovementId) {
                    $query->orWhere('id', $previousMovementId);
                }
            })
            ->where('status', '!=', BankMovement::STATUS_CANCELLED)
            ->lockForUpdate()
            ->get()
            ->filter(fn (BankMovement $movement) => $movement->source_type === 'WAREHOUSE_ENTRY_EXPENSE'
                && (int) $movement->source_id === (int) $expense->id
            )
            ->each(fn (BankMovement $movement) => $this->bankMovementService->cancelMovementForSourceCorrection(
                $movement,
                $reason,
                $userId
            ));
    }
}
