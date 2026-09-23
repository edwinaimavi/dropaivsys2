<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerPurchaseOrder;
use App\Models\Document;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseDispatch;
use App\Models\WarehouseStock;
use App\Services\WarehouseDispatchDocumentService;
use App\Services\WarehouseDispatchService;
use App\Services\CustomerReturnService;
use App\Services\CompanyWarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WarehouseDispatchController extends Controller
{
    public function __construct(
        private readonly WarehouseDispatchService $service,
        private readonly WarehouseDispatchDocumentService $documentService,
        private readonly CustomerReturnService $customerReturnService,
        private readonly CompanyWarehouseService $companyWarehouseService,
        private readonly \App\Services\ArticleInventoryPolicy $articleInventoryPolicy
    ) {
        $this->middleware('can:admin.customer-purchase-orders.dispatch')
            ->only(['data', 'stocks', 'store', 'update', 'confirm', 'cancelDraft']);
        $this->middleware('can:admin.customer-purchase-orders.dispatch.reverse')->only(['reverse']);
        $this->middleware('can:admin.customer-purchase-orders.dispatch.documents.view')
            ->only(['documents', 'showDocument']);
        $this->middleware('can:admin.customer-purchase-orders.dispatch.documents.manage')
            ->only(['storeDocuments', 'deleteDocument']);
    }

    public function data(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $this->ensureOrderAccess($customerPurchaseOrder);
        $customerPurchaseOrder->load([
            'customer',
            'customerBranch',
            'items' => fn ($query) => $query->where('status', '!=', 'deleted'),
            'items.article',
            'items.unit',
            'items.presentation',
            'items.brand',
            'warehouseDispatches' => fn ($query) => $query
                ->withCount(['documents' => fn ($documents) => $documents->where('status', 'ACTIVE')])
                ->latest('dispatch_date')
                ->latest('id'),
            'warehouseDispatches.warehouse',
            'warehouseDispatches.responsibleUser',
            'warehouseDispatches.creator',
            'warehouseDispatches.confirmedBy',
            'warehouseDispatches.cancelledBy',
            'warehouseDispatches.items.customerPurchaseOrderItem.article',
            'warehouseDispatches.customerReturns' => fn ($query) => $query->withSum('items as total_quantity', 'quantity')->latest('return_date'),
        ]);
        $entered = $this->service->enteredQuantities($customerPurchaseOrder);
        $dispatched = $this->service->dispatchedQuantities($customerPurchaseOrder);

        $items = $customerPurchaseOrder->items
            ->filter(fn ($item) => $item->article
                && $this->articleInventoryPolicy->canParticipateInInventory($item->article))
            ->map(function ($item) use ($entered, $dispatched) {
            $requested = round((float) $item->quantity, 4);
            $enteredQuantity = round((float) $entered->get($item->id, 0), 4);
            $dispatchedQuantity = round((float) $dispatched->get($item->id, 0), 4);
            $pending = max(round($requested - $dispatchedQuantity, 4), 0);
            $available = max(min(round($enteredQuantity - $dispatchedQuantity, 4), $pending), 0);

            return [
                'id' => $item->id,
                'article_id' => $item->article_id,
                'article' => $item->article,
                'billing_name_snapshot' => $item->billing_name_snapshot,
                'unit' => $item->unit,
                'presentation' => $item->presentation,
                'brand' => $item->brand,
                'requested_quantity' => $requested,
                'entered_quantity' => $enteredQuantity,
                'dispatched_quantity' => $dispatchedQuantity,
                'pending_dispatch_quantity' => $pending,
                'available_dispatch_quantity' => $available,
                // Los saldos se consultan recién después de seleccionar un almacén.
                'stocks' => [],
            ];
        })->values();

        $customerPurchaseOrder->warehouseDispatches->each(function (WarehouseDispatch $dispatch) {
            $dispatch->setAttribute('document_url', $dispatch->document_path
                ? route('admin.customer-purchase-orders.dispatches.document', [$dispatch->customer_purchase_order_id, $dispatch])
                : null);
            $dispatch->setAttribute('can_reverse', $this->service->canReverse($dispatch));
            $gross = round((float) $dispatch->items->sum('quantity'), 4);
            $returnable = round((float) $this->customerReturnService->returnableQuantities($dispatch)->sum(), 4);
            $returned = round($gross - $returnable, 4);
            $dispatch->setAttribute('returned_quantity', $returned);
            $dispatch->setAttribute('net_delivered_quantity', max(round($gross - $returned, 4), 0));
            $dispatch->setAttribute('returnable_quantity', $returnable);
            $dispatch->setAttribute('can_return', $dispatch->isConfirmed()
                && $returnable > 0
                && Auth::user()?->can('devoluciones_clientes.crear'));
            $dispatch->customerReturns->each(function ($return) {
                $return->setAttribute('can_view', Auth::user()?->can('devoluciones_clientes.ver') ?? false);
                $return->setAttribute('can_edit', $return->isDraft() && (Auth::user()?->can('devoluciones_clientes.editar') ?? false));
                $return->setAttribute('can_confirm', $return->isDraft() && (Auth::user()?->can('devoluciones_clientes.confirmar') ?? false));
                $return->setAttribute('can_cancel', $return->isDraft() && (Auth::user()?->can('devoluciones_clientes.cancelar') ?? false));
                $return->setAttribute('can_reverse', $return->isConfirmed() && (Auth::user()?->can('devoluciones_clientes.reversar') ?? false));
                $return->setAttribute('can_documents', Auth::user()?->can('devoluciones_clientes.documentos') ?? false);
            });
        });
        $editingDispatch = null;
        if ($request->filled('dispatch_id')) {
            $editingDispatch = $customerPurchaseOrder->warehouseDispatches()
                ->with(['items.stock', 'items.customerPurchaseOrderItem.article'])
                ->withCount(['documents' => fn ($documents) => $documents->where('status', 'ACTIVE')])
                ->whereKey((int) $request->input('dispatch_id'))
                ->where('status', WarehouseDispatch::STATUS_DRAFT)
                ->firstOrFail();
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order' => $customerPurchaseOrder,
                'items' => $items,
                'dispatches' => $customerPurchaseOrder->warehouseDispatches,
                'warehouses' => Warehouse::query()
                    ->where('status', 'ACTIVE')
                    ->whereHas('companyWarehouses', fn ($query) => $query
                        ->where('company_id', $customerPurchaseOrder->company_id)
                        ->where('is_active', true))
                    ->orderBy('name')
                    ->get(['id', 'code', 'name']),
                'responsibles' => User::query()->where('status', 1)->orderBy('name')->orderBy('lastname')->get(['id', 'name', 'lastname']),
                'current_user_id' => Auth::id(),
                'idempotency_key' => (string) Str::uuid(),
                'dispatch_document_types' => $this->documentService->types(),
                'editing_dispatch' => $editingDispatch,
                'can_dispatch' => $items->contains(fn ($item) => $item['available_dispatch_quantity'] > 0),
            ],
        ]);
    }

    public function stocks(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $this->ensureOrderAccess($customerPurchaseOrder);
        $validated = $request->validate([
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'dispatch_id' => ['nullable', 'integer', 'exists:warehouse_dispatches,id'],
        ], [
            'warehouse_id.required' => 'Seleccione un almacén para consultar saldos disponibles.',
        ]);

        $customerPurchaseOrder->load([
            'items' => fn ($query) => $query->where('status', '!=', 'deleted'),
            'items.article',
        ]);
        $this->companyWarehouseService->assertEnabled(
            (int) $customerPurchaseOrder->company_id,
            (int) $validated['warehouse_id']
        );
        $entered = $this->service->enteredQuantities($customerPurchaseOrder);
        $dispatched = $this->service->dispatchedQuantities($customerPurchaseOrder);
        $dispatchableItems = $customerPurchaseOrder->items->filter(function ($item) use ($entered, $dispatched) {
            if (! $item->article || ! $this->articleInventoryPolicy->canParticipateInInventory($item->article)) {
                return false;
            }
            $requested = round((float) $item->quantity, 4);
            $dispatchedQuantity = round((float) $dispatched->get($item->id, 0), 4);
            $enteredQuantity = round((float) $entered->get($item->id, 0), 4);
            $pending = max(round($requested - $dispatchedQuantity, 4), 0);
            $available = max(min(round($enteredQuantity - $dispatchedQuantity, 4), $pending), 0);

            return $available > 0;
        });
        $editingDispatch = null;
        $plannedStockIds = collect();
        if (! empty($validated['dispatch_id'])) {
            $editingDispatch = $customerPurchaseOrder->warehouseDispatches()
                ->with('items')
                ->whereKey($validated['dispatch_id'])
                ->where('status', WarehouseDispatch::STATUS_DRAFT)
                ->firstOrFail();
            if ((int) $editingDispatch->warehouse_id === (int) $validated['warehouse_id']) {
                $plannedStockIds = $editingDispatch->items->pluck('warehouse_stock_id');
            }
        }

        $stocks = WarehouseStock::query()
            ->where('company_id', $customerPurchaseOrder->company_id)
            ->where('warehouse_id', $validated['warehouse_id'])
            ->whereIn('article_id', $dispatchableItems->pluck('article_id')->filter()->unique())
            ->where(function ($query) use ($plannedStockIds) {
                $query->where(function ($available) {
                    $available->where('status', 'ACTIVE')->where('current_quantity', '>', 0);
                });
                if ($plannedStockIds->isNotEmpty()) {
                    $query->orWhereIn('id', $plannedStockIds);
                }
            })
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'warehouse_id' => (int) $validated['warehouse_id'],
                'items' => $dispatchableItems->map(fn ($item) => [
                    'customer_purchase_order_item_id' => $item->id,
                    'stocks' => $stocks->where('article_id', $item->article_id)->map(fn ($stock) => [
                        'id' => $stock->id,
                        'warehouse_id' => $stock->warehouse_id,
                        'lot_number' => $stock->lot_number,
                        'expiration_date' => $stock->expiration_date?->format('Y-m-d'),
                        'current_quantity' => (float) $stock->current_quantity,
                        'status' => $stock->status,
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    public function store(Request $request, CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $this->ensureOrderAccess($customerPurchaseOrder);
        [$validated, $documents] = $this->validatedDraftRequest($request, true);
        $dispatch = $this->service->createDraft($customerPurchaseOrder, $validated, $documents)
            ->loadCount(['documents' => fn ($query) => $query->where('status', 'ACTIVE')]);

        return response()->json([
            'status' => 'success',
            'message' => 'Borrador de salida guardado correctamente. Aún no se descontó stock ni se generó Kardex.',
            'data' => $dispatch,
        ], 201);
    }

    public function update(
        Request $request,
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseDispatch $dispatch
    ) {
        $this->ensureDispatchBelongsToOrder($customerPurchaseOrder, $dispatch);
        [$validated, $documents] = $this->validatedDraftRequest($request, false);
        $dispatch = $this->service->updateDraft($dispatch, $validated, $documents)
            ->loadCount(['documents' => fn ($query) => $query->where('status', 'ACTIVE')]);

        return response()->json([
            'status' => 'success',
            'message' => 'Borrador de salida actualizado correctamente.',
            'data' => $dispatch,
        ]);
    }

    public function confirm(CustomerPurchaseOrder $customerPurchaseOrder, WarehouseDispatch $dispatch)
    {
        $this->ensureDispatchBelongsToOrder($customerPurchaseOrder, $dispatch);
        $dispatch = $this->service->confirm($dispatch)
            ->loadCount(['documents' => fn ($query) => $query->where('status', 'ACTIVE')]);

        return response()->json([
            'status' => 'success',
            'message' => 'Salida confirmada correctamente. El stock y Kardex fueron actualizados una sola vez.',
            'data' => $dispatch,
        ]);
    }

    public function cancelDraft(
        Request $request,
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseDispatch $dispatch
    ) {
        $this->ensureDispatchBelongsToOrder($customerPurchaseOrder, $dispatch);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $dispatch = $this->service->cancelDraft($dispatch, $validated['reason']);

        return response()->json([
            'status' => 'success',
            'message' => 'Borrador cancelado sin modificar stock ni Kardex.',
            'data' => $dispatch,
        ]);
    }

    public function reverse(Request $request, CustomerPurchaseOrder $customerPurchaseOrder, WarehouseDispatch $dispatch)
    {
        abort_unless((int) $dispatch->customer_purchase_order_id === (int) $customerPurchaseOrder->id, 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $dispatch = $this->service->reverse($dispatch, $validated['reason']);

        return response()->json([
            'status' => 'success',
            'message' => 'Salida anulada y stock restaurado correctamente.',
            'data' => $dispatch,
        ]);
    }

    public function document(CustomerPurchaseOrder $customerPurchaseOrder, WarehouseDispatch $dispatch)
    {
        abort_unless(
            Auth::user()?->can('admin.customer-purchase-orders.show')
                || Auth::user()?->can('admin.customer-purchase-orders.dispatch.documents.view'),
            403
        );
        $this->ensureDispatchBelongsToOrder($customerPurchaseOrder, $dispatch);
        abort_unless($dispatch->document_path && Storage::disk('public')->exists($dispatch->document_path), 404);

        return Storage::disk('public')->response(
            $dispatch->document_path,
            $dispatch->document_name ?: basename($dispatch->document_path),
            ['Content-Disposition' => 'inline; filename="'.($dispatch->document_name ?: basename($dispatch->document_path)).'"']
        );
    }

    public function documents(CustomerPurchaseOrder $customerPurchaseOrder, WarehouseDispatch $dispatch)
    {
        $this->ensureDispatchBelongsToOrder($customerPurchaseOrder, $dispatch);

        return response()->json([
            'status' => 'success',
            'data' => $this->documentsPayload($customerPurchaseOrder, $dispatch),
        ]);
    }

    public function storeDocuments(
        Request $request,
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseDispatch $dispatch
    ) {
        $this->ensureDispatchBelongsToOrder($customerPurchaseOrder, $dispatch);
        $validated = $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:'.WarehouseDispatchDocumentService::MAX_FILES_PER_REQUEST],
            'documents.*.type' => ['required', 'string', Rule::in($this->documentService->typeKeys())],
            'documents.*.description' => ['nullable', 'string', 'max:1000'],
            'documents.*.file' => ['required', 'file', 'mimes:'.WarehouseDispatchDocumentService::ALLOWED_EXTENSIONS, 'max:'.WarehouseDispatchDocumentService::MAX_FILE_SIZE_KB],
        ]);

        $this->documentService->storeMany($dispatch, $validated['documents'], Auth::id());

        return response()->json([
            'status' => 'success',
            'message' => 'Documentos del despacho adjuntados correctamente.',
            'data' => $this->documentsPayload($customerPurchaseOrder, $dispatch),
        ], 201);
    }

    public function showDocument(
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseDispatch $dispatch,
        Document $document
    ) {
        $this->ensureDispatchBelongsToOrder($customerPurchaseOrder, $dispatch);
        $this->documentService->ensureBelongsToDispatch($dispatch, $document);
        abort_unless(
            $document->status === 'ACTIVE'
                && $document->file_path
                && Storage::disk('public')->exists($document->file_path),
            404
        );

        return Storage::disk('public')->response(
            $document->file_path,
            $document->original_name ?: $document->stored_name,
            ['Content-Disposition' => 'inline']
        );
    }

    public function deleteDocument(
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseDispatch $dispatch,
        Document $document
    ) {
        $this->ensureDispatchBelongsToOrder($customerPurchaseOrder, $dispatch);
        $this->documentService->remove($dispatch, $document, Auth::id());

        return response()->json([
            'status' => 'success',
            'message' => 'Documento retirado del despacho correctamente.',
            'data' => $this->documentsPayload($customerPurchaseOrder, $dispatch),
        ]);
    }

    private function validatedDraftRequest(Request $request, bool $creating): array
    {
        $rules = [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'dispatch_date' => ['required', 'date'],
            'responsible_user_id' => ['required', 'integer', 'exists:users,id'],
            'destination' => ['nullable', 'string', 'max:255'],
            'observation' => ['nullable', 'string', 'max:3000'],
            'document_type' => ['nullable', 'string', 'max:60'],
            'document_number' => ['nullable', 'string', 'max:80'],
            'document' => ['nullable', 'file', 'mimes:'.WarehouseDispatchDocumentService::ALLOWED_EXTENSIONS, 'max:'.WarehouseDispatchDocumentService::MAX_FILE_SIZE_KB],
            'documents' => ['nullable', 'array', 'max:'.WarehouseDispatchDocumentService::MAX_FILES_PER_REQUEST],
            'documents.*.type' => ['required_with:documents.*.file', 'string', Rule::in($this->documentService->typeKeys())],
            'documents.*.description' => ['nullable', 'string', 'max:1000'],
            'documents.*.file' => ['required_with:documents.*.type', 'file', 'mimes:'.WarehouseDispatchDocumentService::ALLOWED_EXTENSIONS, 'max:'.WarehouseDispatchDocumentService::MAX_FILE_SIZE_KB],
            'items' => ['required', 'array', 'min:1'],
            'items.*.customer_purchase_order_item_id' => ['required', 'integer', 'exists:customer_purchase_order_items,id'],
            'items.*.warehouse_stock_id' => ['required', 'integer', 'exists:warehouse_stocks,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,4', 'gt:0'],
        ];
        if ($creating) {
            $rules['idempotency_key'] = ['required', 'uuid', 'max:100'];
        }
        $validated = $request->validate($rules, [
            'warehouse_id.required' => 'Seleccione un almacén para registrar la salida.',
            'responsible_user_id.required' => 'Seleccione el responsable de salida.',
            'items.required' => 'Registre al menos un artículo para el borrador.',
        ]);

        $documents = $validated['documents'] ?? [];
        if ($request->hasFile('document')) {
            $documents[] = [
                'type' => 'other',
                'description' => 'Documento adjunto desde el campo compatible de salida',
                'file' => $request->file('document'),
            ];
        }
        unset($validated['documents'], $validated['document']);

        if ($documents && ! $request->user()?->can('admin.customer-purchase-orders.dispatch.documents.manage')) {
            abort(403);
        }

        return [$validated, $documents];
    }

    private function ensureDispatchBelongsToOrder(
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseDispatch $dispatch
    ): void {
        abort_unless(
            (int) $dispatch->customer_purchase_order_id === (int) $customerPurchaseOrder->id,
            404
        );
        $this->ensureOrderAccess($customerPurchaseOrder);
    }

    private function ensureOrderAccess(CustomerPurchaseOrder $customerPurchaseOrder): void
    {
        abort_unless(Auth::user()?->belongsToCompany((int) $customerPurchaseOrder->company_id), 404);
    }

    private function documentsPayload(
        CustomerPurchaseOrder $customerPurchaseOrder,
        WarehouseDispatch $dispatch
    ): array {
        $documents = $this->documentService->activeDocuments($dispatch)
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'type' => $this->documentService->typeKey($document),
                'type_label' => $this->documentService->typeLabel($document),
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'description' => $document->observation,
                'created_at' => $document->created_at?->toIso8601String(),
                'creator_name' => $document->creator
                    ? trim($document->creator->name.' '.$document->creator->lastname)
                    : 'No registrado',
                'view_url' => route('admin.customer-purchase-orders.dispatches.documents.show', [
                    $customerPurchaseOrder,
                    $dispatch,
                    $document,
                ]),
                'is_legacy' => false,
            ])->values();
        $legacyExists = $dispatch->document_path
            && Storage::disk('public')->exists($dispatch->document_path);

        return [
            'dispatch_id' => $dispatch->id,
            'dispatch_number' => $dispatch->dispatch_number,
            'types' => $this->documentService->types(),
            'documents' => $documents,
            'legacy_document' => $legacyExists ? [
                'original_name' => $dispatch->document_name ?: basename($dispatch->document_path),
                'mime_type' => $dispatch->document_mime,
                'view_url' => route('admin.customer-purchase-orders.dispatches.document', [
                    $customerPurchaseOrder,
                    $dispatch,
                ]),
                'is_legacy' => true,
            ] : null,
            'count' => $documents->count() + ($legacyExists ? 1 : 0),
            'can_manage' => Auth::user()?->can('admin.customer-purchase-orders.dispatch.documents.manage') ?? false,
        ];
    }
}
