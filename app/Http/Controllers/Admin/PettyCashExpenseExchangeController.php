<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\DocumentIssuer;
use App\Models\PettyCashBox;
use App\Models\PettyCashExpense;
use App\Models\PettyCashExpenseExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\DocumentLookupService;
use App\Services\PettyCashWarehouseExpenseService;

class PettyCashExpenseExchangeController extends Controller
{
    public function __construct(private PettyCashWarehouseExpenseService $pettyCashWarehouseExpenseService)
    {
        $this->middleware('can:admin.petty-cash.receipt-exchanges.index')->only('pending');
        $this->middleware('can:admin.petty-cash.receipt-exchanges.store')->only(['store', 'searchIssuer']);
        $this->middleware('can:admin.petty-cash.receipt-exchanges.show')->only('show');
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
        if (!($lookup['success'] ?? false)) {
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
            ->with('documents')
            ->orderBy('expense_date')
            ->orderBy('item_number')
            ->get();

        return response()->json(['data' => $receipts]);
    }

    public function store(Request $request, PettyCashBox $pettyCash)
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
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
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
                if (!$issuer->exists) {
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

    public function show(PettyCashExpenseExchange $exchange)
    {
        $exchange->load(['documentIssuer', 'items.expense', 'documents', 'creator:id,name,lastname']);
        $exchange->documents->each(fn (Document $document) => $document->setAttribute(
            'view_url',
            route('admin.petty-cash.documents.view', $document)
        ));

        return response()->json(['data' => $exchange]);
    }
}
