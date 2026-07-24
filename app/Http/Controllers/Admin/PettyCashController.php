<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PettyCashBox;
use App\Models\PettyCashExpense;
use App\Models\PettyCashReplenishment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PettyCashController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.petty-cash.index')->only(['index', 'list']);
        $this->middleware('can:admin.petty-cash.store')->only('store');
        $this->middleware('can:admin.petty-cash.show')->only(['show', 'viewDocument']);
        $this->middleware('can:admin.petty-cash.update')->only('update');
        $this->middleware('can:admin.petty-cash.destroy')->only('destroy');
        $this->middleware('can:admin.petty-cash.expenses.store')->only('storeExpense');
        $this->middleware('can:admin.petty-cash.expenses.update')->only('updateExpense');
        $this->middleware('can:admin.petty-cash.expenses.destroy')->only('destroyExpense');
        $this->middleware('can:admin.petty-cash.close')->only('close');
        $this->middleware('can:admin.petty-cash.replenishments.store')->only('storeReplenishment');
        $this->middleware('can:admin.petty-cash.pdf')->only('pdf');
        $this->middleware('can:admin.petty-cash.excel')->only('excel');
    }

    public function index()
    {
        $companies = Company::active()->orderBy('business_name')->get();
        $currencies = Currency::where('status', 'ACTIVE')->orderBy('description')->get();
        $banks = Bank::where('status', 'ACTIVE')->orderBy('description')->get();

        return view('admin.petty-cash.index', compact('companies', 'currencies', 'banks'));
    }

    public function list()
    {
        $activeStatuses = [PettyCashBox::STATUS_OPEN, PettyCashBox::STATUS_IN_REVIEW];
        $summary = [
            'active_boxes' => PettyCashBox::whereIn('status', $activeStatuses)->count(),
            'visible_fund' => (float) PettyCashBox::whereIn('status', $activeStatuses)->sum('approved_fund'),
            'total_spent' => (float) PettyCashBox::whereIn('status', $activeStatuses)->sum('total_expenses'),
            'pending_replenishment' => (float) PettyCashBox::where('status', PettyCashBox::STATUS_OPEN)
                ->sum('reimbursement_amount'),
        ];
        $query = PettyCashBox::query()
            ->with(['company:id,business_name,trade_name', 'currency:id,code,symbol'])
            ->orderByDesc('id');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('id', fn (PettyCashBox $box) => $box->id)
            ->addColumn('company', fn (PettyCashBox $box) => $box->company?->trade_name
                ?? $box->company?->business_name ?? '-')
            ->addColumn('period', fn (PettyCashBox $box) => sprintf('%02d/%d', $box->period_month, $box->period_year))
            ->editColumn('start_date', fn (PettyCashBox $box) => $box->start_date?->format('d/m/Y'))
            ->editColumn('end_date', fn (PettyCashBox $box) => $box->end_date?->format('d/m/Y'))
            ->editColumn('approved_fund', fn (PettyCashBox $box) => $this->money($box, $box->approved_fund))
            ->editColumn('total_expenses', fn (PettyCashBox $box) => $this->money($box, $box->total_expenses))
            ->editColumn('cash_balance', fn (PettyCashBox $box) => $this->money($box, $box->cash_balance))
            ->editColumn('reimbursement_amount', fn (PettyCashBox $box) => $this->money($box, $box->reimbursement_amount))
            ->editColumn('status', fn (PettyCashBox $box) => $this->statusBadge($box->status))
            ->editColumn('created_at', fn (PettyCashBox $box) => $box->created_at?->format('d/m/Y H:i'))
            ->addColumn('actions', fn (PettyCashBox $box) => view(
                'admin.petty-cash.partials.actions',
                compact('box')
            )->render())
            ->rawColumns(['status', 'actions'])
            ->with(['summary' => $summary])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $this->validateBox($request);

        $box = DB::transaction(function () use ($validated) {
            $lastId = PettyCashBox::withTrashed()->lockForUpdate()->max('id') ?? 0;
            $fund = round((float) $validated['approved_fund'], 2);

            return PettyCashBox::create([
                ...$validated,
                'code' => sprintf('CC-%06d-%d', $lastId + 1, $validated['period_year']),
                'opening_amount' => $fund,
                'total_expenses' => 0,
                'cash_balance' => $fund,
                'reimbursement_amount' => 0,
                'status' => PettyCashBox::STATUS_OPEN,
                'opened_by' => Auth::id(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        });

        return response()->json(['message' => 'Caja chica aperturada correctamente.', 'data' => $box], 201);
    }

    public function show(PettyCashBox $pettyCash)
    {
        $pettyCash->load([
            'company', 'currency', 'creator',
            'expenses' => fn ($query) => $query->where('status', 'ACTIVE')->orderBy('item_number'),
            'expenses.documents',
            'replenishments' => fn ($query) => $query->where('status', 'ACTIVE')->orderBy('replenishment_date'),
            'replenishments.bank',
            'replenishments.documents',
        ]);

        $pettyCash->expenses->each(fn (PettyCashExpense $expense) => $this->appendDocumentUrls($expense));
        $pettyCash->replenishments->each(fn (PettyCashReplenishment $item) => $this->appendDocumentUrls($item));
        $pettyCash->setAttribute('status_label', PettyCashBox::STATUSES[$pettyCash->status] ?? $pettyCash->status);
        $pettyCash->setAttribute('can_manage_expenses', $pettyCash->canManageExpenses());
        $pettyCash->setAttribute('replenished_total', (float) $pettyCash->replenishments->sum('amount'));
        $pettyCash->setAttribute('can_replenish', $pettyCash->status === PettyCashBox::STATUS_OPEN
            && (float) $pettyCash->reimbursement_amount > 0);

        return response()->json(['data' => $pettyCash]);
    }

    public function update(Request $request, PettyCashBox $pettyCash)
    {
        abort_unless($pettyCash->canManageExpenses(), 422, 'La caja chica ya no puede editarse.');
        $validated = $this->validateBox($request);

        DB::transaction(function () use ($pettyCash, $validated) {
            $locked = PettyCashBox::lockForUpdate()->findOrFail($pettyCash->id);
            if ($locked->expenses()->where('status', 'ACTIVE')->exists()
                && round((float) $locked->approved_fund, 2) !== round((float) $validated['approved_fund'], 2)) {
                throw ValidationException::withMessages([
                    'approved_fund' => 'No puede modificar el fondo cuando existen gastos registrados.',
                ]);
            }
            $locked->update([...$validated, 'updated_by' => Auth::id()]);
            $this->recalculateTotals($locked);
        });

        return response()->json(['message' => 'Caja chica actualizada correctamente.']);
    }

    public function destroy(PettyCashBox $pettyCash)
    {
        DB::transaction(function () use ($pettyCash) {
            $box = PettyCashBox::lockForUpdate()->findOrFail($pettyCash->id);
            if ($box->expenses()->exists()) {
                $box->update(['status' => PettyCashBox::STATUS_CANCELLED, 'updated_by' => Auth::id()]);
            } else {
                $box->delete();
            }
        });

        return response()->json(['message' => 'Caja chica anulada correctamente.']);
    }

    public function storeExpense(Request $request, PettyCashBox $pettyCash)
    {
        return $this->saveExpense($request, $pettyCash);
    }

    public function updateExpense(Request $request, PettyCashExpense $expense)
    {
        return $this->saveExpense($request, $expense->pettyCashBox, $expense);
    }

    public function destroyExpense(PettyCashExpense $expense)
    {
        abort_unless($expense->pettyCashBox->canManageExpenses(), 422, 'La caja chica no admite cambios.');

        DB::transaction(function () use ($expense) {
            $box = PettyCashBox::lockForUpdate()->findOrFail($expense->petty_cash_box_id);
            $expense->delete();
            $this->recalculateTotals($box);
        });

        return response()->json(['message' => 'Gasto eliminado correctamente.']);
    }

    public function close(PettyCashBox $pettyCash)
    {
        DB::transaction(function () use ($pettyCash) {
            $box = PettyCashBox::lockForUpdate()->findOrFail($pettyCash->id);
            abort_unless($box->canManageExpenses(), 422, 'La caja chica no puede cerrarse.');
            abort_if((float) $box->total_expenses <= 0, 422, 'Debe registrar al menos un gasto antes del cierre.');
            $box->update([
                'status' => PettyCashBox::STATUS_CLOSED,
                'closed_by' => Auth::id(),
                'closed_at' => now(),
                'updated_by' => Auth::id(),
            ]);
        });

        return response()->json(['message' => 'Caja chica cerrada correctamente.']);
    }

    public function storeReplenishment(Request $request, PettyCashBox $pettyCash)
    {
        $validated = $request->validate([
            'replenishment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['CASH', 'TRANSFER', 'YAPE', 'PLIN', 'DEPOSIT', 'OTHER'])],
            'bank_id' => ['nullable', 'exists:banks,id'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:150'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $totals = DB::transaction(function () use ($pettyCash, $validated, $request) {
            $box = PettyCashBox::lockForUpdate()->findOrFail($pettyCash->id);
            abort_unless($box->status === PettyCashBox::STATUS_OPEN, 422, 'Solo puede reponer una caja abierta.');
            abort_if((float) $box->reimbursement_amount <= 0, 422, 'No existe monto pendiente de reposición.');

            $spent = round((float) $box->expenses()->where('status', 'ACTIVE')->sum('amount'), 2);
            $paid = round((float) $box->replenishments()->where('status', 'ACTIVE')->sum('amount'), 2);
            $pending = max(0, round($spent - $paid, 2));
            abort_if($pending <= 0, 422, 'No hay monto pendiente de reposición.');
            abort_if(
                round((float) $validated['amount'], 2) > $pending,
                422,
                'El monto a reponer no puede superar el pendiente de reposición.'
            );

            $lastId = PettyCashReplenishment::withTrashed()->lockForUpdate()->max('id') ?? 0;
            $replenishment = $box->replenishments()->create([
                ...collect($validated)->except('document')->all(),
                'code' => sprintf('RCC-%06d', $lastId + 1),
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            $this->storeDocument($replenishment, $request->file('document'), 'PETTY_CASH_REPLENISHMENT');

            $this->recalculateTotals($box);
            $box->refresh();

            return [
                'total_spent' => (float) $box->total_expenses,
                'total_replenished' => round($paid + (float) $validated['amount'], 2),
                'current_balance' => (float) $box->cash_balance,
                'pending_replenishment' => (float) $box->reimbursement_amount,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Reposición registrada correctamente.',
            'data' => $totals,
        ], 201);
    }

    public function viewDocument(Document $document)
    {
        abort_unless(in_array($document->documentable_type, [
            PettyCashExpense::class,
            PettyCashReplenishment::class,
            PettyCashBox::class,
        ], true), 404);
        abort_unless($document->file_path && Storage::disk('public')->exists($document->file_path), 404);

        return Storage::disk('public')->response($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    public function pdf(PettyCashBox $pettyCash)
    {
        $this->loadReportRelations($pettyCash);

        return Pdf::loadView('admin.petty-cash.pdf', ['box' => $pettyCash])
            ->setPaper('a4', 'landscape')
            ->stream($pettyCash->code . '.pdf');
    }

    public function excel(PettyCashBox $pettyCash)
    {
        $this->loadReportRelations($pettyCash);
        $content = view('admin.petty-cash.excel', ['box' => $pettyCash])->render();

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $pettyCash->code . '.xls"',
        ]);
    }

    private function saveExpense(Request $request, PettyCashBox $box, ?PettyCashExpense $expense = null)
    {
        abort_unless($box->canManageExpenses(), 422, 'La caja chica no admite gastos.');
        $validated = $request->validate([
            'expense_date' => ['required', 'date', 'after_or_equal:' . $box->start_date->toDateString(), 'before_or_equal:' . $box->end_date->toDateString()],
            'document_type' => ['nullable', Rule::in(['FACTURA', 'BOLETA', 'RECIBO', 'TICKET', 'OTRO'])],
            'document_number' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_ruc' => ['nullable', 'digits:11'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'concept' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        DB::transaction(function () use ($box, $expense, $validated, $request) {
            $locked = PettyCashBox::lockForUpdate()->findOrFail($box->id);
            abort_unless($locked->canManageExpenses(), 422, 'La caja chica no admite gastos.');
            $otherTotal = (float) $locked->expenses()->where('status', 'ACTIVE')
                ->when($expense, fn ($query) => $query->where('id', '!=', $expense->id))
                ->sum('amount');
            $replenished = (float) $locked->replenishments()->where('status', 'ACTIVE')->sum('amount');
            $available = (float) $locked->approved_fund - $otherTotal + $replenished;
            abort_if((float) $validated['amount'] > $available + 0.009, 422, 'El gasto supera el saldo disponible.');

            $data = [
                ...collect($validated)->except('document')->all(),
                'supplier_name' => mb_strtoupper($validated['supplier_name']),
                'concept' => mb_strtoupper($validated['concept']),
                'status' => 'ACTIVE',
                'updated_by' => Auth::id(),
            ];

            if ($expense) {
                abort_unless((int) $expense->petty_cash_box_id === (int) $locked->id, 404);
                $expense->update($data);
                $saved = $expense;
            } else {
                $data['item_number'] = ((int) $locked->expenses()->withTrashed()->max('item_number')) + 1;
                $data['created_by'] = Auth::id();
                $saved = $locked->expenses()->create($data);
            }

            $this->storeDocument($saved, $request->file('document'), 'PETTY_CASH_EXPENSE');
            $this->recalculateTotals($locked);
        });

        return response()->json(['message' => $expense ? 'Gasto actualizado correctamente.' : 'Gasto registrado correctamente.'], $expense ? 200 : 201);
    }

    private function validateBox(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'between:2020,2100'],
            'periodicity' => ['required', Rule::in(['WEEKLY', 'BIWEEKLY', 'MONTHLY', 'OTHER'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'approved_fund' => ['required', 'numeric', 'gt:0'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'responsible_dni' => ['required', 'digits:8'],
            'supervisor_name' => ['required', 'string', 'max:255'],
            'supervisor_dni' => ['required', 'digits:8'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function recalculateTotals(PettyCashBox $box): void
    {
        $total = round((float) $box->expenses()->where('status', 'ACTIVE')->sum('amount'), 2);
        $replenished = round((float) $box->replenishments()->where('status', 'ACTIVE')->sum('amount'), 2);
        $fund = round((float) $box->approved_fund, 2);
        $totals = PettyCashBox::calculateBalances($fund, $total, $replenished);
        $box->update([
            'total_expenses' => $total,
            'cash_balance' => $totals['current_balance'],
            'reimbursement_amount' => $totals['pending_replenishment'],
            'updated_by' => Auth::id(),
        ]);
    }

    private function storeDocument($documentable, $file, string $typeCode): void
    {
        if (! $file) return;
        $path = $file->store('petty-cash/' . class_basename($documentable) . '/' . $documentable->id, 'public');
        $type = DocumentType::firstOrCreate(
            ['code' => $typeCode],
            ['description' => str_replace('_', ' ', $typeCode), 'status' => 'ACTIVE', 'created_by' => Auth::id(), 'updated_by' => Auth::id()]
        );
        $documentable->documents()->create([
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

    private function appendDocumentUrls($model): void
    {
        $model->documents->each(fn (Document $document) => $document->setAttribute(
            'view_url',
            route('admin.petty-cash.documents.view', $document)
        ));
    }

    private function loadReportRelations(PettyCashBox $box): void
    {
        $box->load([
            'company', 'currency',
            'expenses' => fn ($q) => $q->where('status', 'ACTIVE')->orderBy('item_number'),
            'replenishments' => fn ($q) => $q->where('status', 'ACTIVE')->orderBy('replenishment_date'),
            'replenishments.bank',
        ]);
    }

    private function money(PettyCashBox $box, $amount): string
    {
        return trim(($box->currency?->symbol ?? '') . ' ' . number_format((float) $amount, 2));
    }

    private function statusBadge(string $status): string
    {
        $classes = [
            'OPEN' => 'badge-success',
            'IN_REVIEW' => 'badge-warning',
            'CLOSED' => 'badge-secondary',
            'REIMBURSED' => 'badge-primary',
            'CANCELLED' => 'badge-danger',
        ];
        return '<span class="badge ' . ($classes[$status] ?? 'badge-light') . ' px-2 py-1">'
            . e(PettyCashBox::STATUSES[$status] ?? $status) . '</span>';
    }
}
