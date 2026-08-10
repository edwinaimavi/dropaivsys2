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
use App\Models\ShippingAgency;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Models\WarehouseEntryExpense;
use App\Models\WarehouseEntryExpenseDocument;
use App\Models\WarehouseEntryItemLotDocument;
use App\Services\WarehouseKardexService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
        $this->middleware('can:admin.warehouse-entries.expenses.documents.index')->only(['viewExpenseDocument']);
        $this->middleware('can:admin.warehouse-entries.index')->only(['index', 'list', 'generateNumber']);
        $this->middleware('can:admin.warehouse-entries.load-items')->only(['loadSupplierPurchaseOrderItems']);
        $this->middleware('can:admin.warehouse-entries.store')->only(['store']);
        $this->middleware('can:admin.warehouse-entries.update')->only(['update', 'destroyDocument']);
        $this->middleware('can:admin.warehouse-entries.destroy')->only(['destroy']);
        $this->middleware('can:admin.warehouse-entries.show')->only(['show', 'downloadDocument']);
        $this->middleware('can:admin.warehouse-entries.pdf')->only(['pdf']);
        $this->middleware('can:admin.warehouse-entries.lot-documents.index')->only(['downloadLotDocument']);
        $this->middleware('can:admin.warehouse-entries.lot-documents.destroy')->only(['destroyLotDocument']);
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
            ->get([
                'id',
                'code',
                'code_type',
                'institutional_code',
                'legal_name',
                'commercial_name',
                'billing_name',
                'unit_id',
                'presentation_id',
                'brand_id',
                'has_batch',
                'has_expiration',
            ]);
        $units = Unit::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $presentations = Presentation::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $brands = Brand::query()->where('status', 'ACTIVE')->orderBy('description')->get();
        $warehouses = Warehouse::query()
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description']);
        $shippingAgencies = ShippingAgency::query()
            ->where('status', 'ACTIVE')
            ->orderBy('trade_name')
            ->orderBy('business_name')
            ->get(['id', 'ruc', 'business_name', 'trade_name']);

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
            'warehouses',
            'shippingAgencies'
        ));
    }

    public function list()
    {
        $relatedCustomerOrders = DB::table('supplier_purchase_order_customer_purchase_order')
            ->select(
                'supplier_purchase_order_id',
                DB::raw('MIN(customer_purchase_order_id) as grouped_customer_purchase_order_id')
            )
            ->groupBy('supplier_purchase_order_id');

        $entries = WarehouseEntry::query()
            ->leftJoinSub($relatedCustomerOrders, 'grouped_related_orders', function ($join) {
                $join->on(
                    'grouped_related_orders.supplier_purchase_order_id',
                    '=',
                    'warehouse_entries.supplier_purchase_order_id'
                );
            })
            ->leftJoin(
                'supplier_purchase_orders as grouped_supplier_orders',
                'grouped_supplier_orders.id',
                '=',
                'warehouse_entries.supplier_purchase_order_id'
            )
            ->leftJoin('customer_purchase_orders as grouped_customer_orders', function ($join) {
                $join->on(
                    'grouped_customer_orders.id',
                    '=',
                    DB::raw('COALESCE(grouped_related_orders.grouped_customer_purchase_order_id, grouped_supplier_orders.customer_purchase_order_id)')
                );
            })
            ->select('warehouse_entries.*')
            ->selectRaw("COALESCE(grouped_customer_orders.purchase_order_number, grouped_customer_orders.code, 'Sin OC Cliente') as customer_order_group_sort")
            ->with([
                'supplier:id,business_name,short_name',
                'company:id,business_name,trade_name',
                'currency:id,code,symbol,description',
                'supplierPurchaseOrder:id,code,customer_purchase_order_id',
                'supplierPurchaseOrder.customerPurchaseOrder.customer:id,business_name,full_name,first_name,last_name',
                'supplierPurchaseOrder.customerPurchaseOrder.customerBranch:id,branch_name',
                'supplierPurchaseOrder.customerPurchaseOrders.customer:id,business_name,full_name,first_name,last_name',
                'supplierPurchaseOrder.customerPurchaseOrders.customerBranch:id,branch_name',
                'warehouse:id,code,name,description',
                'documents' => function ($query) {
                    $query->where('observation', self::PDF_OBSERVATION)
                        ->where('status', 'ACTIVE')
                        ->where('mime_type', 'application/pdf')
                        ->latest('id');
                },
            ])
            ->orderBy('customer_order_group_sort')
            ->orderByDesc('warehouse_entries.id');

        return DataTables::of($entries)
            ->addIndexColumn()
            ->editColumn('supplier_purchase_order_id', fn (WarehouseEntry $entry) =>
                $entry->supplierPurchaseOrder?->code ?? $entry->purchase_order_number ?? '-')
            ->addColumn('customer_order', function (WarehouseEntry $entry) {
                $customerOrders = $this->customerOrdersForWarehouseEntry($entry);

                if ($customerOrders->isEmpty()) {
                    return '<span class="warehouse-customer-order-empty">Sin OC cliente</span>';
                }

                return $customerOrders->map(function (CustomerPurchaseOrder $customerOrder) {
                    $number = $customerOrder->purchase_order_number ?: $customerOrder->code ?: '-';
                    $customer = $customerOrder->customer;
                    $customerName = $customer?->business_name
                        ?? $customer?->full_name
                        ?? trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? ''))
                        ?: 'Sin cliente';
                    $branchName = $customerOrder->customerBranch?->branch_name ?: 'Sin sede registrada';

                    return sprintf(
                        '<div class="warehouse-customer-order"><span>%s</span><small>%s</small><small class="warehouse-customer-order-branch">%s</small></div>',
                        e($number),
                        e($customerName),
                        e($branchName)
                    );
                })->implode('');
            })
            ->addColumn('customer_order_group_key', function (WarehouseEntry $entry) {
                return (string) ($this->customerOrdersForWarehouseEntry($entry)->first()?->id ?? 'without-customer-order');
            })
            ->addColumn('customer_order_number', function (WarehouseEntry $entry) {
                $customerOrder = $this->customerOrdersForWarehouseEntry($entry)->first();

                return $customerOrder?->purchase_order_number ?: $customerOrder?->code ?: 'Sin OC Cliente';
            })
            ->addColumn('customer_order_client', function (WarehouseEntry $entry) {
                $customer = $this->customerOrdersForWarehouseEntry($entry)->first()?->customer;

                return $customer?->business_name
                    ?? $customer?->full_name
                    ?? trim(($customer?->first_name ?? '') . ' ' . ($customer?->last_name ?? ''))
                    ?: 'Sin cliente relacionado';
            })
            ->addColumn('customer_order_branch', function (WarehouseEntry $entry) {
                return $this->customerOrdersForWarehouseEntry($entry)->first()?->customerBranch?->branch_name
                    ?: 'Sin sede registrada';
            })
            ->addColumn('grand_total_value', fn (WarehouseEntry $entry) => (float) $entry->grand_total)
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
            ->filterColumn('customer_order', function ($query, $keyword) {
                $query->whereHas('supplierPurchaseOrder', function ($supplierOrderQuery) use ($keyword) {
                    $supplierOrderQuery->where(function ($relatedOrderQuery) use ($keyword) {
                        $relatedOrderQuery
                            ->whereHas('customerPurchaseOrder', function ($customerOrderQuery) use ($keyword) {
                                $this->applyWarehouseCustomerOrderSearch($customerOrderQuery, $keyword);
                            })
                            ->orWhereHas('customerPurchaseOrders', function ($customerOrderQuery) use ($keyword) {
                                $this->applyWarehouseCustomerOrderSearch($customerOrderQuery, $keyword);
                            });
                    });
                });
            })
            ->filterColumn('supplier_purchase_order_id', function ($query, $keyword) {
                $query->where(function ($purchaseOrderQuery) use ($keyword) {
                    $purchaseOrderQuery
                        ->where('warehouse_entries.purchase_order_number', 'like', "%{$keyword}%")
                        ->orWhereHas('supplierPurchaseOrder', function ($supplierOrderQuery) use ($keyword) {
                            $supplierOrderQuery->where('code', 'like', "%{$keyword}%");
                        });
                });
            })
            ->filterColumn('supplier', function ($query, $keyword) {
                $query->whereHas('supplier', function ($supplierQuery) use ($keyword) {
                    $supplierQuery->where('business_name', 'like', "%{$keyword}%")
                        ->orWhere('short_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('company', function ($query, $keyword) {
                $query->whereHas('company', function ($companyQuery) use ($keyword) {
                    $companyQuery->where('business_name', 'like', "%{$keyword}%")
                        ->orWhere('trade_name', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('warehouse', function ($query, $keyword) {
                $query->whereHas('warehouse', function ($warehouseQuery) use ($keyword) {
                    $warehouseQuery->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('currency', function ($query, $keyword) {
                $query->whereHas('currency', function ($currencyQuery) use ($keyword) {
                    $currencyQuery->where('code', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->filterColumn('status', function ($query, $keyword) {
                $normalized = strtolower(trim($keyword));
                $statusAliases = [
                    'registrado' => self::STATUS_REGISTERED,
                    'registrada' => self::STATUS_REGISTERED,
                    'anulado' => self::STATUS_CANCELLED,
                    'anulada' => self::STATUS_CANCELLED,
                    'cancelado' => self::STATUS_CANCELLED,
                    'cancelada' => self::STATUS_CANCELLED,
                ];

                $query->where('warehouse_entries.status', 'like', '%' . ($statusAliases[$normalized] ?? $keyword) . '%');
            })
            ->rawColumns(['customer_order', 'status', 'acciones'])
            ->make(true);
    }

    private function customerOrdersForWarehouseEntry(WarehouseEntry $entry)
    {
        $supplierOrder = $entry->supplierPurchaseOrder;

        if (! $supplierOrder) {
            return collect();
        }

        $customerOrders = $supplierOrder->customerPurchaseOrders;

        if ($customerOrders->isEmpty() && $supplierOrder->customerPurchaseOrder) {
            $customerOrders = collect([$supplierOrder->customerPurchaseOrder]);
        }

        return $customerOrders->unique('id')->sortBy('id')->values();
    }

    private function applyWarehouseCustomerOrderSearch($query, string $keyword): void
    {
        $query->where(function ($searchQuery) use ($keyword) {
            $searchQuery->where('purchase_order_number', 'like', "%{$keyword}%")
                ->orWhere('code', 'like', "%{$keyword}%")
                ->orWhereHas('customer', function ($customerQuery) use ($keyword) {
                    $customerQuery->where('business_name', 'like', "%{$keyword}%")
                        ->orWhere('full_name', 'like', "%{$keyword}%")
                        ->orWhere('first_name', 'like', "%{$keyword}%")
                        ->orWhere('last_name', 'like', "%{$keyword}%");
                })
                ->orWhereHas('customerBranch', function ($branchQuery) use ($keyword) {
                    $branchQuery->where('branch_name', 'like', "%{$keyword}%");
                });
        });
    }

    public function store(Request $request)
    {
        return $this->saveEntry($request);
    }

    public function show(WarehouseEntry $warehouseEntry)
    {
        $warehouseEntry->load([
            'supplierPurchaseOrder.customerPurchaseOrder.customer',
            'supplierPurchaseOrder.customerPurchaseOrder.company',
            'supplierPurchaseOrder.customerPurchaseOrder.currency',
            'supplierPurchaseOrder.customerPurchaseOrders.customer',
            'supplierPurchaseOrder.customerPurchaseOrders.company',
            'supplierPurchaseOrder.customerPurchaseOrders.currency',
            'company',
            'supplier',
            'customer',
            'currency',
            'warehouse',
            'creator:id,name,lastname,email',
            'updater:id,name,lastname,email',
            'items.article',
            'items.supplierPurchaseOrderItem',
            'items.unit',
            'items.presentation',
            'items.brand',
            'items.lots.documents',
            'expenses.provider:id,business_name,short_name,ruc',
            'expenses.shippingAgency:id,business_name,trade_name,ruc',
            'expenses.currency:id,code,symbol',
            'expenses.distributions.item:id,warehouse_entry_id,billing_name_snapshot,quantity,unit_price',
            'expenses.documents',
            'documents' => function ($query) {
                $query->where(function ($innerQuery) {
                    $innerQuery->whereNull('observation')
                        ->orWhere('observation', '!=', self::PDF_OBSERVATION);
                })->with('documentType');
            },
        ]);

        if (! Auth::user()?->can('admin.warehouse-entries.expenses.index')) {
            $warehouseEntry->unsetRelation('expenses');
        } else {
            $warehouseEntry->expenses->each(function (WarehouseEntryExpense $expense) use ($warehouseEntry) {
                $expense->documents->each(fn (WarehouseEntryExpenseDocument $document) => $document->setAttribute(
                    'view_url', route('admin.warehouse-entries.expenses.documents.view', [$warehouseEntry, $document])
                ));
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $warehouseEntry,
            'warehouse_name' => $warehouseEntry->warehouse?->name ?? 'SIN ALMACEN',
        ]);
    }

    public function viewExpenseDocument(WarehouseEntry $warehouseEntry, WarehouseEntryExpenseDocument $expenseDocument)
    {
        abort_unless((int) $expenseDocument->expense?->warehouse_entry_id === (int) $warehouseEntry->id, 404);
        abort_unless($expenseDocument->status === 'ACTIVE' && Storage::disk('public')->exists($expenseDocument->file_path), 404);

        return response()->file(Storage::disk('public')->path($expenseDocument->file_path), [
            'Content-Type' => $expenseDocument->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . ($expenseDocument->original_name ?: basename($expenseDocument->file_path)) . '"',
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

    public function downloadLotDocument(WarehouseEntry $warehouseEntry, WarehouseEntryItemLotDocument $lotDocument)
    {
        $this->ensureEntryLotDocument($warehouseEntry, $lotDocument);
        abort_unless($lotDocument->file_path && Storage::disk('public')->exists($lotDocument->file_path), 404);

        return Storage::disk('public')->download($lotDocument->file_path, $lotDocument->original_name);
    }

    public function destroyLotDocument(WarehouseEntry $warehouseEntry, WarehouseEntryItemLotDocument $lotDocument)
    {
        $this->ensureEntryLotDocument($warehouseEntry, $lotDocument);
        $lotDocument->update(['status' => 'INACTIVE', 'updated_by' => Auth::id()]);

        return response()->json(['status' => 'success', 'message' => 'Documento del lote anulado correctamente.']);
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
            ->map(function (SupplierPurchaseOrderItem $item) use ($receivedByItem, $order) {
                $orderedQuantity = round((float) $item->quantity, 2);
                $receivedQuantity = round((float) ($receivedByItem[$item->id] ?? 0), 2);
                $pendingQuantity = max(round($orderedQuantity - $receivedQuantity, 2), 0);

                return $this->sourceItemPayload(
                    $item,
                    $orderedQuantity,
                    $pendingQuantity,
                    (bool) $order->affect_igv
                );
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
            'order_total' => number_format((float) $order->grand_total, 2, '.', ''),
            'payment_method' => $order->payment_method,
            'payment_condition' => $order->payment_condition,
            'delivery_type' => SupplierPurchaseOrder::normalizeDeliveryType($order->delivery_type),
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
            'expenses' => collect($request->all('expenses')['expenses'] ?? [])
                ->map(fn (array $expense) => $this->normalizeLinkedExpenseFields($expense))
                ->all(),
        ]);
        $hasSupplierPurchaseOrder = $request->filled('supplier_purchase_order_id');

        $documentRules = $this->newDocumentUploadRules($request);

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
            'items.*.id' => ['nullable', 'integer', 'exists:warehouse_entry_items,id'],
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
            'items.*.lots' => ['nullable', 'array'],
            'items.*.lots.*.id' => ['nullable', 'integer', 'exists:warehouse_entry_item_lots,id'],
            'items.*.lots.*.client_key' => ['nullable', 'string', 'max:100'],
            'items.*.lots.*.lot_code' => ['required', 'string', 'max:100'],
            'items.*.lots.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.lots.*.expiration_date' => ['nullable', 'date'],
            'items.*.lots.*.manufacturing_date' => ['nullable', 'date'],
            'items.*.ordered_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'warehouse_entry_documents' => ['nullable', 'array'],
            'warehouse_entry_documents.*.type' => ['required_with:warehouse_entry_documents.*.file', 'string', Rule::in(array_keys($this->warehouseEntryDocumentTypes()))],
            'warehouse_entry_documents.*.description' => ['nullable', 'string', 'max:255'],
            'warehouse_entry_lot_documents' => ['nullable', 'array'],
            'warehouse_entry_lot_documents.*.item_index' => ['required', 'integer', 'min:0'],
            'warehouse_entry_lot_documents.*.lot_key' => ['required', 'string', 'max:100'],
            'warehouse_entry_lot_documents.*.type' => ['required', 'string', Rule::in(array_keys($this->warehouseEntryDocumentTypes()))],
            'warehouse_entry_lot_documents.*.description' => ['nullable', 'string', 'max:255'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.id' => ['nullable', 'integer', 'exists:warehouse_entry_expenses,id'],
            'expenses.*.expense_category' => ['required', Rule::in(['freight_transport', 'other_expense'])],
            'expenses.*.cost_origin' => ['required', Rule::in(['included_in_purchase_price', 'same_purchase_document', 'third_party', 'internal_without_document'])],
            'expenses.*.expense_type' => ['required', Rule::in(['agency_freight', 'pickup_transfer', 'agency_pickup_to_warehouse', 'agency_direct_to_warehouse', 'supplier_warehouse_pickup', 'transfer_to_agency', 'transport_agency', 'courier', 'truck', 'mobility', 'shipping', 'delivery', 'transfer', 'stowage', 'packaging', 'toll', 'insurance', 'commission', 'handling', 'loading_unloading', 'other', 'flete', 'transporte', 'estiba', 'movilidad', 'embalaje', 'peaje', 'seguro', 'comision', 'aduana', 'otro'])],
            'expenses.*.shipping_agency_id' => ['nullable', Rule::exists('shipping_agencies', 'id')->where('status', 'ACTIVE')],
            'expenses.*.provider_id' => ['nullable', 'exists:suppliers,id'],
            'expenses.*.provider_ruc' => ['nullable', 'string', 'max:20'],
            'expenses.*.provider_name' => ['nullable', 'string', 'max:255'],
            'expenses.*.document_type' => ['nullable', 'string', 'max:50'],
            'expenses.*.document_series' => ['nullable', 'string', 'max:20'],
            'expenses.*.document_number' => ['nullable', 'string', 'max:50'],
            'expenses.*.document_date' => ['nullable', 'date'],
            'expenses.*.currency_id' => ['nullable', 'exists:currencies,id'],
            'expenses.*.amount' => ['required', 'numeric', 'gt:0'],
            'expenses.*.distributed_amount' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.affects_igv' => ['required', 'boolean'],
            'expenses.*.igv_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'expenses.*.taxable_amount' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.igv_amount' => ['nullable', 'numeric', 'min:0'],
            'expenses.*.total_amount' => ['nullable', 'numeric', 'gt:0'],
            'expenses.*.affects_inventory_cost' => ['required', 'boolean'],
            'expenses.*.distribution_method' => ['nullable', Rule::in(['quantity', 'amount', 'weight', 'manual'])],
            'expenses.*.description' => ['nullable', 'string', 'max:1000'],
            'expenses.*.distributions' => ['nullable', 'array'],
            'expenses.*.distributions.*.item_index' => ['required', 'integer', 'min:0'],
            'expenses.*.distributions.*.distributed_amount' => ['required', 'numeric', 'min:0'],
            'expenses.*.file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'expense_management' => ['nullable', 'boolean'],
        ] + $documentRules, [
            'warehouse_id.required' => 'Debe seleccionar un almacén.',
            'warehouse_id.exists' => 'El almacén seleccionado no existe o no está activo.',
            'warehouse_entry_documents.*.file.file' => 'El comprobante adjunto debe ser un archivo válido.',
            'warehouse_entry_documents.*.file.mimes' => 'El archivo adjunto debe ser PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX y no debe superar los 10 MB.',
            'warehouse_entry_documents.*.file.max' => 'El archivo adjunto debe ser PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX y no debe superar los 10 MB.',
            'warehouse_entry_lot_documents.*.file.file' => 'El documento del lote debe ser un archivo válido.',
            'warehouse_entry_lot_documents.*.file.mimes' => 'El archivo adjunto debe ser PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX y no debe superar los 10 MB.',
            'warehouse_entry_lot_documents.*.file.max' => 'El archivo adjunto debe ser PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS o XLSX y no debe superar los 10 MB.',
            'expenses.*.amount.required' => 'Ingrese un importe válido.',
            'expenses.*.amount.gt' => 'El importe debe ser mayor a 0.',
            'expenses.*.affects_igv.required' => 'Seleccione si el costo está afecto a IGV.',
            'expenses.*.affects_igv.boolean' => 'Seleccione si el costo está afecto a IGV.',
        ]);

        if ($request->boolean('expense_management')) {
            abort_unless(Auth::user()?->can($entry
                ? 'admin.warehouse-entries.expenses.update'
                : 'admin.warehouse-entries.expenses.store'), 403);
        }

        $this->validateItemLots($validated['items']);

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
                } else {
                    $entryData['entry_number'] = $this->nextEntryNumber();
                    $entryData['created_by'] = Auth::id();
                    $entry = WarehouseEntry::create($entryData);
                }

                $retainedItemIds = [];
                $lotMap = [];
                foreach ($preparedItems as $itemIndex => $item) {
                    $lots = $item['lots'] ?? [];
                    $itemId = $item['_item_id'] ?? null;
                    unset($item['lots']);
                    unset($item['_item_id']);
                    $entryItem = $itemId
                        ? $entry->items()->whereKey($itemId)->firstOrFail()
                        : $entry->items()->make();
                    $entryItem->fill($item)->save();
                    $retainedItemIds[] = $entryItem->id;
                    $retainedLotIds = [];

                    foreach ($lots as $lot) {
                        $lotId = $lot['id'] ?? null;
                        $clientKey = $lot['client_key'] ?? ($lotId ? 'id:' . $lotId : null);
                        unset($lot['id'], $lot['client_key']);
                        $entryLot = $lotId
                            ? $entryItem->lots()->whereKey($lotId)->firstOrFail()
                            : $entryItem->lots()->make(['created_by' => Auth::id()]);
                        $entryLot->fill($lot + ['status' => 'active', 'updated_by' => Auth::id()])->save();
                        $retainedLotIds[] = $entryLot->id;
                        if ($clientKey) $lotMap[$itemIndex . ':' . $clientKey] = $entryLot;
                    }

                    $removedLots = $entryItem->lots()->whereNotIn('id', $retainedLotIds)->get();
                    foreach ($removedLots as $removedLot) {
                        $removedLot->documents()->update(['status' => 'INACTIVE', 'updated_by' => Auth::id()]);
                        $removedLot->update(['status' => 'inactive', 'updated_by' => Auth::id()]);
                    }
                }

                if ($isUpdate) {
                    $entry->items()->whereNotIn('id', $retainedItemIds)->update(['status' => 'deleted']);
                }

                if ($request->boolean('expense_management')) {
                    $this->syncEntryExpenses($entry, $validated['expenses'] ?? [], $request->file('expenses', []), $retainedItemIds);
                }

                $currentCustomerPurchaseOrderIds = $this->customerPurchaseOrderIdsForWarehouseEntry($entry);

                $this->storeEntryDocuments($entry, $request->input('warehouse_entry_documents', []), $request->file('warehouse_entry_documents', []));
                $this->storeEntryLotDocuments(
                    $entry,
                    $lotMap,
                    $request->input('warehouse_entry_lot_documents', []),
                    $request->file('warehouse_entry_lot_documents', [])
                );

                $freshEntry = $entry->fresh([
                    'supplier',
                    'currency',
                    'items.article',
                    'items.unit',
                    'items.presentation',
                    'items.brand',
                    'items.lots',
                    'expenses.distributions',
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

            Log::error('Error saving warehouse entry', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => Auth::id(),
                'request_keys' => array_keys($request->all()),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar el ingreso de almacen.',
                'debug' => config('app.debug') ? [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
            ], 500);
        }
    }

    private function prepareItems(array $items, bool $affectIgv): array
    {
        return collect($items)->map(function (array $item) use ($affectIgv) {
            $quantity = round((float) $item['quantity'], 2);
            $unitPrice = round((float) $item['unit_price'], 6);
            $lineTotal = round($quantity * $unitPrice, 2);
            $subtotal = $affectIgv ? round($lineTotal / 1.18, 2) : 0;
            $taxAmount = $affectIgv ? round($lineTotal - $subtotal, 2) : 0;

            return [
                '_item_id' => $item['id'] ?? null,
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
                'line_total' => $lineTotal,
                'status' => 'active',
                'lots' => collect($item['lots'] ?? [])->map(fn (array $lot) => [
                    'id' => $lot['id'] ?? null,
                    'client_key' => $lot['client_key'] ?? (($lot['id'] ?? null) ? 'id:' . $lot['id'] : null),
                    'lot_code' => $this->upperOrNull($lot['lot_code'] ?? null),
                    'quantity' => round((float) ($lot['quantity'] ?? 0), 4),
                    'expiration_date' => $lot['expiration_date'] ?? null,
                    'manufacturing_date' => $lot['manufacturing_date'] ?? null,
                ])->all(),
            ];
        })->all();
    }

    private function syncEntryExpenses(WarehouseEntry $entry, array $expenses, array $expenseFiles, array $itemIds): void
    {
        $itemsById = $entry->items()->whereIn('id', $itemIds)->get()->keyBy('id');
        $items = collect($itemIds)->map(fn ($id) => $itemsById->get($id))->filter()->values();
        $retainedExpenseIds = [];
        $submittedExpenseIds = collect($expenses)->pluck('id')->filter()->map(fn ($id) => (int) $id);
        $removesExistingExpenses = $entry->expenses()->whereNotIn('id', $submittedExpenseIds)->exists();
        abort_if($removesExistingExpenses && ! Auth::user()?->can('admin.warehouse-entries.expenses.destroy'), 403, 'No tiene permiso para anular gastos vinculados.');

        foreach ($expenses as $index => $data) {
            $data = $this->prepareLinkedExpense($data, $index);
            $existingExpense = ! empty($data['id'])
                ? $entry->expenses()->with('documents')->whereKey($data['id'])->firstOrFail()
                : null;
            $hasAttachedReceipt = isset($expenseFiles[$index]['file'])
                || (bool) $existingExpense?->documents?->isNotEmpty();
            if (! $existingExpense && empty($data['document_date'])) {
                throw ValidationException::withMessages(["expenses.$index.document_date" => 'Seleccione una fecha válida.']);
            }
            if (! $existingExpense && blank($data['document_type'] ?? null)) {
                throw ValidationException::withMessages(["expenses.$index.document_type" => 'Seleccione el tipo de documento.']);
            }
            if (($data['document_type'] ?? null) !== 'SIN_COMPROBANTE'
                && (! $existingExpense || filled($data['document_type'] ?? null))
                && (blank($data['document_series'] ?? null) || blank($data['document_number'] ?? null))) {
                throw ValidationException::withMessages(["expenses.$index.document_number" => 'Ingrese la serie y el número del comprobante.']);
            }
            if ((($data['document_type'] ?? null) === 'SIN_COMPROBANTE' || ! $hasAttachedReceipt)
                && blank($data['description'] ?? null)) {
                throw ValidationException::withMessages(["expenses.$index.description" => 'Ingrese una observación cuando no adjunta comprobante.']);
            }
            if (($data['expense_type'] ?? null) === 'agency_freight'
                && empty($data['shipping_agency_id'])) {
                throw ValidationException::withMessages([
                    "expenses.$index.shipping_agency_id" => 'Seleccione la agencia de envío.',
                ]);
            }
            if (($data['expense_type'] ?? null) !== 'agency_freight'
                && blank($data['provider_name'] ?? null)) {
                throw ValidationException::withMessages([
                    "expenses.$index.provider_name" => 'Ingrese el responsable o persona que cobró.',
                ]);
            }
            $affectsCost = filter_var($data['affects_inventory_cost'], FILTER_VALIDATE_BOOLEAN);
            $method = $affectsCost ? ($data['distribution_method'] ?? null) : null;
            if ($affectsCost && $items->isEmpty()) {
                throw ValidationException::withMessages(["expenses.$index.distribution_method" => 'Debe existir al menos un artículo para distribuir el gasto.']);
            }
            if ($affectsCost && ! $method) {
                throw ValidationException::withMessages(["expenses.$index.distribution_method" => 'Seleccione un método de distribución.']);
            }
            if ($method === 'weight') {
                throw ValidationException::withMessages(["expenses.$index.distribution_method" => 'Los artículos del ingreso no tienen peso registrado. Seleccione otro método.']);
            }

            foreach (['provider_ruc', 'document_type', 'document_series', 'document_number'] as $field) {
                $data[$field] = $this->upperOrNull($data[$field] ?? null);
            }
            if (($data['expense_type'] ?? null) === 'agency_freight') {
                $agency = ShippingAgency::query()->findOrFail($data['shipping_agency_id']);
                $data['provider_name'] = $agency->trade_name ?: $agency->business_name;
                $data['provider_ruc'] = $this->upperOrNull($agency->ruc);
            }
            if (! $data['provider_ruc'] && ! empty($data['provider_id'])) {
                $data['provider_ruc'] = $this->upperOrNull(Supplier::query()->whereKey($data['provider_id'])->value('ruc'));
            }
            if ($data['document_type'] && $data['document_series'] && $data['document_number']) {
                $duplicate = WarehouseEntryExpense::query()
                    ->where('status', 'ACTIVE')
                    ->where('document_type', $data['document_type'])
                    ->where('document_series', $data['document_series'])
                    ->where('document_number', $data['document_number'])
                    ->where(function ($query) use ($data) {
                        if (! empty($data['shipping_agency_id'])) {
                            $query->where('shipping_agency_id', $data['shipping_agency_id']);
                        } elseif (! empty($data['provider_id'])) {
                            $query->where('provider_id', $data['provider_id']);
                        } elseif (! empty($data['provider_ruc'])) {
                            $query->where('provider_ruc', $data['provider_ruc']);
                        } else {
                            $query->where('provider_name', $this->upperOrNull($data['provider_name'] ?? null));
                        }
                    })
                    ->when($data['id'] ?? null, fn ($query, $id) => $query->where('id', '!=', $id))
                    ->exists();
                if ($duplicate) {
                    throw ValidationException::withMessages(["expenses.$index.document_number" => 'Ya existe un gasto con este comprobante para el proveedor.']);
                }
            }

            $taxBreakdown = WarehouseEntryExpense::taxBreakdown(
                (float) $data['amount'],
                filter_var($data['affects_igv'], FILTER_VALIDATE_BOOLEAN)
            );
            $expense = $existingExpense
                ? $existingExpense
                : $entry->expenses()->make(['created_by' => Auth::id()]);
            $expense->fill([
                'supplier_purchase_order_id' => $entry->supplier_purchase_order_id,
                'expense_category' => $data['expense_category'],
                'cost_origin' => $data['cost_origin'],
                'expense_type' => $data['expense_type'],
                'shipping_agency_id' => $data['expense_type'] === 'agency_freight' ? ($data['shipping_agency_id'] ?? null) : null,
                'provider_id' => $data['provider_id'] ?? null,
                'provider_ruc' => $data['provider_ruc'],
                'provider_name' => $this->upperOrNull($data['provider_name'] ?? null),
                'document_type' => $data['document_type'],
                'document_series' => $data['document_series'],
                'document_number' => $data['document_number'],
                'document_date' => $data['document_date'] ?? null,
                'currency_id' => $data['currency_id'] ?? $entry->currency_id,
                'amount' => $taxBreakdown['total_amount'],
                ...$taxBreakdown,
                'affects_inventory_cost' => $affectsCost,
                'distribution_method' => $method,
                'description' => $this->upperOrNull($data['description'] ?? null),
                'status' => 'ACTIVE',
                'updated_by' => Auth::id(),
            ])->save();
            $retainedExpenseIds[] = $expense->id;

            $allocations = $affectsCost
                ? $this->expenseAllocations($data, $items, $method, (float) $expense->amount, $index)
                : [];
            $expense->distributions()->delete();
            foreach ($allocations as $itemIndex => $amount) {
                $expense->distributions()->create([
                    'warehouse_entry_item_id' => $items[$itemIndex]->id,
                    'distributed_amount' => $amount,
                ]);
            }

            $file = $expenseFiles[$index]['file'] ?? null;
            if ($file) {
                $path = $file->store("warehouse_entries/{$entry->id}/expenses/{$expense->id}", 'public');
                $expense->documents()->create([
                    'document_type' => $data['document_type'],
                    'description' => $data['description'] ?? null,
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]);
            }
        }

        $entry->expenses()->whereNotIn('id', $retainedExpenseIds)->update(['status' => 'INACTIVE', 'updated_by' => Auth::id()]);
        $costs = DB::table('warehouse_entry_expense_distributions as distributions')
            ->join('warehouse_entry_expenses as expenses', 'expenses.id', '=', 'distributions.warehouse_entry_expense_id')
            ->where('expenses.warehouse_entry_id', $entry->id)->where('expenses.status', 'ACTIVE')
            ->groupBy('distributions.warehouse_entry_item_id')
            ->selectRaw('distributions.warehouse_entry_item_id, SUM(distributions.distributed_amount) as distributed_amount')
            ->pluck('distributed_amount', 'warehouse_entry_item_id');
        foreach ($items as $item) {
            $additional = round((float) ($costs[$item->id] ?? 0), 2);
            $item->update([
                'additional_cost' => $additional,
                'real_unit_cost' => round((float) $item->unit_price + ($additional / (float) $item->quantity), 6),
            ]);
        }
    }

    private function prepareLinkedExpense(array $data, int $index): array
    {
        $included = ($data['cost_origin'] ?? null) === 'included_in_purchase_price';
        if ($included) {
            $data['amount'] = 0;
            $data['affects_inventory_cost'] = false;
            $data['distribution_method'] = null;
            $data['distributions'] = [];
            if (blank($data['description'] ?? null)) {
                $data['description'] = 'El proveedor asumió el flete / costo incluido en la compra.';
            }
            return $data;
        }

        if ((float) ($data['amount'] ?? 0) <= 0) {
            throw ValidationException::withMessages([
                "expenses.$index.amount" => 'Ingrese un importe válido. El importe debe ser mayor a 0.',
            ]);
        }

        $affectsIgv = filter_var($data['affects_igv'] ?? null, FILTER_VALIDATE_BOOLEAN);
        $documentType = strtoupper(trim((string) ($data['document_type'] ?? '')));
        if ($affectsIgv && ! WarehouseEntryExpense::supportsIgv($documentType)) {
            $message = in_array($documentType, ['RECIBO', 'SIN_COMPROBANTE', ''], true)
                ? 'Los recibos o costos sin comprobante no generan IGV para el análisis.'
                : 'Solo una factura o boleta puede registrarse como afecto a IGV.';

            throw ValidationException::withMessages(["expenses.$index.affects_igv" => $message]);
        }

        $data['affects_igv'] = $affectsIgv;

        return $data;
    }

    private function normalizeLinkedExpenseFields(array $data): array
    {
        $requestedType = strtolower(trim((string) ($data['expense_type'] ?? $data['cost_type'] ?? $data['type'] ?? '')));
        $simpleType = match ($requestedType) {
            'agency_freight', 'flete_agencia', 'transport_agency', 'courier', 'shipping' => 'agency_freight',
            'pickup_transfer', 'recojo_traslado', 'agency_pickup_to_warehouse', 'agency_direct_to_warehouse',
            'supplier_warehouse_pickup', 'transfer_to_agency', 'truck', 'mobility', 'delivery', 'transfer',
            'flete', 'transporte', 'movilidad' => 'pickup_transfer',
            'other', 'otros', 'otros_gastos', 'stowage', 'packaging', 'toll', 'insurance', 'commission',
            'handling', 'loading_unloading', 'estiba', 'embalaje', 'peaje', 'seguro', 'comision', 'aduana', 'otro' => 'other',
            default => $requestedType,
        };

        $mapping = match ($simpleType) {
            'agency_freight' => ['expense_category' => 'freight_transport', 'cost_origin' => 'third_party', 'affects_inventory_cost' => true, 'distribution_method' => 'quantity'],
            'pickup_transfer' => ['expense_category' => 'freight_transport', 'cost_origin' => 'third_party', 'affects_inventory_cost' => true, 'distribution_method' => 'quantity'],
            'other' => ['expense_category' => 'other_expense', 'cost_origin' => 'third_party', 'affects_inventory_cost' => true, 'distribution_method' => 'quantity'],
            default => [],
        };

        if ($mapping) {
            $data = array_merge($data, $mapping, ['expense_type' => $simpleType]);
        }

        $affectsInventoryCost = filter_var(
            $data['affects_inventory_cost'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        );
        $data['affects_inventory_cost'] = $affectsInventoryCost;
        if (array_key_exists('affects_igv', $data)) {
            $data['affects_igv'] = filter_var($data['affects_igv'], FILTER_VALIDATE_BOOLEAN);
        }
        $data['distributed_amount'] = round((float) (
            $data['distributed_amount'] ?? ($affectsInventoryCost ? ($data['amount'] ?? 0) : 0)
        ), 2);

        return $data;
    }

    private function expenseAllocations(array $data, $items, string $method, float $amount, int $expenseIndex): array
    {
        if ($method === 'manual') {
            $allocations = array_fill(0, $items->count(), 0.0);
            foreach ($data['distributions'] ?? [] as $distribution) {
                if (! isset($items[(int) $distribution['item_index']])) {
                    throw ValidationException::withMessages(["expenses.$expenseIndex.distributions" => 'La distribución contiene un artículo inválido.']);
                }
                $allocations[(int) $distribution['item_index']] = round((float) $distribution['distributed_amount'], 2);
            }
            if (abs(array_sum($allocations) - $amount) > 0.009) {
                throw ValidationException::withMessages(["expenses.$expenseIndex.distributions" => 'La distribución del gasto debe coincidir con el importe total.']);
            }
            return $allocations;
        }

        $weights = $items->map(fn ($item) => $method === 'amount' ? (float) $item->line_total : (float) $item->quantity)->all();
        $totalWeight = array_sum($weights);
        if ($totalWeight <= 0) {
            throw ValidationException::withMessages(["expenses.$expenseIndex.distribution_method" => 'No existen valores válidos para distribuir el gasto.']);
        }
        $remaining = (int) round($amount * 100);
        $result = [];
        foreach ($weights as $position => $weight) {
            $cents = $position === array_key_last($weights) ? $remaining : (int) round(($amount * 100) * ($weight / $totalWeight));
            $result[$position] = $cents / 100;
            $remaining -= $cents;
        }
        return $result;
    }

    private function validateItemLots(array $items): void
    {
        $articles = Article::query()
            ->whereIn('id', collect($items)->pluck('article_id')->filter()->unique())
            ->get(['id', 'billing_name', 'has_batch', 'has_expiration'])
            ->keyBy('id');

        foreach ($items as $index => $item) {
            $article = $articles->get((int) $item['article_id']);
            $lots = collect($item['lots'] ?? []);
            $name = $item['billing_name_snapshot'] ?? $article?->billing_name ?? 'seleccionado';

            if ($article?->has_batch && $lots->isEmpty()) {
                throw ValidationException::withMessages([
                    "items.$index.lots" => "El articulo {$name} debe tener al menos un lote.",
                ]);
            }

            if ($lots->isEmpty()) {
                continue;
            }

            if ($article?->has_expiration && $lots->contains(fn ($lot) => empty($lot['expiration_date']))) {
                throw ValidationException::withMessages([
                    "items.$index.lots" => "Todos los lotes del articulo {$name} requieren fecha de vencimiento.",
                ]);
            }

            $total = round((float) $lots->sum(fn ($lot) => (float) ($lot['quantity'] ?? 0)), 4);
            $quantity = round((float) $item['quantity'], 4);

            if (abs($total - $quantity) > 0.0001) {
                throw ValidationException::withMessages([
                    "items.$index.lots" => "La suma de los lotes del articulo {$name} es {$total}, pero la cantidad ingresada es {$quantity}.",
                ]);
            }
        }
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
        $grandTotal = round((float) collect($items)->sum('line_total'), 2);

        return [
            'subtotal' => $subtotal,
            'igv' => $igv,
            'grand_total' => $grandTotal,
        ];
    }

    private function sourceItemPayload(
        SupplierPurchaseOrderItem $item,
        float $orderedQuantity,
        float $pendingQuantity,
        bool $affectIgv
    ): array {
        $article = $item->article;
        $unitPrice = round((float) $item->unit_price, 6);
        $lineTotal = round($pendingQuantity * $unitPrice, 2);
        $subtotal = $affectIgv ? round($lineTotal / 1.18, 2) : 0;
        $taxAmount = $affectIgv ? round($lineTotal - $subtotal, 2) : 0;

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
            'line_total' => $lineTotal,
            'has_batch' => (bool) $article?->has_batch,
            'has_expiration' => (bool) $article?->has_expiration,
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
            'items.lots',
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

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $documentType = $this->resolveWarehouseEntryDocumentType($document['type'] ?? 'other');
            $storedPath = $file->store("warehouse_entries/{$entry->id}/documents/general", 'public');

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

    /**
     * Keep only rows that contain a real upload. Existing document metadata is
     * intentionally ignored: existing records are managed independently and
     * must not be revalidated or recreated when an entry is edited.
     */
    private function newDocumentUploadRules(Request $request): array
    {
        $rules = [];

        foreach (['warehouse_entry_documents', 'warehouse_entry_lot_documents'] as $collection) {
            $inputRows = $request->input($collection, []);
            $fileRows = $request->file($collection, []);
            $newRows = [];

            foreach ($fileRows as $index => $fileRow) {
                $file = is_array($fileRow) ? ($fileRow['file'] ?? null) : null;

                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $newRows[$index] = is_array($inputRows[$index] ?? null)
                    ? $inputRows[$index]
                    : [];
                $rules["{$collection}.{$index}.file"] = [
                    'nullable',
                    'file',
                    'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
                    'max:10240',
                ];
            }

            $request->merge([$collection => $newRows]);
        }

        return $rules;
    }

    private function storeEntryLotDocuments(
        WarehouseEntry $entry,
        array $lotMap,
        array $documentData,
        array $documentFiles
    ): void {
        foreach ($documentData as $index => $document) {
            $file = $documentFiles[$index]['file'] ?? null;
            $mapKey = ($document['item_index'] ?? '') . ':' . ($document['lot_key'] ?? '');
            $lot = $lotMap[$mapKey] ?? null;

            if (! $file instanceof UploadedFile || ! $lot || (int) $lot->warehouseEntryItem->warehouse_entry_id !== (int) $entry->id) {
                throw ValidationException::withMessages([
                    "warehouse_entry_lot_documents.$index.lot_key" => 'El lote seleccionado no existe en este ingreso.',
                ]);
            }

            $storedPath = $file->store("warehouse_entries/{$entry->id}/documents/lots/{$lot->id}", 'public');
            WarehouseEntryItemLotDocument::create([
                'warehouse_entry_id' => $entry->id,
                'warehouse_entry_item_id' => $lot->warehouse_entry_item_id,
                'warehouse_entry_item_lot_id' => $lot->id,
                'document_type' => $document['type'],
                'description' => $this->upperOrNull($document['description'] ?? null),
                'file_path' => $storedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
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

    private function ensureEntryLotDocument(WarehouseEntry $entry, WarehouseEntryItemLotDocument $lotDocument): void
    {
        abort_unless(
            (int) $lotDocument->warehouse_entry_id === (int) $entry->id
            && $lotDocument->status === 'ACTIVE',
            404
        );
    }
}
