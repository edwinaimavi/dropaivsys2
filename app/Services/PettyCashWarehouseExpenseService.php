<?php

namespace App\Services;

use App\Models\Document;
use App\Models\PettyCashExpense;
use App\Models\PettyCashExpenseExchange;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDocument;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PettyCashWarehouseExpenseService
{
    public function applyPettyCashData(array $data, PettyCashExpense $pettyCashExpense): array
    {
        $pettyCashExpense->loadMissing(['pettyCashBox', 'documents.documentType', 'exchange.documents']);
        $exchange = $pettyCashExpense->exchange_status === PettyCashExpense::EXCHANGE_COMPLETED
            && $pettyCashExpense->exchange?->status === PettyCashExpenseExchange::STATUS_ACTIVE
                ? $pettyCashExpense->exchange
                : null;

        $documentType = $exchange
            ? WarehouseEntryExpense::normalizeDocumentType($exchange->document_type)
            : $pettyCashExpense->warehouseDocumentType();
        $sourceDocuments = $this->activeDocuments($pettyCashExpense->documents);
        $sourceInvoice = $sourceDocuments->first(
            fn (Document $document) => $this->sourceDocumentType($document) === WarehouseEntryExpenseDocument::TYPE_INVOICE
        );
        $paymentProof = $sourceDocuments->first(
            fn (Document $document) => $this->sourceDocumentType($document) === WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF
        );
        $exchangeDocument = $exchange?->documents
            ->first(fn (Document $document) => $document->status === 'ACTIVE' && filled($document->file_path));
        $directOfficialDocument = ! $exchange && WarehouseEntryExpense::isOfficialDocument($documentType)
            ? $sourceInvoice
            : null;
        $officialDocument = $exchangeDocument ?: $directOfficialDocument;

        return array_merge($data, [
            'source_type' => WarehouseEntryExpense::SOURCE_PETTY_CASH,
            'petty_cash_expense_id' => $pettyCashExpense->id,
            'petty_cash_replenishment_id' => null,
            ...WarehouseEntryExpense::documentMetadata($documentType),
            'exchanged_document_id' => $exchangeDocument?->id,
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
        $pettyCashExpense->loadMissing(['documents.documentType', 'exchange.documents']);

        $sourceDocuments = $this->activeDocuments($pettyCashExpense->documents);
        $exchangeDocuments = $pettyCashExpense->exchange_status === PettyCashExpense::EXCHANGE_COMPLETED
            && $pettyCashExpense->exchange?->status === PettyCashExpenseExchange::STATUS_ACTIVE
                ? $this->activeDocuments($pettyCashExpense->exchange->documents)
                : collect();

        $this->assertDocumentsExist($pettyCashExpense, $sourceDocuments->concat($exchangeDocuments));

        $sourceDocuments
            ->groupBy(fn (Document $document) => $this->sourceDocumentType($document))
            ->each(fn ($documents, string $documentType) => $this->copyDocuments(
                $warehouseExpense,
                $documents,
                $documentType,
                'petty_cash_expense',
                $documentType === WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF
                    ? 'Sustento de pago de Caja Chica'
                    : 'Documento original de Caja Chica'
            ));

        if ($exchangeDocuments->isNotEmpty()) {
            $this->copyDocuments(
                $warehouseExpense,
                $exchangeDocuments,
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
            ->each(function (Document $document) use ($warehouseExpense, $documentType, $sourceContext, $description) {
                $linkedDocument = $warehouseExpense->documents()->firstOrNew(
                    [
                        'source_document_id' => $document->id,
                        'source_context' => $sourceContext,
                    ],
                );
                if (! $linkedDocument->exists) {
                    $linkedDocument->created_by = $document->created_by ?: Auth::id();
                }
                $linkedDocument->fill([
                    'document_type' => $documentType,
                    'description' => $description,
                    'file_path' => $document->file_path,
                    'original_name' => $document->original_name,
                    'mime_type' => $document->mime_type,
                    'file_size' => $document->file_size,
                    'status' => 'ACTIVE',
                    'updated_by' => Auth::id(),
                ])->save();

                $warehouseExpense->documents()
                    ->where('source_document_id', $document->id)
                    ->where('source_context', $sourceContext)
                    ->where('id', '!=', $linkedDocument->id)
                    ->update(['status' => 'INACTIVE', 'updated_by' => Auth::id()]);
            });
    }

    private function activeDocuments($documents)
    {
        return collect($documents)
            ->filter(fn (Document $document) => $document->status === 'ACTIVE')
            ->values();
    }

    public function sourceDocumentType(Document $document): string
    {
        $typeCode = Str::lower((string) $document->documentType?->code);
        $fileName = Str::lower(Str::ascii((string) ($document->original_name ?: $document->stored_name)));
        $searchable = $typeCode.' '.$fileName;

        if (Str::contains($searchable, [
            'factura', 'boleta', 'recibo_honorario', 'recibo-honorario',
            'recibo por honorario',
        ])) {
            return WarehouseEntryExpenseDocument::TYPE_INVOICE;
        }

        if (Str::contains($searchable, [
            'constancia', 'pago', 'voucher', 'yape', 'plin', 'transferencia',
            'deposito', 'operacion', 'transaction', 'payment',
        ])) {
            return WarehouseEntryExpenseDocument::TYPE_PAYMENT_PROOF;
        }

        return WarehouseEntryExpenseDocument::TYPE_INVOICE;
    }

    private function assertDocumentsExist(PettyCashExpense $pettyCashExpense, $documents): void
    {
        $missing = collect($documents)->first(
            fn (Document $document) => blank($document->file_path)
                || ! Storage::disk('public')->exists($document->file_path)
        );
        if (! $missing) {
            return;
        }

        $fileName = $missing->original_name ?: basename((string) $missing->file_path) ?: 'archivo adjunto';
        throw ValidationException::withMessages([
            'petty_cash_expense_id' => sprintf(
                'El archivo adjunto "%s" del gasto de Caja Chica #%d no existe y debe regularizarse antes de vincularlo.',
                $fileName,
                $pettyCashExpense->id
            ),
        ]);
    }
}
