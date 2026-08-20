<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryCreditPayment;
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
                        'applied_amount' => 'El monto aplicado no puede superar el saldo pendiente.',
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

                if ($proof && $proof->isValid()) {
                    $storedPath = $proof->store(
                        "warehouse-entries/{$entry->id}/credit-payments",
                        'public'
                    );
                }

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
                    'concept' => 'Pago de crédito a proveedor',
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

                return $payment->load($this->paymentRelations());
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }

            throw $exception;
        }
    }

    public function summary(WarehouseEntry $entry): array
    {
        $entry->loadMissing(['creditPayments', 'bankPaymentMovement']);
        $total = max((float) $entry->payable_amount, 0);
        $paid = round((float) $entry->creditPayments->sum('applied_amount'), 4);

        if ($entry->bankPaymentMovement && $paid <= self::MONEY_EPSILON) {
            $paid = $total;
        }

        $pending = max(round($total - $paid, 4), 0);
        $status = $pending <= self::MONEY_EPSILON
            ? 'paid'
            : ($paid > self::MONEY_EPSILON ? 'partial' : 'pending');

        return [
            'total_amount' => round($total, 4),
            'paid_amount' => round(min($paid, $total), 4),
            'pending_amount' => $pending,
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
        if (! str_contains(
            mb_strtolower(Str::ascii((string) $entry->supplierPurchaseOrder->payment_condition), 'UTF-8'),
            'credito'
        )) {
            throw ValidationException::withMessages([
                'warehouse_entry_id' => 'Solo se pueden registrar pagos para ingresos con condición de crédito.',
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
        ];
    }
}
