<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentIssuer;
use App\Models\DocumentType;
use App\Models\PettyCashBox;
use App\Models\PettyCashExpense;
use App\Models\PettyCashExpenseExchange;
use App\Models\PettyCashExpenseExchangeDocument;
use App\Models\PettyCashExpenseExchangeReturn;
use App\Services\DocumentLookupService;
use App\Services\PettyCashCalculator;
use App\Services\PettyCashWarehouseExpenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PettyCashExpenseExchangeController extends Controller
{
    public function __construct(
        private PettyCashWarehouseExpenseService $pettyCashWarehouseExpenseService,
        private PettyCashCalculator $pettyCashCalculator
    )
    {
        $this->middleware('can:admin.petty-cash.receipt-exchanges.index')->only('pending');
        $this->middleware('can:admin.petty-cash.receipt-exchanges.store')->only(['store', 'searchIssuer']);
        $this->middleware('can:admin.petty-cash.receipt-exchanges.show')->only([
            'show', 'viewSettlementDocument', 'viewReturnProof',
        ]);
        $this->middleware('can:admin.petty-cash.receipt-exchanges.destroy')->only('destroySettlementDocument');
    }

    public function searchIssuer(Request $request, DocumentLookupService $documentLookup)
    {
        $validated = $request->validate(['ruc' => ['required', 'regex:/^\d{11}$/']]);
        $ruc = $validated['ruc'];

        $issuer = DocumentIssuer::where('ruc', $ruc)->first();
        if ($issuer) {
            return response()->json([
                'status' => 'success', 'source' => 'cache', 'data' => $issuer,
                'message' => 'Datos cargados desde historial.',
            ]);
        }

        $lookup = $documentLookup->searchRuc($ruc);
        if (! ($lookup['success'] ?? false)) {
            return response()->json([
                'status' => ($lookup['code'] ?? '') === 'RUC_NOT_FOUND' ? 'not_found' : 'error',
                'message' => $lookup['message'] ?? 'No se pudo consultar el RUC. Puede ingresar la razón social manualmente.',
            ], 422);
        }

        $issuer = DocumentIssuer::create([
            'ruc' => $ruc,
            'business_name' => mb_strtoupper($lookup['business_name']),
            'trade_name' => $lookup['commercial_name'] ?: null,
            'address' => $lookup['address'] ?: null,
            'status' => $lookup['status_text'] ?: null,
            'condition' => $lookup['condition'] ?: null,
            'source' => 'api',
            'api_response' => $lookup['raw'] ?? $lookup['data'] ?? null,
            'last_lookup_at' => now(),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'status' => 'success', 'source' => 'api', 'data' => $issuer,
            'message' => 'Datos consultados correctamente.',
        ]);
    }

    public function pending(PettyCashBox $pettyCash)
    {
        $receipts = $pettyCash->expenses()
            ->where('status', 'ACTIVE')
            ->where('document_type', 'RECIBO')
            ->approved()
            ->pendingExchange()
            ->with([
                'documents',
                'exchange.settlementDocuments.creator:id,name,lastname',
                'exchange.returns.responsibleUser:id,name,lastname',
                'exchange.returns.creator:id,name,lastname',
            ])
            ->orderBy('expense_date')
            ->orderBy('item_number')
            ->get();

        return response()->json(['data' => $receipts]);
    }

    public function store(Request $request, PettyCashBox $pettyCash)
    {
        if ($request->filled('expense_id') || $request->has('settlement_documents') || $request->boolean('has_return')) {
            return $this->storeSettlement($request, $pettyCash);
        }

        return $this->storeLegacyExchange($request, $pettyCash);
    }

    private function storeLegacyExchange(Request $request, PettyCashBox $pettyCash)
    {
        $validated = $request->validate([
            'exchange_date' => ['required', 'date'],
            'document_type' => ['required', Rule::in(['FACTURA', 'BOLETA', 'RECIBO_HONORARIOS'])],
            'document_series' => ['required', 'string', 'max:20'],
            'document_correlative' => ['required', 'string', 'max:50'],
            'issuer_ruc' => ['required', 'regex:/^\d{11}$/'],
            'issuer_business_name' => ['required', 'string', 'max:255'],
            'document_issuer_id' => ['nullable', 'integer', 'exists:document_issuers,id'],
            'expense_ids' => ['required', 'array', 'min:1'],
            'expense_ids.*' => ['required', 'integer', 'distinct', 'exists:petty_cash_expenses,id'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'expense_ids.required' => 'Seleccione al menos un recibo para canjear.',
            'expense_ids.min' => 'Seleccione al menos un recibo para canjear.',
            'issuer_ruc.required' => 'Ingrese el RUC del emisor del comprobante real.',
            'issuer_ruc.regex' => 'El RUC del emisor debe tener 11 dígitos.',
            'issuer_business_name.required' => 'Busque o ingrese la razón social del emisor.',
        ]);

        $files = (array) $request->file('documents', []);
        $storedPaths = [];

        try {
            $exchange = DB::transaction(function () use ($pettyCash, $validated, $files, &$storedPaths) {
                $box = PettyCashBox::lockForUpdate()->findOrFail($pettyCash->id);
                $ids = array_map('intval', $validated['expense_ids']);
                $expenses = PettyCashExpense::query()
                    ->whereIn('id', $ids)
                    ->lockForUpdate()
                    ->get();

                if ($expenses->count() !== count($ids) || $expenses->contains(
                    fn (PettyCashExpense $expense) => (int) $expense->petty_cash_box_id !== (int) $box->id
                )) {
                    throw ValidationException::withMessages([
                        'expense_ids' => 'Todos los recibos deben pertenecer a la caja chica seleccionada.',
                    ]);
                }
                if ($expenses->contains(fn (PettyCashExpense $expense) => $expense->document_type !== 'RECIBO')) {
                    throw ValidationException::withMessages([
                        'expense_ids' => 'Solo se pueden canjear gastos registrados como RECIBO.',
                    ]);
                }
                if ($expenses->contains(fn (PettyCashExpense $expense) => $expense->approval_status !== PettyCashExpense::APPROVAL_APPROVED)) {
                    throw ValidationException::withMessages([
                        'expense_ids' => 'No se puede canjear este recibo porque todavía no está aprobado.',
                    ]);
                }
                if ($expenses->contains(fn (PettyCashExpense $expense) => $expense->exchange_status !== PettyCashExpense::EXCHANGE_PENDING)) {
                    throw ValidationException::withMessages([
                        'expense_ids' => 'Uno o más recibos ya fueron canjeados o no están pendientes de canje.',
                    ]);
                }

                $issuer = DocumentIssuer::firstOrNew(['ruc' => $validated['issuer_ruc']]);
                if (! $issuer->exists) {
                    $issuer->fill([
                        'business_name' => mb_strtoupper(trim($validated['issuer_business_name'])),
                        'source' => 'manual',
                        'created_by' => Auth::id(),
                    ]);
                }
                $issuer->updated_by = Auth::id();
                $issuer->save();

                $exchange = $box->expenseExchanges()->create([
                    'document_issuer_id' => $issuer->id,
                    'exchange_date' => $validated['exchange_date'],
                    'document_type' => $validated['document_type'],
                    'document_series' => mb_strtoupper(trim($validated['document_series'])),
                    'document_correlative' => mb_strtoupper(trim($validated['document_correlative'])),
                    'issuer_ruc' => $validated['issuer_ruc'],
                    'issuer_business_name' => mb_strtoupper(trim($validated['issuer_business_name'])),
                    'total_amount' => round((float) $expenses->sum('amount'), 2),
                    'observation' => $validated['observation'] ?? null,
                    'status' => PettyCashExpenseExchange::STATUS_ACTIVE,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);

                foreach ($expenses as $expense) {
                    $exchange->items()->create([
                        'petty_cash_expense_id' => $expense->id,
                        'amount' => $expense->amount,
                        'concept' => $expense->concept,
                        'receipt_type' => $expense->document_type,
                        'receipt_series' => $expense->document_series,
                        'receipt_correlative' => $expense->document_correlative,
                    ]);
                    $expense->update([
                        'exchange_status' => PettyCashExpense::EXCHANGE_COMPLETED,
                        'exchange_id' => $exchange->id,
                        'exchanged_at' => now(),
                        'updated_by' => Auth::id(),
                    ]);
                }

                foreach ($files as $file) {
                    $path = $file->store("petty-cash/receipt-exchanges/{$exchange->id}", 'public');
                    $storedPaths[] = $path;
                    $type = DocumentType::firstOrCreate(
                        ['code' => 'PETTY_CASH_RECEIPT_EXCHANGE_DOCUMENT'],
                        ['description' => 'COMPROBANTE REAL DE CANJE DE RECIBOS', 'status' => 'ACTIVE']
                    );
                    $exchange->documents()->create([
                        'document_type_id' => $type->id,
                        'original_name' => $file->getClientOriginalName(),
                        'stored_name' => basename($path),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'extension' => strtolower($file->getClientOriginalExtension()),
                        'file_size' => $file->getSize(),
                        'status' => 'ACTIVE',
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                }

                $expenses->each(function (PettyCashExpense $expense) {
                    $this->pettyCashWarehouseExpenseService->syncAfterExchange($expense->fresh());
                });

                return $exchange;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter($storedPaths));
            throw $exception;
        }

        return response()->json([
            'message' => 'Recibos canjeados correctamente. El canje no modificó los saldos de caja.',
            'data' => $exchange,
        ], 201);
    }

    private function storeSettlement(Request $request, PettyCashBox $pettyCash)
    {
        $validated = $request->validate([
            'expense_id' => ['required', 'integer', 'exists:petty_cash_expenses,id'],
            'settlement_documents' => ['nullable', 'array', 'max:30'],
            'settlement_documents.*.issuer_ruc' => ['nullable', 'regex:/^\d{11}$/'],
            'settlement_documents.*.issuer_name' => ['required', 'string', 'max:255'],
            'settlement_documents.*.document_type' => ['required', Rule::in([
                'FACTURA', 'BOLETA', 'RECIBO_HONORARIOS', 'OTRO_OFICIAL',
            ])],
            'settlement_documents.*.series' => ['required', 'string', 'max:20'],
            'settlement_documents.*.number' => ['required', 'string', 'max:50'],
            'settlement_documents.*.issue_date' => ['required', 'date'],
            'settlement_documents.*.concept' => ['required', 'string', 'max:500'],
            'settlement_documents.*.amount' => ['required', 'numeric', 'gt:0'],
            'settlement_documents.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'has_return' => ['nullable', 'boolean'],
            'return_amount' => ['nullable', 'required_if:has_return,1', 'numeric', 'gt:0'],
            'return_date' => ['nullable', 'required_if:has_return,1', 'date'],
            'return_responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'return_responsible_name' => ['nullable', 'string', 'max:255'],
            'return_observation' => ['nullable', 'string', 'max:1000'],
            'return_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'expense_id.required' => 'Seleccione un recibo interno para registrar la rendición.',
            'settlement_documents.*.amount.gt' => 'El importe de cada comprobante debe ser mayor a 0.',
            'settlement_documents.*.issuer_ruc.regex' => 'El RUC del emisor debe tener 11 dígitos.',
            'return_amount.required_if' => 'Ingrese el monto retornado.',
            'return_amount.gt' => 'El monto retornado debe ser mayor a 0.',
            'return_date.required_if' => 'Ingrese la fecha del retorno.',
        ]);

        $documents = array_values($validated['settlement_documents'] ?? []);
        $hasReturn = (bool) ($validated['has_return'] ?? false);
        if (empty($documents) && ! $hasReturn) {
            throw ValidationException::withMessages([
                'settlement_documents' => 'Agregue al menos un comprobante o registre el vuelto.',
            ]);
        }

        $storedPaths = [];
        try {
            $exchange = DB::transaction(function () use (
                $request, $pettyCash, $validated, $documents, $hasReturn, &$storedPaths
            ) {
                $box = PettyCashBox::query()->lockForUpdate()->findOrFail($pettyCash->id);
                $expense = PettyCashExpense::query()->lockForUpdate()->findOrFail($validated['expense_id']);

                if ((int) $expense->petty_cash_box_id !== (int) $box->id) {
                    throw ValidationException::withMessages([
                        'expense_id' => 'El recibo seleccionado no pertenece a la caja chica indicada.',
                    ]);
                }
                if ($expense->status !== 'ACTIVE' || in_array($expense->approval_status, [
                    PettyCashExpense::APPROVAL_CANCELLED,
                    PettyCashExpense::APPROVAL_REJECTED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'expense_id' => 'No se puede rendir un recibo anulado o rechazado.',
                    ]);
                }
                if ($expense->document_type !== 'RECIBO') {
                    throw ValidationException::withMessages([
                        'expense_id' => 'Solo se pueden rendir gastos registrados como RECIBO INTERNO.',
                    ]);
                }
                if ($expense->approval_status !== PettyCashExpense::APPROVAL_APPROVED) {
                    throw ValidationException::withMessages([
                        'expense_id' => 'No se puede rendir este recibo porque todavía no está aprobado.',
                    ]);
                }
                if (! in_array($expense->exchange_status, [
                    PettyCashExpense::EXCHANGE_PENDING,
                    PettyCashExpense::EXCHANGE_PARTIAL,
                    PettyCashExpense::EXCHANGE_OBSERVED,
                ], true)) {
                    throw ValidationException::withMessages([
                        'expense_id' => 'El recibo ya fue rendido totalmente o no está disponible para canje.',
                    ]);
                }

                $wasObserved = $expense->exchange_status === PettyCashExpense::EXCHANGE_OBSERVED;
                $exchange = $expense->exchange_id
                    ? PettyCashExpenseExchange::query()->lockForUpdate()->find($expense->exchange_id)
                    : null;
                if ($exchange && $exchange->settlement_status === null) {
                    throw ValidationException::withMessages([
                        'expense_id' => 'Este recibo pertenece a un canje histórico que no admite rendición parcial.',
                    ]);
                }

                $firstDocument = $documents[0] ?? null;
                if (! $exchange) {
                    $exchange = $box->expenseExchanges()->create([
                        'exchange_date' => $firstDocument['issue_date'] ?? $validated['return_date'],
                        'document_type' => $firstDocument['document_type'] ?? 'RENDICION',
                        'document_series' => mb_strtoupper(trim($firstDocument['series'] ?? ($expense->document_series ?: 'RI'))),
                        'document_correlative' => mb_strtoupper(trim($firstDocument['number'] ?? ($expense->document_correlative ?: (string) $expense->id))),
                        'issuer_ruc' => $firstDocument['issuer_ruc'] ?? null,
                        'issuer_business_name' => isset($firstDocument['issuer_name'])
                            ? mb_strtoupper(trim($firstDocument['issuer_name']))
                            : null,
                        'total_amount' => $expense->amount,
                        'original_amount' => $expense->amount,
                        'supported_amount' => 0,
                        'returned_amount' => 0,
                        'pending_amount' => $expense->amount,
                        'settlement_status' => PettyCashExpenseExchange::SETTLEMENT_PENDING,
                        'status' => PettyCashExpenseExchange::STATUS_ACTIVE,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                    $exchange->items()->create([
                        'petty_cash_expense_id' => $expense->id,
                        'amount' => $expense->amount,
                        'concept' => $expense->concept,
                        'receipt_type' => $expense->document_type,
                        'receipt_series' => $expense->document_series,
                        'receipt_correlative' => $expense->document_correlative,
                    ]);
                    $expense->update(['exchange_id' => $exchange->id, 'updated_by' => Auth::id()]);
                    $expense->events()->create([
                        'event' => 'receipt_settlement_started',
                        'description' => 'Se inició la rendición del recibo interno '.$expense->document_full_number.'.',
                        'metadata' => ['exchange_id' => $exchange->id, 'original_amount' => (float) $expense->amount],
                        'created_by' => Auth::id(),
                    ]);
                }

                $existingSupported = round((float) $exchange->settlementDocuments()->sum('amount'), 2);
                $existingReturned = round((float) $exchange->returns()->sum('amount'), 2);
                $newSupported = round((float) collect($documents)->sum(fn ($document) => (float) $document['amount']), 2);
                $newReturned = $hasReturn ? round((float) $validated['return_amount'], 2) : 0.0;
                $originalAmount = round((float) ($exchange->original_amount ?? $expense->amount), 2);
                $pendingAfterDocuments = round($originalAmount - $existingSupported - $existingReturned - $newSupported, 2);

                if ($newSupported > round($originalAmount - $existingSupported - $existingReturned, 2) + 0.009) {
                    throw ValidationException::withMessages([
                        'settlement_documents' => 'La suma de comprobantes y vuelto no puede superar el monto del recibo interno.',
                    ]);
                }
                if ($newReturned > $pendingAfterDocuments + 0.009) {
                    throw ValidationException::withMessages([
                        'return_amount' => 'El vuelto no puede superar el saldo pendiente de rendición.',
                    ]);
                }
                if ($existingSupported + $existingReturned + $newSupported + $newReturned > $originalAmount + 0.009) {
                    throw ValidationException::withMessages([
                        'settlement_documents' => 'La suma de comprobantes y vuelto no puede superar el monto del recibo interno.',
                    ]);
                }

                $newKeys = [];
                foreach ($documents as $index => $documentData) {
                    $ruc = trim((string) ($documentData['issuer_ruc'] ?? '')) ?: null;
                    $type = mb_strtoupper(trim($documentData['document_type']));
                    $series = mb_strtoupper(trim($documentData['series']));
                    $number = mb_strtoupper(trim($documentData['number']));
                    $key = implode('|', [$ruc ?: '-', $type, $series, $number]);
                    if (isset($newKeys[$key]) || $exchange->settlementDocuments()
                        ->where('issuer_ruc', $ruc)
                        ->where('document_type', $type)
                        ->where('series', $series)
                        ->where('number', $number)
                        ->lockForUpdate()
                        ->exists()) {
                        throw ValidationException::withMessages([
                            "settlement_documents.$index.number" => 'Este comprobante ya fue registrado para el recibo interno.',
                        ]);
                    }
                    $newKeys[$key] = true;

                    $issuer = null;
                    if ($ruc) {
                        $issuer = DocumentIssuer::firstOrNew(['ruc' => $ruc]);
                        if (! $issuer->exists) {
                            $issuer->fill(['source' => 'manual', 'created_by' => Auth::id()]);
                        }
                        $issuer->business_name = mb_strtoupper(trim($documentData['issuer_name']));
                        $issuer->updated_by = Auth::id();
                        $issuer->save();
                    }

                    $file = $request->file("settlement_documents.$index.file");
                    $path = null;
                    if ($file) {
                        $path = $file->store("petty-cash/receipt-exchanges/{$exchange->id}/official", 'public');
                        $storedPaths[] = $path;
                    }
                    $document = $exchange->settlementDocuments()->create([
                        'issuer_id' => $issuer?->id,
                        'issuer_ruc' => $ruc,
                        'issuer_name' => mb_strtoupper(trim($documentData['issuer_name'])),
                        'document_type' => $type,
                        'series' => $series,
                        'number' => $number,
                        'issue_date' => $documentData['issue_date'],
                        'concept' => trim($documentData['concept']),
                        'amount' => round((float) $documentData['amount'], 2),
                        'file_path' => $path,
                        'original_name' => $file?->getClientOriginalName(),
                        'mime_type' => $file?->getMimeType(),
                        'file_size' => $file?->getSize(),
                        'status' => PettyCashExpenseExchangeDocument::STATUS_ACTIVE,
                        'created_by' => Auth::id(),
                        'updated_by' => Auth::id(),
                    ]);
                    $expense->events()->create([
                        'event' => 'settlement_document_added',
                        'description' => "Se agregó {$type} {$series}-{$number} por ".number_format((float) $document->amount, 2).'.',
                        'metadata' => ['exchange_id' => $exchange->id, 'document_id' => $document->id, 'amount' => (float) $document->amount],
                        'created_by' => Auth::id(),
                    ]);
                }

                if ($hasReturn) {
                    $returnFile = $request->file('return_file');
                    $returnPath = null;
                    if ($returnFile) {
                        $returnPath = $returnFile->store("petty-cash/receipt-exchanges/{$exchange->id}/returns", 'public');
                        $storedPaths[] = $returnPath;
                    }
                    $return = $exchange->returns()->create([
                        'petty_cash_box_id' => $box->id,
                        'amount' => $newReturned,
                        'return_date' => $validated['return_date'],
                        'responsible_user_id' => $validated['return_responsible_user_id'] ?? null,
                        'responsible_name' => trim((string) ($validated['return_responsible_name'] ?? '')) ?: $expense->supplier_name,
                        'observation' => $validated['return_observation'] ?? null,
                        'file_path' => $returnPath,
                        'original_name' => $returnFile?->getClientOriginalName(),
                        'mime_type' => $returnFile?->getMimeType(),
                        'file_size' => $returnFile?->getSize(),
                        'status' => PettyCashExpenseExchangeReturn::STATUS_ACTIVE,
                        'created_by' => Auth::id(),
                    ]);
                    $expense->events()->create([
                        'event' => 'settlement_return_registered',
                        'description' => 'Se registró un retorno de vuelto a Caja Chica por '.number_format((float) $return->amount, 2).'.',
                        'metadata' => ['exchange_id' => $exchange->id, 'return_id' => $return->id, 'amount' => (float) $return->amount],
                        'created_by' => Auth::id(),
                    ]);
                }

                $previousStatus = $exchange->settlement_status;
                $this->refreshSettlementTotals($exchange, $expense);
                if ($wasObserved) {
                    $expense->events()->create([
                        'event' => 'receipt_settlement_corrected',
                        'description' => 'La rendición observada fue corregida con nueva información.',
                        'metadata' => ['exchange_id' => $exchange->id],
                        'created_by' => Auth::id(),
                    ]);
                }
                if ($previousStatus !== PettyCashExpenseExchange::SETTLEMENT_SETTLED
                    && $exchange->settlement_status === PettyCashExpenseExchange::SETTLEMENT_SETTLED) {
                    $expense->events()->create([
                        'event' => 'receipt_settlement_completed',
                        'description' => 'El recibo interno quedó rendido totalmente.',
                        'metadata' => ['exchange_id' => $exchange->id],
                        'created_by' => Auth::id(),
                    ]);
                }

                $totals = $this->pettyCashCalculator->calculate($box);
                $box->update([
                    'total_expenses' => $totals['total_expenses'],
                    'cash_balance' => $totals['current_balance'],
                    'reimbursement_amount' => $totals['pending_replenishment'],
                    'updated_by' => Auth::id(),
                ]);

                return $exchange->fresh([
                    'items.expense', 'settlementDocuments.creator:id,name,lastname',
                    'returns.responsibleUser:id,name,lastname', 'returns.creator:id,name,lastname',
                    'creator:id,name,lastname',
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter($storedPaths));
            throw $exception;
        }

        return response()->json([
            'message' => $exchange->settlement_status === PettyCashExpenseExchange::SETTLEMENT_SETTLED
                ? 'Rendición completada correctamente.'
                : 'Rendición parcial guardada correctamente.',
            'data' => $exchange,
        ], 201);
    }

    private function refreshSettlementTotals(
        PettyCashExpenseExchange $exchange,
        PettyCashExpense $expense
    ): void {
        $original = round((float) ($exchange->original_amount ?? $expense->amount), 2);
        $supported = round((float) $exchange->settlementDocuments()->sum('amount'), 2);
        $returned = round((float) $exchange->returns()->sum('amount'), 2);
        $pending = max(0, round($original - $supported - $returned, 2));
        $settled = $pending <= 0.009;

        $exchange->update([
            'original_amount' => $original,
            'supported_amount' => $supported,
            'returned_amount' => $returned,
            'pending_amount' => $pending,
            'settlement_status' => $settled
                ? PettyCashExpenseExchange::SETTLEMENT_SETTLED
                : PettyCashExpenseExchange::SETTLEMENT_PARTIAL,
            'settled_at' => $settled ? ($exchange->settled_at ?: now()) : null,
            'updated_by' => Auth::id(),
        ]);
        $expense->update([
            'exchange_status' => $settled
                ? PettyCashExpense::EXCHANGE_COMPLETED
                : PettyCashExpense::EXCHANGE_PARTIAL,
            'exchanged_at' => $settled ? ($expense->exchanged_at ?: now()) : null,
            'exchange_id' => $exchange->id,
            'updated_by' => Auth::id(),
        ]);
    }

    public function show(PettyCashExpenseExchange $exchange)
    {
        $exchange->load([
            'documentIssuer', 'items.expense', 'documents', 'creator:id,name,lastname',
            'settlementDocuments.creator:id,name,lastname',
            'returns.responsibleUser:id,name,lastname', 'returns.creator:id,name,lastname',
        ]);
        $exchange->documents->each(fn (Document $document) => $document->setAttribute(
            'view_url',
            route('admin.petty-cash.documents.view', $document)
        ));

        return response()->json(['data' => $exchange]);
    }

    public function viewSettlementDocument(
        PettyCashExpenseExchange $exchange,
        PettyCashExpenseExchangeDocument $settlementDocument
    ) {
        abort_unless((int) $settlementDocument->exchange_id === (int) $exchange->id, 404);
        abort_unless($settlementDocument->status === PettyCashExpenseExchangeDocument::STATUS_ACTIVE, 404);
        abort_unless($settlementDocument->file_path && Storage::disk('public')->exists($settlementDocument->file_path), 404);

        return Storage::disk('public')->response(
            $settlementDocument->file_path,
            basename((string) ($settlementDocument->original_name ?: 'comprobante')),
            ['Content-Type' => $settlementDocument->mime_type ?: 'application/octet-stream']
        );
    }

    public function viewReturnProof(
        PettyCashExpenseExchange $exchange,
        PettyCashExpenseExchangeReturn $return
    ) {
        abort_unless((int) $return->exchange_id === (int) $exchange->id, 404);
        abort_unless($return->status === PettyCashExpenseExchangeReturn::STATUS_ACTIVE, 404);
        abort_unless($return->file_path && Storage::disk('public')->exists($return->file_path), 404);

        return Storage::disk('public')->response(
            $return->file_path,
            basename((string) ($return->original_name ?: 'constancia-vuelto')),
            ['Content-Type' => $return->mime_type ?: 'application/octet-stream']
        );
    }

    public function destroySettlementDocument(
        PettyCashExpenseExchange $exchange,
        PettyCashExpenseExchangeDocument $settlementDocument
    ) {
        DB::transaction(function () use ($exchange, $settlementDocument) {
            $lockedExchange = PettyCashExpenseExchange::query()->lockForUpdate()->findOrFail($exchange->id);
            $lockedDocument = PettyCashExpenseExchangeDocument::query()->lockForUpdate()->findOrFail($settlementDocument->id);
            abort_unless((int) $lockedDocument->exchange_id === (int) $lockedExchange->id, 404);
            abort_unless($lockedDocument->status === PettyCashExpenseExchangeDocument::STATUS_ACTIVE, 404);

            $expense = PettyCashExpense::query()
                ->where('exchange_id', $lockedExchange->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedDocument->update([
                'status' => PettyCashExpenseExchangeDocument::STATUS_INACTIVE,
                'updated_by' => Auth::id(),
            ]);
            $expense->events()->create([
                'event' => 'settlement_document_removed',
                'description' => "Se retiró {$lockedDocument->document_type} {$lockedDocument->document_full_number} de la rendición.",
                'metadata' => ['exchange_id' => $lockedExchange->id, 'document_id' => $lockedDocument->id],
                'created_by' => Auth::id(),
            ]);
            $this->refreshSettlementTotals($lockedExchange, $expense);

            $box = PettyCashBox::query()->lockForUpdate()->findOrFail($lockedExchange->petty_cash_box_id);
            $totals = $this->pettyCashCalculator->calculate($box);
            $box->update([
                'total_expenses' => $totals['total_expenses'],
                'cash_balance' => $totals['current_balance'],
                'reimbursement_amount' => $totals['pending_replenishment'],
                'updated_by' => Auth::id(),
            ]);
        });

        return response()->json(['message' => 'Comprobante retirado de la rendición.']);
    }
}
