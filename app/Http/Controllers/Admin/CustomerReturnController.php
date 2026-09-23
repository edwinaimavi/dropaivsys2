<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CustomerReturn;
use App\Models\Document;
use App\Models\User;
use App\Models\WarehouseDispatch;
use App\Services\CustomerReturnDocumentService;
use App\Services\CustomerReturnService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CustomerReturnController extends Controller
{
    public function __construct(
        private readonly CustomerReturnService $service,
        private readonly CustomerReturnDocumentService $documentService
    ) {
        $this->middleware('can:devoluciones_clientes.ver')->only(['index', 'list', 'show']);
        $this->middleware('can:devoluciones_clientes.crear')->only(['store']);
        $this->middleware('can:devoluciones_clientes.editar')->only(['update']);
        $this->middleware('can:devoluciones_clientes.confirmar')->only(['confirm']);
        $this->middleware('can:devoluciones_clientes.cancelar')->only(['cancelDraft']);
        $this->middleware('can:devoluciones_clientes.reversar')->only(['reverse']);
        $this->middleware('can:devoluciones_clientes.documentos')->only(['documents', 'storeDocuments', 'showDocument', 'deleteDocument']);
    }

    public function index()
    {
        $companies = Auth::user()->companies()->where('status', true)->orderBy('business_name')->get();
        return view('admin.customer-returns.index', [
            'companies' => $companies,
            'reasons' => CustomerReturn::REASONS,
            'documentTypes' => $this->documentService->types(),
        ]);
    }

    public function list(Request $request)
    {
        $query = CustomerReturn::query()->with([
            'company:id,business_name,trade_name', 'customerPurchaseOrder.customer',
            'warehouseDispatch:id,dispatch_number', 'warehouse:id,code,name',
        ])->withSum('items as total_quantity', 'quantity')
            ->withSum('items as inventory_value', 'total_cost')
            ->whereIn('company_id', $this->companyIds())
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->integer('company_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('return_date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('return_date', '<=', $request->input('date_to')))
            ->when($request->filled('search_text'), function ($q) use ($request) {
                $term = '%'.trim((string) $request->input('search_text')).'%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('return_number', 'like', $term)
                        ->orWhereHas('warehouseDispatch', fn ($dispatch) => $dispatch->where('dispatch_number', 'like', $term))
                        ->orWhereHas('customerPurchaseOrder', fn ($order) => $order->where('code', 'like', $term)->orWhere('purchase_order_number', 'like', $term))
                        ->orWhereHas('customerPurchaseOrder.customer', fn ($customer) => $customer->where('business_name', 'like', $term)->orWhere('full_name', 'like', $term));
                });
            })->latest('return_date')->latest('id');

        return DataTables::eloquent($query)
            ->editColumn('return_date', fn (CustomerReturn $row) => $row->return_date_display)
            ->addColumn('customer_name', fn (CustomerReturn $row) => $this->customerName($row))
            ->addColumn('company_name', fn (CustomerReturn $row) => $row->company?->trade_name ?: $row->company?->business_name)
            ->addColumn('order_number', fn (CustomerReturn $row) => $row->customerPurchaseOrder?->purchase_order_number ?: $row->customerPurchaseOrder?->code)
            ->addColumn('dispatch_number', fn (CustomerReturn $row) => $row->warehouseDispatch?->dispatch_number)
            ->addColumn('warehouse_name', fn (CustomerReturn $row) => collect([$row->warehouse?->code, $row->warehouse?->name])->filter()->implode(' | '))
            ->addColumn('invoice_situation', fn (CustomerReturn $row) => $this->invoiceSituation($row))
            ->addColumn('can_view', fn () => Auth::user()->can('devoluciones_clientes.ver'))
            ->addColumn('can_edit', fn (CustomerReturn $row) => $row->isDraft() && Auth::user()->can('devoluciones_clientes.editar'))
            ->addColumn('can_confirm', fn (CustomerReturn $row) => $row->isDraft() && Auth::user()->can('devoluciones_clientes.confirmar'))
            ->addColumn('can_cancel', fn (CustomerReturn $row) => $row->isDraft() && Auth::user()->can('devoluciones_clientes.cancelar'))
            ->addColumn('can_reverse', fn (CustomerReturn $row) => $row->isConfirmed() && Auth::user()->can('devoluciones_clientes.reversar'))
            ->addColumn('can_documents', fn () => Auth::user()->can('devoluciones_clientes.documentos'))
            ->toJson();
    }

    public function dispatchData(WarehouseDispatch $dispatch)
    {
        abort_unless(Auth::user()?->can('devoluciones_clientes.ver')
            || Auth::user()?->can('devoluciones_clientes.crear')
            || Auth::user()?->can('devoluciones_clientes.editar'), 403);
        $this->ensureDispatchAccess($dispatch);
        $dispatch->load(['customerPurchaseOrder.customer', 'warehouse', 'items.customerPurchaseOrderItem.article']);
        $available = $this->service->returnableQuantities($dispatch);
        $invoices = $this->service->invoiceContext($dispatch->items->pluck('id'));
        $returned = $this->service->confirmedReturnedQuantities($dispatch->items->pluck('id'));
        $dispatch->setAttribute(
            'dispatch_date_display',
            $dispatch->dispatch_date?->copy()?->timezone(config('app.timezone'))->format('d/m/Y H:i')
        );
        $items = $dispatch->items->map(function ($item) use ($available, $returned, $invoices) {
            $invoiceRows = $invoices->where('warehouse_dispatch_item_id', $item->id);
            return [
                'id' => $item->id,
                'article' => $item->customerPurchaseOrderItem?->billing_name_snapshot ?: $item->customerPurchaseOrderItem?->article?->billing_name,
                'lot_number' => $item->lot_number,
                'expiration_date' => $item->expiration_date?->format('Y-m-d'),
                'dispatched_quantity' => (float) $item->quantity,
                'returned_quantity' => (float) $returned->get($item->id, 0),
                'returnable_quantity' => (float) $available->get($item->id, 0),
                'billed_quantity' => round((float) $invoiceRows->sum('quantity'), 4),
                'invoices' => $invoiceRows->map(fn ($invoice) => [
                    'id' => $invoice->id, 'number' => $invoice->full_number,
                    'quantity' => (float) $invoice->quantity, 'payment_status' => $invoice->payment_status,
                    'paid_amount' => (float) $invoice->paid_amount, 'pending_amount' => (float) $invoice->pending_amount,
                ])->values(),
            ];
        })->values();

        return response()->json(['status' => 'success', 'data' => [
            'dispatch' => $dispatch, 'items' => $items, 'reasons' => CustomerReturn::REASONS,
            'responsibles' => User::query()->where('status', 1)->whereHas('companies', fn ($q) => $q->whereKey($dispatch->company_id))
                ->orderBy('name')->orderBy('lastname')->get(['users.id', 'name', 'lastname']),
            'current_user_id' => Auth::id(), 'idempotency_key' => (string) Str::uuid(),
            'current_local_datetime' => now()->timezone(config('app.timezone'))->format('Y-m-d\TH:i'),
            'safety_warning' => 'Confirme únicamente productos aptos para reintegrarse al stock disponible. Productos dañados, vencidos o no aptos requerirán un flujo de cuarentena/baja independiente.',
            'invoice_warning' => 'Esta devolución corresponde total o parcialmente a mercadería facturada. La devolución física no modifica el comprobante ni la cobranza. Debe gestionarse la Nota de Crédito correspondiente.',
        ]]);
    }

    public function store(Request $request, WarehouseDispatch $dispatch)
    {
        $this->ensureDispatchAccess($dispatch);
        [$data, $documents] = $this->validatedRequest($request, $dispatch, true);
        $return = $this->service->createDraft($dispatch, $data, $documents);
        return response()->json(['status' => 'success', 'message' => 'Borrador de devolución guardado. El stock y Kardex no fueron modificados.', 'data' => $return], 201);
    }

    public function show(CustomerReturn $customerReturn)
    {
        $this->ensureReturnAccess($customerReturn);
        $customerReturn->load(['company', 'customerPurchaseOrder.customer', 'warehouseDispatch', 'warehouse',
            'receivedBy', 'creator', 'confirmedBy', 'cancelledBy', 'reversedBy',
            'items.article', 'items.unit', 'items.presentation', 'items.brand',
            'items.kardexMovement', 'items.reversalKardexMovement']);
        $invoices = $this->service->invoiceContext($customerReturn->items->pluck('warehouse_dispatch_item_id'));
        return response()->json(['status' => 'success', 'data' => [
            'return' => $customerReturn,
            'reason_label' => CustomerReturn::REASONS[$customerReturn->reason] ?? $customerReturn->reason,
            'total_quantity' => (float) $customerReturn->items->sum('quantity'),
            'inventory_value' => round((float) $customerReturn->items->sum('total_cost'), 2),
            'actions' => $this->actionPermissions($customerReturn),
            'invoices' => $invoices->map(fn ($row) => ['number' => $row->full_number, 'quantity' => (float) $row->quantity,
                'payment_status' => $row->payment_status, 'paid_amount' => (float) $row->paid_amount, 'pending_amount' => (float) $row->pending_amount])->values(),
            'documents' => $this->documentsPayload($customerReturn),
        ]]);
    }

    public function update(Request $request, CustomerReturn $customerReturn)
    {
        $this->ensureReturnAccess($customerReturn);
        $dispatch = $customerReturn->warehouseDispatch;
        [$data, $documents] = $this->validatedRequest($request, $dispatch, false);
        return response()->json(['status' => 'success', 'message' => 'Borrador actualizado correctamente.',
            'data' => $this->service->updateDraft($customerReturn, $data, $documents)]);
    }

    public function confirm(CustomerReturn $customerReturn)
    {
        $this->ensureReturnAccess($customerReturn);
        return response()->json(['status' => 'success', 'message' => 'Devolución confirmada: stock y Kardex actualizados.',
            'data' => $this->service->confirm($customerReturn)]);
    }

    public function cancelDraft(Request $request, CustomerReturn $customerReturn)
    {
        $this->ensureReturnAccess($customerReturn);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        return response()->json(['status' => 'success', 'message' => 'Borrador cancelado sin modificar stock ni Kardex.',
            'data' => $this->service->cancelDraft($customerReturn, $data['reason'])]);
    }

    public function reverse(Request $request, CustomerReturn $customerReturn)
    {
        $this->ensureReturnAccess($customerReturn);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        return response()->json(['status' => 'success', 'message' => 'Devolución reversada mediante una nueva salida de Kardex.',
            'data' => $this->service->reverse($customerReturn, $data['reason'])]);
    }

    public function documents(CustomerReturn $customerReturn)
    {
        $this->ensureReturnAccess($customerReturn);
        return response()->json(['status' => 'success', 'data' => $this->documentsPayload($customerReturn)]);
    }

    public function storeDocuments(Request $request, CustomerReturn $customerReturn)
    {
        $this->ensureReturnAccess($customerReturn);
        $data = $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:'.CustomerReturnDocumentService::MAX_FILES_PER_REQUEST],
            'documents.*.type' => ['required', Rule::in($this->documentService->typeKeys())],
            'documents.*.description' => ['nullable', 'string', 'max:1000'],
            'documents.*.file' => ['required', 'file', 'mimes:'.CustomerReturnDocumentService::ALLOWED_EXTENSIONS, 'max:'.CustomerReturnDocumentService::MAX_FILE_SIZE_KB],
        ]);
        $this->documentService->storeMany($customerReturn, $data['documents'], Auth::id());
        return response()->json(['status' => 'success', 'message' => 'Documentos adjuntados correctamente.', 'data' => $this->documentsPayload($customerReturn)], 201);
    }

    public function showDocument(CustomerReturn $customerReturn, Document $document)
    {
        $this->ensureReturnAccess($customerReturn);
        $this->documentService->ensureBelongsToReturn($customerReturn, $document);
        abort_unless($document->status === 'ACTIVE' && $document->file_path && Storage::disk('public')->exists($document->file_path), 404);
        return Storage::disk('public')->response($document->file_path, $document->original_name ?: $document->stored_name, ['Content-Disposition' => 'inline']);
    }

    public function deleteDocument(CustomerReturn $customerReturn, Document $document)
    {
        $this->ensureReturnAccess($customerReturn);
        $this->documentService->remove($customerReturn, $document, Auth::id());
        return response()->json(['status' => 'success', 'message' => 'Documento retirado correctamente.', 'data' => $this->documentsPayload($customerReturn)]);
    }

    private function validatedRequest(Request $request, WarehouseDispatch $dispatch, bool $creating): array
    {
        $rules = [
            'return_date' => ['required', 'date'], 'reason' => ['required', Rule::in(array_keys(CustomerReturn::REASONS))],
            'reason_description' => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('reason') === 'other')],
            'observation' => ['nullable', 'string', 'max:3000'],
            'received_by_user_id' => ['required', 'integer', Rule::exists('company_user', 'user_id')->where(fn ($q) => $q->where('company_id', $dispatch->company_id))],
            'items' => ['required', 'array', 'min:1'], 'items.*.warehouse_dispatch_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
            'documents' => ['nullable', 'array', 'max:'.CustomerReturnDocumentService::MAX_FILES_PER_REQUEST],
            'documents.*.type' => ['required_with:documents.*.file', Rule::in($this->documentService->typeKeys())],
            'documents.*.description' => ['nullable', 'string', 'max:1000'],
            'documents.*.file' => ['required_with:documents.*.type', 'file', 'mimes:'.CustomerReturnDocumentService::ALLOWED_EXTENSIONS, 'max:'.CustomerReturnDocumentService::MAX_FILE_SIZE_KB],
        ];
        if ($creating) $rules['idempotency_key'] = ['required', 'uuid', 'max:100'];
        $validated = $request->validate($rules);
        $documents = $validated['documents'] ?? [];
        unset($validated['documents']);
        if ($documents && ! Auth::user()->can('devoluciones_clientes.documentos')) abort(403);
        return [$validated, $documents];
    }

    private function companyIds(): array { return Auth::user()->companies()->pluck('companies.id')->map(fn ($id) => (int) $id)->all(); }
    private function actionPermissions(CustomerReturn $return): array
    {
        $user = Auth::user();

        return [
            'view' => $user?->can('devoluciones_clientes.ver') ?? false,
            'edit' => $return->isDraft() && ($user?->can('devoluciones_clientes.editar') ?? false),
            'confirm' => $return->isDraft() && ($user?->can('devoluciones_clientes.confirmar') ?? false),
            'cancel' => $return->isDraft() && ($user?->can('devoluciones_clientes.cancelar') ?? false),
            'reverse' => $return->isConfirmed() && ($user?->can('devoluciones_clientes.reversar') ?? false),
            'documents' => $user?->can('devoluciones_clientes.documentos') ?? false,
        ];
    }
    private function ensureDispatchAccess(WarehouseDispatch $dispatch): void
    {
        abort_unless(Auth::user()?->belongsToCompany((int) $dispatch->company_id), 404);
        abort_unless((int) $dispatch->company_id === (int) $dispatch->customerPurchaseOrder()->value('company_id'), 404);
    }
    private function ensureReturnAccess(CustomerReturn $return): void { abort_unless(Auth::user()?->belongsToCompany((int) $return->company_id), 404); }
    private function customerName(CustomerReturn $return): string { return $return->customerPurchaseOrder?->customer?->business_name ?: $return->customerPurchaseOrder?->customer?->full_name ?: 'Cliente'; }
    private function invoiceSituation(CustomerReturn $return): string
    {
        $count = $this->service->invoiceContext($return->items()->pluck('warehouse_dispatch_item_id'))->unique('id')->count();
        return $count ? ($count === 1 ? '1 comprobante relacionado' : "$count comprobantes relacionados") : 'Sin comprobante relacionado';
    }
    private function documentsPayload(CustomerReturn $return): array
    {
        return ['return_id' => $return->id, 'types' => $this->documentService->types(),
            'documents' => $this->documentService->activeDocuments($return)->map(fn (Document $document) => [
                'id' => $document->id, 'type' => $this->documentService->typeKey($document),
                'type_label' => $this->documentService->typeLabel($document), 'original_name' => $document->original_name,
                'mime_type' => $document->mime_type, 'file_size' => $document->file_size,
                'description' => $document->observation, 'created_at' => $document->created_at?->toIso8601String(),
                'view_url' => route('admin.customer-returns.documents.show', [$return, $document]),
            ])->values(), 'count' => $this->documentService->activeDocuments($return)->count()];
    }
}
