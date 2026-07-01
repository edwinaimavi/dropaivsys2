<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Presentation;
use App\Models\Supplier;
use App\Models\SupplierPurchaseOrder;
use App\Models\SupplierPurchaseOrderItem;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Models\WarehouseEntry;
use App\Services\WarehouseKardexService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class WarehouseEntryController extends Controller
{
    private const STATUS_REGISTERED = 'registered';
    private const STATUS_CANCELLED = 'cancelled';

    public function __construct()
    {
        $this->middleware('can:admin.warehouse-entries.index')->only(['index', 'list', 'generateNumber']);
        $this->middleware('can:admin.warehouse-entries.load-items')->only(['loadSupplierPurchaseOrderItems']);
        $this->middleware('can:admin.warehouse-entries.store')->only(['store']);
        $this->middleware('can:admin.warehouse-entries.update')->only(['update']);
        $this->middleware('can:admin.warehouse-entries.destroy')->only(['destroy']);
        $this->middleware('can:admin.warehouse-entries.show')->only(['show']);
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
                $entry->created_at?->format('d/m/Y H:i') ?? '-')
            ->addColumn('acciones', fn (WarehouseEntry $entry) =>
                view('admin.warehouse-entries.partials.acciones', compact('entry'))->render())
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
            'documents',
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

    public function destroy(WarehouseEntry $warehouseEntry, WarehouseKardexService $kardexService)
    {
        try {
            DB::transaction(function () use ($warehouseEntry, $kardexService) {
                $kardexService->reverseWarehouseEntry($warehouseEntry, 'Ingreso de almacen anulado');

                $warehouseEntry->update([
                    'status' => self::STATUS_CANCELLED,
                    'updated_by' => Auth::id(),
                ]);
                $warehouseEntry->delete();
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
        ]);

        try {
            return DB::transaction(function () use ($validated, $entry) {
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

                return response()->json([
                    'status' => 'success',
                    'message' => $entry->wasRecentlyCreated
                        ? 'Ingreso de almacen registrado correctamente.'
                        : 'Ingreso de almacen actualizado correctamente.',
                    'data' => $entry->fresh(['items']),
                ], $entry->wasRecentlyCreated ? 201 : 200);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
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

        foreach ($items as $index => $item) {
            $orderItemId = $item['supplier_purchase_order_item_id'] ?? null;

            if (!$orderItemId || !$orderItems->has($orderItemId)) {
                continue;
            }

            $orderedQuantity = round((float) $orderItems->get($orderItemId)->quantity, 2);
            $pending = max(round($orderedQuantity - (float) ($received[$orderItemId] ?? 0), 2), 0);
            $quantity = round((float) $item['quantity'], 2);

            if ($quantity > $pending) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => 'La cantidad ingresada no puede superar el pendiente.',
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
            ->whereIn('items.supplier_purchase_order_item_id', $orderItemIds)
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
}
