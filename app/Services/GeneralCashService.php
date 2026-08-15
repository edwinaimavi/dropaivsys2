<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\CompanyBankAccount;
use App\Models\GeneralCashBox;
use App\Models\GeneralCashExpense;
use App\Models\GeneralCashMovement;
use App\Models\GeneralCashReconciliation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GeneralCashService
{
    public function __construct(private readonly BankMovementService $bankMovementService) {}

    public function fundFromBank(array $data, ?int $userId): GeneralCashMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $idempotencyKey = $data['idempotency_key'] ?? null;
            if ($idempotencyKey && ($existing = GeneralCashMovement::query()->where('idempotency_key', $idempotencyKey)->first())) {
                return $existing->load(['box.currency', 'bankAccount.bank', 'bankMovement', 'documents']);
            }

            $box = GeneralCashBox::query()->with('currency')->lockForUpdate()->findOrFail($data['general_cash_box_id']);
            $account = CompanyBankAccount::query()->with(['currency', 'bank'])->lockForUpdate()
                ->findOrFail($data['company_bank_account_id']);
            $amount = round((float) $data['amount'], 4);
            $this->validateActiveBox($box);

            if ($account->status !== 'ACTIVE') {
                throw ValidationException::withMessages(['company_bank_account_id' => 'La cuenta bancaria origen no está activa.']);
            }
            if ((int) $account->company_id !== (int) $box->company_id) {
                throw ValidationException::withMessages(['company_bank_account_id' => 'La cuenta bancaria debe pertenecer a la misma empresa de Caja General.']);
            }
            if ((int) $account->currency_id !== (int) $box->currency_id) {
                throw ValidationException::withMessages(['company_bank_account_id' => 'La moneda de la cuenta bancaria debe coincidir con la moneda de Caja General.']);
            }
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor a cero.']);
            }
            if ((float) $account->current_balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'La cuenta bancaria origen no tiene saldo suficiente.']);
            }
            $duplicate = GeneralCashMovement::query()
                ->where('source_type', GeneralCashMovement::SOURCE_BANK_FUNDING)
                ->where('company_bank_account_id', $account->id)
                ->where('operation_number', $data['operation_number'])
                ->where('status', '!=', GeneralCashMovement::STATUS_CANCELLED)
                ->first();
            if ($duplicate) {
                if ((int) $duplicate->general_cash_box_id === (int) $box->id
                    && round((float) $duplicate->amount, 4) === $amount) {
                    return $duplicate->load(['box.currency', 'bankAccount.bank', 'bankMovement', 'documents']);
                }
                throw ValidationException::withMessages([
                    'operation_number' => 'El número de operación bancaria ya está vinculado a otro ingreso de Caja General.',
                ]);
            }

            $movement = $this->createCashMovementLocked($box, [
                'movement_date' => $data['movement_date'],
                'direction' => GeneralCashMovement::DIRECTION_IN,
                'movement_type' => GeneralCashMovement::TYPE_INCOME,
                'source_type' => GeneralCashMovement::SOURCE_BANK_FUNDING,
                'amount' => $amount,
                'company_bank_account_id' => $account->id,
                'operation_number' => $data['operation_number'],
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'responsible_name' => $data['responsible_name'] ?? null,
                'description' => $data['description'] ?? 'Ingreso de efectivo desde banco',
                'observation' => $data['observation'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ], $userId);
            $movement->update(['source_id' => $movement->id]);

            $bankMovement = $this->bankMovementService->createMovement([
                'company_bank_account_id' => $account->id,
                'currency_id' => $account->currency_id,
                'movement_date' => $data['movement_date'],
                'movement_type' => 'EGRESO',
                'amount' => $amount,
                'direction' => BankMovement::DIRECTION_OUT,
                'concept' => 'Retiro para Caja General',
                'description' => $data['observation'] ?? $box->name,
                'operation_number' => $data['operation_number'],
                'source_type' => GeneralCashMovement::SOURCE_BANK_FUNDING,
                'source_id' => $movement->id,
                'source_code' => $movement->code,
                'source_description' => 'Ingreso de efectivo a '.$box->code,
                'idempotency_key' => 'general-cash-bank:'.$movement->id,
            ], $userId);
            $movement->update(['bank_movement_id' => $bankMovement->id]);
            $this->attachDocuments($movement, $data['documents'] ?? [], $userId);

            return $movement->fresh(['box.currency', 'bankAccount.bank', 'bankMovement', 'documents']);
        });
    }

    public function createExpense(array $data, ?int $userId): GeneralCashExpense
    {
        return DB::transaction(function () use ($data, $userId) {
            $idempotencyKey = $data['idempotency_key'] ?? null;
            if ($idempotencyKey && ($existing = GeneralCashExpense::query()->where('idempotency_key', $idempotencyKey)->first())) {
                return $existing->load(['box.currency', 'movement', 'documents']);
            }

            $box = GeneralCashBox::query()->with('currency')->lockForUpdate()->findOrFail($data['general_cash_box_id']);
            $this->validateActiveBox($box);
            $amount = round((float) $data['amount'], 4);
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'El importe debe ser mayor a cero.']);
            }
            if ((float) $box->current_balance < $amount) {
                throw ValidationException::withMessages(['amount' => 'Caja General no tiene saldo suficiente para registrar este gasto.']);
            }

            $documentType = GeneralCashExpense::normalizeDocumentType($data['document_type'] ?? null);
            if (! in_array($documentType, GeneralCashExpense::DOCUMENT_TYPES, true)) {
                throw ValidationException::withMessages(['document_type' => 'Seleccione un tipo de comprobante válido.']);
            }
            $official = GeneralCashExpense::isOfficial($documentType);
            $affectsIgv = (bool) ($data['affects_igv'] ?? false);
            if ($affectsIgv && ! in_array($documentType, ['FACTURA', 'BOLETA'], true)) {
                throw ValidationException::withMessages(['affects_igv' => 'Solo una factura o boleta puede registrarse como afecta a IGV.']);
            }
            if ($official && (blank($data['document_series'] ?? null) || blank($data['document_number'] ?? null))) {
                throw ValidationException::withMessages(['document_number' => 'Ingrese la serie y número del comprobante oficial.']);
            }

            $taxableBase = $affectsIgv ? round($amount / 1.18, 4) : $amount;
            $expense = GeneralCashExpense::create([
                ...$data,
                'code' => $this->code('GCE'),
                'company_id' => $box->company_id,
                'document_type' => $documentType,
                'amount' => $amount,
                'affects_igv' => $affectsIgv,
                'taxable_base' => $taxableBase,
                'igv_amount' => $affectsIgv ? round($amount - $taxableBase, 4) : 0,
                'expense_classification' => $official
                    ? GeneralCashExpense::CLASSIFICATION_OFFICIAL
                    : GeneralCashExpense::CLASSIFICATION_UNSUPPORTED,
                'status' => GeneralCashExpense::STATUS_REGISTERED,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $movement = $this->createCashMovementLocked($box, [
                'movement_date' => $data['expense_date'],
                'direction' => GeneralCashMovement::DIRECTION_OUT,
                'movement_type' => GeneralCashMovement::TYPE_EXPENSE,
                'source_type' => GeneralCashMovement::SOURCE_EXPENSE,
                'source_id' => $expense->id,
                'amount' => $amount,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'responsible_name' => $data['person_name'] ?? null,
                'description' => $data['concept'],
                'observation' => $data['observation'] ?? null,
                'idempotency_key' => $idempotencyKey ? 'movement:'.$idempotencyKey : null,
            ], $userId);
            $expense->update(['general_cash_movement_id' => $movement->id]);
            $this->attachDocuments($expense, $data['documents'] ?? [], $userId);

            return $expense->fresh(['box.currency', 'movement', 'supplier', 'documents']);
        });
    }

    public function approveExpense(GeneralCashExpense $expense, ?int $userId): GeneralCashExpense
    {
        return DB::transaction(function () use ($expense, $userId) {
            $expense = GeneralCashExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if (! in_array($expense->status, [GeneralCashExpense::STATUS_REGISTERED, GeneralCashExpense::STATUS_OBSERVED], true)) {
                throw ValidationException::withMessages(['expense' => 'El gasto no está disponible para aprobación.']);
            }
            $expense->update([
                'status' => GeneralCashExpense::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'observed_by' => null,
                'observed_at' => null,
                'observation_reason' => null,
                'updated_by' => $userId,
            ]);

            return $expense->fresh(['approver']);
        });
    }

    public function observeExpense(GeneralCashExpense $expense, string $reason, ?int $userId): GeneralCashExpense
    {
        return DB::transaction(function () use ($expense, $reason, $userId) {
            $expense = GeneralCashExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if (! in_array($expense->status, [GeneralCashExpense::STATUS_REGISTERED, GeneralCashExpense::STATUS_APPROVED], true)) {
                throw ValidationException::withMessages(['expense' => 'El gasto no está disponible para observación.']);
            }
            $expense->update([
                'status' => GeneralCashExpense::STATUS_OBSERVED,
                'observed_by' => $userId,
                'observed_at' => now(),
                'observation_reason' => $reason,
                'updated_by' => $userId,
            ]);

            return $expense->fresh(['observer']);
        });
    }

    public function cancelExpense(GeneralCashExpense $expense, string $reason, ?int $userId): GeneralCashExpense
    {
        return DB::transaction(function () use ($expense, $reason, $userId) {
            $expense = GeneralCashExpense::query()->lockForUpdate()->findOrFail($expense->id);
            if ($expense->status === GeneralCashExpense::STATUS_CANCELLED) {
                throw ValidationException::withMessages(['expense' => 'El gasto ya se encuentra anulado.']);
            }
            $movement = GeneralCashMovement::query()->lockForUpdate()->findOrFail($expense->general_cash_movement_id);
            $this->cancelCashMovementLocked($movement, $reason, $userId);
            $expense->update([
                'status' => GeneralCashExpense::STATUS_CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'updated_by' => $userId,
            ]);

            return $expense->fresh(['movement.reversal', 'canceller']);
        });
    }

    public function cancelFunding(GeneralCashMovement $movement, string $reason, ?int $userId): GeneralCashMovement
    {
        return DB::transaction(function () use ($movement, $reason, $userId) {
            $movement = GeneralCashMovement::query()->lockForUpdate()->findOrFail($movement->id);
            if ($movement->source_type !== GeneralCashMovement::SOURCE_BANK_FUNDING) {
                throw ValidationException::withMessages(['movement' => 'Solo se pueden anular ingresos de Caja General provenientes de banco.']);
            }
            $this->cancelCashMovementLocked($movement, $reason, $userId);
            if ($movement->bank_movement_id) {
                $this->bankMovementService->cancelMovementForSourceCorrection(
                    BankMovement::query()->findOrFail($movement->bank_movement_id),
                    $reason,
                    $userId
                );
            }

            return $movement->fresh(['reversal', 'bankMovement.reversal']);
        });
    }

    public function reconcile(array $data, ?int $userId): GeneralCashReconciliation
    {
        return DB::transaction(function () use ($data, $userId) {
            $box = GeneralCashBox::query()->lockForUpdate()->findOrFail($data['general_cash_box_id']);
            $this->validateActiveBox($box);
            $systemBalance = round((float) $box->current_balance, 4);
            $physicalBalance = round((float) $data['physical_balance'], 4);
            $reconciliation = GeneralCashReconciliation::create([
                ...$data,
                'code' => $this->code('GCA'),
                'company_id' => $box->company_id,
                'system_balance' => $systemBalance,
                'physical_balance' => $physicalBalance,
                'difference' => round($physicalBalance - $systemBalance, 4),
                'status' => GeneralCashReconciliation::STATUS_CLOSED,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $this->attachDocuments($reconciliation, $data['documents'] ?? [], $userId);

            return $reconciliation->fresh(['box.currency', 'responsible', 'documents']);
        });
    }

    private function createCashMovementLocked(
        GeneralCashBox $box,
        array $data,
        ?int $userId,
        bool $requireActive = true
    ): GeneralCashMovement {
        if (! empty($data['idempotency_key'])
            && ($existing = GeneralCashMovement::query()->where('idempotency_key', $data['idempotency_key'])->first())) {
            return $existing;
        }
        $box = GeneralCashBox::query()->lockForUpdate()->findOrFail($box->id);
        if ($requireActive) {
            $this->validateActiveBox($box);
        }
        $amount = round((float) $data['amount'], 4);
        $previous = round((float) $box->current_balance, 4);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor a cero.']);
        }
        if ($data['direction'] === GeneralCashMovement::DIRECTION_OUT && $previous < $amount) {
            throw ValidationException::withMessages(['amount' => 'Caja General no tiene saldo suficiente para esta operación.']);
        }
        $newBalance = $data['direction'] === GeneralCashMovement::DIRECTION_IN
            ? $previous + $amount
            : $previous - $amount;
        $box->update(['current_balance' => $newBalance, 'updated_by' => $userId]);

        return GeneralCashMovement::create([
            ...$data,
            'code' => $this->code('GCM'),
            'general_cash_box_id' => $box->id,
            'company_id' => $box->company_id,
            'previous_balance' => $previous,
            'new_balance' => $newBalance,
            'status' => GeneralCashMovement::STATUS_REGISTERED,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function cancelCashMovementLocked(GeneralCashMovement $movement, string $reason, ?int $userId): GeneralCashMovement
    {
        if ($movement->status === GeneralCashMovement::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['movement' => 'El movimiento ya se encuentra anulado.']);
        }
        if ($movement->movement_type === GeneralCashMovement::TYPE_REVERSAL) {
            throw ValidationException::withMessages(['movement' => 'Una reversa no puede anularse directamente.']);
        }
        $box = GeneralCashBox::query()->lockForUpdate()->findOrFail($movement->general_cash_box_id);
        if ($movement->direction === GeneralCashMovement::DIRECTION_IN
            && (float) $box->current_balance < (float) $movement->amount) {
            throw ValidationException::withMessages([
                'movement' => 'No se puede anular el ingreso porque el efectivo ya fue utilizado y Caja General no tiene saldo suficiente.',
            ]);
        }
        $movement->update([
            'status' => GeneralCashMovement::STATUS_CANCELLED,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'updated_by' => $userId,
        ]);
        $reversal = $this->createCashMovementLocked($box, [
            'movement_date' => now(),
            'direction' => $movement->direction === GeneralCashMovement::DIRECTION_IN
                ? GeneralCashMovement::DIRECTION_OUT
                : GeneralCashMovement::DIRECTION_IN,
            'movement_type' => GeneralCashMovement::TYPE_REVERSAL,
            'source_type' => GeneralCashMovement::SOURCE_REVERSAL,
            'source_id' => $movement->id,
            'amount' => $movement->amount,
            'description' => 'Reversa de '.$movement->code,
            'observation' => $reason,
            'reversal_of_id' => $movement->id,
            'idempotency_key' => 'general-cash-reversal:'.$movement->id,
        ], $userId, false);
        $movement->setRelation('reversal', $reversal);

        return $movement;
    }

    private function attachDocuments(Model $owner, array $documents, ?int $userId): void
    {
        foreach ($documents as $document) {
            if (empty($document['file_path'])) {
                continue;
            }
            $owner->documents()->create([
                'original_name' => $document['original_name'] ?? basename($document['file_path']),
                'stored_name' => basename($document['file_path']),
                'file_path' => $document['file_path'],
                'mime_type' => $document['mime_type'] ?? null,
                'extension' => pathinfo($document['file_path'], PATHINFO_EXTENSION),
                'file_size' => $document['file_size'] ?? null,
                'observation' => $document['category'] ?? 'GENERAL_CASH_OTHER',
                'status' => 'ACTIVE',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function validateActiveBox(GeneralCashBox $box): void
    {
        if ($box->status !== GeneralCashBox::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['general_cash_box_id' => 'La Caja General seleccionada no está activa.']);
        }
    }

    private function code(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, now()->format('YmdHis'), Str::upper(Str::random(6)));
    }
}
