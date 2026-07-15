<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPurchaseOrder;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Presentation;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Services\WarehouseKardexService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class WarehouseEntryController extends Controller
{
    private const STATUS_REGISTERED = 'registered';
    private const STATUS_CANCELLED = 'cancelled';
    private const PDF_OBSERVATION = 'PDF_GENERATED_WAREHOUSE_ENTRY';

    public function __construct()
    {
        $this->middleware('can:admin.warehouse-entries.index')->only(['index', 'list', 'generateNumber']);
        $this->middleware('can:admin.warehouse-entries.load-items')->only(['loadSupplierPurchaseOrderItems']);
        $this->middleware('can:admin.warehouse-entries.store')->only(['store']);
        $this->middleware('can:admin.warehouse-entries.update')->only(['update', 'destroyDocument']);
        $this->middleware('can:admin.warehouse-entries.destroy')->only(['destroy']);
        $this->middleware('can:admin.warehouse-entries.show')->only(['show', 'downloadDocument']);
        $this->middleware('can:admin.warehouse-entries.pdf')->only(['pdf']);
    }

    public function index()
    {
        $supplierPurchaseOrders = SupplierPurchaseOrder::query()
            ->with('supplier:id,business_name,short_name,ruc', 'company:id,business_name,trade_name')
            ->orderByDesc('id')
            ->get();

        $companies = Company::query()->where('status', true)->orderBy('business_name')->get();
        $suppliers = Supplier::query()->where('status', 'ACTIVE')->orderBy('business_name')->get();
        $customers = Customer::query()->orderBy('business_name')->orderBy('full_name')->get();
        $currencies = Currency::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $articles = Article::query()
            ->where('status', 'ACTIVE')
            ->orderBy('billing_name')
            ->get(['id', 'code', 'billing_name', 'unit_id', 'presentation_id', 'brand_id']);
        $units = Unit::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $presentations = Presentation::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $brands = Brand::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description']);

        return view('admin.warehouse-entries.index', compact(
            'supplierPurchaseOrders',
            'companies',
            'suppliers',
            'customers',
            'currencies',
            'articles',
            'units',
            'presentations',
            'brands',
            'warehouses'
        ));
    }

    public function list()
    {
        $entries = WarehouseEntry::query()
            ->with([
                'supplier:id,business_name,short_name',
                'company:id,business_name,trade_name',
                'currency:id,code,symbol,description',
                'supplierPurchaseOrder:id,code',
                'warehouse:id,code,name,description',
                'documents' => function ($query) {
                    $query->where('observation', self::PDF_OBSERVATION)
                        ->where('status', 'ACTIVE')
                        ->where('mime_type', 'application/pdf')
                        ->latest('id');
                },
            ])
            ->orderByDesc('id');

        return DataTables::of($entries)
            ->addIndexColumn()
            ->editColumn('supplier_purchase_order_id', fn (WarehouseEntry $entry) =>
                $entry->supplierPurchaseOrder?->code ?? $entry->purchase_order_number ?? '-')
            ->addColumn('supplier', fn (WarehouseEntry $entry) =>
                $entry->supplier?->short_name ?? $entry->supplier?->business_name ?? '-')
            ->addColumn('company', fn (WarehouseEntry $entry) =>
                $entry->company?->trade_name ?? $entry->company?->business_name ?? '-')
            ->addColumn('warehouse', fn (WarehouseEntry $entry) =>
                $entry->warehouse?->name ?? 'SIN ALMACEN')
            ->addColumn('currency', fn (WarehouseEntry $entry) =>
                $entry->currency?->code ?? $entry->currency?->description ?? '-')
            ->editColumn('grand_total', function (WarehouseEntry $entry) {
                $symbol = $entry->currency?->symbol ?? '';

                return trim($symbol . ' ' . number_format((float) $entry->grand_total, 2));
            })
            ->editColumn('status', function (WarehouseEntry $entry) {
                $status = $this->statusPresentation()[$entry->status] ?? [
                    'label' => ucfirst((string) $entry->status),
                    'class' => 'badge-light text-dark border',
                    'icon' => 'fas fa-info-circle',
                ];

                return sprintf(
                    '<span class="badge %s rounded-pill px-3 py-2 shadow-sm font-weight-bold">
                        <i class="%s mr-1"></i>%s
                    </span>',
                    $status['class'],
                    $status['icon'],
                    e($status['label'])
                );
            })
            ->editColumn('created_at', fn (WarehouseEntry $entry) =>
                $entry->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '-')
            ->addColumn('acciones', function (WarehouseEntry $entry) {
                $pdfUrl = route('admin.warehouse-entries.pdf', $entry);

                return view('admin.warehouse-entries.partials.acciones', compact('entry', 'pdfUrl'))->render();
            })
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function store(Request $request)
    {
        return $this->saveEntry($request);
    }

    public function show(WarehouseEntry $warehouseEntry)
    {
        $warehouseEntry->load([
            'supplierPurchaseOrder',
            'company',
            'supplier',
            'customer',
            'currency',
            'warehouse',
            'items.article',
            'items.supplierPurchaseOrderItem',
            'items.unit',
            'items.presentation',
            'items.brand',
            'documents' => function ($query) {
                $query->where(function ($innerQuery) {
                    $innerQuery->whereNull('observation')
                        ->orWhere('observation', '!=', self::PDF_OBSERVATION);
                })->with('documentType');
            },
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $warehouseEntry,
            'warehouse_name' => $warehouseEntry->warehouse?->name ?? 'SIN ALMACEN',
        ]);
    }

    public function edit(WarehouseEntry $warehouseEntry)
    {
        return $this->show($warehouseEntry);
    }

    public function update(Request $request, WarehouseEntry $warehouseEntry)
    {
        return $this->saveEntry($request, $warehouseEntry);
    }

    public function pdf(WarehouseEntry $warehouseEntry)
    {
        $document = $this->generatedPdfDocument($warehouseEntry);

        if (! $document) {
            $pdfData = $this->generateWarehouseEntryPdf($this->warehouseEntryForPdf($warehouseEntry));
            $document = $pdfData['document'];
        }

        if (! $document->file_path || ! Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($document->file_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
        ]);
    }

    public function downloadDocument(WarehouseEntry $warehouseEntry, Document $document)
    {
        $this->ensureEntryDocument($warehouseEntry, $document);

        if (! $document->file_path || ! Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('public')->download(
            $document->file_path,
            $document->original_name ?: basename($document->file_path)
        );
    }

    public function destroyDocument(WarehouseEntry $warehouseEntry, Document $document)
    {
        $this->ensureEntryDocument($warehouseEntry, $document);

        if ($document->file_path && Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->update([
            'deleted_by' => Auth::id(),
        ]);
        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Documento eliminado correctamente.',
        ]);
    }

    public function destroy(WarehouseEntry $warehouseEntry, WarehouseKardexService $kardexService)
    {
        try {
            DB::transaction(function () use ($warehouseEntry, $kardexService) {
                $supplierPurchaseOrderId = $warehouseEntry->supplier_purchase_order_id;
                $customerPurchaseOrderIds = $this->customerPurchaseOrderIdsForWarehouseEntry($warehouseEntry);

                $kardexService->reverseWarehouseEntry($warehouseEntry, 'Ingreso de almacen anulado');

                $warehouseEntry->update([
                    'status' => self::STATUS_CANCELLED,
                    'updated_by' => Auth::id(),
                ]);
                $warehouseEntry->delete();

                $this->refreshSupplierPurchaseOrderStatus($supplierPurchaseOrderId);
                $this->refreshCustomerPurchaseOrderStatuses($customerPurchaseOrderIds);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Ingreso de almacen eliminado correctamente.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error deleting warehouse entry: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo eliminar el ingreso de almacen.',
            ], 500);
        }
    }

    public function generateNumber()
    {
        return response()->json(['entry_number' => $this->nextEntryNumber()]);
    }

    public function loadSupplierPurchaseOrderItems(Request $request)
    {
        $validated = $request->validate([
            'supplier_purchase_order_id' => ['required', 'exists:supplier_purchase_orders,id'],
            'warehouse_entry_id' => ['nullable', 'exists:warehouse_entries,id'],
        ]);

        $order = SupplierPurchaseOrder::query()
            ->with([
                'company:id,business_name,trade_name',
                'supplier:id,business_name,short_name,ruc',
                'currency:id,code,symbol,description',
                'customerPurchaseOrders.customer:id,business_name,full_name,first_name,last_name',
                'items.article',
                'items.unit',
                'items.presentation',
                'items.brand',
            ])
            ->findOrFail($validated['supplier_purchase_order_id']);

        $entryId = $validated['warehouse_entry_id'] ?? null;
        $receivedByItem = $this->receivedQuantitiesForOrder($order, $entryId);

        $items = $order->items
            ->reject(fn (SupplierPurchaseOrderItem $item) => strtolower((string) $item->status) === 'deleted')
            ->map(function (SupplierPurchaseOrderItem $item) use ($receivedByItem) {
                $orderedQuantity = round((float) $item->quantity, 2);
                $receivedQuantity = round((float) ($receivedByItem[$item->id] ?? 0), 2);
                $pendingQuantity = max(round($orderedQuantity - $receivedQuantity, 2), 0);

                return $this->sourceItemPayload($item, $orderedQuantity, $pendingQuantity);
            })
            ->filter(fn (array $item) => (float) $item['quantity'] > 0)
            ->values();

        $customer = $order->customerPurchaseOrders
            ->pluck('customer')
            ->filter()
            ->first();

        return response()->json([
            'supplier_purchase_order_id' => $order->id,
            'company_id' => $order->company_id,
            'company_name' => $order->company?->trade_name ?? $order->company?->business_name,
            'supplier_id' => $order->supplier_id,
            'supplier_name' => $order->supplier?->short_name ?? $order->supplier?->business_name,
            'supplier_ruc' => $order->supplier?->ruc,
            'customer_id' => $customer?->id,
            'currency_id' => $order->currency_id,
            'currency_name' => trim(($order->currency?->code ?? '') . ' - ' . ($order->currency?->description ?? '')),
            'purchase_order_number' => $order->code,
            'payment_method' => $order->payment_method,
            'payment_condition' => $order->payment_condition,
            'affect_igv' => (bool) $order->affect_igv,
            'items' => $items,
        ]);
    }

    private function saveEntry(Request $request, ?WarehouseEntry $entry = null)
    {
        $generatedPdfPath = null;
        $generatedPdfUrl = null;
        $generatedDocumentId = null;
        $pdfError = null;

        $request->merge([
            'document_type' => $this->normalizeDocumentType($request->input('document_type')),
        ]);
        $hasSupplierPurchaseOrder = $request->filled('supplier_purchase_order_id');

        $validated = $request->validate([
            'supplier_purchase_order_id' => ['nullable', 'exists:supplier_purchase_orders,id'],
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where('status', 'ACTIVE'),
            ],
            'company_id' => [Rule::requiredIf(! $hasSupplierPurchaseOrder), 'nullable', 'exists:companies,id'],
            'supplier_id' => [Rule::requiredIf(! $hasSupplierPurchaseOrder), 'nullable', 'exists:suppliers,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'currency_id' => [Rule::requiredIf(! $hasSupplierPurchaseOrder), 'nullable', 'exists:currencies,id'],
            'purchase_order_number' => ['nullable', 'string', 'max:50'],
            'document_type' => ['required', 'string', Rule::in(['FACTURA', 'BOLETA'])],
            'document_series' => ['nullable', 'string', 'max:20'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'document_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'payment_condition' => ['nullable', 'string', 'max:100'],
            'generate_account_payable' => ['nullable', 'boolean'],
            'payable_amount' => ['nullable', 'numeric', 'min:0'],
            'expected_payment_date' => [
                Rule::requiredIf((bool) $request->boolean('generate_account_payable')),
                'nullable',
                'date',
            ],
            'seller_name' => ['nullable', 'string', 'max:255'],
            'affect_igv' => ['nullable', 'boolean'],
            'guide_series' => ['nullable', 'string', 'max:20'],
            'guide_number' => ['nullable', 'string', 'max:50'],
            'guide_ruc' => ['nullable', 'string', 'max:20'],
            'observations' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in($this->statusValues())],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_purchase_order_item_id' => ['nullable', 'exists:supplier_purchase_order_items,id'],
            'items.*.article_id' => ['required', 'exists:articles,id'],
            'items.*.article_code' => ['nullable', 'string', 'max:255'],
            'items.*.billing_name_snapshot' => ['required', 'string', 'max:255'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
            'items.*.presentation_id' => ['nullable', 'exists:presentations,id'],
            'items.*.brand_id' => ['nullable', 'exists:brands,id'],
            'items.*.origin' => ['nullable', 'string', 'max:100'],
            'items.*.cost_type' => ['nullable', 'string', 'max:100'],
            'items.*.expiration_date' => ['nullable', 'date'],
            'items.*.lot_number' => ['nullable', 'string', 'max:100'],
            'items.*.ordered_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'warehouse_entry_documents' => ['nullable', 'array'],
            'warehouse_entry_documents.*.type' => ['required_with:warehouse_entry_documents.*.file', 'string', Rule::in(array_keys($this->warehouseEntryDocumentTypes()))],
            'warehouse_entry_documents.*.description' => ['nullable', 'string', 'max:255'],
            'warehouse_entry_documents.*.file' => ['required_with:warehouse_entry_documents.*.type', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx', 'max:10240'],
        ]);

        try {
            return DB::transaction(function () use (
                $request,
                $validated,
                $entry,
                &$generatedPdfPath,
                &$generatedPdfUrl,
                &$generatedDocumentId,
                &$pdfError
            ) {
                $previousSupplierPurchaseOrderId = $entry?->supplier_purchase_order_id;
                $previousCustomerPurchaseOrderIds = $entry
                    ? $this->customerPurchaseOrderIdsForWarehouseEntry($entry)
                    : collect();
                $supplierPurchaseOrder = ! empty($validated['supplier_purchase_order_id'])
                    ? SupplierPurchaseOrder::query()
                        ->with('supplier:id,ruc')
                        ->findOrFail($validated['supplier_purchase_order_id'])
                    : null;
                $supplier = $supplierPurchaseOrder?->supplier
                    ?? Supplier::query()->find($validated['supplier_id'] ?? null);
                $affectIgv = $supplierPurchaseOrder
                    ? (bool) $supplierPurchaseOrder->affect_igv
                    : (bool) ($validated['affect_igv'] ?? false);
                $this->validatePendingQuantities($validated['items'], $entry?->id);
                $preparedItems = $this->prepareItems($validated['items'], $affectIgv);
                $totals = $this->calculateTotals($preparedItems);
                $generateAccountPayable = (bool) ($validated['generate_account_payable'] ?? false);
                $guideRuc = $this->upperOrNull($validated['guide_ruc'] ?? null)
                    ?? $supplier?->ruc;

                $entryData = [
                    'supplier_purchase_order_id' => $supplierPurchaseOrder?->id,
                    'warehouse_id' => $validated['warehouse_id'] ?? null,
                    'company_id' => $supplierPurchaseOrder?->company_id ?? $validated['company_id'],
                    'supplier_id' => $supplierPurchaseOrder?->supplier_id ?? $validated['supplier_id'],
                    'customer_id' => $validated['customer_id'] ?? null,
                    'currency_id' => $supplierPurchaseOrder?->currency_id ?? $validated['currency_id'],
                    'purchase_order_number' => $this->upperOrNull($supplierPurchaseOrder?->code ?? ($validated['purchase_order_number'] ?? null)),
                    'document_type' => $validated['document_type'] ?? 'FACTURA',
                    'document_series' => $this->upperOrNull($validated['document_series'] ?? null),
                    'document_number' => $this->upperOrNull($validated['document_number'] ?? null),
                    'document_date' => $validated['document_date'] ?? null,
                    'payment_method' => $supplierPurchaseOrder?->payment_method ?? ($validated['payment_method'] ?? null),
                    'payment_condition' => $supplierPurchaseOrder?->payment_condition ?? ($validated['payment_condition'] ?? null),
                    'generate_account_payable' => $generateAccountPayable,
                    'payable_amount' => $totals['grand_total'],
                    'expected_payment_date' => $generateAccountPayable
                        ? ($validated['expected_payment_date'] ?? null)
                        : null,
                    'seller_name' => $this->upperOrNull($validated['seller_name'] ?? null),
                    'affect_igv' => $affectIgv,
                    'guide_series' => $this->upperOrNull($validated['guide_series'] ?? null),
                    'guide_number' => $this->upperOrNull($validated['guide_number'] ?? null),
                    'guide_ruc' => $guideRuc,
                    'observations' => $this->upperOrNull($validated['observations'] ?? null),
                    'subtotal' => $totals['subtotal'],
                    'igv' => $totals['igv'],
                    'grand_total' => $totals['grand_total'],
                    'status' => $entry
                        ? ($validated['status'] ?? $entry->status)
                        : self::STATUS_REGISTERED,
                    'updated_by' => Auth::id(),
                ];

                $isUpdate = (bool) $entry;

                if ($entry) {
                    $entry->update($entryData);
                    $entry->items()->delete();
                } else {
                    $entryData['entry_number'] = $this->nextEntryNumber();
                    $entryData['created_by'] = Auth::id();
                    $entry = WarehouseEntry::create($entryData);
                }

                foreach ($preparedItems as $item) {
                    $entry->items()->create($item);
                }

                $currentCustomerPurchaseOrderIds = $this->customerPurchaseOrderIdsForWarehouseEntry($entry);

                $this->storeEntryDocuments($entry, $request->input('warehouse_entry_documents', []), $request->file('warehouse_entry_documents', []));

                $freshEntry = $entry->fresh([
                    'supplier',
                    'currency',
                    'items.article',
                    'items.unit',
                    'items.presentation',
                    'items.brand',
                ]);

                if ($isUpdate) {
                    app(WarehouseKardexService::class)->rebuildEntryMovements($freshEntry);
                } else {
                    app(WarehouseKardexService::class)->registerEntryFromWarehouseEntry($freshEntry);
                }

                collect([
                    $previousSupplierPurchaseOrderId,
                    $entry->supplier_purchase_order_id,
                ])
                    ->filter()
                    ->unique()
                    ->each(fn ($supplierPurchaseOrderId) => $this->refreshSupplierPurchaseOrderStatus((int) $supplierPurchaseOrderId));

                $this->refreshCustomerPurchaseOrderStatuses(
                    $previousCustomerPurchaseOrderIds
                        ->merge($currentCustomerPurchaseOrderIds)
                        ->unique()
                        ->values()
                );

                try {
                    $pdfData = $this->generateWarehouseEntryPdf($this->warehouseEntryForPdf($entry));
                    $generatedPdfPath = $pdfData['path'];
                    $generatedPdfUrl = route('admin.warehouse-entries.pdf', $entry);
                    $generatedDocumentId = $pdfData['document']->id;
                } catch (\Throwable $pdfException) {
                    $pdfError = 'El ingreso se guardo, pero no se pudo generar el PDF.';

                    Log::error('Error generating warehouse entry PDF: ' . $pdfException->getMessage());
                }

                return response()->json([
                    'status' => 'success',
                    'message' => $entry->wasRecentlyCreated
                        ? 'Ingreso de almacen registrado correctamente.'
                        : 'Ingreso de almacen actualizado correctamente.',
                    'data' => $entry->fresh(['items']),
                    'pdf_path' => $generatedPdfPath,
                    'pdf_url' => $generatedPdfUrl,
                    'document_id' => $generatedDocumentId,
                    'pdf_error' => $pdfError,
                ], $entry->wasRecentlyCreated ? 201 : 200);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($generatedPdfPath && Storage::disk('public')->exists($generatedPdfPath)) {
                Storage::disk('public')->delete($generatedPdfPath);
            }

            Log::error('Error saving warehouse entry: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar el ingreso de almacen.',
            ], 500);
        }
    }

    private function prepareItems(array $items, bool $affectIgv): array
    {
        return collect($items)->map(function (array $item) use ($affectIgv) {
            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['unit_price'], 2);
            $subtotal = round($quantity * $unitPrice, 2);
            $taxAmount = $affectIgv ? round($subtotal * 0.18, 2) : 0;

            return [
                'supplier_purchase_order_item_id' => $item['supplier_purchase_order_item_id'] ?? null,
                'article_id' => $item['article_id'],
                'article_code' => $this->upperOrNull($item['article_code'] ?? null),
                'billing_name_snapshot' => $this->upperOrNull($item['billing_name_snapshot'] ?? ''),
                'note' => $this->upperOrNull($item['note'] ?? null),
                'unit_id' => $item['unit_id'] ?? null,
                'presentation_id' => $item['presentation_id'] ?? null,
                'brand_id' => $item['brand_id'] ?? null,
                'origin' => $this->upperOrNull($item['origin'] ?? null),
                'cost_type' => $item['cost_type'] ?? null,
                'expiration_date' => $item['expiration_date'] ?? null,
                'lot_number' => $this->upperOrNull($item['lot_number'] ?? null),
                'ordered_quantity' => round((float) ($item['ordered_quantity'] ?? 0), 2),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'line_total' => round($subtotal + $taxAmount, 2),
                'status' => 'active',
            ];
        })->all();
    }

    private function validatePendingQuantities(array $items, ?int $entryId = null): void
    {
        $orderItemIds = collect($items)
            ->pluck('supplier_purchase_order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($orderItemIds->isEmpty()) {
            return;
        }

        $orderItems = SupplierPurchaseOrderItem::query()
            ->whereIn('id', $orderItemIds)
            ->get()
            ->keyBy('id');
        $received = $this->receivedQuantitiesForItemIds($orderItemIds->all(), $entryId);
        $customerPendingByItem = $this->customerPendingQuantitiesForSupplierItemIds(
            $orderItemIds->all(),
            $entryId
        );

        foreach ($items as $index => $item) {
            $orderItemId = $item['supplier_purchase_order_item_id'] ?? null;

            if (!$orderItemId || !$orderItems->has((int) $orderItemId)) {
                continue;
            }

            $orderedQuantity = round((float) $orderItems->get((int) $orderItemId)->quantity, 2);
            $pending = max(round($orderedQuantity - (float) ($received[$orderItemId] ?? 0), 2), 0);
            $quantity = round((float) $item['quantity'], 2);

            if ($quantity > $pending) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'La cantidad ingresada supera la cantidad pendiente de la orden.',
                ]);
            }

            $customerPending = $customerPendingByItem[(int) $orderItemId] ?? null;

            if ($customerPending !== null && $quantity > $customerPending) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'La cantidad ingresada supera la cantidad pendiente de la orden del cliente.',
                ]);
            }
        }
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = round((float) collect($items)->sum('subtotal'), 2);
        $igv = round((float) collect($items)->sum('tax_amount'), 2);

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'grand_total' => round($subtotal + $igv, 2),
        ];
    }

    private function sourceItemPayload(
        SupplierPurchaseOrderItem $item,
        float $orderedQuantity,
        float $pendingQuantity
    ): array {
        $article = $item->article;
        $unitPrice = round((float) $item->unit_price, 2);
        $subtotal = round($pendingQuantity * $unitPrice, 2);
        $taxAmount = round($subtotal * 0.18, 2);

        return [
            'supplier_purchase_order_item_id' => $item->id,
            'article_id' => $item->article_id,
            'article_code' => $item->article_code ?? $article?->code,
            'billing_name_snapshot' => $item->billing_name_snapshot ?? $article?->billing_name ?? 'ARTICULO',
            'note' => $item->note,
            'unit_id' => $item->unit_id ?? $article?->unit_id,
            'presentation_id' => $item->presentation_id ?? $article?->presentation_id,
            'brand_id' => $item->brand_id ?? $article?->brand_id,
            'origin' => $item->origin,
            'cost_type' => $item->cost_type ?? 'PESO',
            'expiration_date' => $item->expiration_date ? (string) $item->expiration_date->format('Y-m-d') : null,
            'lot_number' => null,
            'ordered_quantity' => $orderedQuantity,
            'quantity' => $pendingQuantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'line_total' => round($subtotal + $taxAmount, 2),
        ];
    }

    private function receivedQuantitiesForOrder(SupplierPurchaseOrder $order, ?int $exceptEntryId = null): array
    {
        return $this->receivedQuantitiesForItemIds(
            $order->items->pluck('id')->all(),
            $exceptEntryId
        );
    }

    private function receivedQuantitiesForItemIds(array $orderItemIds, ?int $exceptEntryId = null): array
    {
        if (empty($orderItemIds)) {
            return [];
        }

        return DB::table('warehouse_entry_items as items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'items.warehouse_entry_id')
            ->whereNull('entries.deleted_at')
            ->where('entries.status', self::STATUS_REGISTERED)
            ->whereIn('items.supplier_purchase_order_item_id', $orderItemIds)
            ->where('items.status', '!=', 'deleted')
            ->when($exceptEntryId, fn ($query) => $query->where('entries.id', '!=', $exceptEntryId))
            ->groupBy('items.supplier_purchase_order_item_id')
            ->selectRaw('items.supplier_purchase_order_item_id, SUM(items.quantity) as received_quantity')
            ->get()
            ->pluck('received_quantity', 'supplier_purchase_order_item_id')
            ->map(fn ($quantity) => (float) $quantity)
            ->all();
    }

    private function nextEntryNumber(): string
    {
        $lastNumber = WarehouseEntry::withTrashed()
            ->where('entry_number', 'like', 'ING-%')
            ->pluck('entry_number')
            ->map(fn (?string $number) =>
                preg_match('/^ING-(\d{6,})$/', (string) $number, $matches)
                    ? (int) $matches[1]
                    : 0)
            ->max() ?? 0;

        do {
            $lastNumber++;
            $entryNumber = 'ING-' . str_pad($lastNumber, 6, '0', STR_PAD_LEFT);
        } while (WarehouseEntry::withTrashed()->where('entry_number', $entryNumber)->exists());

        return $entryNumber;
    }

    private function statusPresentation(): array
    {
        return [
            self::STATUS_REGISTERED => [
                'label' => 'Registrado',
                'class' => 'badge-primary text-white',
                'icon' => 'fas fa-clipboard-check',
            ],
            self::STATUS_CANCELLED => [
                'label' => 'Anulado',
                'class' => 'badge-danger text-white',
                'icon' => 'fas fa-ban',
            ],
        ];
    }

    private function statusValues(): array
    {
        return [
            self::STATUS_REGISTERED,
            self::STATUS_CANCELLED,
        ];
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === ''
            ? null
            : mb_strtoupper($value, 'UTF-8');
    }

    private function normalizeDocumentType(?string $value): string
    {
        $value = mb_strtoupper(trim((string) $value), 'UTF-8');

        return $value === '' ? 'FACTURA' : $value;
    }

    private function refreshSupplierPurchaseOrderStatus(?int $supplierPurchaseOrderId): void
    {
        if (! $supplierPurchaseOrderId) {
            return;
        }

        SupplierPurchaseOrder::query()
            ->with('items:id,supplier_purchase_order_id,quantity,status')
            ->find($supplierPurchaseOrderId)
            ?->refreshEntryStatus();
    }

    private function customerPendingQuantitiesForSupplierItemIds(array $supplierItemIds, ?int $exceptEntryId = null): array
    {
        $supplierItems = SupplierPurchaseOrderItem::query()
            ->whereIn('id', $supplierItemIds)
            ->whereNotNull('customer_purchase_order_item_id')
            ->get(['id', 'customer_purchase_order_item_id']);

        if ($supplierItems->isEmpty()) {
            return [];
        }

        $customerItemIds = $supplierItems
            ->pluck('customer_purchase_order_item_id')
            ->unique()
            ->values()
            ->all();

        $requestedByCustomerItem = DB::table('customer_purchase_order_items')
            ->whereIn('id', $customerItemIds)
            ->where('status', '!=', 'deleted')
            ->pluck('quantity', 'id')
            ->map(fn ($quantity) => round((float) $quantity, 2));

        $enteredByCustomerItem = DB::table('warehouse_entry_items as entry_items')
            ->join('warehouse_entries as entries', 'entries.id', '=', 'entry_items.warehouse_entry_id')
            ->join('supplier_purchase_order_items as supplier_items', 'supplier_items.id', '=', 'entry_items.supplier_purchase_order_item_id')
            ->join('supplier_purchase_orders as supplier_orders', 'supplier_orders.id', '=', 'supplier_items.supplier_purchase_order_id')
            ->whereIn('supplier_items.customer_purchase_order_item_id', $customerItemIds)
            ->whereNull('entries.deleted_at')
            ->whereNull('supplier_orders.deleted_at')
            ->where('entries.status', self::STATUS_REGISTERED)
            ->where('supplier_orders.status', '!=', 'cancelled')
            ->where('entry_items.status', '!=', 'deleted')
            ->where('supplier_items.status', '!=', 'deleted')
            ->when($exceptEntryId, fn ($query) => $query->where('entries.id', '!=', $exceptEntryId))
            ->groupBy('supplier_items.customer_purchase_order_item_id')
            ->selectRaw('supplier_items.customer_purchase_order_item_id, SUM(entry_items.quantity) as entered_quantity')
            ->pluck('entered_quantity', 'customer_purchase_order_item_id')
            ->map(fn ($quantity) => round((float) $quantity, 2));

        return $supplierItems
            ->mapWithKeys(function (SupplierPurchaseOrderItem $supplierItem) use (
                $requestedByCustomerItem,
                $enteredByCustomerItem
            ) {
                $customerItemId = (int) $supplierItem->customer_purchase_order_item_id;
                $requested = round((float) ($requestedByCustomerItem[$customerItemId] ?? 0), 2);
                $entered = round((float) ($enteredByCustomerItem[$customerItemId] ?? 0), 2);

                return [
                    $supplierItem->id => max(round($requested - $entered, 2), 0),
                ];
            })
            ->all();
    }

    private function customerPurchaseOrderIdsForWarehouseEntry(WarehouseEntry $entry)
    {
        $supplierItemIds = $entry->items()
            ->whereNotNull('supplier_purchase_order_item_id')
            ->pluck('supplier_purchase_order_item_id')
            ->all();

        return $this->customerPurchaseOrderIdsForSupplierItemIds($supplierItemIds);
    }

    private function customerPurchaseOrderIdsForSupplierItemIds(array $supplierItemIds)
    {
        if (empty($supplierItemIds)) {
            return collect();
        }

        return DB::table('supplier_purchase_order_items as supplier_items')
            ->join('customer_purchase_order_items as customer_items', 'customer_items.id', '=', 'supplier_items.customer_purchase_order_item_id')
            ->whereIn('supplier_items.id', $supplierItemIds)
            ->where('supplier_items.status', '!=', 'deleted')
            ->pluck('customer_items.customer_purchase_order_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function refreshCustomerPurchaseOrderStatuses($customerPurchaseOrderIds): void
    {
        CustomerPurchaseOrder::query()
            ->whereIn('id', collect($customerPurchaseOrderIds)->filter()->unique()->values()->all())
            ->get()
            ->each(fn (CustomerPurchaseOrder $order) => $order->refreshSupplyStatus());
    }

    private function warehouseEntryForPdf(WarehouseEntry $entry): WarehouseEntry
    {
        return $entry->fresh([
            'supplierPurchaseOrder',
            'company',
            'supplier',
            'currency',
            'warehouse',
            'creator',
            'items.article',
            'items.supplierPurchaseOrderItem',
            'items.unit',
            'items.presentation',
            'items.brand',
        ]);
    }

    private function generatedPdfDocument(WarehouseEntry $entry): ?Document
    {
        return $entry->documents()
            ->where('observation', self::PDF_OBSERVATION)
            ->where('status', 'ACTIVE')
            ->where('mime_type', 'application/pdf')
            ->latest('id')
            ->get()
            ->first(fn (Document $document) => $document->file_path
                && Storage::disk('public')->exists($document->file_path));
    }

    private function generateWarehouseEntryPdf(WarehouseEntry $entry): array
    {
        $fileName = 'ingreso_almacen_' . $this->sanitizeFileName($entry->entry_number) . '.pdf';
        $storedPath = 'warehouse_entries/pdfs/' . $fileName;

        $pdf = Pdf::loadView('admin.warehouse-entries.pdf', [
            'entry' => $entry,
            'logoUrl' => $this->warehouseEntryLogoUrl(),
        ])
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => true]);

        Storage::disk('public')->put($storedPath, $pdf->output());

        $this->deletePreviousGeneratedWarehouseEntryPdfs($entry, $storedPath);

        $document = Document::create([
            'documentable_type' => WarehouseEntry::class,
            'documentable_id' => $entry->id,
            'document_type_id' => null,
            'original_name' => $fileName,
            'stored_name' => $fileName,
            'file_path' => $storedPath,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'file_size' => Storage::disk('public')->size($storedPath) ?: 0,
            'issue_date' => now()->toDateString(),
            'expiration_date' => null,
            'observation' => self::PDF_OBSERVATION,
            'status' => 'ACTIVE',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return [
            'path' => $storedPath,
            'url' => route('admin.warehouse-entries.pdf', $entry),
            'document' => $document,
        ];
    }

    private function deletePreviousGeneratedWarehouseEntryPdfs(WarehouseEntry $entry, string $currentPath): void
    {
        $entry->documents()
            ->where('observation', self::PDF_OBSERVATION)
            ->get()
            ->each(function (Document $document) use ($currentPath) {
                if (
                    $document->file_path
                    && $document->file_path !== $currentPath
                    && Storage::disk('public')->exists($document->file_path)
                ) {
                    Storage::disk('public')->delete($document->file_path);
                }

                $document->delete();
            });
    }

    private function sanitizeFileName(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $value);
    }

    private function warehouseEntryLogoUrl(): ?string
    {
        $logoPath = public_path('vendor/adminlte/dist/img/logo_img.png');

        return file_exists($logoPath)
            ? url('vendor/adminlte/dist/img/logo_img.png')
            : null;
    }

    private function storeEntryDocuments(WarehouseEntry $entry, array $documentData, array $documentFiles): void
    {
        foreach ($documentData as $index => $document) {
            $file = $documentFiles[$index]['file'] ?? null;

            if (! $file) {
                continue;
            }

            $documentType = $this->resolveWarehouseEntryDocumentType($document['type'] ?? 'other');
            $storedPath = $file->store('warehouse_entries/documents', 'public');

            Document::create([
                'documentable_type' => WarehouseEntry::class,
                'documentable_id' => $entry->id,
                'document_type_id' => $documentType->id,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => basename($storedPath),
                'file_path' => $storedPath,
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'observation' => $this->upperOrNull($document['description'] ?? null),
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);
        }
    }

    private function resolveWarehouseEntryDocumentType(string $type): DocumentType
    {
        $types = $this->warehouseEntryDocumentTypes();
        $payload = $types[$type] ?? $types['other'];

        return DocumentType::query()->firstOrCreate(
            ['code' => $payload['code']],
            [
                'description' => $payload['label'],
                'observation' => 'Documento de ingreso de almacen',
                'status' => 'ACTIVE',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );
    }

    private function warehouseEntryDocumentTypes(): array
    {
        return [
            'purchase_invoice' => ['code' => 'WE001', 'label' => 'FACTURA'],
            'receipt' => ['code' => 'WE002', 'label' => 'BOLETA'],
            'dispatch_guide' => ['code' => 'WE003', 'label' => 'GUIA DE REMISION'],
            'analysis_certificate' => ['code' => 'WE004', 'label' => 'CERTIFICADO DE ANALISIS'],
            'sanitary_registration' => ['code' => 'WE005', 'label' => 'REGISTRO SANITARIO'],
            'quality_certificate' => ['code' => 'WE006', 'label' => 'CERTIFICADO DE CALIDAD'],
            'bpm_bpa_certificate' => ['code' => 'WE007', 'label' => 'CERTIFICADO BPM / BPA'],
            'technical_sheet' => ['code' => 'WE008', 'label' => 'FICHA TECNICA'],
            'medicine_document' => ['code' => 'WE009', 'label' => 'DOCUMENTO DEL MEDICAMENTO'],
            'other' => ['code' => 'WE010', 'label' => 'OTRO DOCUMENTO'],
        ];
    }

    private function ensureEntryDocument(WarehouseEntry $entry, Document $document): void
    {
        abort_unless(
            $document->documentable_type === WarehouseEntry::class
            && (int) $document->documentable_id === (int) $entry->id,
            404
        );
    }
}
