<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\PettyCashBox;
use App\Models\PettyCashApprovedAmount;
use App\Models\PettyCashExpense;
use App\Models\PettyCashExpenseExchange;
use App\Models\PettyCashReplenishment;
use App\Services\PettyCashCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class PettyCashController extends Controller
{
    public function __construct(private readonly PettyCashCalculator $calculator)
    {
        $this->middleware('can:admin.petty-cash.index')->only([
            'index', 'list', 'previousBalance', 'sourceBankAccounts',
        ]);
        $this->middleware('can:admin.petty-cash.store')->only('store');
        $this->middleware('can:admin.petty-cash.show')->only(['show', 'viewDocument']);
        $this->middleware('can:admin.petty-cash.update')->only('update');
        $this->middleware('can:admin.petty-cash.destroy')->only('destroy');
        $this->middleware('can:admin.petty-cash.expenses.store')->only('storeExpense');
        $this->middleware('can:admin.petty-cash.expenses.update')->only([
            'updateExpense',
            'destroyExpenseDocument',
        ]);
        $this->middleware('can:admin.petty-cash.expenses.destroy')->only('destroyExpense');
        $this->middleware('can:admin.petty-cash.expenses.approve')->only([
            'pendingExpenses',
            'approveExpense',
            'rejectExpense',
        ]);
        $this->middleware('can:admin.petty-cash.close')->only('close');
        $this->middleware('can:admin.petty-cash.replenishments.store')->only('storeReplenishment');
        $this->middleware('can:admin.petty-cash.pdf')->only('pdf');
        $this->middleware('can:admin.petty-cash.excel')->only('excel');
    }

    public function index()
    {
        $companies = Company::active()->orderBy('business_name')->get();
        $currencies = Currency::query()
            ->where('status', 'ACTIVE')
            ->whereIn('code', ['PEN', 'USD'])
            ->orderByRaw("CASE code WHEN 'PEN' THEN 1 WHEN 'USD' THEN 2 ELSE 3 END")
            ->get();
        $defaultCurrencyId = $currencies->firstWhere('code', 'PEN')?->id;
        $banks = Bank::where('status', 'ACTIVE')->orderBy('description')->get();

        return view('admin.petty-cash.index', compact(
            'companies',
            'currencies',
            'defaultCurrencyId',
            'banks'
        ));
    }

    public function list()
    {
        $activeStatuses = [PettyCashBox::STATUS_OPEN, PettyCashBox::STATUS_IN_REVIEW];
        $summary = [
            'active_boxes' => PettyCashBox::whereIn('status', $activeStatuses)->count(),
            'visible_fund' => (float) PettyCashBox::whereIn('status', $activeStatuses)->sum('cash_balance'),
            'total_spent' => (float) PettyCashBox::whereIn('status', $activeStatuses)->sum('total_expenses'),
            'pending_replenishment' => (float) PettyCashBox::where('status', PettyCashBox::STATUS_OPEN)
                ->sum('reimbursement_amount'),
            'pending_expenses_count' => PettyCashExpense::query()
                ->where('status', 'ACTIVE')
                ->pendingApproval()
                ->count(),
        ];
        $query = PettyCashBox::query()
            ->with(['company:id,business_name,trade_name', 'currency:id,code,symbol'])
            ->withCount([
                'expenses as pending_expenses_count' => fn ($query) => $query
                    ->where('status', 'ACTIVE')
                    ->pendingApproval(),
            ])
            ->orderByDesc('id');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('id', fn (PettyCashBox $box) => $box->id)
            ->addColumn('company', fn (PettyCashBox $box) => $box->company?->trade_name
                ?? $box->company?->business_name ?? '-')
            ->addColumn('period', fn (PettyCashBox $box) => sprintf('%02d/%d', $box->period_month, $box->period_year))
            ->editColumn('start_date', fn (PettyCashBox $box) => $box->start_date?->format('d/m/Y'))
            ->editColumn('end_date', fn (PettyCashBox $box) => $box->closed_at?->format('d/m/Y H:i') ?? 'Pendiente')
            ->editColumn('approved_fund', fn (PettyCashBox $box) => $this->money($box, $box->approved_fund))
            ->editColumn('opening_amount', fn (PettyCashBox $box) => $this->money($box, $box->opening_amount))
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
        $this->validatePreviousBalance($validated);
        $approvedAmount = $this->requireActiveApprovedAmount($validated);
        $this->applyOpeningCalculation($validated, $approvedAmount);
        $this->validateOpeningFundSource($validated);
        $files = (float) $validated['approved_fund'] > 0
            ? (array) $request->file('fund_source_receipts', [])
            : [];
        $storedPaths = [];

        try {
            $box = DB::transaction(function () use ($validated, $approvedAmount, $files, &$storedPaths) {
                $lastId = PettyCashBox::withTrashed()->lockForUpdate()->max('id') ?? 0;
                $openingAmount = round((float) $validated['opening_amount'], 2);

                $box = PettyCashBox::create([
                    ...collect($validated)->except(['fund_source_receipts', 'opening_amount'])->all(),
                    'approved_amount_id' => $approvedAmount?->id,
                    'approved_amount_snapshot' => $approvedAmount?->amount,
                    'code' => sprintf('CC-%06d-%d', $lastId + 1, $validated['period_year']),
                    'opening_amount' => $openingAmount,
                    'total_expenses' => 0,
                    'cash_balance' => $openingAmount,
                    'reimbursement_amount' => 0,
                    'status' => PettyCashBox::STATUS_OPEN,
                    'opened_by' => Auth::id(),
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
                foreach ($files as $file) {
                    $storedPaths[] = $this->storeDocument($box, $file, 'PETTY_CASH_OPENING_FUND_RECEIPT');
                }

                return $box;
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter($storedPaths));
            throw $exception;
        }

        return response()->json(['message' => 'Caja chica aperturada correctamente.', 'data' => $box], 201);
    }

    public function show(PettyCashBox $pettyCash)
    {
        $pettyCash->load([
            'company', 'currency', 'approvedAmount.currency:id,code,symbol', 'creator', 'closer:id,name,lastname', 'previousPettyCash:id,code',
            'sourceCompany', 'sourceBankAccount.bank', 'sourceBankAccount.currency', 'documents',
            'expenses' => fn ($query) => $query->where('status', 'ACTIVE')->orderBy('item_number'),
            'expenses.documents', 'expenses.creator:id,name,lastname',
            'expenses.approvedBy:id,name,lastname', 'expenses.rejectedBy:id,name,lastname',
            'expenses.exchange:id,document_type,document_series,document_correlative,total_amount',
            'replenishments' => fn ($query) => $query->where('status', 'ACTIVE')->orderBy('replenishment_date'),
            'replenishments.bank',
            'replenishments.sourceCompany',
            'replenishments.sourceBankAccount.bank',
            'replenishments.sourceBankAccount.currency',
            'replenishments.documents',
            'expenseExchanges' => fn ($query) => $query->where('status', PettyCashExpenseExchange::STATUS_ACTIVE)->latest('exchange_date'),
            'expenseExchanges.items.expense:id,supplier_name,concept',
            'expenseExchanges.documents',
            'expenseExchanges.creator:id,name,lastname',
        ]);

        $this->appendDocumentUrls($pettyCash);
        $pettyCash->expenses->each(fn (PettyCashExpense $expense) => $this->appendDocumentUrls($expense));
        $pettyCash->replenishments->each(fn (PettyCashReplenishment $item) => $this->appendDocumentUrls($item));
        $pettyCash->expenseExchanges->each(fn (PettyCashExpenseExchange $exchange) => $this->appendDocumentUrls($exchange));
        $pettyCash->setAttribute('status_label', PettyCashBox::STATUSES[$pettyCash->status] ?? $pettyCash->status);
        $pettyCash->setAttribute('can_manage_expenses', $pettyCash->canManageExpenses());
        $pettyCash->setAttribute('can_approve_expenses', Auth::user()?->can('admin.petty-cash.expenses.approve') ?? false);
        $pettyCash->setAttribute('pending_expenses_count', $pettyCash->expenses
            ->where('approval_status', PettyCashExpense::APPROVAL_PENDING)->count());
        $pettyCash->setAttribute('pending_exchange_receipts_count', $pettyCash->expenses
            ->where('document_type', 'RECIBO')
            ->where('approval_status', PettyCashExpense::APPROVAL_APPROVED)
            ->where('exchange_status', PettyCashExpense::EXCHANGE_PENDING)
            ->count());
        $pettyCash->setAttribute('can_create_receipt_exchanges', Auth::user()?->can('admin.petty-cash.receipt-exchanges.store') ?? false);
        $pettyCash->setAttribute('can_view_receipt_exchanges', Auth::user()?->can('admin.petty-cash.receipt-exchanges.show') ?? false);
        $pettyCash->setAttribute('replenished_total', (float) $pettyCash->replenishments->sum('amount'));
        $pettyCash->setAttribute('financial_summary', $this->calculator->calculate($pettyCash));
        $pettyCash->setAttribute('can_replenish', $pettyCash->status === PettyCashBox::STATUS_OPEN
            && (float) $pettyCash->reimbursement_amount > 0);
        if (! $pettyCash->approvedAmount) {
            $pettyCash->setRelation('approvedAmount', PettyCashApprovedAmount::query()
                ->with('currency:id,code,symbol')
                ->where('company_id', $pettyCash->company_id)
                ->where('currency_id', $pettyCash->currency_id)
                ->where('active', true)
                ->first());
        }
        if ($pettyCash->approved_amount_snapshot === null && $pettyCash->approvedAmount) {
            $pettyCash->setAttribute('approved_amount_snapshot', $pettyCash->approvedAmount->amount);
        }

        return response()->json(['data' => $pettyCash]);
    }

    public function previousBalance(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'exclude_id' => ['nullable', 'integer', 'exists:petty_cash_boxes,id'],
        ]);
        $previous = PettyCashBox::query()
            ->where('company_id', $validated['company_id'])
            ->where('currency_id', $validated['currency_id'])
            ->when($validated['exclude_id'] ?? null, fn ($query, $id) => $query->where('id', '!=', $id))
            ->where('cash_balance', '>', 0)
            ->whereIn('status', [PettyCashBox::STATUS_CLOSED, PettyCashBox::STATUS_REIMBURSED])
            ->whereDoesntHave('carriedForwardTo')
            ->latest('id')
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'previous_petty_cash_id' => $previous?->id,
                'previous_code' => $previous?->code,
                'previous_balance' => $previous ? (float) $previous->cash_balance : 0,
                'message' => $previous
                    ? "Saldo disponible calculado desde la caja {$previous->code}."
                    : 'Primera apertura para esta empresa y moneda: se utilizará el monto aprobado como fondo inicial.',
            ],
        ]);
    }

    public function sourceBankAccounts(Company $company)
    {
        $accounts = $company->bankAccounts()
            ->where('status', 'ACTIVE')
            ->with(['bank:id,description,short_name', 'currency:id,code'])
            ->orderBy('id')
            ->get()
            ->map(fn (CompanyBankAccount $account) => [
                'id' => $account->id,
                'label' => implode(' - ', array_filter([
                    $account->bank?->short_name ?: $account->bank?->description,
                    $account->currency?->code,
                    $account->account_number,
                ])) . ($account->cci ? " | CCI: {$account->cci}" : ''),
            ]);

        return response()->json(['data' => $accounts]);
    }

    public function update(Request $request, PettyCashBox $pettyCash)
    {
        abort_unless($pettyCash->canManageExpenses(), 422, 'La caja chica ya no puede editarse.');
        $validated = $this->validateBox($request);
        $this->validatePreviousBalance($validated, $pettyCash);
        $hasMovements = $pettyCash->expenses()->where('status', 'ACTIVE')->exists()
            || $pettyCash->replenishments()->where('status', 'ACTIVE')->exists();
        if ($hasMovements) {
            if ((int) $validated['company_id'] !== (int) $pettyCash->company_id
                || (int) $validated['currency_id'] !== (int) $pettyCash->currency_id) {
                throw ValidationException::withMessages([
                    'company_id' => 'No puede cambiar la empresa o moneda cuando la caja tiene movimientos.',
                ]);
            }
            $approvedAmount = $pettyCash->approvedAmount ?: $this->requireActiveApprovedAmount($validated);
            $validated['previous_balance'] = $pettyCash->previous_balance;
            $validated['approved_fund'] = $pettyCash->approved_fund;
            $validated['opening_amount'] = $pettyCash->opening_amount;
        } else {
            $approvedAmount = $this->requireActiveApprovedAmount($validated);
            $this->applyOpeningCalculation($validated, $approvedAmount);
        }
        $approvedAmountSnapshot = $hasMovements
            ? $pettyCash->approved_amount_snapshot
            : $approvedAmount->amount;
        $this->validateOpeningFundSource($validated);

        $files = (float) $validated['approved_fund'] > 0
            ? (array) $request->file('fund_source_receipts', [])
            : [];
        $storedPaths = [];
        try {
            DB::transaction(function () use ($pettyCash, $validated, $approvedAmount, $approvedAmountSnapshot, $files, &$storedPaths) {
            $locked = PettyCashBox::lockForUpdate()->findOrFail($pettyCash->id);
            $locked->update([
                ...collect($validated)->except(['fund_source_receipts', 'opening_amount'])->all(),
                'approved_amount_id' => $approvedAmount?->id,
                'approved_amount_snapshot' => $approvedAmountSnapshot,
                'opening_amount' => $validated['opening_amount'],
                'updated_by' => Auth::id(),
            ]);
            foreach ($files as $file) {
                $storedPaths[] = $this->storeDocument($locked, $file, 'PETTY_CASH_OPENING_FUND_RECEIPT');
            }
            $this->recalculateTotals($locked);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter($storedPaths));
            throw $exception;
        }

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

    public function pendingExpenses()
    {
        $expenses = PettyCashExpense::query()
            ->where('status', 'ACTIVE')
            ->pendingApproval()
            ->with([
                'pettyCashBox.company:id,business_name,trade_name',
                'pettyCashBox.currency:id,code,symbol',
                'creator:id,name,lastname',
                'documents',
            ])
            ->latest('created_at')
            ->get();

        $expenses->each(fn (PettyCashExpense $expense) => $this->appendDocumentUrls($expense));

        return response()->json(['data' => $expenses, 'count' => $expenses->count()]);
    }

    public function approveExpense(Request $request, PettyCashExpense $expense)
    {
        $validated = $request->validate([
            'approval_observation' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($expense, $validated) {
            $lockedExpense = PettyCashExpense::lockForUpdate()->findOrFail($expense->id);
            abort_unless(
                $lockedExpense->status === 'ACTIVE'
                && $lockedExpense->approval_status === PettyCashExpense::APPROVAL_PENDING,
                422,
                'Solo se pueden aprobar gastos pendientes.'
            );
            $box = PettyCashBox::lockForUpdate()->findOrFail($lockedExpense->petty_cash_box_id);
            abort_if($box->status === PettyCashBox::STATUS_CANCELLED, 422, 'La caja chica anulada no admite aprobaciones.');

            $available = $this->calculator->calculate($box)['current_balance'];
            abort_if(
                (float) $lockedExpense->amount > $available + 0.009,
                422,
                'El gasto no puede aprobarse porque supera el saldo disponible.'
            );

            $lockedExpense->update([
                'approval_status' => PettyCashExpense::APPROVAL_APPROVED,
                'approved_at' => now(),
                'approved_by_user_id' => Auth::id(),
                'rejected_at' => null,
                'rejected_by_user_id' => null,
                'approval_observation' => $validated['approval_observation'] ?? null,
                'updated_by' => Auth::id(),
            ]);
            $this->recalculateTotals($box);
        });

        return response()->json(['message' => 'Gasto aprobado correctamente. Ya afecta el saldo de la caja.']);
    }

    public function rejectExpense(Request $request, PettyCashExpense $expense)
    {
        $validated = $request->validate([
            'approval_observation' => ['required', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($expense, $validated) {
            $lockedExpense = PettyCashExpense::lockForUpdate()->findOrFail($expense->id);
            abort_unless(
                $lockedExpense->status === 'ACTIVE'
                && $lockedExpense->approval_status === PettyCashExpense::APPROVAL_PENDING,
                422,
                'Solo se pueden rechazar gastos pendientes.'
            );
            $box = PettyCashBox::lockForUpdate()->findOrFail($lockedExpense->petty_cash_box_id);
            abort_if($box->status === PettyCashBox::STATUS_CANCELLED, 422, 'La caja chica anulada no admite cambios.');

            $lockedExpense->update([
                'approval_status' => PettyCashExpense::APPROVAL_REJECTED,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'rejected_at' => now(),
                'rejected_by_user_id' => Auth::id(),
                'approval_observation' => $validated['approval_observation'],
                'updated_by' => Auth::id(),
            ]);
            $this->recalculateTotals($box);
        });

        return response()->json(['message' => 'Gasto rechazado correctamente. No afecta el saldo de la caja.']);
    }

    public function destroyExpenseDocument(PettyCashExpense $expense, Document $document)
    {
        abort_unless($expense->pettyCashBox->canManageExpenses(), 422, 'La caja chica no admite cambios.');
        abort_unless(
            $document->documentable_type === PettyCashExpense::class
            && (int) $document->documentable_id === (int) $expense->id,
            404
        );

        $path = DB::transaction(function () use ($document) {
            $locked = Document::lockForUpdate()->findOrFail($document->id);
            $filePath = $locked->file_path;
            $locked->update([
                'status' => 'INACTIVE',
                'updated_by' => Auth::id(),
                'deleted_by' => Auth::id(),
            ]);
            $locked->delete();

            return $filePath;
        });

        if ($path) {
            Storage::disk('public')->delete($path);
        }

        return response()->json(['message' => 'Comprobante eliminado correctamente.']);
    }

    public function close(Request $request, PettyCashBox $pettyCash)
    {
        $validated = $request->validate([
            'close_observation' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($pettyCash, $validated) {
            $box = PettyCashBox::lockForUpdate()->findOrFail($pettyCash->id);
            abort_unless($box->canManageExpenses(), 422, 'La caja chica no puede cerrarse.');
            abort_if(
                $box->expenses()->where('status', 'ACTIVE')->pendingApproval()->exists(),
                422,
                'No se puede cerrar la caja chica porque existen gastos pendientes de aprobación. Apruebe o rechace los gastos antes de cerrar.'
            );
            $box->update([
                'status' => PettyCashBox::STATUS_CLOSED,
                'closed_by' => Auth::id(),
                'closed_at' => now(),
                'end_date' => now()->toDateString(),
                'close_observation' => $validated['close_observation'] ?? null,
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
            'observation' => ['nullable', 'string', 'max:1000'],
            'fund_source_company_id' => ['required', 'exists:companies,id'],
            'fund_source_bank_account_id' => ['required', 'exists:company_bank_accounts,id'],
            'fund_source_receipts' => ['nullable', 'array'],
            'fund_source_receipts.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        $this->validateFundSourceAccount($validated);

        $files = array_values(array_filter([
            ...(array) $request->file('fund_source_receipts', []),
            $request->file('document'),
        ]));
        $storedPaths = [];
        try {
            $totals = DB::transaction(function () use ($pettyCash, $validated, $files, &$storedPaths) {
            $box = PettyCashBox::lockForUpdate()->findOrFail($pettyCash->id);
            abort_unless($box->status === PettyCashBox::STATUS_OPEN, 422, 'Solo puede reponer una caja abierta.');
            abort_if((float) $box->reimbursement_amount <= 0, 422, 'No existe monto pendiente de reposición.');

            $spent = round((float) $box->expenses()
                ->where('status', 'ACTIVE')
                ->approved()
                ->sum('amount'), 2);
            $paid = round((float) $box->replenishments()->where('status', 'ACTIVE')->sum('amount'), 2);
            $pending = max(0, round($spent - $paid, 2));
            abort_if($pending <= 0, 422, 'No hay monto pendiente de reposición.');

            $lastId = PettyCashReplenishment::withTrashed()->lockForUpdate()->max('id') ?? 0;
            $replenishment = $box->replenishments()->create([
                ...collect($validated)->except(['document', 'fund_source_receipts'])->all(),
                'code' => sprintf('RCC-%06d', $lastId + 1),
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
            foreach ($files as $file) {
                $storedPaths[] = $this->storeDocument($replenishment, $file, 'PETTY_CASH_REPLENISHMENT');
            }

            $this->recalculateTotals($box);
            $box->refresh();

            return [
                'total_spent' => (float) $box->total_expenses,
                'total_replenished' => round($paid + (float) $validated['amount'], 2),
                'current_balance' => (float) $box->cash_balance,
                'pending_replenishment' => (float) $box->reimbursement_amount,
            ];
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter($storedPaths));
            throw $exception;
        }

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
            PettyCashExpenseExchange::class,
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
            'expense_date' => [
                'required',
                'date',
                'after_or_equal:' . $box->start_date->toDateString(),
                ...($box->end_date ? ['before_or_equal:' . $box->end_date->toDateString()] : []),
            ],
            'document_type' => ['nullable', Rule::in(['FACTURA', 'BOLETA', 'RECIBO', 'TICKET', 'OTRO'])],
            'document_series' => ['nullable', 'string', 'max:20'],
            'document_correlative' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_ruc' => ['nullable', 'digits:11'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'concept' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $files = array_values(array_filter([
            ...(array) $request->file('documents', []),
            $request->file('document'),
        ]));
        $storedPaths = [];

        try {
            DB::transaction(function () use ($box, $expense, $validated, $files, &$storedPaths) {
                $locked = PettyCashBox::lockForUpdate()->findOrFail($box->id);
                abort_unless($locked->canManageExpenses(), 422, 'La caja chica no admite gastos.');
                if ($expense) {
                    if ($expense->exchange_status === PettyCashExpense::EXCHANGE_COMPLETED
                        && ($validated['document_type'] ?? null) !== 'RECIBO') {
                        throw ValidationException::withMessages([
                            'document_type' => 'No puede cambiar el tipo de comprobante porque este recibo ya fue canjeado.',
                        ]);
                    }
                    abort_unless(
                        $expense->approval_status === PettyCashExpense::APPROVAL_PENDING,
                        422,
                        'Solo se pueden editar gastos pendientes de aprobación.'
                    );
                }

                $data = [
                    ...collect($validated)->except(['document', 'documents'])->all(),
                    'document_series' => $this->normalizeDocumentPart($validated['document_series'] ?? null),
                    'document_correlative' => $this->normalizeDocumentPart($validated['document_correlative'] ?? null),
                    'supplier_name' => mb_strtoupper($validated['supplier_name']),
                    'concept' => mb_strtoupper($validated['concept']),
                    'status' => 'ACTIVE',
                    'updated_by' => Auth::id(),
                ];
                $data['document_number'] = $this->buildDocumentNumber(
                    $data['document_series'],
                    $data['document_correlative']
                );
                $newExchangeStatus = ($data['document_type'] ?? null) === 'RECIBO'
                    ? PettyCashExpense::EXCHANGE_PENDING
                    : PettyCashExpense::EXCHANGE_NOT_APPLICABLE;

                if ($expense) {
                    abort_unless((int) $expense->petty_cash_box_id === (int) $locked->id, 404);
                    if ($expense->exchange_status !== PettyCashExpense::EXCHANGE_COMPLETED) {
                        $data['exchange_status'] = $newExchangeStatus;
                    }
                    $expense->update($data);
                    $saved = $expense;
                } else {
                    $data['approval_status'] = PettyCashExpense::APPROVAL_PENDING;
                    $data['exchange_status'] = $newExchangeStatus;
                    $data['approved_at'] = null;
                    $data['approved_by_user_id'] = null;
                    $data['item_number'] = ((int) $locked->expenses()->withTrashed()->max('item_number')) + 1;
                    $data['created_by'] = Auth::id();
                    $saved = $locked->expenses()->create($data);
                }

                foreach ($files as $file) {
                    $storedPaths[] = $this->storeDocument($saved, $file, 'PETTY_CASH_EXPENSE');
                }
                $this->recalculateTotals($locked);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter($storedPaths));
            throw $exception;
        }

        return response()->json([
            'message' => $expense
                ? 'Gasto actualizado correctamente. Continúa pendiente de aprobación administrativa.'
                : 'Gasto registrado correctamente. Queda pendiente de aprobación administrativa.',
        ], $expense ? 200 : 201);
    }

    private function normalizeDocumentPart(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtoupper($value);
    }

    private function buildDocumentNumber(?string $series, ?string $correlative): ?string
    {
        $parts = array_filter([$series, $correlative], fn (?string $value) => filled($value));

        return $parts ? implode('-', $parts) : null;
    }

    private function validateBox(Request $request): array
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'currency_id' => [
                'required',
                Rule::exists('currencies', 'id')->where(
                    fn ($query) => $query
                        ->where('status', 'ACTIVE')
                        ->whereNull('deleted_at')
                        ->whereIn('code', ['PEN', 'USD'])
                ),
            ],
            'start_date' => ['required', 'date'],
            'approved_fund' => ['nullable', 'numeric', 'min:0'],
            'fund_source_company_id' => [
                'nullable',
                'exists:companies,id',
            ],
            'fund_source_bank_account_id' => [
                'nullable',
                'exists:company_bank_accounts,id',
            ],
            'fund_source_receipts' => ['nullable', 'array'],
            'fund_source_receipts.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'previous_balance' => ['nullable', 'numeric', 'min:0'],
            'previous_petty_cash_id' => ['nullable', 'integer', 'exists:petty_cash_boxes,id'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'responsible_dni' => ['required', 'digits:8'],
            'supervisor_name' => ['required', 'string', 'max:255'],
            'supervisor_dni' => ['required', 'digits:8'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);
        $openingDate = Carbon::parse($validated['start_date']);
        $validated['period_month'] = (int) $openingDate->month;
        $validated['period_year'] = (int) $openingDate->year;
        $validated['periodicity'] = 'OTHER';
        $validated['end_date'] = null;

        return $validated;
    }

    private function recalculateTotals(PettyCashBox $box): void
    {
        $totals = $this->calculator->calculate($box);
        $box->update([
            'total_expenses' => $totals['total_expenses'],
            'cash_balance' => $totals['current_balance'],
            'reimbursement_amount' => $totals['pending_replenishment'],
            'updated_by' => Auth::id(),
        ]);
    }

    private function validateFundSourceAccount(array $validated): void
    {
        $account = CompanyBankAccount::query()
            ->whereKey($validated['fund_source_bank_account_id'])
            ->where('company_id', $validated['fund_source_company_id'])
            ->where('status', 'ACTIVE')
            ->first();

        if (! $account) {
            throw ValidationException::withMessages([
                'fund_source_bank_account_id' => 'La cuenta bancaria seleccionada no pertenece a la empresa origen.',
            ]);
        }
    }

    private function requireActiveApprovedAmount(array $validated): PettyCashApprovedAmount
    {
        $approvedAmount = PettyCashApprovedAmount::query()
            ->where('company_id', $validated['company_id'])
            ->where('currency_id', $validated['currency_id'])
            ->where('active', true)
            ->first();

        if (! $approvedAmount || (float) $approvedAmount->amount <= 0) {
            throw ValidationException::withMessages([
                'company_id' => 'No existe un monto aprobado activo para esta empresa y moneda. Configure el monto aprobado antes de aperturar caja.',
            ]);
        }

        return $approvedAmount;
    }

    private function applyOpeningCalculation(array &$validated, PettyCashApprovedAmount $approvedAmount): void
    {
        $hasPreviousBox = ! empty($validated['previous_petty_cash_id']);
        $opening = $this->calculator->opening(
            (float) $approvedAmount->amount,
            $hasPreviousBox ? (float) $validated['previous_balance'] : null
        );
        $validated['previous_balance'] = $opening['available_balance'];
        $validated['approved_fund'] = $opening['fund_to_replenish'];
        $validated['opening_amount'] = $opening['initial_fund'];
    }

    private function validateOpeningFundSource(array &$validated): void
    {
        if ((float) $validated['approved_fund'] > 0) {
            if (empty($validated['fund_source_company_id']) || empty($validated['fund_source_bank_account_id'])) {
                throw ValidationException::withMessages([
                    'fund_source_company_id' => 'Seleccione la empresa y la cuenta bancaria de origen para completar el fondo aprobado.',
                ]);
            }
            $this->validateFundSourceAccount($validated);
            return;
        }

        $validated['fund_source_company_id'] = null;
        $validated['fund_source_bank_account_id'] = null;
        $validated['fund_source_receipts'] = [];
    }

    private function validatePreviousBalance(array &$validated, ?PettyCashBox $current = null): void
    {
        $sourceId = $validated['previous_petty_cash_id'] ?? null;

        if (! $sourceId) {
            $validated['previous_petty_cash_id'] = null;
            $validated['previous_balance'] = 0;
            return;
        }

        $source = PettyCashBox::findOrFail($sourceId);
        $errors = [];
        if ($current && (int) $source->id === (int) $current->id) {
            $errors['previous_petty_cash_id'] = 'Una caja no puede usar su propio saldo anterior.';
        } elseif ((int) $source->company_id !== (int) $validated['company_id']) {
            $errors['previous_petty_cash_id'] = 'El saldo anterior debe pertenecer a la misma empresa.';
        } elseif ((int) $source->currency_id !== (int) $validated['currency_id']) {
            $errors['previous_petty_cash_id'] = 'El saldo disponible debe pertenecer a la misma moneda.';
        } elseif (! in_array($source->status, [PettyCashBox::STATUS_CLOSED, PettyCashBox::STATUS_REIMBURSED], true)) {
            $errors['previous_petty_cash_id'] = 'El saldo anterior debe provenir de una caja finalizada.';
        } elseif ($source->carriedForwardTo()->when($current, fn ($query) => $query->where('id', '!=', $current->id))->exists()) {
            $errors['previous_petty_cash_id'] = 'El saldo de esta caja anterior ya fue utilizado.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $validated['previous_balance'] = round((float) $source->cash_balance, 2);
    }

    private function storeDocument($documentable, $file, string $typeCode): ?string
    {
        if (! $file) return null;
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

        return $path;
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
            'company', 'currency', 'sourceCompany', 'sourceBankAccount.bank', 'sourceBankAccount.currency',
            'expenses' => fn ($q) => $q->where('status', 'ACTIVE')
                ->approved()
                ->orderBy('item_number'),
            'replenishments' => fn ($q) => $q->where('status', 'ACTIVE')->orderBy('replenishment_date'),
            'replenishments.bank',
            'replenishments.sourceCompany',
            'replenishments.sourceBankAccount.bank',
            'replenishments.sourceBankAccount.currency',
            'replenishments.documents',
        ]);
        $box->setAttribute('pending_approval_expenses', $box->expenses()
            ->where('status', 'ACTIVE')
            ->pendingApproval()
            ->orderBy('item_number')
            ->get());
        $box->setAttribute('financial_summary', $this->calculator->calculate($box));
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
