<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankMovement;
use App\Models\BankReconciliation;
use App\Models\BankTransfer;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\CustomerPurchaseOrder;
use App\Models\PettyCashBox;
use App\Models\PettyCashReplenishment;
use App\Models\SupplierPurchaseOrder;
use App\Models\WarehouseEntryExpense;
use App\Services\BankMovementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class BankTreasuryController extends Controller
{
    public function __construct(private readonly BankMovementService $bankService)
    {
        $this->middleware('can:admin.banks.view')->only(['index', 'list', 'show', 'file']);
        $this->middleware('can:admin.banks.edit')->only('configureOpeningBalance');
        $this->middleware('can:admin.banks.movements.create')->only('storeMovement');
        $this->middleware('can:admin.banks.movements.cancel')->only(['cancelMovement', 'cancelTransfer']);
        $this->middleware('can:admin.banks.transfers.create')->only('storeTransfer');
        $this->middleware('can:admin.banks.reconciliations')->only('availableMovements');
        $this->middleware('can:admin.banks.reconciliations.create')->only('storeReconciliation');
        $this->middleware('can:admin.banks.export')->only(['export', 'accountExport']);
    }

    public function index()
    {
        $companies = Company::query()->where('status', true)->orderBy('business_name')->get();
        $currencies = Currency::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $accounts = CompanyBankAccount::query()
            ->with(['company:id,business_name,trade_name', 'bank:id,description,short_name', 'currency:id,code,symbol'])
            ->where('status', 'ACTIVE')
            ->orderBy('company_id')->orderBy('id')->get();
        $customerOrders = CustomerPurchaseOrder::query()
            ->whereNotIn('status', ['cancelled', 'deleted'])
            ->latest('id')->limit(500)
            ->get(['id', 'code', 'purchase_order_number', 'company_id', 'currency_id']);
        $supplierOrders = SupplierPurchaseOrder::query()
            ->whereNotIn('status', ['cancelled', 'deleted'])
            ->latest('id')->limit(500)
            ->get(['id', 'code', 'company_id', 'currency_id', 'payment_currency_id']);
        $pettyCashBoxes = PettyCashBox::query()
            ->where('status', '!=', PettyCashBox::STATUS_CANCELLED)
            ->latest('id')->limit(300)->get(['id', 'code', 'company_id', 'currency_id']);
        $pettyCashReplenishments = PettyCashReplenishment::query()
            ->where('status', 'ACTIVE')->latest('id')->limit(300)
            ->get(['id', 'code', 'petty_cash_box_id', 'fund_source_company_id', 'fund_source_bank_account_id']);
        $warehouseExpenses = WarehouseEntryExpense::query()
            ->with('warehouseEntry:id,entry_number,company_id')
            ->where('status', 'ACTIVE')
            ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', WarehouseEntryExpense::SOURCE_MANUAL))
            ->latest('id')->limit(300)
            ->get(['id', 'warehouse_entry_id', 'source_type', 'document_type', 'document_series', 'document_number', 'total_amount', 'description']);

        return view('admin.banks.index', compact(
            'companies', 'currencies', 'accounts', 'customerOrders', 'supplierOrders',
            'pettyCashBoxes', 'pettyCashReplenishments', 'warehouseExpenses'
        ));
    }

    public function list(Request $request)
    {
        $query = CompanyBankAccount::query()
            ->with(['company:id,business_name,trade_name', 'bank:id,description,short_name', 'currency:id,code,symbol'])
            ->withSum(['movements as total_income' => fn ($query) => $query
                ->where('direction', BankMovement::DIRECTION_IN)], 'amount')
            ->withSum(['movements as total_expense' => fn ($query) => $query
                ->where('direction', BankMovement::DIRECTION_OUT)], 'amount')
            ->when($request->integer('company_id'), fn ($query, $id) => $query->where('company_id', $id))
            ->when($request->integer('currency_id'), fn ($query, $id) => $query->where('currency_id', $id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('id');

        $periodStart = $request->date('date_from')?->startOfDay() ?? now()->startOfMonth();
        $periodEnd = $request->date('date_to')?->endOfDay() ?? now()->endOfMonth();
        $summaryAccountIds = CompanyBankAccount::query()
            ->when($request->integer('company_id'), fn ($query, $id) => $query->where('company_id', $id))
            ->when($request->integer('currency_id'), fn ($query, $id) => $query->where('currency_id', $id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->pluck('id');
        $periodMovements = BankMovement::query()
            ->whereIn('company_bank_account_id', $summaryAccountIds)
            ->whereBetween('movement_date', [$periodStart, $periodEnd]);
        $ledgerBalancePen = (float) BankMovement::query()
            ->whereIn('company_bank_account_id', $summaryAccountIds)
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'IN' THEN amount_pen ELSE -amount_pen END), 0) AS balance")
            ->value('balance');
        $positiveBalancesPen = BankMovement::query()
            ->whereIn('company_bank_account_id', $summaryAccountIds)
            ->groupBy('company_bank_account_id')
            ->selectRaw("SUM(CASE WHEN direction = 'IN' THEN amount_pen ELSE -amount_pen END) AS balance")
            ->get()->sum(fn ($row) => max((float) $row->balance, 0));

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('bank', fn (CompanyBankAccount $account) => $account->bank?->short_name ?: $account->bank?->description)
            ->addColumn('company', fn (CompanyBankAccount $account) => $account->company?->trade_name ?: $account->company?->business_name)
            ->addColumn('currency', fn (CompanyBankAccount $account) => $account->currency?->code)
            ->editColumn('opening_balance', fn (CompanyBankAccount $account) => $this->money($account, $account->opening_balance))
            ->addColumn('income', fn (CompanyBankAccount $account) => $this->money($account, $account->total_income))
            ->addColumn('expense', fn (CompanyBankAccount $account) => $this->money($account, $account->total_expense))
            ->editColumn('current_balance', fn (CompanyBankAccount $account) => '<strong>'.$this->money($account, $account->current_balance).'</strong>')
            ->editColumn('status', fn (CompanyBankAccount $account) => $account->status === 'ACTIVE'
                ? '<span class="badge badge-success">ACTIVA</span>'
                : '<span class="badge badge-secondary">INACTIVA</span>')
            ->addColumn('actions', fn (CompanyBankAccount $account) => view('admin.banks.partials.actions', compact('account'))->render())
            ->rawColumns(['current_balance', 'status', 'actions'])
            ->with(['summary' => [
                'total_banks_pen' => $ledgerBalancePen,
                'period_income_pen' => (float) (clone $periodMovements)->where('direction', 'IN')->sum('amount_pen'),
                'period_expense_pen' => (float) (clone $periodMovements)->where('direction', 'OUT')->sum('amount_pen'),
                'available_balance_pen' => (float) $positiveBalancesPen,
                'pending_reconciliation' => BankMovement::query()
                    ->whereIn('company_bank_account_id', $summaryAccountIds)
                    ->where('status', BankMovement::STATUS_REGISTERED)->count(),
            ]])
            ->make(true);
    }

    public function show(CompanyBankAccount $account)
    {
        $account->load([
            'company:id,business_name,trade_name,ruc', 'bank:id,description,short_name',
            'currency:id,code,symbol,description', 'openingBalanceSetter:id,name,lastname',
        ]);
        $movements = Auth::user()?->can('admin.banks.movements') ? $account->movements()->with(['creator:id,name,lastname', 'canceller:id,name,lastname', 'reversal:id,code'])
            ->latest('movement_date')->latest('id')->limit(150)->get()
            ->each(function (BankMovement $movement) {
                $movement->setAttribute('type_label', BankMovement::typeLabel($movement->movement_type));
                $movement->setAttribute('source_label', BankMovement::sourceLabel($movement->source_type));
                $movement->setAttribute('file_url', $movement->file_path
                    ? route('admin.banks.files', ['type' => 'movement', 'id' => $movement->id]) : null);
                $movement->setAttribute('source_url', match ($movement->source_type) {
                    'WAREHOUSE_ENTRY_PAYMENT' => route('admin.warehouse-entries.index', [
                        'from_warehouse_entry' => $movement->source_id,
                        'auto_open' => 1,
                    ]),
                    'GENERAL_CASH_FUNDING' => Auth::user()?->can('admin.general-cash.show')
                        ? route('admin.general-cash.index', [
                            'from_movement' => $movement->source_id,
                            'auto_open' => 1,
                        ])
                        : null,
                    default => null,
                });
            }) : collect();
        $transfers = Auth::user()?->can('admin.banks.transfers') ? BankTransfer::query()
            ->with(['fromAccount.bank:id,description,short_name', 'toAccount.bank:id,description,short_name', 'currency:id,code,symbol', 'destinationCurrency:id,code,symbol'])
            ->where(fn ($query) => $query->where('from_company_bank_account_id', $account->id)
                ->orWhere('to_company_bank_account_id', $account->id))
            ->latest('transfer_date')->limit(100)->get()
            ->each(fn (BankTransfer $transfer) => $transfer->setAttribute('file_url', $transfer->file_path
                ? route('admin.banks.files', ['type' => 'transfer', 'id' => $transfer->id]) : null)) : collect();
        $reconciliations = Auth::user()?->can('admin.banks.reconciliations')
            ? $account->reconciliations()->withCount('details')->latest('end_date')->limit(60)->get()
                ->each(fn (BankReconciliation $reconciliation) => $reconciliation->setAttribute('file_url', $reconciliation->file_path
                    ? route('admin.banks.files', ['type' => 'reconciliation', 'id' => $reconciliation->id]) : null))
            : collect();
        $trace = $this->trace($account, $movements);

        return response()->json(['data' => compact('account', 'movements', 'transfers', 'reconciliations', 'trace')]);
    }

    public function configureOpeningBalance(Request $request, CompanyBankAccount $account)
    {
        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_balance_date' => ['required', 'date'],
            'opening_balance_observation' => ['nullable', 'string', 'max:1000'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
        ], [
            'opening_balance.required' => 'Ingrese el saldo inicial.',
            'opening_balance.min' => 'El saldo inicial no puede ser negativo.',
            'opening_balance_date.required' => 'Ingrese la fecha del saldo inicial.',
            'exchange_rate.gt' => 'El tipo de cambio debe ser mayor a cero.',
        ]);
        $account->load('currency');
        if (strtoupper((string) $account->currency?->code) !== 'PEN' && empty($validated['exchange_rate'])) {
            throw ValidationException::withMessages(['exchange_rate' => 'Ingrese el tipo de cambio del saldo inicial.']);
        }

        $account = $this->bankService->configureOpeningBalance(
            $account,
            (string) $validated['opening_balance'],
            $validated['opening_balance_date'],
            $validated['opening_balance_observation'] ?? null,
            isset($validated['exchange_rate']) ? (string) $validated['exchange_rate'] : null,
            Auth::id()
        );

        return response()->json(['message' => 'Saldo inicial configurado correctamente.', 'data' => $account]);
    }

    public function storeMovement(Request $request)
    {
        $validated = $request->validate([
            'company_bank_account_id' => ['required', 'exists:company_bank_accounts,id'],
            'movement_date' => ['required', 'date'],
            'direction' => ['required', Rule::in(['IN', 'OUT'])],
            'movement_category' => ['required', Rule::in([
                'CUSTOMER_PAYMENT', 'DEPOSIT', 'OTHER_INCOME', 'SUPPLIER_PAYMENT', 'SUPPLIER_ADVANCE',
                'PETTY_CASH_OPENING', 'PETTY_CASH_REPLENISHMENT', 'BANK_FEE', 'OTHER_EXPENSE',
                'WAREHOUSE_ENTRY_EXPENSE',
                'ADJUSTMENT_POSITIVE', 'ADJUSTMENT_NEGATIVE',
            ])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'operation_number' => ['nullable', 'string', 'max:100'],
            'document_type' => ['nullable', 'string', 'max:50'],
            'document_series' => ['nullable', 'string', 'max:30'],
            'document_number' => ['nullable', 'string', 'max:80'],
            'document_date' => ['nullable', 'date'],
            'source_id' => ['nullable', 'integer'],
            'concept' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1500'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'company_bank_account_id.required' => 'Seleccione una cuenta bancaria.',
            'movement_date.required' => 'Ingrese la fecha del movimiento.',
            'amount.required' => 'Ingrese el monto.',
            'amount.gt' => 'El monto debe ser mayor a cero.',
            'concept.required' => 'Ingrese el concepto del movimiento.',
            'file.mimes' => 'El sustento debe ser PDF, JPG, JPEG, PNG o WEBP.',
        ]);

        $this->validateMovementDirection($validated['movement_category'], $validated['direction']);
        if (str_starts_with($validated['movement_category'], 'ADJUSTMENT_')) {
            abort_unless(Auth::user()?->can('admin.banks.adjustments'), 403);
        }
        [$sourceType, $sourceId, $sourceCode, $sourceDescription] = $this->resolveSource(
            $validated['movement_category'],
            $validated['source_id'] ?? null
        );
        if (in_array($sourceType, ['CUSTOMER_PAYMENT', 'SUPPLIER_PAYMENT', 'SUPPLIER_ADVANCE', 'PETTY_CASH_OPENING', 'PETTY_CASH_REPLENISHMENT', 'WAREHOUSE_ENTRY_EXPENSE'], true)
            && ! $sourceId) {
            throw ValidationException::withMessages(['source_id' => 'Seleccione el registro de origen del movimiento.']);
        }
        if (in_array($validated['movement_category'], ['CUSTOMER_PAYMENT', 'DEPOSIT', 'SUPPLIER_PAYMENT', 'SUPPLIER_ADVANCE', 'WAREHOUSE_ENTRY_EXPENSE'], true)
            && blank($validated['operation_number'] ?? null)) {
            throw ValidationException::withMessages(['operation_number' => 'Ingrese el número de operación bancaria.']);
        }

        $account = CompanyBankAccount::query()->with('currency')->findOrFail($validated['company_bank_account_id']);
        $this->validateSourceCompany($sourceType, $sourceId, $account);
        if (strtoupper((string) $account->currency?->code) !== 'PEN' && empty($validated['exchange_rate'])) {
            throw ValidationException::withMessages(['exchange_rate' => 'Ingrese el tipo de cambio para normalizar el movimiento en soles.']);
        }

        $storedPath = null;
        try {
            $fileData = [];
            if ($request->file('file')) {
                $file = $request->file('file');
                $storedPath = $file->store('bank-treasury/movements', 'public');
                $fileData = [
                    'file_path' => $storedPath,
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ];
            }
            $movement = $this->bankService->createMovement([
                ...collect($validated)->except(['movement_category', 'source_id', 'file'])->all(),
                ...$fileData,
                'currency_id' => $account->currency_id,
                'movement_type' => $this->movementType($validated['movement_category']),
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_code' => $sourceCode,
                'source_description' => $sourceDescription,
            ], Auth::id());
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Movimiento bancario registrado correctamente.', 'data' => $movement], 201);
    }

    public function storeTransfer(Request $request)
    {
        $activeAccountRule = fn () => Rule::exists('company_bank_accounts', 'id')
            ->where(fn ($query) => $query->where('status', 'ACTIVE')->whereNull('deleted_at'));

        $validated = $request->validate([
            'from_company_bank_account_id' => ['required', $activeAccountRule()],
            'to_company_bank_account_id' => ['required', 'different:from_company_bank_account_id', $activeAccountRule()],
            'transfer_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'operation_number' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1500'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'from_company_bank_account_id.required' => 'Seleccione la cuenta bancaria origen.',
            'from_company_bank_account_id.exists' => 'La cuenta bancaria origen no existe o no se encuentra activa.',
            'to_company_bank_account_id.required' => 'Seleccione la cuenta bancaria que recibirá la transferencia.',
            'to_company_bank_account_id.different' => 'No puede transferir a la misma cuenta bancaria.',
            'to_company_bank_account_id.exists' => 'La cuenta bancaria destino no existe o no se encuentra activa.',
            'amount.gt' => 'El monto debe ser mayor a cero.',
            'exchange_rate.gt' => 'El tipo de cambio debe ser mayor a cero.',
            'operation_number.required' => 'Ingrese el número de operación de la transferencia.',
        ]);

        $transferAccounts = CompanyBankAccount::query()
            ->with('currency:id,code')
            ->whereIn('id', [
                $validated['from_company_bank_account_id'],
                $validated['to_company_bank_account_id'],
            ])
            ->get()
            ->keyBy('id');
        $fromAccount = $transferAccounts->get((int) $validated['from_company_bank_account_id']);
        $toAccount = $transferAccounts->get((int) $validated['to_company_bank_account_id']);

        if ($fromAccount?->currency_id !== $toAccount?->currency_id && empty($validated['exchange_rate'])) {
            throw ValidationException::withMessages([
                'exchange_rate' => 'Ingrese un tipo de cambio mayor a cero para transferir entre monedas distintas.',
            ]);
        }

        $storedPath = null;
        try {
            if ($request->file('file')) {
                $file = $request->file('file');
                $storedPath = $file->store('bank-treasury/transfers', 'public');
                $validated += [
                    'file_path' => $storedPath,
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ];
            }
            $transfer = $this->bankService->createTransfer($validated, Auth::id());
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Transferencia registrada correctamente.', 'data' => $transfer], 201);
    }

    public function availableMovements(Request $request, CompanyBankAccount $account)
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);
        $movements = $account->movements()
            ->where('status', BankMovement::STATUS_REGISTERED)
            ->whereBetween('movement_date', [$validated['start_date'].' 00:00:00', $validated['end_date'].' 23:59:59'])
            ->oldest('movement_date')->get()
            ->each(function (BankMovement $movement) {
                $movement->setAttribute('type_label', BankMovement::typeLabel($movement->movement_type));
                $movement->setAttribute('source_label', BankMovement::sourceLabel($movement->source_type));
            });

        return response()->json(['data' => $movements]);
    }

    public function storeReconciliation(Request $request)
    {
        $validated = $request->validate([
            'company_bank_account_id' => ['required', 'exists:company_bank_accounts,id'],
            'period' => ['required', 'date_format:Y-m'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'bank_statement_balance' => ['required', 'numeric'],
            'movement_ids' => ['required', 'array', 'min:1'],
            'movement_ids.*' => ['required', 'integer', 'distinct', 'exists:bank_movements,id'],
            'observation' => ['nullable', 'string', 'max:1500'],
            'file' => ['nullable', 'file', 'mimes:pdf,xls,xlsx,csv,jpg,jpeg,png,webp', 'max:15360'],
        ], [
            'period.required' => 'Seleccione el periodo de conciliación.',
            'movement_ids.required' => 'Seleccione al menos un movimiento para conciliar.',
            'movement_ids.min' => 'Seleccione al menos un movimiento para conciliar.',
            'bank_statement_balance.required' => 'Ingrese el saldo del estado de cuenta bancario.',
        ]);

        $storedPath = null;
        try {
            if ($request->file('file')) {
                $file = $request->file('file');
                $storedPath = $file->store('bank-treasury/reconciliations', 'public');
                $validated += [
                    'file_path' => $storedPath,
                    'file_original_name' => $file->getClientOriginalName(),
                    'file_mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ];
            }
            $reconciliation = $this->bankService->reconcile($validated, Auth::id());
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Conciliación bancaria registrada correctamente.', 'data' => $reconciliation], 201);
    }

    public function cancelMovement(Request $request, BankMovement $movement)
    {
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['cancellation_reason.required' => 'Ingrese el motivo de la anulación.']);
        $movement = $this->bankService->cancelMovement($movement, $validated['cancellation_reason'], Auth::id());

        return response()->json(['message' => 'Movimiento anulado y reversado correctamente.', 'data' => $movement]);
    }

    public function cancelTransfer(Request $request, BankTransfer $transfer)
    {
        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], ['cancellation_reason.required' => 'Ingrese el motivo de la anulación.']);
        $transfer = $this->bankService->cancelTransfer($transfer, $validated['cancellation_reason'], Auth::id());

        return response()->json(['message' => 'Transferencia anulada y reversada correctamente.', 'data' => $transfer]);
    }

    public function file(string $type, int $id): BinaryFileResponse
    {
        $model = match ($type) {
            'movement' => BankMovement::findOrFail($id),
            'transfer' => BankTransfer::findOrFail($id),
            'reconciliation' => BankReconciliation::findOrFail($id),
            default => abort(404),
        };
        abort_unless($model->file_path && Storage::disk('public')->exists($model->file_path), 404);

        return response()->file(Storage::disk('public')->path($model->file_path), [
            'Content-Type' => $model->file_mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $model->file_original_name ?: basename($model->file_path)).'"',
        ]);
    }

    public function export(Request $request, string $format)
    {
        return $this->reportResponse($format, $this->reportData(null, $request));
    }

    public function accountExport(Request $request, CompanyBankAccount $account, string $format)
    {
        return $this->reportResponse($format, $this->reportData($account, $request));
    }

    private function reportData(?CompanyBankAccount $account, Request $request): array
    {
        $accounts = CompanyBankAccount::query()
            ->with(['company', 'bank', 'currency'])
            ->when($account, fn ($query) => $query->whereKey($account->id))
            ->orderBy('company_id')->orderBy('id')->get();
        $accountIds = $accounts->pluck('id');
        $movements = BankMovement::query()->with(['account.bank', 'currency'])
            ->whereIn('company_bank_account_id', $accountIds)
            ->when($request->date('date_from'), fn ($query, $date) => $query->where('movement_date', '>=', $date->startOfDay()))
            ->when($request->date('date_to'), fn ($query, $date) => $query->where('movement_date', '<=', $date->endOfDay()))
            ->orderBy('movement_date')->get();
        $transfers = BankTransfer::query()->with(['fromAccount.bank', 'toAccount.bank', 'currency', 'destinationCurrency'])
            ->where(fn ($query) => $query->whereIn('from_company_bank_account_id', $accountIds)
                ->orWhereIn('to_company_bank_account_id', $accountIds))
            ->orderBy('transfer_date')->get();
        $reconciliations = BankReconciliation::query()->with(['account.bank'])->withCount('details')
            ->whereIn('company_bank_account_id', $accountIds)->orderBy('end_date')->get();

        return compact('account', 'accounts', 'movements', 'transfers', 'reconciliations');
    }

    private function reportResponse(string $format, array $data)
    {
        abort_unless(in_array($format, ['pdf', 'excel', 'print'], true), 404);
        $name = $data['account'] ? 'movimientos_bancarios_'.$data['account']->id : 'bancos_tesoreria';
        if ($format === 'excel') {
            return response(view('admin.banks.report', $data + ['excel' => true])->render(), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => "attachment; filename={$name}.xls",
            ]);
        }
        if ($format === 'print') {
            return view('admin.banks.report', $data + ['printMode' => true]);
        }

        return Pdf::loadView('admin.banks.report', $data)->setPaper('a4', 'landscape')->stream($name.'.pdf');
    }

    private function resolveSource(string $category, ?int $sourceId): array
    {
        return match ($category) {
            'CUSTOMER_PAYMENT' => $this->sourceFromModel('CUSTOMER_PAYMENT', $sourceId, CustomerPurchaseOrder::class, 'purchase_order_number'),
            'SUPPLIER_PAYMENT' => $this->sourceFromModel('SUPPLIER_PAYMENT', $sourceId, SupplierPurchaseOrder::class, 'code'),
            'SUPPLIER_ADVANCE' => $this->sourceFromModel('SUPPLIER_ADVANCE', $sourceId, SupplierPurchaseOrder::class, 'code'),
            'PETTY_CASH_OPENING' => $this->sourceFromModel('PETTY_CASH_OPENING', $sourceId, PettyCashBox::class, 'code'),
            'PETTY_CASH_REPLENISHMENT' => $this->sourceFromModel('PETTY_CASH_REPLENISHMENT', $sourceId, PettyCashReplenishment::class, 'code'),
            'WAREHOUSE_ENTRY_EXPENSE' => $this->warehouseExpenseSource($sourceId),
            'ADJUSTMENT_POSITIVE', 'ADJUSTMENT_NEGATIVE' => ['BANK_ADJUSTMENT', null, null, 'Ajuste bancario manual'],
            default => ['MANUAL', null, null, 'Movimiento bancario manual'],
        };
    }

    private function sourceFromModel(string $type, ?int $id, string $model, string $codeField): array
    {
        if (! $id) {
            return [$type, null, null, null];
        }
        $record = $model::query()->findOrFail($id);
        $code = $record->{$codeField} ?: ($record->code ?? (string) $record->id);

        return [$type, $record->id, $code, BankMovement::sourceLabel($type).' · '.$code];
    }

    private function validateSourceCompany(string $sourceType, ?int $sourceId, CompanyBankAccount $account): void
    {
        if (! $sourceId) {
            return;
        }

        $companyId = match ($sourceType) {
            'CUSTOMER_PAYMENT' => CustomerPurchaseOrder::query()->whereKey($sourceId)->value('company_id'),
            'SUPPLIER_PAYMENT', 'SUPPLIER_ADVANCE' => SupplierPurchaseOrder::query()->whereKey($sourceId)->value('company_id'),
            'PETTY_CASH_OPENING' => PettyCashBox::query()->whereKey($sourceId)->value('company_id'),
            'PETTY_CASH_REPLENISHMENT' => PettyCashReplenishment::query()->whereKey($sourceId)->value('fund_source_company_id'),
            'WAREHOUSE_ENTRY_EXPENSE' => WarehouseEntryExpense::query()
                ->whereKey($sourceId)
                ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', WarehouseEntryExpense::SOURCE_MANUAL))
                ->with('warehouseEntry:id,company_id')
                ->first()?->warehouseEntry?->company_id,
            default => $account->company_id,
        };
        if ((int) $companyId !== (int) $account->company_id) {
            throw ValidationException::withMessages([
                'source_id' => 'El origen seleccionado no pertenece a la empresa de la cuenta bancaria.',
            ]);
        }
    }

    private function movementType(string $category): string
    {
        return match ($category) {
            'ADJUSTMENT_POSITIVE' => 'AJUSTE_POSITIVO',
            'ADJUSTMENT_NEGATIVE' => 'AJUSTE_NEGATIVO',
            'CUSTOMER_PAYMENT', 'DEPOSIT', 'OTHER_INCOME' => 'INGRESO',
            default => 'EGRESO',
        };
    }

    private function warehouseExpenseSource(?int $id): array
    {
        if (! $id) {
            return ['WAREHOUSE_ENTRY_EXPENSE', null, null, null];
        }
        $expense = WarehouseEntryExpense::query()
            ->with('warehouseEntry:id,entry_number,company_id')
            ->where(fn ($query) => $query->whereNull('source_type')->orWhere('source_type', WarehouseEntryExpense::SOURCE_MANUAL))
            ->findOrFail($id);
        if (BankMovement::query()
            ->where('source_type', 'WAREHOUSE_ENTRY_EXPENSE')
            ->where('source_id', $expense->id)
            ->where('status', '!=', BankMovement::STATUS_CANCELLED)
            ->exists()) {
            throw ValidationException::withMessages([
                'source_id' => 'Este costo de almacén ya tiene un movimiento bancario activo vinculado.',
            ]);
        }
        $code = ($expense->warehouseEntry?->entry_number ?: 'INGRESO').'-COSTO-'.$expense->id;

        return ['WAREHOUSE_ENTRY_EXPENSE', $expense->id, $code, 'Costo pagado directamente desde banco'];
    }

    private function validateMovementDirection(string $category, string $direction): void
    {
        $income = in_array($category, ['CUSTOMER_PAYMENT', 'DEPOSIT', 'OTHER_INCOME', 'ADJUSTMENT_POSITIVE'], true);
        if (($income && $direction !== 'IN') || (! $income && $direction !== 'OUT')) {
            throw ValidationException::withMessages(['direction' => 'El tipo seleccionado no coincide con la dirección del movimiento.']);
        }
    }

    private function trace(CompanyBankAccount $account, $movements): array
    {
        $trace = collect();
        if ($account->opening_balance_set_at) {
            $trace->push([
                'date' => $account->opening_balance_set_at,
                'title' => 'Saldo inicial configurado',
                'detail' => trim(($account->openingBalanceSetter?->name ?: 'Usuario').' · '.$account->currency?->code.' '.$account->opening_balance),
                'icon' => 'fa-coins',
            ]);
        }
        $movements->where('status', BankMovement::STATUS_CANCELLED)->each(fn ($movement) => $trace->push([
            'date' => $movement->cancelled_at,
            'title' => 'Movimiento anulado: '.$movement->code,
            'detail' => $movement->cancellation_reason,
            'icon' => 'fa-ban',
        ]));
        $movements->where('movement_type', 'REVERSA')->each(fn ($movement) => $trace->push([
            'date' => $movement->movement_date,
            'title' => 'Reversa registrada: '.$movement->code,
            'detail' => $movement->source_code,
            'icon' => 'fa-undo-alt',
        ]));

        return $trace->sortByDesc('date')->values()->all();
    }

    private function money(CompanyBankAccount $account, $amount): string
    {
        return trim(($account->currency?->symbol ?: $account->currency?->code).' '.number_format((float) $amount, 2));
    }
}
