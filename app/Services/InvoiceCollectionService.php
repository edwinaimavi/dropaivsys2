<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\ElectronicInvoice;
use App\Models\InvoiceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InvoiceCollectionService
{
    public function __construct(private readonly BankMovementService $bankMovementService) {}

    public function register(ElectronicInvoice $invoice, array $data, ?UploadedFile $proof, ?int $userId): InvoiceCollection
    {
        $existing = InvoiceCollection::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();
        if ($existing) {
            return $existing->load(['account.bank', 'currency', 'bankMovement', 'creator']);
        }

        $storedPath = null;
        try {
            if ($proof) {
                $storedPath = $proof->store("electronic_invoices/{$invoice->id}/collections", 'public');
            }

            return DB::transaction(function () use ($invoice, $data, $proof, $storedPath, $userId) {
                $existing = InvoiceCollection::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    if ($storedPath) {
                        Storage::disk('public')->delete($storedPath);
                    }
                    return $existing->load(['account.bank', 'currency', 'bankMovement', 'creator']);
                }

                $invoice = ElectronicInvoice::query()->with(['currency', 'customerPurchaseOrder', 'customer'])
                    ->lockForUpdate()->findOrFail($invoice->id);
                if (in_array($invoice->status, ['draft', 'cancelled', 'voided'], true) || $invoice->is_voided) {
                    throw ValidationException::withMessages(['invoice' => 'Solo se pueden cobrar comprobantes locales generados y vigentes.']);
                }

                $pending = max(0, (float) $invoice->total_amount - (float) $invoice->paid_amount);
                if ($pending <= 0.00001) {
                    throw ValidationException::withMessages(['amount' => 'La factura ya se encuentra cobrada completamente.']);
                }

                $account = CompanyBankAccount::query()->with('currency')->lockForUpdate()
                    ->find($data['company_bank_account_id']);
                if (! $account || $account->status !== 'ACTIVE') {
                    throw ValidationException::withMessages(['company_bank_account_id' => 'Seleccione una cuenta bancaria activa.']);
                }
                if ((int) $account->company_id !== (int) $invoice->company_id) {
                    throw ValidationException::withMessages(['company_bank_account_id' => 'La cuenta bancaria no pertenece a la empresa emisora.']);
                }
                if ((int) $account->currency_id !== (int) $data['currency_id']) {
                    throw ValidationException::withMessages(['currency_id' => 'La moneda del cobro debe coincidir con la moneda de la cuenta bancaria.']);
                }

                $collectionCurrency = Currency::query()->findOrFail($data['currency_id']);
                $amount = (float) $data['amount'];
                $rate = isset($data['exchange_rate']) ? (float) $data['exchange_rate'] : null;
                $invoiceAmount = $this->toInvoiceCurrency($amount, $collectionCurrency->code, $invoice->currency?->code, $rate);
                if ($invoiceAmount > $pending + 0.00001) {
                    throw ValidationException::withMessages(['amount' => 'El monto cobrado no puede superar el saldo pendiente de la factura.']);
                }

                $collection = InvoiceCollection::create([
                    'electronic_invoice_id' => $invoice->id,
                    'customer_purchase_order_id' => $invoice->customer_purchase_order_id,
                    'company_bank_account_id' => $account->id,
                    'currency_id' => $collectionCurrency->id,
                    'collection_date' => $data['collection_date'],
                    'amount' => $amount,
                    'invoice_currency_amount' => $invoiceAmount,
                    'exchange_rate' => $rate,
                    'operation_number' => $data['operation_number'] ?? null,
                    'proof_file_path' => $storedPath,
                    'proof_original_name' => $proof?->getClientOriginalName(),
                    'proof_mime_type' => $proof?->getMimeType(),
                    'proof_size' => $proof?->getSize(),
                    'observation' => $data['observation'] ?? null,
                    'idempotency_key' => $data['idempotency_key'],
                    'created_by' => $userId,
                ]);

                $movement = $this->bankMovementService->createMovement([
                    'company_bank_account_id' => $account->id,
                    'currency_id' => $collectionCurrency->id,
                    'original_currency_id' => $invoice->currency_id,
                    'movement_date' => $data['collection_date'],
                    'movement_type' => 'INGRESO',
                    'amount' => $amount,
                    'original_amount' => $invoiceAmount,
                    'exchange_rate' => $rate,
                    'original_exchange_rate' => $rate,
                    'direction' => BankMovement::DIRECTION_IN,
                    'concept' => "Cobro de factura {$invoice->full_number}",
                    'description' => $data['observation'] ?? null,
                    'operation_number' => $data['operation_number'] ?? null,
                    'document_type' => $invoice->document_type,
                    'document_series' => $invoice->serie,
                    'document_number' => $invoice->correlativo,
                    'document_date' => $invoice->issue_date,
                    'file_path' => $storedPath,
                    'file_original_name' => $proof?->getClientOriginalName(),
                    'file_mime_type' => $proof?->getMimeType(),
                    'file_size' => $proof?->getSize(),
                    'source_type' => 'ELECTRONIC_INVOICE_COLLECTION',
                    'source_id' => $collection->id,
                    'source_code' => $invoice->full_number,
                    'source_description' => trim(($invoice->customerPurchaseOrder?->code ? "OC {$invoice->customerPurchaseOrder->code} | " : '').$invoice->client_name),
                    'idempotency_key' => "invoice-collection:{$collection->id}",
                ], $userId);

                $collection->update(['bank_movement_id' => $movement->id]);
                $paid = round((float) $invoice->paid_amount + $invoiceAmount, 10);
                $newPending = max(0, round((float) $invoice->total_amount - $paid, 10));
                $invoice->update([
                    'paid_amount' => $paid,
                    'pending_amount' => $newPending,
                    'payment_status' => $newPending <= 0.00001 ? 'paid' : 'partial',
                    'updated_by' => $userId,
                ]);

                return $collection->fresh(['account.bank', 'currency', 'bankMovement', 'creator']);
            });
        } catch (\Throwable $e) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            throw $e;
        }
    }

    private function toInvoiceCurrency(float $amount, ?string $collectionCode, ?string $invoiceCode, ?float $rate): float
    {
        $collectionCode = strtoupper((string) $collectionCode);
        $invoiceCode = strtoupper((string) $invoiceCode);
        if ($collectionCode === $invoiceCode) {
            return round($amount, 10);
        }
        if (! $rate || $rate <= 0) {
            throw ValidationException::withMessages(['exchange_rate' => 'Ingrese un tipo de cambio mayor a cero cuando las monedas son diferentes.']);
        }
        if ($collectionCode !== 'PEN' && $invoiceCode !== 'PEN') {
            throw ValidationException::withMessages(['currency_id' => 'Para cobrar en monedas distintas, una de las monedas debe ser PEN.']);
        }

        return round($collectionCode === 'PEN' ? $amount / $rate : $amount * $rate, 10);
    }
}
