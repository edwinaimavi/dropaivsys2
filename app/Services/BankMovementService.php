<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\BankReconciliation;
use App\Models\BankTransfer;
use App\Models\CompanyBankAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BankMovementService
{
    public function createMovement(array $data, ?int $userId): BankMovement
    {
        return DB::transaction(fn () => $this->createMovementLocked($data, $userId));
    }

    public function configureOpeningBalance(
        CompanyBankAccount $account,
        string $amount,
        string $date,
        ?string $observation,
        ?string $exchangeRate,
        ?int $userId
    ): CompanyBankAccount {
        return DB::transaction(function () use ($account, $amount, $date, $observation, $exchangeRate, $userId) {
            $account = CompanyBankAccount::query()->with('currency')->lockForUpdate()->findOrFail($account->id);
            $existing = $account->movements()
                ->where('source_type', 'BANK_OPENING_BALANCE')
                ->where('status', '!=', BankMovement::STATUS_CANCELLED)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $this->cancelMovementLocked($existing, 'RECONFIGURACIÓN DEL SALDO INICIAL', $userId);
            }

            $account->update([
                'opening_balance' => $amount,
                'opening_balance_date' => $date,
                'opening_balance_observation' => $observation,
                'opening_balance_set_by' => $userId,
                'opening_balance_set_at' => now(),
                'updated_by' => $userId,
            ]);

            if ((float) $amount > 0) {
                $this->createMovementLocked([
                    'company_bank_account_id' => $account->id,
                    'currency_id' => $account->currency_id,
                    'movement_date' => $date,
                    'movement_type' => 'SALDO_INICIAL',
                    'amount' => $amount,
                    'exchange_rate' => $exchangeRate,
                    'direction' => BankMovement::DIRECTION_IN,
                    'concept' => 'Saldo inicial de la cuenta',
                    'description' => $observation,
                    'source_type' => 'BANK_OPENING_BALANCE',
                    'source_id' => $account->id,
                    'source_code' => $account->account_number,
                    'source_description' => 'Configuración del saldo inicial',
                ], $userId);
            }

            return $account->fresh(['bank', 'currency', 'openingBalanceSetter']);
        });
    }

    public function createTransfer(array $data, ?int $userId): BankTransfer
    {
        return DB::transaction(function () use ($data, $userId) {
            $accountIds = [(int) $data['from_company_bank_account_id'], (int) $data['to_company_bank_account_id']];
            sort($accountIds);
            $accounts = CompanyBankAccount::query()
                ->with('currency')
                ->whereIn('id', $accountIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $from = $accounts->get((int) $data['from_company_bank_account_id']);
            $to = $accounts->get((int) $data['to_company_bank_account_id']);

            if (! $from || ! $to || $from->id === $to->id) {
                throw ValidationException::withMessages([
                    'to_company_bank_account_id' => 'No puede transferir a la misma cuenta bancaria.',
                ]);
            }
            if ($from->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'from_company_bank_account_id' => 'La cuenta bancaria origen no existe o no se encuentra activa.',
                ]);
            }
            if ($to->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'to_company_bank_account_id' => 'La cuenta bancaria destino no existe o no se encuentra activa.',
                ]);
            }

            $sourceCode = strtoupper((string) $from->currency?->code);
            $destinationCode = strtoupper((string) $to->currency?->code);
            if ($sourceCode !== $destinationCode && $sourceCode !== 'PEN' && $destinationCode !== 'PEN') {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Para transferir entre monedas extranjeras una de las cuentas debe estar expresada en PEN.',
                ]);
            }

            $amount = (float) $data['amount'];
            $rate = isset($data['exchange_rate']) ? (float) $data['exchange_rate'] : null;
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor a cero.']);
            }
            if ((float) $from->current_balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'La cuenta bancaria origen no tiene saldo suficiente para realizar la transferencia.',
                ]);
            }

            $sameCurrency = $sourceCode === $destinationCode;
            if (! $sameCurrency && (! $rate || $rate <= 0)) {
                throw ValidationException::withMessages([
                    'exchange_rate' => 'Ingrese un tipo de cambio mayor a cero para transferir entre monedas distintas.',
                ]);
            }
            $effectiveRate = $rate ?: 1.0;
            $amountPen = $sourceCode === 'PEN' ? $amount : $amount * $effectiveRate;
            $destinationAmount = match (true) {
                $sameCurrency => $amount,
                $destinationCode === 'PEN' => $amountPen,
                default => $amountPen / $effectiveRate,
            };
            $storedRate = ($sourceCode === 'PEN' && $destinationCode === 'PEN') ? null : $effectiveRate;

            $transfer = BankTransfer::create([
                'code' => $this->code('TRF'),
                'company_id' => $from->company_id,
                'from_company_bank_account_id' => $from->id,
                'to_company_bank_account_id' => $to->id,
                'transfer_date' => $data['transfer_date'],
                'amount' => $amount,
                'currency_id' => $from->currency_id,
                'destination_amount' => $destinationAmount,
                'destination_currency_id' => $to->currency_id,
                'exchange_rate' => $storedRate,
                'amount_pen' => $amountPen,
                'operation_number' => $data['operation_number'] ?? null,
                'description' => $data['description'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'file_original_name' => $data['file_original_name'] ?? null,
                'file_mime_type' => $data['file_mime_type'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'status' => BankTransfer::STATUS_REGISTERED,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->createMovementLocked([
                'company_bank_account_id' => $from->id,
                'currency_id' => $from->currency_id,
                'bank_transfer_id' => $transfer->id,
                'movement_date' => $data['transfer_date'],
                'movement_type' => 'TRANSFERENCIA_SALIDA',
                'amount' => $amount,
                'exchange_rate' => $sourceCode === 'PEN' ? null : $storedRate,
                'amount_pen' => $amountPen,
                'direction' => BankMovement::DIRECTION_OUT,
                'concept' => 'Transferencia a otra cuenta',
                'description' => $data['description'] ?? null,
                'operation_number' => $data['operation_number'] ?? null,
                'source_type' => 'BANK_TRANSFER',
                'source_id' => $transfer->id,
                'source_code' => $transfer->code,
                'source_description' => 'Transferencia enviada',
                'idempotency_key' => "bank-transfer:{$transfer->id}:out",
            ], $userId);
            $this->createMovementLocked([
                'company_bank_account_id' => $to->id,
                'currency_id' => $to->currency_id,
                'bank_transfer_id' => $transfer->id,
                'movement_date' => $data['transfer_date'],
                'movement_type' => 'TRANSFERENCIA_ENTRADA',
                'amount' => $destinationAmount,
                'exchange_rate' => $destinationCode === 'PEN' ? null : $storedRate,
                'amount_pen' => $amountPen,
                'direction' => BankMovement::DIRECTION_IN,
                'concept' => 'Transferencia recibida de otra cuenta',
                'description' => $data['description'] ?? null,
                'operation_number' => $data['operation_number'] ?? null,
                'source_type' => 'BANK_TRANSFER',
                'source_id' => $transfer->id,
                'source_code' => $transfer->code,
                'source_description' => 'Transferencia recibida',
                'idempotency_key' => "bank-transfer:{$transfer->id}:in",
            ], $userId);

            return $transfer->fresh(['fromAccount.bank', 'toAccount.bank', 'currency', 'destinationCurrency', 'movements']);
        });
    }

    public function cancelMovement(BankMovement $movement, string $reason, ?int $userId): BankMovement
    {
        return DB::transaction(function () use ($movement, $reason, $userId) {
            $movement = BankMovement::query()->lockForUpdate()->findOrFail($movement->id);
            if ($movement->bank_transfer_id) {
                $this->cancelTransferLocked($movement->transfer()->lockForUpdate()->firstOrFail(), $reason, $userId);

                return $movement->fresh(['reversal']);
            }

            return $this->cancelMovementLocked($movement, $reason, $userId);
        });
    }

    public function cancelMovementForSourceCorrection(
        BankMovement $movement,
        string $reason,
        ?int $userId
    ): BankMovement {
        return DB::transaction(function () use ($movement, $reason, $userId) {
            $movement = BankMovement::query()->lockForUpdate()->findOrFail($movement->id);

            return $this->cancelMovementLocked($movement, $reason, $userId, true);
        });
    }

    public function cancelTransfer(BankTransfer $transfer, string $reason, ?int $userId): BankTransfer
    {
        return DB::transaction(fn () => $this->cancelTransferLocked(
            BankTransfer::query()->lockForUpdate()->findOrFail($transfer->id),
            $reason,
            $userId
        ));
    }

    public function reconcile(array $data, ?int $userId): BankReconciliation
    {
        return DB::transaction(function () use ($data, $userId) {
            $account = CompanyBankAccount::query()->lockForUpdate()->findOrFail($data['company_bank_account_id']);
            $movements = BankMovement::query()
                ->whereIn('id', $data['movement_ids'])
                ->where('company_bank_account_id', $account->id)
                ->where('status', BankMovement::STATUS_REGISTERED)
                ->whereBetween('movement_date', [$data['start_date'].' 00:00:00', $data['end_date'].' 23:59:59'])
                ->lockForUpdate()
                ->get();

            if ($movements->count() !== count(array_unique($data['movement_ids']))) {
                throw ValidationException::withMessages([
                    'movement_ids' => 'Uno o más movimientos no están disponibles para conciliación.',
                ]);
            }

            $systemBalance = $this->systemBalanceAt($account->id, $data['end_date']);
            $statementBalance = (float) $data['bank_statement_balance'];
            $reconciliation = BankReconciliation::create([
                'code' => $this->code('CON'),
                'company_bank_account_id' => $account->id,
                'company_id' => $account->company_id,
                'period' => $data['period'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'system_balance' => $systemBalance,
                'bank_statement_balance' => $statementBalance,
                'difference' => $statementBalance - $systemBalance,
                'status' => BankReconciliation::STATUS_CLOSED,
                'observation' => $data['observation'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'file_original_name' => $data['file_original_name'] ?? null,
                'file_mime_type' => $data['file_mime_type'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'created_by' => $userId,
                'updated_by' => $userId,
                'closed_by' => $userId,
                'closed_at' => now(),
            ]);

            foreach ($movements as $movement) {
                $reconciliation->details()->create([
                    'bank_movement_id' => $movement->id,
                    'status' => BankMovement::STATUS_RECONCILED,
                ]);
                $movement->update([
                    'status' => BankMovement::STATUS_RECONCILED,
                    'updated_by' => $userId,
                ]);
            }

            return $reconciliation->fresh(['account.bank', 'movements']);
        });
    }

    public function systemBalanceAt(int $accountId, string $date): float
    {
        return (float) BankMovement::query()
            ->where('company_bank_account_id', $accountId)
            ->where('movement_date', '<=', $date.' 23:59:59')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN amount ELSE -amount END), 0) AS balance")
            ->value('balance');
    }

    private function createMovementLocked(array $data, ?int $userId): BankMovement
    {
        if (! empty($data['idempotency_key'])) {
            $existing = BankMovement::query()->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $account = CompanyBankAccount::query()->with('currency')->lockForUpdate()
            ->findOrFail($data['company_bank_account_id']);
        if ($account->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'company_bank_account_id' => 'La cuenta bancaria seleccionada está inactiva.',
            ]);
        }
        if ((int) $account->currency_id !== (int) $data['currency_id']) {
            throw ValidationException::withMessages([
                'currency_id' => 'La moneda del movimiento debe coincidir con la moneda de la cuenta bancaria.',
            ]);
        }

        $amount = (float) $data['amount'];
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'El monto debe ser mayor a cero.']);
        }
        $direction = $data['direction'];
        if (! in_array($direction, [BankMovement::DIRECTION_IN, BankMovement::DIRECTION_OUT], true)) {
            throw ValidationException::withMessages(['direction' => 'La dirección del movimiento no es válida.']);
        }

        $currencyCode = strtoupper((string) $account->currency?->code);
        $amountPen = isset($data['amount_pen'])
            ? (float) $data['amount_pen']
            : $this->amountInPen($amount, $currencyCode, isset($data['exchange_rate']) ? (float) $data['exchange_rate'] : null);

        if ($direction === BankMovement::DIRECTION_IN) {
            $account->increment('current_balance', $amount);
        } else {
            $account->decrement('current_balance', $amount);
        }
        $account->update(['last_movement_at' => now(), 'updated_by' => $userId]);
        $account->refresh();

        return BankMovement::create([
            ...$data,
            'code' => $data['code'] ?? $this->code('MOV'),
            'company_id' => $account->company_id,
            'exchange_rate' => $currencyCode === 'PEN' ? null : ($data['exchange_rate'] ?? null),
            'amount_pen' => $amountPen,
            'status' => BankMovement::STATUS_REGISTERED,
            'balance_after' => $account->current_balance,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function cancelMovementLocked(
        BankMovement $movement,
        string $reason,
        ?int $userId,
        bool $allowReconciled = false
    ): BankMovement
    {
        if ($movement->status === BankMovement::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['movement' => 'El movimiento ya se encuentra anulado.']);
        }
        if ($movement->status === BankMovement::STATUS_RECONCILED && ! $allowReconciled) {
            throw ValidationException::withMessages([
                'movement' => 'No se puede anular un movimiento conciliado. Primero debe regularizar la conciliación.',
            ]);
        }
        if ($movement->movement_type === 'REVERSA') {
            throw ValidationException::withMessages(['movement' => 'Una reversa no puede anularse directamente.']);
        }

        $movement->update([
            'status' => BankMovement::STATUS_CANCELLED,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'updated_by' => $userId,
        ]);

        $reversal = $this->createMovementLocked([
            'company_bank_account_id' => $movement->company_bank_account_id,
            'currency_id' => $movement->currency_id,
            'original_currency_id' => $movement->original_currency_id,
            'movement_date' => now(),
            'movement_type' => 'REVERSA',
            'amount' => $movement->amount,
            'original_amount' => $movement->original_amount,
            'exchange_rate' => $movement->exchange_rate,
            'original_exchange_rate' => $movement->original_exchange_rate,
            'amount_pen' => $movement->amount_pen,
            'direction' => $movement->direction === BankMovement::DIRECTION_IN
                ? BankMovement::DIRECTION_OUT
                : BankMovement::DIRECTION_IN,
            'concept' => 'Reversa de '.$movement->code,
            'description' => $reason,
            'operation_number' => $movement->operation_number,
            'source_type' => 'BANK_REVERSAL',
            'source_id' => $movement->id,
            'source_code' => $movement->code,
            'source_description' => 'Reversa por anulación',
            'reversal_of_id' => $movement->id,
            'idempotency_key' => "bank-reversal:{$movement->id}",
        ], $userId);

        $movement->setRelation('reversal', $reversal);

        return $movement;
    }

    private function cancelTransferLocked(BankTransfer $transfer, string $reason, ?int $userId): BankTransfer
    {
        if ($transfer->status === BankTransfer::STATUS_CANCELLED) {
            throw ValidationException::withMessages(['transfer' => 'La transferencia ya se encuentra anulada.']);
        }

        $movements = $transfer->movements()->lockForUpdate()->get();
        foreach ($movements as $movement) {
            $this->cancelMovementLocked($movement, $reason, $userId);
        }
        $transfer->update([
            'status' => BankTransfer::STATUS_CANCELLED,
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
            'updated_by' => $userId,
        ]);

        return $transfer->fresh(['movements.reversal']);
    }

    private function amountInPen(float $amount, string $currencyCode, ?float $rate): float
    {
        return strtoupper($currencyCode) === 'PEN'
            ? $amount
            : $amount * $this->requiredRate($rate);
    }

    private function requiredRate(?float $rate): float
    {
        if (! $rate || $rate <= 0) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Ingrese un tipo de cambio mayor a cero para la operación en moneda extranjera.',
            ]);
        }

        return $rate;
    }

    private function code(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(5));
    }
}
