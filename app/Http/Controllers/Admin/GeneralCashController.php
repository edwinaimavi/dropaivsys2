<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\Currency;
use App\Models\Document;
use App\Models\GeneralCashBox;
use App\Models\GeneralCashExpense;
use App\Models\GeneralCashMovement;
use App\Models\GeneralCashReconciliation;
use App\Models\Supplier;
use App\Models\User;
use App\Services\GeneralCashService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Yajra\DataTables\Facades\DataTables;

class GeneralCashController extends Controller
{
    public function __construct(private readonly GeneralCashService $service)
    {
        $this->middleware('can:admin.general-cash.index')->only(['index', 'list', 'bankAccounts']);
        $this->middleware('can:admin.general-cash.show')->only('show');
        $this->middleware('can:admin.general-cash.store')->only('store');
        $this->middleware('can:admin.general-cash.update')->only('update');
        $this->middleware('can:admin.general-cash.replenishments')->only('storeFunding');
        $this->middleware('can:admin.general-cash.expenses.store')->only('storeExpense');
        $this->middleware('can:admin.general-cash.expenses.approve')->only(['approveExpense', 'observeExpense']);
        $this->middleware('can:admin.general-cash.expenses.annul')->only('cancelExpense');
        $this->middleware('can:admin.general-cash.annul')->only('cancelFunding');
        $this->middleware('can:admin.general-cash.close')->only('storeReconciliation');
        $this->middleware('can:admin.general-cash.documents')->only('viewDocument');
        $this->middleware('can:admin.general-cash.reports')->only('export');
    }

    public function index(Request $request)
    {
        $companies = Company::query()->where('status', true)->orderBy('business_name')->get();
        $currencies = Currency::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $users = User::query()->orderBy('name')->orderBy('lastname')->get(['id', 'name', 'lastname']);
        $suppliers = Supplier::query()->where('status', 'ACTIVE')->orderBy('business_name')
            ->limit(800)->get(['id', 'ruc', 'business_name', 'short_name']);
        $boxes = GeneralCashBox::query()->with(['company:id,business_name,trade_name', 'currency:id,code,symbol'])
            ->where('status', GeneralCashBox::STATUS_ACTIVE)->orderBy('name')->get();
        $autoOpenBoxId = null;
        if ($request->boolean('auto_open') && $request->integer('from_movement')) {
            $autoOpenBoxId = GeneralCashMovement::query()->whereKey($request->integer('from_movement'))
                ->value('general_cash_box_id');
        }

        return view('admin.general-cash.index', compact('companies', 'currencies', 'users', 'suppliers', 'boxes', 'autoOpenBoxId'));
    }

    public function list(Request $request)
    {
        $query = GeneralCashBox::query()
            ->with(['company:id,business_name,trade_name', 'currency:id,code,symbol', 'responsible:id,name,lastname'])
            ->withSum(['movements as total_income' => fn ($query) => $query->where('direction', GeneralCashMovement::DIRECTION_IN)], 'amount')
            ->withSum(['movements as total_expense' => fn ($query) => $query->where('direction', GeneralCashMovement::DIRECTION_OUT)], 'amount')
            ->when($request->integer('company_id'), fn ($query, $id) => $query->where('company_id', $id))
            ->when($request->integer('currency_id'), fn ($query, $id) => $query->where('currency_id', $id))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('id');

        $boxIds = (clone $query)->pluck('id');
        $periodStart = $request->date('date_from')?->startOfDay() ?? now()->startOfMonth();
        $periodEnd = $request->date('date_to')?->endOfDay() ?? now()->endOfMonth();
        $summary = Currency::query()->whereIn('id', GeneralCashBox::query()->whereIn('id', $boxIds)->pluck('currency_id'))
            ->get(['id', 'code', 'symbol'])->map(function (Currency $currency) use ($boxIds, $periodStart, $periodEnd) {
                $currencyBoxIds = GeneralCashBox::query()->whereIn('id', $boxIds)->where('currency_id', $currency->id)->pluck('id');
                $period = GeneralCashMovement::query()->whereIn('general_cash_box_id', $currencyBoxIds)
                    ->whereBetween('movement_date', [$periodStart, $periodEnd]);

                return [
                    'currency' => $currency->code,
                    'symbol' => $currency->symbol ?: $currency->code,
                    'balance' => (float) GeneralCashBox::query()->whereIn('id', $currencyBoxIds)->sum('current_balance'),
                    'income' => (float) (clone $period)->where('direction', GeneralCashMovement::DIRECTION_IN)->sum('amount'),
                    'expense' => (float) (clone $period)->where('direction', GeneralCashMovement::DIRECTION_OUT)->sum('amount'),
                ];
            })->values();
        $pending = GeneralCashExpense::query()->whereIn('general_cash_box_id', $boxIds)
            ->whereIn('status', [GeneralCashExpense::STATUS_REGISTERED, GeneralCashExpense::STATUS_OBSERVED])->count();

        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('company', fn (GeneralCashBox $box) => $box->company?->trade_name ?: $box->company?->business_name)
            ->addColumn('currency', fn (GeneralCashBox $box) => $box->currency?->code)
            ->addColumn('responsible', fn (GeneralCashBox $box) => trim(($box->responsible?->name ?? '').' '.($box->responsible?->lastname ?? '')) ?: 'No asignado')
            ->editColumn('current_balance', fn (GeneralCashBox $box) => '<strong>'.$this->money($box, $box->current_balance).'</strong>')
            ->addColumn('income', fn (GeneralCashBox $box) => $this->money($box, $box->total_income))
            ->addColumn('expense', fn (GeneralCashBox $box) => $this->money($box, $box->total_expense))
            ->editColumn('status', fn (GeneralCashBox $box) => $box->status === GeneralCashBox::STATUS_ACTIVE
                ? '<span class="badge badge-success">ACTIVA</span>'
                : '<span class="badge badge-secondary">INACTIVA</span>')
            ->addColumn('actions', fn (GeneralCashBox $box) => view('admin.general-cash.partials.actions', compact('box'))->render())
            ->rawColumns(['current_balance', 'status', 'actions'])
            ->with(['summary' => ['currencies' => $summary, 'pending' => $pending]])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $this->validateBox($request);
        $box = DB::transaction(fn () => GeneralCashBox::create([
            ...$validated,
            'code' => $this->code('CG'),
            'current_balance' => 0,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]));

        return response()->json(['message' => 'Caja General creada correctamente.', 'data' => $box], 201);
    }

    public function update(Request $request, GeneralCashBox $generalCash)
    {
        $validated = $this->validateBox($request);
        if ($generalCash->movements()->exists()
            && ((int) $generalCash->company_id !== (int) $validated['company_id']
                || (int) $generalCash->currency_id !== (int) $validated['currency_id'])) {
            throw ValidationException::withMessages([
                'company_id' => 'No puede cambiar la empresa ni moneda de una caja que ya tiene movimientos.',
            ]);
        }
        DB::transaction(fn () => $generalCash->update($validated + ['updated_by' => Auth::id()]));

        return response()->json(['message' => 'Caja General actualizada correctamente.', 'data' => $generalCash->fresh()]);
    }

    public function show(GeneralCashBox $generalCash)
    {
        $generalCash->load(['company:id,business_name,trade_name,ruc', 'currency:id,code,symbol,description', 'responsible:id,name,lastname', 'creator:id,name,lastname', 'updater:id,name,lastname']);
        $movements = Auth::user()?->can('admin.general-cash.movements') ? $generalCash->movements()->with([
            'bankAccount.bank:id,description,short_name', 'bankMovement:id,code,status',
            'responsible:id,name,lastname', 'creator:id,name,lastname', 'canceller:id,name,lastname',
            'reversal:id,code', 'documents',
        ])->latest('movement_date')->latest('id')->limit(200)->get() : collect();
        $expenses = Auth::user()?->can('admin.general-cash.expenses') ? $generalCash->expenses()->with([
            'supplier:id,business_name,short_name,ruc', 'creator:id,name,lastname', 'approver:id,name,lastname',
            'observer:id,name,lastname', 'canceller:id,name,lastname', 'documents',
        ])->latest('expense_date')->latest('id')->limit(200)->get() : collect();
        $reconciliations = $generalCash->reconciliations()->with(['responsible:id,name,lastname', 'creator:id,name,lastname', 'documents'])
            ->latest('reconciliation_date')->limit(100)->get();
        collect([$movements, $expenses, $reconciliations])->flatten()->each(function ($record) {
            if ($record->relationLoaded('documents')) {
                $record->documents->each(fn (Document $document) => $this->presentDocument($document));
            }
        });
        $trace = $this->trace($generalCash, $movements, $expenses, $reconciliations);

        return response()->json(['data' => compact('generalCash', 'movements', 'expenses', 'reconciliations', 'trace')]);
    }

    public function bankAccounts(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
        ]);
        $accounts = CompanyBankAccount::query()->with('bank:id,description,short_name')
            ->where('company_id', $validated['company_id'])->where('currency_id', $validated['currency_id'])
            ->where('status', 'ACTIVE')->orderBy('bank_id')->get(['id', 'bank_id', 'account_number', 'current_balance']);

        return response()->json(['data' => $accounts]);
    }

    public function storeFunding(Request $request)
    {
        $validated = $request->validate([
            'general_cash_box_id' => ['required', 'integer', 'exists:general_cash_boxes,id'],
            'company_bank_account_id' => ['required', 'integer', 'exists:company_bank_accounts,id'],
            'movement_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'operation_number' => ['required', 'string', 'max:100'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_name' => ['nullable', 'string', 'max:150'],
            'observation' => ['nullable', 'string', 'max:1500'],
            'support_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,xls,xlsx', 'max:15360'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ], [
            'general_cash_box_id.required' => 'Seleccione la Caja General.',
            'company_bank_account_id.required' => 'Seleccione la cuenta bancaria origen.',
            'movement_date.required' => 'Seleccione la fecha del ingreso.',
            'amount.required' => 'Ingrese el monto retirado.',
            'amount.gt' => 'El monto debe ser mayor a cero.',
            'operation_number.required' => 'Ingrese el número de operación bancaria.',
        ]);
        if ($existing = GeneralCashMovement::query()->where('idempotency_key', $validated['idempotency_key'])->first()) {
            return response()->json([
                'message' => 'El ingreso ya había sido registrado. Se devolvió el movimiento existente.',
                'data' => $existing->load(['box.currency', 'bankAccount.bank', 'bankMovement', 'documents']),
            ], 201);
        }

        return $this->withUploadedDocuments($request, $validated, [
            'support_file' => 'GENERAL_CASH_SUPPORT',
        ], 'general-cash/fundings', fn (array $data) => response()->json([
            'message' => 'Efectivo ingresado correctamente. Se registró el egreso bancario relacionado.',
            'data' => $this->service->fundFromBank($data, Auth::id()),
        ], 201));
    }

    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'general_cash_box_id' => ['required', 'integer', 'exists:general_cash_boxes,id'],
            'expense_date' => ['required', 'date'],
            'expense_type' => ['required', 'string', 'max:80'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'person_name' => ['required', 'string', 'max:180'],
            'identity_document' => ['nullable', 'string', 'max:20'],
            'concept' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(GeneralCashExpense::DOCUMENT_TYPES)],
            'document_series' => ['nullable', 'string', 'max:30'],
            'document_number' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'affects_igv' => ['required', 'boolean'],
            'observation' => ['nullable', 'string', 'max:1500'],
            'receipt_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'payment_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:15360'],
            'idempotency_key' => ['required', 'string', 'max:191'],
        ], [
            'general_cash_box_id.required' => 'Seleccione la Caja General.',
            'expense_date.required' => 'Seleccione la fecha del gasto.',
            'expense_type.required' => 'Seleccione el tipo de gasto.',
            'person_name.required' => 'Ingrese el proveedor, persona o responsable.',
            'concept.required' => 'Ingrese el concepto del gasto.',
            'document_type.required' => 'Seleccione el tipo de comprobante.',
            'amount.required' => 'Ingrese el importe del gasto.',
        ]);
        if ($existing = GeneralCashExpense::query()->where('idempotency_key', $validated['idempotency_key'])->first()) {
            return response()->json([
                'message' => 'El gasto ya había sido registrado. Se devolvió el registro existente.',
                'data' => $existing->load(['box.currency', 'movement', 'documents']),
            ], 201);
        }

        return $this->withUploadedDocuments($request, $validated, [
            'receipt_file' => 'GENERAL_CASH_RECEIPT',
            'payment_file' => 'GENERAL_CASH_PAYMENT_SUPPORT',
        ], 'general-cash/expenses', fn (array $data) => response()->json([
            'message' => 'Gasto general registrado y descontado correctamente.',
            'data' => $this->service->createExpense($data, Auth::id()),
        ], 201));
    }

    public function approveExpense(GeneralCashExpense $expense)
    {
        return response()->json(['message' => 'Gasto aprobado correctamente.', 'data' => $this->service->approveExpense($expense, Auth::id())]);
    }

    public function observeExpense(Request $request, GeneralCashExpense $expense)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], ['reason.required' => 'Ingrese el motivo de la observación.']);

        return response()->json(['message' => 'Gasto observado correctamente.', 'data' => $this->service->observeExpense($expense, $validated['reason'], Auth::id())]);
    }

    public function cancelExpense(Request $request, GeneralCashExpense $expense)
    {
        $validated = $this->validateCancellation($request);

        return response()->json(['message' => 'Gasto anulado y saldo restaurado mediante reversa.', 'data' => $this->service->cancelExpense($expense, $validated['reason'], Auth::id())]);
    }

    public function cancelFunding(Request $request, GeneralCashMovement $movement)
    {
        $validated = $this->validateCancellation($request);

        return response()->json(['message' => 'Ingreso anulado. Banco y Caja General fueron reversados.', 'data' => $this->service->cancelFunding($movement, $validated['reason'], Auth::id())]);
    }

    public function storeReconciliation(Request $request)
    {
        $validated = $request->validate([
            'general_cash_box_id' => ['required', 'integer', 'exists:general_cash_boxes,id'],
            'reconciliation_date' => ['required', 'date'],
            'physical_balance' => ['required', 'numeric', 'min:0'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_name' => ['nullable', 'string', 'max:150'],
            'observation' => ['nullable', 'string', 'max:1500'],
            'support_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp,xls,xlsx', 'max:15360'],
        ], [
            'general_cash_box_id.required' => 'Seleccione la Caja General.',
            'reconciliation_date.required' => 'Seleccione la fecha del arqueo.',
            'physical_balance.required' => 'Ingrese el saldo físico contado.',
        ]);

        return $this->withUploadedDocuments($request, $validated, [
            'support_file' => 'GENERAL_CASH_RECONCILIATION',
        ], 'general-cash/reconciliations', fn (array $data) => response()->json([
            'message' => 'Arqueo de Caja General registrado correctamente.',
            'data' => $this->service->reconcile($data, Auth::id()),
        ], 201));
    }

    public function viewDocument(Document $document): BinaryFileResponse
    {
        abort_unless(in_array($document->documentable_type, [
            GeneralCashMovement::class, GeneralCashExpense::class, GeneralCashReconciliation::class,
        ], true), 404);
        abort_unless($document->status === 'ACTIVE' && $document->file_path && Storage::disk('public')->exists($document->file_path), 404);

        return response()->file(Storage::disk('public')->path($document->file_path), [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.str_replace('"', '', $document->original_name ?: basename($document->file_path)).'"',
        ]);
    }

    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['pdf', 'excel', 'print'], true), 404);
        $boxes = GeneralCashBox::query()->with(['company', 'currency', 'responsible'])
            ->when($request->integer('company_id'), fn ($query, $id) => $query->where('company_id', $id))
            ->when($request->integer('box_id'), fn ($query, $id) => $query->whereKey($id))->orderBy('company_id')->get();
        $movements = GeneralCashMovement::query()->with(['box.currency', 'bankAccount.bank', 'responsible'])
            ->whereIn('general_cash_box_id', $boxes->pluck('id'))
            ->when($request->date('date_from'), fn ($query, $date) => $query->where('movement_date', '>=', $date->startOfDay()))
            ->when($request->date('date_to'), fn ($query, $date) => $query->where('movement_date', '<=', $date->endOfDay()))
            ->orderBy('movement_date')->get();
        $expenses = GeneralCashExpense::query()->with(['box.currency', 'supplier'])
            ->whereIn('general_cash_box_id', $boxes->pluck('id'))->orderBy('expense_date')->get();
        $data = compact('boxes', 'movements', 'expenses');
        if ($format === 'excel') {
            return response(view('admin.general-cash.report', $data + ['excel' => true])->render(), 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename=caja_general.xls',
            ]);
        }
        if ($format === 'print') {
            return view('admin.general-cash.report', $data + ['printMode' => true]);
        }

        return Pdf::loadView('admin.general-cash.report', $data)->setPaper('a4', 'landscape')->stream('caja_general.pdf');
    }

    private function validateBox(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1500'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['required', Rule::in([GeneralCashBox::STATUS_ACTIVE, GeneralCashBox::STATUS_INACTIVE])],
        ], [
            'company_id.required' => 'Seleccione la empresa.',
            'currency_id.required' => 'Seleccione la moneda.',
            'name.required' => 'Ingrese el nombre de la Caja General.',
            'status.required' => 'Seleccione el estado.',
        ]);
    }

    private function validateCancellation(Request $request): array
    {
        return $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']], ['reason.required' => 'Ingrese el motivo de la anulación.']);
    }

    private function withUploadedDocuments(Request $request, array $data, array $fields, string $directory, callable $callback)
    {
        $paths = [];
        $data['documents'] = [];
        try {
            foreach ($fields as $field => $category) {
                if (! $request->file($field)) {
                    continue;
                }
                $file = $request->file($field);
                $path = $file->store($directory, 'public');
                $paths[] = $path;
                $data['documents'][] = [
                    'category' => $category, 'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ];
            }

            return $callback($data);
        } catch (\Throwable $exception) {
            foreach ($paths as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $exception;
        }
    }

    private function presentDocument(Document $document): void
    {
        $available = filled($document->file_path) && Storage::disk('public')->exists($document->file_path);
        $document->setAttribute('file_available', $available);
        $document->setAttribute('view_url', $available && Auth::user()?->can('admin.general-cash.documents')
            ? route('admin.general-cash.documents.view', $document)
            : null);
    }

    private function trace(GeneralCashBox $box, $movements, $expenses, $reconciliations): array
    {
        return collect([[
            'date' => $box->created_at, 'title' => 'Caja General creada',
            'detail' => trim(($box->creator?->name ?: 'Usuario').' · '.$box->code), 'icon' => 'fa-cash-register',
        ]])->concat($movements->where('status', GeneralCashMovement::STATUS_CANCELLED)->map(fn ($movement) => [
            'date' => $movement->cancelled_at, 'title' => 'Movimiento anulado: '.$movement->code,
            'detail' => $movement->cancellation_reason, 'icon' => 'fa-ban',
        ]))->concat($expenses->where('status', GeneralCashExpense::STATUS_APPROVED)->map(fn ($expense) => [
            'date' => $expense->approved_at, 'title' => 'Gasto aprobado: '.$expense->code,
            'detail' => $expense->concept, 'icon' => 'fa-check-circle',
        ]))->concat($reconciliations->map(fn ($reconciliation) => [
            'date' => $reconciliation->reconciliation_date, 'title' => 'Arqueo registrado: '.$reconciliation->code,
            'detail' => 'Diferencia: '.number_format((float) $reconciliation->difference, 2), 'icon' => 'fa-balance-scale',
        ]))->sortByDesc('date')->values()->all();
    }

    private function money(GeneralCashBox $box, $amount): string
    {
        return trim(($box->currency?->symbol ?: $box->currency?->code).' '.number_format((float) $amount, 2));
    }

    private function code(string $prefix): string
    {
        return sprintf('%s-%s-%s', $prefix, now()->format('YmdHis'), Str::upper(Str::random(5)));
    }
}
