<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PettyCashExpense;
use App\Models\PettyCashExpenseExchange;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDocument;
use Illuminate\Support\Facades\Auth;

class PettyCashWarehouseExpenseService
{
    public function applyPettyCashData(array $data, PettyCashExpense $pettyCashExpense): array
    {
        $pettyCashExpense->loadMissing(['pettyCashBox', 'documents', 'exchange.documents']);
        $exchange = $pettyCashExpense->exchange_status === PettyCashExpense::EXCHANGE_COMPLETED
            && $pettyCashExpense->exchange?->status === PettyCashExpenseExchange::STATUS_ACTIVE
                ? $pettyCashExpense->exchange
                : null;

        $documentType = $exchange
            ? WarehouseEntryExpense::normalizeDocumentType($exchange->document_type)
            : 'RECIBO_INTERNO';
        $paymentProof = $pettyCashExpense->documents
            ->first(fn (Document $document) => $document->status === 'ACTIVE' && filled($document->file_path));
        $officialDocument = $exchange?->documents
            ->first(fn (Document $document) => $document->status === 'ACTIVE' && filled($document->file_path));

        return array_merge($data, [
            'source_type' => WarehouseEntryExpense::SOURCE_PETTY_CASH,
            'petty_cash_expense_id' => $pettyCashExpense->id,
            'petty_cash_replenishment_id' => null,
            ...WarehouseEntryExpense::documentMetadata($documentType),
            'exchanged_document_id' => $officialDocument?->id,
            'exchanged_at' => $exchange ? $pettyCashExpense->exchanged_at : null,
            'payment_proof_path' => $paymentProof?->file_path,
            'official_document_path' => $officialDocument?->file_path,
            'provider_id' => $pettyCashExpense->supplier_id,
            'provider_ruc' => $pettyCashExpense->supplier_ruc,
            'provider_name' => $pettyCashExpense->supplier_name,
            'document_type' => $documentType,
            'document_series' => $exchange?->document_series ?: $pettyCashExpense->document_series,
            'document_number' => $exchange?->document_correlative
                ?: ($pettyCashExpense->document_correlative ?: $pettyCashExpense->document_number),
            'document_date' => ($exchange?->exchange_date ?: $pettyCashExpense->expense_date)?->format('Y-m-d'),
            'currency_id' => $pettyCashExpense->pettyCashBox?->currency_id,
            'amount' => (float) $pettyCashExpense->amount,
            'affects_igv' => false,
            'description' => $pettyCashExpense->concept,
        ]);
    }

    public function syncDocuments(WarehouseEntryExpense $warehouseExpense, PettyCashExpense $pettyCashExpense): void
    {
        $pettyCashExpense->loadMissing(['documents', 'exchange.documents']);

        $this->copyDocuments(
            $warehouseExpense,
            $pettyCashExpense->documents,
            WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF,
            'petty_cash_expense',
            'Recibo interno de Caja Chica'
        );

        if ($pettyCashExpense->exchange_status === PettyCashExpense::EXCHANGE_COMPLETED
            && $pettyCashExpense->exchange?->status === PettyCashExpenseExchange::STATUS_ACTIVE) {
            $this->copyDocuments(
                $warehouseExpense,
                $pettyCashExpense->exchange->documents,
                WarehouseEntryExpenseDocument::TYPE_INVOICE,
                'petty_cash_exchange',
                'Comprobante oficial de canje de Caja Chica'
            );
        }
    }

    public function syncAfterExchange(PettyCashExpense $pettyCashExpense): void
    {
        $pettyCashExpense->load(['pettyCashBox', 'exchange.documents', 'documents']);

        $pettyCashExpense->warehouseEntryExpenses()
            ->where('status', 'ACTIVE')
            ->get()
            ->each(function (WarehouseEntryExpense $warehouseExpense) use ($pettyCashExpense) {
                $data = $this->applyPettyCashData($warehouseExpense->toArray(), $pettyCashExpense);
                $tax = WarehouseEntryExpense::taxBreakdown((float) $data['amount'], false);

                $warehouseExpense->fill([
                    'source_type' => $data['source_type'],
                    'petty_cash_expense_id' => $data['petty_cash_expense_id'],
                    'petty_cash_replenishment_id' => $data['petty_cash_replenishment_id'],
                    'document_classification' => $data['document_classification'],
                    'official_document_type' => $data['official_document_type'],
                    'internal_document_type' => $data['internal_document_type'],
                    'exchanged_document_id' => $data['exchanged_document_id'],
                    'exchanged_at' => $data['exchanged_at'],
                    'payment_proof_path' => $data['payment_proof_path'],
                    'official_document_path' => $data['official_document_path'],
                    'provider_id' => $data['provider_id'],
                    'provider_ruc' => $data['provider_ruc'],
                    'provider_name' => $data['provider_name'],
                    'document_type' => $data['document_type'],
                    'document_series' => $data['document_series'],
                    'document_number' => $data['document_number'],
                    'document_date' => $data['document_date'],
                    'currency_id' => $data['currency_id'],
                    'amount' => $tax['total_amount'],
                    ...$tax,
                    'description' => $data['description'],
                    'updated_by' => Auth::id(),
                ])->save();

                $this->syncDocuments($warehouseExpense, $pettyCashExpense);
            });
    }

    private function copyDocuments(
        WarehouseEntryExpense $warehouseExpense,
        $documents,
        string $documentType,
        string $sourceContext,
        string $description
    ): void {
        collect($documents)
            ->filter(fn (Document $document) => $document->status === 'ACTIVE' && filled($document->file_path))
            ->each(function (Document $document) use ($warehouseExpense, $documentType, $sourceContext, $description) {
                $warehouseExpense->documents()->updateOrCreate(
                    [
                        'source_document_id' => $document->id,
                        'document_type' => $documentType,
                    ],
                    [
                        'source_context' => $sourceContext,
                        'description' => $description,
                        'file_path' => $document->file_path,
                        'original_name' => $document->original_name,
                        'mime_type' => $document->mime_type,
                        'file_size' => $document->file_size,
                        'status' => 'ACTIVE',
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]
                );
            });
    }
}
