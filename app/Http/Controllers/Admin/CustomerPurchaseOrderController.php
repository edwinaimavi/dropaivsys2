<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerBranch;
use App\Models\CustomerPurchaseOrder;
use App\Models\Presentation;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CustomerPurchaseOrderController extends Controller
{
    public function index()
    {
        $companies = Company::query()
            ->where('status', true)
            ->orderBy('business_name')
            ->get();

        $customers = Customer::query()
            ->where('status', true)
            ->orderBy('business_name')
            ->orderBy('full_name')
            ->get();

        $quotes = Quote::query()
            ->with('customer:id,business_name,full_name,first_name,last_name')
            ->orderByDesc('created_at')
            ->get();

        $currencies = Currency::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $units = Unit::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $presentations = Presentation::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $brands = Brand::query()
            ->where('status', 'ACTIVE')
            ->orderBy('description')
            ->get();

        $articles = Article::query()
            ->where('status', 'ACTIVE')
            ->orderBy('billing_name')
            ->get([
                'id',
                'code',
                'billing_name',
                'unit_id',
                'presentation_id',
                'brand_id',
            ]);

        return view('admin.customer-purchase-orders.index', compact(
            'companies',
            'customers',
            'quotes',
            'currencies',
            'units',
            'presentations',
            'brands',
            'articles'
        ));
    }

    public function list()
    {
        $orders = CustomerPurchaseOrder::query()
            ->with([
                'quote:id,quote_number',
                'company:id,business_name,trade_name',
                'customer:id,business_name,full_name,first_name,last_name',
                'currency:id,code,symbol,description',
            ])
            ->orderByDesc('id');

        return DataTables::of($orders)
            ->addIndexColumn()
            ->addColumn('quote', function (CustomerPurchaseOrder $order) {
                return $order->quote?->quote_number ?? '-';
            })
            ->addColumn('company', function (CustomerPurchaseOrder $order) {
                return $order->company?->trade_name
                    ?? $order->company?->business_name
                    ?? '-';
            })
            ->addColumn('customer', function (CustomerPurchaseOrder $order) {
                return $order->customer?->business_name
                    ?? $order->customer?->full_name
                    ?? trim(($order->customer?->first_name ?? '') . ' ' . ($order->customer?->last_name ?? ''))
                    ?: '-';
            })
            ->addColumn('currency', function (CustomerPurchaseOrder $order) {
                return $order->currency?->code
                    ?? $order->currency?->description
                    ?? '-';
            })
            ->editColumn('grand_total', function (CustomerPurchaseOrder $order) {
                $symbol = $order->currency?->symbol ?? '';

                return trim($symbol . ' ' . number_format((float) $order->grand_total, 2));
            })
            ->editColumn('status', function (CustomerPurchaseOrder $order) {
                $statuses = [
                    'draft' => ['Borrador', 'secondary'],
                    'approved' => ['Aprobada', 'primary'],
                    'cancelled' => ['Anulada', 'danger'],
                    'delivered' => ['Entregada', 'success'],
                    'invoiced' => ['Facturada', 'info'],
                ];

                [$label, $color] = $statuses[$order->status]
                    ?? [$order->status, 'secondary'];

                return '<span class="badge badge-' . $color . ' px-2 py-1">'
                    . e($label)
                    . '</span>';
            })
            ->editColumn('created_at', function (CustomerPurchaseOrder $order) {
                return $order->created_at?->format('d/m/Y H:i') ?? '-';
            })
            ->addColumn('acciones', function (CustomerPurchaseOrder $order) {
                return view(
                    'admin.customer-purchase-orders.partials.acciones',
                    compact('order')
                )->render();
            })
            ->rawColumns(['status', 'acciones'])
            ->make(true);
    }

    public function generateCode()
    {
        return response()->json([
            'code' => $this->nextCode(),
        ]);
    }

    public function customerBranches(string $customerId)
    {
        $branches = CustomerBranch::query()
            ->where('customer_id', $customerId)
            ->where('status', true)
            ->orderByDesc('is_main')
            ->orderBy('branch_name')
            ->get([
                'id',
                'branch_name',
                'address',
                'reference',
                'payment_condition',
                'phone',
                'email',
                'is_main',
            ]);

        return response()->json([
            'branches' => $branches,
        ]);
    }

    public function quoteItems(Quote $quote)
    {
        $quote->load([
            'items.article',
            'items.unit',
            'items.presentation',
            'items.brand',
        ]);

        $affectIgv = (bool) $quote->affect_igv;

        $items = $quote->items->map(function (QuoteItem $item) use ($affectIgv) {
            $quantity = round((float) $item->quantity, 2);
            $unitPrice = round((float) $item->unit_price, 2);
            $subtotal = round($quantity * $unitPrice, 2);
            $taxAmount = $affectIgv ? round($subtotal * 0.18, 2) : 0;

            return [
                'quote_item_id' => $item->id,
                'market_study_item_id' => $item->market_study_item_id,
                'article_id' => $item->article_id,
                'article_code' => $item->article_code
                    ?? $item->article?->code,
                'billing_name_snapshot' => $item->billing_name_snapshot
                    ?? $item->article?->billing_name,
                'note' => $item->note,
                'unit_id' => $item->unit_id,
                'presentation_id' => $item->presentation_id,
                'brand_id' => $item->brand_id,
                'origin' => $item->origin,
                'expiration_date' => $item->expiration_date
                    ? (string) $item->expiration_date
                    : null,
                'cost_type' => $item->cost_type,
                'quoted_quantity' => $quantity,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'line_total' => round($subtotal + $taxAmount, 2),
            ];
        })->values();

        return response()->json([
            'quote_id' => $quote->id,
            'customer_id' => $quote->customer_id,
            'customer_branch_id' => null,
            'company_id' => $quote->company_id,
            'currency_id' => $quote->currency_id,
            'billing_type' => $quote->billing_type,
            'affect_igv' => $affectIgv,
            'items' => $items,
        ]);
    }

    public function store(Request $request)
    {
        return $this->saveOrder($request);
    }

    public function show(CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $customerPurchaseOrder->load([
            'company',
            'quote',
            'customer',
            'customerBranch',
            'currency',
            'creator',
            'updater',
            'documents',
            'items.quoteItem',
            'items.marketStudyItem',
            'items.article',
            'items.unit',
            'items.presentation',
            'items.brand',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $customerPurchaseOrder,
        ]);
    }

    public function edit(CustomerPurchaseOrder $customerPurchaseOrder)
    {
        return $this->show($customerPurchaseOrder);
    }

    public function update(
        Request $request,
        CustomerPurchaseOrder $customerPurchaseOrder
    ) {
        return $this->saveOrder($request, $customerPurchaseOrder);
    }

    public function destroy(CustomerPurchaseOrder $customerPurchaseOrder)
    {
        $customerPurchaseOrder->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Orden de compra de cliente eliminada correctamente.',
        ]);
    }

    private function saveOrder(
        Request $request,
        ?CustomerPurchaseOrder $order = null
    ) {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'quote_id' => [
                'nullable',
                Rule::exists('quotes', 'id')
                    ->where('customer_id', $request->input('customer_id'))
                    ->whereNull('deleted_at'),
            ],
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_branch_id' => [
                'nullable',
                Rule::exists('customer_branches', 'id')
                    ->where('customer_id', $request->input('customer_id')),
            ],
            'order_type' => ['required', Rule::in(['articles', 'services'])],
            'purchase_order_number' => ['nullable', 'string', 'max:100'],
            'currency_id' => ['required', 'exists:currencies,id'],
            'notification_date' => ['nullable', 'date'],
            'delivery_start_date' => ['nullable', 'date'],
            'delivery_end_date' => ['nullable', 'date', 'after_or_equal:delivery_start_date'],
            'siaf_file_number' => ['nullable', 'string', 'max:100'],
            'acquisition_chart_number' => ['nullable', 'string', 'max:100'],
            'process_type' => ['nullable', 'string', 'max:100'],
            'billing_type' => ['required', Rule::in(['local', 'export'])],
            'affect_igv' => ['required', 'boolean'],
            'observations' => ['nullable', 'string'],
            'status' => [
                'nullable',
                Rule::in(['draft', 'approved', 'cancelled', 'delivered', 'invoiced']),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quote_item_id' => [
                'nullable',
                Rule::exists('quote_items', 'id')
                    ->when(
                        $request->filled('quote_id'),
                        fn ($query) => $query->where(
                            'quote_id',
                            $request->input('quote_id')
                        )
                    ),
            ],
            'items.*.market_study_item_id' => ['nullable', 'exists:market_study_items,id'],
            'items.*.article_id' => ['required', 'exists:articles,id'],
            'items.*.article_code' => ['nullable', 'string', 'max:255'],
            'items.*.billing_name_snapshot' => ['required', 'string', 'max:255'],
            'items.*.note' => ['nullable', 'string'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
            'items.*.presentation_id' => ['nullable', 'exists:presentations,id'],
            'items.*.brand_id' => ['nullable', 'exists:brands,id'],
            'items.*.origin' => ['nullable', 'string', 'max:100'],
            'items.*.expiration_date' => ['nullable', 'date'],
            'items.*.cost_type' => ['nullable', 'string', 'max:100'],
            'items.*.quoted_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.line_total' => ['required', 'numeric', 'min:0'],
            'items.*.status' => ['nullable', 'string', 'max:30'],
        ]);

        try {
            return DB::transaction(function () use ($validated, $order) {
                $affectIgv = (bool) ($validated['affect_igv'] ?? false);
                $preparedItems = $this->prepareItems($validated['items'], $affectIgv);
                $totals = $this->calculateTotals($preparedItems, $affectIgv);

                $orderData = [
                    'company_id' => $validated['company_id'],
                    'quote_id' => $validated['quote_id'] ?? null,
                    'customer_id' => $validated['customer_id'],
                    'customer_branch_id' => $validated['customer_branch_id'] ?? null,
                    'order_type' => $validated['order_type'],
                    'purchase_order_number' => $this->upperOrNull(
                        $validated['purchase_order_number'] ?? null
                    ),
                    'currency_id' => $validated['currency_id'],
                    'notification_date' => $validated['notification_date'] ?? null,
                    'delivery_start_date' => $validated['delivery_start_date'] ?? null,
                    'delivery_end_date' => $validated['delivery_end_date'] ?? null,
                    'siaf_file_number' => $this->upperOrNull(
                        $validated['siaf_file_number'] ?? null
                    ),
                    'acquisition_chart_number' => $this->upperOrNull(
                        $validated['acquisition_chart_number'] ?? null
                    ),
                    'process_type' => $this->upperOrNull(
                        $validated['process_type'] ?? null
                    ),
                    'billing_type' => $validated['billing_type'] ?? 'local',
                    'affect_igv' => $affectIgv,
                    'observations' => $this->upperOrNull(
                        $validated['observations'] ?? null
                    ),
                    'subtotal_exonerated' => $totals['subtotal_exonerated'],
                    'subtotal_taxed' => $totals['subtotal_taxed'],
                    'igv' => $totals['igv'],
                    'grand_total' => $totals['grand_total'],
                    'status' => $validated['status'] ?? 'draft',
                    'updated_by' => Auth::id(),
                ];

                if ($order) {
                    $order->update($orderData);
                    $order->items()->delete();
                } else {
                    $orderData['code'] = $this->nextCode();
                    $orderData['created_by'] = Auth::id();
                    $order = CustomerPurchaseOrder::create($orderData);
                }

                foreach ($preparedItems as $item) {
                    $order->items()->create($item);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => $order->wasRecentlyCreated
                        ? 'Orden de compra de cliente registrada correctamente.'
                        : 'Orden de compra de cliente actualizada correctamente.',
                    'data' => $order->fresh(['items']),
                ], $order->wasRecentlyCreated ? 201 : 200);
            });
        } catch (\Throwable $e) {
            Log::error('Error saving customer purchase order: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo guardar la orden de compra de cliente.',
            ], 500);
        }
    }

    private function prepareItems(array $items, bool $affectIgv): array
    {
        $quoteItems = QuoteItem::query()
            ->with('article')
            ->whereIn(
                'id',
                collect($items)->pluck('quote_item_id')->filter()->all()
            )
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (array $item) use ($quoteItems, $affectIgv) {
                $quoteItem = isset($item['quote_item_id'])
                    ? $quoteItems->get($item['quote_item_id'])
                    : null;

                $quantity = round((float) $item['quantity'], 2);
                $unitPrice = round((float) $item['unit_price'], 2);
                $subtotal = round($quantity * $unitPrice, 2);
                $taxAmount = $affectIgv ? round($subtotal * 0.18, 2) : 0;

                return [
                    'quote_item_id' => $item['quote_item_id'] ?? null,
                    'market_study_item_id' => $item['market_study_item_id']
                        ?? $quoteItem?->market_study_item_id,
                    'article_id' => $item['article_id'] ?? $quoteItem?->article_id,
                    'article_code' => $this->upperOrNull(
                        $item['article_code'] ?? $quoteItem?->article_code
                    ),
                    'billing_name_snapshot' => $this->upperOrNull(
                        $item['billing_name_snapshot']
                            ?? $quoteItem?->billing_name_snapshot
                            ?? $quoteItem?->article?->billing_name
                            ?? 'SERVICIO'
                    ),
                    'note' => $this->upperOrNull($item['note'] ?? $quoteItem?->note),
                    'unit_id' => $item['unit_id'] ?? $quoteItem?->unit_id,
                    'presentation_id' => $item['presentation_id']
                        ?? $quoteItem?->presentation_id,
                    'brand_id' => $item['brand_id'] ?? $quoteItem?->brand_id,
                    'origin' => $this->upperOrNull(
                        $item['origin'] ?? $quoteItem?->origin
                    ),
                    'expiration_date' => $item['expiration_date']
                        ?? $quoteItem?->expiration_date,
                    'cost_type' => $this->upperOrNull(
                        $item['cost_type'] ?? $quoteItem?->cost_type
                    ),
                    'quoted_quantity' => round(
                        (float) ($item['quoted_quantity'] ?? $quoteItem?->quantity ?? 0),
                        2
                    ),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'line_total' => round($subtotal + $taxAmount, 2),
                    'status' => $item['status'] ?? 'active',
                ];
            })
            ->all();
    }

    private function calculateTotals(array $items, bool $affectIgv): array
    {
        $subtotal = round((float) collect($items)->sum('subtotal'), 2);
        $igv = round((float) collect($items)->sum('tax_amount'), 2);

        return [
            'subtotal_exonerated' => $affectIgv ? 0 : $subtotal,
            'subtotal_taxed' => $affectIgv ? $subtotal : 0,
            'igv' => $igv,
            'grand_total' => round($subtotal + $igv, 2),
        ];
    }

    private function nextCode(): string
    {
        $lastNumber = CustomerPurchaseOrder::withTrashed()
            ->where('code', 'like', 'P%')
            ->pluck('code')
            ->map(function (?string $code) {
                return preg_match('/^P(\d{5,})$/', (string) $code, $matches)
                    ? (int) $matches[1]
                    : 0;
            })
            ->max() ?? 0;

        do {
            $lastNumber++;
            $code = 'P' . str_pad($lastNumber, 5, '0', STR_PAD_LEFT);
        } while (
            CustomerPurchaseOrder::withTrashed()
                ->where('code', $code)
                ->exists()
        );

        return $code;
    }

    private function upperOrNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? mb_strtoupper($value) : null;
    }
}
