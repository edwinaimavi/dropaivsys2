<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryCreditPayment;
use App\Models\WarehouseEntryPaymentDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WarehouseEntryCreditPaymentService
{
    public const SOURCE_TYPE = 'WAREHOUSE_ENTRY_CREDIT_PAYMENT';

    private const MONEY_EPSILON = 0.0001;

    public function __construct(
        private readonly BankMovementService $bankMovementService,
        private readonly SupplierPurchaseOrderFinancialService $financialService
    ) {
    }

    public function create(
        WarehouseEntry $warehouseEntry,
        array $data,
        ?UploadedFile $proof,
        ?int $userId
    ): WarehouseEntryCreditPayment {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($warehouseEntry, $data, $proof, $userId, &$storedPath) {
                $entry = WarehouseEntry::query()
                    ->with([
                        'currency:id,code,symbol',
                        'supplier:id,business_name,short_name',
                        'supplierPurchaseOrder:id,code,payment_condition',
                        'bankPaymentMovement' => fn ($query) => $query->select(
                            'bank_movements.id',
                            'bank_movements.source_id',
                            'bank_movements.status'
                        ),
                    ])
                    ->lockForUpdate()
                    ->findOrFail($warehouseEntry->id);

                $existing = WarehouseEntryCreditPayment::query()
                    ->where('idempotency_key', $data['idempotency_key'])
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    if ((int) $existing->warehouse_entry_id !== (int) $entry->id) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'La operación de pago ya fue utilizada para otro ingreso.',
                        ]);
                    }

                    return $existing->load($this->paymentRelations());
                }

                $this->validatePayableEntry($entry);
                $summary = $this->summary($entry);
                $appliedAmount = round((float) $data['applied_amount'], 4);
                if ($appliedAmount <= 0) {
                    throw ValidationException::withMessages([
                        'applied_amount' => 'El monto aplicado debe ser mayor a cero.',
                    ]);
                }
                if ($appliedAmount > $summary['pending_amount']) {
                    throw ValidationException::withMessages([
                        'applied_amount' => 'El monto del pago no puede superar el saldo pendiente.',
                    ]);
                }

                $purchaseCurrency = $entry->currency;
                $paymentCurrency = Currency::query()->findOrFail($data['payment_currency_id']);
                $purchaseCode = strtoupper((string) $purchaseCurrency?->code);
                $paymentCode = strtoupper((string) $paymentCurrency->code);
                if ($purchaseCode !== $paymentCode && $purchaseCode !== 'PEN' && $paymentCode !== 'PEN') {
                    throw ValidationException::withMessages([
                        'payment_currency_id' => 'Una de las monedas del pago debe ser PEN.',
                    ]);
                }

                $rate = $purchaseCode === $paymentCode ? 1.0 : (float) ($data['exchange_rate'] ?? 0);
                if ($purchaseCode !== $paymentCode && $rate <= 0) {
                    throw ValidationException::withMessages([
                        'exchange_rate' => 'Ingrese el tipo de cambio del pago, mayor a cero.',
                    ]);
                }

                $account = CompanyBankAccount::query()
                    ->with(['bank:id,description,short_name', 'currency:id,code,symbol'])
                    ->lockForUpdate()
                    ->find($data['company_bank_account_id']);
                if (! $account
                    || $account->status !== 'ACTIVE'
                    || (int) $account->company_id !== (int) $entry->company_id
                    || (int) $account->currency_id !== (int) $paymentCurrency->id) {
                    throw ValidationException::withMessages([
                        'company_bank_account_id' => 'La cuenta bancaria seleccionada no pertenece a la empresa o moneda del pago.',
                    ]);
                }

                $paidAmount = $this->financialService->convertAppliedToPaid(
                    $appliedAmount,
                    $purchaseCode,
                    $paymentCode,
                    $rate
                );
                $amountPen = $this->financialService->amountInPen($paidAmount, $paymentCode, $rate);

                if (! $proof || ! $proof->isValid()) {
                    throw ValidationException::withMessages([
                        'proof' => 'Adjunte el archivo de constancia del pago.',
                    ]);
                }
                $this->assertUniqueBankOperation(
                    $account->id,
                    $data['payment_date'],
                    (string) $data['operation_number'],
                    $paidAmount
                );

                $storedPath = $proof->store(
                    "warehouse-entries/{$entry->id}/credit-payments",
                    'public'
                );

                $payment = WarehouseEntryCreditPayment::create([
                    'warehouse_entry_id' => $entry->id,
                    'supplier_purchase_order_id' => $entry->supplier_purchase_order_id,
                    'supplier_id' => $entry->supplier_id,
                    'company_bank_account_id' => $account->id,
                    'purchase_currency_id' => $purchaseCurrency->id,
                    'payment_currency_id' => $paymentCurrency->id,
                    'applied_amount' => $appliedAmount,
                    'amount' => $paidAmount,
                    'amount_pen' => $amountPen,
                    'exchange_rate' => $rate,
                    'payment_date' => $data['payment_date'],
                    'payment_method' => $data['payment_method'],
                    'operation_number' => Str::upper(trim((string) $data['operation_number'])),
                    'proof_path' => $storedPath,
                    'proof_original_name' => $proof?->getClientOriginalName(),
                    'proof_mime_type' => $proof?->getMimeType(),
                    'proof_size' => $proof?->getSize(),
                    'observation' => filled($data['observation'] ?? null)
                        ? Str::upper(trim((string) $data['observation']))
                        : null,
                    'idempotency_key' => $data['idempotency_key'],
                    'status' => WarehouseEntryCreditPayment::STATUS_ACTIVE,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $supplierName = $entry->supplier?->short_name ?: $entry->supplier?->business_name;
                $movement = $this->bankMovementService->createMovement([
                    'company_bank_account_id' => $account->id,
                    'currency_id' => $paymentCurrency->id,
                    'original_currency_id' => $purchaseCurrency->id,
                    'movement_date' => $payment->payment_date->toDateString(),
                    'movement_type' => 'EGRESO',
                    'amount' => $paidAmount,
                    'original_amount' => $appliedAmount,
                    'exchange_rate' => $paymentCode === 'PEN' ? null : $rate,
                    'original_exchange_rate' => $rate,
                    'amount_pen' => $amountPen,
                    'direction' => BankMovement::DIRECTION_OUT,
                    'concept' => 'Pago a proveedor por ingreso de almacén',
                    'description' => $payment->observation,
                    'operation_number' => $payment->operation_number,
                    'document_type' => $entry->document_type,
                    'document_series' => $entry->document_series,
                    'document_number' => $entry->document_number,
                    'document_date' => $entry->document_date?->toDateString(),
                    'file_path' => $payment->proof_path,
                    'file_original_name' => $payment->proof_original_name,
                    'file_mime_type' => $payment->proof_mime_type,
                    'file_size' => $payment->proof_size,
                    'source_type' => self::SOURCE_TYPE,
                    'source_id' => $payment->id,
                    'source_code' => $entry->entry_number,
                    'source_description' => collect([
                        "Ingreso: {$entry->entry_number}",
                        $entry->supplierPurchaseOrder?->code ? 'OC proveedor: '.$entry->supplierPurchaseOrder->code : null,
                        $supplierName ? 'Proveedor: '.$supplierName : null,
                    ])->filter()->implode(' · '),
                    'idempotency_key' => "warehouse-credit-payment:{$payment->id}",
                ], $userId);

                $payment->update(['bank_movement_id' => $movement->id]);

                WarehouseEntryPaymentDocument::create([
                    'warehouse_entry_id' => $entry->id,
                    'bank_movement_id' => $movement->id,
                    'warehouse_entry_credit_payment_id' => $payment->id,
                    'document_type' => WarehouseEntryPaymentDocument::TYPE_PAYMENT_PROOF,
                    'file_path' => $storedPath,
                    'original_name' => $proof->getClientOriginalName(),
                    'mime_type' => $proof->getMimeType(),
                    'size' => $proof->getSize(),
                    'uploaded_by' => $userId,
                ]);

                return $payment->load($this->paymentRelations());
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function update(
        WarehouseEntry $warehouseEntry,
        WarehouseEntryCreditPayment $creditPayment,
        array $data,
        ?UploadedFile $proof,
        ?int $userId
    ): WarehouseEntryCreditPayment {
        $storedPath = null;

        try {
            return DB::transaction(function () use (
                $warehouseEntry,
                $creditPayment,
                $data,
                $proof,
                $userId,
                &$storedPath
            ) {
                $entry = WarehouseEntry::query()
                    ->with([
                        'currency:id,code,symbol',
                        'supplier:id,business_name,short_name',
                        'supplierPurchaseOrder:id,code,payment_condition',
                    ])
                    ->lockForUpdate()
                    ->findOrFail($warehouseEntry->id);
                $payment = WarehouseEntryCreditPayment::query()
                    ->where('warehouse_entry_id', $entry->id)
                    ->where('status', WarehouseEntryCreditPayment::STATUS_ACTIVE)
                    ->lockForUpdate()
                    ->findOrFail($creditPayment->id);

                $this->validatePayableEntry($entry);
                $summary = $this->summary($entry);
                $appliedAmount = round((float) $data['applied_amount'], 4);
                $availableAmount = round($summary['pending_amount'] + (float) $payment->applied_amount, 4);
                if ($appliedAmount <= 0 || $appliedAmount > $availableAmount + self::MONEY_EPSILON) {
                    throw ValidationException::withMessages([
                        'applied_amount' => $appliedAmount <= 0
                            ? 'El monto aplicado debe ser mayor a cero.'
                            : 'El monto del pago no puede superar el saldo pendiente.',
                    ]);
                }

                $purchaseCurrency = $entry->currency;
                $paymentCurrency = Currency::query()->findOrFail($data['payment_currency_id']);
                $purchaseCode = strtoupper((string) $purchaseCurrency?->code);
                $paymentCode = strtoupper((string) $paymentCurrency->code);
                if ($purchaseCode !== $paymentCode && $purchaseCode !== 'PEN' && $paymentCode !== 'PEN') {
                    throw ValidationException::withMessages([
                        'payment_currency_id' => 'Una de las monedas del pago debe ser PEN.',
                    ]);
                }
                $rate = $purchaseCode === $paymentCode ? 1.0 : (float) ($data['exchange_rate'] ?? 0);
                if ($purchaseCode !== $paymentCode && $rate <= 0) {
                    throw ValidationException::withMessages([
                        'exchange_rate' => 'Ingrese el tipo de cambio del pago, mayor a cero.',
                    ]);
                }

                $account = CompanyBankAccount::query()
                    ->with(['bank:id,description,short_name', 'currency:id,code,symbol'])
                    ->lockForUpdate()
                    ->find($data['company_bank_account_id']);
                if (! $account
                    || $account->status !== 'ACTIVE'
                    || (int) $account->company_id !== (int) $entry->company_id
                    || (int) $account->currency_id !== (int) $paymentCurrency->id) {
                    throw ValidationException::withMessages([
                        'company_bank_account_id' => 'La cuenta bancaria seleccionada no pertenece a la empresa o moneda del pago.',
                    ]);
                }

                $paidAmount = $this->financialService->convertAppliedToPaid(
                    $appliedAmount,
                    $purchaseCode,
                    $paymentCode,
                    $rate
                );
                $amountPen = $this->financialService->amountInPen($paidAmount, $paymentCode, $rate);
                $this->assertUniqueBankOperation(
                    $account->id,
                    $data['payment_date'],
                    (string) $data['operation_number'],
                    $paidAmount,
                    $payment->bank_movement_id
                );

                if ($proof && $proof->isValid()) {
                    $storedPath = $proof->store(
                        "warehouse-entries/{$entry->id}/credit-payments",
                        'public'
                    );
                }

                $oldMovement = $payment->bankMovement()->lockForUpdate()->first();
                if ($oldMovement && $oldMovement->status !== BankMovement::STATUS_CANCELLED) {
                    $this->bankMovementService->cancelMovementForSourceCorrection(
                        $oldMovement,
                        'CORRECCIÓN DEL PAGO DEL INGRESO '.$entry->entry_number,
                        $userId
                    );
                }

                $oldProofPath = $payment->proof_path;
                $payment->update([
                    'company_bank_account_id' => $account->id,
                    'payment_currency_id' => $paymentCurrency->id,
                    'applied_amount' => $appliedAmount,
                    'amount' => $paidAmount,
                    'amount_pen' => $amountPen,
                    'exchange_rate' => $rate,
                    'payment_date' => $data['payment_date'],
                    'payment_method' => $data['payment_method'],
                    'operation_number' => Str::upper(trim((string) $data['operation_number'])),
                    'proof_path' => $storedPath ?: $payment->proof_path,
                    'proof_original_name' => $storedPath ? $proof?->getClientOriginalName() : $payment->proof_original_name,
                    'proof_mime_type' => $storedPath ? $proof?->getMimeType() : $payment->proof_mime_type,
                    'proof_size' => $storedPath ? $proof?->getSize() : $payment->proof_size,
                    'observation' => filled($data['observation'] ?? null)
                        ? Str::upper(trim((string) $data['observation']))
                        : null,
                    'updated_by' => $userId,
                ]);

                $supplierName = $entry->supplier?->short_name ?: $entry->supplier?->business_name;
                $revision = BankMovement::query()
                    ->where('source_type', self::SOURCE_TYPE)
                    ->where('source_id', $payment->id)
                    ->count() + 1;
                $movement = $this->bankMovementService->createMovement([
                    'company_bank_account_id' => $account->id,
                    'currency_id' => $paymentCurrency->id,
                    'original_currency_id' => $purchaseCurrency->id,
                    'movement_date' => $payment->payment_date->toDateString(),
                    'movement_type' => 'EGRESO',
                    'amount' => $paidAmount,
                    'original_amount' => $appliedAmount,
                    'exchange_rate' => $paymentCode === 'PEN' ? null : $rate,
                    'original_exchange_rate' => $rate,
                    'amount_pen' => $amountPen,
                    'direction' => BankMovement::DIRECTION_OUT,
                    'concept' => 'Pago a proveedor por ingreso de almacén',
                    'description' => $payment->observation,
                    'operation_number' => $payment->operation_number,
                    'document_type' => $entry->document_type,
                    'document_series' => $entry->document_series,
                    'document_number' => $entry->document_number,
                    'document_date' => $entry->document_date?->toDateString(),
                    'file_path' => $payment->proof_path,
                    'file_original_name' => $payment->proof_original_name,
                    'file_mime_type' => $payment->proof_mime_type,
                    'file_size' => $payment->proof_size,
                    'source_type' => self::SOURCE_TYPE,
                    'source_id' => $payment->id,
                    'source_code' => $entry->entry_number,
                    'source_description' => collect([
                        "Ingreso: {$entry->entry_number}",
                        $entry->supplierPurchaseOrder?->code ? 'OC proveedor: '.$entry->supplierPurchaseOrder->code : null,
                        $supplierName ? 'Proveedor: '.$supplierName : null,
                    ])->filter()->implode(' · '),
                    'idempotency_key' => "warehouse-credit-payment:{$payment->id}:v{$revision}",
                ], $userId);
                $payment->update(['bank_movement_id' => $movement->id]);

                if ($storedPath) {
                    WarehouseEntryPaymentDocument::query()
                        ->where('warehouse_entry_credit_payment_id', $payment->id)
                        ->where('file_path', $oldProofPath)
                        ->get()
                        ->each(function (WarehouseEntryPaymentDocument $document) use ($userId) {
                            $document->update([
                                'deleted_by' => $userId,
                                'observation' => 'CONSTANCIA PRINCIPAL REEMPLAZADA',
                            ]);
                            $document->delete();
                        });
                    WarehouseEntryPaymentDocument::create([
                        'warehouse_entry_id' => $entry->id,
                        'bank_movement_id' => $movement->id,
                        'warehouse_entry_credit_payment_id' => $payment->id,
                        'document_type' => WarehouseEntryPaymentDocument::TYPE_PAYMENT_PROOF,
                        'file_path' => $storedPath,
                        'original_name' => $proof->getClientOriginalName(),
                        'mime_type' => $proof->getMimeType(),
                        'size' => $proof->getSize(),
                        'uploaded_by' => $userId,
                    ]);
                }

                return $payment->load($this->paymentRelations());
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function reverse(
        WarehouseEntry $warehouseEntry,
        WarehouseEntryCreditPayment $creditPayment,
        string $reason,
        ?int $userId
    ): void {
        DB::transaction(function () use ($warehouseEntry, $creditPayment, $reason, $userId) {
            $entry = WarehouseEntry::query()->lockForUpdate()->findOrFail($warehouseEntry->id);
            $payment = WarehouseEntryCreditPayment::query()
                ->where('warehouse_entry_id', $entry->id)
                ->where('status', WarehouseEntryCreditPayment::STATUS_ACTIVE)
                ->lockForUpdate()
                ->findOrFail($creditPayment->id);
            $movement = $payment->bankMovement()->lockForUpdate()->first();

            if ($movement && $movement->status !== BankMovement::STATUS_CANCELLED) {
                $this->bankMovementService->cancelMovementForSourceCorrection(
                    $movement,
                    Str::upper(trim($reason)),
                    $userId
                );
            }

            $payment->update([
                'status' => WarehouseEntryCreditPayment::STATUS_REVERSED,
                'deleted_by' => $userId,
                'delete_reason' => Str::upper(trim($reason)),
                'updated_by' => $userId,
            ]);
            $payment->delete();
        });
    }

    public function summary(WarehouseEntry $entry): array
    {
        $entry->loadMissing([
            'creditPayments',
            'bankPaymentMovement',
            'supplierPurchaseOrder.currency:id,code',
            'supplierPurchaseOrder.paymentCurrency:id,code',
            'supplierPurchaseOrder.advancePayments.currency:id,code',
            'supplierPurchaseOrder.advancePayments.purchaseCurrency:id,code',
        ]);
        $total = max((float) ($entry->payable_amount ?: $entry->grand_total), 0);
        $complementaryPaid = round((float) $entry->creditPayments->sum('applied_amount'), 4);
        $advancePaid = round((float) ($entry->supplierPurchaseOrder?->advancePayments ?? collect())
            ->filter(fn ($payment) => strtoupper((string) $payment->status) === 'ACTIVE')
            ->sum(fn ($payment) => (float) ($this->financialService->effectiveAppliedAmount(
                $payment,
                $entry->supplierPurchaseOrder
            ) ?? 0)), 4);
        $warehousePaid = $entry->bankPaymentMovement
            ? round((float) ($entry->bankPaymentMovement->original_amount ?: $total), 4)
            : 0.0;
        $paid = round($advancePaid + $warehousePaid + $complementaryPaid, 4);

        $pending = max(round($total - $paid, 4), 0);
        $status = $pending <= self::MONEY_EPSILON
            ? 'paid'
            : ($paid > self::MONEY_EPSILON ? 'partial' : 'pending');

        return [
            'total_amount' => round($total, 4),
            'paid_amount' => round(min($paid, $total), 4),
            'pending_amount' => $pending,
            'advance_paid_amount' => $advancePaid,
            'warehouse_paid_amount' => $warehousePaid,
            'complementary_paid_amount' => $complementaryPaid,
            'status' => $status,
            'status_label' => match ($status) {
                'paid' => 'Pagado',
                'partial' => 'Parcial',
                default => 'Pendiente',
            },
        ];
    }

    private function validatePayableEntry(WarehouseEntry $entry): void
    {
        if ($entry->status === 'cancelled' || $entry->trashed()) {
            throw ValidationException::withMessages([
                'warehouse_entry_id' => 'No se puede pagar un ingreso anulado.',
            ]);
        }
        if (! $entry->supplier_purchase_order_id || ! $entry->supplierPurchaseOrder) {
            throw ValidationException::withMessages([
                'warehouse_entry_id' => 'El ingreso debe estar relacionado con una OC proveedor.',
            ]);
        }
    }

    private function assertUniqueBankOperation(
        int $accountId,
        string $paymentDate,
        string $operationNumber,
        float $amount,
        ?int $exceptMovementId = null
    ): void {
        $duplicate = BankMovement::query()
            ->where('company_bank_account_id', $accountId)
            ->whereDate('movement_date', $paymentDate)
            ->whereRaw('UPPER(operation_number) = ?', [Str::upper(trim($operationNumber))])
            ->where('amount', round($amount, 4))
            ->where('status', '!=', BankMovement::STATUS_CANCELLED)
            ->when($exceptMovementId, fn ($query) => $query->whereKeyNot($exceptMovementId))
            ->lockForUpdate()
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'operation_number' => 'Ya existe un movimiento bancario activo con la misma cuenta, fecha, operación y monto.',
            ]);
        }
    }

    private function paymentRelations(): array
    {
        return [
            'companyBankAccount.bank:id,description,short_name',
            'companyBankAccount.currency:id,code,symbol',
            'purchaseCurrency:id,code,symbol',
            'paymentCurrency:id,code,symbol',
            'bankMovement:id,code,status',
            'creator:id,name,lastname,email',
            'paymentDocuments.uploader:id,name,lastname,email',
            'deleter:id,name,lastname,email',
        ];
    }
}
