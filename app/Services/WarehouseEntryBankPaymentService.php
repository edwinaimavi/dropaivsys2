<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\CompanyBankAccount;
use App\Models\WarehouseEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseEntryBankPaymentService
{
    public const SOURCE_TYPE = 'WAREHOUSE_ENTRY_PAYMENT';

    public function __construct(private readonly BankMovementService $bankMovementService)
    {
    }

    public function sync(WarehouseEntry $warehouseEntry, ?int $userId): ?BankMovement
    {
        return DB::transaction(function () use ($warehouseEntry, $userId) {
            $entry = WarehouseEntry::query()
                ->with(['currency:id,code,symbol', 'supplier:id,business_name,short_name', 'supplierPurchaseOrder:id,code'])
                ->lockForUpdate()
                ->findOrFail($warehouseEntry->id);
            $existing = $this->activeMovement($entry->id);

            if ($entry->generate_account_payable || $entry->status === 'cancelled' || $entry->trashed()) {
                if ($existing) {
                    $this->bankMovementService->cancelMovementForSourceCorrection(
                        $existing,
                        $entry->generate_account_payable
                            ? 'EL INGRESO FUE CAMBIADO A CUENTA POR PAGAR'
                            : 'INGRESO DE ALMACÉN ANULADO',
                        $userId
                    );
                }

                return null;
            }

            $account = CompanyBankAccount::query()
                ->with(['bank:id,description,short_name', 'currency:id,code,symbol'])
                ->lockForUpdate()
                ->find($entry->payment_company_bank_account_id);

            if (! $account || $account->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'payment_company_bank_account_id' => 'Seleccione una cuenta bancaria activa para registrar el pago al proveedor.',
                ]);
            }
            if ((int) $account->company_id !== (int) $entry->company_id) {
                throw ValidationException::withMessages([
                    'payment_company_bank_account_id' => 'La cuenta bancaria debe pertenecer a la empresa del ingreso de almacén.',
                ]);
            }

            $amounts = $this->paymentAmounts($entry, $account);
            $movementData = $this->movementData($entry, $account, $amounts);

            if ($existing && $this->matches($existing, $movementData)) {
                return $existing->fresh(['account.bank', 'account.currency', 'originalCurrency']);
            }

            if ($existing) {
                $this->bankMovementService->cancelMovementForSourceCorrection(
                    $existing,
                    'CORRECCIÓN DEL PAGO DEL INGRESO DE ALMACÉN '.$entry->entry_number,
                    $userId
                );
                $account->refresh();
            }

            if ((float) $account->current_balance + 0.0001 < $amounts['accountAmount']
                && ! $entry->bank_payment_negative_balance_confirmed) {
                throw ValidationException::withMessages([
                    'bank_payment_negative_balance_confirmed' => sprintf(
                        'La cuenta seleccionada no tiene saldo suficiente (disponible: %s %.2f). Confirme expresamente si desea permitir saldo negativo.',
                        $account->currency?->symbol ?: $account->currency?->code,
                        (float) $account->current_balance
                    ),
                ]);
            }

            $revision = BankMovement::query()
                ->where('source_type', self::SOURCE_TYPE)
                ->where('source_id', $entry->id)
                ->count() + 1;

            return $this->bankMovementService->createMovement([
                ...$movementData,
                'idempotency_key' => "warehouse-entry-payment:{$entry->id}:v{$revision}",
            ], $userId)->fresh(['account.bank', 'account.currency', 'originalCurrency']);
        });
    }

    public function cancel(WarehouseEntry $warehouseEntry, string $reason, ?int $userId): ?BankMovement
    {
        return DB::transaction(function () use ($warehouseEntry, $reason, $userId) {
            $movement = $this->activeMovement($warehouseEntry->id);

            return $movement
                ? $this->bankMovementService->cancelMovementForSourceCorrection($movement, $reason, $userId)
                : null;
        });
    }

    public function syncProofReference(WarehouseEntry $warehouseEntry): void
    {
        BankMovement::query()
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $warehouseEntry->id)
            ->where('status', '!=', BankMovement::STATUS_CANCELLED)
            ->update([
                'file_path' => $warehouseEntry->bank_payment_proof_path,
                'file_original_name' => $warehouseEntry->bank_payment_proof_original_name,
                'file_mime_type' => $warehouseEntry->bank_payment_proof_mime_type,
                'file_size' => $warehouseEntry->bank_payment_proof_size,
            ]);
    }

    private function activeMovement(int $warehouseEntryId): ?BankMovement
    {
        return BankMovement::query()
            ->where('source_type', self::SOURCE_TYPE)
            ->where('source_id', $warehouseEntryId)
            ->where('status', '!=', BankMovement::STATUS_CANCELLED)
            ->latest('id')
            ->lockForUpdate()
            ->first();
    }

    private function paymentAmounts(WarehouseEntry $entry, CompanyBankAccount $account): array
    {
        $originalAmount = round((float) $entry->grand_total, 4);
        if ($originalAmount <= 0) {
            throw ValidationException::withMessages([
                'payable_amount' => 'El total pagado al proveedor debe ser mayor a cero.',
            ]);
        }

        $originalCode = strtoupper((string) $entry->currency?->code);
        $accountCode = strtoupper((string) $account->currency?->code);
        $rate = (float) $entry->bank_payment_exchange_rate;
        $requiresRate = $originalCode !== 'PEN' || $accountCode !== 'PEN';

        if ($requiresRate && $rate <= 0) {
            throw ValidationException::withMessages([
                'bank_payment_exchange_rate' => 'Ingrese un tipo de cambio mayor a cero para registrar el pago en moneda extranjera.',
            ]);
        }

        if ($originalCode !== $accountCode && $originalCode !== 'PEN' && $accountCode !== 'PEN') {
            throw ValidationException::withMessages([
                'payment_company_bank_account_id' => 'No se puede convertir directamente entre dos monedas extranjeras distintas con un solo tipo de cambio.',
            ]);
        }

        $amountPen = $originalCode === 'PEN'
            ? $originalAmount
            : round($originalAmount * $rate, 4);
        $accountAmount = match (true) {
            $originalCode === $accountCode => $originalAmount,
            $accountCode === 'PEN' => $amountPen,
            default => round($amountPen / $rate, 4),
        };

        return compact('originalAmount', 'amountPen', 'accountAmount', 'rate');
    }

    private function movementData(
        WarehouseEntry $entry,
        CompanyBankAccount $account,
        array $amounts
    ): array {
        $supplierName = $entry->supplier?->short_name ?: $entry->supplier?->business_name;
        $sourceDescription = collect([
            $supplierName ? 'Proveedor: '.$supplierName : null,
            $entry->supplierPurchaseOrder?->code ? 'OC proveedor: '.$entry->supplierPurchaseOrder->code : null,
        ])->filter()->implode(' · ');

        return [
            'company_bank_account_id' => $account->id,
            'currency_id' => $account->currency_id,
            'original_currency_id' => $entry->currency_id,
            'movement_date' => $entry->bank_payment_date?->toDateString(),
            'movement_type' => 'EGRESO',
            'amount' => $amounts['accountAmount'],
            'original_amount' => $amounts['originalAmount'],
            'exchange_rate' => strtoupper((string) $account->currency?->code) === 'PEN'
                ? null
                : $amounts['rate'],
            'original_exchange_rate' => $amounts['rate'] > 0 ? $amounts['rate'] : null,
            'amount_pen' => $amounts['amountPen'],
            'direction' => BankMovement::DIRECTION_OUT,
            'concept' => 'Pago a proveedor por ingreso de almacén',
            'description' => $entry->bank_payment_observation,
            'operation_number' => $entry->bank_payment_operation_number,
            'document_type' => $entry->document_type,
            'document_series' => $entry->document_series,
            'document_number' => $entry->document_number,
            'document_date' => $entry->document_date?->toDateString(),
            'file_path' => $entry->bank_payment_proof_path,
            'file_original_name' => $entry->bank_payment_proof_original_name,
            'file_mime_type' => $entry->bank_payment_proof_mime_type,
            'file_size' => $entry->bank_payment_proof_size,
            'source_type' => self::SOURCE_TYPE,
            'source_id' => $entry->id,
            'source_code' => $entry->entry_number,
            'source_description' => $sourceDescription ?: 'Ingreso de almacén',
        ];
    }

    private function matches(BankMovement $movement, array $data): bool
    {
        foreach ([
            'company_bank_account_id', 'currency_id', 'original_currency_id', 'operation_number',
            'document_type', 'document_series', 'document_number', 'file_path', 'description',
        ] as $field) {
            if ((string) ($movement->{$field} ?? '') !== (string) ($data[$field] ?? '')) {
                return false;
            }
        }

        foreach (['amount', 'original_amount', 'exchange_rate', 'original_exchange_rate', 'amount_pen'] as $field) {
            if (abs((float) ($movement->{$field} ?? 0) - (float) ($data[$field] ?? 0)) > 0.0001) {
                return false;
            }
        }

        return (string) $movement->movement_date?->toDateString() === (string) $data['movement_date']
            && (string) $movement->document_date?->toDateString() === (string) ($data['document_date'] ?? '');
    }
}
